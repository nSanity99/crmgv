<?php
session_start();
require_once __DIR__.'/../includes/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['ruolo'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Accesso negato']);
    exit;
}

$id_ordine = filter_input(INPUT_POST, 'id_ordine', FILTER_VALIDATE_INT);
if (!$id_ordine) {
    echo json_encode(['success' => false, 'message' => 'ID non valido']);
    exit;
}

$upload_dir = __DIR__ . '/fatture';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Errore DB']);
    exit;
}

$stmt = $conn->prepare('SELECT fattura_file FROM ordini WHERE id_ordine = ?');
$stmt->bind_param('i', $id_ordine);
$stmt->execute();
$stmt->bind_result($file);
$stmt->fetch();
$stmt->close();

if ($file && file_exists($upload_dir . '/' . $file)) {
    unlink($upload_dir . '/' . $file);
}

$upd = $conn->prepare('UPDATE ordini SET fattura_file = NULL, data_ultima_modifica = CURRENT_TIMESTAMP WHERE id_ordine = ?');
$upd->bind_param('i', $id_ordine);
$success = $upd->execute();
$upd->close();
$conn->close();

echo json_encode(['success' => $success]);
