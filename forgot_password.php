<?php
require_once 'config.php';
require_once 'includes/security_helper.php';

$error = '';
$success = '';
$demo_otp_notice = '';

$reset_state = $_SESSION['reset_session'] ?? null;
$step = $reset_state['step'] ?? 'identifier';

// Handle Reset Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // STEP 1: REQUEST OTP / IDENTIFIER SUBMISSION
    if ($action === 'request_otp') {
        $identifier = trim($_POST['identifier'] ?? '');
        
        if (empty($identifier)) {
            $error = "Please enter your registered email address or phone number.";
        } elseif (is_otp_rate_limited($conn, $identifier)) {
            $error = "Please wait a moment before requesting another verification code.";
        } else {
            // Find account across all roles (Patient, Doctor, RMP, Admin)
            $stmt = $conn->prepare("SELECT id, name, email, phone, role FROM users WHERE email = ? OR phone = ?");
            $stmt->bind_param("ss", $identifier, $identifier);
            $stmt->execute();
            $res = $stmt->get_result();

            record_otp_request($conn, $identifier);

            if ($res && $user = $res->fetch_assoc()) {
                // Generate cryptographically secure OTP & Token
                $otp = sprintf('%06d', random_int(100000, 999999));
                $token = bin2hex(random_bytes(32));

                $_SESSION['reset_session'] = [
                    'user_id' => $user['id'],
                    'identifier' => $identifier,
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'],
                    'role' => $user['role'],
                    'otp' => $otp,
                    'otp_expires' => time() + 600, // 10 minutes expiration
                    'attempts' => 0,
                    'token' => $token,
                    'step' => 'verify_otp',
                    'last_sent' => time()
                ];

                $step = 'verify_otp';
                $success = "If an account matches your entry, a 6-digit verification code has been generated.";
                $demo_otp_notice = "Demo Verification Code for testing: <strong style='font-size:1.1rem; color:var(--secondary-color);'>$otp</strong>";
            } else {
                // Enumeration Protection: Generic response
                $success = "If an account matches your entry, a 6-digit verification code has been generated.";
                $step = 'identifier';
            }
        }
    }

    // RESEND OTP
    elseif ($action === 'resend_otp' && $reset_state) {
        $last_sent = $reset_state['last_sent'] ?? 0;
        if (time() - $last_sent < 30) {
            $error = "Please wait 30 seconds before requesting a new OTP.";
            $step = 'verify_otp';
        } else {
            $new_otp = sprintf('%06d', random_int(100000, 999999));
            $_SESSION['reset_session']['otp'] = $new_otp;
            $_SESSION['reset_session']['otp_expires'] = time() + 600;
            $_SESSION['reset_session']['last_sent'] = time();

            $step = 'verify_otp';
            $success = "A new 6-digit verification code has been generated.";
            $demo_otp_notice = "New Demo Verification Code: <strong style='font-size:1.1rem; color:var(--secondary-color);'>$new_otp</strong>";
        }
    }

    // STEP 2: VERIFY OTP
    elseif ($action === 'verify_otp' && $reset_state) {
        $input_otp = trim($_POST['otp'] ?? '');

        if (empty($input_otp)) {
            $error = "Please enter the 6-digit verification code.";
            $step = 'verify_otp';
        } elseif (time() > $reset_state['otp_expires']) {
            $error = "Verification code has expired. Please request a new code.";
            $step = 'verify_otp';
        } elseif ($reset_state['attempts'] >= 5) {
            unset($_SESSION['reset_session']);
            $error = "Too many failed OTP attempts. Please restart the password reset process.";
            $step = 'identifier';
        } else {
            if (hash_equals((string)$reset_state['otp'], (string)$input_otp)) {
                $_SESSION['reset_session']['step'] = 'new_password';
                $step = 'new_password';
                $success = "Verification successful! Please enter your new password.";
            } else {
                $_SESSION['reset_session']['attempts']++;
                $remaining = 5 - $_SESSION['reset_session']['attempts'];
                $error = "Invalid verification code. ($remaining attempts remaining)";
                $step = 'verify_otp';
            }
        }
    }

    // STEP 3: NEW PASSWORD SUBMISSION
    elseif ($action === 'update_password' && $reset_state && ($reset_state['step'] === 'new_password')) {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters long.";
            $step = 'new_password';
        } elseif ($new_password !== $confirm_password) {
            $error = "New password and Confirm Password do not match.";
            $step = 'new_password';
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $user_id = (int)$reset_state['user_id'];

            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $user_id);

            if ($stmt->execute()) {
                // Invalidate all active user sessions for security
                $conn->query("DELETE FROM user_sessions WHERE user_id = $user_id");

                // Clear Reset Session
                unset($_SESSION['reset_session']);
                $step = 'completed';
                $success = "Password updated successfully! You can now log in with your new password.";
            } else {
                $error = "Failed to update password. Please try again.";
                $step = 'new_password';
            }
        }
    }
}

