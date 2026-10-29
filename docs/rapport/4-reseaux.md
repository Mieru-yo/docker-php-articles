# 4. Réseaux et persistance

## Persistance des données

Le volume nommé `db-data` est monté sur `/var/lib/mysql`. Pour le vérifier, un article est créé, les conteneurs sont supprimés (`docker compose down`, sans l'option `-v`), puis recréés :

```
$ curl.exe -s -o NUL -w "POST -> HTTP %{http_code}" -X POST http://localhost:8080/validation.php -d "title=Persistance&author=Yann&content=Doit survivre a la suppression des conteneurs"
POST -> HTTP 302

$ curl.exe -s http://localhost:8080/ | Select-String "Persistance"
            <h2 class="h3">Persistance <small class="text-muted font-italic"></h2>

$ docker compose down

$ docker volume ls --filter name=db-data
DRIVER    VOLUME NAME
local     db-data

$ docker compose up -d

$ curl.exe -s http://localhost:8080/ | Select-String "Persistance"
            <h2 class="h3">Persistance <small class="text-muted font-italic"></h2>
```

Les conteneurs ont été détruits et recréés, mais le volume `db-data` est resté en place : l'article est toujours affiché. Les données sont donc indépendantes du cycle de vie des conteneurs. Seule la commande `docker compose down -v` supprime le volume.

## Réseau

Les deux services sont rattachés au réseau bridge `app-network`, défini dans le Compose :

```
$ docker network ls --filter name=app-network
NETWORK ID     NAME          DRIVER    SCOPE
d47a33318254   app-network   bridge    local

$ docker network inspect app-network --format "{{.Name}} driver={{.Driver}} containers: {{range .Containers}}{{.Name}}={{.IPv4Address}} {{end}}"
app-network driver=bridge containers: db=172.18.0.2/16 web=172.18.0.3/16
```

### Communication entre `web` et `db`

Sur un réseau bridge défini par l'utilisateur, Docker fournit une résolution DNS par nom de service. Le conteneur `web` joint donc la base avec `DB_HOST=db`, sans adresse IP en dur :

```
$ docker exec web getent hosts db
172.18.0.2      db

$ docker exec -i web php   # script : fsockopen("db", 3306)
TCP db:3306 OK
db -> 172.18.0.2
```

Test avec `curl` depuis le conteneur `web` :

```
$ docker exec web curl -sS --max-time 5 http://db:3306
curl: (1) Received HTTP/0.9 when not allowed

$ docker exec web curl -sS --max-time 5 --http0.9 -o /dev/null -w "connexion vers %{remote_ip}:%{remote_port} etablie\n" http://db:3306
connexion vers 172.26.0.2:3306 etablie

$ docker exec web curl -sS --max-time 5 --http0.9 http://db:3306 | cat -v
J^@^@^@
8.4.13^@ ... caching_sha2_password^@ ... Got packets out of order

$ docker exec web curl -sS --max-time 5 http://db:3307
curl: (7) Failed to connect to db port 3307 after 1 ms: Could not connect to server
```

MySQL ne parle pas HTTP : `curl` refuse la réponse, mais la connexion TCP vers `db:3306` est bien établie. Avec `--http0.9`, on voit le message d'accueil du serveur MySQL (version `8.4.13`, méthode d'authentification `caching_sha2_password`). À titre de comparaison, un port où rien n'écoute (`3307`) donne une erreur de connexion.

### À propos de `ping`

`ping` n'est pas disponible dans le conteneur `web` :

```
$ docker exec web ping -c 2 db
OCI runtime exec failed: exec failed: unable to start container process: exec: "ping": executable file not found in $PATH
```

L'image officielle `php:8.2-apache` ne contient pas `ping`. L'ajouter demanderait d'installer un paquet (`iputils-ping`), ce qui alourdit l'image, et `ping` utilise des sockets ICMP que le durcissement (`cap_drop: ALL`) restreint. Plutôt que d'affaiblir l'image, la connectivité est prouvée par la résolution DNS (`getent`) et par une vraie connexion TCP vers `db:3306`, ce qui est plus pertinent : c'est exactement ce que fait l'application.

### Isolation

Le port MySQL n'est pas publié sur l'hôte, seul `8080` l'est (pour `web`) :

```
$ docker compose ps db --format "{{.Ports}}"
3306/tcp, 33060/tcp

$ Test-NetConnection localhost -Port 3306   # TcpTestSucceeded
False
```

La base n'est donc joignable que depuis `app-network`.
