# API REST FOSER

## Contrat

Les routes sont servies sous `/api`. Les nouvelles intégrations doivent privilégier `/api/v1`. L’API utilise Sanctum bearer tokens pour les ressources privées; les catalogues explicitement publics sont sans authentification. Les workflows historiques `/api/candidate`, `/api/evaluator` et `/api/applications` restent disponibles pour compatibilité.

Réponse JSON standard :

```json
{
  "success": true,
  "data": [],
  "message": null,
  "errors": null,
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 0
  }
}
```

`pagination` vaut `null` pour une réponse sans pagination. Les réponses paginées conservent temporairement `meta` comme alias de compatibilité; les nouveaux clients doivent lire `pagination`. Une réponse 204 (révocation du token) n’a pas de corps. Les erreurs JSON ont les mêmes clés, `success: false`, `data: null`, un message et, pour les erreurs 422, un objet `errors` indexé par champ. Les téléchargements restent des flux binaires, pas des enveloppes JSON.

`meta` est déprécié et sera retiré dans une prochaine version majeure. Les clients nouveaux doivent uniquement consommer `pagination`.

Les collections acceptent `page` et `per_page` (plafond 100 pour données authentifiées, 50 pour catalogues publics). Les endpoints documentés indiquent les filtres et la recherche pris en charge. Les filtres non listés ne sont pas garantis.

## Authentification

| Méthode | Endpoint | Accès |
|---|---|---|
| POST | `/api/v1/auth/login` | Public, 6 tentatives/minute; seuls les comptes actifs non-staff obtiennent un token |
| GET | `/api/v1/auth/me` | Sanctum |
| GET | `/api/v1/users/me` | Sanctum; alias de `auth/me` |
| DELETE | `/api/v1/auth/logout` | Sanctum; révoque le token courant, réponse 204 |
| GET | `/api/user` | Sanctum; compatibilité, réponse limitée par `UserResource` |

Les comptes staff soumis à la MFA Filament ne peuvent pas obtenir un token par simple mot de passe via le login API.

## Ressources authentifiées

Les routes de collection et de détail utilisent généralement `GET /api/v1/{ressource}` et `GET /api/v1/{ressource}/{id}`. Elles nécessitent Sanctum, le throttle API, la permission affichée et une Resource JSON dédiée.

| Ressource | Permission | Recherche/filtres principaux | Restriction objet |
|---|---|---|---|
| `students` | `users.view` | recherche nom/courriel, statut; pagination | Université limitée à son tenant; autre appelant staff autorisé; INEE masqué aux universités |
| `universities` | `university.view` | nom, code, pays, statut, pays | Champs publics/institutionnels limités par `UniversityResource` |
| `researchers` | `research.view` | numéro, ORCID, spécialité, domaine; université/rang | Université limitée à ses chercheurs; chercheur limité à son profil |
| `programs` | `programs.view` | recherche, type/statut/devise | Appels non-staff limités aux programmes publiés |
| `calls` | `calls.view` | recherche/référence/description, programme/statut/devise | Appels brouillons/futurs masqués aux rôles externes |
| `applications` | `applications.view` | référence/statut, appel/programme/candidat | Étudiant propriétaire, université du tenant, évaluateur affecté ou staff; réponse adaptée au rôle |
| `documents` | `documents.view` | titre/auteur/type/langue, catégorie/année/statut | `DocumentAccessService`; fichiers servis par endpoint dédié |
| `evaluations` | `evaluations.view` | liste/détail | Évaluateur limité à ses affectations |
| `research-projects` | `research.view` | liste/détail | Propriétaire ou membre du projet |
| `payments` | `finance.view` | bénéficiaire/statut/décaissement | Réservé au personnel finance autorisé |
| `notifications` | Sanctum | liste/détail | Propriétaire du message |
| `claims` | `applications.view` | liste/détail | Propriétaire étudiant ou staff autorisé |

Le téléchargement de document se fait par `GET /api/v1/documents/{id}/download`, avec permission `documents.view` et contrôle de visibilité/ownership au service d’accès.

## Résultats et finances

| Méthode | Endpoint | Permission et portée |
|---|---|---|
| GET | `/api/v1/results` | Résultats publiés; propriétaire, université tenant, évaluateur affecté ou staff `applications.view` |
| GET | `/api/v1/awards` | Attribution active du bénéficiaire connecté ou liste pour `finance.view` |
| GET | `/api/v1/disbursements` | `finance.view`; filtres `status`, `application_id`; `per_page` maximum 100 |
| GET | `/api/v1/statistics/financial` | `view_statistics` et `view_financial_statistics` |
| GET | `/api/v1/statistics/export` | `view_statistics` et `export_statistics` |

Les Resources financières ne comprennent que les champs nécessaires à l’usage API; les routes de consultation financière ne sont pas accordées aux étudiants ou partenaires.

## Catalogues publics

