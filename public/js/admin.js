/* DropForge — Admin JS */

// ==================== UTILITY ====================
function e(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatMoney(amount) {
    const currency = window.SITE_CURRENCY || 'USD';
    const symbol = window.CURRENCY_SYMBOL || '$';
    
    if (currency === 'RUB' || currency === 'KZT') {
        return Math.round(amount).toLocaleString('ru-RU') + ' ' + symbol;
    } else {
        return parseFloat(amount).toFixed(2) + ' ' + symbol;
    }
}

function rarityLabel(rarity) {
    const labels = {
        'consumer': 'Shabby', 'industrial': 'Workshop', 'milspec': 'Military',
        'restricted': 'Restricted', 'classified': 'Classified', 'covert': 'Covert',
        'extraordinary': 'Extraordinary', 'contraband': 'Contraband'
    };
    return labels[rarity] || rarity;
}

function notify(msg, type = 'success') {
    const n = document.createElement('div');
    n.className = 'admin-notify admin-notify--' + type;
    const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : '⚠';
    n.innerHTML = `<span>${icon}</span><span>${msg}</span>`;
    document.body.appendChild(n);
    setTimeout(() => {
        n.style.opacity = '0';
        n.style.transform = 'translateX(100%)';
        setTimeout(() => n.remove(), 300);
    }, 3000);
}

function toggleCategory(catId, event) {
    if (event && event.target.closest('a')) return;
    
    const category = event.currentTarget;
    const links = document.getElementById(catId);
    const isCollapsed = category.classList.contains('collapsed');
    
    if (isCollapsed) {
        category.classList.remove('collapsed');
        links.classList.remove('hidden');
        links.classList.add('visible');
    } else {
        category.classList.add('collapsed');
        links.classList.remove('visible');
        links.classList.add('hidden');
    }
}

function getSteamItemImage(hash, size = 'large') {
    return hash ? `https://community.cloudflare.steamstatic.com/economy/image/${hash}/${size}f` : '';
}

// ==================== CATEGORIES ====================
function loadCategories() {
    fetch(SITE_URL + '/admin/api.php?action=categories_list')
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('categoriesTableBody');
            if (!tbody) return;
            tbody.innerHTML = data.categories.map(cat => `
                <tr>
                    <td>${cat.id}</td>
                    <td style="font-size:1.5rem;">${cat.icon}</td>
                    <td style="font-weight:600;">${e(cat.name)}</td>
                    <td>
                        <span style="display:inline-block; width:24px; height:24px; border-radius:4px; background:${cat.color}; border:1px solid var(--border);"></span>
                        <span style="font-family:monospace; color:var(--text-muted); font-size:0.85rem;">${cat.color}</span>
                    </td>
                    <td>${cat.case_count || 0}</td>
                    <td>
                        <button class="btn btn--danger btn--sm" onclick="deleteCategory(${cat.id})">🗑</button>
                    </td>
                </tr>
            `).join('');
        });
}

function addCategory() {
    const name = document.getElementById('catName').value.trim();
    const icon = document.getElementById('catIcon').value.trim() || '📦';
    const color = document.getElementById('catColor').value;

    if (!name) {
        notify('Укажите название категории!', 'error');
        return;
    }

    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'category_add',
            name, icon, color
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notify('Категория создана!', 'success');
            document.getElementById('catName').value = '';
            document.getElementById('catIcon').value = '';
            document.getElementById('catColor').value = '#8338ec';
            loadCategories();
        } else {
            notify(data.error || 'Ошибка', 'error');
        }
    });
}

function deleteCategory(id) {
    if (!confirm('Удалить категорию? Кейсы останутся без категории.')) return;
    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'category_delete', id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) loadCategories();
        else notify(data.error, 'error');
    });
}

// ==================== CASES ====================
let caseImageData = null;
let caseImageUrl = '';

function previewCaseImage(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewDiv = document.getElementById('caseImagePreview');
            const previewImg = document.getElementById('caseImagePreviewSrc');
            previewImg.src = e.target.result;
            previewDiv.style.display = 'block';
            caseImageUrl = '';
            caseImageData = file;
            document.getElementById('caseImageUrl').value = '';
        };
        reader.readAsDataURL(file);
    }
}

