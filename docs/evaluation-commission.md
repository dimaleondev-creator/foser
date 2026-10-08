# Évaluations et commissions FOSER

## Cycle d’évaluation

Une affectation suit les statuts `assigned` (attribué), `in_progress` (en évaluation), `submitted` (évaluation soumise), puis `validated` (validée pour la commission). L’ouverture par l’évaluateur démarre l’évaluation. La soumission requiert une note pour chaque critère configuré; les commentaires peuvent être saisis critère par critère et globalement. Une soumission verrouille la grille. Seul un responsable doté de `evaluations.validate` peut valider une évaluation soumise.

La transmission à la commission est refusée s’il n’existe aucune évaluation ou si une seule affectation n’est pas validée. Le résultat ne peut être publié par l’ancien endpoint que lorsque le dossier est effectivement en étape `commission_review`.

## Administration

Les comptes évaluateurs sont créés et administrés via la gestion des utilisateurs. Le profil utilisateur contient les domaines `evaluation_expertise`, configurables avec l’action Filament des utilisateurs. L’affectation et la réattribution nécessitent `applications.assign_evaluator`, contrôlent le statut actif, le rôle, les conflits d’intérêts et le domaine d’expertise. La réattribution est permise avant soumission uniquement; elle efface les notes de la grille non soumise, remet le statut à `assigned`, journalise l’ancien/nouveau responsable et notifie le nouvel évaluateur.

Les affectations peuvent être retirées seulement avant soumission. La ressource Filament Évaluations est en lecture seule; les transitions sont exécutées par les actions du workflow.

## Portail évaluateur et confidentialité

Les listes et détails sont filtrés par l’identifiant de l’évaluateur affecté. Le détail présente les données de projet nécessaires, sans identité, email, INEE, profil académique ni données financières du candidat. Les téléchargements vérifient à la fois l’affectation au dossier et le rattachement du document à ce dossier. Les évaluations soumises/validées restent consultables en lecture seule.

L’API évaluateur possède aussi une action explicite de démarrage et utilise le même service de soumission/verrouillage. La ressource d’évaluation générique n’expose ni commentaire interne ni identifiant d’évaluateur aux lecteurs API ordinaires. Le rôle évaluateur ne possède pas la permission de validation commission.

## Tests

`FinalizationGhTest` couvre l’accès aux seules affectations, l’IDOR de soumission et téléchargement, l’absence de données personnelles, l’expertise, la réattribution, les verrous, l’API et l’interdiction de contourner la commission. `ApplicationWorkflowEndToEndTest` traverse l’affectation, la soumission, la validation, la commission, la décision et les opérations ultérieures.