<?php
require_once 'config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$success = '';
$error = '';

// Handle Adding a Medicine
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_medicine'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $desc = $conn->real_escape_string($_POST['description']);
    $price = $_POST['price'];
    $stock = $_POST['stock'];

    if ($conn->query("INSERT INTO medicines (name, description, price, stock) VALUES ('$name', '$desc', '$price', '$stock')")) {
        $success = "Medicine added successfully!";
    } else {
        $error = "Error adding medicine: " . $conn->error;
    }
}

// Handle Deleting a Medicine
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($conn->query("DELETE FROM medicines WHERE id=$id")) {
        $success = "Medicine deleted successfully!";
    }
}

$medicines = $conn->query("SELECT * FROM medicines ORDER BY id DESC");
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
            <li><a href="admin_verify.php"><i class="fas fa-user-md"></i> Verify Doctors & RMPs</a></li>
            <li><a href="admin_bookings.php"><i class="fas fa-calendar-check"></i> All Bookings</a></li>
            <li><a href="admin_orders.php"><i class="fas fa-box"></i> Medicine Orders</a></li>
            <li><a href="admin_medicines.php" class="active"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="admin_referrals.php"><i class="fas fa-gift"></i> Referral Management</a></li>
            <li><a href="admin_referral_settings.php"><i class="fas fa-sliders-h"></i> Referral Settings</a></li>
            <li><a href="admin_wallets.php"><i class="fas fa-wallet"></i> Wallet Management</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback & Complaints</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Manage Medicines & Tablets</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Add new tablets or update existing medical supplies for delivery.</p>
        
        <?php if($success): ?><p style="color: #2ed573; margin-bottom: 1rem; padding: 1rem; background: rgba(46, 213, 115, 0.1); border-radius: 8px;"><?php echo $success; ?></p><?php endif; ?>
        <?php if($error): ?><p style="color: #ff4757; margin-bottom: 1rem; padding: 1rem; background: rgba(255, 71, 87, 0.1); border-radius: 8px;"><?php echo $error; ?></p><?php endif; ?>

        <!-- Add New Medicine Form -->
        <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2rem;">
            <h3><i class="fas fa-plus-circle"></i> Add New Medicine</h3>
            <form method="POST" action="" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                <div style="grid-column: 1 / -1;">
                    <label>Medicine Name/Tablet</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Paracetamol 500mg">
                </div>
                <div style="grid-column: 1 / -1;">
                    <label>Description & Usage</label>
                    <input type="text" name="description" class="form-control" required placeholder="e.g. For fever and mild pain relief">
                </div>
                <div>
                    <label>Price (₹)</label>
                    <input type="number" step="0.01" name="price" class="form-control" required placeholder="15.00">
                </div>
                <div>
                    <label>Initial Stock</label>
                    <input type="number" name="stock" class="form-control" required placeholder="100">
                </div>
                <div style="grid-column: 1 / -1; margin-top: 1rem;">
                    <button type="submit" name="add_medicine" class="btn btn-primary" style="width: 100%;">Add Medicine</button>
                </div>
            </form>
        </div>

        <!-- Medicine List -->
        <h3 style="margin-bottom: 1rem;">Available Medicines</h3>
        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">ID</th>
                        <th style="padding: 1rem;">Name</th>
                        <th style="padding: 1rem;">Description</th>
                        <th style="padding: 1rem;">Price (₹)</th>
                        <th style="padding: 1rem;">Stock</th>
                        <th style="padding: 1rem;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($medicines && $medicines->num_rows > 0): ?>
                        <?php while($m = $medicines->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem;">#<?php echo $m['id']; ?></td>
                                <td style="padding: 1rem; font-weight: bold;"><?php echo htmlspecialchars($m['name']); ?></td>
                                <td style="padding: 1rem; color: var(--text-secondary); max-width: 250px;"><?php echo htmlspecialchars($m['description']); ?></td>
                                <td style="padding: 1rem; color: var(--secondary-color);">₹<?php echo $m['price']; ?></td>
                                <td style="padding: 1rem;"><?php echo $m['stock']; ?> units</td>
                                <td style="padding: 1rem;">
                                    <a href="?delete=<?php echo $m['id']; ?>" class="btn btn-outline" style="border-color: #ff4757; color: #ff4757; font-size: 0.8rem; padding: 0.4rem 1rem;" onclick="return confirm('Are you sure you want to delete this medicine?');">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="padding: 1rem; text-align: center;">No medicines found in the database.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
