<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\University;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (blank($user->password)) {
                $user->password = \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(48));
            }
        });

        static::created(function (self $user): void {
            $roleName = $user->account_type ?: 'etudiant';
            if ($roleName === 'researcher' && ! Role::query()->where('name', $roleName)->where('guard_name', 'web')->exists()) {
                $roleName = 'chercheur';
            }
            $user->assignRole($roleName);

            if ($user->account_type !== 'etudiant' && Auth::check()) {
                app(\App\Services\AccountInvitationService::class)->invite($user, Auth::user());
            }
        });

        static::saving(function (self $user): void {
            $actor = Auth::user();

            if (! $actor) {
                return;
            }

            if ($user->exists && $actor->id === $user->id && $user->isDirty(['account_type', 'status', 'university_id'])) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Vous ne pouvez pas modifier vos propres privilèges.');
            }

            if ($user->isDirty('account_type') && in_array($user->account_type, ['super_admin', 'admin', 'directeur_general'], true)) {
                $isSuperAdmin = \Illuminate\Support\Facades\DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_id', $actor->id)
                    ->where('model_has_roles.model_type', self::class)
                    ->where('roles.name', 'super_admin')
                    ->exists();

                if (! $isSuperAdmin) {
                    throw new \Illuminate\Auth\Access\AuthorizationException('Seul un super administrateur peut attribuer ce rôle.');
                }
            }
        });

        static::saved(function (self $user): void {
            if ($user->wasChanged('account_type')) {
                $user->syncRoles([$user->account_type]);
            }

            if ($user->account_type === 'universite' && $user->university_id) {
                \Illuminate\Support\Facades\DB::table('university_users')->updateOrInsert(
                    ['user_id' => $user->id],
                    ['id' => (string) \Illuminate\Support\Str::uuid(), 'university_id' => $user->university_id, 'role' => 'responsable', 'created_at' => now(), 'updated_at' => now()],
                );
            } elseif ($user->wasChanged(['account_type', 'university_id'])) {
                \Illuminate\Support\Facades\DB::table('university_users')->where('user_id', $user->id)->delete();
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->email_verified_at !== null
            && ! in_array($this->status, ['suspended', 'disabled', 'inactive'], true)
            && in_array($this->account_type, [
                'super_admin', 'admin', 'directeur_general', 'gestionnaire',
                'agent_dossier', 'agent_finance', 'agent_recherche', 'agent_communication',
            ], true);
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->forceFill(['app_authentication_secret' => $secret])->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->forceFill(['app_authentication_recovery_codes' => $codes])->save();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'account_type',
        'status',
        'university_id',
        'evaluation_expertise',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'evaluation_expertise' => 'array',
        ];
    }

    public function university()
    {
        return $this->belongsTo(University::class);
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'applicant_id');
    }
}
