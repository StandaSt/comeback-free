-- db/sql_karty_prava.sql * Verze: V2 * Aktualizace: 07.03.2026
-- Migrační SQL pro karty + práva + audit výjimek.

START TRANSACTION;

-- 1) Definice karet (zdroj pravdy pro render karet v UI)
CREATE TABLE IF NOT EXISTS `karty` (
  `id_karta` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kod` VARCHAR(80) NOT NULL,
  `nazev` VARCHAR(120) NOT NULL,
  `soubor` VARCHAR(255) NOT NULL,
  `min_role` TINYINT UNSIGNED NOT NULL DEFAULT 9,
  `poradi` INT NOT NULL DEFAULT 100,
  `aktivni` TINYINT(1) NOT NULL DEFAULT 1,
  `zalozeno` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `upraveno` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_karta`),
  UNIQUE KEY `uq_karty_kod` (`kod`),
  KEY `ix_karty_aktivni_poradi` (`aktivni`, `poradi`, `id_karta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Individuální výjimky oprávnění ke kartám (aktuální stav)
CREATE TABLE IF NOT EXISTS `karty_vyjimky` (
  `id_vyjimka` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_user` INT UNSIGNED NOT NULL,
  `id_karta` INT UNSIGNED NOT NULL,
  `akce` ENUM('allow', 'deny') NOT NULL,
  `aktivni` TINYINT(1) NOT NULL DEFAULT 1,
  `zalozeno` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `upraveno` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_vyjimka`),
  UNIQUE KEY `uq_karty_vyjimky_user_karta` (`id_user`, `id_karta`),
  KEY `ix_karty_vyjimky_karta` (`id_karta`),
  KEY `ix_karty_vyjimky_aktivni` (`aktivni`),
  CONSTRAINT `fk_karty_vyjimky_user`
    FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_karty_vyjimky_karta`
    FOREIGN KEY (`id_karta`) REFERENCES `karty` (`id_karta`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Audit historie změn výjimek
CREATE TABLE IF NOT EXISTS `karty_vyjimky_log` (
  `id_log` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_user_cil` INT UNSIGNED NOT NULL,
  `id_karta` INT UNSIGNED NOT NULL,
  `stara_akce` ENUM('allow', 'deny') NULL,
  `stara_aktivni` TINYINT(1) NULL,
  `nova_akce` ENUM('allow', 'deny') NULL,
  `nova_aktivni` TINYINT(1) NULL,
  `provedl_id_user` INT UNSIGNED NOT NULL,
  `kdy` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `poznamka` VARCHAR(255) NULL,
  PRIMARY KEY (`id_log`),
  KEY `ix_karty_vyjimky_log_cil` (`id_user_cil`, `kdy`),
  KEY `ix_karty_vyjimky_log_karta` (`id_karta`, `kdy`),
  KEY `ix_karty_vyjimky_log_provedl` (`provedl_id_user`, `kdy`),
  CONSTRAINT `fk_karty_vyjimky_log_user_cil`
    FOREIGN KEY (`id_user_cil`) REFERENCES `user` (`id_user`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_karty_vyjimky_log_user_provedl`
    FOREIGN KEY (`provedl_id_user`) REFERENCES `user` (`id_user`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_karty_vyjimky_log_karta`
    FOREIGN KEY (`id_karta`) REFERENCES `karty` (`id_karta`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

-- Výchozí poznámka:
-- Nová karta má být defaultně skrytá -> nastav min_role=9 (admin).
-- Render karet čte jen aktivni=1, řazení ORDER BY poradi, id_karta.
