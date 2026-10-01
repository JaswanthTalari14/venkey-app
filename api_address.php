<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized user session']);
    exit;
}

$patient_id = (int)$_SESSION['user_id'];
$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'list';

if ($action === 'list') {
    $res = $conn->query("SELECT * FROM patient_addresses WHERE patient_id = $patient_id ORDER BY is_default DESC, id DESC");
    $addresses = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $addresses[] = $row;
        }
    }
    
    // If patient has no address, pre-fill patient name and phone from users table
    $user_res = $conn->query("SELECT name, phone FROM users WHERE id = $patient_id");
    $user_data = $user_res ? $user_res->fetch_assoc() : ['name' => '', 'phone' => ''];
    
    echo json_encode([
        'success' => true,
        'addresses' => $addresses,
        'default_name' => $user_data['name'] ?? '',
        'default_phone' => $user_data['phone'] ?? ''
    ]);
    exit;
}

if ($action === 'save') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $address_id = isset($input['address_id']) ? (int)$input['address_id'] : 0;
    $full_name = isset($input['full_name']) ? trim($conn->real_escape_string($input['full_name'])) : '';
    $phone = isset($input['phone']) ? trim($conn->real_escape_string($input['phone'])) : '';
    $address_line = isset($input['address_line']) ? trim($conn->real_escape_string($input['address_line'])) : '';
    $city = isset($input['city']) ? trim($conn->real_escape_string($input['city'])) : '';
    $state = isset($input['state']) ? trim($conn->real_escape_string($input['state'])) : '';
    $pincode = isset($input['pincode']) ? trim($conn->real_escape_string($input['pincode'])) : '';
    
    if (empty($full_name) || empty($phone) || empty($address_line) || empty($city) || empty($pincode)) {
        echo json_encode(['success' => false, 'message' => 'Please fill all required delivery address fields.']);
        exit;
    }
    
    // Set other addresses as non-default if making this default
    $conn->query("UPDATE patient_addresses SET is_default = 0 WHERE patient_id = $patient_id");
    
    if ($address_id > 0) {
        // Update existing address
        $stmt = $conn->prepare("UPDATE patient_addresses SET full_name = ?, phone = ?, address_line = ?, city = ?, state = ?, pincode = ?, is_default = 1 WHERE id = ? AND patient_id = ?");
        $stmt->bind_param("ssssssii", $full_name, $phone, $address_line, $city, $state, $pincode, $address_id, $patient_id);
        $stmt->execute();
        $saved_id = $address_id;
    } else {
        // Insert new address
        $stmt = $conn->prepare("INSERT INTO patient_addresses (patient_id, full_name, phone, address_line, city, state, pincode, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("issssss", $patient_id, $full_name, $phone, $address_line, $city, $state, $pincode);
        $stmt->execute();
        $saved_id = $stmt->insert_id;
    }
    
    $full_address_str = "$full_name ($phone), $address_line, $city, $state - $pincode";
    
    echo json_encode([
        'success' => true,
        'address_id' => $saved_id,
        'full_address' => $full_address_str,
        'message' => 'Delivery address saved successfully!'
    ]);
    exit;
}

if ($action === 'set_default') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $address_id = isset($input['address_id']) ? (int)$input['address_id'] : 0;
    
    if ($address_id > 0) {
        $conn->query("UPDATE patient_addresses SET is_default = 0 WHERE patient_id = $patient_id");
        $conn->query("UPDATE patient_addresses SET is_default = 1 WHERE id = $address_id AND patient_id = $patient_id");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid address ID']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
