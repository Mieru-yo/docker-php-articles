# 2b. Image Docker de la base de données

## Dockerfile

Le fichier [docker/db/Dockerfile](../../docker/db/Dockerfile) part de l'image officielle `mysql:8.4`. Il copie le script d'initialisation sous `/docker-entrypoint-initdb.d/10-articles.sql`, emplacement traité par l'entrypoint officiel lors de l'initialisation d'un répertoire de données vide.

Le script SQL ne crée que la table `articles`. La base et l'utilisateur applicatif sont créés par l'image officielle à partir des variables `MYSQL_DATABASE`, `MYSQL_USER` et `MYSQL_PASSWORD` fournies par Compose. La table utilise InnoDB et l'encodage `utf8mb4`.

Le fichier appartient à `root` et est lisible sans être modifiable (`0644`). Aucun mot de passe n'est incorporé à l'image. Le contexte de build reste la racine du dépôt afin de pouvoir copier `db/init/articles.sql`.

## Vérification

Le démon Docker Desktop Linux n'était pas démarré dans l'environnement de travail ; la construction et le démarrage du conteneur n'ont donc pas pu être vérifiés. Aucune sortie de construction ou de démarrage n'est présentée comme un résultat.
