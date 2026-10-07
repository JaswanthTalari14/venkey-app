<?php
require_once 'config.php';
require_once 'includes/notification_functions.php';
require_once 'includes/security_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Customer';

$category_param = isset($_GET['category']) ? trim($_GET['category']) : '';
$related_type_param = isset($_GET['related_type']) ? trim($_GET['related_type']) : '';
$related_id_param = isset($_GET['related_id']) ? trim($_GET['related_id']) : '';

$success_ticket = null;
$error_msg = '';

// Fetch Customer's Own Related Records for Selector
// 1. Orders
$my_orders = [];
$o_res = $conn->query("SELECT id, total_amount, status, created_at FROM orders WHERE patient_id = $user_id ORDER BY id DESC LIMIT 15");
if ($o_res) {
    while ($r = $o_res->fetch_assoc()) $my_orders[] = $r;
}

// 2. Wallet Top-ups / Txs
$my_wallets = [];
$w_res = $conn->query("SELECT id, topup_id, amount, status FROM wallet_topups WHERE customer_id = $user_id ORDER BY id DESC LIMIT 15");
if ($w_res) {
    while ($r = $w_res->fetch_assoc()) $my_wallets[] = $r;
}

// 3. Digital Medical Cards
$my_cards = [];
$c_res = $conn->query("SELECT id, card_number, status FROM digital_medical_cards WHERE patient_id = $user_id ORDER BY id DESC LIMIT 5");
if ($c_res) {
    while ($r = $c_res->fetch_assoc()) $my_cards[] = $r;
}

// 4. Appointments
$my_appts = [];
$a_res = $conn->query("SELECT a.id, a.token_no, a.appointment_date, d.name as doctor_name FROM appointments a JOIN users d ON a.doctor_id = d.id WHERE a.patient_id = $user_id ORDER BY a.id DESC LIMIT 10");
if ($a_res) {
    while ($r = $a_res->fetch_assoc()) $my_appts[] = $r;
}

