<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('citizen');
$pdo = db();
$vid = (int)($_GET['violation_id'] ?? $_POST['violation_id'] ?? 0);

$st = $pdo->prepare('SELECT * FROM v_violation_details WHERE violation_id = ? AND driver_id = ?');
$st->execute([$vid, $u['driver_id']]);
$case = $st->fetch();
if (!$case) { flash('Only the driver named on a violation can appeal it.', 'err'); redirect('citizen/dashboard.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $text = trim($_POST['statement'] ?? '');
    try {
        if (mb_strlen($text) < 20) { throw new RuntimeException('Explain your appeal in at least 20 characters.'); }
        $pdo->prepare('INSERT INTO appeals (violation_id, driver_id, statement) VALUES (?,?,?)')
            ->execute([$vid, $u['driver_id'], $text]);
        flash('Appeal submitted. An administrator will review it.');
        redirect('citizen/dashboard.php');
    } catch (Throwable $t) {
        flash(db_message($t), 'err');
    }
}
$pageTitle = 'Appeal violation';
include __DIR__ . '/../includes/header.php';
?>
<h1>Appeal case <?= e($case['case_id']) ?></h1>
<div class="card">
  <p><b><?= e($case['type_name']) ?></b> on <?= e(date('d M Y, H:i', strtotime($case['violation_datetime']))) ?> at <?= e($case['location']) ?>.</p>
  <p>Fine <?= e(money($case['total_amount'])) ?>, <?= fine_chip($case['fine_status']) ?></p>
</div>
<form method="post" class="form wide" data-validate>
  <?= csrf_field() ?>
  <input type="hidden" name="violation_id" value="<?= (int)$vid ?>">
  <label class="full">Your statement
    <textarea name="statement" rows="6" required minlength="20" placeholder="What happened, and why do you believe this charge is incorrect?"><?= e($_POST['statement'] ?? '') ?></textarea></label>
  <button class="btn" type="submit">Submit appeal</button>
  <a class="btn btn-ghost" href="<?= e(url('citizen/dashboard.php')) ?>">Cancel</a>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
