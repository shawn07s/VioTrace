<?php
require_once __DIR__ . '/includes/functions.php';
$u = require_role();
$driverId  = (int)($_GET['driver_id'] ?? 0);
$vehicleId = (int)($_GET['vehicle_id'] ?? 0);
$pdo = db();

// Citizens may only open their own driver history.
if ($u['role'] === 'citizen') {
    $driverId = (int)$u['driver_id'];
    $vehicleId = 0;
}
if ($driverId) {
    $col = 'driver_id'; $id = $driverId;
    $st = $pdo->prepare('SELECT full_name, license_no FROM drivers WHERE driver_id = ?');
    $st->execute([$id]);
    $sub = $st->fetch();
    $title = $sub ? 'Driver ' . $sub['full_name'] . ' (' . $sub['license_no'] . ')' : null;
} elseif ($vehicleId) {
    $col = 'vehicle_id'; $id = $vehicleId;
    $st = $pdo->prepare('SELECT reg_no, make, model FROM vehicles WHERE vehicle_id = ?');
    $st->execute([$id]);
    $sub = $st->fetch();
    $title = $sub ? 'Vehicle ' . $sub['reg_no'] . ' (' . $sub['make'] . ' ' . $sub['model'] . ')' : null;
} else {
    $title = null;
}
if (!$title) { flash('Choose a driver or a vehicle first.', 'err'); redirect('search.php'); }

$st = $pdo->prepare("SELECT * FROM v_violation_details WHERE $col = ? ORDER BY violation_datetime ASC");
$st->execute([$id]);
$cases = $st->fetchAll();

$st = $pdo->prepare("SELECT a.*, COALESCE(u.username, 'system') AS actor FROM audit_log a
                     LEFT JOIN users u ON u.user_id = a.acting_user
                     WHERE a.$col = ? ORDER BY a.audit_id ASC");
$st->execute([$id]);
$events = $st->fetchAll();

$pageTitle = 'Lifetime history';
include __DIR__ . '/includes/header.php';
?>
<h1>Lifetime history</h1>
<p class="lead"><?= e($title) ?>. This record is read-only and cannot be edited from the application.</p>
<h2>Cases, oldest first</h2>
<?php render_violations($cases); ?>
<h2>Every recorded change</h2>
<?php if (!$events): ?><p class="empty">No audit events yet.</p><?php else: ?>
<div class="tablewrap"><table><thead><tr><th>Time</th><th>Record</th><th>Action</th><th>Before</th><th>After</th><th>By</th></tr></thead><tbody>
<?php foreach ($events as $ev): ?>
  <tr><td><?= e($ev['created_at']) ?></td><td><?= e($ev['table_name']) ?> #<?= (int)$ev['record_id'] ?></td>
      <td><?= e($ev['action']) ?></td><td><small><?= e($ev['old_value']) ?></small></td>
      <td><small><?= e($ev['new_value']) ?></small></td><td><?= e($ev['actor']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
