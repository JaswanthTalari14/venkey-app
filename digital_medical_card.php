<?php
require_once 'config.php';
require_once 'includes/wallet_functions.php';
require_once 'includes/notification_functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$patient_id = (int)$_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

$card_settings = get_medical_card_settings($conn);
$card_price = $card_settings['card_price']; // ₹50.00
$wallet_balance = get_wallet_balance($patient_id);

// Check if patient profile info is available
$pat_q = $conn->query("SELECT name, email, phone, profile_image, created_at FROM users WHERE id = $patient_id");
$patient_info = $pat_q ? $pat_q->fetch_assoc() : ['name' => 'Patient', 'email' => '', 'phone' => '', 'profile_image' => ''];
$profile_img_src = get_profile_image_url($patient_info);
if (empty($profile_img_src)) {
    $profile_img_src = 'https://ui-avatars.com/api/?name=' . urlencode($patient_info['name']) . '&background=4a90e2&color=fff&size=128';
}

// Auto-expire invalid active cards if valid_until has passed
@$conn->query("UPDATE digital_medical_cards SET status = 'expired' WHERE patient_id = $patient_id AND status = 'active' AND valid_until IS NOT NULL AND valid_until < NOW()");

// Handle Wallet Purchase Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase_card_wallet') {
    // Check if patient already has active or pending card
    $existing_chk = $conn->query("SELECT id, status FROM digital_medical_cards WHERE patient_id = $patient_id AND status IN ('active', 'pending') ORDER BY id DESC LIMIT 1");
    if ($existing_chk && $existing_chk->num_rows > 0) {
        $ex = $existing_chk->fetch_assoc();
        if ($ex['status'] === 'active') {
            $error_msg = "Your Digital Medical Card is already active! You cannot purchase another active card.";
        } elseif ($ex['status'] === 'pending') {
            $error_msg = "You already have a pending Digital Medical Card application waiting for Admin approval.";
        }
    } else {
        if ($wallet_balance < $card_price) {
            $error_msg = "Insufficient Wallet balance (₹" . number_format($wallet_balance, 2) . "). Required amount is ₹" . number_format($card_price, 2) . ". Please topup your wallet or use Online Payment.";
        } else {
            // Deduct ₹50 from Wallet
            $tx_id = 'WMC-' . time() . '-' . rand(100, 999);
            $deduct_ok = add_wallet_transaction(
                $patient_id,
                'payment',
                'debit',
                $card_price,
                "Payment for Digital Medical Card Application (Pending Admin Approval)",
                null,
                $tx_id
            );

            if ($deduct_ok) {
                // Insert Pending Card Record
                $stmt = $conn->prepare("INSERT INTO digital_medical_cards (patient_id, amount_paid, payment_method, payment_status, gateway_payment_id, status) VALUES (?, ?, 'Wallet', 'Paid via Wallet', ?, 'pending')");
                $stmt->bind_param("ids", $patient_id, $card_price, $tx_id);
                if ($stmt->execute()) {
                    $app_id = $stmt->insert_id;
                    create_notification(
                        $patient_id,
                        "Medical Card Application Submitted",
                        "Your ₹50 payment for Digital Medical Card application (#DMC-APP-" . str_pad($app_id, 4, '0', STR_PAD_LEFT) . ") was verified. Status is now Pending Admin Approval.",
                        'system',
                        'medical_card',
                        $app_id
                    );
                    $success_msg = "Payment of ₹" . number_format($card_price, 2) . " successful via Wallet! Your Digital Medical Card application has been submitted and is currently PENDING ADMIN APPROVAL.";
                } else {
                    $error_msg = "Failed to record card application. Please contact support.";
                }
            } else {
                $error_msg = "Failed to process Wallet payment. Please try again.";
            }
        }
    }
}

