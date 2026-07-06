let selectedYourItem = null;
let selectedTargetItem = null;

function openInventoryModal() {
    const modal = document.getElementById('inventoryModal');
    if (modal) modal.classList.add('active');
}

function closeInventoryModal() {
    const modal = document.getElementById('inventoryModal');
    if (modal) modal.classList.remove('active');
}

function openTargetModal() {
    const modal = document.getElementById('targetModal');
    if (modal) modal.classList.add('active');
}

function closeTargetModal() {
    const modal = document.getElementById('targetModal');
    if (modal) modal.classList.remove('active');
}

function selectYourItem(item) {
    selectedYourItem = item;
    
    const yourContent = document.getElementById('yourContent');
    if (yourContent) yourContent.innerHTML = '<img src="' + getSteamItemImage(item.item_image || '', 'medium') + '" alt="">';
    const yourInfo = document.getElementById('yourInfo');
    if (yourInfo) yourInfo.style.display = 'block';
    const yourPrice = document.getElementById('yourPrice');
    if (yourPrice) yourPrice.textContent = formatMoney(item.price);
    const yourName = document.getElementById('yourName');
    if (yourName) yourName.textContent = item.name || item.item_name;
    
    closeInventoryModal();
    calculateChance();
}

function selectTargetItem(item) {
    selectedTargetItem = item;
    
    const color = RAIRITY_COLORS[item.rarity] || '#888';
    const targetContent = document.getElementById('targetContent');
    if (targetContent) {
        targetContent.innerHTML = '<img src="' + getSteamItemImage(item.image || item.item_image, 'medium') + '" alt=""><div class="panel-item-rarity" style="background:' + color + '"></div>';
    }
    const targetInfo = document.getElementById('targetInfo');
    if (targetInfo) targetInfo.style.display = 'block';
    const targetPrice = document.getElementById('targetPrice');
    if (targetPrice) targetPrice.textContent = formatMoney(item.price);
    const targetName = document.getElementById('targetName');
    if (targetName) targetName.textContent = item.name || item.item_name;
    
    closeTargetModal();
    calculateChance();
}

function calculateChance() {
    const statusEl = document.getElementById('towerStatus');
    const fillEl = document.getElementById('towerFill');
    const markerEl = document.getElementById('towerMarker');
    const btn = document.getElementById('upgradeBtn');
    const coeffEl = document.getElementById('coefficientDisplay');

    if (!selectedYourItem || !selectedTargetItem) {
        if (statusEl) statusEl.innerHTML = '';
        if (fillEl) fillEl.style.height = '0%';
        if (markerEl) markerEl.style.bottom = '0%';
        if (btn) btn.disabled = true;
        if (coeffEl) coeffEl.textContent = '';
        return;
    }

    const yourPrice = selectedYourItem.price || 0;
    const targetPrice = selectedTargetItem.price || 0;

    if (targetPrice <= yourPrice) {
        if (statusEl) statusEl.innerHTML = '';
        if (fillEl) fillEl.style.height = '0%';
        if (markerEl) markerEl.style.bottom = '0%';
        if (btn) btn.disabled = true;
        if (coeffEl) coeffEl.textContent = '';
        return;
    }

    const coefficient = targetPrice / yourPrice;
    if (coeffEl) coeffEl.textContent = 'x' + coefficient.toFixed(2) + ' КОЭФФИЦИЕНТ';

    const baseChance = (yourPrice / targetPrice) * 100;
    const houseEdge = 5.00;
    const calculatedChance = Math.max(baseChance - houseEdge, 1.0);

    const lossRate = UPGRADE_LOSS_RATE || 66.0;
    const successMultiplier = 1.0 - (lossRate / 100.0);
    const realChance = Math.min(calculatedChance * successMultiplier, 95.0);
    const finalChance = Math.max(realChance, 1.0);

    if (statusEl) statusEl.innerHTML = '<div class="tower-status-value">' + finalChance.toFixed(2) + '%</div><div class="tower-status-label">Шанс апгрейда</div>';
    if (fillEl) fillEl.style.height = finalChance + '%';
    if (markerEl) markerEl.style.bottom = finalChance + '%';
    if (fillEl) fillEl.className = 'tower-fill';
    if (btn) btn.disabled = false;
}

