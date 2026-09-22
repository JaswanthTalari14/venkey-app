<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'includes/header.php';

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $specialization = isset($_POST['specialization']) ? $conn->real_escape_string($_POST['specialization']) : null;
    
    // Check if email belongs to someone else
    $check = $conn->query("SELECT id FROM users WHERE email='$email' AND id != $user_id");
    if ($check && $check->num_rows > 0) {
        $error = "Email address is already in use by another account.";
    } else {
        $query = "UPDATE users SET name='$name', email='$email', phone='$phone'";
        if ($specialization !== null) {
            $query .= ", specialization='$specialization'";
        }
        
        // Password update logic if filled
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $query .= ", password='$password'";
        }
        
        $query .= " WHERE id=$user_id";
        
        if ($conn->query($query)) {
            $_SESSION['name'] = $name; // Update session name
            $success = "Profile updated successfully!";
        } else {
            $error = "Database Error: Failed to update profile.";
        }
    }
}

$user = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
?>

<div class="profile-layout" style="display: flex; gap: 2rem; max-width: 1100px; margin: 2rem auto; flex-wrap: wrap; padding: 0 5%;">
    
    <!-- Left Column: Virtual ID Card -->
    <div class="glass-panel" style="flex: 1; min-width: 300px; text-align: center; padding: 3rem 2rem; display: flex; flex-direction: column; align-items: center; justify-content: center; background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0) 100%); position: relative; overflow: hidden;">
        
        <!-- Decorative Background Circle -->
        <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: var(--primary-color); border-radius: 50%; opacity: 0.1; filter: blur(30px);"></div>
        
        <div style="width: 130px; height: 130px; border-radius: 50%; background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem; box-shadow: 0 10px 30px rgba(74, 144, 226, 0.4);">
            <i class="fas fa-user-astronaut" style="font-size: 4rem; color: #fff;"></i>
        </div>
        
        <h2 style="font-size: 2rem; margin-bottom: 0.5rem; font-weight: 800; letter-spacing: -0.5px;"><?php echo htmlspecialchars($user['name']); ?></h2>
        
        <!-- Premium Role Badge -->
        <?php
            $badge_color = 'var(--text-secondary)';
            if($user['role'] == 'admin') $badge_color = '#ff4757';
            if($user['role'] == 'doctor') $badge_color = 'var(--primary-color)';
            if($user['role'] == 'patient') $badge_color = 'var(--secondary-color)';
            if($user['role'] == 'rmp') $badge_color = 'var(--accent)';
        ?>
        <p style="background: rgba(255,255,255,0.05); color: <?php echo $badge_color; ?>; padding: 0.5rem 1.5rem; border-radius: 30px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; font-size: 0.8rem; margin-bottom: 1rem; border: 1px solid <?php echo $badge_color; ?>;">
            <i class="fas fa-id-badge"></i> <?php echo $user['role']; ?>
        </p>

        <?php if (!empty($user['specialization'])): ?>
            <p style="color: var(--text-secondary); margin-bottom: 1rem; font-size: 1.1rem;"><i class="fas fa-stethoscope" style="color: var(--primary-color);"></i> <?php echo htmlspecialchars($user['specialization']); ?></p>
        <?php endif; ?>
        
        <div style="width: 100%; height: 1px; background: var(--glass-border); margin: 2rem 0;"></div>
        
        <div style="width: 100%; display: flex; justify-content: space-around; color: var(--text-secondary); font-size: 0.9rem;">
            <div>
                <i class="fas fa-envelope" style="display: block; margin-bottom: 0.5rem; font-size: 1.2rem; color: var(--secondary-color);"></i>
                <span>Registered</span>
            </div>
            <div>
                <i class="fas fa-calendar-alt" style="display: block; margin-bottom: 0.5rem; font-size: 1.2rem; color: var(--primary-color);"></i>
                <span><?php echo date('M Y', strtotime($user['created_at'])); ?></span>
            </div>
        </div>
    </div>

    <!-- Right Column: Settings Form -->
    <div class="glass-panel" style="flex: 2; min-width: 400px; padding: 3rem;">
        <h3 style="margin-bottom: 2.5rem; border-bottom: 2px solid var(--primary-color); display: inline-block; padding-bottom: 0.5rem; font-size: 1.5rem;"><i class="fas fa-user-edit"></i> Edit Profile Details</h3>
        
        <?php if($error): ?><div style="background: rgba(255, 71, 87, 0.1); border-left: 4px solid #ff4757; padding: 1rem; margin-bottom: 2rem; color: #ff4757; border-radius: 0 8px 8px 0;"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div><?php endif; ?>
        <?php if($success): ?><div style="background: rgba(46, 213, 115, 0.1); border-left: 4px solid #2ed573; padding: 1rem; margin-bottom: 2rem; color: #2ed573; border-radius: 0 8px 8px 0;"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div><?php endif; ?>
        
        <form method="POST" action="">
            <div style="display: flex; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="form-group" style="flex: 1; margin: 0;">
                    <label style="font-weight: 500;"><i class="fas fa-user"></i> Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required style="background: rgba(0,0,0,0.2);">
                </div>
                <div class="form-group" style="flex: 1; margin: 0;">
                    <label style="font-weight: 500;"><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required style="background: rgba(0,0,0,0.2);">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="font-weight: 500;"><i class="fas fa-phone-alt"></i> Phone Number</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" required style="background: rgba(0,0,0,0.2);">
            </div>
            
            <?php if ($user['role'] == 'doctor' || $user['role'] == 'rmp'): ?>
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="font-weight: 500;"><i class="fas fa-briefcase-medical"></i> Specialization / Professional Title</label>
                <input type="text" name="specialization" class="form-control" value="<?php echo htmlspecialchars($user['specialization']); ?>" placeholder="e.g. Cardiologist, General Checkups" style="background: rgba(0,0,0,0.2);">
            </div>
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label style="font-weight: 500;"><i class="fas fa-lock"></i> Account Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter a new password (leave blank to keep current)" style="background: rgba(0,0,0,0.2); border-color: rgba(255,255,255,0.05);">
                <small style="color: var(--text-secondary); display: block; margin-top: 0.5rem;"><i class="fas fa-info-circle"></i> Only fill this if you want to change your password.</small>
            </div>
            
            <button type="submit" name="update_profile" class="btn btn-primary" style="width: 100%; font-size: 1.1rem; padding: 1rem;"><i class="fas fa-save"></i> Save Profile Changes</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
