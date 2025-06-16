-- Aggiornamenti per supporto notifiche dashboard

ALTER TABLE ordini
    ADD COLUMN IF NOT EXISTS `letto_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `stato_ordine`;

ALTER TABLE segnalazioni
    ADD COLUMN IF NOT EXISTS `letto_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `stato`;

ALTER TABLE segnalazioni_chat
    ADD COLUMN IF NOT EXISTS `letto_da_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `data_risposta`,
    ADD COLUMN IF NOT EXISTS `letto_da_utente` TINYINT(1) NOT NULL DEFAULT 0 AFTER `letto_da_admin`;

ALTER TABLE ordini_chat
    ADD COLUMN IF NOT EXISTS `letto_da_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `data_risposta`,
    ADD COLUMN IF NOT EXISTS `letto_da_utente` TINYINT(1) NOT NULL DEFAULT 0 AFTER `letto_da_admin`;
