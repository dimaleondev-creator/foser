<?php

namespace Tests\Feature;

use App\Enums\StudentApplicationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_registration_page_is_available(): void
    {
        $this->get('/student/register')->assertOk()->assertSee('Créer votre compte')
            ->assertSee('Pays ou lieu de naissance')->assertSee('name="birth_place"', false)->assertSee('maxlength="120" required', false);
    }

    public function test_student_login_page_is_available(): void
    {
        $this->get('/student/login')->assertOk()->assertSee('Se connecter');
    }

    public function test_student_dashboard_requires_student_authentication(): void
    {
        $this->get('/student')->assertRedirect('/student/login');
    }

    public function test_student_workflow_exposes_the_required_statuses(): void
    {
        $this->assertSame('brouillon', StudentApplicationStatus::BROUILLON->value);
        $this->assertSame('soumis', StudentApplicationStatus::SOUMIS->value);
        $this->assertSame('verification', StudentApplicationStatus::VERIFICATION->value);
        $this->assertSame('complement', StudentApplicationStatus::COMPLEMENT->value);
        $this->assertSame('evaluation', StudentApplicationStatus::EVALUATION->value);
        $this->assertSame('valide', StudentApplicationStatus::VALIDE->value);
        $this->assertSame('rejete', StudentApplicationStatus::REJETE->value);
        $this->assertSame('approuve', StudentApplicationStatus::APPROUVE->value);
        $this->assertSame('engage', StudentApplicationStatus::ENGAGE->value);
        $this->assertSame('decaisse', StudentApplicationStatus::DECAISSE->value);
        $this->assertSame('cloture', StudentApplicationStatus::CLOTURE->value);
    }
}
