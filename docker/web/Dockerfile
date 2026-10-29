# Image Web : PHP 8.2 + Apache (image officielle)
FROM php:8.2-apache

# Extensions PHP nécessaires pour MySQL, puis suppression des outils de compilation.
# L'image officielle garde gcc, make, autoconf et les en-têtes du noyau ($PHPIZE_DEPS)
# pour compiler des extensions : inutiles une fois pdo_mysql et mysqli compilées,
# ils ajoutent des vulnérabilités (linux-libc-dev : 63 CVE HIGH/CRITICAL selon Trivy).
RUN docker-php-ext-install pdo_mysql mysqli \
    && apt-get purge -y --auto-remove \
        autoconf dpkg-dev file g++ gcc make pkg-config re2c libc6-dev \
    && rm -rf /var/lib/apt/lists/* /tmp/* /usr/src/php.tar.xz

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

# Exécution en utilisateur non-root.
USER www-data

EXPOSE 80

# Vérifie que la page d'accueil répond (elle répond aussi si la base est indisponible).
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -fsS -o /dev/null http://localhost/ || exit 1
