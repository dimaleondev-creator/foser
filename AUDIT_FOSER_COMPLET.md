# Audit technique complet du portail FOSER

Date de l’audit : 5 octobre 2026  
Périmètre : dépôt Laravel existant, hors analyse fonctionnelle de l’IA. Aucun code applicatif n’a été modifié.

## Méthode et limites

Aucun cahier des charges formel distinct n’a été trouvé dans le dépôt. La comparaison fonctionnelle s’appuie donc sur les documents disponibles (`FINALISATION-FOSER.md`, `ARCHITECTURE.md`, `docs/`, `WCAG-AUDIT.md`), les routes, les modèles, migrations, contrôleurs, services, policies, ressources Filament et tests. Les statuts décrivent le périmètre vérifiable dans le dépôt, pas une conformité contractuelle exhaustive. Une validation de production, des données réelles et une recette métier avec les responsables FOSER restent nécessaires.

L’audit a constaté 347 routes enregistrées. `php artisan route:list --json` s’exécute. `php artisan test` donne 160 tests réussis et 5 échoués (627 assertions). La suite ciblée `IneeAuthenticationTest` passe : 16 tests, 119 assertions. La suite ciblée `StudentPortalTest` échoue sur 2 tests et en réussit 2 (15 assertions) : ces cas rendent les pages sans migrations alors qu’un composer de vue interroge `system_settings`; la table existe dans la migration métier. Ce défaut est observé dans le dispositif de test, et ne prouve pas une panne de l’application avec son schéma migré.

Les chemins cités ci-dessous sont relatifs à la racine du dépôt. « Complet » signifie que le périmètre visible dans le dépôt est livré et testé, non que le cahier des charges absent est intégralement satisfait.

## Résumé exécutif

Le projet est un portail Laravel 12 / Filament 4 déjà substantiel : plusieurs espaces de rôles, un modèle de données métier étendu, un parcours de candidature testé de bout en bout, un dispositif INEE testé, des fonctionnalités publiques de contenu et une API REST v1 sont présents. Ce n’est pas un simple socle vide.

Le risque principal est la confidentialité inter-rôles dans l’API. Les endpoints génériques `/api/v1/students`, `/api/v1/applications` et `/api/v1/evaluations` vérifient des permissions générales, mais ne filtrent pas les dossiers selon l’identité du candidat, les affectations de l’évaluateur ou le périmètre de l’université. Or plusieurs rôles non administratifs possèdent ces permissions. Les ressources renvoient notamment l’INEE et l’adresse électronique des étudiants ainsi que les notes/commentaires de candidature. Corriger et tester le cloisonnement est un préalable à toute mise en production.

Autres constats prioritaires : le garde-fou de privilèges du modèle `User` est enregistré dans le callback `created` au lieu d’être enregistré directement dans `booted`; l’OpenAPI documente des endpoints IA/versionnés qui ne correspondent pas aux routes; les tests complets ne sont pas verts; les commissions ne disposent pas d’un module métier dédié démontré. La documentation d’architecture est périmée : elle affirme qu’il n’existe ni tables métier ni ressources Filament.

## Référentiel d’état et de priorité

- **COMPLET** : périmètre implémenté de façon cohérente avec des tests couvrant les parcours essentiels observés.
- **PARTIEL** : fonctions importantes présentes, mais couverture, workflow, intégration ou preuve de conformité incomplets.
- **MANQUANT** : aucun module ou flux fonctionnel correspondant n’a été trouvé dans le périmètre inspecté.
- **BUGUÉ** : défaut reproductible ou incohérence de sécurité/intégrité établie par le code ou les tests.
- **CRITIQUE** : confidentialité, privilèges ou intégrité de production potentiellement compromis; bloquant avant mise en production.
- **HAUTE** : risque important ou parcours métier essentiel non fiabilisé.
- **MOYENNE** : limitation notable qui doit être résolue avant la finalisation générale.
- **BASSE** : amélioration non bloquante ou preuve/qualité à compléter.

## Audit des 40 modules

