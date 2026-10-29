# 5. Sécurité et bonnes pratiques

## Scan de vulnérabilités avec Trivy

L'image Web est scannée avec Trivy, lancé depuis son image officielle (aucune installation sur la machine) :

```bash
docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy:latest \
  image --scanners vuln --severity CRITICAL,HIGH docker-php-articles-web:latest
```

### Image Web : avant correction

```
docker-php-articles-web:avant-j3 (debian 13.7)
==============================================
Total: 120 (HIGH: 117, CRITICAL: 3)
```

| Paquet | Vulnérabilités | Correctif disponible |
|---|---|---|
| `linux-libc-dev` (en-têtes du noyau) | 61, dont 2 CRITICAL | non |
| `libxml2` | 8, dont 1 CRITICAL | non |
| autres (`bsdutils`, `libblkid1`, `libmount1`…) | 51 | non |

Aucune des 120 vulnérabilités n'a de version corrigée publiée dans Debian 13 : un `apt-get upgrade` ne change rien. La correction consiste donc à **supprimer les paquets inutiles** à l'exécution.

### Image Web : correction

L'image officielle `php:8.2-apache` conserve tous les outils de compilation (`$PHPIZE_DEPS` : `gcc`, `g++`, `make`, `autoconf`…, plus `libc6-dev` et `linux-libc-dev`) pour pouvoir compiler des extensions. Une fois `pdo_mysql` et `mysqli` compilées, ils ne servent plus et augmentent la surface d'attaque. Ils sont supprimés dans la même instruction `RUN` que la compilation :

```dockerfile
RUN docker-php-ext-install pdo_mysql mysqli \
    && apt-get purge -y --auto-remove \
        autoconf dpkg-dev file g++ gcc make pkg-config re2c libc6-dev \
    && rm -rf /var/lib/apt/lists/* /tmp/* /usr/src/php.tar.xz
```

En plus de réduire les vulnérabilités, un attaquant qui prendrait le contrôle du conteneur ne disposerait plus d'un compilateur.

### Image Web : après correction

```
docker-php-articles-web:apres-j3 (debian 13.7)
==============================================
Total: 59 (HIGH: 58, CRITICAL: 1)
```

| | CRITICAL | HIGH | Total |
|---|---|---|---|
| Avant | 3 | 117 | 120 |
| Après | 1 | 58 | 59 |

La vulnérabilité critique restante (`CVE-2026-6653`, `libxml2`) n'a pas encore de correctif publié par Debian, et `libxml2` est nécessaire à PHP : elle ne peut pas être supprimée. Elle disparaîtra en reconstruisant l'image quand Debian publiera le correctif (le pipeline CI reconstruit l'image à chaque push).

Rapports complets : `docs/rapport/sorties/5-trivy-avant-j3.txt` et `5-trivy-apres-j3.txt`.

### Image de la base de données

Même scan sur `nellotsuka/docker-php-articles-db:latest` :

```
nellotsuka/docker-php-articles-db:latest (oracle 9.8)    oracle       0
usr/lib/mysqlsh/.../certifi-2026.2.25                    python-pkg   1
usr/lib/mysqlsh/.../cryptography-46.0.5                  python-pkg   3
usr/lib/mysqlsh/.../pyopenssl-25.3.0                     python-pkg   1
usr/lib/mysqlsh/.../urllib3-2.6.3                        python-pkg   4
usr/local/bin/gosu                                       gobinary    26

Python (python-pkg)  Total: 9 (HIGH: 8, CRITICAL: 1)
gosu (gobinary)      Total: 26 (HIGH: 25, CRITICAL: 1)
```

Le système (Oracle Linux 9.8) n'a aucune vulnérabilité. Elles viennent de deux composants ajoutés par l'image officielle `mysql:8.4` :

- **MySQL Shell** (`/usr/lib/mysqlsh`, 467 Mo) : outil d'administration en ligne de commande, qui embarque ses propres paquets Python. Il n'est utilisé ni par le serveur ni par le point d'entrée. Il est donc supprimé dans le Dockerfile :

```dockerfile
RUN rpm -e --nodeps mysql-shell && rm -rf /var/cache/dnf /var/cache/yum
```

- **gosu** : sert au point d'entrée pour passer de `root` à l'utilisateur `mysql` avant de lancer le serveur. Il est indispensable et ne peut pas être supprimé. La faille critique (`CVE-2025-68121`) se trouve dans la bibliothèque TLS de Go, que `gosu` n'utilise pas (il ne fait aucune connexion réseau). Elle sera corrigée quand l'image officielle `mysql:8.4` sera reconstruite avec une version récente de `gosu`.

Après correction :

```
docker-php-articles-db:sans-mysqlsh (oracle 9.8)   oracle      0
usr/local/bin/gosu                                 gobinary   26

Total: 26 (HIGH: 25, CRITICAL: 1)
```

| Image `db` | CRITICAL | HIGH | Total |
|---|---|---|---|
| Avant | 2 | 33 | 35 |
| Après | 1 | 25 | 26 |

La stack a été relancée avec cette image : `db` et `web` sont `healthy` et l'ajout d'un article fonctionne.

```
$ docker exec db sh -c 'command -v mysqlsh || echo "mysqlsh absent"; mysql --version'
mysqlsh absent
mysql  Ver 8.4.13 for Linux on x86_64 (MySQL Community Server - GPL)
```

Rapports complets : `docs/rapport/sorties/5-trivy-db-avant.txt` et `5-trivy-db-apres.txt`.

## Mesures de sécurité de l'image Web

| Mesure | Où | Effet |
|---|---|---|
| Utilisateur non-root `www-data` | `USER www-data` (Dockerfile) | Une faille dans l'application ne donne pas les droits root |
| Code en lecture seule, propriétaire `root`, droits `644` | `COPY --chown=root:root --chmod=644` | Le processus Web ne peut pas modifier le code ; pas de `chmod 777` |
| Outils de compilation supprimés | `apt-get purge` (Dockerfile) | 61 vulnérabilités en moins, pas de compilateur pour un attaquant |
| Versions d'Apache et PHP masquées | `ServerTokens Prod`, `expose_php = Off` | Un attaquant ne peut pas cibler une version précise |
| Erreurs PHP non affichées, sorties échappées | code PHP, `php.ini-production` | Pas de fuite d'informations, pas de faille XSS |
| Aucun secret dans l'image | variables `DB_*` fournies à l'exécution | Les mots de passe ne sont ni dans le Dockerfile ni dans le code |
| Système de fichiers en lecture seule, `cap_drop: ALL`, `no-new-privileges` | `docker-compose.yml` (partie 3) | Limite ce qu'un conteneur compromis peut faire |

## Vérifications

Image lancée avec le durcissement prévu pour le Compose et la base MySQL du projet :

```
$ docker exec j3test-web id
uid=33(www-data) gid=33(www-data) groups=33(www-data)

$ docker run --rm docker-php-articles-web:apres-j3 sh -c 'php -m | grep -E "^(pdo_mysql|mysqli)$"; which gcc make || echo "gcc/make absents"'
mysqli
pdo_mysql
gcc/make absents

$ curl -s -o /dev/null -w 'ajout: HTTP %{http_code}\n' -X POST http://127.0.0.1:8092/validation.php -d 'title=Test J3&author=Jeremy&content=Image sans outils de compilation'
ajout: HTTP 302

$ docker inspect j3test-web --format '{{.State.Health.Status}}'
healthy
```
