<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('admin');
$pdo = db();
$isMain = is_main_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    try {
        if ($act === 'create') {
            $name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $pass = (string)($_POST['password'] ?? '');
            $pin = (string)($_POST['pin'] ?? '');
            if ($name === '') { throw new RuntimeException('Enter the staff member\'s full name.'); }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Enter a valid email address.'); }
            if (strlen($pass) < 8) { throw new RuntimeException('Password must be at least 8 characters.'); }
            if (!preg_match('/^[0-9]{6}$/', $pin)) { throw new RuntimeException('The PIN must be exactly 6 digits.'); }
            // Admin IDs continue the sequence: admin6, admin7, ...
            $n = (int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(username, 6) AS UNSIGNED)), 0)
                                   FROM users WHERE role = 'admin' AND username REGEXP '^admin[0-9]+$'")->fetchColumn();
            $newName = 'admin' . ($n + 1);
            $status = $isMain ? 'Approved' : 'Pending';   // the database also forces Pending for non-main admins
            $pdo->prepare("INSERT INTO users (username, password_hash, pin_hash, full_name, email, role, admin_type, approval_status, created_by, approved_by)
                           VALUES (?,?,?,?,?, 'admin', 'staff', ?, ?, ?)")
                ->execute([$newName, password_hash($pass, PASSWORD_DEFAULT), password_hash($pin, PASSWORD_DEFAULT),
                           $name, $email, $status, $u['user_id'], $isMain ? $u['user_id'] : null]);
            flash($isMain ? "Admin ID $newName created and active."
                          : "Admin ID $newName created. It stays inactive until adminDC approves it.");
        } elseif ($act === 'approve' || $act === 'reject') {
            if (!$isMain) { throw new RuntimeException('Only adminDC can approve or reject admin IDs.'); }
            $st = $pdo->prepare("UPDATE users SET approval_status = ?, approved_by = ?
                                 WHERE user_id = ? AND role = 'admin' AND approval_status = 'Pending'");
            $st->execute([$act === 'approve' ? 'Approved' : 'Rejected', $u['user_id'], (int)($_POST['user_id'] ?? 0)]);
            if ($st->rowCount() === 0) { throw new RuntimeException('That admin ID is not waiting for approval.'); }
            flash($act === 'approve' ? 'Admin ID approved. It can sign in now.' : 'Admin ID rejected.');
        } else {
            throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $t) {
        flash(db_message($t), 'err');
    }
    redirect('admin/admins.php');
}

$rows = $pdo->query("SELECT u.*, c.username AS creator, a.username AS approver
  FROM users u LEFT JOIN users c ON c.user_id = u.created_by LEFT JOIN users a ON a.user_id = u.approved_by
  WHERE u.role = 'admin' ORDER BY u.user_id")->fetchAll();
$pageTitle = 'Admin IDs';
include __DIR__ . '/../includes/header.php';
?>
<h1>Admin IDs</h1>
<p class="lead">Any administrator can create an admin ID for a new staff member. A new ID stays inactive until the main admin, adminDC, approves it.</p>
<div class="tablewrap"><table><thead><tr><th>Admin ID</th><th>Name</th><th>Type</th><th>Status</th><th>Created by</th><th>Approved by</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr><td class="mono"><?= e($r['username']) ?></td><td><?= e($r['full_name']) ?><br><small><?= e($r['email']) ?></small></td>
      <td><?= $r['admin_type'] === 'main' ? 'Main admin' : 'Staff' ?></td>
      <td><?= chip($r['approval_status'], $r['approval_status'] === 'Approved' ? 'Active' : null) ?></td>
      <td><?= e($r['creator'] ?? '') ?></td><td><?= e($r['approver'] ?? '') ?></td>
      <td class="actions">
        <?php if ($isMain && $r['approval_status'] === 'Pending'): ?>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$r['user_id'] ?>">
            <button class="btn btn-sm" name="action" value="approve" type="submit">Approve</button>
            <button class="btn btn-sm btn-ghost" name="action" value="reject" type="submit">Reject</button></form>
        <?php endif; ?>
      </td></tr>
<?php endforeach; ?></tbody></table></div>

<h2>Create an admin ID</h2>
<form method="post" class="form" data-validate>
  <?= csrf_field() ?><input type="hidden" name="action" value="create">
  <p class="hint">The ID is assigned automatically in sequence (admin6, admin7, and so on).</p>
  <label>Full name <input name="full_name" required></label>
  <label>Email <input type="email" name="email" required></label>
  <label>Password <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
  <label>6-digit PIN <input type="password" name="pin" required pattern="[0-9]{6}" maxlength="6" inputmode="numeric" autocomplete="off"></label>
  <button class="btn" type="submit"><?= $isMain ? 'Create admin ID' : 'Submit for adminDC approval' ?></button>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