| # | Module | État | Fichiers concernés | Fonctionnalités existantes | Manques, bugs et risques | Priorité |
|---:|---|---|---|---|---|---|
| 1 | Architecture Laravel | PARTIEL | `ARCHITECTURE.md`, `app/`, `bootstrap/app.php`, `composer.json` | Laravel 12, Filament 4, Sanctum, Spatie Permission; organisation par contrôleurs, services et ressources. | `ARCHITECTURE.md` est contradictoire avec le code actuel : il annonce l’absence de tables métier et de ressources Filament. Architecture cible Domain/Application/Infrastructure non matérialisée. Rafraîchir le document et établir les frontières de domaine. | MOYENNE |
| 2 | Routes web et API | PARTIEL | `routes/web.php`, `routes/api.php`, `tests/Feature/ModuleRoutesTest.php` | 347 routes enregistrées couvrent pages publiques, espaces authentifiés, workflows et API. Middleware et limites de débit présents sur plusieurs routes sensibles. | Contrat OpenAPI incomplet/décalé des routes livrées. Les routes génériques API n’appliquent pas uniformément le cloisonnement par propriétaire/tenant. Pas de preuve d’un contrôle automatisé exhaustif de toutes les routes et méthodes. | HAUTE |
| 3 | Models | PARTIEL | `app/Models/`, `app/Enums/` | Modèles pour candidature, résultat, attribution, finance, INEE via profil étudiant, documents, projets, contenu, notifications et autres domaines. | Aucun modèle de commission/séance de commission identifié. La coexistence d’états historiques et canoniques (`status`, `workflow_status`) demande une stratégie de migration et de cohérence maintenue. | MOYENNE |
| 4 | Migrations | PARTIEL | `database/migrations/2026_08_24_090000_create_foser_business_schema.php`, `database/migrations/` | Schéma métier étendu, relations, index, clés étrangères, champs d’audit et migrations d’évolution. `system_settings` est bien créé dans le schéma métier. | La suite complète échoue sur des tests qui n’initialisent pas le schéma avant d’afficher des vues. L’exécution et la restauration sur PostgreSQL cible n’ont pas été vérifiées durant cet audit; SQLite de test ne suffit pas à certifier le comportement PostgreSQL. | HAUTE |
| 5 | Controllers | PARTIEL | `app/Http/Controllers/`, `app/Http/Controllers/Api/` | Contrôleurs publics, étudiant, chercheur, université, évaluateur, administration et API dédiés. | Vérifier systématiquement validation, autorisation d’objet, filtrage de tenant et gestion d’erreurs. Des endpoints API génériques exposent des collections sans scope métier (voir sécurité et API). | HAUTE |
| 6 | Services | PARTIEL | `app/Services/` | Services dédiés aux workflows de candidature, finance, INEE, évaluation, capacité d’appel, notifications, import, statistiques et complétude. | Certains invariants sont contrôlés par les contrôleurs plutôt que réimposés au niveau service; `prepareDisbursement()` n’interdit pas lui-même un montant négatif. `FinancialWorkflow::transition()` accepte un nom de table et lit l’état avant sa transaction sans verrouillage; aucun appel métier de production à ce service générique n’a été trouvé dans `app/`. | HAUTE |
| 7 | Policies | BUGUÉ | `app/Policies/`, `app/Http/Middleware/EnsurePermission.php`, `app/Http/Controllers/Api/V1/ApiController.php` | Policies par domaine et middleware de permissions Spatie. | Les permissions d’accès aux collections ne remplacent pas une autorisation par enregistrement. Les endpoints génériques `show()` et `index()` font des lectures sans policy/scoping; étudiants, évaluateurs et universités peuvent recevoir des ressources hors de leur périmètre avec les grants actuels. | CRITIQUE |
| 8 | Middleware | PARTIEL | `app/Http/Middleware/`, `bootstrap/app.php` | Middleware rôle, permission, accès université, locale et headers de sécurité globalement enregistrés. | Le contrôle d’accès reste principalement un contrôle de capacité générale; il ne protège pas à lui seul les objets d’autres étudiants, affectations ou universités. Vérifier également la cohérence des redirects web et réponses JSON API. | HAUTE |
| 9 | Filament Resources | PARTIEL | `app/Filament/Resources/`, `app/Filament/Resources/BaseCrudResource.php`, `app/Providers/Filament/AdminPanelProvider.php` | Nombreuses ressources CRUD découvertes automatiquement et restreintes par permissions; panneau `/admin`. | Une ressource CRUD disponible n’atteste pas un workflow d’administration complet avec validation, historique, verrouillage et transitions contrôlées. Revue ressource par ressource et tests d’accès selon les rôles nécessaires. | MOYENNE |
| 10 | Dashboards | PARTIEL | `app/Filament/Widgets/`, `app/Services/DashboardStatisticsService.php`, `app/Services/RoleDashboardResolver.php`, `docs/dashboard.md` | Dashboard Filament, widgets statistiques et résolveur de tableaux de bord par rôle. | La diffusion temps réel SSE/WebSocket n’est pas activée selon `FINALISATION-FOSER.md`. Mesures de fraîcheur, latence et filtrage des statistiques sensibles à confirmer. | MOYENNE |
| 11 | Authentification | PARTIEL | `app/Http/Controllers/StudentPortalController.php`, `app/Http/Controllers/ResearcherPortalController.php`, `app/Http/Controllers/PasswordResetController.php`, `app/Providers/Filament/AdminPanelProvider.php`, `app/Models/User.php` | Inscription et connexion étudiants/chercheurs, réinitialisation, invitation, vérification email, tokens Sanctum et MFA Filament configuré pour une liste de rôles. | Vérifier la couverture MFA de tous les comptes privilégiés, l’expiration/révocation des tokens, la gestion des comptes suspendus sur chaque canal et les parcours de récupération. Garde-fou de privilèges `User::saving` mal enregistré (voir rôle/sécurité). | HAUTE |
| 12 | Gestion des rôles | BUGUÉ | `database/seeders/RolesAndPermissionsSeeder.php`, `app/Models/User.php`, `app/Services/RoleDashboardResolver.php` | Rôles et permissions Spatie, synchronisation du rôle depuis `account_type`, filtrage de tableaux de bord. | Le listener `saving` censé empêcher l’auto-modification de privilèges est imbriqué dans `created`, et seulement dans une branche de création de compte. Sa protection n’est donc pas enregistrée de façon fiable; auditer aussi tous les grants génériques. | HAUTE |
| 13 | Gestion INEE | COMPLET | `app/Http/Controllers/IneController.php`, `app/Services/IneAdministrationService.php`, `app/Services/StudentApplicationWorkflow.php`, `tests/Feature/IneeAuthenticationTest.php` | Déclaration, vérification, recherche/récupération, OTP, codes de connexion, administration et throttling. Les 16 tests INEE exécutés passent (119 assertions). | Pas d’API INEE v1 identifiée; conformité au référentiel national et intégration externe non prouvées. Le verdict complet se limite aux fonctions locales présentes et aux tests actuels. | MOYENNE |
| 14 | Étudiants | PARTIEL | `app/Http/Controllers/StudentPortalController.php`, `app/Http/Controllers/StudyLoanController.php`, `resources/views/student/`, `tests/Feature/StudentPortalTest.php`, `tests/Feature/StudentPortalExpansionTest.php` | Espace étudiant, profil, candidatures, pièces, résultats, réclamations, notifications, prêts et paiements consultables. | Endpoints API génériques étudiants/candidatures sans scope d’identité. Deux tests d’affichage ciblés échouent faute de migrations dans leur classe; fiabiliser le setup et exécuter une suite complète verte. | CRITIQUE |
| 15 | Chercheurs | PARTIEL | `app/Http/Controllers/ResearcherPortalController.php`, `app/Http/Controllers/Api/V1/ResearcherWorkspaceController.php`, `app/Models/Researcher.php`, `tests/Feature/ResearcherRegistrationTest.php` | Inscription avec approbation, profil, projets, publications, suivi, conventions, laboratoire et décaissements consultables. | Le parcours général est testé partiellement; vérifier isolation des projets/documents, validation institutionnelle, transitions de financement et contrats API sur l’ensemble du cycle. | MOYENNE |
| 16 | Universités | PARTIEL | `app/Http/Controllers/UniversityPortalController.php`, `app/Http/Controllers/Api/V1/UniversityWorkspaceController.php`, `app/Http/Middleware/EnsureUniversityResponsible.php`, `tests/Feature/UniversityWorkspaceExpansionTest.php` | Espace établissement, utilisateurs, étudiants, laboratoires, validation, import, rapports et messages; API de workspace dédiée. | La séparation tenant du workspace dédié est contournée par des endpoints génériques `/api/v1/students` et `/api/v1/applications` accessibles aux permissions université. Vérifier également les exports et téléchargements par tenant. | CRITIQUE |
| 17 | Évaluateurs | PARTIEL | `app/Http/Controllers/EvaluatorPortalController.php`, `app/Services/ApplicationEvaluationService.php`, `app/Http/Controllers/Api/EvaluatorAssignmentController.php` | Affectation, soumission d’évaluation, conflit d’intérêts et publication de résultats. | Les endpoints génériques `/api/v1/applications` et `/api/v1/evaluations` sont autorisés par permission générale sans filtrage sur les affectations; risque d’accès aux dossiers/commentaires hors affectation. | CRITIQUE |
| 18 | Administrateurs | PARTIEL | `app/Providers/Filament/AdminPanelProvider.php`, `app/Models/User.php`, `app/Policies/UserPolicy.php`, `app/Http/Controllers/RoleDashboardController.php` | Panneau Filament protégé par vérification d’email, état du compte et permissions; MFA requis pour certaines fonctions/rôles. | Valider l’accès et le MFA pour chaque rôle à privilèges élevés, les comptes de secours et la prévention d’escalade. Le listener de protection du modèle utilisateur est défectueux. | HAUTE |
| 19 | Appels à candidatures | PARTIEL | `app/Http/Controllers/CallPortalController.php`, `app/Services/CallCapacityService.php`, `app/Models/Call.php`, `app/Filament/Resources/Calls/`, `tests/Feature/PublicNewsAndCallsTest.php` | Publication/listing public, détail, documents, résultats, capacité et gestion Filament. | Prouver les règles complètes de calendrier, suspension, clôture et republication; les exigences formelles ne sont pas disponibles. | MOYENNE |
| 20 | Candidatures | BUGUÉ | `app/Services/ApplicationWorkflowService.php`, `app/Services/ApplicationCompletenessService.php`, `app/Http/Controllers/Api/V1/ApplicationController.php`, `app/Http/Resources/ApplicationResource.php`, `tests/Feature/ApplicationWorkflowEndToEndTest.php` | Workflow canonique testé de la création à la soumission, évaluation, décision, attribution, engagement et décaissement; complétude et historique. | Exposition confirmée de candidatures hors périmètre via l’API générique : ressource contenant `applicant_id` et `applicant_note`, sans filtrage par candidat/tenant. Les tests de bout en bout couvrent un scénario, pas les courses concurrentes ni les droits de lecture. | CRITIQUE |
| 21 | Documents | PARTIEL | `app/Models/Document.php`, `app/Http/Controllers/Api/V1/DocumentDownloadController.php`, `app/Http/Controllers/StudentPortalController.php`, `app/Policies/DocumentPolicy.php`, `tests/Feature/DocumentCenterTest.php` | Documents privés, catégories, historique de versions au schéma, téléversement et téléchargements contrôlés; documents publics filtrés. | Vérifier ownership et tenant à chaque téléchargement, types/tailles sur chaque canal, antivirus, conservation et suppression. Aucune preuve de scan malware ou de politique de rétention exhaustive dans les éléments inspectés. | HAUTE |
| 22 | Évaluations | PARTIEL | `app/Models/Evaluation.php`, `app/Services/ApplicationEvaluationService.php`, `app/Http/Controllers/Api/V1/EvaluationController.php`, `app/Http/Resources/EvaluationResource.php` | Critères, notes, évaluateur, statut et conflit; API de consultation et contrôles du workflow. | Listing/détail API génériques exposent les évaluations hors affectation; `comment` est sérialisé. Contrôler confidentialité des évaluations non publiées et des critères/notes par rôle. | CRITIQUE |
| 23 | Commissions | MANQUANT | `app/Services/ApplicationWorkflowService.php`, `app/Enums/ApplicationStatus.php`, `database/migrations/2026_08_24_090000_create_foser_business_schema.php` | Le statut `commission_review` et la transition vers décision existent. | Aucun modèle, ressource ou workflow dédié de composition, quorum, convocation, séance, procès-verbal, vote, récusation ou historisation de commission identifié. À définir avec le métier. | HAUTE |
| 24 | Décisions | PARTIEL | `app/Models/ApplicationResult.php`, `app/Services/ApplicationWorkflowService.php`, `app/Http/Controllers/ApplicationWorkflowController.php`, `tests/Feature/ApplicationWorkflowEndToEndTest.php` | Enregistrement du résultat/décision, motif, acteur et publication contrôlée dans le parcours testé. | Gouvernance de décision collective, versionnement/correction après publication, signature et procédure de recours non démontrés. | HAUTE |
| 25 | Attributions | PARTIEL | `app/Models/ApplicationAward.php`, `app/Services/ApplicationWorkflowService.php`, `app/Filament/Resources/ApplicationResults/`, `tests/Feature/ApplicationWorkflowEndToEndTest.php` | Création d’attribution pour une décision acceptée et montant borné par le plafond d’appel; persistance auditée dans le parcours principal. | Cycle de notification, acceptation/refus du bénéficiaire, avenants, solde disponible et rapprochement avec paiements restent à spécifier/vérifier. | HAUTE |
| 26 | Paiements | PARTIEL | `app/Models/Payment.php`, `app/Http/Controllers/Api/V1/PaymentController.php`, `app/Http/Controllers/StudentPortalController.php`, `app/Filament/Resources/Payments/` | Modèle, consultation par API et espace étudiant, administration Filament. | Aucune intégration de prestataire ou preuve de rapprochement bancaire/idempotence n’a été identifiée. Distinguer paiement comptabilisé, engagé et décaissé dans le contrat métier. | HAUTE |
| 27 | Décaissements | PARTIEL | `app/Models/Disbursement.php`, `app/Services/ApplicationWorkflowService.php`, `app/Services/FinancialWorkflow.php`, `tests/Feature/FinancialWorkflowTest.php`, `tests/Feature/ApplicationWorkflowEndToEndTest.php` | Engagement, préparation et exécution de décaissement dans le workflow de candidature; journalisation et tests métier partiels. | `prepareDisbursement()` ne rejette pas un montant négatif au niveau service (les contrôleurs actuels le valident). `FinancialWorkflow` lit le statut avant la transaction sans verrou, n’impose pas de liste blanche pour le nom de table et n’a aucun appel métier dans `app/` retrouvé. Tester concurrence et cumul des tranches. | HAUTE |
| 28 | Réclamations | PARTIEL | `app/Models/Claim.php`, `app/Http/Controllers/StudentPortalController.php`, `app/Http/Controllers/Api/V1/ClaimController.php`, `app/Filament/Resources/Claims/` | Création/consultation côté étudiant et lecture API/Filament. | Cycle de traitement complet (affectation, réponse, délais, escalade, clôture, réouverture et notification) non démontré dans les éléments inspectés. Vérifier confidentialité des réclamations. | MOYENNE |
| 29 | Notifications | PARTIEL | `app/Services/NotificationService.php`, `app/Jobs/DeliverNotificationJob.php`, `app/Http/Controllers/NotificationCenterController.php`, `app/Models/NotificationDelivery.php`, `tests/Unit/NotificationCenterTest.php` | Historique, préférences, lecture, modèles et journaux de livraison; notifications déclenchées par certaines transitions. | Livraison réelle email/SMS, reprises, idempotence, alertes d’échec et conformité aux préférences à éprouver sur les fournisseurs de production. Provider SMS livré en mode nul par défaut. | MOYENNE |
| 30 | Messagerie | PARTIEL | `app/Models/MessageThread.php`, `app/Http/Controllers/StudentPortalController.php`, `app/Http/Controllers/UniversityPortalController.php`, `app/Filament/Resources/MessageThreads/` | Création de fils côté étudiant/établissement et réponse dans l’espace université. | Modération, pièces jointes, accusés de lecture, recherche, archivage, règles de conservation et autorisation participant par participant à confirmer. | MOYENNE |
| 31 | Actualités | PARTIEL | `app/Models/News.php`, `app/Models/PressRelease.php`, `app/Http/Controllers/PublicPortalController.php`, `app/Filament/Resources/News/`, `tests/Feature/PublicNewsAndCallsTest.php` | Articles publics par slug, photos, vidéos, communiqués et contenu administrable; visibilité publiée filtrée. | Vérifier workflow éditorial à plusieurs étapes, programmation de publication, métadonnées SEO et autorisations de médias. | BASSE |
| 32 | Médiathèque | PARTIEL | `app/Models/Media.php`, `app/Models/MediaAlbum.php`, `app/Http/Controllers/PublicPortalController.php`, `app/Filament/Resources/Media/`, `app/Filament/Resources/MediaAlbums/` | Albums, médias, pages publiques photos/vidéos et ressources Filament. | Cycle de modération, contrôle MIME réel, quotas, transformations/miniatures, accessibilité des médias et politique de conservation non vérifiés. | MOYENNE |
| 33 | Centre documentaire | PARTIEL | `app/Models/Document.php`, `app/Models/DocumentCategory.php`, `app/Http/Controllers/PublicPortalController.php`, `app/Http/Controllers/Api/V1/DocumentDownloadController.php`, `tests/Feature/DocumentCenterTest.php` | Catégories, liste/téléchargement public, contrôle de statut et route de téléchargement API contrôlée. | Traçabilité et autorisation des documents privés à couvrir systématiquement; versionnement, validation éditoriale et indexation documentaire restent à confirmer. | MOYENNE |
| 34 | Statistiques | PARTIEL | `app/Services/DashboardStatisticsService.php`, `app/Http/Controllers/Api/V1/DecisionStatisticsController.php`, `app/Filament/Widgets/`, `tests/Feature/DecisionStatisticsTest.php`, `FINALISATION-FOSER.md` | Indicateurs, séries, exports et statistiques régionales agrégées; widgets Filament. | La carte régionale est explicitement partielle (données SVG/GeoJSON des 13 régions à terminer); temps réel non activé. Restreindre et agréger les statistiques sensibles selon les rôles. | MOYENNE |
| 35 | Journal d’audit | PARTIEL | `app/Services/AuditLogger.php`, `app/Models/AuditLog.php`, `app/Policies/AuditPolicy.php`, `app/Filament/Resources/AuditLogs/` | Enregistre acteur, événement, entité, IP, user-agent et valeurs anciennes/nouvelles pour plusieurs workflows. | Couverture des mutations non exhaustive démontrée; pas de garantie d’immutabilité, rétention, export sécurisé, horodatage résistant à l’altération ou alerte d’accès dans les éléments inspectés. | HAUTE |
| 36 | API | BUGUÉ | `routes/api.php`, `app/Http/Controllers/Api/V1/ApiController.php`, `app/Http/Controllers/Api/V1/StudentController.php`, `app/Http/Resources/StudentResource.php`, `app/Http/Resources/ApplicationResource.php`, `docs/openapi.yaml`, `tests/Feature/ApiV1Test.php` | API v1 Sanctum avec ressources, statistiques, catalogues publics, recherche, workspaces et opérations de workflow; tests de présence et de permissions sélectionnés. | **Risque critique confirmé par code** : les grants étudiant/chercheur/évaluateur incluent `applications.view`; le rôle université possède `users.view` et `applications.view`. Les endpoints génériques ne filtrent pas sur le propriétaire/tenant. `StudentResource` renvoie email et INEE; `ApplicationResource` renvoie identifiant et note du candidat. Le contrat OpenAPI annonce notamment `/api/v1/assistant/ask`, absent des routes API (les routes assistant correspondantes sont web). | CRITIQUE |
| 37 | Sécurité | BUGUÉ | `app/Models/User.php`, `app/Http/Middleware/SecurityHeaders.php`, `config/security.php`, `app/Http/Controllers/Api/V1/ApiController.php`, `docs/security.md`, `tests/Feature/SecurityTest.php` | Throttling, headers de base, MFA Filament partiel, stockage privé et tests de sécurité existent. | Cloisonnement API insuffisant (critique). Le listener `User::saving` est enregistré depuis `created`, et seulement dans une branche conditionnelle : le contrôle anti-auto-élévation n’est pas fiable. CSP uniquement si configurée; `docs/security.md` réclame encore revue production, CSP, HTTPS/HSTS. | CRITIQUE |
| 38 | Multilingue | PARTIEL | `app/Http/Middleware/SetLocale.php`, `routes/web.php`, `lang/en/`, `lang/fr/`, `tests/Feature/LocalizationTest.php`, `resources/views/` | Français par défaut, bascule en/en français persistée par cookie; tests de bascule et surcharge CMS. | Traduction complète des vues et des messages métier non prouvée; certaines pages/contenus restent français ou peuvent avoir un `lang` statique. Audit d’inventaire des chaînes et test de chaque espace requis. | MOYENNE |
| 39 | Responsive/mobile | PARTIEL | `resources/css/app.css`, `resources/js/app.js`, `resources/views/welcome.blade.php`, `WCAG-AUDIT.md` | Styles responsive et menu mobile présents; audit statique WCAG documenté. | Pas de validation réelle sur appareils/navigateurs durant cet audit. L’audit WCAG marque encore le lien de saut, contraste/focus et parcours clavier du menu mobile à traiter ou vérifier. | MOYENNE |
| 40 | Tests | BUGUÉ | `tests/Feature/`, `tests/Unit/`, `phpunit.xml`, `tests/Feature/StudentPortalTest.php` | Couverture notable des workflows, INEE, API, espaces, sécurité, contenu et statistiques. INEE ciblé passe; workflow candidature et finance ont des tests dédiés. | La suite actuelle est rouge : 160 réussis, 5 échoués, 627 assertions. La classe `StudentPortalTest` ne migre pas la base de test; 2 tests d’affichage échouent sur `system_settings` absent dans SQLite mémoire. Tests manquants pour les accès croisés API et concurrence des transitions financières. | HAUTE |

