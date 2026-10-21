<?php
require_once __DIR__ . '/../config/database.php';
requireAdmin();
$error = null;
$dbError = null;
try {
    $pdo = db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyAdminCsrf($_POST['csrf'] ?? null)) {
            $error = 'Your form expired. Refresh the page and try again.';
        } else {
            $paymentId = (int)($_POST['id'] ?? 0);
            $status = (string)($_POST['status'] ?? 'paid');
            if (!in_array($status, ['paid', 'failed', 'refunded'], true)) $status = 'paid';
            $pdo->prepare('UPDATE payments SET status = :status WHERE id = :id')->execute(['status' => $status, 'id' => $paymentId]);
            if ($status === 'refunded') {
                $reservationStatement = $pdo->prepare('SELECT reservation_id FROM payments WHERE id = :id LIMIT 1');
                $reservationStatement->execute(['id' => $paymentId]);
                $reservationId = (int)$reservationStatement->fetchColumn();
                if ($reservationId) {
                    $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = :id")->execute(['id' => $reservationId]);
                    $pdo->prepare("UPDATE units u JOIN reservations r ON r.unit_id = u.id SET u.status = 'available' WHERE r.id = :id AND u.status = 'reserved'")->execute(['id' => $reservationId]);
                }
            }
            header('Location: payments.php?notice=updated');
            exit;
        }
    }
    $payments = $pdo->query("SELECT p.*, r.full_name, r.reservation_id, u.unit_number, a.name AS apartment_name FROM payments p JOIN reservations r ON r.id = p.reservation_id JOIN units u ON u.id = r.unit_id JOIN apartments a ON a.id = u.apartment_id ORDER BY p.paid_at DESC, p.id DESC")->fetchAll();
} catch (Throwable $exception) {
    $dbError = 'Unable to load payments. Verify the MySQL connection and schema.';
    $payments = [];
}
$pageTitle = 'Payments';
$currentAdminPage = 'payments';
require __DIR__ . '/partials/header.php';
?>
<?php if ($dbError): ?><div class="database-notice" role="status"><strong>Database unavailable.</strong><span><?= e($dbError) ?></span></div><?php endif; ?>
<section class="admin-page-toolbar"><div><p class="admin-lede">Keep a clean record of reservation payments and any refunds.</p></div><span class="toolbar-count"><?= count($payments) ?> recorded payments</span></section>
<?php if ($error): ?><div class="form-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="admin-panel admin-list-panel"><div class="admin-table-wrap"><table class="admin-table admin-table-wide"><thead><tr><th>Renter</th><th>Reservation</th><th>Method</th><th>Reference</th><th>Amount</th><th>Status</th><th>Update</th></tr></thead><tbody><?php foreach ($payments as $payment): ?><tr><td><strong><?= e($payment['full_name']) ?></strong></td><td>#<?= (int)$payment['reservation_id'] ?><small>Unit <?= e($payment['unit_number']) ?> · <?= e($payment['apartment_name']) ?></small></td><td><?= e(str_replace('_', ' ', $payment['payment_method'])) ?></td><td><code><?= e($payment['transaction_reference']) ?></code></td><td><strong><?= pesos((float)$payment['amount']) ?></strong></td><td><span class="status-pill status-<?= e($payment['status']) ?>"><?= e($payment['status']) ?></span></td><td><form method="post" class="inline-status-form"><input type="hidden" name="csrf" value="<?= e(adminCsrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$payment['id'] ?>"><select name="status" aria-label="Update payment status"><option value="paid" <?= $payment['status'] === 'paid' ? 'selected' : '' ?>>Paid</option><option value="failed" <?= $payment['status'] === 'failed' ? 'selected' : '' ?>>Failed</option><option value="refunded" <?= $payment['status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option></select><button type="submit">Save</button></form></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php require __DIR__ . '/partials/footer.php'; ?>