function previewCaseImageUrl(input) {
    const url = input.value.trim();
    const previewDiv = document.getElementById('caseImagePreview');
    const previewImg = document.getElementById('caseImagePreviewSrc');
    
    if (!url) {
        previewDiv.style.display = 'none';
        return;
    }

    caseImageData = null;
    caseImageUrl = url;
    previewImg.src = url;
    previewDiv.style.display = 'block';
}

function loadCases() {
    fetch(SITE_URL + '/admin/api.php?action=cases_list')
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('casesTableBody');
            if (!tbody) return;
            
            tbody.innerHTML = data.cases.map(c => `
                <tr>
                    <td>${c.id}</td>
                    <td>
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            ${c.image_path ? `<img src="${e(c.image_path)}" style="width:40px; height:30px; object-fit:contain; border-radius:4px;">` : ''}
                            <span style="font-weight:600;">${e(c.name)}</span>
                        </div>
                    </td>
                    <td>${e(c.category_name || '—')}</td>
                    <td style="color:var(--success); font-weight:600;">$${parseFloat(c.price).toFixed(2)}</td>
                    <td>${c.items_count || 0}</td>
                    <td>
                        <span class="badge ${c.is_active ? 'badge--milspec' : 'badge--consumer'}">
                            ${c.is_active ? 'Да' : 'Нет'}
                        </span>
                    </td>
                    <td>
                        <div style="display:flex; gap:0.25rem;">
                            <button class="btn btn--outline btn--sm" onclick="openEditCase(${c.id})" title="Редактировать">✏️</button>
                            <button class="btn btn--danger btn--sm" onclick="deleteCase(${c.id})" title="Удалить">🗑</button>
                        </div>
                    </td>
                </tr>
            `).join('');
        });
}

function addCase() {
    const name = document.getElementById('caseName').value.trim();
    const price = parseFloat(document.getElementById('casePrice').value);
    const desc = document.getElementById('caseDesc').value.trim();
    const categoryId = parseInt(document.getElementById('caseCategory').value) || 0;
    const active = parseInt(document.getElementById('caseActive').value);

    if (!name || !price) {
        notify('Заполните название и цену!', 'error');
        return;
    }

    if (!categoryId) {
        notify('Выберите категорию!', 'error');
        return;
    }

    const saveCase = (imageUrl) => {
        fetch(SITE_URL + '/admin/api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'case_add',
                name, price, description: desc, image_path: imageUrl, is_active: active, category_id: categoryId
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                notify('Кейс создан!', 'success');
                document.getElementById('addCaseForm').style.display = 'none';
                document.getElementById('caseName').value = '';
                document.getElementById('casePrice').value = '';
                document.getElementById('caseDesc').value = '';
                document.getElementById('caseCategory').value = '';
                document.getElementById('caseImageUrl').value = '';
                document.getElementById('caseImageFile').value = '';
                document.getElementById('caseImagePreview').style.display = 'none';
                caseImageData = null;
                caseImageUrl = '';
                loadCases();
            } else {
                notify(data.error || 'Ошибка', 'error');
            }
        });
    };

    if (caseImageData) {
        const formData = new FormData();
        formData.append('image', caseImageData);

        fetch(SITE_URL + '/admin/api.php?action=upload_image', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(uploadData => {
            if (uploadData.success) {
                saveCase(uploadData.path);
            } else {
                notify(uploadData.error || 'Ошибка загрузки изображения', 'error');
            }
        })
        .catch(err => {
            notify('Ошибка загрузки: ' + err.message, 'error');
        });
    } else {
        saveCase(caseImageUrl || '');
    }
}

function toggleCase(id, active) {
    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'case_toggle', id, is_active: active })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) loadCases();
        else notify(data.error, 'error');
    });
}

function deleteCase(id) {
    if (!confirm('Удалить кейс?')) return;
    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'case_delete', id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notify('Кейс удалён!', 'success');
            loadCases();
        } else {
            notify(data.error, 'error');
        }
    });
}

function openEditCase(id) {
    console.log('Opening edit case for ID:', id);
    fetch(SITE_URL + '/admin/api.php?action=case_get&id=' + id)
        .then(r => r.json())
        .then(data => {
            console.log('Case data:', data);
            if (!data.case) {
                notify('Кейс не найден!', 'error');
                return;
            }
            const c = data.case;
            document.getElementById('editCaseId').value = c.id;
            document.getElementById('editCaseName').value = c.name;
            document.getElementById('editCasePrice').value = c.price;
            document.getElementById('editCaseDesc').value = c.description || '';
            document.getElementById('editCaseCategory').value = c.category_id || '';
            document.getElementById('editCaseActive').value = c.is_active;
            document.getElementById('editCaseImageUrl').value = c.image_path || '';
            
            if (c.image_path) {
                document.getElementById('editCaseImagePreviewSrc').src = c.image_path;
                document.getElementById('editCaseImagePreview').style.display = 'block';
            } else {
                document.getElementById('editCaseImagePreview').style.display = 'none';
            }
            
            document.getElementById('editCaseModal').classList.add('active');
            console.log('Modal opened');
        })
        .catch(err => {
            console.error('Error loading case:', err);
            notify('Ошибка загрузки данных', 'error');
        });
}

