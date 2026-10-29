# 2.1 Dockerfile du service Web

Fichier : `docker/web/Dockerfile`, construit depuis la racine du dépôt :

```bash
docker build -t docker-php-articles-web -f docker/web/Dockerfile .
```

## Corrections préalables du code PHP

Avant de conteneuriser l'application, trois problèmes du code fourni ont été corrigés :

| Problème | Correction |
|---|---|
| Hôte `localhost` et identifiants `test/test` codés en dur dans `db-config.php` : impossible de joindre le conteneur `db`. | Lecture de `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` depuis l'environnement. L'application refuse de démarrer si une variable manque. |
| `validation.php` testait `$_POST["title"]` au lieu de `$_POST["author"]`. | Condition corrigée. |
| Titre, contenu et auteur affichés sans échappement : un article contenant `<script>` était exécuté (faille XSS). Les erreurs PHP et le message PDO (hôte, utilisateur) étaient affichés au visiteur. | `htmlspecialchars` sur chaque valeur affichée, `display_errors` désactivé, message d'erreur neutre (le détail part dans les logs du conteneur). |

## Justification des instructions

| Instruction | Justification |
|---|---|
| `FROM php:8.2-apache AS build` | Image officielle PHP 8.2 avec Apache intégré, demandée par le sujet. Apache et PHP dans le même conteneur : un seul service Web à gérer. Sert d'étape de préparation (multi-stage, voir partie 6). |
| `RUN docker-php-ext-install pdo_mysql mysqli && apt-get purge … && rm -rf …` | Extensions nécessaires pour se connecter à MySQL (`pdo_mysql` est utilisée par l'application, `mysqli` est demandée par le sujet). Dans la même couche, les outils de compilation fournis par l'image (`gcc`, `make`, en-têtes du noyau…) sont supprimés, ainsi que les listes et le cache `apt` (`rm -rf /var/lib/apt/lists/* /var/cache/apt/*`, l'équivalent de `apt-get clean`) et les sources PHP : ils ne servent plus et ajoutaient 61 vulnérabilités (voir partie 5). |
| `COPY … zz-security-hardening.conf` + `a2enconf` | Configuration Apache : `ServerTokens Prod` et `ServerSignature Off` masquent la version d'Apache, `ServerName` supprime l'avertissement au démarrage. Le préfixe `zz-` la fait charger après `security.conf` de Debian, qui sinon remettait la version complète. |
| `COPY … zz-security.ini` | `expose_php = Off` : supprime l'en-tête `X-Powered-By` qui révélait la version de PHP. |
| Configuration Apache par défaut conservée | Le `DocumentRoot` de l'image est déjà `/var/www/html`, et les modules nécessaires sont déjà chargés : `php_module` (exécute le PHP), `mpm_prefork_module` (obligatoire avec `mod_php`), `dir_module` (sert `index.php`). Aucun module supplémentaire n'est utile à l'application. |
| `mv php.ini-production php.ini` | Configuration PHP de production (erreurs non affichées, limites adaptées). |
| `COPY --chown=root:root --chmod=644 app/ /var/www/html/` | Copie le code dans le DocumentRoot par défaut d'Apache. Les fichiers appartiennent à `root` et sont en lecture seule pour `www-data` : même si l'application était compromise, le processus Web ne pourrait pas modifier le code. |
| `FROM scratch` + `COPY --from=build / /` | Étape finale : le système de fichiers préparé est copié dans une image vide. Les fichiers supprimés pendant la préparation ne restent dans aucune couche : l'image passe de 176 à 63 Mo compressés (partie 6). |
| `ENV PATH=… PHP_INI_DIR=… APACHE_CONFDIR=… APACHE_ENVVARS=…`, `WORKDIR /var/www/html` | La copie ne reprend pas la configuration de l'image : on redéclare celle de `php:8.2-apache` dont PHP et Apache ont besoin. |
| `USER www-data` | Apache tourne en utilisateur non-root. Une faille dans l'application ne donne pas les droits root dans le conteneur. |
| `EXPOSE 80` | Documente le port d'écoute d'Apache. Pour qu'un utilisateur non-root puisse écouter sur le port 80, le Compose ajoute le `sysctl` `net.ipv4.ip_unprivileged_port_start=0` (voir partie 3). |
| `STOPSIGNAL SIGWINCH` | Signal d'arrêt propre d'Apache, repris de l'image officielle : Apache termine les requêtes en cours avant de s'arrêter. |
| `HEALTHCHECK … curl -fsS http://localhost/` | Docker vérifie toutes les 30 s que la page répond. La page répond en 200 même si la base est indisponible : l'état du conteneur Web reste indépendant de celui de la base. |
| `ENTRYPOINT ["docker-php-entrypoint"]`, `CMD ["apache2-foreground"]` | Point d'entrée et commande de l'image officielle : Apache est lancé au premier plan, comme processus principal du conteneur. |

Configuration Apache vérifiée dans le conteneur :

```
$ docker exec web sh -c "grep -h DocumentRoot /etc/apache2/sites-enabled/*"
	DocumentRoot /var/www/html

$ docker exec web apache2ctl -M
Loaded Modules:
 core_module (static)
 ...
 dir_module (shared)
 ...
 mpm_prefork_module (shared)
 ...
 php_module (shared)
 ...
```

## Pourquoi pas de `chmod 777`

`chmod 777` donne à **tous** les utilisateurs le droit de lire, modifier et exécuter les fichiers. Dans un conteneur Web, un attaquant qui exploite une faille de l'application pourrait alors remplacer le code PHP (par exemple déposer une porte dérobée). Ici, le code appartient à `root` en `644` : `www-data` peut seulement le lire.

## `.dockerignore`

Le contexte de build est la racine du dépôt, partagée avec l'image de la base. Le `.dockerignore` fonctionne en liste blanche : tout est exclu (`*`), sauf `app/`, `db/init/` et les deux fichiers de configuration du service Web. Le `.git`, la documentation et surtout le fichier `.env` (mots de passe) ne sont jamais envoyés au daemon Docker.

## Tests

Image lancée avec le durcissement prévu pour le Compose (`--read-only`, `tmpfs`, `--cap-drop ALL`, `sysctl`, `no-new-privileges`) et une base MySQL 8.4 temporaire :

```
$ docker exec j2test-web id
uid=33(www-data) gid=33(www-data) groups=33(www-data)

$ curl -s -o /dev/null -w 'HTTP %{http_code}\n' -X POST http://127.0.0.1:8090/validation.php -d 'title=Test J2&author=Jeremy&content=Image Web durcie'
HTTP 302

$ docker exec j2test-web sh -c 'touch /var/www/html/pirate.php'
touch: cannot touch '/var/www/html/pirate.php': Read-only file system

$ docker inspect j2test-web --format '{{.State.Health.Status}}'
healthy

$ curl -sI http://127.0.0.1:8090/ | grep -i -E "^(HTTP|server|x-powered-by)"
HTTP/1.1 200 OK
Server: Apache
```
