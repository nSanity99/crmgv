<?php
session_start();

require_once __DIR__.'/includes/db_config.php';

// Verifica login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id_ordine = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error || !$id_ordine) {
    header('Location: i_miei_ordini.php?edit=error');
    exit;
}

$stmt_info = $conn->prepare('SELECT nome_richiedente, centro_costo, consenti_modifica, id_utente_richiedente FROM ordini WHERE id_ordine = ?');
$stmt_info->bind_param('i', $id_ordine);
$stmt_info->execute();
$info = $stmt_info->get_result()->fetch_assoc();
$stmt_info->close();

if (!$info || $info['id_utente_richiedente'] != $_SESSION['user_id'] || $info['consenti_modifica'] != 1) {
    $conn->close();
    header('Location: i_miei_ordini.php?edit=error');
    exit;
}

$richiedente_nome = htmlspecialchars($info['nome_richiedente']);
$id_utente_richiedente = $_SESSION['user_id'];
$centro_costo_selezionato = htmlspecialchars($info['centro_costo']);

// Carica prodotti esistenti
$prodotti_esistenti = [];
$stmt_det = $conn->prepare('SELECT nome_prodotto, quantita, unita_misura, note_prodotto FROM dettagli_ordine WHERE id_ordine = ? ORDER BY id_dettaglio_ordine');
$stmt_det->bind_param('i', $id_ordine);
$stmt_det->execute();
$res_det = $stmt_det->get_result();
while ($row = $res_det->fetch_assoc()) {
    $prodotti_esistenti[] = [
        'name' => $row['nome_prodotto'],
        'quantity' => (int)$row['quantita'],
        'unit' => $row['unita_misura'],
        'notes' => $row['note_prodotto'] ?? ''
    ];
}
$stmt_det->close();
$conn->close();
$current_date = date('d/m/Y');

// Opzioni per i dropdown
$centri_di_costo = [
    "CCNord",
    "Contact Centre Sud",
    "CGM",
    "Edil Eboli",
    "Elimar",
    "Il Tulipano",
    "Lac San Luca",
    "La Nona Musa",
    "San Luca Hotel",
    "San Pio",
    "Tenuta Elisa"
];
sort($centri_di_costo);

$unita_di_misura = ["Pezzo", "Cartone", "Scatolo"];

// Caricamento catalogo prodotti con categorie
$catalogo_prodotti = [];
$categorie_lookup = [];
$conn_prod = isset($db_port)
    ? new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port)
    : new mysqli($db_host, $db_user, $db_pass, $db_name);
if (!$conn_prod->connect_error) {
    $sql = "SELECT c.id AS cat_id, c.nome AS categoria, p.nome AS prodotto FROM catalogo_prodotti p JOIN categorie_prodotti c ON p.categoria_id=c.id ORDER BY c.nome ASC, p.nome ASC";
    $res = $conn_prod->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $catalogo_prodotti[$row['cat_id']]['nome'] = $row['categoria'];
            $catalogo_prodotti[$row['cat_id']]['prodotti'][] = $row['prodotto'];
            $categorie_lookup[$row['cat_id']] = $row['categoria'];
        }
        $res->free();
    }
    $conn_prod->close();
}

// Gestione messaggi di feedback
$feedback_message = '';
$feedback_type = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'order_success') {
        $feedback_message = 'Richiesta d\'acquisto inviata con successo!';
        $feedback_type = 'success';
    } elseif ($_GET['status'] === 'order_error') {
        $feedback_message = 'Errore durante l\'invio della richiesta.';
        if (isset($_GET['message'])) {
            $feedback_message .= ' Dettaglio: ' . htmlspecialchars(urldecode($_GET['message']));
        }
        $feedback_type = 'error';
    }
}

