<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }
function redirect(string $path): void { header('Location: ' . url($path)); exit; }
function money($n): string { return 'BDT ' . number_format((float)$n, 2); }
function user(): ?array { return $_SESSION['user'] ?? null; }
function is_main_admin(): bool { $u = user(); return $u && $u['role'] === 'admin' && ($u['admin_type'] ?? null) === 'main'; }

/* ---------- input formats ---------- */
function valid_license(string $s): bool { return (bool)preg_match('/^DL[0-9]{8}$/', $s); }
function valid_phone(string $s): bool { return (bool)preg_match('/^01[3-9][0-9]{8}$/', $s); }
function valid_nid(string $s): bool { return (bool)preg_match('/^([0-9]{10}|[0-9]{17})$/', $s); }
function valid_reg(string $s): bool { return (bool)preg_match('/^[A-Z][A-Z ]+-[A-Z]{1,3}-[0-9]{2}-[0-9]{4}$/', $s); }

/** "Dhaka Metro Ha 11 2345" -> "DHAKA METRO-HA-11-2345" (the form stored in the database). */
function normalize_reg(string $raw): string
{
    $s = strtoupper(trim((string)preg_replace('/\s+/', ' ', $raw)));
    if (preg_match('/^(.+?)[\s-]+([A-Z]{1,3})[\s-]+([0-9]{2})[\s-]+([0-9]{4})$/', $s, $m)) {
        return trim((string)preg_replace('/\s+/', ' ', $m[1])) . '-' . $m[2] . '-' . $m[3] . '-' . $m[4];
    }
    return $s;
}
/** Public pages show only the last 4 characters of a registration number. */
function mask_reg(string $reg): string { return str_repeat('*', max(0, strlen($reg) - 4)) . substr($reg, -4); }

/* ---------- messages, CSRF, access control ---------- */
/** flash('text','ok|err') queues a message; flash() returns and clears the queue. */
function flash(?string $msg = null, string $type = 'ok'): array
{
    if ($msg !== null) {
        $_SESSION['flash'][] = ['m' => $msg, 't' => $type];
        return [];
    }
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}

function csrf_field(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('This form expired. Go back, reload the page and try again.');
    }
}

/** Gate a page by role. Guests are sent to sign in and brought back afterwards. */
function require_role(string ...$roles): array
{
    $u = user();
    if (!$u) {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            $_SESSION['next'] = $_SERVER['REQUEST_URI'] ?? '';
        }
        redirect('login.php');
    }
    if ($roles && !in_array($u['role'], $roles, true)) {
        http_response_code(403);
        exit('Your account role cannot open this page.');
    }
    return $u;
}

/** The page a guest tried to open before signing in, if it is safely inside this app. */
function consume_next(): ?string
{
    $n = (string)($_SESSION['next'] ?? '');
    unset($_SESSION['next']);
    if ($n !== '' && strpos($n, BASE_URL . '/') === 0 && strpos($n, '//') === false
        && strpos($n, '..') === false && !preg_match('/[\r\n]/', $n)) {
        return $n;
    }
    return null;
}

/** Complete a sign-in (after password, and after PIN for admins). */
function start_session_for(array $row): void
{
    session_regenerate_id(true);
    unset($_SESSION['pin_pending']);
    $_SESSION['user'] = [
        'user_id'    => (int)$row['user_id'],
        'username'   => $row['username'],
        'full_name'  => $row['full_name'],
        'role'       => $row['role'],
        'admin_type' => $row['admin_type'],
        'driver_id'  => $row['driver_id'] !== null ? (int)$row['driver_id'] : null,
    ];
}

/** Turns a database exception into a message that is safe to show. */
function db_message(Throwable $t): string
{
    if ($t instanceof PDOException && isset($t->errorInfo[1])) {
        if ((int)$t->errorInfo[1] === 1644) {      // our SIGNAL ... MESSAGE_TEXT
            return (string)$t->errorInfo[2];
        }
        if ((int)$t->errorInfo[1] === 1062) {
            return 'That value already exists in the system.';
        }
        if ((int)$t->errorInfo[1] === 3819 || (int)$t->errorInfo[1] === 4025) {
            return 'A value is not in the required format (database rule).';
        }
    }
    if ($t instanceof RuntimeException) {
        return $t->getMessage();
    }
    error_log('VioTrace: ' . $t->getMessage());
    return 'Something went wrong on our side. Nothing was saved.';
}

