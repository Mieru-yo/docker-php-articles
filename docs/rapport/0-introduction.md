# Introduction

Conteneurisation d'une application Web PHP / MySQL (liste d'articles) : une image par service, orchestration avec Docker Compose, sécurisation et optimisation des images, pipeline CI/CD et publication sur Docker Hub.

| | |
|---|---|
| Dépôt Git | <https://github.com/Mieru-yo/docker-php-articles> |
| Image Web | <https://hub.docker.com/r/nellotsuka/docker-php-articles-web> |
| Image base de données | <https://hub.docker.com/r/nellotsuka/docker-php-articles-db> |

## Répartition du travail

Chaque partie a été développée sur sa propre branche et fusionnée dans `main` par Pull Request, avec des commits au format *Conventional Commits*. Les noms partagés (services, réseau, volume, variables, images) ont été fixés au départ dans `docs/ORGANISATION.md`, pour que chacun puisse avancer sans attendre l'autre.

| Partie | Réalisée par |
|---|---|
| 1. Architecture | Yann |
| Corrections du code PHP, 2.1 Dockerfile Web | Jérémy |
| 2.2 Dockerfile de la base | Yann |
| 3. Docker Compose | Yann |
| 4. Réseaux et persistance | Yann |
| 5. Sécurité | Jérémy |
| 6. Optimisation | Jérémy |
| 7.1 Pipeline CI/CD | Jérémy |
| 7.2 Publication manuelle sur Docker Hub | Yann |
| 8. Test depuis un clone neuf, README, assemblage du rapport | Jérémy |
