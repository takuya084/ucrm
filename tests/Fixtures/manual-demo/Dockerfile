FROM php:8.3-cli
RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libzip-dev libonig-dev libcurl4-openssl-dev libsqlite3-dev poppler-utils && \
    for ext in gd zip mbstring curl bcmath pdo_sqlite; do \
      if ! php -r "exit(extension_loaded('$ext') ? 0 : 1);"; then docker-php-ext-install -j2 "$ext" || exit 1; fi; \
    done && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY app/ /app/app/
COPY bootstrap/ /app/bootstrap/
COPY config/ /app/config/
COPY database/ /app/database/
COPY resources/ /app/resources/
COPY routes/ /app/routes/
COPY public/ /app/public/
COPY vendor/ /app/vendor/
COPY tools/ /app/tools/
COPY tests/Fixtures/ /app/tests/Fixtures/
COPY artisan composer.json /app/
COPY tests/Fixtures/manual-demo/ /demo/
COPY tools/demo-review/print-pdf-maintenance.php /demo/print-pdf-maintenance.php
RUN printf '%s' local-manual-demo-v1 > /opt/manual-demo-marker
ENTRYPOINT ["sh", "/demo/entrypoint.sh"]
