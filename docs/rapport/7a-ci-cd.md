# 7a. Pipeline CI/CD (GitHub Actions)

Fichier : `.github/workflows/docker.yml`.

## Déclenchement

Le pipeline se lance à chaque `push` (toutes les branches) et à chaque Pull Request vers `main`. Chaque modification est donc vérifiée avant et après sa fusion.

## Étapes

Le pipeline contient trois jobs, enchaînés avec `needs` : un job ne démarre que si le précédent a réussi.

| Job | Ce qu'il fait | Pourquoi |
|---|---|---|
| **Lint PHP** | `php -l` sur chaque fichier de `app/`, avec l'image `php:8.2-cli` | Détecte une erreur de syntaxe avant même de construire les images, avec la même version de PHP que l'image Web |
| **Build et test de la stack** | Copie `.env.example` en `.env`, lance `docker compose up -d --build --wait`, puis teste l'application | Construit les deux images et vérifie que la stack fonctionne réellement, pas seulement qu'elle se construit |
| **Publication Docker Hub** | Construit et pousse les images `web` et `db` avec les tags `latest` et le SHA court du commit | Ne s'exécute que sur un `push` vers `main` : seul le code fusionné est publié |

### Tests exécutés par le job « Build et test »

1. `docker compose up --wait` attend que les **healthchecks** des deux conteneurs passent à `healthy` (échec au bout de 180 s).
2. La page d'accueil répond et contient le titre « Articles ».
3. Un article est ajouté via `validation.php` (réponse attendue : redirection `302`), puis on vérifie qu'il apparaît sur la page d'accueil : le Web lit et écrit bien dans la base.
4. Le processus du conteneur `web` ne tourne pas en root (`id -u` différent de `0`).
5. En cas d'échec, les logs des conteneurs sont affichés. La stack est toujours arrêtée à la fin (`docker compose down -v`).

## Secrets

La connexion à Docker Hub utilise deux secrets du dépôt GitHub (*Settings → Secrets and variables → Actions*) :

| Secret | Valeur |
|---|---|
| `DOCKERHUB_USERNAME` | `nellotsuka` |
| `DOCKERHUB_TOKEN` | jeton d'accès Docker Hub (*Read & Write*), pas le mot de passe du compte |

Aucun identifiant n'apparaît dans le fichier du pipeline. Si les secrets ne sont pas configurés, le job de publication affiche un avertissement et ne fait rien, au lieu d'échouer.

## Tags publiés

| Tag | Exemple | Usage |
|---|---|---|
| `latest` | `nellotsuka/docker-php-articles-web:latest` | Dernière version de `main`, utilisée par `docker-compose.yml` |
| SHA court du commit | `nellotsuka/docker-php-articles-web:21effd9` | Version figée : permet de revenir à une version précise |

## Exécution

Premier passage sur la branche `ci/github-actions` (run `40853016316`) :

```
## Lint PHP -> success
   php -l sur app/ -> success
## Build et test de la stack -> success
   Fichier .env de test -> success
   Build et démarrage (attend que les healthchecks soient OK) -> success
   État des conteneurs -> success
   La page d'accueil répond -> success
   Ajout d'un article et lecture depuis la base -> success
   Le conteneur web tourne en non-root -> success
   Logs en cas d'échec -> skipped
   Arrêt de la stack -> success
## Publication Docker Hub -> skipped
```

La publication est ignorée car il ne s'agit pas d'un push sur `main`.

### Publication depuis `main`

Après l'ajout des deux secrets, le pipeline du commit `21effd9` sur `main` a été relancé (run `40880302492`, tentative 2) :

```
## Lint PHP -> success                    (13:46:06 -> 13:46:19)
## Build et test de la stack -> success   (13:46:22 -> 13:47:20)
## Publication Docker Hub -> success      (13:47:22 -> 13:48:28)
```

Tags relevés ensuite par l'API publique de Docker Hub (`https://hub.docker.com/v2/repositories/nellotsuka/<dépôt>/tags`) :

| Dépôt | Tag | Poussé le (UTC) | Taille compressée | Par | Digest |
|---|---|---|---|---|---|
| `docker-php-articles-web` | `latest` | 2026-10-29 13:48:02 | 62,6 Mo | `nellotsuka` (CI) | `sha256:10b4567fafd5…` |
| `docker-php-articles-web` | `21effd9` | 2026-10-29 13:48:03 | 62,6 Mo | `nellotsuka` (CI) | `sha256:10b4567fafd5…` |
| `docker-php-articles-db` | `latest` | 2026-10-29 13:48:16 | 238,3 Mo | `nellotsuka` (CI) | `sha256:6b84d9c4e0c6…` |
| `docker-php-articles-db` | `21effd9` | 2026-10-29 13:48:18 | 238,3 Mo | `nellotsuka` (CI) | `sha256:6b84d9c4e0c6…` |

`latest` et `21effd9` ont le même digest : ce sont deux noms pour la même image. Le tag `6c5b832`, poussé à la main par `yannd0cker` (partie 7b), reste disponible dans les deux dépôts.
