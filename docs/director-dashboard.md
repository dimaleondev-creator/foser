# Dashboard Direction FOSER

## Accès et périmètre

La page `/director/dashboard` est réservée au rôle `directeur_general` et à la permission `reports.view`. Les exports XLSX et PDF exigent aussi `reports.export`. Les requêtes agrègent des comptes et des montants; elles ne sélectionnent ni noms, ni emails, ni INEE, ni identifiants d’étudiants. Les enregistrements supprimés logiquement sont exclus quand les tables exposent `deleted_at`.

## Définition des indicateurs

- Étudiants: profils étudiants actifs de comptes `etudiant`; les filtres de période portent sur la date de création du profil.
- Chercheurs: profils `approved` ou `active` de comptes `chercheur`/`researcher`.
- Universités: établissements actifs.
- Candidatures: dossiers actifs dont le propriétaire a un profil étudiant; le statut canonique `workflow_status` prévaut sur `status`.
- Brouillon: `draft`/`brouillon`; soumise: `submitted`/`soumis`; vérification: complétude, documents en attente, vérification universitaire et statuts hérités équivalents; validée: `eligible`/`valide`; rejetée: `rejected`/`rejete`; évaluation: `evaluator_assignment`/`evaluation`; attribuée: `awarded`/`approuve`; terminée: `disbursed`, `paye`, `completed`, `archive`.
- Montant demandé: somme des budgets de candidatures dans le filtre.
- Montants engagé, attribué et décaissé: engagements, attributions actives et décaissements exécutés issus de leurs tables métier. Montant restant = engagé moins décaissé, borné à zéro.
- Paiements: nombre d’enregistrements de paiement; en attente: statuts `pending`, `planned`, `soumis`; terminés: `paid`, `execute`, `valide`, `completed`.
- Projets de recherche: statuts soumis, évaluation, financé et terminé sont comptés depuis `research_projects`; montant consacré = engagements financiers liés aux projets.

## Filtres et analyses

Les filtres date, région, université, programme, année académique et statut sont validés côté serveur. L’année académique n’existe que sur `student_profiles`; elle filtre donc les indicateurs étudiants, candidatures et leur finance, pas les profils chercheurs. Les projets de recherche sont filtrés par période, région, université et relation réelle entre `research_projects.research_program_id` et `research_programs.program_id`.

Les graphiques couvrent les évolutions mensuelle/annuelle, les répartitions université/région/sexe/programme/statut, les montants mensuels décaissés et les projets financés par programme.

## Alertes décisionnelles

- Dossiers bloqués: dossier ouvert dont `updated_at` remonte à plus de 30 jours.
- Dossiers en retard: évaluations attribuées/en cours dépassant `due_at`.
- Paiements en attente: paiements aux statuts en attente documentés ci-dessus.
- Appels proches de fermeture: appels publiés dont la clôture intervient dans les 14 jours.
- Intervention requise: dossiers avec pièces à compléter ou dossiers en évaluation sans affectation.

Les alertes présentent uniquement des volumes, sans données personnelles.

## Exports et tests

L’export Excel est un vrai fichier XLSX généré avec OpenSpout; le PDF est rendu par Dompdf et reprend le snapshot filtré. L’impression utilise la feuille d’impression du navigateur. `DirectorDashboardTest` couvre les sommes réelles, les filtres, la confidentialité, le rôle et les permissions d’export. Le projet utilise `barryvdh/laravel-dompdf` pour la génération PDF.
