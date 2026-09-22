<?php
session_start();
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "medicalak";

$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create DB if not exists
$conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
$conn->select_db($dbname);

// Simple setup flag or script run check could be done here, 
// but we will run db.sql directly from admin or manually.

$gemini_api_key = 'AIzaSyB2jB_N-GL6O0ad_lgtD5xxOlv6h0xcA2Q';
?>
