# Corrections des fondations FOSER

Date : 5 octobre 2026
Périmètre : corrections ciblées issues de l’audit précédent. Aucun développement IA et aucun déploiement.

## Problèmes corrigés

- Le garde-fou `User::saving` était enregistré à l’intérieur du callback `created`; il n’était donc pas installé pour les mises à jour habituelles. Il est maintenant enregistré directement dans `booted`, empêchant un compte de modifier ses propres privilèges et un non-super-administrateur d’attribuer les rôles `super_admin`, `admin` ou `directeur_general`.
- Les lectures génériques API de candidatures et d’évaluations n’imposaient aucun scope d’objet. Les candidats/chercheurs voient désormais leurs propres candidatures, les évaluateurs leurs affectations, et les établissements les candidatures de leurs étudiants. Les lectures de détail utilisent le même scope et retournent 404 hors périmètre.
- La liste API générique des étudiants est limitée aux profils étudiants. Les universités sont filtrées sur leurs établissements; un accès global reste possible lorsqu’il est explicitement accordé par `users.view`, comme l’attend le test INEE existant.
- Les réclamations sont limitées au réclamant ou aux étudiants du tenant universitaire. Les notifications sont maintenant scellées par utilisateur sur l’index **et** le détail, supprimant l’accès direct à la notification d’un autre compte.
- Les projets de recherche API sont limités au chercheur principal/membre, à l’évaluateur assigné ou au périmètre laboratoire de l’établissement; le personnel autorisé conserve l’accès institutionnel.
- La lecture et le téléchargement des documents API sont centralisés dans `DocumentAccessService`. Les documents non publiés restent inaccessibles; les documents privés publiés ne sont accessibles qu’au personnel habilité, à leur déposant, ou dans le cadre d’une candidature/projet/évaluation/université autorisé. Le catalogue conserve ses documents publics.
- Le service de workflow refusait déjà les montants au-dessus de l’engagement, mais acceptait un montant négatif s’il était appelé hors contrôleur HTTP. La borne inférieure est maintenant contrôlée dans le service.
- Les tests des vues publiques, du login étudiant et du login Filament n’initialisaient pas le schéma bien que leurs view composers lisent la base. `RefreshDatabase` a été ajouté aux classes concernées; cela corrige les échecs de suite sans modifier le comportement applicatif.

## Fichiers modifiés

### Application

- `app/Models/User.php`
- `app/Http/Controllers/Api/V1/ApiController.php`
- `app/Http/Controllers/Api/V1/StudentController.php`
- `app/Http/Controllers/Api/V1/ApplicationController.php`
- `app/Http/Controllers/Api/V1/EvaluationController.php`
- `app/Http/Controllers/Api/V1/ClaimController.php`
- `app/Http/Controllers/Api/V1/NotificationController.php`
- `app/Http/Controllers/Api/V1/ResearchProjectController.php`
- `app/Http/Controllers/Api/V1/DocumentController.php`
- `app/Http/Controllers/Api/V1/DocumentDownloadController.php`
- `app/Services/DocumentAccessService.php` (nouveau)
- `app/Services/ApplicationWorkflowService.php`

### Tests

- `tests/Feature/ApiResourceScopingTest.php` (nouveau)
- `tests/Feature/UserPrivilegeProtectionTest.php` (nouveau)
- `tests/Feature/DocumentCenterTest.php`
- `tests/Feature/ApplicationWorkflowEndToEndTest.php`
- `tests/Feature/StudentPortalTest.php`
- `tests/Feature/SecurityTest.php`
- `tests/Feature/ExampleTest.php`

Le dépôt contenait déjà de nombreuses modifications locales avant cette intervention. Elles ont été conservées; les fichiers mentionnés ci-dessus ont été modifiés ou ajoutés pour ce travail, mais certains étaient également déjà modifiés localement.

## Migrations créées

Aucune migration créée ou modifiée pour ces corrections. `php artisan migrate:status` s’exécute; les migrations métier présentes sont marquées `Ran`. La migration `2026_09_14_144000_create_ai_interactions_table` apparaît `Pending` dans cet environnement. Elle n’a pas été modifiée ni exécutée.

## Routes modifiées

Aucune route ajoutée, supprimée ou modifiée. `php artisan route:list --json` enregistre toujours 347 routes. Les endpoints API existants conservent leurs middlewares Sanctum, permissions et throttling; les restrictions par objet/tenant sont appliquées dans les contrôleurs/services de lecture.

