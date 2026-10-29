# 3. Orchestration Docker Compose

## Service de base de données (phase 1)

Le [docker-compose.yml](../../docker-compose.yml) décrit le service `db`, avec le nom de conteneur `db`, l'image construite par `docker/db/Dockerfile`, le réseau bridge `app-network` et le volume nommé `db-data` monté sur `/var/lib/mysql`. Le port MySQL n'est pas publié sur l'hôte. Le healthcheck utilise `mysqladmin ping -h 127.0.0.1`.

Les quatre variables `MYSQL_*` sont définies dans le fichier local `.env`, à créer à partir de [.env.example](../../.env.example). Les mots de passe de l'exemple sont des valeurs fictives à remplacer ; `.env` est ignoré par Git.

Le Compose de cette étape couvre uniquement le service `db`, conformément à Y3 en phase 1. Le raccordement du service Web et son durcissement relèvent de l'intégration de phase 2.

## Vérification

La configuration statique a été validée avec `docker compose --env-file .env.example config --quiet`. Le démarrage demandé avec `docker compose --env-file .env.example up db` n'a pas pu être vérifié : le démon Docker Desktop Linux était indisponible dans l'environnement de travail.
