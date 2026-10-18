<?php
require_once __DIR__ . '/includes/functions.php';
$u = require_role();
switch ($u['role']) {
    case 'admin':   redirect('admin/dashboard.php');
    case 'officer': redirect('officer/dashboard.php');
    default:        redirect('citizen/dashboard.php');
}