## Fonctionnalités terminées dans le périmètre observé

- Parcours INEE local de déclaration/récupération/OTP/authentification, couvert par 16 tests ciblés réussis.
- Parcours canonique de candidature testé jusqu’au décaissement, avec transitions et journalisation.
- Socle Laravel 12, Filament, Sanctum, rôles/permissions et espaces distincts déjà présents.
- Fonctionnalités publiques de contenu, actualités, agenda, partenaires, témoignages et centre documentaire présentes et testées sur des périmètres ciblés.
- Tests de localisation français/anglais, de recherche et de catalogues publics présents.

## Fonctionnalités partielles

- Cloisonnement réel des données par étudiant, évaluateur et université sur toutes les routes et ressources.
- Workflow de commissions, paiements, réclamations, messagerie et notifications de bout en bout.
- API et contrat OpenAPI cohérents, complets et stables.
- Statistiques régionales, carte complète, tableaux de bord temps réel et observabilité de production.
- Traduction, accessibilité et validation responsive exhaustives.
- Journal d’audit uniforme, rétention et résistance à l’altération.
- Suite de tests verte et exécution/certification des migrations sur PostgreSQL de production.

## Fonctionnalités manquantes

- Module métier de commission identifié par entités et workflow dédiés (composition, séance, quorum, procès-verbal, votes/récusations), au-delà du simple statut `commission_review`.
- Cahier des charges fonctionnel formel et critères d’acceptation versionnés dans le dépôt : leur absence empêche une comparaison exhaustive point à point.
- Preuve d’une intégration de prestataire de paiement et d’un rapprochement comptable, si ceux-ci sont exigés.
- Preuve d’un audit d’accessibilité assisté et d’une recette sur appareils réels.

