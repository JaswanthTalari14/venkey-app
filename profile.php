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
    $qualification = isset($_POST['qualification']) ? $conn->real_escape_string($_POST['qualification']) : null;
    $experience = isset($_POST['experience']) ? $conn->real_escape_string($_POST['experience']) : null;
    
    // Check if email belongs to someone else
    $check = $conn->query("SELECT id FROM users WHERE email='$email' AND id != $user_id");
    if ($check && $check->num_rows > 0) {
        $error = "Email address is already in use by another account.";
    } else {
        $query = "UPDATE users SET name='$name', email='$email', phone='$phone'";
        if ($specialization !== null) {
            $query .= ", specialization='$specialization'";
        }
        if ($qualification !== null) {
            $query .= ", qualification='$qualification'";
        }
        if ($experience !== null) {
            $query .= ", experience='$experience'";
        }
        
        // Password update logic if filled
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $query .= ", password='$password'";
        }

        // Handle Profile Photo Upload
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_photo'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];

            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed_exts)) {
                $error = "Invalid file format. Only JPG, JPEG, PNG, WEBP, and GIF images are allowed.";
            } elseif ($file_size > 5 * 1024 * 1024) {
                $error = "Image size exceeds 5MB limit.";
            } else {
                $upload_dir = 'uploads/profile_images/';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0755, true);
                }

                $new_filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
                $target_file = $upload_dir . $new_filename;

                if (move_uploaded_file($file_tmp, $target_file)) {
                    // Remove old profile photo if present
                    $old_photo_q = $conn->query("SELECT profile_image FROM users WHERE id = $user_id");
                    if ($old_photo_q && $old_row = $old_photo_q->fetch_assoc()) {
                        if (!empty($old_row['profile_image']) && file_exists($old_row['profile_image'])) {
                            @unlink($old_row['profile_image']);
                        }
                    }
                    $query .= ", profile_image='$target_file'";
                    $_SESSION['profile_image'] = $target_file;
                } else {
                    $error = "Failed to save profile photo upload.";
                }
            }
        }

        // Handle Medical Verification Document Upload for Doctor & RMP
        if (isset($_FILES['verification_doc']) && $_FILES['verification_doc']['error'] === UPLOAD_ERR_OK) {
            $doc_file = $_FILES['verification_doc'];
            $doc_name = $doc_file['name'];
            $doc_tmp = $doc_file['tmp_name'];
            $doc_size = $doc_file['size'];

            $allowed_doc_exts = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
            $d_ext = strtolower(pathinfo($doc_name, PATHINFO_EXTENSION));

            if (!in_array($d_ext, $allowed_doc_exts)) {
                $error = "Invalid document type. Allowed formats: PDF, JPG, PNG, WEBP.";
            } elseif ($doc_size > 10 * 1024 * 1024) {
                $error = "Verification document exceeds 10MB limit.";
            } else {
                $doc_dir = 'uploads/verification_docs/';
                if (!is_dir($doc_dir)) {
                    @mkdir($doc_dir, 0755, true);
                }
                $target_doc = $doc_dir . 'doc_' . $user_id . '_' . time() . '.' . $d_ext;
                if (move_uploaded_file($doc_tmp, $target_doc)) {
                    $query .= ", verification_document='$target_doc'";
                } else {
                    $error = "Failed to upload verification document.";
                }
            }
        }

        if (empty($error)) {
            $query .= " WHERE id=$user_id";
            if ($conn->query($query)) {
                $_SESSION['name'] = $name; // Update session name
                $success = "Profile updated successfully!";
            } else {
                $error = "Database Error: Failed to update profile.";
            }
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
        
        <!-- Profile Avatar & Camera Upload Trigger -->
        <div style="position: relative; width: 130px; height: 130px; margin-bottom: 1.5rem; margin-left: auto; margin-right: auto;">
            <div style="width: 130px; height: 130px; border-radius: 50%; background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 30px rgba(74, 144, 226, 0.4); overflow: hidden; border: 3px solid rgba(255,255,255,0.25);">
                <?php
                    $user_img = '';
                    $possible_fields = ['profile_image', 'image', 'avatar', 'photo'];
                    foreach ($possible_fields as $f) {
                        if (!empty($user[$f]) && file_exists($user[$f])) {
                            $user_img = $user[$f];
                            break;
                        }
                    }
                ?>
                <?php if (!empty($user_img)): ?>
                    <img src="<?php echo htmlspecialchars($user_img); ?>?v=<?php echo filemtime($user_img); ?>" alt="<?php echo htmlspecialchars($user['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <i class="fas <?php 
                        if ($user['role'] === 'doctor') echo 'fa-user-md';
                        elseif ($user['role'] === 'rmp') echo 'fa-user-nurse';
                        else echo 'fa-user-astronaut';
                    ?>" style="font-size: 4rem; color: #fff;"></i>
                <?php endif; ?>
            </div>

            <!-- Quick Camera Upload Icon -->
            <label for="avatar_quick_upload" title="Upload Profile Photo" style="position: absolute; bottom: 2px; right: 2px; width: 38px; height: 38px; border-radius: 50%; background: var(--primary-color); color: #ffffff; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.35); border: 2px solid #ffffff; transition: transform 0.2s ease;">
                <i class="fas fa-camera" style="font-size: 0.95rem;"></i>
            </label>
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
        
        <form method="POST" action="" enctype="multipart/form-data">
            <!-- Hidden quick upload input triggered by camera button -->
            <input type="file" id="avatar_quick_upload" name="profile_photo" accept="image/png, image/jpeg, image/webp, image/gif" style="display: none;" onchange="this.form.submit();">

            <!-- Profile Photo Upload Field -->
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="font-weight: 500;"><i class="fas fa-camera"></i> Profile Photo</label>
                <input type="file" name="profile_photo" class="form-control" accept="image/png, image/jpeg, image/webp, image/gif" style="background: rgba(0,0,0,0.2);">
                <small style="color: var(--text-secondary); display: block; margin-top: 0.35rem;"><i class="fas fa-info-circle"></i> Select an image to upload or update your profile photo (Max 5MB).</small>
            </div>

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

            <div style="display: flex; gap: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1; min-width: 180px; margin: 0;">
                    <label style="font-weight: 500;"><i class="fas fa-graduation-cap"></i> Qualification</label>
                    <input type="text" name="qualification" class="form-control" value="<?php echo htmlspecialchars($user['qualification'] ?? 'MBBS, MD'); ?>" placeholder="e.g. MBBS, MD, MS" style="background: rgba(0,0,0,0.2);">
                </div>
                <div class="form-group" style="flex: 1; min-width: 180px; margin: 0;">
                    <label style="font-weight: 500;"><i class="fas fa-award"></i> Experience</label>
                    <input type="text" name="experience" class="form-control" value="<?php echo htmlspecialchars($user['experience'] ?? '5+ Years'); ?>" placeholder="e.g. 5+ Years" style="background: rgba(0,0,0,0.2);">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="font-weight: 500;"><i class="fas fa-file-medical"></i> Verification / License Document</label>
                <input type="file" name="verification_doc" class="form-control" accept=".pdf,image/png,image/jpeg,image/webp" style="background: rgba(0,0,0,0.2);">
                <?php
                    $v_doc_path = $user['verification_document'] ?? ($user['license_document'] ?? ($user['document_path'] ?? ''));
                ?>
                <?php if (!empty($v_doc_path) && file_exists($v_doc_path)): ?>
                    <small style="color: #2ed573; display: block; margin-top: 0.35rem;"><i class="fas fa-check-circle"></i> Document uploaded (<?php echo basename($v_doc_path); ?>). Upload a new file to replace.</small>
                <?php else: ?>
                    <small style="color: var(--text-secondary); display: block; margin-top: 0.35rem;"><i class="fas fa-info-circle"></i> Upload your medical license certificate (PDF, JPG, PNG - Max 10MB) for Admin verification.</small>
                <?php endif; ?>
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
