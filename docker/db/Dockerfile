FROM mysql:8.4

# MySQL Shell (mysqlsh) is not used by the server or the entrypoint, but it bundles Python
# packages with known vulnerabilities (certifi: CRITICAL, cryptography, urllib3, pyopenssl).
RUN rpm -e --nodeps mysql-shell && rm -rf /var/cache/dnf /var/cache/yum

# The official MySQL entrypoint runs this script when initializing an empty data directory.
COPY --chown=root:root --chmod=644 db/init/articles.sql /docker-entrypoint-initdb.d/10-articles.sql
