<?php
session_start();
require_once __DIR__.'/includes/db_config.php';

// --- Blocco Sicurezza (invariato) ---
ini_set('log_errors', 1); ini_set('error_log', 'C:/xampp/php_error.log'); error_reporting(E_ALL); ini_set('display_errors', 0);
$ruolo_admin_atteso = 'admin';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== $ruolo_admin_atteso) { header("Location: login.php"); exit; }
$username_display_go = htmlspecialchars(isset($_SESSION['username']) ? $_SESSION['username'] : 'N/A');
$user_role_display_go = htmlspecialchars(isset($_SESSION['ruolo']) ? $_SESSION['ruolo'] : 'N/A');

// --- Impostazioni Paginazione (invariato) ---
$ordini_per_pagina = 10;
$pagina_corrente = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($pagina_corrente < 1) { $pagina_corrente = 1; }
$offset = ($pagina_corrente - 1) * $ordini_per_pagina;

// --- Logica Filtri (invariato) ---
$stati_possibili = ['Inviato', 'In Lavorazione', 'Approvato', 'Approvato Parzialmente', 'Rifiutato', 'Evaso'];
$filtro_richiedente = isset($_GET['richiedente']) ? trim($_GET['richiedente']) : '';
$filtro_centro_costo = isset($_GET['centro_costo']) ? trim($_GET['centro_costo']) : '';
$filtro_stato = isset($_GET['stato']) && in_array($_GET['stato'], $stati_possibili) ? $_GET['stato'] : '';
$filtro_data_da = isset($_GET['data_da']) ? $_GET['data_da'] : '';
$filtro_data_a = isset($_GET['data_a']) ? $_GET['data_a'] : '';

// --- Logica Database (invariato) ---
$conn_go = isset($db_port) ? new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port) : new mysqli($db_host, $db_user, $db_pass, $db_name);
$ordini_dal_db = [];
$chat_messaggi = [];
$ordini_modifiche = [];
$db_error_message_go = null;
$totale_ordini = 0;

