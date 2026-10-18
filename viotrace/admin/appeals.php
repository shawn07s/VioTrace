<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $remarks = trim($_POST['remarks'] ?? '');
        if ($remarks === '') { throw new RuntimeException('Write a short remark explaining the decision.'); }
        // One atomic stored procedure: locks appeal + fine, updates both, commits or rolls back.
        $st = $pdo->prepare('CALL sp_resolve_appeal(?, ?, ?, ?)');
        $st->execute([(int)$_POST['appeal_id'], $u['user_id'], $_POST['outcome'] ?? '', $remarks]);
        $st->closeCursor();
        flash('Appeal ' . strtolower($_POST['outcome']) . '.');
    } catch (Throwable $t) {
        flash(db_message($t), 'err');
    }
    redirect('admin/appeals.php');
}

$rows = $pdo->query("SELECT a.*, v.case_id, d.full_name, d.license_no, v.violation_datetime, v.location, ve.reg_no,
    t.name AS type_name, f.total_amount, f.status AS fine_status
  FROM appeals a
  JOIN violations v ON v.violation_id = a.violation_id
  JOIN drivers d ON d.driver_id = a.driver_id
  JOIN vehicles ve ON ve.vehicle_id = v.vehicle_id
  JOIN violation_types t ON t.type_id = v.type_id
  JOIN fines f ON f.violation_id = v.violation_id
  ORDER BY (a.status = 'Pending') DESC, a.submitted_at DESC")->fetchAll();

$pageTitle = 'Appeals';
include __DIR__ . '/../includes/header.php';
?>
<h1>Appeals</h1>
<?php if (!$rows): ?><p class="empty">No appeals have been submitted.</p><?php endif; ?>
<?php foreach ($rows as $a): ?>
<article class="card">
  <header class="cardhead">
    <b>Case <?= e($a['case_id']) ?>, <?= e($a['type_name']) ?></b> <?= chip($a['status']) ?>
  </header>
  <p><?= e($a['full_name']) ?> (<?= e($a['license_no']) ?>), vehicle <?= e($a['reg_no']) ?>, <?= e($a['location']) ?>,
     <?= e(date('d M Y', strtotime($a['violation_datetime']))) ?>. Fine <?= e(money($a['total_amount'])) ?> <?= fine_chip($a['fine_status']) ?></p>
  <blockquote><?= nl2br(e($a['statement'])) ?></blockquote>
  <?php if ($a['status'] === 'Pending'): ?>
    <form method="post" class="inline-form" data-confirm="Record this decision? It cannot be changed afterwards.">
      <?= csrf_field() ?>
      <input type="hidden" name="appeal_id" value="<?= (int)$a['appeal_id'] ?>">
      <input name="remarks" required placeholder="Reason for the decision" size="40" aria-label="Reason for the decision">
      <button class="btn btn-sm" name="outcome" value="Approved" type="submit">Approve</button>
      <button class="btn btn-sm btn-ghost" name="outcome" value="Rejected" type="submit">Reject</button>
    </form>
  <?php else: ?>
    <p><small>Resolved <?= e($a['resolved_at']) ?>. Remarks: <?= e($a['admin_remarks']) ?></small></p>
  <?php endif; ?>
</article>
<?php endforeach; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