// Fetch current card record (Active, Pending, Rejected, or Expired)
$card_query = $conn->query("
    SELECT * FROM digital_medical_cards 
    WHERE patient_id = $patient_id 
    ORDER BY id DESC LIMIT 1
");
$current_card = ($card_query && $card_query->num_rows > 0) ? $card_query->fetch_assoc() : null;

$card_status = $current_card ? $current_card['status'] : 'unapplied';

include 'includes/header.php';
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
            <li><a href="digital_medical_card.php" class="active"><i class="fas fa-id-card"></i> Digital Medical Card</a></li>
            <li><a href="health_vault.php"><i class="fas fa-vault"></i> Health Vault</a></li>
            <li><a href="health_journey.php"><i class="fas fa-route"></i> Healthcare Journey</a></li>
            <li><a href="book_consult.php"><i class="fas fa-calendar-check"></i> Consultations</a></li>
            <li><a href="medicines.php"><i class="fas fa-pills"></i> Order Medicines</a></li>
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
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <h2>Digital Medical Card</h2>
                <p style="color: var(--text-secondary); margin-top: 0.3rem;">Get exclusive discounts on doctor consultations and medicine orders for 5 full months.</p>
            </div>
            <div style="background: rgba(80, 227, 194, 0.1); border: 1px solid rgba(80, 227, 194, 0.3); padding: 0.5rem 1rem; border-radius: 12px; font-weight: 600; color: var(--secondary-color); font-size: 0.9rem;">
                <i class="fas fa-wallet"></i> Wallet Balance: ₹<?php echo number_format($wallet_balance, 2); ?>
            </div>
        </div>

        <?php if ($success_msg): ?>
            <div style="color: #2ed573; margin-bottom: 1.5rem; padding: 1rem 1.25rem; background: rgba(46, 213, 115, 0.12); border-radius: 12px; border-left: 4px solid #2ed573; font-weight: 500;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div style="color: #ff4757; margin-bottom: 1.5rem; padding: 1rem 1.25rem; background: rgba(255, 71, 87, 0.12); border-radius: 12px; border-left: 4px solid #ff4757; font-weight: 500;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <!-- =================================================================== -->
        <!-- STATE 1: ACTIVE DIGITAL MEDICAL CARD -->
        <!-- =================================================================== -->
        <?php if ($card_status === 'active'): ?>
            <?php
                $valid_until_ts = strtotime($current_card['valid_until']);
                $now_ts = time();
                $diff_sec = max(0, $valid_until_ts - $now_ts);
                $remaining_days = ceil($diff_sec / 86400);
                $qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($current_card['card_number']);
            ?>

            <div style="max-width: 580px; margin: 0 auto 2rem auto;">
                <!-- Digital Card Wrapper -->
                <div class="glass-panel" id="digitalMedicalCardBox" style="background: linear-gradient(135deg, rgba(16, 26, 43, 0.95), rgba(24, 38, 64, 0.95)); border: 2px solid var(--secondary-color); border-radius: 20px; padding: 1.8rem; box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5); position: relative; overflow: hidden;">
                    <!-- Background Glow Overlay -->
                    <div style="position: absolute; top: -50px; right: -50px; width: 180px; height: 180px; background: radial-gradient(circle, rgba(80, 227, 194, 0.25) 0%, transparent 70%); pointer-events: none;"></div>

                    <!-- Header -->
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed rgba(255, 255, 255, 0.15); padding-bottom: 1rem; margin-bottom: 1.2rem;">
                        <div style="display: flex; align-items: center; gap: 0.6rem;">
                            <i class="fas fa-heartbeat" style="color: var(--secondary-color); font-size: 1.8rem;"></i>
                            <div>
                                <h3 style="color: #ffffff; margin: 0; font-size: 1.25rem; letter-spacing: 0.5px;">MedicalAk</h3>
                                <div style="font-size: 0.72rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700;">Digital Medical Card</div>
                            </div>
                        </div>
                        <span style="background: rgba(46, 213, 115, 0.18); color: #2ed573; border: 1px solid #2ed573; padding: 0.35rem 0.85rem; border-radius: 20px; font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem;">
                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i> ACTIVE
                        </span>
                    </div>

                    <!-- Card Body -->
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.8rem;">
                                <img src="<?php echo htmlspecialchars($profile_img_src); ?>" alt="<?php echo htmlspecialchars($patient_info['name']); ?>" style="width: 52px; height: 52px; border-radius: 50%; object-fit: cover; border: 2px solid var(--secondary-color); background: rgba(0,0,0,0.3);">
                                <div>
                                    <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Patient Name</div>
                                    <div style="font-size: 1.2rem; font-weight: 800; color: #ffffff; margin-top: 0.15rem;"><?php echo htmlspecialchars($patient_info['name']); ?></div>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Card Number</div>
                                <div style="font-size: 1.15rem; font-weight: 800; color: var(--primary-color); letter-spacing: 1px; margin-top: 0.15rem; font-family: monospace;">
                                    <?php echo htmlspecialchars($current_card['card_number']); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Validity Dates Row -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; background: rgba(0, 0, 0, 0.25); padding: 0.85rem 1rem; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.05);">
                            <div>
                                <div style="font-size: 0.72rem; color: var(--text-secondary); text-transform: uppercase;">Valid From</div>
                                <div style="font-size: 0.9rem; font-weight: 700; color: #ffffff; margin-top: 0.2rem;">
                                    <?php echo date('d M Y', strtotime($current_card['valid_from'])); ?>
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 0.72rem; color: var(--text-secondary); text-transform: uppercase;">Valid Until</div>
                                <div style="font-size: 0.9rem; font-weight: 700; color: var(--secondary-color); margin-top: 0.2rem;">
                                    <?php echo date('d M Y', strtotime($current_card['valid_until'])); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Remaining Days Counter Badge -->
                        <div style="text-align: center; background: rgba(80, 227, 194, 0.1); border: 1px solid rgba(80, 227, 194, 0.3); padding: 0.6rem; border-radius: 10px; color: var(--secondary-color); font-weight: 700; font-size: 0.88rem;">
                            <i class="fas fa-clock"></i> Valid for <?php echo $remaining_days; ?> days remaining
                        </div>

                        <!-- Benefits & QR Code Row -->
                        <div style="border-top: 1px dashed rgba(255, 255, 255, 0.15); padding-top: 0.9rem; margin-top: 0.2rem; display: flex; justify-content: space-between; align-items: flex-end; gap: 0.5rem;">
                            <div>
                                <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 0.5rem;">Card Member Benefits</div>
                                <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.88rem; color: #ffffff;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-check-circle" style="color: #2ed573;"></i>
                                        <span><strong><?php echo (float)$card_settings['consultation_discount_percent']; ?>% Discount</strong> on Doctor Consultations (Auto Applied)</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-check-circle" style="color: #2ed573;"></i>
                                        <span><strong><?php echo (float)$card_settings['medicine_discount_percent']; ?>% Discount</strong> on All Medicine Purchases (Auto Applied)</span>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: center; background: #ffffff; padding: 6px; border-radius: 10px; border: 1px solid var(--secondary-color); flex-shrink: 0;">
                                <img src="<?php echo $qr_code_url; ?>" alt="QR Code" style="width: 70px; height: 70px; display: block;">
                                <div style="font-size: 0.55rem; color: #0f172a; font-weight: 800; margin-top: 2px;">VERIFIED CARD</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DOWNLOAD MEDICAL CARD BUTTON & VIEW MEDICAL CARD BUTTON -->
                <div style="margin-top: 1.25rem; text-align: center; display: flex; flex-direction: column; gap: 0.75rem;">
                    <button type="button" id="btnDownloadCard" onclick="downloadMedicalCardPDF()" class="btn btn-primary" style="width: 100%; padding: 0.85rem 1.5rem; font-size: 1rem; font-weight: 700; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; gap: 0.6rem; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border: none; cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.35);">
                        <i class="fas fa-file-pdf" style="font-size: 1.2rem;"></i>
                        <span id="downloadBtnText">Download Medical Card</span>
                    </button>

                    <!-- VIEW MEDICAL CARD BUTTON -->
                    <a href="download_medical_card.php" target="_blank" class="btn btn-outline" style="width: 100%; padding: 0.85rem 1.5rem; font-size: 1rem; font-weight: 700; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; gap: 0.6rem; background: rgba(80, 227, 194, 0.1); border: 1px solid var(--secondary-color); color: var(--secondary-color); text-decoration: none;">
                        <i class="fas fa-eye" style="font-size: 1.1rem;"></i>
                        <span>View Medical Card</span>
                    </a>
                </div>
            </div>

        <!-- =================================================================== -->
        <!-- STATE 2: PENDING APPROVAL SCREEN -->
        <!-- =================================================================== -->
        <?php elseif ($card_status === 'pending'): ?>
            <div class="glass-panel" style="max-width: 580px; margin: 0 auto; padding: 2.2rem; border-radius: 20px; text-align: center; border: 1px solid rgba(245, 166, 35, 0.4); background: linear-gradient(135deg, rgba(16, 26, 43, 0.9), rgba(30, 25, 15, 0.9));">
                <div style="width: 76px; height: 76px; background: rgba(245, 166, 35, 0.15); color: #f5a623; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto; font-size: 2.2rem; border: 2px solid #f5a623;">
                    <i class="fas fa-hourglass-half"></i>
                </div>

                <h3 style="color: #ffffff; margin-bottom: 0.5rem; font-size: 1.4rem;">Medical Card Application Pending Approval</h3>
                
                <div style="display: inline-block; background: rgba(245, 166, 35, 0.18); color: #f5a623; border: 1px solid #f5a623; padding: 0.35rem 1rem; border-radius: 20px; font-weight: 700; font-size: 0.85rem; margin-bottom: 1.25rem;">
                    🟡 Pending Admin Approval
                </div>

                <div style="background: rgba(0, 0, 0, 0.3); border: 1px solid var(--glass-border); border-radius: 12px; padding: 1.2rem; text-align: left; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; flex-direction: column; gap: 0.6rem;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-secondary);">Application ID:</span>
                        <strong style="color: var(--primary-color);">#DMC-APP-<?php echo str_pad($current_card['id'], 4, '0', STR_PAD_LEFT); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-secondary);">Amount Paid:</span>
                        <strong style="color: var(--secondary-color);">₹<?php echo number_format($current_card['amount_paid'], 2); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-secondary);">Payment Method:</span>
                        <strong style="color: #ffffff;"><?php echo htmlspecialchars($current_card['payment_method']); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-secondary);">Application Date:</span>
                        <strong style="color: #ffffff;"><?php echo date('d M Y, h:i A', strtotime($current_card['created_at'])); ?></strong>
                    </div>
                </div>

                <p style="color: var(--text-secondary); font-size: 0.92rem; line-height: 1.5; margin-bottom: 0;">
                    Your Digital Medical Card application has been submitted successfully. It will become active after Admin approval.
                </p>
            </div>

        <!-- =================================================================== -->
        <!-- STATE 3: UNAPPLIED / REJECTED / EXPIRED (APPLY & RENEW FLOW) -->
        <!-- =================================================================== -->
        <?php else: ?>
            <div style="max-width: 640px; margin: 0 auto;">
                <?php if ($card_status === 'expired'): ?>
                    <div style="background: rgba(255, 71, 87, 0.12); border: 1px solid #ff4757; color: #ff4757; padding: 1rem 1.25rem; border-radius: 14px; margin-bottom: 1.5rem; font-weight: 600; text-align: center;">
                        <i class="fas fa-exclamation-triangle"></i> Your Digital Medical Card has expired. Renew your card to reactivate 5-month discounts.
                    </div>
                <?php elseif ($card_status === 'rejected'): ?>
                    <div style="background: rgba(255, 71, 87, 0.12); border: 1px solid #ff4757; color: #ff4757; padding: 1rem 1.25rem; border-radius: 14px; margin-bottom: 1.5rem; font-weight: 600;">
                        <i class="fas fa-times-circle"></i> Your previous application was rejected by Admin<?php echo !empty($current_card['admin_notes']) ? ': ' . htmlspecialchars($current_card['admin_notes']) : '.'; ?> You can re-apply below.
                    </div>
                <?php endif; ?>

                <!-- Benefits & Pricing Card -->
                <div class="glass-panel" style="padding: 2rem; border-radius: 20px; border: 1px solid var(--glass-border);">
                    <div style="text-align: center; margin-bottom: 1.8rem;">
                        <div style="width: 70px; height: 70px; background: rgba(74, 144, 226, 0.12); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem auto; color: var(--primary-color); font-size: 2rem;">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <h3 style="color: #ffffff; margin-bottom: 0.4rem; font-size: 1.4rem;">Get Your Digital Medical Card</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">Unlock guaranteed savings on healthcare consultations and medicine deliveries.</p>
                    </div>

                    <!-- Price Tag Banner -->
                    <div style="background: linear-gradient(135deg, rgba(74, 144, 226, 0.15), rgba(80, 227, 194, 0.15)); border: 1px solid var(--primary-color); border-radius: 16px; padding: 1.25rem; text-align: center; margin-bottom: 1.8rem;">
                        <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; font-weight: 700;">Special Membership Price</div>
                        <div style="font-size: 2.5rem; font-weight: 900; color: var(--secondary-color); margin: 0.2rem 0;">₹<?php echo number_format($card_price, 2); ?></div>
                        <div style="font-size: 0.85rem; color: var(--text-primary); font-weight: 600;">Valid for 5 Full Calendar Months (Starts after Admin Approval)</div>
                    </div>

                    <!-- Included Benefits List -->
                    <div style="margin-bottom: 1.8rem;">
                        <h4 style="color: #ffffff; font-size: 1rem; margin-bottom: 0.8rem; font-weight: 700;">Card Membership Benefits:</h4>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <div style="display: flex; align-items: flex-start; gap: 0.75rem; background: rgba(0, 0, 0, 0.2); padding: 0.85rem 1rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.03);">
                                <i class="fas fa-user-md" style="color: var(--primary-color); font-size: 1.2rem; margin-top: 0.1rem;"></i>
                                <div>
                                    <div style="font-weight: 700; color: #ffffff; font-size: 0.95rem;"><?php echo (float)$card_settings['consultation_discount_percent']; ?>% Off Doctor Consultations</div>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary);">Save on online video consults & offline home visits automatically.</div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: flex-start; gap: 0.75rem; background: rgba(0, 0, 0, 0.2); padding: 0.85rem 1rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.03);">
                                <i class="fas fa-pills" style="color: var(--secondary-color); font-size: 1.2rem; margin-top: 0.1rem;"></i>
                                <div>
                                    <div style="font-weight: 700; color: #ffffff; font-size: 0.95rem;"><?php echo (float)$card_settings['medicine_discount_percent']; ?>% Off Medicine Orders</div>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary);">Get automatic discount on all prescription & doorstep medicine orders.</div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: flex-start; gap: 0.75rem; background: rgba(0, 0, 0, 0.2); padding: 0.85rem 1rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.03);">
                                <i class="fas fa-shield-alt" style="color: #2ed573; font-size: 1.2rem; margin-top: 0.1rem;"></i>
                                <div>
                                    <div style="font-weight: 700; color: #ffffff; font-size: 0.95rem;">Verified Digital Membership ID</div>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary);">Unique card number (DMC-XXXXXX) issued after Admin verification.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Action Tabs -->
                    <div style="border-top: 1px solid var(--glass-border); padding-top: 1.5rem;">
                        <h4 style="color: #ffffff; font-size: 0.95rem; margin-bottom: 1rem; text-align: center; font-weight: 700;">Select Payment Method (₹<?php echo number_format($card_price, 2); ?>)</h4>
                        
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <!-- Option A: Pay via Wallet -->
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="purchase_card_wallet">
                                <button type="submit" class="btn btn-outline" style="width: 100%; padding: 0.85rem; font-size: 0.95rem; display: flex; justify-content: space-between; align-items: center;" <?php echo ($wallet_balance < $card_price) ? 'disabled style="opacity:0.5;"' : ''; ?>>
                                    <span><i class="fas fa-wallet" style="color: var(--secondary-color);"></i> Pay via Wallet (Balance: ₹<?php echo number_format($wallet_balance, 2); ?>)</span>
                                    <i class="fas fa-arrow-right"></i>
                                </button>
                            </form>

                            <!-- Option B: Pay Online / Razorpay -->
                            <button type="button" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-size: 1rem;" onclick="triggerCardPaymentRazorpay(<?php echo $card_price; ?>)">
                                <i class="fas fa-credit-card"></i> Pay Online via Razorpay / UPI (₹<?php echo number_format($card_price, 2); ?>)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <!-- Contextual Support Link -->
        <div class="glass-panel" style="margin-top: 2rem; padding: 1.2rem 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; border-left: 4px solid var(--secondary-color); border-radius: 16px;">
            <div>
                <h4 style="margin: 0; font-size: 1rem; color: var(--text-primary); font-weight: 700;"><i class="fas fa-headset" style="color: var(--secondary-color); margin-right: 0.5rem;"></i> Need Help with your Digital Medical Card?</h4>
                <p style="margin: 0.3rem 0 0 0; font-size: 0.85rem; color: var(--text-secondary);">Have questions about discounts, card verification, or approval? Contact our support team directly.</p>
            </div>
            <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
                <a href="create_ticket.php?category=Medical Card&entity_type=Medical Card" class="btn btn-outline" style="padding: 0.4rem 0.9rem; font-size: 0.85rem;">
                    <i class="fas fa-ticket-alt"></i> Create Ticket
                </a>
                <a href="<?php echo build_whatsapp_url('Hello, I need help regarding my Digital Medical Card.'); ?>" target="_blank" class="btn" style="padding: 0.4rem 0.9rem; font-size: 0.85rem; background: #25d366; color: #fff; border: none;">
                    <i class="fab fa-whatsapp"></i> Chat on WhatsApp
                </a>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Simulated Online Payment Modal for Demo Mode -->