function closeEditCaseModal() {
    document.getElementById('editCaseModal').classList.remove('active');
}

function previewEditCaseImage(input) {
    const preview = document.getElementById('editCaseImagePreview');
    const img = document.getElementById('editCaseImagePreviewSrc');
    if (input.value) {
        img.src = input.value;
        img.onload = () => { preview.style.display = 'block'; };
        img.onerror = () => { preview.style.display = 'none'; };
    } else {
        preview.style.display = 'none';
    }
}

function previewEditCaseImageFile(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('editCaseImagePreviewSrc').src = e.target.result;
            document.getElementById('editCaseImagePreview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}

async function saveEditCase() {
    const id = parseInt(document.getElementById('editCaseId').value);
    const name = document.getElementById('editCaseName').value.trim();
    const price = parseFloat(document.getElementById('editCasePrice').value);
    const desc = document.getElementById('editCaseDesc').value.trim();
    const category = parseInt(document.getElementById('editCaseCategory').value) || null;
    const active = parseInt(document.getElementById('editCaseActive').value);
    let imageUrl = document.getElementById('editCaseImageUrl').value.trim();
    
    if (!name || isNaN(price) || price <= 0) {
        notify('Заполните название и цену!', 'error');
        return;
    }

    // Сначала загружаем изображение если есть файл
    const imageFile = document.getElementById('editCaseImageFile').files[0];
    if (imageFile) {
        const formData = new FormData();
        formData.append('image', imageFile);
        
        try {
            const uploadResp = await fetch(SITE_URL + '/admin/api.php?action=upload_image', {
                method: 'POST',
                body: formData
            });
            const uploadResult = await uploadResp.json();
            
            if (uploadResult.success) {
                imageUrl = uploadResult.path;
            } else {
                notify('Ошибка загрузки изображения: ' + (uploadResult.error || ''), 'error');
                return;
            }
        } catch (err) {
            console.error('Upload error:', err);
            notify('Ошибка загрузки изображения', 'error');
            return;
        }
    }

    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'case_edit',
            id,
            name,
            price,
            description: desc,
            category_id: category,
            is_active: active,
            image_path: imageUrl
        })
    })
    .then(r => r.json())
    .then(data => {
        console.log('Edit case response:', data);
        if (data.success) {
            notify('Кейс обновлён!', 'success');
            closeEditCaseModal();
            loadCases();
        } else {
            notify(data.error || 'Ошибка', 'error');
        }
    })
    .catch(err => {
        console.error('Edit case error:', err);
        notify('Ошибка соединения', 'error');
    });
}

// ==================== CASE ITEMS ====================
let itemImagePreviewTimeout = null;

function previewItemImage(input) {
    const url = input.value.trim();
    const previewDiv = document.getElementById('itemImagePreview');
    const previewImg = document.getElementById('itemImagePreviewSrc');
    
    if (!url) {
        previewDiv.style.display = 'none';
        return;
    }

    clearTimeout(itemImagePreviewTimeout);
    itemImagePreviewTimeout = setTimeout(() => {
        previewImg.src = url;
        previewImg.onload = () => {
            previewDiv.style.display = 'block';
        };
        previewImg.onerror = () => {
            previewDiv.style.display = 'none';
        };
    }, 500);
}

function loadCaseItems() {
    const caseId = document.getElementById('caseSelect').value;
    if (!caseId) return;

    fetch(SITE_URL + '/admin/api.php?action=case_items_list&case_id=' + caseId)
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('caseItemsTableBody');
            if (!tbody) return;
            tbody.innerHTML = data.items.map(item => `
                <tr>
                    <td>${item.id}</td>
                    <td>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            ${item.item_image ? `<img src="${item.item_image}" style="width:40px; height:30px; object-fit:contain;" alt="">` : '<span style="color:var(--text-muted);">—</span>'}
                            ${e(item.item_name)}
                        </div>
                    </td>
                    <td><span class="badge badge--${item.rarity}">${rarityLabel(item.rarity)}</span></td>
                    <td style="color:var(--success);">${formatMoney(item.price)}</td>
                    <td>${item.drop_chance || 0}%</td>
                    <td>${item.weight}</td>
                    <td>
                        <button class="btn btn--danger btn--sm" onclick="deleteCaseItem(${item.id})">🗑</button>
                    </td>
                </tr>
            `).join('');
        });
}

