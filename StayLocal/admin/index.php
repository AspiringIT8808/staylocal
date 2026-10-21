<?php
require_once __DIR__ . '/../config/database.php';
requireAdmin();
$stats = ['apartments'=>0,'units'=>0,'available'=>0,'pending'=>0,'visits'=>0,'bill_attention'=>0];
$recentReservations=[]; $recentBills=[]; $billingAvailable=false; $dbError=null;
try {
    $pdo=db();
    $stats['apartments']=(int)$pdo->query('SELECT COUNT(*) FROM apartments')->fetchColumn();
    $stats['units']=(int)$pdo->query('SELECT COUNT(*) FROM units')->fetchColumn();
    $stats['available']=(int)$pdo->query("SELECT COUNT(*) FROM units WHERE status='available'")->fetchColumn();
    $stats['pending']=(int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE status='pending_payment'")->fetchColumn();
    $stats['visits']=(int)$pdo->query("SELECT COUNT(*) FROM visits WHERE status='scheduled' AND visit_date>=CURDATE()")->fetchColumn();
    $recentReservations=$pdo->query("SELECT r.id,r.full_name,r.status,u.unit_number,a.name AS apartment_name FROM reservations r JOIN units u ON u.id=r.unit_id JOIN apartments a ON a.id=u.apartment_id ORDER BY r.id DESC LIMIT 5")->fetchAll();
} catch(Throwable $exception) { $dbError='Dashboard data could not be loaded. Check the database connection and schema.'; }
try {
    $pdo=db();
    $stats['bill_attention']=(int)$pdo->query("SELECT COUNT(*) FROM tenant_bills WHERE status IN ('unpaid','overdue','submitted')")->fetchColumn();
    $recentBills=$pdo->query("SELECT b.id,b.bill_type,b.description,b.amount,b.due_date,b.status,t.full_name,u.unit_number FROM tenant_bills b JOIN tenants t ON t.id=b.tenant_id JOIN units u ON u.id=b.unit_id ORDER BY FIELD(b.status,'overdue','unpaid','submitted','paid'),b.due_date ASC,b.id DESC LIMIT 5")->fetchAll();
    $billingAvailable=true;
} catch(Throwable $exception) { $billingAvailable=false; }
$pageTitle='Overview'; $currentAdminPage='dashboard'; require __DIR__.'/partials/header.php';
?>
<section class="dashboard-hero"><div><p class="admin-kicker">PROPERTY DESK / OVERVIEW</p><h2>Good day, <?= e((string)($_SESSION['admin_name'] ?? 'Administrator')) ?>.</h2><p>Your operational snapshot for <?= e(date('l, F j, Y')) ?>.</p></div><div class="dashboard-actions"><a class="button button-light" href="reports.php">Monthly results →</a><a class="button button-dark" href="billing.php">Manage bills</a></div></section>
<?php if($dbError): ?><div class="database-notice" role="status"><strong>Dashboard data unavailable.</strong><span><?= e($dbError) ?></span></div><?php endif; ?>
<section class="dashboard-stat-grid" aria-label="Dashboard metrics">
  <article class="dashboard-stat"><span class="stat-label">Apartment buildings</span><strong><?= $stats['apartments'] ?></strong><small>Listings in the directory</small></article>
  <article class="dashboard-stat"><span class="stat-label">Available units</span><strong><?= $stats['available'] ?><em> / <?= $stats['units'] ?> total</em></strong><small>Currently open for reservation</small></article>
  <article class="dashboard-stat dashboard-stat-attention"><span class="stat-label">Reservations to review</span><strong><?= $stats['pending'] ?></strong><small><a href="reservations.php">Open reservation queue →</a></small></article>
  <article class="dashboard-stat dashboard-stat-attention"><span class="stat-label">Bills needing attention</span><strong><?= $billingAvailable ? $stats['bill_attention'] : '—' ?></strong><small><a href="billing.php">Unpaid, overdue or submitted →</a></small></article>
  <article class="dashboard-stat"><span class="stat-label">Upcoming visits</span><strong><?= $stats['visits'] ?></strong><small><a href="visits.php">View visit calendar →</a></small></article>
</section>
<section class="admin-content-grid dashboard-panels">
  <div class="admin-panel dashboard-panel"><div class="panel-heading"><div><p class="eyebrow">LATEST ACTIVITY</p><h2>Reservations</h2></div><a class="text-link" href="reservations.php">View all →</a></div><?php if(!$recentReservations): ?><div class="admin-empty">New reservation requests will appear here.</div><?php else: ?><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Renter</th><th>Home</th><th>Status</th></tr></thead><tbody><?php foreach($recentReservations as $r): ?><tr><td><strong><?= e($r['full_name']) ?></strong><small>Reservation #<?= (int)$r['id'] ?></small></td><td>Unit <?= e($r['unit_number']) ?><small><?= e($r['apartment_name']) ?></small></td><td><span class="status-pill status-<?= e($r['status']) ?>"><?= e(str_replace('_',' ',$r['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
  <div class="admin-panel dashboard-panel"><div class="panel-heading"><div><p class="eyebrow">PAYMENT FOLLOW-UP</p><h2>Tenant bills</h2></div><a class="text-link" href="billing.php">Open billing →</a></div><?php if(!$billingAvailable): ?><div class="admin-empty">Run the billing migration to see tenant bills.</div><?php elseif(!$recentBills): ?><div class="admin-empty">No tenant bills have been created yet.</div><?php else: ?><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Tenant / unit</th><th>Bill</th><th>Amount</th><th>Status</th></tr></thead><tbody><?php foreach($recentBills as $b): ?><tr><td><strong><?= e($b['full_name']) ?></strong><small>Unit <?= e($b['unit_number']) ?></small></td><td><?= e(ucfirst($b['bill_type'])) ?><small><?= e($b['description']) ?> · Due <?= e(date('M j',strtotime($b['due_date']))) ?></small></td><td><?= pesos((float)$b['amount']) ?></td><td><span class="status-pill status-<?= e($b['status']) ?>"><?= e(str_replace('_',' ',$b['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
</section>
<section class="report-promo"><div><p class="eyebrow">MONTH-END REVIEW</p><h2>See what came in—and what is still outstanding.</h2><p>Review recorded collections, unpaid rent by unit, and overdue payments for any month.</p></div><a class="button button-dark" href="reports.php">Open monthly results →</a></section>
<?php require __DIR__.'/partials/footer.php'; ?>
