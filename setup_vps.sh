#!/bin/bash

# =============================================================================
# DropForge — Скрипт автоматической подготовки VPS
# =============================================================================
# 
# Использование:
#   chmod +x setup_vps.sh
#   sudo ./setup_vps.sh
#
# Скрипт устанавливает все необходимые зависимости для работы DropForge
# =============================================================================

set -e  # Остановить скрипт при ошибке

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # Без цвета

# Логирование
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[OK]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Проверка root прав
if [ "$EUID" -ne 0 ]; then 
    log_error "Пожалуйста, запустите скрипт от root (sudo ./setup_vps.sh)"
    exit 1
fi

log_info "🚀 Начало установки DropForge на VPS..."

# =============================================================================
# Шаг 1: Обновление системы
# =============================================================================
log_info "📦 Обновление системы..."
apt update -qq
apt upgrade -y -qq
log_success "Система обновлена"

# =============================================================================
# Шаг 2: Установка PHP и расширений
# =============================================================================
log_info "☕ Установка PHP 8.1 и расширений..."

# Проверяем версию Ubuntu/Debian
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    VERSION=$VERSION_ID
else
    log_error "Не удалось определить ОС"
    exit 1
fi

# Для Ubuntu 22.04+
if [ "$OS" = "ubuntu" ] && [ "$(echo "$VERSION >= 22.04" | bc)" -eq 1 ]; then
    apt install -y -qq software-properties-common
    add-apt-repository ppa:ondrej/php -y
    apt update -qq
fi

# Установка PHP и расширений
apt install -y -qq \
    php8.1 php8.1-cli php8.1-fpm php8.1-mysql \
    php8.1-curl php8.1-json php8.1-mbstring php8.1-xml \
    php8.1-zip php8.1-gd php8.1-bcmath php8.1-intl

log_success "PHP 8.1 установлен"

# =============================================================================
# Шаг 3: Установка Nginx
# =============================================================================
log_info "🌐 Установка Nginx..."
apt install -y -qq nginx
systemctl enable nginx
systemctl start nginx
log_success "Nginx установлен и запущен"

# =============================================================================
# Шаг 4: Установка MySQL
# =============================================================================
log_info "🗄️  Установка MySQL Server..."

# Проверяем, установлен ли уже MySQL
if ! command -v mysql &> /dev/null; then
    apt install -y -qq mysql-server
    systemctl enable mysql
    systemctl start mysql
    log_success "MySQL Server установлен и запущен"
else
    log_warning "MySQL уже установлен, пропускаем установку"
fi

# =============================================================================
# Шаг 5: Установка дополнительных утилит
# =============================================================================
log_info "🔧 Установка дополнительных утилит..."
apt install -y -qq \
    unzip git curl wget \
    certbot python3-certbot-nginx \
    ufw fail2ban

log_success "Дополнительные утилиты установлены"

# =============================================================================
# Шаг 6: Настройка фаервола
# =============================================================================
log_info "🔥 Настройка UFW фаервола..."

ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow 'Nginx Full'
ufw allow OpenSSH
ufw --force enable

log_success "Фаервол настроен"

# =============================================================================
# Шаг 7: Настройка PHP-FPM
# =============================================================================
log_info "⚙️  Настройка PHP-FPM..."

# Оптимизация php.ini
PHP_INI="/etc/php/8.1/fpm/php.ini"
if [ -f "$PHP_INI" ]; then
    sed -i 's/upload_max_filesize = .*/upload_max_filesize = 64M/' "$PHP_INI"
    sed -i 's/post_max_size = .*/post_max_size = 64M/' "$PHP_INI"
    sed -i 's/max_execution_time = .*/max_execution_time = 300/' "$PHP_INI"
    sed -i 's/max_input_time = .*/max_input_time = 300/' "$PHP_INI"
    sed -i 's/memory_limit = .*/memory_limit = 256M/' "$PHP_INI"
    
    systemctl restart php8.1-fpm
    log_success "PHP-FPM настроен"
else
    log_warning "Файл php.ini не найден"
fi

# =============================================================================
# Шаг 8: Создание директории для сайта
# =============================================================================
log_info "📁 Создание директории для сайта..."

SITE_DIR="/var/www/dropforge"
mkdir -p "$SITE_DIR"
chown -R www-data:www-data "$SITE_DIR"
chmod -R 755 "$SITE_DIR"

log_success "Директория создана: $SITE_DIR"

# =============================================================================
# Шаг 9: Создание конфигурации Nginx
# =============================================================================
log_info "📝 Создание конфигурации Nginx..."

NGINX_CONF="/etc/nginx/sites-available/dropforge"

