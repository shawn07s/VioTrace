<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('admin');
$pdo = db();

function must_driver(PDO $pdo, string $license): int {
    $st = $pdo->prepare('SELECT driver_id FROM drivers WHERE license_no = ?');
    $st->execute([$license]);
    $id = $st->fetchColumn();
    if (!$id) { throw new RuntimeException('No driver has licence number ' . $license . '.'); }
    return (int)$id;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    $f = function (string $k): string { return trim((string)($_POST[$k] ?? '')); };
    try {
        $pdo->beginTransaction();
        if ($act === 'add_driver') {
            if (!valid_license($f('license_no'))) { throw new RuntimeException('Licence number must be DL followed by 8 digits, for example DL00000001.'); }
            $dobDt = DateTime::createFromFormat('Y-m-d', $f('dob'));
            if (!$dobDt || $dobDt->diff(new DateTime('today'))->y < 18) { throw new RuntimeException('A driver must be at least 18 years old.'); }
            $pdo->prepare('INSERT INTO drivers (license_no, full_name, phone, address, date_of_birth, license_expiry) VALUES (?,?,?,?,?,?)')
                ->execute([$f('license_no'), $f('full_name'), $f('phone') ?: null, $f('address') ?: null, $f('dob'), $f('expiry')]);
            flash('Driver added.');
        } elseif ($act === 'add_vehicle') {
            $owner = must_driver($pdo, strtoupper($f('owner_license')));
            $reg = normalize_reg($f('reg_no'));
            if (!valid_reg($reg)) { throw new RuntimeException('Vehicle number must look like DHAKA METRO-HA-11-2345.'); }
            $pdo->prepare('INSERT INTO vehicles (reg_no, vehicle_type, make, model, manufacture_year) VALUES (?,?,?,?,?)')
                ->execute([$reg, $f('vehicle_type'), $f('make'), $f('model'), (int)$f('year')]);
            $vid = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO vehicle_ownership (vehicle_id, driver_id, owned_from) VALUES (?,?,CURDATE())')->execute([$vid, $owner]);
            flash('Vehicle registered with its first owner.');
        } elseif ($act === 'transfer') {
            $new = must_driver($pdo, strtoupper($f('new_license')));
            $st = $pdo->prepare('SELECT vehicle_id FROM vehicles WHERE reg_no = ? FOR UPDATE');
            $st->execute([normalize_reg($f('reg_no'))]);
            $vid = $st->fetchColumn();
            if (!$vid) { throw new RuntimeException('No vehicle has that registration number.'); }
            $pdo->prepare('UPDATE vehicle_ownership SET owned_to = CURDATE() WHERE vehicle_id = ? AND owned_to IS NULL')->execute([$vid]);
            $pdo->prepare('INSERT INTO vehicle_ownership (vehicle_id, driver_id, owned_from) VALUES (?,?,CURDATE())')->execute([$vid, $new]);
            flash('Ownership transferred. The previous owner stays in the history.');
        } elseif ($act === 'add_type') {
            $pdo->prepare('INSERT INTO violation_types (code, name, description, base_fine) VALUES (?,?,?,?)')
                ->execute([strtoupper($f('code')), $f('name'), $f('description') ?: null, $f('base_fine')]);
            flash('Violation type added.');
        } elseif ($act === 'update_fine') {
            $pdo->prepare('UPDATE violation_types SET base_fine = ? WHERE type_id = ?')->execute([$f('base_fine'), (int)$f('type_id')]);
            flash('Base fine updated. Existing fines keep the amount they were issued with.');
        } elseif ($act === 'add_officer') {
            if (strlen($f('password')) < 8) { throw new RuntimeException('Password must be at least 8 characters.'); }
            if (!filter_var($f('email'), FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Enter a valid email address.'); }
            // Officer IDs are generated in sequence: DMP04325200001, DMP04325200002, ...
            $last = $pdo->query("SELECT MAX(username) FROM users WHERE role = 'officer' AND username REGEXP '^DMP[0-9]{11}$'")->fetchColumn();
            $newId = $last ? substr((string)$last, 0, 8) . str_pad((string)((int)substr((string)$last, 8) + 1), 6, '0', STR_PAD_LEFT)
                           : 'DMP04325200001';
            $pdo->prepare("INSERT INTO users (username, password_hash, full_name, email, role, created_by) VALUES (?,?,?,?, 'officer', ?)")
                ->execute([$newId, password_hash($f('password'), PASSWORD_DEFAULT), $f('full_name'), $f('email'), $u['user_id']]);
            $uid = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO officers (user_id, badge_no, officer_rank, station) VALUES (?,?,?,?)')
                ->execute([$uid, $f('badge_no'), $f('rank'), $f('station')]);
            flash('Officer account created. Officer ID: ' . $newId . '. Give the officer this ID and the password you set.');
        } else {
            throw new RuntimeException('Unknown action.');
        }
        $pdo->commit();
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        flash(db_message($t), 'err');
    }
    redirect('admin/manage.php');
}

$types = $pdo->query('SELECT * FROM violation_types ORDER BY name')->fetchAll();
$vehicles = $pdo->query("SELECT ve.reg_no, ve.vehicle_type, ve.make, ve.model, d.full_name, d.license_no
  FROM vehicles ve LEFT JOIN vehicle_ownership o ON o.vehicle_id = ve.vehicle_id AND o.owned_to IS NULL
  LEFT JOIN drivers d ON d.driver_id = o.driver_id ORDER BY ve.reg_no")->fetchAll();
$drivers = $pdo->query('SELECT license_no, full_name, license_expiry FROM drivers ORDER BY license_no')->fetchAll();
$pageTitle = 'Manage data';
include __DIR__ . '/../includes/header.php';
?>
<h1>Manage data</h1>
<div class="grid2">
<section>
  <h2>Add a driver</h2>
  <form method="post" class="form" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="add_driver">
    <label>Licence number <input name="license_no" required pattern="DL[0-9]{8}" maxlength="10" placeholder="DL00000001"></label>
    <label>Full name <input name="full_name" required></label>
    <label>Phone <input name="phone"></label>
    <label>Address <input name="address"></label>
    <label>Date of birth <input type="date" name="dob" required></label>
    <label>Licence expiry <input type="date" name="expiry" required></label>
    <button class="btn" type="submit">Add driver</button></form>
</section>
<section>
  <h2>Register a vehicle</h2>
  <form method="post" class="form" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="add_vehicle">
    <label>Registration number <input name="reg_no" required></label>
    <label>Type <select name="vehicle_type"><?php foreach (['Car','Motorcycle','Microbus','Bus','Truck','CNG','Other'] as $t) echo '<option>' . e($t) . '</option>'; ?></select></label>
    <label>Make <input name="make" required></label>
    <label>Model <input name="model" required></label>
    <label>Year <input type="number" name="year" min="1950" max="2100" required></label>
    <label>Owner licence number <input name="owner_license" required pattern="DL[0-9]{8}" maxlength="10" placeholder="DL00000001"></label>
    <button class="btn" type="submit">Register vehicle</button></form>
</section>
<section>
  <h2>Transfer ownership</h2>
  <form method="post" class="form" data-validate data-confirm="Transfer this vehicle to the new owner?"><?= csrf_field() ?><input type="hidden" name="action" value="transfer">
    <label>Registration number <input name="reg_no" required></label>
    <label>New owner licence number <input name="new_license" required pattern="DL[0-9]{8}" maxlength="10" placeholder="DL00000001"></label>
    <button class="btn" type="submit">Transfer</button></form>
</section>
<section>
  <h2>Add an officer</h2>
  <form method="post" class="form" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="add_officer">
    <label>Full name <input name="full_name" required></label>
    <label>Email <input type="email" name="email" required></label>
    <label>Password <input type="password" name="password" required minlength="8"></label>
    <p class="hint">The officer ID (DMP04325200011 and onwards) is generated automatically. Only administrators can create officer IDs.</p>
    <label>Badge number <input name="badge_no" required></label>
    <label>Rank <input name="rank" required></label>
    <label>Station <input name="station" required></label>
    <button class="btn" type="submit">Create officer</button></form>
</section>
<section>
  <h2>Violation types</h2>
  <div class="tablewrap"><table><thead><tr><th>Code</th><th>Name</th><th>Base fine</th></tr></thead><tbody>
  <?php foreach ($types as $t): ?>
    <tr><td><?= e($t['code']) ?></td><td><?= e($t['name']) ?></td>
      <td><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="update_fine">
        <input type="hidden" name="type_id" value="<?= (int)$t['type_id'] ?>">
        <input type="number" step="0.01" min="1" name="base_fine" value="<?= e($t['base_fine']) ?>" style="width:7rem" aria-label="Base fine for <?= e($t['name']) ?>">
        <button class="btn btn-sm btn-ghost" type="submit">Save</button></form></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <h3>Add a type</h3>
  <form method="post" class="form" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="add_type">
    <label>Code <input name="code" required maxlength="10"></label>
    <label>Name <input name="name" required></label>
    <label>Description <input name="description"></label>
    <label>Base fine (BDT) <input type="number" step="0.01" min="1" name="base_fine" required></label>
    <button class="btn" type="submit">Add type</button></form>
</section>
<section>
  <h2>Vehicles and current owners</h2>
  <div class="tablewrap"><table><thead><tr><th>Registration</th><th>Vehicle</th><th>Owner</th></tr></thead><tbody>
  <?php foreach ($vehicles as $v): ?>
    <tr><td><?= e($v['reg_no']) ?></td><td><?= e($v['make'] . ' ' . $v['model']) ?> <small>(<?= e($v['vehicle_type']) ?>)</small></td>
        <td><?= e($v['full_name'] ?? 'No owner') ?> <small><?= e($v['license_no'] ?? '') ?></small></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <h2>Drivers</h2>
  <div class="tablewrap"><table><thead><tr><th>Licence</th><th>Name</th><th>Expires</th></tr></thead><tbody>
  <?php foreach ($drivers as $d): ?>
    <tr><td><?= e($d['license_no']) ?></td><td><?= e($d['full_name']) ?></td><td><?= e($d['license_expiry']) ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
</section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
