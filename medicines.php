<?php
require_once 'config.php';
require_once 'includes/referral_functions.php';
require_once 'includes/wallet_functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$patient_id = (int)$_SESSION['user_id'];
$avail_ref_balance = get_customer_referral_balance($patient_id);
$avail_wallet_balance = get_wallet_balance($patient_id);

$active_card = get_patient_active_medical_card($conn, $patient_id);
$med_card_settings = get_medical_card_settings($conn);
$has_active_card = ($active_card !== null);
$card_medicine_discount_percent = $has_active_card ? (float)$med_card_settings['medicine_discount_percent'] : 0.00;

include 'includes/header.php';

$success = '';
$error = '';
$online_order_data = null;
$order_success_details = null;

// Fetch Patient Saved Addresses
$saved_addresses_res = $conn->query("SELECT * FROM patient_addresses WHERE patient_id = $patient_id ORDER BY is_default DESC, id DESC");
$saved_addresses = [];
if ($saved_addresses_res) {
    while ($addr = $saved_addresses_res->fetch_assoc()) {
        $saved_addresses[] = $addr;
    }
}

// Fetch Patient profile for default fallback name/phone
$user_info_res = $conn->query("SELECT name, phone FROM users WHERE id = $patient_id");
$patient_info = $user_info_res ? $user_info_res->fetch_assoc() : ['name' => '', 'phone' => ''];

