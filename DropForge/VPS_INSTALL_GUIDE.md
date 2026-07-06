# 🚀 DropForge — Руководство по установке на VPS

## 📋 Описание

Этот пакет содержит всё необходимое для развёртывания сайта DropForge (CS:GO кейс сайт) на вашем VPS.

**install.php** — единый файл установщика, который:
- ✅ Проверит требования сервера
- ✅ Создаст базу данных MySQL
- ✅ Создаст пользователя БД и настроит права доступа
- ✅ Создаст все необходимые таблицы (24 таблицы)
- ✅ Заполнит начальными данными
- ✅ Создаст учётную запись администратора
- ✅ Сгенерирует конфигурационный файл

---

## ⚡ Быстрый старт

### Шаг 1: Подготовка VPS

```bash
# Обновите систему
sudo apt update && sudo apt upgrade -y

# Установите PHP 8.1+ и необходимые расширения
sudo apt install -y php8.1 php8.1-cli php8.1-fpm php8.1-mysql \
    php8.1-curl php8.1-json php8.1-mbstring php8.1-xml \
    nginx mysql-server unzip git

# Установите Composer (опционально, для зависимостей)
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Шаг 2: Загрузка файлов

```bash
# Перейдите в директорию сайта
cd /var/www

# Создайте директорию для сайта
sudo mkdir -p dropforge
cd dropforge

# Загрузите файлы (через SCP, SFTP или git)
# Пример с git:
# git clone <ваш-репозиторий> .

# Или загрузите архив:
# wget <ссылка-на-архив>
# unzip archive.zip
```

### Шаг 3: Настройка прав доступа

```bash
# Установите владельца
sudo chown -R www-data:www-data /var/www/dropforge

# Установите права
sudo chmod -R 755 /var/www/dropforge
sudo chmod -R 777 /var/www/dropforge/public/uploads
```

### Шаг 4: Запуск установщика

1. Откройте браузер и перейдите по адресу:
   ```
   http://ваш-домен-или-ip/install.php
   ```

2. Следуйте инструкциям мастера установки:
   - Проверка требований
   - Настройка базы данных (укажите root-доступ к MySQL)
   - Создание таблиц
   - Настройки сайта
   - Создание администратора

3. После завершения:
   - **Удалите файл install.php** (из соображений безопасности)
   - Переместите содержимое `public/` в корень веб-сервера (если нужно)

---

## 🔧 Ручная установка (альтернатива)

### 1. Создание базы данных

```bash
# Войдите в MySQL
sudo mysql -u root -p

# Выполните SQL команды
CREATE DATABASE dropforge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dropforge'@'localhost' IDENTIFIED BY 'ваш_сложный_пароль';
GRANT ALL PRIVILEGES ON dropforge.* TO 'dropforge'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 2. Настройка конфигурации

Создайте файл `config/config.php`:

```php
<?php
define('DB_HOST',     '127.0.0.1');
define('DB_NAME',     'dropforge');
define('DB_USER',     'dropforge');
define('DB_PASS',     'ваш_пароль');
define('DB_CHARSET',  'utf8mb4');

define('SITE_URL',    'https://ваш-домен.ru');
define('STEAM_API_KEY', 'ваш-steam-api-key');
define('SITE_NAME',   'DropForge');

define('UPLOAD_DIR',  __DIR__ . '/../uploads/');
define('ASSETS_DIR',  __DIR__ . '/../public/assets/');

define('RAIRITY_ORDER', [
    'consumer', 'industrial', 'milspec', 'restricted',
    'classified', 'covert', 'extraordinary', 'contraband'
]);

define('RAIRITY_COLORS', [
    'consumer'      => '#b0c3d9',
    'industrial'    => '#5e98d9',
    'milspec'       => '#4b69ff',
    'restricted'    => '#8847ff',
    'classified'    => '#d32ce6',
    'covert'        => '#eb4b4b',
    'extraordinary' => '#e4ae39',
    'contraband'    => '#de9b35'
]);
```

### 3. Импорт структуры БД

```bash
mysql -u dropforge -p dropforge < database/check_and_fix_structure.sql
mysql -u dropforge -p dropforge < public/sql/add_live_feed.sql
mysql -u dropforge -p dropforge < public/sql/add_support_tickets.sql
mysql -u dropforge -p dropforge < public/sql/init_settings.sql
```

