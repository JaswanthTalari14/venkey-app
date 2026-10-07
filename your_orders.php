<?php
require_once 'config.php';
require_once 'includes/wallet_functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$patient_id = (int)$_SESSION['user_id'];
$success = '';
$error = '';

// Handle Cancel Order, Delete Order, and Reorder Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    
    if ($order_id > 0) {
        $chk_q = $conn->query("SELECT * FROM orders WHERE id = $order_id AND patient_id = $patient_id");
        if ($chk_q && $chk_q->num_rows > 0) {
            $ord_data = $chk_q->fetch_assoc();
            
            if ($action === 'cancel_order') {
                if ($ord_data['status'] === 'cancelled') {
                    $error = "Order #ORD-" . str_pad($order_id, 4, '0', STR_PAD_LEFT) . " is already cancelled.";
                } elseif ($ord_data['status'] === 'delivered') {
                    $error = "Order #ORD-" . str_pad($order_id, 4, '0', STR_PAD_LEFT) . " has already been delivered and cannot be cancelled.";
                } else {
                    $conn->query("UPDATE orders SET status = 'cancelled' WHERE id = $order_id AND patient_id = $patient_id");
                    
                    // Refund to wallet if paid via Wallet, or log Refund Request for online payments
                    if ($ord_data['payment_method'] === 'Wallet' || $ord_data['payment_status'] === 'Paid via Wallet') {
                        add_wallet_transaction($patient_id, 'refund', 'credit', $ord_data['total_amount'], "Refund for cancelled Order #ORD-" . str_pad($order_id, 4, '0', STR_PAD_LEFT), $order_id);
                    } else if (in_array(strtolower($ord_data['payment_status'] ?? ''), ['paid', 'completed']) || !empty($ord_data['gateway_payment_id'])) {
                        $amt_val = floatval($ord_data['total_amount']);
                        $gw_ref = $conn->real_escape_string($ord_data['gateway_payment_id'] ?? '');
                        $conn->query("INSERT INTO refund_requests (patient_id, order_id, transaction_id, amount, reason, status) VALUES ($patient_id, $order_id, '$gw_ref', $amt_val, 'Patient cancelled Order #ORD-" . str_pad($order_id, 4, '0', STR_PAD_LEFT) . "', 'Requested')");
                    }
                    
                    $success = "Order #ORD-" . str_pad($order_id, 4, '0', STR_PAD_LEFT) . " has been cancelled successfully.";
                }
            } elseif ($action === 'reorder_order') {
                // Feature Group 8: Medicine Reorder with Prescription Validation Review
                $items_res = $conn->query("SELECT medicine_id, quantity, price FROM order_items WHERE order_id = $order_id");
                if ($items_res && $items_res->num_rows > 0) {
                    $total_amt = (float)$ord_data['total_amount'];
                    $addr_esc = $conn->real_escape_string($ord_data['address']);
                    
                    $conn->query("INSERT INTO orders (patient_id, total_amount, status, payment_method, payment_status, address) VALUES ($patient_id, $total_amt, 'pending', 'COD', 'Cash on Delivery', '$addr_esc')");
                    $new_order_id = $conn->insert_id;
                    
                    while ($it = $items_res->fetch_assoc()) {
                        $mid = (int)$it['medicine_id'];
                        $qty = (int)$it['quantity'];
                        $prc = (float)$it['price'];
                        $conn->query("INSERT INTO order_items (order_id, medicine_id, quantity, price) VALUES ($new_order_id, $mid, $qty, $prc)");
                    }
                    
                    $success = "Reorder placed successfully as Order #ORD-" . str_pad($new_order_id, 4, '0', STR_PAD_LEFT) . "! Please review your medicines and prescription instructions.";
                }
            } elseif ($action === 'delete_order') {
                $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
                $conn->query("DELETE FROM orders WHERE id = $order_id AND patient_id = $patient_id");
                $success = "Order #ORD-" . str_pad($order_id, 4, '0', STR_PAD_LEFT) . " record deleted successfully.";
            }
        } else {
            $error = "Order not found or unauthorized access.";
        }
    }
}

include 'includes/header.php';

// Server-side Search & Filter Support
$search_param = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_param = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$payment_param = isset($_GET['payment']) ? trim($_GET['payment']) : 'all';

