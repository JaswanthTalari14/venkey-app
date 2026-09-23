<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$patient_id = $_SESSION['user_id'];

if (!$order_id) {
    header("Location: medicines.php");
    exit;
}

$stmt = $conn->prepare("SELECT id, gateway_order_id FROM orders WHERE id = ? AND patient_id = ?");
$stmt->bind_param("ii", $order_id, $patient_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    header("Location: medicines.php");
    exit;
}

$order = $res->fetch_assoc();
$merchantTransactionId = $order['gateway_order_id'];
$merchantId = PHONEPE_MERCHANT_ID;
$saltKey = PHONEPE_SALT_KEY;
$saltIndex = PHONEPE_SALT_INDEX;
$env = PHONEPE_ENV;

$is_paid = false;

if (!empty($merchantId) && !empty($saltKey) && !empty($merchantTransactionId)) {
    $url = ($env === 'UAT')
        ? "https://api-preprod.phonepe.com/apis/pg-sandbox/pg/v1/status/$merchantId/$merchantTransactionId"
        : "https://api.phonepe.com/apis/hermes/pg/v1/status/$merchantId/$merchantTransactionId";

    $xVerify = hash('sha256', "/pg/v1/status/$merchantId/$merchantTransactionId" . $saltKey) . '###' . $saltIndex;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-VERIFY: ' . $xVerify,
        'X-MERCHANT-ID: ' . $merchantId
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    $resData = json_decode($response, true);

    if (isset($resData['code']) && ($resData['code'] === 'PAYMENT_SUCCESS' || $resData['code'] === 'PAYMENT_INITIATED')) {
        $is_paid = true;
    }
} else {
    // If test mode / fallback
    $is_paid = true;
}

if ($is_paid) {
    $conn->query("UPDATE orders SET payment_status = 'Paid', payment_method = 'Online Payment (PhonePe)' WHERE id = $order_id");
    require_once 'includes/referral_functions.php';
    $o_res = $conn->query("SELECT total_amount FROM orders WHERE id = $order_id");
    if ($o_res && $o_row = $o_res->fetch_assoc()) {
        process_referral_order_qualification($order_id, $patient_id, $o_row['total_amount'], true);
    }
    header("Location: medicines.php?success=1");
    exit;
} else {
    $conn->query("UPDATE orders SET payment_status = 'Failed' WHERE id = $order_id");
    header("Location: medicines.php");
    exit;
}
?>
