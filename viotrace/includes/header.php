<?php
$u = user();
$pageTitle = $pageTitle ?? 'VioTrace';
$nav = [];
if ($u) {
    $nav[] = ['dashboard.php', 'Dashboard'];
    if ($u['role'] === 'officer') { $nav[] = ['officer/add_violation.php', 'Log violation']; }
    if ($u['role'] === 'admin') {
        $nav[] = ['admin/payments.php', 'Payments'];
        $nav[] = ['admin/appeals.php', 'Appeals'];
        $nav[] = ['admin/admins.php', 'Admins'];
        $nav[] = ['admin/audit.php', 'Audit trail'];
        $nav[] = ['admin/manage.php', 'Manage data'];
    }
    $nav[] = ['search.php', 'Search cases'];
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | VioTrace</title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<header class="top">
  <div class="top-in">
    <a class="brand" href="<?= e(url('')) ?>">
      <span class="logo-tile"><img src="<?= e(url('assets/img/logo.png')) ?>" alt="VioTrace DMP Traffic Management System logo" width="48" height="48"></span>
      <span class="brand-text"><b>VioTrace</b><small>DMP Traffic Management System</small></span>
    </a>
    <nav aria-label="Main">
      <?php foreach ($nav as $n): ?>
        <a href="<?= e(url($n[0])) ?>"><?= e($n[1]) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="who">
      <?php if ($u): ?>
        <span class="who-name"><?= e($u['full_name']) ?> <small>(<?= e($u['role']) ?>)</small></span>
        <a class="btn btn-sm btn-light" href="<?= e(url('logout.php')) ?>">Sign out</a>
      <?php else: ?>
        <a class="btn btn-sm btn-light" href="<?= e(url('login.php')) ?>">Sign in</a>
        <a class="btn btn-sm btn-outline-light" href="<?= e(url('register.php')) ?>">Create account</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main class="wrap">
<?php foreach (flash() as $f): ?>
  <div class="flash flash-<?= e($f['t']) ?>" role="status"><?= e($f['m']) ?></div>
<?php endforeach; ?>
