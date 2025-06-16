<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    error_log('[order_mailer] vendor/autoload.php mancante. Eseguire composer install.');
    return;
}

require_once __DIR__ . '/mailer_config.php';

function sendOrderNotification($orderId, $nomeRichiedente, $centroCosto, array $prodotti) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $GLOBALS['mail_host'];
        $mail->Port = $GLOBALS['mail_port'];
        $mail->SMTPSecure = $GLOBALS['mail_secure'];
        $mail->SMTPAuth = true;
        $mail->Username = $GLOBALS['mail_username'];
        $mail->Password = $GLOBALS['mail_password'];

        $mail->setFrom($GLOBALS['mail_from'], $GLOBALS['mail_from_name']);
        $mail->addAddress($GLOBALS['mail_to']);

        $mail->isHTML(true);
        $mail->Subject = "Nuovo ordine #{$orderId}";

        $body = "<h2>Dettagli Ordine #{$orderId}</h2>";
        $body .= "<p><strong>Richiedente:</strong> " . htmlspecialchars($nomeRichiedente, ENT_QUOTES, 'UTF-8') . "<br>";
        $body .= "<strong>Centro di Costo:</strong> " . htmlspecialchars($centroCosto, ENT_QUOTES, 'UTF-8') . "</p>";
        $body .= "<h3>Prodotti</h3><ul>";
        foreach ($prodotti as $p) {
            $nome = htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8');
            $qty = (int)($p['quantity'] ?? 0);
            $unit = htmlspecialchars($p['unit'] ?? '', ENT_QUOTES, 'UTF-8');
            $note = htmlspecialchars($p['notes'] ?? '', ENT_QUOTES, 'UTF-8');
            $body .= "<li>{$nome} - {$qty} {$unit}";
            if ($note) {
                $body .= "<br><em>{$note}</em>";
            }
            $body .= "</li>";
        }
        $body .= "</ul>";

        $mail->Body = $body;
        $mail->send();
    } catch (Exception $e) {
        error_log('[order_mailer] Errore invio email: ' . $mail->ErrorInfo);
    }
}
?>
