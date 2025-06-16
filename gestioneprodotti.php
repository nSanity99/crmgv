<?php
session_start();
require_once __DIR__.'/includes/db_config.php';

// Solo admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['ruolo'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

$conn = isset($db_port)
    ? new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port)
    : new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Logica POST per gestire tutte le azioni del form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect_url = 'gestioneprodotti.php';

    // Gestione eliminazione categoria
    if (isset($_POST['delete_category_id'])) {
        $id = (int)$_POST['delete_category_id'];
        if ($id === 1) {
            $_SESSION['catalogo_error'] = "Impossibile eliminare la categoria predefinita.";
        } else {
            $check = $conn->prepare("SELECT COUNT(*) FROM catalogo_prodotti WHERE categoria_id=?");
            $check->bind_param('i', $id);
            $check->execute();
            $check->bind_result($count);
            $check->fetch();
            $check->close();

            if ($count > 0) {
                $_SESSION['catalogo_error'] = "Categoria con prodotti associati, impossibile eliminarla.";
            } else {
                $stmt = $conn->prepare("DELETE FROM categorie_prodotti WHERE id=?");
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $_SESSION['catalogo_success'] = "Categoria eliminata con successo.";
                } else {
                    $_SESSION['catalogo_error'] = "Errore durante l'eliminazione della categoria.";
                }
                $stmt->close();
            }
        }
    }
    // Gestione eliminazione prodotto
    elseif (isset($_POST['delete_product_id'])) {
        $id = (int)$_POST['delete_product_id'];
        $redirect_url = 'gestioneprodotti.php?categoria_id=' . ($_POST['current_category_id'] ?? '');
        $stmt = $conn->prepare("DELETE FROM catalogo_prodotti WHERE id=?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $_SESSION['catalogo_success'] = "Prodotto eliminato con successo.";
        } else {
            $_SESSION['catalogo_error'] = "Errore durante l'eliminazione del prodotto.";
        }
        $stmt->close();
    }
    // Gestione aggiornamento categoria
    elseif (isset($_POST['update_category_id'])) {
        $id = (int)$_POST['update_category_id'];
        $nome_cat = trim($_POST['new_nome_categoria']);
        if (empty($nome_cat)) {
            $_SESSION['catalogo_error'] = "Il nome della categoria non può essere vuoto.";
        } else {
            $stmt = $conn->prepare("UPDATE categorie_prodotti SET nome=? WHERE id=?");
            $stmt->bind_param('si', $nome_cat, $id);
            if ($stmt->execute()) {
                $_SESSION['catalogo_success'] = "Categoria aggiornata con successo.";
            } else {
                $_SESSION['catalogo_error'] = "Errore durante l'aggiornamento della categoria.";
            }
            $stmt->close();
        }
    }
    // Gestione aggiornamento prodotto
    elseif (isset($_POST['update_product_id'])) {
        $id = (int)$_POST['update_product_id'];
        $nome = trim($_POST['new_nome_prodotto']);
        $categoria_id = (int)($_POST['new_categoria_id'] ?? 1);
        $redirect_url = 'gestioneprodotti.php?categoria_id=' . $categoria_id;
        if (empty($nome)) {
            $_SESSION['catalogo_error'] = "Il nome del prodotto non può essere vuoto.";
        } else {
            $stmt = $conn->prepare("UPDATE catalogo_prodotti SET nome=?, categoria_id=? WHERE id=?");
            $stmt->bind_param('sii', $nome, $categoria_id, $id);
            if ($stmt->execute()) {
                $_SESSION['catalogo_success'] = "Prodotto aggiornato con successo.";
            } else {
                $_SESSION['catalogo_error'] = "Errore durante l'aggiornamento del prodotto.";
            }
            $stmt->close();
        }
    }
    // Gestione aggiunta categoria
    elseif (isset($_POST['add_new_category'])) {
        $nome_cat = trim($_POST['nome_categoria']);
        if (empty($nome_cat)) {
            $_SESSION['catalogo_error'] = "Il nome della categoria non può essere vuoto.";
        } else {
            $stmt = $conn->prepare("INSERT INTO categorie_prodotti (nome) VALUES (?)");
            $stmt->bind_param('s', $nome_cat);
            if ($stmt->execute()) {
                $_SESSION['catalogo_success'] = "Categoria aggiunta con successo.";
            } else {
                $_SESSION['catalogo_error'] = $conn->errno == 1062 ? "Categoria già esistente." : "Errore durante l'inserimento.";
            }
            $stmt->close();
        }
    }
    // Gestione aggiunta prodotto
    elseif (isset($_POST['add_new_product'])) {
        $nome = trim($_POST['nome_prodotto']);
        $categoria_id = (int)($_POST['categoria_id'] ?? 1);
        $redirect_url = 'gestioneprodotti.php?categoria_id=' . $categoria_id;
        if (empty($nome)) {
            $_SESSION['catalogo_error'] = "Il nome del prodotto non può essere vuoto.";
        } else {
            $stmt = $conn->prepare("INSERT INTO catalogo_prodotti (nome, categoria_id) VALUES (?, ?)");
            $stmt->bind_param('si', $nome, $categoria_id);
            if ($stmt->execute()) {
                $_SESSION['catalogo_success'] = "Prodotto aggiunto con successo.";
            } else {
                $_SESSION['catalogo_error'] = $conn->errno == 1062 ? "Prodotto già esistente." : "Errore durante l'inserimento.";
            }
            $stmt->close();
        }
    }

    $conn->close();
    header("Location: " . $redirect_url);
    exit;
}

