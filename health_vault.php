<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$patient_id = (int)$_SESSION['user_id'];
$patient_name = $_SESSION['name'] ?? 'Patient';

// Fetch patient's basic info
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->bind_param("i", $patient_id);
$user_stmt->execute();
$patient_data = $user_stmt->get_result()->fetch_assoc();

// Tab handling
$active_tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'overview';

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Health Vault Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Health Vault</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Health Vault</h3>
        <ul class="sidebar-menu">
            <li><a href="patient_dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="health_vault.php?tab=overview" class="<?php echo $active_tab === 'overview' ? 'active' : ''; ?>"><i class="fas fa-shield-alt"></i> Vault Center</a></li>
            <li><a href="health_vault.php?tab=timeline" class="<?php echo $active_tab === 'timeline' ? 'active' : ''; ?>"><i class="fas fa-stream"></i> Health Timeline</a></li>
            <li><a href="health_vault.php?tab=documents" class="<?php echo $active_tab === 'documents' ? 'active' : ''; ?>"><i class="fas fa-file-medical"></i> Documents & Reports</a></li>
            <li><a href="health_vault.php?tab=family" class="<?php echo $active_tab === 'family' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Family Profiles</a></li>
            <li><a href="health_vault.php?tab=emergency" class="<?php echo $active_tab === 'emergency' ? 'active' : ''; ?>"><i class="fas fa-id-card"></i> Emergency Card</a></li>
            <li><a href="health_vault.php?tab=trends" class="<?php echo $active_tab === 'trends' ? 'active' : ''; ?>"><i class="fas fa-heartbeat"></i> Health Trends</a></li>
            <li><a href="health_vault.php?tab=sharing" class="<?php echo $active_tab === 'sharing' ? 'active' : ''; ?>"><i class="fas fa-share-alt"></i> Secure Sharing</a></li>
            <li><a href="medicines.php"><i class="fas fa-pills"></i> My Medicines</a></li>
            <li><a href="prescription_vault.php"><i class="fas fa-prescription"></i> Prescriptions</a></li>
            <li><a href="chatbot.php"><i class="fas fa-robot"></i> AI Health Assistant</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="margin: 0;"><i class="fas fa-vault" style="color: var(--primary-color);"></i> My Health Vault</h2>
                <p style="color: var(--text-secondary); margin: 0.3rem 0 0;">Connected Healthcare Center for <?php echo htmlspecialchars($patient_name); ?></p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="health_vault.php?tab=sharing" class="btn btn-outline" style="font-size: 0.85rem;"><i class="fas fa-lock"></i> Secure Share</a>
                <a href="health_vault.php?tab=emergency" class="btn btn-primary" style="font-size: 0.85rem;"><i class="fas fa-qrcode"></i> Emergency QR</a>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div style="display: flex; gap: 0.5rem; margin-bottom: 2rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem; overflow-x: auto; scrollbar-width: none;">
            <a href="health_vault.php?tab=overview" class="btn <?php echo $active_tab === 'overview' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.85rem; border-radius: 20px; white-space: nowrap;"><i class="fas fa-tachometer-alt"></i> Overview</a>
            <a href="health_vault.php?tab=timeline" class="btn <?php echo $active_tab === 'timeline' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.85rem; border-radius: 20px; white-space: nowrap;"><i class="fas fa-stream"></i> Timeline</a>
            <a href="health_vault.php?tab=documents" class="btn <?php echo $active_tab === 'documents' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.85rem; border-radius: 20px; white-space: nowrap;"><i class="fas fa-folder"></i> Documents</a>
            <a href="health_vault.php?tab=family" class="btn <?php echo $active_tab === 'family' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.85rem; border-radius: 20px; white-space: nowrap;"><i class="fas fa-user-friends"></i> Family</a>
            <a href="health_vault.php?tab=emergency" class="btn <?php echo $active_tab === 'emergency' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.85rem; border-radius: 20px; white-space: nowrap;"><i class="fas fa-id-card"></i> Emergency</a>
            <a href="health_vault.php?tab=trends" class="btn <?php echo $active_tab === 'trends' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.85rem; border-radius: 20px; white-space: nowrap;"><i class="fas fa-chart-line"></i> Trends</a>
            <a href="health_vault.php?tab=sharing" class="btn <?php echo $active_tab === 'sharing' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.85rem; border-radius: 20px; white-space: nowrap;"><i class="fas fa-key"></i> Sharing</a>
        </div>

        <?php if ($active_tab === 'overview'): ?>
            <!-- TAB 1: HEALTH OVERVIEW -->
            <?php
            // Fetch summary stats
            $next_app = $conn->query("SELECT a.*, u.name as doctor_name, u.specialization FROM appointments a JOIN users u ON a.doctor_id = u.id WHERE a.patient_id = $patient_id AND a.appointment_date >= CURDATE() AND a.status NOT IN ('cancelled', 'completed') ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 1");
            $next_app_data = ($next_app && $next_app->num_rows > 0) ? $next_app->fetch_assoc() : null;

            $active_meds_res = $conn->query("SELECT COUNT(*) as cnt FROM refill_reminders WHERE patient_id = $patient_id AND status = 'active'");
            $active_meds_cnt = $active_meds_res ? $active_meds_res->fetch_assoc()['cnt'] : 0;

            $recent_doc = $conn->query("SELECT * FROM medical_documents WHERE patient_id = $patient_id ORDER BY created_at DESC LIMIT 1");
            $recent_doc_data = ($recent_doc && $recent_doc->num_rows > 0) ? $recent_doc->fetch_assoc() : null;

            $latest_prescription = $conn->query("SELECT p.*, u.name as doctor_name FROM prescriptions p JOIN users u ON p.doctor_id = u.id WHERE p.patient_id = $patient_id ORDER BY p.created_at DESC LIMIT 1");
            $latest_rx_data = ($latest_prescription && $latest_prescription->num_rows > 0) ? $latest_prescription->fetch_assoc() : null;
            ?>

            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2rem; border-left: 4px solid var(--primary-color);">
                <h3 style="margin-top: 0; color: var(--text-primary);">Good Morning, <?php echo htmlspecialchars($patient_name); ?> 👋</h3>
                <p style="color: var(--text-secondary); margin-bottom: 0;">Here is your real-time health overview synced directly with your records.</p>
            </div>

            <div class="features-grid" style="margin-bottom: 2rem;">
                <div class="feature-card glass-panel" style="padding: 1.25rem;">
                    <h4 style="color: var(--primary-color); font-size: 0.9rem;"><i class="fas fa-calendar-check"></i> Upcoming Appointment</h4>
                    <?php if ($next_app_data): ?>
                        <p style="font-weight: bold; margin: 0.5rem 0 0.2rem; font-size: 1.1rem; color: var(--text-primary);"><?php echo htmlspecialchars($next_app_data['doctor_name']); ?></p>
                        <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.8rem;"><?php echo date('M d, Y', strtotime($next_app_data['appointment_date'])) . ' at ' . date('h:i A', strtotime($next_app_data['appointment_time'])); ?></p>
                        <span style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 10px; font-size: 0.75rem; font-weight: bold; background: rgba(74, 144, 226, 0.2); color: var(--primary-color);"><?php echo ucfirst($next_app_data['status']); ?></span>
                    <?php else: ?>
                        <p style="color: var(--text-secondary); font-size: 0.9rem; margin: 0.8rem 0;">No upcoming appointments.</p>
                        <a href="book_consult.php" class="btn btn-outline" style="font-size: 0.8rem;">Book Consultation</a>
                    <?php endif; ?>
                </div>

                <div class="feature-card glass-panel" style="padding: 1.25rem;">
                    <h4 style="color: var(--secondary-color); font-size: 0.9rem;"><i class="fas fa-pills"></i> Active Medicines</h4>
                    <p style="font-size: 2rem; font-weight: bold; margin: 0.5rem 0; color: var(--text-primary);"><?php echo $active_meds_cnt; ?></p>
                    <a href="medicines.php" class="btn btn-outline" style="font-size: 0.8rem;">View Schedule & Reminders</a>
                </div>

                <div class="feature-card glass-panel" style="padding: 1.25rem;">
                    <h4 style="color: var(--accent); font-size: 0.9rem;"><i class="fas fa-file-medical-alt"></i> Recent Report</h4>
                    <?php if ($recent_doc_data): ?>
                        <p style="font-weight: bold; margin: 0.5rem 0 0.2rem; font-size: 1rem; color: var(--text-primary); truncate;"><?php echo htmlspecialchars($recent_doc_data['title']); ?></p>
                        <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.8rem;"><?php echo htmlspecialchars($recent_doc_data['category']) . ' • ' . date('M d, Y', strtotime($recent_doc_data['created_at'])); ?></p>
                        <a href="<?php echo htmlspecialchars($recent_doc_data['file_path']); ?>" target="_blank" class="btn btn-outline" style="font-size: 0.8rem;"><i class="fas fa-eye"></i> Open File</a>
                    <?php else: ?>
                        <p style="color: var(--text-secondary); font-size: 0.9rem; margin: 0.8rem 0;">No reports uploaded yet.</p>
                        <a href="health_vault.php?tab=documents" class="btn btn-outline" style="font-size: 0.8rem;">Upload Document</a>
                    <?php endif; ?>
                </div>

                <div class="feature-card glass-panel" style="padding: 1.25rem;">
                    <h4 style="color: #2ed573; font-size: 0.9rem;"><i class="fas fa-prescription"></i> Latest Prescription</h4>
                    <?php if ($latest_rx_data): ?>
                        <p style="font-weight: bold; margin: 0.5rem 0 0.2rem; font-size: 1rem; color: var(--text-primary);">Rx #<?php echo $latest_rx_data['id']; ?> - Dr. <?php echo htmlspecialchars($latest_rx_data['doctor_name']); ?></p>
                        <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.8rem;"><?php echo date('M d, Y', strtotime($latest_rx_data['consultation_date'])); ?></p>
                        <a href="prescription_vault.php" class="btn btn-outline" style="font-size: 0.8rem;">View Full Rx</a>
                    <?php else: ?>
                        <p style="color: var(--text-secondary); font-size: 0.9rem; margin: 0.8rem 0;">No active prescriptions.</p>
                        <a href="book_consult.php" class="btn btn-outline" style="font-size: 0.8rem;">Consult Doctor</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Connected AI Health Summary -->
            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                    <h3 style="margin: 0; font-size: 1.1rem; color: var(--primary-color); display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-robot"></i> Your Connected AI Health Summary
                    </h3>
                    <span style="font-size: 0.75rem; background: rgba(74, 144, 226, 0.15); color: var(--primary-color); padding: 0.25rem 0.6rem; border-radius: 10px; font-weight: bold;">Verified Data Only</span>
                </div>
                <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 12px; font-size: 0.9rem; line-height: 1.6; color: var(--text-primary);">
                    <p style="margin-top: 0;"><strong>Summary for <?php echo htmlspecialchars($patient_name); ?>:</strong></p>
                    <ul style="margin: 0.5rem 0; padding-left: 1.2rem; color: var(--text-secondary);">
                        <li><strong>Appointments:</strong> You have <?php echo $next_app_data ? '1 upcoming appointment with Dr. ' . htmlspecialchars($next_app_data['doctor_name']) : 'no pending appointments'; ?>.</li>
                        <li><strong>Active Medication:</strong> <?php echo $active_meds_cnt; ?> active medicine reminder(s) configured.</li>
                        <li><strong>Medical Documents:</strong> <?php echo $recent_doc_data ? 'Latest document "' . htmlspecialchars($recent_doc_data['title']) . '" uploaded on ' . date('M d, Y', strtotime($recent_doc_data['created_at'])) : 'No uploaded medical documents'; ?>.</li>
                        <li><strong>Emergency Status:</strong> Blood Group: <?php echo !empty($patient_data['blood_group']) ? htmlspecialchars($patient_data['blood_group']) : 'Not configured'; ?>, Allergies: <?php echo !empty($patient_data['allergies']) ? htmlspecialchars($patient_data['allergies']) : 'None listed'; ?>.</li>
                    </ul>
                    <p style="margin-bottom: 0; font-size: 0.8rem; color: var(--accent); opacity: 0.9;">* Disclaimer: AI summaries are generated strictly from your authorized records and do not constitute autonomous medical advice.</p>
                </div>
            </div>

        <?php elseif ($active_tab === 'timeline'): ?>
            <!-- TAB 2: ADVANCED HEALTH TIMELINE -->
            <?php
            // Build chronological event timeline
            $events = [];

            // 1. Appointments
            $app_q = $conn->query("SELECT a.id, a.appointment_date as date_val, a.appointment_time, a.status, a.notes, u.name as doctor_name FROM appointments a JOIN users u ON a.doctor_id = u.id WHERE a.patient_id = $patient_id");
            if ($app_q) {
                while($row = $app_q->fetch_assoc()) {
                    $events[] = [
                        'date' => $row['date_val'] . ' ' . ($row['appointment_time'] ?? '00:00:00'),
                        'type' => 'Appointment',
                        'icon' => 'fa-calendar-alt',
                        'color' => '#4a90e2',
                        'title' => 'Appointment with Dr. ' . $row['doctor_name'],
                        'description' => 'Status: ' . ucfirst($row['status']) . ($row['notes'] ? ' — ' . $row['notes'] : ''),
                        'link' => 'book_consult.php'
                    ];
                }
            }

            // 2. Orders
            $ord_q = $conn->query("SELECT id, created_at as date_val, status, total_amount FROM orders WHERE patient_id = $patient_id");
            if ($ord_q) {
                while($row = $ord_q->fetch_assoc()) {
                    $events[] = [
                        'date' => $row['date_val'],
                        'type' => 'Medicine Order',
                        'icon' => 'fa-box',
                        'color' => '#50e3c2',
                        'title' => 'Medicine Order #' . $row['id'],
                        'description' => 'Total: ₹' . $row['total_amount'] . ' — Status: ' . ucfirst($row['status']),
                        'link' => 'your_orders.php'
                    ];
                }
            }

            // 3. Prescriptions
            $rx_q = $conn->query("SELECT p.id, p.consultation_date as date_val, p.notes, u.name as doctor_name FROM prescriptions p JOIN users u ON p.doctor_id = u.id WHERE p.patient_id = $patient_id");
            if ($rx_q) {
                while($row = $rx_q->fetch_assoc()) {
                    $events[] = [
                        'date' => $row['date_val'] . ' 10:00:00',
                        'type' => 'Prescription',
                        'icon' => 'fa-prescription',
                        'color' => '#2ed573',
                        'title' => 'Prescription Rx #' . $row['id'] . ' by Dr. ' . $row['doctor_name'],
                        'description' => $row['notes'] ?: 'Digital Prescription Record',
                        'link' => 'prescription_vault.php'
                    ];
                }
            }

            // 4. Lab Reports / Documents
            $doc_q = $conn->query("SELECT id, title, category, created_at as date_val, file_path FROM medical_documents WHERE patient_id = $patient_id");
            if ($doc_q) {
                while($row = $doc_q->fetch_assoc()) {
                    $events[] = [
                        'date' => $row['date_val'],
                        'type' => 'Document',
                        'icon' => 'fa-file-medical-alt',
                        'color' => '#ffab00',
                        'title' => 'Document: ' . $row['title'],
                        'description' => 'Category: ' . $row['category'],
                        'link' => $row['file_path']
                    ];
                }
            }

            // Sort timeline chronologically descending
            usort($events, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
            ?>

            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2rem;">
                <h3 style="margin-top: 0;"><i class="fas fa-stream" style="color: var(--primary-color);"></i> Chronological Health Timeline</h3>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Connected medical events, consultations, medicine orders, prescriptions, and lab reports.</p>

                <?php if (count($events) > 0): ?>
                    <div style="position: relative; padding-left: 2rem; border-left: 2px solid var(--glass-border);">
                        <?php foreach($events as $ev): ?>
                            <div style="position: relative; margin-bottom: 2rem;">
                                <div style="position: absolute; left: -2.65rem; top: 0; width: 32px; height: 32px; border-radius: 50%; background: <?php echo $ev['color']; ?>; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.85rem; box-shadow: 0 4px 10px rgba(0,0,0,0.3);">
                                    <i class="fas <?php echo $ev['icon']; ?>"></i>
                                </div>
                                <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 12px;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem;">
                                        <h4 style="margin: 0; font-size: 1rem; color: var(--text-primary);"><?php echo htmlspecialchars($ev['title']); ?></h4>
                                        <span style="font-size: 0.8rem; color: var(--text-secondary); font-weight: bold;"><?php echo date('M d, Y — h:i A', strtotime($ev['date'])); ?></span>
                                    </div>
                                    <p style="color: var(--text-secondary); font-size: 0.85rem; margin: 0.5rem 0 0.8rem;"><?php echo htmlspecialchars($ev['description']); ?></p>
                                    <a href="<?php echo htmlspecialchars($ev['link']); ?>" <?php echo (strpos($ev['link'], 'uploads/') !== false) ? 'target="_blank"' : ''; ?> class="btn btn-outline" style="font-size: 0.75rem; padding: 0.3rem 0.7rem;">
                                        View Record <i class="fas fa-external-link-alt" style="font-size: 0.7rem; margin-left: 0.3rem;"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-secondary); padding: 2rem;">No timeline events recorded yet.</p>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'documents'): ?>
            <!-- TAB 3: ADVANCED MEDICAL DOCUMENT MANAGEMENT -->
            <?php
            $docs_q = $conn->query("SELECT * FROM medical_documents WHERE patient_id = $patient_id ORDER BY created_at DESC");
            ?>
            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <h3 style="margin: 0;"><i class="fas fa-file-medical-alt" style="color: var(--primary-color);"></i> Medical Documents & Reports Vault</h3>
                    <button onclick="document.getElementById('uploadDocModal').style.display='flex'" class="btn btn-primary" style="font-size: 0.85rem;">
                        <i class="fas fa-upload"></i> Upload New Document
                    </button>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
                    <?php if ($docs_q && $docs_q->num_rows > 0): ?>
                        <?php while($doc = $docs_q->fetch_assoc()): ?>
                            <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 12px;">
                                <div style="display: flex; align-items: center; gap: 0.8rem; margin-bottom: 0.8rem;">
                                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(74, 144, 226, 0.15); color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <h4 style="margin: 0; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-primary);"><?php echo htmlspecialchars($doc['title']); ?></h4>
                                        <span style="font-size: 0.75rem; color: var(--secondary-color); font-weight: bold;"><?php echo htmlspecialchars($doc['category']); ?></span>
                                    </div>
                                </div>
                                <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 1rem;">Uploaded: <?php echo date('M d, Y', strtotime($doc['created_at'])); ?></p>
                                <div style="display: flex; gap: 0.5rem;">
                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn btn-outline" style="flex: 1; font-size: 0.75rem; text-align: center;">Preview</a>
                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" download class="btn btn-primary" style="flex: 1; font-size: 0.75rem; text-align: center;">Download</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; text-align: center; color: var(--text-secondary); padding: 2rem;">No medical documents uploaded yet. Click "Upload New Document" to add reports.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upload Modal -->
            <div id="uploadDocModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); z-index: 99999; align-items: center; justify-content: center; padding: 1rem;">
                <div style="background: var(--darker-bg, #121826); border: 1px solid var(--glass-border); border-radius: 16px; padding: 1.5rem; max-width: 450px; width: 100%; color: var(--text-primary);">
                    <h3 style="margin-top: 0;">Upload Medical Document</h3>
                    <form id="docUploadForm" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Document Title *</label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. CBC Blood Test Report">
                        </div>
                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category" class="form-control" required>
                                <option value="Lab Reports">Lab Reports</option>
                                <option value="Prescriptions">Prescriptions</option>
                                <option value="X-Rays">X-Rays</option>
                                <option value="Discharge Reports">Discharge Reports</option>
                                <option value="Medical Certificates">Medical Certificates</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Select File (JPG, PNG, PDF, DOC) *</label>
                            <input type="file" name="document_file" class="form-control" required accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                        </div>
                        <div style="display: flex; gap: 0.8rem; margin-top: 1.5rem;">
                            <button type="button" onclick="document.getElementById('uploadDocModal').style.display='none'" class="btn btn-outline" style="flex: 1;">Cancel</button>
                            <button type="submit" class="btn btn-primary" style="flex: 1;">Upload</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            document.getElementById('docUploadForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'upload_medical_document');

                fetch('api_patient_features.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert(res.message);
                        location.reload();
                    } else {
                        alert(res.message || 'Upload failed');
                    }
                });
            });
            </script>

        <?php elseif ($active_tab === 'family'): ?>
            <!-- TAB 4: FAMILY HEALTH PROFILES -->
            <?php
            $fam_q = $conn->query("SELECT * FROM family_members WHERE primary_user_id = $patient_id ORDER BY created_at DESC");
            ?>
            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <h3 style="margin: 0;"><i class="fas fa-users" style="color: var(--primary-color);"></i> Authorized Family Health Profiles</h3>
                    <button onclick="document.getElementById('addFamilyModal').style.display='flex'" class="btn btn-primary" style="font-size: 0.85rem;">
                        <i class="fas fa-user-plus"></i> Add Family Member
                    </button>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
                    <?php if ($fam_q && $fam_q->num_rows > 0): ?>
                        <?php while($f = $fam_q->fetch_assoc()): ?>
                            <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <h4 style="margin: 0; font-size: 1.05rem; color: var(--text-primary);"><?php echo htmlspecialchars($f['name']); ?></h4>
                                    <span style="padding: 0.25rem 0.6rem; border-radius: 10px; font-size: 0.75rem; font-weight: bold; background: rgba(80, 227, 194, 0.15); color: var(--secondary-color);"><?php echo htmlspecialchars($f['relationship']); ?></span>
                                </div>
                                <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0.8rem 0 0.4rem;">
                                    <strong>Blood Group:</strong> <?php echo htmlspecialchars($f['blood_group'] ?: 'Not specified'); ?><br>
                                    <strong>Gender:</strong> <?php echo htmlspecialchars($f['gender'] ?: 'Not specified'); ?><br>
                                    <strong>Allergies:</strong> <?php echo htmlspecialchars($f['allergies'] ?: 'None listed'); ?>
                                </p>
                                <button onclick="deleteFamilyMember(<?php echo $f['id']; ?>)" class="btn btn-outline" style="font-size: 0.75rem; color: #ff4757; border-color: #ff4757; width: 100%; margin-top: 0.5rem;">Remove Profile</button>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; text-align: center; color: var(--text-secondary); padding: 2rem;">No family profiles added yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add Family Modal -->
            <div id="addFamilyModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); z-index: 99999; align-items: center; justify-content: center; padding: 1rem;">
                <div style="background: var(--darker-bg, #121826); border: 1px solid var(--glass-border); border-radius: 16px; padding: 1.5rem; max-width: 450px; width: 100%; color: var(--text-primary);">
                    <h3 style="margin-top: 0;">Add Family Member</h3>
                    <form id="addFamilyForm">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Ramesh Kumar">
                        </div>
                        <div class="form-group">
                            <label>Relationship *</label>
                            <select name="relationship" class="form-control" required>
                                <option value="Father">Father</option>
                                <option value="Mother">Mother</option>
                                <option value="Spouse">Spouse</option>
                                <option value="Child">Child</option>
                                <option value="Sibling">Sibling</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Blood Group</label>
                            <input type="text" name="blood_group" class="form-control" placeholder="e.g. O+">
                        </div>
                        <div class="form-group">
                            <label>Known Allergies</label>
                            <input type="text" name="allergies" class="form-control" placeholder="e.g. Penicillin, Dust">
                        </div>
                        <div style="display: flex; gap: 0.8rem; margin-top: 1.5rem;">
                            <button type="button" onclick="document.getElementById('addFamilyModal').style.display='none'" class="btn btn-outline" style="flex: 1;">Cancel</button>
                            <button type="submit" class="btn btn-primary" style="flex: 1;">Save Profile</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            document.getElementById('addFamilyForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'add_family_member');

                fetch('api_patient_features.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert(res.message);
                        location.reload();
                    } else {
                        alert(res.message);
                    }
                });
            });

            function deleteFamilyMember(fid) {
                if (!confirm('Remove this family member profile?')) return;
                const fd = new FormData();
                fd.append('action', 'delete_family_member');
                fd.append('family_id', fid);
                fetch('api_patient_features.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => { if(res.success) location.reload(); });
            }
            </script>

        <?php elseif ($active_tab === 'emergency'): ?>
            <!-- TAB 5: EMERGENCY MEDICAL CARD -->
            <?php
            $emg_q = $conn->query("SELECT * FROM emergency_cards WHERE user_id = $patient_id");
            $emg_data = ($emg_q && $emg_q->num_rows > 0) ? $emg_q->fetch_assoc() : null;
            $public_fields = $emg_data ? json_decode($emg_data['public_fields_json'], true) : ['name', 'blood_group', 'emergency_contact'];
            if (!is_array($public_fields)) $public_fields = [];
            $qr_token = $emg_data['qr_token'] ?? md5('EMG_' . $patient_id);
            $emg_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/emergency_view.php?qr=' . $qr_token;
            ?>

            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2rem;">
                <h3 style="margin-top: 0;"><i class="fas fa-id-card" style="color: #ff4757;"></i> Emergency Medical Card Configuration</h3>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Select exactly which medical details are publicly accessible when your Emergency QR is scanned.</p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    <div>
                        <h4 style="color: var(--secondary-color); margin-bottom: 1rem;">Public Field Visibility Controls</h4>
                        <form id="emgCardForm">
                            <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.8rem; cursor: pointer; color: var(--text-primary);">
                                <input type="checkbox" name="public_fields[]" value="name" <?php echo in_array('name', $public_fields) ? 'checked' : ''; ?>>
                                <span>Patient Full Name</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.8rem; cursor: pointer; color: var(--text-primary);">
                                <input type="checkbox" name="public_fields[]" value="blood_group" <?php echo in_array('blood_group', $public_fields) ? 'checked' : ''; ?>>
                                <span>Blood Group (<?php echo htmlspecialchars($patient_data['blood_group'] ?: 'Not set'); ?>)</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.8rem; cursor: pointer; color: var(--text-primary);">
                                <input type="checkbox" name="public_fields[]" value="allergies" <?php echo in_array('allergies', $public_fields) ? 'checked' : ''; ?>>
                                <span>Known Allergies (<?php echo htmlspecialchars($patient_data['allergies'] ?: 'None listed'); ?>)</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.8rem; cursor: pointer; color: var(--text-primary);">
                                <input type="checkbox" name="public_fields[]" value="emergency_contact" <?php echo in_array('emergency_contact', $public_fields) ? 'checked' : ''; ?>>
                                <span>Emergency Contact Number</span>
                            </label>
                            <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Save Card Preferences</button>
                        </form>
                    </div>

                    <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.5rem; border-radius: 16px; text-align: center;">
                        <h4 style="margin-top: 0; color: var(--text-primary);"><i class="fas fa-qrcode"></i> Your Secure Emergency QR</h4>
                        <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">Scan this QR code in emergencies to reveal authorized information.</p>

                        <div style="background: #fff; padding: 1rem; border-radius: 12px; display: inline-block; margin-bottom: 1rem;">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?php echo urlencode($emg_url); ?>" alt="Emergency QR Code" style="width: 180px; height: 180px; display: block;">
                        </div>

                        <div>
                            <a href="emergency_view.php?qr=<?php echo $qr_token; ?>" target="_blank" class="btn btn-outline" style="font-size: 0.8rem;"><i class="fas fa-external-link-alt"></i> Test QR View Page</a>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            document.getElementById('emgCardForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'save_emergency_card');

                fetch('api_patient_features.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert(res.message);
                        location.reload();
                    }
                });
            });
            </script>

        <?php elseif ($active_tab === 'trends'): ?>
            <!-- TAB 6: HEALTH TRENDS TRACKING -->
            <?php
            $trends_q = $conn->query("SELECT * FROM health_trends WHERE patient_id = $patient_id ORDER BY measured_at DESC LIMIT 20");
            ?>
            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <h3 style="margin: 0;"><i class="fas fa-heartbeat" style="color: var(--primary-color);"></i> Health Metrics & Trends</h3>
                    <button onclick="document.getElementById('addTrendModal').style.display='flex'" class="btn btn-primary" style="font-size: 0.85rem;">
                        <i class="fas fa-plus"></i> Log Vital Reading
                    </button>
                </div>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; text-align: left; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--glass-border);">
                                <th style="padding: 0.8rem;">Metric</th>
                                <th style="padding: 0.8rem;">Value</th>
                                <th style="padding: 0.8rem;">Measured Date</th>
                                <th style="padding: 0.8rem;">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($trends_q && $trends_q->num_rows > 0): ?>
                                <?php while($t = $trends_q->fetch_assoc()): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                        <td style="padding: 0.8rem; font-weight: bold; color: var(--primary-color);"><?php echo htmlspecialchars(strtoupper(str_replace('_', ' ', $t['metric_type']))); ?></td>
                                        <td style="padding: 0.8rem; font-weight: bold; color: var(--secondary-color);"><?php echo htmlspecialchars($t['metric_value']) . ' ' . htmlspecialchars($t['unit']); ?></td>
                                        <td style="padding: 0.8rem; color: var(--text-secondary);"><?php echo date('M d, Y h:i A', strtotime($t['measured_at'])); ?></td>
                                        <td style="padding: 0.8rem; color: var(--text-secondary);"><?php echo htmlspecialchars($t['notes'] ?: '-'); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="padding: 1rem; text-align: center; color: var(--text-secondary);">No vital trends logged yet. Click "Log Vital Reading" to add measurements.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Trend Modal -->
            <div id="addTrendModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); z-index: 99999; align-items: center; justify-content: center; padding: 1rem;">
                <div style="background: var(--darker-bg, #121826); border: 1px solid var(--glass-border); border-radius: 16px; padding: 1.5rem; max-width: 450px; width: 100%; color: var(--text-primary);">
                    <h3 style="margin-top: 0;">Log Vital Measurement</h3>
                    <form id="addTrendForm">
                        <div class="form-group">
                            <label>Metric Type *</label>
                            <select name="metric_type" class="form-control" required>
                                <option value="Blood Pressure (Systolic)">Blood Pressure (Systolic)</option>
                                <option value="Blood Pressure (Diastolic)">Blood Pressure (Diastolic)</option>
                                <option value="Weight">Weight (kg)</option>
                                <option value="Blood Sugar">Blood Sugar (mg/dL)</option>
                                <option value="Temperature">Temperature (°F)</option>
                                <option value="Heart Rate">Heart Rate (BPM)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Reading Value *</label>
                            <input type="number" step="0.1" name="metric_value" class="form-control" required placeholder="e.g. 120">
                        </div>
                        <div class="form-group">
                            <label>Unit</label>
                            <input type="text" name="unit" class="form-control" placeholder="e.g. mmHg, kg, mg/dL">
                        </div>
                        <div style="display: flex; gap: 0.8rem; margin-top: 1.5rem;">
                            <button type="button" onclick="document.getElementById('addTrendModal').style.display='none'" class="btn btn-outline" style="flex: 1;">Cancel</button>
                            <button type="submit" class="btn btn-primary" style="flex: 1;">Log Vital</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            document.getElementById('addTrendForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'add_health_trend');

                fetch('api_patient_features.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert(res.message);
                        location.reload();
                    } else {
                        alert(res.message);
                    }
                });
            });
            </script>

        <?php elseif ($active_tab === 'sharing'): ?>
            <!-- TAB 7: SECURE MEDICAL RECORD SHARING -->
            <?php
            $shares_q = $conn->query("SELECT * FROM secure_shares WHERE patient_id = $patient_id ORDER BY created_at DESC");
            ?>
            <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2rem;">
                <h3 style="margin-top: 0;"><i class="fas fa-share-alt" style="color: var(--primary-color);"></i> Secure Medical Record Sharing</h3>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Select medical records to generate a time-limited secure link or QR code. Revoke access anytime.</p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    <div>
                        <h4 style="color: var(--secondary-color); margin-bottom: 1rem;">Generate New Share Token</h4>
                        <form id="createShareForm">
                            <p style="font-size: 0.85rem; font-weight: bold; margin-bottom: 0.5rem;">Select items to include:</p>
                            <label style="display: block; margin-bottom: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="items[]" value="latest_prescription" checked> Latest Prescriptions
                            </label>
                            <label style="display: block; margin-bottom: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="items[]" value="lab_reports" checked> Lab & Diagnostic Reports
                            </label>
                            <label style="display: block; margin-bottom: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="items[]" value="consultation_notes"> Consultation Notes
                            </label>
                            <label style="display: block; margin-bottom: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="items[]" value="health_timeline"> Full Health Timeline
                            </label>

                            <p style="font-size: 0.85rem; font-weight: bold; margin: 1rem 0 0.5rem;">Expiration duration:</p>
                            <select name="expiration_minutes" class="form-control" style="margin-bottom: 1rem;">
                                <option value="15">15 Minutes</option>
                                <option value="30">30 Minutes</option>
                                <option value="60" selected>1 Hour</option>
                                <option value="1440">24 Hours</option>
                            </select>

                            <button type="submit" class="btn btn-primary" style="width: 100%;">Generate Secure Link</button>
                        </form>
                    </div>

                    <div>
                        <h4 style="color: var(--text-primary); margin-bottom: 1rem;">Active Share Links</h4>
                        <div style="display: flex; flex-direction: column; gap: 0.8rem;">
                            <?php if ($shares_q && $shares_q->num_rows > 0): ?>
                                <?php while($s = $shares_q->fetch_assoc()): 
                                    $is_expired = strtotime($s['expires_at']) < time();
                                    $is_active = !$s['is_revoked'] && !$is_expired;
                                ?>
                                    <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1rem; border-radius: 12px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span style="font-size: 0.75rem; font-weight: bold; padding: 0.2rem 0.6rem; border-radius: 10px; background: <?php echo $is_active ? 'rgba(46, 213, 115, 0.15)' : 'rgba(255, 71, 87, 0.15)'; ?>; color: <?php echo $is_active ? '#2ed573' : '#ff4757'; ?>;">
                                                <?php echo $s['is_revoked'] ? 'Revoked' : ($is_expired ? 'Expired' : 'Active'); ?>
                                            </span>
                                            <span style="font-size: 0.75rem; color: var(--text-secondary);">Expires: <?php echo date('M d, h:i A', strtotime($s['expires_at'])); ?></span>
                                        </div>
                                        <div style="margin-top: 0.8rem;">
                                            <a href="view_shared.php?token=<?php echo $s['share_token']; ?>" target="_blank" style="color: var(--primary-color); font-size: 0.8rem; word-break: break-all;"><i class="fas fa-link"></i> view_shared.php?token=<?php echo substr($s['share_token'], 0, 10); ?>...</a>
                                        </div>
                                        <?php if ($is_active): ?>
                                            <button onclick="revokeShare(<?php echo $s['id']; ?>)" class="btn btn-outline" style="font-size: 0.75rem; color: #ff4757; border-color: #ff4757; margin-top: 0.8rem; width: 100%;">Revoke Access Now</button>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p style="color: var(--text-secondary); font-size: 0.9rem;">No share tokens created yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            document.getElementById('createShareForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'create_secure_share');

                fetch('api_patient_features.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert(res.message + '\n\nShare URL: ' + window.location.origin + '/' + res.share_url);
                        location.reload();
                    } else {
                        alert(res.message);
                    }
                });
            });

            function revokeShare(sid) {
                if (!confirm('Revoke access to this shared link immediately?')) return;
                const fd = new FormData();
                fd.append('action', 'revoke_secure_share');
                fd.append('share_id', sid);
                fetch('api_patient_features.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => { if(res.success) location.reload(); });
            }
            </script>
        <?php endif; ?>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
