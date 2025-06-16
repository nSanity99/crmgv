<?php
// File centralizzato per le credenziali del database
$db_host = 'db2.contact.local'; 
$db_user = 'invoice'; 
$db_name = 'crm_gruppo_vitolo';
$db_pass= 'Nocciola2020!';
$conn_gu = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn_gu->connect_error) {
    die("Connessione fallita: " . $conn_gu->connect_error);
    }
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn_gu->connect_error);
    }
?>
