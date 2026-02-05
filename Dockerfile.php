############################################
# Base Image
############################################

# Learn more about the Server Side Up PHP Docker Images at:
# https://serversideup.net/open-source/docker-php/
FROM serversideup/php:8.5-frankenphp AS base

## Uncomment if you need to install additional PHP extensions
# USER root
# RUN install-php-extensions bcmath gd

############################################
# Development Image
############################################
FROM base AS development

# We can pass USER_ID and GROUP_ID as build arguments
# to ensure the www-data user has the same UID and GID
# as the user running Docker.
ARG USER_ID
ARG GROUP_ID

# Switch to root so we can set the user ID and group ID
USER root

# Set the user ID and group ID for www-data
RUN docker-php-serversideup-set-id www-data $USER_ID:$GROUP_ID  && \
    docker-php-serversideup-set-file-permissions --owner $USER_ID:$GROUP_ID

# Drop privileges back to www-data
USER www-data

############################################
# Composer Dependencies
############################################
FROM composer:2 AS composer

WORKDIR /app

# Copy composer files first for better layer caching
COPY composer.json composer.lock ./

# Install production dependencies only
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

############################################
# CI image
############################################
FROM base AS ci

# Sometimes CI images need to run as root
USER root

############################################
# Production Image
############################################
FROM base AS deploy

# Copy application files
COPY --chown=www-data:www-data . /var/www/html

# Copy vendor from composer stage
COPY --from=composer --chown=www-data:www-data /app/vendor /var/www/html/vendor

# Create volume mount directories so Docker doesn't create them as root
RUN mkdir -p /var/www/html/.infrastructure/volume_data/sqlite \
    && chown -R www-data:www-data /var/www/html/.infrastructure

USER www-data