if (isset($_GET['success']) && isset($_GET['order_id'])) {
    $success_order_id = (int)$_GET['order_id'];
    $succ_q = $conn->query("
        SELECT o.id, o.total_amount, o.payment_method, o.payment_status, o.address, o.created_at, m.name as medicine_name, oi.quantity 
        FROM orders o 
        JOIN order_items oi ON o.id = oi.order_id 
        JOIN medicines m ON oi.medicine_id = m.id 
        WHERE o.id = $success_order_id AND o.patient_id = $patient_id
    ");
    if ($succ_q && $succ_q->num_rows > 0) {
        $order_success_details = $succ_q->fetch_assoc();
    }
} elseif (isset($_GET['success'])) {
    $success = "Medicine ordered successfully! It will be delivered soon.";
}

if (!isset($GLOBALS['medicines_initialized'])) {
    $GLOBALS['medicines_initialized'] = true;
    $check_meds = @$conn->query("SELECT 1 FROM medicines LIMIT 1");
    if (!$check_meds || $check_meds->num_rows == 0) {
        $check_col = $conn->query("SHOW COLUMNS FROM medicines LIKE 'image'");
        if ($check_col && $check_col->num_rows == 0) {
            $conn->query("ALTER TABLE medicines ADD COLUMN image VARCHAR(255) DEFAULT NULL");
        }
        $check_pay_col = $conn->query("SHOW COLUMNS FROM orders LIKE 'payment_method'");
        if ($check_pay_col && $check_pay_col->num_rows == 0) {
            $conn->query("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) DEFAULT 'COD', ADD COLUMN payment_status VARCHAR(50) DEFAULT 'Cash on Delivery', ADD COLUMN gateway_order_id VARCHAR(100) DEFAULT NULL, ADD COLUMN gateway_payment_id VARCHAR(100) DEFAULT NULL");
        }
        $conn->query("INSERT INTO medicines (name, description, price, stock, image) VALUES 
            ('Paracetamol 500mg', 'Fever and mild pain relief.', 15.00, 100, 'images/medicines/paracetamol.png'),
            ('Amoxicillin 250mg', 'Antibiotic for bacterial infections.', 120.00, 50, 'images/medicines/amoxicillin.png'),
            ('Cetirizine 10mg', 'Allergy relief tablets.', 45.00, 200, 'images/medicines/cetirizine.png'),
            ('Vitamin C + Zinc', 'Immunity booster supplement.', 250.00, 80, 'images/medicines/vitaminc.png')");
    }
}

// Handle Order Creation Request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['order'])) {
    $medicine_id = (int)$_POST['medicine_id'];
    $qty = max(1, (int)$_POST['quantity']);
    $payment_method = isset($_POST['payment_method']) && $_POST['payment_method'] === 'Online Payment' ? 'Online Payment' : 'COD';
    
    // Address Handling & Backend Validation
    $delivery_address = '';
    $address_id = isset($_POST['address_id']) ? (int)$_POST['address_id'] : 0;
    
    if ($address_id > 0) {
        $addr_q = $conn->query("SELECT * FROM patient_addresses WHERE id = $address_id AND patient_id = $patient_id");
        if ($addr_q && $addr_q->num_rows > 0) {
            $a = $addr_q->fetch_assoc();
            $delivery_address = $a['full_name'] . " (" . $a['phone'] . "), " . $a['address_line'] . ", " . $a['city'] . ", " . $a['state'] . " - " . $a['pincode'];
        }
    }
    
    // Fallback: Check if new address details submitted directly in form
    if (empty($delivery_address) && !empty($_POST['new_address_line'])) {
        $full_name = trim($conn->real_escape_string($_POST['new_full_name'] ?? $patient_info['name']));
        $phone = trim($conn->real_escape_string($_POST['new_phone'] ?? $patient_info['phone']));
        $address_line = trim($conn->real_escape_string($_POST['new_address_line']));
        $city = trim($conn->real_escape_string($_POST['new_city']));
        $state = trim($conn->real_escape_string($_POST['new_state']));
        $pincode = trim($conn->real_escape_string($_POST['new_pincode']));
        
        if (!empty($full_name) && !empty($phone) && !empty($address_line) && !empty($city) && !empty($pincode)) {
            $conn->query("UPDATE patient_addresses SET is_default = 0 WHERE patient_id = $patient_id");
            $ins_stmt = $conn->prepare("INSERT INTO patient_addresses (patient_id, full_name, phone, address_line, city, state, pincode, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $ins_stmt->bind_param("issssss", $patient_id, $full_name, $phone, $address_line, $city, $state, $pincode);
            $ins_stmt->execute();
            $delivery_address = "$full_name ($phone), $address_line, $city, $state - $pincode";
        }
    }

    // MANDATORY ADDRESS VALIDATION
    if (empty($delivery_address)) {
        $error = "Please add a delivery address to continue.";
    } else {
        // Fetch Medicine Details & Backend Pricing Validation
        $med_res = $conn->query("SELECT price, name FROM medicines WHERE id=$medicine_id");
        if ($med_res && $med_res->num_rows > 0) {
            $med = $med_res->fetch_assoc();
            $original_total = $med['price'] * $qty;
            $subtotal_after_card = $original_total;

            // Apply Digital Medical Card Discount if Active
            $card_disc_info = calculate_medicine_order_discount($conn, $patient_id, $original_total);
            if ($card_disc_info['has_discount']) {
                $subtotal_after_card = $card_disc_info['final_amount'];
            }
            $total = $subtotal_after_card;

            // 1. Handle optional Referral Reward Balance Deduction
            if (isset($_POST['use_referral_balance']) && $_POST['use_referral_balance'] == '1' && $avail_ref_balance > 0) {
                $deduction = min($avail_ref_balance, $total);
                $total = max(0.00, $total - $deduction);
                
                $tx_red = 'RED_' . time() . '_' . rand(1000, 9999);
                $conn->query("INSERT INTO referral_rewards (customer_id, reward_type, amount, status, description, transaction_id) 
                              VALUES ($patient_id, 'redemption', -$deduction, 'redeemed', 'Redeemed referral reward balance at order checkout', '$tx_red')");
                $avail_ref_balance = get_customer_referral_balance($patient_id);
            }

            // 2. Handle optional Wallet Balance Deduction
            $wallet_used = 0.00;
            if (isset($_POST['use_wallet_balance']) && $_POST['use_wallet_balance'] == '1' && $avail_wallet_balance > 0 && $total > 0) {
                $wallet_used = min($avail_wallet_balance, $total);
                $total = max(0.00, $total - $wallet_used);
            }
            
            $escaped_address = $conn->real_escape_string($delivery_address);

            if ($total == 0.00 && $wallet_used > 0) {
                // FULL WALLET PAYMENT
                $stmt = $conn->prepare("INSERT INTO orders (patient_id, total_amount, address, payment_method, payment_status) VALUES (?, ?, ?, 'Wallet', 'Paid via Wallet')");
                $stmt->bind_param("ids", $patient_id, $original_total, $escaped_address);
                $stmt->execute();
                $order_id = $stmt->insert_id;
                
                $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, medicine_id, quantity, price) VALUES (?, ?, ?, ?)");
                $item_stmt->bind_param("iiid", $order_id, $medicine_id, $qty, $med['price']);
                $item_stmt->execute();

                process_wallet_payment($patient_id, $order_id, $wallet_used);
                $avail_wallet_balance = get_wallet_balance($patient_id);
                process_referral_order_qualification($order_id, $patient_id, $original_total, true);
                
                header("Location: medicines.php?success=1&order_id=" . $order_id);
                exit;
            } else if ($payment_method === 'COD') {
                // CASH ON DELIVERY FLOW
                $stmt = $conn->prepare("INSERT INTO orders (patient_id, total_amount, address, payment_method, payment_status) VALUES (?, ?, ?, 'COD', 'Cash on Delivery')");
                $stmt->bind_param("ids", $patient_id, $original_total, $escaped_address);
                $stmt->execute();
                $order_id = $stmt->insert_id;
                
                $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, medicine_id, quantity, price) VALUES (?, ?, ?, ?)");
                $item_stmt->bind_param("iiid", $order_id, $medicine_id, $qty, $med['price']);
                $item_stmt->execute();

                if ($wallet_used > 0) {
                    process_wallet_payment($patient_id, $order_id, $wallet_used);
                    $avail_wallet_balance = get_wallet_balance($patient_id);
                }

                process_referral_order_qualification($order_id, $patient_id, $original_total, true);
                
                header("Location: medicines.php?success=1&order_id=" . $order_id);
                exit;
            } else {
                // ONLINE PAYMENT FLOW FOR REMAINING TOTAL
                $stmt = $conn->prepare("INSERT INTO orders (patient_id, total_amount, address, payment_method, payment_status) VALUES (?, ?, ?, 'Online Payment', 'Pending')");
                $stmt->bind_param("ids", $patient_id, $original_total, $escaped_address);
                $stmt->execute();
                $order_id = $stmt->insert_id;
                
                $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, medicine_id, quantity, price) VALUES (?, ?, ?, ?)");
                $item_stmt->bind_param("iiid", $order_id, $medicine_id, $qty, $med['price']);
                $item_stmt->execute();

                if ($wallet_used > 0) {
                    process_wallet_payment($patient_id, $order_id, $wallet_used);
                    $avail_wallet_balance = get_wallet_balance($patient_id);
                }

                if (defined('PHONEPE_MERCHANT_ID') && !empty(PHONEPE_MERCHANT_ID)) {
                    header("Location: phonepe_pay.php?order_id=" . $order_id);
                    exit;
                }
                
                $online_order_data = [
                    'order_id' => $order_id,
                    'amount_paise' => (int)round($total * 100),
                    'amount_display' => number_format($total, 2),
                    'medicine_name' => $med['name'],
                    'patient_id' => $patient_id
                ];
            }
        }
    }
}

// 1. Medicine Catalog Smart Data Loading & Pagination
$med_per_page = 8;
$med_page = isset($_GET['med_page']) ? max(1, (int)$_GET['med_page']) : 1;
$med_offset = ($med_page - 1) * $med_per_page;

$med_count_res = $conn->query("SELECT COUNT(*) as total FROM medicines");
$total_meds = $med_count_res ? (int)$med_count_res->fetch_assoc()['total'] : 0;
$total_med_pages = max(1, ceil($total_meds / $med_per_page));

$medicines = $conn->query("SELECT * FROM medicines ORDER BY id ASC LIMIT $med_per_page OFFSET $med_offset");

// 2. Patient Medicine Orders Smart Data Loading & Pagination
$patient_id_for_orders = (int)$_SESSION['user_id'];
$ord_per_page = 10;
$ord_page = isset($_GET['ord_page']) ? max(1, (int)$_GET['ord_page']) : 1;
$ord_offset = ($ord_page - 1) * $ord_per_page;

$ord_count_res = $conn->query("SELECT COUNT(*) as total FROM orders WHERE patient_id = $patient_id_for_orders AND (is_deleted = 0 OR is_deleted IS NULL)");
$total_my_orders = $ord_count_res ? (int)$ord_count_res->fetch_assoc()['total'] : 0;
$total_ord_pages = max(1, ceil($total_my_orders / $ord_per_page));

