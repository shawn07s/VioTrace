<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('officer');
$pdo = db();
$st = $pdo->prepare('SELECT * FROM officers WHERE user_id = ?');
$st->execute([$u['user_id']]);
$off = $st->fetch();

$st = $pdo->prepare('SELECT * FROM v_violation_details WHERE officer_id = ? ORDER BY violation_id DESC LIMIT 50');
$st->execute([$off['officer_id']]);
$rows = $st->fetchAll();

$pageTitle = 'Officer dashboard';
include __DIR__ . '/../includes/header.php';
?>
<h1>Officer dashboard</h1>
<p class="lead"><?= e($off['officer_rank']) ?>, badge <?= e($off['badge_no']) ?>, <?= e($off['station']) ?>.</p>
<p><a class="btn" href="<?= e(url('officer/add_violation.php')) ?>">Log a violation</a></p>
<h2>Your latest 50 cases</h2>
<?php render_violations($rows); ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
