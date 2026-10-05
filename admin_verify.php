<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once 'includes/notification_functions.php';

// Handle Verification Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_verify') {
    $target_user_id = (int)$_POST['user_id'];
    $new_status = (int)$_POST['status'];
    $admin_id = (int)$_SESSION['user_id'];

    $u_res = $conn->query("SELECT role, is_verified FROM users WHERE id = $target_user_id AND role IN ('doctor', 'rmp')");
    if ($u_res && $u_res->num_rows > 0) {
        $u_row = $u_res->fetch_assoc();
        $prev_status = (int)$u_row['is_verified'];
        $role = $u_row['role'];

        $stmt = $conn->prepare("UPDATE users SET is_verified = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $new_status, $target_user_id);
            $stmt->execute();

            // Log Verification History
            $conn->query("INSERT INTO verification_history (user_id, admin_id, previous_status, new_status, reason) VALUES ($target_user_id, $admin_id, $prev_status, $new_status, 'Admin verification status update')");

            // Send Realtime Notification
            $status_txt = $new_status ? "VERIFIED" : "UNVERIFIED";
            create_notification($target_user_id, "Account Verification Update", "Your account verification status has been updated to {$status_txt} by Admin.", "info", "verification", (string)$target_user_id, $role);
        }
    }

    $page_param = isset($_GET['page']) ? '?page=' . (int)$_GET['page'] : '';
    header("Location: admin_verify.php" . $page_param);
    exit;
}

include 'includes/header.php';

// Pagination setup for Smart Data Loading
$per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

$count_res = $conn->query("SELECT COUNT(*) as total FROM users WHERE role IN ('doctor', 'rmp')");
$total_records = ($count_res) ? (int)$count_res->fetch_assoc()['total'] : 0;
$total_pages = max(1, ceil($total_records / $per_page));

