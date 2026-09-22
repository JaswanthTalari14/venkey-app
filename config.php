<?php
session_start();

$host   = getenv('DB_HOST') ?: "localhost";
$user   = getenv('DB_USER') ?: "root";
$pass   = getenv('DB_PASS') ?: "";
$dbname = getenv('DB_NAME') ?: "medicalak";
$port   = getenv('DB_PORT') ? intval(getenv('DB_PORT')) : 3306;

$conn = new mysqli($host, $user, $pass, "", $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create DB if not exists
$conn->query("CREATE DATABASE IF NOT EXISTS `$dbname`");
$conn->select_db($dbname);

$gemini_api_key = getenv('GEMINI_API_KEY') ?: 'AIzaSyB2jB_N-GL6O0ad_lgtD5xxOlv6h0xcA2Q';
?>
