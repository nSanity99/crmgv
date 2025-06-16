<?php
session_start();

// --- Blocco Debug e Configurazione Sessione/Log ---
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php_error.log');
error_reporting(E_ALL);
ini_set('display_errors', 0); 

$timestamp = date("Y-m-d H:i:s");
error_log("--- [{$timestamp}] Accesso a dashboard.php ---");

// Blocco di sicurezza flessibile
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}
$user_role_from_session = $_SESSION['ruolo'] ?? 'user';

$username_display = htmlspecialchars(isset($_SESSION['username']) ? $_SESSION['username'] : 'N/A');
$user_role_display = htmlspecialchars(isset($_SESSION['ruolo']) ? $_SESSION['ruolo'] : 'N/A');
?>
       
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gruppo Vitolo</title>
    <style>
        /* Stili CSS di base (invariati) */
        html, body { height: 100%; margin: 0; padding: 0; }
        body { background-color: #f8f9fa; color: #495057; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; display: flex; align-items: center; justify-content: center; }
        .dashboard-container { max-width: 1200px; width: 100%; padding: 0 15px; animation: fadeInSlideUp 0.6s ease-out forwards; margin: 25px auto; background-color: transparent; box-shadow: none; border-top: none; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; background-color: #ffffff; padding: 25px 40px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.06); border-bottom: 5px solid #B08D57; margin-bottom: 40px; }
        .header-branding { display: flex; align-items: center; }
        .header-branding .logo { max-height: 55px; margin-right: 20px; display: block; }
        .header-titles h1 { font-size: 1.8em; color: #2E572E; margin: 0; font-weight: 600; line-height: 1.2; }
        .header-titles h2 { font-size: 1em; color: #6c757d; margin: 0; font-weight: 400; letter-spacing: 0.5px; }
        .user-session-controls { text-align: right; font-size: 0.9em; color: #555; display: flex; align-items: center; gap: 15px; }
        .user-session-controls .user-info span { display: block; line-height: 1.3; }
        .user-session-controls strong { font-weight: 600; color: #333; }
        .user-session-controls .logout-button { padding: 8px 16px; font-size: 0.9em; background-color: #D42A2A; color: white !important; text-decoration: none; border-radius: 5px; transition: background-color 0.2s ease, transform 0.2s ease; font-weight: 500; }
        .user-session-controls .logout-button:hover { background-color: #c82333; transform: translateY(-1px); }
        .footer-logo-area { text-align: center; margin-top: 60px; padding-top: 30px; border-top: 1px solid #e9ecef; }
        .footer-logo-area img { max-width: 60px; opacity: 0.5; }
        @keyframes fadeInSlideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* Stili per le "Tool Cards" (invariati) */
        .tool-cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); gap: 30px; }
        .tool-card { background-color: #ffffff; border-radius: 8px; padding: 25px 30px; text-decoration: none; color: #495057; box-shadow: 0 4px 15px rgba(0,0,0,0.06); transition: transform 0.25s ease, box-shadow 0.25s ease; display: flex; flex-direction: column; border: 1px solid #dee2e6; }
        .tool-card:hover { transform: translateY(-5px); box-shadow: 0 7px 25px rgba(0,0,0,0.1); }
        .tool-card-header { display: flex; align-items: center; margin-bottom: 18px; }
        .tool-card-icon { font-size: 2.2em; color: #B08D57; background-color: rgba(176, 141, 87, 0.1); width: 50px; height: 50px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 15px; }
        .tool-card-title { font-size: 1.35em; color: #2E572E; margin: 0; font-weight: 600; line-height: 1.3; }
        .tool-card-description { font-size: 0.95em; color: #6c757d; line-height: 1.6; flex-grow: 1; margin-bottom: 0; }

        /* ========== NUOVI STILI PER LA DASHBOARD UTENTE ========== */
        .user-dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .category-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            border: 1px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .category-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #B08D57;
        }
        .category-card .icon { font-size: 3.5em; line-height: 1; margin-bottom: 20px; }
        .category-card .title { font-size: 1.5em; font-weight: 600; color: #2E572E; margin: 0; }
        .tool-submenu { display: none; /* Nascosto di default */ }
        .tool-submenu .back-button {
            display: inline-block;
            margin-bottom: 25px;
            background-color: #6c757d;
            color: white;
            padding: 8px 18px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
        }
        .tool-submenu .back-button:hover { background-color: #5a6268; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-branding">
                <a href="dashboard.php" class="header-logo-link"><img src="assets/logo.png" alt="Logo Gruppo Vitolo" class="logo"></a>
                <div class="header-titles">
                    <h1>Pannello Principale</h1>
                    <h2><?php echo ($user_role_from_session === 'admin') ? 'Vista Amministrazione' : 'Area Riservata'; ?></h2>
                </div>
            </div>
            <div class="user-session-controls">
                <div class="user-info">
                    <span>Accesso come: <strong><?php echo $username_display; ?></strong></span>
                    <span>(Ruolo: <strong><?php echo $user_role_display; ?></strong>)</span>
                </div>
                <a href="logout.php" class="logout-button">Logout</a>
            </div>
        </header>
        <?php if ($user_role_from_session === 'admin'): ?>
            <div class="tool-cards-grid">
                <a href="gestioneordini.php" class="tool-card">
                    <div class="tool-card-header"><div class="tool-card-icon">🛒</div><h3 class="tool-card-title">Gestione Ordini</h3></div>
                    <p class="tool-card-description">Approva o rifiuta le richieste di acquisto/ordine inviate.</p>
                </a>
                <a href="gestione_segnalazione.php" class="tool-card">
                    <div class="tool-card-header"><div class="tool-card-icon">🔔</div><h3 class="tool-card-title">Gestione Segnalazioni</h3></div>
                    <p class="tool-card-description">Visualizza e gestisci le segnalazioni inviate dagli utenti.</p>
                </a>
                <a href="evasioneordini.php" class="tool-card">
                    <div class="tool-card-header"><div class="tool-card-icon">🚚</div><h3 class="tool-card-title">Evasione Ordini</h3></div>
                    <p class="tool-card-description">Contrassegna come "evasi" i prodotti che sono stati approvati.</p>
                </a>
                <a href="riepilogo_ordini.php" class="tool-card">
                    <div class="tool-card-header"><div class="tool-card-icon">📊</div><h3 class="tool-card-title">Riepilogo Ordini</h3></div>
                    <p class="tool-card-description">Visualizza e filtra lo storico di tutti gli ordini per analisi.</p>
                </a>
                <a href="gestioneutenze.php" class="tool-card">
                    <div class="tool-card-header"><div class="tool-card-icon">👤</div><h3 class="tool-card-title">Gestione Utenze</h3></div>
                    <p class="tool-card-description">Crea, visualizza e modifica gli utenti del sistema.</p>
                </a>
                <a href="gestioneprodotti.php" class="tool-card">
                    <div class="tool-card-header"><div class="tool-card-icon">📦</div><h3 class="tool-card-title">Gestione Prodotti</h3></div>
                    <p class="tool-card-description">Aggiungi nuovi prodotti al catalogo.</p>
                </a>
            </div>
        <?php endif; ?>

        <?php if ($user_role_from_session === 'user'): ?>
            
            <div id="main-choices" class="user-dashboard-grid">
                <div class="category-card" data-target="acquisti-tools">
                    <div class="icon">🛍️</div>
                    <h2 class="title">Devo fare un acquisto/ordine</h2>
                </div>
                <div class="category-card" data-target="segnalazioni-tools">
                    <div class="icon">🛠️</div>
                    <h2 class="title">Devo fare una segnalazione</h2>
                </div>
            </div>

            <div id="acquisti-tools" class="tool-submenu">
                <a href="#" class="back-button">← Torna alle scelte</a>
                <div class="tool-cards-grid">
                    <a href="form_page.php" class="tool-card">
                        <div class="tool-card-header"><div class="tool-card-icon">📦</div><h3 class="tool-card-title">Crea Richiesta Acquisto/Ordine</h3></div>
                        <p class="tool-card-description">Crea una nuova richiesta di acquisto per materiali o servizi.</p>
                    </a>
                    <a href="i_miei_ordini.php" class="tool-card">
                        <div class="tool-card-header"><div class="tool-card-icon">🧾</div><h3 class="tool-card-title">Le Mie Richieste</h3></div>
                        <p class="tool-card-description">Controlla lo stato delle tue richieste di acquisto passate.</p>
                    </a>
                </div>
            </div>

            <div id="segnalazioni-tools" class="tool-submenu">
                <a href="#" class="back-button">← Torna alle scelte</a>
                <div class="tool-cards-grid">
                    <a href="segnalazioni_form.php" class="tool-card">
                        <div class="tool-card-header"><div class="tool-card-icon">🗣️</div><h3 class="tool-card-title">Invia una Segnalazione</h3></div>
                        <p class="tool-card-description">Segnala un problema o invia una richiesta di intervento (non di acquisto).</p>
                    </a>
                    <a href="le_mie_segnalazioni.php" class="tool-card">
                        <div class="tool-card-header"><div class="tool-card-icon">🗒️</div><h3 class="tool-card-title">Le Mie Segnalazioni</h3></div>
                        <p class="tool-card-description">Rivedi le tue segnalazioni inviate e controlla il loro stato.</p>
                    </a>
                </div>
            </div>

        <?php endif; ?>

        <div class="footer-logo-area">
            <img src="assets/logo.png" alt="Logo Gruppo Vitolo">
        </div>
    </div>

<script>
// La nuova logica JS si applica solo se siamo nella dashboard utente
const isUserDashboard = document.getElementById('main-choices');

if (isUserDashboard) {
    const mainChoices = document.getElementById('main-choices');
    const acquistiSubmenu = document.getElementById('acquisti-tools');
    const segnalazioniSubmenu = document.getElementById('segnalazioni-tools');
    const categoryCards = document.querySelectorAll('.category-card');
    const backButtons = document.querySelectorAll('.back-button');

    // Funzione per mostrare un sottomenu specifico
    function showSubmenu(targetId) {
        mainChoices.style.display = 'none';
        if (targetId === 'acquisti-tools') {
            acquistiSubmenu.style.display = 'block';
        } else if (targetId === 'segnalazioni-tools') {
            segnalazioniSubmenu.style.display = 'block';
        }
    }

    // Funzione per tornare alla vista principale
    function showMainChoices() {
        mainChoices.style.display = 'grid';
        acquistiSubmenu.style.display = 'none';
        segnalazioniSubmenu.style.display = 'none';
    }

    // Aggiungi l'evento click alle card principali
    categoryCards.forEach(card => {
        card.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.dataset.target;
            showSubmenu(targetId);
        });
    });

    // Aggiungi l'evento click ai pulsanti "Indietro"
    backButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            showMainChoices();
        });
    });
}
</script>

</body>
</html>