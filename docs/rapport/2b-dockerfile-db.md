# 2b. Image Docker de la base de données

## Dockerfile

Le fichier [docker/db/Dockerfile](../../docker/db/Dockerfile) part de l'image officielle `mysql:8.4`. Il copie le script d'initialisation sous `/docker-entrypoint-initdb.d/10-articles.sql`, emplacement traité par l'entrypoint officiel lors de l'initialisation d'un répertoire de données vide.

Le script SQL ne crée que la table `articles`. La base et l'utilisateur applicatif sont créés par l'image officielle à partir des variables `MYSQL_DATABASE`, `MYSQL_USER` et `MYSQL_PASSWORD` fournies par Compose. La table utilise InnoDB et l'encodage `utf8mb4`.

Le fichier appartient à `root` et est lisible sans être modifiable (`0644`). Aucun mot de passe n'est incorporé à l'image. Le contexte de build reste la racine du dépôt afin de pouvoir copier `db/init/articles.sql`.

## Vérification

Image construite et démarrée avec `docker compose up -d --build db` (fichier `.env` copié depuis `.env.example`) :

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

La base `articles` et l'utilisateur `app` sont donc bien créés par l'image MySQL, et le script ne crée que la table.
