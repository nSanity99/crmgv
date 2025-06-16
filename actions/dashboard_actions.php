<?php
session_start();
require_once __DIR__.'/../includes/db_config.php';

// Sicurezza base
if (!isset($_SESSION['loggedin']) || $_SESSION['ruolo'] !== 'admin' || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../login.php");
    exit;
}

$form_action = $_POST['form_action'] ?? '';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($conn->connect_error) {
    $_SESSION['error_message_usermgmt'] = "Errore di connessione al database.";
    header("Location: ../gestioneutenze.php");
    exit;
}

// --- LOGICA MODIFICA UTENTE ---
if ($form_action === 'edit_user_submit') {
    // ... (Il codice di questo blocco è già completo e corretto dalla nostra discussione precedente) ...
    // Lo includo per completezza.
    $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    $username = trim(htmlspecialchars($_POST['username']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $nome = trim(htmlspecialchars($_POST['nome']));
    $ruolo = trim(htmlspecialchars($_POST['ruolo']));
    $gruppo_lavoro = trim($_POST['gruppo_lavoro']);
    $password = $_POST['password'];
    $redirect_error_url = $_POST['redirect_error'];
    $redirect_success_url = $_POST['redirect_success'];

    if (!$user_id || empty($username) || !in_array($ruolo, ['user', 'admin'])) {
        $_SESSION['error_message_usermgmt'] = "Dati mancanti o non validi.";
        header("Location: " . $redirect_error_url);
        exit;
    }

    if (!empty($password)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE utenti SET username = ?, email = ?, nome = ?, ruolo = ?, gruppo_lavoro = ?, password_hash = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $username, $email, $nome, $ruolo, $gruppo_lavoro, $password_hash, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE utenti SET username = ?, email = ?, nome = ?, ruolo = ?, gruppo_lavoro = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $username, $email, $nome, $ruolo, $gruppo_lavoro, $user_id);
    }
    
    if ($stmt->execute()) {
        $_SESSION['success_message_usermgmt'] = "Utente '" . $username . "' aggiornato.";
        header("Location: " . $redirect_success_url);
    } else {
        $_SESSION['error_message_usermgmt'] = "Errore: l'username o l'email potrebbero già esistere.";
        header("Location: " . $redirect_error_url);
    }
    $stmt->close();
}

// --- LOGICA CREAZIONE UTENTE ---
elseif ($form_action === 'create_user_submit') {
    $username = trim(htmlspecialchars($_POST['username']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $nome = trim(htmlspecialchars($_POST['nome']));
    $ruolo = trim(htmlspecialchars($_POST['ruolo']));
    $gruppo_lavoro = trim($_POST['gruppo_lavoro']);
    $password = $_POST['password'];

    if (empty($username) || empty($password) || !in_array($ruolo, ['user', 'admin'])) {
        $_SESSION['error_message_usermgmt'] = "Username, password e ruolo sono obbligatori.";
        header("Location: ../gestioneutenze.php?action=create_user");
        exit;
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO utenti (username, email, nome, ruolo, gruppo_lavoro, password_hash) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $username, $email, $nome, $ruolo, $gruppo_lavoro, $password_hash);

    if ($stmt->execute()) {
        $_SESSION['success_message_usermgmt'] = "Nuovo utente '" . $username . "' creato con successo!";
        header("Location: ../gestioneutenze.php?action=user_created");
    } else {
        $_SESSION['error_message_usermgmt'] = "Errore: l'username o l'email potrebbero già esistere.";
        header("Location: ../gestioneutenze.php?action=create_user");
    }
    $stmt->close();
}

// --- LOGICA ELIMINAZIONE UTENTE ---
elseif ($form_action === 'delete_user_submit') {
    $user_id_to_delete = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);

    if ($user_id_to_delete == $_SESSION['user_id']) {
        $_SESSION['error_message_usermgmt'] = "Non puoi eliminare il tuo stesso account.";
        header("Location: ../gestioneutenze.php");
        exit;
    }
    
    if ($user_id_to_delete) {
        $stmt = $conn->prepare("DELETE FROM utenti WHERE id = ?");
        $stmt->bind_param("i", $user_id_to_delete);
        if ($stmt->execute()) {
             $_SESSION['success_message_usermgmt'] = "Utente eliminato con successo.";
        } else {
             $_SESSION['error_message_usermgmt'] = "Errore durante l'eliminazione dell'utente.";
        }
        $stmt->close();
    } else {
        $_SESSION['error_message_usermgmt'] = "ID utente non valido.";
    }
    header("Location: ../gestioneutenze.php");
    exit;
}

else {
    header("Location: ../dashboard.php");
}

$conn->close();
exit;
?>