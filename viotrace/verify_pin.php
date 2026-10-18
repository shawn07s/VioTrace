<?php
require_once __DIR__ . '/includes/functions.php';
if (user()) { redirect('dashboard.php'); }

$p = $_SESSION['pin_pending'] ?? null;
if (!$p || $p['expires'] < time()) {
    unset($_SESSION['pin_pending']);
    flash('Your sign-in session expired. Please sign in again.', 'err');
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pin = (string)($_POST['pin'] ?? '');
    $st = db()->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'admin' AND is_active = 1 AND approval_status = 'Approved'");
    $st->execute([$p['user_id']]);
    $row = $st->fetch();
    if ($row && preg_match('/^[0-9]{6}$/', $pin) && password_verify($pin, (string)$row['pin_hash'])) {
        start_session_for($row);
        consume_next();                       // admins always land on their dashboard
        redirect('dashboard.php');
    }
    $_SESSION['pin_pending']['tries']++;
    if ($_SESSION['pin_pending']['tries'] >= 5) {
        unset($_SESSION['pin_pending']);
        flash('Too many wrong PIN attempts. Sign in again.', 'err');
        redirect('login.php');
    }
    flash('Wrong PIN. ' . (5 - $_SESSION['pin_pending']['tries']) . ' attempts left.', 'err');
}
$pageTitle = 'Admin PIN';
include __DIR__ . '/includes/header.php';
?>
<section class="narrow">
  <h1>Enter your admin PIN</h1>
  <p>Your password was accepted. Admin accounts also need the 6-digit PIN.</p>
  <form method="post" class="form" data-validate>
    <?= csrf_field() ?>
    <label>PIN
      <input type="password" name="pin" required autofocus inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code">
    </label>
    <button class="btn" type="submit">Verify and sign in</button>
    <a class="btn btn-ghost" href="<?= e(url('login.php')) ?>">Start over</a>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
