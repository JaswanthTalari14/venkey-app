<?php
$conn = new mysqli("localhost", "root", "", "medicalak");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$query = "CREATE TABLE IF NOT EXISTS referrals (
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
);";

if ($conn->query($query) === TRUE) {
    echo "Table referrals created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}
?>