<div id="cardPaymentGatewayModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); z-index: 99999; align-items: center; justify-content: center; padding: 1rem;">
    <div class="glass-panel" style="background: #1a1f2c; border: 1px solid var(--glass-border); width: 100%; max-width: 420px; padding: 2rem; border-radius: 16px; text-align: center;">
        <i class="fas fa-shield-alt" style="color: var(--secondary-color); font-size: 2rem; margin-bottom: 0.5rem;"></i>
        <h3 style="color: #fff; margin-bottom: 0.3rem;">Medical Card Online Payment</h3>
        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1rem;">Digital Medical Card Application Fee</p>
        <div style="font-size: 2.2rem; font-weight: bold; color: var(--secondary-color); margin-bottom: 1.5rem;">₹<?php echo number_format($card_price, 2); ?></div>

        <div style="display: flex; gap: 0.75rem;">
            <button type="button" onclick="document.getElementById('cardPaymentGatewayModal').style.display='none';" class="btn" style="flex: 1; background: rgba(255,255,255,0.1); color: #fff;">Cancel</button>
            <button type="button" id="btnConfirmCardPay" class="btn btn-primary" style="flex: 1.5;">Complete ₹<?php echo number_format($card_price, 0); ?> Payment</button>
        </div>
    </div>
</div>