$my_orders = $conn->query("
    SELECT o.id, o.created_at, o.status, o.total_amount, o.payment_method, o.payment_status, o.address, m.name as medicine_name, oi.quantity 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN medicines m ON oi.medicine_id = m.id
    WHERE o.patient_id = $patient_id_for_orders AND (o.is_deleted = 0 OR o.is_deleted IS NULL)
    ORDER BY o.created_at DESC
    LIMIT $ord_per_page OFFSET $ord_offset
");

function build_med_link($m_page, $o_page) {
    return "?med_page=$m_page&ord_page=$o_page";
}
?>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Patient Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Patient Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Patient Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="patient_dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="digital_medical_card.php"><i class="fas fa-id-card"></i> Digital Medical Card</a></li>
            <li><a href="book_consult.php"><i class="fas fa-calendar-check"></i> Consultations</a></li>
            <li><a href="medicines.php" class="active"><i class="fas fa-pills"></i> Order Medicines</a></li>
            <li><a href="your_orders.php"><i class="fas fa-boxes"></i> Your Orders</a></li>
            <li><a href="nearby_doctors.php"><i class="fas fa-map-marker-alt"></i> Find Doctors (10km)</a></li>
            <li><a href="privacy_consult.php"><i class="fas fa-user-secret"></i> Privacy Consult</a></li>
            <li><a href="book_tests.php"><i class="fas fa-vial"></i> Book Labs (RMP)</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="refer_earn.php"><i class="fas fa-gift"></i> Refer & Earn</a></li>
            <li><a href="my_wallet.php"><i class="fas fa-wallet"></i> My Wallet</a></li>
            <li><a href="chatbot.php"><i class="fas fa-robot"></i> AI Chatbot</a></li>
            <li><a href="javascript:void(0);" class="pwaInstallBtn"><i class="fas fa-download"></i> Install App</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Medicine Delivery</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Order prescribed or over-the-counter medicines delivered directly to your home.</p>
        
        <?php if($success): ?>
            <p style="color: #2ed573; margin-bottom: 1rem; padding: 1rem; background: rgba(46, 213, 115, 0.1); border-radius: 8px; border-left: 4px solid #2ed573;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </p>
        <?php endif; ?>
        
        <?php if($error): ?>
            <p style="color: #ff4757; margin-bottom: 1rem; padding: 1rem; background: rgba(255, 71, 87, 0.1); border-radius: 8px; border-left: 4px solid #ff4757;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </p>
        <?php endif; ?>

        <!-- Medicine Cards Grid -->
        <div class="features-grid" style="margin-top: 1rem;">
            <?php while($med = $medicines->fetch_assoc()): ?>
                <?php 
                    $img_src = 'images/medicines/default.png';
                    if (!empty($med['image']) && file_exists($med['image'])) {
                        $img_src = $med['image'];
                    } else {
                        $name_lower = strtolower($med['name']);
                        if (strpos($name_lower, 'paracetamol') !== false) $img_src = 'images/medicines/paracetamol.png';
                        elseif (strpos($name_lower, 'amoxicillin') !== false) $img_src = 'images/medicines/amoxicillin.png';
                        elseif (strpos($name_lower, 'cetirizine') !== false) $img_src = 'images/medicines/cetirizine.png';
                        elseif (strpos($name_lower, 'vitamin') !== false) $img_src = 'images/medicines/vitaminc.png';
                    }
                ?>
                <div class="feature-card glass-panel" style="padding: 1rem; display: flex; flex-direction: column;">
                    <div style="width: 100%; height: 125px; overflow: hidden; border-radius: 10px; margin-bottom: 0.6rem; background: rgba(0,0,0,0.2);">
                        <img src="<?php echo htmlspecialchars($img_src); ?>" alt="<?php echo htmlspecialchars($med['name']); ?>" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px; transition: transform 0.3s ease;">
                    </div>
                    <h4 style="color: var(--text-primary); margin-bottom: 0.25rem; font-size: 1.05rem;"><?php echo htmlspecialchars($med['name']); ?></h4>
                    <p style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 0.5rem; min-height: 32px; line-height: 1.3;"><?php echo htmlspecialchars($med['description']); ?></p>
                    <p style="font-size: 1.3rem; font-weight: bold; color: var(--secondary-color); margin-bottom: 0.5rem;">₹<?php echo number_format($med['price'], 2); ?></p>
                    
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: auto;">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                            <label style="font-size: 0.8rem; color: var(--text-secondary);">Quantity:</label>
                            <input type="number" id="qty_med_<?php echo $med['id']; ?>" value="1" min="1" max="10" class="form-control" style="width: 75px; padding: 0.3rem 0.5rem;" required>
                        </div>
                        
                        <button type="button" class="btn btn-primary" style="width: 100%; padding: 0.5rem 1rem;" onclick="openCheckoutModal(<?php echo $med['id']; ?>, '<?php echo htmlspecialchars(addslashes($med['name'])); ?>', <?php echo $med['price']; ?>, '<?php echo htmlspecialchars(addslashes($img_src)); ?>')">
                            <i class="fas fa-shopping-cart"></i> Order Now
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Medicine Catalog Pagination Controls -->
        <?php if ($total_med_pages > 1): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; pt: 1rem; border-top: 1px solid var(--glass-border); flex-wrap: wrap; gap: 1rem;">
                <span style="font-size: 0.88rem; color: var(--text-secondary);">
                    Showing <?php echo min($med_offset + 1, $total_meds); ?>–<?php echo min($med_offset + $med_per_page, $total_meds); ?> of <?php echo $total_meds; ?> medicines
                </span>
                <div style="display: flex; gap: 0.4rem; align-items: center;">
                    <?php if ($med_page > 1): ?>
                        <a href="<?php echo build_med_link($med_page - 1, $ord_page); ?>" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-chevron-left"></i> Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_med_pages; $i++): ?>
                        <a href="<?php echo build_med_link($i, $ord_page); ?>" class="btn <?php echo ($i === $med_page) ? 'btn-primary' : 'btn-outline'; ?>" style="padding: 0.4rem 0.75rem; font-size: 0.85rem; min-width: 36px; text-align: center;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($med_page < $total_med_pages): ?>
                        <a href="<?php echo build_med_link($med_page + 1, $ord_page); ?>" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">Next <i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Your Medicine Orders Table -->
        <h3 style="margin-top: 3rem; margin-bottom: 1rem;">Your Medicine Orders</h3>
        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">Order ID</th>
                        <th style="padding: 1rem;">Medicine</th>
                        <th style="padding: 1rem;">Qty</th>
                        <th style="padding: 1rem;">Total Amount</th>
                        <th style="padding: 1rem;">Delivery Address</th>
                        <th style="padding: 1rem;">Payment Method</th>
                        <th style="padding: 1rem;">Payment Status</th>
                        <th style="padding: 1rem;">Date Ordered</th>
                        <th style="padding: 1rem;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($my_orders && $my_orders->num_rows > 0): ?>
                        <?php while($o = $my_orders->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem;">#ORD-<?php echo str_pad($o['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td style="padding: 1rem; font-weight: bold; color: var(--primary-color);"><?php echo htmlspecialchars($o['medicine_name']); ?></td>
                                <td style="padding: 1rem;"><?php echo $o['quantity']; ?></td>
                                <td style="padding: 1rem; color: var(--secondary-color); font-weight: bold;">₹<?php echo number_format($o['total_amount'], 2); ?></td>
                                <td style="padding: 1rem; font-size: 0.82rem; color: var(--text-secondary); max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($o['address']); ?>">
                                    <i class="fas fa-map-marker-alt" style="color: var(--primary-color);"></i> <?php echo htmlspecialchars($o['address']); ?>
                                </td>
                                <td style="padding: 1rem; font-size: 0.88rem; color: var(--text-secondary);"><?php echo htmlspecialchars($o['payment_method'] ?? 'COD'); ?></td>
                                <td style="padding: 1rem;">
                                    <?php
                                        $pay_status = $o['payment_status'] ?? 'Cash on Delivery';
                                        $pay_color = '#f5a623';
                                        if (in_array($pay_status, ['Paid', 'Paid via Wallet'])) $pay_color = '#2ed573';
                                        if ($pay_status === 'Failed') $pay_color = '#ff4757';
                                        if ($pay_status === 'Cash on Delivery') $pay_color = '#3498db';
                                    ?>
                                    <span style="color: <?php echo $pay_color; ?>; font-weight: bold; font-size: 0.82rem;">
                                        <?php echo htmlspecialchars($pay_status); ?>
                                    </span>
                                    <?php if ($pay_status === 'Pending' && ($o['payment_method'] ?? '') === 'Online Payment'): ?>
                                        <button onclick="retryPayment(<?php echo $o['id']; ?>, <?php echo $o['total_amount']; ?>, '<?php echo htmlspecialchars(addslashes($o['medicine_name'])); ?>')" class="btn btn-primary" style="padding: 0.2rem 0.6rem; font-size: 0.75rem; margin-left: 0.5rem;"><i class="fas fa-redo"></i> Pay Again</button>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: var(--text-secondary);"><?php echo date('M d, Y', strtotime($o['created_at'])); ?></td>
                                <td style="padding: 1rem;">
                                    <?php
                                        $status_color = 'var(--text-primary)';
                                        if ($o['status'] == 'pending') $status_color = 'var(--accent)';
                                        if ($o['status'] == 'shipped') $status_color = '#3498db';
                                        if ($o['status'] == 'delivered') $status_color = '#2ed573';
                                        if ($o['status'] == 'cancelled') $status_color = '#ff4757';
                                    ?>
                                    <span style="color: <?php echo $status_color; ?>; font-weight: bold; text-transform: capitalize; background: rgba(255,255,255,0.05); padding: 0.3rem 0.8rem; border-radius: 12px; font-size: 0.8rem;">
                                        <?php echo $o['status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9" style="padding: 1rem; text-align: center; color: var(--text-secondary);">You have not ordered any medicines yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Patient Orders Pagination Controls -->
            <?php if ($total_ord_pages > 1): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; pt: 1rem; border-top: 1px solid var(--glass-border); flex-wrap: wrap; gap: 1rem;">
                    <span style="font-size: 0.88rem; color: var(--text-secondary);">
                        Showing <?php echo min($ord_offset + 1, $total_my_orders); ?>–<?php echo min($ord_offset + $ord_per_page, $total_my_orders); ?> of <?php echo $total_my_orders; ?> orders
                    </span>
                    <div style="display: flex; gap: 0.4rem; align-items: center;">
                        <?php if ($ord_page > 1): ?>
                            <a href="<?php echo build_med_link($med_page, $ord_page - 1); ?>" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-chevron-left"></i> Previous</a>
                        <?php endif; ?>

                        <?php for ($j = 1; $j <= $total_ord_pages; $j++): ?>
                            <a href="<?php echo build_med_link($med_page, $j); ?>" class="btn <?php echo ($j === $ord_page) ? 'btn-primary' : 'btn-outline'; ?>" style="padding: 0.4rem 0.75rem; font-size: 0.85rem; min-width: 36px; text-align: center;">
                                <?php echo $j; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($ord_page < $total_ord_pages): ?>
                            <a href="<?php echo build_med_link($med_page, $ord_page + 1); ?>" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">Next <i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- ========================================================= -->
<!-- MEDICINE CHECKOUT MODAL (PART 2 - PART 6) -->
<!-- ========================================================= -->
<div id="medicineCheckoutModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 99998; align-items: center; justify-content: center; padding: 1rem; overflow-y: auto;">
    <div class="glass-panel" style="background: var(--darker-bg); border: 1px solid var(--glass-border); width: 100%; max-width: 580px; padding: 1.8rem; border-radius: 20px; box-shadow: 0 25px 60px rgba(0,0,0,0.6); position: relative; max-height: 90vh; overflow-y: auto;">
        
        <!-- Modal Header -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.8rem;">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <i class="fas fa-shopping-bag" style="color: var(--primary-color); font-size: 1.4rem;"></i>
                <h3 style="color: var(--text-primary); margin: 0; font-size: 1.25rem;">Medicine Checkout</h3>
            </div>
            <button type="button" onclick="closeCheckoutModal()" style="background: transparent; border: none; color: var(--text-secondary); font-size: 1.3rem; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" action="" id="checkoutForm" onsubmit="return validateAndSubmitCheckout(event);">
            <input type="hidden" name="order" value="1">
            <input type="hidden" id="modal_medicine_id" name="medicine_id" value="">
            <input type="hidden" id="modal_quantity" name="quantity" value="1">
            <input type="hidden" id="modal_address_id" name="address_id" value="">

            <!-- Validation Error Alert Box -->
            <div id="checkoutAlert" style="display: none; background: rgba(255, 71, 87, 0.12); border: 1px solid #ff4757; color: #ff4757; padding: 0.75rem 1rem; border-radius: 10px; font-size: 0.88rem; margin-bottom: 1rem;">
                <i class="fas fa-exclamation-circle"></i> <span id="checkoutAlertText">Please add a delivery address to continue.</span>
            </div>

            <!-- STEP 1: DELIVERY ADDRESS -->
            <div style="margin-bottom: 1.5rem; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 12px; padding: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <h4 style="margin: 0; font-size: 0.95rem; color: var(--text-primary);"><i class="fas fa-map-marker-alt" style="color: var(--secondary-color);"></i> Delivery Address</h4>
                    <button type="button" id="btnToggleAddressForm" onclick="toggleAddressForm()" style="background: none; border: none; color: var(--primary-color); font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                        <i class="fas fa-plus"></i> Add / Edit Address
                    </button>
                </div>

                <!-- Saved Address Display Card -->
                <div id="savedAddressContainer">
                    <?php if (!empty($saved_addresses)): ?>
                        <?php $default_addr = $saved_addresses[0]; ?>
                        <div id="selectedAddressCard" style="background: rgba(80, 227, 194, 0.08); border: 1px solid rgba(80, 227, 194, 0.3); border-radius: 10px; padding: 0.8rem 1rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.3rem;">
                                <strong style="color: var(--text-primary); font-size: 0.9rem;" id="disp_addr_name"><?php echo htmlspecialchars($default_addr['full_name']); ?> (<?php echo htmlspecialchars($default_addr['phone']); ?>)</strong>
                                <span style="background: var(--secondary-color); color: #000; font-size: 0.68rem; font-weight: bold; padding: 0.15rem 0.5rem; border-radius: 10px;">DEFAULT</span>
                            </div>
                            <p style="color: var(--text-secondary); font-size: 0.82rem; margin: 0; line-height: 1.4;" id="disp_addr_text">
                                <?php echo htmlspecialchars($default_addr['address_line'] . ", " . $default_addr['city'] . ", " . $default_addr['state'] . " - " . $default_addr['pincode']); ?>
                            </p>
                        </div>
                        <script>document.getElementById('modal_address_id').value = "<?php echo $default_addr['id']; ?>";</script>
                    <?php else: ?>
                        <div id="noAddressNotice" style="text-align: center; color: var(--text-secondary); padding: 0.75rem; font-size: 0.85rem; border: 1px dashed var(--glass-border); border-radius: 10px;">
                            No delivery address saved yet. Please add your delivery address below.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Add/Edit Delivery Address Inline Form -->
                <div id="addAddressForm" style="display: <?php echo empty($saved_addresses) ? 'block' : 'none'; ?>; margin-top: 0.8rem; border-top: 1px dashed var(--glass-border); padding-top: 0.8rem;">
                    <div style="font-size: 0.85rem; font-weight: 600; color: var(--primary-color); margin-bottom: 0.6rem;">Provide Delivery Address Details:</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; margin-bottom: 0.6rem;">
                        <input type="text" id="new_full_name" name="new_full_name" class="form-control" placeholder="Full Name *" value="<?php echo htmlspecialchars($patient_info['name']); ?>" style="padding: 0.4rem 0.6rem; font-size: 0.82rem;">
                        <input type="text" id="new_phone" name="new_phone" class="form-control" placeholder="Phone Number *" value="<?php echo htmlspecialchars($patient_info['phone']); ?>" style="padding: 0.4rem 0.6rem; font-size: 0.82rem;">
                    </div>
                    <div style="margin-bottom: 0.6rem;">
                        <input type="text" id="new_address_line" name="new_address_line" class="form-control" placeholder="House No, Street, Colony, Area *" style="padding: 0.4rem 0.6rem; font-size: 0.82rem;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.6rem; margin-bottom: 0.6rem;">
                        <input type="text" id="new_city" name="new_city" class="form-control" placeholder="City *" style="padding: 0.4rem 0.6rem; font-size: 0.82rem;">
                        <input type="text" id="new_state" name="new_state" class="form-control" placeholder="State" value="Telangana" style="padding: 0.4rem 0.6rem; font-size: 0.82rem;">
                        <input type="text" id="new_pincode" name="new_pincode" class="form-control" placeholder="Pincode *" style="padding: 0.4rem 0.6rem; font-size: 0.82rem;">
                    </div>
                    <button type="button" class="btn btn-outline" onclick="saveAddressViaAjax()" style="width: 100%; padding: 0.35rem 0.6rem; font-size: 0.8rem;">
                        <i class="fas fa-save"></i> Save & Use This Address
                    </button>
                </div>
            </div>

            <!-- STEP 2: ORDER SUMMARY -->
            <div style="margin-bottom: 1.5rem; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 12px; padding: 1rem;">
                <h4 style="margin: 0 0 0.75rem 0; font-size: 0.95rem; color: var(--text-primary);"><i class="fas fa-list-alt" style="color: var(--primary-color);"></i> Order Summary</h4>
                
                <div style="display: flex; align-items: center; gap: 0.8rem; margin-bottom: 0.8rem; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 0.8rem;">
                    <img id="summary_img" src="" alt="Medicine" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; background: rgba(0,0,0,0.3);">
                    <div style="flex: 1;">
                        <div id="summary_med_name" style="font-weight: bold; color: var(--text-primary); font-size: 0.92rem;">Medicine Name</div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary);">Qty: <span id="summary_qty_display">1</span> x ₹<span id="summary_unit_price">0.00</span></div>
                    </div>
                    <div style="font-weight: bold; color: var(--text-primary); font-size: 0.95rem;">₹<span id="summary_subtotal">0.00</span></div>
                </div>

                <div style="display: flex; justify-content: space-between; font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 0.4rem;">
                    <span>Delivery Charges:</span>
                    <span style="color: #2ed573; font-weight: bold;">FREE</span>
                </div>

                <?php if ($has_active_card && $card_medicine_discount_percent > 0): ?>
                <div style="display: flex; justify-content: space-between; font-size: 0.82rem; color: #2ed573; margin-bottom: 0.4rem; font-weight: 600;">
                    <span><i class="fas fa-id-card"></i> Digital Medical Card Discount (<?php echo $card_medicine_discount_percent; ?>%):</span>
                    <span>-₹<span id="summary_card_discount">0.00</span></span>
                </div>
                <?php endif; ?>

                <!-- Wallet / Referral Balance Checkboxes -->
                <?php if ($avail_ref_balance > 0): ?>
                <div style="margin-top: 0.4rem;">
                    <label style="font-size: 0.8rem; color: var(--secondary-color); cursor: pointer; display: flex; align-items: center; gap: 0.3rem; font-weight: 600;">
                        <input type="checkbox" id="modal_use_ref" name="use_referral_balance" value="1" onchange="recalculateModalTotals()"> 
                        <i class="fas fa-gift"></i> Use Referral Balance (Available: ₹<?php echo number_format($avail_ref_balance, 2); ?>)
                    </label>
                </div>
                <?php endif; ?>

                <?php if ($avail_wallet_balance > 0): ?>
                <div style="margin-top: 0.4rem;">
                    <label style="font-size: 0.8rem; color: #2ed573; cursor: pointer; display: flex; align-items: center; gap: 0.3rem; font-weight: 600;">
                        <input type="checkbox" id="modal_use_wallet" name="use_wallet_balance" value="1" onchange="recalculateModalTotals()"> 
                        <i class="fas fa-wallet"></i> Use Wallet Balance (Available: ₹<?php echo number_format($avail_wallet_balance, 2); ?>)
                    </label>
                </div>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--glass-border); margin-top: 0.8rem; padding-top: 0.8rem;">
                    <span style="font-size: 1rem; font-weight: bold; color: var(--text-primary);">Final Payable Amount:</span>
                    <span style="font-size: 1.4rem; font-weight: 800; color: var(--secondary-color);">₹<span id="summary_final_payable">0.00</span></span>
                </div>
            </div>

            <!-- STEP 3: PAYMENT METHOD -->
            <div style="margin-bottom: 1.5rem; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 12px; padding: 1rem;">
                <h4 style="margin: 0 0 0.75rem 0; font-size: 0.95rem; color: var(--text-primary);"><i class="fas fa-credit-card" style="color: var(--accent);"></i> Payment Method</h4>
                <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                    <label style="display: flex; align-items: center; gap: 0.6rem; padding: 0.6rem 0.8rem; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 8px; cursor: pointer; font-size: 0.88rem; color: var(--text-primary);">
                        <input type="radio" name="payment_method" value="COD" checked onclick="updateSubmitButtonLabel()">
                        <i class="fas fa-truck-loading" style="color: #3498db;"></i> Cash on Delivery (Pay when delivered)
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.6rem; padding: 0.6rem 0.8rem; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 8px; cursor: pointer; font-size: 0.88rem; color: var(--text-primary);">
                        <input type="radio" name="payment_method" value="Online Payment" onclick="updateSubmitButtonLabel()">
                        <i class="fas fa-shield-alt" style="color: #2ed573;"></i> Online Payment (UPI / Cards / Net Banking)
                    </label>
                </div>
            </div>

            <!-- STEP 4: SUBMIT ORDER BUTTON WITH DUPLICATE PROTECTION -->
            <button type="submit" id="btnPlaceMedicineOrder" class="btn btn-primary" style="width: 100%; font-size: 1.05rem; padding: 0.8rem; font-weight: bold;">
                <i class="fas fa-check-circle"></i> Place Order (COD)
            </button>
        </form>
    </div>
</div>

<!-- ========================================================= -->
<!-- ORDER SUCCESS CONFIRMATION MODAL (PART 15) -->
<!-- ========================================================= -->
<?php if ($order_success_details): ?>
<div id="orderSuccessModal" style="display: flex; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); z-index: 99999; align-items: center; justify-content: center; padding: 1rem;">
    <div class="glass-panel" style="background: var(--darker-bg); border: 1px solid rgba(46, 213, 115, 0.4); width: 100%; max-width: 480px; padding: 2rem; border-radius: 20px; box-shadow: 0 25px 60px rgba(0,0,0,0.7); text-align: center;">
        
        <div style="width: 70px; height: 70px; background: rgba(46, 213, 115, 0.15); border: 2px solid #2ed573; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.2rem auto; box-shadow: 0 0 25px rgba(46, 213, 115, 0.3);">
            <i class="fas fa-check" style="font-size: 2.2rem; color: #2ed573;"></i>
        </div>

        <h3 style="color: #fff; margin-bottom: 0.5rem; font-size: 1.5rem;">Order Successfully Placed!</h3>
        <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 1.5rem;">Your medicine order has been confirmed and is being processed for delivery.</p>

        <div style="background: rgba(255,255,255,0.04); border: 1px solid var(--glass-border); border-radius: 12px; padding: 1.2rem; margin-bottom: 1.5rem; text-align: left;">
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 0.5rem; margin-bottom: 0.5rem; font-size: 0.88rem;">
                <span style="color: var(--text-secondary);">Order Reference:</span>
                <strong style="color: var(--primary-color);">#ORD-<?php echo str_pad($order_success_details['id'], 4, '0', STR_PAD_LEFT); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 0.5rem; margin-bottom: 0.5rem; font-size: 0.88rem;">
                <span style="color: var(--text-secondary);">Medicine:</span>
                <strong style="color: #fff;"><?php echo htmlspecialchars($order_success_details['medicine_name']); ?> (x<?php echo $order_success_details['quantity']; ?>)</strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 0.5rem; margin-bottom: 0.5rem; font-size: 0.88rem;">
                <span style="color: var(--text-secondary);">Payment Method:</span>
                <strong style="color: var(--secondary-color);"><?php echo htmlspecialchars($order_success_details['payment_method']); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 0.5rem; margin-bottom: 0.5rem; font-size: 0.88rem;">
                <span style="color: var(--text-secondary);">Payment Status:</span>
                <strong style="color: #2ed573;"><?php echo htmlspecialchars($order_success_details['payment_status']); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 0.95rem; font-weight: bold; margin-top: 0.5rem;">
                <span style="color: var(--text-primary);">Total Amount:</span>
                <span style="color: var(--secondary-color);">₹<?php echo number_format($order_success_details['total_amount'], 2); ?></span>
            </div>
            <div style="margin-top: 0.8rem; font-size: 0.8rem; color: var(--text-secondary); border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 0.6rem;">
                <i class="fas fa-map-marker-alt" style="color: var(--primary-color);"></i> Delivered to: <?php echo htmlspecialchars($order_success_details['address']); ?>
            </div>
        </div>

        <button type="button" class="btn btn-primary" onclick="window.location.href='medicines.php'" style="width: 100%; padding: 0.75rem;">
            <i class="fas fa-boxes"></i> Done / View Orders
        </button>
    </div>
</div>
<?php endif; ?>

<!-- Payment Modal Dialog for Test/Sandbox Mode & Fallback -->
<div id="paymentGatewayModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); z-index: 99999; align-items: center; justify-content: center; padding: 1rem;">
    <div class="glass-panel" style="background: #1a1f2c; border: 1px solid var(--glass-border); width: 100%; max-width: 440px; padding: 2rem; border-radius: 16px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); text-align: center;">
        <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-bottom: 1rem;">
            <i class="fas fa-shield-alt" style="color: var(--secondary-color); font-size: 1.8rem;"></i>
            <h3 style="color: #fff; margin: 0;">Online Payment Gateway</h3>
        </div>
        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1rem;" id="modalMedName">Medicine Payment</p>
        <div style="font-size: 2rem; font-weight: bold; color: var(--secondary-color); margin-bottom: 1.5rem;" id="modalAmount">₹0.00</div>
        
        <div style="background: rgba(255,255,255,0.04); border: 1px solid var(--glass-border); border-radius: 10px; padding: 1rem; margin-bottom: 1.5rem; text-align: left;">
            <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.5rem; font-weight: bold;">Select Payment Type:</div>
            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: #fff; margin-bottom: 0.5rem; cursor: pointer;">
                <input type="radio" name="pay_type" value="upi" checked> <i class="fas fa-mobile-alt" style="color: #2ed573;"></i> UPI / GPay / PhonePe / Paytm
            </label>
            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: #fff; margin-bottom: 0.5rem; cursor: pointer;">
                <input type="radio" name="pay_type" value="card"> <i class="fas fa-credit-card" style="color: #3498db;"></i> Credit / Debit Card
            </label>
            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: #fff; cursor: pointer;">
                <input type="radio" name="pay_type" value="netbanking"> <i class="fas fa-university" style="color: #f5a623;"></i> Net Banking
            </label>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <button id="btnCancelPay" class="btn" style="flex: 1; background: rgba(255,255,255,0.1); color: #fff;">Cancel</button>
            <button id="btnConfirmPay" class="btn btn-primary" style="flex: 1.5;">Complete Payment</button>
        </div>
    </div>
</div>

<?php if ($online_order_data): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    triggerRazorpayCheckout(
        <?php echo $online_order_data['order_id']; ?>,
        <?php echo $online_order_data['amount_paise']; ?>,
        "<?php echo htmlspecialchars(addslashes($online_order_data['medicine_name'])); ?>"
    );
});
</script>
<?php endif; ?>

