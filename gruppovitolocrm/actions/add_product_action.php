<?php
session_start();
require_once __DIR__.'/../includes/db_config.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Errore sconosciuto'];

if ($_SERVER["REQUEST_METHOD"] !== 'POST') {
    $response['message'] = 'Metodo di richiesta non valido.';
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['ruolo'] ?? '') !== 'admin') {
    $response['message'] = 'Accesso non autorizzato.';
    echo json_encode($response);
    exit;
}

$nome = trim($_POST['nome_prodotto'] ?? '');
$categoria_id = isset($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : 1;
if ($nome === '') {
    $response['message'] = 'Nome prodotto mancante.';
    echo json_encode($response);
    exit;
}

$conn = isset($db_port) ? new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port) : new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    $response['message'] = 'Errore connessione database.';
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare("INSERT INTO catalogo_prodotti (nome, categoria_id) VALUES (?, ?)");
if (!$stmt) {
    $response['message'] = 'Errore preparazione statement.';
    echo json_encode($response);
    $conn->close();
    exit;
}

$stmt->bind_param("si", $nome, $categoria_id);
if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Prodotto aggiunto con successo';
    $response['id'] = $conn->insert_id;
} else {
    if ($conn->errno == 1062) {
        $response['message'] = 'Prodotto già esistente.';
    } else {
        $response['message'] = 'Errore durante l\'inserimento.';
    }
}
$stmt->close();
$conn->close();

echo json_encode($response);
exit;
?>
