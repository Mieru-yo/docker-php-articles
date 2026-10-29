# 3. Orchestration Docker Compose

## Service de base de donnÃ©es (phase 1)

Le [docker-compose.yml](../../docker-compose.yml) dÃ©crit le service `db`, avec le nom de conteneur `db`, l'image construite par `docker/db/Dockerfile`, le rÃ©seau bridge `app-network` et le volume nommÃ© `db-data` montÃ© sur `/var/lib/mysql`. Le port MySQL n'est pas publiÃ© sur l'hÃ´te. Le healthcheck utilise `mysqladmin ping -h 127.0.0.1`.

Les quatre variables `MYSQL_*` sont dÃ©finies dans le fichier local `.env`, Ã  crÃ©er Ã  partir de [.env.example](../../.env.example). Les mots de passe de l'exemple sont des valeurs fictives Ã  remplacer ; `.env` est ignorÃ© par Git.

Le Compose de cette Ã©tape couvre uniquement le service `db`, conformÃ©ment Ã  Y3 en phase 1. Le raccordement du service Web et son durcissement relÃ¨vent de l'intÃ©gration de phase 2.

## VÃ©rification

La configuration a Ã©tÃ© validÃ©e avec `docker compose --env-file .env.example config --quiet`, puis le service a Ã©tÃ© dÃ©marrÃ© avec `docker compose up -d --build db` :

```
$ docker compose ps
NAME   IMAGE                                      SERVICE   STATUS
db     nellotsuka/docker-php-articles-db:latest   db        Up 45 seconds (healthy)   3306/tcp, 33060/tcp
```

Le volume `db-data` et le rÃ©seau `app-network` sont crÃ©Ã©s avec ces noms exacts, et aucun port n'est publiÃ© sur l'hÃ´te (`3306/tcp` reste interne).