<script>
var activeMedicine = { id: 0, name: '', price: 0, img: '' };
var activeOrderId = null;
var availRefBalance = <?php echo (float)$avail_ref_balance; ?>;
var availWalletBalance = <?php echo (float)$avail_wallet_balance; ?>;

function openCheckoutModal(medId, medName, medPrice, medImg) {
    var qtyInput = document.getElementById('qty_med_' + medId);
    var qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;
    
    activeMedicine = { id: medId, name: medName, price: medPrice, img: medImg };
    
    document.getElementById('modal_medicine_id').value = medId;
    document.getElementById('modal_quantity').value = qty;
    document.getElementById('summary_img').src = medImg;
    document.getElementById('summary_med_name').innerText = medName;
    document.getElementById('summary_qty_display').innerText = qty;
    document.getElementById('summary_unit_price').innerText = medPrice.toFixed(2);
    
    document.getElementById('checkoutAlert').style.display = 'none';
    recalculateModalTotals();
    updateSubmitButtonLabel();
    
    document.getElementById('medicineCheckoutModal').style.display = 'flex';
}

function closeCheckoutModal() {
    document.getElementById('medicineCheckoutModal').style.display = 'none';
}

function recalculateModalTotals() {
    var qty = parseInt(document.getElementById('modal_quantity').value) || 1;
    var subtotal = activeMedicine.price * qty;
    document.getElementById('summary_subtotal').innerText = subtotal.toFixed(2);
    
    var activeCardDiscPercent = <?php echo $card_medicine_discount_percent; ?>;
    var cardDisc = 0;
    if (activeCardDiscPercent > 0) {
        cardDisc = (subtotal * activeCardDiscPercent) / 100;
        var cardEl = document.getElementById('summary_card_discount');
        if (cardEl) cardEl.innerText = cardDisc.toFixed(2);
    }

    var finalAmount = Math.max(0, subtotal - cardDisc);
    
    var useRefCb = document.getElementById('modal_use_ref');
    if (useRefCb && useRefCb.checked && availRefBalance > 0) {
        var refDeduct = Math.min(availRefBalance, finalAmount);
        finalAmount = Math.max(0, finalAmount - refDeduct);
    }
    
    var useWalletCb = document.getElementById('modal_use_wallet');
    if (useWalletCb && useWalletCb.checked && availWalletBalance > 0 && finalAmount > 0) {
        var walletDeduct = Math.min(availWalletBalance, finalAmount);
        finalAmount = Math.max(0, finalAmount - walletDeduct);
    }
    
    document.getElementById('summary_final_payable').innerText = finalAmount.toFixed(2);
}

