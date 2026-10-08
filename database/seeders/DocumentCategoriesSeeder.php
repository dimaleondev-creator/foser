<?php

namespace Database\Seeders;

use App\Models\DocumentCategory;
use Illuminate\Database\Seeder;

class DocumentCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'lois' => 'Lois', 'decrets' => 'Décrets', 'arretes' => 'Arrêtés',
            'reglements' => 'Règlements', 'guides' => 'Guides', 'rapports' => 'Rapports',
            'etudes' => 'Études', 'formulaires' => 'Formulaires', 'manuels' => 'Manuels',
            'documents-institutionnels' => 'Documents institutionnels', 'autres-documents-officiels' => 'Autres documents officiels',
            'textes-reglementaires' => 'Textes réglementaires', 'rapports-annuels' => 'Rapports annuels',
        ] as $slug => $name) {
            DocumentCategory::query()->firstOrCreate(['slug' => $slug], ['name' => $name]);
        }
    }
}