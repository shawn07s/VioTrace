<?php
require_once __DIR__ . '/includes/functions.php';
$u = require_role();
sweep_overdue();

$q = trim($_GET['q'] ?? '');
$rows = [];
if ($q !== '') {
    $sql = 'SELECT * FROM v_violation_details WHERE (case_id = ? OR license_no = ? OR reg_no = ?)';
    $params = [strtoupper($q), strtoupper($q), normalize_reg($q)];
    if ($u['role'] === 'citizen') {          // citizens only see their own licence / current vehicles
        [$scope, $sp] = citizen_scope($u);
        $sql .= ' AND ' . $scope;
        $params = array_merge($params, $sp);
    }
    $st = db()->prepare($sql . ' ORDER BY violation_datetime DESC');
    $st->execute($params);
    $rows = $st->fetchAll();
}
$pageTitle = 'Search cases';
include __DIR__ . '/includes/header.php';
?>
<h1>Search cases</h1>
<form method="get" class="inline-form" data-validate>
  <input name="q" value="<?= e($q) ?>" required placeholder="Case ID, licence number or vehicle registration" aria-label="Case ID, licence number or vehicle registration" size="40">
  <button class="btn" type="submit">Search</button>
</form>
<?php if ($u['role'] === 'citizen'): ?>
  <p class="hint">You can search your own cases, licence number and vehicles you currently own.</p>
<?php endif; ?>
<?php if ($q !== ''): ?>
  <h2><?= count($rows) ?> case<?= count($rows) === 1 ? '' : 's' ?> for “<?= e($q) ?>”</h2>
  <?php render_violations($rows); ?>
  <?php if ($rows && $u['role'] !== 'citizen'): ?>
    <p><a href="<?= e(url('history.php?driver_id=' . (int)$rows[0]['driver_id'])) ?>">Lifetime history for <?= e($rows[0]['driver_name']) ?></a>
       or <a href="<?= e(url('history.php?vehicle_id=' . (int)$rows[0]['vehicle_id'])) ?>">for vehicle <?= e($rows[0]['reg_no']) ?></a></p>
  <?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
