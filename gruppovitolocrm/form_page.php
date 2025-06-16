<?php
session_start();
require_once __DIR__.'/includes/db_config.php';

// Verifica se l'utente è loggato, altrimenti reindirizza alla pagina di login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Preparazione dati per il form
$current_date = date('d/m/Y');
$richiedente_nome = (!empty($_SESSION['user_fullname'])) ? htmlspecialchars($_SESSION['user_fullname']) : htmlspecialchars($_SESSION['username']);
$id_utente_richiedente = $_SESSION['user_id'];

// Opzioni per i dropdown
$centri_di_costo = ["CCNord", "Contact Centre Sud", "CGM", "Edil Eboli", "Elimar", "Il Tulipano", "Lac San Luca", "La Nona Musa", "San Luca Hotel", "San Pio", "Tenuta Elisa"];
sort($centri_di_costo);
$unita_di_misura = ["Pezzo", "Cartone", "Scatolo", "Blister", "Risma", "Busta"];

// ## PREPARAZIONE DATI OTTIMIZZATA PER RICERCA E DROPDOWN ##
$catalogo_flat = [];
$catalogo_strutturato = []; // NUOVO: Array strutturato per le dropdown Categoria->Prodotti
$categorie_lookup = [];

$conn_prod = isset($db_port)
    ? new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port)
    : new mysqli($db_host, $db_user, $db_pass, $db_name);

if (!$conn_prod->connect_error) {
    // Prima prendo le categorie per il lookup del form admin
    $res_cat = $conn_prod->query("SELECT id, nome FROM categorie_prodotti ORDER BY nome ASC");
    if($res_cat) {
        while($row = $res_cat->fetch_assoc()) {
            $categorie_lookup[$row['id']] = $row['nome'];
        }
        $res_cat->free();
    }

    // Poi creo sia la lista "piatta" sia quella strutturata
    // MODIFICATO: Aggiunto ORDER BY c.nome per raggruppare correttamente
    $sql = "SELECT p.nome AS prodotto, c.nome AS categoria 
            FROM catalogo_prodotti p 
            JOIN categorie_prodotti c ON p.categoria_id = c.id 
            ORDER BY c.nome ASC, p.nome ASC";
            
    $res = $conn_prod->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            // 1. Popolo la lista piatta per la ricerca live (invariato)
            $catalogo_flat[] = [
                'nome' => $row['prodotto'],
                'categoria' => $row['categoria']
            ];
            // 2. NUOVO: Popolo l'array strutturato per le dropdown
            $catalogo_strutturato[$row['categoria']][] = $row['prodotto'];
        }
        $res->free();
    }
    $conn_prod->close();
}

