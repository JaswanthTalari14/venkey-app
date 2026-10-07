<?php
require_once 'config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$success_msg = '';
$error_msg = '';

$settings = get_medical_card_settings($conn);

// Handle Admin Save Settings Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_card_settings'])) {
    $price = max(1.0, floatval($_POST['card_price']));
    $months = max(1, intval($_POST['validity_months']));
    $consult_disc = max(0.0, floatval($_POST['consultation_discount_percent']));
    $med_disc = max(0.0, floatval($_POST['medicine_discount_percent']));

    $stmt = $conn->prepare("UPDATE digital_medical_card_settings SET card_price = ?, validity_months = ?, consultation_discount_percent = ?, medicine_discount_percent = ? WHERE id = 1");
    $stmt->bind_param("didd", $price, $months, $consult_disc, $med_disc);
    if ($stmt->execute()) {
        $success_msg = "Digital Medical Card settings updated successfully!";
        $settings = get_medical_card_settings($conn);
    } else {
        $error_msg = "Failed to update settings.";
    }
}

// Search & Filter Parameters
$status_filter = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where_clauses = ["1=1"];
if ($status_filter !== 'all') {
    $where_clauses[] = "c.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if (!empty($search)) {
    $search_esc = $conn->real_escape_string($search);
    $where_clauses[] = "(p.name LIKE '%$search_esc%' OR p.phone LIKE '%$search_esc%' OR c.card_number LIKE '%$search_esc%' OR c.gateway_payment_id LIKE '%$search_esc%')";
}
$where_sql = implode(" AND ", $where_clauses);

