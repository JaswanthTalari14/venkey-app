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
/* Custom Aesthetic Styling for Medical Document Authenticity Verification */
.ver-hero-card {
    background: linear-gradient(135deg, #064e3b 0%, #0f172a 50%, #0284c7 100%);
    border: 1px solid var(--glass-border, rgba(255,255,255,0.15));
    border-radius: 20px;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4);
    position: relative;
    overflow: hidden;
}

.ver-hero-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, transparent 60%);
    pointer-events: none;
}

.ver-glass-card {
    background: var(--darker-bg, #121826);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.12));
    border-radius: 18px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.35);
    color: var(--text-primary, #ffffff);
}

.ver-glass-header {
    background: rgba(255, 255, 255, 0.03);
    border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.1));
    padding: 1.25rem 1.5rem;
}

.ver-search-input-group {
    background: rgba(15, 23, 42, 0.85);
    border: 1.5px solid rgba(16, 185, 129, 0.5);
    border-radius: 14px;
    padding: 0.35rem;
    box-shadow: 0 4px 20px rgba(16, 185, 129, 0.2);
    transition: all 0.3s ease;
}

.ver-search-input-group:focus-within {
    border-color: #10b981;
    box-shadow: 0 0 20px rgba(16, 185, 129, 0.4);
}

.ver-search-input {
    background: transparent !important;
    border: none !important;
    color: #ffffff !important;
    font-size: 0.95rem;
    font-weight: 600;
    box-shadow: none !important;
}

.ver-search-input::placeholder {
    color: rgba(255, 255, 255, 0.5) !important;
}

.ver-btn-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border: none;
    color: #ffffff;
    font-weight: 700;
    border-radius: 10px;
    padding: 0.6rem 1.4rem;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
    transition: all 0.25s ease;
}

.ver-btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5);
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    color: #ffffff;
}

.ver-file-upload-box {
    border: 2px dashed rgba(16, 185, 129, 0.4);
    background: rgba(16, 185, 129, 0.03);
    border-radius: 14px;
    padding: 1.25rem;
    transition: all 0.3s ease;
}

.ver-file-upload-box:hover {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.08);
}

.ver-info-box {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.1));
    border-radius: 12px;
    padding: 1rem;
}

.ver-hash-box {
    background: #090d16;
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 12px;
    padding: 1rem;
}

@media (max-width: 576px) {
    .ver-search-input-group {
        flex-direction: column;
        background: transparent;
        border: none;
        box-shadow: none;
        padding: 0;
    }
    .ver-search-input-group .ver-search-input {
        background: rgba(15, 23, 42, 0.9) !important;
        border: 1.5px solid rgba(16, 185, 129, 0.5) !important;
        border-radius: 12px !important;
        padding: 0.75rem 1rem !important;
        margin-bottom: 0.5rem;
    }
    .ver-search-input-group .ver-btn-primary {
        width: 100%;
        border-radius: 12px;
        padding: 0.75rem;
    }
}
</style>

