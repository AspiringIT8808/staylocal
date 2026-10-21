<?php
require_once __DIR__ . '/config/database.php';
tenantSessionStart();
if (tenantIsLoggedIn()) { header('Location: tenant-dashboard.php'); exit; }
$error = null; $dbError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirmation'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') $error = 'Enter your name, a valid email address, and phone number.';
    elseif (strlen($password) < 8) $error = 'Your password must be at least 8 characters.';
    elseif ($password !== $confirm) $error = 'The passwords do not match.';
    else {
        try {
            $check = db()->prepare('SELECT id FROM tenants WHERE email = :email LIMIT 1'); $check->execute(['email' => $email]);
            if ($check->fetch()) $error = 'An account already exists for that email. Sign in instead.';
            else {
                $insert = db()->prepare('INSERT INTO tenants (full_name, email, phone, password_hash) VALUES (:full_name, :email, :phone, :password_hash)');
                $insert->execute(['full_name' => $name, 'email' => $email, 'phone' => $phone, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
                session_regenerate_id(true); $_SESSION['tenant_id'] = (int)db()->lastInsertId(); $_SESSION['tenant_email'] = $email; $_SESSION['tenant_name'] = $name;
                header('Location: tenant-dashboard.php'); exit;
            }
        } catch (Throwable $exception) { $dbError = 'Import sql/migrate_add_tenants.sql before creating tenant accounts.'; }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Create tenant account · StayLocal</title><link rel="stylesheet" href="assets/style.css"><script src="assets/password-toggle.js" defer></script></head><body class="admin-login-body"><main class="admin-login-shell"><a class="admin-login-brand" href="index.php"><strong>StayLocal</strong><small>tenant portal</small></a><section class="admin-login-card"><p class="eyebrow">TENANT PORTAL</p><h1>Create your account.</h1><p class="admin-login-intro">Save your renter information once, reserve a unit, and keep track of where you live.</p><?php if ($error): ?><div class="form-alert" role="alert"><?= e($error) ?></div><?php endif; ?><?php if ($dbError): ?><div class="database-notice" role="status"><strong>Database required.</strong><span><?= e($dbError) ?></span></div><?php endif; ?><form method="post" class="admin-login-form"><label>Full name<input type="text" name="full_name" required autocomplete="name" value="<?= e($_POST['full_name'] ?? '') ?>"></label><label>Email address<input type="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></label><label>Phone number<input type="tel" name="phone" required autocomplete="tel" value="<?= e($_POST['phone'] ?? '') ?>"></label><label>Password<input type="password" name="password" required minlength="8" autocomplete="new-password"></label><label>Confirm password<input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label><button class="button button-dark full-width" type="submit">Create tenant account →</button></form><div class="admin-login-foot"><a href="tenant-login.php">Already have an account? Sign in</a><a href="index.php">Browse homes</a></div></section></main></body></html>
