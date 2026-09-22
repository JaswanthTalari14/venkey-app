<?php
require_once 'config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$success = '';

// Seed some sample medicines if empty
$check_meds = $conn->query("SELECT COUNT(*) as count FROM medicines");
$row = $check_meds->fetch_assoc();
if ($row['count'] == 0) {
    $conn->query("INSERT INTO medicines (name, description, price, stock) VALUES 
        ('Paracetamol 500mg', 'Fever and mild pain relief.', 15.00, 100),
        ('Amoxicillin 250mg', 'Antibiotic for bacterial infections.', 120.00, 50),
        ('Cetirizine 10mg', 'Allergy relief tablets.', 45.00, 200),
        ('Vitamin C + Zinc', 'Immunity booster supplement.', 250.00, 80)");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['order'])) {
    $medicine_id = $_POST['medicine_id'];
    $qty = $_POST['quantity'];
    $patient_id = $_SESSION['user_id'];
    
    // Get price
    $med = $conn->query("SELECT price FROM medicines WHERE id=$medicine_id")->fetch_assoc();
    $total = $med['price'] * $qty;
    
    // Create direct order for simplicity
    $conn->query("INSERT INTO orders (patient_id, total_amount, address) VALUES ($patient_id, $total, 'User Default Address')");
    $order_id = $conn->insert_id;
    
    $conn->query("INSERT INTO order_items (order_id, medicine_id, quantity, price) VALUES ($order_id, $medicine_id, $qty, {$med['price']})");
    $success = "Medicine ordered successfully! It will be delivered soon.";
}

$medicines = $conn->query("SELECT * FROM medicines");

// Fetch patient's medicine orders
$patient_id_for_orders = $_SESSION['user_id'];
$my_orders = $conn->query("
    SELECT o.id, o.created_at, o.status, o.total_amount, m.name as medicine_name, oi.quantity 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN medicines m ON oi.medicine_id = m.id
    WHERE o.patient_id = $patient_id_for_orders
    ORDER BY o.created_at DESC
");
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <h3 style="margin-bottom: 2rem;">Patient Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="patient_dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="book_consult.php"><i class="fas fa-calendar-check"></i> Consultations</a></li>
            <li><a href="medicines.php" class="active"><i class="fas fa-pills"></i> Order Medicines</a></li>
            <li><a href="nearby_doctors.php"><i class="fas fa-map-marker-alt"></i> Find Doctors (10km)</a></li>
            <li><a href="privacy_consult.php"><i class="fas fa-user-secret"></i> Privacy Consult</a></li>
            <li><a href="book_tests.php"><i class="fas fa-vial"></i> Book Labs (RMP)</a></li>
            <li><a href="chatbot.php"><i class="fas fa-robot"></i> AI Chatbot</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Medicine Delivery</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Order prescribed or over-the-counter medicines delivered directly to your home.</p>
        
        <?php if($success): ?><p style="color: #2ed573; margin-bottom: 1rem; padding: 1rem; background: rgba(46, 213, 115, 0.1); border-radius: 8px;"><?php echo $success; ?></p><?php endif; ?>

        <div class="features-grid" style="margin-top: 1rem;">
            <?php while($med = $medicines->fetch_assoc()): ?>
                <div class="feature-card glass-panel" style="padding: 1.5rem;">
                    <h4 style="color: #fff; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($med['name']); ?></h4>
                    <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1rem; min-height: 40px;"><?php echo htmlspecialchars($med['description']); ?></p>
                    <p style="font-size: 1.5rem; font-weight: bold; color: var(--secondary-color); margin-bottom: 1rem;">₹<?php echo $med['price']; ?></p>
                    
                    <form method="POST" action="" style="display: flex; gap: 0.5rem;">
                        <input type="hidden" name="medicine_id" value="<?php echo $med['id']; ?>">
                        <input type="number" name="quantity" value="1" min="1" max="10" class="form-control" style="width: 80px;" required>
                        <button type="submit" name="order" class="btn btn-primary" style="flex: 1;">Order Now</button>
                    </form>
                </div>
            <?php endwhile; ?>
        </div>

        <h3 style="margin-top: 3rem; margin-bottom: 1rem;">Your Medicine Orders</h3>
        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">Order ID</th>
                        <th style="padding: 1rem;">Medicine</th>
                        <th style="padding: 1rem;">Qty</th>
                        <th style="padding: 1rem;">Total Amount</th>
                        <th style="padding: 1rem;">Date Ordered</th>
                        <th style="padding: 1rem;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($my_orders && $my_orders->num_rows > 0): ?>
                        <?php while($o = $my_orders->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem;">#<?php echo $o['id']; ?></td>
                                <td style="padding: 1rem; font-weight: bold; color: var(--primary-color);"><?php echo htmlspecialchars($o['medicine_name']); ?></td>
                                <td style="padding: 1rem;"><?php echo $o['quantity']; ?></td>
                                <td style="padding: 1rem; color: var(--secondary-color);">₹<?php echo $o['total_amount']; ?></td>
                                <td style="padding: 1rem;"><?php echo date('M d, Y', strtotime($o['created_at'])); ?></td>
                                <td style="padding: 1rem;">
                                    <?php
                                        $status_color = 'var(--text-primary)';
                                        if ($o['status'] == 'pending') $status_color = 'var(--accent)';
                                        if ($o['status'] == 'shipped') $status_color = '#3498db';
                                        if ($o['status'] == 'delivered') $status_color = '#2ed573';
                                        if ($o['status'] == 'cancelled') $status_color = '#ff4757';
                                    ?>
                                    <span style="color: <?php echo $status_color; ?>; font-weight: bold; text-transform: capitalize; background: rgba(255,255,255,0.05); padding: 0.3rem 0.8rem; border-radius: 12px; font-size: 0.8rem;">
                                        <?php echo $o['status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="padding: 1rem; text-align: center;">You have not ordered any medicines yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