<main class="container my-4 my-md-5">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            
            <!-- Hero / Title Banner -->
            <div class="card ver-hero-card text-white mb-4">
                <div class="card-body p-4 p-md-5 text-center position-relative" style="z-index: 2;">
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-15 rounded-pill mb-3 fw-semibold border" style="background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.4) !important; color: #34d399; font-size: 0.85rem;">
                        <i class="fas fa-shield-alt"></i> MedicalIAk Trust & Authenticity Registry
                    </div>
                    
                    <h1 class="display-6 fw-extrabold mb-3 text-white" style="letter-spacing: -0.5px;">Medical Document Authenticity Verification</h1>
                    
                    <p class="lead text-light text-opacity-90 mb-4 mx-auto" style="max-width: 650px; font-size: 1rem; line-height: 1.5;">
                        Verify official prescriptions, lab reports, medical certificates, and health credentials issued through the MedicalIAk Healthcare Portal.
                    </p>

                    <!-- Search Form -->
                    <form id="verifyForm" action="verify_document.php" method="GET" class="mx-auto" style="max-width: 640px;">
                        <div class="input-group input-group-lg ver-search-input-group">
                            <span class="input-group-text bg-transparent border-0 text-success ps-3 d-none d-sm-flex">
                                <i class="fas fa-search" style="color: #10b981;"></i>
                            </span>
                            <input type="text" name="vid" id="verificationIdInput" class="form-control ver-search-input" 
                                   placeholder="Enter Verification ID (e.g. DOC-VER-A1B2-C3D4)" 
                                   value="<?php echo htmlspecialchars($vid); ?>" required>
                            <button type="submit" class="btn ver-btn-primary">
                                <i class="fas fa-check-circle me-1"></i> Verify Record
                            </button>
                        </div>
                        <div class="form-text text-light text-opacity-75 mt-3" style="font-size: 0.83rem;">
                            <i class="fas fa-qrcode me-1" style="color: #34d399;"></i> Or scan the QR code printed on official MedicalIAk documents
                        </div>
                    </form>
                </div>
            </div>

            <!-- Verification Output Container -->
            <div id="resultContainer">
                <?php if (!empty($vid) && !$initial_doc): ?>
                    <div class="card ver-glass-card text-center p-4 p-md-5 mb-4">
                        <div class="text-danger mb-3">
                            <i class="fas fa-exclamation-triangle fa-3x" style="color: #ef4444;"></i>
                        </div>
                        <h3 class="fw-bold text-white mb-2">Document Record Not Found</h3>
                        <p class="text-secondary mb-4 mx-auto" style="max-width: 520px; font-size: 0.92rem;">
                            No official medical document matching Verification ID <strong class="text-warning font-monospace"><?php echo htmlspecialchars($vid); ?></strong> was found in MedicalIAk verified document registry.
                        </p>
                        <div class="alert alert-warning border-0 rounded-3 text-start mx-auto mb-0" style="max-width: 580px; background: rgba(245, 158, 11, 0.12); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3) !important;">
                            <i class="fas fa-info-circle me-1"></i> <strong>Notice for Verifiers:</strong> If you received a paper or digital copy claiming to be issued by MedicalIAk, please check the ID for typos or test the file integrity using our SHA-256 verification tool below.
                        </div>
                    </div>
                <?php elseif ($initial_doc): ?>
                    <?php 
                        $status = $initial_doc['status'];
                        $badge_class = 'bg-success';
                        $status_icon = 'fa-check-circle';
                        $status_title = 'OFFICIALLY VERIFIED & VALID';
                        
                        if ($status === 'REVOKED') {
                            $badge_class = 'bg-danger';
                            $status_icon = 'fa-ban';
                            $status_title = 'DOCUMENT REVOKED BY ISSUER';
                        } elseif ($status === 'EXPIRED' || ($initial_doc['expires_at'] && strtotime($initial_doc['expires_at']) < time())) {
                            $badge_class = 'bg-warning text-dark';
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

                    <div class="card ver-glass-card mb-4 overflow-hidden">
                        <div class="ver-glass-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <span class="badge <?php echo $badge_class; ?> px-3 py-2 rounded-pill fs-6 fw-bold">
                                    <i class="fas <?php echo $status_icon; ?> me-1"></i> <?php echo $status_title; ?>
                                </span>
                            </div>
                            <div class="text-secondary small">
                                Verification ID: <strong class="text-info font-monospace fs-6"><?php echo htmlspecialchars($initial_doc['verification_id']); ?></strong>
                            </div>
                        </div>

                        <div class="card-body p-4 p-md-5">
                            <?php if ($status === 'REVOKED'): ?>
                                <div class="alert border-0 rounded-4 p-4 mb-4" style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4) !important; color: #fca5a5;">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-exclamation-circle fa-2x text-danger me-3 mt-1"></i>
                                        <div>
                                            <h5 class="fw-bold mb-1 text-white">Revocation Notice</h5>
                                            <p class="mb-1 text-light text-opacity-90">This medical document was formally revoked by the issuing authority and is no longer valid for official use.</p>
                                            <div class="mt-2 text-white">
                                                <strong>Reason:</strong> <?php echo htmlspecialchars($initial_doc['revocation_reason'] ?? 'Not specified'); ?><br>
                                                <strong>Revoked Date:</strong> <?php echo $initial_doc['revoked_at'] ? date('M d, Y - h:i A', strtotime($initial_doc['revoked_at'])) : 'N/A'; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="row g-3 g-md-4">
                                <div class="col-md-6">
                                    <div class="ver-info-box">
                                        <div class="text-secondary small text-uppercase fw-semibold mb-1">Document Type</div>
                                        <div class="h5 fw-bold text-white mb-0">
                                            <i class="fas fa-file-medical me-2" style="color: #10b981;"></i>
                                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $initial_doc['doc_type']))); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="ver-info-box">
                                        <div class="text-secondary small text-uppercase fw-semibold mb-1">Issued Date</div>
                                        <div class="h5 fw-bold text-white mb-0">
                                            <i class="fas fa-calendar-check me-2" style="color: #34d399;"></i>
                                            <?php echo $initial_doc['issued_at'] ? date('M d, Y', strtotime($initial_doc['issued_at'])) : 'N/A'; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="ver-info-box">
                                        <div class="text-secondary small text-uppercase fw-semibold mb-1">Authorized Issuer</div>
                                        <div class="fw-bold text-white fs-6 mb-1">
                                            <i class="fas fa-user-md me-2" style="color: #38bdf8;"></i>
                                            <?php echo htmlspecialchars($initial_doc['issuer_name'] ? 'Dr. ' . str_replace('Dr. ', '', $initial_doc['issuer_name']) : 'Authorized Medical Specialist'); ?>
                                        </div>
                                        <div class="small text-secondary">
                                            <?php echo htmlspecialchars(ucwords($initial_doc['issuer_specialization'] ?? $initial_doc['issuer_role'] ?? 'Verified Doctor')); ?>
                                            <span class="badge bg-success bg-opacity-25 text-success ms-1 border border-success border-opacity-25"><i class="fas fa-check-circle"></i> Verified</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="ver-info-box">
                                        <div class="text-secondary small text-uppercase fw-semibold mb-1">Patient Holder (Masked PII)</div>
                                        <div class="fw-bold text-white fs-6 mb-1">
                                            <i class="fas fa-user-shield me-2" style="color: #a78bfa;"></i>
                                            <?php echo htmlspecialchars($masked_name); ?>
                                        </div>
                                        <?php if (!empty($initial_doc['patient_health_id'])): ?>
                                            <div class="small text-secondary font-monospace">
                                                Health ID: <?php echo htmlspecialchars($initial_doc['patient_health_id']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- SHA-256 Digital Signature Hash -->
                            <div class="mt-4 ver-hash-box">
                                <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                    <span class="small fw-bold text-uppercase" style="color: #34d399;">
                                        <i class="fas fa-fingerprint me-1"></i> SHA-256 Cryptographic Hash Signature
                                    </span>
                                    <span class="badge bg-secondary bg-opacity-50 text-light border border-secondary border-opacity-50">Tamper-Evident</span>
                                </div>
                                <div class="font-monospace text-break small text-light bg-black p-3 rounded-3 border border-secondary border-opacity-40">
                                    <?php echo htmlspecialchars($initial_doc['doc_hash']); ?>
                                </div>
                                <div class="small text-secondary mt-2">
                                    This SHA-256 digital signature guarantees that the document contents have not been altered or forged after issuance.
                                </div>
                            </div>

                            <div class="mt-4 d-flex align-items-center justify-content-between flex-wrap gap-2 border-top border-secondary border-opacity-25 pt-3">
                                <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#reportModal">
                                    <i class="fas fa-flag me-1"></i> Report Suspicious Document
                                </button>
                                <a href="verify_document.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 text-light">
                                    <i class="fas fa-redo me-1"></i> Verify Another Document
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SHA-256 File Integrity Tool -->
            <div class="card ver-glass-card mb-4 overflow-hidden">
                <div class="ver-glass-header">
                    <h5 class="fw-bold text-white mb-1">
                        <i class="fas fa-file-contract me-2" style="color: #10b981;"></i> Upload File Integrity Check
                    </h5>
                    <p class="text-secondary small mb-0">
                        Upload any MedicalIAk PDF or image report to verify whether its content matches the tamper-evident hash stored in our server.
                    </p>
                </div>
                <div class="card-body p-4">
                    <form id="fileIntegrityForm" enctype="multipart/form-data">
                        <div class="ver-file-upload-box mb-3">
                            <label for="docFile" class="form-label small fw-semibold text-light mb-2 d-block">
                                <i class="fas fa-cloud-upload-alt me-1" style="color: #10b981;"></i> Select PDF or Image Document (.pdf, .png, .jpg, .jpeg)
                            </label>
                            <input class="form-control text-light border-0" type="file" id="docFile" name="doc_file" accept=".pdf,.png,.jpg,.jpeg" required style="background: rgba(15, 23, 42, 0.8);">
                        </div>
                        <button type="submit" class="btn ver-btn-primary w-100 py-25 fw-bold">
                            <i class="fas fa-shield-alt me-1"></i> Run SHA-256 Integrity Check
                        </button>
                    </form>

                    <!-- Integrity Result Box -->
                    <div id="integrityResult" class="mt-4" style="display: none;"></div>
                </div>
            </div>

            <!-- Trust FAQ / System Details -->
            <div class="card ver-glass-card p-4">
                <h5 class="fw-bold text-white mb-4">
                    <i class="fas fa-info-circle me-2" style="color: #38bdf8;"></i> How MedicalIAk Authenticity Verification Works
                </h5>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="d-flex align-items-start">
                            <div class="p-3 rounded-3 me-3 flex-shrink-0" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8;">
                                <i class="fas fa-key fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-white mb-1">Unique Verification ID</h6>
                                <p class="small text-secondary mb-0">Every prescription and certified record gets a unique cryptographically generated Verification ID and QR token.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-start">
                            <div class="p-3 rounded-3 me-3 flex-shrink-0" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
                                <i class="fas fa-shield-alt fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-white mb-1">SHA-256 Hashing</h6>
                                <p class="small text-secondary mb-0">A 256-bit digital checksum is calculated upon issuance. Any modification to the document alters the hash and fails verification.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-start">
                            <div class="p-3 rounded-3 me-3 flex-shrink-0" style="background: rgba(239, 68, 68, 0.15); color: #fca5a5;">
                                <i class="fas fa-ban fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-white mb-1">Instant Revocation Check</h6>
                                <p class="small text-secondary mb-0">If a doctor or facility revokes an issued document, its status is updated instantly in real time across the portal.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<!-- Report Suspicious Document Modal -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="background: var(--darker-bg, #121826); border: 1px solid var(--glass-border, rgba(255,255,255,0.15)) !important; border-radius: 18px; color: #ffffff;">
            <div class="modal-header border-0 rounded-top-4" style="background: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%);">
                <h5 class="modal-title fw-bold text-white" id="reportModalLabel">
                    <i class="fas fa-flag me-2"></i> Report Suspicious Document
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="reportDocForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="report_suspicious">
                    <input type="hidden" name="verification_id" value="<?php echo htmlspecialchars($vid); ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Verification ID</label>
                        <input type="text" class="form-control font-monospace text-info border-secondary border-opacity-25" value="<?php echo htmlspecialchars($vid ? $vid : 'Unregistered / General'); ?>" readonly style="background: rgba(255,255,255,0.05);">
                    </div>

                    <div class="mb-3">
                        <label for="reporterEmail" class="form-label small fw-semibold text-secondary">Your Contact Email (Optional)</label>
                        <input type="email" class="form-control text-white border-secondary border-opacity-25" id="reporterEmail" name="reporter_email" placeholder="email@example.com" style="background: rgba(255,255,255,0.05);">
                    </div>

                    <div class="mb-3">
                        <label for="reportReason" class="form-label small fw-semibold text-secondary">Reason for Report *</label>
                        <select class="form-select text-white border-secondary border-opacity-25" id="reportReason" name="reason" required style="background: #1e293b;">
                            <option value="">-- Select Reason --</option>
                            <option value="Suspected Alteration or Forgery">Suspected Alteration or Forgery</option>
                            <option value="Unauthorized Doctor/Practitioner Name">Unauthorized Doctor/Practitioner Name</option>
                            <option value="Expired or Reused Prescription">Expired or Reused Prescription</option>
                            <option value="Incorrect Patient Information">Incorrect Patient Information</option>
                            <option value="Other Security Concern">Other Security Concern</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="reportDetails" class="form-label small fw-semibold text-secondary">Additional Details</label>
                        <textarea class="form-control text-white border-secondary border-opacity-25" id="reportDetails" name="details" rows="3" placeholder="Provide any additional context or observations..." style="background: rgba(255,255,255,0.05);"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 rounded-bottom-4" style="background: rgba(255,255,255,0.02);">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 text-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border: none;">
                        <i class="fas fa-paper-plane me-1"></i> Submit Report
                    </button>
                </div>
            </form>
        </div>
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
                <div class="p-3 ver-glass-card text-center text-secondary">
                    <div class="spinner-border spinner-border-sm text-emerald me-2" role="status" style="color: #10b981;"></div>
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
                        <div class="alert alert-danger rounded-3 mb-0" style="background: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4);">
                            <i class="fas fa-exclamation-triangle me-1"></i> ${data.message}
                        </div>
                    `;
                    return;
                }

                if (data.match_found && data.is_authentic) {
                    integrityResult.innerHTML = `
                        <div class="alert border-0 rounded-4 p-4 mb-0 shadow-sm" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4) !important;">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-check-circle fa-2x text-success me-3 mt-1" style="color: #34d399 !important;"></i>
                                <div>
                                    <h5 class="fw-bold text-success mb-1" style="color: #34d399 !important;">Authentic File Signature Match!</h5>
                                    <p class="mb-2 text-light">The uploaded document's digital SHA-256 signature matches the official record registered in MedicalIAk system.</p>
                                    <div class="small font-monospace p-2 rounded border text-info mb-2" style="background: #090d16; border-color: rgba(16, 185, 129, 0.3) !important;">
                                        <strong>SHA-256:</strong> ${data.uploaded_hash}
                                    </div>
                                    <div class="small text-secondary">
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
                        <div class="alert border-0 rounded-4 p-4 mb-0 shadow-sm" style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.4) !important;">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-exclamation-triangle fa-2x text-warning me-3 mt-1"></i>
                                <div>
                                    <h5 class="fw-bold text-warning mb-1">Hash Match Found, But Document Status: ${data.status}</h5>
                                    <p class="mb-2 text-light">This file matches a registered document hash, but the document status is currently marked as <strong>${data.status}</strong>.</p>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    integrityResult.innerHTML = `
                        <div class="alert border-0 rounded-4 p-4 mb-0 shadow-sm" style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4) !important;">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-times-circle fa-2x text-danger me-3 mt-1"></i>
                                <div>
                                    <h5 class="fw-bold text-danger mb-1" style="color: #fca5a5 !important;">Warning: Unverified or Altered Document</h5>
                                    <p class="mb-2 text-light">The SHA-256 digital signature of this file does not match any registered official MedicalIAk document.</p>
                                    <div class="small font-monospace p-2 rounded border text-danger mb-2" style="background: #090d16; border-color: rgba(239, 68, 68, 0.3) !important;">
                                        <strong>Calculated SHA-256:</strong> ${data.uploaded_hash}
                                    </div>
                                    <p class="small text-secondary mb-0">Possible reasons: The file has been modified after issuance, converted incorrectly, or was not issued through MedicalIAk.</p>
                                </div>
                            </div>
                        </div>
                    `;
                }
            })
            .catch(err => {
                integrityResult.innerHTML = `
                    <div class="alert alert-danger rounded-3 mb-0" style="background: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4);">
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
                    const modalEl = document.getElementById('reportModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                    reportDocForm.reset();
                }
            })
            .catch(err => alert('Failed to submit report. Please try again.'));
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