## Tests exécutés

- `php artisan test --filter=UserPrivilegeProtectionTest` : 2 tests, 2 assertions, réussite après le dernier changement au modèle utilisateur.
- `php artisan test --filter=ApiResourceScopingTest` : 6 tests, 21 assertions, réussite. Couvre candidat, évaluateur, université, réclamations, notifications et projets.
- `php artisan test --filter=DocumentCenterTest` : 4 tests, 8 assertions, réussite, incluant le refus de téléchargement privé par un autre utilisateur.
- `php artisan test --filter=ApplicationWorkflowEndToEndTest` : 1 test, 13 assertions, réussite; montant négatif refusé sans créer de décaissement.
- `php artisan test --filter=IneeAuthenticationTest` : 16 tests, 119 assertions, réussite; préserve notamment l’accès accordé explicitement par `users.view`.
- `php artisan test --filter='SecurityTest|ExampleTest'` : 7 tests, 17 assertions, réussite.
- Suites groupées API, rôles, INEE, étudiants, documents, université, finance et autorisation : 51 tests, 257 assertions, réussite; `FinancialWorkflowTest` : 2 tests, 3 assertions, réussite.
- `php artisan test` : 174 tests, 676 assertions, réussite complète. Cette exécution a eu lieu avant le dernier remplacement équivalent de comparaison de rôle; le test ciblé `UserPrivilegeProtectionTest` a été rejoué et passe sur l’état final.
- `php artisan migrate:status` : commande exécutée avec succès; une migration IA préexistante reste en attente.
- `php artisan route:list --json` : commande exécutée avec succès; 347 routes et middlewares attendus confirmés sur les routes API contrôlées.
- `git diff --check` sur les fichiers suivis touchés : aucune erreur d’espacement.

## Résultats

Les échecs précédemment observés dans les pages de connexion et les headers de sécurité provenaient de classes de test sans migrations; les classes corrigées passent. Les tests de sécurité ajoutés vérifient explicitement les réponses hors périmètre et l’absence d’écriture de journal de téléchargement en cas de refus.

Le contrôle statique de l’éditeur signale encore `hasRole()` dans `User.php` comme méthode non résolue. C’est une méthode du trait Spatie `HasRoles`, déjà utilisé par le modèle et exercée par les tests; le diagnostic ne correspond pas à une erreur d’exécution PHP. Aucun autre problème n’a été relevé dans les nouveaux scopes API par cet outil.

## Problèmes restant à corriger

- Compléter une matrice de permissions approuvée métier et étendre les tests d’isolation à toutes les ressources API, exports et téléchargements. Les listes génériques de chercheurs, universités, programmes/appels, paiements et documents privés méritent une revue formelle supplémentaire.
- Aligner `docs/openapi.yaml` sur les routes réellement disponibles, notamment les chemins d’assistant documentés qui ne sont pas des endpoints API présents. Cette documentation n’a pas été touchée.
- Auditer toutes les policies et opérations Filament par rôle, y compris MFA pour chaque profil privilégié; cette correction a ciblé les lectures API et le modèle utilisateur, pas une refonte globale des autorisations.
- Renforcer l’atomicité, le verrouillage concurrent, l’idempotence et le cumul des tranches dans les opérations financières au-delà du contrôle de montant négatif ajouté ici.
- Revoir la validation et le cycle de vie des téléversements (scan antivirus, quotas, conservation), les workflows complets de réclamation/messagerie et les garanties de livraison de notifications.
- Définir et valider la CSP, HTTPS/HSTS derrière proxy, la rétention et la surveillance du journal d’audit avant production.
- Exécuter les migrations et les tests d’intégration sur la version PostgreSQL cible. `migrate:status` confirme l’état du schéma consulté, mais cette intervention n’a pas lancé de migration ni de test PostgreSQL dédié.
- Les parcours d’inscription/connexion étudiant, chercheur et INEE ont été conservés et validés; l’atomicité transactionnelle de toutes les variantes d’inscription reste à tester/corriger si une preuve de panne est établie.
- Le cahier des charges fonctionnel formel n’était pas présent; la recette finale doit comparer ces corrections à une version approuvée des exigences.

## Périmètre IA

Aucun fichier IA n’a été modifié, aucune fonctionnalité IA développée et aucune migration IA exécutée. Les changements IA préexistants de l’arbre de travail ont été laissés intacts.