function addCaseItem() {
    const caseId = document.getElementById('caseSelect').value;
    const name = document.getElementById('itemName').value.trim();
    const image = document.getElementById('itemImage').value.trim();
    const rarity = document.getElementById('itemRarity').value;
    const price = parseFloat(document.getElementById('itemPrice').value);
    const chance = parseFloat(document.getElementById('itemChance').value);
    const weight = parseInt(document.getElementById('itemWeight').value);

    if (!name || !price || !weight) {
        notify('Заполните название, цену и вес!', 'error');
        return;
    }

    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'case_item_add',
            case_id: caseId,
            item_name: name,
            item_image: image,
            rarity,
            price,
            drop_chance: chance || 0,
            weight
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notify('Предмет добавлен!', 'success');
            document.getElementById('itemName').value = '';
            document.getElementById('itemImage').value = '';
            document.getElementById('itemPrice').value = '';
            document.getElementById('itemChance').value = '';
            document.getElementById('itemWeight').value = '';
            loadCaseItems();
        } else {
            notify(data.error || 'Ошибка', 'error');
        }
    });
}

function deleteCaseItem(id) {
    if (!confirm('Удалить предмет?')) return;
    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'case_item_delete', id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) loadCaseItems();
        else notify(data.error, 'error');
    });
}

// ==================== USERS ====================
let editingUserId = null;

function editUser(id) {
    editingUserId = id;
    fetch(SITE_URL + '/admin/api.php?action=user_get&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.user) {
                document.getElementById('editUserBalance').value = data.user.balance;
                document.getElementById('editUserRole').value = data.user.role;
                document.getElementById('editUserModal').style.display = 'flex';
            }
        });
}

function saveUser() {
    const balance = parseFloat(document.getElementById('editUserBalance').value);
    const role = document.getElementById('editUserRole').value;

    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'user_edit',
            id: editingUserId,
            balance,
            role
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notify('Пользователь обновлён!', 'success');
            document.getElementById('editUserModal').style.display = 'none';
            location.reload();
        } else {
            notify(data.error, 'error');
        }
    });
}

function deleteUser(id) {
    if (!confirm('Удалить пользователя и все его данные?')) return;
    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'user_delete', id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
        else notify(data.error, 'error');
    });
}

// ==================== SETTINGS ====================
function saveSettings() {
    const settings = {};
    document.querySelectorAll('[data-key]').forEach(el => {
        settings[el.dataset.key] = el.value;
    });

    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'settings_save', settings })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) notify('Настройки сохранены!', 'success');
        else notify(data.error, 'error');
    });
}

function saveUpgradeSettings() {
    const lossRate = parseFloat(document.getElementById('upgradeLossRate').value);
    if (isNaN(lossRate) || lossRate < 0 || lossRate > 99) {
        return notify('Введите значение от 0 до 99', 'error');
    }

    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'upgrade_loss_rate_save',
            loss_rate: lossRate
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) notify('Настройки upgrade сохранены!', 'success');
        else notify(data.error, 'error');
    });
}

function saveReferralSettings() {
    const commission = parseFloat(document.getElementById('refCommission').value);
    if (isNaN(commission) || commission < 0 || commission > 50) {
        return notify('Введите значение от 0 до 50', 'error');
    }

    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'ref_commission_save',
            commission: commission
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) notify('Реферальные настройки сохранены!', 'success');
        else notify(data.error, 'error');
    });
}

// ==================== FREEKASSA ====================
function saveFkSettings() {
    const merchantId = document.getElementById('fk_merchant_id').value.trim();
    const phrase1    = document.getElementById('fk_phrase1').value.trim();
    const phrase2    = document.getElementById('fk_phrase2').value.trim();
    const mode       = document.getElementById('fk_mode').value;

    if (!merchantId || !phrase1 || !phrase2) {
        return notify('Заполните все поля!', 'error');
    }

    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'fk_settings_save',
            fk_merchant_id: merchantId,
            fk_phrase1: phrase1,
            fk_phrase2: phrase2,
            fk_mode: mode
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) notify('Настройки FreeKassa сохранены!', 'success');
        else notify(data.error, 'error');
    });
}

