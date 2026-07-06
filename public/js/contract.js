/* DropForge — Contract JS */

let selectedItems = SELECTED_IDS.slice();

function updateUI() {
    const count = selectedItems.length;
    document.getElementById('selectedCount').textContent = count;
    
    const signBtn = document.getElementById('signBtn');
    signBtn.disabled = count < 10;
    
    // Update cards
    for (let i = 0; i < 10; i++) {
        const card = document.getElementById('card-' + i);
        if (selectedItems[i]) {
            const item = CONTRACT_INVENTORY.find(function(it) { return it.id === selectedItems[i]; });
            if (item) {
                card.classList.add('filled');
                card.innerHTML = '<img src="' + getSteamItemImage(item.item_image, 'medium') + '" alt=""><div class="contract-card-name">' + item.item_name + '</div><div class="contract-card-price">₽ ' + Math.round(item.price * 90) + '</div><div class="contract-card-rarity" style="background:' + RAIRITY_COLORS[item.rarity] + '"></div><div class="check">✓</div>';
            }
        } else {
            card.classList.remove('filled');
            card.innerHTML = '<div class="contract-card-rarity" style="background:var(--border)"></div>';
        }
    }

    // Update grid
    CONTRACT_INVENTORY.forEach(function(item) {
        const gridItem = document.querySelector('.contract-item-card[data-id="' + item.id + '"]');
        if (gridItem) {
            if (selectedItems.includes(item.id)) {
                gridItem.classList.add('selected');
            } else {
                gridItem.classList.remove('selected');
            }
        }
    });
    
    // Update total
    let total = 0;
    selectedItems.forEach(function(id) {
        const item = CONTRACT_INVENTORY.find(function(it) { return it.id === id; });
        if (item) total += item.price;
    });
    document.getElementById('totalRub').textContent = Math.round(total * 90);
    
    // Update result info
    if (count >= 10) {
        const mostCommon = getMostCommonRarity();
        if (mostCommon) {
            const nextIdx = (RAIRITY_ORDER.indexOf(mostCommon) || 0) + 1;
            if (nextIdx < RAIRITY_ORDER.length) {
                document.getElementById('resultInfo').textContent = 'В результате вы получите предмет ' + RAIRITY_ORDER[nextIdx] + ' редкости';
            }
        }
    }
}

function getMostCommonRarity() {
    const counts = {};
    selectedItems.forEach(function(id) {
        const item = CONTRACT_INVENTORY.find(function(it) { return it.id === id; });
        if (item) {
            counts[item.rarity] = (counts[item.rarity] || 0) + 1;
        }
    });
    let max = 0;
    let rarity = null;
    for (let r in counts) {
        if (counts[r] > max) {
            max = counts[r];
            rarity = r;
        }
    }
    return rarity;
}

function toggleContractItem(id) {
    const idx = selectedItems.indexOf(id);
    if (idx > -1) {
        if (selectedItems.length > 0) {
            selectedItems.splice(idx, 1);
        }
    } else if (selectedItems.length < 10) {
        selectedItems.push(id);
    }
    
    updateUI();
}

function openContractModal() {
    document.getElementById('contractModal').classList.add('active');
}

function closeContractModal() {
    document.getElementById('contractModal').classList.remove('active');
}

function signContract() {
    if (selectedItems.length < 10) return;
    
    const btn = document.getElementById('signBtn');
    btn.disabled = true;
    btn.textContent = 'ОБРАБОТКА...';
    
    setTimeout(function() {
        Modal.alert('✅ Контракт создан!', 'Вы получите предмет следующей редкости.', '🤝');
        selectedItems = [];
        updateUI();
        btn.textContent = 'СОЗДАТЬ КОНТРАКТ';
    }, 1500);
}

function clearContract() {
    selectedItems = [];
    updateUI();
}

function filterContractItems() {
    const query = document.getElementById('contractSearch').value.toLowerCase();
    document.querySelectorAll('.contract-item-card').forEach(function(el) {
        const name = el.getAttribute('data-name');
        el.style.display = name.includes(query) ? '' : 'none';
    });
}

function filterModalItems(query) {
    document.querySelectorAll('.contract-modal-item').forEach(function(el) {
        const text = el.textContent.toLowerCase();
        el.style.display = text.includes(query.toLowerCase()) ? '' : 'none';
    });
}

window.toggleContractItem = toggleContractItem;
window.openContractModal = openContractModal;
window.closeContractModal = closeContractModal;
window.signContract = signContract;
window.clearContract = clearContract;
window.filterContractItems = filterContractItems;
window.filterModalItems = filterModalItems;

updateUI();
