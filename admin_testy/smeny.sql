-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Počítač: 127.0.0.1
-- Vytvořeno: Čtv 26. bře 2026, 17:18
-- Verze serveru: 10.4.32-MariaDB
-- Verze PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Databáze: `comeback`
--

-- --------------------------------------------------------

--
-- Struktura tabulky `smeny_aktualizace`
--

CREATE TABLE `smeny_aktualizace` (
  `id_smeny_aktualizace` bigint(20) UNSIGNED NOT NULL,
  `start_day` date NOT NULL COMMENT 'Pondělí týdne, který se právě zpracovává / byl zpracován',
  `skip_weeks` int(11) NOT NULL COMMENT 'Hodnota skipWeeks použitá pro stažení',
  `id_pob` int(10) UNSIGNED NOT NULL COMMENT 'Pobočka',
  `stav` enum('cekajici','bezi','hotovo','chyba') NOT NULL DEFAULT 'cekajici',
  `datum_od` date NOT NULL DEFAULT '2020-10-12' COMMENT 'Spodní hranice historie',
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `posledni_ok` datetime DEFAULT NULL COMMENT 'Kdy byl tento týden+pobočka úspěšně dotažen',
  `pocet_bloku` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `pocet_hodin` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `chyba_text` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `smeny_plan`
--

CREATE TABLE `smeny_plan` (
  `id_smeny_plan` bigint(20) UNSIGNED NOT NULL,
  `start_day` date NOT NULL COMMENT 'Pondělí daného týdne ze Směn',
  `datum` date NOT NULL COMMENT 'Konkrétní den směny',
  `id_pob` int(10) UNSIGNED NOT NULL COMMENT 'Pobočka',
  `id_user` int(10) UNSIGNED NOT NULL COMMENT 'Uživatel / zaměstnanec',
  `id_slot` int(10) UNSIGNED NOT NULL COMMENT 'Slot / typ role ze Směn',
  `cas_od` time NOT NULL COMMENT 'Začátek bloku',
  `cas_do` time NOT NULL COMMENT 'Konec bloku',
  `zdroj` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Zdroj dat',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

--
-- Indexy pro exportované tabulky
--

--
-- Indexy pro tabulku `smeny_aktualizace`
--
ALTER TABLE `smeny_aktualizace`
  ADD PRIMARY KEY (`id_smeny_aktualizace`),
  ADD UNIQUE KEY `uq_akt_start_pob` (`start_day`,`id_pob`),
  ADD KEY `idx_akt_stav` (`stav`),
  ADD KEY `idx_akt_skip` (`skip_weeks`),
  ADD KEY `idx_akt_started` (`started_at`),
  ADD KEY `idx_akt_finished` (`finished_at`);

--
-- Indexy pro tabulku `smeny_plan`
--
ALTER TABLE `smeny_plan`
  ADD PRIMARY KEY (`id_smeny_plan`),
  ADD UNIQUE KEY `uq_plan_blok` (`datum`,`id_pob`,`id_user`,`id_slot`,`cas_od`,`cas_do`),
  ADD KEY `idx_plan_start_day` (`start_day`),
  ADD KEY `idx_plan_datum` (`datum`),
  ADD KEY `idx_plan_pob_datum` (`id_pob`,`datum`),
  ADD KEY `idx_plan_user_datum` (`id_user`,`datum`),
  ADD KEY `idx_plan_slot_datum` (`id_slot`,`datum`);

--
-- AUTO_INCREMENT pro tabulky
--

--
-- AUTO_INCREMENT pro tabulku `smeny_aktualizace`
--
ALTER TABLE `smeny_aktualizace`
  MODIFY `id_smeny_aktualizace` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `smeny_plan`
--
ALTER TABLE `smeny_plan`
  MODIFY `id_smeny_plan` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
