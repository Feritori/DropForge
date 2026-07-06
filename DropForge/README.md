# 🎰 DropForge — CS:GO Кейс Сайт

**DropForge** — это полноценная платформа для открытия кейсов с оружием CS:GO с интеграцией Steam, платёжными системами и админ-панелью.

![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Nginx](https://img.shields.io/badge/Nginx-009639?style=for-the-badge&logo=nginx&logoColor=white)

---

## ✨ Возможности

### 🎮 Для пользователей:
- 🔐 Авторизация через Steam
- 📦 Открытие кейсов с анимацией
- 🎒 Инвентарь с историей выигрышей
- 💰 Баланс и транзакции
- 🎁 Бесплатные кейсы
- 📅 Ежедневные бонусы
- ⭐ Боевой пропуск (Battle Pass)
- 🎫 Промокоды
- 🔄 Улучшение предметов (Upgrade)
- 📝 Контрактная система
- 💬 Поддержка (тикеты)
- 📊 Лента выигрышей в реальном времени
- 👥 Реферальная программа
- 💳 Пополнение через FreeKassa/YooMoney
- 📤 Вывод скинов

### 🛠️ Для администраторов:
- 📊 Панель управления
- 👥 Управление пользователями
- 📦 Создание/редактирование кейсов
- 🎒 Управление предметами
- 💰 Настройка платёжных шлюзов
- 📈 Статистика и аналитика
- 🎫 Обработка тикетов поддержки
- ⚙️ Гибкие настройки сайта

---

## 🚀 Быстрая установка

### 1. Подготовка VPS

```bash
# Клонирование или загрузка файлов
cd /var/www
sudo mkdir -p dropforge
cd dropforge

# Запуск скрипта настройки
chmod +x setup_vps.sh
sudo ./setup_vps.sh
```

### 2. Установка через браузер

Откройте в браузере:
```
http://ваш-IP-адрес/install.php
```

Следуйте инструкциям мастера установки.

### 3. Завершение

```bash
# Удалите установщик
sudo rm install.php

# Настройте SSL
sudo certbot --nginx -d ваш-домен.ru
```

📖 **Подробная инструкция:** [VPS_INSTALL_GUIDE.md](VPS_INSTALL_GUIDE.md)

---

## 📁 Структура проекта

```
DropForge/
├── install.php              # Мастер установки
├── setup_vps.sh            # Скрипт настройки VPS
├── VPS_INSTALL_GUIDE.md    # Полная документация
├── QUICK_START.md          # Быстрый старт
├── README.md               # Этот файл
├── .gitignore              # Git исключения
│
├── config/                 # Конфигурация
│   └── config.php          # Настройки БД и сайта
│
├── includes/               # Библиотеки
│   ├── database.php        # Подключение к БД
│   └── functions.php       # Вспомогательные функции
│
├── public/                 # Публичная часть (корень сайта)
│   ├── index.php           # Главная страница
│   ├── case.php            # Страница кейса
│   ├── cases.php           # Все кейсы
│   ├── inventory.php       # Инвентарь
│   ├── profile.php         # Профиль
│   ├── login.php           # Вход
│   ├── support.php         # Поддержка
│   ├── history.php         # История
│   ├── battle_pass.php     # Боевой пропуск
│   ├── daily_bonus.php     # Ежедневный бонус
│   ├── free_case.php       # Бесплатный кейс
│   ├── contract.php        # Контракты
│   ├── upgrade.php         # Улучшение
│   ├── promo.php           # Промокоды
│   ├── deposits.php        # Пополнение
│   ├── transactions.php    # Транзакции
│   │
│   ├── admin/              # Админ-панель
│   │   ├── index.php       # Главная админки
│   │   ├── api.php         # API админки
│   │   └── header.php      # Шапка админки
│   │
│   ├── api/                # Backend API
│   │   ├── auth.php        # Авторизация
│   │   ├── case.php        # API кейсов
│   │   ├── inventory.php   # API инвентаря
│   │   ├── payment/        # Платёжные шлюзы
│   │   ├── steam.php       # Steam API
│   │   └── ...
│   │
│   ├── assets/             # Статические файлы
│   │   ├── css/
│   │   ├── js/
│   │   └── images/
│   │
│   └── layouts/            # Шаблоны
│       ├── header.php
│       ├── footer.php
│       └── layout.php
│
├── database/               # SQL скрипты
│   ├── check_and_fix_structure.sql
│   ├── currency_update.sql
│   ├── free_case_items.sql
│   └── migrate_fix_errors.sql
│
└── public/sql/             # Дополнительные SQL
    ├── add_live_feed.sql
    ├── add_support_tickets.sql
    ├── create_pending_payments.sql
    ├── init_settings.sql
    └── ...
```

---

## 🗄️ База данных

Установщик автоматически создаёт 24 таблицы:

| Таблица | Описание |
|---------|----------|
| `users` | Пользователи |
| `admin_users` | Администраторы |
| `cases` | Кейсы |
| `case_items` | Предметы в кейсах |
| `categories` | Категории |
| `inventory` | Инвентарь |
| `transactions` | Транзакции |
| `payments` | Платежи |
| `settings` | Настройки сайта |
| `free_cases` | Бесплатные кейсы |
| `battle_pass_seasons` | Сезоны BP |
| `support_tickets` | Тикеты поддержки |
| и другие... | |

---

## 🔧 Требования

### Сервер:
- **PHP:** 8.1 или выше
- **MySQL:** 5.7+ / MariaDB 10.3+
- **Веб-сервер:** Nginx или Apache
- **OS:** Ubuntu 20.04+ / Debian 10+

### Расширения PHP:
- `pdo_mysql`
- `curl`
- `json`
- `session`
- `mbstring`
- `xml`

---

## 🔐 Безопасность

- ✅ HTTPS/SSL шифрование
- ✅ Prepared statements (защита от SQL-инъекций)
- ✅ XSS защита
- ✅ CSRF токены
- ✅ Хэширование паролей (bcrypt)
- ✅ Защита от brute-force
- ✅ Fail2ban интеграция
- ✅ UFW фаервол

---

## 💳 Платёжные системы

- **FreeKassa** — банковские карты, электронные кошельки
- **YooMoney** — ЮMoney (Яндекс.Деньги)
- **Cryptocurrency** — через сторонние шлюзы

Настройка производится в админ-панели.

---

## 🎨 Технологии

- **Backend:** PHP 8.1+
- **Database:** MySQL 8.0 / MariaDB
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Web Server:** Nginx
- **API:** REST-like endpoints
- **Steam:** OpenID + Web API

---

## 📖 Документация

| Файл | Описание |
|------|----------|
| [QUICK_START.md](QUICK_START.md) | Быстрый старт за 5 минут |
| [VPS_INSTALL_GUIDE.md](VPS_INSTALL_GUIDE.md) | Полное руководство по установке |
| [INSTALLATION.md](INSTALLATION.md) | Инструкция по обновлению |

---

## 🤝 Поддержка

- **Email:** support@dropforge.gg
- **Telegram:** @dropforge_support

---

## 📄 Лицензия

© 2024 DropForge. Все права защищены.

---

## 🎉 Готово к запуску!

Сайт полностью готов к развёртыванию на production-сервере.

**Начните установку прямо сейчас:**
```bash
sudo ./setup_vps.sh
# Затем откройте http://ваш-IP/install.php
```

Удачи! 🚀
