<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('citizen');
$pdo = db();
$fineId = (int)($_GET['fine_id'] ?? $_POST['fine_id'] ?? 0);

[$scope, $sp] = citizen_scope($u);
$st = $pdo->prepare("SELECT * FROM v_violation_details WHERE fine_id = ? AND $scope");
$st->execute(array_merge([$fineId], $sp));
$case = $st->fetch();
if (!$case) { flash('That fine was not found on your account.', 'err'); redirect('citizen/dashboard.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $method = $_POST['method'] ?? '';
    $demo   = $_POST['demo'] ?? 'none';
    $ref    = 'VT' . date('ymdHis') . strtoupper(bin2hex(random_bytes(3)));
    try {
        if (!in_array($method, ['bKash', 'Nagad', 'Card', 'Cash'], true)) {
            throw new RuntimeException('Choose a payment method.');
        }
        $pdo->beginTransaction();                                   // START TRANSACTION
        $st = $pdo->prepare('SELECT status, total_amount FROM fines WHERE fine_id = ? FOR UPDATE');   // row lock
        $st->execute([$fineId]);
        $fine = $st->fetch();
        if ($demo === 'lock') { $pdo->query('SELECT SLEEP(15)'); }  // lab demo: hold the lock 15 seconds
        if (!$fine || !in_array($fine['status'], ['Unpaid', 'Overdue'], true)) {
            $label = ($fine['status'] ?? '') === 'Pending' ? 'waiting for payment approval' : strtolower((string)($fine['status'] ?? 'closed'));
            throw new RuntimeException('This fine is already ' . $label . ' and cannot be paid again.');
        }
        // The payment starts as Pending. An administrator approves it later.
        $st = $pdo->prepare("INSERT INTO payments (fine_id, user_id, amount, method, reference_no, status) VALUES (?,?,?,?,?, 'Pending')");
        $st->execute([$fineId, $u['user_id'], $fine['total_amount'], $method, $ref]);
        $pdo->prepare("UPDATE fines SET status = 'Pending' WHERE fine_id = ?")->execute([$fineId]);
        if ($demo === 'fail') { throw new RuntimeException('Simulated gateway failure. The payment was rolled back.'); }
        $pdo->commit();                                             // COMMIT
        flash('Payment submitted. Your reference is ' . $ref . '. It shows as pending until an administrator approves it.');
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }            // ROLLBACK
        try {   // keep a record of the failed attempt (outside the rolled-back transaction)
            $pdo->prepare("INSERT INTO payments (fine_id, user_id, amount, method, reference_no, status, note)
                           VALUES (?,?,?,?,?, 'Failed', ?)")
                ->execute([$fineId, $u['user_id'], $case['total_amount'], in_array($method, ['bKash','Nagad','Card','Cash'], true) ? $method : 'Cash',
                           'F' . $ref, mb_substr(db_message($t), 0, 250)]);
        } catch (Throwable $ignored) { }
        flash(db_message($t), 'err');
    }
    redirect('citizen/dashboard.php');
}
$pageTitle = 'Pay fine';
include __DIR__ . '/../includes/header.php';
?>
<h1>Pay fine for case <span class="mono"><?= e($case['case_id']) ?></span></h1>
<div class="card">
  <p><b><?= e($case['type_name']) ?></b> on <?= e(date('d M Y', strtotime($case['violation_datetime']))) ?>, <?= e($case['location']) ?>.</p>
  <p>Vehicle <?= e($case['reg_no']) ?>. Due <?= e(date('d M Y', strtotime($case['due_date']))) ?>. <?= fine_chip($case['fine_status']) ?></p>
  <p class="total">Amount to pay: <b><?= e(money($case['total_amount'])) ?></b></p>
</div>
<?php if (in_array($case['fine_status'], ['Unpaid', 'Overdue'], true)): ?>
<p class="hint">After you pay, the fine shows as pending until an administrator approves the payment.</p>
<form method="post" class="form" data-validate data-confirm="Submit this payment now?">
  <?= csrf_field() ?>
  <input type="hidden" name="fine_id" value="<?= (int)$fineId ?>">
  <label>Payment method
    <select name="method" required>
      <option value="">Choose a method</option>
      <option>bKash</option><option>Nagad</option><option>Card</option><option>Cash</option>
    </select></label>
  <details class="demo"><summary>Lab demo options</summary>
    <label>Simulate
      <select name="demo">
        <option value="none">Normal payment</option>
        <option value="fail">Fail after the update, to show ROLLBACK</option>
        <option value="lock">Hold the row lock for 15 seconds, to show blocking</option>
      </select></label>
  </details>
  <button class="btn" type="submit">Pay <?= e(money($case['total_amount'])) ?></button>
  <a class="btn btn-ghost" href="<?= e(url('citizen/dashboard.php')) ?>">Cancel</a>
</form>
<?php elseif ($case['fine_status'] === 'Pending'): ?>
  <p class="notice">Your payment was received and is waiting for administrator approval. Nothing more is needed from you.</p>
<?php else: ?>
  <p>This fine is <?= fine_chip($case['fine_status']) ?> and needs no payment.</p>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
