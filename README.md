# CRM Gruppo Vitolo

Questo progetto ora utilizza [PHPMailer](https://github.com/PHPMailer/PHPMailer) per inviare una mail di notifica quando viene creato un nuovo ordine.

Per installare la dipendenza è necessario avere Composer. Dopo aver installato Composer, eseguire:

```bash
composer install
```

Quindi configurare i parametri SMTP nel file `includes/mailer/mailer_config.php` con le proprie credenziali.