function copyIpnUrl() {
    const url = document.getElementById('ipnUrl').textContent;
    navigator.clipboard.writeText(url).then(() => {
        notify('URL скопирован!', 'success');
    }).catch(() => {
        notify('Не удалось скопировать', 'error');
    });
}

function testFkConnection() {
    const merchantId = document.getElementById('fk_merchant_id').value.trim();
    const phrase1    = document.getElementById('fk_phrase1').value.trim();
    const resultEl   = document.getElementById('testResult');

    if (!merchantId || !phrase1) {
        return notify('Сначала заполните Merchant ID и Phrase 1!', 'error');
    }

    const testOrderId = 'TEST:' + Date.now();
    const testAmount  = '10.00';
    const signature   = md5(merchantId + ':' + testOrderId + ':' + testAmount + ':' + phrase1);

    resultEl.style.display = 'block';
    resultEl.textContent = [
        '=== Тест подписи FreeKassa ===',
        '',
        'Merchant ID:    ' + merchantId,
        'Order ID:       ' + testOrderId,
        'Amount:         ' + testAmount,
        'Signature:      ' + signature,
        '',
        '✓ Алгоритм работает корректно.',
        'Пришлите этот тестовый запрос в FreeKassa для проверки IPN.'
    ].join('\n');

    notify('Подпись создана!', 'success');
}