// Gestione messaggi di feedback (invariato)
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
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modulo Richiesta Acquisti - Gruppo Vitolo</title>
    <link rel="stylesheet" href="assets/style.css"> 
    <style>
        html { scroll-behavior: smooth; }
        body { background-color: #f8f9fa; color: #495057; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; margin: 0; padding: 0; overflow-y: auto; min-height: 100vh; }
        .page-outer-container { max-width: 900px; padding: 0; animation: fadeInSlideUp 0.6s ease-out forwards; margin: 25px auto; }
        .module-header { display: flex; justify-content: space-between; align-items: center; background-color: #ffffff; padding: 18px 30px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); border-bottom: 4px solid #B08D57; margin-bottom: 30px; }
        .header-branding { display: flex; align-items: center; }
        .header-branding .logo { max-height: 45px; margin-right: 15px; display: block; }
        .header-titles h1 { font-size: 1.5em; color: #2E572E; margin: 0; font-weight: 600; line-height: 1.2; }
        .header-titles h2 { font-size: 0.9em; color: #6c757d; margin: 0; font-weight: 400; }
        .user-session-controls { text-align: right; font-size: 0.9em; color: #555; display: flex; align-items: center; gap: 15px; }
        .user-session-controls .user-info span { display: block; line-height: 1.3; }
        .user-session-controls strong { font-weight: 600; color: #333; }
        .nav-link-button, .user-session-controls .logout-button { padding: 7px 14px; font-size: 0.9em; color: white !important; text-decoration: none; border-radius: 5px; transition: background-color 0.2s ease, transform 0.2s ease; font-weight: 500; }
        .nav-link-button { background-color: #6c757d; }
        .nav-link-button:hover { background-color: #5a6268; transform: translateY(-1px); }
        .user-session-controls .logout-button { background-color: #D42A2A; }
        .user-session-controls .logout-button:hover { background-color: #c82333; transform: translateY(-1px); }
        .module-content { background-color: #ffffff; padding: 25px 30px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
        .form-row { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px; }
        .form-group { flex: 1; min-width: 200px; position: relative; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #495057; font-size:0.95em; }
        .form-group input[type="text"], .form-group input[type="date"], .form-group input[type="number"], .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 5px; font-size: 1em; box-sizing: border-box; }
        .form-group input[readonly], .form-group select:disabled { background-color: #e9ecef; cursor: not-allowed; }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .action-button { background-color: #B08D57; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1em; text-decoration: none; display: inline-block; transition: background-color 0.2s ease, transform 0.2s ease; font-weight: 500; margin-top: 10px; }
        .action-button:hover { background-color: #9c7b4c; transform: translateY(-1px); }
        .action-button.add-product { background-color: #2E572E; } .action-button.add-product:hover { background-color: #1e3c1e; }
        .action-button.confirm-product { background-color: #007bff; } .action-button.confirm-product:hover { background-color: #0056b3; }
        .admin-button.secondary { background-color: #6c757d; }
        .admin-button.secondary:hover { background-color: #5a6268; }
        .action-button.submit-order { background-color: #28a745; font-size: 1.1em; padding: 12px 25px;} .action-button.submit-order:hover { background-color: #1e7e34; }
        #product-search-results { display: none; position: absolute; top: 100%; left: 0; right: 0; background-color: white; border: 1px solid #ddd; border-top: none; border-radius: 0 0 5px 5px; max-height: 250px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .search-result-item { padding: 10px 15px; cursor: pointer; border-bottom: 1px solid #f0f0f0; }
        .search-result-item:last-child { border-bottom: none; }
        .search-result-item:hover { background-color: #f5f5f5; }
        .search-result-item small { color: #6c757d; margin-left: 8px; font-style: italic; }
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
                <a href="dashboard.php"><img src="assets/logo.png" alt="Logo Gruppo Vitolo" class="logo"></a>
                <div class="header-titles">
                    <h1>Modulo Richiesta Acquisti</h1>
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

            <form id="main-request-form" action="actions/submit_order_action.php" method="POST">
                
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
                                <option value="<?php echo htmlspecialchars($cdc); ?>"><?php echo htmlspecialchars($cdc); ?></option>
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
                        <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group" style="flex-basis: 100%;">
                            <label for="product-search-input">Cerca Prodotto per Nome:</label>
                            <input type="text" id="product-search-input" placeholder="Inizia a scrivere per cercare nel catalogo..." autocomplete="off">
                            <div id="product-search-results"></div>
                        </div>
                    </div>
                    
                    <p style="text-align:center; margin: 20px 0; font-weight: 500; color: #6c757d;">-- OPPURE --</p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category-select">1. Seleziona Categoria:</label>
                            <select id="category-select">
                                <option value="">Scegli una categoria...</option>
                                <?php foreach (array_keys($catalogo_strutturato) as $categoria): ?>
                                    <option value="<?php echo htmlspecialchars($categoria); ?>"><?php echo htmlspecialchars($categoria); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="product-select-by-category">2. Seleziona Prodotto:</label>
                            <select id="product-select-by-category" disabled>
                                <option value="">Prima scegli la categoria</option>
                            </select>
                        </div>
                    </div>

                    <hr style="border: 0; border-top: 1px dashed #ced4da; margin: 15px 0 25px;">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="product_quantity">Quantità:</label>
                            <input type="number" id="product_quantity" min="1" max="999">
                        </div>
                        <div class="form-group">
                            <label for="product_unit">Unità di misura:</label>
                            <select id="product_unit">
                                <?php foreach ($unita_di_misura as $udm): ?>
                                    <option value="<?php echo htmlspecialchars($udm); ?>"><?php echo htmlspecialchars($udm); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="flex-basis: 100%;">
                            <label for="product_notes">Note:</label>
                            <textarea id="product_notes"></textarea>
                        </div>
                    </div>
                    
                    <button type="button" id="confirm-product-btn" class="action-button confirm-product">Conferma Prodotto</button>
                    <button type="button" id="cancel-product-btn" class="admin-button secondary" style="margin-left:10px;">Annulla</button>
                </div>

                <div id="added-products-list"></div>
                
                <input type="hidden" name="prodotti_json" id="prodotti_json">

                <div class="main-form-actions">
                    <button type="submit" id="submit-order-btn" class="action-button submit-order">Invia Richiesta</button> 
                </div>
            </form>
            </main>
        
        <footer class="footer-logo-area">
            <img src="assets/logo.png" alt="Logo Gruppo Vitolo">
        </footer>
    </div>
</body>

<script>
// ## INIZIO MODIFICA JAVASCRIPT ##
// Passiamo entrambi gli array di prodotti a JavaScript
const catalogoFlatJS = <?php echo json_encode($catalogo_flat); ?>;
const catalogoStrutturatoJS = <?php echo json_encode($catalogo_strutturato); ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Riferimenti agli elementi del form (alcuni nuovi)
    const productSearchInput = document.getElementById('product-search-input');
    const productSearchResults = document.getElementById('product-search-results');
    const categorySelect = document.getElementById('category-select'); // Nuovo
    const productSelectByCategory = document.getElementById('product-select-by-category'); // Nuovo

    let selectedProduct = null;

    const showProductFormBtn = document.getElementById('show-product-form-btn');
    const productEntryForm = document.getElementById('product-entry-form');
    const confirmProductBtn = document.getElementById('confirm-product-btn');
    const cancelProductBtn = document.getElementById('cancel-product-btn');
    const addedProductsList = document.getElementById('added-products-list');
    const productQuantityInput = document.getElementById('product_quantity');
    const productUnitInput = document.getElementById('product_unit');
    const productNotesInput = document.getElementById('product_notes');
    const prodottiJsonInput = document.getElementById('prodotti_json');
    let addedProductsArray = [];

    // --- LOGICA ESISTENTE: Ricerca testuale (leggermente adattata) ---
    productSearchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        productSearchResults.innerHTML = '';
        selectedProduct = null;
        
        // Se si usa la ricerca, si deselezionano le dropdown per evitare conflitti
        categorySelect.value = '';
        productSelectByCategory.innerHTML = '<option value="">Prima scegli la categoria</option>';
        productSelectByCategory.disabled = true;

        if (searchTerm.length < 2) {
            productSearchResults.style.display = 'none';
            return;
        }

        const filteredProducts = catalogoFlatJS.filter(p => 
            p.nome.toLowerCase().includes(searchTerm)
        );

        if (filteredProducts.length > 0) {
            filteredProducts.forEach(product => {
                const item = document.createElement('div');
                item.classList.add('search-result-item');
                item.innerHTML = `${product.nome} <small>${product.categoria}</small>`;
                item.addEventListener('click', () => {
                    productSearchInput.value = product.nome;
                    // L'oggetto `product` qui ha già la struttura {nome: '...', categoria: '...'}
                    selectedProduct = product; 
                    productSearchResults.style.display = 'none';
                });
                productSearchResults.appendChild(item);
            });
            productSearchResults.style.display = 'block';
        } else {
            productSearchResults.style.display = 'none';
        }
    });

    // --- NUOVA LOGICA: Dropdown Categoria -> Prodotto ---
    categorySelect.addEventListener('change', function() {
        const selectedCat = this.value;
        productSelectByCategory.innerHTML = '<option value="">Seleziona un prodotto...</option>'; // Reset
        productSelectByCategory.disabled = true;
        
        // Se si usano le dropdown, si pulisce la ricerca testuale
        productSearchInput.value = '';
        selectedProduct = null;

        if (selectedCat && catalogoStrutturatoJS[selectedCat]) {
            catalogoStrutturatoJS[selectedCat].forEach(productName => {
                const option = document.createElement('option');
                option.value = productName;
                option.textContent = productName;
                productSelectByCategory.appendChild(option);
            });
            productSelectByCategory.disabled = false;
        }
    });

    productSelectByCategory.addEventListener('change', function() {
        const selectedProdName = this.value;
        const selectedCatName = categorySelect.value;
        if (selectedProdName) {
            productSearchInput.value = selectedProdName; // Aggiorna il campo principale
            // Crea l'oggetto `selectedProduct` che serve alla logica di conferma
            selectedProduct = { nome: selectedProdName, categoria: selectedCatName };
        } else {
            productSearchInput.value = '';
            selectedProduct = null;
        }
    });

    // --- LOGICA COMUNE (invariata o con minime modifiche) ---
    
    document.addEventListener('click', function(e) {
        if (!productEntryForm.contains(e.target)) {
            productSearchResults.style.display = 'none';
        }
    });

    confirmProductBtn.addEventListener('click', function() {
        let productName = productSearchInput.value.trim();
        let productCategory = 'Non a catalogo'; // Default

        // Se un prodotto è stato selezionato (da ricerca o dropdown), usa la sua categoria
        if (selectedProduct && selectedProduct.nome === productName) {
            productCategory = selectedProduct.categoria;
        }

        const quantity = productQuantityInput.value.trim();
        const unit = productUnitInput.value;
        const notes = productNotesInput.value.trim();

        if (!productName) {
            alert('Cercare e selezionare un prodotto, o specificarne uno nuovo.');
            productSearchInput.focus();
            return;
        }
        if (!quantity || parseInt(quantity) <= 0 || parseInt(quantity) > 999) {
            alert('Inserire una quantità valida (1-999).');
            productQuantityInput.focus();
            return;
        }

        addedProductsArray.push({ name: productName, category: productCategory, quantity, unit, notes });
        updateHiddenJsonInput();
        renderAddedProducts();
        
        clearProductForm();
        productEntryForm.style.display = 'none';
        showProductFormBtn.style.display = 'inline-block';
    });

    function clearProductForm() {
        productSearchInput.value = '';
        productSearchResults.style.display = 'none';
        selectedProduct = null;
        productQuantityInput.value = '';
        productUnitInput.value = productUnitInput.options[0].value;
        productNotesInput.value = '';
        // NUOVO: Resetta anche le dropdown
        categorySelect.value = '';
        productSelectByCategory.innerHTML = '<option value="">Prima scegli la categoria</option>';
        productSelectByCategory.disabled = true;
    }
    
    // Funzioni `showProductFormBtn`, `cancelProductBtn`, `updateHiddenJsonInput`, `renderAddedProducts`, `htmlspecialchars` e `submit` event listener
    // sono invariate e possono essere copiate dal tuo codice originale. Per completezza, le includo qui.

    showProductFormBtn.addEventListener('click', () => {
        productEntryForm.style.display = 'block';
        showProductFormBtn.style.display = 'none';
        productSearchInput.focus();
        productEntryForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });

    cancelProductBtn.addEventListener('click', () => {
        productEntryForm.style.display = 'none';
        showProductFormBtn.style.display = 'inline-block';
        clearProductForm();
    });

    function updateHiddenJsonInput() {
        prodottiJsonInput.value = JSON.stringify(addedProductsArray);
    }

    function renderAddedProducts() {
        addedProductsList.innerHTML = '';
        addedProductsArray.forEach((product, index) => {
            const itemDiv = document.createElement('div');
            itemDiv.classList.add('product-item');
            const detailsSpan = document.createElement('span');
            detailsSpan.classList.add('product-details');
            detailsSpan.innerHTML = `<strong>${htmlspecialchars(product.name)}</strong> <small>(${htmlspecialchars(product.category)})</small> - ${htmlspecialchars(product.quantity)} ${htmlspecialchars(product.unit)}`;
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
            removeBtn.addEventListener('click', () => {
                addedProductsArray.splice(index, 1);
                updateHiddenJsonInput();
                renderAddedProducts();
            });
            itemDiv.appendChild(detailsSpan);
            itemDiv.appendChild(removeBtn);
            addedProductsList.appendChild(itemDiv);
        });
    }

    function htmlspecialchars(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    
    document.getElementById('main-request-form').addEventListener('submit', (event) => {
        updateHiddenJsonInput();
        if (addedProductsArray.length === 0) {
            alert('Aggiungere almeno un prodotto alla richiesta prima di inviare.');
            event.preventDefault();
        }
    });
});
</script>

</body>
</html>