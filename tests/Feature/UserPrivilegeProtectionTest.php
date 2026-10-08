<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPrivilegeProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_user_cannot_change_their_own_account_type(): void
    {
        $user = User::factory()->create(['account_type' => 'agent_dossier']);
        $this->actingAs($user);
        $user->account_type = 'etudiant';

        $this->expectException(AuthorizationException::class);
        $user->save();
    }

    public function test_non_super_admin_cannot_assign_an_administrative_role(): void
    {
        $actor = User::factory()->create(['account_type' => 'agent_dossier']);
        $target = User::factory()->create(['account_type' => 'etudiant']);
        $this->actingAs($actor);
        $target->account_type = 'admin';

        $this->expectException(AuthorizationException::class);
        $target->save();
    }

    public function test_non_super_admin_cannot_create_an_account_with_an_administrative_role(): void
    {
        $actor = User::factory()->create(['account_type' => 'admin']);
        $actor->assignRole('admin');
        $this->actingAs($actor);

        $this->expectException(AuthorizationException::class);
        User::create([
            'name' => 'Admin créé sans autorisation',
            'email' => 'forged-admin@example.test',
            'password' => 'Strong-password-123',
            'account_type' => 'admin',
            'status' => 'active',
        ]);
    }
}