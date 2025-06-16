<?php
// File: order_actions.php
session_start();
require_once __DIR__.'/../includes/db_config.php';

ob_clean(); 
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Errore sconosciuto.'];

// --- Sicurezza e Validazione Iniziale ---
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

$action = isset($_POST['action']) ? $_POST['action'] : '';
$detail_id = isset($_POST['detail_id']) ? filter_var($_POST['detail_id'], FILTER_VALIDATE_INT) : null;
$order_id = isset($_POST['order_id']) ? filter_var($_POST['order_id'], FILTER_VALIDATE_INT) : null;
$id_utente_decisione = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if (!$detail_id || !$order_id || !$id_utente_decisione || !in_array($action, ['approve', 'reject', 'reset'])) {
    $response['message'] = "Dati mancanti o azione non valida. Azione ricevuta: {$action}";
    ob_end_clean();
    echo json_encode($response);
    exit;
}

// --- Logica di Aggiornamento Database ---
$conn = isset($db_port) 
        ? new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port) 
        : new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    $response['message'] = 'Errore di connessione al database.';
    error_log("[order_actions.php] Errore connessione DB: " . $conn->connect_error);
    ob_end_clean();
    echo json_encode($response);
    exit;
}

$conn->begin_transaction();
try {
    
    // Logica differenziata per azione
    $nuovo_stato_prodotto = '';
    if ($action === 'approve' || $action === 'reject') {
        $nuovo_stato_prodotto = ($action === 'approve') ? 'Approvato' : 'Rifiutato';
        $data_decisione = date('Y-m-d H:i:s');
        
        // Modificato per permettere l'aggiornamento anche da stati diversi da 'Inviato' (utile se si cambia idea)
        $stmt_dettaglio = $conn->prepare("UPDATE dettagli_ordine SET stato_prodotto = ?, id_utente_decisione = ?, data_decisione = ? WHERE id_dettaglio_ordine = ?");
        if (!$stmt_dettaglio) throw new Exception("DB Error (prepare detail): ".$conn->error);
        $stmt_dettaglio->bind_param("sisi", $nuovo_stato_prodotto, $id_utente_decisione, $data_decisione, $detail_id);
        
    } elseif ($action === 'reset') {
        $nuovo_stato_prodotto = 'Inviato';
        
        $stmt_dettaglio = $conn->prepare("UPDATE dettagli_ordine SET stato_prodotto = ?, id_utente_decisione = NULL, data_decisione = NULL WHERE id_dettaglio_ordine = ?");
        if (!$stmt_dettaglio) throw new Exception("DB Error (prepare reset): ".$conn->error);
        $stmt_dettaglio->bind_param("si", $nuovo_stato_prodotto, $detail_id);
    }
    
    $stmt_dettaglio->execute();
    $stmt_dettaglio->close();
    
    // =======================================================================
    // --- LOGICA DI AGGIORNAMENTO STATO ORDINE (CORRETTA) ---
    // =======================================================================
    $stmt_check = $conn->prepare("
        SELECT
            COUNT(*) as total_items,
            SUM(CASE WHEN stato_prodotto = 'Inviato' THEN 1 ELSE 0 END) as pending_items,
            SUM(CASE WHEN stato_prodotto = 'Approvato' THEN 1 ELSE 0 END) as approved_items
        FROM dettagli_ordine
        WHERE id_ordine = ?
    ");
    if (!$stmt_check) throw new Exception("DB Error (prepare check): ".$conn->error);
    $stmt_check->bind_param("i", $order_id);
    $stmt_check->execute();
    $counts = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    $nuovo_stato_ordine = 'In Lavorazione'; // Stato di default

    if ($counts) {
        $total = (int)$counts['total_items'];
        $pending = (int)$counts['pending_items'];
        $approved = (int)$counts['approved_items'];
        
        // Calcoliamo i rifiutati. Se pending e approved non fanno il totale, il resto sono rifiutati.
        $rejected = $total - $pending - $approved;

        if ($pending > 0) {
            // Se ci sono ancora prodotti in attesa
            if ($pending == $total) {
                $nuovo_stato_ordine = 'Inviato'; // Tutti i prodotti sono stati resettati a 'Inviato'
            } else {
                $nuovo_stato_ordine = 'In Lavorazione'; // Alcuni prodotti processati, altri no
            }
        } else {
            // Se non ci sono più prodotti 'Inviato', la revisione è completa.
            // La logica qui è stata resa più robusta.
            if ($approved == $total) {
                $nuovo_stato_ordine = 'Approvato'; // Esattamente TUTTI approvati
            } elseif ($rejected == $total) {
                $nuovo_stato_ordine = 'Rifiutato'; // Esattamente TUTTI rifiutati
            } else {
                // Se non sono tutti approvati né tutti rifiutati, allora è per forza un mix.
                $nuovo_stato_ordine = 'Approvato Parzialmente';
            }
        }
    }

    $stmt_ordine = $conn->prepare("UPDATE ordini SET stato_ordine = ? WHERE id_ordine = ?");
    if (!$stmt_ordine) throw new Exception("DB Error (prepare order update): ".$conn->error);
    $stmt_ordine->bind_param("si", $nuovo_stato_ordine, $order_id);
    $stmt_ordine->execute();
    $stmt_ordine->close();

    $conn->commit();
    $response = [
        'success' => true,
        'message' => 'Stato aggiornato con successo.',
        'newState' => $nuovo_stato_prodotto,
        'newOrderStatus' => $nuovo_stato_ordine
    ];

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
    error_log("[order_actions.php] ERRORE TRANSAZIONE: " . $e->getMessage());
} finally {
    if ($conn) $conn->close();
}

ob_end_clean();
echo json_encode($response);
exit;
?>