<?php
session_start();
require_once __DIR__.'/../includes/db_config.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Errore sconosciuto'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metodo di richiesta non valido.';
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['ruolo'] ?? '') !== 'admin') {
    $response['message'] = 'Accesso non autorizzato.';
    echo json_encode($response);
    exit;
}

$id_ordine = filter_input(INPUT_POST, 'id_ordine', FILTER_VALIDATE_INT);
if (!$id_ordine) {
    $response['message'] = 'ID ordine non valido.';
    echo json_encode($response);
    exit;
}

$conn = isset($db_port)
    ? new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port)
    : new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    $response['message'] = 'Errore di connessione al database.';
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare("UPDATE ordini SET consenti_modifica = 1 WHERE id_ordine = ? AND consenti_modifica = 0");
if ($stmt) {
    $stmt->bind_param('i', $id_ordine);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'Ordine sbloccato per modifica.';
    } else {
        $response['message'] = 'Ordine già modificabile o non trovato.';
    }
    $stmt->close();
} else {
    $response['message'] = 'Errore preparazione statement.';
}

$conn->close();

echo json_encode($response);
exit;
?>
