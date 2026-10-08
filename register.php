<?php
require_once 'config.php';

// Instant Redirect for Logged-In Users
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'patient';
    if ($role === 'patient') {
        header("Location: patient_dashboard.php");
    } elseif ($role === 'doctor') {
        header("Location: doctor_dashboard.php");
    } elseif ($role === 'admin') {
        header("Location: admin_dashboard.php");
    } elseif ($role === 'rmp') {
        header("Location: rmp_dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

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
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $raw_password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'patient';

    if (empty($name) || empty($email) || empty($phone) || empty($raw_password)) {
        $error = "Please fill in all required fields.";
    } else {
        // Prepared statement for duplicate email check (fast & safe)
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();

        if ($res_check && $res_check->num_rows > 0) {
            $error = "Email already registered.";
        } else {
            $password = password_hash($raw_password, PASSWORD_DEFAULT);

            // Assign mock coordinates to doctors within 10km radius so they appear on the Patient tracking map automatically
            $lat = ($role == 'doctor') ? (28.7042 + (rand(-50, 50) / 1000)) : null;
            $lon = ($role == 'doctor') ? (77.1026 + (rand(-50, 50) / 1000)) : null;
            $spec = ($role == 'doctor') ? "General Practitioner" : null;

            $stmt_ins = $conn->prepare("INSERT INTO users (name, email, phone, password, role, latitude, longitude, specialization) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_ins->bind_param("sssssdds", $name, $email, $phone, $password, $role, $lat, $lon, $spec);

            if ($stmt_ins->execute()) {
                $new_user_id = $stmt_ins->insert_id;

                // Process Referral linkage if patient and referral code exists
                if ($role === 'patient' && !empty($ref_code)) {
                    require_once 'includes/referral_functions.php';
                    register_referral_claim($new_user_id, $ref_code);
                }
                unset($_SESSION['pending_ref']);

                $success = "Registration successful! You can now login.";
            } else {
                $error = "Registration failed. Try again.";
            }
        }
    }
}
include 'includes/header.php';
?>

<style>
@keyframes registerPageEntrance {
    0% { opacity: 0; transform: translateY(24px); }
    100% { opacity: 1; transform: translateY(0); }
}

.auth-split-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 160px);
    padding: 2.5rem 1rem;
    animation: registerPageEntrance 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.auth-split-card {
    width: 100%;
    max-width: 960px;
    display: grid;
    grid-template-columns: 1fr 1.15fr;
    background: linear-gradient(135deg, rgba(16, 26, 43, 0.95) 0%, rgba(24, 38, 64, 0.95) 100%);
    border: 1px solid rgba(80, 227, 194, 0.35);
    border-radius: 28px;
    overflow: hidden;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5), 0 0 40px rgba(80, 227, 194, 0.12);
    backdrop-filter: blur(16px);
}

.register-hero-column {
    background: linear-gradient(135deg, rgba(80, 227, 194, 0.12) 0%, rgba(74, 144, 226, 0.15) 100%);
    padding: 3rem 2.2rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    border-right: 1px solid rgba(255, 255, 255, 0.08);
    position: relative;
}

.register-hero-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(80, 227, 194, 0.18);
    border: 1px solid rgba(80, 227, 194, 0.4);
    color: var(--secondary-color);
    padding: 0.4rem 1rem;
    border-radius: 30px;
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    margin-bottom: 1.2rem;
}

.register-hero-title {
    font-size: 1.9rem;
    font-weight: 800;
    color: #ffffff;
    line-height: 1.25;
    margin-bottom: 0.9rem;
    letter-spacing: -0.5px;
}

.register-hero-desc {
    font-size: 0.9rem;
    color: var(--text-secondary);
    line-height: 1.5;
    margin-bottom: 2rem;
}

.register-perks-list {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    margin-bottom: 2rem;
}

.register-perk-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.88rem;
    color: #ffffff;
    font-weight: 500;
}

.register-perk-item i {
    color: #2ed573;
    font-size: 1rem;
    flex-shrink: 0;
}

