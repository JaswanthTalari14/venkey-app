<?php
require_once 'config.php';
require_once 'includes/security_helper.php';

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
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (is_login_locked($conn, $ip, $email)) {
        $error = "Too many failed login attempts. Please try again in 15 minutes for security.";
    } else {
        $stmt = $conn->prepare("SELECT id, name, email, password, role, profile_image, image, avatar, photo FROM users WHERE email = ? LIMIT 1");
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

                $user_p_img = '';
                foreach (['profile_image', 'image', 'avatar', 'photo'] as $f) {
                    if (!empty($user[$f])) {
                        $user_p_img = $user[$f];
                        break;
                    }
                }
                $_SESSION['profile_image'] = $user_p_img;

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

<style>
@keyframes loginCardEntrance {
    0% { opacity: 0; transform: translateY(24px) scale(0.97); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
}

.auth-wrapper-login {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 160px);
    padding: 2.5rem 1rem;
    position: relative;
}

.auth-card-login {
    width: 100%;
    max-width: 440px;
    background: linear-gradient(135deg, rgba(20, 30, 48, 0.94) 0%, rgba(36, 59, 85, 0.94) 100%);
    border: 1px solid rgba(74, 144, 226, 0.35);
    border-radius: 24px;
    padding: 2.5rem 2.2rem;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4), 0 0 30px rgba(74, 144, 226, 0.15);
    backdrop-filter: blur(16px);
    animation: loginCardEntrance 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    position: relative;
    overflow: hidden;
}

.auth-card-login::before {
    content: '';
    position: absolute;
    top: -60px;
    left: -60px;
    width: 160px;
    height: 160px;
    background: radial-gradient(circle, rgba(74, 144, 226, 0.3) 0%, transparent 70%);
    pointer-events: none;
}

.auth-header-login {
    text-align: center;
    margin-bottom: 2rem;
}

.auth-brand-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(74, 144, 226, 0.15);
    border: 1px solid rgba(74, 144, 226, 0.35);
    padding: 0.4rem 1rem;
    border-radius: 30px;
    color: var(--primary-color);
    font-size: 0.82rem;
    font-weight: 700;
    margin-bottom: 0.9rem;
    letter-spacing: 0.5px;
}

.auth-title-login {
    font-size: 1.85rem;
    font-weight: 800;
    color: #ffffff;
    margin-bottom: 0.4rem;
    letter-spacing: -0.5px;
}

.auth-subtitle-login {
    font-size: 0.88rem;
    color: var(--text-secondary);
    line-height: 1.4;
}

.input-group-custom {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon-prefix {
    position: absolute;
    left: 1rem;
    color: var(--primary-color);
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
    border-color: var(--primary-color);
    background: rgba(0, 0, 0, 0.4);
    box-shadow: 0 0 15px rgba(74, 144, 226, 0.25);
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
    color: var(--primary-color);
}

.btn-auth-submit {
    width: 100%;
    padding: 0.9rem 1.5rem;
    font-size: 1rem;
    font-weight: 700;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--primary-color) 0%, #357abd 100%);
    color: #ffffff;
    border: none;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 6px 20px rgba(74, 144, 226, 0.35);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    margin-top: 1.2rem;
}

.btn-auth-submit:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(74, 144, 226, 0.5);
    background: linear-gradient(135deg, #5198eb 0%, #3a82cf 100%);
}

.btn-auth-submit:active:not(:disabled) {
    transform: translateY(0) scale(0.98);
}

.btn-auth-submit:disabled {
    opacity: 0.75;
    cursor: not-allowed;
}

.auth-error-alert {
    background: rgba(255, 71, 87, 0.12);
    border: 1px solid rgba(255, 71, 87, 0.4);
    border-radius: 12px;
    color: #ff4757;
    padding: 0.8rem 1rem;
    font-size: 0.88rem;
    font-weight: 500;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
</style>

<div class="auth-wrapper-login">
    <div class="auth-card-login">
        <div class="auth-header-login">
            <div class="auth-brand-badge">
                <i class="fas fa-heartbeat"></i> MedicalAk Healthcare
            </div>
            <h2 class="auth-title-login">Welcome Back</h2>
            <p class="auth-subtitle-login">Log in to manage consultations, orders & health records</p>
        </div>

        <?php if($error): ?>
            <div class="auth-error-alert">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm" onsubmit="handleLoginSubmit(event)">
            <div class="form-group" style="margin-bottom: 1.2rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem;">Email Address</label>
                <div class="input-group-custom">
                    <i class="fas fa-envelope input-icon-prefix"></i>
                    <input type="email" name="email" class="input-field-custom" placeholder="Enter your email address" required autocomplete="email">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem;">Password</label>
                <div class="input-group-custom">
                    <i class="fas fa-lock input-icon-prefix"></i>
                    <input type="password" name="password" id="loginPasswordInput" class="input-field-custom" placeholder="Enter your password" required autocomplete="current-password">
                    <i class="fas fa-eye input-toggle-suffix" id="loginTogglePassword" onclick="toggleLoginPassword()" title="Show/Hide Password"></i>
                </div>
                <div style="text-align: right; margin-top: 0.6rem;">
                    <a href="forgot_password.php" style="color: var(--primary-color); font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: color 0.2s ease;">Forgot Password?</a>
                </div>
            </div>

            <button type="submit" id="btnLoginSubmit" class="btn-auth-submit">
                <span id="loginBtnText">Login</span> <i class="fas fa-sign-in-alt" id="loginBtnIcon"></i>
            </button>
        </form>

        <p style="text-align: center; margin-top: 1.8rem; font-size: 0.9rem; color: var(--text-secondary);">
            Don't have an account? <a href="register.php" style="color: var(--secondary-color); font-weight: 700; text-decoration: none; margin-left: 0.3rem;">Sign Up</a>
        </p>
    </div>
</div>

<script>
function toggleLoginPassword() {
    var pwdInput = document.getElementById('loginPasswordInput');
    var toggleIcon = document.getElementById('loginTogglePassword');
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

function handleLoginSubmit(e) {
    var btn = document.getElementById('btnLoginSubmit');
    var btnText = document.getElementById('loginBtnText');
    var btnIcon = document.getElementById('loginBtnIcon');
    
    if (btn && !btn.disabled) {
        btn.disabled = true;
        if (btnText) btnText.innerText = 'Logging in...';
        if (btnIcon) btnIcon.className = 'fas fa-spinner fa-spin';
    }
}
</script>

<?php include 'includes/footer.php'; ?>
