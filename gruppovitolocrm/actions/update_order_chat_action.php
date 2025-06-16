<?php
session_start();
require_once __DIR__.'/../includes/db_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['ruolo'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato.']);
    exit;
}

$id_ordine = filter_input(INPUT_POST, 'id_ordine', FILTER_VALIDATE_INT);
$messaggio = trim($_POST['messaggio_admin'] ?? '');

if (!$id_ordine || $messaggio === '') {
    echo json_encode(['success' => false, 'message' => 'Dati non validi.']);
    exit;
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Errore di connessione al database.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO ordini_chat (id_ordine, id_utente, messaggio_admin, data_messaggio) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
$admin_id = $_SESSION['user_id'];
$stmt->bind_param('iis', $id_ordine, $admin_id, $messaggio);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    $upd = $conn->prepare("UPDATE ordini SET data_ultima_modifica = CURRENT_TIMESTAMP WHERE id_ordine = ?");
    $upd->bind_param('i', $id_ordine);
    $upd->execute();
    $upd->close();
}

$conn->close();

echo json_encode(['success' => $success]);
exit;
?>