## Bugs et risques constatés

### Critiques avant mise en production

1. **API et confidentialité inter-rôles** : les permissions larges ouvrent des endpoints génériques de lecture sans scope objet/tenant. Les réponses de ressources comprennent des données personnelles (email, INEE, notes de candidature, commentaires d’évaluation). Retirer ces lectures génériques aux rôles non administratifs ou imposer des queries/policies strictement scellées par propriétaire, affectation et université; ajouter des tests négatifs avec plusieurs identités.
2. **Garde-fou de privilèges `User`** : l’écouteur `saving` est déclaré à l’intérieur de l’écouteur `created`, conditionnellement. Le déplacer au niveau d’enregistrement d’événements de modèle et couvrir l’auto-modification de rôle, statut et université ainsi que l’attribution de rôles élevés.

### Bugs et risques hauts

3. **Tests non reproductibles/rouges** : les pages d’authentification étudiante consultent un réglage global en base; les tests concernés ne préparent pas leurs migrations. Mettre à niveau la fixture de test et obtenir une suite complète verte.
4. **Montant de décaissement** : ajouter au service la borne inférieure (et contrôler le cumul déjà décaissé, si les tranches sont possibles), pas seulement aux validateurs HTTP.
5. **Transitions financières concurrentes** : relire et verrouiller l’opération dans une transaction; utiliser une liste blanche de tables et une mise à jour conditionnelle sur le statut attendu.
6. **OpenAPI en dérive** : retirer ou implémenter les chemins décrits qui ne correspondent pas aux routes livrées; valider le document automatiquement en CI contre le routage réel.
7. **CSP et sécurité de production** : définir/valider une CSP compatible avec les scripts réels, HTTPS/HSTS derrière le proxy, secrets et paramètres de production; faire une revue sécurité dédiée.

