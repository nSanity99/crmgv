<?php
session_start();
require_once __DIR__.'/includes/db_config.php';

// --- Sicurezza ---
$ruolo_admin_atteso = 'admin';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== $ruolo_admin_atteso) {
    die("Accesso negato.");
}

// Determina quale report generare
$report_type = $_GET['report'] ?? '';
if (!in_array($report_type, ['gestione', 'riepilogo'])) {
    die("Tipo di report non valido.");
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port ?? 3306);
if ($conn->connect_error) {
    die("Errore di connessione al database: " . $conn->connect_error);
}

// Prepara il nome del file e gli header
$filename = "report.csv";
$headers = [];
$data_rows = [];

// ===================================================================
// Logica per il report da "GESTIONE ORDINI"
// ===================================================================
if ($report_type === 'gestione') {
    $filename = "Report_Gestione_Ordini_" . date('Y-m-d') . ".csv";
    $headers = ['ID Ordine', 'Data Richiesta', 'Richiedente', 'Centro di Costo', 'Stato Ordine', 'Nome Prodotto', 'Quantita', 'Unita', 'Note', 'Stato Prodotto', 'Data Evasione'];
    
    // Ricostruisci la query di gestioneordini.php
    $sql = "SELECT o.id_ordine, o.data_richiesta, o.nome_richiedente, o.centro_costo, o.stato_ordine, do.nome_prodotto, do.quantita, do.unita_misura, do.note_prodotto, do.stato_prodotto, do.data_evasione FROM ordini o LEFT JOIN dettagli_ordine do ON o.id_ordine = do.id_ordine";
    $filtro_richiedente = $_GET['richiedente'] ?? '';
    // ... Aggiungi qui tutti gli altri filtri da gestioneordini.php come li avevi...
    // Per semplicità, qui metto solo un esempio, ma dovresti copiare la logica completa
    if (!empty($filtro_richiedente)) {
        $sql .= " WHERE o.nome_richiedente LIKE ?";
        $params[] = "%".$filtro_richiedente."%";
        $types = "s";
    }
    $sql .= " ORDER BY o.id_ordine DESC, do.nome_prodotto ASC";

    $stmt = $conn->prepare($sql);
    if($stmt && (!empty($params) ? $stmt->bind_param($types, ...$params) : true) && $stmt->execute()) {
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) { $data_rows[] = $row; }
    }
}

// ===================================================================
// Logica per il report da "RIEPILOGO ORDINI EVASI"
// ===================================================================
if ($report_type === 'riepilogo') {
    $filename = "Report_Prodotti_Evasi_" . date('Y-m-d') . ".csv";
    $headers = ['ID Ordine', 'Data Richiesta Orig.', 'Richiedente', 'Centro di Costo', 'Nome Prodotto', 'Quantita', 'Unita', 'Note', 'Data Evasione', 'Approvato da'];
    
    // Ricostruisci la query di riepilogo_ordini.php
    $sql = "SELECT o.id_ordine, o.data_richiesta, o.nome_richiedente, o.centro_costo, do.nome_prodotto, do.quantita, do.unita_misura, do.note_prodotto, do.data_evasione, u.username as admin_username FROM dettagli_ordine do JOIN ordini o ON do.id_ordine = o.id_ordine LEFT JOIN utenti u ON do.id_utente_decisione = u.id WHERE do.stato_prodotto = 'Evaso'";
    $start_date_filter = $_GET['start_date'] ?? '';
    $end_date_filter = $_GET['end_date'] ?? '';
    $params = [];
    $types = '';
    if (!empty($start_date_filter)) { $sql .= " AND do.data_evasione >= ?"; $params[] = $start_date_filter . ' 00:00:00'; $types .= 's'; }
    if (!empty($end_date_filter)) { $sql .= " AND do.data_evasione <= ?"; $params[] = $end_date_filter . ' 23:59:59'; $types .= 's'; }
    $sql .= " ORDER BY do.data_evasione DESC";

    $stmt = $conn->prepare($sql);
    if($stmt && (!empty($params) ? $stmt->bind_param($types, ...$params) : true) && $stmt->execute()) {
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) { $data_rows[] = $row; }
    }
}

$conn->close();

// --- Forza il download del file CSV ---
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Crea un puntatore al file di output
$output = fopen('php://output', 'w');

// Scrivi l'intestazione
fputcsv($output, $headers, ';'); // Uso il punto e virgola come separatore, spesso meglio per l'Excel italiano

// Scrivi le righe di dati
foreach ($data_rows as $row) {
    fputcsv($output, $row, ';');
}

fclose($output);
exit;
?>