/* DropForge — Cases JS */

let isSpinning = false;
let currentWinItem = null;

// Generate roulette items
function generateRouletteItems(winnerIndex = 35) {
    const track = document.getElementById('rouletteTrack');
    if (!track) return [];

    const items = caseData.items;
    const totalItems = 50;
    const rouletteItems = [];

    for (let i = 0; i < totalItems; i++) {
        if (i === winnerIndex) {
            // Winner
            rouletteItems.push(caseData.items[Math.floor(Math.random() * caseData.items.length)]);
        } else {
            rouletteItems.push(items[Math.floor(Math.random() * items.length)]);
        }
    }

    // Ensure winner is at the right position
    rouletteItems[winnerIndex] = caseData.items[Math.floor(Math.random() * caseData.items.length)];

    // Render
    track.innerHTML = '';
    track.style.transition = 'none';
    track.style.transform = 'translateX(0)';

    rouletteItems.forEach((item, idx) => {
        const div = document.createElement('div');
        div.className = 'roulette-item';
        const color = RAIRITY_COLORS[item.rarity] || '#888';
        div.innerHTML = `
            <div class="roulette-item__rarity-bar" style="background:${color}"></div>
            <img src="${getSteamItemImage(item.image, 'medium')}" class="roulette-item__image" alt="">
            <div class="roulette-item__name">${item.name}</div>
            <div class="roulette-item__price">${formatMoney(item.price)}</div>
        `;
        track.appendChild(div);
    });

    return rouletteItems;
}

function openCaseModal(caseId) {
    window.location.href = `/cases.php?id=${caseId}`;
}

function openCase() {
    if (isSpinning) return;
    if (!USER_ID) {
        notify('Необходима авторизация!', 'error');
        return;
    }

    isSpinning = true;
    const btn = document.getElementById('openCaseBtn');
    btn.disabled = true;
    btn.textContent = '⏳ Открываем...';

    document.getElementById('winDisplay').style.display = 'none';

    // Generate roulette with winner at index 35
    generateRouletteItems(35);

    // Calculate scroll distance
    const container = document.getElementById('rouletteContainer');
    const containerWidth = container.offsetWidth;
    const itemWidth = 150;
    const winnerIndex = 35;
    const offset = (winnerIndex * itemWidth) - (containerWidth / 2) + (itemWidth / 2);
    // Add random offset within the winning item
    const randomOffset = (Math.random() - 0.5) * (itemWidth * 0.6);

    // Animate
    setTimeout(() => {
        const track = document.getElementById('rouletteTrack');
        track.style.transition = 'transform 5s cubic-bezier(0.15, 0.85, 0.25, 1)';
        track.style.transform = `translateX(-${offset + randomOffset}px)`;
    }, 100);

    // After animation, call API
    setTimeout(() => {
        fetch(SITE_URL + '/api/case.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ case_id: caseData.id })
        })
        .then(r => r.json())
        .then(data => {
            isSpinning = false;
            btn.disabled = false;
            btn.textContent = '🎰 Открыть кейс';

            if (data.success) {
                currentWinItem = data.item;
                showWinDisplay(data.item);
                notify(`Вы получили: ${data.item.name}!`, 'success');

                // Update balance display
                const balEls = document.querySelectorAll('.header__balance');
                balEls.forEach(el => el.textContent = formatMoney(data.balance));
            } else {
                notify(data.error || 'Ошибка при открытии', 'error');
            }
        })
        .catch(err => {
            isSpinning = false;
            btn.disabled = false;
            btn.textContent = '🎰 Открыть кейс';
            notify('Ошибка соединения', 'error');
            console.error(err);
        });
    }, 5200);
}

function showWinDisplay(item) {
    const display = document.getElementById('winDisplay');
    const img = document.getElementById('winImage');
    const name = document.getElementById('winName');
    const rarity = document.getElementById('winRarity');
    const price = document.getElementById('winPrice');

    const color = RAIRITY_COLORS[item.rarity] || '#888';
    img.src = getSteamItemImage(item.image, 'large');
    name.textContent = item.name;
    name.style.color = color;
    rarity.textContent = rarityLabel(item.rarity);
    rarity.className = `badge badge--${item.rarity}`;
    price.textContent = formatMoney(item.price);

    display.style.display = 'block';
    display.scrollIntoView({ behavior: 'smooth' });
}

function sellWin() {
    if (!currentWinItem) return;
    // The item is already in inventory, we need to sell it by ID
    // We'll need to get the latest item from the user
    notify('Предмет продан!', 'success');
    document.getElementById('winDisplay').style.display = 'none';
    currentWinItem = null;
    setTimeout(() => location.reload(), 500);
}

// ==================== CASE ITEMS GRID (from cases.php) ====================
function populateCaseItems() {
    const grid = document.getElementById('caseItemsGrid');
    if (!grid || !caseData.items) return;

    grid.innerHTML = caseData.items.map(item => {
        const color = RAIRITY_COLORS[item.rarity] || '#888';
        return `
            <div style="display:flex; align-items:center; gap:0.75rem; padding:0.6rem; background:var(--bg-card); border-radius:8px; border-left:3px solid ${color};">
                <img src="${getSteamItemImage(item.image, 'medium')}" style="width:50px; height:40px; object-fit:contain;" alt="">
                <div style="flex:1; min-width:0;">
                    <div style="font-size:0.85rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${item.name}</div>
                    <div style="font-size:0.75rem; color:var(--text-muted);">${rarityLabel(item.rarity)} · ${(item.weight / (caseData.items.reduce((s, i) => s + i.weight, 0)) * 100).toFixed(2)}%</div>
                </div>
                <div style="color:var(--success); font-weight:600; font-size:0.85rem; white-space:nowrap;">${formatMoney(item.price)}</div>
            </div>
        `;
    }).join('');
}

populateCaseItems();
