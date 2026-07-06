/* DropForge — Inventory JS */

function sellItem(id) {
    Modal.confirm('Продать предмет?', 'Вы не сможете отменить продажу.', function() {
        fetch(SITE_URL + '/api/inventory.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'sell', id })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Modal.alert('✅ Продано', formatMoney(data.amount) + ' зачислено на баланс', '💰');
                setTimeout(() => location.reload(), 500);
            } else {
                Modal.alert('Ошибка', data.error || 'Не удалось продать', '❌');
            }
        })
        .catch(() => Modal.alert('Ошибка', 'Нет соединения', '❌'));
    }, '💰');
}

function sellAll() {
    Modal.confirm('Продать всё?', 'Все предметы из инвентаря будут проданы.', function() {
        fetch(SITE_URL + '/api/inventory.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'sell_all' })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Modal.alert('✅ Продано', data.count + ' предметов за ' + formatMoney(data.amount), '💰');
                setTimeout(() => location.reload(), 500);
            } else {
                Modal.alert('Ошибка', data.error || 'Не удалось продать', '❌');
            }
        })
        .catch(() => Modal.alert('Ошибка', 'Нет соединения', '❌'));
    }, '💰');
}

function useItem(id) {
    window.location.href = '/upgrade.php?item=' + id;
}
