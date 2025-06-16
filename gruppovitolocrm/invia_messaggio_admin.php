<?php
session_start();
require_once __DIR__.'/includes/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: gestione_segnalazioni.php?status=notallowed");
    exit;
}

$id_segnalazione = filter_input(INPUT_POST, 'id_segnalazione', FILTER_VALIDATE_INT);
$messaggio = trim($_POST['messaggio_admin'] ?? '');

if (!$id_segnalazione || $messaggio === '') {
    header("Location: gestione_segnalazioni.php?status=invalid");
    exit;
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    header("Location: gestione_segnalazioni.php?status=dberror");
    exit;
}

$stmt = $conn->prepare("INSERT INTO segnalazioni_chat (id_segnalazione, id_utente, messaggio_admin, data_messaggio) VALUES (?, 0, ?, CURRENT_TIMESTAMP)");
$stmt->bind_param("is", $id_segnalazione, $messaggio);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    $upd = $conn->prepare("UPDATE segnalazioni SET data_ultima_modifica = CURRENT_TIMESTAMP WHERE id_segnalazione = ?");
    $upd->bind_param("i", $id_segnalazione);
    $upd->execute();
    $upd->close();
    header("Location: gestione_segnalazioni.php?status=success");
} else {
    header("Location: gestione_segnalazioni.php?status=error");
}
$conn->close();
exit;
