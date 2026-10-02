<?php
require_once 'config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

function get_order_status_style($status) {
    $st = strtolower(trim($status ?? ''));
    switch ($st) {
        case 'delivered':
            return ['color' => '#2ed573', 'bg' => 'rgba(46, 213, 115, 0.12)', 'border' => '#2ed573'];
        case 'shipped':
            return ['color' => '#4a90e2', 'bg' => 'rgba(74, 144, 226, 0.12)', 'border' => '#4a90e2'];
        case 'packing':
        case 'packed':
            return ['color' => '#a55eea', 'bg' => 'rgba(165, 94, 234, 0.12)', 'border' => '#a55eea'];
        case 'processing':
            return ['color' => '#3498db', 'bg' => 'rgba(52, 152, 219, 0.12)', 'border' => '#3498db'];
        case 'out for delivery':
            return ['color' => '#fa8231', 'bg' => 'rgba(250, 130, 49, 0.12)', 'border' => '#fa8231'];
        case 'rejected':
        case 'cancelled':
        case 'failed':
            return ['color' => '#ff4757', 'bg' => 'rgba(255, 71, 87, 0.12)', 'border' => '#ff4757'];
        case 'pending':
        default:
            return ['color' => '#f5a623', 'bg' => 'rgba(245, 166, 35, 0.12)', 'border' => '#f5a623'];
    }
}

