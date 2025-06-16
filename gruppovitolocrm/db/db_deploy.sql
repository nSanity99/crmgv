-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versione server:              10.4.28-MariaDB - mariadb.org binary distribution
-- S.O. server:                  Win64
-- HeidiSQL Versione:            12.10.0.7000
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dump della struttura del database gruppo_vitolo_db
CREATE DATABASE IF NOT EXISTS `gruppo_vitolo_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `gruppo_vitolo_db`;

-- Dump della struttura di tabella gruppo_vitolo_db.dettagli_ordine
CREATE TABLE IF NOT EXISTS `dettagli_ordine` (
  `id_dettaglio_ordine` int(11) NOT NULL AUTO_INCREMENT,
  `id_ordine` int(11) NOT NULL,
  `nome_prodotto` varchar(255) NOT NULL,
  `quantita` int(11) NOT NULL,
  `unita_misura` varchar(50) NOT NULL,
  `note_prodotto` text DEFAULT NULL,
  `stato_prodotto` varchar(50) NOT NULL DEFAULT 'Inviato',
  `data_evasione` datetime DEFAULT NULL,
  `id_utente_decisione` int(11) DEFAULT NULL,
  `data_decisione` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_dettaglio_ordine`),
  KEY `idx_id_ordine` (`id_ordine`),
  KEY `idx_id_utente_decisione` (`id_utente_decisione`),
  CONSTRAINT `dettagli_ordine_ibfk_1` FOREIGN KEY (`id_ordine`) REFERENCES `ordini` (`id_ordine`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `dettagli_ordine_ibfk_2` FOREIGN KEY (`id_utente_decisione`) REFERENCES `utenti` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Dump dei dati della tabella gruppo_vitolo_db.dettagli_ordine: ~0 rows (circa)

-- Dump della struttura di tabella gruppo_vitolo_db.ordini
CREATE TABLE IF NOT EXISTS `ordini` (
  `id_ordine` int(11) NOT NULL AUTO_INCREMENT,
  `data_richiesta` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id_utente_richiedente` int(11) DEFAULT NULL,
  `nome_richiedente` varchar(150) NOT NULL,
  `centro_costo` varchar(100) NOT NULL,
  `stato_ordine` varchar(50) NOT NULL DEFAULT 'Inviato',
  `consenti_modifica` tinyint(1) NOT NULL DEFAULT 0,
  `fattura_file` varchar(255) DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_ultima_modifica` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_ordine`),
  KEY `idx_id_utente_richiedente` (`id_utente_richiedente`),
  CONSTRAINT `ordini_ibfk_1` FOREIGN KEY (`id_utente_richiedente`) REFERENCES `utenti` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Dump dei dati della tabella gruppo_vitolo_db.ordini: ~0 rows (circa)

-- Dump della struttura di tabella gruppo_vitolo_db.segnalazioni
CREATE TABLE IF NOT EXISTS `segnalazioni` (
  `id_segnalazione` int(11) NOT NULL AUTO_INCREMENT,
  `id_utente_segnalante` int(11) DEFAULT NULL,
  `nome_utente_segnalante` varchar(150) NOT NULL,
  `data_invio` timestamp NOT NULL DEFAULT current_timestamp(),
  `titolo` varchar(255) NOT NULL,
  `descrizione` text NOT NULL,
  `area_competenza` varchar(100) NOT NULL,
  `stato` varchar(50) NOT NULL DEFAULT 'Inviata',
  `note_interne` text DEFAULT NULL,
  `data_ultima_modifica` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `messaggio_admin` text DEFAULT NULL,
  `risposta_utente` text DEFAULT NULL,
  PRIMARY KEY (`id_segnalazione`),
  KEY `idx_id_utente_segnalante` (`id_utente_segnalante`),
  CONSTRAINT `fk_segnalazioni_utenti` FOREIGN KEY (`id_utente_segnalante`) REFERENCES `utenti` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dump dei dati della tabella gruppo_vitolo_db.segnalazioni: ~2 rows (circa)
INSERT INTO `segnalazioni` (`id_segnalazione`, `id_utente_segnalante`, `nome_utente_segnalante`, `data_invio`, `titolo`, `descrizione`, `area_competenza`, `stato`, `note_interne`, `data_ultima_modifica`, `messaggio_admin`, `risposta_utente`) VALUES
	(3, 2, 'users', '2025-06-07 21:14:35', 'test', 'test', 'Generale', 'Conclusa', 'Cad', '2025-06-07 22:10:06', 'Che problema ha?', 'Vari'),
	(4, 2, 'users', '2025-06-07 22:11:02', 'Stanza B12 PC non funzionante', 'Il PC non funziona', 'IT / Informatica', 'Conclusa', 'da', '2025-06-07 22:24:37', NULL, NULL);

-- Dump della struttura di tabella gruppo_vitolo_db.segnalazioni_chat
CREATE TABLE IF NOT EXISTS `segnalazioni_chat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_segnalazione` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `messaggio_admin` text DEFAULT NULL,
  `risposta_utente` text DEFAULT NULL,
  `data_messaggio` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_risposta` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_segnalazione` (`id_segnalazione`),
  KEY `id_utente` (`id_utente`),
  CONSTRAINT `segnalazioni_chat_ibfk_1` FOREIGN KEY (`id_segnalazione`) REFERENCES `segnalazioni` (`id_segnalazione`) ON DELETE CASCADE,
  CONSTRAINT `segnalazioni_chat_ibfk_2` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dump dei dati della tabella gruppo_vitolo_db.segnalazioni_chat: ~9 rows (circa)
INSERT INTO `segnalazioni_chat` (`id`, `id_segnalazione`, `id_utente`, `messaggio_admin`, `risposta_utente`, `data_messaggio`, `data_risposta`) VALUES
        (1, 3, 1, 'aaa', 'che?', '2025-06-07 21:56:19', '2025-06-07 21:56:29'),
        (2, 3, 1, 'aaaaaa', 'hai finito?', '2025-06-07 21:56:42', '2025-06-07 21:56:52'),
        (3, 3, 1, 'non ho parole', '111', '2025-06-07 22:05:57', '2025-06-07 22:08:52'),
        (4, 3, 1, 'VabbÃ¨ te lo chiudo', ',', '2025-06-07 22:09:23', '2025-06-07 22:10:06'),
        (5, 4, 1, 'Ciao,\r\nSpecificami il problema nel dettaglio.\r\nGrazie', 'Non si accende, ho controllato anche i cavi.. non so cosa dirti', '2025-06-07 22:11:37', '2025-06-07 22:12:13'),
        (6, 4, 1, 'Contattato Ufficio IT, in attesa di intervento. Al termine rispondi qui', 'Risolto grazie', '2025-06-07 22:12:34', '2025-06-07 22:12:48'),
        (7, 4, 1, 'aaa', NULL, '2025-06-07 22:15:33', NULL),
        (8, 4, 1, 'aaaad2231', NULL, '2025-06-07 22:16:57', NULL),
        (9, 4, 1, 'adsdasd', NULL, '2025-06-07 22:24:37', NULL);

-- Dump della struttura di tabella gruppo_vitolo_db.ordini_chat
CREATE TABLE IF NOT EXISTS `ordini_chat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ordine` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `messaggio_admin` text DEFAULT NULL,
  `risposta_utente` text DEFAULT NULL,
  `data_messaggio` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_risposta` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_ordine` (`id_ordine`),
  KEY `id_utente` (`id_utente`),
  CONSTRAINT `ordini_chat_ibfk_1` FOREIGN KEY (`id_ordine`) REFERENCES `ordini` (`id_ordine`) ON DELETE CASCADE,
  CONSTRAINT `ordini_chat_ibfk_2` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------------
-- Tabella ordini_modifiche: traccia delle modifiche apportate dagli utenti
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ordini_modifiche` (
  `id_modifica` int(11) NOT NULL AUTO_INCREMENT,
  `id_ordine` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `prima` text NOT NULL,
  `dopo` text NOT NULL,
  `data_modifica` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_modifica`),
  KEY `idx_modifica_ordine` (`id_ordine`),
  CONSTRAINT `modifiche_ordine_ibfk_1` FOREIGN KEY (`id_ordine`) REFERENCES `ordini` (`id_ordine`) ON DELETE CASCADE,
  CONSTRAINT `modifiche_utente_ibfk_2` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Dump della struttura di tabella gruppo_vitolo_db.utenti
CREATE TABLE IF NOT EXISTS `utenti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `nome` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `ruolo` varchar(20) NOT NULL DEFAULT 'user',
  `gruppo_lavoro` varchar(50) DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Dump dei dati della tabella gruppo_vitolo_db.utenti: ~2 rows (circa)
INSERT INTO `utenti` (`id`, `username`, `email`, `nome`, `password_hash`, `ruolo`, `gruppo_lavoro`, `data_creazione`) VALUES
        (1, 'admin', 'admin@gruppovitolo.example.com', 'admin', '$2y$10$9RMP49bT0CRS9I.MXuIa7ek2SHfovBVWezAMjYvXTyz5oq.2EV3NO', 'admin', 'Amministrazione', '2025-06-06 07:36:44'),
        (2, 'users', '', 'users', '$2y$10$jCb4tU2C6hc99e4gFJUCTePCTwJhtK7BuK1lF046bJrscFDw4ikVi', 'user', 'Amministrazione', '2025-06-06 07:36:44');

-- ------------------------------------------------------------------
-- Tabella categorie_prodotti: raggruppa i prodotti per categoria
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categorie_prodotti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nome_categoria` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `categorie_prodotti` (`id`, `nome`) VALUES
        (1, 'Generale');

-- ------------------------------------------------------------------
-- Tabella catalogo_prodotti: elenco di prodotti disponibili per l'app
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `catalogo_prodotti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `categoria_id` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nome` (`nome`),
  KEY `idx_categoria` (`categoria_id`),
  CONSTRAINT `catalogo_categoria_fk` FOREIGN KEY (`categoria_id`) REFERENCES `categorie_prodotti` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Esempio di prodotti iniziali
INSERT INTO `catalogo_prodotti` (`id`, `nome`) VALUES
        (1, 'Carta A4'),
        (2, 'Toner Stampante');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
