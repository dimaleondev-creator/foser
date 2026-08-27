<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Reader;

class UniversityStudentImportService
{
    private const REQUIRED_COLUMNS = ['inee', 'name', 'email'];

    public function import(UploadedFile $file, string $universityId, int $importedBy): array
    {
        $rows = $this->readRows($file);
        $report = ['imported' => 0, 'updated' => 0, 'rejected' => 0, 'errors' => 0, 'lines' => []];
        $seenEmails = [];
        $seenInees = [];

        foreach ($rows as $line => $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $inee = trim((string) ($row['inee'] ?? ''));
            $error = $this->validateRow($row, $email, $inee, $seenEmails, $seenInees);
            if ($error) {
                $report['errors']++;
                $report['lines'][] = ['line' => $line, 'status' => 'error', 'message' => $error];
                continue;
            }
            $seenEmails[$email] = true;
            $seenInees[$inee] = true;

            $existing = User::query()->where('email', $email)->first();
            $existingProfile = $existing
                ? DB::table('student_profiles')->where('user_id', $existing->id)->first()
                : null;
            $ineeProfile = DB::table('student_profiles')->where('inee', $inee)->first();
            if ($ineeProfile && (! $existingProfile || $ineeProfile->user_id !== $existingProfile->user_id)) {
                $report['rejected']++;
                $report['lines'][] = ['line' => $line, 'status' => 'rejected', 'message' => 'Cet identifiant étudiant existe déjà.'];
                continue;
            }
            if ($existingProfile && $existingProfile->university_id !== $universityId) {
                $report['rejected']++;
                $report['lines'][] = ['line' => $line, 'status' => 'rejected', 'message' => 'Cet étudiant appartient déjà à un autre établissement.'];
                continue;
            }

            if ($existing && $existingProfile) {
                DB::table('student_profiles')->where('user_id', $existing->id)->update($this->profileData($row, $universityId) + ['updated_at' => now()]);
                $report['updated']++;
                $report['lines'][] = ['line' => $line, 'status' => 'updated', 'message' => 'Étudiant mis à jour.'];
                continue;
            }

            if ($existing && ! $existingProfile) {
                $report['rejected']++;
                $report['lines'][] = ['line' => $line, 'status' => 'rejected', 'message' => 'Un compte existe déjà pour cet email.'];
                continue;
            }

            $user = User::create(['name' => trim((string) $row['name']), 'email' => $email, 'password' => Hash::make(Str::random(32))]);
            $user->assignRole('etudiant');
            DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, ...$this->profileData($row, $universityId), 'created_at' => now(), 'updated_at' => now()]);
            $report['imported']++;
            $report['lines'][] = ['line' => $line, 'status' => 'imported', 'message' => 'Étudiant importé.'];
        }

        DB::table('university_imports')->insert([
            'id' => (string) Str::uuid(), 'university_id' => $universityId, 'imported_by' => $importedBy,
            'filename' => $file->getClientOriginalName(), 'status' => 'completed',
            'imported_count' => $report['imported'], 'updated_count' => $report['updated'],
            'rejected_count' => $report['rejected'], 'error_count' => $report['errors'],
            'report' => json_encode($report), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $report;
    }

    public function preview(UploadedFile $file): array
    {
        return array_slice($this->readRows($file), 1, 10, true);
    }

    private function readRows(UploadedFile $file): array
    {
        $reader = new Reader();
        $reader->open($file->getRealPath());
        $rows = [];
        $headers = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $line = 1;
            foreach ($sheet->getRowIterator() as $row) {
                $values = array_map(static fn (mixed $value): string => trim((string) $value), $row->toArray());
                if ($line === 1) {
                    $headers = array_map(static fn (string $value): string => strtolower(trim($value)), $values);
                    $missing = array_diff(self::REQUIRED_COLUMNS, $headers);
                    if ($missing) {
                        $reader->close();
                        throw ValidationException::withMessages(['file' => 'Colonnes obligatoires manquantes : '.implode(', ', $missing).'.']);
                    }
                } else {
                    $rows[$line] = array_combine($headers, array_pad(array_slice($values, 0, count($headers)), count($headers), '')) ?: [];
                }
                $line++;
            }
            break;
        }
        $reader->close();
        if ($headers === []) {
            throw ValidationException::withMessages(['file' => 'Le fichier ne contient pas de ligne d’en-tête.']);
        }
        return $rows;
    }

    private function validateRow(array $row, string $email, string $inee, array $seenEmails, array $seenInees): ?string
    {
        if ($inee === '' || trim((string) ($row['name'] ?? '')) === '') {
            return 'Nom ou code INEE manquant.';
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Adresse email invalide.';
        }
        if (isset($seenEmails[$email]) || isset($seenInees[$inee])) {
            return 'Doublon détecté dans le fichier.';
        }
        return null;
    }

    private function profileData(array $row, string $universityId): array
    {
        return ['university_id' => $universityId, 'inee' => trim((string) $row['inee']), 'phone' => $row['phone'] ?? null, 'nationality' => $row['nationality'] ?? null, 'address' => $row['address'] ?? null, 'program' => $row['program'] ?? null, 'academic_year' => $row['academic_year'] ?? null, 'validation_status' => 'pending'];
    }
}
