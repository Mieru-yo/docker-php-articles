# 9. Difficultés rencontrées

| Problème | Cause | Solution retenue |
|---|---|---|
| Le code fourni ne se connectait pas à la base | Hôte `localhost` et identifiants `test/test` codés en dur dans `db-config.php` | Lecture de `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` depuis l'environnement (partie 2.1) |
| La version d'Apache restait visible malgré `ServerTokens Prod` | `security.conf` de Debian, chargé après notre fichier, remettait la valeur par défaut | Fichier renommé `zz-security-hardening.conf` pour être chargé en dernier |
| L'en-tête `X-Powered-By` restait présent | `expose_php` vaut `On` même dans `php.ini-production` | Fichier `zz-security.ini` avec `expose_php = Off` |
| Apache en non-root ne pouvait pas écouter sur le port 80 | Avec `USER www-data`, la capability `NET_BIND_SERVICE` ajoutée par `cap_add` n'est pas transmise au processus, et `no-new-privileges` empêche `setcap` | `sysctl net.ipv4.ip_unprivileged_port_start=0` dans le Compose, en gardant `cap_drop: ALL` |
| La suppression des outils de compilation ne réduisait pas la taille de l'image | Un fichier supprimé reste stocké dans la couche de l'image de base | Multi-stage avec `FROM scratch` + `COPY --from=build / /` : 176 → 63 Mo (partie 6) |
| `ping` absent du conteneur Web | Non installé dans l'image officielle | Test réseau fait avec `getent hosts db` et une connexion TCP vers `db:3306`, sans alourdir l'image (partie 4) |
| Caractères accentués cassés dans certains fichiers | Encodage par défaut de PowerShell sous Windows | Fichiers réécrits en UTF-8 |
