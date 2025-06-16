<?php
session_start();
require_once __DIR__.'/../includes/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: ../le_mie_segnalazioni.php?reply=error');
    exit;
}

$id_messaggio = filter_input(INPUT_POST, 'id_messaggio', FILTER_VALIDATE_INT);
$risposta = trim($_POST['risposta_utente'] ?? '');

if (!$id_messaggio || $risposta === '') {
    header('Location: ../le_mie_segnalazioni.php?reply=error');
    exit;
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    header('Location: ../le_mie_segnalazioni.php?reply=error');
    exit;
}

$sql = 'SELECT sc.id_segnalazione, sc.risposta_utente, s.id_utente_segnalante FROM segnalazioni_chat sc JOIN segnalazioni s ON sc.id_segnalazione = s.id_segnalazione WHERE sc.id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id_messaggio);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row || $row['id_utente_segnalante'] != $_SESSION['user_id'] || !empty($row['risposta_utente'])) {
    $conn->close();
    header('Location: ../le_mie_segnalazioni.php?reply=error');
    exit;
}

$sql = 'UPDATE segnalazioni_chat SET risposta_utente = ?, data_risposta = CURRENT_TIMESTAMP WHERE id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('si', $risposta, $id_messaggio);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    $upd = $conn->prepare('UPDATE segnalazioni SET data_ultima_modifica = CURRENT_TIMESTAMP WHERE id_segnalazione = ?');
    if ($upd) {
        $upd->bind_param('i', $row['id_segnalazione']);
        $upd->execute();
        $upd->close();
    }
}

$conn->close();

if ($success) {
    header('Location: ../le_mie_segnalazioni.php?reply=success');
} else {
    header('Location: ../le_mie_segnalazioni.php?reply=error');
}
exit;
?>