<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('admin');
sweep_overdue();
$pdo = db();

$threshold = max(2, (int)($_GET['min'] ?? 3));
$totals = $pdo->query("SELECT COUNT(*) AS cases,
    COALESCE(SUM(CASE WHEN status = 'Paid' THEN total_amount END), 0) AS collected,
    COALESCE(SUM(CASE WHEN status IN ('Unpaid','Overdue') THEN total_amount END), 0) AS outstanding,
    COALESCE(SUM(CASE WHEN status = 'Pending' THEN total_amount END), 0) AS awaiting,
    SUM(status = 'Pending') AS awaiting_n,
    SUM(status = 'Overdue') AS overdue
  FROM fines")->fetch();
$pending = (int)$pdo->query("SELECT COUNT(*) FROM appeals WHERE status = 'Pending'")->fetchColumn();

$byType = $pdo->query("SELECT t.name, COUNT(*) AS cases, SUM(f.total_amount) AS fines
  FROM violations v JOIN violation_types t ON t.type_id = v.type_id JOIN fines f ON f.violation_id = v.violation_id
  GROUP BY t.type_id, t.name ORDER BY cases DESC")->fetchAll();
$byArea = $pdo->query("SELECT v.area, COUNT(*) AS cases, SUM(f.total_amount) AS fines
  FROM violations v JOIN fines f ON f.violation_id = v.violation_id
  GROUP BY v.area ORDER BY cases DESC")->fetchAll();
$byMonth = $pdo->query("SELECT DATE_FORMAT(v.violation_datetime, '%Y-%m') AS month, COUNT(*) AS cases, SUM(f.total_amount) AS fines
  FROM violations v JOIN fines f ON f.violation_id = v.violation_id
  GROUP BY month ORDER BY month DESC LIMIT 12")->fetchAll();
$st = $pdo->prepare("SELECT d.license_no, d.full_name, COUNT(*) AS cases, SUM(f.total_amount) AS fines
  FROM violations v JOIN drivers d ON d.driver_id = v.driver_id JOIN fines f ON f.violation_id = v.violation_id
  GROUP BY d.driver_id, d.license_no, d.full_name HAVING COUNT(*) >= ? ORDER BY cases DESC");
$st->execute([$threshold]);
$repeat = $st->fetchAll();

function bars(array $rows, string $label): void {
    if (!$rows) { echo '<p class="empty">No data yet.</p>'; return; }
    $max = max(array_map(function ($r) { return (int)$r['cases']; }, $rows));
    echo '<table class="bars"><tbody>';
    foreach ($rows as $r) {
        $w = $max ? round(((int)$r['cases'] / $max) * 100) : 0;
        echo '<tr><td>' . e($r[$label]) . '</td><td class="barcell"><span style="width:' . (int)$w . '%"></span></td>'
           . '<td>' . (int)$r['cases'] . '</td><td>' . e(money($r['fines'])) . '</td></tr>';
    }
    echo '</tbody></table>';
}
$pageTitle = 'Analytics';
include __DIR__ . '/../includes/header.php';
?>
<h1>Analytics</h1>
<div class="stats">
  <div class="stat"><b><?= (int)$totals['cases'] ?></b><span>Total cases</span></div>
  <div class="stat"><b><?= e(money($totals['collected'])) ?></b><span>Collected (approved)</span></div>
  <div class="stat"><b><?= e(money($totals['outstanding'])) ?></b><span>Outstanding</span></div>
  <div class="stat"><b><a href="<?= e(url('admin/payments.php')) ?>"><?= (int)$totals['awaiting_n'] ?></a></b><span>Payments to approve (<?= e(money($totals['awaiting'])) ?>)</span></div>
  <div class="stat"><b><?= (int)$totals['overdue'] ?></b><span>Overdue fines</span></div>
  <div class="stat"><b><a href="<?= e(url('admin/appeals.php')) ?>"><?= $pending ?></a></b><span>Appeals pending</span></div>
</div>
<div class="grid2">
  <section><h2>By violation type</h2><?php bars($byType, 'name'); ?></section>
  <section><h2>By area</h2><?php bars($byArea, 'area'); ?></section>
  <section><h2>By month</h2><?php bars($byMonth, 'month'); ?></section>
  <section>
    <h2>Repeat offenders</h2>
    <form method="get" class="inline-form">
      <label>At least <input type="number" name="min" min="2" value="<?= (int)$threshold ?>" style="width:5rem"> violations</label>
      <button class="btn btn-sm" type="submit">Apply</button>
    </form>
    <?php if (!$repeat): ?><p class="empty">No drivers at this threshold.</p><?php else: ?>
    <div class="tablewrap"><table><thead><tr><th>Driver</th><th>Cases</th><th>Total fines</th><th></th></tr></thead><tbody>
    <?php foreach ($repeat as $r): ?>
      <tr><td><?= e($r['full_name']) ?><br><small><?= e($r['license_no']) ?></small></td><td><?= (int)$r['cases'] ?></td>
          <td><?= e(money($r['fines'])) ?></td>
          <td><a href="<?= e(url('search.php?q=' . urlencode($r['license_no']))) ?>">View</a></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
