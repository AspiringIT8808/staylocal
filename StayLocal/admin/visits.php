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
            $status = (string)($_POST['status'] ?? 'scheduled');
            if (!in_array($status, ['scheduled', 'completed', 'cancelled'], true)) $status = 'scheduled';
            $pdo->prepare('UPDATE visits SET status = :status WHERE id = :id')->execute(['status' => $status, 'id' => (int)$_POST['id']]);
            header('Location: visits.php?notice=updated');
            exit;
        }
    }
    $visits = $pdo->query("SELECT v.*, u.unit_number, a.name AS apartment_name, a.area FROM visits v JOIN units u ON u.id = v.unit_id JOIN apartments a ON a.id = u.apartment_id ORDER BY v.visit_date ASC, v.visit_time ASC")->fetchAll();
} catch (Throwable $exception) {
    $dbError = 'Unable to load visits. Verify the MySQL connection and schema.';
    $visits = [];
}
$pageTitle = 'Visit calendar';
$currentAdminPage = 'visits';
require __DIR__ . '/partials/header.php';
?>
<?php if ($dbError): ?><div class="database-notice" role="status"><strong>Database unavailable.</strong><span><?= e($dbError) ?></span></div><?php endif; ?>
<section class="admin-page-toolbar"><div><p class="admin-lede">See who is coming in, where they are going, and what still needs confirmation.</p></div><span class="toolbar-count"><?= count($visits) ?> scheduled records</span></section>
<?php if ($error): ?><div class="form-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="admin-calendar-list"><?php if (!$visits): ?><div class="admin-panel admin-empty">No visits have been scheduled yet. New appointment requests will appear here.</div><?php else: ?><?php $lastDate = ''; foreach ($visits as $visit): $date = $visit['visit_date']; if ($date !== $lastDate): if ($lastDate !== ''): ?></div><?php endif; ?><section class="admin-day-group"><div class="admin-day-heading"><div><p class="eyebrow"><?= date('l', strtotime($date)) ?></p><h2><?= date('F j, Y', strtotime($date)) ?></h2></div><span><?= date('M j', strtotime($date)) ?></span></div><div class="admin-day-items"><?php $lastDate = $date; endif; ?><article class="visit-admin-card"><div class="visit-time"><strong><?= date('g:i A', strtotime($visit['visit_time'])) ?></strong><small>30 min viewing</small></div><div class="visit-person"><strong><?= e($visit['full_name']) ?></strong><span><?= e($visit['email']) ?> · <?= e($visit['phone']) ?></span></div><div class="visit-home"><strong>Unit <?= e($visit['unit_number']) ?></strong><span><?= e($visit['apartment_name']) ?> · <?= e($visit['area']) ?></span></div><form method="post" class="inline-status-form"><input type="hidden" name="csrf" value="<?= e(adminCsrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$visit['id'] ?>"><select name="status" aria-label="Update visit status"><option value="scheduled" <?= $visit['status'] === 'scheduled' ? 'selected' : '' ?>>Scheduled</option><option value="completed" <?= $visit['status'] === 'completed' ? 'selected' : '' ?>>Completed</option><option value="cancelled" <?= $visit['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option></select><button type="submit">Save</button></form></article><?php endforeach; ?></div></section><?php endif; ?></div>
<?php require __DIR__ . '/partials/footer.php'; ?>
