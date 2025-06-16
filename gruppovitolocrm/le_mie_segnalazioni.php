<?php
session_start();
require_once __DIR__.'/includes/db_config.php';

// Sicurezza: L'utente deve essere loggato
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Dati dell'utente loggato
$id_utente_loggato = $_SESSION['user_id'];
$username_display = htmlspecialchars($_SESSION['username'] ?? 'N/A');

// Connessione al DB e recupero delle segnalazioni SOLO di questo utente
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$mie_segnalazioni = [];
$chat_messaggi = [];
$db_error_message = null;

if ($conn->connect_error) {
    $db_error_message = "Impossibile connettersi al database per caricare lo storico.";
} else {
    $sql = "SELECT id_segnalazione, titolo, descrizione, area_competenza, data_invio, stato, data_ultima_modifica
            FROM segnalazioni
            WHERE id_utente_segnalante = ?
            ORDER BY data_invio DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_utente_loggato);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $mie_segnalazioni = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        // Carica messaggi
        if (!empty($mie_segnalazioni)) {
            $ids = array_column($mie_segnalazioni, 'id_segnalazione');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $sql_chat = "SELECT id, id_segnalazione, messaggio_admin, risposta_utente, data_messaggio, data_risposta
                         FROM segnalazioni_chat
                         WHERE id_segnalazione IN ($placeholders)
                         ORDER BY data_messaggio ASC";
            $stmt_chat = $conn->prepare($sql_chat);
            $stmt_chat->bind_param($types, ...$ids);
            $stmt_chat->execute();
            $res_chat = $stmt_chat->get_result();
            if ($res_chat) {
                while ($row = $res_chat->fetch_assoc()) {
                    $chat_messaggi[$row['id_segnalazione']][] = $row;
                }
                $res_chat->free();
            }
            $stmt_chat->close();
        }
    } else {
        $db_error_message = "Errore nel caricamento delle tue segnalazioni.";
    }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Le Mie Segnalazioni - Gruppo Vitolo</title>
    <style>
        /* Stili generali */
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
        .report-summary { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; cursor: pointer; flex-wrap: wrap; gap: 10px; }
        .report-summary-info { display: flex; align-items: center; gap: 10px; }
        .report-summary-info h3 { margin: 0 0 5px 0; color: #343a40; font-size: 1.1em; }
        .report-summary-info span { font-size: 0.9em; color: #6c757d; }
        .new-notif { display: none; }
        .new-notif-dot { width: 8px; height: 8px; background-color: #dc3545; border-radius: 50%; display: inline-block; margin-left: 6px; }
        .report-meta { display: flex; align-items: center; gap: 20px; }
        .report-details { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out, padding 0.4s ease-out; background-color: #fdfdfd; border-top: 1px solid #e9ecef; padding: 0 20px; }
        .report-record.is-open .report-details { max-height: 1000px; padding: 25px; }
        .detail-item { margin-bottom: 15px; }
        .detail-item strong { color: #2E572E; }
        .status-badge { padding: 4px 12px; border-radius: 15px; font-size: 0.85em; font-weight: bold; color: white; }
        .status-badge.inviata { background-color: #007bff; }
        .status-badge.in-lavorazione { background-color: #ffc107; color: #212529; }
        .status-badge.in-attesa-di-risposta { background-color: #17a2b8; }
        .status-badge.conclusa { background-color: #28a745; }
        /* Chat bubble styles */
        .chat-messages { display: flex; flex-direction: column; gap: 8px; max-height: 300px; overflow-y: auto; margin-bottom: 15px; padding-right: 10px; }
        .chat-message { display: flex; }
        .chat-message.admin { justify-content: flex-start; }
        .chat-message.user { justify-content: flex-end; }
        .bubble { max-width: 75%; padding: 10px 14px; border-radius: 12px; font-size: 0.95em; line-height: 1.4; border: 1px solid transparent; }
        .chat-message.admin .bubble { background-color: #e9f5ff; border-color: #c3ddf2; border-top-left-radius: 0; color: #034a73; }
        .chat-message.user .bubble { background-color: #f0fdf4; border-color: #cde7d8; border-top-right-radius: 0; color: #1b4332; }
        .bubble time { display: block; font-size: 0.75em; color: #6c757d; margin-top: 6px; text-align: right; }
        .reply-form textarea { width:100%; padding:8px; min-height:80px; border:1px solid #ccc; border-radius:4px; }
        .reply-form button { margin-top:10px; }
    </style>
</head>
<body>
    <div class="module-page-container">
        <header class="page-header">
            <div class="header-branding">
                <a href="dashboard.php"><img src="assets/logo.png" alt="Logo Gruppo Vitolo" class="logo"></a>
                <div class="header-titles">
                    <h1>Le Mie Segnalazioni</h1>
                    <h2>Storico e Stato delle Tue Richieste</h2>
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
                <h2>Riepilogo delle tue segnalazioni inviate</h2>
            </div>
            <div id="reports-list-container">
                <?php if ($db_error_message): ?>
                    <p style="color: red; font-weight: bold;"><?php echo $db_error_message; ?></p>
                <?php elseif (empty($mie_segnalazioni)): ?>
                    <p style="text-align:center; padding: 30px; color: #6c757d; font-style: italic;">Non hai ancora inviato nessuna segnalazione.</p>
                <?php else: ?>
                    <?php foreach ($mie_segnalazioni as $s): ?>
                        <?php
                        // Calcola notifiche non lette
                        $unread = 0;
                        if (!empty($chat_messaggi[$s['id_segnalazione']])) {
                            foreach ($chat_messaggi[$s['id_segnalazione']] as $m) {
                                if (empty($m['risposta_utente']) && $s['stato'] !== 'Conclusa') {
                                    $unread++;
                                }
                            }
                        }
                        ?>
                        <div class="report-record">
                            <div class="report-summary">
                                <div class="report-summary-info">
                                    <h3><?php echo htmlspecialchars($s['titolo']); ?></h3>
                                    <span>Inviata il: <?php echo date('d/m/Y H:i', strtotime($s['data_invio'])); ?></span>
                                    <?php if ($unread > 0): ?><span class="new-notif-dot"></span><?php endif; ?>
                                </div>
                                <div class="report-meta"><span class="status-badge <?php echo strtolower(str_replace(' ', '-', $s['stato'])); ?>"><?php echo htmlspecialchars($s['stato']); ?></span></div>
                            </div>
                            <div class="report-details">
                                <div class="detail-item"><strong>Descrizione:</strong><p><?php echo nl2br(htmlspecialchars($s['descrizione'])); ?></p></div>
                                <div class="detail-item"><strong>Area:</strong> <?php echo htmlspecialchars($s['area_competenza']); ?></div>
                                <div class="detail-item"><strong>Ultimo aggiornamento:</strong> <?php echo date('d/m/Y H:i', strtotime($s['data_ultima_modifica'])); ?></div>
                                <?php if (!empty($chat_messaggi[$s['id_segnalazione']])): ?>
                                    <div class="chat-messages">
                                        <?php foreach ($chat_messaggi[$s['id_segnalazione']] as $msg): ?>
                                            <div class="chat-message admin">
                                                <div class="bubble"><p><?php echo nl2br(htmlspecialchars($msg['messaggio_admin'])); ?></p><time><?php echo date('d/m H:i', strtotime($msg['data_messaggio'])); ?></time></div>
                                            </div>
                                            <?php if (empty($msg['risposta_utente']) && $s['stato'] !== 'Conclusa'): ?>
                                                <div class="chat-message user">
                                                    <form class="bubble reply-form" action="actions/rispondi_a_admin_action.php" method="POST">
                                                        <input type="hidden" name="id_messaggio" value="<?php echo $msg['id']; ?>">
                                                        <textarea name="risposta_utente" placeholder="La tua risposta..." required></textarea>
                                                        <button type="submit" class="nav-link-button">Invia</button>
                                                    </form>
                                                </div>
                                            <?php elseif (!empty($msg['risposta_utente'])): ?>
                                                <div class="chat-message user">
                                                    <div class="bubble"><p><?php echo nl2br(htmlspecialchars($msg['risposta_utente'])); ?></p><time><?php echo date('d/m H:i', strtotime($msg['data_risposta'])); ?></time></div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('reports-list-container')?.addEventListener('click', function(e) {
                const sum = e.target.closest('.report-summary'); if (sum) sum.closest('.report-record').classList.toggle('is-open');
            });
        });
    </script>
</body>
</html>
