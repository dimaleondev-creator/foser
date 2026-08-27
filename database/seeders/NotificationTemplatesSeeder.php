<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'account.created' => ['subject' => 'Bienvenue au FOSER', 'body' => 'Votre compte FOSER a été créé.'],
            'account.verified' => ['subject' => 'Compte vérifié', 'body' => 'Votre compte FOSER est maintenant vérifié.'],
            'application.submitted' => ['subject' => 'Dossier soumis', 'body' => 'Votre dossier {{ reference }} a bien été soumis.'],
            'application.missing_document' => ['subject' => 'Pièce manquante', 'body' => 'Une pièce est manquante dans votre dossier {{ reference }}.'],
            'application.result' => ['subject' => 'Décision FOSER', 'body' => 'La décision concernant votre dossier est disponible.'],
            'application.accepted' => ['subject' => 'Dossier accepté', 'body' => 'Votre dossier a été accepté par le FOSER.'],
            'application.rejected' => ['subject' => 'Dossier rejeté', 'body' => 'Votre dossier n’a pas été retenu.'],
            'application.status_changed' => ['subject' => 'Mise à jour de votre dossier', 'body' => 'Le statut de votre dossier est maintenant : {{ status }}.'],
            'application.complement_requested' => ['subject' => 'Pièce complémentaire demandée', 'body' => 'Une pièce complémentaire est nécessaire pour votre dossier.'],
            'disbursement.created' => ['subject' => 'Décaissement', 'body' => 'Une information sur votre décaissement est disponible.'],
            'payment.completed' => ['subject' => 'Paiement effectué', 'body' => 'Un paiement a été effectué sur votre dossier.'],
            'claim.created' => ['subject' => 'Réclamation reçue', 'body' => 'Votre réclamation a bien été enregistrée.'],
            'claim.replied' => ['subject' => 'Réponse à votre réclamation', 'body' => 'Une réponse est disponible dans votre espace FOSER.'],
            'message.created' => ['subject' => 'Nouveau message FOSER', 'body' => 'Vous avez reçu un nouveau message.'],
            'call.published' => ['subject' => 'Nouvel appel à candidatures', 'body' => 'Un nouvel appel à candidatures est publié.'],
            'result.published' => ['subject' => 'Résultats publiés', 'body' => 'Les résultats de l’appel sont disponibles.'],
            'news.published' => ['subject' => 'Actualité FOSER', 'body' => 'Une nouvelle actualité FOSER est disponible.'],
            'reminder' => ['subject' => 'Rappel FOSER', 'body' => 'Une action est attendue sur votre espace FOSER.'],
        ];
        foreach ($templates as $event => $channels) {
            foreach (['internal', 'email', 'sms', 'whatsapp'] as $channel) {
                DB::table('notification_templates')->updateOrInsert(['event' => $event, 'channel' => $channel], ['id' => (string) Str::uuid(), ...$channels, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }
}