// Fetch all non-deleted orders with patient details and medicine items
$orders_query = $conn->query("
    SELECT o.id as order_id, o.patient_id, o.total_amount, o.status as order_status, 
           o.payment_method, o.payment_status, o.gateway_payment_id, o.gateway_order_id, 
           o.address, o.created_at, o.cancellation_reason, o.estimated_delivery_time,
           p.name as patient_name, p.phone as patient_phone, p.email as patient_email,
           oi.id as item_id, oi.medicine_id, oi.quantity, oi.price as unit_price,
           m.name as medicine_name, m.image as medicine_image
    FROM orders o
    JOIN users p ON o.patient_id = p.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN medicines m ON oi.medicine_id = m.id
    WHERE (o.is_deleted = 0 OR o.is_deleted IS NULL)
    ORDER BY o.created_at DESC, o.id DESC
");

$orders_by_id = [];
if ($orders_query) {
    while ($row = $orders_query->fetch_assoc()) {
        $oid = $row['order_id'];
        if (!isset($orders_by_id[$oid])) {
            $orders_by_id[$oid] = [
                'order_id' => $row['order_id'],
                'patient_id' => $row['patient_id'],
                'patient_name' => $row['patient_name'],
                'patient_phone' => $row['patient_phone'],
                'patient_email' => $row['patient_email'],
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
                'items' => []
            ];
        }

        if (!empty($row['medicine_id'])) {
            $orders_by_id[$oid]['items'][] = [
                'item_id' => $row['item_id'],
                'medicine_id' => $row['medicine_id'],
                'medicine_name' => $row['medicine_name'],
                'medicine_image' => $row['medicine_image'],
                'quantity' => (int)$row['quantity'],
                'unit_price' => (float)$row['unit_price'],
                'item_total' => (float)$row['unit_price'] * (int)$row['quantity']
            ];
        }
    }
}
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Admin Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Admin Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Admin Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php"><i class="fas fa-chart-pie"></i> Overview</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="admin_verify.php"><i class="fas fa-user-md"></i> Verify Doctors & RMPs</a></li>
            <li><a href="admin_bookings.php"><i class="fas fa-calendar-check"></i> All Bookings</a></li>
            <li><a href="admin_orders_management.php" class="active"><i class="fas fa-boxes"></i> Order Management</a></li>
            <li><a href="admin_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="admin_referrals.php"><i class="fas fa-gift"></i> Referral Management</a></li>
            <li><a href="admin_referral_settings.php"><i class="fas fa-sliders-h"></i> Referral Settings</a></li>
            <li><a href="admin_wallets.php"><i class="fas fa-wallet"></i> Wallet Management</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback & Complaints</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <div style="margin-bottom: 1.5rem;">
            <h2>Admin Order Management</h2>
            <p style="color: var(--text-secondary); margin-top: 0.2rem;">View, track, update delivery estimates, cancel with mandatory reason, and manage all patient medicine orders.</p>
        </div>

        <!-- Search and Status Filter Bar -->
        <div class="glass-panel" style="padding: 1.25rem; margin-bottom: 1.5rem; border-radius: 16px;">
            <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; justify-content: space-between;">
                <div style="flex: 1; min-width: 240px; position: relative;">
                    <input type="text" id="adminOrderSearch" class="form-control" placeholder="Search by Order ID (#ORD-0001), Patient, Phone, Address, Medicine..." style="padding-left: 2.4rem;" onkeyup="filterAdminOrders()">
                    <i class="fas fa-search" style="position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                </div>
                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                    <select id="adminStatusFilter" class="form-control" style="width: auto; min-width: 150px;" onchange="filterAdminOrders()">
                        <option value="all">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="packing">Packing</option>
                        <option value="shipped">Shipped</option>
                        <option value="out for delivery">Out for Delivery</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <button type="button" class="btn btn-outline" onclick="resetAdminFilters()" style="padding: 0.55rem 1rem; font-size: 0.85rem;">
                        <i class="fas fa-redo-alt"></i> Reset
                    </button>
                </div>
            </div>
            <div style="margin-top: 0.8rem; font-size: 0.85rem; color: var(--text-secondary); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <span>Showing <strong id="visibleCount" style="color: var(--text-primary);"><?php echo count($orders_by_id); ?></strong> of <strong style="color: var(--primary-color);"><?php echo count($orders_by_id); ?></strong> total orders</span>
            </div>
        </div>

        <!-- Orders Cards Container -->
        <div id="adminOrdersContainer" style="display: flex; flex-direction: column; gap: 1.5rem;">
            <?php if (!empty($orders_by_id)): ?>
                <?php foreach ($orders_by_id as $order): ?>
                    <?php
                        $ord_id = $order['order_id'];
                        $formatted_id = "#ORD-" . str_pad($ord_id, 4, '0', STR_PAD_LEFT);
                        $order_date = date('M d, Y', strtotime($order['created_at']));
                        $order_time = date('h:i A', strtotime($order['created_at']));

                        $med_names = [];
                        foreach ($order['items'] as $it) {
                            $med_names[] = strtolower($it['medicine_name']);
                        }
                        $search_index = strtolower($formatted_id . ' ' . $order['patient_name'] . ' ' . $order['patient_phone'] . ' ' . implode(' ', $med_names) . ' ' . $order['address'] . ' ' . $order['payment_method'] . ' ' . $order['payment_status'] . ' ' . $order['order_status']);

                        // Status Color Mapping
                        $st_style = get_order_status_style($order['order_status']);
                        $status_color = $st_style['color'];
                        $status_bg = $st_style['bg'];
                        $status_border = $st_style['border'];

                        // Payment Status Colors
                        $pay_status = $order['payment_status'];
                        $pay_color = '#f5a623';
                        if (in_array($pay_status, ['Paid', 'Paid via Wallet'])) $pay_color = '#2ed573';
                        elseif ($pay_status === 'Failed') $pay_color = '#ff4757';
                        elseif ($pay_status === 'Cash on Delivery') $pay_color = '#3498db';
                    ?>

                    <div class="glass-panel admin-order-card" 
                         id="order-card-<?php echo $ord_id; ?>"
                         data-order-id="<?php echo $ord_id; ?>"
                         data-search="<?php echo htmlspecialchars($search_index); ?>"
                         data-status="<?php echo strtolower($order['order_status']); ?>"
                         style="padding: 1.5rem; border-radius: 18px; transition: var(--transition);">

                        <!-- Card Header -->
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid var(--glass-border); padding-bottom: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.8rem;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                                    <strong style="font-size: 1.15rem; color: var(--primary-color); font-weight: 800;"><?php echo $formatted_id; ?></strong>
                                    <span id="card-status-badge-<?php echo $ord_id; ?>" style="color: <?php echo $status_color; ?>; background: <?php echo $status_bg; ?>; font-weight: 700; text-transform: capitalize; padding: 0.3rem 0.85rem; border-radius: 20px; font-size: 0.82rem; border: 1px solid <?php echo $status_border; ?>; display: inline-flex; align-items: center; gap: 0.35rem;">
                                        <i class="fas fa-circle" style="font-size: 0.5rem;"></i><span class="status-text"><?php echo htmlspecialchars($order['order_status']); ?></span>
                                    </span>
                                </div>
                                <div style="font-size: 0.82rem; color: var(--text-secondary); margin-top: 0.35rem;">
                                    <i class="fas fa-clock" style="margin-right: 3px;"></i> Ordered on <?php echo $order_date; ?> at <?php echo $order_time; ?>
                                </div>
                            </div>

                            <div style="text-align: right; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                                <div>
                                    <div style="font-size: 0.78rem; color: var(--text-secondary);">Total Amount</div>
                                    <div style="font-size: 1.35rem; font-weight: 800; color: var(--secondary-color);">₹<?php echo number_format($order['total_amount'], 2); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Content Grid -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
                            <!-- Patient & Delivery Address Box -->
                            <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 12px; padding: 1rem;">
                                <div style="font-size: 0.82rem; font-weight: 700; color: var(--primary-color); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.6rem;">
                                    <i class="fas fa-user-circle"></i> Patient & Delivery Address
                                </div>
                                <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-primary); margin-bottom: 0.2rem;">
                                    <?php echo htmlspecialchars($order['patient_name']); ?>
                                </div>
                                <?php if (!empty($order['patient_phone'])): ?>
                                    <div style="font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                                        <i class="fas fa-phone-alt" style="font-size: 0.75rem; margin-right: 4px;"></i><?php echo htmlspecialchars($order['patient_phone']); ?>
                                    </div>
                                <?php endif; ?>

                                <div style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.45; overflow-wrap: anywhere; word-break: break-word; background: rgba(0,0,0,0.15); padding: 0.6rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.04);">
                                    <i class="fas fa-map-marker-alt" style="color: #ff4757; margin-right: 4px;"></i>
                                    <?php echo !empty($order['address']) ? htmlspecialchars($order['address']) : '<em style="opacity: 0.6;">No address provided</em>'; ?>
                                </div>
                            </div>

                            <!-- Medicines & Order Items Box -->
                            <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 12px; padding: 1rem;">
                                <div style="font-size: 0.82rem; font-weight: 700; color: var(--primary-color); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.6rem;">
                                    <i class="fas fa-pills"></i> Ordered Medicines (<?php echo count($order['items']); ?>)
                                </div>
                                <div style="max-height: 120px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.4rem; padding-right: 4px;">
                                    <?php foreach ($order['items'] as $it): ?>
                                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; border-bottom: 1px dashed rgba(255,255,255,0.06); padding-bottom: 0.3rem;">
                                            <div style="overflow-wrap: anywhere; word-break: break-word; flex: 1; padding-right: 0.5rem;">
                                                <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($it['medicine_name']); ?></strong>
                                                <span style="color: var(--text-secondary); font-size: 0.78rem;"> (x<?php echo $it['quantity']; ?>)</span>
                                            </div>
                                            <div style="font-weight: 600; color: var(--secondary-color); white-space: nowrap;">
                                                ₹<?php echo number_format($it['item_total'], 2); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Payment & Status Info Box -->
                            <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 12px; padding: 1rem;">
                                <div style="font-size: 0.82rem; font-weight: 700; color: var(--primary-color); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.6rem;">
                                    <i class="fas fa-receipt"></i> Payment & Delivery Status
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.85rem;">
                                    <div>
                                        <span style="color: var(--text-secondary);">Method: </span>
                                        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($order['payment_method']); ?></strong>
                                    </div>
                                    <div>
                                        <span style="color: var(--text-secondary);">Payment Status: </span>
                                        <strong style="color: <?php echo $pay_color; ?>;"><?php echo htmlspecialchars($pay_status); ?></strong>
                                    </div>
                                    
                                    <!-- Estimated Delivery Box -->
                                    <div id="card-est-time-box-<?php echo $ord_id; ?>" style="margin-top: 0.3rem; font-size: 0.82rem;">
                                        <span style="color: var(--text-secondary);">Est. Delivery: </span>
                                        <strong class="est-val" style="color: var(--primary-color);"><?php echo !empty($order['estimated_delivery_time']) ? htmlspecialchars($order['estimated_delivery_time']) : 'Not set'; ?></strong>
                                    </div>

                                    <!-- Cancellation Reason Box -->
                                    <div id="card-cancel-reason-box-<?php echo $ord_id; ?>" style="display: <?php echo ($order['order_status'] === 'cancelled' && !empty($order['cancellation_reason'])) ? 'block' : 'none'; ?>; margin-top: 0.4rem; padding: 0.4rem 0.6rem; background: rgba(255,71,87,0.1); border-left: 3px solid #ff4757; border-radius: 6px; color: #ff4757; font-size: 0.8rem; overflow-wrap: anywhere; word-break: break-word;">
                                        <strong>Cancellation Reason: </strong><span class="reason-val"><?php echo htmlspecialchars($order['cancellation_reason']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Action Buttons Toolbar -->
                        <div style="display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: center; justify-content: space-between; border-top: 1px solid var(--glass-border); padding-top: 1rem;">
                            <!-- Status Change Dropdown + Update Button -->
                            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                <label style="font-size: 0.82rem; color: var(--text-secondary); font-weight: 600;">Status:</label>
                                <select id="status-select-<?php echo $ord_id; ?>" class="form-control" style="width: auto; padding: 0.35rem 0.75rem; font-size: 0.82rem;">
                                    <option value="pending" <?php echo (strtolower($order['order_status']) === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo (strtolower($order['order_status']) === 'processing') ? 'selected' : ''; ?>>Processing</option>
                                    <option value="packing" <?php echo (in_array(strtolower($order['order_status']), ['packing', 'packed'])) ? 'selected' : ''; ?>>Packing</option>
                                    <option value="shipped" <?php echo (strtolower($order['order_status']) === 'shipped') ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="out for delivery" <?php echo (strtolower($order['order_status']) === 'out for delivery') ? 'selected' : ''; ?>>Out for Delivery</option>
                                    <option value="delivered" <?php echo (strtolower($order['order_status']) === 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="cancelled" <?php echo (strtolower($order['order_status']) === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="rejected" <?php echo (strtolower($order['order_status']) === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                                <button type="button" id="update-btn-<?php echo $ord_id; ?>" class="btn btn-primary" style="padding: 0.35rem 0.85rem; font-size: 0.82rem;" onclick="submitCardStatusUpdate(<?php echo $ord_id; ?>)">
                                    <i class="fas fa-sync-alt"></i> Update
                                </button>
                            </div>

                            <!-- Admin Actions -->
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                                <button type="button" class="btn btn-outline" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;" onclick="viewFullOrderModal(<?php echo $ord_id; ?>)" title="View Complete Details">
                                    <i class="fas fa-eye"></i> Details
                                </button>
                                <button type="button" class="btn btn-outline" style="padding: 0.45rem 0.85rem; font-size: 0.82rem; color: var(--primary-color); border-color: var(--primary-color);" onclick="openEstTimeModal(<?php echo $ord_id; ?>, '<?php echo htmlspecialchars(addslashes($order['estimated_delivery_time'])); ?>')" title="Set/Update Estimated Delivery Time">
                                    <i class="fas fa-clock"></i> Set Est. Time
                                </button>
                                <?php if (strtolower($order['order_status']) !== 'cancelled'): ?>
                                    <button type="button" class="btn btn-outline" style="padding: 0.45rem 0.85rem; font-size: 0.82rem; color: #ffa502; border-color: #ffa502;" onclick="openCancelModal(<?php echo $ord_id; ?>)" title="Cancel Order">
                                        <i class="fas fa-times-circle"></i> Cancel Order
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline" style="padding: 0.45rem 0.85rem; font-size: 0.82rem; color: #ff4757; border-color: rgba(255, 71, 87, 0.4);" onclick="openDeleteModal(<?php echo $ord_id; ?>, '<?php echo $formatted_id; ?>')" title="Delete Order">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="glass-panel" style="padding: 3rem; text-align: center; border-radius: 16px;">
                    <i class="fas fa-box-open" style="font-size: 3rem; color: var(--text-secondary); opacity: 0.4; margin-bottom: 1rem;"></i>
                    <h3 style="color: var(--text-primary);">No medicine orders found.</h3>
                    <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.4rem;">Orders placed by patients will appear here for complete management.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- ================= MODALS ================= -->

<!-- 1. Cancel Order Modal with Mandatory Reason -->
<div id="cancelOrderModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); z-index: 1000000; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: var(--darker-bg, #121826); border: 1px solid var(--glass-border, rgba(255,255,255,0.15)); border-radius: 18px; padding: 1.5rem; max-width: 420px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.7); color: var(--text-primary);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.6rem;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #ffa502;"><i class="fas fa-times-circle"></i> Cancel Order</h3>
            <button type="button" onclick="closeCancelModal()" style="background: none; border: none; color: var(--text-secondary); font-size: 1.2rem; cursor: pointer;">&times;</button>
        </div>
        <p style="font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 1rem;">Please enter a mandatory cancellation reason for this order. The patient will be notified with this reason.</p>

        <input type="hidden" id="cancelModalOrderId" value="0">
        <div style="margin-bottom: 1.2rem;">
            <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 0.4rem; color: var(--text-primary);">Cancellation Reason <span style="color: #ff4757;">*</span></label>
            <textarea id="cancelReasonInput" class="form-control" rows="3" placeholder="Enter reason (e.g. Out of stock, Address unserviceable, Patient request)..." style="width: 100%; font-size: 0.88rem;"></textarea>
            <div id="cancelReasonErr" style="color: #ff4757; font-size: 0.78rem; margin-top: 0.3rem; display: none;">Reason cannot be empty.</div>
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
            <button type="button" class="btn btn-outline" onclick="closeCancelModal()" style="padding: 0.55rem 1rem; font-size: 0.85rem;">Cancel</button>
            <button type="button" id="submitCancelBtn" class="btn btn-primary" onclick="submitCancelOrder()" style="padding: 0.55rem 1.2rem; font-size: 0.85rem; background: #ffa502; border-color: #ffa502;">Confirm Cancellation</button>
        </div>
    </div>
</div>

<!-- 2. Set Estimated Delivery Time Modal -->
<div id="estTimeModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); z-index: 1000000; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: var(--darker-bg, #121826); border: 1px solid var(--glass-border, rgba(255,255,255,0.15)); border-radius: 18px; padding: 1.5rem; max-width: 400px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.7); color: var(--text-primary);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.6rem;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: var(--primary-color);"><i class="fas fa-clock"></i> Set Estimated Delivery Time</h3>
            <button type="button" onclick="closeEstTimeModal()" style="background: none; border: none; color: var(--text-secondary); font-size: 1.2rem; cursor: pointer;">&times;</button>
        </div>
        <p style="font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 1rem;">Set or update expected delivery timeframe (e.g., "45 minutes", "Today by 6 PM", "Tomorrow 11 AM").</p>

        <input type="hidden" id="estTimeOrderId" value="0">
        <div style="margin-bottom: 1.2rem;">
            <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 0.4rem; color: var(--text-primary);">Estimated Delivery Time</label>
            <input type="text" id="estTimeInput" class="form-control" placeholder="e.g. 45 minutes / Today 6:00 PM" style="width: 100%; font-size: 0.88rem;">
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
            <button type="button" class="btn btn-outline" onclick="closeEstTimeModal()" style="padding: 0.55rem 1rem; font-size: 0.85rem;">Cancel</button>
            <button type="button" id="submitEstTimeBtn" class="btn btn-primary" onclick="submitEstTime()" style="padding: 0.55rem 1.2rem; font-size: 0.85rem;">Save Delivery Time</button>
        </div>
    </div>
</div>

<!-- 3. Delete Order Confirmation Modal -->
<div id="deleteOrderModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); z-index: 1000000; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: var(--darker-bg, #121826); border: 1px solid var(--glass-border, rgba(255,255,255,0.15)); border-radius: 18px; padding: 1.5rem; max-width: 380px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.7); color: var(--text-primary); text-align: center;">
        <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(255, 71, 87, 0.15); color: #ff4757; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.25rem;">
            <i class="fas fa-trash-alt"></i>
        </div>
        <h3 style="margin: 0 0 0.5rem; font-size: 1.15rem; font-weight: 700; color: var(--text-primary);">Delete this order?</h3>
        <p style="margin: 0 0 1.25rem; font-size: 0.85rem; color: var(--text-secondary); line-height: 1.4;">Are you sure you want to delete order <strong id="deleteModalFormattedId" style="color: var(--primary-color);">#ORD-0000</strong>? This action will remove the record.</p>

        <input type="hidden" id="deleteModalOrderId" value="0">
        <div style="display: flex; gap: 0.75rem; justify-content: center;">
            <button type="button" class="btn btn-outline" onclick="closeDeleteModal()" style="flex: 1; padding: 0.6rem 1rem; font-size: 0.85rem;">Cancel</button>
            <button type="button" id="submitDeleteBtn" class="btn btn-primary" onclick="submitDeleteOrder()" style="flex: 1; padding: 0.6rem 1rem; font-size: 0.85rem; background: #ff4757; border-color: #ff4757;">Delete</button>
        </div>
    </div>
</div>

<!-- 4. View Complete Order Details Modal -->
<div id="fullOrderModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); z-index: 1000000; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: var(--darker-bg, #121826); border: 1px solid var(--glass-border, rgba(255,255,255,0.15)); border-radius: 18px; padding: 1.5rem; max-width: 580px; width: 100%; max-height: 85vh; overflow-y: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.7); color: var(--text-primary);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.6rem;">
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--primary-color);" id="fullModalTitle">Order Details</h3>
            <button type="button" onclick="closeFullOrderModal()" style="background: none; border: none; color: var(--text-secondary); font-size: 1.2rem; cursor: pointer;">&times;</button>
        </div>
        <div id="fullModalBody" style="font-size: 0.88rem; line-height: 1.5;">
            Loading order details...
        </div>
    </div>
</div>

<script>
function filterAdminOrders() {
    var search = document.getElementById('adminOrderSearch').value.toLowerCase().trim();
    var status = document.getElementById('adminStatusFilter').value.toLowerCase();
    var cards = document.querySelectorAll('.admin-order-card');
    var visible = 0;

    cards.forEach(function(card) {
        var cardSearch = card.getAttribute('data-search') || '';
        var cardStatus = card.getAttribute('data-status') || '';

        var matchesSearch = !search || cardSearch.indexOf(search) !== -1;
        var matchesStatus = (status === 'all') || (cardStatus === status);

        if (matchesSearch && matchesStatus) {
            card.style.display = 'block';
            visible++;
        } else {
            card.style.display = 'none';
        }
    });

    document.getElementById('visibleCount').innerText = visible;
}

function resetAdminFilters() {
    document.getElementById('adminOrderSearch').value = '';
    document.getElementById('adminStatusFilter').value = 'all';
    filterAdminOrders();
}

// Status Style Color Mapping for JavaScript
function getStatusStyleJS(status) {
    var st = (status || '').toLowerCase().trim();
    switch (st) {
        case 'delivered':
            return { color: '#2ed573', bg: 'rgba(46, 213, 115, 0.12)', border: '#2ed573' };
        case 'shipped':
            return { color: '#4a90e2', bg: 'rgba(74, 144, 226, 0.12)', border: '#4a90e2' };
        case 'packing':
        case 'packed':
            return { color: '#a55eea', bg: 'rgba(165, 94, 234, 0.12)', border: '#a55eea' };
        case 'processing':
            return { color: '#3498db', bg: 'rgba(52, 152, 219, 0.12)', border: '#3498db' };
        case 'out for delivery':
            return { color: '#fa8231', bg: 'rgba(250, 130, 49, 0.12)', border: '#fa8231' };
        case 'rejected':
        case 'cancelled':
        case 'failed':
            return { color: '#ff4757', bg: 'rgba(255, 71, 87, 0.12)', border: '#ff4757' };
        case 'pending':
        default:
            return { color: '#f5a623', bg: 'rgba(245, 166, 35, 0.12)', border: '#f5a623' };
    }
}

// Toast notification helper
function showStatusToast(message, isSuccess = true) {
    var toast = document.getElementById('statusToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'statusToast';
        toast.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000000; padding: 12px 20px; border-radius: 12px; font-weight: 600; font-size: 0.9rem; box-shadow: 0 10px 30px rgba(0,0,0,0.5); transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; max-width: 380px;';
        document.body.appendChild(toast);
    }
    toast.style.background = isSuccess ? 'rgba(46, 213, 115, 0.95)' : 'rgba(255, 71, 87, 0.95)';
    toast.style.color = '#ffffff';
    toast.innerHTML = (isSuccess ? '<i class="fas fa-check-circle"></i> ' : '<i class="fas fa-exclamation-circle"></i> ') + escapeHtml(message);
    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0)';

    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px)';
    }, 3500);
}

