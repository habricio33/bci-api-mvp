FROM php:8.2-apache

# Instala dependências de sistema necessárias para as extensões PHP
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# Configura e instala as extensões do PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        gd \
        intl \
        mbstring \
        zip \
        opcache

# Habilita módulos essenciais do Apache (mod_rewrite, mod_headers)
RUN a2enmod rewrite headers

# Copia configurações personalizadas do Apache e PHP
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/custom.ini $PHP_INI_DIR/conf.d/custom.ini

# Define diretório de trabalho
WORKDIR /var/www/html

# Copia os arquivos da aplicação
COPY . /var/www/html/

# Ajusta permissões para o usuário do Apache
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
