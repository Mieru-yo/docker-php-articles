# Organisation du projet

Projet réalisé en binôme : **Jérémy CHAN FUNG TING** et **Yann**.

## Répartition des tâches

Chaque partie a un responsable, qui la développe, la teste et rédige sa section du README et du rapport.

| Partie du sujet | Responsable | Branche |
|---|---|---|
| Correction du code PHP (variables d'environnement, bug de validation, faille XSS) | Jérémy | `fix/app-config` |
| 2.1 Dockerfile Web + HEALTHCHECK + `.dockerignore` | Jérémy | `feat/web-dockerfile` |
| 5 Sécurité de l'image Web (scan Trivy / Scout, non-root) | Jérémy | `feat/web-security` |
| 6 Optimisation (taille avant / après, multi-stage) | Jérémy | `feat/web-optimisation` |
| 7 Pipeline CI/CD GitHub Actions | Jérémy | `ci/github-actions` |
| 1 Schéma d'architecture | Yann | `docs/architecture` |
| 2.2 Base de données (script d'init, utilisateur MySQL) | Yann | `feat/db` |
| 3 `docker-compose.yml`, `.env.example`, test de persistance | Yann | `feat/compose` |
| 4 Réseaux et tests de communication | Yann | `feat/network` |
| 7 Publication Docker Hub + 8 test sur une autre machine | Yann | `docs/deploiement` |
| README et rapport final | Les deux (chacun ses parties) | `docs/rapport` |

**Dépendance :** Yann peut commencer le `docker-compose.yml` avec l'image officielle `php:8.2-apache`, puis la remplacer par le Dockerfile Web une fois celui-ci fusionné dans `main`.

## Workflow Git

1. Toujours partir d'un `main` à jour :
   ```bash
   git checkout main
   git pull
   git checkout -b feat/ma-tache
   ```
2. Faire des petits commits clairs, puis pousser la branche :
   ```bash
   git push -u origin feat/ma-tache
   ```
3. Ouvrir une **Pull Request** vers `main` sur GitHub. **L'autre membre la relit et l'approuve** avant la fusion.
4. Personne ne pousse directement sur `main`.
5. Chacun committe avec **son propre compte Git** (vérifier avec `git config user.email`).

## Convention des messages de commit

Format : `type(portée): description courte`

| Type | Usage | Exemple |
|---|---|---|
| `feat` | nouvelle fonctionnalité | `feat(web): add non-root Dockerfile with healthcheck` |
| `fix` | correction | `fix(app): read database config from environment` |
| `docs` | documentation | `docs: add architecture diagram` |
| `ci` | pipeline | `ci: build and push images on main` |
| `chore` | maintenance | `chore: add .gitignore` |
| `refactor` | réorganisation sans changement de comportement | `refactor(db): split init script` |
