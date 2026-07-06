<?php
/**
 * Cron: Очистка просроченных промокодов Battle Pass
 * Запускать: php cleanup_bp_promos.php
 * Или через cron: 0 */1 * * * php /var/www/dropforge/public/cleanup_bp_promos.php
 */

require_once __DIR__ . '/includes/functions.php';

$db = db();

// Удаляем просроченные неиспользованные промокоды
$stmt = $db->prepare("DELETE FROM bp_promo_codes WHERE used = 0 AND expires_at < NOW()");
$deleted = $stmt->execute();

error_log("BP Promo Cleanup: " . $deleted . " expired codes deleted");

echo "✅ Cleaned up " . $deleted . " expired promo codes\n";
