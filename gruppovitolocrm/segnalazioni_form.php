<?php
session_start();
require_once __DIR__.'/includes/db_config.php';

// Sicurezza: Verifica che l'utente sia loggato
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Preparazione dati per il form
$richiedente_nome = (!empty($_SESSION['user_fullname'])) ? htmlspecialchars($_SESSION['user_fullname']) : htmlspecialchars($_SESSION['username']);
$id_utente_richiedente = $_SESSION['user_id'];

// Opzioni per i dropdown
$aree_di_competenza = ["Manutenzione", "IT / Informatica", "Pulizie", "Generale"];
sort($aree_di_competenza);

// Gestione messaggi di feedback
$feedback_message = '';
$feedback_type = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $feedback_message = 'Segnalazione inviata con successo!';
        $feedback_type = 'success';
    } elseif ($_GET['status'] === 'error') {
        $feedback_message = 'Errore durante l\'invio della segnalazione. Riprova.';
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
    <title>Invia Segnalazione - Gruppo Vitolo</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Stili ripresi e adattati da form_page.php per coerenza */
        html { scroll-behavior: smooth; }
        body { background-color: #f8f9fa; color: #495057; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
        .page-outer-container { max-width: 900px; padding: 0; animation: fadeInSlideUp 0.6s ease-out forwards; margin: 25px auto; }
        .module-header { display: flex; justify-content: space-between; align-items: center; background-color: #ffffff; padding: 18px 30px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); border-bottom: 4px solid #B08D57; margin-bottom: 30px; }
        .header-branding { display: flex; align-items: center; }
        .header-branding .logo { max-height: 45px; margin-right: 15px; }
        .header-titles h1 { font-size: 1.5em; color: #2E572E; margin: 0; font-weight: 600; }
        .header-titles h2 { font-size: 0.9em; color: #6c757d; margin: 0; }
        .user-session-controls { text-align: right; font-size: 0.9em; display: flex; align-items: center; gap: 15px; }
        .user-session-controls strong { font-weight: 600; }
        .nav-link-button, .logout-button { padding: 7px 14px; font-size: 0.9em; color: white !important; text-decoration: none; border-radius: 5px; transition: all 0.2s ease; font-weight: 500; }
        .nav-link-button { background-color: #6c757d; }
        .nav-link-button:hover { background-color: #5a6268; }
        .logout-button { background-color: #D42A2A; }
        .logout-button:hover { background-color: #c82333; }
        .module-content { background-color: #ffffff; padding: 25px 30px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
        .form-row { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size:0.95em; }
        .form-group input[type="text"], .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 5px; font-size: 1em; box-sizing: border-box; }
        .form-group textarea { min-height: 120px; resize: vertical; }
        .action-button { background-color: #B08D57; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 1.1em; transition: all 0.2s ease; font-weight: 500; }
        .action-button:hover { background-color: #9c7b4c; }
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
                    <h1>Modulo di Segnalazione</h1>
                    <h2>Gruppo Vitolo</h2>
                </div>
            </div>
            <div class="user-session-controls">
                <span>Accesso come: <strong><?php echo $richiedente_nome; ?></strong></span>
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

            <form id="segnalazione-form" action="actions/submit_segnalazione_action.php" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="titolo">Oggetto / Titolo della Segnalazione</label>
                        <input type="text" id="titolo" name="titolo" required placeholder="Es. Problema stampante ufficio, luce fulminata corridoio, ...">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="area_competenza">Macro Area</label>
                        <select id="area_competenza" name="area_competenza" required>
                            <option value="">Seleziona un'area...</option>
                            <?php foreach ($aree_di_competenza as $area): ?>
                                <option value="<?php echo htmlspecialchars($area); ?>"><?php echo htmlspecialchars($area); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="descrizione">Descrizione Dettagliata</label>
                        <textarea id="descrizione" name="descrizione" required placeholder="Descrivi qui il problema o la richiesta nel modo più dettagliato possibile..."></textarea>
                    </div>
                </div>
                
                <div class="main-form-actions">
                    <button type="submit" class="action-button">Invia Segnalazione</button> 
                </div>
            </form>
        </main>
        <footer class="footer-logo-area">
            <img src="assets/logo.png" alt="Logo Gruppo Vitolo">
        </footer>
    </div>
</body>
</html>