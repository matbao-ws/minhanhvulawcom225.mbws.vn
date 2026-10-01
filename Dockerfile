# Core image (staging). Config (.env) is mounted at runtime by the compose file
# in the MatBaoAI-SEO repo (infra/compose.yml); nothing secret is baked in.
FROM serversideup/php:8.3-fpm-nginx

USER root
RUN install-php-extensions intl gd bcmath exif
USER www-data

ENV PHP_OPCACHE_ENABLE=1

WORKDIR /var/www/html

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY --chown=www-data:www-data . .
# storage/ is excluded by .dockerignore. Create it before dump-autoload: its
# package:discover boots the app, and the core touches Blade while booting
# ("Please provide a valid cache path" without storage/framework/views).
RUN mkdir -p storage/app/private storage/app/public storage/logs \
        storage/framework/cache/data storage/framework/sessions storage/framework/views \
        bootstrap/cache \
    && composer dump-autoload --optimize --no-dev
