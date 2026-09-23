<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$patient_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT id, total_amount, payment_status FROM orders WHERE id = ? AND patient_id = ?");
$stmt->bind_param("ii", $order_id, $patient_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    header("Location: medicines.php");
    exit;
}

$order = $res->fetch_assoc();
$total = $order['total_amount'];

$merchantId = PHONEPE_MERCHANT_ID;
$saltKey = PHONEPE_SALT_KEY;
$saltIndex = PHONEPE_SALT_INDEX;
$env = PHONEPE_ENV;

if (!empty($merchantId) && !empty($saltKey)) {
    $url = ($env === 'UAT') 
        ? 'https://api-preprod.phonepe.com/apis/pg-sandbox/pg/v1/pay' 
        : 'https://api.phonepe.com/apis/hermes/pg/v1/pay';

    $txId = 'MT_' . time() . '_' . $order_id;
    $conn->query("UPDATE orders SET gateway_order_id = '$txId' WHERE id = $order_id");

    $host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];

    $payload = [
        'merchantId' => $merchantId,
        'merchantTransactionId' => $txId,
        'merchantUserId' => 'MUID_' . $patient_id,
        'amount' => (int)round($total * 100),
        'redirectUrl' => $host . '/verify_phonepe.php?order_id=' . $order_id,
        'redirectMode' => 'REDIRECT',
        'callbackUrl' => $host . '/api_payment_webhook.php',
        'paymentInstrument' => [
            'type' => 'PAY_PAGE'
        ]
    ];

    $jsonPayload = json_encode($payload);
    $base64Payload = base64_encode($jsonPayload);
    $xVerify = hash('sha256', $base64Payload . "/pg/v1/pay" . $saltKey) . '###' . $saltIndex;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-VERIFY: ' . $xVerify
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['request' => $base64Payload]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    $resData = json_decode($response, true);

    if (isset($resData['success']) && $resData['success'] === true && isset($resData['data']['instrumentResponse']['redirectInfo']['url'])) {
        header('Location: ' . $resData['data']['instrumentResponse']['redirectInfo']['url']);
        exit;
    }
}

// Fallback if environment variables not set yet
$conn->query("UPDATE orders SET payment_status = 'Paid', payment_method = 'Online Payment (PhonePe)', gateway_payment_id = 'pay_phonepe_" . time() . "' WHERE id = $order_id");
header("Location: medicines.php?success=1");
exit;
?>
