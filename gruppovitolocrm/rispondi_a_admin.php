<?php
require 'connessione.php'; // connessione al DB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id_segnalazione', FILTER_VALIDATE_INT);
    $msg = trim($_POST['messaggio_admin'] ?? '');

    if (!$id || $msg === '') {
        header("Location: gestione_segnalazioni.php?status=error");
        exit;
    }

    // Inserisce un nuovo messaggio come admin nella tabella chat
    $stmt = $conn->prepare("INSERT INTO segnalazioni_chat (id_segnalazione, id_utente, messaggio_admin, data_messaggio) VALUES (?, 0, ?, CURRENT_TIMESTAMP)");
    $stmt->bind_param("is", $id, $msg);

    if ($stmt->execute()) {
        // Aggiorna anche la data_ultima_modifica nella tabella principale
        $upd = $conn->prepare("UPDATE segnalazioni SET data_ultima_modifica = CURRENT_TIMESTAMP WHERE id_segnalazione = ?");
        $upd->bind_param("i", $id);
        $upd->execute();
        $upd->close();

        header("Location: gestione_segnalazioni.php?status=success");
    } else {
        header("Location: gestione_segnalazioni.php?status=error");
    }

    $stmt->close();
    $conn->close();
}
?>
