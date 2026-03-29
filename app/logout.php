<?php
require_once __DIR__ . '/includes/app_config.php';
unset($_SESSION['capp_customer_id'], $_SESSION['capp_customer_email']);
header('Location: login.php');
exit;
