<?php
require_once 'config.php';
require_once 'includes/notification_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized admin session']);
    exit;
}

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';

// 1. Delete Order (Safe Deletion)
if ($action === 'delete_order') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;

    if ($order_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Order ID']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE orders SET is_deleted = 1 WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'order_id' => $order_id, 'message' => 'Order deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete order']);
    }
    exit;
}

// 2. Cancel Order with Mandatory Reason
if ($action === 'cancel_order') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
    $reason = isset($input['reason']) ? trim($input['reason']) : '';

    if ($order_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Order ID']);
        exit;
    }

    if (empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'Cancellation reason is mandatory and cannot be empty']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled', cancellation_reason = ? WHERE id = ?");
    $stmt->bind_param("si", $reason, $order_id);
    if ($stmt->execute()) {
        // Fetch patient_id for notification
        $res = $conn->query("SELECT patient_id FROM orders WHERE id = $order_id");
        if ($res && $row = $res->fetch_assoc()) {
            $patient_id = $row['patient_id'];
            create_notification(
                $patient_id,
                "Order #ORD-" . str_pad($order_id, 4, '0', STR_PAD_LEFT) . " Cancelled",
                "Your order has been cancelled by Admin. Reason: " . $reason,
                'order',
                'order',
                $order_id
            );
        }
        echo json_encode([
            'success' => true,
            'order_id' => $order_id,
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
            'message' => 'Order cancelled successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel order']);
    }
    exit;
}

// 3. Set/Update Estimated Delivery Time
if ($action === 'set_delivery_time') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
    $est_time = isset($input['estimated_delivery_time']) ? trim($input['estimated_delivery_time']) : '';

    if ($order_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Order ID']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE orders SET estimated_delivery_time = ? WHERE id = ?");
    $stmt->bind_param("si", $est_time, $order_id);
    if ($stmt->execute()) {
        // Fetch patient_id for notification
        if (!empty($est_time)) {
            $res = $conn->query("SELECT patient_id FROM orders WHERE id = $order_id");
            if ($res && $row = $res->fetch_assoc()) {
                create_notification(
                    $row['patient_id'],
                    "Estimated Delivery Updated",
                    "Estimated delivery time for Order #ORD-" . str_pad($order_id, 4, '0', STR_PAD_LEFT) . " is: " . $est_time,
                    'order',
                    'order',
                    $order_id
                );
            }
        }
        echo json_encode([
            'success' => true,
            'order_id' => $order_id,
            'estimated_delivery_time' => $est_time,
            'message' => 'Estimated delivery time updated successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update estimated delivery time']);
    }
    exit;
}

// 4. Update Order Status
if ($action === 'update_status') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
    $status = isset($input['status']) ? trim($input['status']) : '';

    $allowed = ['pending', 'shipped', 'delivered', 'cancelled'];
    if ($order_id <= 0 || !in_array($status, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid order status parameter']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $order_id);
    if ($stmt->execute()) {
        require_once 'includes/referral_functions.php';
        sync_pending_referrals();
        echo json_encode([
            'success' => true,
            'order_id' => $order_id,
            'status' => $status,
            'message' => 'Order status updated to ' . ucfirst($status)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update order status']);
    }
    exit;
}

// 5. Fetch Full Details for Single Order
if ($action === 'get_order_details') {
    $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
    if ($order_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        exit;
    }

    $order_q = $conn->query("
        SELECT o.*, p.name as patient_name, p.phone as patient_phone, p.email as patient_email
        FROM orders o
        JOIN users p ON o.patient_id = p.id
        WHERE o.id = $order_id
    ");

    if (!$order_q || $order_q->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    $order = $order_q->fetch_assoc();

    $items_q = $conn->query("
        SELECT oi.*, m.name as medicine_name, m.image as medicine_image
        FROM order_items oi
        JOIN medicines m ON oi.medicine_id = m.id
        WHERE oi.order_id = $order_id
    ");

    $items = [];
    if ($items_q) {
        while ($row = $items_q->fetch_assoc()) {
            $items[] = $row;
        }
    }

    $order['items'] = $items;
    echo json_encode(['success' => true, 'order' => $order]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid API action']);
?>
