<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('admin');
$table = $_GET['table'] ?? '';
$allowed = ['violations', 'fines', 'payments', 'appeals', 'vehicle_ownership', 'users'];
$sql = "SELECT a.*, COALESCE(u.username, 'system') AS actor FROM audit_log a LEFT JOIN users u ON u.user_id = a.acting_user";
$params = [];
if (in_array($table, $allowed, true)) { $sql .= ' WHERE a.table_name = ?'; $params[] = $table; }
$st = db()->prepare($sql . ' ORDER BY a.audit_id DESC LIMIT 300');
$st->execute($params);
$rows = $st->fetchAll();
$pageTitle = 'Audit trail';
include __DIR__ . '/../includes/header.php';
?>
<h1>Audit trail</h1>
<p class="lead">Written by database triggers. Rows cannot be edited or deleted, even by the application.</p>
<form method="get" class="inline-form">
  <label>Table
    <select name="table" onchange="this.form.submit()">
      <option value="">All tables</option>
      <?php foreach ($allowed as $t): ?><option <?= $t === $table ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
    </select></label>
  <noscript><button class="btn btn-sm" type="submit">Filter</button></noscript>
</form>
<div class="tablewrap"><table><thead><tr><th>#</th><th>Time</th><th>Record</th><th>Action</th><th>Before</th><th>After</th><th>By</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr><td><?= (int)$r['audit_id'] ?></td><td><?= e($r['created_at']) ?></td>
      <td><?= e($r['table_name']) ?> #<?= (int)$r['record_id'] ?></td><td><?= e($r['action']) ?></td>
      <td><small><?= e($r['old_value']) ?></small></td><td><small><?= e($r['new_value']) ?></small></td><td><?= e($r['actor']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
