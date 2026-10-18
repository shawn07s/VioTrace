<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('citizen');
sweep_overdue();
[$scope, $params] = citizen_scope($u);
$st = db()->prepare("SELECT * FROM v_violation_details WHERE $scope ORDER BY violation_datetime DESC");
$st->execute($params);
$rows = $st->fetchAll();

$owed = 0.0; $open = 0; $waiting = 0;
foreach ($rows as $r) {
    if (in_array($r['fine_status'], ['Unpaid', 'Overdue'], true)) { $owed += (float)$r['total_amount']; $open++; }
    if ($r['fine_status'] === 'Pending') { $waiting++; }
}
$pageTitle = 'My violations';
include __DIR__ . '/../includes/header.php';
?>
<h1>My violations</h1>
<div class="stats">
  <div class="stat"><b><?= count($rows) ?></b><span>Total cases</span></div>
  <div class="stat"><b><?= $open ?></b><span>Awaiting payment</span></div>
  <div class="stat"><b><?= $waiting ?></b><span>Payments pending approval</span></div>
  <div class="stat"><b><?= e(money($owed)) ?></b><span>Amount due</span></div>
</div>
<p><a href="<?= e(url('history.php')) ?>">View my lifetime history</a></p>
<?php render_violations($rows); ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