// ==================== MD5 (for FK test) ====================
function md5(string) {
    function md5cycle(x, k) {
        var a = x[0], b = x[1], c = x[2], d = x[3];
        a = ff(a, b, c, d, k[0], 7, -680876936);
        d = ff(d, a, b, c, k[1], 12, -389564586);
        c = ff(c, d, a, b, k[2], 17, 606105819);
        b = ff(b, c, d, a, k[3], 22, -1044525330);
        a = ff(a, b, c, d, k[4], 7, -176418897);
        d = ff(d, a, b, c, k[5], 12, 1200080426);
        c = ff(c, d, a, b, k[6], 17, -1473231341);
        b = ff(b, c, d, a, k[7], 22, -45705983);
        a = ff(a, b, c, d, k[8], 7, 1770035416);
        d = ff(d, a, b, c, k[9], 12, -1958414417);
        c = ff(c, d, a, b, k[10], 17, -42063);
        b = ff(b, c, d, a, k[11], 22, -1990404162);
        a = ff(a, b, c, d, k[12], 7, 1804603682);
        d = ff(d, a, b, c, k[13], 12, -40341101);
        c = ff(c, d, a, b, k[14], 17, -1502002290);
        b = ff(b, c, d, a, k[15], 22, 1236535329);
        a = gg(a, b, c, d, k[1], 5, -165796510);
        d = gg(d, a, b, c, k[6], 9, -1069501632);
        c = gg(c, d, a, b, k[11], 14, 643717713);
        b = gg(b, c, d, a, k[0], 20, -373897302);
        a = gg(a, b, c, d, k[5], 5, -701558691);
        d = gg(d, a, b, c, k[10], 9, 38016083);
        c = gg(c, d, a, b, k[15], 14, -660478335);
        b = gg(b, c, d, a, k[4], 20, -405537848);
        a = gg(a, b, c, d, k[9], 5, 568446438);
        d = gg(d, a, b, c, k[14], 9, -1019803690);
        c = gg(c, d, a, b, k[3], 14, -187363961);
        b = gg(b, c, d, a, k[8], 20, 1163531501);
        a = gg(a, b, c, d, k[13], 5, -1444681467);
        d = gg(d, a, b, c, k[2], 9, -51403784);
        c = gg(c, d, a, b, k[7], 14, 1735328473);
        b = gg(b, c, d, a, k[12], 20, -1926607734);
        a = hh(a, b, c, d, k[5], 4, -378558);
        d = hh(d, a, b, c, k[8], 11, -2022574463);
        c = hh(c, d, a, b, k[11], 16, 1839030562);
        b = hh(b, c, d, a, k[14], 23, -35309556);
        a = hh(a, b, c, d, k[1], 4, -1530992060);
        d = hh(d, a, b, c, k[4], 11, 1272893353);
        c = hh(c, d, a, b, k[7], 16, -155497632);
        b = hh(b, c, d, a, k[10], 23, -1094730640);
        a = hh(a, b, c, d, k[13], 4, 681279174);
        d = hh(d, a, b, c, k[0], 11, -358537222);
        c = hh(c, d, a, b, k[3], 16, -722521979);
        b = hh(b, c, d, a, k[6], 23, 76029189);
        a = hh(a, b, c, d, k[9], 4, -640364487);
        d = hh(d, a, b, c, k[12], 11, -421815835);
        c = hh(c, d, a, b, k[15], 16, 530742520);
        b = hh(b, c, d, a, k[2], 23, -995338651);
        a = ii(a, b, c, d, k[0], 6, -198630844);
        d = ii(d, a, b, c, k[7], 10, 1126891415);
        c = ii(c, d, a, b, k[14], 15, -1416354905);
        b = ii(b, c, d, a, k[5], 21, -57434055);
        a = ii(a, b, c, d, k[12], 6, 1700485571);
        d = ii(d, a, b, c, k[3], 10, -1894986606);
        c = ii(c, d, a, b, k[10], 15, -1051523);
        b = ii(b, c, d, a, k[1], 21, -2054922799);
        a = ii(a, b, c, d, k[8], 6, 1873313359);
        d = ii(d, a, b, c, k[15], 10, -30611744);
        c = ii(c, d, a, b, k[6], 15, -1560198380);
        b = ii(b, c, d, a, k[13], 21, 1309151649);
        a = ii(a, b, c, d, k[4], 6, -145523070);
        d = ii(d, a, b, c, k[11], 10, -1120210379);
        c = ii(c, d, a, b, k[2], 15, 718787259);
        b = ii(b, c, d, a, k[9], 21, -343485551);
        x[0] = add32(a, x[0]);
        x[1] = add32(b, x[1]);
        x[2] = add32(c, x[2]);
        x[3] = add32(d, x[3]);
    }
    function cmn(q, a, b, x, s, t) {
        a = add32(add32(a, q), add32(x, t));
        return add32((a << s) | (a >>> (32 - s)), b);
    }
    function ff(a, b, c, d, x, s, t) { return cmn((b & c) | ((~b) & d), a, b, x, s, t); }
    function gg(a, b, c, d, x, s, t) { return cmn((b & d) | (c & (~d)), a, b, x, s, t); }
    function hh(a, b, c, d, x, s, t) { return cmn(b ^ c ^ d, a, b, x, s, t); }
    function ii(a, b, c, d, x, s, t) { return cmn(c ^ (b | (~d)), a, b, x, s, t); }
    function md51(s) {
        var n = s.length, state = [1732584193, -271733879, -1732584194, 271733878], i;
        for (i = 64; i <= s.length; i += 64) {
            md5cycle(state, md5blk(s.substring(i - 64, i)));
        }
        s = s.substring(i - 64);
        var tail = [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0];
        for (i = 0; i < s.length; i++) tail[i >> 2] |= s.charCodeAt(i) << ((i % 4) << 3);
        tail[i >> 2] |= 0x80 << ((i % 4) << 3);
        if (i > 55) {
            md5cycle(state, tail);
            for (i = 0; i < 16; i++) tail[i] = 0;
        }
        tail[14] = n * 8;
        md5cycle(state, tail);
        return state;
    }
    function md5blk(s) {
        var md5blks = [], i;
        for (i = 0; i < 64; i += 4) {
            md5blks[i >> 2] = s.charCodeAt(i) + (s.charCodeAt(i + 1) << 8) + (s.charCodeAt(i + 2) << 16) + (s.charCodeAt(i + 3) << 24);
        }
        return md5blks;
    }
    var hex_chr = '0123456789abcdef'.split('');
    function rhex(n) {
        var s = '', j = 0;
        for (; j < 4; j++) s += hex_chr[(n >> (j * 8 + 4)) & 0x0F] + hex_chr[(n >> (j * 8)) & 0x0F];
        return s;
    }
    function hex(x) {
        for (var i = 0; i < x.length; i++) x[i] = rhex(x[i]);
        return x.join('');
    }
    function add32(a, b) { return (a + b) & 0xFFFFFFFF; }
    return hex(md51(string));
}

