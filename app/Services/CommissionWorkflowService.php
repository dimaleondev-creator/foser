<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\CommissionApplication;
use App\Models\CommissionDecision;
use App\Models\CommissionMember;
use App\Models\CommissionMinutes;
use App\Models\CommissionVote;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommissionWorkflowService
{
    private const MEMBER_ROLES = ['president', 'secretary', 'rapporteur', 'member'];

    private const VOTES = ['favorable', 'defavorable', 'abstention'];

    private const DECISIONS = ['accepted', 'rejected', 'adjourned', 'complement_requested'];

    public function validateContext(array $data): void
    {
        abort_unless((int) ($data['quorum_percentage'] ?? 0) >= 1 && (int) ($data['quorum_percentage'] ?? 0) <= 100, 422);
        $call = DB::table('calls')->where('id', $data['call_id'])->first();
        abort_unless($call && $call->program_id === $data['program_id'], 422, 'L’appel doit appartenir au programme sélectionné.');
    }

    public function created(User $actor, Commission $commission): void
    {
        app(AuditLogger::class)->record('commission.created', Commission::class, $commission->id, [], [
            'name' => $commission->name,
            'call_id' => $commission->call_id,
            'program_id' => $commission->program_id,
            'status' => $commission->status,
            'performed_by' => $actor->id,
        ]);
    }

    public function updated(User $actor, Commission $commission, array $before): void
    {
        app(AuditLogger::class)->record('commission.updated', Commission::class, $commission->id, $before, [
            'name' => $commission->name,
            'call_id' => $commission->call_id,
            'program_id' => $commission->program_id,
            'scheduled_at' => $commission->scheduled_at?->toIso8601String(),
            'venue' => $commission->venue,
            'performed_by' => $actor->id,
        ]);
    }

    public function addMember(User $actor, Commission $commission, int $userId, string $role): CommissionMember
    {
        $this->authorize($actor, 'manageMembers', $commission);
        abort_unless(in_array($role, self::MEMBER_ROLES, true), 422);
        abort_unless(in_array($commission->status, ['draft', 'scheduled'], true), 422, 'La liste des membres est verrouillée après le début de la réunion.');
        abort_unless(! $commission->convocation_sent_at, 422, 'Les membres ne peuvent plus être modifiés après l’émission des convocations.');

        return DB::transaction(function () use ($actor, $commission, $userId, $role): CommissionMember {
            $target = User::query()->whereKey($userId)->where('status', 'active')->firstOrFail();
            abort_unless(in_array($target->account_type, [
                'super_admin', 'admin', 'directeur_general', 'gestionnaire', 'agent_dossier',
                'agent_finance', 'agent_recherche', 'evaluateur', 'chercheur', 'researcher',
            ], true), 422, 'Ce type de compte ne peut pas être membre d’une commission.');

            $existing = CommissionMember::query()->where('commission_id', $commission->id)->where('user_id', $target->id)->first();
            if ($existing) {
                throw ValidationException::withMessages(['user_id' => 'Cette personne est déjà membre de la commission.']);
            }

            if (in_array($role, ['president', 'secretary'], true)) {
                $previousMembers = CommissionMember::query()->where('commission_id', $commission->id)->where('role', $role)->get();
                foreach ($previousMembers as $previousMember) {
                    $previousMember->update(['role' => 'member']);
                    $this->audit($actor, $commission, 'commission.member_role_changed', ['member_id' => $previousMember->id, 'role' => $role], ['member_id' => $previousMember->id, 'role' => 'member']);
                }
            }

            $member = CommissionMember::query()->create([
                'commission_id' => $commission->id,
                'user_id' => $target->id,
                'role' => $role,
                'attendance_status' => 'pending',
            ]);
            $this->audit($actor, $commission, 'commission.member_added', [], [
                'member_id' => $member->id,
                'user_id' => $target->id,
                'role' => $role,
            ]);

            return $member;
        });
    }

    public function removeMember(User $actor, Commission $commission, string $memberId): void
    {
        $this->authorize($actor, 'manageMembers', $commission);
        abort_unless(in_array($commission->status, ['draft', 'scheduled'], true), 422);
        abort_unless(! $commission->convocation_sent_at, 422, 'Les membres ne peuvent plus être modifiés après l’émission des convocations.');
        $member = $commission->members()->whereKey($memberId)->firstOrFail();
        abort_unless(! $member->votes()->exists(), 422, 'Un membre ayant voté ne peut pas être retiré.');
        $memberData = ['user_id' => $member->user_id, 'role' => $member->role];
        $member->delete();
        $this->audit($actor, $commission, 'commission.member_removed', $memberData, []);
    }

    public function attachApplication(User $actor, Commission $commission, string $applicationId, ?string $observations = null): CommissionApplication
    {
        $this->authorize($actor, 'manageApplications', $commission);
        abort_unless(in_array($commission->status, ['draft', 'scheduled'], true), 422);
        abort_unless(! $commission->convocation_sent_at, 422, 'Les dossiers ne peuvent plus être modifiés après l’émission des convocations.');

        return DB::transaction(function () use ($actor, $commission, $applicationId, $observations): CommissionApplication {
            $application = DB::table('applications')->where('id', $applicationId)->lockForUpdate()->firstOrFail();
            abort_unless($application->call_id === $commission->call_id && $application->program_id === $commission->program_id, 422, 'La candidature ne correspond pas à l’appel et au programme de la commission.');
            abort_unless(($application->workflow_status ?? null) === 'commission_review', 422, 'La candidature doit avoir terminé le contrôle et les évaluations.');
            abort_unless(DB::table('evaluations')->where('application_id', $applicationId)->exists(), 422, 'La candidature doit avoir au moins une évaluation.');
            abort_unless(! DB::table('evaluations')->where('application_id', $applicationId)->where('status', '!=', 'validated')->exists(), 422, 'Toutes les évaluations doivent être validées.');
            abort_unless(! CommissionApplication::query()->where('application_id', $applicationId)->whereHas('commission', fn ($query) => $query->whereNotIn('status', ['cancelled', 'archived']))->exists(), 409, 'Le dossier est déjà inscrit à une commission active.');

            $item = CommissionApplication::query()->create([
                'commission_id' => $commission->id,
                'application_id' => $applicationId,
                'added_by' => $actor->id,
                'observations' => $observations,
            ]);
            $this->audit($actor, $commission, 'commission.application_added', [], [
                'commission_application_id' => $item->id,
                'application_id' => $applicationId,
                'reference' => $application->reference,
            ]);

            return $item;
        });
    }

    public function schedule(User $actor, Commission $commission): void
    {
        $this->authorize($actor, 'update', $commission);
        abort_unless($commission->status === 'draft', 422);
        abort_unless($commission->members()->where('role', 'president')->exists(), 422, 'Désignez le président de la commission.');
        abort_unless($commission->members()->where('role', 'secretary')->exists(), 422, 'Désignez le secrétaire de la commission.');
        abort_unless($commission->members()->count() >= 2, 422, 'Une commission doit compter au moins deux membres.');
        abort_unless(filled($commission->agenda), 422, 'L’ordre du jour est obligatoire.');
        abort_unless(filled($commission->convocation_text), 422, 'Le texte de convocation est obligatoire.');
        abort_unless($commission->applications()->exists(), 422, 'Inscrivez au moins un dossier à la commission.');

        $commission->update(['status' => 'scheduled']);
        $this->audit($actor, $commission, 'commission.scheduled', ['status' => 'draft'], ['status' => 'scheduled']);
    }

    public function sendConvocations(User $actor, Commission $commission): void
    {
        $this->authorize($actor, 'update', $commission);
        abort_unless($commission->status === 'scheduled', 422);
        abort_unless(filled($commission->agenda) && filled($commission->convocation_text) && $commission->members()->exists() && $commission->applications()->exists(), 422);

        DB::transaction(function () use ($commission): void {
            $commission->update(['convocation_sent_at' => now()]);
            $caseReferences = $commission->applications()->with('application')->get()->pluck('application.reference')->filter()->values()->all();
            foreach ($commission->members()->with('user')->get() as $member) {
                try {
                    app(NotificationService::class)->notify(
                        $member->user,
                        'commission.convocation',
                        [
                            'commission' => $commission->name,
                            'scheduled_at' => $commission->scheduled_at->format('d/m/Y H:i'),
                            'venue' => $commission->venue,
                            'agenda' => $commission->agenda,
                            'convocation' => $commission->convocation_text,
                            'applications' => implode(', ', $caseReferences),
                        ],
                        ['internal'],
                        'commission.convocation:'.$commission->id.':'.$member->user_id,
                    );
                } catch (\Throwable) {
                }
            }
        });
        $this->audit($actor, $commission, 'commission.convocations_sent', [], ['members' => $commission->members()->count()]);
    }

    public function markAttendance(User $actor, Commission $commission, string $memberId, string $status): void
    {
        $this->authorize($actor, 'manageAttendance', $commission);
        abort_unless($commission->status === 'scheduled', 422, 'La présence est verrouillée au démarrage de la séance.');
        abort_unless(in_array($status, ['present', 'absent'], true), 422);
        $member = $commission->members()->whereKey($memberId)->firstOrFail();
        $before = $member->attendance_status;
        $member->update(['attendance_status' => $status, 'attended_at' => $status === 'present' ? now() : null]);
        $this->audit($actor, $commission, 'commission.attendance_recorded', ['member_id' => $member->id, 'attendance_status' => $before], ['member_id' => $member->id, 'attendance_status' => $status]);
    }

    public function quorum(Commission $commission): array
    {
        $members = $commission->members()->count();
        $present = $commission->members()->where('attendance_status', 'present')->count();
        $required = $members > 0 ? (int) ceil($members * $commission->quorum_percentage / 100) : 1;

        return [
            'members' => $members,
            'present' => $present,
            'percentage' => $members > 0 ? round($present * 100 / $members, 1) : 0.0,
            'required' => $required,
            'reached' => $members > 0 && $present >= $required,
        ];
    }

    public function startMeeting(User $actor, Commission $commission): void
    {
        $this->authorize($actor, 'update', $commission);
        abort_unless($commission->status === 'scheduled', 422);
        abort_unless($commission->convocation_sent_at, 422, 'La convocation doit être émise avant l’ouverture de la séance.');
        abort_unless($this->quorum($commission)['reached'], 422, 'Le quorum n’est pas atteint.');

        $commission->update(['status' => 'in_progress', 'started_at' => now()]);
        $this->audit($actor, $commission, 'commission.meeting_started', ['status' => 'scheduled'], ['status' => 'in_progress', 'started_at' => now()->toIso8601String()]);
    }

    public function castVote(User $actor, Commission $commission, string $itemId, string $vote, ?string $comment = null): CommissionVote
    {
        abort_unless(in_array($vote, self::VOTES, true), 422);
        $item = $commission->applications()->whereKey($itemId)->firstOrFail();
        abort_unless($actor->status === 'active', 403);

        return DB::transaction(function () use ($actor, $commission, $item, $vote, $comment): CommissionVote {
            $locked = Commission::query()->whereKey($commission->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'in_progress', 422, 'Le vote est fermé.');
            abort_unless($this->quorum($locked)['reached'], 422, 'Le quorum n’est pas atteint.');
            $member = $locked->members()->where('user_id', $actor->id)->where('attendance_status', 'present')->first();
            abort_unless($member, 403, 'Seul un membre présent peut voter.');
            abort_unless($item->commission_id === $locked->id, 404);
            abort_unless(! CommissionDecision::query()->where('commission_application_id', $item->id)->exists(), 422, 'Le vote est clos pour ce dossier, une décision a été proposée.');
            abort_unless(! CommissionVote::query()->where('commission_application_id', $item->id)->where('commission_member_id', $member->id)->exists(), 409, 'Un vote a déjà été enregistré pour ce membre et ce dossier.');

            $record = CommissionVote::query()->create([
                'commission_id' => $locked->id,
                'commission_application_id' => $item->id,
                'commission_member_id' => $member->id,
                'vote' => $vote,
                'comment' => $comment,
                'voted_at' => now(),
            ]);
            $this->audit($actor, $locked, 'commission.vote_cast', [], [
                'vote_id' => $record->id,
                'commission_application_id' => $item->id,
                'member_id' => $member->id,
                'vote' => $vote,
            ]);

            return $record;
        });
    }

    public function voteTally(CommissionApplication $item): array
    {
        return CommissionVote::query()->where('commission_application_id', $item->id)
            ->select('vote', DB::raw('count(*) as total'))
            ->groupBy('vote')->pluck('total', 'vote')->all();
    }

    public function proposeDecision(User $actor, Commission $commission, string $itemId, string $decision, string $justification): CommissionDecision
    {
        $this->authorize($actor, 'decide', $commission);
        abort_unless(in_array($decision, self::DECISIONS, true), 422);
        abort_unless(filled($justification), 422);
        $item = $commission->applications()->whereKey($itemId)->firstOrFail();

        return DB::transaction(function () use ($actor, $commission, $item, $decision, $justification): CommissionDecision {
            $locked = Commission::query()->whereKey($commission->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'in_progress', 422);
            abort_unless($this->quorum($locked)['reached'], 422, 'Aucune délibération ne peut être enregistrée sans quorum.');
            abort_unless($item->commission_id === $locked->id, 404);
            abort_unless(CommissionVote::query()->where('commission_application_id', $item->id)->exists(), 422, 'Un vote doit être enregistré avant la décision.');
            abort_unless(! CommissionDecision::query()->where('commission_application_id', $item->id)->exists(), 409, 'Une décision existe déjà pour ce dossier.');

            $record = CommissionDecision::query()->create([
                'commission_id' => $locked->id,
                'commission_application_id' => $item->id,
                'decided_by' => $actor->id,
                'decision' => $decision,
                'status' => 'pending_validation',
                'justification' => $justification,
                'decided_at' => now(),
            ]);
            $this->audit($actor, $locked, 'commission.decision_proposed', [], [
                'decision_id' => $record->id,
                'application_id' => $item->application_id,
                'decision' => $decision,
            ]);

            return $record;
        });
    }

    public function validateDecision(User $actor, Commission $commission, string $decisionId): void
    {
        $this->authorize($actor, 'validateDecision', $commission);
        DB::transaction(function () use ($actor, $commission, $decisionId): void {
            $locked = Commission::query()->whereKey($commission->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'in_progress', 422, 'La séance doit être en cours pour valider une décision.');
            $decision = $locked->decisions()->whereKey($decisionId)->lockForUpdate()->firstOrFail();
            abort_unless($decision->status === 'pending_validation', 422);
            abort_unless((int) $decision->decided_by !== (int) $actor->id, 403, 'La personne qui propose la décision ne peut pas la valider.');
            abort_unless($this->quorum($locked)['reached'], 422, 'Le quorum doit rester atteint au moment de la validation.');
            $item = $decision->commissionApplication()->firstOrFail();
            $resultDecision = match ($decision->decision) {
                'accepted' => 'accepted',
                'rejected' => 'rejected',
                'adjourned', 'complement_requested' => 'deferred',
            };
            $reason = $decision->decision === 'complement_requested'
                ? 'Complément demandé. '.$decision->justification
                : $decision->justification;

            app(ApplicationWorkflowService::class)->recordDecision($actor, $item->application_id, $resultDecision, null, $reason);
            $decision->update(['status' => 'validated', 'validated_by' => $actor->id, 'validated_at' => now()]);
            $this->audit($actor, $locked, 'commission.decision_validated', ['status' => 'pending_validation'], [
                'decision_id' => $decision->id,
                'status' => 'validated',
                'validated_by' => $actor->id,
            ]);
        });
    }

    public function saveMinutes(User $actor, Commission $commission, string $summary, string $content, ?string $filePath = null): CommissionMinutes
    {
        $this->authorize($actor, 'manageMinutes', $commission);
        abort_unless($commission->status === 'in_progress', 422);
        abort_unless($commission->decisions()->count() === $commission->applications()->count(), 422, 'Une décision est requise pour chaque dossier avant le procès-verbal.');
        abort_unless(! $commission->decisions()->where('status', '!=', 'validated')->exists(), 422, 'Toutes les décisions doivent être validées avant le procès-verbal.');
        $minutes = $commission->minutes()->first();
        abort_unless(! $minutes || $minutes->status === 'draft', 422, 'Le procès-verbal validé est verrouillé.');
        $documentId = $minutes?->document_id;

        if ($filePath) {
            $previous = $documentId ? Document::query()->find($documentId) : null;
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('local');
            $storedFile = $disk->path($filePath);
            $document = Document::query()->create([
                'uploaded_by' => $actor->id,
                'title' => 'Procès-verbal - '.$commission->name,
                'document_type' => 'commission_minutes',
                'disk' => 'local',
                'path' => $filePath,
                'mime_type' => $disk->mimeType($filePath),
                'size' => is_file($storedFile) ? filesize($storedFile) : null,
                'visibility' => 'private',
                'status' => 'published',
            ]);
            $documentId = $document->id;
            if ($previous) {
                Storage::disk($previous->disk)->delete($previous->path);
                $previous->delete();
            }
        }

        $minutesContent = $this->minutesAnnex($commission, $content);
        $minutes = CommissionMinutes::query()->updateOrCreate(['commission_id' => $commission->id], [
            'document_id' => $documentId,
            'authored_by' => $minutes?->authored_by ?? $actor->id,
            'summary' => $summary,
            'content' => $minutesContent,
            'status' => 'draft',
            'validated_by' => null,
            'validated_at' => null,
        ]);
        $this->audit($actor, $commission, 'commission.minutes_saved', [], [
            'minutes_id' => $minutes->id,
            'document_id' => $documentId,
            'authored_by' => $minutes->authored_by,
        ]);

        return $minutes;
    }

    public function validateMinutes(User $actor, Commission $commission): void
    {
        $this->authorize($actor, 'validateMinutes', $commission);
        abort_unless($commission->status === 'in_progress', 422);
        $minutes = $commission->minutes()->firstOrFail();
        abort_unless($minutes->status === 'draft', 422);
        abort_unless((int) $minutes->authored_by !== (int) $actor->id, 403, 'L’auteur du procès-verbal ne peut pas le valider.');
        abort_unless($commission->decisions()->count() === $commission->applications()->count(), 422, 'Une décision validée est requise pour chaque dossier.');
        abort_unless(! $commission->decisions()->where('status', '!=', 'validated')->exists(), 422, 'Toutes les décisions doivent être validées avant le procès-verbal.');
        $minutes->update(['status' => 'validated', 'validated_by' => $actor->id, 'validated_at' => now()]);
        $this->audit($actor, $commission, 'commission.minutes_validated', ['status' => 'draft'], ['status' => 'validated', 'validated_by' => $actor->id]);
    }

    public function completeMeeting(User $actor, Commission $commission): void
    {
        $this->authorize($actor, 'update', $commission);
        abort_unless($commission->status === 'in_progress', 422);
        abort_unless($this->quorum($commission)['reached'], 422, 'Le quorum n’est pas atteint.');
        abort_unless($commission->applications()->exists(), 422, 'Aucun dossier n’est inscrit à la commission.');
        abort_unless($commission->decisions()->count() === $commission->applications()->count(), 422, 'Une décision validée est requise pour chaque dossier.');
        abort_unless(! $commission->decisions()->where('status', '!=', 'validated')->exists(), 422);
        abort_unless($commission->minutes()->where('status', 'validated')->exists(), 422, 'Le procès-verbal doit être validé avant la clôture.');

        $commission->update(['status' => 'completed', 'completed_at' => now()]);
        $this->audit($actor, $commission, 'commission.meeting_completed', ['status' => 'in_progress'], ['status' => 'completed']);
    }

    public function cancel(User $actor, Commission $commission): void
    {
        $this->authorize($actor, 'update', $commission);
        abort_unless(in_array($commission->status, ['draft', 'scheduled'], true), 422);
        abort_unless(! $commission->votes()->exists(), 422, 'Une séance ayant enregistré des votes ne peut pas être annulée.');
        $commission->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->audit($actor, $commission, 'commission.cancelled', [], ['status' => 'cancelled']);
    }

    public function archive(User $actor, Commission $commission): void
    {
        $this->authorize($actor, 'update', $commission);
        abort_unless(in_array($commission->status, ['completed', 'cancelled'], true), 422);
        $commission->update(['status' => 'archived', 'archived_at' => now()]);
        $this->audit($actor, $commission, 'commission.archived', [], ['status' => 'archived']);
    }

    public function assertCanViewApplication(User $user, Commission $commission, string $applicationId): CommissionApplication
    {
        Gate::forUser($user)->authorize('view', $commission);
        return $commission->applications()->where('application_id', $applicationId)->firstOrFail();
    }

    private function authorize(User $actor, string $ability, Commission $commission): void
    {
        abort_unless($actor->status === 'active', 403);
        Gate::forUser($actor)->authorize($ability, $commission);
    }

    private function audit(User $actor, Commission $commission, string $event, array $old, array $new): void
    {
        app(AuditLogger::class)->record($event, Commission::class, $commission->id, $old, ['commission_id' => $commission->id, ...$new]);
    }

    private function minutesAnnex(Commission $commission, string $content): string
    {
        $lines = [
            trim($content),
            '',
            'COMPTE RENDU DE SÉANCE',
            'Commission : '.$commission->name,
            'Date : '.$commission->scheduled_at->format('d/m/Y H:i'),
            'Lieu : '.($commission->venue ?: 'Non renseigné'),
            'Ordre du jour : '.($commission->agenda ?: 'Non renseigné'),
            '',
            'PARTICIPANTS',
        ];
        foreach ($commission->members()->with('user')->orderBy('role')->get() as $member) {
            $lines[] = $member->user->name.' · '.$member->role.' · '.$member->attendance_status;
        }
        $lines[] = '';
        $lines[] = 'DÉLIBÉRATIONS ET VOTES';
        foreach ($commission->applications()->with(['application', 'decision'])->get() as $item) {
            $tally = $this->voteTally($item);
            $lines[] = $item->application->reference.' · décision : '.($item->decision?->decision ?? 'Non renseignée');
            $lines[] = 'Favorable : '.($tally['favorable'] ?? 0).'; défavorable : '.($tally['defavorable'] ?? 0).'; abstention : '.($tally['abstention'] ?? 0);
            if ($item->decision?->justification) {
                $lines[] = 'Justification : '.$item->decision->justification;
            }
        }

        return implode("\n", $lines);
    }
}