<?php
require_once 'config.php';
require_once 'includes/security_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$results = [
    'patients' => [],
    'doctors' => [],
    'rmps' => [],
    'orders' => [],
    'payments' => [],
    'medical_cards' => [],
    'appointments' => []
];

$total_matches = 0;

if (!empty($q)) {
    $search_param = "%$q%";

    // 1. Patients
    $p_stmt = $conn->prepare("SELECT id, health_id, name, email, phone, created_at FROM users WHERE role = 'patient' AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR health_id LIKE ? OR CAST(id AS CHAR) = ?) LIMIT 10");
    $p_stmt->bind_param("sssss", $search_param, $search_param, $search_param, $search_param, $q);
    $p_stmt->execute();
    $p_res = $p_stmt->get_result();
    while ($row = $p_res->fetch_assoc()) {
        $results['patients'][] = $row;
        $total_matches++;
    }

    // 2. Doctors
    $d_stmt = $conn->prepare("SELECT id, name, email, phone, specialization, is_verified FROM users WHERE role = 'doctor' AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR specialization LIKE ? OR CAST(id AS CHAR) = ?) LIMIT 10");
    $d_stmt->bind_param("sssss", $search_param, $search_param, $search_param, $search_param, $q);
    $d_stmt->execute();
    $d_res = $d_stmt->get_result();
    while ($row = $d_res->fetch_assoc()) {
        $results['doctors'][] = $row;
        $total_matches++;
    }

    // 3. RMPs
    $r_stmt = $conn->prepare("SELECT id, name, email, phone, is_verified FROM users WHERE role = 'rmp' AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR CAST(id AS CHAR) = ?) LIMIT 10");
    $r_stmt->bind_param("ssss", $search_param, $search_param, $search_param, $q);
    $r_stmt->execute();
    $r_res = $r_stmt->get_result();
    while ($row = $r_res->fetch_assoc()) {
        $results['rmps'][] = $row;
        $total_matches++;
    }

    // 4. Orders
    $o_stmt = $conn->prepare("SELECT o.*, u.name as patient_name FROM orders o JOIN users u ON o.patient_id = u.id WHERE CAST(o.id AS CHAR) LIKE ? OR o.gateway_order_id LIKE ? OR o.gateway_payment_id LIKE ? OR u.name LIKE ? ORDER BY o.id DESC LIMIT 10");
    $o_stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
    $o_stmt->execute();
    $o_res = $o_stmt->get_result();
    while ($row = $o_res->fetch_assoc()) {
        $results['orders'][] = $row;
        $total_matches++;
    }

    // 5. Payments / Wallet Transactions
    $w_stmt = $conn->prepare("SELECT wt.*, u.name as customer_name FROM wallet_transactions wt JOIN users u ON wt.customer_id = u.id WHERE wt.transaction_id LIKE ? OR wt.payment_id LIKE ? OR u.name LIKE ? OR CAST(wt.amount AS CHAR) LIKE ? ORDER BY wt.id DESC LIMIT 10");
    $w_stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
    $w_stmt->execute();
    $w_res = $w_stmt->get_result();
    while ($row = $w_res->fetch_assoc()) {
        $results['payments'][] = $row;
        $total_matches++;
    }

    // 6. Digital Medical Cards
    $c_stmt = $conn->prepare("SELECT c.*, u.name as patient_name FROM digital_medical_cards c JOIN users u ON c.patient_id = u.id WHERE c.card_number LIKE ? OR u.name LIKE ? OR c.gateway_payment_id LIKE ? OR CAST(c.id AS CHAR) = ? ORDER BY c.id DESC LIMIT 10");
    $c_stmt->bind_param("ssss", $search_param, $search_param, $search_param, $q);
    $c_stmt->execute();
    $c_res = $c_stmt->get_result();
    while ($row = $c_res->fetch_assoc()) {
        $results['medical_cards'][] = $row;
        $total_matches++;
    }

    // 7. Appointments
    $a_stmt = $conn->prepare("SELECT a.*, p.name as patient_name, d.name as doctor_name FROM appointments a JOIN users p ON a.patient_id = p.id JOIN users d ON a.doctor_id = d.id WHERE p.name LIKE ? OR d.name LIKE ? OR a.token_no LIKE ? OR CAST(a.id AS CHAR) = ? ORDER BY a.id DESC LIMIT 10");
    $a_stmt->bind_param("ssss", $search_param, $search_param, $search_param, $q);
    $a_stmt->execute();
    $a_res = $a_stmt->get_result();
    while ($row = $a_res->fetch_assoc()) {
        $results['appointments'][] = $row;
        $total_matches++;
    }
}

