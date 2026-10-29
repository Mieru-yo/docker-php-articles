# Organisation du projet

Projet réalisé en binôme : **Jérémy CHAN FUNG TING** (côté Web) et **Yann** (côté base de données et orchestration).

Ce fichier est la **référence commune**. Il est écrit pour être donné tel quel à vos agents IA : il fixe qui fait quoi, dans quel ordre, quels fichiers chacun a le droit de modifier, et les noms partagés que personne ne doit changer seul.

---

## 1. Contrat technique (à respecter par les deux)

Ces valeurs sont fixées **dès maintenant** pour que chacun puisse travailler sans attendre l'autre. Pour en changer une, il faut l'accord des deux et une PR qui modifie ce fichier.

### Services, réseau, volume

| Élément | Valeur |
|---|---|
| Service / conteneur Web | `web` (`container_name: web`) |
| Service / conteneur base | `db` (`container_name: db`) |
| Service d'administration (optionnel) | `adminer` |
| Réseau | `app-network` (driver `bridge`) |
| Volume de la base | `db-data`, monté sur `/var/lib/mysql` |
| Port publié | `8080` sur l'hôte → `80` dans le conteneur `web` |
| URL de l'application | `http://localhost:8080` |

### Images

| Image | Dockerfile | Contexte de build | Nom Docker Hub |
|---|---|---|---|
| Web (PHP 8.2 + Apache) | `docker/web/Dockerfile` | racine du dépôt (`.`) | `nellotsuka/docker-php-articles-web` |
| Base (MySQL 8.4) | `docker/db/Dockerfile` | racine du dépôt (`.`) | `nellotsuka/docker-php-articles-db` |

Tags publiés : `latest` et le SHA court du commit (ex. `a1b2c3d`).
Compte Docker Hub : **`nellotsuka`** (compte de Jérémy).

### Variables d'environnement

Le fichier `.env` (jamais commité) est copié depuis `.env.example`.

| Variable dans `.env` | Lue par | Rôle |
|---|---|---|
| `MYSQL_ROOT_PASSWORD` | `db` | mot de passe root MySQL |
| `MYSQL_DATABASE` | `db` | nom de la base (`articles`) |
| `MYSQL_USER` | `db` | utilisateur applicatif (`app`) |
| `MYSQL_PASSWORD` | `db` | mot de passe de l'utilisateur applicatif |

Le `docker-compose.yml` transmet au service `web` :

| Variable vue par PHP | Valeur dans Compose |
|---|---|
| `DB_HOST` | `db` |
| `DB_PORT` | `3306` |
| `DB_NAME` | `${MYSQL_DATABASE}` |
| `DB_USER` | `${MYSQL_USER}` |
| `DB_PASSWORD` | `${MYSQL_PASSWORD}` |

Le code PHP lit **uniquement** ces variables `DB_*` (aucune valeur en dur).

### Base de données

- La base et l'utilisateur sont créés **par l'image MySQL** grâce aux variables `MYSQL_*`. Le script `db/init/articles.sql` ne crée **que la table** `articles` (plus de `CREATE DATABASE` ni de `CREATE USER ... @'localhost'`, qui empêchaient la connexion depuis le conteneur `web`).
- Encodage : `utf8mb4`.
- Healthcheck `db` (dans Compose) : `mysqladmin ping -h 127.0.0.1`.

### Durcissement du service `web` dans Compose

Le conteneur Web tourne en utilisateur non-root (`www-data`). Le service `web` du Compose doit contenir :

```yaml
read_only: true
tmpfs:
  - /tmp
  - /var/run/apache2
  - /var/lock/apache2
cap_drop:
  - ALL
sysctls:
  net.ipv4.ip_unprivileged_port_start: 0   # permet à www-data d'écouter sur le port 80 sans être root
security_opt:
  - no-new-privileges:true
```

Pourquoi un `sysctl` plutôt que `cap_add: NET_BIND_SERVICE` : un processus non-root ne reçoit pas les capabilities ajoutées par `cap_add`, et `no-new-privileges` bloque les capabilities posées sur un fichier (`setcap`). Le `sysctl` est limité au réseau du conteneur et ne donne aucun autre droit.

Si le Dockerfile Web nécessite un autre réglage, Jérémy met à jour ce paragraphe dans sa PR et prévient Yann.

### Healthcheck `web`

Défini dans le Dockerfile Web : requête HTTP sur `http://localhost/` (la page répond même si la base est indisponible). Compose n'a pas besoin de le redéfinir.

---

## 2. Qui possède quels fichiers

