<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$tx_id = isset($_GET['tx_id']) ? trim($_GET['tx_id']) : '';

if (!empty($tx_id)) {
    header("Location: digital_receipt.php?type=wallet&id=" . urlencode($tx_id));
    exit;
}

header("Location: my_wallet.php");
exit;
?>
