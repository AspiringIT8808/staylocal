<?php
require_once __DIR__ . '/config/database.php';
tenantSessionStart();
if (!tenantIsLoggedIn()) {
    $returnUnit = filter_input(INPUT_GET, 'unit_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'unit_id', FILTER_VALIDATE_INT);
    header('Location: tenant-login.php?next=' . urlencode('reservation.php?unit_id=' . (int)$returnUnit));
    exit;
}

$tenant = currentTenant();
if (!$tenant) { header('Location: tenant-login.php?session=expired'); exit; }
$unitId = filter_input(INPUT_GET, 'unit_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'unit_id', FILTER_VALIDATE_INT);
$selectedDate = trim((string)($_GET['date'] ?? $_POST['move_in_date'] ?? ''));
$unit = null;
$apartment = null;
$existingHome = null;
$error = null;
$dbError = null;

$activeHomeSql = "SELECT r.id,r.status AS reservation_status,r.move_in_date,u.unit_number,u.status AS unit_status,a.name AS apartment_name,a.area FROM reservations r JOIN units u ON u.id=r.unit_id JOIN apartments a ON a.id=u.apartment_id WHERE r.tenant_id=:tenant AND (r.status='pending_payment' OR (r.status='paid' AND u.status IN ('occupied','reserved'))) ORDER BY CASE WHEN u.status='occupied' THEN 0 ELSE 1 END,r.id DESC LIMIT 1";

try {
    $pdo = db();
    $statement = $pdo->prepare('SELECT u.*, a.name AS apartment_name, a.area, a.address, a.pet_friendly FROM units u JOIN apartments a ON a.id = u.apartment_id WHERE u.id = :id LIMIT 1');
    $statement->execute(['id' => $unitId ?: 0]);
    $unit = $statement->fetch();
    if ($unit) {
        $apartment = ['name'=>$unit['apartment_name'],'area'=>$unit['area'],'address'=>$unit['address'],'pet_friendly'=>$unit['pet_friendly']];
    }
    $homeCheck = $pdo->prepare($activeHomeSql);
    $homeCheck->execute(['tenant'=>(int)$tenant['id']]);
    $existingHome = $homeCheck->fetch() ?: null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $unit) {
        $fullName = trim((string)($tenant['full_name'] ?? ''));
        $email = trim((string)($tenant['email'] ?? ''));
        $phone = trim((string)($tenant['phone'] ?? ''));
        $moveInDate = trim((string)($_POST['move_in_date'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if (!verifyTenantCsrf($_POST['csrf'] ?? null)) {
            $error = 'Your form expired. Refresh the page and try again.';
        } elseif ($existingHome) {
            $error = 'Your account already has an active or pending unit. Clear or close that residence with property management before requesting another.';
        } elseif ($unit['status'] !== 'available') {
            $error = 'This unit is no longer available for reservation.';
        } elseif ($fullName === '' || $phone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide your full name, a valid email address, and a phone number.';
        } elseif (!$moveInDate || strtotime($moveInDate) === false || $moveInDate < date('Y-m-d')) {
            $error = 'Please choose a move-in date from today onward.';
        } else {
            $pdo->beginTransaction();
            // Serialize booking attempts for this tenant and recheck active residence status inside the transaction.
            $pdo->prepare('SELECT id FROM tenants WHERE id=:id FOR UPDATE')->execute(['id'=>(int)$tenant['id']]);
            $lockedHome = $pdo->prepare($activeHomeSql . ' FOR UPDATE');
            $lockedHome->execute(['tenant'=>(int)$tenant['id']]);
            $activeNow = $lockedHome->fetch() ?: null;
            if ($activeNow) {
                $pdo->rollBack();
                $existingHome = $activeNow;
                $error = 'Your account already has an active or pending unit. Clear or close that residence with property management before requesting another.';
            } else {
                $lockedUnit = $pdo->prepare('SELECT status,monthly_rent FROM units WHERE id=:id FOR UPDATE');
                $lockedUnit->execute(['id'=>(int)$unit['id']]);
                $freshUnit = $lockedUnit->fetch();
                if (!$freshUnit || $freshUnit['status'] !== 'available') {
                    $pdo->rollBack();
                    $error = 'This unit is no longer available for reservation.';
                } else {
                    $insert = $pdo->prepare("INSERT INTO reservations (tenant_id, unit_id, full_name, email, phone, move_in_date, notes, amount_due, status) VALUES (:tenant_id, :unit_id, :full_name, :email, :phone, :move_in_date, :notes, :amount_due, 'pending_payment')");
                    $insert->execute([
                        'tenant_id'=>(int)$tenant['id'],'unit_id'=>(int)$unit['id'],'full_name'=>$fullName,'email'=>$email,
                        'phone'=>$phone,'move_in_date'=>$moveInDate,'notes'=>$notes ?: null,'amount_due'=>$freshUnit['monthly_rent'],
                    ]);
                    $newReservationId = (int)$pdo->lastInsertId();
                    $pdo->commit();
                    header('Location: payment.php?reservation_id=' . $newReservationId);
                    exit;
                }
            }
        }
    }
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    $dbError = 'The reservation flow needs the MySQL connection. Import sql/staylocal.sql in XAMPP, then try again.';
}

$pageTitle = $unit ? 'Reserve Unit ' . $unit['unit_number'] : 'Reserve a unit';
$currentPage = 'homes';
require __DIR__ . '/partials/header.php';
?>
<div class="page-shell checkout-page">
    <?php if ($dbError): ?>
        <div class="database-notice" role="status"><strong>Connect your local directory.</strong><span><?= e($dbError) ?></span></div>
    <?php elseif (!$unit): ?>
        <div class="empty-state detail-empty"><p class="eyebrow">RESERVATION NOT FOUND</p><h1>Choose a unit first.</h1><p>Return to the listings and open the unit you want to reserve.</p><a class="button button-dark" href="index.php">Browse apartments</a></div>
    <?php elseif ($existingHome): ?>
        <div class="active-home-notice" role="status">
            <p class="eyebrow">ONE ACTIVE HOME AT A TIME</p>
            <h1>Your account already has a unit.</h1>
            <p><?= e($existingHome['apartment_name']) ?> · Unit <?= e($existingHome['unit_number']) ?> is currently linked to your account<?= $existingHome['reservation_status']==='pending_payment' ? ' as a reservation awaiting payment' : '' ?>. To move to another home, contact property management to close or update the existing residence first.</p>
            <a class="button button-dark" href="tenant-dashboard.php">View your residence →</a>
            <a class="text-link" href="unit.php?id=<?= (int)$unit['id'] ?>">Back to this unit</a>
        </div>
    <?php else: ?>
        <a class="back-link" href="unit.php?id=<?= (int)$unit['id'] ?>">← Back to Unit <?= e($unit['unit_number']) ?></a>
        <div class="checkout-layout">
            <main>
                <p class="eyebrow">STEP 1 OF 2 · RESERVATION</p>
                <h1 class="checkout-title">Reserve your place.</h1>
                <p class="checkout-intro">Your tenant account details will be attached to this reservation. Choose your move-in date and add any notes before continuing to payment.</p>
                <?php if ($error): ?><div class="form-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
                <?php if ($existingHome): ?><div class="form-alert" role="alert">An active unit is already linked to this account. Please return to your residence page.</div><?php else: ?>
                <form method="post" class="form-card">
                    <input type="hidden" name="csrf" value="<?= e(tenantCsrfToken()) ?>">
                    <input type="hidden" name="unit_id" value="<?= (int)$unit['id'] ?>">
                    <div class="form-section"><h2>Renter details</h2><div class="tenant-summary"><strong><?= e($tenant['full_name']) ?></strong><span><?= e($tenant['email']) ?> · <?= e($tenant['phone']) ?></span><a href="tenant-dashboard.php">Edit details in your tenant portal</a></div></div>
                    <div class="form-section"><h2>Move-in details</h2><div class="form-grid reservation-fields"><label class="reservation-date-card">Preferred move-in date<input type="date" name="move_in_date" min="<?= date('Y-m-d') ?>" required value="<?= e($_POST['move_in_date'] ?? '') ?>"></label><label class="reservation-notes-card">Pets or accessibility notes<textarea name="notes" rows="4" placeholder="Optional"><?= e($_POST['notes'] ?? '') ?></textarea></label></div></div>
                    <div class="form-consent"><input id="reservation-consent" type="checkbox" required><label for="reservation-consent">I understand this reservation starts the online payment step. Property viewing, document review, and final contract signing are completed in person.</label></div>
                    <button class="button button-dark" type="submit">Continue to online payment →</button>
                </form><?php endif; ?>
            </main>
            <aside class="checkout-summary"><img src="<?= e($unit['image_url'] ?: 'assets/feat-view.jpg') ?>" alt="Unit <?= e($unit['unit_number']) ?> interior"><div class="summary-body"><p class="eyebrow"><?= e($unit['apartment_name']) ?> · <?= e($unit['area']) ?></p><h2>Unit <?= e($unit['unit_number']) ?></h2><p><?= e($unit['unit_type']) ?> · <?= (int)$unit['size_sqm'] ?> sqm</p><div class="summary-total"><span>Reservation amount</span><strong><?= pesos((float)$unit['monthly_rent']) ?></strong><small>One month rent, applied to your move-in balance.</small></div></div></aside>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
