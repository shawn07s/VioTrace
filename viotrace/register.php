<?php
require_once __DIR__ . '/includes/functions.php';
if (user()) { redirect('dashboard.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $license = strtoupper(trim($_POST['license_no'] ?? ''));
    $vehRaw  = trim($_POST['vehicle_no'] ?? '');
    $vehicle = $vehRaw !== '' ? normalize_reg($vehRaw) : '';
    $phone   = trim($_POST['phone'] ?? '');
    $nid     = trim($_POST['nid'] ?? '');
    $dob     = trim($_POST['date_of_birth'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $pass    = (string)($_POST['password'] ?? '');
    try {
        if ($license === '' && $vehicle === '') {
            throw new RuntimeException('Enter your driving licence number or your vehicle number. At least one is required.');
        }
        if ($license !== '' && !valid_license($license)) {
            throw new RuntimeException('Licence number must be DL followed by 8 digits, for example DL00000001.');
        }
        if ($vehicle !== '' && !valid_reg($vehicle)) {
            throw new RuntimeException('Vehicle number must look like DHAKA METRO-HA-11-2345.');
        }
        if (!valid_phone($phone)) {
            throw new RuntimeException('Enter a Bangladeshi mobile number such as 01711000001. It becomes your username.');
        }
        if (!valid_nid($nid)) {
            throw new RuntimeException('NID must be exactly 10 or 17 digits.');
        }
        $dobDt = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$dobDt) {
            throw new RuntimeException('Enter your date of birth.');
        }
        if ($dobDt->diff(new DateTime('today'))->y < 18) {
            throw new RuntimeException('You must be at least 18 years old to register.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email address or leave it empty.');
        }
        if (strlen($pass) < 8) {
            throw new RuntimeException('Password must be at least 8 characters.');
        }
        if ($pass !== (string)($_POST['confirm'] ?? '')) {
            throw new RuntimeException('The two passwords do not match.');
        }

        $pdo = db();
        $byLicense = null;
        $byVehicle = null;
        if ($license !== '') {
            $st = $pdo->prepare('SELECT driver_id, full_name, date_of_birth FROM drivers WHERE license_no = ?');
            $st->execute([$license]);
            $byLicense = $st->fetch();
            if (!$byLicense) { throw new RuntimeException('No driver record has that licence number.'); }
        }
        if ($vehicle !== '') {
            $st = $pdo->prepare('SELECT d.driver_id, d.full_name, d.date_of_birth FROM vehicles v
                                 JOIN vehicle_ownership o ON o.vehicle_id = v.vehicle_id AND o.owned_to IS NULL
                                 JOIN drivers d ON d.driver_id = o.driver_id WHERE v.reg_no = ?');
            $st->execute([$vehicle]);
            $byVehicle = $st->fetch();
            if (!$byVehicle) { throw new RuntimeException('No current owner is on record for that vehicle number.'); }
        }
        if ($byLicense && $byVehicle && (int)$byLicense['driver_id'] !== (int)$byVehicle['driver_id']) {
            throw new RuntimeException('That licence and that vehicle belong to different people.');
        }
        $driver = $byLicense ?: $byVehicle;
        if ($driver['date_of_birth'] !== $dob) {
            throw new RuntimeException('Your date of birth does not match our records.');
        }
        $checks = [
            ['SELECT 1 FROM users WHERE driver_id = ?', $driver['driver_id'], 'This driver already has an account.'],
            ['SELECT 1 FROM users WHERE username = ?',  $phone,               'An account already exists for that mobile number.'],
            ['SELECT 1 FROM users WHERE nid = ?',       $nid,                 'That NID is already used by another account.'],
        ];
        foreach ($checks as $c) {
            $st = $pdo->prepare($c[0]);
            $st->execute([$c[1]]);
            if ($st->fetch()) { throw new RuntimeException($c[2]); }
        }
        $st = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, email, role, nid, driver_id)
                             VALUES (?,?,?,?, 'citizen', ?, ?)");
        $st->execute([$phone, password_hash($pass, PASSWORD_DEFAULT), $driver['full_name'],
                      $email !== '' ? $email : null, $nid, $driver['driver_id']]);
        flash('Account created. Sign in with your mobile number ' . $phone . '.');
        redirect('login.php');
    } catch (Throwable $t) {
        flash(db_message($t), 'err');
    }
}
$pageTitle = 'Create account';
include __DIR__ . '/includes/header.php';
?>
<section class="narrow wide-narrow">
  <h1>Create a driver account</h1>
  <p>Your driving licence or vehicle must already be on record. Fill in at least one of the first two fields.</p>
  <form method="post" class="form" data-validate data-require-one="license_no,vehicle_no">
    <?= csrf_field() ?>
    <fieldset>
      <legend>Your record</legend>
      <label>Driving licence number
        <input name="license_no" pattern="DL[0-9]{8}" maxlength="10" placeholder="DL00000001" value="<?= e($_POST['license_no'] ?? '') ?>">
        <small>DL followed by 8 digits.</small></label>
      <label>Vehicle number
        <input name="vehicle_no" maxlength="40" placeholder="DHAKA METRO-HA-11-2345" value="<?= e($_POST['vehicle_no'] ?? '') ?>">
        <small>As on your Bangladesh vehicle registration. Use this if you have no licence on record.</small></label>
    </fieldset>
    <fieldset>
      <legend>About you</legend>
      <label>Mobile number (your username)
        <input name="phone" required pattern="01[3-9][0-9]{8}" maxlength="11" inputmode="numeric" placeholder="01711000001" value="<?= e($_POST['phone'] ?? '') ?>">
        <small>One account per mobile number.</small></label>
      <label>Date of birth <input type="date" name="date_of_birth" required data-adult value="<?= e($_POST['date_of_birth'] ?? '') ?>">
        <small>You must be 18 or older.</small></label>
      <label>NID number <input name="nid" required pattern="[0-9]{10}|[0-9]{17}" maxlength="17" inputmode="numeric" value="<?= e($_POST['nid'] ?? '') ?>">
        <small>10 or 17 digits.</small></label>
      <label>Email <small>(optional)</small> <input type="email" name="email" autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
    </fieldset>
    <fieldset>
      <legend>Password</legend>
      <label>Password <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
      <label>Confirm password <input type="password" name="confirm" required minlength="8" autocomplete="new-password"></label>
    </fieldset>
    <button class="btn" type="submit">Create account</button>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