// Submit Same Card Status Update
function submitCardStatusUpdate(orderId) {
    var selectElem = document.getElementById('status-select-' + orderId);
    var btnElem = document.getElementById('update-btn-' + orderId);
    if (!selectElem || !btnElem) return;

    var newStatus = selectElem.value;

    if (newStatus === 'cancelled') {
        openCancelModal(orderId);
        return;
    }

    var originalBtnText = btnElem.innerHTML;
    btnElem.disabled = true;
    btnElem.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

    fetch('api_admin_order_action.php?action=update_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, status: newStatus })
    })
    .then(res => res.json())
    .then(data => {
        btnElem.disabled = false;
        btnElem.innerHTML = originalBtnText;

        if (data.success) {
            var card = document.getElementById('order-card-' + orderId);
            if (card) {
                // 1. Update data-status attribute on card
                card.setAttribute('data-status', newStatus.toLowerCase());

                // 2. Update search index attribute
                var oldSearch = card.getAttribute('data-search') || '';
                card.setAttribute('data-search', oldSearch + ' ' + newStatus.toLowerCase());

                // 3. Immediately update badge text & status color on the SAME card
                var badge = document.getElementById('card-status-badge-' + orderId);
                if (badge) {
                    var style = getStatusStyleJS(newStatus);
                    badge.style.color = style.color;
                    badge.style.borderColor = style.border;
                    badge.style.background = style.bg;

                    var statusTextSpan = badge.querySelector('.status-text');
                    if (statusTextSpan) {
                        statusTextSpan.innerText = newStatus;
                    }
                }

                // 4. Hide cancellation reason box if status moved away from cancelled
                var reasonBox = document.getElementById('card-cancel-reason-box-' + orderId);
                if (reasonBox && newStatus !== 'cancelled') {
                    reasonBox.style.display = 'none';
                }
            }

            showStatusToast('Order status updated successfully.', true);
            filterAdminOrders();
        } else {
            showStatusToast(data.message || 'Failed to update order status', false);
        }
    })
    .catch(err => {
        btnElem.disabled = false;
        btnElem.innerHTML = originalBtnText;
        showStatusToast('Network error while updating order status.', false);
    });
}

