<?php
session_start();
require_once __DIR__.'/includes/db_config.php';

// Sicurezza: Solo gli admin possono accedere
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$username_display = htmlspecialchars($_SESSION['username'] ?? 'N/A');

// Array degli stati possibili per il dropdown del form
$stati_possibili = ['Inviata', 'In Lavorazione', 'In Attesa di Risposta', 'Conclusa'];

// Connessione DB e recupero segnalazioni e chat
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$segnalazioni = [];
$chat_messaggi = [];
$db_error_message = null;

if ($conn->connect_error) {
    $db_error_message = "Impossibile connettersi al database.";
} else {
    // Ordiniamo per stato, mettendo prima quelle aperte, e poi per data
    $sql = "SELECT id_segnalazione, nome_utente_segnalante, data_invio, titolo, descrizione, area_competenza, stato, note_interne
            FROM segnalazioni
            ORDER BY
                CASE stato
                    WHEN 'Inviata' THEN 1
                    WHEN 'In Lavorazione' THEN 2
                    WHEN 'In Attesa di Risposta' THEN 3
                    WHEN 'Conclusa' THEN 4
                    ELSE 5
                END,
                data_invio DESC";
    $result = $conn->query($sql);
    if ($result) {
        $segnalazioni = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();

        // Recupero dei messaggi di chat per tutte le segnalazioni
        $sql_chat = "SELECT id_segnalazione, messaggio_admin, risposta_utente, data_messaggio, data_risposta FROM segnalazioni_chat ORDER BY data_messaggio ASC";
        $res_chat = $conn->query($sql_chat);
        if ($res_chat) {
            while ($row = $res_chat->fetch_assoc()) {
                $chat_messaggi[$row['id_segnalazione']][] = $row;
            }
            $res_chat->free();
        }
    } else {
        $db_error_message = "Errore nel caricamento delle segnalazioni.";
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Segnalazioni - Gruppo Vitolo</title>
    <style>
        /* Stile coerente con le altre pagine di gestione */
        html { box-sizing: border-box; }
        *, *:before, *:after { box-sizing: inherit; }
        body { background-color: #f8f9fa; color: #495057; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
        .module-page-container { max-width: 1100px; margin: 25px auto; padding: 0 15px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; background-color: #ffffff; padding: 18px 30px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); border-bottom: 4px solid #B08D57; margin-bottom: 30px; }
        .header-branding { display: flex; align-items: center; gap: 15px; }
        .header-branding .logo { max-height: 45px; }
        .header-titles h1 { font-size: 1.5em; color: #2E572E; margin: 0; }
        .header-titles h2 { font-size: 0.9em; color: #6c757d; margin: 0; }
        .user-session-controls { display: flex; align-items: center; gap: 15px; }
        .nav-link-button, .logout-button { text-decoration: none; padding: 8px 15px; border-radius: 5px; color: white !important; font-weight: 500; transition: all 0.2s ease; border: none; cursor: pointer; }
        .nav-link-button { background-color: #6c757d; }
        .logout-button { background-color: #D42A2A; }
        .module-content { background-color: #ffffff; padding: 25px 30px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
        .app-section-header h2 { font-size: 1.4em; color: #2E572E; margin: 0; padding-bottom: 15px; border-bottom: 1px solid #e9ecef; }
        .report-record { border: 1px solid #e9ecef; margin-top: 20px; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .report-summary { display: grid; grid-template-columns: 1fr auto; align-items: center; padding: 15px 20px; cursor: pointer; gap: 20px; }
        .report-summary-info h3 { margin: 0 0 5px 0; color: #343a40; font-size: 1.1em; }
        .report-summary-info span { font-size: 0.9em; color: #6c757d; }
        .report-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; }
        .report-details { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out, padding 0.4s ease-out; background-color: #fdfdfd; border-top: 1px solid #e9ecef; padding: 0 25px; }
        .report-record.is-open .report-details { max-height: 1500px; padding: 25px; }
        .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .detail-box h4 { margin-top: 0; margin-bottom: 10px; color: #2E572E; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .detail-box p { margin: 0; white-space: pre-wrap; }
        .management-form .form-group { margin-bottom: 15px; }
        .management-form label { font-weight: bold; display: block; margin-bottom: 5px; }
        .management-form select, .management-form textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.95em; }
        .management-form textarea { min-height: 100px; resize: vertical; }
        .update-btn { background-color: #007bff; color:white; font-weight: bold; padding: 8px 15px; border-radius: 4px; }
        .status-badge { padding: 4px 12px; border-radius: 15px; font-size: 0.85em; font-weight: bold; color: white; text-align: center; }
        .status-badge.inviata { background-color: #007bff; }
        .status-badge.in-lavorazione { background-color: #ffc107; color: #212529; }
        .status-badge.in-attesa-di-risposta { background-color: #17a2b8; }
        .status-badge.conclusa { background-color: #28a745; }

        /* Nuovi stili per chat più pulita */
        .chat-messages { display: flex; flex-direction: column; gap: 8px; max-height: 300px; overflow-y: auto; margin-bottom: 15px; padding-right: 10px; }
        .chat-message { display: flex; }
        .chat-message.admin { justify-content: flex-start; }
        .chat-message.user { justify-content: flex-end; }
        .bubble { max-width: 75%; padding: 10px 14px; border-radius: 12px; font-size: 0.95em; line-height: 1.4; border: 1px solid transparent; }
        .chat-message.admin .bubble { background-color: #e9f5ff; border-color: #c3ddf2; border-top-left-radius: 0; color: #034a73; }
        .chat-message.user .bubble { background-color: #f0fdf4; border-color: #cde7d8; border-top-right-radius: 0; color: #1b4332; }
        .bubble time { display: block; font-size: 0.75em; color: #6c757d; margin-top: 6px; text-align: right; }
    </style>
</head>
<body>
    <div class="module-page-container">
        <header class="page-header">
            <div class="header-branding">
                <a href="dashboard.php"><img src="assets/logo.png" alt="Logo Gruppo Vitolo" class="logo"></a>
                <div class="header-titles">
                    <h1>Gestione Segnalazioni</h1>
                    <h2>Pannello di Amministrazione</h2>
                </div>
            </div>
            <div class="user-session-controls">
                <span><strong><?php echo $username_display; ?></strong></span>
                <a href="dashboard.php" class="nav-link-button">Dashboard</a>
                <a href="logout.php" class="logout-button">Logout</a>
            </div>
        </header>
        <main class="module-content">
            <div class="app-section-header">
                <h2>Elenco Segnalazioni Ricevute</h2>
            </div>
            <div id="reports-list-container">
                <?php if ($db_error_message): ?>
                    <p style="color: red;"><?php echo $db_error_message; ?></p>
                <?php elseif (empty($segnalazioni)): ?>
                    <p style="text-align:center; padding: 30px; color: #6c757d; font-style: italic;">Nessuna segnalazione presente.</p>
                <?php else: ?>
                    <?php foreach ($segnalazioni as $s): ?>
                        <div class="report-record" id="report-record-<?php echo $s['id_segnalazione']; ?>">
                            <div class="report-summary">
                                <div class="report-summary-info">
                                    <h3><?php echo htmlspecialchars($s['titolo']); ?></h3>
                                    <span>Inviata da: <strong><?php echo htmlspecialchars($s['nome_utente_segnalante']); ?></strong> il <?php echo date('d/m/Y H:i', strtotime($s['data_invio'])); ?></span>
                                </div>
                                <div class="report-meta">
                                    <?php $status_class = strtolower(str_replace(' ', '-', $s['stato'])); ?>
                                    <span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($s['stato']); ?></span>
                                </div>
                            </div>
                            <div class="report-details">
                                <div class="details-grid">
                                    <div class="detail-box">
                                        <h4>Descrizione del Problema</h4>
                                        <p><?php echo nl2br(htmlspecialchars($s['descrizione'])); ?></p>
                                        <p style="margin-top:15px;"><strong>Area di Competenza:</strong> <?php echo htmlspecialchars($s['area_competenza']); ?></p>
                                    </div>
                                    <div class="detail-box">
                                        <h4>Gestione Interna</h4>
                                        <?php if (!empty($chat_messaggi[$s['id_segnalazione']])): ?>
                                            <div class="chat-messages">
                                                <?php foreach ($chat_messaggi[$s['id_segnalazione']] as $msg): ?>
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
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <form class="management-form">
                                            <input type="hidden" name="id_segnalazione" value="<?php echo $s['id_segnalazione']; ?>">
                                            <div class="form-group">
                                                <label for="stato-<?php echo $s['id_segnalazione']; ?>">Stato:</label>
                                                <select id="stato-<?php echo $s['id_segnalazione']; ?>" name="stato">
                                                    <?php foreach ($stati_possibili as $stato_opt): ?>
                                                        <option value="<?php echo $stato_opt; ?>" <?php echo ($s['stato'] === $stato_opt) ? 'selected' : ''; ?>><?php echo $stato_opt; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="note_interne-<?php echo $s['id_segnalazione']; ?>">Note Interne (visibili solo agli admin):</label>
                                                <textarea id="note_interne-<?php echo $s['id_segnalazione']; ?>" name="note_interne"><?php echo htmlspecialchars($s['note_interne'] ?? ''); ?></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label for="messaggio_admin-<?php echo $s['id_segnalazione']; ?>">Nuovo Messaggio per l'Utente:</label>
                                                <textarea id="messaggio_admin-<?php echo $s['id_segnalazione']; ?>" name="messaggio_admin"></textarea>
                                            </div>
                                            <button type="button" class="nav-link-button update-btn">Aggiorna</button>
                                            <span class="update-feedback" style="margin-left: 10px; color: green; font-weight: bold;"></span>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('reports-list-container');

        container.addEventListener('click', function(event) {
            // Logica per aprire/chiudere la fisarmonica
            const summary = event.target.closest('.report-summary');
            if (summary) {
                const record = summary.closest('.report-record');
                if (record) record.classList.toggle('is-open');
            }

            // Logica per il pulsante "Aggiorna"
            const updateButton = event.target.closest('.update-btn');
            if (updateButton) {
                const form = updateButton.closest('.management-form');
                const id = form.querySelector('input[name="id_segnalazione"]').value;
                const nuovoStato = form.querySelector('select[name="stato"]').value;
                const noteInterne = form.querySelector('textarea[name="note_interne"]').value;
                const messaggioAdmin = form.querySelector('textarea[name="messaggio_admin"]').value;
                const feedbackSpan = form.querySelector('.update-feedback');
                
                updateButton.textContent = '...';
                updateButton.disabled = true;

                const formData = new FormData();
                formData.append('id_segnalazione', id);
                formData.append('nuovo_stato', nuovoStato);
                formData.append('note_interne', noteInterne);
                formData.append('messaggio_admin', messaggioAdmin);

                fetch('actions/update_segnalazione_action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Aggiorna il badge dello stato nella riga di riepilogo
                        const recordDiv = document.getElementById(`report-record-${id}`);
                        const statusBadge = recordDiv.querySelector('.report-summary .status-badge');
                        statusBadge.textContent = nuovoStato;
                        statusBadge.className = 'status-badge ' + nuovoStato.toLowerCase().replace(/ /g, '-');
                        
                        // Mostra un feedback di successo
                        feedbackSpan.textContent = 'Salvato!';
                        setTimeout(() => { feedbackSpan.textContent = ''; }, 2000);
                    } else {
                        alert('Errore: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Errore:', error);
                    alert('Errore di comunicazione con il server.');
                })
                .finally(() => {
                    updateButton.textContent = 'Aggiorna';
                    updateButton.disabled = false;
                });
            }
        });
    });
    </script>
</body>
</html>
