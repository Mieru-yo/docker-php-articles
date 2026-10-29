# 3. Orchestration Docker Compose

Le [docker-compose.yml](../../docker-compose.yml) définit deux services (`web` et `db`), le réseau bridge `app-network` et le volume nommé `db-data`.

| Section | Rôle |
|---|---|
| `services` | Les deux conteneurs de la stack (`web`, `db`) : image, variables, ports, dépendances, sécurité |
| `networks` | Crée le réseau bridge `app-network`, avec un nom fixe, qui relie les deux services |
| `volumes` | Crée le volume nommé `db-data`, qui stocke les données MySQL en dehors des conteneurs |

## Configuration

Les quatre variables `MYSQL_*` sont définies dans le fichier local `.env`, à créer à partir de [.env.example](../../.env.example). Les mots de passe de l'exemple sont des valeurs fictives à remplacer ; `.env` est ignoré par Git. Une variable manquante fait échouer `docker compose` (syntaxe `${VAR:?message}`).

## Service `db`

| Élément | Valeur | Justification |
|---|---|---|
| Image | construite par `docker/db/Dockerfile` | MySQL 8.4 avec le script de création de la table |
| `container_name` | `db` | nom fixe, utilisé par `docker exec db …` |
| Volume | `db-data` → `/var/lib/mysql` | les données survivent à la suppression du conteneur |
| Ports | aucun publié | la base n'est joignable que depuis `app-network` |
| Healthcheck | `mysqladmin ping -h 127.0.0.1` | permet à `web` d'attendre que MySQL soit prêt |
| `restart` | `unless-stopped` | redémarre la base après un crash ou un redémarrage de Docker, sauf si on l'a arrêtée volontairement |

## Service `web`

| Élément | Valeur | Justification |
|---|---|---|
| Image | construite par `docker/web/Dockerfile` | PHP 8.2 + Apache, non-root |
| Port | `8080:80` | application sur `http://localhost:8080` |
| Variables | `DB_HOST=db`, `DB_PORT=3306`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | seules variables lues par le code PHP ; les trois dernières viennent des `MYSQL_*` |
| `restart` | `unless-stopped` | même politique que `db` |
| `depends_on` | `db` avec `condition: service_healthy` | le Web démarre après que la base répond |
| `read_only: true` + `tmpfs` | `/tmp`, `/var/run/apache2`, `/var/lock/apache2` | système de fichiers en lecture seule, seuls les dossiers temporaires d'Apache sont inscriptibles |
| `cap_drop: ALL` | toutes les capabilities retirées | réduit la surface d'attaque |
| `sysctls` | `net.ipv4.ip_unprivileged_port_start: 0` | permet à `www-data` d'écouter sur le port 80 sans capability |
| `no-new-privileges` | activé | interdit toute élévation de privilèges |

Le healthcheck du service Web est défini dans son Dockerfile.

## Vérification

La stack complète a été démarrée avec `docker compose up -d --build` :

```
$ docker compose ps
NAME   IMAGE                                       SERVICE   STATUS                        PORTS
db     nellotsuka/docker-php-articles-db:latest    db        Up About a minute (healthy)   3306/tcp, 33060/tcp
web    nellotsuka/docker-php-articles-web:latest   web       Up About a minute (healthy)   0.0.0.0:8080->80/tcp, [::]:8080->80/tcp
```

Test de l'application (création d'un article, puis lecture de la page d'accueil) :

```
$ curl -s -o NUL -w "GET / -> HTTP %{http_code}" http://localhost:8080/
GET / -> HTTP 200
$ curl -s -o NUL -w "POST -> HTTP %{http_code}" -X POST http://localhost:8080/validation.php -d "title=Test Y3&author=Yann&content=Stack complete"
POST -> HTTP 302
$ curl -s http://localhost:8080/ | grep "Test Y3"
            <h2 class="h3">Test Y3 <small class="text-muted font-italic"></h2>
```

Vérification du durcissement :

```
$ docker exec web id
uid=33(www-data) gid=33(www-data) groups=33(www-data)
$ docker exec web sh -c "touch /var/www/html/x"
touch: cannot touch '/var/www/html/x': Read-only file system
```

Le port MySQL n'étant pas publié, seul le service Web est accessible depuis l'hôte. Le test de persistance du volume est présenté dans la partie 4.
