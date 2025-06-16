<?php
function logUserAction($message) {
    $logFile = __DIR__ . '/../logs/user_actions.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}
?>
