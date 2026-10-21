<?php
$pageTitle = $pageTitle ?? 'StayLocal';
$currentPage = $currentPage ?? '';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | StayLocal</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/tenant.css">
    <script src="assets/password-toggle.js" defer></script>
</head>

<body>
    <header class="site-header">
        <div class="header-inner">
            <a class="wordmark" href="index.php" aria-label="StayLocal home"><span
                    class="wordmark-name"><span>Stay</span>Local</span></a>
            <nav class="site-nav" aria-label="Primary navigation">
                <a class="<?= $currentPage === 'homes' ? 'active' : '' ?>" href="index.php">Find a home</a>
                <a href="index.php#how-it-works">How it works</a>
                <a class="nav-tenant <?= $currentPage === 'tenant' ? 'active' : '' ?>"
                    href="<?= tenantIsLoggedIn() ? 'tenant-dashboard.php' : 'tenant-login.php' ?>"><?= tenantIsLoggedIn() ? 'My portal' : 'Tenant Login' ?></a>
            </nav>
        </div>
    </header>
    <main>