<script>
function triggerCardPaymentRazorpay(amount) {
    var amountPaise = Math.round(amount * 100);
    var razorpayKey = "<?php echo RAZORPAY_KEY_ID; ?>";

    if (!razorpayKey || razorpayKey.indexOf('samplekey') !== -1 || razorpayKey === 'rzp_test_samplekeyid') {
        document.getElementById('cardPaymentGatewayModal').style.display = 'flex';
        return;
    }

    try {
        var options = {
            "key": razorpayKey,
            "amount": amountPaise,
            "currency": "INR",
            "name": "MedicalAk Medical Card",
            "description": "Digital Medical Card Application Fee (₹50)",
            "handler": function (response){
                submitCardPaymentVerification(response.razorpay_payment_id || ('pay_card_' + Date.now()), response.razorpay_order_id || '');
            },
            "theme": { "color": "#4a90e2" }
        };
        var rzp1 = new Razorpay(options);
        rzp1.on('payment.failed', function (response){
            document.getElementById('cardPaymentGatewayModal').style.display = 'flex';
        });
        rzp1.open();
    } catch (e) {
        document.getElementById('cardPaymentGatewayModal').style.display = 'flex';
    }
}

document.getElementById('btnConfirmCardPay').addEventListener('click', function() {
    document.getElementById('cardPaymentGatewayModal').style.display = 'none';
    submitCardPaymentVerification('pay_online_card_' + Date.now(), 'order_card_' + Date.now());
});