function updateSubmitButtonLabel() {
    var btn = document.getElementById('btnPlaceMedicineOrder');
    var isOnline = document.querySelector('input[name="payment_method"][value="Online Payment"]').checked;
    if (isOnline) {
        btn.innerHTML = '<i class="fas fa-shield-alt"></i> Proceed to Pay (Online)';
    } else {
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Place Order (COD)';
    }
}

function toggleAddressForm() {
    var form = document.getElementById('addAddressForm');
    form.style.display = (form.style.display === 'none' || !form.style.display) ? 'block' : 'none';
}

function saveAddressViaAjax() {
    var fullName = document.getElementById('new_full_name').value.trim();
    var phone = document.getElementById('new_phone').value.trim();
    var addressLine = document.getElementById('new_address_line').value.trim();
    var city = document.getElementById('new_city').value.trim();
    var state = document.getElementById('new_state').value.trim();
    var pincode = document.getElementById('new_pincode').value.trim();
    
    if (!fullName || !phone || !addressLine || !city || !pincode) {
        alert('Please fill all required address fields (*).');
        return;
    }
    
    fetch('api_address.php?action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            full_name: fullName,
            phone: phone,
            address_line: addressLine,
            city: city,
            state: state,
            pincode: pincode
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('modal_address_id').value = data.address_id;
            
            var container = document.getElementById('savedAddressContainer');
            container.innerHTML = `
                <div id="selectedAddressCard" style="background: rgba(80, 227, 194, 0.08); border: 1px solid rgba(80, 227, 194, 0.3); border-radius: 10px; padding: 0.8rem 1rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.3rem;">
                        <strong style="color: var(--text-primary); font-size: 0.9rem;">${escapeHtml(fullName)} (${escapeHtml(phone)})</strong>
                        <span style="background: var(--secondary-color); color: #000; font-size: 0.68rem; font-weight: bold; padding: 0.15rem 0.5rem; border-radius: 10px;">DEFAULT</span>
                    </div>
                    <p style="color: var(--text-secondary); font-size: 0.82rem; margin: 0; line-height: 1.4;">
                        ${escapeHtml(addressLine)}, ${escapeHtml(city)}, ${escapeHtml(state)} - ${escapeHtml(pincode)}
                    </p>
                </div>
            `;
            document.getElementById('addAddressForm').style.display = 'none';
            document.getElementById('checkoutAlert').style.display = 'none';
        } else {
            alert('Failed to save address: ' + data.message);
        }
    })
    .catch(err => {
        alert('Failed to connect to server to save address.');
    });
}