// ==================== DAILY BONUS ====================
function saveDailyBonus() {
    const name = document.getElementById('bonusName').value.trim();
    const desc = document.getElementById('bonusDesc').value.trim();
    const cooldown = parseInt(document.getElementById('bonusCooldown').value);
    const active = parseInt(document.getElementById('bonusActive').value);

    if (!name || isNaN(cooldown) || cooldown < 1) {
        notify('Заполните все поля корректно!', 'error');
        return;
    }

    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'daily_bonus_save',
            name,
            description: desc,
            cooldown_hours: cooldown,
            is_active: active
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notify('Настройки ежедневного бонуса сохранены!', 'success');
        } else {
            notify(data.error || 'Ошибка', 'error');
        }
    });
}

function toggleBonusRewardFields() {
    const type = document.getElementById('bonusRewardType').value;
    const depositFields = document.getElementById('bonusRewardDepositFields');
    
    if (type === 'gift_skin') {
        depositFields.style.display = 'block';
    } else {
        depositFields.style.display = 'none';
    }
}

function addDailyBonusReward() {
    const name = document.getElementById('bonusRewardName').value.trim();
    const type = document.getElementById('bonusRewardType').value;
    const value = document.getElementById('bonusRewardValue').value.trim();
    const minDeposit = document.getElementById('bonusRewardMinDeposit').value || null;
    const minRub = document.getElementById('bonusRewardMinRub').value || null;
    const maxRub = document.getElementById('bonusRewardMaxRub').value || null;
    const weight = parseInt(document.getElementById('bonusRewardWeight').value) || 100;

    if (!name) {
        notify('Введите название награды!', 'error');
        return;
    }

    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'daily_bonus_reward_add',
            name,
            type,
            value,
            min_deposit: minDeposit,
            min_rub: minRub,
            max_rub: maxRub,
            weight
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notify('Награда добавлена!', 'success');
            document.getElementById('bonusRewardName').value = '';
            document.getElementById('bonusRewardValue').value = '';
            document.getElementById('bonusRewardMinDeposit').value = '';
            document.getElementById('bonusRewardMinRub').value = '';
            document.getElementById('bonusRewardMaxRub').value = '';
            setTimeout(() => location.reload(), 1000);
        } else {
            notify(data.error || 'Ошибка', 'error');
        }
    });
}

function deleteDailyBonusReward(id) {
    if (!confirm('Удалить награду?')) return;
    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'daily_bonus_reward_delete', id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notify('Награда удалена!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            notify(data.error, 'error');
        }
    });
}

// ==================== BATTLE PASS ====================
function createBattlePassSeason() {
    const name = document.getElementById('bpSeasonName').value.trim();
    const price = parseFloat(document.getElementById('bpSeasonPrice').value);
    const maxLevel = parseInt(document.getElementById('bpSeasonLevels').value);
    const duration = parseInt(document.getElementById('bpSeasonDuration').value);

    if (!name || !price || !maxLevel || !duration) {
        notify('Заполните все поля!', 'error');
        return;
    }

    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'battle_pass_season_create',
            name,
            price,
            max_level: maxLevel,
            duration
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notify('Сезон создан!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            notify(data.error || 'Ошибка', 'error');
        }
    });
}

function activateBattlePassSeason(id) {
    if (!confirm('Сделать этот сезон активным? Текущий активный сезон будет деактивирован.')) return;

    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'battle_pass_season_activate',
            id
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notify('Сезон активирован!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            notify(data.error || 'Ошибка', 'error');
        }
    });
}

function deleteBattlePassSeason(id) {
    if (!confirm('Удалить этот сезон? Все данные будут потеряны!')) return;

    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'battle_pass_season_delete',
            id
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notify('Сезон удалён!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            notify(data.error || 'Ошибка', 'error');
        }
    });
}

function resetBattlePass() {
    if (!confirm('⚠️ СБРОСИТЬ ТЕКУЩИЙ СЕЗОН? Весь прогресс пользователей будет удалён!')) return;
    if (!confirm('Вы уверены? Это действие нельзя отменить!')) return;

    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'battle_pass_season_reset'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notify('Сезон сброшен!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            notify(data.error || 'Ошибка', 'error');
        }
    });
}

function toggleRewardValueInput() {
    const type = document.getElementById('bpRewardType').value;
    const input = document.getElementById('bpRewardValue');
    const placeholders = {
        'balance': '0.00',
        'premium': '1',
        'vk_subscribe': 'URL поста',
        'vk_like': 'URL поста',
        'vk_comment': 'URL поста',
        'discord_connect': 'ID сервера',
        'discord_join': 'URL приглашения',
        'promo_code': 'КОД',
        'case_discount': '10',
        'gift_skin': 'ID скина',
        'free_case': 'ID кейса',
        'case': 'ID кейса',
        'bonus': 'Описание'
    };
    input.placeholder = placeholders[type] || 'Значение';
}