include 'includes/header.php';
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
            <li><a href="admin_approval_center.php"><i class="fas fa-check-double"></i> Approval Center</a></li>
            <li><a href="admin_search.php" class="active"><i class="fas fa-search"></i> Global Search</a></li>
            <li><a href="admin_audit.php"><i class="fas fa-history"></i> Audit Timeline</a></li>
            <li><a href="admin_digital_cards.php"><i class="fas fa-id-card"></i> Medical Cards</a></li>
            <li><a href="admin_verify.php"><i class="fas fa-user-md"></i> Verify Doctors & RMPs</a></li>
            <li><a href="admin_wallets.php"><i class="fas fa-wallet"></i> Wallets</a></li>
            <li><a href="admin_refunds.php"><i class="fas fa-undo"></i> Refunds</a></li>
            <li><a href="admin_orders_management.php"><i class="fas fa-boxes"></i> Orders</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <h2><i class="fas fa-search" style="color: var(--primary-color);"></i> Advanced Admin Global Search</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Instant parameterized search across Patients, Doctors, RMPs, Orders, Payments, Medical Cards, and Appointments.</p>

        <!-- Search Bar with Live Instant Autocomplete -->
        <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2rem; position: relative;">
            <form method="GET" action="admin_search.php" id="globalSearchForm" style="display: flex; gap: 1rem;">
                <div style="position: relative; flex: 1;">
                    <input type="text" id="adminSearchInput" name="q" class="form-control" placeholder="Search patients, doctors, RMPs, orders, payments, medical cards, appointments..." value="<?php echo htmlspecialchars($q); ?>" autocomplete="off" required style="font-size: 1rem; padding-right: 2.5rem;">
                    <span id="searchSpinner" style="display: none; position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: var(--primary-color);"><i class="fas fa-spinner fa-spin"></i></span>
                </div>
                <button type="submit" class="btn btn-primary" style="padding: 0.7rem 1.5rem;"><i class="fas fa-search"></i> Search</button>
            </form>

            <!-- Live Suggestion Dropdown Box -->
            <div id="liveSearchDropdown" style="display: none; position: absolute; left: 1.5rem; right: 8.5rem; top: 100%; background: #121826; border: 1px solid var(--glass-border); border-radius: 12px; box-shadow: 0 15px 35px rgba(0,0,0,0.5); z-index: 99999; max-height: 400px; overflow-y: auto; padding: 0.8rem;"></div>
        </div>

        <?php if (!empty($q)): ?>
            <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: var(--text-primary);">Search Results for "<span style="color: var(--primary-color);"><?php echo htmlspecialchars($q); ?></span>"</h3>
                <span style="background: rgba(80, 227, 194, 0.15); color: #50e3c2; padding: 0.3rem 0.8rem; border-radius: 20px; font-weight: bold; font-size: 0.85rem;">
                    Total Matches: <?php echo $total_matches; ?>
                </span>
            </div>

            <!-- 1. Patients Results -->
            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                <h3 style="margin-top: 0; color: var(--primary-color); font-size: 1.1rem;"><i class="fas fa-user-injured"></i> Patients (<?php echo count($results['patients']); ?>)</h3>
                <?php if (!empty($results['patients'])): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                            <thead><tr style="border-bottom: 1px solid var(--glass-border); text-align: left;"><th style="padding: 0.8rem;">Health ID</th><th style="padding: 0.8rem;">Patient Name</th><th style="padding: 0.8rem;">Contact</th><th style="padding: 0.8rem;">Registered</th><th style="padding: 0.8rem;">Action</th></tr></thead>
                            <tbody>
                                <?php foreach($results['patients'] as $p): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                        <td style="padding: 0.8rem; font-weight: bold; color: var(--primary-color);"><?php echo htmlspecialchars($p['health_id'] ?: ('MAK-' . str_pad($p['id'], 6, '0', STR_PAD_LEFT))); ?></td>
                                        <td style="padding: 0.8rem; font-weight: bold;"><?php echo htmlspecialchars($p['name']); ?></td>
                                        <td style="padding: 0.8rem; color: var(--text-secondary);"><?php echo htmlspecialchars($p['phone'] . ' | ' . $p['email']); ?></td>
                                        <td style="padding: 0.8rem; color: var(--text-secondary);"><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
                                        <td style="padding: 0.8rem;"><a href="admin_users.php?search=<?php echo urlencode($p['name']); ?>" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">Manage Profile</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 0;">No matching patients found.</p>
                <?php endif; ?>
            </div>

            <!-- 2. Doctors Results -->
            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                <h3 style="margin-top: 0; color: #ff7f50; font-size: 1.1rem;"><i class="fas fa-user-md"></i> Doctors (<?php echo count($results['doctors']); ?>)</h3>
                <?php if (!empty($results['doctors'])): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                            <thead><tr style="border-bottom: 1px solid var(--glass-border); text-align: left;"><th style="padding: 0.8rem;">ID</th><th style="padding: 0.8rem;">Doctor Name</th><th style="padding: 0.8rem;">Specialization</th><th style="padding: 0.8rem;">Contact</th><th style="padding: 0.8rem;">Verification</th><th style="padding: 0.8rem;">Action</th></tr></thead>
                            <tbody>
                                <?php foreach($results['doctors'] as $d): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                        <td style="padding: 0.8rem;">#DOC-<?php echo $d['id']; ?></td>
                                        <td style="padding: 0.8rem; font-weight: bold;">Dr. <?php echo htmlspecialchars($d['name']); ?></td>
                                        <td style="padding: 0.8rem; color: var(--text-secondary);"><?php echo htmlspecialchars($d['specialization'] ?: 'General Physician'); ?></td>
                                        <td style="padding: 0.8rem; color: var(--text-secondary);"><?php echo htmlspecialchars($d['phone'] . ' | ' . $d['email']); ?></td>
                                        <td style="padding: 0.8rem;">
                                            <span style="padding: 0.2rem 0.5rem; border-radius: 10px; font-size: 0.75rem; font-weight: bold; background: <?php echo $d['is_verified'] ? 'rgba(46, 213, 115, 0.2)' : 'rgba(255, 71, 87, 0.2)'; ?>; color: <?php echo $d['is_verified'] ? '#2ed573' : '#ff4757'; ?>;">
                                                <?php echo $d['is_verified'] ? 'Verified' : 'Unverified'; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 0.8rem;"><a href="admin_verify.php?page=1" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">Verify Account</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 0;">No matching doctors found.</p>
                <?php endif; ?>
            </div>

            <!-- 3. RMPs Results -->
            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                <h3 style="margin-top: 0; color: #a55eea; font-size: 1.1rem;"><i class="fas fa-user-nurse"></i> RMPs (<?php echo count($results['rmps']); ?>)</h3>
                <?php if (!empty($results['rmps'])): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                            <thead><tr style="border-bottom: 1px solid var(--glass-border); text-align: left;"><th style="padding: 0.8rem;">RMP ID</th><th style="padding: 0.8rem;">RMP Name</th><th style="padding: 0.8rem;">Contact</th><th style="padding: 0.8rem;">Status</th><th style="padding: 0.8rem;">Action</th></tr></thead>
                            <tbody>
                                <?php foreach($results['rmps'] as $r): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                        <td style="padding: 0.8rem;">#RMP-<?php echo $r['id']; ?></td>
                                        <td style="padding: 0.8rem; font-weight: bold;"><?php echo htmlspecialchars($r['name']); ?></td>
                                        <td style="padding: 0.8rem; color: var(--text-secondary);"><?php echo htmlspecialchars($r['phone'] . ' | ' . $r['email']); ?></td>
                                        <td style="padding: 0.8rem;">
                                            <span style="padding: 0.2rem 0.5rem; border-radius: 10px; font-size: 0.75rem; font-weight: bold; background: <?php echo $r['is_verified'] ? 'rgba(46, 213, 115, 0.2)' : 'rgba(255, 71, 87, 0.2)'; ?>; color: <?php echo $r['is_verified'] ? '#2ed573' : '#ff4757'; ?>;">
                                                <?php echo $r['is_verified'] ? 'Verified' : 'Unverified'; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 0.8rem;"><a href="admin_verify.php?page=1" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">Verify Account</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 0;">No matching RMPs found.</p>
                <?php endif; ?>
            </div>

            <!-- 4. Orders Results -->
            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                <h3 style="margin-top: 0; color: var(--secondary-color); font-size: 1.1rem;"><i class="fas fa-boxes"></i> Orders (<?php echo count($results['orders']); ?>)</h3>
                <?php if (!empty($results['orders'])): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                            <thead><tr style="border-bottom: 1px solid var(--glass-border); text-align: left;"><th style="padding: 0.8rem;">Order Ref</th><th style="padding: 0.8rem;">Patient</th><th style="padding: 0.8rem;">Amount</th><th style="padding: 0.8rem;">Gateway Tx ID</th><th style="padding: 0.8rem;">Status</th><th style="padding: 0.8rem;">Action</th></tr></thead>
                            <tbody>
                                <?php foreach($results['orders'] as $o): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                        <td style="padding: 0.8rem; font-weight: bold;">#ORD-<?php echo str_pad($o['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                        <td style="padding: 0.8rem;"><?php echo htmlspecialchars($o['patient_name']); ?></td>
                                        <td style="padding: 0.8rem; font-weight: bold; color: #2ed573;">₹<?php echo number_format($o['total_amount'], 2); ?></td>
                                        <td style="padding: 0.8rem; font-family: monospace; color: var(--primary-color);"><?php echo htmlspecialchars($o['gateway_payment_id'] ?: 'N/A'); ?></td>
                                        <td style="padding: 0.8rem; text-transform: capitalize; font-weight: bold;"><?php echo htmlspecialchars($o['status']); ?></td>
                                        <td style="padding: 0.8rem; display: flex; gap: 0.3rem;">
                                            <a href="digital_receipt.php?type=order&id=<?php echo $o['id']; ?>" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;"><i class="fas fa-receipt"></i> Receipt</a>
                                            <a href="admin_orders_management.php?search=<?php echo $o['id']; ?>" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;"><i class="fas fa-edit"></i> Manage</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 0;">No matching orders found.</p>
                <?php endif; ?>
            </div>

            <!-- 5. Payments / Wallet Transactions -->
            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                <h3 style="margin-top: 0; color: #2ed573; font-size: 1.1rem;"><i class="fas fa-wallet"></i> Payments & Wallet Transactions (<?php echo count($results['payments']); ?>)</h3>
                <?php if (!empty($results['payments'])): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                            <thead><tr style="border-bottom: 1px solid var(--glass-border); text-align: left;"><th style="padding: 0.8rem;">Transaction ID</th><th style="padding: 0.8rem;">Customer</th><th style="padding: 0.8rem;">Amount</th><th style="padding: 0.8rem;">Type</th><th style="padding: 0.8rem;">Status</th><th style="padding: 0.8rem;">Action</th></tr></thead>
                            <tbody>
                                <?php foreach($results['payments'] as $w): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                        <td style="padding: 0.8rem; font-family: monospace; font-weight: bold;"><?php echo htmlspecialchars($w['transaction_id']); ?></td>
                                        <td style="padding: 0.8rem;"><?php echo htmlspecialchars($w['customer_name']); ?></td>
                                        <td style="padding: 0.8rem; font-weight: bold; color: <?php echo $w['direction'] === 'credit' ? '#2ed573' : '#ff4757'; ?>;">
                                            <?php echo $w['direction'] === 'credit' ? '+' : '-'; ?>₹<?php echo number_format($w['amount'], 2); ?>
                                        </td>
                                        <td style="padding: 0.8rem; text-transform: uppercase; font-size: 0.78rem;"><?php echo str_replace('_', ' ', $w['transaction_type']); ?></td>
                                        <td style="padding: 0.8rem; font-weight: bold;"><?php echo ucfirst($w['status']); ?></td>
                                        <td style="padding: 0.8rem;">
                                            <a href="digital_receipt.php?type=wallet&id=<?php echo urlencode($w['transaction_id']); ?>" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;"><i class="fas fa-receipt"></i> Digital Receipt</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 0;">No matching payments found.</p>
                <?php endif; ?>
            </div>

            <!-- 6. Digital Medical Cards -->
            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                <h3 style="margin-top: 0; color: #f5a623; font-size: 1.1rem;"><i class="fas fa-id-card"></i> Digital Medical Cards (<?php echo count($results['medical_cards']); ?>)</h3>
                <?php if (!empty($results['medical_cards'])): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                            <thead><tr style="border-bottom: 1px solid var(--glass-border); text-align: left;"><th style="padding: 0.8rem;">Card Number</th><th style="padding: 0.8rem;">Patient</th><th style="padding: 0.8rem;">Amount</th><th style="padding: 0.8rem;">Validity</th><th style="padding: 0.8rem;">Status</th><th style="padding: 0.8rem;">Action</th></tr></thead>
                            <tbody>
                                <?php foreach($results['medical_cards'] as $c): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                        <td style="padding: 0.8rem; font-weight: bold; color: #f5a623;"><?php echo htmlspecialchars($c['card_number'] ?: ('DMC-APP-' . $c['id'])); ?></td>
                                        <td style="padding: 0.8rem;"><?php echo htmlspecialchars($c['patient_name']); ?></td>
                                        <td style="padding: 0.8rem; font-weight: bold;">₹<?php echo number_format($c['amount_paid'], 2); ?></td>
                                        <td style="padding: 0.8rem; color: var(--text-secondary);"><?php echo $c['valid_until'] ? date('M d, Y', strtotime($c['valid_until'])) : 'Pending'; ?></td>
                                        <td style="padding: 0.8rem; font-weight: bold; text-transform: capitalize;"><?php echo $c['status']; ?></td>
                                        <td style="padding: 0.8rem; display: flex; gap: 0.3rem;">
                                            <a href="digital_receipt.php?type=medical_card&id=<?php echo urlencode($c['card_number'] ?: $c['id']); ?>" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;"><i class="fas fa-receipt"></i> Receipt</a>
                                            <a href="admin_digital_cards.php" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;"><i class="fas fa-id-card"></i> Card Hub</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 0;">No matching Medical Cards found.</p>
                <?php endif; ?>
            </div>

            <!-- 7. Appointments -->
            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                <h3 style="margin-top: 0; color: #70a1ff; font-size: 1.1rem;"><i class="fas fa-calendar-check"></i> Appointments (<?php echo count($results['appointments']); ?>)</h3>
                <?php if (!empty($results['appointments'])): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                            <thead><tr style="border-bottom: 1px solid var(--glass-border); text-align: left;"><th style="padding: 0.8rem;">Token / Appt ID</th><th style="padding: 0.8rem;">Patient</th><th style="padding: 0.8rem;">Doctor</th><th style="padding: 0.8rem;">Date & Time</th><th style="padding: 0.8rem;">Status</th><th style="padding: 0.8rem;">Action</th></tr></thead>
                            <tbody>
                                <?php foreach($results['appointments'] as $a): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                        <td style="padding: 0.8rem; font-weight: bold; color: #70a1ff;"><?php echo htmlspecialchars($a['token_no'] ?: ('#APP-' . $a['id'])); ?></td>
                                        <td style="padding: 0.8rem;"><?php echo htmlspecialchars($a['patient_name']); ?></td>
                                        <td style="padding: 0.8rem;">Dr. <?php echo htmlspecialchars($a['doctor_name']); ?></td>
                                        <td style="padding: 0.8rem; color: var(--text-secondary);"><?php echo date('M d, Y', strtotime($a['appointment_date'])) . ' ' . date('h:i A', strtotime($a['appointment_time'])); ?></td>
                                        <td style="padding: 0.8rem; font-weight: bold; text-transform: capitalize;"><?php echo $a['status']; ?></td>
                                        <td style="padding: 0.8rem;"><a href="admin_bookings.php" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">Bookings Hub</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 0;">No matching appointments found.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Debounced Live Autocomplete Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('adminSearchInput');
    const dropdown = document.getElementById('liveSearchDropdown');
    const spinner = document.getElementById('searchSpinner');
    let debounceTimer = null;

    if (!input || !dropdown) return;

    input.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(debounceTimer);

        if (query.length < 2) {
            dropdown.style.display = 'none';
            dropdown.innerHTML = '';
            return;
        }

        if (spinner) spinner.style.display = 'inline-block';

        debounceTimer = setTimeout(function() {
            fetch('api_admin_search.php?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    if (spinner) spinner.style.display = 'none';
                    if (!data.success || data.total_results === 0) {
                        dropdown.innerHTML = '<div style="padding: 0.8rem; color: var(--text-secondary); text-align: center; font-size: 0.88rem;">No matching records found</div>';
                        dropdown.style.display = 'block';
                        return;
                    }

                    let html = '<div style="font-size: 0.8rem; color: var(--primary-color); font-weight: bold; padding: 0.4rem 0.6rem; border-bottom: 1px solid var(--glass-border);">Quick Matches (' + data.total_results + ')</div>';

                    // Patients
                    if (data.results.patients.length > 0) {
                        html += '<div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.4rem; padding: 0 0.6rem;">PATIENTS</div>';
                        data.results.patients.forEach(p => {
                            html += `<a href="${p.link}" style="display: block; padding: 0.5rem 0.6rem; color: #ffffff; text-decoration: none; border-radius: 6px;" onmouseover="this.style.background='rgba(255,255,255,0.08)'" onmouseout="this.style.background='transparent'">
                                <strong>${p.name}</strong> <span style="font-size: 0.8rem; color: var(--text-secondary);">(${p.health_id} | ${p.phone})</span>
                            </a>`;
                        });
                    }

                    // Orders
                    if (data.results.orders.length > 0) {
                        html += '<div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.4rem; padding: 0 0.6rem;">ORDERS</div>';
                        data.results.orders.forEach(o => {
                            html += `<a href="${o.receipt_url}" style="display: block; padding: 0.5rem 0.6rem; color: #ffffff; text-decoration: none; border-radius: 6px;" onmouseover="this.style.background='rgba(255,255,255,0.08)'" onmouseout="this.style.background='transparent'">
                                <strong>${o.order_ref}</strong> — ${o.patient_name} <span style="color: #2ed573; font-weight: bold;">(₹${o.amount})</span>
                            </a>`;
                        });
                    }

                    // Payments
                    if (data.results.payments.length > 0) {
                        html += '<div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.4rem; padding: 0 0.6rem;">PAYMENTS</div>';
                        data.results.payments.forEach(w => {
                            html += `<a href="${w.receipt_url}" style="display: block; padding: 0.5rem 0.6rem; color: #ffffff; text-decoration: none; border-radius: 6px;" onmouseover="this.style.background='rgba(255,255,255,0.08)'" onmouseout="this.style.background='transparent'">
                                <strong>${w.transaction_id}</strong> — ${w.customer_name} <span style="color: #50e3c2;">(₹${w.amount})</span>
                            </a>`;
                        });
                    }

                    html += `<div style="text-align: center; padding: 0.6rem 0 0.2rem; border-top: 1px solid var(--glass-border); margin-top: 0.4rem;">
                        <a href="admin_search.php?q=${encodeURIComponent(query)}" style="color: var(--primary-color); font-size: 0.85rem; font-weight: bold; text-decoration: none;">View All ${data.total_results} Search Results →</a>
                    </div>`;

                    dropdown.innerHTML = html;
                    dropdown.style.display = 'block';
                })
                .catch(err => {
                    if (spinner) spinner.style.display = 'none';
                });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
