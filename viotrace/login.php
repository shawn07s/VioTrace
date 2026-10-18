<?php
require_once __DIR__ . '/includes/functions.php';
if (user()) { redirect('dashboard.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = db()->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1');
    $st->execute([trim($_POST['username'] ?? '')]);
    $row = $st->fetch();
    if (!$row || !password_verify((string)($_POST['password'] ?? ''), $row['password_hash'])) {
        flash('Wrong username or password.', 'err');
    } elseif ($row['approval_status'] === 'Pending') {
        flash('This admin ID is waiting for approval from adminDC. You can sign in once it is approved.', 'err');
    } elseif ($row['approval_status'] === 'Rejected') {
        flash('This admin ID was rejected by adminDC and cannot sign in.', 'err');
    } elseif ($row['role'] === 'admin') {
        // Admins need a second factor: the 6-digit PIN
        session_regenerate_id(true);
        $_SESSION['pin_pending'] = ['user_id' => (int)$row['user_id'], 'expires' => time() + 300, 'tries' => 0];
        redirect('verify_pin.php');
    } else {
        start_session_for($row);
        $next = consume_next();
        if ($next !== null) { header('Location: ' . $next); exit; }
        redirect('dashboard.php');
    }
}
$pageTitle = 'Sign in';
include __DIR__ . '/includes/header.php';
?>
<section class="narrow">
  <h1>Sign in</h1>
  <form method="post" class="form" data-validate>
    <?= csrf_field() ?>
    <label>Username
      <input name="username" required autofocus autocomplete="username">
      <small>Drivers use their mobile number. Officers use their DMP ID. Admins use their admin ID.</small></label>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn" type="submit">Continue</button>
  </form>
  <p>New driver? <a href="<?= e(url('register.php')) ?>">Create an account</a>.</p>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