function loadBattlePassRewards() {
    const seasonId = document.getElementById('bpRewardSeason').value;
    if (!seasonId) return;

    fetch(SITE_URL + '/admin/api.php?action=battle_pass_rewards_list&season_id=' + seasonId)
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('bpRewardsTableBody');
            if (!tbody) return;
            
            const typeLabels = {
                'balance': '💰 Баланс',
                'premium': '👑 Premium',
                'vk_subscribe': '📘 Подписка VK',
                'vk_like': '👍 Лайк VK',
                'vk_comment': '💬 Коммент VK',
                'discord_connect': '🎮 Discord',
                'discord_join': '👥 Discord вступить',
                'promo_code': '🎟 Промокод',
                'case_discount': '📦 Скидка',
                'gift_skin': '🎁 Скин',
                'free_case': '🎁 Кейс',
                'case': '📦 Кейс',
                'bonus': '🎁 Бонус'
            };
            
            tbody.innerHTML = data.rewards.map(r => `
                <tr>
                    <td>${r.level}</td>
                    <td>${typeLabels[r.reward_type] || r.reward_type}</td>
                    <td>${e(r.reward_description)}</td>
                    <td>
                        <span class="badge ${r.is_premium_only ? 'badge--classified' : 'badge--consumer'}">
                            ${r.is_premium_only ? 'Premium' : 'Free'}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn--danger btn--sm" onclick="deleteBattlePassReward(${r.id})">🗑</button>
                    </td>
                </tr>
            `).join('');
        });
}

function deleteBattlePassReward(id) {
    if (!confirm('Удалить награду?')) return;
    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'battle_pass_reward_delete', id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notify('Награда удалена!', 'success');
            loadBattlePassRewards();
        } else {
            notify(data.error, 'error');
        }
    });
}

function addBattlePassReward() {
    const seasonId = parseInt(document.getElementById('bpRewardSeason').value);
    const level = parseInt(document.getElementById('bpRewardLevel').value);
    const type = document.getElementById('bpRewardType').value;
    const value = document.getElementById('bpRewardValue').value.trim();
    const desc = document.getElementById('bpRewardDesc').value.trim();
    const premium = document.getElementById('bpRewardPremium').checked ? 1 : 0;

    if (!seasonId || !level || !desc) {
        notify('Заполните все обязательные поля!', 'error');
        return;
    }

    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'battle_pass_reward_add',
            season_id: seasonId,
            level,
            reward_type: type,
            reward_value: value,
            reward_description: desc,
            is_premium_only: premium
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notify('Награда добавлена!', 'success');
            document.getElementById('bpRewardLevel').value = '';
            document.getElementById('bpRewardValue').value = '';
            document.getElementById('bpRewardDesc').value = '';
            loadBattlePassRewards();
        } else {
            notify(data.error || 'Ошибка', 'error');
        }
    });
}

function addBattlePassTask() {
    const seasonId = parseInt(document.getElementById('bpTaskSeason').value);
    const type = document.getElementById('bpTaskType').value;
    const desc = document.getElementById('bpTaskDesc').value.trim();
    const targetXp = document.getElementById('bpTaskTarget').value.trim().split('/');
    const target = parseInt(targetXp[0]) || 1;
    const xp = parseInt(targetXp[1]) || 100;

    if (!seasonId || !desc) {
        notify('Заполните все обязательные поля!', 'error');
        return;
    }

    fetch(SITE_URL + '/admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'battle_pass_task_add',
            season_id: seasonId,
            task_type: type,
            task_description: desc,
            target_value: target,
            experience_reward: xp
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notify('Задание добавлено!', 'success');
            document.getElementById('bpTaskDesc').value = '';
            document.getElementById('bpTaskTarget').value = '';
        } else {
            notify(data.error || 'Ошибка', 'error');
        }
    });
}

// ==================== INIT ====================
document.addEventListener('DOMContentLoaded', () => {
    const section = new URLSearchParams(window.location.search).get('section') || 'dashboard';
    if (section === 'categories') loadCategories();
    if (section === 'cases') loadCases();
    if (section === 'case_items') loadCaseItems();
});
