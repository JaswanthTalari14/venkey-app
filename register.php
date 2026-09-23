<?php
require_once 'config.php';
require_once 'includes/referral_functions.php';

$error = '';
$success = '';

// Store pending referral code in session if present in URL
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $_SESSION['pending_ref'] = trim($_GET['ref']);
}

$ref_code = isset($_POST['referral_code']) 
    ? trim($_POST['referral_code']) 
    : (isset($_SESSION['pending_ref']) ? $_SESSION['pending_ref'] : '');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $check = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($check && $check->num_rows > 0) {
        $error = "Email already registered.";
    } else {
        // Assign mock coordinates to doctors within 10km radius so they appear on the Patient tracking map automatically
        $lat = ($role == 'doctor') ? (28.7042 + (rand(-50, 50) / 1000)) : "NULL";
        $lon = ($role == 'doctor') ? (77.1026 + (rand(-50, 50) / 1000)) : "NULL";
        $spec = ($role == 'doctor') ? "'General Practitioner'" : "NULL";

        $query = "INSERT INTO users (name, email, phone, password, role, latitude, longitude, specialization) 
                  VALUES ('$name', '$email', '$phone', '$password', '$role', $lat, $lon, $spec)";
        
        if ($conn->query($query)) {
            $new_user_id = $conn->insert_id;

            // Process Referral linkage if patient and referral code exists
            if ($role === 'patient' && !empty($ref_code)) {
                register_referral_claim($new_user_id, $ref_code);
            }
            unset($_SESSION['pending_ref']);

            $success = "Registration successful! You can now login.";
        } else {
            $error = "Registration failed. Try again.";
        }
    }
}
include 'includes/header.php';
?>

<div class="form-container glass-panel">
    <h2 style="text-align: center; margin-bottom: 2rem;">Create Account</h2>
    <?php if($error): ?><p style="color: #ff4757; text-align: center; margin-bottom: 1rem;"><?php echo $error; ?></p><?php endif; ?>
    <?php if($success): ?><p style="color: #2ed573; text-align: center; margin-bottom: 1rem;"><?php echo $success; ?></p><?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" class="form-control" placeholder="John Doe" required>
        </div>
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="john@example.com" required>
        </div>
        <div class="form-group">
            <label>Phone Number</label>
            <input type="text" name="phone" class="form-control" placeholder="10-digit number" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" class="form-control" placeholder="Must be at least 6 characters" required>
        </div>
        <div class="form-group">
            <label>I am a</label>
            <select name="role" class="form-control" required>
                <option value="patient">Patient</option>
                <option value="doctor">Doctor</option>
                <option value="rmp">RMP</option>
            </select>
        </div>
        <div class="form-group">
            <label>Referral Code (Optional)</label>
            <input type="text" name="referral_code" class="form-control" placeholder="e.g. MED123456" value="<?php echo htmlspecialchars($ref_code); ?>">
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Register <i class="fas fa-user-plus"></i></button>
    </form>
    <p style="text-align: center; margin-top: 1.5rem; color: var(--text-secondary);">Already have an account? <a href="login.php" style="color: var(--primary-color);">Login</a></p>
</div>

<?php include 'includes/footer.php'; ?>