if ($conn_go->connect_error) {
    $db_error_message_go = "Impossibile connettersi al database.";
} else {
    $sql_where_clause = "";
    $where_conditions = [];
    $params = [];
    $types = "";

    if (!empty($filtro_richiedente)) { $where_conditions[] = "nome_richiedente LIKE ?"; $params[] = "%".$filtro_richiedente."%"; $types .= "s"; }
    if (!empty($filtro_centro_costo)) { $where_conditions[] = "centro_costo LIKE ?"; $params[] = "%".$filtro_centro_costo."%"; $types .= "s"; }
    if (!empty($filtro_stato)) { $where_conditions[] = "stato_ordine = ?"; $params[] = $filtro_stato; $types .= "s"; }
    if (!empty($filtro_data_da)) { $where_conditions[] = "data_richiesta >= ?"; $params[] = $filtro_data_da." 00:00:00"; $types .= "s"; }
    if (!empty($filtro_data_a)) { $where_conditions[] = "data_richiesta <= ?"; $params[] = $filtro_data_a." 23:59:59"; $types .= "s"; }

    if (count($where_conditions) > 0) {
        $sql_where_clause = " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql_count = "SELECT COUNT(*) as total FROM ordini" . $sql_where_clause;
    $stmt_count = $conn_go->prepare($sql_count);
    if ($stmt_count) {
        if (!empty($params)) { $stmt_count->bind_param($types, ...$params); }
        $stmt_count->execute();
        $totale_ordini = $stmt_count->get_result()->fetch_assoc()['total'];
        $stmt_count->close();
    }
    $totale_pagine = ceil($totale_ordini / $ordini_per_pagina);
    if ($pagina_corrente > $totale_pagine && $totale_pagine > 0) {
        $pagina_corrente = $totale_pagine;
        $offset = ($pagina_corrente - 1) * $ordini_per_pagina;
    }

    $sql_base = "SELECT id_ordine, data_richiesta, nome_richiedente, centro_costo, stato_ordine, consenti_modifica FROM ordini" . $sql_where_clause;
    $sql_base .= " ORDER BY CASE stato_ordine WHEN 'Inviato' THEN 1 WHEN 'In Lavorazione' THEN 2 WHEN 'Approvato Parzialmente' THEN 3 WHEN 'Approvato' THEN 4 WHEN 'Rifiutato' THEN 5 WHEN 'Evaso' THEN 6 ELSE 7 END, data_richiesta DESC";
    $sql_base .= " LIMIT ? OFFSET ?";

    $stmt_ordini = $conn_go->prepare($sql_base);
    if ($stmt_ordini) {
        $pag_params = array_merge($params, [$ordini_per_pagina, $offset]);
        $pag_types = $types . "ii";
        
        $stmt_ordini->bind_param($pag_types, ...$pag_params);
        $stmt_ordini->execute();
        $result_ordini = $stmt_ordini->get_result();
        
        while ($ordine_row = $result_ordini->fetch_assoc()) {
            $current_order_id = $ordine_row['id_ordine'];
            $ordine_row['prodotti'] = [];
            $stmt_dettagli = $conn_go->prepare("SELECT id_dettaglio_ordine, nome_prodotto, quantita, unita_misura, note_prodotto, stato_prodotto FROM dettagli_ordine WHERE id_ordine = ? ORDER BY nome_prodotto ASC");
            if ($stmt_dettagli) {
                $stmt_dettagli->bind_param("i", $current_order_id);
                $stmt_dettagli->execute();
                $result_dettagli = $stmt_dettagli->get_result();
                while ($dettaglio_row = $result_dettagli->fetch_assoc()) {
                    $ordine_row['prodotti'][] = $dettaglio_row;
                }
                $stmt_dettagli->close();
            }
            $ordini_dal_db[] = $ordine_row;
        }
        $stmt_ordini->close();

        if (!empty($ordini_dal_db)) {
            $ids = array_column($ordini_dal_db, 'id_ordine');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types_chat = str_repeat('i', count($ids));
            $sql_chat = "SELECT id, id_ordine, messaggio_admin, risposta_utente, data_messaggio, data_risposta FROM ordini_chat WHERE id_ordine IN ($placeholders) ORDER BY data_messaggio ASC";
            $stmt_chat = $conn_go->prepare($sql_chat);
            $stmt_chat->bind_param($types_chat, ...$ids);
            $stmt_chat->execute();
            $res_chat = $stmt_chat->get_result();
            if ($res_chat) {
                while ($row = $res_chat->fetch_assoc()) {
                    $chat_messaggi[$row['id_ordine']][] = $row;
                }
                $res_chat->free();
            }
            $stmt_chat->close();

            // Carica anche le modifiche registrate
            $types_mod = str_repeat('i', count($ids));
            $sql_mod = "SELECT id_ordine, prima, dopo, data_modifica FROM ordini_modifiche WHERE id_ordine IN ($placeholders) ORDER BY data_modifica ASC";
            $stmt_mod = $conn_go->prepare($sql_mod);
            $stmt_mod->bind_param($types_mod, ...$ids);
            $stmt_mod->execute();
            $res_mod = $stmt_mod->get_result();
            if ($res_mod) {
                while ($row = $res_mod->fetch_assoc()) {
                    $ordini_modifiche[$row['id_ordine']][] = $row;
                }
                $res_mod->free();
            }
            $stmt_mod->close();
        }
    } else {
        $db_error_message_go = "Errore durante la preparazione degli ordini.";
    }
    $conn_go->close();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Ordini - Richiesta Acquisti</title>
    <link rel="stylesheet" href="assets/style.css">
    
    <style>
        /* --- Stili Filtri --- */
        .filter-form { background-color: #f8f9fa; padding: 25px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #dee2e6; }
        .filter-form h3 { margin-top: 0; margin-bottom: 20px; border-bottom: 2px solid #0056b3; padding-bottom: 10px; color: #0056b3; font-size: 1.3em;}
        .filter-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { margin-bottom: 8px; font-weight: bold; font-size: 0.9em; color: #343a40; }
        .filter-group input, .filter-group select { padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; transition: border-color 0.2s; }
        .filter-group input:focus, .filter-group select:focus { border-color: #007bff; outline: none; }
        .filter-actions { grid-column: 1 / -1; display: flex; gap: 15px; margin-top: 10px; flex-wrap: wrap; }
        
        /* --- Stili Ordini "Card" --- */
        .order-record {
            background-color: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: box-shadow 0.2s ease-in-out;
            overflow: hidden; /* Necessario per l'animazione */
        }
        .order-record:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .order-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            cursor: pointer;
            flex-wrap: wrap;
            gap: 10px;
        }
        .order-summary-info h3 { margin: 0 0 5px 0; color: #343a40; }
        .order-summary-info span { font-size: 0.9em; color: #6c757d; }
        .order-meta { display: flex; align-items: center; gap: 20px; font-size: 0.9em; color: #343a40; flex-shrink: 0; }
        .order-details {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-in-out, padding 0.4s ease-in-out;
            background-color: #fdfdfd;
            border-top: 1px solid #e9ecef;
            padding: 0 20px;
        }
        .order-record.is-open .order-details {
            max-height: 1000px; /* Valore alto per contenere i prodotti */
            padding: 20px;
        }
        .order-details h4 { margin-top: 0; }

        /* --- Stili Dettagli Prodotto --- */
        .product-detail-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f1f1; gap: 15px; flex-wrap: wrap; }
        .product-detail-item:last-child { border-bottom: none; }
        .product-info strong { color: #0056b3; }
        .product-info .notes { font-style: italic; color: #555; display: block; font-size: 0.85em; margin-top: 3px;}
        .product-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }
        .admin-button.small { padding: 5px 10px; font-size: 0.8em; }

        /* --- Badge di stato (invariati ma confermati) --- */
        .status-badge { padding: 5px 12px; border-radius: 15px; font-size: 0.8em; font-weight: bold; color: white; text-align: center; min-width: 120px; display: inline-block; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-badge.inviato { background-color: #007bff; }
        .status-badge.in-lavorazione { background-color: #17a2b8; }
        .status-badge.approvato { background-color: #28a745; }
        .status-badge.approvato-parzialmente { background-color: #ffc107; color: #212529; }
        .status-badge.rifiutato { background-color: #dc3545; }
        .status-badge.evaso { background-color: #6c757d; }
        .status-prodotto { font-weight: bold; font-size: 0.9em; }
        .status-prodotto.approvato { color: #28a745; }
        .status-prodotto.rifiutato { color: #dc3545; }
        .status-prodotto.inviato { color: #007bff; }

        /* Stili chat analoghi alle segnalazioni */
        .chat-messages { display: flex; flex-direction: column; gap: 8px; max-height: 300px; overflow-y: auto; margin-bottom: 15px; padding-right: 10px; }
        .chat-message { display: flex; }
        .chat-message.admin { justify-content: flex-start; }
        .chat-message.user { justify-content: flex-end; }
        .bubble { max-width: 75%; padding: 10px 14px; border-radius: 12px; font-size: 0.95em; line-height: 1.4; border: 1px solid transparent; }
        .chat-message.admin .bubble { background-color: #e9f5ff; border-color: #c3ddf2; border-top-left-radius: 0; color: #034a73; }
        .chat-message.user .bubble { background-color: #f0fdf4; border-color: #cde7d8; border-top-right-radius: 0; color: #1b4332; }
        .bubble time { display: block; font-size: 0.75em; color: #6c757d; margin-top: 6px; text-align: right; }
        
        /* --- Paginazione (invariata) --- */
        .pagination-controls { display: flex; justify-content: center; align-items: center; padding: 20px 0; gap: 5px; flex-wrap: wrap; }
        .pagination-controls a, .pagination-controls span { display: inline-block; padding: 8px 12px; border: 1px solid #dee2e6; background-color: #fff; color: #007bff; text-decoration: none; border-radius: 4px; margin-bottom: 5px; }
        .pagination-controls a:hover { background-color: #e9ecef; }
        .pagination-controls span.current-page { background-color: #007bff; color: white; border-color: #007bff; font-weight: bold; z-index: 2; }
        .pagination-controls span.disabled { color: #6c757d; background-color: #f8f9fa; cursor: not-allowed; }

        /* --- Media Queries per Responsività --- */
        @media (max-width: 1024px) {
            .filter-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .filter-grid { grid-template-columns: 1fr; }
            .order-summary, .product-detail-item { flex-direction: column; align-items: flex-start; }
            .order-meta { margin-top: 10px; }
        }
    </style>
</head>
<body>
    
    <div class="module-page-container">
        <header class="page-header">
            <div class="header-branding">
                <a href="dashboard.php" class="header-logo-link"><img src="assets/logo.png" alt="Logo Gruppo Vitolo" class="logo"></a>
                <div class="header-titles">
                    <h1>Gestione Ordini</h1>
                    <h2>Applicazione Richiesta Acquisti</h2>
                </div>
            </div>
            <div class="user-session-controls">
                <div class="user-info">
                    <span><strong><?php echo $username_display_go; ?></strong> (<?php echo $user_role_display_go; ?>)</span>
                </div>
                <a href="dashboard.php" class="nav-link-button">Dashboard</a>
                <a href="logout.php" class="logout-button">Logout</a>
            </div>
        </header>
        
        <main class="module-content">
            
            <form action="gestioneordini.php" method="get" class="filter-form">
                <h3>Filtra Ordini</h3>
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="richiedente">Richiedente</label>
                        <input type="text" id="richiedente" name="richiedente" value="<?php echo htmlspecialchars($filtro_richiedente); ?>" placeholder="Nome o parte del nome...">
                    </div>
                    <div class="filter-group">
                        <label for="centro_costo">Centro di Costo</label>
                        <input type="text" id="centro_costo" name="centro_costo" value="<?php echo htmlspecialchars($filtro_centro_costo); ?>" placeholder="Cantiere, ufficio...">
                    </div>
                    <div class="filter-group">
                        <label for="stato">Stato Ordine</label>
                        <select id="stato" name="stato">
                            <option value="">Tutti gli stati</option>
                            <?php foreach ($stati_possibili as $stato): ?>
                                <option value="<?php echo $stato; ?>" <?php if ($filtro_stato === $stato) echo 'selected'; ?>>
                                    <?php echo $stato; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="data_da">Dal Giorno</label>
                        <input type="date" id="data_da" name="data_da" value="<?php echo htmlspecialchars($filtro_data_da); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="data_a">Al Giorno</label>
                        <input type="date" id="data_a" name="data_a" value="<?php echo htmlspecialchars($filtro_data_a); ?>">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="admin-button approve">Applica Filtri</button>
                        <a href="gestioneordini.php" class="admin-button secondary">Resetta Filtri</a>
                        <?php
                        $export_params = $_GET;
                        unset($export_params['page']);
                        $export_query = http_build_query($export_params);
                        if ($totale_ordini > 0) {
                             echo '<a href="export_csv.php?report=gestione&' . $export_query . '" class="admin-button" style="background-color: #1D6F42;" target="_blank">Esporta Filtri in CSV</a>';
                        }
                        ?>
                    </div>
                </div>
            </form>

            <div class="app-section-header">
                <h2>Ordini Ricevuti (<?php echo $totale_ordini; ?> totali)</h2>
            </div>

            <div class="orders-list" id="orders-list-container">
                <?php if (!empty($ordini_dal_db)): ?>
                    <?php foreach ($ordini_dal_db as $ordine): ?>
                        <div class="order-record" id="order-<?php echo $ordine['id_ordine']; ?>">
                            <div class="order-summary" data-order-id="<?php echo $ordine['id_ordine']; ?>">
                                <div class="order-summary-info">
                                    <h3>Ordine #<?php echo htmlspecialchars($ordine['id_ordine']); ?> - <?php echo htmlspecialchars($ordine['centro_costo']); ?></h3>
                                    <span>Richiedente: <?php echo htmlspecialchars($ordine['nome_richiedente']); ?></span>
                                </div>
                                <div class="order-meta">
                                    <span>Data: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($ordine['data_richiesta']))); ?></span>
                                    <?php $status_class = strtolower(str_replace(' ', '-', $ordine['stato_ordine'])); ?>
                                    <span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($ordine['stato_ordine']); ?></span>
                                </div>
                            </div>
                            <div class="order-details">
                                <h4>Dettaglio Prodotti:</h4>
                                <?php if ($ordine['consenti_modifica'] == 0 && $ordine['stato_ordine'] !== 'Evaso'): ?>
                                    <button type="button" class="admin-button small unlock-order-btn" data-order-id="<?php echo $ordine['id_ordine']; ?>">Sblocca per Modifica</button>
                                <?php elseif ($ordine['consenti_modifica'] == 1): ?>
                                    <p style="color:#0d6efd;font-weight:bold;">Ordine modificabile dall'utente.</p>
                                <?php endif; ?>
                                <?php if (!empty($ordine['prodotti'])): ?>
                                    <?php foreach ($ordine['prodotti'] as $prodotto): ?>
                                        <div class="product-detail-item" data-product-id="<?php echo $prodotto['id_dettaglio_ordine']; ?>">
                                            <div class="product-info">
                                                <strong><?php echo htmlspecialchars($prodotto['nome_prodotto']); ?></strong>
                                                (Quantità: <?php echo htmlspecialchars($prodotto['quantita']); ?> <?php echo htmlspecialchars($prodotto['unita_misura']); ?>)
                                                <span class="status-prodotto <?php echo strtolower($prodotto['stato_prodotto']); ?>">
                                                    (Stato: <?php echo htmlspecialchars($prodotto['stato_prodotto']); ?>)
                                                </span>
                                                <?php if (!empty($prodotto['note_prodotto'])): ?>
                                                    <span class="notes">Note: <?php echo htmlspecialchars($prodotto['note_prodotto']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="product-actions">
                                                <?php if (strtolower($prodotto['stato_prodotto']) === 'inviato'): ?>
                                                    <button type="button" class="admin-button approve product-action-btn" data-action="approve" data-order-id="<?php echo $ordine['id_ordine']; ?>" data-detail-id="<?php echo $prodotto['id_dettaglio_ordine']; ?>">✓ Approva</button>
                                                    <button type="button" class="admin-button reject product-action-btn" data-action="reject" data-order-id="<?php echo $ordine['id_ordine']; ?>" data-detail-id="<?php echo $prodotto['id_dettaglio_ordine']; ?>">✗ Rifiuta</button>
                                                <?php else: ?>
                                                    <span class="status-prodotto <?php echo strtolower(htmlspecialchars($prodotto['stato_prodotto'])); ?>"><strong><?php echo htmlspecialchars($prodotto['stato_prodotto']); ?></strong></span>
                                                    <button type="button" class="admin-button secondary small edit-status-btn" data-action="reset" data-order-id="<?php echo $ordine['id_ordine']; ?>" data-detail-id="<?php echo $prodotto['id_dettaglio_ordine']; ?>">Modifica</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>Nessun prodotto in questo ordine.</p>
                                <?php endif; ?>

                               <?php if (!empty($chat_messaggi[$ordine['id_ordine']])): ?>
                                    <div class="chat-messages">
                                        <?php foreach ($chat_messaggi[$ordine['id_ordine']] as $msg): ?>
                                            <div class="chat-message admin">
                                                <div class="bubble">
                                                    <p><?php echo nl2br(htmlspecialchars($msg['messaggio_admin'])); ?></p>
                                                    <time><?php echo date('d/m H:i', strtotime($msg['data_messaggio'])); ?></time>
                                                </div>
                                            </div>
                                            <?php if (!empty($msg['risposta_utente'])): ?>
                                                <div class="chat-message user">
                                                    <div class="bubble">
                                                        <p><?php echo nl2br(htmlspecialchars($msg['risposta_utente'])); ?></p>
                                                        <time><?php echo date('d/m H:i', strtotime($msg['data_risposta'])); ?></time>
                                                    </div>
                                                </div>
                               <?php endif; ?>

                                <?php if (!empty($ordini_modifiche[$ordine['id_ordine']])): ?>
                                    <div class="order-modifications" style="margin-top:10px;">
                                        <h5>Modifiche effettuate:</h5>
                                        <ul>
                                            <?php foreach ($ordini_modifiche[$ordine['id_ordine']] as $mod): ?>
                                                <li>
                                                    <em><?php echo date('d/m/Y H:i', strtotime($mod['data_modifica'])); ?></em><br>
                                                    <strong>Prima:</strong> <?php echo htmlspecialchars($mod['prima']); ?><br>
                                                    <strong>Dopo:</strong> <?php echo htmlspecialchars($mod['dopo']); ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <form class="management-form">
                                    <input type="hidden" name="id_ordine" value="<?php echo $ordine['id_ordine']; ?>">
                                    <div class="form-group">
                                        <label for="messaggio_admin-<?php echo $ordine['id_ordine']; ?>">Nuovo Messaggio per l'Utente:</label>
                                        <textarea id="messaggio_admin-<?php echo $ordine['id_ordine']; ?>" name="messaggio_admin"></textarea>
                                    </div>
                                    <button type="button" class="nav-link-button update-chat-btn">Invia</button>
                                    <span class="update-feedback" style="margin-left:10px;color:green;font-weight:bold;"></span>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; padding: 30px; color: #6c757d; font-style: italic;">Nessun ordine trovato con i filtri applicati. Prova a modificare la ricerca.</p>
                <?php endif; ?>
            </div>

            <?php if ($totale_pagine > 1): ?>
            <nav class="pagination-controls" aria-label="Navigazione pagine">
                <?php
                    $query_params = $_GET;
                    unset($query_params['page']);
                    $base_url = http_build_query($query_params);
                    $url_prefix = 'gestioneordini.php?' . (!empty($base_url) ? $base_url . '&' : '');
                ?>
                <?php if ($pagina_corrente > 1): ?><a href="<?php echo $url_prefix; ?>page=<?php echo $pagina_corrente - 1; ?>">&laquo; Prec</a><?php else: ?><span class="disabled">&laquo; Prec</span><?php endif; ?>
                <?php for ($i = 1; $i <= $totale_pagine; $i++): ?>
                    <?php if ($i == $pagina_corrente): ?><span class="current-page"><?php echo $i; ?></span><?php else: ?><a href="<?php echo $url_prefix; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a><?php endif; ?>
                <?php endfor; ?>
                <?php if ($pagina_corrente < $totale_pagine): ?><a href="<?php echo $url_prefix; ?>page=<?php echo $pagina_corrente + 1; ?>">Succ &raquo;</a><?php else: ?><span class="disabled">Succ &raquo;</span><?php endif; ?>
            </nav>
            <?php endif; ?>
            
        </main>
        <footer class="footer-logo-area">
            <img src="assets/logo.png" alt="Logo Gruppo Vitolo">
        </footer>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
        const ordersListContainer = document.getElementById('orders-list-container');

    if (ordersListContainer) {
        ordersListContainer.addEventListener('click', function(event) {
            const target = event.target;
            
            // Logica per aprire/chiudere i dettagli dell'ordine
            const summary = target.closest('.order-summary');
            if (summary) {
                const orderRecord = summary.closest('.order-record');
                if (orderRecord) {
                    // Chiudi tutti gli altri ordini aperti prima di aprirne uno nuovo
                    document.querySelectorAll('.order-record.is-open').forEach(openRecord => {
                        if (openRecord !== orderRecord) {
                            openRecord.classList.remove('is-open');
                        }
                    });
                    // Apri o chiudi l'ordine cliccato
                    orderRecord.classList.toggle('is-open');
                }
            }

            // Logica per i pulsanti di azione sul prodotto (invariata nel funzionamento)
            if (target.classList.contains('product-action-btn') || target.classList.contains('edit-status-btn')) {
                event.stopPropagation(); // Evita che il click si propaghi e chiuda/apra l'ordine
                handleProductAction(target);
            }

            if (target.classList.contains('unlock-order-btn')) {
                event.stopPropagation();
                unlockOrder(target);
            }

            const chatBtn = target.closest('.update-chat-btn');
            if (chatBtn) {
                event.stopPropagation();
                handleChatSubmit(chatBtn);
            }
        });
    }

    function handleProductAction(button) {
        const actionContainer = button.parentElement;
        actionContainer.querySelectorAll('button').forEach(btn => btn.disabled = true);
        const originalButtonText = button.innerHTML;
        button.innerHTML = '...';

        const action = button.dataset.action;
        const orderId = button.dataset.orderId;
        const detailId = button.dataset.detailId;
        
        const formData = new FormData();
        formData.append('action', action);
        formData.append('order_id', orderId);
        formData.append('detail_id', detailId);

        fetch('actions/order_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.ok ? response.json() : Promise.reject('Errore di rete'))
        .then(data => {
            if (data.success) {
                if (action === 'reset') {
                    revertUiToChoice(actionContainer, orderId, detailId);
                } else {
                    updateUiAfterAction(actionContainer, data.newState, orderId, detailId);
                }
                updateOrderStatusBadge(actionContainer, data.newOrderStatus);
            } else {
                alert('Errore: ' + data.message);
                button.innerHTML = originalButtonText; // Ripristina solo il pulsante cliccato
                actionContainer.querySelectorAll('button').forEach(btn => btn.disabled = false);
            }
        })
        .catch(error => {
            console.error('Errore AJAX:', error);
            alert('Si è verificato un errore di comunicazione con il server.');
            button.innerHTML = originalButtonText;
             actionContainer.querySelectorAll('button').forEach(btn => btn.disabled = false);
        });
    }
    
    function updateUiAfterAction(actionContainer, newState, orderId, detailId) {
        const productItemDiv = actionContainer.closest('.product-detail-item');
        const statusSpan = productItemDiv.querySelector('.product-info .status-prodotto');
        statusSpan.textContent = `(Stato: ${newState})`;
        statusSpan.className = `status-prodotto ${newState.toLowerCase()}`;
        
        actionContainer.innerHTML = `
            <span class='status-prodotto ${newState.toLowerCase()}'><strong>${newState}</strong></span>
            <button type="button" class="admin-button secondary small edit-status-btn" data-action="reset" data-order-id="${orderId}" data-detail-id="${detailId}">Modifica</button>
        `;
    }

    function revertUiToChoice(actionContainer, orderId, detailId) {
        const productItemDiv = actionContainer.closest('.product-detail-item');
        const statusSpan = productItemDiv.querySelector('.product-info .status-prodotto');
        statusSpan.textContent = `(Stato: Inviato)`;
        statusSpan.className = `status-prodotto inviato`;
        
        actionContainer.innerHTML = `
            <button type="button" class="admin-button approve product-action-btn" data-action="approve" data-order-id="${orderId}" data-detail-id="${detailId}">✓ Approva</button>
            <button type="button" class="admin-button reject product-action-btn" data-action="reject" data-order-id="${orderId}" data-detail-id="${detailId}">✗ Rifiuta</button>
        `;
    }

    function updateOrderStatusBadge(actionContainer, newOrderStatus) {
        const orderRecordDiv = actionContainer.closest('.order-record');
        const orderStatusBadge = orderRecordDiv.querySelector('.order-summary .status-badge');
        if (orderStatusBadge && newOrderStatus) {
            orderStatusBadge.textContent = newOrderStatus;
            orderStatusBadge.className = 'status-badge ' + newOrderStatus.toLowerCase().replace(/ /g, '-');
        }
    }

    function unlockOrder(button) {
        const orderId = button.dataset.orderId;
        button.textContent = '...';
        button.disabled = true;
        const fd = new FormData();
        fd.append('id_ordine', orderId);
        fetch('actions/unlock_order_action.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    button.outerHTML = '<p style="color:#0d6efd;font-weight:bold;">Ordine modificabile dall\'utente.</p>';
                } else {
                    alert(data.message);
                    button.textContent = 'Sblocca per Modifica';
                    button.disabled = false;
                }
            })
            .catch(() => { alert('Errore di rete.'); button.textContent = 'Sblocca per Modifica'; button.disabled = false; });
    }

    function handleChatSubmit(button) {
        const form = button.closest('.management-form');
        const id = form.querySelector('input[name="id_ordine"]').value;
        const msg = form.querySelector('textarea[name="messaggio_admin"]').value;
        const feedback = form.querySelector('.update-feedback');
        button.textContent = '...';
        button.disabled = true;
        const fd = new FormData();
        fd.append('id_ordine', id);
        fd.append('messaggio_admin', msg);
        fetch('actions/update_order_chat_action.php', {
            method: 'POST',
            body: fd
        }).then(r => r.json())
        .then(data => {
            if (data.success) {
                feedback.textContent = 'Inviato!';
                setTimeout(() => { feedback.textContent = ''; }, 2000);
                form.querySelector('textarea[name="messaggio_admin"]').value = '';
            } else {
                alert('Errore: ' + data.message);
            }
        }).catch(err => { console.error(err); alert('Errore di comunicazione.'); })
        .finally(() => { button.textContent = 'Invia'; button.disabled = false; });
    }
});
</script>

</body>
</html>