function updateOrderStatusInline(orderId, newStatus) {
    var selectElem = document.getElementById('status-select-' + orderId);
    if (selectElem) selectElem.value = newStatus;
    submitCardStatusUpdate(orderId);
}

// Cancel Modal Functions
function openCancelModal(orderId) {
    document.getElementById('cancelModalOrderId').value = orderId;
    document.getElementById('cancelReasonInput').value = '';
    document.getElementById('cancelReasonErr').style.display = 'none';
    document.getElementById('cancelOrderModal').style.display = 'flex';
}

function closeCancelModal() {
    document.getElementById('cancelOrderModal').style.display = 'none';
}

function submitCancelOrder() {
    var orderId = parseInt(document.getElementById('cancelModalOrderId').value);
    var reason = document.getElementById('cancelReasonInput').value.trim();
    var err = document.getElementById('cancelReasonErr');
    var btn = document.getElementById('submitCancelBtn');

    if (!reason) {
        err.style.display = 'block';
        return;
    }
    err.style.display = 'none';
    btn.disabled = true;
    btn.innerText = 'Cancelling...';

    fetch('api_admin_order_action.php?action=cancel_order', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, reason: reason })
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerText = 'Confirm Cancellation';
        closeCancelModal();

        if (data.success) {
            var card = document.getElementById('order-card-' + orderId);
            if (card) {
                card.setAttribute('data-status', 'cancelled');
                var badge = document.getElementById('card-status-badge-' + orderId);
                if (badge) {
                    badge.style.color = '#ff4757';
                    badge.style.borderColor = '#ff4757';
                    badge.style.background = 'rgba(255, 71, 87, 0.12)';
                    var textSpan = badge.querySelector('.status-text');
                    if (textSpan) textSpan.innerText = 'cancelled';
                }

                var reasonBox = document.getElementById('card-cancel-reason-box-' + orderId);
                if (reasonBox) {
                    reasonBox.style.display = 'block';
                    var valSpan = reasonBox.querySelector('.reason-val');
                    if (valSpan) valSpan.innerText = data.cancellation_reason;
                }
            }
            filterAdminOrders();
        } else {
            alert(data.message || 'Failed to cancel order');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerText = 'Confirm Cancellation';
        closeCancelModal();
        alert('Server error occurred while cancelling order.');
    });
}

