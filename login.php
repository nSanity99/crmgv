<?php
session_start();

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: dashboard.php");
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php_errors.log'); // Cambiato per Linux

// Includi il file di configurazione con le credenziali
require_once __DIR__ . '/includes/db_config.php';

$login_error = '';
error_log("--- Inizio tentativo di login (con ruoli) --- [" . date("Y-m-d H:i:s") . "]");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username_input = trim($_POST['username']);
    $password_input = $_POST['password'];

    error_log("[Login con ruoli] Input ricevuto - Username: [{$username_input}]");

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($conn->connect_error) {
        error_log("[Login con ruoli] Errore di connessione al database: " . $conn->connect_error);
        $login_error = "Errore di sistema. Riprova più tardi.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password_hash, ruolo, nome FROM utenti WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $username_input);
            if (!$stmt->execute()) {
                $login_error = "Errore di sistema. Riprova più tardi.";
            } else {
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($user_id, $db_username, $hashed_password_from_db, $db_ruolo, $db_user_fullname);
                    $stmt->fetch();
                    if (password_verify($password_input, $hashed_password_from_db)) {
                        session_regenerate_id(true);
                        $_SESSION['loggedin'] = true;
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['username'] = $db_username;
                        $_SESSION['ruolo'] = $db_ruolo;
                        $_SESSION['user_fullname'] = $db_user_fullname;
                        header("Location: dashboard.php");
                        exit;
                    } else {
                        $login_error = "Nome utente o password non validi.";
                    }
                } else {
                    $login_error = "Nome utente o password non validi.";
                }
            }
            $stmt->close();
        } else {
            $login_error = "Errore di sistema. Riprova più tardi.";
        }
        $conn->close();
    }
}
error_log("--- Fine tentativo di login (con ruoli) --- [" . date("Y-m-d H:i:s") . "]");
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Login - Gruppo Vitolo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0; padding: 0; box-sizing: border-box;
        }

        body, html {
            height: 100%;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow: hidden;
        }

        body {
            background: linear-gradient(135deg,rgba(255, 74, 68, 0.8) 0%,rgb(87, 35, 35) 100%);
            position: relative;
        }

        #particles-js {
            position: absolute;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .login-container {
            position: relative;
            z-index: 1;
            max-width: 420px;
            margin: auto;
            margin-top: 6%;
            padding: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            backdrop-filter: blur(12px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            animation: slideUp 1s ease-out forwards;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo {
    width: 100px;
    margin-bottom: 50px;
    display: block;
    margin-left: auto;
    margin-right: auto;
    animation: rotateIn 1s ease-out;
    filter: drop-shadow(0 0 10px #B08D57);
}


        @keyframes rotateIn {
            from { opacity: 0; transform: scale(0.5) rotate(-180deg); }
            to { opacity: 1; transform: scale(1) rotate(0); }
        }

        h1 {
    font-size: 2em;
    color: #fff;
    margin-bottom: 5px;
    text-align: center;
}


        h2 {
    font-size: 1em;
    color: #f0f0f0;
    margin-bottom: 30px;
    font-weight: 300;
    text-align: center;
}


        form {
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            color: #fff;
            font-size: 0.9em;
            margin-bottom: 5px;
            display: block;
            font-weight: 500;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 1em;
            transition: background 0.3s ease;
        }

        input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.35);
        }

        button[type="submit"] {
            width: 100%;
            padding: 12px;
            background: #B08D57;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s;
        }

        button:hover {
            background: #9c7b4c;
            transform: translateY(-2px);
        }

        .error-message {
            background-color: rgba(255, 0, 0, 0.2);
            color: #fff;
            border-left: 5px solid #ff4d4d;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 10% 20px;
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <div id="particles-js"></div>

    <div class="login-container">
        <img src="assets/logo.png" class="logo" alt="Logo Gruppo Vitolo">
        <h1>Gruppo Vitolo</h1>
        <h2>Accesso Riservato</h2>

        <?php if (!empty($login_error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($login_error); ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST" novalidate>
            <div class="form-group">
                <label for="username">Nome Utente:</label>
                <input type="text" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" name="login">Accedi</button>
        </form>
    </div>

    <!-- Particles.js -->
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <script>
        particlesJS("particles-js", {
            "particles": {
                "number": { "value": 60 },
                "color": { "value": "#ffffff" },
                "shape": { "type": "circle" },
                "opacity": { "value": 0.2 },
                "size": { "value": 3 },
                "move": {
                    "enable": true,
                    "speed": 1.5,
                    "direction": "none",
                    "out_mode": "out"
                },
                "line_linked": {
                    "enable": true,
                    "distance": 150,
                    "color": "#ffffff",
                    "opacity": 0.1,
                    "width": 1
                }
            },
            "interactivity": {
                "events": {
                    "onhover": { "enable": true, "mode": "repulse" }
                }
            },
            "retina_detect": true
        });
    </script>
</body>
</html>
