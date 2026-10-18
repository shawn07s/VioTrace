<?php
require_once __DIR__ . '/includes/functions.php';

$q = strtoupper(trim($_GET['case_id'] ?? ''));
$case = null;
if ($q !== '') {
    $st = db()->prepare('SELECT case_id, fine_id, type_name, violation_datetime, area, reg_no, amount, surcharge,
                                total_amount, fine_status, due_date, paid_at, appeal_status
                         FROM v_violation_details WHERE case_id = ?');
    $st->execute([$q]);
    $case = $st->fetch() ?: null;
}
$u = user();
$pageTitle = 'DMP Traffic Management System';
include __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="hero-head">
    <span class="hero-logo"><img src="<?= e(url('assets/img/logo.png')) ?>" alt="VioTrace DMP Traffic Management System logo" width="110" height="110"></span>
    <div>
      <h1>VioTrace: DMP Traffic Management System</h1>
      <p>Look up a traffic case with its Case ID, see the fine, and pay it online after signing in.
         Every payment is checked and approved by an administrator.</p>
    </div>
  </div>
  <form method="get" action="<?= e(url('')) ?>#result" class="case-search" data-validate>
    <label for="case_id">Case ID</label>
    <div class="case-row">
      <input id="case_id" name="case_id" value="<?= e($q) ?>" required placeholder="VT-5E1A0001" maxlength="12" autocomplete="off">
      <button class="btn btn-accent" type="submit">Look up fine</button>
    </div>
    <small>The Case ID is printed on the notice or shown on your driver dashboard.</small>
  </form>
</section>

<?php if ($q !== ''): ?>
<section id="result" class="result">
  <?php if (!$case): ?>
    <div class="flash flash-err">No case was found for “<?= e($q) ?>”. Check the Case ID and try again.</div>
  <?php else: ?>
    <div class="card">
      <div class="cardhead">
        <h2>Case <span class="mono"><?= e($case['case_id']) ?></span></h2>
        <?= fine_chip($case['fine_status']) ?>
      </div>
      <dl class="facts">
        <div><dt>Offence</dt><dd><?= e($case['type_name']) ?></dd></div>
        <div><dt>Date</dt><dd><?= e(date('d M Y, H:i', strtotime($case['violation_datetime']))) ?></dd></div>
        <div><dt>Area</dt><dd><?= e($case['area']) ?></dd></div>
        <div><dt>Vehicle</dt><dd class="mono"><?= e(mask_reg($case['reg_no'])) ?></dd></div>
        <div><dt>Fine</dt><dd><?= e(money($case['amount'])) ?></dd></div>
        <div><dt>Repeat surcharge</dt><dd><?= e(money($case['surcharge'])) ?></dd></div>
        <div><dt>Total to pay</dt><dd><b><?= e(money($case['total_amount'])) ?></b></dd></div>
        <div><dt>Due date</dt><dd><?= e(date('d M Y', strtotime($case['due_date']))) ?></dd></div>
      </dl>
      <?php $s = $case['fine_status']; ?>
      <?php if (in_array($s, ['Unpaid', 'Overdue'], true)): ?>
        <?php if ($u && $u['role'] === 'citizen'): ?>
          <p><a class="btn" href="<?= e(url('citizen/pay.php?fine_id=' . (int)$case['fine_id'])) ?>">Pay this fine</a></p>
        <?php elseif (!$u): ?>
          <p>To pay this fine, sign in with your driver account. You will return to the payment page afterwards.</p>
          <p><a class="btn" href="<?= e(url('citizen/pay.php?fine_id=' . (int)$case['fine_id'])) ?>">Sign in to pay</a>
             <a class="btn btn-ghost" href="<?= e(url('register.php')) ?>">Create a driver account</a></p>
        <?php endif; ?>
      <?php elseif ($s === 'Pending'): ?>
        <p class="notice">A payment has been received and is waiting for administrator approval. The fine will show as Paid once it is approved.</p>
      <?php elseif ($s === 'Paid'): ?>
        <p class="notice notice-ok">Paid and approved on <?= e(date('d M Y', strtotime((string)$case['paid_at']))) ?>.</p>
      <?php else: ?>
        <p class="notice">This fine is <?= e(strtolower($s)) ?>. No payment is needed.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="steps">
  <article><h3>1. Find your case</h3><p>Enter the Case ID to see the offence, the fine and its payment status.</p></article>
  <article><h3>2. Sign in and pay</h3><p>Sign in with the mobile number you registered with, then choose a payment method.</p></article>
  <article><h3>3. Admin approval</h3><p>Your payment shows as pending until an administrator approves it. Disagree with a charge? Submit an appeal.</p></article>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
