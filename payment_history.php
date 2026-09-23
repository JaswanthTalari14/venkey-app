<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Fetch payments depending on role
$payments = [];
$total_paid = 0;
$successful_count = 0;
$pending_count = 0;

if ($role === 'patient') {
    // Fetch Medicine Orders
    $order_res = $conn->query("
        SELECT id, 'Medicine Order' as item_type, total_amount as amount, payment_method, payment_status, 
               gateway_payment_id as ref_id, created_at 
        FROM orders 
        WHERE patient_id = $user_id 
        ORDER BY created_at DESC
    ");
    while ($row = $order_res->fetch_assoc()) {
        $payments[] = $row;
    }
    
    // Fetch Doctor Consultations (Sample fixed fee or booked appointments)
    $app_res = $conn->query("
        SELECT a.id, CONCAT('Doctor Consult (', u.name, ')') as item_type, 300.00 as amount, 
               'Online / Wallet' as payment_method, 
               (CASE WHEN a.status = 'cancelled' THEN 'Refunded' WHEN a.status = 'pending' THEN 'Pending' ELSE 'Paid' END) as payment_status,
               CONCAT('APP_', a.id) as ref_id, a.created_at
        FROM appointments a
        JOIN users u ON a.doctor_id = u.id
        WHERE a.patient_id = $user_id
        ORDER BY a.created_at DESC
    ");
    while ($row = $app_res->fetch_assoc()) {
        $payments[] = $row;
    }

    // Fetch Lab Test Bookings
    $test_res = $conn->query("
        SELECT t.id, CONCAT('Lab Test: ', t.test_name) as item_type, 500.00 as amount,
               'UPI / Online' as payment_method,
               (CASE WHEN t.status = 'completed' THEN 'Paid' ELSE 'Pending' END) as payment_status,
               CONCAT('TEST_', t.id) as ref_id, t.created_at
        FROM test_bookings t
        WHERE t.patient_id = $user_id
        ORDER BY t.created_at DESC
    ");
    while ($row = $test_res->fetch_assoc()) {
        $payments[] = $row;
    }
} elseif ($role === 'doctor') {
    // Fetch Patient Medicine Orders & Consultation Payments for Doctor
    $order_res = $conn->query("
        SELECT o.id, CONCAT('Medicine Order #', o.id, ' - ', u.name) as item_type, o.total_amount as amount, 
               o.payment_method, o.payment_status, o.gateway_payment_id as ref_id, o.created_at 
        FROM orders o
        JOIN users u ON o.patient_id = u.id
        ORDER BY o.created_at DESC
    ");
    while ($row = $order_res->fetch_assoc()) {
        $payments[] = $row;
    }
} elseif ($role === 'rmp') {
    // Fetch Test Bookings and Referral Payouts for RMP
    $ref_res = $conn->query("
        SELECT r.id, CONCAT('Referral: ', r.patient_name) as item_type, 250.00 as amount,
               'Direct Payout' as payment_method, r.payment_status,
               CONCAT('REF_', r.id) as ref_id, r.created_at
        FROM referrals r
        WHERE r.rmp_id = $user_id
        ORDER BY r.created_at DESC
    ");
    while ($row = $ref_res->fetch_assoc()) {
        $payments[] = $row;
    }
    
    $test_res = $conn->query("
        SELECT t.id, CONCAT('Lab Test: ', t.test_name, ' (', u.name, ')') as item_type, 500.00 as amount,
               'UPI / Online' as payment_method,
               (CASE WHEN t.status = 'completed' THEN 'Paid' ELSE 'Pending' END) as payment_status,
               CONCAT('TEST_', t.id) as ref_id, t.created_at
        FROM test_bookings t
        JOIN users u ON t.patient_id = u.id
        WHERE t.rmp_id = $user_id
        ORDER BY t.created_at DESC
    ");
    while ($row = $test_res->fetch_assoc()) {
        $payments[] = $row;
    }
} else {
    // Admin: Fetch All Platform Transactions
    $order_res = $conn->query("
        SELECT o.id, CONCAT('Medicine Order (', u.name, ')') as item_type, o.total_amount as amount, 
               o.payment_method, o.payment_status, o.gateway_payment_id as ref_id, o.created_at 
        FROM orders o
        JOIN users u ON o.patient_id = u.id
        ORDER BY o.created_at DESC
    ");
    while ($row = $order_res->fetch_assoc()) {
        $payments[] = $row;
    }
}

// Sort all combined payment items by date DESC
usort($payments, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Calculate metrics
foreach ($payments as $p) {
    $st = strtolower($p['payment_status']);
    if ($st === 'paid' || $st === 'completed') {
        $total_paid += (float)$p['amount'];
        $successful_count++;
    } elseif ($st === 'pending' || $st === 'cash on delivery') {
        $pending_count++;
    }
}

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Navigation Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;"><?php echo ucfirst($role); ?> Menu</h3>
        <ul class="sidebar-menu">
            <?php if ($role === 'patient'): ?>
                <li><a href="patient_dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
                <li><a href="book_consult.php"><i class="fas fa-calendar-check"></i> Consultations</a></li>
                <li><a href="medicines.php"><i class="fas fa-pills"></i> Order Medicines</a></li>
                <li><a href="nearby_doctors.php"><i class="fas fa-map-marker-alt"></i> Find Doctors (10km)</a></li>
                <li><a href="privacy_consult.php"><i class="fas fa-user-secret"></i> Privacy Consult</a></li>
                <li><a href="book_tests.php"><i class="fas fa-vial"></i> Book Labs (RMP)</a></li>
                <li><a href="payment_history.php" class="active"><i class="fas fa-receipt"></i> Payment History</a></li>
                <li><a href="chatbot.php"><i class="fas fa-robot"></i> AI Chatbot</a></li>
            <?php elseif ($role === 'doctor'): ?>
                <li><a href="doctor_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="doctor_appointments.php"><i class="fas fa-calendar-day"></i> Appointments</a></li>
                <li><a href="doctor_referrals.php"><i class="fas fa-exchange-alt"></i> Patient Referrals</a></li>
                <li><a href="doctor_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
                <li><a href="doctor_orders.php"><i class="fas fa-box"></i> Medicine Orders</a></li>
                <li><a href="doctor_queries.php"><i class="fas fa-user-secret"></i> Anonymous Queries</a></li>
                <li><a href="payment_history.php" class="active"><i class="fas fa-receipt"></i> Payment History</a></li>
                <li><a href="profile.php"><i class="fas fa-cog"></i> Settings & Availability</a></li>
            <?php elseif ($role === 'rmp'): ?>
                <li><a href="rmp_dashboard.php"><i class="fas fa-flask"></i> My Booked Tests</a></li>
                <li><a href="rmp_upload.php"><i class="fas fa-file-upload"></i> Upload Results</a></li>
                <li><a href="rmp_referral.php"><i class="fas fa-user-md"></i> Doctor Referrals</a></li>
                <li><a href="payment_history.php" class="active"><i class="fas fa-receipt"></i> Payment History</a></li>
                <li><a href="profile.php"><i class="fas fa-cog"></i> Settings</a></li>
            <?php else: ?>
                <li><a href="admin_dashboard.php"><i class="fas fa-chart-pie"></i> Overview</a></li>
                <li><a href="admin_users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
                <li><a href="admin_verify.php"><i class="fas fa-user-md"></i> Verify Doctors & RMPs</a></li>
                <li><a href="admin_bookings.php"><i class="fas fa-calendar-check"></i> All Bookings</a></li>
                <li><a href="admin_orders.php"><i class="fas fa-box"></i> Medicine Orders</a></li>
                <li><a href="admin_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
                <li><a href="payment_history.php" class="active"><i class="fas fa-receipt"></i> Payment History</a></li>
                <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback & Complaints</a></li>
            <?php endif; ?>
        </ul>
    </aside>

    <main class="dashboard-content">
        <h2><i class="fas fa-file-invoice-dollar" style="color: var(--primary-color);"></i> Payment & Transaction History</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Track all payment logs, completed transactions, receipts, and order billing statuses.</p>

        <!-- Metrics Overview Grid -->
        <div class="features-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-top: 0; margin-bottom: 2rem;">
            <div class="feature-card glass-panel" style="padding: 1.5rem;">
                <h4 style="color: var(--primary-color); font-size: 0.95rem;"><i class="fas fa-wallet"></i> Total Paid Amount</h4>
                <p style="font-size: 1.8rem; font-weight: bold; margin: 0.75rem 0; color: var(--text-primary);">₹<?php echo number_format($total_paid, 2); ?></p>
                <span style="font-size: 0.8rem; color: var(--text-secondary);">Verified transactions</span>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.5rem;">
                <h4 style="color: #2ed573; font-size: 0.95rem;"><i class="fas fa-check-circle"></i> Successful Payments</h4>
                <p style="font-size: 1.8rem; font-weight: bold; margin: 0.75rem 0; color: #2ed573;"><?php echo $successful_count; ?></p>
                <span style="font-size: 0.8rem; color: var(--text-secondary);">Completed & confirmed</span>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.5rem;">
                <h4 style="color: var(--accent); font-size: 0.95rem;"><i class="fas fa-clock"></i> Pending / COD</h4>
                <p style="font-size: 1.8rem; font-weight: bold; margin: 0.75rem 0; color: var(--accent);"><?php echo $pending_count; ?></p>
                <span style="font-size: 0.8rem; color: var(--text-secondary);">Awaiting final settlement</span>
            </div>
        </div>

        <!-- Payment History Table -->
        <div class="glass-panel" style="overflow-x: auto; padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <h3 style="font-size: 1.2rem;">All Transactions (<?php echo count($payments); ?>)</h3>
                <input type="text" id="paymentSearch" class="form-control" placeholder="Search transaction or ID..." style="max-width: 280px; font-size: 0.9rem; padding: 0.5rem 1rem;" onkeyup="filterPayments()">
            </div>

            <?php if (count($payments) > 0): ?>
                <table id="paymentsTable" style="width: 100%; text-align: left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border);">
                            <th style="padding: 1rem;">Transaction Ref</th>
                            <th style="padding: 1rem;">Item / Service</th>
                            <th style="padding: 1rem;">Amount</th>
                            <th style="padding: 1rem;">Method</th>
                            <th style="padding: 1rem;">Status</th>
                            <th style="padding: 1rem;">Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                            <?php 
                                $status = $p['payment_status'];
                                $badge_style = "background: rgba(245, 166, 35, 0.2); color: var(--accent); border: 1px solid var(--accent);";
                                $st_lower = strtolower($status);
                                if ($st_lower === 'paid' || $st_lower === 'completed') {
                                    $badge_style = "background: rgba(46, 213, 115, 0.2); color: #2ed573; border: 1px solid #2ed573;";
                                } elseif ($st_lower === 'failed' || $st_lower === 'cancelled') {
                                    $badge_style = "background: rgba(255, 71, 87, 0.2); color: #ff4757; border: 1px solid #ff4757;";
                                }
                            ?>
                            <tr style="border-bottom: 1px solid var(--glass-border);">
                                <td style="padding: 1rem; font-weight: 600; font-family: monospace;">
                                    <?php echo htmlspecialchars($p['ref_id'] ?: ('TXN_' . $p['id'])); ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php echo htmlspecialchars($p['item_type']); ?>
                                </td>
                                <td style="padding: 1rem; font-weight: 700; color: var(--text-primary);">
                                    ₹<?php echo number_format($p['amount'], 2); ?>
                                </td>
                                <td style="padding: 1rem; font-size: 0.9rem; color: var(--text-secondary);">
                                    <i class="fas fa-credit-card" style="margin-right: 0.3rem;"></i> 
                                    <?php echo htmlspecialchars($p['payment_method'] ?: 'COD / Online'); ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <span style="padding: 0.3rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-block; <?php echo $badge_style; ?>">
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: var(--text-secondary);">
                                    <?php echo date('M d, Y h:i A', strtotime($p['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: var(--text-secondary); padding: 3rem 1rem;">No payment transactions found in your history.</p>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
function filterPayments() {
    const input = document.getElementById('paymentSearch');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('paymentsTable');
    if (!table) return;
    const trs = table.getElementsByTagName('tr');

    for (let i = 1; i < trs.length; i++) {
        let text = trs[i].textContent || trs[i].innerText;
        if (text.toLowerCase().indexOf(filter) > -1) {
            trs[i].style.display = "";
        } else {
            trs[i].style.display = "none";
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?>
