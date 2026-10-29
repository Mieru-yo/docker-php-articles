# 4. Réseaux et persistance

## Persistance des données (partie 3)

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

## Réseau (partie 4)

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

### À propos de `ping`

La commande `ping` demandée par le sujet n'est pas utilisable depuis `web` :

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
