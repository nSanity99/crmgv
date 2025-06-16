<?php
session_start();
require_once __DIR__.'/includes/db_config.php';

ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php_error.log');
error_reporting(E_ALL);

$ruolo_admin_atteso = 'admin';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== $ruolo_admin_atteso) {
    header("Location: login.php");
    exit;
}

$username_display = htmlspecialchars($_SESSION['username'] ?? '');
$user_role_display = htmlspecialchars($_SESSION['ruolo'] ?? '');

$filter_user = trim($_GET['user'] ?? '');
$filter_action = trim($_GET['action'] ?? '');
$filter_from = $_GET['from'] ?? '';
$filter_to = $_GET['to'] ?? '';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$logs = [];
$db_error_message = null;
if ($conn->connect_error) {
    $db_error_message = "Errore di connessione al database.";
} else {
    $sql = "SELECT id, user_id, username, action, details, action_time FROM user_logs WHERE 1";
    $params = [];
    $types = '';
    if ($filter_user !== '') { $sql .= " AND username LIKE ?"; $params[] = "%$filter_user%"; $types .= 's'; }
    if ($filter_action !== '') { $sql .= " AND action LIKE ?"; $params[] = "%$filter_action%"; $types .= 's'; }
    if ($filter_from !== '') { $sql .= " AND action_time >= ?"; $params[] = $filter_from . ' 00:00:00'; $types .= 's'; }
    if ($filter_to !== '') { $sql .= " AND action_time <= ?"; $params[] = $filter_to . ' 23:59:59'; $types .= 's'; }
    $sql .= " ORDER BY action_time DESC LIMIT 100";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($types)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        $stmt->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Log Azioni Utenti</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; margin:0; padding:0; }
        .page-outer-container { max-width: 1200px; margin: 20px auto; padding:0 15px; }
        .module-header { display:flex; justify-content:space-between; align-items:center; background-color:#fff; padding:20px 30px; border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,0.06); border-bottom:5px solid #B08D57; margin-bottom:30px; }
        .header-branding{ display:flex; align-items:center; }
        .header-branding .logo{ max-height:55px; margin-right:20px; }
        .header-titles h1{ font-size:1.6em; color:#2E572E; margin:0; font-weight:600; }
        .header-titles h2{ font-size:1em; color:#6c757d; margin:0; font-weight:400; }
        .user-session-controls{ text-align:right; font-size:0.9em; color:#555; display:flex; align-items:center; gap:15px; }
        .user-session-controls .nav-link-button, .user-session-controls .logout-button{ padding:8px 16px; font-size:0.9em; text-decoration:none; border-radius:5px; }
        .user-session-controls .nav-link-button{ background-color:#6c757d; color:#fff; }
        .user-session-controls .logout-button{ background-color:#D42A2A; color:#fff; }
        .filter-form{ margin-bottom:20px; background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.05); display:flex; gap:20px; align-items:flex-end; }
        .filter-form .filter-group{ display:flex; flex-direction:column; }
        .filter-form label{ font-weight:600; margin-bottom:5px; font-size:0.9em; }
        .filter-form input{ padding:8px; border:1px solid #ccc; border-radius:4px; }
        table.logs-table{ width:100%; border-collapse:collapse; font-size:0.95em; }
        table.logs-table th, table.logs-table td{ border:1px solid #dee2e6; padding:10px 12px; }
        table.logs-table th{ background-color:#f8f9fa; color:#495057; font-weight:600; }
        table.logs-table tr:nth-child(even){ background:#fcfdff; }
        table.logs-table tr:hover{ background:#eef4f8; }
        .footer-logo-area{ text-align:center; margin-top:40px; padding-top:25px; border-top:1px solid #e9ecef; }
        .footer-logo-area img{ max-width:60px; opacity:0.5; }
    </style>
</head>
<body>
<div class="page-outer-container">
    <header class="module-header">
        <div class="header-branding">
            <a href="dashboard.php" class="header-logo-link"><img src="assets/logo.png" alt="Logo Gruppo Vitolo" class="logo"></a>
            <div class="header-titles">
                <h1>Log Azioni</h1>
                <h2>Area Amministrazione</h2>
            </div>
        </div>
        <div class="user-session-controls">
            <span><strong><?php echo $username_display; ?></strong> (<?php echo $user_role_display; ?>)</span>
            <a href="dashboard.php" class="nav-link-button">Dashboard</a>
            <a href="logout.php" class="logout-button">Logout</a>
        </div>
    </header>

    <main class="module-content">
        <form method="get" action="logs.php" class="filter-form">
            <div class="filter-group">
                <label for="user">Utente</label>
                <input type="text" id="user" name="user" value="<?php echo htmlspecialchars($filter_user); ?>">
            </div>
            <div class="filter-group">
                <label for="action">Azione</label>
                <input type="text" id="action" name="action" value="<?php echo htmlspecialchars($filter_action); ?>">
            </div>
            <div class="filter-group">
                <label for="from">Da Data</label>
                <input type="date" id="from" name="from" value="<?php echo htmlspecialchars($filter_from); ?>">
            </div>
            <div class="filter-group">
                <label for="to">A Data</label>
                <input type="date" id="to" name="to" value="<?php echo htmlspecialchars($filter_to); ?>">
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="nav-link-button" style="background-color:#B08D57;">Filtra</button>
            </div>
        </form>

        <?php if ($db_error_message): ?>
            <p class="error-message"><?php echo $db_error_message; ?></p>
        <?php else: ?>
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Utente</th>
                        <th>Azione</th>
                        <th>Dettagli</th>
                        <th>Data/Ora</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo $log['id']; ?></td>
                                <td><?php echo htmlspecialchars($log['username']); ?></td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><?php echo htmlspecialchars($log['details']); ?></td>
                                <td><?php echo $log['action_time']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center;">Nessun log trovato.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
    <footer class="footer-logo-area">
        <img src="assets/logo.png" alt="Logo Gruppo Vitolo">
    </footer>
</div>
</body>
</html>
