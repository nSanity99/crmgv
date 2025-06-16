<?php
session_start();
require_once __DIR__.'/includes/db_config.php';

// --- Sicurezza ---
ini_set('log_errors', 1); ini_set('error_log', 'C:/xampp/php_error.log'); error_reporting(E_ALL); ini_set('display_errors', 0);
$ruolo_admin_atteso = 'admin';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== $ruolo_admin_atteso) { header("Location: login.php"); exit; }
$username_display_ro = htmlspecialchars($_SESSION['username'] ?? 'N/A');
$user_role_display_ro = htmlspecialchars($_SESSION['ruolo'] ?? 'N/A');

// --- Logica Filtri e Recupero Dati ---
$conn_ro = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port ?? 3306);
$ordini_finali = [];
$db_error_message_ro = null;
$start_date_filter = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date_filter = isset($_GET['end_date']) ? $_GET['end_date'] : '';

if ($conn_ro->connect_error) {
    $db_error_message_ro = "Impossibile connettersi al database.";
} else {
    // 1. TROVA GLI ORDINI CHE CONTENGONO PRODOTTI EVASI
    $sql_ordini = "SELECT DISTINCT o.id_ordine, o.data_richiesta, o.nome_richiedente, o.centro_costo, o.fattura_file FROM ordini o JOIN dettagli_ordine do ON o.id_ordine = do.id_ordine WHERE do.stato_prodotto = 'Evaso'";
    $params = [];
    $types = '';
    if (!empty($start_date_filter)) { $sql_ordini .= " AND do.data_evasione >= ?"; $params[] = $start_date_filter . ' 00:00:00'; $types .= 's'; }
    if (!empty($end_date_filter)) { $sql_ordini .= " AND do.data_evasione <= ?"; $params[] = $end_date_filter . ' 23:59:59'; $types .= 's'; }
    $sql_ordini .= " ORDER BY o.data_richiesta DESC";
    $stmt_ordini = $conn_ro->prepare($sql_ordini);
    
    if($stmt_ordini) {
        if(!empty($types)) { $stmt_ordini->bind_param($types, ...$params); }
        $stmt_ordini->execute();
        $result_ordini = $stmt_ordini->get_result();

        // 2. PER OGNI ORDINE, CARICA I SUOI PRODOTTI EVASI
        while ($dati_ordine = $result_ordini->fetch_assoc()) {
            $current_order_id = $dati_ordine['id_ordine'];
            $dati_ordine['prodotti'] = [];
            $sql_prodotti = "SELECT do.id_dettaglio_ordine, do.nome_prodotto, do.quantita, do.unita_misura, do.note_prodotto, do.data_evasione, u.username as admin_username FROM dettagli_ordine do LEFT JOIN utenti u ON do.id_utente_decisione = u.id WHERE do.id_ordine = ? AND do.stato_prodotto = 'Evaso' ORDER BY do.nome_prodotto";
            $stmt_prodotti = $conn_ro->prepare($sql_prodotti);
            if($stmt_prodotti) {
                $stmt_prodotti->bind_param('i', $current_order_id);
                $stmt_prodotti->execute();
                $result_prodotti = $stmt_prodotti->get_result();
                $dati_ordine['prodotti'] = $result_prodotti->fetch_all(MYSQLI_ASSOC);
                $stmt_prodotti->close();
            }
            $ordini_finali[] = $dati_ordine;
        }
        $stmt_ordini->close();
    }
    $conn_ro->close();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riepilogo Ordini Evasi</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Ripristino lo stile grafico che ti piace */
        html { box-sizing: border-box; }
        *, *:before, *:after { box-sizing: inherit; }
        body { background-color: #f8f9fa; color: #495057; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
        .module-page-container { max-width: 1200px; margin: 25px auto; padding: 0 15px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; background-color: #ffffff; padding: 18px 30px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); border-bottom: 4px solid #B08D57; margin-bottom: 30px; }
        .header-branding { display: flex; align-items: center; gap: 15px; }
        .header-branding .logo { max-height: 45px; }
        .header-titles h1 { font-size: 1.5em; color: #2E572E; margin: 0; }
        .header-titles h2 { font-size: 0.9em; color: #6c757d; margin: 0; }
        .user-session-controls { display: flex; align-items: center; gap: 15px; }
        .admin-button, .nav-link-button, .logout-button { text-decoration: none; padding: 8px 15px; border-radius: 5px; color: white !important; font-weight: 500; transition: background-color 0.2s ease; border: none; cursor: pointer; }
        .admin-button.approve, .admin-button { background-color: #B08D57; }
        .admin-button.secondary, .nav-link-button { background-color: #6c757d; }
        .logout-button { background-color: #D42A2A; }
        .module-content { background-color: #ffffff; padding: 25px 30px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
        .app-section-header { margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #e9ecef; }
        .app-section-header h2 { font-size: 1.4em; color: #2E572E; margin: 0; }
        .filter-form { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #dee2e6; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { margin-bottom: 5px; font-weight: bold; font-size: 0.9em; }
        .filter-group input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .order-record { border: 1px solid #e9ecef; margin-bottom: 15px; border-radius: 6px; overflow: hidden; }
        .order-summary { display: flex; justify-content: space-between; align-items: center; padding: 15px 50px 15px 20px; background-color: #f8f9fa; cursor: pointer; position: relative; transition: background-color 0.2s ease; }
        .order-summary:hover { background-color: #e9ecef; }
        .order-summary h3 { margin: 0; font-size: 1.1em; }
        .order-summary .order-meta { font-size: 0.9em; color: #555; text-align: right; }
        
        /* --- CSS CORRETTO PER L'ANIMAZIONE --- */
        .order-summary::after { content: '▼'; font-family: 'Segoe UI Symbol'; position: absolute; right: 20px; top: 50%; transform: translateY(-50%); transition: transform 0.3s ease; }
        .order-record.active .order-summary::after { transform: translateY(-50%) rotate(180deg); }
        .order-details {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-out, padding 0.4s ease-out;
        }
        .order-record.active .order-details {
            max-height: 1000px; /* Valore alto per permettere l'espansione */
            padding: 20px;
        }
        /* --- Fine CSS Animazione --- */
        
        .product-detail-item { padding: 10px 0; border-bottom: 1px solid #f1f1f1; display: flex; justify-content: space-between; align-items: center; }
        .product-detail-item:last-child { border-bottom: none; }
        .product-info .notes { display: block; font-size: 0.9em; color: #6c757d; font-style: italic; }
        .product-meta { text-align: right; font-size: 0.9em; color: #555; }
        .invoice-section { margin-top: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .invoice-section form { display: inline-block; }
        .footer-logo-area { text-align: center; margin-top: 40px; padding-top: 25px; border-top: 1px solid #e9ecef; }
        .footer-logo-area img { max-width: 60px; opacity: 0.5; }
    </style>
</head>
<body>
    <div class="module-page-container">
        <header class="page-header">
            <div class="header-branding">
                <a href="dashboard.php" class="header-logo-link"><img src="assets/logo.png" alt="Logo Gruppo Vitolo" class="logo"></a>
                <div class="header-titles">
                    <h1>Report Ordini Evasi</h1>
                    <h2>Applicazione Richiesta Acquisti</h2>
                </div>
            </div>
            <div class="user-session-controls">
                <span><strong><?php echo $username_display_ro; ?></strong> (<?php echo $user_role_display_ro; ?>)</span>
                <a href="dashboard.php" class="nav-link-button">Dashboard</a>
                <a href="logout.php" class="logout-button">Logout</a>
            </div>
        </header>
        
        <main class="module-content">
            <form action="riepilogo_ordini.php" method="get" class="filter-form">
                 <div class="filter-grid">
                    <div class="filter-group">
                        <label for="start_date">Da Data Evasione</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date_filter); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="end_date">A Data Evasione</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date_filter); ?>">
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <div>
                            <button type="submit" class="admin-button approve">Filtra</button>
                            <a href="riepilogo_ordini.php" class="admin-button secondary">Reset</a>
                        </div>
                    </div>
                </div>
            </form>

            <div class="app-section-header">
                <h2>Ordini con Prodotti Evasi</h2>
            </div>

            <div class="orders-list">
                <?php if (!empty($ordini_finali)): ?>
                    <?php foreach ($ordini_finali as $ordine): ?>
                        <div class="order-record">
                            <div class="order-summary">
                                <div>
                                    <h3>Ordine #<?php echo htmlspecialchars($ordine['id_ordine']); ?> - <?php echo htmlspecialchars($ordine['centro_costo']); ?></h3>
                                    <span style="font-size:0.9em; color:#555;">Richiedente: <?php echo htmlspecialchars($ordine['nome_richiedente']); ?></span>
                                </div>
                                <div class="order-meta">
                                    <span>Data Richiesta Orig.: <?php echo htmlspecialchars(date('d/m/Y', strtotime($ordine['data_richiesta']))); ?></span>
                                </div>
                            </div>
                            <div class="order-details">
                                <h4>Dettaglio Prodotti Evasi:</h4>
                                <?php if (!empty($ordine['prodotti'])): ?>
                                    <?php foreach ($ordine['prodotti'] as $prodotto): ?>
                                        <div class="product-detail-item">
                                            <div class="product-info">
                                                <strong><?php echo htmlspecialchars($prodotto['nome_prodotto']); ?></strong>
                                                (Quantità: <?php echo htmlspecialchars($prodotto['quantita']); ?> <?php echo htmlspecialchars($prodotto['unita_misura']); ?>)
                                                <?php if (!empty($prodotto['note_prodotto'])): ?>
                                                    <span class="notes">Note: <?php echo htmlspecialchars($prodotto['note_prodotto']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="product-meta">
                                                <div>Approvato da: <strong><?php echo htmlspecialchars($prodotto['admin_username'] ?? 'N/D'); ?></strong></div>
                                                <div>Evaso il: <strong><?php echo $prodotto['data_evasione'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($prodotto['data_evasione']))) : '-'; ?></strong></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <div class="invoice-section">
                                    <?php if (!empty($ordine['fattura_file'])): ?>
                                        <p>Fattura: <a href="fatture/<?php echo rawurlencode($ordine['fattura_file']); ?>" target="_blank" class="nav-link-button">Download</a></p>
                                        <form class="invoice-form" enctype="multipart/form-data" method="POST" action="actions/upload_fattura_action.php">
                                            <input type="hidden" name="id_ordine" value="<?php echo $ordine['id_ordine']; ?>">
                                            <input type="file" name="fattura" accept="application/pdf">
                                            <button type="submit" class="admin-button secondary small">Aggiorna</button>
                                        </form>
                                        <form class="delete-invoice-form" method="POST" action="actions/delete_fattura_action.php" onsubmit="return confirm('Eliminare la fattura?');">
                                            <input type="hidden" name="id_ordine" value="<?php echo $ordine['id_ordine']; ?>">
                                            <button type="submit" class="admin-button secondary small">Elimina</button>
                                        </form>
                                    <?php else: ?>
                                        <form class="invoice-form" enctype="multipart/form-data" method="POST" action="actions/upload_fattura_action.php">
                                            <input type="hidden" name="id_ordine" value="<?php echo $ordine['id_ordine']; ?>">
                                            <input type="file" name="fattura" accept="application/pdf" required>
                                            <button type="submit" class="admin-button secondary small">Carica Fattura</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; padding: 30px; color: #6c757d; font-style: italic;">Nessun ordine con prodotti evasi trovato.</p>
                <?php endif; ?>
            </div>
        </main>
        <footer class="footer-logo-area">
            <img src="assets/logo.png" alt="Logo Gruppo Vitolo">
        </footer>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.order-summary').forEach(summary => {
            summary.addEventListener('click', function() {
                const orderRecord = this.closest('.order-record');
                orderRecord.classList.toggle('active');
            });
        });

        document.querySelectorAll('.invoice-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const fd = new FormData(form);
                fetch('actions/upload_fattura_action.php', { method: 'POST', body: fd })
                    .then(r => r.json()).then(() => location.reload());
            });
        });

        document.querySelectorAll('.delete-invoice-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                if (!confirm('Eliminare la fattura?')) return;
                const fd = new FormData(form);
                fetch('actions/delete_fattura_action.php', { method: 'POST', body: fd })
                    .then(r => r.json()).then(() => location.reload());
            });
        });
    });
</script>
</body>
</html>