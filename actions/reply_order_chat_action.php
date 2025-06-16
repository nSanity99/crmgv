<?php
session_start();
require_once __DIR__.'/../includes/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: ../i_miei_ordini.php?reply=error');
    exit;
}

$id_messaggio = filter_input(INPUT_POST, 'id_messaggio', FILTER_VALIDATE_INT);
$risposta = trim($_POST['risposta_utente'] ?? '');

if (!$id_messaggio || $risposta === '') {
    header('Location: ../i_miei_ordini.php?reply=error');
    exit;
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    header('Location: ../i_miei_ordini.php?reply=error');
    exit;
}

$sql = 'SELECT oc.id_ordine, oc.risposta_utente, o.id_utente_richiedente FROM ordini_chat oc JOIN ordini o ON oc.id_ordine = o.id_ordine WHERE oc.id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id_messaggio);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row || $row['id_utente_richiedente'] != $_SESSION['user_id'] || !empty($row['risposta_utente'])) {
    $conn->close();
    header('Location: ../i_miei_ordini.php?reply=error');
    exit;
}

$sql = 'UPDATE ordini_chat SET risposta_utente = ?, data_risposta = CURRENT_TIMESTAMP WHERE id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('si', $risposta, $id_messaggio);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    $upd = $conn->prepare('UPDATE ordini SET data_ultima_modifica = CURRENT_TIMESTAMP WHERE id_ordine = ?');
    if ($upd) {
        $upd->bind_param('i', $row['id_ordine']);
        $upd->execute();
        $upd->close();
    }
}

$conn->close();

if ($success) {
    header('Location: ../i_miei_ordini.php?reply=success');
} else {
    header('Location: ../i_miei_ordini.php?reply=error');
}
exit;
?>
