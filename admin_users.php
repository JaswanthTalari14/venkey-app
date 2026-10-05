<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$success = '';
if (isset($_POST['delete_user'])) {
    $uid = (int)$_POST['user_id'];
    $conn->query("DELETE FROM users WHERE id=$uid");
    $success = "User deleted successfully.";
}

include 'includes/header.php';

// Search and Filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : 'all';

// Server-side Pagination parameters
$per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Build SQL query dynamically
$where_clauses = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ? OR id = ?)";
    $search_like = "%" . $search . "%";
    $search_id = (int)$search;
    $params[] = &$search_like;
    $params[] = &$search_like;
    $params[] = &$search_like;
    $params[] = &$search_id;
    $types .= "sssi";
}

if (!empty($role_filter) && $role_filter !== 'all') {
    $where_clauses[] = "role = ?";
    $params[] = &$role_filter;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// Count total matching users
$count_sql = "SELECT COUNT(*) as total FROM users WHERE $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!empty($types)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_res = $count_stmt->get_result();
$total_users = $count_res ? (int)$count_res->fetch_assoc()['total'] : 0;
$total_pages = max(1, ceil($total_users / $per_page));

// Fetch users for the current page
$data_sql = "SELECT * FROM users WHERE $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params_data = $params;
$types_data = $types . "ii";
$params_data[] = &$per_page;
$params_data[] = &$offset;

$data_stmt = $conn->prepare($data_sql);
$data_stmt->bind_param($types_data, ...$params_data);
$data_stmt->execute();
$users = $data_stmt->get_result();

// Helper to build query links for pagination
function build_user_page_link($p, $search, $role) {
    $query = ['page' => $p];
    if (!empty($search)) $query['search'] = $search;
    if (!empty($role) && $role !== 'all') $query['role'] = $role;
    return '?' . http_build_query($query);
}
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Admin Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Admin Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Admin Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php"><i class="fas fa-chart-pie"></i> Overview</a></li>
            <li><a href="admin_users.php" class="active"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="admin_verify.php"><i class="fas fa-user-md"></i> Verify Doctors & RMPs</a></li>
            <li><a href="admin_bookings.php"><i class="fas fa-calendar-check"></i> All Bookings</a></li>
            <li><a href="admin_orders_management.php"><i class="fas fa-boxes"></i> Order Management</a></li>
            <li><a href="admin_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="admin_referrals.php"><i class="fas fa-gift"></i> Referral Management</a></li>
            <li><a href="admin_referral_settings.php"><i class="fas fa-sliders-h"></i> Referral Settings</a></li>
            <li><a href="admin_wallets.php"><i class="fas fa-wallet"></i> Wallet Management</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback & Complaints</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Manage Users</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">View and manage registered patients, doctors, RMPs, and staff.</p>
        
        <?php if($success): ?><p style="color: #2ed573; margin-bottom: 1rem; padding: 0.8rem; background: rgba(46, 213, 115, 0.1); border-radius: 10px; border-left: 4px solid #2ed573;"><?php echo htmlspecialchars($success); ?></p><?php endif; ?>

        <!-- Search and Role Filter Bar -->
        <div class="glass-panel" style="padding: 1.2rem; margin-bottom: 1.5rem;">
            <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; justify-content: space-between;">
                <div style="flex: 1; min-width: 240px; position: relative;">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, phone, or ID..." value="<?php echo htmlspecialchars($search); ?>" style="padding-left: 2.3rem;">
                    <i class="fas fa-search" style="position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                </div>
                
                <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                    <select name="role" class="form-control" style="width: auto; min-width: 140px;" onchange="this.form.submit()">
                        <option value="all" <?php echo ($role_filter === 'all') ? 'selected' : ''; ?>>All Roles</option>
                        <option value="patient" <?php echo ($role_filter === 'patient') ? 'selected' : ''; ?>>Patient</option>
                        <option value="doctor" <?php echo ($role_filter === 'doctor') ? 'selected' : ''; ?>>Doctor</option>
                        <option value="rmp" <?php echo ($role_filter === 'rmp') ? 'selected' : ''; ?>>RMP</option>
                        <option value="admin" <?php echo ($role_filter === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    </select>

                    <button type="submit" class="btn btn-primary" style="padding: 0.55rem 1rem; font-size: 0.85rem;">Filter</button>
                    <?php if (!empty($search) || $role_filter !== 'all'): ?>
                        <a href="admin_users.php" class="btn btn-outline" style="padding: 0.55rem 0.9rem; font-size: 0.85rem;">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">ID</th>
                        <th style="padding: 1rem;">Name</th>
                        <th style="padding: 1rem;">Email</th>
                        <th style="padding: 1rem;">Phone</th>
                        <th style="padding: 1rem;">Role</th>
                        <th style="padding: 1rem;">Registered</th>
                        <th style="padding: 1rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users && $users->num_rows > 0): ?>
                        <?php while($u = $users->fetch_assoc()): 
                            $u_img_url = get_profile_image_url($u);
                        ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem;">#<?php echo $u['id']; ?></td>
                                <td style="padding: 1rem; font-weight: bold; color: var(--text-primary);">
                                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                                        <?php if (!empty($u_img_url)): ?>
                                            <img src="<?php echo $u_img_url; ?>" alt="" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid var(--primary-color);">
                                        <?php else: ?>
                                            <div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; font-size: 0.85rem; color: var(--text-secondary);">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($u['name']); ?></span>
                                    </div>
                                </td>
                                <td style="padding: 1rem; color: var(--text-secondary);"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td style="padding: 1rem; color: var(--text-secondary);"><?php echo htmlspecialchars($u['phone'] ?? '-'); ?></td>
                                <td style="padding: 1rem;">
                                    <span style="text-transform: uppercase; font-weight: 700; background: var(--glass-border); padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.75rem; color: var(--secondary-color);">
                                        <?php echo htmlspecialchars($u['role']); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; color: var(--text-secondary); font-size: 0.88rem;"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                <td style="padding: 1rem;">
                                    <?php if($u['role'] !== 'admin'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete user completely?');">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-outline" style="border-color: #ff4757; color: #ff4757; font-size: 0.8rem; padding: 0.3rem 0.8rem;">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary); font-size: 0.8rem; opacity: 0.7;">Admin</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="padding: 1.5rem; text-align: center; color: var(--text-secondary);">No users matching criteria.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; pt: 1rem; border-top: 1px solid var(--glass-border); flex-wrap: wrap; gap: 1rem;">
                    <span style="font-size: 0.88rem; color: var(--text-secondary);">
                        Showing <?php echo min($offset + 1, $total_users); ?>–<?php echo min($offset + $per_page, $total_users); ?> of <?php echo $total_users; ?> users
                    </span>
                    <div style="display: flex; gap: 0.4rem; align-items: center;">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo build_user_page_link($page - 1, $search, $role_filter); ?>" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-chevron-left"></i> Previous</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="<?php echo build_user_page_link($i, $search, $role_filter); ?>" class="btn <?php echo ($i === $page) ? 'btn-primary' : 'btn-outline'; ?>" style="padding: 0.4rem 0.75rem; font-size: 0.85rem; min-width: 36px; text-align: center;">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="<?php echo build_user_page_link($page + 1, $search, $role_filter); ?>" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">Next <i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>

