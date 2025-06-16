<?php
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Logout - Gruppo Vitolo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, html { height: 100%; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow: hidden; }
        body { background: linear-gradient(135deg,rgba(255, 74, 68, 0.8) 0%,rgb(87, 35, 35) 100%); position: relative; }
        #particles-js { position: absolute; width: 100%; height: 100%; z-index: 0; }
        .logout-container { position: relative; z-index: 1; max-width: 420px; margin: auto; margin-top: 6%; padding: 40px; background: rgba(255, 255, 255, 0.1); border-radius: 20px; backdrop-filter: blur(12px); box-shadow: 0 8px 32px rgba(0,0,0,0.25); animation: slideUp 1s ease-out forwards; text-align:center; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .logo { width: 100px; margin-bottom: 30px; display: block; margin-left: auto; margin-right: auto; animation: rotateIn 1s ease-out; filter: drop-shadow(0 0 10px #B08D57); }
        @keyframes rotateIn { from { opacity: 0; transform: scale(0.5) rotate(-180deg); } to { opacity: 1; transform: scale(1) rotate(0); } }
        h1 { font-size: 2em; color: #fff; margin-bottom: 10px; text-align: center; }
        p { color: #f0f0f0; margin-bottom: 30px; }
        a.button { background: #B08D57; color: white; padding: 12px 20px; border-radius: 8px; text-decoration: none; display: inline-block; transition: background 0.3s ease, transform 0.2s; }
        a.button:hover { background: #9c7b4c; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div id="particles-js"></div>
    <div class="logout-container">
        <img src="assets/logo.png" class="logo" alt="Logo Gruppo Vitolo">
        <h1>Logout effettuato</h1>
        <p>La sessione è stata terminata correttamente.</p>
        <a href="login.php" class="button">Torna al Login</a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <script>
        particlesJS("particles-js", {
            "particles": {
                "number": { "value": 60 },
                "color": { "value": "#ffffff" },
                "shape": { "type": "circle" },
                "opacity": { "value": 0.2 },
                "size": { "value": 3 },
                "move": { "enable": true, "speed": 1.5, "direction": "none", "out_mode": "out" },
                "line_linked": { "enable": true, "distance": 150, "color": "#ffffff", "opacity": 0.1, "width": 1 }
            },
            "interactivity": { "events": { "onhover": { "enable": true, "mode": "repulse" } } },
            "retina_detect": true
        });
    </script>
</body>
</html>
