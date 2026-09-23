CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('patient', 'doctor', 'rmp', 'admin') DEFAULT 'patient',
    specialization VARCHAR(100) DEFAULT NULL, -- for doctors
    latitude DECIMAL(10,8) DEFAULT NULL, -- for location tracking (10km radius feature)
    longitude DECIMAL(11,8) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    type ENUM('online', 'offline') NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id),
    FOREIGN KEY (doctor_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 0,
    image VARCHAR(255) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    payment_method VARCHAR(50) DEFAULT 'COD',
    payment_status VARCHAR(50) DEFAULT 'Cash on Delivery',
    gateway_order_id VARCHAR(100) DEFAULT NULL,
    gateway_payment_id VARCHAR(100) DEFAULT NULL,
    address TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id)
);

CREATE TABLE IF NOT EXISTS privacy_consultations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    suggestion TEXT DEFAULT NULL, -- Doctor or AI suggestion
    status ENUM('pending', 'answered') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS test_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    rmp_id INT NOT NULL,
    test_name VARCHAR(100) NOT NULL,
    scheduled_date DATE NOT NULL,
    result_path VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id),
    FOREIGN KEY (rmp_id) REFERENCES users(id)
);

-- Insert an admin user
INSERT IGNORE INTO users (name, email, phone, password, role) VALUES 
('Admin', 'admin@medicalak.com', '0000000000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- password is 'password' (bcrypt default hash)

-- Insert a sample doctor
INSERT IGNORE INTO users (name, email, phone, password, role, specialization, latitude, longitude) VALUES 
('Dr. Smith', 'doctor@medicalak.com', '1234567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'Cardiologist', 28.7041, 77.1025);

-- Insert a sample patient
INSERT IGNORE INTO users (name, email, phone, password, role, latitude, longitude) VALUES 
('John Doe', 'patient@medicalak.com', '0987654321', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 28.7042, 77.1026);

CREATE TABLE IF NOT EXISTS referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rmp_id INT NOT NULL,
    doctor_id INT NOT NULL,
    patient_name VARCHAR(100) NOT NULL,
    patient_phone VARCHAR(15) NOT NULL,
    notes TEXT,
    payment_status ENUM('pending', 'completed') DEFAULT 'completed',
    referral_status ENUM('pending', 'accepted', 'completed', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rmp_id) REFERENCES users(id),
    FOREIGN KEY (doctor_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS referral_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    program_enabled TINYINT(1) DEFAULT 1,
    referrer_reward DECIMAL(10,2) DEFAULT 50.00,
    referred_reward DECIMAL(10,2) DEFAULT 25.00,
    min_order_amount DECIMAL(10,2) DEFAULT 199.00,
    expiry_days INT DEFAULT 30,
    max_rewards INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS referral_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT UNIQUE NOT NULL,
    referral_code VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS customer_referrals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    referrer_customer_id INT NOT NULL,
    referred_customer_id INT UNIQUE NOT NULL,
    referral_code VARCHAR(50) NOT NULL,
    status ENUM('Invited', 'Registered', 'Order Pending', 'Qualified', 'Reward Earned', 'Reward Reversed', 'Expired', 'Rejected') DEFAULT 'Registered',
    qualifying_order_id INT DEFAULT NULL,
    invited_at TIMESTAMP NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    qualified_at TIMESTAMP NULL,
    reward_earned_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_customer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS referral_rewards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    referral_id INT DEFAULT NULL,
    customer_id INT NOT NULL,
    reward_type ENUM('referrer_reward', 'referred_reward', 'reversal', 'redemption') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('earned', 'reversed', 'redeemed') DEFAULT 'earned',
    related_order_id INT DEFAULT NULL,
    transaction_id VARCHAR(100) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS wallet_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    min_topup DECIMAL(10,2) DEFAULT 50.00,
    max_topup DECIMAL(10,2) DEFAULT 10000.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wallets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT UNIQUE NOT NULL,
    available_balance DECIMAL(10,2) DEFAULT 0.00,
    pending_balance DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('active', 'frozen') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    wallet_id INT NOT NULL,
    customer_id INT NOT NULL,
    transaction_id VARCHAR(100) UNIQUE NOT NULL,
    transaction_type ENUM('topup', 'topup_pending', 'topup_approved', 'topup_rejected', 'payment', 'refund', 'referral_reward', 'referral_reversal', 'admin_credit', 'admin_debit', 'correction') NOT NULL,
    direction ENUM('credit', 'debit') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    previous_balance DECIMAL(10,2) NOT NULL,
    new_balance DECIMAL(10,2) NOT NULL,
    status ENUM('completed', 'pending', 'failed', 'reversed') DEFAULT 'completed',
    order_id INT DEFAULT NULL,
    payment_id VARCHAR(100) DEFAULT NULL,
    referral_id INT DEFAULT NULL,
    reason TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS wallet_topups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    wallet_id INT NOT NULL,
    customer_id INT NOT NULL,
    topup_id VARCHAR(100) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'online',
    payment_id VARCHAR(100) DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected', 'failed', 'completed') DEFAULT 'pending',
    gateway_reference VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
);
