<?php
require_once __DIR__ . '/../config/database.php';
adminSessionStart();

if (adminIsLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = null;
$dbError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter your administrator email and password.';
    } else {
        try {
            $statement = db()->prepare('SELECT * FROM admin_users WHERE email = :email LIMIT 1');
            $statement->execute(['email' => $email]);
            $admin = $statement->fetch();
            if ($admin && password_verify($password, $admin['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_name'] = $admin['full_name'];
                db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $admin['id']]);
                header('Location: index.php');
                exit;
            }
            $error = 'The email or password did not match an administrator account.';
        } catch (Throwable $exception) {
            $dbError = 'Connect MySQL and import sql/staylocal.sql before signing in.';
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin sign in · StayLocal</title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="../assets/password-toggle.js" defer></script>
</head>
<body class="admin-login-body">
<main class="admin-login-shell">
    <a class="admin-login-brand" href="../index.php"><strong>StayLocal</strong><small>property desk</small></a>
    <section class="admin-login-card">
        <p class="eyebrow">PRIVATE WORKSPACE</p>
        <h1>Welcome back.</h1>
        <p class="admin-login-intro">Sign in to manage listings, availability, reservations, payments, and in-person visits.</p>
        <?php if ($error): ?><div class="form-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?php if ($dbError): ?><div class="database-notice" role="status"><strong>Database required.</strong><span><?= e($dbError) ?></span></div><?php endif; ?>
        <form method="post" class="admin-login-form">
            <label>Email address<input type="email" name="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>"></label>
            <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
            <button class="button button-dark full-width" type="submit">Sign in to dashboard →</button>
        </form>
        <div class="admin-login-foot"><a href="../index.php">← Return to public website</a><span>Local property management workspace</span></div>
    </section>
</main>
</body>
</html>