// Fetch Digital Medical Cards applications
$cards_query = $conn->query("
    SELECT c.*, p.name as patient_name, p.phone as patient_phone, p.email as patient_email 
    FROM digital_medical_cards c
    JOIN users p ON c.patient_id = p.id
    WHERE $where_sql
    ORDER BY CASE WHEN c.status = 'pending' THEN 1 ELSE 2 END, c.id DESC
");

// Count Status Badges
$cnt_pending = $conn->query("SELECT COUNT(*) as cnt FROM digital_medical_cards WHERE status = 'pending'")->fetch_assoc()['cnt'];
$cnt_active = $conn->query("SELECT COUNT(*) as cnt FROM digital_medical_cards WHERE status = 'active'")->fetch_assoc()['cnt'];
$cnt_rejected = $conn->query("SELECT COUNT(*) as cnt FROM digital_medical_cards WHERE status = 'rejected'")->fetch_assoc()['cnt'];
$cnt_expired = $conn->query("SELECT COUNT(*) as cnt FROM digital_medical_cards WHERE status = 'expired'")->fetch_assoc()['cnt'];
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
            <li><a href="admin_digital_cards.php" class="active"><i class="fas fa-id-card"></i> Medical Card Approvals</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="admin_verify.php"><i class="fas fa-user-md"></i> Verify Doctors & RMPs</a></li>
            <li><a href="admin_bookings.php"><i class="fas fa-calendar-check"></i> All Bookings</a></li>
            <li><a href="admin_orders_management.php"><i class="fas fa-boxes"></i> Order Management</a></li>
            <li><a href="admin_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="admin_referrals.php"><i class="fas fa-gift"></i> Referral Management</a></li>
            <li><a href="admin_referral_settings.php"><i class="fas fa-sliders-h"></i> Referral Settings</a></li>
            <li><a href="admin_wallets.php"><i class="fas fa-wallet"></i> Wallet Management</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback & Complaints</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <h2>Digital Medical Card Approvals</h2>
                <p style="color: var(--text-secondary); margin-top: 0.2rem;">Review ₹50 card applications, approve 5-month memberships, and manage discount settings.</p>
            </div>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <span style="background: rgba(245, 166, 35, 0.15); color: #f5a623; border: 1px solid #f5a623; padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.82rem; font-weight: 700;">
                    <i class="fas fa-clock"></i> Pending: <?php echo $cnt_pending; ?>
                </span>
                <span style="background: rgba(46, 213, 115, 0.15); color: #2ed573; border: 1px solid #2ed573; padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.82rem; font-weight: 700;">
                    <i class="fas fa-check-circle"></i> Active: <?php echo $cnt_active; ?>
                </span>
            </div>
        </div>

        <?php if ($success_msg): ?>
            <div style="color: #2ed573; margin-bottom: 1.5rem; padding: 1rem; background: rgba(46, 213, 115, 0.12); border-radius: 10px; border-left: 4px solid #2ed573;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div style="color: #ff4757; margin-bottom: 1.5rem; padding: 1rem; background: rgba(255, 71, 87, 0.12); border-radius: 10px; border-left: 4px solid #ff4757;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <!-- FILTER & SEARCH BAR -->
        <div class="glass-panel" style="padding: 1.2rem; border-radius: 16px; margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: center;">
            <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 1rem; width: 100%;">
                <div style="flex: 2; min-width: 220px;">
                    <input type="text" name="search" class="form-control" placeholder="Search by Patient Name, Phone, Card Number, TX ID..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending Approval (<?php echo $cnt_pending; ?>)</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Approved / Active (<?php echo $cnt_active; ?>)</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected (<?php echo $cnt_rejected; ?>)</option>
                        <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired (<?php echo $cnt_expired; ?>)</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1.2rem; font-size: 0.88rem;">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="admin_digital_cards.php" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.88rem;">Reset</a>
            </form>
        </div>

        <!-- APPLICATIONS TABLE -->
        <div class="glass-panel" style="overflow-x: auto; padding: 1.2rem; border-radius: 16px; margin-bottom: 2rem;">
            <h3 style="margin-bottom: 1rem; color: #ffffff; font-size: 1.1rem;"><i class="fas fa-list"></i> Card Applications & Status</h3>
            
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 0.85rem;">ID / Card No</th>
                        <th style="padding: 0.85rem;">Patient Details</th>
                        <th style="padding: 0.85rem;">Amount & Payment</th>
                        <th style="padding: 0.85rem;">Application Date</th>
                        <th style="padding: 0.85rem;">Validity Period</th>
                        <th style="padding: 0.85rem;">Status</th>
                        <th style="padding: 0.85rem; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($cards_query && $cards_query->num_rows > 0): ?>
                        <?php while ($c = $cards_query->fetch_assoc()): ?>
                            <?php
                                $c_st = strtolower($c['status']);
                                $badge_bg = 'rgba(245, 166, 35, 0.15)';
                                $badge_color = '#f5a623';
                                $badge_border = '#f5a623';
                                $badge_text = '🟡 Pending';

                                if ($c_st === 'active') {
                                    $badge_bg = 'rgba(46, 213, 115, 0.15)';
                                    $badge_color = '#2ed573';
                                    $badge_border = '#2ed573';
                                    $badge_text = '🟢 Active';
                                } elseif ($c_st === 'rejected') {
                                    $badge_bg = 'rgba(255, 71, 87, 0.15)';
                                    $badge_color = '#ff4757';
                                    $badge_border = '#ff4757';
                                    $badge_text = '🔴 Rejected';
                                } elseif ($c_st === 'expired') {
                                    $badge_bg = 'rgba(148, 163, 184, 0.15)';
                                    $badge_color = '#94a3b8';
                                    $badge_border = '#94a3b8';
                                    $badge_text = '⚪ Expired';
                                }
                            ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 0.85rem;">
                                    <div style="font-weight: 700; color: var(--primary-color);">#DMC-APP-<?php echo str_pad($c['id'], 4, '0', STR_PAD_LEFT); ?></div>
                                    <?php if (!empty($c['card_number'])): ?>
                                        <div style="font-size: 0.82rem; font-weight: 800; color: var(--secondary-color); font-family: monospace; margin-top: 0.2rem;">
                                            <?php echo htmlspecialchars($c['card_number']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 0.85rem;">
                                    <div style="font-weight: 700; color: #ffffff;"><?php echo htmlspecialchars($c['patient_name']); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary);"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($c['patient_phone']); ?></div>
                                </td>

                                <td style="padding: 0.85rem;">
                                    <div style="font-weight: 800; color: var(--secondary-color);">₹<?php echo number_format($c['amount_paid'], 2); ?></div>
                                    <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 0.1rem;">
                                        <?php echo htmlspecialchars($c['payment_method']); ?> (<?php echo htmlspecialchars($c['payment_status']); ?>)
                                    </div>
                                    <?php if (!empty($c['gateway_payment_id'])): ?>
                                        <div style="font-size: 0.72rem; color: var(--primary-color); font-family: monospace;"><?php echo htmlspecialchars($c['gateway_payment_id']); ?></div>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 0.85rem; font-size: 0.85rem; color: var(--text-primary);">
                                    <?php echo date('d M Y, h:i A', strtotime($c['created_at'])); ?>
                                </td>

                                <td style="padding: 0.85rem; font-size: 0.82rem;">
                                    <?php if (!empty($c['valid_from']) && !empty($c['valid_until'])): ?>
                                        <div style="color: #ffffff; font-weight: 600;"><?php echo date('d M Y', strtotime($c['valid_from'])); ?> to <?php echo date('d M Y', strtotime($c['valid_until'])); ?></div>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary); italic;">Not Activated Yet</span>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 0.85rem;">
                                    <span style="background: <?php echo $badge_bg; ?>; color: <?php echo $badge_color; ?>; border: 1px solid <?php echo $badge_border; ?>; padding: 0.25rem 0.65rem; border-radius: 12px; font-size: 0.78rem; font-weight: 700;">
                                        <?php echo $badge_text; ?>
                                    </span>
                                </td>

                                <td style="padding: 0.85rem; text-align: right;">
                                    <?php if ($c_st === 'pending'): ?>
                                        <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.78rem; margin-right: 0.3rem;" onclick="approveCardApplication(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['patient_name'])); ?>')">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button type="button" class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.78rem; color: #ff4757; border-color: rgba(255, 71, 87, 0.4);" onclick="rejectCardApplication(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['patient_name'])); ?>')">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php elseif ($c_st === 'active'): ?>
                                        <span style="font-size: 0.8rem; color: #2ed573; font-weight: 600;"><i class="fas fa-check-circle"></i> Active</span>
                                    <?php elseif ($c_st === 'rejected'): ?>
                                        <span style="font-size: 0.8rem; color: #ff4757; font-weight: 600;"><i class="fas fa-times-circle"></i> Rejected</span>
                                    <?php else: ?>
                                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Expired</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                                No Digital Medical Card applications found matching filter criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- SETTINGS PANEL -->
        <div class="glass-panel" style="padding: 1.5rem; border-radius: 20px; max-width: 650px;">
            <h3 style="margin-bottom: 0.5rem; color: #ffffff; font-size: 1.15rem;"><i class="fas fa-sliders-h" style="color: var(--secondary-color);"></i> Digital Medical Card Settings</h3>
            <p style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 1.25rem;">Configure application purchase price, validity duration, and discount percentages.</p>

            <form method="POST" action="">
                <input type="hidden" name="save_card_settings" value="1">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label style="font-size: 0.85rem; font-weight: 700;">Card Purchase Price (₹)</label>
                        <input type="number" step="0.5" name="card_price" class="form-control" required value="<?php echo (float)$settings['card_price']; ?>">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.85rem; font-weight: 700;">Validity Duration (Months)</label>
                        <input type="number" name="validity_months" class="form-control" required min="1" max="60" value="<?php echo (int)$settings['validity_months']; ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label style="font-size: 0.85rem; font-weight: 700;">Consultation Discount (%)</label>
                        <input type="number" step="0.5" name="consultation_discount_percent" class="form-control" required min="0" max="100" value="<?php echo (float)$settings['consultation_discount_percent']; ?>">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.85rem; font-weight: 700;">Medicine Discount (%)</label>
                        <input type="number" step="0.5" name="medicine_discount_percent" class="form-control" required min="0" max="100" value="<?php echo (float)$settings['medicine_discount_percent']; ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem;">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </form>
        </div>
    </main>
</div>

<script>
function approveCardApplication(appId, patientName) {
    if (!confirm('Are you sure you want to APPROVE Digital Medical Card for ' + patientName + '?\n\nThis will activate 5-month discounts and generate card number.')) return;

    fetch('api_admin_medical_card_action.php?action=approve_card', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ app_id: appId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert('Approval Error: ' + data.message);
        }
    })
    .catch(err => {
        alert('Network error. Please try again.');
    });
}

function rejectCardApplication(appId, patientName) {
    var reason = prompt('Reason for rejecting Medical Card application for ' + patientName + ':', 'Information incomplete or payment verification pending');
    if (reason === null) return;

    fetch('api_admin_medical_card_action.php?action=reject_card', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ app_id: appId, reason: reason })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert('Rejection Error: ' + data.message);
        }
    })
    .catch(err => {
        alert('Network error. Please try again.');
    });
}
</script>

<?php include 'includes/footer.php'; ?>
