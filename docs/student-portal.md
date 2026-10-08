# Espace étudiant

## Accès et profil

La création d’un compte étudiant exige une déclaration INEE préalable et une identité correspondant à cette déclaration. L’INEE est unique par profil. La connexion accepte l’adresse email et le mot de passe; le parcours INEE permet aussi l’authentification par code. Les dossiers exigent un INEE présent et non suspendu.

Le profil regroupe les coordonnées personnelles, l’établissement, la formation, le niveau et l’année académique. L’université ne peut plus être changée après la première soumission d’un dossier. Les modifications du profil sont journalisées.

## Candidatures

Les appels publiés et ouverts sont consultables depuis l’espace étudiant; leur page publique présente les conditions, les dates et les pièces requises. Une candidature créée sur un appel ouvert commence en brouillon. Le formulaire permet d’enregistrer et reprendre le brouillon.

La soumission exige les champs de candidature requis et les pièces obligatoires. Les pièces acceptées sont PDF, JPEG et PNG, jusqu’à 10 Mo chacune. L’étudiant propriétaire peut ajouter, remplacer ou supprimer une pièce tant que le dossier est en brouillon ou en complément. Le remplacement réinitialise le contrôle de la pièce.

Après soumission, l’étudiant ne peut plus modifier le dossier ni ses pièces. Le parcours visible présente les étapes BROUILLON, PIÈCES, CONTRÔLE, SOUMIS, VÉRIFICATION UNIVERSITÉ, VÉRIFICATION FOSER, ÉVALUATION, DÉCISION, ATTRIBUTION, DÉCAISSEMENT et TERMINÉ, avec le statut courant et l’historique daté.

## Données privées et services

Les listes, téléchargements, attestations, résultats, paiements, réclamations, notifications et conversations sont filtrés par l’identité authentifiée ou l’appartenance au fil. Un accès à un dossier, document ou fil d’un autre étudiant répond 404. L’historique des paiements n’inclut que les versements payés; l’attestation exige une attribution active appartenant à l’étudiant.

Les réclamations et messages sont consultables depuis l’assistance. Un nouveau fil de messagerie est associé à l’étudiant et au premier compte FOSER actif de type administrateur. Les réponses sont réservées aux participants d’un fil ouvert. Si aucun compte FOSER actif n’est disponible, l’envoi échoue explicitement avec HTTP 503.

## Tests

Les parcours sont couverts par `StudentPortalTest`, `StudentPortalActionsTest`, `StudentPortalExpansionTest`, `StudentDocumentWorkflowTest`, `PortalWorkflowTest`, `ApiResourceScopingTest` et `IneeAuthenticationTest`. Le workflow complet jusqu’au décaissement est vérifié par `ApplicationWorkflowEndToEndTest`.