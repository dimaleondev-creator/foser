<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

abstract class SectionPage extends Page
{
    protected string $view = 'filament.pages.module-placeholder';

    protected static array $permissions = [];

    protected static string $module = '';

    protected static array $items = [];

    public static function canAccess(): bool
    {
        return Gate::any(static::$permissions);
    }

    public function getModuleName(): string
    {
        return static::$module;
    }

    public function getModuleItems(): array
    {
        return static::$items;
    }

    public function getModuleItemUrl(string $item): ?string
    {
        $routes = [
            'appels a candidatures' => 'filament.admin.resources.calls.index',
            'candidatures' => 'filament.admin.resources.applications.index',
            'evaluations' => 'filament.admin.resources.evaluations.index',
            'resultats' => 'filament.admin.resources.application-results.index',
            'actualites' => 'filament.admin.resources.news.index',
            'actualites et articles' => 'filament.admin.resources.news.index',
            'communiques' => 'filament.admin.resources.press-releases.index',
            'evenements' => 'filament.admin.resources.events.index',
            'newsletter' => 'filament.admin.resources.newsletter-subscribers.index',
            'abonnes newsletter' => 'filament.admin.resources.newsletter-subscribers.index',
            'campagnes newsletter' => 'filament.admin.resources.newsletter-campaigns.index',
            'galeries photos' => 'filament.admin.resources.media-albums.index',
            'videos' => 'filament.admin.resources.media.index',
            'temoignages' => 'filament.admin.resources.testimonials.index',
            'partenaires' => 'filament.admin.resources.partners.index',
            'pages institutionnelles' => 'filament.admin.resources.cms-contents.index',
            'messages de contact' => 'filament.admin.resources.contact-messages.index',
            'documents' => 'filament.admin.resources.documents.index',
            'categories' => 'filament.admin.resources.document-categories.index',
            'telechargements' => 'filament.admin.resources.downloads.index',
            'dossiers' => 'filament.admin.resources.applications.index',
            'engagements' => 'filament.admin.resources.financial-commitments.index',
            'decaissements' => 'filament.admin.resources.disbursements.index',
            'paiements' => 'filament.admin.resources.payments.index',
            'albums' => 'filament.admin.resources.media-albums.index',
            'photos' => 'filament.admin.resources.media.index',
            'programmes' => 'filament.admin.resources.programs.index',
            'aides financieres' => 'filament.admin.resources.financial-aids.index',
            "prets d'etudes" => 'filament.admin.resources.study-loan-applications.index',
            'recherche' => 'filament.admin.resources.research-programs.index',
            'innovation' => 'filament.admin.resources.innovation-programs.index',
            'projets' => 'filament.admin.resources.research-projects.index',
            'chercheurs' => 'filament.admin.resources.researchers.index',
            'laboratoires' => 'filament.admin.resources.laboratories.index',
            'publications' => 'filament.admin.resources.publications.index',
            'notifications' => 'filament.admin.resources.notification-templates.index',
            'messagerie' => 'filament.admin.resources.message-threads.index',
            'reclamations' => 'filament.admin.resources.claims.index',
            'etudiants' => 'filament.admin.resources.users.index',
            'universites' => 'filament.admin.resources.universities.index',
            'agents' => 'filament.admin.resources.users.index',
            'audit' => 'filament.admin.resources.audit-logs.index',
            'parametres' => 'filament.admin.resources.system-settings.index',
        ];

        $normalizedItem = mb_strtolower(trim($item));
        $key = str_replace(['à', 'â', 'é', 'è', 'ê', 'ë', 'î', 'ï', 'ô', 'ù', 'û', 'ü', 'ç'], ['a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'u', 'u', 'u', 'c'], $normalizedItem);

        return isset($routes[$key]) && Route::has($routes[$key]) ? route($routes[$key]) : null;
    }
}
