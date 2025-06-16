<?php
session_start();
require_once __DIR__.'/../includes/db_config.php';

ob_clean();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Errore sconosciuto.'];

// Sicurezza...
$ruolo_admin_atteso = 'admin';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== $ruolo_admin_atteso) {
    $response['message'] = 'Accesso non autorizzato.';
    ob_end_clean();
    echo json_encode($response);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $response['message'] = 'Metodo di richiesta non valido.';
    ob_end_clean();
    echo json_encode($response);
    exit;
}

$detail_id = isset($_POST['detail_id']) ? filter_var($_POST['detail_id'], FILTER_VALIDATE_INT) : null;
if (!$detail_id) {
    $response['message'] = 'ID dettaglio mancante.';
    ob_end_clean();
    echo json_encode($response);
    exit;
}

$conn = isset($db_port) ? new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port) : new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    $response['message'] = 'Errore di connessione al database.';
    ob_end_clean();
    echo json_encode($response);
    exit;
}

$conn->begin_transaction();
try {
    // Aggiorna lo stato del prodotto E LA SUA DATA DI EVASIONE
    $stmt_evadi = $conn->prepare("UPDATE dettagli_ordine SET stato_prodotto = 'Evaso', data_evasione = NOW() WHERE id_dettaglio_ordine = ? AND stato_prodotto = 'Approvato'");
    if (!$stmt_evadi) throw new Exception("DB Error (prepare evade): " . $conn->error);
    $stmt_evadi->bind_param("i", $detail_id);
    $stmt_evadi->execute();

    if ($stmt_evadi->affected_rows === 0) {
        throw new Exception("Impossibile evadere il prodotto. Potrebbe essere già stato evaso o non è in stato 'Approvato'.");
    }
    
    // Il resto della logica per aggiornare lo stato dell'ordine generale...
    $stmt_get_order = $conn->prepare("SELECT id_ordine FROM dettagli_ordine WHERE id_dettaglio_ordine = ?");
    if (!$stmt_get_order) throw new Exception("DB Error (get order ID): " . $conn->error);
    $stmt_get_order->bind_param("i", $detail_id);
    $stmt_get_order->execute();
    $order_id_result = $stmt_get_order->get_result()->fetch_assoc();
    $order_id = $order_id_result['id_ordine'];
    $stmt_get_order->close();
    $stmt_evadi->close();

    if ($order_id) {
        $stmt_check_status = $conn->prepare("SELECT COUNT(*) as pending_count FROM dettagli_ordine WHERE id_ordine = ? AND stato_prodotto NOT IN ('Evaso', 'Rifiutato')");
        if (!$stmt_check_status) throw new Exception("DB Error (check status): " . $conn->error);
        $stmt_check_status->bind_param("i", $order_id);
        $stmt_check_status->execute();
        $status_result = $stmt_check_status->get_result()->fetch_assoc();
        $stmt_check_status->close();
        
        if ($status_result['pending_count'] == 0) {
            $stmt_complete_order = $conn->prepare("UPDATE ordini SET stato_ordine = 'Evaso' WHERE id_ordine = ?");
            if (!$stmt_complete_order) throw new Exception("DB Error (complete order): " . $conn->error);
            $stmt_complete_order->bind_param("i", $order_id);
            $stmt_complete_order->execute();
            $stmt_complete_order->close();
        }
    }
    
    $conn->commit();
    $response = ['success' => true, 'message' => 'Prodotto evaso con successo.'];

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
} finally {
    if ($conn) $conn->close();
}

ob_end_clean();
echo json_encode($response);
exit;
?>