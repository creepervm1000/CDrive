# Build the frontend explicitly. This avoids Railpack/npm lifecycle hooks from
# invoking the removed build/demi.sh tooling.
FROM node:24-bookworm AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY . .
RUN npm run build

# The runtime image does not need Node or the frontend build dependencies.
RUN rm -rf node_modules

FROM dunglas/frankenphp:php8.3.33-trixie

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Retry the package installation: free-tier builders occasionally hit
# transient DNS/mirror failures ("Temporary failure resolving 'deb.debian.org'").
RUN apt-get update \
	&& for i in 1 2 3; do \
		apt-get install -y --no-install-recommends \
			libfreetype6-dev \
			libjpeg62-turbo-dev \
			libpng-dev \
			libzip-dev \
			unzip \
		&& break || { [ "$i" -lt 3 ] && { echo "apt-get failed (attempt $i), retrying..."; apt-get update; sleep 5; } || exit 1; }; \
	done \
	&& docker-php-ext-configure gd --with-freetype --with-jpeg \
	&& docker-php-ext-install -j"$(nproc)" gd zip \
	&& pecl install apcu \
	&& docker-php-ext-enable apcu \
	&& rm -rf /var/lib/apt/lists/* /tmp/pear

WORKDIR /app
COPY --from=assets /app /app

RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader \
	&& composer dump-autoload --no-dev --optimize \
	&& mkdir -p config data custom_apps \
	&& chown -R www-data:www-data /app

# FrankenPHP reads its config from /etc/frankenphp/Caddyfile (not /etc/caddy).
# Our Caddyfile uses {$PORT:8080} directly, so no SERVER_NAME nesting is needed;
# nested placeholders like SERVER_NAME="{$PORT:8080}" only expand one level and
# make Caddy fail with: invalid port '8080}'.
COPY Caddyfile /etc/frankenphp/Caddyfile

EXPOSE 8080