### Dette technique

- `ARCHITECTURE.md` ne représente plus le système réellement livré.
- Deux vocabulaires de statut (`status` historique et `workflow_status` canonique) augmentent le risque de désynchronisation et de règles contradictoires.
- Contrôleurs, routes et ressources génériques multiplient les chemins d’accès et compliquent une politique d’autorisation centralisée.
- L’absence d’un cahier des charges formel rend les statuts « complet » relatifs au dépôt et aux tests, non à une obligation métier validée.
- Une partie du dossier de finalisation est historique (validation datée du 26 août 2026); ses nombres de tests et évaluations « production ready » ne remplacent pas une exécution courante.

## Plan d’action priorisé

### P0 — Bloquant sécurité

- Corriger le cloisonnement des endpoints API de candidatures, étudiants et évaluations. Auditer aussi les téléchargements, notifications, claims, statistiques et exports pour les fuites par ID direct (IDOR).
- Corriger l’enregistrement du garde-fou de modèle `User` et ajouter des tests d’élévation de privilèges.
- Ajouter des tests de sécurité à plusieurs comptes couvrant candidat A/B, évaluateur affecté/non affecté et université A/B; vérifier le refus sur index, show, filtre et export.

### P1 — Fiabilité métier et recette

- Stabiliser les tests et atteindre une suite complète verte; corriger l’initialisation de base de `StudentPortalTest` sans masquer une erreur d’application.
- Renforcer invariants financiers dans les services, transitions atomiques/verrouillées, montants non négatifs, limites cumulées et idempotence.
- Définir le workflow de commission avec les responsables métier, puis l’implémenter ou documenter le périmètre explicitement exclu.
- Faire tourner migrations, tests d’intégration et restauration sur PostgreSQL version cible; examiner les journaux et index avant recette.