.register-stats-pill {
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.08);
    padding: 0.9rem 1.2rem;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: space-around;
    text-align: center;
}

.stats-item-val {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--secondary-color);
}

.stats-item-lbl {
    font-size: 0.72rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.15rem;
}

.register-form-column {
    padding: 3rem 2.5rem;
}

.register-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: #ffffff;
    margin-bottom: 0.3rem;
}

.register-subtitle {
    font-size: 0.88rem;
    color: var(--text-secondary);
    margin-bottom: 1.8rem;
}

.input-group-custom {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon-prefix {
    position: absolute;
    left: 1rem;
    color: var(--secondary-color);
    font-size: 1rem;
    pointer-events: none;
    transition: color 0.2s ease;
}

.input-field-custom {
    width: 100%;
    padding: 0.85rem 1rem 0.85rem 2.7rem;
    background: rgba(0, 0, 0, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 14px;
    color: #ffffff;
    font-size: 0.95rem;
    transition: all 0.25s ease;
    outline: none;
}

.input-field-custom:focus {
    border-color: var(--secondary-color);
    background: rgba(0, 0, 0, 0.4);
    box-shadow: 0 0 15px rgba(80, 227, 194, 0.25);
}

.input-toggle-suffix {
    position: absolute;
    right: 1rem;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 0.95rem;
    padding: 0.3rem;
    transition: color 0.2s ease;
}

.input-toggle-suffix:hover {
    color: var(--secondary-color);
}

.btn-register-submit {
    width: 100%;
    padding: 0.9rem 1.5rem;
    font-size: 1rem;
    font-weight: 700;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--secondary-color) 0%, #2ed573 100%);
    color: #0b1329;
    border: none;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 6px 20px rgba(80, 227, 194, 0.3);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    margin-top: 1.2rem;
}

.btn-register-submit:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(80, 227, 194, 0.45);
    background: linear-gradient(135deg, #64ebd0 0%, #3fe384 100%);
}

.btn-register-submit:active:not(:disabled) {
    transform: translateY(0) scale(0.98);
}

.btn-register-submit:disabled {
    opacity: 0.75;
    cursor: not-allowed;
}

.auth-alert-msg {
    border-radius: 12px;
    padding: 0.8rem 1rem;
    font-size: 0.88rem;
    font-weight: 500;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.auth-alert-error {
    background: rgba(255, 71, 87, 0.12);
    border: 1px solid rgba(255, 71, 87, 0.4);
    color: #ff4757;
}

.auth-alert-success {
    background: rgba(46, 213, 115, 0.12);
    border: 1px solid rgba(46, 213, 115, 0.4);
    color: #2ed573;
}

@media (max-width: 860px) {
    .auth-split-card {
        grid-template-columns: 1fr;
        max-width: 480px;
    }
    .register-hero-column {
        border-right: none;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        padding: 2.2rem 1.8rem;
    }
    .register-form-column {
        padding: 2.2rem 1.8rem;
    }
}
</style>

<div class="auth-split-wrapper">
    <div class="auth-split-card">
        <!-- Left Hero Feature Banner Column -->
        <div class="register-hero-column">
            <div>
                <div class="register-hero-tag">
                    <i class="fas fa-heartbeat"></i> Join MedicalAk
                </div>
                <h2 class="register-hero-title">Smart Healthcare at Your Fingertips</h2>
                <p class="register-hero-desc">Create your account to book doctor visits, order genuine medicines, track health records & unlock Digital Medical Card discounts.</p>
                
                <div class="register-perks-list">
                    <div class="register-perk-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Instant Online & Doorstep Doctor Appointments</span>
                    </div>
                    <div class="register-perk-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Genuine Medicine Delivery with Real-Time Tracking</span>
                    </div>
                    <div class="register-perk-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Digital Medical Card Eligibility (10% Consult & 5% Medicine Discounts)</span>
                    </div>
                    <div class="register-perk-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Verified Health Vault & E-Prescriptions</span>
                    </div>
                </div>
            </div>

            <div class="register-stats-pill">
                <div>
                    <div class="stats-item-val">10,000+</div>
                    <div class="stats-item-lbl">Happy Patients</div>
                </div>
                <div style="border-left: 1px solid rgba(255,255,255,0.1); padding-left: 1rem;">
                    <div class="stats-item-val">Verified</div>
                    <div class="stats-item-lbl">Doctors & RMPs</div>
                </div>
            </div>
        </div>

        <!-- Right Form Column -->
        <div class="register-form-column">
            <h2 class="register-title">Create Account</h2>
            <p class="register-subtitle">Fill in your details below to get started.</p>

            <?php if($error): ?>
                <div class="auth-alert-msg auth-alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="auth-alert-msg auth-alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm" onsubmit="handleRegisterSubmit(event)">
                <div class="form-group" style="margin-bottom: 1.1rem;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem;">Full Name</label>
                    <div class="input-group-custom">
                        <i class="fas fa-user input-icon-prefix"></i>
                        <input type="text" name="name" class="input-field-custom" placeholder="John Doe" required autocomplete="name">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.1rem;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem;">Email Address</label>
                    <div class="input-group-custom">
                        <i class="fas fa-envelope input-icon-prefix"></i>
                        <input type="email" name="email" class="input-field-custom" placeholder="john@example.com" required autocomplete="email">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.1rem;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem;">Phone Number</label>
                    <div class="input-group-custom">
                        <i class="fas fa-phone input-icon-prefix"></i>
                        <input type="text" name="phone" class="input-field-custom" placeholder="10-digit mobile number" required autocomplete="tel">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.1rem;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem;">Password</label>
                    <div class="input-group-custom">
                        <i class="fas fa-lock input-icon-prefix"></i>
                        <input type="password" name="password" id="regPasswordInput" class="input-field-custom" placeholder="Must be at least 6 characters" required autocomplete="new-password">
                        <i class="fas fa-eye input-toggle-suffix" id="regTogglePassword" onclick="toggleRegPassword()" title="Show/Hide Password"></i>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.1rem;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem;">I am a</label>
                    <div class="input-group-custom">
                        <i class="fas fa-user-tag input-icon-prefix"></i>
                        <select name="role" class="input-field-custom" required style="cursor: pointer;">
                            <option value="patient">Patient</option>
                            <option value="doctor">Doctor</option>
                            <option value="rmp">RMP</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.1rem;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem;">Referral Code (Optional)</label>
                    <div class="input-group-custom">
                        <i class="fas fa-gift input-icon-prefix"></i>
                        <input type="text" name="referral_code" class="input-field-custom" placeholder="e.g. MED123456" value="<?php echo htmlspecialchars($ref_code); ?>">
                    </div>
                </div>

                <button type="submit" id="btnRegisterSubmit" class="btn-register-submit">
                    <span id="regBtnText">Create Account</span> <i class="fas fa-user-plus" id="regBtnIcon"></i>
                </button>
            </form>

            <p style="text-align: center; margin-top: 1.8rem; font-size: 0.9rem; color: var(--text-secondary);">
                Already have an account? <a href="login.php" style="color: var(--primary-color); font-weight: 700; text-decoration: none; margin-left: 0.3rem;">Log In</a>
            </p>
        </div>
    </div>
</div>

<script>
function toggleRegPassword() {
    var pwdInput = document.getElementById('regPasswordInput');
    var toggleIcon = document.getElementById('regTogglePassword');
    if (!pwdInput || !toggleIcon) return;
    if (pwdInput.type === 'password') {
        pwdInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        pwdInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

function handleRegisterSubmit(e) {
    var btn = document.getElementById('btnRegisterSubmit');
    var btnText = document.getElementById('regBtnText');
    var btnIcon = document.getElementById('regBtnIcon');
    
    if (btn && !btn.disabled) {
        btn.disabled = true;
        if (btnText) btnText.innerText = 'Creating Account...';
        if (btnIcon) btnIcon.className = 'fas fa-spinner fa-spin';
    }
}
</script>

<?php include 'includes/footer.php'; ?>
