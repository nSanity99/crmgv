<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__.'/../includes/db_config.php';

header('Content-Type: application/json');

// --- Sicurezza ---
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['ruolo'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato.']);
    exit;
}

// --- Input ---
$id_segnalazione = filter_input(INPUT_POST, 'id_segnalazione', FILTER_VALIDATE_INT);
$nuovo_stato = trim(htmlspecialchars($_POST['nuovo_stato'] ?? ''));
$note_interne = trim(htmlspecialchars($_POST['note_interne'] ?? ''));
$messaggio_admin = trim($_POST['messaggio_admin'] ?? '');

$stati_validi = ['Inviata', 'In Lavorazione', 'In Attesa di Risposta', 'Conclusa'];

if (!$id_segnalazione || !in_array($nuovo_stato, $stati_validi)) {
    echo json_encode(['success' => false, 'message' => 'Dati non validi.']);
    exit;
}

// --- Connessione ---
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Errore di connessione al database.']);
    exit;
}

// --- Transazione ---
$conn->begin_transaction();

try {
    // 1. Update segnalazione
    $sql = "UPDATE segnalazioni SET stato = ?, note_interne = ?, data_ultima_modifica = CURRENT_TIMESTAMP WHERE id_segnalazione = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Errore prepare UPDATE: " . $conn->error);

    $stmt->bind_param("ssi", $nuovo_stato, $note_interne, $id_segnalazione);
    if (!$stmt->execute()) throw new Exception("Errore execute UPDATE: " . $stmt->error);
    $stmt->close();

    // 2. Inserimento messaggio admin (se presente)
    if ($messaggio_admin !== '') {
        $id_admin = $_SESSION['user_id'] ?? null;
        if (!$id_admin) throw new Exception("ID admin non disponibile nella sessione.");

        $insert = $conn->prepare("INSERT INTO segnalazioni_chat (id_segnalazione, id_utente, messaggio_admin, data_messaggio) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
        if (!$insert) throw new Exception("Errore prepare INSERT: " . $conn->error);

        $insert->bind_param("iis", $id_segnalazione, $id_admin, $messaggio_admin);
        if (!$insert->execute()) throw new Exception("Errore execute INSERT: " . $insert->error);
        $insert->close();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Segnalazione aggiornata correttamente.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Errore durante il salvataggio: ' . $e->getMessage()
    ]);
}

$conn->close();
exit;
