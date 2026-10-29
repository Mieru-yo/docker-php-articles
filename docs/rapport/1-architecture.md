# 1. Architecture

## Vue d'ensemble

Le schéma [architecture.png](../architecture.png) présente l'architecture de la stack ; sa source éditable est [architecture.drawio](../architecture.drawio).

La stack est composée de deux services isolés sur le réseau bridge `app-network` :

- `web` (`container_name: web`) exécute l'application PHP 8.2 sous Apache. Le port `8080` de l'hôte est publié vers le port `80` du conteneur.
- `db` (`container_name: db`) exécute MySQL 8.4. La base `articles` est initialisée avec la table `articles`. Aucun port MySQL n'est publié sur l'hôte.

L'application rejoint la base sur le réseau interne à l'adresse `db:3306`. Le volume nommé `db-data` conserve les données MySQL dans `/var/lib/mysql` lorsque les conteneurs sont remplacés.

## Configuration

Les paramètres MySQL sont conservés dans le fichier local `.env`, créé à partir de `.env.example` et exclu du contrôle de version. Compose configure le serveur avec les variables `MYSQL_*` et transmet au service Web leurs équivalents applicatifs `DB_*`. Le code PHP ne contient donc aucun identifiant de connexion fixe.

## Contrat retenu

Les noms des services, du réseau et du volume, les ports, les versions des images et les variables d'environnement suivent le contrat commun décrit dans [ORGANISATION.md](../ORGANISATION.md).
