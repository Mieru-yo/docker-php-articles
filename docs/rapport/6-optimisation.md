# 6. Optimisation de l'image Web

## Constat de départ

Après la partie 5, l'image Web ne contient plus d'outils de compilation, mais sa taille n'a pas baissé (mesures `docker images` et `docker image inspect`) :

| Image | Compressée | Sur disque | Couches |
|---|---|---|---|
| avant la partie 5 | 175,9 Mo | 708 Mo | 20 |
| après la partie 5 | 176,1 Mo | 709 Mo | 20 |

Une image Docker est un empilement de couches en lecture seule. Supprimer un fichier dans une couche ne fait que le masquer : il reste stocké dans la couche de `php:8.2-apache` où il a été créé. Les outils de compilation supprimés en partie 5 sont donc toujours téléchargés.

## Choix de l'image de base

| Option | Retenue | Raison |
|---|---|---|
| `php:8.2-apache` (Debian) | ✅ | Image officielle demandée par le sujet, Apache et PHP dans un seul processus. |
| `php:8.2-fpm-alpine` | ❌ | Image plus petite, mais PHP-FPM a besoin d'un serveur Web séparé (Nginx) : un deuxième service, ou deux processus dans le même conteneur. Changement d'architecture non justifié pour cette application. |

L'optimisation porte donc sur le contenu de l'image, pas sur la base.

## Multi-stage build

Le Dockerfile est découpé en deux étapes :

1. **Étape `build`** (`FROM php:8.2-apache`) : compilation des extensions, suppression des outils de compilation, des en-têtes PHP, de la documentation et des pages de manuel, configuration d'Apache et de PHP, copie du code.
2. **Étape finale** (`FROM scratch`) : image vide dans laquelle on copie le système de fichiers final de l'étape 1 (`COPY --from=build / /`). Les fichiers supprimés n'existent plus dans aucune couche.

`COPY --from=build / /` ne reprend pas la configuration de l'image (variables, point d'entrée…). Elle est redéclarée à partir de celle de `php:8.2-apache` : `PATH`, `PHP_INI_DIR`, `APACHE_CONFDIR`, `APACHE_ENVVARS`, `WORKDIR`, `STOPSIGNAL SIGWINCH`, `ENTRYPOINT ["docker-php-entrypoint"]`, `CMD ["apache2-foreground"]`.

Nettoyage effectué dans l'étape 1 :

| Supprimé | Pourquoi |
|---|---|
| `gcc`, `g++`, `make`, `autoconf`, `libc6-dev`, `linux-libc-dev`… | Outils de compilation, inutiles une fois les extensions compilées |
| `/usr/local/include/php`, `/usr/local/lib/php/build` | En-têtes et scripts de compilation d'extensions PHP |
| `/usr/src/php.tar.xz` | Archive des sources de PHP |
| `/var/lib/apt/lists/*`, `/var/cache/apt/*` | Listes et cache des paquets `apt` |
| `/usr/share/doc/*`, `/usr/share/man/*`, `/usr/share/info/*` | Documentation |

## Résultat

Tailles mesurées avec `docker image inspect --format '{{.Size}}'` (compressée), `docker images` (sur disque) et `docker image inspect --format '{{len .RootFS.Layers}}'` (couches) :

| | Taille compressée (téléchargée depuis Docker Hub) | Taille sur disque | Couches |
|---|---|---|---|
| Avant | 176,1 Mo | 709 Mo | 20 |
| Après | 62,6 Mo | 276 Mo | 2 |
| Gain | **−64 %** | **−61 %** | |

Le contenu de l'image est identique à celui de la partie 5 : le scan Trivy donne le même résultat.

```
$ trivy image --scanners vuln --severity CRITICAL,HIGH docker-php-articles-web:apres-j4
docker-php-articles-web:apres-j4 (debian 13.7)
Total: 59 (HIGH: 58, CRITICAL: 1)
```

## `.dockerignore`

Le contexte de build est la racine du dépôt. Le `.dockerignore` fonctionne en liste blanche :

```
*
!app/
!db/init/
!docker/web/apache-security.conf
!docker/web/php-security.ini
```

Seuls le code PHP, le script SQL et les deux fichiers de configuration du service Web sont envoyés au daemon. Le dossier `.git`, la documentation, les captures d'écran et le fichier `.env` (mots de passe) ne peuvent pas se retrouver dans une image par erreur.

## Vérification avec la stack complète

L'image optimisée a été testée avec le `docker-compose.yml` du projet :

```
$ docker compose ps
NAME      IMAGE                                       STATUS                    PORTS
db        nellotsuka/docker-php-articles-db:latest    Up 31 seconds (healthy)   3306/tcp, 33060/tcp
web       nellotsuka/docker-php-articles-web:latest   Up 8 seconds (healthy)    127.0.0.1:8095->80/tcp

$ curl -s -o /dev/null -w 'ajout: HTTP %{http_code}\n' -X POST http://127.0.0.1:8095/validation.php -d 'title=Test J4&author=Jeremy&content=Image aplatie'
ajout: HTTP 302

$ docker exec web id
uid=33(www-data) gid=33(www-data) groups=33(www-data)

$ docker exec web sh -c 'touch /var/www/html/x'
touch: cannot touch '/var/www/html/x': Read-only file system

$ docker stop web
web

$ docker inspect web --format '{{.State.ExitCode}}'
0
```

L'arrêt en 2 secondes avec le code 0 confirme que le signal d'arrêt `SIGWINCH` redéclaré est bien transmis à Apache.