### P2 — Contrats, gouvernance et exploitation

- Aligner `docs/openapi.yaml` sur les endpoints réellement publiés et valider schémas, droits et codes de réponse en CI.
- Définir les workflows métier de réclamation, messagerie, attribution/paiement, notification et journal d’audit avec critères d’acceptation.
- Finaliser CSP, HTTPS/HSTS, secrets, alertes/metrics, sauvegardes/restaurations et politiques de rétention.
- Mettre à jour `ARCHITECTURE.md`, `FINALISATION-FOSER.md` et les documents de sécurité avec des validations datées et reproductibles.

### P3 — Expérience et conformité

- Compléter anglais/français dans tous les portails, notifications et validations.
- Terminer les données GeoJSON/SVG régionales, l’accessibilité clavier/contrastes et la recette responsive sur appareils.
- Établir des tests de parcours transverses pour administrateurs, étudiants, chercheurs, universités et évaluateurs.

## Ordre exact recommandé de finalisation

1. API : restreindre immédiatement les ressources/exports et corriger le cloisonnement par compte, affectation et université.
2. Autorisation globale : réparer le listener `User`, auditer policies, middleware, routes web/API et accès Filament.
3. Tests sécurité : écrire les tests négatifs inter-comptes/tenants et les faire passer avant tout autre élargissement fonctionnel.
4. Suite de tests : corriger le setup des pages étudiant et toute autre défaillance du run complet; conserver un résultat vert reproductible.
5. Migrations et données : valider migrations, index, contraintes et restauration sur PostgreSQL cible.
6. Finance : renforcer invariants de montants, verrouillage, idempotence et rapprochement attribution-engagement-décaissement-paiement.
7. Commissions et décisions : obtenir validation métier, implémenter la gouvernance de séance/vote et relier ce module aux décisions publiées.
8. Candidatures et appels : valider règles de recevabilité, capacité, pièces, conflits, calendrier, résultats et cas concurrents.
9. Documents et données personnelles : revue des droits de lecture/téléchargement, validations de fichiers, rétention et traçabilité.
10. Réclamations et messagerie : finaliser cycle de vie, rôles participants, délais, escalade et notifications.
11. Notifications et audit : valider fournisseurs, reprises, préférences, couverture des événements et conservation des traces.
12. API/OpenAPI : stabiliser endpoints, serializers, pagination, erreurs et contrat publié après stabilisation des droits et workflows.
13. Sécurité opérationnelle : CSP, HTTPS/HSTS/proxy, secrets, observabilité, sauvegarde/restauration et revue préproduction.
14. Statistiques/dashboards : terminer carte régionale, agrégations autorisées, fraîcheur des données et besoin réel de temps réel.
15. Multilingue, accessibilité et responsive : compléter les traductions, audit clavier/lecteur d’écran/contrastes et essais mobiles.
16. Documentation et recette finale : corriger l’architecture documentaire, annexer le cahier des charges officiel et obtenir les validations métier et sécurité.

## À traiter plus tard

Le module IA est volontairement hors périmètre de cet audit et ne doit pas être modifié à ce stade. Le dépôt contient notamment `app/Services/AIService.php`, `app/Services/AI/`, `app/Services/RagService.php`, `app/Http/Controllers/AssistantController.php`, `app/Jobs/` et des routes/vues d’assistant. `FINALISATION-FOSER.md` indique qu’aucune clé ni intégration externe IA n’est activée et que le provider est local/déterministe; l’API documentée et les dépendances de service devront être auditées séparément avant toute activation. Aucune conclusion de conformité fonctionnelle IA n’est formulée ici.

## Conclusion

Le socle métier est bien avancé et plusieurs parcours essentiels existent; toutefois, la présence d’un workflow fonctionnel ne compense pas le cloisonnement insuffisant de l’API. Les travaux P0 de sécurité et la suite de tests verte doivent précéder la finalisation des modules, toute recette de production ou toute déclaration de conformité au cahier des charges.