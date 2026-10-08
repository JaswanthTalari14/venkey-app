<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_role = $_SESSION['role'] ?? 'patient';

// Admin is strictly denied access as per system security and prompt requirements
if ($user_role === 'admin') {
    header("Location: admin_dashboard.php");
    exit;
}

include 'includes/header.php';

// Prepare role meta details
$role_title = 'Patient Guide';
$role_icon = 'fa-user';
$role_badge = '👤 Patient';

if ($user_role === 'doctor') {
    $role_title = 'Doctor Guide';
    $role_icon = 'fa-user-md';
    $role_badge = '👨‍⚕️ Doctor';
} elseif ($user_role === 'rmp') {
    $role_title = 'RMP Guide';
    $role_icon = 'fa-stethoscope';
    $role_badge = '🩺 RMP';
}
?>

<style>
/* Guideline Page Specific Styles */
.guideline-container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 1.5rem 1rem;
}

.guideline-hero {
    background: linear-gradient(135deg, rgba(5, 150, 105, 0.15) 0%, rgba(16, 185, 129, 0.25) 50%, rgba(4, 120, 87, 0.15) 100%);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 20px;
    padding: 2.2rem 2rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.guideline-role-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: linear-gradient(135deg, #059669, #10B981);
    color: #ffffff;
    font-weight: 700;
    font-size: 0.88rem;
    padding: 0.4rem 1rem;
    border-radius: 50px;
    margin-bottom: 1rem;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.guideline-search-box {
    position: relative;
    margin-top: 1.5rem;
    max-width: 700px;
}

.guideline-search-box input {
    width: 100%;
    padding: 1rem 1.2rem 1rem 3.2rem;
    border-radius: 14px;
    border: 2px solid rgba(16, 185, 129, 0.35);
    background: var(--card-bg, rgba(18, 24, 38, 0.95));
    color: var(--text-primary, #ffffff);
    font-size: 1.05rem;
    outline: none;
    transition: all 0.3s ease;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}

.guideline-search-box input:focus {
    border-color: #10B981;
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.25);
}

.guideline-search-box i {
    position: absolute;
    left: 1.2rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1.2rem;
    color: #10B981;
}

.category-pills {
    display: flex;
    gap: 0.6rem;
    flex-wrap: wrap;
    margin-bottom: 2rem;
    padding-bottom: 0.5rem;
}

.category-pill {
    background: var(--card-bg, rgba(255, 255, 255, 0.05));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.15));
    color: var(--text-primary, #ffffff);
    padding: 0.55rem 1.1rem;
    border-radius: 30px;
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    text-decoration: none;
}

.category-pill:hover, .category-pill.active {
    background: #10B981;
    color: #ffffff;
    border-color: #10B981;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.guide-card {
    background: var(--card-bg, rgba(18, 24, 38, 0.85));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.12));
    border-radius: 16px;
    margin-bottom: 1.25rem;
    overflow: hidden;
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.guide-card:hover {
    border-color: rgba(16, 185, 129, 0.4);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
}

.guide-card-header {
    padding: 1.25rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    user-select: none;
    background: rgba(255, 255, 255, 0.02);
}

.guide-card-header h3 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text-primary, #ffffff);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.guide-card-header .toggle-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(16, 185, 129, 0.15);
    color: #10B981;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s ease;
}

.guide-card.expanded .toggle-icon {
    transform: rotate(180deg);
    background: #10B981;
    color: #ffffff;
}

.guide-card-body {
    display: none;
    padding: 1.5rem;
    border-top: 1px solid var(--glass-border, rgba(255, 255, 255, 0.08));
    background: rgba(0, 0, 0, 0.15);
}

.guide-card.expanded .guide-card-body {
    display: block;
}

.guide-section-block {
    margin-bottom: 1.25rem;
}

.guide-section-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #10B981;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.4rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.guide-section-text {
    font-size: 0.95rem;
    color: var(--text-secondary, #cbd5e1);
    line-height: 1.6;
    margin: 0;
}

.guide-steps-list {
    margin: 0.5rem 0 0 0;
    padding-left: 1.25rem;
    color: var(--text-primary, #f8fafc);
}

.guide-steps-list li {
    margin-bottom: 0.5rem;
    line-height: 1.5;
    font-size: 0.95rem;
}

.guide-steps-list li strong {
    color: #34d399;
}

.guide-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: linear-gradient(135deg, #059669, #10B981);
    color: #ffffff !important;
    font-weight: 700;
    font-size: 0.9rem;
    padding: 0.65rem 1.4rem;
    border-radius: 10px;
    text-decoration: none;
    margin-top: 1rem;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.guide-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.45);
}

.no-results-box {
    display: none;
    text-align: center;
    padding: 3rem 1.5rem;
    background: var(--card-bg, rgba(18, 24, 38, 0.6));
    border-radius: 16px;
    border: 1px dashed rgba(255, 255, 255, 0.2);
    margin: 2rem 0;
}

.faq-section {
    margin-top: 3.5rem;
    padding-top: 2rem;
    border-top: 1px solid var(--glass-border, rgba(255, 255, 255, 0.15));
}
</style>

