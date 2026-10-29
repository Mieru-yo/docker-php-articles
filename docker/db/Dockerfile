FROM mysql:8.4

# The official MySQL entrypoint runs this script when initializing an empty data directory.
COPY --chown=root:root --chmod=644 db/init/articles.sql /docker-entrypoint-initdb.d/10-articles.sql
