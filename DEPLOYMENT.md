# Déploiement FOSER avec Docker Compose

Cette procédure cible un serveur Linux équipé de Docker Engine et Docker Compose v2. Les secrets ne doivent jamais être commités.

## 1. Installation

```sh
git clone <URL_DU_DEPOT> foser-portal
cd foser-portal
cp .env.example .env
mkdir -p docker/nginx/certs backups
chmod +x docker/entrypoint.sh scripts/backup.sh scripts/restore.sh
```

Installer Docker Engine et le plugin Compose selon la documentation officielle de la distribution. Le serveur doit exposer uniquement `80` et `443` vers Internet.

## 2. Configuration

Éditer `.env`, qui reste local et ignoré par Git :

- générer `APP_KEY` avec `docker compose run --rm app php artisan key:generate --show` ;
- définir `APP_URL`, `DB_PASSWORD`, `REDIS_PASSWORD`, `BACKUP_ENCRYPTION_KEY`, les paramètres SMTP et `SANCTUM_STATEFUL_DOMAINS` ;
- pour l’Object Storage, définir `FILESYSTEM_DISK=object`, `OBJECT_STORAGE_*` et un bucket S3 compatible ;
- conserver `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` ;
- fournir un certificat TLS Let’s Encrypt dans `TLS_CERT_DIR`, avec `fullchain.pem` et `privkey.pem`.

Vérifier les secrets avant démarrage :

```sh
grep -nE 'change-me|replace-with|APP_KEY=$' .env
```

La commande ne doit rien retourner.

## 3. Migration

```sh
docker compose build app
docker compose run --rm app php artisan migrate --force
```

Les migrations sont appliquées avant de lancer les workers.

## 4. Seed

```sh
docker compose run --rm app php artisan db:seed --force
```

Ne pas utiliser de données de démonstration en production.

## 5. Build frontend

Le build Vite est effectué dans l’image multi-stage :

```sh
docker compose build --pull app
```

Puis publier l’image avec un tag immuable :

```sh
docker tag foser-portal:latest registry.example.com/foser-portal:$GIT_SHA
docker push registry.example.com/foser-portal:$GIT_SHA
```

Définir `APP_VERSION=$GIT_SHA` dans `.env` sur le serveur.

## 6. Démarrage des workers

```sh
docker compose up -d postgres redis app nginx queue scheduler
docker compose ps
```

Le worker utilise Redis, trois tentatives, un timeout de 90 secondes et redémarre après une heure pour limiter les fuites mémoire.

## 7. Scheduler

Le service `scheduler` exécute `php artisan schedule:work`. Vérifier :

```sh
docker compose exec app php artisan schedule:list
docker compose logs --tail=100 scheduler
```

## 8. Backup

Le service de backup produit des artefacts chiffrés : dump PostgreSQL, fichiers de `storage/app` et copie du bucket Object Storage lorsqu’il est configuré. Les fichiers locaux sont conservés dans `BACKUP_DIR` pendant `BACKUP_RETENTION_DAYS` jours :

```sh
./scripts/backup.sh
```

Planifier cette commande sur l’hôte, par exemple chaque nuit à 02:00 :

```cron
0 2 * * * cd /srv/foser-portal && /usr/bin/flock -n /var/run/foser-backup.lock ./scripts/backup.sh >> /var/log/foser-backup.log 2>&1
```

Le backup est envoyé vers `BACKUP_S3_BUCKET` si cette variable est définie. Configurer sur ce bucket le versioning, le chiffrement serveur et une règle Lifecycle d’au moins 14 jours. Conserver `BACKUP_ENCRYPTION_KEY` dans un gestionnaire de secrets séparé. Tester régulièrement la restauration complète décrite dans [RESTORE.md](RESTORE.md).

## 9. Restauration

La procédure complète, incluant la restauration des documents et de l’Object Storage, est documentée dans [RESTORE.md](RESTORE.md). Elle commence par l’arrêt des workers, restaure la base avec `pg_restore`, restaure les fichiers après contrôle, puis redémarre les services et vérifie les healthchecks.

## 10. Mise à jour

```sh
git fetch --tags origin
git checkout <VERSION>
cp .env.example /tmp/foser.env.example
# conserver le .env existant et reporter uniquement les nouvelles variables

docker compose build --pull app
docker compose run --rm app php artisan down --render='errors::503'
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan optimize:clear
docker compose run --rm app php artisan config:cache
docker compose run --rm app php artisan route:cache
docker compose run --rm app php artisan view:cache
docker compose up -d --remove-orphans app nginx queue scheduler
docker compose run --rm app php artisan up
```

Contrôler `/up`, les logs, les queues et les principales pages avant de considérer la mise à jour terminée.

## 11. Rollback

Conserver au moins l’image précédente et le backup pris avant migration :

```sh
export APP_VERSION=<VERSION_PRECEDENTE>
docker compose run --rm app php artisan down --render='errors::503'
docker compose up -d --remove-orphans app nginx queue scheduler
```

Si la migration n’est pas rétrocompatible, restaurer le backup correspondant avant de remettre le trafic. Ne jamais supprimer les volumes PostgreSQL ou Redis pendant un rollback.

## Préparation Kubernetes

Compose constitue le premier déploiement. Les services `app`, `queue` et `scheduler` sont stateless et peuvent devenir des Deployments séparés; `nginx` peut devenir un Ingress ou rester un reverse proxy. PostgreSQL, Redis, les volumes et les sauvegardes doivent être fournis par des services managés ou des StatefulSets avec PersistentVolumes. Les variables `.env` sont transposables en Secrets/ConfigMaps, et les healthchecks correspondent aux probes Kubernetes. Aucun cluster Kubernetes n’est requis pour ce déploiement Compose.

## HTTPS et certificats

Nginx redirige HTTP vers HTTPS et exige :

```text
/etc/nginx/certs/fullchain.pem
/etc/nginx/certs/privkey.pem
```

Renouveler le certificat hors conteneur avec Certbot, puis recharger Nginx :

```sh
docker compose exec nginx nginx -s reload
```

## Logs et supervision

Les conteneurs utilisent le driver Docker `json-file` avec rotation à `10m` et cinq fichiers. Laravel écrit en `stderr` en production. Centraliser les logs Docker vers la solution de supervision de l’exploitation et surveiller : erreurs 5xx, `failed_jobs`, saturation disque, PostgreSQL, Redis et statut des healthchecks.

## Health check

- Laravel : `GET /up` ;
- Nginx : `GET /healthz` ;
- PostgreSQL et Redis : healthchecks Compose.

Les healthchecks ne contiennent aucun secret en clair dans les logs.
