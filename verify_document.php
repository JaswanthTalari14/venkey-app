<?php
require_once 'config.php';
$vid = trim($_GET['vid'] ?? $_GET['qr'] ?? $_GET['id'] ?? '');

$initial_doc = null;
if (!empty($vid)) {
    $initial_doc = get_document_verification_by_id($conn, $vid);
}

include 'includes/header.php';
?>

<main class="container my-4 my-md-5">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            
            <!-- Hero / Title Banner -->
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-4 bg-gradient-dark text-white" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0284c7 100%);">
                <div class="card-body p-4 p-md-5 text-center position-relative">
                    <div class="badge bg-info bg-opacity-25 text-info px-3 py-2 rounded-pill mb-3 fw-semibold border border-info border-opacity-25">
                        <i class="fas fa-shield-alt me-1"></i> MedicalIAk Trust & Authenticity Registry
                    </div>
                    <h1 class="display-6 fw-bold mb-2">Medical Document Authenticity Verification</h1>
                    <p class="lead text-light text-opacity-75 mb-4 mx-auto" style="max-width: 650px;">
                        Verify official prescriptions, lab reports, medical certificates, and health credentials issued through the MedicalIAk Healthcare Portal.
                    </p>

                    <!-- Search Form -->
                    <form id="verifyForm" action="verify_document.php" method="GET" class="mx-auto" style="max-width: 620px;">
                        <div class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden bg-white p-1">
                            <span class="input-group-text bg-transparent border-0 text-muted ps-3">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" name="vid" id="verificationIdInput" class="form-control border-0 text-dark fw-bold fs-6" 
                                   placeholder="Enter Verification ID (e.g. DOC-VER-A1B2-C3D4)" 
                                   value="<?php echo htmlspecialchars($vid); ?>" required style="box-shadow: none;">
                            <button type="submit" class="btn btn-primary px-4 fw-bold rounded-3">
                                <i class="fas fa-check-circle me-1"></i> Verify
                            </button>
                        </div>
                        <div class="form-text text-light text-opacity-75 mt-2">
                            <i class="fas fa-qrcode me-1"></i> Or scan the QR code printed on the official MedicalIAk document
                        </div>
                    </form>
                </div>
            </div>

            <!-- Verification Output Container -->
            <div id="resultContainer">
                <?php if (!empty($vid) && !$initial_doc): ?>
                    <div class="card border-0 shadow-sm rounded-4 text-center p-5 mb-4 bg-white">
                        <div class="text-danger mb-3">
                            <i class="fas fa-exclamation-triangle fa-4x"></i>
                        </div>
                        <h3 class="fw-bold text-dark mb-2">Document Record Not Found</h3>
                        <p class="text-muted mb-4 mx-auto" style="max-width: 500px;">
                            No official medical document with Verification ID <strong><?php echo htmlspecialchars($vid); ?></strong> was found in MedicalIAk verified document registry.
                        </p>
                        <div class="alert alert-warning border-0 rounded-3 text-start mx-auto" style="max-width: 550px;">
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

                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
                        <div class="card-header bg-transparent border-bottom p-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <span class="badge <?php echo $badge_class; ?> px-3 py-2 rounded-pill fs-6 fw-bold">
                                    <i class="fas <?php echo $status_icon; ?> me-1"></i> <?php echo $status_title; ?>
                                </span>
                            </div>
                            <div class="text-muted small">
                                Verification ID: <strong class="text-dark font-monospace"><?php echo htmlspecialchars($initial_doc['verification_id']); ?></strong>
                            </div>
                        </div>

                        <div class="card-body p-4 p-md-5">
                            <?php if ($status === 'REVOKED'): ?>
                                <div class="alert alert-danger border-0 rounded-4 p-4 mb-4">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-exclamation-circle fa-2x me-3 mt-1"></i>
                                        <div>
                                            <h5 class="fw-bold mb-1">Revocation Notice</h5>
                                            <p class="mb-1">This medical document was formally revoked by the issuing authority and is no longer valid for official use.</p>
                                            <div class="mt-2 text-dark">
                                                <strong>Reason:</strong> <?php echo htmlspecialchars($initial_doc['revocation_reason'] ?? 'Not specified'); ?><br>
                                                <strong>Revoked Date:</strong> <?php echo $initial_doc['revoked_at'] ? date('M d, Y - h:i A', strtotime($initial_doc['revoked_at'])) : 'N/A'; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <div class="text-muted small text-uppercase fw-semibold mb-1">Document Type</div>
                                        <div class="h5 fw-bold text-dark mb-0">
                                            <i class="fas fa-file-medical text-primary me-2"></i>
                                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $initial_doc['doc_type']))); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <div class="text-muted small text-uppercase fw-semibold mb-1">Issued Date</div>
                                        <div class="h5 fw-bold text-dark mb-0">
                                            <i class="fas fa-calendar-check text-success me-2"></i>
                                            <?php echo $initial_doc['issued_at'] ? date('M d, Y', strtotime($initial_doc['issued_at'])) : 'N/A'; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <div class="text-muted small text-uppercase fw-semibold mb-1">Authorized Issuer</div>
                                        <div class="fw-bold text-dark fs-6 mb-1">
                                            <i class="fas fa-user-md text-info me-2"></i>
                                            <?php echo htmlspecialchars($initial_doc['issuer_name'] ? 'Dr. ' . str_replace('Dr. ', '', $initial_doc['issuer_name']) : 'Authorized Medical Specialist'); ?>
                                        </div>
                                        <div class="small text-muted">
                                            <?php echo htmlspecialchars(ucwords($initial_doc['issuer_specialization'] ?? $initial_doc['issuer_role'] ?? 'Verified Doctor')); ?>
                                            <span class="badge bg-success bg-opacity-10 text-success ms-1"><i class="fas fa-check-circle"></i> Verified</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <div class="text-muted small text-uppercase fw-semibold mb-1">Patient Holder (Masked)</div>
                                        <div class="fw-bold text-dark fs-6 mb-1">
                                            <i class="fas fa-user-shield text-secondary me-2"></i>
                                            <?php echo htmlspecialchars($masked_name); ?>
                                        </div>
                                        <?php if (!empty($initial_doc['patient_health_id'])): ?>
                                            <div class="small text-muted font-monospace">
                                                Health ID: <?php echo htmlspecialchars($initial_doc['patient_health_id']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- SHA-256 Digital Signature Hash -->
                            <div class="mt-4 p-3 bg-dark text-white rounded-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="small fw-bold text-uppercase text-info">
                                        <i class="fas fa-fingerprint me-1"></i> SHA-256 Cryptographic Hash Signature
                                    </span>
                                    <span class="badge bg-secondary">Tamper-Evident</span>
                                </div>
                                <div class="font-monospace text-break small text-light bg-black p-2 rounded border border-secondary">
                                    <?php echo htmlspecialchars($initial_doc['doc_hash']); ?>
                                </div>
                                <div class="small text-muted mt-2">
                                    This SHA-256 signature guarantees that the original document content has not been altered or forged.
                                </div>
                            </div>

                            <div class="mt-4 d-flex align-items-center justify-content-between flex-wrap gap-2 border-top pt-3">
                                <button type="button" class="btn btn-outline-danger btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#reportModal">
                                    <i class="fas fa-flag me-1"></i> Report Suspicious Document
                                </button>
                                <a href="verify_document.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                                    <i class="fas fa-redo me-1"></i> Verify Another Document
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SHA-256 File Integrity Tool -->
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
                <div class="card-header bg-light border-bottom p-4">
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="fas fa-file-contract text-primary me-2"></i> Upload File Integrity Check
                    </h5>
                    <p class="text-muted small mb-0">
                        Upload any MedicalIAk PDF or image report to verify whether its content matches the tamper-evident hash stored in our server.
                    </p>
                </div>
                <div class="card-body p-4">
                    <form id="fileIntegrityForm" enctype="multipart/form-data">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-7">
                                <label for="docFile" class="form-label small fw-semibold text-muted">Select PDF or Image Document</label>
                                <input class="form-control" type="file" id="docFile" name="doc_file" accept=".pdf,.png,.jpg,.jpeg" required>
                            </div>
                            <div class="col-md-5 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100 fw-semibold rounded-3 py-2">
                                    <i class="fas fa-shield-alt me-1"></i> Run SHA-256 Integrity Check
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Integrity Result Box -->
                    <div id="integrityResult" class="mt-4" style="display: none;"></div>
                </div>
            </div>

            <!-- Trust FAQ / System Details -->
            <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
                <h5 class="fw-bold text-dark mb-3">
                    <i class="fas fa-info-circle text-info me-2"></i> How MedicalIAk Authenticity Verification Works
                </h5>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="d-flex align-items-start">
                            <div class="badge bg-primary bg-opacity-10 text-primary p-3 rounded-3 me-3">
                                <i class="fas fa-key fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1">Unique Verification ID</h6>
                                <p class="small text-muted mb-0">Every prescription and certified record gets a unique cryptographically generated Verification ID and QR token.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-start">
                            <div class="badge bg-success bg-opacity-10 text-success p-3 rounded-3 me-3">
                                <i class="fas fa-shield-alt fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1">SHA-256 Hashing</h6>
                                <p class="small text-muted mb-0">A 256-bit digital checksum is calculated upon issuance. Any modification to the document alters the hash and fails verification.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-start">
                            <div class="badge bg-danger bg-opacity-10 text-danger p-3 rounded-3 me-3">
                                <i class="fas fa-ban fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1">Instant Revocation Check</h6>
                                <p class="small text-muted mb-0">If a doctor or facility revokes an issued document, its status is updated instantly in real time across the portal.</p>
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
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-danger text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="reportModalLabel">
                    <i class="fas fa-flag me-2"></i> Report Suspicious Document
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="reportDocForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="report_suspicious">
                    <input type="hidden" name="verification_id" value="<?php echo htmlspecialchars($vid); ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Verification ID</label>
                        <input type="text" class="form-control font-monospace bg-light" value="<?php echo htmlspecialchars($vid ? $vid : 'Unregistered / General'); ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="reporterEmail" class="form-label fw-semibold">Your Contact Email (Optional)</label>
                        <input type="email" class="form-control" id="reporterEmail" name="reporter_email" placeholder="email@example.com">
                    </div>

                    <div class="mb-3">
                        <label for="reportReason" class="form-label fw-semibold">Reason for Report *</label>
                        <select class="form-select" id="reportReason" name="reason" required>
                            <option value="">-- Select Reason --</option>
                            <option value="Suspected Alteration or Forgery">Suspected Alteration or Forgery</option>
                            <option value="Unauthorized Doctor/Practitioner Name">Unauthorized Doctor/Practitioner Name</option>
                            <option value="Expired or Reused Prescription">Expired or Reused Prescription</option>
                            <option value="Incorrect Patient Information">Incorrect Patient Information</option>
                            <option value="Other Security Concern">Other Security Concern</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="reportDetails" class="form-label fw-semibold">Additional Details</label>
                        <textarea class="form-control" id="reportDetails" name="details" rows="3" placeholder="Provide any additional context or observations..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">
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
                <div class="p-3 bg-light rounded-3 text-center text-muted">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
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
                        <div class="alert alert-danger rounded-3 mb-0">
                            <i class="fas fa-exclamation-triangle me-1"></i> ${data.message}
                        </div>
                    `;
                    return;
                }

                if (data.match_found && data.is_authentic) {
                    integrityResult.innerHTML = `
                        <div class="alert alert-success border-0 rounded-4 p-4 mb-0 shadow-sm">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-check-circle fa-2x text-success me-3 mt-1"></i>
                                <div>
                                    <h5 class="fw-bold text-success mb-1">Authentic File Signature Match!</h5>
                                    <p class="mb-2 text-dark">The uploaded document's digital SHA-256 signature matches the official record registered in MedicalIAk system.</p>
                                    <div class="small font-monospace bg-white p-2 rounded border text-dark mb-2">
                                        <strong>SHA-256:</strong> ${data.uploaded_hash}
                                    </div>
                                    <div class="small text-muted">
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
                        <div class="alert alert-warning border-0 rounded-4 p-4 mb-0 shadow-sm">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-exclamation-triangle fa-2x text-warning me-3 mt-1"></i>
                                <div>
                                    <h5 class="fw-bold text-dark mb-1">Hash Match Found, But Document Status: ${data.status}</h5>
                                    <p class="mb-2 text-dark">This file matches a registered document hash, but the document status is currently marked as <strong>${data.status}</strong>.</p>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    integrityResult.innerHTML = `
                        <div class="alert alert-danger border-0 rounded-4 p-4 mb-0 shadow-sm">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-times-circle fa-2x text-danger me-3 mt-1"></i>
                                <div>
                                    <h5 class="fw-bold text-danger mb-1">Warning: Unverified or Altered Document</h5>
                                    <p class="mb-2 text-dark">The SHA-256 digital signature of this file does not match any registered official MedicalIAk document.</p>
                                    <div class="small font-monospace bg-white p-2 rounded border text-danger mb-2">
                                        <strong>Calculated SHA-256:</strong> ${data.uploaded_hash}
                                    </div>
                                    <p class="small text-muted mb-0">Possible reasons: The file has been modified after issuance, converted incorrectly, or was not issued through MedicalIAk.</p>
                                </div>
                            </div>
                        </div>
                    `;
                }
            })
            .catch(err => {
                integrityResult.innerHTML = `
                    <div class="alert alert-danger rounded-3 mb-0">
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
