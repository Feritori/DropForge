/* DropForge — Main JS */

// ==================== NOTIFICATIONS ====================
function notify(message, type = 'info') {
    const el = document.getElementById('notification');
    if (!el) return;
    el.className = `notification notification--${type}`;
    el.textContent = message;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 3500);
}

// ==================== DROPDOWN ====================
document.addEventListener('click', (e) => {
    const btn = e.target.closest('#userMenuBtn');
    const menu = document.getElementById('userMenu');
    if (btn && menu) {
        menu.classList.toggle('show');
    } else if (menu && !menu.contains(e.target)) {
        menu.classList.remove('show');
    }
});

// ==================== FORMAT MONEY ====================
function formatMoney(amount) {
    const currency = window.SITE_CURRENCY || 'USD';
    const symbol = window.CURRENCY_SYMBOL || '$';
    
    // Форматирование числа в зависимости от валюты
    if (currency === 'RUB' || currency === 'KZT') {
        // Рубли и тенге без копеек
        return Math.round(amount).toLocaleString('ru-RU') + ' ' + symbol;
    } else {
        // Доллары с копейками
        return parseFloat(amount).toFixed(2) + ' ' + symbol;
    }
}

// ==================== SHOW WIN ====================
function showWin(item) {
    const overlay = document.getElementById('winOverlay');
    const winItem = document.getElementById('winItem');
    const color = RAIRITY_COLORS[item.rarity] || '#888';

    winItem.innerHTML = `
        <div class="win-rarity-text" style="color:${color}">${rarityLabel(item.rarity)}</div>
        <img src="${getSteamItemImage(item.image, 'large')}" class="win-item__image" alt="">
        <div class="win-item__name">${item.name}</div>
        <div class="win-item__price">${formatMoney(item.price)}</div>
    `;
    overlay.classList.add('active');
}

function closeWin() {
    const overlay = document.getElementById('winOverlay');
    overlay.classList.remove('active');
}

// ==================== GET STEAM IMAGE ====================
function getSteamItemImage(hash, size = 'large') {
    if (!hash) return '/assets/images/default-case.png';
    
    // If it's already a full URL, return as-is
    if (hash.startsWith('http')) return hash;
    
    // Only real Steam CDN hashes start with -9a81
    if (hash.startsWith('-9a81')) {
        return `https://community.cloudflare.steamstatic.com/economy/image/${hash}/${size}f`;
    }
    
    // Fake/invalid hash — return default
    return '/assets/images/default-case.png';
}

// ==================== KEYBOARD SHORTCUTS ====================
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeWin();
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    }
    if (e.key === ' ' && document.getElementById('openCaseBtn') && !document.getElementById('openCaseBtn').disabled) {
        e.preventDefault();
        openCase();
    }
});

// ==================== AUTO REFRESH BALANCE ====================
setInterval(() => {
    if (USER_ID) {
        fetch(SITE_URL + '/api/user?action=info')
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    const balEls = document.querySelectorAll('.header__balance');
                    balEls.forEach(el => el.textContent = formatMoney(d.user.balance));
                }
            }).catch(() => {});
    }
}, 10000);