cat > "$NGINX_CONF" << 'EOF'
server {
    listen 80;
    server_name _;
    root /var/www/dropforge/public;
    index index.php;

    # Максимальный размер загружаемых файлов
    client_max_body_size 64M;

    # Логи
    access_log /var/log/nginx/dropforge_access.log;
    error_log /var/log/nginx/dropforge_error.log;

    # Основная локация
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP обработчик
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Таймауты
        fastcgi_connect_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_read_timeout 300;
    }

    # Запрет доступа к скрытым файлам
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Запрет доступа к конфигурации
    location ~ /config/ {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Запрет доступа к includes
    location ~ /includes/ {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Запрет доступа к .sql файлам
    location ~ \.sql$ {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Кэширование статических файлов
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
EOF

# Создаём симлинк
ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/dropforge

# Удаляем дефолтную конфигурацию
rm -f /etc/nginx/sites-enabled/default

# Проверяем конфигурацию и перезапускаем Nginx
if nginx -t; then
    systemctl restart nginx
    log_success "Nginx сконфигурирован"
else
    log_error "Ошибка в конфигурации Nginx"
    exit 1
fi

# =============================================================================
# Шаг 10: Установка Composer (опционально)
# =============================================================================
log_info "📦 Установка Composer..."

if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    log_success "Composer установлен"
else
    log_warning "Composer уже установлен"
fi

# =============================================================================
# Шаг 11: Настройка безопасности MySQL
# =============================================================================
log_info "🔐 Настройка безопасности MySQL..."

# Создаём скрипт для безопасной настройки MySQL
cat > /tmp/mysql_secure.sql << 'EOF'
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
EOF

mysql -u root < /tmp/mysql_secure.sql 2>/dev/null || true
rm -f /tmp/mysql_secure.sql

log_success "MySQL настроен"

# =============================================================================
# Шаг 12: Создание информации о завершении
# =============================================================================
log_info "📋 Создание файла с информацией..."

cat > "$SITE_DIR/INSTALL_INFO.txt" << EOF
=============================================================================
DropForge — Установка завершена!
Дата: $(date '+%Y-%m-%d %H:%M:%S')
=============================================================================

✅ Установлено и настроено:
- PHP 8.1 с необходимыми расширениями
- Nginx веб-сервер
- MySQL Server
- Composer
- Certbot (SSL)
- UFW фаервол
- Fail2ban

📁 Директория сайта: $SITE_DIR
🌐 Сайт доступен по: http://$(hostname -I | awk '{print $1}')

📝 СЛЕДУЮЩИЕ ШАГИ (ТОЛЬКО ЧЕРЕЗ БРАУЗЕР!):

1. Загрузите файлы DropForge на сервер:
   
   Вариант A — Git (рекомендуется):
     cd /var/www
     git clone <ваш-репозиторий> dropforge
     chown -R www-data:www-data dropforge
   
   Вариант B — SCP/SFTP:
     # Загрузите все файлы из папки DropForge/ в /var/www/dropforge/
     scp -r DropForge/* root@ваш-сервер:/var/www/dropforge/

2. Откройте установщик в браузере:
   http://ваш-IP/install.php
   
   🎯 ТОЛЬКО ЧЕРЕЗ БРАУЗЕР — никаких SSH команд!
   Установщик сам:
   - Создаст базу данных MySQL
   - Создаст таблицу и данные
   - Настроит сайт
   - Создаст config.php
   - Создаст админа

3. После установки:
   - Удалите install.php (безопасность!)
   - Настройте SSL: sudo certbot --nginx -d ваш-домен.ru
   - Настройте платёжки в админ-панели

🔐 Настройка SSL (после привязки домена):
   sudo certbot --nginx -d ваш-домен.ru

📊 Логи:
   Nginx:    /var/log/nginx/
   PHP-FPM:  /var/log/php8.1-fpm.log
   MySQL:    /var/log/mysql/

=============================================================================
EOF

log_success "Файл INSTALL_INFO.txt создан"

# =============================================================================
# Завершение
# =============================================================================
echo ""
echo "============================================================================="
echo -e "${GREEN}✅ Сервер готов к установке DropForge!${NC}"
echo "============================================================================="
echo ""
echo -e "${BLUE}📁 Директория сайта:${NC} $SITE_DIR"
echo ""
echo -e "${YELLOW}📝 ДАЛЬШЕ — ТОЛЬКО ЧЕРЕЗ БРАУЗЕР, БЕЗ SSH:${NC}"
echo ""
echo "   1. Загрузите файлы DropForge в $SITE_DIR"
echo "      (scp -r DropForge/* root@сервер:$SITE_DIR/)"
echo ""
echo "   2. Откройте в браузере:"
echo -e "      ${GREEN}http://$(hostname -I | awk '{print $1}')/install.php${NC}"
echo ""
echo "   3. Следуйте мастеру установки — всё делается через веб!"
echo ""
echo "📄 Подробная инструкция: $SITE_DIR/INSTALL_INFO.txt"
echo ""
echo "============================================================================="
echo ""

exit 0
