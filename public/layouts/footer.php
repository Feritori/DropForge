    </main>

    <!-- NOTIFICATION CONTAINER -->
    <div class="notification-container" id="notificationContainer"></div>

    <!-- LIVE FEED -->
    <div class="live-feed" id="liveFeed">
        <div class="live-feed__label">🔴 LIVE</div>
        <div class="live-feed__track">
            <div class="live-feed__items" id="liveFeedItems">
                <!-- Заполняется через JS -->
            </div>
        </div>
    </div>

    <style>
        body { padding-bottom: 60px; }
        @media (max-width: 1024px) {
            body { padding-bottom: 50px; }
        }
    </style>

    <!-- FOOTER -->
    <footer style="text-align:center; padding:2rem; color:var(--text-muted); border-top:1px solid var(--border); margin-top:2rem; margin-bottom: 60px;">
        <p>© <?= date('Y') ?> <?= e(SITE_NAME) ?> — All rights reserved. Not affiliated with Valve Corporation.</p>
    </footer>

    <!-- NOTIFICATION -->
    <div class="notification" id="notification"></div>

    <!-- WIN OVERLAY -->
    <div class="win-overlay" id="winOverlay">
        <div class="win-item" id="winItem"></div>
        <button class="btn btn--primary btn--lg" onclick="closeWin()" style="margin-top:2rem;">Забрать</button>
    </div>

    <!-- MODAL SYSTEM -->
    <div class="modal-overlay" id="modalOverlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); z-index:99999; justify-content:center; align-items:center;">
        <div class="modal-box" style="background:var(--bg-card); border-radius:16px; padding:2rem; max-width:420px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.5); border:1px solid var(--border);">
            <div id="modalIcon" style="text-align:center; font-size:3rem; margin-bottom:1rem;">📦</div>
            <div id="modalTitle" style="text-align:center; font-size:1.25rem; font-weight:700; margin-bottom:0.75rem;"></div>
            <div id="modalMessage" style="text-align:center; color:var(--text-secondary); margin-bottom:1.5rem; font-size:0.95rem; line-height:1.5;"></div>
            <div id="modalActions" style="display:flex; gap:0.75rem; justify-content:center;"></div>
        </div>
    </div>

    <script>
    const Modal = {
        show(opts) {
            const o = document.getElementById('modalOverlay');
            document.getElementById('modalIcon').textContent = opts.icon || '📦';
            document.getElementById('modalTitle').textContent = opts.title || '';
            document.getElementById('modalMessage').textContent = opts.message || '';
            const actions = document.getElementById('modalActions');
            actions.innerHTML = '';
            if (opts.onConfirm) {
                const btn = document.createElement('button');
                btn.className = 'btn btn--primary';
                btn.textContent = opts.confirmText || 'OK';
                btn.onclick = () => { o.style.display='none'; opts.onConfirm(); };
                actions.appendChild(btn);
            }
            if (opts.onCancel) {
                const btn = document.createElement('button');
                btn.className = 'btn btn--outline';
                btn.textContent = opts.cancelText || 'Отмена';
                btn.onclick = () => { o.style.display='none'; opts.onCancel(); };
                actions.appendChild(btn);
            }
            o.style.display = 'flex';
        },
        alert(title, message, icon) {
            this.show({ title, message, icon: icon||'ℹ️', confirmText:'OK' });
        },
        confirm(title, message, onConfirm, icon) {
            this.show({ title, message, icon: icon||'⚠️', confirmText:'Да', cancelText:'Отмена', onConfirm, onCancel:()=>{} });
        }
    };
    // Закрытие по клику на оверлей
    document.addEventListener('click', e => {
        if (e.target.id === 'modalOverlay') document.getElementById('modalOverlay').style.display = 'none';
    });
    </script>

    <script src="/js/main.js"></script>
    <script>
        const SITE_URL = '<?= SITE_URL ?>';
        const USER_ID  = <?= $user ? $user['id'] : 'null' ?>;
        
        // Закрытие dropdown при клике вне
        document.addEventListener('click', (e) => {
            const menu = document.getElementById('userMenu');
            if (menu && !e.target.closest('.header__user')) {
                menu.style.display = 'none';
            }
        });
        
        // ==================== LIVE FEED ====================
        function loadLiveFeed() {
            fetch(SITE_URL + '/api/support.php?action=live_feed')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.wins && data.wins.length > 0) {
                        renderLiveFeed(data.wins);
                    }
                })
                .catch(() => {});
        }
        
        function renderLiveFeed(wins) {
            const container = document.getElementById('liveFeedItems');
            if (!container) return;
            
            let html = '';
            // Дублируем для бесшовной анимации
            const items = [...wins, ...wins];
            items.forEach(w => {
                const avatar = w.user_avatar || 'https://cdn.jsdelivr.net/gh/loganmcdaniel/loganmcdaniel/avatar.svg';
                const image = w.item_image || '/assets/images/default-case.png';
                html += `
                    <div class="live-feed__item" style="border-left-color: ${w.rarity_color || '#888'}">
                        <img src="${avatar}" alt="" onerror="this.src='https://cdn.jsdelivr.net/gh/loganmcdaniel/loganmcdaniel/avatar.svg'">
                        <div class="live-feed__item-info">
                            <span class="live-feed__item-user">${escapeHtml(w.username)}</span>
                            <span class="live-feed__item-item" style="color: ${w.rarity_color || '#888'}">${escapeHtml(w.item_name)}</span>
                            <span class="live-feed__item-price">${formatMoney(w.price)}</span>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        }
        
        // ==================== NOTIFICATIONS ====================
        function notify(message, type = 'info') {
            const container = document.getElementById('notificationContainer');
            if (!container) return;
            
            const icons = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' };
            const notification = document.createElement('div');
            notification.className = `notification notification--${type}`;
            notification.innerHTML = `
                <span class="notification__icon">${icons[type] || icons.info}</span>
                <span class="notification__text">${message}</span>
            `;
            container.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }
        
        // ==================== CURRENCY MENU ====================
        function toggleCurrencyMenu() {
            const menu = document.getElementById('currencyMenu');
            menu.classList.toggle('active');
        }
        
        // Закрытие currency menu при клике вне
        document.addEventListener('click', (e) => {
            const converter = document.getElementById('currencyConverter');
            const menu = document.getElementById('currencyMenu');
            if (converter && !converter.contains(e.target) && menu) {
                menu.classList.remove('active');
            }
        });
        
        // ==================== MOBILE MENU ====================
        function toggleMobileMenu() {
            const nav = document.getElementById('mainNav');
            if (nav) nav.classList.toggle('active');
        }
        
        // ==================== UTILS ====================
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Загрузка live feed
        loadLiveFeed();
        // Обновление каждые 10 секунд
        setInterval(loadLiveFeed, 10000);
    </script>
    <?= isset($extraScripts) ? $extraScripts : '' ?>
</body>
</html>
