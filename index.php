<?php
require_once __DIR__ . '/includes/config.php';
if (!empty($_SESSION['logged_in'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
