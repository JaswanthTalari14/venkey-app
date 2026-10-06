<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$results = [
    'patients' => [],
    'doctors' => [],
    'orders' => [],
    'wallets' => [],
    'appointments' => [],
    'referrals' => []
];

if (!empty($q)) {
    $search_param = "%$q%";

    // 1. Search Users (Patients, Doctors, RMPs)
    $u_stmt = $conn->prepare("SELECT id, name, email, phone, role, specialization FROM users WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? LIMIT 10");
    $u_stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $u_stmt->execute();
    $u_res = $u_stmt->get_result();
    while($row = $u_res->fetch_assoc()) {
        if ($row['role'] === 'doctor') $results['doctors'][] = $row;
        else $results['patients'][] = $row;
    }

    // 2. Search Orders
    $o_stmt = $conn->prepare("SELECT o.*, u.name as patient_name FROM orders o JOIN users u ON o.patient_id = u.id WHERE CAST(o.id AS CHAR) LIKE ? OR o.gateway_payment_id LIKE ? OR o.gateway_order_id LIKE ? LIMIT 10");
    $o_stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $o_stmt->execute();
    $o_res = $o_stmt->get_result();
    while($row = $o_res->fetch_assoc()) $results['orders'][] = $row;

    // 3. Search Wallet Transactions
    $w_stmt = $conn->prepare("SELECT wt.*, u.name as customer_name FROM wallet_transactions wt JOIN users u ON wt.customer_id = u.id WHERE wt.transaction_id LIKE ? OR wt.payment_id LIKE ? LIMIT 10");
    $w_stmt->bind_param("ss", $search_param, $search_param);
    $w_stmt->execute();
    $w_res = $w_stmt->get_result();
    while($row = $w_res->fetch_assoc()) $results['wallets'][] = $row;

    // 4. Search Appointments
    $a_stmt = $conn->prepare("SELECT a.*, p.name as patient_name, d.name as doctor_name FROM appointments a JOIN users p ON a.patient_id = p.id JOIN users d ON a.doctor_id = d.id WHERE p.name LIKE ? OR d.name LIKE ? OR a.token_no LIKE ? LIMIT 10");
    $a_stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $a_stmt->execute();
    $a_res = $a_stmt->get_result();
    while($row = $a_res->fetch_assoc()) $results['appointments'][] = $row;
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
            <li><a href="admin_search.php" class="active"><i class="fas fa-search"></i> Global Search</a></li>
            <li><a href="admin_orders_management.php"><i class="fas fa-boxes"></i> Orders</a></li>
            <li><a href="admin_wallets.php"><i class="fas fa-wallet"></i> Wallets</a></li>
            <li><a href="admin_reconciliation.php"><i class="fas fa-calculator"></i> Reconciliation</a></li>
            <li><a href="admin_audit.php"><i class="fas fa-clipboard-list"></i> Audit Logs</a></li>
            <li><a href="admin_risk.php"><i class="fas fa-shield-alt"></i> Risk Center</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Users</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <h2><i class="fas fa-search" style="color: var(--primary-color);"></i> Intelligent Admin Global Search</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Search patients, doctors, orders, transaction IDs, wallet references, and appointments across the platform.</p>

        <!-- Search Bar -->
        <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2rem;">
            <form method="GET" action="" style="display: flex; gap: 1rem;">
                <input type="text" name="q" class="form-control" placeholder="Enter Patient Name, Email, Phone, Order ID, Gateway Tx ID, Doctor..." value="<?php echo htmlspecialchars($q); ?>" required style="font-size: 1rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.7rem 1.5rem;"><i class="fas fa-search"></i> Search</button>
            </form>
        </div>

        <?php if (!empty($q)): ?>
            <!-- Results Container -->
            <!-- 1. Patients -->
            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                <h3 style="margin-top: 0; color: var(--primary-color);"><i class="fas fa-users"></i> Users & Patients (<?php echo count($results['patients']); ?>)</h3>
                <?php if (!empty($results['patients'])): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead><tr style="border-bottom: 1px solid var(--glass-border);"><th style="padding: 0.8rem;">ID</th><th style="padding: 0.8rem;">Name</th><th style="padding: 0.8rem;">Contact</th><th style="padding: 0.8rem;">Role</th></tr></thead>
                            <tbody>
                                <?php foreach($results['patients'] as $p): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                        <td style="padding: 0.8rem;">#<?php echo $p['id']; ?></td>
                                        <td style="padding: 0.8rem; font-weight: bold;"><?php echo htmlspecialchars($p['name']); ?></td>
                                        <td style="padding: 0.8rem; color: var(--text-secondary);"><?php echo htmlspecialchars($p['email'] . ' | ' . $p['phone']); ?></td>
                                        <td style="padding: 0.8rem; text-transform: uppercase; font-size: 0.8rem; font-weight: bold; color: var(--secondary-color);"><?php echo $p['role']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">No matching users found.</p>
                <?php endif; ?>
            </div>

            <!-- 2. Orders -->
            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                <h3 style="margin-top: 0; color: var(--secondary-color);"><i class="fas fa-boxes"></i> Orders (<?php echo count($results['orders']); ?>)</h3>
                <?php if (!empty($results['orders'])): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead><tr style="border-bottom: 1px solid var(--glass-border);"><th style="padding: 0.8rem;">Order ID</th><th style="padding: 0.8rem;">Patient</th><th style="padding: 0.8rem;">Amount</th><th style="padding: 0.8rem;">Gateway Tx ID</th><th style="padding: 0.8rem;">Status</th></tr></thead>
                            <tbody>
                                <?php foreach($results['orders'] as $o): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                        <td style="padding: 0.8rem; font-weight: bold;">#ORD-<?php echo $o['id']; ?></td>
                                        <td style="padding: 0.8rem;"><?php echo htmlspecialchars($o['patient_name']); ?></td>
                                        <td style="padding: 0.8rem; font-weight: bold; color: #2ed573;">₹<?php echo $o['total_amount']; ?></td>
                                        <td style="padding: 0.8rem; font-family: monospace; color: var(--primary-color);"><?php echo htmlspecialchars($o['gateway_payment_id'] ?: 'N/A'); ?></td>
                                        <td style="padding: 0.8rem; text-transform: capitalize; font-weight: bold;"><?php echo $o['status']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">No matching orders found.</p>
                <?php endif; ?>
            </div>

            <!-- 3. Wallet Transactions -->
            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                <h3 style="margin-top: 0; color: var(--accent);"><i class="fas fa-wallet"></i> Wallet Transactions (<?php echo count($results['wallets']); ?>)</h3>
                <?php if (!empty($results['wallets'])): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead><tr style="border-bottom: 1px solid var(--glass-border);"><th style="padding: 0.8rem;">Tx ID</th><th style="padding: 0.8rem;">Customer</th><th style="padding: 0.8rem;">Amount</th><th style="padding: 0.8rem;">Type</th><th style="padding: 0.8rem;">Action</th></tr></thead>
                            <tbody>
                                <?php foreach($results['wallets'] as $w): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                        <td style="padding: 0.8rem; font-family: monospace; font-weight: bold;"><?php echo htmlspecialchars($w['transaction_id']); ?></td>
                                        <td style="padding: 0.8rem;"><?php echo htmlspecialchars($w['customer_name']); ?></td>
                                        <td style="padding: 0.8rem; font-weight: bold; color: <?php echo $w['direction'] === 'credit' ? '#2ed573' : '#ff4757'; ?>;"><?php echo $w['direction'] === 'credit' ? '+' : '-'; ?>₹<?php echo $w['amount']; ?></td>
                                        <td style="padding: 0.8rem; text-transform: uppercase; font-size: 0.8rem;"><?php echo str_replace('_', ' ', $w['transaction_type']); ?></td>
                                        <td style="padding: 0.8rem;"><a href="wallet_receipt.php?tx_id=<?php echo $w['transaction_id']; ?>" target="_blank" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">Receipt</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">No matching wallet transactions found.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
