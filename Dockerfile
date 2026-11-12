FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www

# Install dependencies cần thiết
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl \
    libzip-dev \
    libmemcached-dev libssl-dev zlib1g-dev \
    && pecl install memcached-3.2.0 \
    && docker-php-ext-enable memcached

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Cài đặt extension PHP cho MySQL
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mysqli zip gd

# Cài đặt Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy composer files trước để tối ưu cache
COPY composer.lock composer.json /var/www/

# Cài đặt các gói PHP (bỏ qua dev)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy toàn bộ mã nguồn vào container
COPY . /var/www

# Cấp quyền cho storage và bootstrap/cache
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Khai báo cổng cho Render (Render sẽ tự map cổng qua biến môi trường $PORT)
EXPOSE 8000

# Chạy server Artisan kèm port của Render
CMD php artisan serve --host=0.0.0.0 --port=${PORT:-8000}