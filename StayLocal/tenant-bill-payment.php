<?php
require_once __DIR__ . '/config/database.php';
requireTenant();
$tenant = currentTenant();
if (!$tenant) { header('Location: tenant-login.php?session=expired'); exit; }
$billId = filter_input(INPUT_GET, 'bill_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'bill_id', FILTER_VALIDATE_INT);
$pdo = db();
$statement = $pdo->prepare('SELECT b.*, u.unit_number, u.unit_type, u.size_sqm, u.floor_number, u.image_url, a.name AS apartment_name, a.area FROM tenant_bills b JOIN units u ON u.id=b.unit_id JOIN apartments a ON a.id=u.apartment_id WHERE b.id=:id AND b.tenant_id=:tenant LIMIT 1');
$statement->execute(['id' => $billId ?: 0, 'tenant' => (int)$tenant['id']]);
$bill = $statement->fetch();
$error = null;
$success = false;
$reference = null;
$selectedMethod = null;

if ($bill && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyTenantCsrf($_POST['csrf'] ?? null)) {
        $error = 'Your form expired. Refresh the page and try again.';
    } elseif (!in_array($bill['status'], ['unpaid', 'overdue'], true)) {
        $error = 'This bill is no longer available for payment.';
    } else {
        $selectedMethod = (string)($_POST['payment_method'] ?? '');
        if (!in_array($selectedMethod, ['gcash', 'card', 'qr_code'], true)) {
            $error = 'Select a payment option to continue.';
        } elseif ($selectedMethod === 'card' && (trim((string)($_POST['cardholder_name'] ?? '')) === '' || strlen(preg_replace('/\D+/', '', (string)($_POST['card_number'] ?? ''))) < 12 || trim((string)($_POST['card_expiry'] ?? '')) === '' || !in_array(strlen(preg_replace('/\D+/', '', (string)($_POST['card_cvv'] ?? ''))), [3, 4], true))) {
            $error = 'Enter the cardholder name, a valid card number, expiry date, and security code.';
        } elseif (!isset($_POST['payment_consent'])) {
            $error = 'Confirm the payment details before continuing.';
        } else {
            $reference = trim((string)($_POST['transaction_reference'] ?? ''));
            if ($reference === '' || strlen($reference) > 100) {
                $error = 'Enter the transaction number or confirmation reference from your payment app.';
            } else {
                $update = $pdo->prepare("UPDATE tenant_bills SET payment_reference=:reference,status='submitted' WHERE id=:id AND tenant_id=:tenant AND status IN ('unpaid','overdue')");
                $update->execute(['reference' => $reference, 'id' => (int)$bill['id'], 'tenant' => (int)$tenant['id']]);
                $success = $update->rowCount() === 1;
                if (!$success) { $error = 'This bill has already changed status. Return to your billing page.'; }
            }
        }
    }
}