// Est Time Modal Functions
function openEstTimeModal(orderId, currentVal) {
    document.getElementById('estTimeOrderId').value = orderId;
    document.getElementById('estTimeInput').value = currentVal || '';
    document.getElementById('estTimeModal').style.display = 'flex';
}

function closeEstTimeModal() {
    document.getElementById('estTimeModal').style.display = 'none';
}

function submitEstTime() {
    var orderId = parseInt(document.getElementById('estTimeOrderId').value);
    var estTime = document.getElementById('estTimeInput').value.trim();
    var btn = document.getElementById('submitEstTimeBtn');

    btn.disabled = true;
    btn.innerText = 'Saving...';

    fetch('api_admin_order_action.php?action=set_delivery_time', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, estimated_delivery_time: estTime })
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerText = 'Save Delivery Time';
        closeEstTimeModal();

        if (data.success) {
            var box = document.getElementById('card-est-time-box-' + orderId);
            if (box) {
                var valSpan = box.querySelector('.est-val');
                if (valSpan) valSpan.innerText = data.estimated_delivery_time || 'Not set';
            }
        } else {
            alert(data.message || 'Failed to update delivery time');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerText = 'Save Delivery Time';
        closeEstTimeModal();
        alert('Server error occurred.');
    });
}

// Delete Modal Functions
function openDeleteModal(orderId, formattedId) {
    document.getElementById('deleteModalOrderId').value = orderId;
    document.getElementById('deleteModalFormattedId').innerText = formattedId;
    document.getElementById('deleteOrderModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteOrderModal').style.display = 'none';
}

