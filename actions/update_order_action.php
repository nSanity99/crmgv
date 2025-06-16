<?php
session_start();
require_once __DIR__.'/../includes/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: ../i_miei_ordini.php?edit=error');
    exit;
}

$id_ordine = filter_input(INPUT_POST, 'id_ordine', FILTER_VALIDATE_INT);
$prodotti_json = $_POST['prodotti_json'] ?? '[]';
$prodotti = json_decode($prodotti_json, true);

if (!$id_ordine || empty($prodotti) || !is_array($prodotti)) {
    header('Location: ../i_miei_ordini.php?edit=error');
    exit;
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    header('Location: ../i_miei_ordini.php?edit=error');
    exit;
}

$conn->begin_transaction();
try {
    $stmt_check = $conn->prepare('SELECT id_utente_richiedente, consenti_modifica FROM ordini WHERE id_ordine = ?');
    $stmt_check->bind_param('i', $id_ordine);
    $stmt_check->execute();
    $info = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if (!$info || $info['id_utente_richiedente'] != $_SESSION['user_id'] || $info['consenti_modifica'] != 1) {
        throw new Exception('Ordine non modificabile.');
    }

    $stmt_old = $conn->prepare('SELECT nome_prodotto, quantita, unita_misura, note_prodotto FROM dettagli_ordine WHERE id_ordine = ? ORDER BY id_dettaglio_ordine');
    $stmt_old->bind_param('i', $id_ordine);
    $stmt_old->execute();
    $old_data = $stmt_old->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_old->close();

    $stmt_del = $conn->prepare('DELETE FROM dettagli_ordine WHERE id_ordine = ?');
    $stmt_del->bind_param('i', $id_ordine);
    $stmt_del->execute();
    $stmt_del->close();

    $stmt_ins = $conn->prepare('INSERT INTO dettagli_ordine (id_ordine, nome_prodotto, quantita, unita_misura, note_prodotto, stato_prodotto) VALUES (?, ?, ?, ?, ?, "Inviato")');
    if (!$stmt_ins) throw new Exception('Errore preparazione insert.');
    foreach ($prodotti as $p) {
        $nome = trim($p['name'] ?? '');
        $qta = (int)($p['quantity'] ?? 0);
        $um = trim($p['unit'] ?? '');
        $note = trim($p['notes'] ?? '');
        if ($nome === '' || $qta <= 0 || $um === '') {
            throw new Exception('Dati prodotto non validi.');
        }
        $stmt_ins->bind_param('isiss', $id_ordine, $nome, $qta, $um, $note);
        $stmt_ins->execute();
    }
    $stmt_ins->close();

    $stmt_upd = $conn->prepare('UPDATE ordini SET consenti_modifica = 0, stato_ordine = "Inviato", data_ultima_modifica = CURRENT_TIMESTAMP WHERE id_ordine = ?');
    $stmt_upd->bind_param('i', $id_ordine);
    $stmt_upd->execute();
    $stmt_upd->close();

    $stmt_mod = $conn->prepare('INSERT INTO ordini_modifiche (id_ordine, id_utente, prima, dopo) VALUES (?, ?, ?, ?)');
    $old_json = json_encode($old_data, JSON_UNESCAPED_UNICODE);
    $new_json = json_encode($prodotti, JSON_UNESCAPED_UNICODE);
    $stmt_mod->bind_param('iiss', $id_ordine, $_SESSION['user_id'], $old_json, $new_json);
    $stmt_mod->execute();
    $stmt_mod->close();

    $conn->commit();
    $conn->close();
    header('Location: ../i_miei_ordini.php?edit=success');
    exit;
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    header('Location: ../i_miei_ordini.php?edit=error');
    exit;
}
?>
