<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'patient';
$msg = '';

// Handle Logout All Devices (Server-side session invalidation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout_all_devices'])) {
    $conn->query("DELETE FROM user_sessions WHERE user_id = $user_id");
    // Destroy current session as well
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header("Location: login.php?msg=" . urlencode("Logged out from all devices successfully."));
    exit;
}

// Fetch user info & active sessions
$u_res = $conn->query("SELECT * FROM users WHERE id = $user_id");
$user_data = $u_res ? $u_res->fetch_assoc() : [];

$sessions_res = $conn->query("SELECT * FROM user_sessions WHERE user_id = $user_id ORDER BY last_active DESC");

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Security Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Account Security</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Security Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile Settings</a></li>
            <li><a href="security_center.php" class="active"><i class="fas fa-shield-alt"></i> Security Center</a></li>
            <li><a href="data_privacy.php"><i class="fas fa-user-lock"></i> Data & Privacy</a></li>
            <li><a href="recently_viewed.php"><i class="fas fa-history"></i> Recently Viewed</a></li>
            <li><a href="favorites.php"><i class="fas fa-heart"></i> Favorites</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <h2><i class="fas fa-shield-alt" style="color: var(--primary-color);"></i> Account Security Center</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Manage active login sessions, security settings, and device access.</p>

        <?php if ($msg): ?>
            <div style="background: rgba(46, 213, 115, 0.15); border: 1px solid #2ed573; color: #2ed573; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <!-- Account Info Card -->
            <div class="glass-panel" style="padding: 1.5rem;">
                <h3 style="margin-top: 0; color: var(--text-primary);"><i class="fas fa-user-shield"></i> Security Overview</h3>
                <p style="font-size: 0.9rem; color: var(--text-secondary); margin: 0.8rem 0;">
                    <strong>Account Email:</strong> <?php echo htmlspecialchars($user_data['email'] ?? ''); ?><br>
                    <strong>Phone Number:</strong> <?php echo htmlspecialchars($user_data['phone'] ?? ''); ?><br>
                    <strong>Account Role:</strong> <span style="text-transform: uppercase; font-weight: bold; color: var(--primary-color);"><?php echo htmlspecialchars($user_data['role'] ?? ''); ?></span><br>
                    <strong>Last Login Timestamp:</strong> <?php echo !empty($user_data['last_login']) ? date('M d, Y h:i A', strtotime($user_data['last_login'])) : 'Active Session'; ?>
                </p>
            </div>

            <!-- Logout All Devices Action -->
            <div class="glass-panel" style="padding: 1.5rem; border-left: 4px solid #ff4757;">
                <h3 style="margin-top: 0; color: #ff4757;"><i class="fas fa-power-off"></i> Logout All Devices</h3>
                <p style="font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 1.2rem;">If you suspect unauthorized account access, click below to immediately terminate all active sessions across browsers and devices.</p>
                <form method="POST" onsubmit="return confirm('Log out from ALL active devices immediately?')">
                    <input type="hidden" name="logout_all_devices" value="1">
                    <button type="submit" class="btn btn-outline" style="color: #ff4757; border-color: #ff4757; width: 100%;">
                        <i class="fas fa-sign-out-alt"></i> Terminate All Active Sessions
                    </button>
                </form>
            </div>
        </div>

        <!-- Active Device Sessions -->
        <div class="glass-panel" style="padding: 1.5rem;">
            <h3 style="margin-top: 0; color: var(--text-primary);"><i class="fas fa-desktop"></i> Active Device Sessions</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; text-align: left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border);">
                            <th style="padding: 0.8rem;">Browser / Device</th>
                            <th style="padding: 0.8rem;">IP Address</th>
                            <th style="padding: 0.8rem;">Last Active</th>
                            <th style="padding: 0.8rem;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($sessions_res && $sessions_res->num_rows > 0): ?>
                            <?php while($s = $sessions_res->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 0.8rem; font-weight: bold; color: var(--text-primary);"><?php echo htmlspecialchars(substr($s['user_agent'] ?? 'Web Browser', 0, 40)); ?>...</td>
                                    <td style="padding: 0.8rem; font-family: monospace; color: var(--primary-color);"><?php echo htmlspecialchars($s['ip_address'] ?? '127.0.0.1'); ?></td>
                                    <td style="padding: 0.8rem; color: var(--text-secondary);"><?php echo date('M d, Y h:i A', strtotime($s['last_active'])); ?></td>
                                    <td style="padding: 0.8rem;"><span style="color: #2ed573; font-weight: bold;">Active</span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 0.8rem; font-weight: bold;">Current Web Browser</td>
                                <td style="padding: 0.8rem; font-family: monospace; color: var(--primary-color);"><?php echo $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'; ?></td>
                                <td style="padding: 0.8rem; color: var(--text-secondary);">Just now</td>
                                <td style="padding: 0.8rem;"><span style="color: #2ed573; font-weight: bold;">Active Session</span></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