function validateAndSubmitCheckout(event) {
    var addressId = document.getElementById('modal_address_id').value;
    var newAddressLine = document.getElementById('new_address_line').value.trim();
    
    if (!addressId && !newAddressLine) {
        event.preventDefault();
        var alertBox = document.getElementById('checkoutAlert');
        document.getElementById('checkoutAlertText').innerText = "Please add a delivery address to continue.";
        alertBox.style.display = 'block';
        document.getElementById('addAddressForm').style.display = 'block';
        return false;
    }
    
    // Anti-double-submit protection
    var btn = document.getElementById('btnPlaceMedicineOrder');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Order...';
    return true;
}

function triggerRazorpayCheckout(orderId, amountPaise, medicineName) {
    activeOrderId = orderId;
    var razorpayKey = "<?php echo RAZORPAY_KEY_ID; ?>";
    
    if (!razorpayKey || razorpayKey.indexOf('samplekey') !== -1 || razorpayKey === 'rzp_test_samplekeyid') {
        openDemoPaymentModal(orderId, (amountPaise / 100).toFixed(2), medicineName);
        return;
    }

    try {
        var options = {
            "key": razorpayKey,
            "amount": amountPaise,
            "currency": "INR",
            "name": "MedicalAk Medicine Delivery",
            "description": "Payment for " + medicineName,
            "handler": function (response){
                submitPaymentVerification(orderId, response.razorpay_payment_id || ('pay_test_' + Date.now()), response.razorpay_order_id || '', response.razorpay_signature || '');
            },
            "modal": {
                "ondismiss": function() {
                    alert('Payment window closed. You can click "Pay Again" in your order list anytime.');
                }
            },
            "prefill": {
                "name": "<?php echo htmlspecialchars(addslashes($patient_info['name'])); ?>",
                "phone": "<?php echo htmlspecialchars(addslashes($patient_info['phone'])); ?>"
            },
            "theme": {
                "color": "#4a90e2"
            }
        };
        var rzp1 = new Razorpay(options);
        rzp1.on('payment.failed', function (response){
            openDemoPaymentModal(orderId, (amountPaise / 100).toFixed(2), medicineName);
        });
        rzp1.open();
    } catch (e) {
        openDemoPaymentModal(orderId, (amountPaise / 100).toFixed(2), medicineName);
    }
}

