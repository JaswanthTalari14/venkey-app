<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$patient_id = (int)$_SESSION['user_id'];
include 'includes/header.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT p.*, d.name as doctor_name, d.specialization 
        FROM prescriptions p
        JOIN users d ON p.doctor_id = d.id
        WHERE p.patient_id = ?";

if (!empty($search)) {
    $sql .= " AND (d.name LIKE ? OR p.notes LIKE ? OR p.consultation_date LIKE ?)";
}
$sql .= " ORDER BY p.consultation_date DESC, p.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $s_param = "%$search%";
    $stmt->bind_param("isss", $patient_id, $s_param, $s_param, $s_param);
} else {
    $stmt->bind_param("i", $patient_id);
}
$stmt->execute();
$prescriptions = $stmt->get_result();
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Patient Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Patient Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Patient Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="patient_dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="book_consult.php"><i class="fas fa-calendar-check"></i> Consultations</a></li>
            <li><a href="prescription_vault.php" class="active"><i class="fas fa-file-prescription"></i> Prescription Vault</a></li>
            <li><a href="medicines.php"><i class="fas fa-pills"></i> Order Medicines</a></li>
            <li><a href="your_orders.php"><i class="fas fa-boxes"></i> Your Orders</a></li>
            <li><a href="nearby_doctors.php"><i class="fas fa-map-marker-alt"></i> Find Doctors (10km)</a></li>
            <li><a href="privacy_consult.php"><i class="fas fa-user-secret"></i> Privacy Consult</a></li>
            <li><a href="book_tests.php"><i class="fas fa-vial"></i> Book Labs (RMP)</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="refer_earn.php"><i class="fas fa-gift"></i> Refer & Earn</a></li>
            <li><a href="my_wallet.php"><i class="fas fa-wallet"></i> My Wallet</a></li>
            <li><a href="chatbot.php"><i class="fas fa-robot"></i> AI Chatbot</a></li>
            <li><a href="javascript:void(0);" class="pwaInstallBtn"><i class="fas fa-download"></i> Install App</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <h2><i class="fas fa-file-prescription" style="color: var(--primary-color);"></i> Prescription Vault</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Access and download all your digital doctor prescriptions and medical records securely.</p>

        <!-- Search Bar -->
        <div class="glass-panel" style="padding: 1.2rem; margin-bottom: 1.5rem;">
            <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 240px; position: relative;">
                    <input type="text" name="search" class="form-control" placeholder="Search by doctor name, symptoms, date..." value="<?php echo htmlspecialchars($search); ?>" style="padding-left: 2.3rem;">
                    <i class="fas fa-search" style="position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                </div>
                <button type="submit" class="btn btn-primary" style="padding: 0.55rem 1.2rem; font-size: 0.88rem;">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="prescription_vault.php" class="btn btn-outline" style="padding: 0.55rem 1rem; font-size: 0.88rem;">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="features-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
            <?php if ($prescriptions && $prescriptions->num_rows > 0): ?>
                <?php while($p = $prescriptions->fetch_assoc()): ?>
                    <?php
                        $p_id = (int)$p['id'];
                        $items_q = $conn->query("SELECT * FROM prescription_items WHERE prescription_id = $p_id");
                    ?>
                    <div class="feature-card glass-panel" style="padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.8rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.8rem;">
                                <div>
                                    <h4 style="color: var(--primary-color); font-weight: 700; margin: 0; font-size: 1.1rem;">Dr. <?php echo htmlspecialchars($p['doctor_name']); ?></h4>
                                    <span style="font-size: 0.82rem; color: var(--text-secondary);"><?php echo htmlspecialchars($p['specialization'] ?? 'General Practitioner'); ?></span>
                                </div>
                                <span style="font-size: 0.8rem; background: var(--glass-bg); border: 1px solid var(--glass-border); padding: 0.2rem 0.6rem; border-radius: 12px; color: var(--text-primary);">
                                    <i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($p['consultation_date'])); ?>
                                </span>
                            </div>

                            <?php if (!empty($p['notes'])): ?>
                                <p style="font-size: 0.88rem; color: var(--text-primary); margin-bottom: 0.8rem; line-height: 1.4;">
                                    <strong>Doctor Instructions:</strong> <?php echo htmlspecialchars($p['notes']); ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($items_q && $items_q->num_rows > 0): ?>
                                <div style="margin-bottom: 1rem;">
                                    <strong style="font-size: 0.85rem; color: var(--secondary-color);">Prescribed Medicines:</strong>
                                    <ul style="padding-left: 1.2rem; margin-top: 0.4rem; font-size: 0.85rem; color: var(--text-secondary);">
                                        <?php while($item = $items_q->fetch_assoc()): ?>
                                            <li style="margin-bottom: 0.3rem;">
                                                <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($item['medicine_name']); ?></strong> — <?php echo htmlspecialchars($item['dosage']); ?>, <?php echo htmlspecialchars($item['frequency']); ?> (<?php echo htmlspecialchars($item['duration']); ?>)
                                            </li>
                                        <?php endwhile; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="display: flex; gap: 0.6rem; margin-top: 1rem; pt: 0.8rem; border-top: 1px solid var(--glass-border);">
                            <?php if (!empty($p['file_path'])): ?>
                                <a href="view_document.php?type=prescription&file=<?php echo urlencode(basename($p['file_path'])); ?>" target="_blank" class="btn btn-primary" style="flex: 1; font-size: 0.85rem; text-align: center;">
                                    <i class="fas fa-eye"></i> View File
                                </a>
                            <?php endif; ?>
                            <a href="medicines.php" class="btn btn-outline" style="flex: 1; font-size: 0.85rem; text-align: center;">
                                <i class="fas fa-shopping-cart"></i> Order Meds
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="glass-panel" style="grid-column: 1 / -1; padding: 3rem; text-align: center; border-radius: 16px;">
                    <i class="fas fa-file-prescription" style="font-size: 3rem; color: var(--text-secondary); opacity: 0.4; margin-bottom: 1rem;"></i>
                    <h3 style="color: var(--text-primary);">No prescriptions found in your vault.</h3>
                    <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.4rem;">Digital prescriptions issued by your doctors during consultations will automatically appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
