# Installation FOSER

## Prerequis

- Docker Desktop avec Compose v2
- PHP 8.2+ et Composer 2 pour un developpement hors Docker
- Node.js 20+ pour Vite

## Installation Docker

Copier `.env.example` vers `.env`, definir une valeur secrete pour `APP_KEY` et renseigner le mot de passe PostgreSQL.

```powershell
Copy-Item .env.example .env
php artisan key:generate
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize:clear
```

Le portail est disponible sur `http://127.0.0.1:8000` et Filament sur `/admin`.

Pour traiter les queues Redis :

```powershell
docker compose exec app php artisan queue:work redis --tries=3
```

## Installation locale XAMPP

Verifier que les extensions `pdo_pgsql` et `redis` sont activees, puis configurer les variables `DB_*` et `REDIS_*` dans `.env`.

```powershell
composer install
npm install
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve
```

## Verifications

```powershell
php artisan optimize:clear
php artisan migrate:status
php artisan route:list
php artisan filament:about
php artisan test
```

Aucun compte administrateur ni donnees metier fictives n'est cree automatiquement. Un utilisateur doit etre cree dans un environnement controle, puis son adresse email doit etre verifiee pour acceder au panneau Filament.