function openDemoPaymentModal(orderId, amountDisplay, medicineName) {
    activeOrderId = orderId;
    document.getElementById('modalMedName').innerText = "Payment for " + medicineName;
    document.getElementById('modalAmount').innerText = "₹" + amountDisplay;
    document.getElementById('paymentGatewayModal').style.display = 'flex';
}

document.getElementById('btnCancelPay').addEventListener('click', function() {
    document.getElementById('paymentGatewayModal').style.display = 'none';
    alert('Payment cancelled. Your order remains Pending. You can click "Pay Again" anytime in your order history.');
});

document.getElementById('btnConfirmPay').addEventListener('click', function() {
    if (!activeOrderId) return;
    document.getElementById('paymentGatewayModal').style.display = 'none';
    
    var simPaymentId = 'pay_online_' + Date.now();
    submitPaymentVerification(activeOrderId, simPaymentId, 'order_online_' + Date.now(), 'simulated_sig_' + Date.now());
});

function submitPaymentVerification(orderId, paymentId, razorpayOrderId, signature) {
    fetch('verify_payment.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            order_id: orderId,
            razorpay_payment_id: paymentId,
            razorpay_order_id: razorpayOrderId,
            razorpay_signature: signature
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'medicines.php?success=1&order_id=' + orderId;
        } else {
            alert('Payment verification error: ' + data.message);
            window.location.href = 'medicines.php';
        }
    })
    .catch(err => {
        alert('Connection error during verification. Please refresh.');
        window.location.href = 'medicines.php';
    });
}

function retryPayment(orderId, totalAmount, medicineName) {
    var amountPaise = Math.round(totalAmount * 100);
    triggerRazorpayCheckout(orderId, amountPaise, medicineName);
}

function escapeHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php include 'includes/footer.php'; ?>
