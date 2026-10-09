<?php
require_once 'config.php';
$vid = trim($_GET['vid'] ?? $_GET['qr'] ?? $_GET['id'] ?? '');

$initial_doc = null;
if (!empty($vid)) {
    $initial_doc = get_document_verification_by_id($conn, $vid);
}

include 'includes/header.php';
?>

<style>
/* MedicalIAk Document Verification Custom Aesthetics */
.ver-hero-banner {
    background: linear-gradient(135deg, #059669 0%, #10B981 50%, #047857 100%);
    border-radius: 18px;
    color: #ffffff;
    padding: 2rem 1.75rem;
    margin-bottom: 1.75rem;
    box-shadow: 0 10px 30px -5px rgba(16, 185, 129, 0.4);
    position: relative;
    overflow: hidden;
}

.ver-search-box {
    background: rgba(0, 0, 0, 0.25);
    border: 1.5px solid rgba(255, 255, 255, 0.35);
    border-radius: 14px;
    padding: 0.4rem;
    display: flex;
    gap: 0.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.ver-search-box:focus-within {
    border-color: #ffffff;
    box-shadow: 0 0 20px rgba(255, 255, 255, 0.4);
}

.ver-input {
    background: transparent !important;
    border: none !important;
    color: #ffffff !important;
    font-size: 1rem;
    font-weight: 600;
    padding: 0.6rem 0.9rem;
    width: 100%;
    box-shadow: none !important;
}

.ver-input::placeholder {
    color: rgba(255, 255, 255, 0.7) !important;
}

.ver-verify-btn {
    background: #ffffff !important;
    color: #047857 !important;
    font-weight: 800 !important;
    font-size: 0.92rem !important;
    border-radius: 10px !important;
    padding: 0.65rem 1.4rem !important;
    border: none !important;
    white-space: nowrap;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transition: all 0.2s ease;
}

.ver-verify-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.25);
    background: #f8fafc !important;
}

.ver-card-item {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.12));
    border-radius: 14px;
    padding: 1.1rem;
}

.ver-hash-display {
    background: #090d16;
    border: 1px solid rgba(16, 185, 129, 0.35);
    border-radius: 12px;
    padding: 1rem;
    font-family: monospace;
    word-break: break-all;
    color: #34d399;
}

.ver-drag-area {
    border: 2px dashed rgba(16, 185, 129, 0.4);
    background: rgba(16, 185, 129, 0.04);
    border-radius: 14px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
}

.ver-drag-area:hover {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.08);
}

@media (max-width: 576px) {
    .ver-hero-banner {
        padding: 1.5rem 1rem;
    }
    .ver-search-box {
        flex-direction: column;
        background: transparent;
        border: none;
        box-shadow: none;
        padding: 0;
    }
    .ver-input {
        background: rgba(0, 0, 0, 0.35) !important;
        border: 1.5px solid rgba(255, 255, 255, 0.4) !important;
        border-radius: 12px !important;
        margin-bottom: 0.5rem;
    }
    .ver-verify-btn {
        width: 100%;
        border-radius: 12px !important;
        padding: 0.75rem !important;
    }
}
</style>