// Fetch all medicine orders belonging strictly to the currently authenticated patient
$orders_query = $conn->query("
    SELECT o.id as order_id, o.total_amount, o.status as order_status, o.payment_method, o.payment_status, 
           o.gateway_payment_id, o.gateway_order_id, o.address, o.created_at, o.cancellation_reason, o.estimated_delivery_time,
           o.payment_confirmed_at, o.packing_at, o.shipped_at, o.out_for_delivery_at, o.delivered_at, o.cancelled_at,
           oi.id as item_id, oi.medicine_id, oi.quantity, oi.price as unit_price,
           m.name as medicine_name, m.image as medicine_image, m.description as medicine_desc
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN medicines m ON oi.medicine_id = m.id
    WHERE o.patient_id = $patient_id AND (o.is_deleted = 0 OR o.is_deleted IS NULL)
    ORDER BY o.created_at DESC, o.id DESC
");

// Group multiple medicines by order_id
$orders_by_id = [];
if ($orders_query) {
    while ($row = $orders_query->fetch_assoc()) {
        $oid = $row['order_id'];
        if (!isset($orders_by_id[$oid])) {
            $orders_by_id[$oid] = [
                'order_id' => $row['order_id'],
                'total_amount' => (float)$row['total_amount'],
                'order_status' => $row['order_status'],
                'payment_method' => $row['payment_method'] ?? 'COD',
                'payment_status' => $row['payment_status'] ?? 'Cash on Delivery',
                'gateway_payment_id' => $row['gateway_payment_id'],
                'gateway_order_id' => $row['gateway_order_id'],
                'address' => $row['address'],
                'created_at' => $row['created_at'],
                'cancellation_reason' => $row['cancellation_reason'] ?? '',
                'estimated_delivery_time' => $row['estimated_delivery_time'] ?? '',
                'payment_confirmed_at' => $row['payment_confirmed_at'] ?? null,
                'packing_at' => $row['packing_at'] ?? null,
                'shipped_at' => $row['shipped_at'] ?? null,
                'out_for_delivery_at' => $row['out_for_delivery_at'] ?? null,
                'delivered_at' => $row['delivered_at'] ?? null,
                'cancelled_at' => $row['cancelled_at'] ?? null,
                'items' => []
            ];
        }
        
        $img_src = 'images/medicines/default.png';
        if (!empty($row['medicine_image']) && file_exists($row['medicine_image'])) {
            $img_src = $row['medicine_image'];
        } else {
            $name_lower = strtolower($row['medicine_name']);
            if (strpos($name_lower, 'paracetamol') !== false) $img_src = 'images/medicines/paracetamol.png';
            elseif (strpos($name_lower, 'amoxicillin') !== false) $img_src = 'images/medicines/amoxicillin.png';
            elseif (strpos($name_lower, 'cetirizine') !== false) $img_src = 'images/medicines/cetirizine.png';
            elseif (strpos($name_lower, 'vitamin') !== false) $img_src = 'images/medicines/vitaminc.png';
        }
        
        $orders_by_id[$oid]['items'][] = [
            'item_id' => $row['item_id'],
            'medicine_id' => $row['medicine_id'],
            'medicine_name' => $row['medicine_name'],
            'medicine_desc' => $row['medicine_desc'],
            'medicine_image' => $img_src,
            'quantity' => (int)$row['quantity'],
            'unit_price' => (float)$row['unit_price'],
            'item_total' => (float)$row['unit_price'] * (int)$row['quantity']
        ];
    }
}
?>

<style>
.btn-delete-card {
    background: rgba(255, 71, 87, 0.1);
    border: 1px solid rgba(255, 71, 87, 0.3);
    color: #ff4757;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}
.btn-delete-card:hover {
    background: #ff4757;
    color: #ffffff;
    transform: scale(1.05);
}
.btn-cancel-order {
    background: rgba(255, 71, 87, 0.1);
    border: 1px solid rgba(255, 71, 87, 0.35);
    color: #ff4757;
    padding: 0.35rem 0.8rem;
    font-size: 0.82rem;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.2s ease;
}
.btn-cancel-order:hover {
    background: #ff4757;
    color: #ffffff;
}
.search-filter-box {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 1.25rem;
    margin-bottom: 2rem;
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
}
.search-input-wrapper {
    position: relative;
    flex: 2;
    min-width: 250px;
}
.search-input-wrapper i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    font-size: 0.95rem;
}
.search-input-wrapper input {
    width: 100%;
    padding-left: 2.5rem !important;
}
.filter-select {
    flex: 1;
    min-width: 150px;
}

/* Order Tracking Timeline CSS */
.order-tracking-section {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--glass-border);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    margin-bottom: 1.2rem;
}
.order-tracking-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    letter-spacing: 0.5px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    padding-bottom: 0.6rem;
}
.order-timeline-container {
    position: relative;
    padding-left: 0.2rem;
}
.timeline-step {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    padding-bottom: 1.2rem;
}
.timeline-step:last-child {
    padding-bottom: 0;
}
.timeline-step:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 11px;
    top: 22px;
    bottom: -2px;
    width: 2px;
    background: rgba(255, 255, 255, 0.12);
    z-index: 1;
}
.timeline-step.completed:not(:last-child)::before {
    background: #2ed573;
}
.timeline-step.current:not(:last-child)::before {
    background: linear-gradient(to bottom, var(--primary-color) 0%, rgba(255, 255, 255, 0.12) 100%);
}
.timeline-node {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    font-weight: bold;
    flex-shrink: 0;
    position: relative;
    z-index: 2;
    transition: var(--transition);
}
.timeline-step.completed .timeline-node {
    background: #2ed573;
    color: #0a0a0a;
    box-shadow: 0 0 10px rgba(46, 213, 115, 0.3);
}
.timeline-step.current .timeline-node {
    background: var(--primary-color);
    color: #ffffff;
    box-shadow: 0 0 0 4px rgba(74, 144, 226, 0.22), 0 0 12px rgba(74, 144, 226, 0.4);
    animation: trackingPulse 2s infinite;
}
@keyframes trackingPulse {
    0% { box-shadow: 0 0 0 0 rgba(74, 144, 226, 0.5); }
    70% { box-shadow: 0 0 0 6px rgba(74, 144, 226, 0); }
    100% { box-shadow: 0 0 0 0 rgba(74, 144, 226, 0); }
}
.timeline-step.upcoming .timeline-node {
    background: transparent;
    border: 2px solid rgba(255, 255, 255, 0.2);
    color: transparent;
}
.timeline-step.cancelled .timeline-node {
    background: #ff4757;
    color: #ffffff;
    box-shadow: 0 0 10px rgba(255, 71, 87, 0.3);
}
.timeline-content {
    flex: 1;
    min-width: 0;
}
.timeline-label {
    font-size: 0.88rem;
    font-weight: 600;
    line-height: 1.3;
}
.timeline-step.completed .timeline-label {
    color: var(--text-primary);
}
.timeline-step.current .timeline-label {
    color: var(--primary-color);
    font-weight: 700;
}
.timeline-step.upcoming .timeline-label {
    color: var(--text-secondary);
    opacity: 0.65;
}
.timeline-step.cancelled .timeline-label {
    color: #ff4757;
}
.timeline-time {
    font-size: 0.78rem;
    color: var(--text-secondary);
    margin-top: 0.15rem;
    line-height: 1.3;
}
.timeline-step.current .timeline-time {
    color: var(--accent);
    font-weight: 600;
}
</style>

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
            <li><a href="book_consult.php"><i class="fas fa-calendar-check"></i> Consultations</a></li>
            <li><a href="medicines.php"><i class="fas fa-pills"></i> Order Medicines</a></li>
            <li><a href="your_orders.php" class="active"><i class="fas fa-boxes"></i> Your Orders</a></li>
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
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <h2>Your Medicine Orders</h2>
                <p style="color: var(--text-secondary); margin-top: 0.3rem;">Track and view complete history of all your medicine delivery orders.</p>
            </div>
            <a href="medicines.php" class="btn btn-primary" style="padding: 0.6rem 1.2rem;">
                <i class="fas fa-plus"></i> Order New Medicines
            </a>
        </div>

        <?php if($success): ?>
            <p style="color: #2ed573; margin-bottom: 1.5rem; padding: 1rem; background: rgba(46, 213, 115, 0.1); border-radius: 10px; border-left: 4px solid #2ed573;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </p>
        <?php endif; ?>
        
        <?php if($error): ?>
            <p style="color: #ff4757; margin-bottom: 1.5rem; padding: 1rem; background: rgba(255, 71, 87, 0.1); border-radius: 10px; border-left: 4px solid #ff4757;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </p>
        <?php endif; ?>

        <!-- SEARCH AND FILTER TOOLBAR -->
        <?php if (!empty($orders_by_id)): ?>
            <div class="search-filter-box glass-panel">
                <div class="search-input-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="orderSearchInput" class="form-control" placeholder="Search by Order ID (#ORD-0001), Medicine Name, Address..." value="<?php echo htmlspecialchars($search_param); ?>" onkeyup="filterOrders()" onchange="filterOrders()">
                </div>

                <div class="filter-select">
                    <select id="orderStatusFilter" class="form-control" onchange="filterOrders()">
                        <option value="all" <?php echo $status_param === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?php echo $status_param === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="shipped" <?php echo $status_param === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="delivered" <?php echo $status_param === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo $status_param === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <div class="filter-select">
                    <select id="paymentFilter" class="form-control" onchange="filterOrders()">
                        <option value="all" <?php echo $payment_param === 'all' ? 'selected' : ''; ?>>All Payments</option>
                        <option value="paid" <?php echo $payment_param === 'paid' ? 'selected' : ''; ?>>Paid (Online / Wallet)</option>
                        <option value="cod" <?php echo $payment_param === 'cod' ? 'selected' : ''; ?>>Cash on Delivery</option>
                        <option value="pending_pay" <?php echo $payment_param === 'pending_pay' ? 'selected' : ''; ?>>Pending Payment</option>
                        <option value="failed" <?php echo $payment_param === 'failed' ? 'selected' : ''; ?>>Failed Payment</option>
                    </select>
                </div>

                <div class="filter-select">
                    <select id="dateFilter" class="form-control" onchange="filterOrders()">
                        <option value="all">All Time</option>
                        <option value="30days">Last 30 Days</option>
                        <option value="6months">Last 6 Months</option>
                        <option value="year">Last 1 Year</option>
                    </select>
                </div>

                <button type="button" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="resetOrderFilters()">
                    <i class="fas fa-undo"></i> Reset Filters
                </button>
            </div>

            <!-- Orders Counter Badge -->
            <div style="margin-bottom: 1rem; font-size: 0.88rem; color: var(--text-secondary); display: flex; justify-content: space-between; align-items: center;">
                <span>Showing <strong id="visibleOrderCount" style="color: var(--text-primary);"><?php echo count($orders_by_id); ?></strong> of <strong style="color: var(--primary-color);"><?php echo count($orders_by_id); ?></strong> medicine orders</span>
            </div>

            <!-- Order Cards List -->
            <div id="ordersCardsList" style="display: flex; flex-direction: column; gap: 1.5rem;">
                <?php foreach ($orders_by_id as $order): ?>
                    <?php
                        $ord_id = $order['order_id'];
                        $formatted_id = "#ORD-" . str_pad($ord_id, 4, '0', STR_PAD_LEFT);
                        $order_date = date('M d, Y', strtotime($order['created_at']));
                        $order_time = date('h:i A', strtotime($order['created_at']));
                        
                        // Combine medicine names for search indexing
                        $med_names = [];
                        foreach ($order['items'] as $it) {
                            $med_names[] = strtolower($it['medicine_name']);
                        }
                        $search_index = strtolower($formatted_id . ' ' . implode(' ', $med_names) . ' ' . $order['address'] . ' ' . $order['payment_method'] . ' ' . $order['payment_status'] . ' ' . $order['order_status']);
                        
                        // Status Colors
                        $status_color = 'var(--text-primary)';
                        if ($order['order_status'] === 'pending') $status_color = 'var(--accent)';
                        elseif ($order['order_status'] === 'shipped') $status_color = '#3498db';
                        elseif ($order['order_status'] === 'delivered') $status_color = '#2ed573';
                        elseif ($order['order_status'] === 'cancelled') $status_color = '#ff4757';
                        
                        // Payment Status Colors
                        $pay_status = $order['payment_status'];
                        $pay_color = '#f5a623';
                        if (in_array($pay_status, ['Paid', 'Paid via Wallet'])) $pay_color = '#2ed573';
                        elseif ($pay_status === 'Failed') $pay_color = '#ff4757';
                        elseif ($pay_status === 'Cash on Delivery') $pay_color = '#3498db';
                    ?>

                    <div class="glass-panel order-card-wrapper" 
                         data-order-id="<?php echo $ord_id; ?>"
                         data-search="<?php echo htmlspecialchars($search_index); ?>"
                         data-status="<?php echo strtolower($order['order_status']); ?>"
                         data-payment-method="<?php echo strtolower($order['payment_method']); ?>"
                         data-payment-status="<?php echo strtolower($pay_status); ?>"
                         data-timestamp="<?php echo strtotime($order['created_at']); ?>"
                         style="padding: 1.5rem; transition: var(--transition);">
                         
                        <!-- Order Card Header -->
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--glass-border); padding-bottom: 1rem; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.8rem;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <strong style="font-size: 1.1rem; color: var(--primary-color);"><?php echo $formatted_id; ?></strong>
                                    <span style="color: <?php echo $status_color; ?>; font-weight: bold; text-transform: capitalize; background: rgba(255,255,255,0.06); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; border: 1px solid <?php echo $status_color; ?>;">
                                        <i class="fas fa-circle" style="font-size: 0.5rem; vertical-align: middle; margin-right: 0.3rem;"></i><?php echo htmlspecialchars($order['order_status']); ?>
                                    </span>
                                </div>
                                <div style="font-size: 0.82rem; color: var(--text-secondary); margin-top: 0.3rem;">
                                    <i class="fas fa-calendar-alt"></i> Placed on <?php echo $order_date; ?> at <?php echo $order_time; ?>
                                </div>
                                <?php if (!empty($order['estimated_delivery_time']) && !in_array(strtolower($order['order_status']), ['delivered', 'cancelled'])): ?>
                                    <div style="font-size: 0.82rem; color: var(--primary-color); font-weight: 600; margin-top: 0.3rem;">
                                        <i class="fas fa-shipping-fast"></i> Est. Delivery: <?php echo htmlspecialchars($order['estimated_delivery_time']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (strtolower($order['order_status']) === 'cancelled' && !empty($order['cancellation_reason'])): ?>
                                    <div style="margin-top: 0.4rem; font-size: 0.8rem; color: #ff4757; background: rgba(255, 71, 87, 0.1); border-left: 3px solid #ff4757; padding: 0.35rem 0.6rem; border-radius: 4px; overflow-wrap: anywhere; word-break: break-word;">
                                        <strong>Cancellation Reason:</strong> <?php echo htmlspecialchars($order['cancellation_reason']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div style="text-align: right; display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap;">
                                <div>
                                    <div style="font-size: 0.78rem; color: var(--text-secondary);">Total Payable</div>
                                    <div style="font-size: 1.3rem; font-weight: 800; color: var(--secondary-color);">₹<?php echo number_format($order['total_amount'], 2); ?></div>
                                </div>

                                <button type="button" class="btn btn-outline" style="padding: 0.4rem 0.9rem; font-size: 0.85rem;" onclick="openOrderDetailsModal(<?php echo $ord_id; ?>)">
                                    <i class="fas fa-eye"></i> View Details
                                </button>

                                <!-- Delete Order Card Icon Button -->
                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete <?php echo $formatted_id; ?> record?');">
                                    <input type="hidden" name="action" value="delete_order">
                                    <input type="hidden" name="order_id" value="<?php echo $ord_id; ?>">
                                    <button type="submit" class="btn-delete-card" title="Delete Order Record">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Order Items List -->
                        <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1rem;">
                            <?php foreach ($order['items'] as $item): ?>
                                <div style="display: flex; align-items: center; gap: 1rem; background: rgba(0,0,0,0.2); padding: 0.75rem 1rem; border-radius: 10px; border: 1px solid rgba(255,255,255,0.03);">
                                    <div style="width: 48px; height: 48px; border-radius: 8px; overflow: hidden; background: rgba(255,255,255,0.05); flex-shrink: 0;">
                                        <img src="<?php echo htmlspecialchars($item['medicine_image']); ?>" alt="<?php echo htmlspecialchars($item['medicine_name']); ?>" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: var(--text-primary); font-size: 0.92rem;"><?php echo htmlspecialchars($item['medicine_name']); ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo htmlspecialchars($item['medicine_desc']); ?></div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 0.88rem; color: var(--text-primary); font-weight: 500;">Qty: <?php echo $item['quantity']; ?> × ₹<?php echo number_format($item['unit_price'], 2); ?></div>
                                        <div style="font-size: 0.9rem; color: var(--secondary-color); font-weight: bold;">₹<?php echo number_format($item['item_total'], 2); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Order Card Footer Info -->
                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--glass-border); padding-top: 0.8rem; font-size: 0.85rem; flex-wrap: wrap; gap: 0.8rem;">
                            <div style="color: var(--text-secondary); max-width: 60%;">
                                <i class="fas fa-map-marker-alt" style="color: var(--primary-color);"></i> <strong>Delivery Address:</strong> 
                                <span style="color: var(--text-primary);"><?php echo htmlspecialchars($order['address']); ?></span>
                            </div>

                            <div style="display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap;">
                                <div>
                                    <span style="color: var(--text-secondary);">Payment:</span> 
                                    <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($order['payment_method']); ?></strong> 
                                    (<span style="color: <?php echo $pay_color; ?>; font-weight: bold;"><?php echo htmlspecialchars($pay_status); ?></span>)
                                </div>

                                <!-- Cancel Order Button -->
                                <?php if (!in_array($order['order_status'], ['cancelled', 'delivered'])): ?>
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel Order <?php echo $formatted_id; ?>?');">
                                        <input type="hidden" name="action" value="cancel_order">
                                        <input type="hidden" name="order_id" value="<?php echo $ord_id; ?>">
                                        <button type="submit" class="btn-cancel-order">
                                            <i class="fas fa-ban"></i> Cancel Order
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($pay_status === 'Pending' && $order['payment_method'] === 'Online Payment' && $order['order_status'] !== 'cancelled'): ?>
                                    <button type="button" class="btn btn-primary" style="padding: 0.3rem 0.8rem; font-size: 0.8rem;" onclick="retryPayment(<?php echo $ord_id; ?>, <?php echo $order['total_amount']; ?>, '<?php echo htmlspecialchars(addslashes($order['items'][0]['medicine_name'] ?? 'Medicine Order')); ?>')">
                                        <i class="fas fa-redo"></i> Pay Again
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Empty Search Results Message -->
            <div id="noSearchMatchNotice" class="glass-panel" style="display: none; text-align: center; padding: 3rem 2rem; border-radius: 20px; margin-top: 1rem;">
                <i class="fas fa-search" style="font-size: 2.2rem; color: var(--text-secondary); margin-bottom: 1rem;"></i>
                <h4 style="color: var(--text-primary); margin-bottom: 0.5rem; font-size: 1.2rem;">No orders match your search and filter criteria.</h4>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1.2rem;">Try changing your keywords or resetting status/payment filters.</p>
                <button type="button" class="btn btn-outline" onclick="resetOrderFilters()">
                    <i class="fas fa-undo"></i> Reset Filters
                </button>
            </div>

        <?php else: ?>
            <!-- Empty State -->
            <div class="glass-panel" style="text-align: center; padding: 4rem 2rem; border-radius: 20px;">
                <div style="width: 80px; height: 80px; background: rgba(74, 144, 226, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto;">
                    <i class="fas fa-box-open" style="font-size: 2.5rem; color: var(--primary-color);"></i>
                </div>
                <h3 style="color: var(--text-primary); margin-bottom: 0.5rem; font-size: 1.4rem;">No medicine orders yet.</h3>
                <p style="color: var(--text-secondary); font-size: 0.95rem; max-width: 420px; margin: 0 auto 1.8rem auto;">You haven't placed any medicine delivery orders so far. Order prescribed medicines delivered directly to your doorstep.</p>
                <a href="medicines.php" class="btn btn-primary" style="padding: 0.75rem 1.8rem; font-size: 1rem;">
                    <i class="fas fa-pills"></i> Order Medicines
                </a>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- ========================================================= -->
<!-- ORDER DETAILS MODAL -->
<!-- ========================================================= -->
<div id="orderDetailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 99999; align-items: center; justify-content: center; padding: 1rem; overflow-y: auto;">
    <div class="glass-panel" style="background: var(--darker-bg); border: 1px solid var(--glass-border); width: 100%; max-width: 580px; padding: 1.8rem; border-radius: 20px; box-shadow: 0 25px 60px rgba(0,0,0,0.7); position: relative; max-height: 90vh; overflow-y: auto;">
        
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.8rem;">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <i class="fas fa-receipt" style="color: var(--primary-color); font-size: 1.4rem;"></i>
                <h3 style="color: var(--text-primary); margin: 0; font-size: 1.25rem;" id="modalOrderTitle">Order Details</h3>
            </div>
            <button type="button" onclick="closeOrderDetailsModal()" style="background: transparent; border: none; color: var(--text-secondary); font-size: 1.3rem; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div id="orderDetailsContent">
            <!-- Dynamic Content loaded via JS -->
        </div>
    </div>
</div>

<!-- Payment Modal Dialog for Online Retry Payment -->
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

<script>
var ordersData = <?php echo json_encode($orders_by_id); ?>;
var activeOrderId = null;

// REAL-TIME INSTANT CLIENT-SIDE SEARCH AND FILTER LOGIC
function filterOrders() {
    var searchKey = document.getElementById('orderSearchInput').value.toLowerCase().trim();
    var statusFilter = document.getElementById('orderStatusFilter').value;
    var paymentFilter = document.getElementById('paymentFilter').value;
    var dateFilter = document.getElementById('dateFilter').value;
    
    var cards = document.querySelectorAll('.order-card-wrapper');
    var visibleCount = 0;
    var nowSec = Math.floor(Date.now() / 1000);
    
    cards.forEach(function(card) {
        var searchData = card.getAttribute('data-search') || '';
        var statusData = card.getAttribute('data-status') || '';
        var payMethodData = card.getAttribute('data-payment-method') || '';
        var payStatusData = card.getAttribute('data-payment-status') || '';
        var timestamp = parseInt(card.getAttribute('data-timestamp')) || 0;
        
        var matchesSearch = !searchKey || searchData.indexOf(searchKey) !== -1;
        var matchesStatus = (statusFilter === 'all') || (statusData === statusFilter);
        
        var matchesPayment = true;
        if (paymentFilter === 'paid') {
            matchesPayment = (payStatusData === 'paid' || payStatusData === 'paid via wallet');
        } else if (paymentFilter === 'cod') {
            matchesPayment = (payMethodData === 'cod' || payStatusData === 'cash on delivery');
        } else if (paymentFilter === 'pending_pay') {
            matchesPayment = (payStatusData === 'pending');
        } else if (paymentFilter === 'failed') {
            matchesPayment = (payStatusData === 'failed');
        }

        var matchesDate = true;
        if (dateFilter === '30days') {
            matchesDate = (nowSec - timestamp) <= (30 * 86400);
        } else if (dateFilter === '6months') {
            matchesDate = (nowSec - timestamp) <= (180 * 86400);
        } else if (dateFilter === 'year') {
            matchesDate = (nowSec - timestamp) <= (365 * 86400);
        }

        if (matchesSearch && matchesStatus && matchesPayment && matchesDate) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    var countBadge = document.getElementById('visibleOrderCount');
    if (countBadge) countBadge.innerText = visibleCount;
    
    var notice = document.getElementById('noSearchMatchNotice');
    if (notice) {
        notice.style.display = (visibleCount === 0 && cards.length > 0) ? 'block' : 'none';
    }
}

function resetOrderFilters() {
    document.getElementById('orderSearchInput').value = '';
    document.getElementById('orderStatusFilter').value = 'all';
    document.getElementById('paymentFilter').value = 'all';
    document.getElementById('dateFilter').value = 'all';
    filterOrders();
}

document.addEventListener("DOMContentLoaded", function() {
    filterOrders();
});

function formatTrackingDate(dateStr) {
    if (!dateStr || dateStr === '0000-00-00 00:00:00' || dateStr === 'null' || dateStr === null) return '';
    try {
        var d = new Date(String(dateStr).replace(/-/g, "/"));
        if (isNaN(d.getTime())) return '';
        var day = d.getDate();
        var monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        var month = monthNames[d.getMonth()];
        var hours = d.getHours();
        var minutes = d.getMinutes();
        var ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        minutes = minutes < 10 ? '0' + minutes : minutes;
        return day + ' ' + month + ', ' + hours + ':' + minutes + ' ' + ampm;
    } catch(e) {
        return '';
    }
}

function buildOrderTimelineHtml(order) {
    var rawStatus = String(order.order_status || 'pending').toLowerCase().trim();
    var payStatus = String(order.payment_status || '').toLowerCase().trim();
    var payMethod = String(order.payment_method || 'cod').toLowerCase().trim();

    var isCancelled = (rawStatus === 'cancelled' || rawStatus === 'rejected');

    var createdTime = formatTrackingDate(order.created_at);
    var payConfirmedTime = formatTrackingDate(order.payment_confirmed_at) || createdTime;
    var packingTime = formatTrackingDate(order.packing_at) || (inArray(rawStatus, ['shipped', 'out for delivery', 'delivered']) ? createdTime : '');
    var shippedTime = formatTrackingDate(order.shipped_at) || (inArray(rawStatus, ['out for delivery', 'delivered']) ? createdTime : '');
    var outDeliveryTime = formatTrackingDate(order.out_for_delivery_at) || (rawStatus === 'delivered' ? createdTime : '');
    var deliveredTime = formatTrackingDate(order.delivered_at);
    var cancelledTime = formatTrackingDate(order.cancelled_at) || createdTime;

    var isPayConfirmed = (payStatus === 'paid' || payStatus === 'paid via wallet' || payStatus === 'completed' || payStatus === 'cash on delivery' || payMethod === 'cod');

    if (isCancelled) {
        var cancelSteps = [
            { title: 'Order Placed', status: 'completed', icon: '✓', time: createdTime },
            { title: 'Payment Confirmed', status: isPayConfirmed ? 'completed' : 'upcoming', icon: isPayConfirmed ? '✓' : '○', time: isPayConfirmed ? payConfirmedTime : '' },
            { title: rawStatus === 'rejected' ? 'Order Rejected' : 'Order Cancelled', status: 'cancelled', icon: '✕', time: cancelledTime }
        ];
        return renderTimelineSteps(cancelSteps);
    }

    var currentStepIdx = 0;
    if (rawStatus === 'delivered') {
        currentStepIdx = 5;
    } else if (rawStatus === 'out for delivery') {
        currentStepIdx = 4;
    } else if (rawStatus === 'shipped') {
        currentStepIdx = 3;
    } else if (inArray(rawStatus, ['packing', 'packed', 'processing'])) {
        currentStepIdx = 2;
    } else if (rawStatus === 'pending') {
        if (isPayConfirmed) {
            currentStepIdx = 2;
        } else {
            currentStepIdx = 1;
        }
    }

    var stepConfigs = [
        { name: 'Order Placed', time: createdTime },
        { name: 'Payment Confirmed', time: payConfirmedTime },
        { name: 'Packing', time: packingTime },
        { name: 'Shipped', time: shippedTime },
        { name: 'Out for Delivery', time: outDeliveryTime },
        { name: 'Delivered', time: deliveredTime }
    ];

    var steps = stepConfigs.map(function(stepConf, idx) {
        var state = 'upcoming';
        var icon = '○';
        var timeStr = '';

        if (idx < currentStepIdx) {
            state = 'completed';
            icon = '✓';
            timeStr = stepConf.time || createdTime;
        } else if (idx === currentStepIdx) {
            if (currentStepIdx === 5 && rawStatus === 'delivered') {
                state = 'completed';
                icon = '✓';
                timeStr = stepConf.time || createdTime;
            } else {
                state = 'current';
                icon = '●';
                timeStr = 'Waiting';
            }
        } else {
            state = 'upcoming';
            icon = '○';
            timeStr = '';
        }

        return {
            title: stepConf.name,
            status: state,
            icon: icon,
            time: timeStr
        };
    });

    return renderTimelineSteps(steps);
}

function renderTimelineSteps(steps) {
    var html = `
        <div class="order-tracking-section">
            <div class="order-tracking-title">
                <span style="font-size: 1.1rem; color: var(--primary-color);">📦</span> ORDER TRACKING
            </div>
            <div class="order-timeline-container">
    `;

    steps.forEach(function(step) {
        html += `
            <div class="timeline-step ${step.status}">
                <div class="timeline-node">${step.icon}</div>
                <div class="timeline-content">
                    <div class="timeline-label">${step.title}</div>
                    ${step.time ? `<div class="timeline-time">${step.time}</div>` : ''}
                </div>
            </div>
        `;
    });

    html += `
            </div>
        </div>
    `;

    return html;
}

function inArray(needle, haystack) {
    return haystack.indexOf(needle) !== -1;
}

function openOrderDetailsModal(orderId) {
    var order = ordersData[orderId];
    if (!order) return;
    
    document.getElementById('modalOrderTitle').innerText = "Order #ORD-" + String(orderId).padStart(4, '0');
    
    var itemsHtml = '';
    order.items.forEach(function(item) {
        itemsHtml += `
            <div style="display: flex; align-items: center; gap: 0.8rem; margin-bottom: 0.6rem; border-bottom: 1px solid rgba(255,255,255,0.04); padding-bottom: 0.6rem;">
                <img src="${item.medicine_image}" alt="${escapeHtml(item.medicine_name)}" style="width: 42px; height: 42px; object-fit: cover; border-radius: 6px; background: rgba(0,0,0,0.3);">
                <div style="flex: 1;">
                    <div style="font-weight: bold; color: var(--text-primary); font-size: 0.88rem;">${escapeHtml(item.medicine_name)}</div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary);">Qty: ${item.quantity} × ₹${item.unit_price.toFixed(2)}</div>
                </div>
                <div style="font-weight: bold; color: var(--secondary-color); font-size: 0.9rem;">₹${item.item_total.toFixed(2)}</div>
            </div>
        `;
    });
    
    var txHtml = order.gateway_payment_id ? `
        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 0.4rem; margin-bottom: 0.4rem; font-size: 0.85rem;">
            <span style="color: var(--text-secondary);">Transaction ID:</span>
            <strong style="color: var(--primary-color);">${escapeHtml(order.gateway_payment_id)}</strong>
        </div>
    ` : '';

    var html = `
        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 12px; padding: 1rem; margin-bottom: 1.2rem;">
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 0.4rem; margin-bottom: 0.4rem; font-size: 0.85rem;">
                <span style="color: var(--text-secondary);">Order Date:</span>
                <strong style="color: var(--text-primary);">${new Date(order.created_at).toLocaleString()}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 0.4rem; margin-bottom: 0.4rem; font-size: 0.85rem;">
                <span style="color: var(--text-secondary);">Order Status:</span>
                <strong style="color: var(--primary-color); text-transform: capitalize;">${escapeHtml(order.order_status)}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 0.4rem; margin-bottom: 0.4rem; font-size: 0.85rem;">
                <span style="color: var(--text-secondary);">Payment Method:</span>
                <strong style="color: var(--text-primary);">${escapeHtml(order.payment_method)}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 0.4rem; margin-bottom: 0.4rem; font-size: 0.85rem;">
                <span style="color: var(--text-secondary);">Payment Status:</span>
                <strong style="color: #2ed573;">${escapeHtml(order.payment_status)}</strong>
            </div>
            ${txHtml}
        </div>

        ${buildOrderTimelineHtml(order)}

        <div style="margin-bottom: 1.2rem;">
            <div style="font-size: 0.85rem; font-weight: bold; color: var(--text-secondary); margin-bottom: 0.6rem;">Ordered Items:</div>
            ${itemsHtml}
        </div>

        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 12px; padding: 1rem; margin-bottom: 1rem;">
            <div style="font-size: 0.85rem; font-weight: bold; color: var(--text-secondary); margin-bottom: 0.4rem;">Delivery Address:</div>
            <div style="font-size: 0.85rem; color: var(--text-primary); line-height: 1.4;">
                <i class="fas fa-map-marker-alt" style="color: var(--primary-color);"></i> ${escapeHtml(order.address)}
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--glass-border); padding-top: 0.8rem;">
            <span style="font-size: 1rem; font-weight: bold; color: var(--text-primary);">Final Payable Amount:</span>
            <span style="font-size: 1.4rem; font-weight: 800; color: var(--secondary-color);">₹${order.total_amount.toFixed(2)}</span>
        </div>
    `;
    
    document.getElementById('orderDetailsContent').innerHTML = html;
    document.getElementById('orderDetailsModal').style.display = 'flex';
}

function closeOrderDetailsModal() {
    document.getElementById('orderDetailsModal').style.display = 'none';
}

function retryPayment(orderId, totalAmount, medicineName) {
    var amountPaise = Math.round(totalAmount * 100);
    triggerRazorpayCheckout(orderId, amountPaise, medicineName);
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
                    alert('Payment window closed. You can retry payment anytime.');
                }
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
            window.location.href = 'your_orders.php';
        } else {
            alert('Payment verification error: ' + data.message);
            window.location.href = 'your_orders.php';
        }
    })
    .catch(err => {
        alert('Connection error during verification. Please refresh.');
        window.location.href = 'your_orders.php';
    });
}

function escapeHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php include 'includes/footer.php'; ?>