// Recupero dati per la visualizzazione
$success_message = $_SESSION['catalogo_success'] ?? '';
$error_message = $_SESSION['catalogo_error'] ?? '';
unset($_SESSION['catalogo_success'], $_SESSION['catalogo_error']);

$selected_cat_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;
$selected_category = null;

$categorie = [];
$res = $conn->query("SELECT id, nome FROM categorie_prodotti ORDER BY nome ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $categorie[] = $row;
        if ($selected_cat_id && $row['id'] == $selected_cat_id) {
            $selected_category = $row;
        }
    }
    $res->free();
}

$prodotti = [];
if ($selected_cat_id) {
    $stmt = $conn->prepare("SELECT id, nome, categoria_id FROM catalogo_prodotti WHERE categoria_id = ? ORDER BY nome ASC");
    $stmt->bind_param('i', $selected_cat_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $prodotti[] = $row;
        }
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Catalogo</title>
    <link rel="stylesheet" href="assets/style.css"> <style>
        :root {
            --primary-bg: #f8f9fa;
            --content-bg: #ffffff;
            --border-color: #dee2e6;
            --text-color: #495057;
            --title-color: #2E572E;
            --accent-color: #B08D57;
            --danger-color: #D42A2A;
            --danger-hover: #c82333;
            --success-color: #0f5132;
            --success-bg: #d1e7dd;
            --error-color: #842029;
            --error-bg: #f8d7da;
            --shadow: 0 4px 12px rgba(0,0,0,0.08);
            --radius: 8px;
        }
        
        body { background-color: var(--primary-bg); color: var(--text-color); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; }
        .page-container { max-width: 1200px; margin: 30px auto; padding: 0 15px; }
        .module-header { display: flex; justify-content: space-between; align-items: center; background-color: var(--content-bg); padding: 15px 25px; border-radius: var(--radius); box-shadow: var(--shadow); border-bottom: 4px solid var(--accent-color); margin-bottom: 30px; }
        .header-branding { display: flex; align-items: center; }
        .header-branding .logo { max-height: 45px; margin-right: 15px; }
        .header-titles h1 { font-size: 1.6em; color: var(--title-color); margin: 0; font-weight: 600; }
        .user-session-controls { display: flex; align-items: center; gap: 15px; }
        .user-session-controls a { padding: 8px 16px; font-size: 0.9em; color: white !important; text-decoration: none; border-radius: 5px; transition: all 0.2s ease; font-weight: 500; }
        .user-session-controls .nav-link-button { background-color: #6c757d; }
        .user-session-controls .nav-link-button:hover { background-color: #5a6268; transform: translateY(-2px); }
        .user-session-controls .logout-button { background-color: var(--danger-color); }
        .user-session-controls .logout-button:hover { background-color: var(--danger-hover); transform: translateY(-2px); }

        .content-grid { display: flex; gap: 30px; align-items: flex-start; }
        .sidebar { flex: 0 0 280px; }
        .main-content { flex: 1; }

        .panel { background: var(--content-bg); border-radius: var(--radius); box-shadow: var(--shadow); padding: 25px; }
        .panel-header { font-size: 1.3em; color: var(--title-color); margin-top: 0; margin-bottom: 25px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; }

        .sidebar .panel-header { font-size: 1.1em; }
        .category-list { list-style: none; padding: 0; margin: 0; }
        .category-list li a { display: block; padding: 12px 15px; text-decoration: none; color: var(--text-color); border-radius: 6px; margin-bottom: 5px; transition: all 0.2s ease; border: 1px solid transparent; }
        .category-list li a:hover { background-color: #e9ecef; color: var(--title-color); }
        .category-list li a.active { background-color: var(--accent-color); color: white; font-weight: 600; border-color: var(--accent-color); }

        .catalogo-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.95em; }
        .catalogo-table th, .catalogo-table td { border: 1px solid var(--border-color); padding: 12px 15px; text-align: left; vertical-align: middle; }
        .catalogo-table th { background-color: #f8f9fa; font-weight: 600; }
        .catalogo-table tr:nth-child(even) { background-color: #f8f9fa; }
        .catalogo-table td form { display: flex; gap: 8px; align-items: center; }

        .feedback-message { margin-bottom: 20px; padding: 15px; border-radius: var(--radius); font-weight: 500; }
        .feedback-message.success { color: var(--success-color); background-color: var(--success-bg); border: 1px solid var(--success-color); }
        .feedback-message.error { color: var(--error-color); background-color: var(--error-bg); border: 1px solid var(--error-color); }
        
        /* Stili per Form */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input[type="text"], .form-group select { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 5px; box-sizing: border-box; }
        .form-action-button { background-color: var(--title-color); color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; transition: background-color 0.2s ease; font-weight: 500; }
        .form-action-button:hover { background-color: #1e3c1e; }
        
        /* Pulsanti piccoli per le tabelle */
        .table-button { padding: 5px 10px; font-size: 0.85em; border: none; border-radius: 4px; color: white; cursor: pointer; transition: all 0.2s ease; }
        .table-button.save { background-color: #007bff; }
        .table-button.save:hover { background-color: #0056b3; }
        .table-button.danger { background-color: var(--danger-color); }
        .table-button.danger:hover { background-color: var(--danger-hover); }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .main-content { animation: fadeIn 0.5s ease-in-out; }
    </style>
</head>
<body>
    <div class="page-container">
        <header class="module-header">
            <div class="header-branding">
                <a href="dashboard.php"><img src="assets/logo.png" alt="Logo" class="logo"></a>
                <div class="header-titles">
                    <h1>Gestione Catalogo</h1>
                </div>
            </div>
            <div class="user-session-controls">
                <a href="dashboard.php" class="nav-link-button">Dashboard</a>
                <a href="logout.php" class="logout-button">Logout</a>
            </div>
        </header>

        <?php if ($success_message): ?>
            <div class="feedback-message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php elseif ($error_message): ?>
            <div class="feedback-message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="content-grid">
            <aside class="sidebar">
                <div class="panel">
                    <h3 class="panel-header">Categorie</h3>
                    <ul class="category-list">
                        <li><a href="gestioneprodotti.php" class="<?php echo !$selected_cat_id ? 'active' : ''; ?>">Gestione Categorie</a></li>
                        <hr>
                        <?php foreach ($categorie as $cat): ?>
                            <li>
                                <a href="?categoria_id=<?php echo $cat['id']; ?>" class="<?php echo ($selected_cat_id == $cat['id']) ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($cat['nome']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </aside>

            <main class="main-content">
                <div class="panel">
                    <?php if ($selected_cat_id && $selected_category): ?>
                        <h3 class="panel-header">Prodotti in: <?php echo htmlspecialchars($selected_category['nome']); ?></h3>
                        
                        <div style="margin-bottom: 30px; border-bottom: 1px solid var(--border-color); padding-bottom: 20px;">
                            <h4>Aggiungi Nuovo Prodotto</h4>
                            <form method="POST" action="gestioneprodotti.php">
                                <input type="hidden" name="add_new_product" value="1">
                                <input type="hidden" name="categoria_id" value="<?php echo $selected_cat_id; ?>">
                                <div class="form-group">
                                    <label for="nome_prodotto">Nome prodotto:</label>
                                    <input type="text" id="nome_prodotto" name="nome_prodotto" required>
                                </div>
                                <button type="submit" class="form-action-button">Aggiungi Prodotto</button>
                            </form>
                        </div>

                        <table class="catalogo-table">
                            <thead>
                                <tr><th>Nome Prodotto</th><th>Azioni</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($prodotti)): ?>
                                    <tr><td colspan="2" style="text-align:center;">Nessun prodotto in questa categoria.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($prodotti as $p): ?>
                                    <tr>
                                        <td>
                                            <form method="POST" action="gestioneprodotti.php">
                                                <input type="hidden" name="update_product_id" value="<?php echo $p['id']; ?>">
                                                <input type="text" name="new_nome_prodotto" value="<?php echo htmlspecialchars($p['nome']); ?>" style="flex-grow: 1;">
                                                <select name="new_categoria_id">
                                                    <?php foreach ($categorie as $cat): ?>
                                                        <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] == $p['categoria_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['nome']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="table-button save">Salva</button>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="POST" action="gestioneprodotti.php" onsubmit="return confirm('Sei sicuro di voler eliminare questo prodotto?');">
                                                <input type="hidden" name="delete_product_id" value="<?php echo $p['id']; ?>">
                                                <input type="hidden" name="current_category_id" value="<?php echo $selected_cat_id; ?>">
                                                <button type="submit" class="table-button danger">Elimina</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>

                    <?php else: ?>
                        <h3 class="panel-header">Gestione Categorie</h3>

                        <div style="margin-bottom: 30px; border-bottom: 1px solid var(--border-color); padding-bottom: 20px;">
                            <h4>Aggiungi Nuova Categoria</h4>
                            <form method="POST" action="gestioneprodotti.php">
                                <input type="hidden" name="add_new_category" value="1">
                                <div class="form-group">
                                    <label for="nome_categoria">Nome categoria:</label>
                                    <input type="text" id="nome_categoria" name="nome_categoria" required>
                                </div>
                                <button type="submit" class="form-action-button">Aggiungi Categoria</button>
                            </form>
                        </div>
                        
                        <table class="catalogo-table">
                            <thead>
                                <tr><th>Nome Categoria</th><th>Azioni</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categorie)): ?>
                                    <tr><td colspan="2" style="text-align:center;">Nessuna categoria presente.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($categorie as $c): ?>
                                    <tr>
                                        <td>
                                            <form method="POST" action="gestioneprodotti.php">
                                                <input type="hidden" name="update_category_id" value="<?php echo $c['id']; ?>">
                                                <input type="text" name="new_nome_categoria" value="<?php echo htmlspecialchars($c['nome']); ?>" style="flex-grow: 1;">
                                                <button type="submit" class="table-button save">Salva</button>
                                            </form>
                                        </td>
                                        <td>
                                            <?php if ($c['id'] != 1): // Non permettere la cancellazione della categoria default ?>
                                            <form method="POST" action="gestioneprodotti.php" onsubmit="return confirm('Sei sicuro di voler eliminare questa categoria?');">
                                                <input type="hidden" name="delete_category_id" value="<?php echo $c['id']; ?>">
                                                <button type="submit" class="table-button danger">Elimina</button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>