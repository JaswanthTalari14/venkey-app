<?php
require_once 'config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit;
}

$doctor_id = $_SESSION['user_id'];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $new_status = $_POST['status'];
    $conn->query("UPDATE appointments SET status='$new_status' WHERE id=$appointment_id AND doctor_id=$doctor_id");
    $success = "Appointment status updated successfully.";
}

$appointments = $conn->query("
    SELECT a.id, a.appointment_date, a.appointment_time, a.type, a.status, a.notes, p.name as patient_name, p.phone as patient_phone
    FROM appointments a
    JOIN users p ON a.patient_id = p.id
    WHERE a.doctor_id = $doctor_id
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <h3 style="margin-bottom: 2rem;">Doctor Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="doctor_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="doctor_appointments.php" class="active"><i class="fas fa-calendar-day"></i> Appointments</a></li>
            <li><a href="doctor_referrals.php"><i class="fas fa-exchange-alt"></i> Patient Referrals</a></li>
            <li><a href="doctor_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="doctor_orders.php"><i class="fas fa-box"></i> Medicine Orders</a></li>
            <li><a href="doctor_queries.php"><i class="fas fa-user-secret"></i> Anonymous Queries</a></li>
            <li><a href="profile.php"><i class="fas fa-cog"></i> Settings & Availability</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>My Appointments</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Manage your online and offline consultations with patients.</p>

        <?php if($success): ?><p style="color: #2ed573; margin-bottom: 1rem;"><?php echo $success; ?></p><?php endif; ?>

        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">Patient Name</th>
                        <th style="padding: 1rem;">Contact</th>
                        <th style="padding: 1rem;">Date & Time</th>
                        <th style="padding: 1rem;">Type</th>
                        <th style="padding: 1rem;">Notes</th>
                        <th style="padding: 1rem;">Status</th>
                        <th style="padding: 1rem;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($appointments && $appointments->num_rows > 0): ?>
                        <?php while($a = $appointments->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem; font-weight: bold;"><?php echo htmlspecialchars($a['patient_name']); ?></td>
                                <td style="padding: 1rem;">
                                    <a href="tel:<?php echo htmlspecialchars($a['patient_phone']); ?>" style="color: var(--primary-color);"><i class="fas fa-phone"></i> Call</a>
                                </td>
                                <td style="padding: 1rem; color: var(--secondary-color);">
                                    <?php echo date('M d', strtotime($a['appointment_date'])); ?> @ <?php echo date('h:i A', strtotime($a['appointment_time'])); ?>
                                </td>
                                <td style="padding: 1rem; text-transform: capitalize;"><?php echo $a['type']; ?></td>
                                <td style="padding: 1rem; max-width: 200px; color: var(--text-secondary); font-size: 0.9rem;">
                                    <?php echo empty($a['notes']) ? 'None' : htmlspecialchars($a['notes']); ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <span style="color: <?php echo $a['status'] == 'pending' ? 'var(--accent)' : 'var(--text-primary)'; ?>; text-transform: capitalize;"><?php echo $a['status']; ?></span>
                                </td>
                                <td style="padding: 1rem;">
                                    <form method="POST" style="display: flex; gap: 0.5rem;">
                                        <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                                        <select name="status" class="form-control" style="width: auto; padding: 0.3rem;" required>
                                            <option value="pending" <?php if($a['status'] == 'pending') echo 'selected'; ?>>Pending</option>
                                            <option value="confirmed" <?php if($a['status'] == 'confirmed') echo 'selected'; ?>>Confirm</option>
                                            <option value="completed" <?php if($a['status'] == 'completed') echo 'selected'; ?>>Completed</option>
                                            <option value="cancelled" <?php if($a['status'] == 'cancelled') echo 'selected'; ?>>Cancel</option>
                                        </select>
                                        <button type="submit" name="update_status" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="padding: 1rem; text-align: center;">No appointments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
