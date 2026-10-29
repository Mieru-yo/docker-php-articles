# Image Web : PHP 8.2 + Apache (image officielle), construite en deux étapes.

# ---------------------------------------------------------------------------
# Étape 1 : préparation complète du système de fichiers
# ---------------------------------------------------------------------------
FROM php:8.2-apache AS build

# Extensions PHP nécessaires pour MySQL, puis suppression des outils de compilation.
# L'image officielle garde gcc, make, autoconf et les en-têtes du noyau ($PHPIZE_DEPS)
# pour compiler des extensions : inutiles une fois pdo_mysql et mysqli compilées,
# ils ajoutent des vulnérabilités (linux-libc-dev : 63 CVE HIGH/CRITICAL selon Trivy).
# Les en-têtes et outils de développement PHP, la documentation et les pages de
# manuel sont aussi supprimés : ils ne servent pas à l'exécution.
RUN docker-php-ext-install pdo_mysql mysqli \
    && apt-get purge -y --auto-remove \
        autoconf dpkg-dev file g++ gcc make pkg-config re2c libc6-dev \
    && rm -rf /var/lib/apt/lists/* /var/cache/apt/* /tmp/* \
        /usr/src/php.tar.xz /usr/local/include/php /usr/local/lib/php/build \
        /usr/share/doc/* /usr/share/man/* /usr/share/info/*

# Configuration Apache et PHP de production : masque les versions d'Apache et de PHP.
# Le préfixe zz- fait charger la conf après security.conf de Debian, qui sinon l'écraserait.
COPY docker/web/apache-security.conf /etc/apache2/conf-available/zz-security-hardening.conf
COPY docker/web/php-security.ini /usr/local/etc/php/conf.d/zz-security.ini
RUN a2enconf zz-security-hardening \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Code de l'application, appartenant à root et en lecture seule pour www-data :
# même compromis, le processus Web ne peut pas modifier le code.
# (pas de chmod 777 : il donnerait à tout utilisateur le droit de modifier le code)
COPY --chown=root:root --chmod=644 app/ /var/www/html/

# ---------------------------------------------------------------------------
# Étape 2 : image finale aplatie
# ---------------------------------------------------------------------------
# Les fichiers supprimés à l'étape 1 restent stockés dans les couches de
# php:8.2-apache. En recopiant le système de fichiers final dans une image vide,
# ils disparaissent réellement : l'image ne contient plus qu'une seule couche.
FROM scratch

COPY --from=build / /

# La copie ne reprend pas la configuration de l'image : on redéclare celle de
# php:8.2-apache dont Apache et PHP ont besoin.
ENV PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin \
    PHP_INI_DIR=/usr/local/etc/php \
    APACHE_CONFDIR=/etc/apache2 \
    APACHE_ENVVARS=/etc/apache2/envvars

WORKDIR /var/www/html

# Exécution en utilisateur non-root.
USER www-data

EXPOSE 80

# Signal d'arrêt propre d'Apache (repris de l'image officielle).
STOPSIGNAL SIGWINCH

# Vérifie que la page d'accueil répond (elle répond aussi si la base est indisponible).
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -fsS -o /dev/null http://localhost/ || exit 1

ENTRYPOINT ["docker-php-entrypoint"]
CMD ["apache2-foreground"]
