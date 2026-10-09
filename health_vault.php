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

        <!-- Navigation Tabs (Mobile Only Switcher) -->
        <style>
            @media (min-width: 992px) {
                .vault-subtabs-mobile { display: none !important; }
            }
        </style>
        <div class="vault-subtabs-mobile" style="display: flex; gap: 0.5rem; margin-bottom: 2rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem; overflow-x: auto; scrollbar-width: none;">
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
            <!-- TAB 5: ADVANCED EMERGENCY INFORMATION CARD & ACCESS SYSTEM -->
            <?php
            $emg_q = $conn->query("SELECT * FROM emergency_cards WHERE user_id = $patient_id");
            $emg_data = ($emg_q && $emg_q->num_rows > 0) ? $emg_q->fetch_assoc() : null;
            
            $completion_status = $emg_data ? ($emg_data['completion_status'] ?? 'information_available') : 'not_created';
            $last_reviewed = $emg_data ? ($emg_data['last_reviewed_at'] ?? $emg_data['updated_at']) : null;
            $needs_review = false;
            if ($last_reviewed && (time() - strtotime($last_reviewed) > 180 * 86400)) {
                $needs_review = true;
                $completion_status = 'ready_for_review';
            }

            $public_fields = $emg_data ? json_decode($emg_data['public_fields_json'], true) : ['name', 'blood_group', 'allergies', 'emergency_contact', 'conditions', 'medications'];
            if (!is_array($public_fields)) $public_fields = [];
            
            $qr_token = $emg_data['qr_token'] ?? md5('EMG_' . $patient_id);
            $is_qr_enabled = isset($emg_data['is_qr_enabled']) ? (int)$emg_data['is_qr_enabled'] : 1;
            $emg_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/emergency_view.php?qr=' . $qr_token;

            $allergies_arr = [];
            if ($emg_data && !empty($emg_data['allergies_json'])) {
                $decoded = json_decode($emg_data['allergies_json'], true);
                if (is_array($decoded)) $allergies_arr = $decoded;
            }
            if (empty($allergies_arr) && !empty($patient_data['allergies']) && !stristr($patient_data['allergies'], 'No known')) {
                $items = array_map('trim', explode(',', $patient_data['allergies']));
                foreach ($items as $it) {
                    if (!empty($it)) $allergies_arr[] = ['allergy' => $it, 'severity' => 'Unspecified', 'reaction' => ''];
                }
            }
            ?>

            <!-- Card Overview Header Panel -->
            <div class="glass-panel" style="padding: 1.8rem; margin-bottom: 2rem; border-left: 5px solid #ff4757; border-radius: 20px; box-shadow: 0 10px 30px rgba(255, 71, 87, 0.15);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.8rem; margin-bottom: 0.5rem;">
                            <div style="width: 45px; height: 45px; border-radius: 50%; background: rgba(255, 71, 87, 0.15); color: #ff4757; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                                <i class="fas fa-heartbeat"></i>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 1.4rem; color: var(--text-primary);">EMERGENCY MEDICAL INFORMATION CARD</h3>
                                <p style="margin: 0.2rem 0 0; color: var(--text-secondary); font-size: 0.85rem;">Patient Identity & Rapid Response Health Summary</p>
                            </div>
                        </div>

                        <!-- Card Completion Badge -->
                        <div style="margin-top: 0.8rem; display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap;">
                            <?php if ($completion_status === 'information_available'): ?>
                                <span style="background: rgba(46, 213, 115, 0.15); color: #2ed573; border: 1.5px solid #2ed573; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: 800; display: inline-flex; align-items: center; gap: 0.4rem;">
                                    <i class="fas fa-check-circle"></i> Information Available
                                </span>
                            <?php elseif ($completion_status === 'ready_for_review'): ?>
                                <span style="background: rgba(255, 165, 2, 0.15); color: #ffa502; border: 1.5px solid #ffa502; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: 800; display: inline-flex; align-items: center; gap: 0.4rem;">
                                    <i class="fas fa-history"></i> Ready for Periodic Review
                                </span>
                            <?php elseif ($completion_status === 'incomplete'): ?>
                                <span style="background: rgba(236, 204, 104, 0.15); color: #eccc68; border: 1.5px solid #eccc68; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: 800; display: inline-flex; align-items: center; gap: 0.4rem;">
                                    <i class="fas fa-exclamation-triangle"></i> Incomplete Information
                                </span>
                            <?php else: ?>
                                <span style="background: rgba(255, 71, 87, 0.15); color: #ff4757; border: 1.5px solid #ff4757; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: 800; display: inline-flex; align-items: center; gap: 0.4rem;">
                                    <i class="fas fa-plus-circle"></i> Not Created Yet
                                </span>
                            <?php endif; ?>

                            <?php if ($last_reviewed): ?>
                                <span style="font-size: 0.8rem; color: var(--text-secondary);">
                                    <i class="fas fa-clock"></i> Last Updated: <?php echo date('M d, Y', strtotime($last_reviewed)); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($needs_review): ?>
                            <div style="background: rgba(255, 165, 2, 0.1); border-left: 3px solid #ffa502; padding: 0.6rem 0.9rem; border-radius: 6px; margin-top: 0.8rem; font-size: 0.82rem; color: #ffa502;">
                                <i class="fas fa-bell"></i> <strong>Neutral Reminder:</strong> Your Emergency Card has not been reviewed recently. Please verify your emergency contact numbers and medication details.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Action Buttons -->
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <a href="emergency_quick_view.php" class="btn btn-primary" style="font-size: 0.9rem; background: linear-gradient(135deg, #ff4757, #ff6b81); border: none; font-weight: 800; box-shadow: 0 4px 15px rgba(255, 71, 87, 0.4);">
                            <i class="fas fa-bolt"></i> Emergency Quick View
                        </a>
                        <a href="download_emergency_card.php" target="_blank" class="btn btn-outline" style="font-size: 0.9rem; color: #2ed573; border-color: #2ed573;">
                            <i class="fas fa-file-pdf"></i> Download PDF
                        </a>
                        <?php if ($emg_data): ?>
                            <button onclick="confirmDeleteCard()" class="btn btn-outline" style="font-size: 0.85rem; color: #ff4757; border-color: rgba(255, 71, 87, 0.4);">
                                <i class="fas fa-trash-alt"></i> Delete Card
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Smart Form & Configuration Container -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">
                
                <!-- Left: 6-Step Smart Form -->
                <div class="glass-panel" style="padding: 2rem;">
                    <h4 style="margin-top: 0; color: var(--text-primary); border-bottom: 1px solid var(--glass-border); padding-bottom: 0.8rem; margin-bottom: 1.5rem;">
                        <i class="fas fa-user-edit" style="color: var(--primary-color);"></i> Maintain Emergency Card Details
                    </h4>

                    <form id="smartEmergencyForm">
                        <input type="hidden" name="action" value="save_emergency_info">

                        <!-- SECTION 1: Emergency Contacts -->
                        <div style="margin-bottom: 1.8rem; background: rgba(255,255,255,0.02); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 14px;">
                            <h5 style="margin-top: 0; color: #2ed573; font-size: 0.95rem; font-weight: 800;"><i class="fas fa-phone-alt"></i> 1. Emergency Contact Details</h5>
                            
                            <!-- Primary Contact -->
                            <div style="margin-bottom: 1rem;">
                                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); display: block; margin-bottom: 0.3rem;">Primary Contact Full Name *</label>
                                <input type="text" name="primary_contact_name" class="form-control" value="<?php echo htmlspecialchars($emg_data['primary_contact_name'] ?? $patient_data['name']); ?>" required placeholder="e.g. Jane Doe">
                            </div>

                            <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.2rem;">
                                <div style="flex: 1; min-width: 140px;">
                                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); display: block; margin-bottom: 0.3rem;">Relationship *</label>
                                    <input type="text" name="primary_contact_rel" class="form-control" value="<?php echo htmlspecialchars($emg_data['primary_contact_rel'] ?? 'Spouse/Family'); ?>" placeholder="e.g. Spouse, Parent, Brother">
                                </div>
                                <div style="flex: 1; min-width: 160px;">
                                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); display: block; margin-bottom: 0.3rem;">Phone Number (Validated) *</label>
                                    <input type="tel" id="primary_contact_phone" name="primary_contact_phone" class="form-control" value="<?php echo htmlspecialchars($emg_data['primary_contact_phone'] ?? $patient_data['emergency_contact'] ?? $patient_data['phone']); ?>" required placeholder="e.g. 9876543210">
                                </div>
                            </div>

                            <!-- Secondary Contact -->
                            <div style="border-top: 1px dashed var(--glass-border); padding-top: 1rem; margin-top: 0.5rem;">
                                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-secondary); display: block; margin-bottom: 0.3rem;">Secondary Contact (Optional)</label>
                                <input type="text" name="secondary_contact_name" class="form-control" value="<?php echo htmlspecialchars($emg_data['secondary_contact_name'] ?? ''); ?>" placeholder="e.g. John Smith (Secondary)">
                                
                                <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 0.8rem;">
                                    <div style="flex: 1; min-width: 140px;">
                                        <input type="text" name="secondary_contact_rel" class="form-control" value="<?php echo htmlspecialchars($emg_data['secondary_contact_rel'] ?? ''); ?>" placeholder="Relationship e.g. Friend, Doctor">
                                    </div>
                                    <div style="flex: 1; min-width: 160px;">
                                        <input type="tel" id="secondary_contact_phone" name="secondary_contact_phone" class="form-control" value="<?php echo htmlspecialchars($emg_data['secondary_contact_phone'] ?? ''); ?>" placeholder="Secondary Phone Number">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 2: Known Allergies & Severity -->
                        <div style="margin-bottom: 1.8rem; background: rgba(255,255,255,0.02); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 14px; border-left: 4px solid #ff4757;">
                            <h5 style="margin-top: 0; color: #ff4757; font-size: 0.95rem; font-weight: 800;"><i class="fas fa-allergies"></i> 2. Known Allergies & Severity</h5>
                            
                            <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 1rem; cursor: pointer; color: var(--text-primary); font-size: 0.9rem;">
                                <input type="checkbox" id="no_allergies_cb" name="no_allergies_confirmed" value="1" <?php echo ($emg_data['no_allergies_confirmed'] ?? 0) ? 'checked' : ''; ?> onchange="toggleAllergiesList(this)">
                                <span style="font-weight: 700; color: #2ed573;">Explicitly Confirm: "I have NO KNOWN medical allergies"</span>
                            </label>

                            <div id="allergiesDynamicContainer" style="<?php echo ($emg_data['no_allergies_confirmed'] ?? 0) ? 'display:none;' : ''; ?>">
                                <div id="allergiesList">
                                    <?php if (!empty($allergies_arr)): ?>
                                        <?php foreach ($allergies_arr as $idx => $alg): ?>
                                            <div class="allergy-row" style="display: flex; gap: 0.5rem; margin-bottom: 0.6rem; align-items: center; flex-wrap: wrap;">
                                                <input type="text" name="allergy_name[]" class="form-control" style="flex: 2; min-width: 130px;" value="<?php echo htmlspecialchars($alg['allergy'] ?? ''); ?>" placeholder="Allergy e.g. Penicillin">
                                                <select name="allergy_severity[]" class="form-control" style="flex: 1; min-width: 110px; background: #1e293b; color: #fff;">
                                                    <option value="Mild" <?php echo ($alg['severity'] ?? '') === 'Mild' ? 'selected' : ''; ?>>Mild</option>
                                                    <option value="Moderate" <?php echo ($alg['severity'] ?? '') === 'Moderate' ? 'selected' : ''; ?>>Moderate</option>
                                                    <option value="Severe" <?php echo ($alg['severity'] ?? '') === 'Severe' ? 'selected' : ''; ?>>Severe</option>
                                                    <option value="Life-Threatening" <?php echo ($alg['severity'] ?? '') === 'Life-Threatening' ? 'selected' : ''; ?>>Life-Threatening</option>
                                                    <option value="Unspecified" <?php echo ($alg['severity'] ?? '') === 'Unspecified' ? 'selected' : ''; ?>>Unspecified</option>
                                                </select>
                                                <button type="button" onclick="this.parentElement.remove()" class="btn btn-outline" style="color: #ff4757; border-color: #ff4757; padding: 0.4rem 0.7rem;"><i class="fas fa-times"></i></button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" onclick="addAllergyRow()" class="btn btn-outline" style="font-size: 0.8rem; margin-top: 0.4rem;"><i class="fas fa-plus"></i> Add Allergy Entry</button>
                            </div>
                        </div>

                        <!-- SECTION 3: Medical Conditions & Devices -->
                        <div style="margin-bottom: 1.8rem; background: rgba(255,255,255,0.02); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 14px;">
                            <h5 style="margin-top: 0; color: #4a90e2; font-size: 0.95rem; font-weight: 800;"><i class="fas fa-stethoscope"></i> 3. Medical Conditions & Devices</h5>
                            
                            <div style="margin-bottom: 1rem;">
                                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); display: block; margin-bottom: 0.3rem;">Important Medical Conditions</label>
                                <textarea name="conditions_text" class="form-control" rows="2" placeholder="e.g. Asthma, Type 1 Diabetes, Hypertension, Epilepsy"><?php echo htmlspecialchars($emg_data['conditions_text'] ?? ''); ?></textarea>
                            </div>

                            <div>
                                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); display: block; margin-bottom: 0.3rem;">Medical Devices / Implants</label>
                                <input type="text" name="medical_devices" class="form-control" value="<?php echo htmlspecialchars($emg_data['medical_devices'] ?? ''); ?>" placeholder="e.g. Cardiac Pacemaker, Hearing Aid, Insulin Pump">
                            </div>
                        </div>

                        <!-- SECTION 4: Current Medications -->
                        <div style="margin-bottom: 1.8rem; background: rgba(255,255,255,0.02); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 14px;">
                            <h5 style="margin-top: 0; color: #50e3c2; font-size: 0.95rem; font-weight: 800;"><i class="fas fa-pills"></i> 4. Current Medications</h5>
                            <textarea name="medications_text" class="form-control" rows="2" placeholder="e.g. Metformin 500mg daily, Albuterol Inhaler as needed"><?php echo htmlspecialchars($emg_data['medications_text'] ?? ''); ?></textarea>
                            <small style="color: var(--text-secondary); display: block; margin-top: 0.35rem;"><i class="fas fa-info-circle"></i> Only enter medications explicitly confirmed by you.</small>
                        </div>

                        <!-- SECTION 5: Blood Group & Emergency Instructions -->
                        <div style="margin-bottom: 1.8rem; background: rgba(255,255,255,0.02); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 14px;">
                            <h5 style="margin-top: 0; color: #eccc68; font-size: 0.95rem; font-weight: 800;"><i class="fas fa-notes-medical"></i> 5. Identity & Special Emergency Instructions</h5>
                            
                            <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem;">
                                <div style="flex: 1; min-width: 130px;">
                                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); display: block; margin-bottom: 0.3rem;">Blood Group</label>
                                    <select name="blood_group" class="form-control" style="background: #1e293b; color: #fff;">
                                        <option value="">Select Blood Group</option>
                                        <?php 
                                            $b_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                            $curr_b = $emg_data['blood_group'] ?? $patient_data['blood_group'] ?? '';
                                            foreach ($b_groups as $bg) {
                                                $sel = ($curr_b === $bg) ? 'selected' : '';
                                                echo "<option value='$bg' $sel>$bg</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                                <div style="flex: 1; min-width: 140px;">
                                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); display: block; margin-bottom: 0.3rem;">Date of Birth</label>
                                    <input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($emg_data['date_of_birth'] ?? ''); ?>">
                                </div>
                            </div>

                            <div>
                                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); display: block; margin-bottom: 0.3rem;">Special Emergency Instructions</label>
                                <textarea name="emergency_instructions" class="form-control" rows="2" placeholder="e.g. In case of seizure, lay patient on left side. Do not administer aspirin."><?php echo htmlspecialchars($emg_data['emergency_instructions'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- SECTION 6: Privacy & Public QR Field Sharing -->
                        <div style="margin-bottom: 1.8rem; background: rgba(255,255,255,0.02); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 14px;">
                            <h5 style="margin-top: 0; color: #a29bfe; font-size: 0.95rem; font-weight: 800;"><i class="fas fa-lock"></i> 6. Sharing & Public Field Visibility</h5>
                            <p style="font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 0.8rem;">Select which fields are revealed when your Emergency QR code is scanned:</p>

                            <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.6rem; cursor: pointer; color: var(--text-primary); font-size: 0.88rem;">
                                <input type="checkbox" name="public_fields[]" value="name" <?php echo in_array('name', $public_fields) ? 'checked' : ''; ?>>
                                <span>Patient Full Name</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.6rem; cursor: pointer; color: var(--text-primary); font-size: 0.88rem;">
                                <input type="checkbox" name="public_fields[]" value="blood_group" <?php echo in_array('blood_group', $public_fields) ? 'checked' : ''; ?>>
                                <span>Blood Group</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.6rem; cursor: pointer; color: var(--text-primary); font-size: 0.88rem;">
                                <input type="checkbox" name="public_fields[]" value="allergies" <?php echo in_array('allergies', $public_fields) ? 'checked' : ''; ?>>
                                <span>Known Allergies & Severities</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.6rem; cursor: pointer; color: var(--text-primary); font-size: 0.88rem;">
                                <input type="checkbox" name="public_fields[]" value="emergency_contact" <?php echo in_array('emergency_contact', $public_fields) ? 'checked' : ''; ?>>
                                <span>Emergency Contacts & Call Buttons</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.6rem; cursor: pointer; color: var(--text-primary); font-size: 0.88rem;">
                                <input type="checkbox" name="public_fields[]" value="conditions" <?php echo in_array('conditions', $public_fields) ? 'checked' : ''; ?>>
                                <span>Medical Conditions</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.6rem; cursor: pointer; color: var(--text-primary); font-size: 0.88rem;">
                                <input type="checkbox" name="public_fields[]" value="medications" <?php echo in_array('medications', $public_fields) ? 'checked' : ''; ?>>
                                <span>Current Medications</span>
                            </label>
                        </div>

                        <!-- Form Submission -->
                        <div id="formFeedback" style="display: none; margin-bottom: 1rem; padding: 0.8rem; border-radius: 8px;"></div>
                        <button type="submit" id="saveCardBtn" class="btn btn-primary" style="width: 100%; padding: 0.9rem; font-size: 1rem; font-weight: 800; border-radius: 12px;">
                            <i class="fas fa-save"></i> Save Emergency Information Card
                        </button>
                    </form>
                </div>

                <!-- Right: Secure QR Access Controls & Live Preview -->
                <div>
                    <div class="glass-panel" style="padding: 1.8rem; text-align: center; margin-bottom: 1.5rem; border-radius: 20px;">
                        <h4 style="margin-top: 0; color: var(--text-primary);"><i class="fas fa-qrcode" style="color: #ff4757;"></i> Secure Revocable Emergency QR</h4>
                        <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">Scanning this QR code reveals only patient-authorized public details.</p>

                        <div style="background: #fff; padding: 1rem; border-radius: 16px; display: inline-block; margin-bottom: 1.2rem; border: 3px solid #ff4757;">
                            <img id="emgQrImg" src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?php echo urlencode($emg_url); ?>" alt="Emergency QR Code" style="width: 180px; height: 180px; display: block;">
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <a href="emergency_view.php?qr=<?php echo $qr_token; ?>" target="_blank" class="btn btn-outline" style="font-size: 0.85rem;"><i class="fas fa-external-link-alt"></i> Preview Public QR View</a>
                            
                            <button onclick="toggleQrAccess(<?php echo $is_qr_enabled ? 0 : 1; ?>)" class="btn btn-outline" style="font-size: 0.85rem; color: <?php echo $is_qr_enabled ? '#ff4757' : '#2ed573'; ?>; border-color: <?php echo $is_qr_enabled ? '#ff4757' : '#2ed573'; ?>;">
                                <i class="fas <?php echo $is_qr_enabled ? 'fa-eye-slash' : 'fa-eye'; ?>"></i> <?php echo $is_qr_enabled ? 'Disable Emergency QR Access' : 'Enable Emergency QR Access'; ?>
                            </button>

                            <button onclick="regenerateQrToken()" class="btn btn-outline" style="font-size: 0.85rem; color: #a29bfe; border-color: #a29bfe;">
                                <i class="fas fa-sync-alt"></i> Regenerate QR Token (Revoke Old)
                            </button>
                        </div>
                    </div>

                    <!-- Security & Informed Consent Notice -->
                    <div class="glass-panel" style="padding: 1.25rem; font-size: 0.82rem; color: var(--text-secondary); border-left: 3px solid var(--primary-color); border-radius: 14px;">
                        <strong style="color: var(--text-primary);"><i class="fas fa-shield-alt" style="color: var(--primary-color);"></i> Privacy & Security Assurance:</strong>
                        <ul style="margin: 0.5rem 0 0; padding-left: 1.2rem; line-height: 1.5;">
                            <li>Server-side IDOR protection ensures only you can modify your records.</li>
                            <li>QR token is unguessable and can be revoked at any time.</li>
                            <li>No financial details or full medical history are ever exposed publicly.</li>
                        </ul>
                    </div>
                </div>

            </div>

            <!-- Smart Form Scripts -->
            <script>
            let isFormDirty = false;

            document.querySelectorAll('#smartEmergencyForm input, #smartEmergencyForm textarea, #smartEmergencyForm select').forEach(el => {
                el.addEventListener('change', () => { isFormDirty = true; });
                el.addEventListener('input', () => { isFormDirty = true; });
            });

            window.addEventListener('beforeunload', function(e) {
                if (isFormDirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            function toggleAllergiesList(cb) {
                document.getElementById('allergiesDynamicContainer').style.display = cb.checked ? 'none' : 'block';
            }

            function addAllergyRow() {
                const container = document.getElementById('allergiesList');
                const row = document.createElement('div');
                row.className = 'allergy-row';
                row.style.cssText = 'display: flex; gap: 0.5rem; margin-bottom: 0.6rem; align-items: center; flex-wrap: wrap;';
                row.innerHTML = `
                    <input type="text" name="allergy_name[]" class="form-control" style="flex: 2; min-width: 130px;" placeholder="Allergy e.g. Penicillin">
                    <select name="allergy_severity[]" class="form-control" style="flex: 1; min-width: 110px; background: #1e293b; color: #fff;">
                        <option value="Mild">Mild</option>
                        <option value="Moderate">Moderate</option>
                        <option value="Severe">Severe</option>
                        <option value="Life-Threatening">Life-Threatening</option>
                        <option value="Unspecified" selected>Unspecified</option>
                    </select>
                    <button type="button" onclick="this.parentElement.remove()" class="btn btn-outline" style="color: #ff4757; border-color: #ff4757; padding: 0.4rem 0.7rem;"><i class="fas fa-times"></i></button>
                `;
                container.appendChild(row);
            }

            document.getElementById('smartEmergencyForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const feedback = document.getElementById('formFeedback');
                feedback.style.display = 'none';

                // Collect dynamic allergies
                const allergiesArr = [];
                const names = document.querySelectorAll('input[name="allergy_name[]"]');
                const sevs = document.querySelectorAll('select[name="allergy_severity[]"]');
                
                names.forEach((el, idx) => {
                    const val = el.value.trim();
                    if (val) {
                        allergiesArr.push({
                            allergy: val,
                            severity: sevs[idx] ? sevs[idx].value : 'Unspecified',
                            reaction: ''
                        });
                    }
                });

                const formData = new FormData(this);
                formData.append('allergies_json', JSON.stringify(allergiesArr));

                document.getElementById('saveCardBtn').disabled = true;
                document.getElementById('saveCardBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving Verified Information...';

                fetch('api_patient_features.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    document.getElementById('saveCardBtn').disabled = false;
                    document.getElementById('saveCardBtn').innerHTML = '<i class="fas fa-save"></i> Save Emergency Information Card';
                    
                    feedback.style.display = 'block';
                    if (res.success) {
                        isFormDirty = false;
                        feedback.style.background = 'rgba(46, 213, 115, 0.15)';
                        feedback.style.color = '#2ed573';
                        feedback.style.border = '1px solid #2ed573';
                        feedback.innerHTML = '<i class="fas fa-check-circle"></i> ' + res.message;
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        feedback.style.background = 'rgba(255, 71, 87, 0.15)';
                        feedback.style.color = '#ff4757';
                        feedback.style.border = '1px solid #ff4757';
                        feedback.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + res.message;
                    }
                })
                .catch(err => {
                    document.getElementById('saveCardBtn').disabled = false;
                    document.getElementById('saveCardBtn').innerHTML = '<i class="fas fa-save"></i> Save Emergency Information Card';
                    feedback.style.display = 'block';
                    feedback.style.background = 'rgba(255, 71, 87, 0.15)';
                    feedback.style.color = '#ff4757';
                    feedback.innerHTML = '<i class="fas fa-exclamation-circle"></i> Server communication error. Please try again.';
                });
            });

            function toggleQrAccess(newStatus) {
                const fd = new FormData();
                fd.append('action', 'toggle_qr_access');
                fd.append('is_qr_enabled', newStatus);
                fetch('api_patient_features.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => { if(res.success) location.reload(); });
            }

            function regenerateQrToken() {
                if (!confirm("Are you sure you want to regenerate your Emergency QR token? Any printed or shared QR code will no longer grant access.")) return;
                const fd = new FormData();
                fd.append('action', 'regenerate_qr_token');
                fetch('api_patient_features.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => { if(res.success) location.reload(); });
            }

            function confirmDeleteCard() {
                if (!confirm("CAUTION: Are you sure you want to delete your Emergency Information Card? This action cannot be undone.")) return;
                const fd = new FormData();
                fd.append('action', 'delete_emergency_card');
                fetch('api_patient_features.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => { if(res.success) location.reload(); });
            }
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
