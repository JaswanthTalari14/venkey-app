<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$security_verified = $_SESSION['security_gate_verified'] ?? false;
session_unset();
session_destroy();

session_start();
if ($security_verified) {
    $_SESSION['security_gate_verified'] = true;
}

header("Location: index.php");
exit;
?>