<div class="dashboard-layout">
    <?php if (isset($_SESSION['user_id'])): ?>
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Navigation">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Navigation Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Authenticity Hub</h3>
        <ul class="sidebar-menu">
            <li><a href="verify_document.php" class="active"><i class="fas fa-shield-alt"></i> Verify Document</a></li>
            <?php if ($_SESSION['role'] === 'patient'): ?>
                <li><a href="patient_dashboard.php"><i class="fas fa-home"></i> Dashboard Overview</a></li>
                <li><a href="prescription_vault.php"><i class="fas fa-file-prescription"></i> Prescription Vault</a></li>
                <li><a href="health_vault.php"><i class="fas fa-vault"></i> Health Vault</a></li>
                <li><a href="customer_support.php"><i class="fas fa-headset"></i> Support Center</a></li>
            <?php elseif ($_SESSION['role'] === 'doctor'): ?>
                <li><a href="doctor_dashboard.php"><i class="fas fa-home"></i> Doctor Dashboard</a></li>
                <li><a href="doctor_appointments.php"><i class="fas fa-calendar-check"></i> Appointments & Prescriptions</a></li>
            <?php elseif ($_SESSION['role'] === 'admin'): ?>
                <li><a href="admin_dashboard.php"><i class="fas fa-chart-pie"></i> Admin Panel</a></li>
                <li><a href="admin_audit.php"><i class="fas fa-history"></i> Audit Timeline</a></li>
            <?php else: ?>
                <li><a href="rmp_dashboard.php"><i class="fas fa-user-nurse"></i> RMP Panel</a></li>
            <?php endif; ?>
        </ul>
    </aside>
    <?php endif; ?>

    <main class="dashboard-content">
        <!-- MedicalIAk Emerald Hero Banner -->
        <div class="ver-hero-banner">
            <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(255,255,255,0.2); padding: 0.35rem 0.9rem; border-radius: 20px; font-size: 0.82rem; font-weight: 700; margin-bottom: 0.8rem;">
                <i class="fas fa-shield-alt"></i> MedicalIAk Trust & Authenticity Registry
            </div>
            <h2 style="font-size: 1.75rem; font-weight: 800; margin: 0 0 0.5rem 0; color: #ffffff; line-height: 1.2;">
                Medical Document Authenticity Verification
            </h2>
            <p style="margin: 0 0 1.5rem 0; font-size: 0.92rem; color: rgba(255, 255, 255, 0.92); max-width: 680px; line-height: 1.4;">
                Verify official prescriptions, lab reports, medical certificates, and health credentials issued through the MedicalIAk Healthcare Portal.
            </p>

            <!-- Verification ID Search Form -->
            <form id="verifyForm" action="verify_document.php" method="GET" style="max-width: 620px;">
                <div class="ver-search-box">
                    <input type="text" name="vid" id="verificationIdInput" class="ver-input" 
                           placeholder="Enter Verification ID (e.g. DOC-VER-A1B2-C3D4)" 
                           value="<?php echo htmlspecialchars($vid); ?>" required>
                    <button type="submit" class="btn ver-verify-btn">
                        <i class="fas fa-check-circle me-1"></i> Verify Record
                    </button>
                </div>
                <div style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.85); margin-top: 0.6rem;">
                    <i class="fas fa-qrcode me-1"></i> Or scan the QR code printed on official MedicalIAk documents
                </div>
            </form>
        </div>

        <!-- Verification Results Output -->
        <div id="resultContainer">
            <?php if (!empty($vid) && !$initial_doc): ?>
                <div class="glass-panel" style="padding: 2.5rem 1.5rem; text-align: center; margin-bottom: 1.5rem; border-left: 4px solid #ef4444;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem;"></i>
                    <h3 style="color: var(--text-primary); margin: 0 0 0.5rem 0; font-weight: 800;">Document Record Not Found</h3>
                    <p style="color: var(--text-secondary); max-width: 520px; margin: 0 auto 1.25rem; font-size: 0.9rem;">
                        No official medical document matching Verification ID <strong style="color: #f59e0b; font-family: monospace;"><?php echo htmlspecialchars($vid); ?></strong> was found in MedicalIAk verified document registry.
                    </p>
                    <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 12px; padding: 1rem; color: #fbbf24; text-align: left; max-width: 580px; margin: 0 auto; font-size: 0.85rem;">
                        <i class="fas fa-info-circle me-1"></i> <strong>Notice for Verifiers:</strong> If you received a paper or digital copy claiming to be issued by MedicalIAk, please check the ID for typos or test the file integrity using our SHA-256 verification tool below.
                    </div>
                </div>
            <?php elseif ($initial_doc): ?>
                <?php 
                    $status = $initial_doc['status'];
                    $badge_bg = 'rgba(16, 185, 129, 0.15)';
                    $badge_border = 'rgba(16, 185, 129, 0.4)';
                    $badge_color = '#10b981';
                    $status_icon = 'fa-check-circle';
                    $status_title = 'OFFICIALLY VERIFIED & VALID';
                    
                    if ($status === 'REVOKED') {
                        $badge_bg = 'rgba(239, 68, 68, 0.15)';
                        $badge_border = 'rgba(239, 68, 68, 0.4)';
                        $badge_color = '#ef4444';
                        $status_icon = 'fa-ban';
                        $status_title = 'DOCUMENT REVOKED BY ISSUER';
                    } elseif ($status === 'EXPIRED' || ($initial_doc['expires_at'] && strtotime($initial_doc['expires_at']) < time())) {
                        $badge_bg = 'rgba(245, 158, 11, 0.15)';
                        $badge_border = 'rgba(245, 158, 11, 0.4)';
                        $badge_color = '#f59e0b';
                        $status_icon = 'fa-clock';
                        $status_title = 'DOCUMENT EXPIRED';
                    }

                    // Mask patient name
                    $pname = $initial_doc['patient_name'] ?? 'Registered Patient';
                    $parts = explode(' ', $pname);
                    $masked_parts = array_map(function($p) {
                        if (mb_strlen($p) <= 1) return $p;
                        return mb_substr($p, 0, 1) . str_repeat('*', max(1, mb_strlen($p) - 1));
                    }, $parts);
                    $masked_name = implode(' ', $masked_parts);
                ?>

                <div class="glass-panel" style="padding: 1.75rem; margin-bottom: 1.75rem; border-left: 4px solid <?php echo $badge_color; ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.8rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 1rem; margin-bottom: 1.25rem;">
                        <span style="background: <?php echo $badge_bg; ?>; border: 1px solid <?php echo $badge_border; ?>; color: <?php echo $badge_color; ?>; padding: 0.4rem 1rem; border-radius: 20px; font-weight: 800; font-size: 0.88rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                            <i class="fas <?php echo $status_icon; ?>"></i> <?php echo $status_title; ?>
                        </span>
                        <div style="font-size: 0.85rem; color: var(--text-secondary);">
                            Verification ID: <strong style="color: var(--primary-color); font-family: monospace; font-size: 0.95rem;"><?php echo htmlspecialchars($initial_doc['verification_id']); ?></strong>
                        </div>
                    </div>

                    <?php if ($status === 'REVOKED'): ?>
                        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 1.1rem; color: #fca5a5; margin-bottom: 1.25rem;">
                            <div style="display: flex; gap: 0.8rem; align-items: flex-start;">
                                <i class="fas fa-exclamation-circle" style="font-size: 1.5rem; color: #ef4444; margin-top: 0.1rem;"></i>
                                <div>
                                    <h4 style="margin: 0 0 0.3rem 0; color: #ffffff; font-weight: 700;">Revocation Notice</h4>
                                    <p style="margin: 0 0 0.4rem 0; font-size: 0.88rem; color: rgba(255,255,255,0.9);">This medical document was formally revoked by the issuing authority and is no longer valid for official use.</p>
                                    <div style="font-size: 0.85rem; color: #ffffff;">
                                        <strong>Reason:</strong> <?php echo htmlspecialchars($initial_doc['revocation_reason'] ?? 'Not specified'); ?><br>
                                        <strong>Revoked Date:</strong> <?php echo $initial_doc['revoked_at'] ? date('M d, Y - h:i A', strtotime($initial_doc['revoked_at'])) : 'N/A'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                        <div class="ver-card-item">
                            <div style="font-size: 0.78rem; color: var(--text-secondary); text-transform: uppercase; font-weight: bold; margin-bottom: 0.3rem;">Document Type</div>
                            <div style="font-size: 1.05rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-file-medical" style="color: #10b981;"></i>
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $initial_doc['doc_type']))); ?>
                            </div>
                        </div>

                        <div class="ver-card-item">
                            <div style="font-size: 0.78rem; color: var(--text-secondary); text-transform: uppercase; font-weight: bold; margin-bottom: 0.3rem;">Issued Date</div>
                            <div style="font-size: 1.05rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-calendar-check" style="color: #34d399;"></i>
                                <?php echo $initial_doc['issued_at'] ? date('M d, Y', strtotime($initial_doc['issued_at'])) : 'N/A'; ?>
                            </div>
                        </div>

                        <div class="ver-card-item">
                            <div style="font-size: 0.78rem; color: var(--text-secondary); text-transform: uppercase; font-weight: bold; margin-bottom: 0.3rem;">Authorized Issuer</div>
                            <div style="font-size: 1rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.2rem;">
                                <i class="fas fa-user-md" style="color: #38bdf8;"></i>
                                <?php echo htmlspecialchars($initial_doc['issuer_name'] ? 'Dr. ' . str_replace('Dr. ', '', $initial_doc['issuer_name']) : 'Authorized Medical Specialist'); ?>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-secondary);">
                                <?php echo htmlspecialchars(ucwords($initial_doc['issuer_specialization'] ?? $initial_doc['issuer_role'] ?? 'Verified Doctor')); ?>
                                <span style="background: rgba(16,185,129,0.15); color: #10b981; padding: 0.15rem 0.5rem; border-radius: 8px; font-weight: bold; margin-left: 0.3rem;"><i class="fas fa-check-circle"></i> Verified</span>
                            </div>
                        </div>

                        <div class="ver-card-item">
                            <div style="font-size: 0.78rem; color: var(--text-secondary); text-transform: uppercase; font-weight: bold; margin-bottom: 0.3rem;">Patient Holder (Masked PII)</div>
                            <div style="font-size: 1rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.2rem;">
                                <i class="fas fa-user-shield" style="color: #a78bfa;"></i>
                                <?php echo htmlspecialchars($masked_name); ?>
                            </div>
                            <?php if (!empty($initial_doc['patient_health_id'])): ?>
                                <div style="font-size: 0.78rem; color: var(--text-secondary); font-family: monospace;">
                                    Health ID: <?php echo htmlspecialchars($initial_doc['patient_health_id']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- SHA-256 Digital Signature Hash -->
                    <div style="margin-top: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <span style="font-size: 0.8rem; font-weight: 800; color: #10b981; text-transform: uppercase; letter-spacing: 0.5px;">
                                <i class="fas fa-fingerprint me-1"></i> SHA-256 Cryptographic Digital Signature
                            </span>
                            <span style="font-size: 0.72rem; background: rgba(255,255,255,0.08); padding: 0.2rem 0.6rem; border-radius: 10px; color: var(--text-secondary);">Tamper-Evident</span>
                        </div>
                        <div class="ver-hash-display">
                            <?php echo htmlspecialchars($initial_doc['doc_hash']); ?>
                        </div>
                        <p style="font-size: 0.78rem; color: var(--text-secondary); margin: 0.5rem 0 0 0;">
                            This 256-bit digital checksum guarantees that the document contents have not been altered or forged after issuance.
                        </p>
                    </div>

                    <div style="margin-top: 1.5rem; pt: 1rem; border-top: 1px solid var(--glass-border); display: flex; justify-content: space-between; gap: 0.6rem; flex-wrap: wrap;">
                        <button type="button" class="btn btn-outline" style="font-size: 0.82rem; color: #ef4444; border-color: rgba(239, 68, 68, 0.4);" onclick="document.getElementById('reportModal').style.display='flex'">
                            <i class="fas fa-flag me-1"></i> Report Suspicious Document
                        </button>
                        <a href="verify_document.php" class="btn btn-outline" style="font-size: 0.82rem;">
                            <i class="fas fa-redo me-1"></i> Verify Another Document
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- SHA-256 File Integrity Tool -->
        <div class="glass-panel" style="padding: 1.75rem; margin-bottom: 1.75rem; border-left: 4px solid #10b981;">
            <div style="margin-bottom: 1.25rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.8rem;">
                <h3 style="margin: 0 0 0.3rem 0; font-size: 1.15rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-file-contract" style="color: #10b981;"></i> Upload File Integrity Check
                </h3>
                <p style="margin: 0; font-size: 0.85rem; color: var(--text-secondary);">
                    Upload any MedicalIAk PDF or image report to test whether its content matches the tamper-evident hash stored in our server.
                </p>
            </div>

            <form id="fileIntegrityForm" enctype="multipart/form-data">
                <div class="ver-drag-area" style="margin-bottom: 1.25rem;">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 2.2rem; color: #10b981; margin-bottom: 0.6rem;"></i>
                    <h4 style="margin: 0 0 0.3rem 0; font-size: 0.95rem; font-weight: 700; color: var(--text-primary);">Select PDF or Image Document</h4>
                    <p style="margin: 0 0 1rem 0; font-size: 0.8rem; color: var(--text-secondary);">Supports official MedicalIAk PDF reports, PNG, and JPG images</p>
                    <input type="file" id="docFile" name="doc_file" accept=".pdf,.png,.jpg,.jpeg" required style="max-width: 320px; margin: 0 auto; display: block;" class="form-control">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-size: 0.92rem; font-weight: 800; background: linear-gradient(135deg, #059669 0%, #10B981 100%); border: none;">
                    <i class="fas fa-shield-alt me-1"></i> Run SHA-256 Integrity Check
                </button>
            </form>

            <!-- Integrity Result Box -->
            <div id="integrityResult" style="margin-top: 1.25rem; display: none;"></div>
        </div>

        <!-- Trust FAQ / System Details -->
        <div class="glass-panel" style="padding: 1.75rem;">
            <h3 style="margin: 0 0 1.25rem 0; font-size: 1.15rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-info-circle" style="color: #38bdf8;"></i> How MedicalIAk Authenticity Verification Works
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem;">
                <div style="display: flex; gap: 0.8rem; align-items: flex-start;">
                    <div style="width: 42px; height: 42px; border-radius: 12px; background: rgba(56, 189, 248, 0.15); color: #38bdf8; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                        <i class="fas fa-key"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 0.3rem 0; font-size: 0.92rem; font-weight: 700; color: var(--text-primary);">Unique Verification ID</h4>
                        <p style="margin: 0; font-size: 0.8rem; color: var(--text-secondary); line-height: 1.4;">Every prescription and certified record gets a unique cryptographically generated Verification ID and QR token.</p>
                    </div>
                </div>

                <div style="display: flex; gap: 0.8rem; align-items: flex-start;">
                    <div style="width: 42px; height: 42px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); color: #34d399; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 0.3rem 0; font-size: 0.92rem; font-weight: 700; color: var(--text-primary);">SHA-256 Hashing</h4>
                        <p style="margin: 0; font-size: 0.8rem; color: var(--text-secondary); line-height: 1.4;">A 256-bit digital checksum is calculated upon issuance. Any modification to the document alters the hash and fails verification.</p>
                    </div>
                </div>

                <div style="display: flex; gap: 0.8rem; align-items: flex-start;">
                    <div style="width: 42px; height: 42px; border-radius: 12px; background: rgba(239, 68, 68, 0.15); color: #fca5a5; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                        <i class="fas fa-ban"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 0.3rem 0; font-size: 0.92rem; font-weight: 700; color: var(--text-primary);">Instant Revocation Check</h4>
                        <p style="margin: 0; font-size: 0.8rem; color: var(--text-secondary); line-height: 1.4;">If a doctor or facility revokes an issued document, its status is updated instantly in real time across the portal.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Report Suspicious Document Modal -->
<div id="reportModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.75); backdrop-filter: blur(5px); z-index: 999999; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: var(--darker-bg, #121826); border: 1px solid var(--glass-border); border-radius: 18px; max-width: 480px; width: 100%; color: var(--text-primary); overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.6);">
        <div style="background: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%); padding: 1.2rem 1.5rem; display: flex; justify-content: space-between; align-items: center; color: #ffffff;">
            <h4 style="margin: 0; font-weight: 800; font-size: 1.05rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-flag"></i> Report Suspicious Document
            </h4>
            <button type="button" onclick="document.getElementById('reportModal').style.display='none'" style="background: none; border: none; color: #ffffff; font-size: 1.2rem; cursor: pointer;">&times;</button>
        </div>
        <form id="reportDocForm" style="padding: 1.5rem;">
            <input type="hidden" name="action" value="report_suspicious">
            <input type="hidden" name="verification_id" value="<?php echo htmlspecialchars($vid); ?>">

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.3rem;">Verification ID</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($vid ? $vid : 'Unregistered / General'); ?>" readonly style="font-family: monospace; color: var(--primary-color);">
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.3rem;">Your Contact Email (Optional)</label>
                <input type="email" class="form-control" name="reporter_email" placeholder="email@example.com">
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.3rem;">Reason for Report *</label>
                <select class="form-select form-control" name="reason" required>
                    <option value="">-- Select Reason --</option>
                    <option value="Suspected Alteration or Forgery">Suspected Alteration or Forgery</option>
                    <option value="Unauthorized Doctor/Practitioner Name">Unauthorized Doctor/Practitioner Name</option>
                    <option value="Expired or Reused Prescription">Expired or Reused Prescription</option>
                    <option value="Incorrect Patient Information">Incorrect Patient Information</option>
                    <option value="Other Security Concern">Other Security Concern</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label style="display: block; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.3rem;">Additional Details</label>
                <textarea class="form-control" name="details" rows="3" placeholder="Provide any additional context or observations..."></textarea>
            </div>

            <div style="display: flex; gap: 0.8rem; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('reportModal').style.display='none'" style="font-size: 0.85rem;">Cancel</button>
                <button type="submit" class="btn" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: #ffffff; font-weight: 800; font-size: 0.85rem; border: none; padding: 0.5rem 1.2rem; border-radius: 8px;">
                    <i class="fas fa-paper-plane me-1"></i> Submit Report
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileIntegrityForm = document.getElementById('fileIntegrityForm');
    const integrityResult = document.getElementById('integrityResult');
    const reportDocForm = document.getElementById('reportDocForm');

    // SHA-256 File Integrity submit
    if (fileIntegrityForm) {
        fileIntegrityForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(fileIntegrityForm);
            formData.append('action', 'verify_file_integrity');
            
            const currentVid = '<?php echo htmlspecialchars($vid); ?>';
            if (currentVid) {
                formData.append('verification_id', currentVid);
            }

            integrityResult.style.display = 'block';
            integrityResult.innerHTML = `
                <div class="glass-panel" style="padding: 1rem; text-align: center; color: var(--text-secondary);">
                    <i class="fas fa-sync-alt fa-spin" style="color: var(--primary-color); margin-right: 0.4rem;"></i>
                    Calculating SHA-256 cryptographic checksum and matching with MedicalIAk records...
                </div>
            `;

            fetch('api_document_verification.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    integrityResult.innerHTML = `
                        <div class="glass-panel" style="padding: 1rem; color: #ef4444; border-left: 4px solid #ef4444;">
                            <i class="fas fa-exclamation-triangle me-1"></i> ${data.message}
                        </div>
                    `;
                    return;
                }

                if (data.match_found && data.is_authentic) {
                    integrityResult.innerHTML = `
                        <div class="glass-panel" style="padding: 1.25rem; border-left: 4px solid #10b981; background: rgba(16, 185, 129, 0.08);">
                            <div style="display: flex; align-items: flex-start; gap: 0.8rem;">
                                <i class="fas fa-check-circle" style="font-size: 1.8rem; color: #10b981; margin-top: 0.1rem;"></i>
                                <div>
                                    <h4 style="margin: 0 0 0.3rem 0; color: #10b981; font-weight: 800;">Authentic File Signature Match!</h4>
                                    <p style="margin: 0 0 0.5rem 0; color: var(--text-primary); font-size: 0.9rem;">The uploaded document's digital SHA-256 signature matches the official record registered in MedicalIAk system.</p>
                                    <div style="font-family: monospace; background: #090d16; padding: 0.5rem 0.8rem; border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; font-size: 0.85rem; margin-bottom: 0.5rem; word-break: break-all;">
                                        <strong>SHA-256:</strong> ${data.uploaded_hash}
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                        <strong>Document:</strong> ${data.doc_type} | 
                                        <strong>Verification ID:</strong> ${data.verification_id} | 
                                        <strong>Issuer:</strong> ${data.issuer_name}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                } else if (data.match_found && !data.is_authentic) {
                    integrityResult.innerHTML = `
                        <div class="glass-panel" style="padding: 1.25rem; border-left: 4px solid #f59e0b; background: rgba(245, 158, 11, 0.08);">
                            <div style="display: flex; align-items: flex-start; gap: 0.8rem;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 1.8rem; color: #f59e0b; margin-top: 0.1rem;"></i>
                                <div>
                                    <h4 style="margin: 0 0 0.3rem 0; color: #f59e0b; font-weight: 800;">Hash Match Found, But Document Status: ${data.status}</h4>
                                    <p style="margin: 0; color: var(--text-primary); font-size: 0.9rem;">This file matches a registered document hash, but the document status is currently marked as <strong>${data.status}</strong>.</p>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    integrityResult.innerHTML = `
                        <div class="glass-panel" style="padding: 1.25rem; border-left: 4px solid #ef4444; background: rgba(239, 68, 68, 0.08);">
                            <div style="display: flex; align-items: flex-start; gap: 0.8rem;">
                                <i class="fas fa-times-circle" style="font-size: 1.8rem; color: #ef4444; margin-top: 0.1rem;"></i>
                                <div>
                                    <h4 style="margin: 0 0 0.3rem 0; color: #ef4444; font-weight: 800;">Warning: Unverified or Altered Document</h4>
                                    <p style="margin: 0 0 0.5rem 0; color: var(--text-primary); font-size: 0.9rem;">The SHA-256 digital signature of this file does not match any registered official MedicalIAk document.</p>
                                    <div style="font-family: monospace; background: #090d16; padding: 0.5rem 0.8rem; border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; font-size: 0.85rem; margin-bottom: 0.5rem; word-break: break-all;">
                                        <strong>Calculated SHA-256:</strong> ${data.uploaded_hash}
                                    </div>
                                    <p style="margin: 0; font-size: 0.8rem; color: var(--text-secondary);">Possible reasons: The file has been modified after issuance, converted incorrectly, or was not issued through MedicalIAk.</p>
                                </div>
                            </div>
                        </div>
                    `;
                }
            })
            .catch(err => {
                integrityResult.innerHTML = `
                    <div class="glass-panel" style="padding: 1rem; color: #ef4444; border-left: 4px solid #ef4444;">
                        <i class="fas fa-exclamation-circle me-1"></i> An error occurred while calculating file checksum. Please try again.
                    </div>
                `;
            });
        });
    }

    // Report Suspicious Form submit
    if (reportDocForm) {
        reportDocForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(reportDocForm);

            fetch('api_document_verification.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    document.getElementById('reportModal').style.display = 'none';
                    reportDocForm.reset();
                }
            })
            .catch(err => alert('Failed to submit report. Please try again.'));
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
