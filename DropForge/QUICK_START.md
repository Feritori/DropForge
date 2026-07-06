# ⚡ DropForge — Быстрый старт

## 🚀 Установка за 5 минут

### Вариант 1: Автоматическая установка (рекомендуется)

```bash
# 1. Загрузите файлы на VPS
cd /var/www
sudo mkdir -p dropforge
cd dropforge
# Загрузите файлы через SCP/SFTP

# 2. Запустите скрипт настройки VPS
chmod +x setup_vps.sh
sudo ./setup_vps.sh

# 3. Откройте в браузере
# http://ваш-IP/install.php
```

### Вариант 2: Ручная установка

```bash
# 1. Установите зависимости
sudo apt update
sudo apt install -y nginx mysql-server php8.1-fpm php8.1-mysql php8.1-curl php8.1-json

# 2. Создайте базу данных
sudo mysql -u root -p
CREATE DATABASE dropforge CHARACTER SET utf8mb4;
CREATE USER 'dropforge'@'localhost' IDENTIFIED BY 'пароль';
GRANT ALL ON dropforge.* TO 'dropforge'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# 3. Настройте Nginx
sudo nano /etc/nginx/sites-available/dropforge
# (скопируйте конфиг из VPS_INSTALL_GUIDE.md)

sudo ln -s /etc/nginx/sites-available/dropforge /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl restart nginx

# 4. Загрузите файлы и откройте
# http://ваш-IP/install.php
```

---

## 📋 Что делает install.php?

| Шаг | Действие |
|-----|----------|
| 1 | Проверка требований (PHP, расширения) |
| 2 | Создание базы данных MySQL |
| 3 | Создание пользователя БД |
| 4 | Создание 24 таблиц |
| 5 | Заполнение настройками по умолчанию |
| 6 | Создание администратора |
| 7 | Генерация config/config.php |

---

## 🔐 Данные после установки

После завершения установщик покажет:

- **Логин администратора** (по умолчанию: `admin`)
- **Пароль администратора** (генерируется случайно)
- **Параметры базы данных**

⚠️ **Сохраните эти данные!**

---

## ✅ После установки

```bash
# 1. Удалите установщик (безопасность)
sudo rm /var/www/dropforge/install.php

# 2. Настройте SSL
sudo certbot --nginx -d ваш-домен.ru

# 3. Войдите в админ-панель
# https://ваш-домен.ru/admin/index.php
```

---

## 📁 Структура файлов

```
dropforge/
├── install.php              # ← Запустить в браузере
├── setup_vps.sh            # ← Запустить для подготовки VPS
├── VPS_INSTALL_GUIDE.md    # ← Подробная инструкция
├── QUICK_START.md          # ← Этот файл
├── config/                 # ← Создаётся при установке
│   └── config.php
└── public/                 # ← Корень веб-сайта
    ├── index.php
    ├── admin/
    ├── api/
    ├── css/
    ├── js/
    └── uploads/
```

---

## ❓ Проблемы?

### Ошибка подключения к БД
```bash
sudo systemctl status mysql
sudo mysql -u root -p
# Проверьте пользователя и права
```

### Ошибка 500
```bash
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/php8.1-fpm.log
```

### Страница не открывается
```bash
sudo systemctl status nginx
sudo nginx -t
sudo ufw status
```

---

## 📞 Поддержка

- Email: support@dropforge.gg
- Документация: `VPS_INSTALL_GUIDE.md`
