<?php
require_once 'config.php';
require_once 'includes/security_helper.php';

$error = '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (is_login_locked($conn, $ip, $email)) {
        $error = "Too many failed login attempts. Please try again in 15 minutes for security.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                reset_login_attempts($conn, $ip, $email);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['profile_image'] = $user['profile_image'] ?? '';

                register_current_session($conn, $user['id']);
                session_write_close();

                if ($user['role'] == 'patient') {
                    header("Location: patient_dashboard.php");
                } elseif ($user['role'] == 'doctor') {
                    header("Location: doctor_dashboard.php");
                } elseif ($user['role'] == 'admin') {
                    header("Location: admin_dashboard.php");
                } elseif ($user['role'] == 'rmp') {
                    header("Location: rmp_dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit;
            } else {
                record_failed_login($conn, $ip, $email);
                $error = "Invalid email or password.";
            }
        } else {
            record_failed_login($conn, $ip, $email);
            $error = "Invalid email or password.";
        }
    }
}
include 'includes/header.php';
?>

<div class="form-container glass-panel">
    <h2 style="text-align: center; margin-bottom: 2rem;">Welcome Back</h2>
    <?php if($error): ?><p style="color: #ff4757; text-align: center; margin-bottom: 1rem;"><?php echo $error; ?></p><?php endif; ?>
    <form method="POST" action="">
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
        </div>
        <div class="form-group">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <label style="margin-bottom: 0;">Password</label>
                <a href="forgot_password.php" style="color: var(--primary-color); font-size: 0.85rem; text-decoration: none;">Forgot Password?</a>
            </div>
            <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Login <i class="fas fa-sign-in-alt"></i></button>
    </form>
    <p style="text-align: center; margin-top: 1.5rem; color: var(--text-secondary);">Don't have an account? <a href="register.php" style="color: var(--primary-color);">Sign Up</a></p>
</div>

<?php include 'includes/footer.php'; ?>