// Retrieve doctors and RMPs for the current page
$stmt = $conn->prepare("SELECT * FROM users WHERE role IN ('doctor', 'rmp') ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $per_page, $offset);
$stmt->execute();
$professionals = $stmt->get_result();
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
            <li><a href="admin_users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="admin_verify.php" class="active"><i class="fas fa-user-md"></i> Verify Doctors & RMPs</a></li>
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
        <h2>Verify Medical Professionals</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Review licenses and verify Doctors & RMPs before they appear on the main platform.</p>

        <div class="glass-panel" style="padding: 1.5rem;">
            <?php if ($professionals && $professionals->num_rows > 0): ?>
                <?php while($p = $professionals->fetch_assoc()): ?>
                    <?php
                        // Check for uploaded profile image across common column names
                        $img_src = '';
                        $possible_img_fields = ['profile_image', 'image', 'avatar', 'photo'];
                        foreach ($possible_img_fields as $field) {
                            if (!empty($p[$field]) && file_exists($p[$field])) {
                                $img_src = $p[$field];
                                break;
                            }
                        }

                        // Check for verification document
                        $doc_src = '';
                        $possible_doc_fields = ['verification_document', 'license_document', 'document_path'];
                        foreach ($possible_doc_fields as $dfield) {
                            if (!empty($p[$dfield]) && file_exists($p[$dfield])) {
                                $doc_src = $p[$dfield];
                                break;
                            }
                        }
                    ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--glass-border); padding: 1.1rem 0; gap: 1rem; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 1rem; flex: 1; min-width: 260px;">
                            <!-- Doctor Profile Image / Avatar Display -->
                            <?php if (!empty($img_src)): ?>
                                <img src="<?php echo htmlspecialchars($img_src); ?>?v=<?php echo filemtime($img_src); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" style="width: 54px; height: 54px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-color); flex-shrink: 0; background: rgba(0,0,0,0.15);">
                            <?php else: ?>
                                <div style="width: 54px; height: 54px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 1.4rem; border: 2px solid var(--primary-color); flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.15);">
                                    <i class="fas <?php echo ($p['role'] === 'rmp') ? 'fa-user-nurse' : 'fa-user-md'; ?>"></i>
                                </div>
                            <?php endif; ?>

                            <div>
                                <h4 style="color: var(--text-primary); font-weight: 700; font-size: 1.05rem; margin: 0 0 0.3rem 0; display: flex; align-items: center; flex-wrap: wrap; gap: 0.4rem;">
                                    <span><?php echo htmlspecialchars($p['name']); ?></span>
                                    <span style="font-size: 0.75rem; background: var(--primary-color); color: #ffffff; padding: 0.15rem 0.55rem; border-radius: 12px; text-transform: uppercase; font-weight: 700;"><?php echo htmlspecialchars($p['role']); ?></span>
                                    <?php if (!empty($p['is_verified'])): ?>
                                        <span style="font-size: 0.72rem; background: rgba(46, 213, 115, 0.15); color: #2ed573; border: 1px solid #2ed573; padding: 0.1rem 0.5rem; border-radius: 12px; font-weight: 600;">
                                            <i class="fas fa-check-circle"></i> Verified
                                        </span>
                                    <?php endif; ?>
                                </h4>
                                <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 0; line-height: 1.45;">
                                    Specialization: <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($p['specialization'] ?? 'General / RMP'); ?></strong> 
                                    <span style="opacity: 0.6; margin: 0 0.3rem;">|</span> 
                                    Phone: <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($p['phone']); ?></strong>
                                    <?php if (!empty($p['email'])): ?>
                                        <span style="opacity: 0.6; margin: 0 0.3rem;">|</span> Email: <span style="color: var(--text-secondary);"><?php echo htmlspecialchars($p['email']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($p['qualification'])): ?>
                                        <span style="opacity: 0.6; margin: 0 0.3rem;">|</span> Qual: <span style="color: var(--text-secondary);"><?php echo htmlspecialchars($p['qualification']); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                            <!-- Document Viewer Button -->
                            <?php if (!empty($doc_src)): ?>
                                <button type="button" class="btn btn-primary" onclick="openDocViewer('<?php echo htmlspecialchars(addslashes($doc_src)); ?>', '<?php echo htmlspecialchars(addslashes($p['name'])); ?>', '<?php echo htmlspecialchars(addslashes(strtoupper($p['role']))); ?>')" style="font-size: 0.85rem; padding: 0.45rem 0.9rem;">
                                    <i class="fas fa-file-medical"></i> View Document
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline" disabled style="opacity: 0.5; font-size: 0.85rem; padding: 0.45rem 0.9rem; cursor: not-allowed;" title="No verification document uploaded">
                                    <i class="fas fa-file-excel"></i> No Document
                                </button>
                            <?php endif; ?>

                            <!-- Verification Toggle Form -->
                            <form method="POST" action="" style="margin: 0;">
                                <input type="hidden" name="action" value="toggle_verify">
                                <input type="hidden" name="user_id" value="<?php echo $p['id']; ?>">
                                <?php if (!empty($p['is_verified'])): ?>
                                    <input type="hidden" name="status" value="0">
                                    <button type="submit" class="btn btn-outline" style="border-color: #ffa502; color: #ffa502; font-size: 0.85rem; padding: 0.45rem 0.9rem;">
                                        <i class="fas fa-undo"></i> Mark Unverified
                                    </button>
                                <?php else: ?>
                                    <input type="hidden" name="status" value="1">
                                    <button type="submit" class="btn btn-outline" style="border-color: #2ed573; color: #2ed573; font-size: 0.85rem; padding: 0.45rem 0.9rem;">
                                        <i class="fas fa-check"></i> Mark Verified
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>

                <!-- Server-Side Pagination Controls -->
                <?php if ($total_pages > 1): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; pt: 1rem; border-top: 1px solid var(--glass-border); flex-wrap: wrap; gap: 1rem;">
                        <span style="font-size: 0.88rem; color: var(--text-secondary);">
                            Showing <?php echo min($offset + 1, $total_records); ?>–<?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> records
                        </span>
                        <div style="display: flex; gap: 0.4rem; align-items: center;">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo ($page - 1); ?>" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-chevron-left"></i> Previous</a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>" class="btn <?php echo ($i === $page) ? 'btn-primary' : 'btn-outline'; ?>" style="padding: 0.4rem 0.75rem; font-size: 0.85rem; min-width: 36px; text-align: center;">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo ($page + 1); ?>" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">Next <i class="fas fa-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <p style="color: var(--text-secondary);">No medical professionals registered.</p>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- ========================================================= -->
