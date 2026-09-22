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

<section id="features" style="margin-top: 6rem;">
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
    </div>
</section>

<?php include 'includes/footer.php'; ?>
