<?php
session_start();
require_once __DIR__.'/includes/logger.php';

// Consentire accesso solo agli admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true ||
    !isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
    header("Location: login.php");
    exit;
}

logUserAction("Visualizzazione log di sistema da parte di '" . ($_SESSION['username'] ?? 'N/A') . "'");

$logFile = __DIR__ . '/logs/user_actions.log';
$log_entries = [];
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    // Mostra ultime 200 righe per non sovraccaricare la pagina
    $log_entries = array_slice($lines, -200);
}

$username_display = htmlspecialchars($_SESSION['username'] ?? 'N/A');
$user_role_display = htmlspecialchars($_SESSION['ruolo'] ?? 'N/A');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Utente</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background-color: #f8f9fa; color: #495057; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; }
        .page-outer-container { max-width: 1000px; margin: 25px auto; animation: fadeInSlideUp 0.6s ease-out forwards; }
        .module-header { display: flex; justify-content: space-between; align-items: center; background-color: #ffffff; padding: 18px 30px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); border-bottom: 4px solid #B08D57; margin-bottom: 30px; }
        .header-branding { display: flex; align-items: center; }
        .header-branding .logo { max-height: 45px; margin-right: 15px; display: block; }
        .header-titles h1 { font-size: 1.5em; color: #2E572E; margin: 0; font-weight: 600; line-height: 1.2; }
        .user-session-controls { display: flex; align-items: center; gap: 15px; }
        .log-container { background-color: #ffffff; padding: 25px 30px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); white-space: pre-wrap; font-family: monospace; }
        .logout-button, .nav-link-button { padding: 7px 14px; border-radius: 5px; color: white !important; text-decoration: none; font-weight: 500; transition: background-color 0.2s ease; }
        .nav-link-button { background-color: #6c757d; }
        .logout-button { background-color: #D42A2A; }
    </style>
</head>
<body>
    <div class="page-outer-container">
        <header class="module-header">
            <div class="header-branding">
                <a href="dashboard.php" class="header-logo-link"><img src="assets/logo.png" alt="Logo Gruppo Vitolo" class="logo"></a>
                <div class="header-titles">
                    <h1>Log di Sistema</h1>
                </div>
            </div>
            <div class="user-session-controls">
                <div class="user-info">
                    <span><strong><?php echo $username_display; ?></strong></span>
                    <span>(<?php echo $user_role_display; ?>)</span>
                </div>
                <a href="dashboard.php" class="nav-link-button">Dashboard</a>
                <a href="logout.php" class="logout-button">Logout</a>
            </div>
        </header>
        <main class="log-container">
            <?php if (!empty($log_entries)): ?>
                <?php foreach ($log_entries as $line): ?>
                    <?php echo htmlspecialchars($line) . "<br>\n"; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Log vuoto o non disponibile.</p>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
