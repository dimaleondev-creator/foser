<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsurePermission;
use App\Models\User;
use App\Policies\ApplicationPolicy;
use App\Policies\ResearchPolicy;
use Filament\Panel;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    public function test_verified_user_with_an_administrative_permission_can_access_filament(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->email_verified_at = now();
        $user->status = 'active';
        $user->shouldReceive('hasAnyPermission')->once()->andReturnTrue();

        $this->assertTrue($user->canAccessPanel(Panel::make()->id('admin')));
    }
    public function test_verified_student_without_an_administrative_permission_is_denied_filament(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->email_verified_at = now();
        $user->status = 'active';
        $user->shouldReceive('hasAnyPermission')->once()->andReturnFalse();

        $this->assertFalse($user->canAccessPanel(Mockery::mock(Panel::class)));
    }

    public function test_unverified_user_with_a_permission_cannot_access_filament(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->email_verified_at = null;
        $user->status = 'active';
        $this->assertFalse($user->canAccessPanel(Mockery::mock(Panel::class)));
    }

    public function test_application_policy_uses_granular_permissions(): void
    {
        $student = Mockery::mock(User::class);
        $student->shouldReceive('can')->with('applications.submit')->andReturnTrue();
        $student->shouldReceive('can')->with('applications.validate')->andReturnFalse();

        $policy = new ApplicationPolicy();

        $this->assertTrue($policy->submit($student, new \stdClass()));
        $this->assertFalse($policy->validate($student, new \stdClass()));
    }

    public function test_permission_middleware_returns_forbidden_without_permission(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->with('finance.manage')->andReturnFalse();

        $request = Request::create('/finance', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        (new EnsurePermission())->handle($request, fn ($passedRequest) => response('ok'), 'finance.manage');
    }

    public function test_research_policy_separates_view_manage_and_evaluate_permissions(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->with('research.view')->andReturnTrue();
        $user->shouldReceive('can')->with('research.manage')->andReturnFalse();
        $user->shouldReceive('can')->with('research.evaluate')->andReturnFalse();

        $policy = new ResearchPolicy();

        $this->assertTrue($policy->view($user));
        $this->assertFalse($policy->manage($user));
        $this->assertFalse($policy->evaluate($user));
    }
}
