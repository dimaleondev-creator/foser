# Restauration FOSER

Cette procédure restaure une sauvegarde chiffrée dans un environnement Docker Compose. Elle doit être testée régulièrement sur un environnement isolé.

## Pré-requis

- Docker Engine et Compose v2;
- un fichier `.env` valide, sans secret dans le dépôt;
- `BACKUP_ENCRYPTION_KEY` identique à celle utilisée lors de la sauvegarde;
- le fichier `foser-YYYYMMDD-HHMMSS.dump.enc` et, si nécessaire, l'archive de fichiers correspondante;
- un accès aux identifiants PostgreSQL et à l'Object Storage.

Ne jamais afficher la clé de chiffrement dans les logs ou la ligne de commande.

## 1. Préparer le service

```sh
docker compose up -d postgres redis app
docker compose ps
```

Vérifier les healthchecks avant de continuer.

## 2. Restaurer PostgreSQL

Arrêter les consommateurs de données :

```sh
docker compose stop queue scheduler
```

Restaurer le dump custom chiffré :

```sh
./scripts/restore.sh backups/foser-YYYYMMDD-HHMMSS.dump.enc
```

La commande utilise `pg_restore --clean --if-exists --no-owner`. Elle remplace les données présentes dans la base cible. Vérifier le nom de la base et l’environnement avant exécution.

## 3. Restaurer les documents locaux

L’archive de fichiers est chiffrée séparément par le conteneur de backup. La déchiffrer dans un répertoire temporaire protégé, puis restaurer uniquement `storage/app` :

```sh
mkdir -p restore-tmp
openssl enc -d -aes-256-cbc -pbkdf2 \
  -in backups/foser-YYYYMMDD-HHMMSS-files.tar.gz.enc \
  -out restore-tmp/files.tar.gz \
  -pass env:BACKUP_ENCRYPTION_KEY

tar -xzf restore-tmp/files.tar.gz -C restore-tmp
# contrôler le contenu avant remplacement
rsync -a restore-tmp/app/ storage/app/
rm -rf restore-tmp
```

Sur le serveur, le chemin cible doit correspondre au volume `app_storage` monté par Compose. Ne jamais restaurer une archive non vérifiée directement dans le webroot.

## 4. Restaurer l’Object Storage

Si l’application utilise `FILESYSTEM_DISK=object`, l’archive `object-storage` contient une copie du bucket au moment du backup. Après déchiffrement et contrôle, synchroniser son contenu vers un bucket de restauration ou le bucket cible avec les identifiants S3 de l’environnement :

```sh
aws --endpoint-url "$OBJECT_STORAGE_ENDPOINT" s3 sync restore-tmp/object-storage/ "s3://$OBJECT_STORAGE_BUCKET/"
```

Pour un fournisseur AWS standard, retirer `--endpoint-url`. Vérifier les permissions, le chiffrement serveur et la versioning policy du bucket avant la synchronisation.

## 5. Vérifier et remettre en service

```sh
docker compose run --rm app php artisan migrate:status
docker compose run --rm app php artisan optimize:clear
docker compose run --rm app php artisan config:cache
docker compose up -d queue scheduler nginx
docker compose ps
docker compose logs --tail=100 app queue scheduler nginx
```

Contrôler `/up`, la connexion administrateur, une lecture documentaire autorisée, les jobs en attente et les journaux. Supprimer les fichiers temporaires après vérification.

## Rétention et sécurité

La rétention locale est contrôlée par `BACKUP_RETENTION_DAYS`. Le bucket de backup doit appliquer une règle Lifecycle équivalente, avec versioning et chiffrement côté serveur. La clé `BACKUP_ENCRYPTION_KEY` doit être conservée séparément des sauvegardes, idéalement dans un gestionnaire de secrets.

Une restauration complète doit être exercée au moins trimestriellement et documenter la durée réelle de reprise (RTO) et la perte maximale acceptable de données (RPO).
