<?php
require_once __DIR__ . '/config/database.php';
tenantSessionStart();
if (!tenantIsLoggedIn()) {
    header('Location: tenant-login.php');
    exit;
}
// Ignore any stale pre-payment notice from an older version of the flow.
unset($_SESSION['staylocal_notice']);

$reservationId = filter_input(INPUT_GET, 'reservation_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
$reservation = null;
$error = null;
$success = false;
$dbError = null;

try {
    $pdo = db();
    $statement = $pdo->prepare('SELECT r.*, u.unit_number, u.unit_type, u.monthly_rent, u.image_url, a.name AS apartment_name, a.area FROM reservations r JOIN units u ON u.id = r.unit_id JOIN apartments a ON a.id = u.apartment_id WHERE r.id = :id AND r.tenant_id = :tenant_id LIMIT 1');
    $statement->execute(['id' => $reservationId ?: 0, 'tenant_id' => (int)$_SESSION['tenant_id']]);
    $reservation = $statement->fetch();

    if ($reservation && $_SERVER['REQUEST_METHOD'] === 'POST' && $reservation['status'] === 'pending_payment') {
        $method = (string)($_POST['payment_method'] ?? 'card');
        $allowedMethods = ['card', 'gcash', 'qr_code'];
        $reference = trim((string)($_POST['transaction_reference'] ?? ''));
        $cardholder = trim((string)($_POST['cardholder_name'] ?? ''));
        $cardNumber = preg_replace('/\D+/', '', (string)($_POST['card_number'] ?? ''));
        $cardExpiry = trim((string)($_POST['card_expiry'] ?? ''));
        $cardCvv = preg_replace('/\D+/', '', (string)($_POST['card_cvv'] ?? ''));
        if (!in_array($method, $allowedMethods, true)) {
            $error = 'Please choose a supported payment method.';
        } elseif ($method === 'card' && ($cardholder === '' || strlen($cardNumber) < 12 || strlen($cardNumber) > 19 || $cardExpiry === '' || !in_array(strlen($cardCvv), [3, 4], true))) {
            $error = 'Enter the cardholder name, a valid card number, expiry date, and security code.';
        } elseif ($reference === '') {
            $error = $method === 'card' ? 'Enter the card payment confirmation reference.' : 'Enter a payment reference or confirmation number.';
        } else {
            $pdo->beginTransaction();
            $payment = $pdo->prepare("INSERT INTO payments (reservation_id, payment_method, transaction_reference, amount, status, paid_at) VALUES (:reservation_id, :payment_method, :transaction_reference, :amount, 'paid', NOW())");
            $payment->execute([
                'reservation_id' => $reservation['id'],
                'payment_method' => $method,
                'transaction_reference' => $reference,
                'amount' => $reservation['amount_due'],
            ]);
            $updateReservation = $pdo->prepare("UPDATE reservations SET status = 'paid' WHERE id = :id");
            $updateReservation->execute(['id' => $reservation['id']]);
            $updateUnit = $pdo->prepare("UPDATE units SET status = 'reserved' WHERE id = (SELECT unit_id FROM reservations WHERE id = :id)");
            $updateUnit->execute(['id' => $reservation['id']]);
            $pdo->commit();
            $reservation['status'] = 'paid';
            $success = true;
        }
    }
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $dbError = 'The payment step needs the MySQL connection and the updated SQL schema. Import sql/staylocal.sql in XAMPP, then try again.';
}

$pageTitle = 'Online payment';
$currentPage = 'homes';
require __DIR__ . '/partials/header.php';
?>
<div class="page-shell checkout-page">
    <?php if ($dbError): ?>
        <div class="database-notice" role="status"><strong>Connect your local directory.</strong><span><?= e($dbError) ?></span></div>
    <?php elseif (!$reservation): ?>
        <div class="empty-state detail-empty"><p class="eyebrow">PAYMENT NOT FOUND</p><h1>We could not find that reservation.</h1><p>Start again from an available unit listing.</p><a class="button button-dark" href="index.php">Browse apartments</a></div>
    <?php elseif ($success): ?>
        <div class="media-modal is-open" id="payment-confirm-modal" aria-hidden="false"><div class="media-modal-backdrop" data-confirm-close></div><section class="media-modal-card confirm-card" role="dialog" aria-modal="true"><button class="media-modal-close" type="button" data-confirm-close aria-label="Close">×</button><p class="eyebrow">PAYMENT RECORDED</p><h2>Payment complete—thank you</h2><p>Your demo payment reference has been recorded. The property team will confirm it and contact you about the in-person next steps.</p><button class="button button-dark" type="button" data-confirm-close>Close</button></section></div><div class="success-panel"><p class="eyebrow">RESERVATION CONFIRMED</p><h1>Your place is held.</h1><p>Reservation #<?= (int)$reservation['id'] ?> for Unit <?= e($reservation['unit_number']) ?> is marked paid. The property team will contact <?= e($reservation['email']) ?> to confirm the next in-person steps.</p><div class="success-actions"><a class="button button-dark" href="visit.php?unit_id=<?= (int)$reservation['unit_id'] ?>">Schedule your in-person visit</a><a class="text-link" href="index.php">Return to apartments</a></div></div>
    <?php else: ?>
        <div class="checkout-layout">
            <main>
                <p class="eyebrow">STEP 2 OF 2 · ONLINE PAYMENT</p>
                <h1 class="checkout-title">Complete your reservation.</h1>
                <p class="checkout-intro">Choose a payment method and enter the confirmation reference. This demo checkout records the payment locally; connect a payment gateway before accepting live funds.</p>
                <?php if ($error): ?><div class="form-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
                <form method="post" class="form-card">
                    <input type="hidden" name="reservation_id" value="<?= (int)$reservation['id'] ?>">
                    <div class="form-section"><h2>Payment method</h2><div class="payment-options"><label class="payment-option"><input type="radio" name="payment_method" value="card" checked><span><strong>Card</strong><small>Visa, Mastercard, or debit card</small></span></label><label class="payment-option"><input type="radio" name="payment_method" value="gcash"><span><strong>GCash</strong><small>Mobile wallet confirmation</small></span></label><label class="payment-option"><input type="radio" name="payment_method" value="qr_code"><span><strong>QR code</strong><small>Scan to pay like a shopping platform</small></span></label></div></div>
                    <div class="payment-detail-panel" data-payment-details="card">
                        <div class="payment-fields-grid"><label>Cardholder name<input type="text" name="cardholder_name" autocomplete="cc-name" placeholder="Name on card"></label><label>Card number<input type="text" name="card_number" inputmode="numeric" autocomplete="cc-number" maxlength="19" placeholder="1234 5678 9012 3456"></label><label>Expiry date<input type="month" name="card_expiry" autocomplete="cc-exp"></label><label>Security code<input type="password" name="card_cvv" inputmode="numeric" autocomplete="cc-csc" maxlength="4" placeholder="CVV"></label></div>
                        <p class="payment-security-note">Demo checkout only. Card details are used for validation and are never saved.</p>
                    </div>
                    <div class="payment-detail-panel" data-payment-details="gcash" hidden><label>GCash payment reference<input type="text" name="gcash_reference" placeholder="e.g. GCash confirmation number"></label></div>
                    <div class="payment-detail-panel qr-payment-panel" data-payment-details="qr_code" hidden><div class="qr-payment-content"><img src="assets/payment-qr.png" alt="StayLocal demo payment QR code"><div><strong>Scan to pay</strong><p>Open your payment app, scan this code, then enter the confirmation reference below.</p></div></div></div>
                    <div class="form-section"><label>Payment / transaction reference<input type="text" name="transaction_reference" required placeholder="e.g. STAYLOCAL-12345"></label></div>
                    <div class="form-consent"><input id="payment-consent" type="checkbox" required><label for="payment-consent">I confirm this payment is for the reservation amount shown and understand that final documents and lease signing are handled in person.</label></div>
                    <button class="button button-dark" type="submit">Pay <?= pesos((float)$reservation['amount_due']) ?> and reserve →</button>
                </form>
            </main>
            <aside class="checkout-summary"><img src="<?= e($reservation['image_url']) ?>" alt="Unit <?= e($reservation['unit_number']) ?> interior"><div class="summary-body"><p class="eyebrow"><?= e($reservation['apartment_name']) ?> · <?= e($reservation['area']) ?></p><h2>Unit <?= e($reservation['unit_number']) ?></h2><p><?= e($reservation['unit_type']) ?></p><div class="summary-total"><span>Total due today</span><strong><?= pesos((float)$reservation['amount_due']) ?></strong><small>Reservation amount for the selected home.</small></div></div></aside>
        </div>
    <?php endif; ?>
</div>
<script>
const paymentForm=document.querySelector('.checkout-page form');
if(paymentForm){const syncPaymentFields=()=>{const method=paymentForm.querySelector('input[name=payment_method]:checked')?.value||'card';paymentForm.querySelectorAll('[data-payment-details]').forEach(panel=>{const active=panel.dataset.paymentDetails===method;panel.hidden=!active;panel.querySelectorAll('input').forEach(input=>input.disabled=!active)});};paymentForm.querySelectorAll('input[name=payment_method]').forEach(input=>input.addEventListener('change',syncPaymentFields));syncPaymentFields();}
</script><script>document.querySelectorAll('[data-confirm-close]').forEach(b=>b.addEventListener('click',()=>{const m=b.closest('.media-modal');m.classList.remove('is-open');m.setAttribute('aria-hidden','true');document.body.classList.remove('modal-lock')}));if(document.querySelector('.media-modal.is-open'))document.body.classList.add('modal-lock');</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
