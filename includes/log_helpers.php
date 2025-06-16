<?php
require_once __DIR__ . '/db_config.php';
function log_action($user_id, $username, $action, $details = null) {
    global $db_host, $db_user, $db_pass, $db_name, $db_port;
    $conn = isset($db_port)
        ? new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port)
        : new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        error_log('[log_action] DB connection error: ' . $conn->connect_error);
        return;
    }
    $stmt = $conn->prepare("INSERT INTO user_logs (user_id, username, action, details) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('isss', $user_id, $username, $action, $details);
        $stmt->execute();
        $stmt->close();
    } else {
        error_log('[log_action] Prepare failed: ' . $conn->error);
    }
    $conn->close();
}
?>