// Handle Form POST Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket_submit'])) {
    $category = trim($_POST['category'] ?? 'Other');
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = trim($_POST['priority'] ?? 'normal');
    $related_entity_type = trim($_POST['related_entity_type'] ?? '');
    $related_entity_id = trim($_POST['related_entity_id'] ?? '');

    if (empty($subject) || empty($description)) {
        $error_msg = "Please enter a ticket subject and detailed description.";
    } else {
        // Idempotency check: prevent double click within 30 seconds
        $chk_dup = $conn->prepare("SELECT ticket_number FROM support_tickets WHERE customer_id = ? AND subject = ? AND created_at >= NOW() - INTERVAL 30 SECOND");
        $chk_dup->bind_param("is", $user_id, $subject);
        $chk_dup->execute();
        $dup_res = $chk_dup->get_result();

        if ($dup_res && $dup_res->num_rows > 0) {
            $error_msg = "Duplicate ticket submission detected. Please wait a moment before submitting again.";
        } else {
            // Handle Attachment Upload
            $attachment_path = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['attachment'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

                if (in_array($ext, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
                    $upload_dir = 'uploads/support/';
                    if (!file_exists($upload_dir)) {
                        @mkdir($upload_dir, 0777, true);
                    }
                    $filename = 'SUP_ATTACH_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $target_path = $upload_dir . $filename;

                    if (move_uploaded_file($file['tmp_name'], $target_path)) {
                        $attachment_path = $target_path;
                    }
                } else {
                    $error_msg = "Invalid attachment file. Allowed formats: JPG, PNG, WEBP, PDF up to 5MB.";
                }
            }

            if (empty($error_msg)) {
                // Generate Unique Ticket Number SUP-2026-XXXXX
                $seq = rand(10000, 99999);
                $ticket_num = 'SUP-' . date('Y') . '-' . $seq;

                $stmt = $conn->prepare("
                    INSERT INTO support_tickets 
                    (ticket_number, customer_id, category, subject, description, priority, status, related_entity_type, related_entity_id, attachment_path)
                    VALUES (?, ?, ?, ?, ?, ?, 'open', ?, ?, ?)
                ");
                $stmt->bind_param("sisssssss", $ticket_num, $user_id, $category, $subject, $description, $priority, $related_entity_type, $related_entity_id, $attachment_path);

                if ($stmt->execute()) {
                    $ticket_id = $conn->insert_id;

                    // Insert Initial Reply / Message
                    $reply_stmt = $conn->prepare("
                        INSERT INTO support_ticket_replies 
                        (ticket_id, sender_id, sender_role, message, attachment_path)
                        VALUES (?, ?, 'patient', ?, ?)
                    ");
                    $reply_stmt->bind_param("iiss", $ticket_id, $user_id, $description, $attachment_path);
                    $reply_stmt->execute();

                    // Notify Admin Users
                    $admin_users = $conn->query("SELECT id FROM users WHERE role = 'admin'");
                    if ($admin_users) {
                        while ($adm = $admin_users->fetch_assoc()) {
                            create_notification(
                                (int)$adm['id'],
                                "New Support Ticket #$ticket_num",
                                "Customer $user_name created support ticket ($category): $subject",
                                'system',
                                'ticket',
                                (string)$ticket_id,
                                'admin'
                            );
                        }
                    }

                    // Log Audit Event
                    log_admin_activity($conn, $user_id, "Ticket Created", "support_tickets", $ticket_id, "Customer created support ticket #$ticket_num ($category)");

                    $success_ticket = [
                        'id' => $ticket_id,
                        'ticket_number' => $ticket_num,
                        'subject' => $subject
                    ];
                } else {
                    $error_msg = "Failed to create support ticket. Please try again.";
                }
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Patient Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="patient_dashboard.php"><i class="fas fa-columns"></i> Dashboard</a></li>
            <li><a href="customer_support.php"><i class="fas fa-headset"></i> Customer Support</a></li>
            <li><a href="create_ticket.php" class="active"><i class="fas fa-plus-circle"></i> Create Ticket</a></li>
            <li><a href="my_tickets.php"><i class="fas fa-ticket-alt"></i> My Tickets</a></li>
            <li><a href="your_orders.php"><i class="fas fa-shopping-bag"></i> My Orders</a></li>
            <li><a href="my_wallet.php"><i class="fas fa-wallet"></i> My Wallet</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <div style="max-width: 700px; margin: 0 auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2 style="margin: 0; color: var(--text-primary);"><i class="fas fa-ticket-alt" style="color: var(--primary-color);"></i> Create Support Ticket</h2>
                    <p style="color: var(--text-secondary); margin-top: 0.3rem;">Submit your query to our healthcare support team for fast resolution.</p>
                </div>
                <a href="customer_support.php" class="btn btn-outline" style="font-size: 0.85rem;"><i class="fas fa-arrow-left"></i> Back to Support</a>
            </div>

            <?php if ($success_ticket): ?>
                <div class="glass-panel" style="padding: 2.5rem; text-align: center; border-top: 5px solid #2ed573; background: #121826;">
                    <i class="fas fa-check-circle" style="font-size: 3.5rem; color: #2ed573; margin-bottom: 1rem; display: block;"></i>
                    <h3 style="color: var(--text-primary); margin-bottom: 0.5rem; font-size: 1.5rem;">Ticket Created Successfully! 🎉</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 1rem;">Your support ticket number is:</p>
                    <div style="font-size: 1.8rem; font-weight: 800; color: var(--primary-color); font-family: monospace; margin-bottom: 1.5rem;">
                        <?php echo htmlspecialchars($success_ticket['ticket_number']); ?>
                    </div>
                    <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 2rem;">
                        Our support team will review your request and reply shortly. You will receive notifications on updates.
                    </p>
                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                        <a href="ticket_view.php?id=<?php echo $success_ticket['id']; ?>" class="btn btn-primary" style="padding: 0.75rem 1.5rem;"><i class="fas fa-comments"></i> View Ticket Conversation</a>
                        <a href="my_tickets.php" class="btn btn-outline" style="padding: 0.75rem 1.5rem;"><i class="fas fa-list"></i> My Support Tickets</a>
                    </div>
                </div>
            <?php else: ?>

                <?php if ($error_msg): ?>
                    <div style="background: rgba(255, 71, 87, 0.15); border: 1px solid #ff4757; color: #ff4757; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_msg); ?>
                    </div>
                <?php endif; ?>

                <div class="glass-panel" style="padding: 2rem;">
                    <form method="POST" enctype="multipart/form-data" id="ticketForm">
                        <!-- Category Selector -->
                        <div style="margin-bottom: 1.2rem;">
                            <label style="display: block; font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 0.4rem; font-weight: bold;">
                                <i class="fas fa-folder" style="color: var(--primary-color);"></i> Support Category:
                            </label>
                            <select name="category" class="form-control" required style="font-size: 0.95rem; padding: 0.6rem 1rem;">
                                <?php
                                $categories = ['Payment', 'Order', 'Wallet', 'Medical Card', 'Appointment', 'Prescription', 'Account/Login', 'Referral', 'Technical Issue', 'Other'];
                                foreach ($categories as $cat) {
                                    $sel = ($category_param === $cat) ? 'selected' : '';
                                    echo "<option value=\"$cat\" $sel>$cat</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Subject -->
                        <div style="margin-bottom: 1.2rem;">
                            <label style="display: block; font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 0.4rem; font-weight: bold;">
                                <i class="fas fa-heading" style="color: var(--primary-color);"></i> Ticket Subject:
                            </label>
                            <input type="text" name="subject" class="form-control" placeholder="Brief summary of your issue (e.g. Wallet amount not credited)" required style="font-size: 0.95rem; padding: 0.6rem 1rem;">
                        </div>

                        <!-- Related Record Selector (Optional) -->
                        <div style="margin-bottom: 1.2rem;">
                            <label style="display: block; font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 0.4rem; font-weight: bold;">
                                <i class="fas fa-link" style="color: var(--secondary-color);"></i> Connect Related Record (Optional):
                            </label>
                            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 0.5rem;">
                                <select name="related_entity_type" id="relatedType" class="form-control" style="font-size: 0.88rem; padding: 0.5rem 0.8rem;" onchange="updateRelatedOptions()">
                                    <option value="">-- None --</option>
                                    <option value="order" <?php echo $related_type_param === 'order' ? 'selected' : ''; ?>>Medicine Order</option>
                                    <option value="wallet" <?php echo $related_type_param === 'wallet' ? 'selected' : ''; ?>>Wallet Transaction</option>
                                    <option value="medical_card" <?php echo $related_type_param === 'medical_card' ? 'selected' : ''; ?>>Medical Card</option>
                                    <option value="appointment" <?php echo $related_type_param === 'appointment' ? 'selected' : ''; ?>>Doctor Appointment</option>
                                </select>

                                <input type="text" name="related_entity_id" id="relatedIdInput" class="form-control" placeholder="Record ID / Ref (e.g. 1024 or WAL-XXXX)" value="<?php echo htmlspecialchars($related_id_param); ?>" style="font-size: 0.88rem; padding: 0.5rem 0.8rem;">
                            </div>
                        </div>

                        <!-- Detailed Description -->
                        <div style="margin-bottom: 1.2rem;">
                            <label style="display: block; font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 0.4rem; font-weight: bold;">
                                <i class="fas fa-align-left" style="color: var(--primary-color);"></i> Detailed Description:
                            </label>
                            <textarea name="description" class="form-control" rows="5" required placeholder="Describe your issue in detail. Include timestamps, transaction IDs, or symptoms..." style="font-size: 0.95rem; padding: 0.8rem 1rem; line-height: 1.5;"></textarea>
                        </div>

                        <!-- Attachment Upload -->
                        <div style="margin-bottom: 1.8rem;">
                            <label style="display: block; font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 0.4rem; font-weight: bold;">
                                <i class="fas fa-paperclip" style="color: var(--primary-color);"></i> Attachment (Optional):
                            </label>
                            <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf" style="font-size: 0.85rem; padding: 0.5rem;">
                            <small style="color: var(--text-secondary); display: block; margin-top: 0.3rem;">Attach screenshot, payment proof, or PDF (Max 5MB: JPG, PNG, WEBP, PDF)</small>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                            <a href="customer_support.php" class="btn btn-outline">Cancel</a>
                            <button type="submit" name="create_ticket_submit" value="1" class="btn btn-primary" style="padding: 0.75rem 2rem; font-weight: bold; font-size: 1rem;">
                                <i class="fas fa-paper-plane"></i> Submit Ticket
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