### 4. Настройка веб-сервера

#### Nginx конфигурация:

```nginx
server {
    listen 80;
    server_name ваш-домен.ru;
    root /var/www/dropforge/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    # SSL (после настройки Let's Encrypt)
    # listen 443 ssl http2;
    # ssl_certificate /etc/letsencrypt/live/ваш-домен.ru/fullchain.pem;
    # ssl_certificate_key /etc/letsencrypt/live/ваш-домен.ru/privkey.pem;
}
```

### 5. Установка SSL (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d ваш-домен.ru
```

---

## 📊 Структура базы данных

Установщик создаст следующие таблицы:

| Таблица | Описание |
|---------|----------|
| `users` | Пользователи сайта |
| `admin_users` | Администраторы |
| `cases` | Кейсы |
| `case_items` | Предметы в кейсах |
| `categories` | Категории кейсов |
| `inventory` | Инвентарь пользователей |
| `transactions` | История транзакций |
| `payments` | Платежи |
| `pending_payments` | Ожидающие платежи |
| `settings` | Настройки сайта |
| `free_cases` | Бесплатные кейсы |
| `free_case_items` | Предметы бесплатных кейсов |
| `daily_bonus_rewards` | Награды ежедневного бонуса |
| `battle_pass_seasons` | Сезоны боевого пропуска |
| `battle_pass_rewards` | Награды боевого пропуска |
| `promo_codes` | Промокоды |
| `promo_code_uses` | Использования промокодов |
| `live_wins` | Лента выигрышей |
| `support_tickets` | Тикеты поддержки |
| `support_messages` | Сообщения поддержки |
| `referrals` | Реферальная система |
| `withdraw_requests` | Заявки на вывод |
| `contract_items` | Контрактная система |
| `upgrade_games` | История улучшений |

---

## 🔐 Безопасность

### После установки:

1. **Удалите установщик:**
   ```bash
   rm /var/www/dropforge/install.php
   ```

2. **Запретите доступ к чувствительным файлам:**
   ```nginx
   location ~ /\. {
       deny all;
   }
   
   location ~ /config/ {
       deny all;
   }
   
   location ~ /includes/ {
       deny all;
   }
   ```

3. **Настройте фаервол:**
   ```bash
   sudo ufw allow 'Nginx Full'
   sudo ufw allow OpenSSH
   sudo ufw enable
   ```

4. **Смените пароль администратора** после первого входа

5. **Регулярно обновляйте систему:**
   ```bash
   sudo apt update && sudo apt upgrade -y
   ```

---

## 🎮 Настройка после установки

### 1. Войдите в админ-панель
```
https://ваш-домен.ru/admin/index.php
```

### 2. Настройте платёжные системы
- FreeKassa
- YooMoney
- Другие шлюзы

### 3. Создайте кейсы и предметы
- Добавьте категории
- Создайте кейсы
- Наполните кейсы предметами

### 4. Настройте Steam API
- Получите API ключ на [steamcommunity.com/dev/apikey](https://steamcommunity.com/dev/apikey)
- Добавьте ключ в настройках

### 5. Настройте ботов для выдачи скинов
- Интеграция с Steam ботами
- Настройка автоматической выдачи

---

## ❓ Решение проблем

### Ошибка "Database connection failed"
- Проверьте данные подключения в `config/config.php`
- Убедитесь, что MySQL запущен: `sudo systemctl status mysql`
- Проверьте права пользователя БД

### Ошибка 404 на страницах
- Проверьте настройки `try_files` в Nginx
- Убедитесь, что `.htaccess` обрабатывается (для Apache)

### Ошибка 500
- Проверьте логи PHP: `/var/log/php8.1-fpm.log`
- Проверьте логи Nginx: `/var/log/nginx/error.log`
- Включите отображение ошибок в PHP (только для отладки)

### Не работает авторизация через Steam
- Проверьте Steam API ключ
- Убедитесь, что URL сайта совпадает с настроенным в Steam
- Проверьте, что открыт порт 443 для HTTPS

---

## 📞 Поддержка

- Email: support@dropforge.gg
- Документация: [INSTALLATION.md](INSTALLATION.md)

---

## 📄 Лицензия

© 2024 DropForge. Все права защищены.
