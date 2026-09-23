<?php
require_once 'config.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $result = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];

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
            $error = "Invalid password.";
        }
    } else {
         $error = "User not found with this email.";
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
            <label>Password</label>
            <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Login <i class="fas fa-sign-in-alt"></i></button>
    </form>
    <p style="text-align: center; margin-top: 1.5rem; color: var(--text-secondary);">Don't have an account? <a href="register.php" style="color: var(--primary-color);">Sign Up</a></p>
</div>

<?php include 'includes/footer.php'; ?>
