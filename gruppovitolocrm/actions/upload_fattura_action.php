<?php
session_start();
require_once __DIR__.'/../includes/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['ruolo'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Accesso negato']);
    exit;
}

$id_ordine = filter_input(INPUT_POST, 'id_ordine', FILTER_VALIDATE_INT);
if (!$id_ordine || !isset($_FILES['fattura'])) {
    echo json_encode(['success' => false, 'message' => 'Dati non validi']);
    exit;
}

$upload_dir = __DIR__ . '/fatture';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Errore DB']);
    exit;
}

$stmt = $conn->prepare('SELECT fattura_file FROM ordini WHERE id_ordine = ?');
$stmt->bind_param('i', $id_ordine);
$stmt->execute();
$stmt->bind_result($current_file);
$stmt->fetch();
$stmt->close();

$original = basename($_FILES['fattura']['name']);
$ext = pathinfo($original, PATHINFO_EXTENSION);
$filename = 'ordine_' . $id_ordine . ($ext ? '.' . $ext : '');
$dest = $upload_dir . '/' . $filename;

if (!move_uploaded_file($_FILES['fattura']['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'message' => 'Impossibile salvare il file']);
    $conn->close();
    exit;
}

if ($current_file && $current_file !== $filename && file_exists($upload_dir . '/' . $current_file)) {
    unlink($upload_dir . '/' . $current_file);
}

$upd = $conn->prepare('UPDATE ordini SET fattura_file = ?, data_ultima_modifica = CURRENT_TIMESTAMP WHERE id_ordine = ?');
$upd->bind_param('si', $filename, $id_ordine);
$success = $upd->execute();
$upd->close();
$conn->close();

echo json_encode(['success' => $success]);