$pageTitle = 'Pay a bill';
$currentPage = 'tenant';
require __DIR__ . '/partials/header.php';
?>
<div class="page-shell checkout-page bill-checkout">
    <a class="back-link" href="tenant-dashboard.php">← Back to tenant billing</a>
    <?php if (!$bill): ?>
        <div class="empty-state"><h1>Bill unavailable</h1><p>We could not find an unpaid bill belonging to your account.</p><a class="button button-dark" href="tenant-dashboard.php">Return to billing</a></div>
    <?php elseif ($success): ?>
        <section class="bill-payment-result" role="status">
            <p class="eyebrow">PAYMENT OPTION SUBMITTED</p>
            <h1>Payment request recorded.</h1>
            <p>Your selected payment option was submitted for property-admin verification. This demo does not charge a card, open a wallet, or transfer funds.</p>
            <dl class="bill-receipt-details">
                <dt>Tenant</dt><dd><?= e($tenant['full_name']) ?></dd>
                <dt>Property / unit</dt><dd><?= e($bill['apartment_name']) ?> · Unit <?= e($bill['unit_number']) ?></dd>
                <dt>Bill</dt><dd><?= e(ucfirst($bill['bill_type'])) ?> — <?= e($bill['description']) ?></dd>
                <dt>Payment option</dt><dd><?= e(['gcash'=>'GCash','card'=>'Card','qr_code'=>'QR code'][$selectedMethod] ?? '') ?></dd>
                <dt>Amount</dt><dd><strong><?= pesos((float)$bill['amount']) ?></strong></dd>
                <dt>Due date</dt><dd><?= e(date('M j, Y', strtotime($bill['due_date']))) ?></dd>
                <dt>Demo reference</dt><dd><code><?= e((string)$reference) ?></code></dd>
            </dl>
            <a class="button button-dark" href="tenant-dashboard.php">Return to bills</a>
        </section>
    <?php else: ?>
        <div class="checkout-layout bill-checkout-layout">
            <main>
                <p class="eyebrow">TENANT BILLING · PAYMENT</p>
                <h1 class="checkout-title">Pay your bill.</h1>
                <p class="checkout-intro">Review your bill and residence details, choose a payment option, then continue. This demo records your selection for admin verification; connect a payment gateway before accepting live funds.</p>
                <?php if ($error): ?><div class="form-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
                <form method="post" class="form-card bill-payment-form">
                    <input type="hidden" name="csrf" value="<?= e(tenantCsrfToken()) ?>">
                    <input type="hidden" name="bill_id" value="<?= (int)$bill['id'] ?>">
                    <div class="form-section">
                        <h2>Payment option</h2>
                        <div class="payment-options">
                            <label class="payment-option"><input type="radio" name="payment_method" value="gcash" <?= ($selectedMethod ?? 'gcash') === 'gcash' ? 'checked' : '' ?>><span><strong>GCash</strong><small>Wallet option · demo flow</small></span></label>
                            <label class="payment-option"><input type="radio" name="payment_method" value="card" <?= ($selectedMethod ?? '') === 'card' ? 'checked' : '' ?>><span><strong>Card</strong><small>Card option · demo flow</small></span></label>
                            <label class="payment-option"><input type="radio" name="payment_method" value="qr_code" <?= ($selectedMethod ?? '') === 'qr_code' ? 'checked' : '' ?>><span><strong>QR code</strong><small>Scan to pay like a shopping platform</small></span></label>
                        </div>
                    </div>
                    <div class="payment-detail-panel" data-payment-details="card"><div class="payment-fields-grid"><label>Cardholder name<input type="text" name="cardholder_name" autocomplete="cc-name" placeholder="Name on card"></label><label>Card number<input type="text" name="card_number" inputmode="numeric" autocomplete="cc-number" maxlength="19" placeholder="1234 5678 9012 3456"></label><label>Expiry date<input type="month" name="card_expiry" autocomplete="cc-exp"></label><label>Security code<input type="password" name="card_cvv" inputmode="numeric" autocomplete="cc-csc" maxlength="4" placeholder="CVV"></label></div><p class="payment-security-note">Demo checkout only. Card details are never saved.</p></div>
                    <div class="payment-detail-panel" data-payment-details="gcash" hidden><p class="payment-inline-note">Open GCash, complete the payment, then enter the confirmation reference after returning here.</p></div>
                    <div class="payment-detail-panel qr-payment-panel" data-payment-details="qr_code" hidden><div class="qr-payment-content"><img src="assets/payment-qr.png" alt="StayLocal demo payment QR code"><div><strong>Scan to pay</strong><p>Use your payment app to scan the code, then submit the reference for admin verification.</p></div></div></div>
                    <div class="form-section"><label>Transaction reference<input type="text" name="transaction_reference" maxlength="100" required placeholder="e.g. GCash-20261021-8F4K2M or QR-20261021-8F4K2M"></label></div>
                    <div class="form-consent"><input id="bill-payment-consent" type="checkbox" name="payment_consent" value="1" required><label for="bill-payment-consent">I have reviewed the bill details and understand this demo records my chosen payment option for administrator verification; it does not transfer money.</label></div>
                    <button class="button button-dark full-width" type="submit">Pay <?= pesos((float)$bill['amount']) ?> →</button>
                </form>
            </main>
            <aside class="checkout-summary bill-summary">
                <img src="<?= e($bill['image_url'] ?: 'assets/feat-view.jpg') ?>" alt="Unit <?= e($bill['unit_number']) ?>">
                <div class="summary-body">
                    <p class="eyebrow"><?= e($bill['apartment_name']) ?> · <?= e($bill['area']) ?></p>
                    <h2>Unit <?= e($bill['unit_number']) ?></h2>
                    <p><?= e($bill['unit_type']) ?> · <?= (int)$bill['size_sqm'] ?> sqm · Floor <?= (int)$bill['floor_number'] ?></p>
                    <dl class="bill-summary-details">
                        <dt>Tenant</dt><dd><?= e($tenant['full_name']) ?></dd>
                        <dt>Email</dt><dd><?= e($tenant['email']) ?></dd>
                        <dt>Phone</dt><dd><?= e($tenant['phone']) ?></dd>
                        <dt>Bill type</dt><dd><?= e(ucfirst($bill['bill_type'])) ?></dd>
                        <dt>Description</dt><dd><?= e($bill['description']) ?></dd>
                        <dt>Due date</dt><dd><?= e(date('M j, Y', strtotime($bill['due_date']))) ?></dd>
                    </dl>
                    <div class="summary-total"><span>Amount due</span><strong><?= pesos((float)$bill['amount']) ?></strong><small>Review the amount and due date before submitting.</small></div>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</div>
<script>
const billPaymentForm=document.querySelector('.bill-payment-form');
if(billPaymentForm){const syncBillPaymentFields=()=>{const method=billPaymentForm.querySelector('input[name=payment_method]:checked')?.value||'gcash';billPaymentForm.querySelectorAll('[data-payment-details]').forEach(panel=>{const active=panel.dataset.paymentDetails===method;panel.hidden=!active;panel.querySelectorAll('input').forEach(input=>input.disabled=!active)});};billPaymentForm.querySelectorAll('input[name=payment_method]').forEach(input=>input.addEventListener('change',syncBillPaymentFields));syncBillPaymentFields();}
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
