<?php
require_once 'config.php';
require_once 'includes/security_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$admin_id = (int)$_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

$wa_settings = get_whatsapp_support_settings($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_whatsapp_settings'])) {
    $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
    $whatsapp_number = preg_replace('/[^0-9]/', '', trim($_POST['whatsapp_number'] ?? ''));
    $display_name = trim($_POST['display_name'] ?? 'MedicalAk Support');
    $availability_type = trim($_POST['availability_type'] ?? 'auto');
    $start_time = trim($_POST['start_time'] ?? '09:00:00');
    $end_time = trim($_POST['end_time'] ?? '21:00:00');
    $working_days_arr = isset($_POST['working_days']) ? (array)$_POST['working_days'] : ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    $working_days_str = implode(',', array_map('trim', $working_days_arr));
    $offline_message = trim($_POST['offline_message'] ?? 'Our WhatsApp support team is currently unavailable. Support hours: 9:00 AM – 9:00 PM.');

    if (empty($whatsapp_number)) {
        $error_msg = "Please enter a valid WhatsApp support phone number.";
    } else {
        $stmt = $conn->prepare("
            UPDATE whatsapp_settings 
            SET is_enabled = ?, 
                whatsapp_number = ?, 
                display_name = ?, 
                availability_type = ?, 
                start_time = ?, 
                end_time = ?, 
                working_days = ?, 
                offline_message = ? 
            WHERE id = 1
        ");
        $stmt->bind_param("isssssss", $is_enabled, $whatsapp_number, $display_name, $availability_type, $start_time, $end_time, $working_days_str, $offline_message);

        if ($stmt->execute()) {
            log_admin_activity($conn, $admin_id, "WhatsApp Settings Updated", "whatsapp_settings", 1, "Updated WhatsApp support number ($whatsapp_number) and availability mode ($availability_type)");
            $success_msg = "WhatsApp Support settings updated successfully!";
            $wa_settings = get_whatsapp_support_settings($conn);
        } else {
            $error_msg = "Failed to update WhatsApp settings.";
        }
    }
}

$is_currently_online = is_whatsapp_support_available($conn);

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
            <li><a href="admin_support.php"><i class="fas fa-headset"></i> Support Tickets</a></li>
            <li><a href="admin_whatsapp_settings.php" class="active"><i class="fab fa-whatsapp"></i> WhatsApp Settings</a></li>
            <li><a href="admin_search.php"><i class="fas fa-search"></i> Global Search</a></li>
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
        <div style="max-width: 700px; margin: 0 auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <h2 style="margin: 0; color: var(--text-primary);"><i class="fab fa-whatsapp" style="color: #25D366;"></i> WhatsApp Support Settings</h2>
                    <p style="color: var(--text-secondary); margin-top: 0.3rem;">Configure official WhatsApp number, working hours, and availability status.</p>
                </div>

                <div>
                    <?php if ($is_currently_online): ?>
                        <span style="background: rgba(46, 213, 115, 0.2); color: #2ed573; border: 1px solid #2ed573; padding: 0.4rem 1rem; border-radius: 20px; font-weight: bold; font-size: 0.85rem;">
                            🟢 Live Status: Available Now
                        </span>
                    <?php else: ?>
                        <span style="background: rgba(255, 71, 87, 0.2); color: #ff4757; border: 1px solid #ff4757; padding: 0.4rem 1rem; border-radius: 20px; font-weight: bold; font-size: 0.85rem;">
                            🔴 Live Status: Offline
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($success_msg): ?>
                <div style="background: rgba(46, 213, 115, 0.15); border: 1px solid #2ed573; color: #2ed573; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div style="background: rgba(255, 71, 87, 0.15); border: 1px solid #ff4757; color: #ff4757; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <div class="glass-panel" style="padding: 2rem; border-top: 4px solid #25D366;">
                <form method="POST">
                    
                    <!-- Enable Switch -->
                    <div style="margin-bottom: 1.5rem; background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 10px; border: 1px solid var(--glass-border);">
                        <label style="display: flex; align-items: center; gap: 0.8rem; font-weight: bold; color: var(--text-primary); cursor: pointer; font-size: 1rem;">
                            <input type="checkbox" name="is_enabled" value="1" <?php echo $wa_settings['is_enabled'] ? 'checked' : ''; ?> style="width: 20px; height: 20px;">
                            Enable WhatsApp Support Channel
                        </label>
                        <small style="color: var(--text-secondary); display: block; margin-top: 0.4rem; margin-left: 2.1rem;">
                            When enabled, customers will see the WhatsApp Support button on the Home page, Support Center, and Order/Payment pages.
                        </small>
                    </div>

                    <!-- WhatsApp Number -->
                    <div style="margin-bottom: 1.2rem;">
                        <label style="display: block; font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 0.4rem; font-weight: bold;">
                            Official WhatsApp Support Phone Number (With Country Code):
                        </label>
                        <input type="text" name="whatsapp_number" class="form-control" placeholder="e.g. 919876543210 (digits only)" value="<?php echo htmlspecialchars($wa_settings['whatsapp_number']); ?>" required style="font-size: 0.95rem; padding: 0.6rem 1rem;">
                        <small style="color: var(--text-secondary); font-size: 0.8rem;">Example: For India (+91), enter <code>919876543210</code> without spaces or <code>+</code> sign.</small>
                    </div>

                    <!-- Display Name -->
                    <div style="margin-bottom: 1.2rem;">
                        <label style="display: block; font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 0.4rem; font-weight: bold;">
                            Support Display Name:
                        </label>
                        <input type="text" name="display_name" class="form-control" value="<?php echo htmlspecialchars($wa_settings['display_name']); ?>" required style="font-size: 0.95rem; padding: 0.6rem 1rem;">
                    </div>

                    <!-- Availability Mode -->
                    <div style="margin-bottom: 1.2rem;">
                        <label style="display: block; font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 0.4rem; font-weight: bold;">
                            Availability Mode:
                        </label>
                        <select name="availability_type" class="form-control" style="font-size: 0.95rem; padding: 0.6rem 1rem;">
                            <option value="auto" <?php echo $wa_settings['availability_type'] === 'auto' ? 'selected' : ''; ?>>🕒 Auto Schedule (Based on Working Hours)</option>
                            <option value="online" <?php echo $wa_settings['availability_type'] === 'online' ? 'selected' : ''; ?>>🟢 Force Online (24/7 Available)</option>
                            <option value="offline" <?php echo $wa_settings['availability_type'] === 'offline' ? 'selected' : ''; ?>>🔴 Force Offline (Maintenance / Off-duty)</option>
                        </select>
                    </div>

                    <!-- Start & End Hours -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.2rem;">
                        <div>
                            <label style="display: block; font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 0.4rem; font-weight: bold;">Support Start Time:</label>
                            <input type="time" name="start_time" class="form-control" value="<?php echo htmlspecialchars($wa_settings['start_time']); ?>" style="font-size: 0.95rem; padding: 0.6rem 1rem;">
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 0.4rem; font-weight: bold;">Support End Time:</label>
                            <input type="time" name="end_time" class="form-control" value="<?php echo htmlspecialchars($wa_settings['end_time']); ?>" style="font-size: 0.95rem; padding: 0.6rem 1rem;">
                        </div>
                    </div>

                    <!-- Working Days -->
                    <div style="margin-bottom: 1.2rem;">
                        <label style="display: block; font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 0.5rem; font-weight: bold;">Working Days:</label>
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <?php
                            $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                            foreach ($days as $day) {
                                $checked = in_array($day, $wa_settings['working_days']) ? 'checked' : '';
                                echo "<label style=\"font-size: 0.88rem; color: var(--text-primary); cursor: pointer;\"><input type=\"checkbox\" name=\"working_days[]\" value=\"$day\" $checked> $day</label>";
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Offline Custom Message -->
                    <div style="margin-bottom: 1.8rem;">
                        <label style="display: block; font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 0.4rem; font-weight: bold;">
                            Offline Notice Message:
                        </label>
                        <textarea name="offline_message" class="form-control" rows="3" style="font-size: 0.9rem; padding: 0.6rem 1rem;"><?php echo htmlspecialchars($wa_settings['offline_message']); ?></textarea>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <a href="admin_support.php" class="btn btn-outline">Back to Tickets</a>
                        <button type="submit" name="save_whatsapp_settings" value="1" class="btn" style="background: #25D366; color: #ffffff; padding: 0.75rem 2rem; font-weight: bold; font-size: 1rem; border-radius: 10px;">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
