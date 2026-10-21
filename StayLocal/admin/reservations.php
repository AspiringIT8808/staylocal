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
            $reservationId = (int)($_POST['id'] ?? 0);
            $status = (string)($_POST['status'] ?? 'pending_payment');
            if (!in_array($status, ['pending_payment', 'paid', 'cancelled'], true)) $status = 'pending_payment';
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE reservations SET status = :status WHERE id = :id')->execute(['status' => $status, 'id' => $reservationId]);
            $unitStatement = $pdo->prepare('SELECT unit_id FROM reservations WHERE id = :id LIMIT 1');
            $unitStatement->execute(['id' => $reservationId]);
            $unitId = (int)$unitStatement->fetchColumn();
            if ($unitId) {
                $unitStatus = $status === 'paid' ? 'reserved' : ($status === 'cancelled' ? 'available' : 'available');
                $pdo->prepare('UPDATE units SET status = :status WHERE id = :id AND status <> \'occupied\'')->execute(['status' => $unitStatus, 'id' => $unitId]);
            }
            $pdo->commit();
            header('Location: reservations.php?notice=updated');
            exit;
        }
    }
    $reservations = $pdo->query("SELECT r.*, u.unit_number, u.unit_type, a.name AS apartment_name FROM reservations r JOIN units u ON u.id = r.unit_id JOIN apartments a ON a.id = u.apartment_id ORDER BY FIELD(r.status, 'pending_payment', 'paid', 'cancelled'), r.id DESC")->fetchAll();
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $dbError = 'Unable to load reservations. Verify the MySQL connection and schema.';
    $reservations = [];
}
$pageTitle = 'Reservations';
$currentAdminPage = 'reservations';
require __DIR__ . '/partials/header.php';
?>
<?php if ($dbError): ?><div class="database-notice" role="status"><strong>Database unavailable.</strong><span><?= e($dbError) ?></span></div><?php endif; ?>
<section class="admin-page-toolbar"><div><p class="admin-lede">Track reservation inquiries from first contact through payment and move-in.</p></div><span class="toolbar-count"><?= count($reservations) ?> total reservations</span></section>
<?php if ($error): ?><div class="form-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="admin-panel admin-list-panel"><div class="admin-table-wrap"><table class="admin-table admin-table-wide"><thead><tr><th>Renter</th><th>Unit</th><th>Move-in</th><th>Amount</th><th>Status</th><th>Update</th></tr></thead><tbody><?php foreach ($reservations as $reservation): ?><tr><td><strong><?= e($reservation['full_name']) ?></strong><small><?= e($reservation['email']) ?> · <?= e($reservation['phone']) ?></small></td><td>Unit <?= e($reservation['unit_number']) ?><small><?= e($reservation['apartment_name']) ?> · <?= e($reservation['unit_type']) ?></small></td><td><?= e(date('M j, Y', strtotime($reservation['move_in_date']))) ?></td><td><strong><?= pesos((float)$reservation['amount_due']) ?></strong></td><td><span class="status-pill status-<?= e($reservation['status']) ?>"><?= e(str_replace('_', ' ', $reservation['status'])) ?></span></td><td><form method="post" class="inline-status-form"><input type="hidden" name="csrf" value="<?= e(adminCsrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$reservation['id'] ?>"><select name="status" aria-label="Update reservation status"><option value="pending_payment" <?= $reservation['status'] === 'pending_payment' ? 'selected' : '' ?>>Pending payment</option><option value="paid" <?= $reservation['status'] === 'paid' ? 'selected' : '' ?>>Paid</option><option value="cancelled" <?= $reservation['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option></select><button type="submit">Save</button></form></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php require __DIR__ . '/partials/footer.php'; ?>
