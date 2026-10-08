<?php include 'includes/header.php'; ?>

<section class="hero">
    <div class="hero-text">
        <h1>Smart Healthcare,<br>Wherever You Are.</h1>
        <p>A comprehensive platform for online consultations, doortodoor medical visits, medicine delivery, and privacy-focused services. Your health, simplified.</p>
        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="<?php echo htmlspecialchars($_SESSION['role']); ?>_dashboard.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 0.8rem 2rem;">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?> <i class="fas fa-arrow-right"></i></a>
        <?php else: ?>
            <a href="register.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 0.8rem 2rem;">Get Started Now <i class="fas fa-arrow-right"></i></a>
        <?php endif; ?>
    </div>
    <div class="hero-image glass-panel" style="padding: 3rem;">
        <!-- Placeholder for a beautiful 3D health graphic or illustration -->
        <i class="fas fa-heartbeat" style="font-size: 8rem; color: var(--primary-color);"></i>
        <div style="margin-top: 2rem;">
            <h3>24/7 AI Health Assistant</h3>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">Chat with our AI chatbot anytime for immediate guidance.</p>
        </div>
    </div>
</section>

<?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'): ?>
<!-- Sleek & Small Green Gradient Guideline Card -->
<section style="max-width: 1200px; margin: 1.5rem auto 0 auto; padding: 0 1rem;">
    <div style="background: linear-gradient(135deg, #059669 0%, #10B981 50%, #047857 100%); border-radius: 14px; color: #ffffff; padding: 0.85rem 1.25rem; box-shadow: 0 6px 20px -3px rgba(16, 185, 129, 0.4); position: relative; overflow: hidden;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; position: relative; z-index: 2; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(255, 255, 255, 0.2); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                    📖
                </div>
                <div>
                    <h3 style="font-size: 1rem; font-weight: 800; margin: 0; color: #ffffff; line-height: 1.2;">How to Use MedicalAk</h3>
                    <p style="margin: 0.15rem 0 0 0; font-size: 0.8rem; color: rgba(255, 255, 255, 0.9); line-height: 1.2;">Complete Website Guideline — Learn every feature step by step</p>
                </div>
            </div>
            <a href="guidelines.php" class="btn" style="background: #ffffff; color: #047857; font-weight: 700; font-size: 0.82rem; padding: 0.45rem 0.9rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; white-space: nowrap; box-shadow: 0 2px 8px rgba(0,0,0,0.15); flex-shrink: 0;">
                <span>View Guideline</span>
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<section id="features" style="margin-top: 3rem;">
    <h2 style="text-align: center; font-size: 2.5rem; margin-bottom: 1rem;">Our Services</h2>
    <p style="text-align: center; color: var(--text-secondary);">Everything you need for a healthier life, all in one platform.</p>
    
    <div class="features-grid">
        <a href="book_consult.php" style="text-decoration: none; color: inherit; display: block;">
            <div class="feature-card glass-panel" style="height: 100%;">
                <i class="fas fa-stethoscope feature-icon"></i>
                <h3>Online & Offline Consults</h3>
                <p>Connect with top doctors online or book a doortodoor offline consultation instantly.</p>
            </div>
        </a>
        
        <a href="nearby_doctors.php" style="text-decoration: none; color: inherit; display: block;">
            <div class="feature-card glass-panel" style="height: 100%;">
                <i class="fas fa-map-marker-alt feature-icon"></i>
                <h3>10km Doctor Tracking</h3>
                <p>Find alternative doctors nearby precisely tracked within a 10 km radius of your location.</p>
            </div>
        </a>
        
        <a href="privacy_consult.php" style="text-decoration: none; color: inherit; display: block;">
            <div class="feature-card glass-panel" style="height: 100%;">
                <i class="fas fa-user-secret feature-icon"></i>
                <h3>Privacy Consultation</h3>
                <p>Upload photos of health issues securely without sharing personal identity details.</p>
            </div>
        </a>
        
        <a href="medicines.php" style="text-decoration: none; color: inherit; display: block;">
            <div class="feature-card glass-panel" style="height: 100%;">
                <i class="fas fa-pills feature-icon"></i>
                <h3>Medicine Delivery</h3>
                <p>Order prescribed medicines easily with fast, reliable home delivery services.</p>
            </div>
        </a>
        
        <a href="book_tests.php" style="text-decoration: none; color: inherit; display: block;">
            <div class="feature-card glass-panel" style="height: 100%;">
                <i class="fas fa-flask feature-icon"></i>
                <h3>RMP Diagnostic Tests</h3>
                <p>Book essential health checkups and lab tests directly with Registered Medical Practitioners.</p>
            </div>
        </a>
        
        <a href="chatbot.php" style="text-decoration: none; color: inherit; display: block;">
            <div class="feature-card glass-panel" style="height: 100%;">
                <i class="fas fa-robot feature-icon"></i>
                <h3>AI Chatbot Assistant</h3>
                <p>24/7 automated assistance to answer queries and guide you to the right specialist.</p>
            </div>
        </a>

        <a href="customer_support.php" style="text-decoration: none; color: inherit; display: block;">
            <div class="feature-card glass-panel" style="height: 100%; border-top: 3px solid #25D366;">
                <i class="fas fa-headset feature-icon" style="color: #25D366;"></i>
                <h3>Customer & WhatsApp Support</h3>
                <p>Chat directly on WhatsApp or create tracked support tickets for instant resolution.</p>
            </div>
        </a>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