**Règle :** on ne modifie que ses propres fichiers. Si on a besoin d'un changement dans un fichier de l'autre, on le demande (commentaire sur sa PR ou message), on ne le fait pas soi-même.

| Fichier / dossier | Propriétaire |
|---|---|
| `app/` (code PHP) | Jérémy |
| `docker/web/` (Dockerfile Web et config Apache) | Jérémy |
| `.dockerignore` | Jérémy (liste blanche : si l'image `db` a besoin d'un nouveau fichier, Yann demande à Jérémy de l'ajouter) |
| `.github/workflows/` (CI/CD) | Jérémy |
| `docs/rapport/2a-dockerfile-web.md`, `5-securite.md`, `6-optimisation.md`, `7a-ci-cd.md` | Jérémy |
| `db/init/` (script SQL) | Yann |
| `docker/db/` (Dockerfile base) | Yann |
| `docker-compose.yml`, `.env.example` | Yann |
| `docs/architecture.*` (schéma) | Yann |
| `README.md` | Yann |
| `docs/rapport/1-architecture.md`, `2b-dockerfile-db.md`, `3-compose.md`, `4-reseaux.md`, `7b-docker-hub.md`, `8-tests.md` | Yann |
| `docs/ORGANISATION.md` | Les deux (prévenir l'autre à chaque modification) |
| `docs/rapport/rapport.md` (assemblage final) | Jérémy, en phase 4 |

Les captures d'écran vont dans `docs/rapport/img/`, préfixées par le numéro de partie (ex. `3-persistance-avant.png`).

---

## 3. Ordre de réalisation

Les deux travaillent **en parallèle**. Les 🔒 indiquent une tâche bloquée par une tâche de l'autre ; la colonne « En attendant » donne quoi faire pour ne jamais être à l'arrêt.

### Phase 1 : chacun avance seul (aucun blocage)

| Ordre | Jérémy | Yann |
|---|---|---|
| 1 | **J1** `fix/app-config` : lire la config depuis `DB_*`, corriger le bug de `validation.php` (teste `title` au lieu de `author`), échapper l'affichage avec `htmlspecialchars` (faille XSS) | **Y1** `docs/architecture` : schéma d'architecture (draw.io exporté en PNG) à partir du §1 |
| 2 | **J2** `feat/web-dockerfile` : Dockerfile Web + `.dockerignore` + HEALTHCHECK | **Y2** `feat/db` : `docker/db/Dockerfile` + script d'init corrigé (§1) |
| 3 | Section rapport `2a-dockerfile-web.md` | **Y3** `feat/compose` : `docker-compose.yml` + `.env.example`, testé avec `docker compose up db` seulement |

**➜ Point de synchronisation A :** J1 et J2 fusionnés dans `main`. Jérémy prévient Yann.

### Phase 2 : intégration

| Ordre | Jérémy | Yann |
|---|---|---|
| 4 | **J3** `feat/web-security` : scan de l'image Web (Trivy ou Docker Scout), correction des vulnérabilités critiques, vérification non-root | 🔒 **Y3 (fin)** : brancher le service `web` sur `docker/web/Dockerfile`, appliquer le durcissement du §1, tester `docker compose up -d` complet. *Bloqué par A.* |
| 5 | **J4** `feat/web-optimisation` : taille de l'image avant / après, image de base plus légère ou multi-stage, `.dockerignore` affiné | 🔒 **Y4** `feat/network` : test de persistance (partie 3) + `docker network ls/inspect`, `ping db` depuis `web` (partie 4). *Bloqué par Y3 fin.* |
| 6 | Sections rapport `5-securite.md`, `6-optimisation.md` | Sections rapport `1`, `2b`, `3`, `4` |

**En attendant A, Yann :** Y1, Y2, Y3 (partie `db`), rédaction de `1-architecture.md` et `2b-dockerfile-db.md`, création de son compte Docker Hub pour être ajouté comme collaborateur (§6).

**➜ Point de synchronisation B :** J3, J4 et Y3 fusionnés (images et Compose définitifs).

### Phase 3 : CI/CD et publication

| Ordre | Jérémy | Yann |
|---|---|---|
| 7 | **J5** `ci/github-actions` : pipeline sur `push` et `pull_request` : build des 2 images, `php -l` sur `app/`, démarrage de la stack et test HTTP. Peut démarrer dès la phase 2 (sans le push). | **Y5** `docs/deploiement` : publication manuelle sur Docker Hub (`docker login`, `tag`, `push`), captures des dépôts publics |
| 8 | 🔒 **J5 (fin)** : étape de push vers Docker Hub (tags `latest` + SHA), via les secrets GitHub. *Bloqué par le jeton Docker Hub (§6).* | 🔒 **Y6** : cloner le dépôt sur **son PC** (= autre machine), `docker compose up -d`, vérifier l'application, captures. *Bloqué par B.* |
| 9 | Section rapport `7a-ci-cd.md` | Sections rapport `7b-docker-hub.md`, `8-tests.md` |

### Phase 4 : finalisation

| Ordre | Jérémy | Yann |
|---|---|---|
| 10 | 🔒 Assemblage de `docs/rapport/rapport.md` et export PDF. *Bloqué par toutes les sections.* | README complet : architecture, prérequis, commandes, variables, procédure de test |
| 11 | Relecture du README | Relecture du rapport |
| 12 | Démo : vidéo courte ou captures (voir §6) | Démo : vidéo courte ou captures (voir §6) |

---

## 4. Workflow Git

1. Toujours partir d'un `main` à jour :
   ```bash
   git checkout main
   git pull
   git checkout -b <branche-de-la-tâche>
   ```
2. Petits commits clairs (convention ci-dessous), puis :
   ```bash
   git push -u origin <branche-de-la-tâche>
   ```
3. Ouvrir une **Pull Request** vers `main` et la fusionner soi-même (pas de relecture obligatoire). Prévenir l'autre membre après chaque fusion, surtout aux points de synchronisation A et B. On ne pousse jamais de commit sur la branche de l'autre.
4. Personne ne pousse directement sur `main`.
5. Chacun committe avec **son propre compte Git** (`git config user.email`).
6. Après une fusion dans `main`, l'autre membre met sa branche à jour : `git pull origin main`.

### Convention des messages de commit

Format : `type(portée): description courte`, en anglais.

| Type | Usage | Exemple |
|---|---|---|
| `feat` | nouvelle fonctionnalité | `feat(web): add non-root Dockerfile with healthcheck` |
| `fix` | correction | `fix(app): read database config from environment` |
| `docs` | documentation | `docs: add architecture diagram` |
| `ci` | pipeline | `ci: build and push images on main` |
| `chore` | maintenance | `chore: add .gitignore` |
| `refactor` | réorganisation sans changement de comportement | `refactor(db): split init script` |

---

## 5. Règles pour les agents IA

À donner à votre agent en même temps que ce fichier :

1. Tu travailles pour **<Jérémy | Yann>**. Tu ne modifies **que** les fichiers attribués à cette personne au §2.
2. Respecte strictement le contrat du §1 (noms, ports, variables). Si une valeur te semble mauvaise, signale-le au lieu de la changer.
3. Une branche par tâche (noms du §3), jamais de commit sur `main`, jamais de `git push --force` sur une branche partagée.
4. Messages de commit selon la convention du §4. Commits petits et cohérents.
5. Ne committe jamais `.env` ni aucun mot de passe réel. Aucun secret en dur dans un Dockerfile ou dans le code.
6. Interdit : `chmod 777`, `--privileged`, utilisateur root dans le conteneur final.
7. Dans le rapport, ne mets que des sorties de commandes **réellement exécutées** et de vraies captures d'écran.
8. Pour chaque tâche, rédige la section correspondante du rapport (`docs/rapport/…`) : ce qui a été fait, les commandes, la justification de chaque choix.
9. Si ta tâche est 🔒 bloquée, passe à la colonne « En attendant » au lieu de contourner le blocage.

---

## 6. Docker Hub et décisions encore ouvertes

### Docker Hub (décidé)

- Compte : **`nellotsuka`** (Jérémy).
- Avant la phase 3, Jérémy :
  1. crée les dépôts publics `docker-php-articles-web` et `docker-php-articles-db` ;
  2. ajoute Yann comme collaborateur de ces dépôts (Yann doit avoir un compte Docker Hub), pour qu'il puisse faire la publication manuelle (Y5) avec son propre compte ;
  3. crée un jeton d'accès Docker Hub (Account settings → Personal access tokens, droits *Read & Write*) et l'ajoute dans les secrets GitHub du dépôt : `DOCKERHUB_USERNAME` = `nellotsuka`, `DOCKERHUB_TOKEN` = le jeton. Cela débloque J5 (fin).

### Décisions encore ouvertes

| Question | À trancher avant | Proposition |
|---|---|---|

| Service `adminer` (optionnel) | Phase 2 | Yann l'ajoute au Compose s'il a le temps, sur le réseau `app-network`, port `8081`. |