<!-- FEATURE 3: IN-PAGE VERIFICATION DOCUMENT VIEWER MODAL -->
<!-- ========================================================= -->
<div id="docViewerModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 99999; align-items: center; justify-content: center; padding: 1rem;">
    <div class="glass-panel" style="background: var(--darker-bg); border: 1px solid var(--glass-border); width: 100%; max-width: 800px; padding: 1.5rem; border-radius: 20px; box-shadow: 0 25px 60px rgba(0,0,0,0.7); display: flex; flex-direction: column; max-height: 90vh;">
        
        <!-- Modal Header -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.8rem;">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <i class="fas fa-id-card" style="color: var(--primary-color); font-size: 1.3rem;"></i>
                <h3 id="docViewerTitle" style="color: var(--text-primary); margin: 0; font-size: 1.15rem;">Verification Document</h3>
            </div>
            <button type="button" onclick="closeDocViewer()" style="background: transparent; border: none; color: var(--text-secondary); font-size: 1.4rem; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Document Preview Container -->
        <div id="docViewerContent" style="flex: 1; overflow-y: auto; background: rgba(0,0,0,0.3); border-radius: 12px; padding: 1rem; display: flex; align-items: center; justify-content: center; min-height: 350px;">
            <!-- Dynamic Preview Content populated via JS -->
        </div>

        <!-- Modal Footer Actions -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; border-top: 1px solid var(--glass-border); padding-top: 0.8rem;">
            <a id="docViewerDirectLink" href="#" target="_blank" class="btn btn-outline" style="font-size: 0.85rem; padding: 0.4rem 0.9rem;">
                <i class="fas fa-external-link-alt"></i> Open Fullscreen
            </a>
            <button type="button" onclick="closeDocViewer()" class="btn btn-primary" style="font-size: 0.85rem; padding: 0.4rem 1.2rem;">
                Close
            </button>
        </div>
    </div>
</div>

<script>
function openDocViewer(docUrl, name, role) {
    var modal = document.getElementById('docViewerModal');
    var title = document.getElementById('docViewerTitle');
    var content = document.getElementById('docViewerContent');
    var directLink = document.getElementById('docViewerDirectLink');
    
    title.innerText = (role ? role + ' ' : '') + 'Document — ' + name;
    directLink.href = docUrl;
    
    var ext = docUrl.split('.').pop().toLowerCase();
    
    if (ext === 'pdf') {
        content.innerHTML = '<iframe src="' + docUrl + '" style="width: 100%; height: 500px; border: none; border-radius: 8px;"></iframe>';
    } else if (['jpg', 'jpeg', 'png', 'webp', 'gif'].indexOf(ext) !== -1) {
        content.innerHTML = '<img src="' + docUrl + '" alt="Verification Document" style="max-width: 100%; max-height: 500px; object-fit: contain; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.3);">';
    } else {
        content.innerHTML = '<div style="text-align: center; color: var(--text-secondary);"><i class="fas fa-file-alt" style="font-size: 3rem; margin-bottom: 1rem; color: var(--primary-color);"></i><p>Preview not directly embedded for format .' + ext + '.</p><a href="' + docUrl + '" target="_blank" class="btn btn-primary">Download / View File</a></div>';
    }
    
    modal.style.display = 'flex';
}

function closeDocViewer() {
    var modal = document.getElementById('docViewerModal');
    modal.style.display = 'none';
    document.getElementById('docViewerContent').innerHTML = '';
}
</script>

<?php include 'includes/footer.php'; ?>