// Reset Flow Cancel Action
if (isset($_GET['cancel'])) {
    unset($_SESSION['reset_session']);
    header("Location: login.php");
    exit;
}

include 'includes/header.php';
?>

<div class="form-container glass-panel" style="max-width: 480px; margin: 2rem auto;">
    <h2 style="text-align: center; margin-bottom: 0.5rem; color: var(--text-primary);">
        <i class="fas fa-key" style="color: var(--primary-color);"></i> Reset Password
    </h2>
    <p style="text-align: center; color: var(--text-secondary); margin-bottom: 2rem; font-size: 0.9rem;">
        Recover access to your MedicalAk account safely
    </p>

    <?php if ($error): ?>
        <div style="background: rgba(255, 71, 87, 0.15); border: 1px solid #ff4757; color: #ff4757; padding: 0.85rem 1rem; border-radius: 10px; margin-bottom: 1.5rem; font-size: 0.9rem; text-align: center;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div style="background: rgba(46, 213, 115, 0.15); border: 1px solid #2ed573; color: #2ed573; padding: 0.85rem 1rem; border-radius: 10px; margin-bottom: 1.5rem; font-size: 0.9rem; text-align: center;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <?php if ($demo_otp_notice): ?>
                <div style="margin-top: 0.5rem; border-top: 1px solid rgba(46, 213, 115, 0.3); padding-top: 0.5rem;">
                    <?php echo $demo_otp_notice; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- STEP 1: IDENTIFIER FORM -->
    <?php if ($step === 'identifier'): ?>
        <form method="POST" action="forgot_password.php">
            <input type="hidden" name="action" value="request_otp">
            <div class="form-group">
                <label>Registered Email or Phone Number</label>
                <div style="position: relative;">
                    <i class="fas fa-user" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                    <input type="text" name="identifier" class="form-control" placeholder="Enter email address or mobile number" required style="padding-left: 2.75rem;">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1.5rem;">
                Send Verification Code <i class="fas fa-paper-plane"></i>
            </button>
        </form>

    <!-- STEP 2: VERIFY OTP FORM -->
    <?php elseif ($step === 'verify_otp'): ?>
        <form method="POST" action="forgot_password.php">
            <input type="hidden" name="action" value="verify_otp">
            <div class="form-group">
                <label>Enter 6-Digit Verification Code</label>
                <div style="position: relative;">
                    <i class="fas fa-shield-alt" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                    <input type="text" name="otp" class="form-control" placeholder="6-digit OTP code" required maxlength="6" pattern="[0-9]{6}" style="padding-left: 2.75rem; font-weight: bold; letter-spacing: 4px; font-size: 1.1rem;">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                Verify Code <i class="fas fa-check-double"></i>
            </button>
        </form>

        <form method="POST" action="forgot_password.php" style="margin-top: 1rem;">
            <input type="hidden" name="action" value="resend_otp">
            <button type="submit" class="btn btn-outline" style="width: 100%; font-size: 0.85rem;">
                <i class="fas fa-sync-alt"></i> Resend Verification Code
            </button>
        </form>

    <!-- STEP 3: NEW PASSWORD FORM -->
    <?php elseif ($step === 'new_password'): ?>
        <form method="POST" action="forgot_password.php">
            <input type="hidden" name="action" value="update_password">
            <div class="form-group">
                <label>New Password (Min. 6 characters)</label>
                <div style="position: relative;">
                    <i class="fas fa-lock" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                    <input type="password" name="new_password" class="form-control" placeholder="Enter new password" required minlength="6" style="padding-left: 2.75rem;">
                </div>
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <div style="position: relative;">
                    <i class="fas fa-lock" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required minlength="6" style="padding-left: 2.75rem;">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1.5rem;">
                Update Password <i class="fas fa-save"></i>
            </button>
        </form>

    <!-- STEP 4: COMPLETED CONFIRMATION -->
    <?php elseif ($step === 'completed'): ?>
        <div style="text-align: center; margin-top: 1rem;">
            <a href="login.php" class="btn btn-primary" style="width: 100%; padding: 0.85rem 1.5rem;">
                Back to Login <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem; border-top: 1px solid var(--glass-border); padding-top: 1rem; font-size: 0.88rem;">
        <a href="login.php" style="color: var(--text-secondary); text-decoration: none;"><i class="fas fa-arrow-left"></i> Back to Login</a>
        <?php if ($step !== 'identifier' && $step !== 'completed'): ?>
            <a href="forgot_password.php?cancel=1" style="color: #ff4757; text-decoration: none;">Cancel Reset</a>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
