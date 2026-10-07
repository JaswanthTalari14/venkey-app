<?php
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'redirect' => 'index.php']);
exit;
