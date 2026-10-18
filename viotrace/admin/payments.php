<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $decision = $_POST['decision'] ?? '';
        $note = trim($_POST['note'] ?? '');
        if ($decision === 'Rejected' && $note === '') { throw new RuntimeException('Write a short reason when rejecting a payment.'); }
        // Atomic stored procedure: locks the payment and the fine, then approves or rejects both.
        $st = $pdo->prepare('CALL sp_review_payment(?, ?, ?, ?)');
        $st->execute([(int)($_POST['payment_id'] ?? 0), $u['user_id'], $decision, $note !== '' ? $note : 'Verified']);
        $st->closeCursor();
        flash($decision === 'Approved' ? 'Payment approved. The fine is now Paid.' : 'Payment rejected. The fine is payable again.');
    } catch (Throwable $t) {
        flash(db_message($t), 'err');
    }
    redirect('admin/payments.php');
}

$pending = $pdo->query("SELECT p.*, v.case_id, t.name AS type_name, d.full_name, d.license_no, pu.username AS payer
  FROM payments p JOIN fines f ON f.fine_id = p.fine_id JOIN violations v ON v.violation_id = f.violation_id
  JOIN violation_types t ON t.type_id = v.type_id JOIN drivers d ON d.driver_id = v.driver_id
  JOIN users pu ON pu.user_id = p.user_id
  WHERE p.status = 'Pending' ORDER BY p.paid_at")->fetchAll();
$history = $pdo->query("SELECT p.*, v.case_id, ru.username AS reviewer
  FROM payments p JOIN fines f ON f.fine_id = p.fine_id JOIN violations v ON v.violation_id = f.violation_id
  LEFT JOIN users ru ON ru.user_id = p.reviewed_by
  WHERE p.status <> 'Pending' ORDER BY p.payment_id DESC LIMIT 50")->fetchAll();

$pageTitle = 'Payment approvals';
include __DIR__ . '/../includes/header.php';
?>
<h1>Payment approvals</h1>
<p class="lead">Drivers see their fine as “Payment pending” until you approve the payment here.</p>
<h2>Waiting for approval (<?= count($pending) ?>)</h2>
<?php if (!$pending): ?><p class="empty">No payments are waiting.</p><?php else: ?>
<div class="tablewrap"><table><thead><tr><th>Case ID</th><th>Driver</th><th>Offence</th><th>Amount</th><th>Method and reference</th><th>Submitted</th><th>Decision</th></tr></thead><tbody>
<?php foreach ($pending as $p): ?>
  <tr><td class="mono"><?= e($p['case_id']) ?></td>
      <td><?= e($p['full_name']) ?><br><small><?= e($p['license_no']) ?>, paid by <?= e($p['payer']) ?></small></td>
      <td><?= e($p['type_name']) ?></td><td><?= e(money($p['amount'])) ?></td>
      <td><?= e($p['method']) ?><br><small class="mono"><?= e($p['reference_no']) ?></small></td>
      <td><?= e($p['paid_at']) ?></td>
      <td>
        <form method="post" class="inline-form" data-confirm="Record this decision?">
          <?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= (int)$p['payment_id'] ?>">
          <input name="note" placeholder="Note (required to reject)" aria-label="Note" size="22">
          <button class="btn btn-sm" name="decision" value="Approved" type="submit">Approve</button>
          <button class="btn btn-sm btn-ghost" name="decision" value="Rejected" type="submit">Reject</button>
        </form>
      </td></tr>
<?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<h2>Recent decisions</h2>
<?php if (!$history): ?><p class="empty">Nothing here yet.</p><?php else: ?>
<div class="tablewrap"><table><thead><tr><th>Case ID</th><th>Amount</th><th>Reference</th><th>Status</th><th>Reviewed by</th><th>Note</th></tr></thead><tbody>
<?php foreach ($history as $p): ?>
  <tr><td class="mono"><?= e($p['case_id']) ?></td><td><?= e(money($p['amount'])) ?></td><td class="mono"><?= e($p['reference_no']) ?></td>
      <td><?= chip($p['status']) ?></td><td><?= e($p['reviewer'] ?? '') ?><br><small><?= e($p['reviewed_at'] ?? '') ?></small></td>
      <td><small><?= e($p['review_note'] ?? $p['note'] ?? '') ?></small></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