function doUpgrade() {
    if (!selectedYourItem || !selectedTargetItem) return;

    const btn = document.getElementById('upgradeBtn');
    const statusEl = document.getElementById('towerStatus');
    const fillEl = document.getElementById('towerFill');
    const resultEl = document.getElementById('towerResult');
    
    btn.disabled = true;
    btn.textContent = 'ОБРАБОТКА...';
    if (statusEl) statusEl.innerHTML = '<div class="tower-status-label">Подождите, идет апгрейд</div>';
    if (fillEl) { fillEl.style.height = '0%'; fillEl.className = 'tower-fill'; }
    if (resultEl) resultEl.innerHTML = '';

    const yourPrice = selectedYourItem.price || 0;
    const targetPrice = selectedTargetItem.price || 0;
    const baseChance = (yourPrice / targetPrice) * 100;
    const houseEdge = 5.00;
    const calculatedChance = Math.max(baseChance - houseEdge, 1.0);
    const lossRate = UPGRADE_LOSS_RATE || 66.0;
    const successMultiplier = 1.0 - (lossRate / 100.0);
    const realChance = Math.min(calculatedChance * successMultiplier, 95.0);
    const finalChance = Math.max(realChance, 1.0);

    const won = Math.random() * 100 < finalChance;

    setTimeout(function() {
        if (won) {
            if (fillEl) fillEl.className = 'tower-fill success';
            if (statusEl) statusEl.innerHTML = '<div class="tower-status-fail">УСПЕХ</div>';
            if (resultEl) resultEl.innerHTML = '<div class="tower-result-text">Апгрейд успешен</div><button class="tower-result-btn" onclick="resetUpgrade()">НОВЫЙ UPGRADE</button>';
            const resultBtn = document.querySelector('.tower-result-btn');
            if (resultBtn) resultBtn.style.display = 'inline-block';
            notify('Успешно! Вы получили: ' + (selectedTargetItem.name || selectedTargetItem.item_name));
            setTimeout(function() { location.reload(); }, 2000);
        } else {
            if (fillEl) fillEl.className = 'tower-fill fail';
            if (statusEl) statusEl.innerHTML = '<div class="tower-status-fail">НЕУДАЧА</div>';
            if (resultEl) resultEl.innerHTML = '<div class="tower-result-text">Апгрейд не удался</div><button class="tower-result-btn" onclick="resetUpgrade()">НОВЫЙ UPGRADE</button>';
            const resultBtn = document.querySelector('.tower-result-btn');
            if (resultBtn) resultBtn.style.display = 'inline-block';
            notify('Не повезло. Шанс был ' + finalChance.toFixed(1) + '%');
            setTimeout(function() { location.reload(); }, 1500);
        }
    }, 1500);
}
        
function resetUpgrade() {
    selectedYourItem = null;
    selectedTargetItem = null;

    const yourContent = document.getElementById('yourContent');
    if (yourContent) yourContent.innerHTML = '<div class="platform-placeholder">Выберите<br>предмет</div>';
    const yourInfo = document.getElementById('yourInfo');
    if (yourInfo) yourInfo.style.display = 'none';
    
    const targetContent = document.getElementById('targetContent');
    if (targetContent) targetContent.innerHTML = '<div class="platform-placeholder">Выберите<br>цель</div>';
    const targetInfo = document.getElementById('targetInfo');
    if (targetInfo) targetInfo.style.display = 'none';
    
    const towerStatus = document.getElementById('towerStatus');
    if (towerStatus) towerStatus.innerHTML = '';
    const towerFill = document.getElementById('towerFill');
    if (towerFill) { towerFill.style.height = '0%'; towerFill.className = 'tower-fill'; }
    const towerMarker = document.getElementById('towerMarker');
    if (towerMarker) towerMarker.style.bottom = '0%';
    const towerResult = document.getElementById('towerResult');
    if (towerResult) towerResult.innerHTML = '';
    const coefficientDisplay = document.getElementById('coefficientDisplay');
    if (coefficientDisplay) coefficientDisplay.textContent = '';
    
    const btn = document.getElementById('upgradeBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'УЛУЧШИТЬ'; }
}

function filterInventory() {
    const query = document.getElementById('inventorySearch');
    if (!query) return;
    const value = query.value.toLowerCase();
    document.querySelectorAll('#inventoryList .panel-item').forEach(function(el) {
        const name = el.getAttribute('data-name');
        el.style.display = name && name.includes(value) ? '' : 'none';
    });
}

function filterTargets() {
    const query = document.getElementById('targetSearch');
    if (!query) return;
    const value = query.value.toLowerCase();
    document.querySelectorAll('#itemsList .target-item').forEach(function(el) {
        const name = el.getAttribute('data-name');
        el.style.display = name && name.includes(value) ? '' : 'none';
    });
}

function filterTargetModal(query) {
    document.querySelectorAll('#targetModalGrid .upgrade-modal-item').forEach(function(el) {
        const text = el.textContent.toLowerCase();
        el.style.display = text.includes(query.toLowerCase()) ? '' : 'none';
    });
}

window.openInventoryModal = openInventoryModal;
window.closeInventoryModal = closeInventoryModal;
window.openTargetModal = openTargetModal;
window.closeTargetModal = closeTargetModal;
window.selectYourItem = selectYourItem;
window.selectTargetItem = selectTargetItem;
window.doUpgrade = doUpgrade;
window.resetUpgrade = resetUpgrade;
window.filterInventory = filterInventory;
window.filterTargets = filterTargets;
window.filterTargetModal = filterTargetModal;
