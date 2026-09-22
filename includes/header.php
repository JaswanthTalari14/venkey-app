<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedicalAk - Smart Healthcare Solutions</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <a href="index.php" class="logo">MedicalAk</a>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="#features">Features</a></li>
                <li><a href="#about">About</a></li>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <?php if($_SESSION['role'] == 'patient'): ?>
                        <li><a href="patient_dashboard.php">Dashboard</a></li>
                    <?php elseif($_SESSION['role'] == 'doctor'): ?>
                        <li><a href="doctor_dashboard.php">Dashboard</a></li>
                    <?php elseif($_SESSION['role'] == 'admin'): ?>
                        <li><a href="admin_dashboard.php">Admin Panel</a></li>
                    <?php elseif($_SESSION['role'] == 'rmp'): ?>
                        <li><a href="rmp_dashboard.php">RMP Panel</a></li>
                    <?php endif; ?>
                    <li><a href="profile.php" style="color: var(--secondary-color);"><i class="fas fa-user-circle"></i> Profile</a></li>
                    <li><a href="logout.php" class="btn btn-outline" style="padding: 0.4rem 1rem;">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="btn btn-outline">Login</a></li>
                    <li><a href="register.php" class="btn btn-primary">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main>
