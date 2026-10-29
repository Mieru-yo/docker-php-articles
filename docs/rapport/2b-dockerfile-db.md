# 2b. Image Docker de la base de donnÃ©es

## Dockerfile

Le fichier [docker/db/Dockerfile](../../docker/db/Dockerfile) part de l'image officielle `mysql:8.4`. Il copie le script d'initialisation sous `/docker-entrypoint-initdb.d/10-articles.sql`, emplacement traitÃ© par l'entrypoint officiel lors de l'initialisation d'un rÃ©pertoire de donnÃ©es vide.

Le script SQL ne crÃ©e que la table `articles`. La base et l'utilisateur applicatif sont crÃ©Ã©s par l'image officielle Ã  partir des variables `MYSQL_DATABASE`, `MYSQL_USER` et `MYSQL_PASSWORD` fournies par Compose. La table utilise InnoDB et l'encodage `utf8mb4`.

Le fichier appartient Ã  `root` et est lisible sans Ãªtre modifiable (`0644`). Aucun mot de passe n'est incorporÃ© Ã  l'image. Le contexte de build reste la racine du dÃ©pÃ´t afin de pouvoir copier `db/init/articles.sql`.

## VÃ©rification

Image construite et dÃ©marrÃ©e avec `docker compose up -d --build db` (fichier `.env` copiÃ© depuis `.env.example`) :

```
$ docker compose ps
NAME   IMAGE                                      SERVICE   STATUS
db     nellotsuka/docker-php-articles-db:latest   db        Up 45 seconds (healthy)   3306/tcp, 33060/tcp

$ echo "SHOW TABLES;" | docker exec -i db sh -c 'mysql -uapp -p"$MYSQL_PASSWORD" articles'
Tables_in_articles
articles
```

La structure obtenue confirme InnoDB et `utf8mb4` :

```
CREATE TABLE `articles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `author` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

La base `articles` et l'utilisateur `app` sont donc bien crÃ©Ã©s par l'image MySQL, et le script ne crÃ©e que la table.