function submitCardPaymentVerification(paymentId, razorpayOrderId) {
    fetch('api_digital_medical_card.php?action=apply_card_online', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            payment_id: paymentId,
            razorpay_order_id: razorpayOrderId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'digital_medical_card.php';
        } else {
            alert('Payment Error: ' + data.message);
        }
    })
    .catch(err => {
        alert('Network connection error. Please refresh.');
    });
}
</script>

<!-- html2pdf.js CDN for PDF Download generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script>
function showCardDownloadToast(message, isError) {
    var existing = document.getElementById('cardDownloadToast');
    if (existing) existing.remove();

    var toast = document.createElement('div');
    toast.id = 'cardDownloadToast';
    toast.style.cssText = 'position: fixed; bottom: 25px; right: 25px; z-index: 999999; background: rgba(18, 18, 18, 0.94); color: ' + (isError ? '#ff4757' : '#2ed573') + '; padding: 0.85rem 1.4rem; border-radius: 12px; border: 1px solid ' + (isError ? 'rgba(255, 71, 87, 0.4)' : 'rgba(46, 213, 115, 0.4)') + '; font-weight: 600; font-size: 0.9rem; box-shadow: 0 10px 30px rgba(0,0,0,0.5); backdrop-filter: blur(10px); display: flex; align-items: center; gap: 0.6rem; transition: opacity 0.4s ease;';
    toast.innerHTML = (isError ? '<i class="fas fa-exclamation-circle"></i> ' : '<i class="fas fa-check-circle"></i> ') + message;
    document.body.appendChild(toast);

    setTimeout(function() {
        toast.style.opacity = '0';
        setTimeout(function() { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 400);
    }, 3200);
}

function urlToBase64(url) {
    return new Promise(function(resolve) {
        if (!url || url.indexOf('data:image') === 0) {
            resolve(url);
            return;
        }
        var img = new Image();
        img.crossOrigin = 'Anonymous';
        img.onload = function() {
            try {
                var canvas = document.createElement('canvas');
                canvas.width = img.naturalWidth || img.width || 128;
                canvas.height = img.naturalHeight || img.height || 128;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                var dataURL = canvas.toDataURL('image/png');
                resolve(dataURL);
            } catch (e) {
                resolve(url);
            }
        };
        img.onerror = function() {
            resolve(url);
        };
        img.src = url;
    });
}

function downloadMedicalCardPDF() {
    var btn = document.getElementById('btnDownloadCard');
    var btnText = document.getElementById('downloadBtnText');
    if (!btn || btn.disabled) return;

    btn.disabled = true;
    var originalHtml = btnText.innerHTML;
    btnText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating Medical Card...';

    showCardDownloadToast('Generating your Digital Medical Card PDF...');

    var cardNumber = "<?php echo htmlspecialchars($current_card['card_number'] ?? 'DMC-CARD'); ?>";
    var cleanCardNum = cardNumber.replace(/[^A-Za-z0-9\-]/g, '');
    var filename = 'Digital-Medical-Card-' + cleanCardNum + '.pdf';
    var patientName = "<?php echo htmlspecialchars($patient_info['name'] ?? 'Patient'); ?>";
    var profileImg = "<?php echo htmlspecialchars($profile_img_src ?? ''); ?>";
    var qrUrl = "<?php echo htmlspecialchars($qr_code_url ?? ''); ?>";
    var validFrom = "<?php echo date('d M Y', strtotime($current_card['valid_from'] ?? 'now')); ?>";
    var validUntil = "<?php echo date('d M Y', strtotime($current_card['valid_until'] ?? '+5 months')); ?>";
    var consultDisc = "<?php echo (float)($card_settings['consultation_discount_percent'] ?? 20); ?>";
    var medDisc = "<?php echo (float)($card_settings['medicine_discount_percent'] ?? 15); ?>";

    Promise.all([urlToBase64(profileImg), urlToBase64(qrUrl)]).then(function(images) {
        var base64Profile = images[0];
        var base64Qr = images[1];

        // Position temporary render box inside document viewport so Chromium/WebKit fully rasterizes all text glyphs and layout bitmaps
        var tempDiv = document.createElement('div');
        tempDiv.id = 'tempMedicalCardRenderBox';
        tempDiv.style.cssText = 'position: absolute; top: 0; left: 0; width: 620px; z-index: -1; opacity: 0.999; pointer-events: none; background: #0f172a; border-radius: 20px; overflow: hidden;';

        var cardHtml = `
        <div style="width: 620px; background: #0f172a; border: 3px solid #50e3c2; border-radius: 20px; padding: 26px; font-family: 'Inter', system-ui, -apple-system, sans-serif; color: #ffffff; box-sizing: border-box; background-image: radial-gradient(circle at top right, rgba(80, 227, 194, 0.15) 0%, transparent 60%);">
            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed rgba(255, 255, 255, 0.2); padding-bottom: 16px; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 44px; height: 44px; background: rgba(80, 227, 194, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #50e3c2; font-size: 24px; font-weight: bold;">✚</div>
                    <div>
                        <div style="color: #ffffff; font-size: 24px; font-weight: 800; letter-spacing: 0.5px; line-height: 1.2;">MedicalAk</div>
                        <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700;">Digital Medical Card</div>
                    </div>
                </div>
                <div style="background: rgba(46, 213, 115, 0.2); color: #2ed573; border: 1.5px solid #2ed573; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 800; display: inline-flex; align-items: center; gap: 6px;">
                    <span style="font-size: 12px;">●</span> ACTIVE
                </div>
            </div>

            <!-- Body Details -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <img src="${base64Profile}" alt="Patient Photo" style="width: 62px; height: 62px; border-radius: 50%; object-fit: cover; border: 2.5px solid #50e3c2; background: #1e293b;">
                    <div>
                        <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">Patient Name</div>
                        <div style="font-size: 20px; font-weight: 800; color: #ffffff; margin-top: 2px;">${patientName}</div>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">Card Number</div>
                    <div style="font-size: 19px; font-weight: 800; color: #4a90e2; letter-spacing: 1px; margin-top: 2px; font-family: monospace;">
                        ${cardNumber}
                    </div>
                </div>
            </div>

            <!-- Dates Row -->
            <div style="display: flex; justify-content: space-between; background: #1e293b; padding: 14px 20px; border-radius: 14px; border: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 18px;">
                <div>
                    <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: 700;">Valid From</div>
                    <div style="font-size: 14px; font-weight: 800; color: #ffffff; margin-top: 3px;">${validFrom}</div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: 700;">Valid Until</div>
                    <div style="font-size: 14px; font-weight: 800; color: #50e3c2; margin-top: 3px;">${validUntil}</div>
                </div>
            </div>

            <!-- Benefits & QR Code Row -->
            <div style="border-top: 1px dashed rgba(255, 255, 255, 0.2); padding-top: 16px; display: flex; justify-content: space-between; align-items: flex-end;">
                <div>
                    <div style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Card Member Benefits</div>
                    <div style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; color: #ffffff;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="color: #2ed573; font-weight: bold; font-size: 14px;">✔</span>
                            <span><strong>${consultDisc}% Discount</strong> on Doctor Consultations</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="color: #2ed573; font-weight: bold; font-size: 14px;">✔</span>
                            <span><strong>${medDisc}% Discount</strong> on All Medicine Purchases</span>
                        </div>
                    </div>
                </div>
                <div style="text-align: center; background: #ffffff; padding: 6px; border-radius: 10px; border: 1.5px solid #50e3c2;">
                    <img src="${base64Qr}" alt="QR Code" style="width: 75px; height: 75px; display: block;">
                    <div style="font-size: 8px; color: #0f172a; font-weight: 800; margin-top: 3px; letter-spacing: 0.5px;">VERIFIED CARD</div>
                </div>
            </div>
        </div>
        `;

        tempDiv.innerHTML = cardHtml;
        document.body.appendChild(tempDiv);

        if (typeof html2pdf !== 'undefined') {
            var opt = {
                margin:       [0.1, 0.1, 0.1, 0.1],
                filename:     filename,
                image:        { type: 'jpeg', quality: 0.99 },
                html2canvas:  { 
                    scale: 2, 
                    useCORS: true, 
                    allowTaint: true, 
                    logging: false, 
                    backgroundColor: '#0f172a',
                    scrollX: 0,
                    scrollY: 0
                },
                jsPDF:        { unit: 'in', format: [7.2, 4.5], orientation: 'landscape' }
            };

            html2pdf().set(opt).from(tempDiv.children[0]).save().then(function() {
                if (tempDiv.parentNode) document.body.removeChild(tempDiv);
                btn.disabled = false;
                btnText.innerHTML = originalHtml;
                showCardDownloadToast('Medical Card Downloaded Successfully ✅', false);
            }).catch(function(err) {
                console.warn("Client PDF generation error:", err);
                if (tempDiv.parentNode) document.body.removeChild(tempDiv);
                triggerServerDownloadPDF(filename);
            });
        } else {
            if (tempDiv.parentNode) document.body.removeChild(tempDiv);
            triggerServerDownloadPDF(filename);
        }
    }).catch(function(err) {
        console.warn("Image preloading error:", err);
        triggerServerDownloadPDF(filename);
    });
}

function triggerServerDownloadPDF(filename) {
    var btn = document.getElementById('btnDownloadCard');
    var btnText = document.getElementById('downloadBtnText');
    
    window.location.href = 'download_medical_card.php?download=1';
    
    setTimeout(function() {
        if (btn) btn.disabled = false;
        if (btnText) btnText.innerHTML = '<i class="fas fa-file-pdf" style="font-size: 1.2rem;"></i> Download Medical Card';
        showCardDownloadToast('Medical Card Downloaded Successfully ✅', false);
    }, 2000);
}
</script>

<?php include 'includes/footer.php'; ?>
