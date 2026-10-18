<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('officer');
$pdo = db();

$st = $pdo->prepare('SELECT officer_id FROM officers WHERE user_id = ?');
$st->execute([$u['user_id']]);
$officerId = (int)$st->fetchColumn();
$types = $pdo->query('SELECT type_id, code, name, base_fine FROM violation_types WHERE is_active = 1 ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $reg = normalize_reg((string)($_POST['reg_no'] ?? ''));
        $lic = strtoupper(trim($_POST['license_no'] ?? ''));
        if (!valid_reg($reg)) { throw new RuntimeException('Vehicle number must look like DHAKA METRO-HA-11-2345.'); }
        if ($lic !== '' && !valid_license($lic)) { throw new RuntimeException('Licence number must be DL followed by 8 digits, for example DL00000001.'); }
        $dt  = DateTime::createFromFormat('Y-m-d\TH:i', (string)($_POST['violation_datetime'] ?? ''));
        $loc = trim($_POST['location'] ?? '');
        $area = trim($_POST['area'] ?? '');
        $speed = trim($_POST['speed'] ?? '');
        if (!$dt) { throw new RuntimeException('Enter the date and time of the violation.'); }
        if ($loc === '' || $area === '') { throw new RuntimeException('Location and area are required.'); }
        if ($speed !== '' && (!ctype_digit($speed) || (int)$speed > 400)) {
            throw new RuntimeException('Speed reading must be a whole number of km/h.');
        }

        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT vehicle_id FROM vehicles WHERE reg_no = ? FOR UPDATE');
        $st->execute([$reg]);
        $vehicleId = $st->fetchColumn();
        if (!$vehicleId) { throw new RuntimeException('No vehicle is registered with that number.'); }

        if ($lic !== '') {                       // driver stated by the officer
            $st = $pdo->prepare('SELECT driver_id FROM drivers WHERE license_no = ?');
            $st->execute([$lic]);
        } else {                                 // default: current owner
            $st = $pdo->prepare('SELECT driver_id FROM vehicle_ownership WHERE vehicle_id = ? AND owned_to IS NULL');
            $st->execute([$vehicleId]);
        }
        $driverId = $st->fetchColumn();
        if (!$driverId) { throw new RuntimeException('No driver found. Check the licence number or the vehicle owner record.'); }

        $st = $pdo->prepare('INSERT INTO violations (vehicle_id, driver_id, officer_id, type_id, location, area,
              violation_datetime, evidence_photo_ref, evidence_speed_reading, officer_notes) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $st->execute([$vehicleId, $driverId, $officerId, (int)($_POST['type_id'] ?? 0), $loc, $area,
            $dt->format('Y-m-d H:i:s'), trim($_POST['photo_ref'] ?? '') ?: null,
            $speed !== '' ? (int)$speed : null, trim($_POST['notes'] ?? '') ?: null]);
        $vid = (int)$pdo->lastInsertId();

        // The trigger has already created the fine; read it back for the confirmation.
        $st = $pdo->prepare('SELECT v.case_id, f.total_amount, f.surcharge FROM fines f JOIN violations v ON v.violation_id = f.violation_id WHERE f.violation_id = ?');
        $st->execute([$vid]);
        $fine = $st->fetch();
        $pdo->commit();

        $msg = 'Violation logged. Case ID ' . $fine['case_id'] . '. Fine: ' . money($fine['total_amount']);
        if ((float)$fine['surcharge'] > 0) { $msg .= ' (includes ' . money($fine['surcharge']) . ' repeat-offender surcharge)'; }
        flash($msg . '.');
        redirect('officer/dashboard.php');
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        flash(db_message($t), 'err');
    }
}
$pageTitle = 'Log violation';
include __DIR__ . '/../includes/header.php';
?>
<h1>Log a violation</h1>
<form method="post" class="form wide" data-validate>
  <?= csrf_field() ?>
  <label>Vehicle registration <input name="reg_no" required placeholder="DHAKA METRO-HA-11-2345" value="<?= e($_POST['reg_no'] ?? '') ?>"></label>
  <label>Driver licence <small>(leave empty to use the current owner)</small>
    <input name="license_no" pattern="DL[0-9]{8}" maxlength="10" placeholder="DL00000001" value="<?= e($_POST['license_no'] ?? '') ?>"></label>
  <label>Violation type
    <select name="type_id" required>
      <?php foreach ($types as $t): ?>
        <option value="<?= (int)$t['type_id'] ?>"><?= e($t['name']) ?> (<?= e(money($t['base_fine'])) ?>)</option>
      <?php endforeach; ?>
    </select></label>
  <label>Date and time <input type="datetime-local" name="violation_datetime" required data-max-now value="<?= e($_POST['violation_datetime'] ?? '') ?>"></label>
  <label>Location <input name="location" required maxlength="150" value="<?= e($_POST['location'] ?? '') ?>"></label>
  <label>Area <input name="area" required maxlength="80" placeholder="Mirpur" value="<?= e($_POST['area'] ?? '') ?>"></label>
  <label>Photo reference <input name="photo_ref" maxlength="255" placeholder="IMG-0101.jpg" value="<?= e($_POST['photo_ref'] ?? '') ?>"></label>
  <label>Speed-gun reading (km/h) <input type="number" name="speed" min="0" max="400" value="<?= e($_POST['speed'] ?? '') ?>"></label>
  <label class="full">Officer notes <textarea name="notes" rows="3"><?= e($_POST['notes'] ?? '') ?></textarea></label>
  <p class="hint full">The fine amount and any repeat-offender surcharge are set by the database when you save.</p>
  <button class="btn" type="submit">Save violation</button>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
