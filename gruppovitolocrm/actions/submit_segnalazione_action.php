<?php
session_start();
require_once __DIR__.'/../includes/db_config.php';

// Sicurezza e validazione
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../dashboard.php");
    exit;
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    header("Location: ../login.php?error=session_expired");
    exit;
}

// Recupero e pulizia dati
$id_utente = $_SESSION['user_id'];
$nome_utente = (!empty($_SESSION['user_fullname'])) ? trim($_SESSION['user_fullname']) : trim($_SESSION['username']);
$titolo = trim(htmlspecialchars($_POST['titolo'] ?? ''));
$area_competenza = trim(htmlspecialchars($_POST['area_competenza'] ?? ''));
$descrizione = trim(htmlspecialchars($_POST['descrizione'] ?? ''));

// Controllo validità
if (empty($titolo) || empty($area_competenza) || empty($descrizione)) {
    $error_msg = urlencode("Tutti i campi sono obbligatori.");
    header("Location: ../segnalazioni_form.php?status=error&message=$error_msg");
    exit;
}

// Connessione al DB
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    error_log("Errore connessione DB in submit_segnalazione_action: " . $conn->connect_error);
    $error_msg = urlencode("Errore di connessione al sistema.");
    header("Location: ../segnalazioni_form.php?status=error&message=$error_msg");
    exit;
}

// Inserimento dati nella tabella
$sql = "INSERT INTO segnalazioni (id_utente_segnalante, nome_utente_segnalante, titolo, area_competenza, descrizione) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("issss", $id_utente, $nome_utente, $titolo, $area_competenza, $descrizione);
    if ($stmt->execute()) {
        // Successo
        header("Location: ../segnalazioni_form.php?status=success");
    } else {
        // Errore
        error_log("Errore esecuzione statement in submit_segnalazione_action: " . $stmt->error);
        $error_msg = urlencode("Impossibile salvare la segnalazione.");
        header("Location: ../segnalazioni_form.php?status=error&message=$error_msg");
    }
    $stmt->close();
} else {
    error_log("Errore preparazione statement in submit_segnalazione_action: " . $conn->error);
    $error_msg = urlencode("Errore di sistema interno.");
    header("Location: ../segnalazioni_form.php?status=error&message=$error_msg");
}

$conn->close();
exit;
?>