<div class="guideline-container">
    <!-- Header Banner -->
    <div class="guideline-hero">
        <div class="guideline-role-badge">
            <?php echo $role_badge; ?>
        </div>
        <h1 style="margin: 0 0 0.5rem 0; font-size: 2.2rem; font-weight: 800; color: var(--text-primary, #ffffff);">
            📖 How to Use MedicalAk
        </h1>
        <p style="margin: 0; font-size: 1.1rem; color: var(--text-secondary, #cbd5e1); max-width: 750px; line-height: 1.6;">
            Welcome to MedicalAk 👋<br>
            This guide explains all features available to your role and shows you how to use MedicalAk step by step.
        </p>

        <!-- Search Guideline Input -->
        <div class="guideline-search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="guidelineSearchInput" placeholder="🔎 Search how to use MedicalAk (e.g. Order medicine, Wallet, Appointment, Medical Card)..." autocomplete="off">
        </div>
    </div>

    <!-- Category Filter Pills -->
    <div class="category-pills" id="categoryPills">
        <button class="category-pill active" data-category="all">
            <i class="fas fa-th-large"></i> All Categories
        </button>
        <?php if ($user_role === 'patient'): ?>
            <button class="category-pill" data-category="account">👤 Account</button>
            <button class="category-pill" data-category="card">💳 Medical Card</button>
            <button class="category-pill" data-category="vault">🔐 Health Vault</button>
            <button class="category-pill" data-category="consult">👨‍⚕️ Consultations</button>
            <button class="category-pill" data-category="nearby">📍 Nearby Doctors</button>
            <button class="category-pill" data-category="privacy">🕵️ Privacy Consult</button>
            <button class="category-pill" data-category="medicines">💊 Medicines</button>
            <button class="category-pill" data-category="orders">📦 Orders</button>
            <button class="category-pill" data-category="tests">🧪 Lab Tests</button>
            <button class="category-pill" data-category="queue">📊 Queue Tracker</button>
            <button class="category-pill" data-category="wallet">💰 Wallet</button>
            <button class="category-pill" data-category="referral">🎁 Refer & Earn</button>
            <button class="category-pill" data-category="support">💬 Support</button>
            <button class="category-pill" data-category="ai">🤖 AI Chatbot</button>
            <button class="category-pill" data-category="pwa">📱 PWA App</button>
        <?php elseif ($user_role === 'doctor'): ?>
            <button class="category-pill" data-category="profile">👨‍⚕️ Profile & Fees</button>
            <button class="category-pill" data-category="workspace">📋 Consultation Desk</button>
            <button class="category-pill" data-category="appointments">📅 Appointments</button>
            <button class="category-pill" data-category="referrals">🔄 Referrals</button>
            <button class="category-pill" data-category="medicines">💊 Manage Medicines</button>
            <button class="category-pill" data-category="orders">📦 Medicine Orders</button>
            <button class="category-pill" data-category="queries">🕵️ Privacy Queries</button>
            <button class="category-pill" data-category="vault">📄 Prescriptions</button>
            <button class="category-pill" data-category="payments">💳 Earnings</button>
        <?php elseif ($user_role === 'rmp'): ?>
            <button class="category-pill" data-category="profile">🩺 RMP Profile</button>
            <button class="category-pill" data-category="tests">🧪 Lab Tests</button>
            <button class="category-pill" data-category="upload">📤 Upload Results</button>
            <button class="category-pill" data-category="referrals">👨‍⚕️ Doctor Referrals</button>
            <button class="category-pill" data-category="followups">📅 Follow-up Reminders</button>
            <button class="category-pill" data-category="payments">💰 Commission Earnings</button>
        <?php endif; ?>
    </div>

    <!-- Guideline Cards List -->
    <div id="guidelineCardsList">

    <?php if ($user_role === 'patient'): ?>

        <!-- PATIENT FEATURE 1: ACCOUNT PROFILE -->
        <div class="guide-card expanded" data-category="account" data-keywords="account profile name email phone password settings security update">
            <div class="guide-card-header">
                <h3><i class="fas fa-user-circle" style="color: #10B981;"></i> 👤 How to Manage Account & Profile</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">This feature allows you to update your personal details, contact number, registered email, residential address, and account password.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Profile</strong> from the top navigation or sidebar menu.</li>
                        <li><strong>Step 2:</strong> View your current account details (Full Name, Email, Phone, Address).</li>
                        <li><strong>Step 3:</strong> Modify any fields you want to update.</li>
                        <li><strong>Step 4:</strong> To change password, type your current password and set a new password.</li>
                        <li><strong>Step 5:</strong> Click <strong>Save Changes</strong>.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Your profile information updates instantly across MedicalAk. Order delivery receipts and consultation notifications will use your updated details.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Keep your mobile phone number active to receive SMS and WhatsApp consultation reminders.</p>
                </div>
                <a href="profile.php" class="guide-action-btn">➡️ Open Profile Settings</a>
            </div>
        </div>

        <!-- PATIENT FEATURE 2: DIGITAL MEDICAL CARD -->
        <div class="guide-card" data-category="card" data-keywords="digital medical card qr code blood group emergency id download card print">
            <div class="guide-card-header">
                <h3><i class="fas fa-id-card" style="color: #10B981;"></i> 🪪 How to Use Digital Medical Card</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Your Digital Medical Card contains a unique Medical ID, QR code, blood group, emergency contacts, and verified health profile for emergency doctors.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Digital Medical Card</strong> from your sidebar menu.</li>
                        <li><strong>Step 2:</strong> View your assigned Medical Card ID and QR code.</li>
                        <li><strong>Step 3:</strong> Tap <strong>View QR Code</strong> for scan verification during clinic visits.</li>
                        <li><strong>Step 4:</strong> Click <strong>Download Medical Card</strong> to save a digital copy or print for offline emergency use.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Doctors and emergency medical practitioners can scan your card QR code to retrieve critical medical history instantly.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">New digital medical cards undergo verification by the MedicalAk Admin team before displaying fully approved status.</p>
                </div>
                <a href="digital_medical_card.php" class="guide-action-btn">➡️ Open Digital Medical Card</a>
            </div>
        </div>

        <!-- PATIENT FEATURE 3: HEALTH VAULT -->
        <div class="guide-card" data-category="vault" data-keywords="health vault medical records upload prescription lab report pdf image documents">
            <div class="guide-card-header">
                <h3><i class="fas fa-vault" style="color: #10B981;"></i> 🔐 How to Use Health Vault & Medical Records</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">A secure digital storage repository to upload, organize, and access all your past medical prescriptions, lab reports, and doctor certificates.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Health Vault</strong> from your sidebar.</li>
                        <li><strong>Step 2:</strong> Click <strong>Upload Document</strong> button.</li>
                        <li><strong>Step 3:</strong> Select Category (Prescription, Lab Report, Consultation Note, or General Document).</li>
                        <li><strong>Step 4:</strong> Choose the PDF or image file from your device.</li>
                        <li><strong>Step 5:</strong> Click <strong>Upload to Vault</strong>.</li>
                        <li><strong>Step 6:</strong> View or download uploaded files anytime from your documents list.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Uploaded documents are safely attached to your medical history and can be shared with consulting doctors during appointments.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Maximum file size is 10MB. Supported formats include PDF, PNG, JPG, and JPEG.</p>
                </div>
                <a href="health_vault.php" class="guide-action-btn">➡️ Open Health Vault</a>
            </div>
        </div>

        <!-- PATIENT FEATURE 4: BOOK CONSULTATION -->
        <div class="guide-card" data-category="consult" data-keywords="book consultation doctor online offline video chat home visit slot doctor fee">
            <div class="guide-card-header">
                <h3><i class="fas fa-calendar-check" style="color: #10B981;"></i> 👨‍⚕️ How to Book Doctor Consultations</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Book online video/chat consultations or request door-to-door offline home visit appointments with verified specialist doctors.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Consultations</strong> (`book_consult.php`).</li>
                        <li><strong>Step 2:</strong> Choose consultation type: <strong>Online Consultation</strong> or <strong>Offline Home Visit</strong>.</li>
                        <li><strong>Step 3:</strong> Select doctor by specialty (General Physician, Cardiologist, Dermatologist, etc.).</li>
                        <li><strong>Step 4:</strong> Choose your preferred date and available time slot.</li>
                        <li><strong>Step 5:</strong> Enter symptoms or consultation reason.</li>
                        <li><strong>Step 6:</strong> Pay using Wallet Balance or Online Payment Gateway and confirm booking.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Your appointment is booked! You will receive a token number and confirmation notice. The doctor will meet you online or visit your home at the scheduled time.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Ensure you log in 5 minutes before your online appointment or keep your home address accurate for door-to-door visits.</p>
                </div>
                <a href="book_consult.php" class="guide-action-btn">➡️ Open Book Consultation</a>
            </div>
        </div>

        <!-- PATIENT FEATURE 5: FIND NEARBY DOCTORS (10KM) -->
        <div class="guide-card" data-category="nearby" data-keywords="nearby doctors 10km radius gps location find doctor distance fee appointment">
            <div class="guide-card-header">
                <h3><i class="fas fa-map-marker-alt" style="color: #10B981;"></i> 📍 How to Find Nearby Doctors (10km Radius)</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Locate doctors near you precisely calculated within a 10 kilometer radius using real-time GPS location tracking.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Find Doctors (10km)</strong>.</li>
                        <li><strong>Step 2:</strong> Allow browser location permissions when requested.</li>
                        <li><strong>Step 3:</strong> Browse doctors listed with precise distance in km, specialization, and consultation fee.</li>
                        <li><strong>Step 4:</strong> Click <strong>Book Consultation</strong> on any doctor card to schedule immediate care.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Connects you instantly with healthcare professionals closest to your current location.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">GPS must be enabled on your mobile device or browser for distance calculation.</p>
                </div>
                <a href="nearby_doctors.php" class="guide-action-btn">➡️ Open Find Nearby Doctors</a>
            </div>
        </div>

        <!-- PATIENT FEATURE 6: PRIVACY CONSULT -->
        <div class="guide-card" data-category="privacy" data-keywords="privacy consult anonymous photo upload sensitive health question doctor response secret">
            <div class="guide-card-header">
                <h3><i class="fas fa-user-secret" style="color: #10B981;"></i> 🕵️ How to Use Anonymous Privacy Consultation</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Ask sensitive personal health questions and upload condition photos completely anonymously without sharing your name or identity.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Privacy Consult</strong>.</li>
                        <li><strong>Step 2:</strong> Type your confidential health question into the query text box.</li>
                        <li><strong>Step 3:</strong> (Optional) Upload photos or documents related to your health condition.</li>
                        <li><strong>Step 4:</strong> Click <strong>Submit Anonymous Query</strong>.</li>
                        <li><strong>Step 5:</strong> Check back on the page to view confidential doctor answers.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Doctors review your query identified only by a randomized code and respond with expert medical advice.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Do not type your real name or mobile number inside the query text if you want complete privacy.</p>
                </div>
                <a href="privacy_consult.php" class="guide-action-btn">➡️ Open Privacy Consult</a>
            </div>
        </div>

        <!-- PATIENT FEATURE 7: ORDER MEDICINES -->
        <div class="guide-card" data-category="medicines" data-keywords="order medicine pharmacy cart dosage checkout delivery address prescription">
            <div class="guide-card-header">
                <h3><i class="fas fa-pills" style="color: #10B981;"></i> 💊 How to Order Medicines</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Search, browse, and buy prescribed medicines and health products delivered straight to your home.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Order Medicines</strong> (`medicines.php`).</li>
                        <li><strong>Step 2:</strong> Search for medicines by name or filter by health category.</li>
                        <li><strong>Step 3:</strong> Select required quantity and click <strong>Add to Cart</strong>.</li>
                        <li><strong>Step 4:</strong> Open your <strong>Cart</strong> and review items.</li>
                        <li><strong>Step 5:</strong> Enter delivery address and choose payment method (Wallet / Online Payment).</li>
                        <li><strong>Step 6:</strong> Click <strong>Place Order</strong>.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Your order status updates to <strong>Pending</strong>, then <strong>Shipped</strong>, and final <strong>Delivered</strong> status upon arrival.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Prescription drugs require uploading a doctor prescription during checkout or from Health Vault.</p>
                </div>
                <a href="medicines.php" class="guide-action-btn">➡️ Open Order Medicines</a>
            </div>
        </div>

        <!-- PATIENT FEATURE 8: YOUR ORDERS & TRACKING -->
        <div class="guide-card" data-category="orders" data-keywords="your orders track order status receipt invoice shipping delivery pending shipped">
            <div class="guide-card-header">
                <h3><i class="fas fa-boxes" style="color: #10B981;"></i> 📦 How to Track Orders & View Receipts</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">View active order status, monitor delivery progress, and download official digital receipts.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Your Orders</strong> (`your_orders.php`).</li>
                        <li><strong>Step 2:</strong> View order list with Order ID, Date, Amount, and live Status badge.</li>
                        <li><strong>Step 3:</strong> Click <strong>View Details</strong> to see purchased medicine list.</li>
                        <li><strong>Step 4:</strong> Click <strong>Digital Receipt</strong> to view or print the official tax receipt.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">You can track order dispatch and verify digital receipts anytime.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Orders can be cancelled only while status is <strong>Pending</strong>.</p>
                </div>
                <a href="your_orders.php" class="guide-action-btn">➡️ Open Your Orders</a>
            </div>
        </div>

        <!-- PATIENT FEATURE 9: BOOK LAB TESTS (RMP) -->
        <div class="guide-card" data-category="tests" data-keywords="book tests lab diagnostic checkups rmp sample collection test report">
            <div class="guide-card-header">
                <h3><i class="fas fa-vial" style="color: #10B981;"></i> 🧪 How to Book Lab & Diagnostic Tests</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Book diagnostic blood tests, health checkups, and lab sample collection directly with Registered Medical Practitioners (RMP).</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Book Labs (RMP)</strong> (`book_tests.php`).</li>
                        <li><strong>Step 2:</strong> Select test package (Blood Count, Diabetes, Lipid Profile, Thyroid, etc.).</li>
                        <li><strong>Step 3:</strong> Select RMP practitioner and sample collection date.</li>
                        <li><strong>Step 4:</strong> Complete payment and submit booking.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">The RMP collects samples as scheduled and uploads diagnostic PDF reports directly to your Health Vault.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Follow fasting guidelines listed under specific lab tests before sample collection.</p>
                </div>
                <a href="book_tests.php" class="guide-action-btn">➡️ Open Book Labs</a>
            </div>
        </div>

        <!-- PATIENT FEATURE 10: LIVE QUEUE TRACKER -->
        <div class="guide-card" data-category="queue" data-keywords="live queue tracker token current serving position wait time doctor appointment">
            <div class="guide-card-header">
                <h3><i class="fas fa-digital-tachograph" style="color: #10B981;"></i> 📊 How to Use Live Digital Queue Tracker</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Real-time token tracker that displays your exact queue position, doctor token serving status, and estimated waiting time.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Go to your <strong>Patient Dashboard</strong>.</li>
                        <li><strong>Step 2:</strong> Find the <strong>Live Digital Queue Tracker</strong> box.</li>
                        <li><strong>Step 3:</strong> Check your <strong>Patient Token</strong>, <strong>Current Serving Token</strong>, and <strong>Estimated Wait</strong>.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Updates live as the doctor completes preceding patient consultations.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Log in 10 minutes before your token arrives to prevent token skipping.</p>
                </div>
                <a href="patient_dashboard.php#live-queue-card" class="guide-action-btn">➡️ Open Queue Tracker</a>
            </div>
        </div>

        <!-- PATIENT FEATURE 11: WALLET -->
        <div class="guide-card" data-category="wallet" data-keywords="my wallet top up balance add money upi phonepe payment gateway 1click checkout">
            <div class="guide-card-header">
                <h3><i class="fas fa-wallet" style="color: #10B981;"></i> 💰 How to Use My Wallet & Top-Up Balance</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Built-in digital wallet balance for instant 1-click payment checkout across consultations, medicines, and lab tests.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>My Wallet</strong> (`my_wallet.php`).</li>
                        <li><strong>Step 2:</strong> Check your current wallet balance.</li>
                        <li><strong>Step 3:</strong> Enter amount to add (e.g. ₹500, ₹1000) and click <strong>Top Up Wallet</strong>.</li>
                        <li><strong>Step 4:</strong> Pay via UPI, PhonePe, or payment gateway.</li>
                        <li><strong>Step 5:</strong> View wallet transactions log below.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Wallet balance is credited immediately, enabling superfast 1-click checkout.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Refunds for cancelled orders are returned straight to your MedicalAk Wallet.</p>
                </div>
                <a href="my_wallet.php" class="guide-action-btn">➡️ Open My Wallet</a>
            </div>
        </div>

        <!-- PATIENT FEATURE 12: REFER & EARN -->
        <div class="guide-card" data-category="referral" data-keywords="refer and earn referral code link whatsapp invite share bonus cash wallet reward">
            <div class="guide-card-header">
                <h3><i class="fas fa-gift" style="color: #10B981;"></i> 🎁 How to Use Refer & Earn</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Invite friends to MedicalAk and earn cash rewards credited directly into your MedicalAk Wallet balance.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Refer & Earn</strong> (`refer_earn.php`).</li>
                        <li><strong>Step 2:</strong> Copy your unique referral code or tap <strong>Share on WhatsApp</strong>.</li>
                        <li><strong>Step 3:</strong> Send your referral link to friends and family.</li>
                        <li><strong>Step 4:</strong> When friends register and complete a service, your referral reward is unlocked!</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Bonus earnings are credited directly to your wallet balance.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Referral rewards apply only for genuine new account signups.</p>
                </div>
                <a href="refer_earn.php" class="guide-action-btn">➡️ Open Refer & Earn</a>
            </div>
        </div>

        <!-- PATIENT FEATURE 13: CUSTOMER SUPPORT & TICKETS -->
        <div class="guide-card" data-category="support" data-keywords="customer support whatsapp ticket create ticket my tickets help desk contact agent">
            <div class="guide-card-header">
                <h3><i class="fas fa-headset" style="color: #10B981;"></i> 💬 How to Use Customer Support & Tickets</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Get assistance via WhatsApp chat or raise formal tracked support tickets for payment, order, or technical help.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Customer Support</strong> (`customer_support.php`).</li>
                        <li><strong>Step 2:</strong> Tap <strong>Chat on WhatsApp</strong> for instant live assistance.</li>
                        <li><strong>Step 3:</strong> To submit a formal ticket, click <strong>Create Ticket</strong> (`create_ticket.php`).</li>
                        <li><strong>Step 4:</strong> Select category, enter subject and detailed message, attach screenshots, and submit.</li>
                        <li><strong>Step 5:</strong> Track ticket status in <strong>My Tickets</strong> (`my_tickets.php`).</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Support agents review your ticket and reply with resolution updates.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Support tickets can be submitted 24/7.</p>
                </div>
                <a href="customer_support.php" class="guide-action-btn">➡️ Open Support Center</a>
            </div>
        </div>

        <!-- PATIENT FEATURE 14: AI CHATBOT -->
        <div class="guide-card" data-category="ai" data-keywords="ai chatbot medicalak assistant automated symptom checker health advice chat">
            <div class="guide-card-header">
                <h3><i class="fas fa-robot" style="color: #10B981;"></i> 🤖 How to Use MedicalAk AI Health Chatbot</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">An automated 24/7 AI health assistant to ask general health queries, evaluate symptoms, and receive specialist recommendations.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>AI Chatbot</strong> (`chatbot.php`).</li>
                        <li><strong>Step 2:</strong> Type your health query or symptoms into the chat input box.</li>
                        <li><strong>Step 3:</strong> Press Enter or click <strong>Send</strong>.</li>
                        <li><strong>Step 4:</strong> Read instant AI recommendations and specialist booking suggestions.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Gives quick automated guidance and directs you to suitable medical services.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">The AI Chatbot provides informational advice only and does not replace emergency medical diagnosis.</p>
                </div>
                <a href="chatbot.php" class="guide-action-btn">➡️ Open AI Chatbot</a>
            </div>
        </div>

        <!-- PATIENT FEATURE 15: PWA APP INSTALLATION -->
        <div class="guide-card" data-category="pwa" data-keywords="pwa install app home screen offline mobile app desktop standalone">
            <div class="guide-card-header">
                <h3><i class="fas fa-download" style="color: #10B981;"></i> 📱 How to Install MedicalAk as a PWA Mobile App</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Install MedicalAk as a lightweight native app on your phone home screen without Play Store or App Store downloads.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Click <strong>Install App</strong> in your sidebar menu.</li>
                        <li><strong>Step 2:</strong> Tap <strong>Install</strong> on the browser popup prompt.</li>
                        <li><strong>Step 3:</strong> Open MedicalAk from your mobile home screen app icon anytime!</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Launches as a fullscreen native app with offline capabilities and faster load times.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">On iOS Safari: Tap Share button -> Select "Add to Home Screen".</p>
                </div>
                <a href="javascript:void(0);" class="pwaInstallBtn guide-action-btn">➡️ Install App Now</a>
            </div>
        </div>

    <?php elseif ($user_role === 'doctor'): ?>

        <!-- DOCTOR FEATURE 1: PROFILE & AVAILABILITY -->
        <div class="guide-card expanded" data-category="profile" data-keywords="doctor profile settings fee availability specialization license time slots qualifications">
            <div class="guide-card-header">
                <h3><i class="fas fa-user-md" style="color: #10B981;"></i> 👨‍⚕️ How to Manage Doctor Profile & Availability</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Configure your professional details, medical registration number, consultation fee, specialization, and weekly consultation hours.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Settings & Availability</strong> (`profile.php`).</li>
                        <li><strong>Step 2:</strong> Update Name, Specialization, Medical License Number, and Contact details.</li>
                        <li><strong>Step 3:</strong> Set your <strong>Consultation Fee</strong> (₹) for online/offline visits.</li>
                        <li><strong>Step 4:</strong> Configure available weekly time slots.</li>
                        <li><strong>Step 5:</strong> Click <strong>Save Changes</strong>.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Patients view your verified profile, fee, and available slots when booking consultations.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Ensure your medical license number is correct for verified doctor badge status.</p>
                </div>
                <a href="profile.php" class="guide-action-btn">➡️ Open Doctor Settings</a>
            </div>
        </div>

        <!-- DOCTOR FEATURE 2: CONSULTATION DESK & WORKSPACE -->
        <div class="guide-card" data-category="workspace" data-keywords="consultation desk doctor workspace patient health vault prescription issuance notes clinical summary">
            <div class="guide-card-header">
                <h3><i class="fas fa-stethoscope" style="color: #10B981;"></i> 📋 How to Use Doctor Consultation Workspace</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Your central clinical desk to review patient health vault records, write consultation notes, and generate digital prescriptions.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open an appointment from <strong>Appointments</strong> to launch <strong>Doctor Workspace</strong> (`doctor_workspace.php`).</li>
                        <li><strong>Step 2:</strong> Inspect patient chief complaint, health vault files, and AI summary.</li>
                        <li><strong>Step 3:</strong> Fill in clinical diagnosis, prescribed medicines, dosage instructions, and advice.</li>
                        <li><strong>Step 4:</strong> Click <strong>Issue Prescription & Complete Consultation</strong>.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Consultation marks as <strong>Completed</strong>, and digital prescription is sent instantly to patient account.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Prescriptions issued carry digital validity under your Doctor ID.</p>
                </div>
                <a href="doctor_appointments.php" class="guide-action-btn">➡️ Open Appointments</a>
            </div>
        </div>

        <!-- DOCTOR FEATURE 3: APPOINTMENT MANAGEMENT -->
        <div class="guide-card" data-category="appointments" data-keywords="appointments schedule online offline confirm cancel complete queue token date filter">
            <div class="guide-card-header">
                <h3><i class="fas fa-calendar-day" style="color: #10B981;"></i> 📅 How to Manage Appointments & Daily Queue</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">View, confirm, filter, and manage your daily online and offline patient appointment schedule.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Appointments</strong> (`doctor_appointments.php`).</li>
                        <li><strong>Step 2:</strong> Filter appointments by Date, Status (Pending/Confirmed/Completed), or Type.</li>
                        <li><strong>Step 3:</strong> Click <strong>Confirm</strong> to accept booking or <strong>Start Consult</strong> to open workspace.</li>
                        <li><strong>Step 4:</strong> Update status as consultations progress.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Updates live queue tracker status for waiting patients.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Keep appointment status updated promptly to avoid queue delays.</p>
                </div>
                <a href="doctor_appointments.php" class="guide-action-btn">➡️ Open Appointments</a>
            </div>
        </div>

        <!-- DOCTOR FEATURE 4: PATIENT REFERRALS -->
        <div class="guide-card" data-category="referrals" data-keywords="patient referrals rmp doctor handoff specialist transfer notes">
            <div class="guide-card-header">
                <h3><i class="fas fa-exchange-alt" style="color: #10B981;"></i> 🔄 How to Handle Patient Referrals</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Receive incoming patient referrals sent by RMPs or refer patients outward to higher specialist doctors.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Patient Referrals</strong> (`doctor_referrals.php`).</li>
                        <li><strong>Step 2:</strong> View incoming RMP referrals with clinical summaries.</li>
                        <li><strong>Step 3:</strong> To refer outward, click <strong>Create Referral</strong>, select target doctor, and add transfer notes.</li>
                        <li><strong>Step 4:</strong> Click Submit.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Target doctor gets notified with full case transfer details.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Referrals maintain patient medical records continuity.</p>
                </div>
                <a href="doctor_referrals.php" class="guide-action-btn">➡️ Open Patient Referrals</a>
            </div>
        </div>

        <!-- DOCTOR FEATURE 5: MANAGE MEDICINES CATALOGUE -->
        <div class="guide-card" data-category="medicines" data-keywords="manage medicines catalogue pharmacy add stock price dosage drug">
            <div class="guide-card-header">
                <h3><i class="fas fa-pills" style="color: #10B981;"></i> 💊 How to Manage Medicines Catalogue</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Add, update, and manage medicines listed in the pharmacy catalogue.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Manage Medicines</strong> (`doctor_medicines.php`).</li>
                        <li><strong>Step 2:</strong> View listed medicines, prices, and stock numbers.</li>
                        <li><strong>Step 3:</strong> Click <strong>Add New Medicine</strong> to list a new drug with name, description, unit price, and stock count.</li>
                        <li><strong>Step 4:</strong> Save changes.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Updated medicine details become immediately available for patient order placement.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Keep stock quantities updated to avoid order out-of-stock cancellations.</p>
                </div>
                <a href="doctor_medicines.php" class="guide-action-btn">➡️ Open Manage Medicines</a>
            </div>
        </div>

        <!-- DOCTOR FEATURE 6: MEDICINE ORDERS MANAGEMENT -->
        <div class="guide-card" data-category="orders" data-keywords="medicine orders status dispatch shipped pending processing prescription check">
            <div class="guide-card-header">
                <h3><i class="fas fa-box" style="color: #10B981;"></i> 📦 How to Manage Medicine Orders</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Manage patient medicine orders and update fulfillment status from pending to shipped and delivered.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Medicine Orders</strong> (`doctor_orders.php`).</li>
                        <li><strong>Step 2:</strong> Review patient orders, address, and uploaded prescriptions.</li>
                        <li><strong>Step 3:</strong> Update status from Pending -> Processing -> Shipped -> Delivered.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Patients receive live order tracking notifications.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Verify attached prescription document before marking order shipped.</p>
                </div>
                <a href="doctor_orders.php" class="guide-action-btn">➡️ Open Medicine Orders</a>
            </div>
        </div>

        <!-- DOCTOR FEATURE 7: ANONYMOUS PRIVACY QUERIES -->
        <div class="guide-card" data-category="queries" data-keywords="anonymous privacy queries health secret question photo review answer confidential">
            <div class="guide-card-header">
                <h3><i class="fas fa-user-secret" style="color: #10B981;"></i> 🕵️ How to Answer Anonymous Privacy Queries</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Review sensitive health queries and photos submitted anonymously by patients and provide medical guidance confidentially.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Anonymous Queries</strong> (`doctor_queries.php`).</li>
                        <li><strong>Step 2:</strong> View pending query list and attached condition images.</li>
                        <li><strong>Step 3:</strong> Click <strong>Answer Query</strong>, write medical recommendations.</li>
                        <li><strong>Step 4:</strong> Click <strong>Submit Response</strong>.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Response is delivered to patient while preserving 100% identity anonymity.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Do not request personal identification inside anonymous responses.</p>
                </div>
                <a href="doctor_queries.php" class="guide-action-btn">➡️ Open Anonymous Queries</a>
            </div>
        </div>

        <!-- DOCTOR FEATURE 8: PRESCRIPTION VAULT -->
        <div class="guide-card" data-category="vault" data-keywords="prescription vault history pdf download issued digital prescriptions search">
            <div class="guide-card-header">
                <h3><i class="fas fa-file-prescription" style="color: #10B981;"></i> 📄 How to Access Prescription Vault</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">View and manage all historical digital prescriptions generated across your patient consultations.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Prescription Vault</strong> (`prescription_vault.php`).</li>
                        <li><strong>Step 2:</strong> Search by patient name, prescription ID, or date.</li>
                        <li><strong>Step 3:</strong> View, print, or download PDF copies.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Allows fast retrieval of past clinical treatment records.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">All issued prescriptions are permanently archived.</p>
                </div>
                <a href="prescription_vault.php" class="guide-action-btn">➡️ Open Prescription Vault</a>
            </div>
        </div>

        <!-- DOCTOR FEATURE 9: EARNINGS & PAYMENTS -->
        <div class="guide-card" data-category="payments" data-keywords="doctor earnings payment history consult payouts transactions revenue">
            <div class="guide-card-header">
                <h3><i class="fas fa-receipt" style="color: #10B981;"></i> 💳 How to View Earnings & Payment History</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Track consultation earnings, completed appointments revenue, and payout records.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Payment History</strong> (`payment_history.php`).</li>
                        <li><strong>Step 2:</strong> Review list of completed consult payouts, amounts, and dates.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Provides full financial clarity for clinical consult earnings.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Payouts are processed according to MedicalAk schedule.</p>
                </div>
                <a href="payment_history.php" class="guide-action-btn">➡️ Open Payment History</a>
            </div>
        </div>

    <?php elseif ($user_role === 'rmp'): ?>

        <!-- RMP FEATURE 1: PROFILE & SETTINGS -->
        <div class="guide-card expanded" data-category="profile" data-keywords="rmp profile license settings clinic address contact practitioner">
            <div class="guide-card-header">
                <h3><i class="fas fa-stethoscope" style="color: #10B981;"></i> 🩺 How to Manage RMP Profile & Settings</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Update your practitioner profile, RMP license number, clinic location, and contact information.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Settings</strong> (`profile.php`).</li>
                        <li><strong>Step 2:</strong> Update Name, RMP License Registration Number, Phone, and Clinic Address.</li>
                        <li><strong>Step 3:</strong> Save changes.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Patients can find your diagnostic lab services and contact you for doorstep checkups.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Ensure your phone number is correct for patient coordination.</p>
                </div>
                <a href="profile.php" class="guide-action-btn">➡️ Open RMP Settings</a>
            </div>
        </div>

        <!-- RMP FEATURE 2: DIAGNOSTIC TESTS & QUEUE -->
        <div class="guide-card" data-category="tests" data-keywords="diagnostic tests queue pending checkups rmp dashboard bookings stats">
            <div class="guide-card-header">
                <h3><i class="fas fa-flask" style="color: #10B981;"></i> 🧪 How to Manage Diagnostic Tests & Queue</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Overview of patient lab test bookings, diagnostic queue, pending checkups, and commission earnings summary.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>RMP Dashboard & Tests</strong> (`rmp_dashboard.php`).</li>
                        <li><strong>Step 2:</strong> Check summary metrics: Total Tests, Pending Checkups, and Commission Earned (₹).</li>
                        <li><strong>Step 3:</strong> View patient test bookings list with scheduled dates and contact details.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Helps organize daily sample collections efficiently.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Update booking status after sample collection.</p>
                </div>
                <a href="rmp_dashboard.php" class="guide-action-btn">➡️ Open RMP Dashboard</a>
            </div>
        </div>

        <!-- RMP FEATURE 3: UPLOAD TEST RESULTS -->
        <div class="guide-card" data-category="upload" data-keywords="upload test results pdf report lab report image patient health vault">
            <div class="guide-card-header">
                <h3><i class="fas fa-file-upload" style="color: #10B981;"></i> 📤 How to Upload Diagnostic Test Results</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Upload finished diagnostic lab reports (PDF or image) directly to the patient's Health Vault.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Upload Results</strong> (`rmp_upload.php`).</li>
                        <li><strong>Step 2:</strong> Select patient test booking from dropdown.</li>
                        <li><strong>Step 3:</strong> Choose test report PDF or image file from your device.</li>
                        <li><strong>Step 4:</strong> Click <strong>Upload Report & Notify Patient</strong>.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Test status updates to <strong>Completed</strong> and PDF report attaches to patient's Health Vault.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Ensure uploaded documents are clear and legible.</p>
                </div>
                <a href="rmp_upload.php" class="guide-action-btn">➡️ Open Upload Results</a>
            </div>
        </div>

        <!-- RMP FEATURE 4: DOCTOR REFERRALS -->
        <div class="guide-card" data-category="referrals" data-keywords="doctor referrals rmp referral specialist handoff commission earned doctor">
            <div class="guide-card-header">
                <h3><i class="fas fa-user-md" style="color: #10B981;"></i> 👨‍⚕️ How to Use Doctor Referrals & Earn Commission</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Refer patients requiring specialized care to doctors on MedicalAk and earn referral commissions upon completion.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Doctor Referrals</strong> (`rmp_referral.php`).</li>
                        <li><strong>Step 2:</strong> Select patient and target specialist doctor.</li>
                        <li><strong>Step 3:</strong> Enter diagnostic referral notes.</li>
                        <li><strong>Step 4:</strong> Click <strong>Send Doctor Referral</strong>.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Doctor receives referral notice, and your account earns referral commission when consultation completes.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Referral earnings update automatically on your dashboard.</p>
                </div>
                <a href="rmp_referral.php" class="guide-action-btn">➡️ Open Doctor Referrals</a>
            </div>
        </div>

        <!-- RMP FEATURE 5: PATIENT FOLLOW-UP REMINDERS -->
        <div class="guide-card" data-category="followups" data-keywords="patient follow up reminders schedule date phone reason status rmp dashboard">
            <div class="guide-card-header">
                <h3><i class="fas fa-clock" style="color: #10B981;"></i> 📅 How to Manage Patient Follow-Up Reminders</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">Schedule and track health follow-up reminders for patients under your care.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Go to <strong>RMP Dashboard</strong> (`rmp_dashboard.php`).</li>
                        <li><strong>Step 2:</strong> Locate <strong>Patient Follow-up Reminders</strong> section.</li>
                        <li><strong>Step 3:</strong> Fill in Patient Name, Phone, Date, and Reason.</li>
                        <li><strong>Step 4:</strong> Click <strong>Schedule Follow-Up</strong>.</li>
                        <li><strong>Step 5:</strong> Toggle completed status when done.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Keeps a structured schedule of patient follow-ups.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Helps maintain post-test monitoring quality.</p>
                </div>
                <a href="rmp_dashboard.php" class="guide-action-btn">➡️ Open RMP Dashboard</a>
            </div>
        </div>

        <!-- RMP FEATURE 6: COMMISSION EARNINGS & PAYMENTS -->
        <div class="guide-card" data-category="payments" data-keywords="rmp commission earnings payment history lab test fee doctor referral fee payout">
            <div class="guide-card-header">
                <h3><i class="fas fa-receipt" style="color: #10B981;"></i> 💰 How to Track RMP Commission Earnings</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-info-circle"></i> What is this?</div>
                    <p class="guide-section-text">View commission earnings for completed lab tests (₹100/test) and doctor referrals (₹150/referral).</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-list-ol"></i> How to use it</div>
                    <ol class="guide-steps-list">
                        <li><strong>Step 1:</strong> Open <strong>Payment History</strong> (`payment_history.php`).</li>
                        <li><strong>Step 2:</strong> Review commission logs and total earnings breakdown.</li>
                    </ol>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-check-circle"></i> What happens after using it?</div>
                    <p class="guide-section-text">Provides full financial records of your RMP earnings.</p>
                </div>
                <div class="guide-section-block">
                    <div class="guide-section-title"><i class="fas fa-exclamation-triangle"></i> Important information</div>
                    <p class="guide-section-text">Commissions calculate automatically on completed services.</p>
                </div>
                <a href="payment_history.php" class="guide-action-btn">➡️ Open Payment History</a>
            </div>
        </div>

    <?php endif; ?>

    </div>

    <!-- No Search Results Box -->
    <div class="no-results-box" id="noResultsBox">
        <i class="fas fa-search" style="font-size: 3rem; color: #10B981; margin-bottom: 1rem;"></i>
        <h3 style="margin: 0 0 0.5rem 0; color: var(--text-primary, #ffffff);">No matching instructions found</h3>
        <p style="margin: 0; color: var(--text-secondary, #94a3b8);">Try searching with different keywords like "Wallet", "Order", "Consultation", or "Medical Card".</p>
    </div>

    <!-- FAQ / COMMON QUESTIONS SECTION -->
    <div class="faq-section">
        <h2 style="font-size: 1.6rem; font-weight: 800; color: var(--text-primary, #ffffff); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem;">
            <span style="color: #10B981;">❓</span> Common Questions & Troubleshooting
        </h2>

        <div class="guide-card">
            <div class="guide-card-header">
                <h3><i class="fas fa-question-circle" style="color: #10B981;"></i> How do I change my account password?</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <p class="guide-section-text">Go to <strong>Profile</strong> -> enter your current password -> type your new password -> click <strong>Save Changes</strong>. If you forgot your password, use the <strong>Forgot Password</strong> link on the login page.</p>
            </div>
        </div>

        <div class="guide-card">
            <div class="guide-card-header">
                <h3><i class="fas fa-question-circle" style="color: #10B981;"></i> What should I do if my payment or wallet top-up is pending?</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <p class="guide-section-text">Check your <strong>Payment History</strong> or <strong>My Wallet</strong> page. If payment is completed on your bank side but pending on site, click <strong>Customer Support</strong> to send your transaction reference ID to support agents.</p>
            </div>
        </div>

        <div class="guide-card">
            <div class="guide-card-header">
                <h3><i class="fas fa-question-circle" style="color: #10B981;"></i> Why is my Digital Medical Card pending approval?</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <p class="guide-section-text">New digital medical cards undergo verification by the MedicalAk Admin team to ensure information accuracy. Verification is usually completed within a short time window.</p>
            </div>
        </div>

        <div class="guide-card">
            <div class="guide-card-header">
                <h3><i class="fas fa-question-circle" style="color: #10B981;"></i> How do I install MedicalAk as an app on my phone?</h3>
                <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="guide-card-body">
                <p class="guide-section-text">Click the <strong>Install App</strong> button in the menu. On Android Chrome, accept the install prompt. On iPhone Safari, tap the Share icon and select <strong>Add to Home Screen</strong>.</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Accordion Toggle Handler
    const cardHeaders = document.querySelectorAll('.guide-card-header');
    cardHeaders.forEach(function(header) {
        header.addEventListener('click', function() {
            const card = this.closest('.guide-card');
            card.classList.toggle('expanded');
        });
    });

    // Search Filtering Handler
    const searchInput = document.getElementById('guidelineSearchInput');
    const guideCards = document.querySelectorAll('#guidelineCardsList .guide-card');
    const noResultsBox = document.getElementById('noResultsBox');
    const categoryPills = document.querySelectorAll('.category-pill');
    let currentCategory = 'all';

    function filterCards() {
        const query = searchInput.value.toLowerCase().trim();
        let visibleCount = 0;

        guideCards.forEach(function(card) {
            const cardCategory = card.getAttribute('data-category');
            const keywords = (card.getAttribute('data-keywords') || '').toLowerCase();
            const textContent = card.innerText.toLowerCase();

            const matchesCategory = (currentCategory === 'all' || cardCategory === currentCategory);
            const matchesQuery = (query === '' || keywords.includes(query) || textContent.includes(query));

            if (matchesCategory && matchesQuery) {
                card.style.display = 'block';
                visibleCount++;
                if (query !== '') {
                    card.classList.add('expanded');
                }
            } else {
                card.style.display = 'none';
            }
        });

        if (visibleCount === 0) {
            noResultsBox.style.display = 'block';
        } else {
            noResultsBox.style.display = 'none';
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterCards);
    }

    categoryPills.forEach(function(pill) {
        pill.addEventListener('click', function() {
            categoryPills.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            currentCategory = this.getAttribute('data-category');
            filterCards();
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