// Log
$timestamp = date("Y-m-d H:i:s");
ini_set('log_errors', 1); 
ini_set('error_log', 'C:/xampp/php_error.log');
error_log("--- [{$timestamp}] Accesso a form_page.php UTENTE: " . htmlspecialchars($_SESSION['username']) . " ---");

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica Richiesta - Gruppo Vitolo</title>
    <link rel="stylesheet" href="assets/style.css"> 
    <style>
        html {
            scroll-behavior: smooth; 
        }
        body { 
            background-color: #f8f9fa; 
            color: #495057; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            line-height: 1.6;
            margin: 0; 
            padding: 0; 
            overflow-y: auto; 
            min-height: 100vh; 
        }
        .page-outer-container { 
            max-width: 900px; 
            padding: 0; 
            animation: fadeInSlideUp 0.6s ease-out forwards; 
            margin: 25px auto; 
        }

        .module-header { display: flex; justify-content: space-between; align-items: center; background-color: #ffffff; padding: 18px 30px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); border-bottom: 4px solid #B08D57; margin-bottom: 30px; }
        .header-branding { display: flex; align-items: center; }
        .header-branding .logo { max-height: 45px; margin-right: 15px; display: block; }
        .header-titles h1 { font-size: 1.5em; color: #2E572E; margin: 0; font-weight: 600; line-height: 1.2; }
        .header-titles h2 { font-size: 0.9em; color: #6c757d; margin: 0; font-weight: 400; }

        .user-session-controls { text-align: right; font-size: 0.9em; color: #555; display: flex; align-items: center; gap: 15px; }
        .user-session-controls .user-info span { display: block; line-height: 1.3; }
        .user-session-controls strong { font-weight: 600; color: #333; }
        
        /* STILE AGGIUNTO PER IL PULSANTE DASHBOARD E LOGOUT */
        .nav-link-button,
        .user-session-controls .logout-button { 
            padding: 7px 14px; 
            font-size: 0.9em; 
            color: white !important; 
            text-decoration: none; 
            border-radius: 5px; 
            transition: background-color 0.2s ease, transform 0.2s ease; 
            font-weight: 500; 
        }
        .nav-link-button { background-color: #6c757d; }
        .nav-link-button:hover { background-color: #5a6268; transform: translateY(-1px); }
        .user-session-controls .logout-button { background-color: #D42A2A; }
        .user-session-controls .logout-button:hover { background-color: #c82333; transform: translateY(-1px); }
        
        .module-content { background-color: #ffffff; padding: 25px 30px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
        .form-row { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px; }
        .form-group { flex: 1; min-width: 200px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #495057; font-size:0.95em; }
        .form-group input[type="text"], 
        .form-group input[type="date"], 
        .form-group input[type="number"], 
        .form-group select, 
        .form-group textarea {
            width: 100%; padding: 10px 12px; border: 1px solid #ced4da;
            border-radius: 5px; font-size: 1em; box-sizing: border-box; 
        }
        .form-group input[readonly] { background-color: #e9ecef; cursor: not-allowed; }
        .form-group textarea { min-height: 80px; resize: vertical; }

        .action-button { background-color: #B08D57; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1em; text-decoration: none; display: inline-block; transition: background-color 0.2s ease, transform 0.2s ease; font-weight: 500; margin-top: 10px; }
        .action-button:hover { background-color: #9c7b4c; transform: translateY(-1px); }
        .action-button.add-product { background-color: #2E572E; } .action-button.add-product:hover { background-color: #1e3c1e; }
        .action-button.confirm-product { background-color: #007bff; } .action-button.confirm-product:hover { background-color: #0056b3; }
        .admin-button.secondary { background-color: #6c757d; }
        .admin-button.secondary:hover { background-color: #5a6268; }
        .action-button.submit-order { background-color: #28a745; font-size: 1.1em; padding: 12px 25px;} .action-button.submit-order:hover { background-color: #1e7e34; }

        #product-entry-form { background-color: #f8f9fa; padding: 20px; border: 1px solid #e9ecef; border-radius: 6px; margin-top: 20px; margin-bottom: 20px; }
        #product-entry-form h3 { margin-top:0; margin-bottom:15px; font-size:1.2em; color:#2E572E; }
        #added-products-list { margin-top: 25px; }
        .product-item { background-color: #fdfcf9; border: 1px solid #eee; padding: 10px 15px; border-radius: 5px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; font-size: 0.95em; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .product-item span { margin-right: 10px; } .product-item .product-details { flex-grow: 1; }
        .product-item .product-notes { font-size: 0.85em; color: #6c757d; display: block; margin-top: 3px; }
        .product-item .remove-item-btn { background: none; border: none; color: #D42A2A; cursor: pointer; font-size: 1.1em; padding: 5px; }
        .product-item .remove-item-btn:hover { color: #b02323; }
        .main-form-actions { text-align: right; margin-top: 30px; }
        .feedback-message-container { margin-bottom: 20px; }
        .feedback-message { padding: 12px 15px; border-radius: 5px; font-weight: 500; text-align: center; }
        .feedback-message.success { color: #0f5132; background-color: #d1e7dd; border: 1px solid #badbcc; }
        .feedback-message.error { color: #842029; background-color: #f8d7da; border: 1px solid #f5c2c7; }
        .footer-logo-area { text-align: center; margin-top: 40px; padding-top: 25px; border-top: 1px solid #e9ecef; }
        .footer-logo-area img { max-width: 60px; opacity: 0.5; }
    </style>
</head>
<body>
    <div class="page-outer-container">
        <header class="module-header">
            <div class="header-branding">
                <a href="dashboard.php"> <img src="assets/logo.png" alt="Logo Gruppo Vitolo" class="logo">
                </a>
                <div class="header-titles">
                    <h1>Modifica Richiesta</h1>
                    <h2>Gruppo Vitolo</h2>
                </div>
            </div>
            <div class="user-session-controls">
                <div class="user-info">
                    <span>Accesso come: <strong><?php echo $richiedente_nome; ?></strong></span>
                </div>
                <a href="dashboard.php" class="nav-link-button">Dashboard</a>
                <a href="logout.php" class="logout-button">Logout</a>
            </div>
        </header>
        
        <main class="module-content">
            <?php if ($feedback_message): ?>
                <div class="feedback-message-container">
                    <div class="feedback-message <?php echo $feedback_type; ?>">
                        <?php echo $feedback_message; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form id="main-request-form" action="actions/update_order_action.php" method="POST">
                <input type="hidden" name="id_ordine" value="<?php echo $id_ordine; ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label for="data_richiesta_display">Data Richiesta:</label>
                        <input type="text" id="data_richiesta_display" name="data_richiesta_display" value="<?php echo $current_date; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="richiedente_display">Richiedente:</label>
                        <input type="text" id="richiedente_display" name="richiedente_display" value="<?php echo $richiedente_nome; ?>" readonly>
                        <input type="hidden" name="id_utente_richiedente" value="<?php echo htmlspecialchars($_SESSION['user_id']); ?>">
                        <input type="hidden" name="nome_richiedente" value="<?php echo $richiedente_nome; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex-grow: 2;">
                        <label for="centro_costo">Centro di costo:</label>
                        <select id="centro_costo" name="centro_costo" required>
                            <option value="">Seleziona un centro di costo...</option>
                            <?php foreach ($centri_di_costo as $cdc): ?>
                                <option value="<?php echo htmlspecialchars($cdc); ?>" <?php echo ($cdc == $centro_costo_selezionato) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cdc); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom:15px;">
                    <h3 style="margin:0; font-size:1.3em; color:#2E572E;">Prodotti Richiesti</h3>
                    <button type="button" id="show-product-form-btn" class="action-button add-product">+ Aggiungi Prodotto</button>
                </div>
                
                <div id="product-entry-form" style="display:none;">
                    <h3>Dettaglio Prodotto</h3>
                    <?php if (isset($_SESSION['ruolo']) && $_SESSION['ruolo'] === 'admin'): ?>
                    <div class="form-row">
                        <div class="form-group" style="flex-basis:50%;">
                            <label for="new_product_admin">Nuovo prodotto per catalogo:</label>
                            <input type="text" id="new_product_admin" />
                        </div>
                        <div class="form-group" style="flex-basis:50%;">
                            <label for="new_product_category">Categoria:</label>
                            <select id="new_product_category">
                                <?php foreach ($categorie_lookup as $cid => $cname): ?>
                                    <option value="<?php echo $cid; ?>"><?php echo htmlspecialchars($cname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" id="add-product-admin-btn" class="action-button add-product" style="margin-top:10px;">Aggiungi</button>
                    </div>
                    <?php endif; ?>
                    <div class="form-row">
                        <div class="form-group" style="flex-basis: 100%;">
                            <label for="product_name_select">Prodotto:</label>
                            <select id="product_name_select" name="product_name_select">
                                <option value="">Seleziona prodotto...</option>
                                <?php foreach ($catalogo_prodotti as $cid => $cinfo): ?>
                                    <optgroup label="<?php echo htmlspecialchars($cinfo['nome']); ?>" data-id="<?php echo $cid; ?>">
                                        <?php foreach ($cinfo['prodotti'] as $pr): ?>
                                            <option value="<?php echo htmlspecialchars($pr); ?>" data-cat="<?php echo $cid; ?>"><?php echo htmlspecialchars($pr); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                                <option value="__custom__">Prodotto personalizzato...</option>
                            </select>
                            <input type="text" id="product_name_custom" style="display:none; margin-top:8px;" placeholder="Specificare prodotto" />
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="product_quantity">Quantità:</label>
                            <input type="number" id="product_quantity" name="product_quantity_temp" min="1" max="999">
                        </div>
                        <div class="form-group">
                            <label for="product_unit">Unità di misura:</label>
                            <select id="product_unit" name="product_unit_temp">
                                <?php foreach ($unita_di_misura as $udm): ?>
                                    <option value="<?php echo htmlspecialchars($udm); ?>"><?php echo htmlspecialchars($udm); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="flex-basis: 100%;">
                            <label for="product_notes">Note:</label>
                            <textarea id="product_notes" name="product_notes_temp"></textarea>
                        </div>
                    </div>
                    <button type="button" id="confirm-product-btn" class="action-button confirm-product">Conferma Prodotto</button>
                    <button type="button" id="cancel-product-btn" class="admin-button secondary" style="margin-left:10px;">Annulla</button>
                </div>

                <div id="added-products-list">
                    </div>
                
                <input type="hidden" name="prodotti_json" id="prodotti_json">

                <div class="main-form-actions">
                    <button type="submit" id="submit-order-btn" class="action-button submit-order">Salva Modifiche</button>
                </div>
            </form>
        </main>
        <footer class="footer-logo-area">
            <img src="assets/logo.png" alt="Logo Gruppo Vitolo">
        </footer>
    </div>

<script>
// Il codice Javascript rimane invariato
document.addEventListener('DOMContentLoaded', function() {
    const showProductFormBtn = document.getElementById('show-product-form-btn');
    const productEntryForm = document.getElementById('product-entry-form');
    const confirmProductBtn = document.getElementById('confirm-product-btn');
    const cancelProductBtn = document.getElementById('cancel-product-btn');
    const addedProductsList = document.getElementById('added-products-list');
    const productSelect = document.getElementById('product_name_select');
    const productNameCustom = document.getElementById('product_name_custom');
    const productQuantityInput = document.getElementById('product_quantity');
    const productUnitInput = document.getElementById('product_unit');
    const productNotesInput = document.getElementById('product_notes');
    const prodottiJsonInput = document.getElementById('prodotti_json');
    const addProductAdminBtn = document.getElementById('add-product-admin-btn');
    const newProductAdminInput = document.getElementById('new_product_admin');
    const newProductCategorySelect = document.getElementById('new_product_category');
    let addedProductsArray = <?php echo json_encode($prodotti_esistenti, JSON_UNESCAPED_UNICODE); ?>;

    showProductFormBtn.addEventListener('click', function() {
        productEntryForm.style.display = 'block';
        showProductFormBtn.style.display = 'none';
        productSelect.focus();
        productEntryForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });

    cancelProductBtn.addEventListener('click', function() {
        productEntryForm.style.display = 'none';
        showProductFormBtn.style.display = 'inline-block';
        clearProductForm();
    });

    if (addProductAdminBtn) {
        addProductAdminBtn.addEventListener('click', function() {
            const newName = newProductAdminInput.value.trim();
            if (!newName) {
                alert('Inserire il nome del prodotto da aggiungere.');
                newProductAdminInput.focus();
                return;
            }
            const fd = new FormData();
            fd.append('nome_prodotto', newName);
            fd.append('categoria_id', newProductCategorySelect.value);
            addProductAdminBtn.disabled = true;
            fetch('actions/add_product_action.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const opt = document.createElement('option');
                        opt.value = newName;
                        opt.textContent = newName;
                        opt.setAttribute('data-cat', newProductCategorySelect.value);
                        const group = productSelect.querySelector(`optgroup[data-id="${newProductCategorySelect.value}"]`);
                        if (group) {
                            group.appendChild(opt);
                        } else {
                            productSelect.insertBefore(opt, productSelect.lastElementChild);
                        }
                        productSelect.value = newName;
                        newProductAdminInput.value = '';
                    } else {
                        alert(data.message);
                    }
                })
                .catch(() => alert('Errore di rete durante l\'aggiunta del prodotto.'))
                .finally(() => { addProductAdminBtn.disabled = false; });
        });
    }

    productSelect.addEventListener('change', function() {
        if (productSelect.value === '__custom__') {
            productNameCustom.style.display = 'block';
            productNameCustom.focus();
        } else {
            productNameCustom.style.display = 'none';
            productNameCustom.value = '';
        }
    });

    confirmProductBtn.addEventListener('click', function() {
        let name = '';
        if (productSelect.value === '__custom__') {
            name = productNameCustom.value.trim();
        } else {
            name = productSelect.value.trim();
        }
        const quantity = productQuantityInput.value.trim();
        const unit = productUnitInput.value;
        const notes = productNotesInput.value.trim();

        if (!name) {
            alert('Inserire il nome del prodotto.');
            if (productSelect.value === '__custom__') {
                productNameCustom.focus();
            } else {
                productSelect.focus();
            }
            return;
        }
        if (!quantity || parseInt(quantity) <= 0 || parseInt(quantity) > 999) {
            alert('Inserire una quantità valida (1-999).');
            productQuantityInput.focus();
            return;
        }

        addedProductsArray.push({ name, quantity, unit, notes });
        updateHiddenJsonInput();
        renderAddedProducts();
        
        clearProductForm();
        productEntryForm.style.display = 'none';
        showProductFormBtn.style.display = 'inline-block';
    });

    function clearProductForm() {
        productSelect.value = '';
        productNameCustom.value = '';
        productNameCustom.style.display = 'none';
        productQuantityInput.value = '';
        productUnitInput.value = productUnitInput.options[0].value;
        productNotesInput.value = '';
    }

    function renderAddedProducts() {
        addedProductsList.innerHTML = ''; 
        if (addedProductsArray.length === 0) {
            const noProductsMsg = document.createElement('p');
            noProductsMsg.textContent = 'Nessun prodotto aggiunto alla richiesta.';
            noProductsMsg.style.textAlign = 'center';
            noProductsMsg.style.color = '#6c757d';
            noProductsMsg.style.fontStyle = 'italic';
            noProductsMsg.style.padding = '20px 0';
            addedProductsList.appendChild(noProductsMsg);
            return;
        }

        addedProductsArray.forEach((product, index) => {
            const itemDiv = document.createElement('div');
            itemDiv.classList.add('product-item');
            
            const detailsSpan = document.createElement('span');
            detailsSpan.classList.add('product-details');
            detailsSpan.innerHTML = `<strong>${htmlspecialchars(product.name)}</strong> - ${htmlspecialchars(product.quantity)} ${htmlspecialchars(product.unit)}`;
            if (product.notes) {
                const notesSpan = document.createElement('span');
                notesSpan.classList.add('product-notes');
                notesSpan.textContent = `Note: ${htmlspecialchars(product.notes)}`;
                detailsSpan.appendChild(notesSpan);
            }
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.classList.add('remove-item-btn');
            removeBtn.innerHTML = '&times;';
            removeBtn.title = 'Rimuovi prodotto';
            removeBtn.addEventListener('click', function() {
                addedProductsArray.splice(index, 1);
                updateHiddenJsonInput();
                renderAddedProducts();
            });
            
            itemDiv.appendChild(detailsSpan);
            itemDiv.appendChild(removeBtn);
            addedProductsList.appendChild(itemDiv);
        });
    }
    
    function updateHiddenJsonInput() {
        prodottiJsonInput.value = JSON.stringify(addedProductsArray);
    }

    function htmlspecialchars(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    renderAddedProducts();

    const mainRequestForm = document.getElementById('main-request-form');
    mainRequestForm.addEventListener('submit', function(event) {
        updateHiddenJsonInput(); 
        if (addedProductsArray.length === 0) {
            alert('Aggiungere almeno un prodotto alla richiesta prima di inviare.');
            event.preventDefault();
            return;
        }
        console.log("Tentativo di invio ordine a actions/update_order_action.php...");
    });
});
</script>

</body>
</html>