Ces endpoints sont soumis au throttle API et ne retournent que les enregistrements publics publiés. Aucun chemin de stockage privé n’est inclus.

| Méthode | Endpoint | Recherche et filtres |
|---|---|---|
| GET | `/api/v1/events` | `q`, `category`, pagination (maximum 50) |
| GET | `/api/v1/partners` | `q`, `category`, pagination (maximum 50) |
| GET | `/api/v1/testimonials` | `q`, pagination (maximum 50), consentement requis |
| GET | `/api/v1/news` | `q`, `category`, pagination; actualités publiques publiées |
| GET | `/api/v1/news/{slug}` | Détail d’une actualité publique publiée |
| GET | `/api/v1/public/documents` | `q`, `category`, `year`, `language`, pagination; fichier jamais exposé en chemin direct |
| GET | `/api/v1/search` | `q` obligatoire, `type` optionnel, pagination (maximum 50) |
| GET | `/api/v1/statistics/regions` | Agrégats régionaux publics, paramètre `year` |

## Espaces chercheurs et universités

### Chercheur

Tous les endpoints requièrent Sanctum, un rôle chercheur autorisé, `research.view` et le throttle API.

- `GET /api/v1/researchers/me`
- `GET /api/v1/researchers/projects` avec `page`, `per_page` (maximum 100), `status`, `search`
- `POST /api/v1/researchers/projects` (`research.manage`)
- `GET /api/v1/researchers/projects/{id}`; `PUT` pour modifier un brouillon; `POST /submit`
- `GET /api/v1/researchers/publications` avec pagination, statut et recherche
- `GET /api/v1/researchers/calls`
- `GET /api/v1/researchers/projects/{id}/evaluations`

Les données de profil et projet passent par des Resources, et les projets sont limités au propriétaire/membre.

### Université

Tous les endpoints requièrent Sanctum, `role.dashboard:universite`, `university.view` et le throttle API.

- `GET /api/v1/university/me`
- `GET /api/v1/university/students` et `GET /api/v1/university/students/{user_id}` (`university.students.view`)
- `GET /api/v1/university/applications`
- `POST /api/v1/university/applications/{id}/validate` (`university.applications.validate`)
- `POST /api/v1/university/applications/{id}/request-correction` (`university.applications.correction`)
- `POST /api/v1/university/applications/{id}/reject` (`university.applications.reject`)
- `POST /api/v1/university/imports` (`university.imports.create`, fichier limité à 20 Mo et types tabulaires autorisés)
- `GET /api/v1/university/statistics` (`university.reports.view`)

Les listes et détails sont limités à l’université associée. Les Resources étudiants ne renvoient ni INEE, date de naissance, téléphone, adresse ni identifiant national.

## Workflows historiques

Ils restent hors préfixe `/v1` pour compatibilité. Ils sont protégés par Sanctum, rôle, permissions, validation, ownership et/ou tenant scope.

- Étudiant : `/api/candidate/candidatures` (GET/POST), `/api/candidate/candidatures/{id}` (GET/PUT), `/api/candidate/candidatures/{id}/submit` (POST).
- Évaluateur : `/api/evaluator/assignments` (GET), `/api/evaluator/assignments/{id}` (GET), sous-actions `/start`, `/evaluation`, `/conflict` (POST).
- Workflow institutionnel : `/api/applications/{id}/{verify,assign-evaluators,commission,decision,publish-result,award,commit,disburse}`; chaque transition exige sa permission spécifique.

## Spécification

Le contrat OpenAPI v3 est dans [openapi.yaml](openapi.yaml). La section assistant qui y existe est historique et hors périmètre de cette finalisation; cette documentation ne modifie pas ni ne décrit le comportement IA.

## Limites de surface

- Les routes CRUD versionnées sont majoritairement en lecture; les écritures passent par les workflows spécialisés et refusent les changements de statut hors transitions autorisées.
- Aucun endpoint d’administration générique pour créer/modifier/supprimer des utilisateurs n’est exposé par l’API.
- Les paiements sont consultables par rôle finance; les décaissements sont limités à `finance.view`; les résultats et attributions suivent le propriétaire, le tenant ou le rôle finance explicité ci-dessus.
- Aucun endpoint public ne renvoie de chemins physiques de stockage; l’accès aux fichiers utilise des URLs de téléchargement contrôlées.
- La révocation vise le token courant (`DELETE /api/v1/auth/logout`); aucune route de révocation arbitraire par identifiant de token n’est fournie.

## Validation automatisée

La spécification est parsée par un test Symfony YAML. La suite API couvre enveloppes succès/erreur, validation, Sanctum, expiration de token, rate limiting, scopes IDOR étudiant/université/chercheur/évaluateur, résultats/attributions/décaissements, catalogues publics, recherche, filtres et pagination. Dernière validation globale : **241 tests réussis, 1 169 assertions**; lot API ciblé : **79 tests réussis, 434 assertions**.
