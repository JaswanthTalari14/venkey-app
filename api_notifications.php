<?php
require_once 'config.php';
require_once 'includes/notification_functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized user session']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'fetch';

// 1. Server-Sent Events (SSE) Real-Time Notification Stream
if ($action === 'stream') {
    // Disable output buffering
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', 1);
    }
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    $last_unread = get_unread_notification_count($user_id);
    $initial_list = get_user_notifications($user_id, 15);

    // Initial Event
    echo "data: " . json_encode([
        'type' => 'init',
        'unread_count' => $last_unread,
        'notifications' => $initial_list
    ]) . "\n\n";
    @flush();

    // Loop to emit real-time notifications
    $max_iterations = 25; // Keep connection active for up to 50 seconds per SSE request
    for ($i = 0; $i < $max_iterations; $i++) {
        if (connection_aborted()) break;
        sleep(2);

        $current_unread = get_unread_notification_count($user_id);
        if ($current_unread !== $last_unread) {
            $last_unread = $current_unread;
            $recent_list = get_user_notifications($user_id, 10);

            echo "data: " . json_encode([
                'type' => 'update',
                'unread_count' => $current_unread,
                'notifications' => $recent_list
            ]) . "\n\n";
            @flush();
        }
    }
    exit;
}

// JSON API Handlers
header('Content-Type: application/json');

if ($action === 'fetch') {
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

    $unread_count = get_unread_notification_count($user_id);
    $notifications = get_user_notifications($user_id, $limit, $filter);

    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count,
        'notifications' => $notifications
    ]);
    exit;
}

if ($action === 'mark_read') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $notif_id = isset($input['id']) ? (int)$input['id'] : 0;

    if ($notif_id > 0) {
        mark_notification_as_read($user_id, $notif_id);
    }
    $unread_count = get_unread_notification_count($user_id);

    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count
    ]);
    exit;
}

if ($action === 'mark_all_read') {
    mark_all_notifications_read($user_id);

    echo json_encode([
        'success' => true,
        'unread_count' => 0
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
