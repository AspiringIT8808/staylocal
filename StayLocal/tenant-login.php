<?php
require_once __DIR__ . '/config/database.php';
tenantSessionStart();
if (tenantIsLoggedIn()) { header('Location: tenant-dashboard.php'); exit; }
$error = null; $dbError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? ''))); $password = (string)($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') $error = 'Enter your email and password.';
    else try {
        $statement = db()->prepare('SELECT * FROM tenants WHERE email = :email LIMIT 1'); $statement->execute(['email' => $email]); $tenant = $statement->fetch();
        if ($tenant && password_verify($password, $tenant['password_hash'])) {
            session_regenerate_id(true); $_SESSION['tenant_id'] = (int)$tenant['id']; $_SESSION['tenant_email'] = $tenant['email']; $_SESSION['tenant_name'] = $tenant['full_name'];
            db()->prepare('UPDATE tenants SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $tenant['id']]); header('Location: tenant-dashboard.php'); exit;
        }
        $error = 'The email or password did not match a tenant account.';
    } catch (Throwable $exception) { $dbError = 'Import sql/migrate_add_tenants.sql before signing in.'; }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Tenant sign in · StayLocal</title><link rel="stylesheet" href="assets/style.css"><script src="assets/password-toggle.js" defer></script></head><body class="admin-login-body"><main class="admin-login-shell"><a class="admin-login-brand" href="index.php"><strong>StayLocal</strong><small>tenant portal</small></a><section class="admin-login-card"><p class="eyebrow">TENANT PORTAL</p><h1>Welcome home.</h1><p class="admin-login-intro">Sign in to see your account details and the unit connected to your reservation.</p><?php if ($error): ?><div class="form-alert" role="alert"><?= e($error) ?></div><?php endif; ?><?php if ($dbError): ?><div class="database-notice" role="status"><strong>Database required.</strong><span><?= e($dbError) ?></span></div><?php endif; ?><form method="post" class="admin-login-form"><label>Email address<input type="email" name="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button class="button button-dark full-width" type="submit">Sign in →</button></form><div class="admin-login-foot"><a href="tenant-signup.php">New tenant? Create an account</a><a href="index.php">Browse homes</a></div></section></main></body></html>
