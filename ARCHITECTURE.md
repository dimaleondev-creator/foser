# Architecture FOSER

## Positionnement

FOSER est un portail institutionnel Laravel 12. Le socle est prepare pour separer le site public, l'API SPA et l'administration sans introduire de tables metier avant validation des processus.

## Socle actuel

- **Backend** : Laravel 12, PHP 8.2+.
- **API** : Laravel Sanctum, routes dans `routes/api.php`.
- **Administration** : Filament 4, panneau `/admin`.
- **Permissions** : Spatie Laravel Permission, guard `web`.
- **Donnees** : PostgreSQL 16 en Docker; migrations Laravel reversibles.
- **Cache et queues** : Redis 7 prepare pour cache et workers.
- **Notifications** : canal Laravel database prepare par migration.
- **Frontend** : Vite comme point d'integration actuel; une SPA React/TypeScript pourra etre ajoutee dans `resources/js` sans coupler le domaine.

## Limites volontaires

Aucune table metier FOSER, ressource Filament ou regle de domaine n'est ajoutee a ce stade. Les seules migrations supplementaires concernent l'infrastructure Laravel, Sanctum, les permissions et les notifications.

## Organisation cible

- `app/Domain/<BoundedContext>` : regles et objets metier par contexte.
- `app/Application` : cas d'utilisation et orchestration.
- `app/Infrastructure` : integrations externes, stockage et notifications.
- `app/Filament` : administration, uniquement comme adaptateur de presentation.
- `routes/api.php` : contrat API versionnable.
- `resources/js` : client web, localisable en francais et anglais.

Les controllers doivent rester minces, les donnees doivent etre validees par Form Requests, et les actions longues doivent passer par la queue Redis.

## Securite

- Authentification web par session et API par Sanctum.
- Acces Filament limite aux utilisateurs dont l'adresse est verifiee.
- Autorisation metier via roles et permissions Spatie, jamais via des conditions dispersees dans les vues.
- Secrets uniquement dans `.env`; `.env` est ignore par Git.
- Les fichiers prives restent hors du repertoire public.