/* ---------- display helpers ---------- */
function chip(string $status, ?string $label = null): string
{
    return '<span class="chip chip-' . e(strtolower($status)) . '">' . e($label ?? $status) . '</span>';
}
/** Fine status chip: "Pending" means the payment is waiting for admin approval. */
function fine_chip(string $status): string
{
    return chip($status, $status === 'Pending' ? 'Payment pending' : null);
}

/** Run the overdue sweep (the scheduled event does this daily; this is the fallback). */
function sweep_overdue(): void
{
    $pdo = db();
    $pdo->exec('SET @current_user_id = NULL');      // audit rows show "system"
    $pdo->exec('CALL sp_mark_overdue()');
    $u = user();
    if ($u) { $pdo->exec('SET @current_user_id = ' . (int)$u['user_id']); }
}

/** SQL fragment + params limiting v_violation_details to what a citizen may see. */
function citizen_scope(array $u): array
{
    return [
        '(driver_id = ? OR vehicle_id IN (SELECT vehicle_id FROM vehicle_ownership WHERE driver_id = ? AND owned_to IS NULL))',
        [$u['driver_id'], $u['driver_id']],
    ];
}

/** Shared violations table. Citizens also get Pay / Appeal actions. */
function render_violations(array $rows): void
{
    $u = user();
    if (!$rows) {
        echo '<p class="empty">No violations found.</p>';
        return;
    }
    echo '<div class="tablewrap"><table><thead><tr><th>Case ID</th><th>When and where</th><th>Vehicle and driver</th>'
       . '<th>Offence</th><th>Fine</th><th>Due</th><th>Status</th><th>Appeal</th><th></th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td class="mono">' . e($r['case_id']) . '</td>';
        echo '<td>' . e(date('d M Y, H:i', strtotime($r['violation_datetime']))) . '<br><small>'
           . e($r['location']) . ', ' . e($r['area']) . '</small></td>';
        echo '<td>' . e($r['reg_no']) . '<br><small>' . e($r['driver_name']) . ', ' . e($r['license_no']) . '</small></td>';
        echo '<td>' . e($r['type_name']) . '<details><summary>Evidence</summary><small>';
        echo 'Officer: ' . e($r['officer_name']) . ' (' . e($r['badge_no']) . ')<br>';
        if ($r['evidence_photo_ref']) { echo 'Photo: ' . e($r['evidence_photo_ref']) . '<br>'; }
        if ($r['evidence_speed_reading'] !== null) { echo 'Speed reading: ' . (int)$r['evidence_speed_reading'] . ' km/h<br>'; }
        echo 'Notes: ' . e($r['officer_notes'] ?: 'None') . '</small></details></td>';
        echo '<td>' . e(money($r['total_amount']));
        if ((float)$r['surcharge'] > 0) { echo '<br><small>includes ' . e(money($r['surcharge'])) . ' repeat surcharge</small>'; }
        echo '</td><td>' . e(date('d M Y', strtotime($r['due_date']))) . '</td>';
        echo '<td>' . fine_chip($r['fine_status']) . '</td>';
        echo '<td>' . ($r['appeal_status'] ? chip($r['appeal_status']) : '<small>None</small>') . '</td><td class="actions">';
        if ($u && $u['role'] === 'citizen') {
            if (in_array($r['fine_status'], ['Unpaid', 'Overdue'], true)) {
                echo '<a class="btn btn-sm" href="' . e(url('citizen/pay.php?fine_id=' . (int)$r['fine_id'])) . '">Pay fine</a> ';
            }
            if (!$r['appeal_status'] && (int)$r['driver_id'] === (int)$u['driver_id']
                && !in_array($r['fine_status'], ['Waived', 'Refunded'], true)) {
                echo '<a class="btn btn-sm btn-ghost" href="' . e(url('citizen/appeal.php?violation_id=' . (int)$r['violation_id'])) . '">Appeal</a>';
            }
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
}