function submitDeleteOrder() {
    var orderId = parseInt(document.getElementById('deleteModalOrderId').value);
    var btn = document.getElementById('submitDeleteBtn');

    btn.disabled = true;
    btn.innerText = 'Deleting...';

    fetch('api_admin_order_action.php?action=delete_order', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId })
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerText = 'Delete';
        closeDeleteModal();

        if (data.success) {
            var card = document.getElementById('order-card-' + orderId);
            if (card) {
                card.style.opacity = '0';
                setTimeout(function() {
                    card.remove();
                    filterAdminOrders();
                }, 300);
            }
        } else {
            alert(data.message || 'Failed to delete order');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerText = 'Delete';
        closeDeleteModal();
        alert('Server error occurred while deleting order.');
    });
}

// Full Details Modal
function viewFullOrderModal(orderId) {
    document.getElementById('fullModalTitle').innerText = 'Order #' + String(orderId).padStart(4, '0') + ' Details';
    document.getElementById('fullModalBody').innerHTML = '<div style="text-align: center; padding: 2rem;">Loading details...</div>';
    document.getElementById('fullOrderModal').style.display = 'flex';

    fetch('api_admin_order_action.php?action=get_order_details&order_id=' + orderId)
    .then(res => res.json())
    .then(data => {
        if (data.success && data.order) {
            var o = data.order;
            var html = `
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 12px; border: 1px solid var(--glass-border);">
                        <div style="font-weight: bold; color: var(--primary-color); margin-bottom: 0.4rem;">Patient & Delivery Address</div>
                        <div><strong>Name:</strong> ${escapeHtml(o.patient_name)}</div>
                        <div><strong>Phone:</strong> ${escapeHtml(o.patient_phone || 'N/A')}</div>
                        <div><strong>Email:</strong> ${escapeHtml(o.patient_email || 'N/A')}</div>
                        <div style="margin-top: 0.4rem; font-size: 0.85rem; color: var(--text-secondary); line-height: 1.4; overflow-wrap: anywhere; word-break: break-word;">
                            <strong>Address:</strong> ${escapeHtml(o.address || 'N/A')}
                        </div>
                    </div>

                    <div style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 12px; border: 1px solid var(--glass-border);">
                        <div style="font-weight: bold; color: var(--primary-color); margin-bottom: 0.4rem;">Order Status & Payment</div>
                        <div><strong>Status:</strong> <span style="text-transform: capitalize; font-weight: bold;">${escapeHtml(o.status)}</span></div>
                        <div><strong>Payment Method:</strong> ${escapeHtml(o.payment_method)}</div>
                        <div><strong>Payment Status:</strong> ${escapeHtml(o.payment_status)}</div>
                        <div><strong>Total Amount:</strong> ₹${parseFloat(o.total_amount).toFixed(2)}</div>
                        ${o.estimated_delivery_time ? `<div><strong>Est. Delivery Time:</strong> ${escapeHtml(o.estimated_delivery_time)}</div>` : ''}
                        ${o.cancellation_reason ? `<div style="color: #ff4757; margin-top: 0.3rem;"><strong>Cancellation Reason:</strong> ${escapeHtml(o.cancellation_reason)}</div>` : ''}
                    </div>

                    <div style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 12px; border: 1px solid var(--glass-border);">
                        <div style="font-weight: bold; color: var(--primary-color); margin-bottom: 0.6rem;">Ordered Medicines (${(o.items || []).length})</div>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            ${(o.items || []).map(it => `
                                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; border-bottom: 1px dashed rgba(255,255,255,0.08); padding-bottom: 0.3rem;">
                                    <div><strong>${escapeHtml(it.medicine_name)}</strong> (x${it.quantity})</div>
                                    <div style="color: var(--secondary-color); font-weight: bold;">₹${(parseFloat(it.price) * parseInt(it.quantity)).toFixed(2)}</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('fullModalBody').innerHTML = html;
        } else {
            document.getElementById('fullModalBody').innerHTML = '<div style="color: #ff4757; text-align: center; padding: 2rem;">Failed to load order details.</div>';
        }
    })
    .catch(err => {
        document.getElementById('fullModalBody').innerHTML = '<div style="color: #ff4757; text-align: center; padding: 2rem;">Server error loading details.</div>';
    });
}

function closeFullOrderModal() {
    document.getElementById('fullOrderModal').style.display = 'none';
}

function escapeHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php include 'includes/footer.php'; ?>
