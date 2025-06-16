<?php
session_start();
require_once __DIR__.'/includes/db_config.php';

// --- Blocco Sicurezza ---
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php_error.log');
error_reporting(E_ALL);
ini_set('display_errors', 0);
$ruolo_admin_atteso = 'admin';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== $ruolo_admin_atteso) {
    header("Location: login.php");
    exit;
}
$username_display_eo = htmlspecialchars(isset($_SESSION['username']) ? $_SESSION['username'] : 'N/A');
$user_role_display_eo = htmlspecialchars(isset($_SESSION['ruolo']) ? $_SESSION['ruolo'] : 'N/A');

// --- Logica PHP per caricare i prodotti DA EVADERE (dal vecchio file funzionante) ---
$conn_eo = isset($db_port)
           ? new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port)
           : new mysqli($db_host, $db_user, $db_pass, $db_name);

$ordini_da_evadere = [];
$db_error_message_eo = null;
if ($conn_eo->connect_error) {
    $db_error_message_eo = "Impossibile connettersi al database.";
} else {
    // 1. Selezioniamo solo gli ordini che hanno prodotti approvati in attesa
    $sql_ordini_evasione = "SELECT id_ordine, data_richiesta, nome_richiedente, centro_costo, stato_ordine
                            FROM ordini
                            WHERE stato_ordine IN ('Approvato', 'Approvato Parzialmente')
                            ORDER BY data_richiesta ASC";
    
    $result_ordini = $conn_eo->query($sql_ordini_evasione);
    if ($result_ordini) {
        while ($ordine_row = $result_ordini->fetch_assoc()) {
            $current_order_id = $ordine_row['id_ordine'];
            $ordine_row['prodotti'] = []; 
            
            // 2. Per ogni ordine, selezioniamo SOLO i prodotti 'Approvati'
            $stmt_dettagli = $conn_eo->prepare("SELECT id_dettaglio_ordine, nome_prodotto, quantita, unita_misura, note_prodotto 
                                                FROM dettagli_ordine 
                                                WHERE id_ordine = ? AND stato_prodotto = 'Approvato'");
            if ($stmt_dettagli) {
                $stmt_dettagli->bind_param("i", $current_order_id);
                $stmt_dettagli->execute();
                $result_dettagli = $stmt_dettagli->get_result();
                $ordine_row['prodotti'] = $result_dettagli->fetch_all(MYSQLI_ASSOC);
                $stmt_dettagli->close();
            }
            
            // 3. Aggiungiamo l'ordine alla lista solo se ha effettivamente prodotti da evadere
            if (!empty($ordine_row['prodotti'])) {
                $ordini_da_evadere[] = $ordine_row;
            }
        }
        $result_ordini->free();
    } else {
        $db_error_message_eo = "Errore durante il caricamento degli ordini da evadere.";
    }
    $conn_eo->close();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evasione Ordini - Richiesta Acquisti</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Stili CSS moderni dal nuovo file */
        html { box-sizing: border-box; }
        *, *:before, *:after { box-sizing: inherit; }
        body { background-color: #f8f9fa; color: #495057; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
        .module-page-container { max-width: 1200px; margin: 25px auto 40px auto; padding: 0 15px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; background-color: #ffffff; padding: 18px 30px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); border-bottom: 4px solid #B08D57; margin-bottom: 30px; }
        .header-branding { display: flex; align-items: center; gap: 15px; }
        .header-branding .logo { max-height: 45px; }
        .header-titles h1 { font-size: 1.5em; color: #2E572E; margin: 0; }
        .header-titles h2 { font-size: 0.9em; color: #6c757d; margin: 0; }
        .user-session-controls { display: flex; align-items: center; gap: 15px; }
        .admin-button, .nav-link-button, .logout-button { text-decoration: none; padding: 8px 15px; border-radius: 5px; color: white !important; font-weight: 500; transition: background-color 0.2s ease; border: none; cursor: pointer; }
        .admin-button.approve { background-color: #28a745; }
        .admin-button.approve:hover { background-color: #218838; }
        .nav-link-button { background-color: #6c757d; }
        .logout-button { background-color: #D42A2A; }
        .module-content { background-color: #ffffff; padding: 25px 30px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
        .app-section-header { margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #e9ecef; }
        .app-section-header h2 { font-size: 1.4em; color: #2E572E; margin: 0; }
        
        /* Stili per la fisarmonica (accordion) */
        .order-record { border: 1px solid #e9ecef; margin-bottom: 20px; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 5px rgba(0,0,0,0.04); }
        .order-summary { display: flex; justify-content: space-between; align-items: center; padding: 18px 50px 18px 20px; background-color: #f8f9fa; cursor: pointer; position: relative; }
        .order-summary h3 { margin: 0; font-size: 1.1em; color: #2E572E; }
        .order-summary .order-meta { font-size: 0.9em; color: #555; }
        .order-summary::after { content: '▼'; font-family: 'Segoe UI Symbol'; position: absolute; right: 20px; top: 50%; transform: translateY(-50%); transition: transform 0.3s ease; }
        .order-record.is-open .order-summary::after { transform: translateY(-50%) rotate(180deg); }
        .order-details { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out, padding 0.4s ease-out; padding: 0 20px; }
        .order-record.is-open .order-details { max-height: 1500px; padding: 20px 20px 30px 20px; }

        .product-detail-item { padding: 12px 0; border-bottom: 1px solid #f1f1f1; display: flex; justify-content: space-between; align-items: center; }
        .product-detail-item:last-child { border-bottom: none; }
        .product-info strong { color: #343a40; }
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
                    <h1>Evasione Ordini</h1>
                    <h2>Applicazione Richiesta Acquisti</h2>
                </div>
            </div>
            <div class="user-session-controls">
                <div class="user-info">
                    <span><strong><?php echo $username_display_eo; ?></strong> (<?php echo $user_role_display_eo; ?>)</span>
                </div>
                <a href="dashboard.php" class="nav-link-button">Dashboard</a>
                <a href="logout.php" class="logout-button">Logout</a>
            </div>
        </header>
        
        <main class="module-content">
            <div class="app-section-header">
                <h2>Prodotti Approvati in Attesa di Evasione</h2>
            </div>

            <div class="orders-list" id="orders-list-container">
                <?php if ($db_error_message_eo): ?>
                    <p class="error-message"><?php echo htmlspecialchars($db_error_message_eo); ?></p>
                <?php elseif (!empty($ordini_da_evadere)): ?>
                    <?php foreach ($ordini_da_evadere as $ordine): ?>
                        <div class="order-record" id="order-record-<?php echo $ordine['id_ordine']; ?>">
                            <div class="order-summary">
                                <div>
                                    <h3>Ordine #<?php echo htmlspecialchars($ordine['id_ordine']); ?> - <?php echo htmlspecialchars($ordine['centro_costo']); ?></h3>
                                    <span class="order-meta">Richiedente: <?php echo htmlspecialchars($ordine['nome_richiedente']); ?></span>
                                </div>
                            </div>
                            <div class="order-details">
                                <h4>Prodotti da evadere per questo ordine:</h4>
                                <?php foreach ($ordine['prodotti'] as $prodotto): ?>
                                    <div class="product-detail-item" id="product-item-<?php echo $prodotto['id_dettaglio_ordine']; ?>">
                                        <div class="product-info">
                                            <strong><?php echo htmlspecialchars($prodotto['nome_prodotto']); ?></strong>
                                            (Quantità: <?php echo htmlspecialchars($prodotto['quantita']); ?> <?php echo htmlspecialchars($prodotto['unita_misura']); ?>)
                                        </div>
                                        <div class="product-actions">
                                            <button type="button" class="admin-button approve evade-product-btn" data-detail-id="<?php echo $prodotto['id_dettaglio_ordine']; ?>">✓ Evadi Prodotto</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; padding: 30px; color: #6c757d; font-style: italic;">Nessun prodotto approvato da evadere al momento.</p>
                <?php endif; ?>
            </div>
        </main>
        <footer class="footer-logo-area">
            <img src="assets/logo.png" alt="Logo Gruppo Vitolo">
        </footer>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ordersContainer = document.getElementById('orders-list-container');

    ordersContainer.addEventListener('click', function(event) {
        
        // --- Logica per la fisarmonica (accordion) ---
        const summary = event.target.closest('.order-summary');
        if (summary) {
            const orderRecord = summary.closest('.order-record');
            if (orderRecord) {
                // Chiudi tutte le altre tendine prima di aprirne una nuova
                document.querySelectorAll('.order-record.is-open').forEach(openRecord => {
                    if (openRecord !== orderRecord) {
                        openRecord.classList.remove('is-open');
                    }
                });
                orderRecord.classList.toggle('is-open');
            }
        }

        // --- Logica per gestire il click sul bottone "Evadi" ---
        const evadeButton = event.target.closest('.evade-product-btn');
        if (evadeButton) {
            const detailId = evadeButton.dataset.detailId;
            const productItemDiv = evadeButton.closest('.product-detail-item');
            const productName = productItemDiv.querySelector('.product-info strong').textContent;

            if (confirm(`Sei sicuro di voler contrassegnare il prodotto "${productName}" come Evaso?`)) {
                
                evadeButton.textContent = '...';
                evadeButton.disabled = true;

                const formData = new FormData();
                formData.append('detail_id', detailId);

                fetch('actions/evade_order_action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Animazione di scomparsa del prodotto
                        productItemDiv.style.transition = 'opacity 0.5s ease, max-height 0.5s ease, padding 0.5s ease, margin 0.5s ease';
                        productItemDiv.style.opacity = '0';
                        productItemDiv.style.maxHeight = '0';
                        productItemDiv.style.padding = '0';
                        productItemDiv.style.margin = '0';
                        productItemDiv.style.border = 'none';

                        setTimeout(() => {
                            const orderDetailsDiv = productItemDiv.parentElement;
                            productItemDiv.remove();
                            
                            // Se non ci sono più prodotti, rimuovi l'intera card dell'ordine
                            if (orderDetailsDiv.querySelectorAll('.product-detail-item').length === 0) {
                                const orderRecordDiv = orderDetailsDiv.closest('.order-record');
                                orderRecordDiv.style.transition = 'opacity 0.5s ease';
                                orderRecordDiv.style.opacity = '0';
                                setTimeout(() => { 
                                    orderRecordDiv.remove();
                                    // Se non ci sono più ordini, mostra il messaggio
                                    if(document.querySelectorAll('.order-record').length === 0) {
                                        ordersContainer.innerHTML = '<p style="text-align:center; padding: 30px; color: #6c757d; font-style: italic;">Nessun prodotto approvato da evadere al momento.</p>';
                                    }
                                }, 500);
                            }
                        }, 500); // Aspetta la fine dell'animazione
                    } else {
                        alert('Errore: ' + data.message);
                        evadeButton.textContent = '✓ Evadi Prodotto';
                        evadeButton.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Errore:', error);
                    alert('Si è verificato un errore di comunicazione con il server.');
                    evadeButton.textContent = '✓ Evadi Prodotto';
                    evadeButton.disabled = false;
                });
            }
        }
    });

    // Apri di default la prima tendina se esiste
    const firstOrder = document.querySelector('.order-record');
    if(firstOrder) {
        firstOrder.classList.add('is-open');
    }
});
</script>

</body>
</html>