-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Počítač: 127.0.0.1
-- Vytvořeno: Čtv 23. čec 2026, 19:20
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
-- Struktura tabulky `admin_info`
--

CREATE TABLE `admin_info` (
  `id_admin_info` int(10) UNSIGNED NOT NULL,
  `typ` varchar(50) NOT NULL,
  `nadpis` varchar(120) NOT NULL DEFAULT 'Admin info',
  `obsah` text NOT NULL,
  `pozn` text NOT NULL,
  `id_odeslal` int(10) UNSIGNED DEFAULT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `admin_info_user`
--

CREATE TABLE `admin_info_user` (
  `id_admin_info_user` int(10) UNSIGNED NOT NULL,
  `id_admin_info` int(10) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `token` char(64) NOT NULL,
  `odeslano` datetime DEFAULT NULL,
  `zobrazeno` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `api_restia`
--

CREATE TABLE `api_restia` (
  `id_api_restia` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED DEFAULT NULL,
  `id_login` bigint(20) UNSIGNED DEFAULT NULL,
  `kdy_start` datetime(6) NOT NULL,
  `ms` int(10) UNSIGNED NOT NULL,
  `metoda` varchar(10) NOT NULL,
  `endpoint` varchar(200) NOT NULL,
  `url` text DEFAULT NULL,
  `active_pos_id` char(36) DEFAULT NULL,
  `http_status` smallint(5) UNSIGNED DEFAULT NULL,
  `bytes_out` int(10) UNSIGNED DEFAULT NULL,
  `bytes_in` int(10) UNSIGNED DEFAULT NULL,
  `pocet_zaznamu` int(10) UNSIGNED DEFAULT NULL,
  `total_count` int(10) UNSIGNED DEFAULT NULL,
  `chyba` text DEFAULT NULL,
  `poznamka` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `api_smeny`
--

CREATE TABLE `api_smeny` (
  `id_api_smeny` bigint(20) UNSIGNED NOT NULL,
  `kdy_start` datetime(6) NOT NULL,
  `ms` int(10) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED DEFAULT NULL,
  `id_login` int(10) UNSIGNED DEFAULT NULL,
  `bytes_out` int(10) UNSIGNED DEFAULT NULL,
  `bytes_in` int(10) UNSIGNED DEFAULT NULL,
  `chyba` text DEFAULT NULL,
  `ok` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `card_icons`
--

CREATE TABLE `card_icons` (
  `id_ikon` int(10) UNSIGNED NOT NULL,
  `nazev` varchar(100) NOT NULL,
  `soubor` varchar(255) NOT NULL,
  `poradi` int(10) UNSIGNED NOT NULL DEFAULT 100,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `zadano` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `cis_akce`
--

CREATE TABLE `cis_akce` (
  `id_akce` int(11) NOT NULL,
  `akce_text` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `cis_chyby`
--

CREATE TABLE `cis_chyby` (
  `id_cis_chyba` int(10) UNSIGNED NOT NULL,
  `kod` varchar(50) NOT NULL,
  `uroven` tinyint(3) UNSIGNED NOT NULL COMMENT '1=INFO, 2=WARN, 3=ERROR',
  `oblast` varchar(30) NOT NULL,
  `popis` varchar(255) NOT NULL,
  `hint` varchar(255) DEFAULT NULL,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `zadano` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `cis_doruceni`
--

CREATE TABLE `cis_doruceni` (
  `id_doruceni` smallint(5) UNSIGNED NOT NULL,
  `nazev` varchar(50) NOT NULL,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `cis_mzda_typ`
--

CREATE TABLE `cis_mzda_typ` (
  `id_mzda_typ` tinyint(3) UNSIGNED NOT NULL,
  `kod` varchar(30) NOT NULL,
  `nazev` varchar(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `cis_obj_platby`
--

CREATE TABLE `cis_obj_platby` (
  `id_platba` smallint(5) UNSIGNED NOT NULL,
  `nazev` varchar(50) NOT NULL,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `cis_obj_platforma`
--

CREATE TABLE `cis_obj_platforma` (
  `id_platforma` smallint(5) UNSIGNED NOT NULL,
  `kod` varchar(50) NOT NULL,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `cis_obj_stav`
--

CREATE TABLE `cis_obj_stav` (
  `id_stav` smallint(5) UNSIGNED NOT NULL,
  `nazev` varchar(50) NOT NULL,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `cis_polozka_kat`
--

CREATE TABLE `cis_polozka_kat` (
  `id_kat` int(10) UNSIGNED NOT NULL,
  `nazev` varchar(120) NOT NULL,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `cis_polozky`
--

CREATE TABLE `cis_polozky` (
  `id_polozka` bigint(20) UNSIGNED NOT NULL,
  `pos_id` varchar(50) NOT NULL,
  `pol_nazev` varchar(255) NOT NULL,
  `id_polozka_kat` int(10) UNSIGNED DEFAULT NULL,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `zadano` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `cis_prac_zarazeni`
--

CREATE TABLE `cis_prac_zarazeni` (
  `id_prac_zar` int(10) UNSIGNED NOT NULL,
  `nazev` varchar(50) NOT NULL,
  `zadal` int(10) UNSIGNED DEFAULT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp(),
  `aktivni` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `cis_role`
--

CREATE TABLE `cis_role` (
  `id_role` int(10) UNSIGNED NOT NULL,
  `id_role_smeny` int(11) DEFAULT NULL,
  `role` varchar(50) NOT NULL,
  `card_color` char(7) DEFAULT NULL,
  `zadal` int(10) UNSIGNED DEFAULT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp(),
  `aktivni` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `cis_slot`
--

CREATE TABLE `cis_slot` (
  `id_slot` int(10) UNSIGNED NOT NULL,
  `slot` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `cis_sloupce`
--

CREATE TABLE `cis_sloupce` (
  `id_nazev_sloupce` int(10) UNSIGNED NOT NULL,
  `nazev_sloupce` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `dr_pracovni`
--

CREATE TABLE `dr_pracovni` (
  `id_dr` bigint(20) UNSIGNED NOT NULL,
  `datum_reportu` date NOT NULL,
  `id_pob` int(10) UNSIGNED NOT NULL,
  `oteviral` int(10) UNSIGNED DEFAULT NULL,
  `zaviral` int(10) UNSIGNED DEFAULT NULL,
  `hotovost` decimal(10,2) DEFAULT NULL,
  `terminal` decimal(10,2) DEFAULT NULL,
  `stravenky` decimal(10,2) DEFAULT NULL,
  `vydaje_benzin` decimal(10,2) DEFAULT NULL,
  `vydaje_auta` decimal(10,2) DEFAULT NULL,
  `vydaje_suroviny` decimal(10,2) DEFAULT NULL,
  `vydaje_ostatni` decimal(10,2) DEFAULT NULL,
  `vydaje_phm_soukrome` decimal(10,2) DEFAULT NULL,
  `poznamka` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `dr_pracovni_osoby`
--

CREATE TABLE `dr_pracovni_osoby` (
  `id_dr_osoby` bigint(20) UNSIGNED NOT NULL,
  `id_dr` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `id_slot` tinyint(3) UNSIGNED NOT NULL COMMENT '1=instor, 2=kuryr',
  `smena_od` time DEFAULT NULL,
  `smena_do` time DEFAULT NULL,
  `pauza` decimal(5,2) DEFAULT NULL,
  `odpracovano` decimal(6,2) DEFAULT NULL,
  `rozvozu_manual` smallint(5) UNSIGNED DEFAULT NULL,
  `vlastni_vuz` tinyint(1) NOT NULL DEFAULT 0,
  `vyplatit_phm` decimal(10,2) NOT NULL DEFAULT 0.00,
  `poradi` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `helpdesk`
--

CREATE TABLE `helpdesk` (
  `id_helpdesk` int(10) UNSIGNED NOT NULL,
  `id_user_zalozil` int(10) UNSIGNED NOT NULL,
  `typ` varchar(30) NOT NULL DEFAULT 'chyba',
  `stav` varchar(30) NOT NULL DEFAULT 'novy',
  `verejny` tinyint(1) NOT NULL DEFAULT 1,
  `predmet` varchar(160) NOT NULL,
  `popis` text NOT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp(),
  `upraveno` datetime DEFAULT NULL,
  `posledni_zprava` datetime DEFAULT NULL,
  `uzavreno` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `helpdesk_notifikace`
--

CREATE TABLE `helpdesk_notifikace` (
  `id_helpdesk_notifikace` int(10) UNSIGNED NOT NULL,
  `id_helpdesk` int(10) UNSIGNED NOT NULL,
  `id_helpdesk_zprava` int(10) UNSIGNED DEFAULT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `typ` varchar(40) NOT NULL,
  `text` varchar(255) NOT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp(),
  `precteno` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `helpdesk_priloha`
--

CREATE TABLE `helpdesk_priloha` (
  `id_helpdesk_priloha` int(10) UNSIGNED NOT NULL,
  `id_helpdesk` int(10) UNSIGNED NOT NULL,
  `id_helpdesk_zprava` int(10) UNSIGNED DEFAULT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `puvodni_nazev` varchar(255) NOT NULL,
  `ulozeny_nazev` varchar(255) NOT NULL,
  `cesta` varchar(255) NOT NULL,
  `mime_typ` varchar(120) DEFAULT NULL,
  `velikost_b` int(10) UNSIGNED DEFAULT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `helpdesk_read`
--

CREATE TABLE `helpdesk_read` (
  `id_helpdesk_read` int(10) UNSIGNED NOT NULL,
  `id_helpdesk` int(10) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `precteno` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `helpdesk_sledujici`
--

CREATE TABLE `helpdesk_sledujici` (
  `id_helpdesk_sledujici` int(10) UNSIGNED NOT NULL,
  `id_helpdesk` int(10) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `duvod` varchar(40) NOT NULL DEFAULT 'sleduje',
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `helpdesk_snapshot`
--

CREATE TABLE `helpdesk_snapshot` (
  `id_helpdesk_snapshot` int(10) UNSIGNED NOT NULL,
  `id_helpdesk` int(10) UNSIGNED NOT NULL,
  `id_helpdesk_zprava` int(10) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `jmeno` varchar(160) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `role` int(10) UNSIGNED DEFAULT NULL,
  `role_nazev` varchar(120) DEFAULT NULL,
  `pobocky_json` longtext DEFAULT NULL,
  `sloty_json` longtext DEFAULT NULL,
  `karty_json` longtext DEFAULT NULL,
  `obdobi_json` longtext DEFAULT NULL,
  `session_json` longtext DEFAULT NULL,
  `raw_json` longtext DEFAULT NULL,
  `url_stranky` varchar(500) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `ip_adresa` varchar(45) DEFAULT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `helpdesk_zprava`
--

CREATE TABLE `helpdesk_zprava` (
  `id_helpdesk_zprava` int(10) UNSIGNED NOT NULL,
  `id_helpdesk` int(10) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `typ_autora` varchar(20) NOT NULL DEFAULT 'user',
  `zprava` text NOT NULL,
  `systemova` tinyint(1) NOT NULL DEFAULT 0,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_adresa`
--

CREATE TABLE `hr_adresa` (
  `id_adresa` int(10) UNSIGNED NOT NULL,
  `id_person` int(10) UNSIGNED NOT NULL,
  `typ_adresy` enum('trvala','kontaktni') NOT NULL,
  `ulice` varchar(150) DEFAULT NULL,
  `cislo_popisne` varchar(20) DEFAULT NULL,
  `cislo_orientacni` varchar(20) DEFAULT NULL,
  `mesto` varchar(100) DEFAULT NULL,
  `psc` varchar(10) DEFAULT NULL,
  `stat` varchar(80) NOT NULL DEFAULT 'Česká republika',
  `zadal` int(10) UNSIGNED NOT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp(),
  `platny` tinyint(1) NOT NULL DEFAULT 1,
  `aktualizace` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_akce`
--

CREATE TABLE `hr_akce` (
  `id_akce` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED DEFAULT NULL,
  `id_person` int(10) UNSIGNED DEFAULT NULL,
  `id_dokument` int(10) UNSIGNED DEFAULT NULL,
  `verze` smallint(5) UNSIGNED DEFAULT NULL,
  `akce` varchar(80) NOT NULL,
  `detail` text DEFAULT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_cis_pozadavek_stav`
--

CREATE TABLE `hr_cis_pozadavek_stav` (
  `id_stav` tinyint(3) UNSIGNED NOT NULL,
  `nazev` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_dokument`
--

CREATE TABLE `hr_dokument` (
  `id_dokument` int(10) UNSIGNED NOT NULL,
  `verze` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `id_person` int(10) UNSIGNED NOT NULL,
  `id_uchazec_dotaznik` int(10) UNSIGNED DEFAULT NULL,
  `id_dokument_typ` int(10) UNSIGNED NOT NULL,
  `id_dokument_stav` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `zdroj` tinyint(3) UNSIGNED NOT NULL,
  `cislo_dokladu` varchar(80) DEFAULT NULL,
  `platnost_od` date DEFAULT NULL,
  `platnost_do` date DEFAULT NULL,
  `podpis_do` datetime DEFAULT NULL,
  `poznamka` text DEFAULT NULL,
  `odeslano` datetime DEFAULT NULL,
  `podepsano` datetime DEFAULT NULL,
  `zadal` int(10) UNSIGNED NOT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp(),
  `platny` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_dokument_soubor`
--

CREATE TABLE `hr_dokument_soubor` (
  `id_dokument_soubor` int(10) UNSIGNED NOT NULL,
  `id_dokument` int(10) UNSIGNED NOT NULL,
  `verze` smallint(5) UNSIGNED NOT NULL,
  `poradi` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `puvodni_nazev` varchar(255) NOT NULL,
  `ulozeny_nazev` varchar(255) NOT NULL,
  `cesta` varchar(500) NOT NULL,
  `mime_typ` varchar(120) DEFAULT NULL,
  `velikost_b` int(10) UNSIGNED DEFAULT NULL,
  `soubor_hash` char(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_dokument_stav`
--

CREATE TABLE `hr_dokument_stav` (
  `id_dokument_stav` int(10) UNSIGNED NOT NULL,
  `nazev` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_dokument_typ`
--

CREATE TABLE `hr_dokument_typ` (
  `id_dokument_typ` int(10) UNSIGNED NOT NULL,
  `nazev` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_dotaznik_typ`
--

CREATE TABLE `hr_dotaznik_typ` (
  `id_dotaznik_typ` int(10) UNSIGNED NOT NULL,
  `kod` varchar(40) NOT NULL,
  `nazev` varchar(120) NOT NULL,
  `poradi` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `verze` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `zadal` int(10) UNSIGNED DEFAULT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_email`
--

CREATE TABLE `hr_email` (
  `id_email` int(10) UNSIGNED NOT NULL,
  `id_person` int(10) UNSIGNED NOT NULL,
  `id_email_typ` int(10) UNSIGNED NOT NULL,
  `email` varchar(150) NOT NULL,
  `hlavni` tinyint(1) NOT NULL DEFAULT 0,
  `poznamka` varchar(255) DEFAULT NULL,
  `zadal` int(10) UNSIGNED NOT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp(),
  `platny` tinyint(1) NOT NULL DEFAULT 1,
  `aktualizace` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_email_typ`
--

CREATE TABLE `hr_email_typ` (
  `id_email_typ` int(10) UNSIGNED NOT NULL,
  `nazev` varchar(50) NOT NULL,
  `poradi` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `zadal` int(10) UNSIGNED NOT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_mzdy_mesic`
--

CREATE TABLE `hr_mzdy_mesic` (
  `id_hr_mzda_mesic` int(10) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED DEFAULT NULL,
  `rok` smallint(5) UNSIGNED NOT NULL,
  `mesic` tinyint(3) UNSIGNED NOT NULL,
  `datum_od` date NOT NULL,
  `datum_do` date NOT NULL,
  `mzda_typ` enum('hodinova','fix','kombinovana','bez_mzdy') NOT NULL DEFAULT 'hodinova',
  `slot` enum('instor','kuryr') DEFAULT NULL,
  `hodiny` decimal(10,2) DEFAULT NULL,
  `hodinova_sazba` decimal(10,2) DEFAULT NULL,
  `mesicni_fix` decimal(10,2) DEFAULT NULL,
  `isk` decimal(10,4) DEFAULT NULL,
  `bonus_1` decimal(10,2) DEFAULT NULL,
  `bonus_2` decimal(10,2) DEFAULT NULL,
  `bonus_cista` decimal(10,2) DEFAULT NULL,
  `cista_mzda` decimal(10,2) DEFAULT NULL,
  `hruba_mzda` decimal(10,2) DEFAULT NULL,
  `superhruba_mzda` decimal(10,2) DEFAULT NULL,
  `naklad_col_hod` decimal(10,2) DEFAULT NULL,
  `naklad_col_den` decimal(10,2) DEFAULT NULL,
  `je_manager_col` tinyint(1) NOT NULL DEFAULT 0,
  `import_jmeno` varchar(120) DEFAULT NULL,
  `import_list` varchar(120) DEFAULT NULL,
  `zdroj` varchar(50) DEFAULT NULL,
  `stav` enum('import','rozpracovano','uzavreno') NOT NULL DEFAULT 'import',
  `poznamka` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_osobni_udaje`
--

CREATE TABLE `hr_osobni_udaje` (
  `id_osobni_udaje` int(10) UNSIGNED NOT NULL,
  `id_person` int(10) UNSIGNED NOT NULL,
  `jmeno` varchar(60) NOT NULL,
  `prijmeni` varchar(80) NOT NULL,
  `rodne_prijmeni` varchar(80) DEFAULT NULL,
  `datum_narozeni` date DEFAULT NULL,
  `rodne_cislo` varchar(20) DEFAULT NULL,
  `pohlavi` enum('muz','zena','neuvedeno') NOT NULL DEFAULT 'neuvedeno',
  `statni_obcanstvi` varchar(80) DEFAULT NULL,
  `misto_narozeni` varchar(100) DEFAULT NULL,
  `poznamka` text DEFAULT NULL,
  `zadal` int(10) UNSIGNED NOT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp(),
  `platny` tinyint(1) NOT NULL DEFAULT 1,
  `aktualizace` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_person`
--

CREATE TABLE `hr_person` (
  `id_person` int(10) UNSIGNED NOT NULL,
  `vztah` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `id_user` int(10) UNSIGNED DEFAULT NULL,
  `osobni_cislo` varchar(20) DEFAULT NULL,
  `titul` varchar(50) DEFAULT NULL,
  `titul_za` varchar(50) DEFAULT NULL,
  `jmeno` varchar(60) NOT NULL,
  `prijmeni` varchar(80) NOT NULL,
  `foto` varchar(500) DEFAULT NULL,
  `id_uchazec_stav` int(10) UNSIGNED DEFAULT NULL,
  `id_uchazec_zdroj` int(10) UNSIGNED DEFAULT NULL,
  `id_person_duplicita` int(10) UNSIGNED DEFAULT NULL,
  `id_slot` int(10) UNSIGNED DEFAULT NULL,
  `pracoviste_preference` varchar(255) DEFAULT NULL,
  `mozny_nastup` date DEFAULT NULL,
  `ocekavana_mzda` decimal(10,2) DEFAULT NULL,
  `povidani` text DEFAULT NULL,
  `interni_poznamka` text DEFAULT NULL,
  `prvni_kontakt` datetime NOT NULL DEFAULT current_timestamp(),
  `posledni_aktivita` datetime DEFAULT NULL,
  `zadal` int(10) UNSIGNED DEFAULT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp(),
  `upravil` int(10) UNSIGNED DEFAULT NULL,
  `upraveno` datetime DEFAULT NULL,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_person_pracoviste`
--

CREATE TABLE `hr_person_pracoviste` (
  `id_person_pracoviste` int(10) UNSIGNED NOT NULL,
  `id_person` int(10) UNSIGNED NOT NULL,
  `id_pob` int(10) UNSIGNED NOT NULL,
  `hlavni` tinyint(1) NOT NULL DEFAULT 0,
  `platnost_od` date NOT NULL,
  `platnost_do` date DEFAULT NULL,
  `zadal` int(10) UNSIGNED NOT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp(),
  `platny` tinyint(1) NOT NULL DEFAULT 1,
  `aktualizace` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_person_zarazeni`
--

CREATE TABLE `hr_person_zarazeni` (
  `id_person_zarazeni` int(10) UNSIGNED NOT NULL,
  `id_person` int(10) UNSIGNED NOT NULL,
  `id_slot` int(10) UNSIGNED NOT NULL,
  `hlavni` tinyint(1) NOT NULL DEFAULT 0,
  `platnost_od` date NOT NULL,
  `platnost_do` date DEFAULT NULL,
  `zadal` int(10) UNSIGNED NOT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp(),
  `platny` tinyint(1) NOT NULL DEFAULT 1,
  `aktualizace` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_pozadavek`
--

CREATE TABLE `hr_pozadavek` (
  `id_hr_pozadavek` int(10) UNSIGNED NOT NULL,
  `id_pob` int(10) UNSIGNED NOT NULL,
  `id_slot` int(10) UNSIGNED NOT NULL,
  `upresneni` text DEFAULT NULL,
  `stav` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `id_person` int(10) UNSIGNED DEFAULT NULL,
  `vyrizeno_kdy` datetime DEFAULT NULL,
  `vyridil` int(10) UNSIGNED DEFAULT NULL,
  `zadal` int(10) UNSIGNED NOT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp(),
  `zruseno_kdy` datetime DEFAULT NULL,
  `zrusil` int(10) UNSIGNED DEFAULT NULL,
  `poznamka` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_pracovni_vztah`
--

CREATE TABLE `hr_pracovni_vztah` (
  `id_pracovni_vztah` int(10) UNSIGNED NOT NULL,
  `id_person` int(10) UNSIGNED NOT NULL,
  `id_pracovni_vztah_typ` int(10) UNSIGNED NOT NULL,
  `datum_nastupu` date NOT NULL,
  `datum_ukonceni` date DEFAULT NULL,
  `uvazek` decimal(4,2) DEFAULT NULL,
  `hodin_tydne` decimal(5,2) DEFAULT NULL,
  `doba_urcita` tinyint(1) NOT NULL DEFAULT 0,
  `delka_zk_doby` smallint(5) UNSIGNED DEFAULT NULL,
  `poznamka` text DEFAULT NULL,
  `zadal` int(10) UNSIGNED NOT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp(),
  `platny` tinyint(1) NOT NULL DEFAULT 1,
  `aktualizace` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_pracovni_vztah_typ`
--

CREATE TABLE `hr_pracovni_vztah_typ` (
  `id_pracovni_vztah_typ` int(10) UNSIGNED NOT NULL,
  `kod` varchar(20) NOT NULL,
  `nazev` varchar(100) NOT NULL,
  `poradi` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `zadal` int(10) UNSIGNED NOT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_sazby`
--

CREATE TABLE `hr_sazby` (
  `id_hr_sazba` int(10) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED DEFAULT NULL,
  `import_jmeno` varchar(120) DEFAULT NULL,
  `platnost_od` date NOT NULL,
  `platnost_do` date DEFAULT NULL,
  `id_mzda_typ` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `hodinova_sazba` decimal(10,2) DEFAULT NULL,
  `mesicni_fix` decimal(10,2) DEFAULT NULL,
  `je_manager_col` tinyint(1) NOT NULL DEFAULT 0,
  `naklad_col_hod` decimal(10,2) DEFAULT NULL,
  `naklad_col_den` decimal(10,2) DEFAULT NULL,
  `zdroj` varchar(50) DEFAULT NULL,
  `poznamka` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_telefon`
--

CREATE TABLE `hr_telefon` (
  `id_telefon` int(10) UNSIGNED NOT NULL,
  `id_person` int(10) UNSIGNED NOT NULL,
  `id_telefon_typ` int(10) UNSIGNED NOT NULL,
  `telefon` varchar(30) NOT NULL,
  `telefon_normalizovany` varchar(30) DEFAULT NULL,
  `hlavni` tinyint(1) NOT NULL DEFAULT 0,
  `poznamka` varchar(255) DEFAULT NULL,
  `zadal` int(10) UNSIGNED NOT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp(),
  `platny` tinyint(1) NOT NULL DEFAULT 1,
  `aktualizace` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_telefon_typ`
--

CREATE TABLE `hr_telefon_typ` (
  `id_telefon_typ` int(10) UNSIGNED NOT NULL,
  `nazev` varchar(50) NOT NULL,
  `poradi` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `zadal` int(10) UNSIGNED NOT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_uchazec_aktivita`
--

CREATE TABLE `hr_uchazec_aktivita` (
  `id_uchazec_aktivita` int(10) UNSIGNED NOT NULL,
  `id_person` int(10) UNSIGNED NOT NULL,
  `id_uchazec_aktivita_typ` int(10) UNSIGNED NOT NULL,
  `planovano_na` datetime DEFAULT NULL,
  `provedeno_kdy` datetime DEFAULT NULL,
  `trvani_minut` smallint(5) UNSIGNED DEFAULT NULL,
  `predmet` varchar(200) DEFAULT NULL,
  `obsah` text DEFAULT NULL,
  `vysledek` text DEFAULT NULL,
  `dalsi_krok` text DEFAULT NULL,
  `dalsi_krok_do` datetime DEFAULT NULL,
  `id_user` int(10) UNSIGNED DEFAULT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp(),
  `zruseno` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_uchazec_aktivita_typ`
--

CREATE TABLE `hr_uchazec_aktivita_typ` (
  `id_uchazec_aktivita_typ` int(10) UNSIGNED NOT NULL,
  `kod` varchar(40) NOT NULL,
  `nazev` varchar(100) NOT NULL,
  `poradi` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `zadal` int(10) UNSIGNED DEFAULT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_uchazec_dotaznik`
--

CREATE TABLE `hr_uchazec_dotaznik` (
  `id_uchazec_dotaznik` int(10) UNSIGNED NOT NULL,
  `id_person` int(10) UNSIGNED NOT NULL,
  `id_dotaznik_typ` int(10) UNSIGNED NOT NULL,
  `stav` enum('pripraven','odeslan','otevren','rozpracovan','vyplnen','zrusen','vyprsel') NOT NULL DEFAULT 'pripraven',
  `odeslano` datetime DEFAULT NULL,
  `otevreno` datetime DEFAULT NULL,
  `ulozeno` datetime DEFAULT NULL,
  `data_json` longtext DEFAULT NULL,
  `ip_adresa` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `zadal` int(10) UNSIGNED DEFAULT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_uchazec_podminky`
--

CREATE TABLE `hr_uchazec_podminky` (
  `id_uchazec_podminky` int(10) UNSIGNED NOT NULL,
  `id_person` int(10) UNSIGNED NOT NULL,
  `id_slot` int(10) UNSIGNED NOT NULL,
  `id_pob` int(10) UNSIGNED NOT NULL,
  `id_pracovni_vztah_typ` int(10) UNSIGNED NOT NULL,
  `datum_nastupu` date NOT NULL,
  `datum_ukonceni` date DEFAULT NULL,
  `uvazek` decimal(4,2) DEFAULT NULL,
  `hodin_tydne` decimal(5,2) DEFAULT NULL,
  `delka_zk_doby` smallint(5) UNSIGNED DEFAULT NULL,
  `mzda_castka` decimal(10,2) NOT NULL,
  `mzda_hodinova` tinyint(1) NOT NULL DEFAULT 1,
  `poznamka` text DEFAULT NULL,
  `zadal` int(10) UNSIGNED NOT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp(),
  `aktualizace` datetime DEFAULT NULL,
  `platny` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_uchazec_pozice`
--

CREATE TABLE `hr_uchazec_pozice` (
  `id_uchazec_pozice` int(10) UNSIGNED NOT NULL,
  `id_person` int(10) UNSIGNED NOT NULL,
  `id_slot` int(10) UNSIGNED NOT NULL,
  `hlavni` tinyint(1) NOT NULL DEFAULT 0,
  `poznamka` varchar(255) DEFAULT NULL,
  `zadal` int(10) UNSIGNED DEFAULT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp(),
  `aktivni` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_uchazec_pracoviste`
--

CREATE TABLE `hr_uchazec_pracoviste` (
  `id_uchazec_pracoviste` int(10) UNSIGNED NOT NULL,
  `id_person` int(10) UNSIGNED NOT NULL,
  `id_pob` int(10) UNSIGNED NOT NULL,
  `hlavni` tinyint(1) NOT NULL DEFAULT 0,
  `poznamka` varchar(255) DEFAULT NULL,
  `zadal` int(10) UNSIGNED DEFAULT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp(),
  `aktivni` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_uchazec_stav`
--

CREATE TABLE `hr_uchazec_stav` (
  `id_uchazec_stav` int(10) UNSIGNED NOT NULL,
  `kod` varchar(40) NOT NULL,
  `nazev` varchar(100) NOT NULL,
  `poradi` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `konecny` tinyint(1) NOT NULL DEFAULT 0,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `zadal` int(10) UNSIGNED DEFAULT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_uchazec_token`
--

CREATE TABLE `hr_uchazec_token` (
  `id_uchazec_token` int(10) UNSIGNED NOT NULL,
  `id_person` int(10) UNSIGNED NOT NULL,
  `id_uchazec_dotaznik` int(10) UNSIGNED DEFAULT NULL,
  `typ` varchar(40) NOT NULL,
  `token_hash` binary(32) NOT NULL,
  `platny_do` datetime NOT NULL,
  `odeslano` datetime DEFAULT NULL,
  `prvni_otevreni` datetime DEFAULT NULL,
  `posledni_otevreni` datetime DEFAULT NULL,
  `pocet_otevreni` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `pouzito_kdy` datetime DEFAULT NULL,
  `zneplatneno_kdy` datetime DEFAULT NULL,
  `zadal` int(10) UNSIGNED DEFAULT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp(),
  `aktivni` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `hr_uchazec_zdroj`
--

CREATE TABLE `hr_uchazec_zdroj` (
  `id_uchazec_zdroj` int(10) UNSIGNED NOT NULL,
  `nazev` varchar(100) NOT NULL,
  `poradi` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `zadal` int(10) UNSIGNED DEFAULT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `init_scripty`
--

CREATE TABLE `init_scripty` (
  `id_init_script` int(10) UNSIGNED NOT NULL,
  `hlavni_oblast` varchar(50) NOT NULL,
  `krok` varchar(50) NOT NULL,
  `nazev` varchar(150) NOT NULL,
  `zdroj_dat` varchar(150) NOT NULL,
  `soubor` varchar(255) NOT NULL,
  `poradi` int(10) UNSIGNED DEFAULT NULL,
  `db_tabulky` text DEFAULT NULL,
  `procenta` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `spousti` varchar(100) NOT NULL COMMENT 'odkud se spouští',
  `poznamka` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `karty`
--

CREATE TABLE `karty` (
  `id_karta` int(10) UNSIGNED NOT NULL,
  `nazev` varchar(120) NOT NULL,
  `subtitle_min` varchar(255) DEFAULT NULL,
  `subtitle_max` varchar(255) DEFAULT NULL,
  `soubor` varchar(255) NOT NULL,
  `min_role` tinyint(3) UNSIGNED NOT NULL DEFAULT 9,
  `poradi` int(11) NOT NULL DEFAULT 100,
  `refresh_op` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'op = období a pobočky',
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `zalozeno` datetime NOT NULL DEFAULT current_timestamp(),
  `upraveno` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `kontrolni_prehledy`
--

CREATE TABLE `kontrolni_prehledy` (
  `id_prehled` int(10) UNSIGNED NOT NULL,
  `id_pob` int(10) UNSIGNED NOT NULL,
  `rok` smallint(5) UNSIGNED NOT NULL,
  `mesic` tinyint(3) UNSIGNED NOT NULL,
  `obj_restia` int(10) UNSIGNED DEFAULT NULL,
  `obj_is` int(10) UNSIGNED DEFAULT NULL,
  `rozdil` int(11) NOT NULL DEFAULT 0,
  `stav` enum('ke_kontrole','srovnano','uzavreno','manual_check','chyba') NOT NULL DEFAULT 'ke_kontrole',
  `posledni_kontrola` datetime DEFAULT NULL,
  `posledni_oprava` datetime DEFAULT NULL,
  `chyba_text` varchar(500) DEFAULT NULL,
  `zmeneno` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `loader_json`
--

CREATE TABLE `loader_json` (
  `loader_key` varchar(64) NOT NULL,
  `loader_json` longtext NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `log_chyby`
--

CREATE TABLE `log_chyby` (
  `id_log_chyby` bigint(20) UNSIGNED NOT NULL,
  `kdy` datetime NOT NULL DEFAULT current_timestamp(),
  `id_user` int(10) UNSIGNED DEFAULT NULL,
  `modul` varchar(100) NOT NULL,
  `akce` varchar(150) NOT NULL,
  `kod` varchar(100) NOT NULL,
  `zprava` varchar(255) NOT NULL,
  `detail` text DEFAULT NULL,
  `soubor` varchar(255) DEFAULT NULL,
  `radek` int(10) UNSIGNED DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `data_json` longtext DEFAULT NULL,
  `vyreseno` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `poznamka` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `objednavky_kontrola`
--

CREATE TABLE `objednavky_kontrola` (
  `id_kontrola` int(10) UNSIGNED NOT NULL,
  `rok` smallint(5) UNSIGNED NOT NULL,
  `mesic` tinyint(3) UNSIGNED NOT NULL,
  `id_pob` int(11) NOT NULL,
  `restia_pocet` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `db_pocet` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rozdil` int(11) NOT NULL DEFAULT 0,
  `kontrola_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `objednavky_restia`
--

CREATE TABLE `objednavky_restia` (
  `id_obj` bigint(20) UNSIGNED NOT NULL,
  `id_pob` int(10) UNSIGNED NOT NULL,
  `report` date DEFAULT NULL COMMENT 'PD 06:00-06:00 podle createdAt',
  `id_platforma` smallint(5) UNSIGNED NOT NULL,
  `restia_id_obj` varchar(64) DEFAULT NULL COMMENT 'id',
  `restia_created_at` datetime DEFAULT NULL COMMENT 'createdAt',
  `restia_order_number` varchar(64) DEFAULT NULL COMMENT 'orderNumber',
  `profil_typ` varchar(50) DEFAULT NULL COMMENT 'profile.type',
  `profil_nazev` varchar(150) DEFAULT NULL COMMENT 'profile.name',
  `profil_menu_id` char(36) DEFAULT NULL COMMENT 'profile.menuId',
  `profil_pos_id` varchar(64) DEFAULT NULL COMMENT 'profile.posId',
  `profil_url` varchar(255) DEFAULT NULL COMMENT 'profile.url',
  `je_vyzvednuti` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'isPickup',
  `je_v_restauraci` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'isInRestaurant',
  `je_vlastni_rozvoz` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'isSelfDelivery',
  `kuryr_poradi` int(10) UNSIGNED DEFAULT NULL COMMENT 'courierOrder',
  `pos_import_stav` varchar(50) DEFAULT NULL COMMENT 'posImportStatus',
  `rest_obj` varchar(80) NOT NULL COMMENT 'id',
  `short_code` varchar(50) DEFAULT NULL COMMENT 'shortCode',
  `seriove_cislo` varchar(50) DEFAULT NULL COMMENT 'serialNumber',
  `id_stav` smallint(5) UNSIGNED DEFAULT NULL,
  `id_platba` smallint(5) UNSIGNED DEFAULT NULL,
  `id_doruceni` smallint(5) UNSIGNED DEFAULT NULL,
  `id_zak` int(10) UNSIGNED DEFAULT NULL,
  `obj_pozn` text DEFAULT NULL COMMENT 'note',
  `restia_imported_at` datetime(3) DEFAULT current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `obj_adresa`
--

CREATE TABLE `obj_adresa` (
  `id_obj` bigint(20) UNSIGNED NOT NULL,
  `ulice` varchar(150) DEFAULT NULL COMMENT 'destination.street / destination.address',
  `cislo_domovni` varchar(30) DEFAULT NULL COMMENT 'destination.houseNumber',
  `mesto` varchar(100) DEFAULT NULL COMMENT 'destination.city',
  `psc` varchar(20) DEFAULT NULL COMMENT 'destination.zip / destination.postalCode',
  `stat` varchar(100) DEFAULT NULL COMMENT 'destination.country',
  `lat` decimal(10,7) DEFAULT NULL COMMENT 'destination.lat',
  `lng` decimal(10,7) DEFAULT NULL COMMENT 'destination.lng',
  `vzdalenost_m` int(10) UNSIGNED DEFAULT NULL COMMENT 'destination.distance',
  `cas_jizdy_s` int(10) UNSIGNED DEFAULT NULL COMMENT 'destination.time',
  `vytvoreno` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `zmeneno` datetime(3) NOT NULL DEFAULT current_timestamp(3) ON UPDATE current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `obj_casy`
--

CREATE TABLE `obj_casy` (
  `id_obj` bigint(20) UNSIGNED NOT NULL,
  `report` date NOT NULL COMMENT 'PD 06:00-06:00 podle createdAt',
  `cas_vytvor` datetime DEFAULT NULL COMMENT 'createdAt',
  `cas_expirace` datetime DEFAULT NULL COMMENT 'expiresAt',
  `cas_slib` datetime DEFAULT NULL COMMENT 'promisedAt',
  `cas_pripr_do` datetime DEFAULT NULL COMMENT 'prepareAt',
  `cas_pripr_v` datetime DEFAULT NULL COMMENT 'preparedAt',
  `cas_pripravy` int(11) DEFAULT NULL COMMENT 'cookingTimeMinutes',
  `cas_dokonc` datetime DEFAULT NULL COMMENT 'finishedAt',
  `cas_doruc` datetime DEFAULT NULL COMMENT 'deliveredAt',
  `cas_status_zmena` datetime DEFAULT NULL COMMENT 'statusUpdatedAt',
  `cas_uzavreni` datetime DEFAULT NULL COMMENT 'closedAt',
  `cas_import_restia` datetime DEFAULT NULL COMMENT 'importedAt',
  `cas_import_pos` datetime DEFAULT NULL COMMENT 'posImportedAt',
  `cas_vyzv` datetime DEFAULT NULL COMMENT 'pickupAt',
  `cas_disp` datetime DEFAULT NULL COMMENT 'deliveryAt'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `obj_ceny`
--

CREATE TABLE `obj_ceny` (
  `id_obj` bigint(20) UNSIGNED NOT NULL,
  `cena_celk` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'vypočteno z cenových polí',
  `cena_pol` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'itemsPrice',
  `cena_balne` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'packingPrice',
  `cena_dopr` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'deliveryPrice',
  `dyska` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'tipPrice',
  `cena_do_min` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'surchargeToMin',
  `cena_servis` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'serviceFeePrice',
  `sleva` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'discountPrice',
  `zaokrouhleni` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'roundingPrice',
  `mena` char(3) NOT NULL DEFAULT 'CZK' COMMENT 'CZK'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `obj_import`
--

CREATE TABLE `obj_import` (
  `id_import` bigint(20) UNSIGNED NOT NULL,
  `typ_importu` varchar(30) NOT NULL COMMENT 'historie|den|aktualni_den|test',
  `id_pob` int(10) UNSIGNED DEFAULT NULL,
  `datum_od` datetime DEFAULT NULL,
  `datum_do` datetime DEFAULT NULL,
  `stav` enum('ceka','bezi','ok','chyba') NOT NULL DEFAULT 'ceka',
  `pocet_obj` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `pocet_restia` int(10) UNSIGNED DEFAULT NULL COMMENT 'počet objednávek podle Restie',
  `pocet_is` int(10) UNSIGNED DEFAULT NULL COMMENT 'počet objednávek v IS pro stejný interval',
  `rozdil` int(11) NOT NULL DEFAULT 0 COMMENT 'pocet_is - pocet_restia',
  `pocet_novych` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `pocet_zmenenych` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `pocet_chyb` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `poznamka` text DEFAULT NULL,
  `spusteno` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `dokonceno` datetime(3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `obj_kuryr`
--

CREATE TABLE `obj_kuryr` (
  `id_obj_kuryr` bigint(20) UNSIGNED NOT NULL,
  `id_obj` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(50) DEFAULT NULL COMMENT 'deliveryType',
  `externi_id` varchar(100) DEFAULT NULL COMMENT 'courierData.id',
  `poradi` int(10) UNSIGNED DEFAULT NULL COMMENT 'courierOrder',
  `jmeno` varchar(150) DEFAULT NULL COMMENT 'courierData.name',
  `telefon` varchar(50) DEFAULT NULL COMMENT 'courierData.phone',
  `vytvoreno` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `zmeneno` datetime(3) NOT NULL DEFAULT current_timestamp(3) ON UPDATE current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `obj_polozka_kds_tag`
--

CREATE TABLE `obj_polozka_kds_tag` (
  `id_obj_polozka_kds_tag` bigint(20) UNSIGNED NOT NULL,
  `id_obj_polozka` bigint(20) UNSIGNED NOT NULL,
  `tag` varchar(100) NOT NULL COMMENT 'KDSTags / kdsTags'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `obj_polozka_mod`
--

CREATE TABLE `obj_polozka_mod` (
  `id_obj_polozka_mod` bigint(20) UNSIGNED NOT NULL,
  `id_obj_polozka` bigint(20) UNSIGNED NOT NULL,
  `restia_mod_id` varchar(64) DEFAULT NULL COMMENT 'modifiers.id / mods.id / options.id / extras.id',
  `typ` varchar(50) DEFAULT NULL COMMENT 'type',
  `pos_id` varchar(50) DEFAULT NULL COMMENT 'posId',
  `nazev` varchar(255) NOT NULL COMMENT 'label / name',
  `mnozstvi` decimal(10,3) NOT NULL DEFAULT 1.000 COMMENT 'count / qty',
  `cena_ks` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'price',
  `cena_celk` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'totalPrice',
  `zadano` datetime(3) NOT NULL DEFAULT current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `obj_polozky`
--

CREATE TABLE `obj_polozky` (
  `id_obj_polozka` bigint(20) UNSIGNED NOT NULL,
  `id_obj` bigint(20) UNSIGNED NOT NULL,
  `id_res_polozka` bigint(20) UNSIGNED DEFAULT NULL,
  `res_item` text NOT NULL COMMENT 'items.posId / items.id',
  `poznamka` text DEFAULT NULL COMMENT 'items.note',
  `poradi` smallint(5) UNSIGNED DEFAULT NULL COMMENT 'pořadí v items',
  `mnozstvi` int(11) NOT NULL DEFAULT 1 COMMENT 'items.count',
  `cena_ks` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'items.price',
  `cena_celk` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'items.totalPrice',
  `je_extra` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'items.isExtra',
  `zadano` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `obj_restia_detail`
--

CREATE TABLE `obj_restia_detail` (
  `id_obj` bigint(20) UNSIGNED NOT NULL,
  `restia_raw_json` longtext DEFAULT NULL COMMENT 'celý JSON objednávky z Restie',
  `restia_payload_hash` char(64) DEFAULT NULL COMMENT 'SHA-256 hash JSON payloadu',
  `restia_last_seen_at` datetime(3) DEFAULT NULL COMMENT 'kdy byla objednávka naposledy potvrzena v Restii'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `obj_sluzba`
--

CREATE TABLE `obj_sluzba` (
  `id_obj_sluzba` bigint(20) UNSIGNED NOT NULL,
  `id_obj` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(50) DEFAULT NULL COMMENT 'servicesData.provider',
  `externi_id` varchar(100) DEFAULT NULL COMMENT 'servicesData.externalId / servicesData.id',
  `stav` varchar(50) DEFAULT NULL COMMENT 'servicesData.status',
  `vytvoreno` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `zmeneno` datetime(3) NOT NULL DEFAULT current_timestamp(3) ON UPDATE current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `online_restia`
--

CREATE TABLE `online_restia` (
  `id_akce` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `start` datetime NOT NULL,
  `konec` datetime DEFAULT NULL,
  `zapisy` int(11) DEFAULT 0,
  `aktualizace` int(11) DEFAULT 0,
  `ignore` int(11) NOT NULL DEFAULT 0,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `online_restia_pobocky`
--

CREATE TABLE `online_restia_pobocky` (
  `id_akce` int(11) NOT NULL,
  `id_pob` int(10) UNSIGNED NOT NULL,
  `od` datetime NOT NULL,
  `do` datetime NOT NULL,
  `zapisy` int(11) NOT NULL DEFAULT 0,
  `aktualizace` int(11) NOT NULL DEFAULT 0,
  `ignore` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `pobocka`
--

CREATE TABLE `pobocka` (
  `id_pob` int(10) UNSIGNED NOT NULL,
  `kod` varchar(20) NOT NULL,
  `nazev` varchar(100) NOT NULL,
  `ulice` varchar(150) NOT NULL,
  `mesto` varchar(100) NOT NULL,
  `oblast` varchar(50) NOT NULL DEFAULT '',
  `psc` int(10) UNSIGNED NOT NULL,
  `zadal` int(10) UNSIGNED NOT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp(),
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `restia_activePosId` char(36) DEFAULT NULL,
  `prvni_obj` date DEFAULT NULL,
  `start_smeny` date DEFAULT NULL,
  `pob_color` varchar(7) NOT NULL,
  `end_po` time NOT NULL DEFAULT '01:00:00',
  `end_ut` time NOT NULL DEFAULT '01:00:00',
  `end_st` time NOT NULL DEFAULT '01:00:00',
  `end_ct` time NOT NULL DEFAULT '01:00:00',
  `end_pa` time NOT NULL DEFAULT '01:00:00',
  `end_so` time NOT NULL DEFAULT '01:00:00',
  `end_ne` time NOT NULL DEFAULT '01:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `pob_email`
--

CREATE TABLE `pob_email` (
  `id_pob` int(10) UNSIGNED NOT NULL,
  `email` varchar(150) NOT NULL,
  `zadal` int(10) UNSIGNED NOT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp(),
  `aktivni` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `pob_manager`
--

CREATE TABLE `pob_manager` (
  `id_pob` int(10) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `zadal` int(10) UNSIGNED NOT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp(),
  `aktivni` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `pob_povoleni`
--

CREATE TABLE `pob_povoleni` (
  `id_user` int(10) UNSIGNED NOT NULL,
  `id_pob` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `pob_povoleni_hist`
--

CREATE TABLE `pob_povoleni_hist` (
  `id_povoleni` int(10) UNSIGNED NOT NULL,
  `id_pob` int(10) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `zadal` int(10) UNSIGNED DEFAULT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp(),
  `aktivni` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `pob_tel`
--

CREATE TABLE `pob_tel` (
  `id_pob` int(10) UNSIGNED NOT NULL,
  `telefon` varchar(30) NOT NULL,
  `zadal` int(10) UNSIGNED NOT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp(),
  `aktivni` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `push_audit`
--

CREATE TABLE `push_audit` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED DEFAULT NULL,
  `id_zarizeni` bigint(20) UNSIGNED DEFAULT NULL,
  `typ` varchar(40) NOT NULL,
  `stav` enum('ok','fail') NOT NULL,
  `http_status` smallint(5) UNSIGNED DEFAULT NULL,
  `chyba` text DEFAULT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `push_login_2fa`
--

CREATE TABLE `push_login_2fa` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `token` char(64) NOT NULL,
  `stav` enum('ceka','ok','ne','exp') NOT NULL DEFAULT 'ceka',
  `ip` varchar(45) NOT NULL,
  `prohlizec` varchar(255) DEFAULT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp(),
  `vyprsi` datetime NOT NULL,
  `rozhodnuto` datetime DEFAULT NULL,
  `id_zarizeni` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `push_parovani`
--

CREATE TABLE `push_parovani` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `token_hash` binary(32) NOT NULL,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp(),
  `expirace` datetime NOT NULL,
  `pouzito_kdy` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `push_zarizeni`
--

CREATE TABLE `push_zarizeni` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `endpoint` text NOT NULL,
  `endpoint_hash` binary(32) NOT NULL,
  `klic_public` varchar(255) NOT NULL,
  `klic_auth` varchar(255) NOT NULL,
  `nazev` varchar(80) DEFAULT NULL,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp(),
  `naposledy` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `reporty`
--

CREATE TABLE `reporty` (
  `id_reportu` bigint(20) UNSIGNED NOT NULL,
  `datum_reportu` date NOT NULL,
  `id_pob` int(10) UNSIGNED NOT NULL,
  `oteviral` int(10) UNSIGNED DEFAULT NULL,
  `zaviral` int(10) UNSIGNED DEFAULT NULL,
  `oteviral_text` varchar(120) DEFAULT NULL,
  `zaviral_text` varchar(120) DEFAULT NULL,
  `poznamka` text DEFAULT NULL,
  `zdroj` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=Google historie, 2=system formular',
  `stav` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=ulozeno, 2=uzamceno, 9=storno',
  `zadal` int(10) UNSIGNED DEFAULT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp(),
  `editovano` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `platny` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `reporty_is`
--

CREATE TABLE `reporty_is` (
  `id_reportu` bigint(20) UNSIGNED NOT NULL,
  `datum_reportu` date NOT NULL,
  `id_pob` int(10) UNSIGNED NOT NULL,
  `oteviral` int(10) UNSIGNED DEFAULT NULL,
  `zaviral` int(10) UNSIGNED DEFAULT NULL,
  `oteviral_text` varchar(120) DEFAULT NULL,
  `zaviral_text` varchar(120) DEFAULT NULL,
  `poznamka` text DEFAULT NULL,
  `zdroj` tinyint(3) UNSIGNED NOT NULL DEFAULT 2 COMMENT '1=Google historie, 2=system formular',
  `stav` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=ulozeno, 2=uzamceno, 9=storno',
  `zadal` int(10) UNSIGNED DEFAULT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp(),
  `editovano` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `platny` tinyint(1) NOT NULL DEFAULT 1,
  `platny_unique` tinyint(4) GENERATED ALWAYS AS (case when `platny` = 1 then 1 else NULL end) VIRTUAL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `reporty_is_osoby`
--

CREATE TABLE `reporty_is_osoby` (
  `id_reporty_osoby` bigint(20) UNSIGNED NOT NULL,
  `id_reportu` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED DEFAULT NULL,
  `jmeno` varchar(50) DEFAULT NULL,
  `prijmeni` varchar(50) DEFAULT NULL,
  `slot` tinyint(3) UNSIGNED DEFAULT NULL,
  `smena_od` time DEFAULT NULL,
  `smena_do` time DEFAULT NULL,
  `pauza` decimal(5,2) NOT NULL DEFAULT 0.00,
  `odpracovano` decimal(6,2) NOT NULL DEFAULT 0.00,
  `rozvozu_restia` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `rozvozu_manual` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `sazba` decimal(6,2) NOT NULL DEFAULT 0.00,
  `rozvozu_celkem` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `vlastni_vuz` tinyint(1) NOT NULL DEFAULT 0,
  `vyplatit_phm` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `reporty_is_pokladna`
--

CREATE TABLE `reporty_is_pokladna` (
  `id_reportu` bigint(20) UNSIGNED NOT NULL,
  `hotovost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `terminal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stravenky` decimal(10,2) NOT NULL DEFAULT 0.00,
  `rozdil` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vydaje_benzin` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vydaje_auta` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vydaje_suroviny` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vydaje_ostatni` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vydaje_phm_soukrome` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vydaje_doklady_ks` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `reporty_is_restia`
--

CREATE TABLE `reporty_is_restia` (
  `id_reportu` bigint(20) UNSIGNED NOT NULL,
  `trzba` decimal(10,2) NOT NULL DEFAULT 0.00,
  `wolt` decimal(10,2) NOT NULL DEFAULT 0.00,
  `wolt_obj` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `bolt` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bolt_obj` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `damejidlo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `damejidlo_obj` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `web` decimal(10,2) NOT NULL DEFAULT 0.00,
  `web_obj` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `wolt_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
  `wolt_cash_obj` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `dj_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
  `dj_cash_obj` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `col_pomer` decimal(10,6) DEFAULT NULL,
  `zrusene_obj_ks` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `zrusene_obj_kc` decimal(10,2) NOT NULL DEFAULT 0.00,
  `zpozdene_rozvozy_5_min` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `make_time_prumer_sec` int(10) UNSIGNED DEFAULT NULL,
  `objednavky_nezrusene_ks` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `nase_rozvozy_ks` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `woltdrive_ks` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `woltdrive_pozde_5_min` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `woltdrive_pozde_nase_vina` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `nase_rozvozy_pozde_pomer` decimal(10,6) DEFAULT NULL,
  `woltdrive_zpozdene_ks` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `doruceno_vcas_pomer` decimal(10,6) DEFAULT NULL,
  `woltdrive_zpozdene_pomer` decimal(10,6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `reporty_osoby`
--

CREATE TABLE `reporty_osoby` (
  `id_reporty_osoby` bigint(20) UNSIGNED NOT NULL,
  `id_reportu` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED DEFAULT NULL,
  `jmeno` varchar(50) DEFAULT NULL,
  `prijmeni` varchar(50) DEFAULT NULL,
  `slot` tinyint(3) UNSIGNED DEFAULT NULL,
  `smena_od` time DEFAULT NULL,
  `smena_do` time DEFAULT NULL,
  `pauza` decimal(5,2) NOT NULL DEFAULT 0.00,
  `odpracovano` decimal(6,2) NOT NULL DEFAULT 0.00,
  `rozvozu_restia` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `rozvozu_manual` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `rozvozu_celkem` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `vlastni_vuz` tinyint(1) NOT NULL DEFAULT 0,
  `vyplatit_phm` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `reporty_pokladna`
--

CREATE TABLE `reporty_pokladna` (
  `id_reportu` bigint(20) UNSIGNED NOT NULL,
  `hotovost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `terminal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stravenky` decimal(10,2) NOT NULL DEFAULT 0.00,
  `rozdil` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vydaje_benzin` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vydaje_auta` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vydaje_suroviny` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vydaje_ostatni` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vydaje_phm_soukrome` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vydaje_doklady_ks` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `reporty_restia`
--

CREATE TABLE `reporty_restia` (
  `id_reportu` bigint(20) UNSIGNED NOT NULL,
  `trzba` decimal(10,2) NOT NULL DEFAULT 0.00,
  `wolt` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bolt` decimal(10,2) NOT NULL DEFAULT 0.00,
  `damejidlo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `web` decimal(10,2) NOT NULL DEFAULT 0.00,
  `wolt_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
  `dj_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
  `col_pomer` decimal(10,6) DEFAULT NULL,
  `zrusene_obj_ks` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `zrusene_obj_kc` decimal(10,2) NOT NULL DEFAULT 0.00,
  `zpozdene_rozvozy_5_min` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `make_time_prumer_sec` int(10) UNSIGNED DEFAULT NULL,
  `objednavky_nezrusene_ks` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `nase_rozvozy_ks` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `woltdrive_ks` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `woltdrive_pozde_5_min` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `woltdrive_pozde_nase_vina` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `nase_rozvozy_pozde_pomer` decimal(10,6) DEFAULT NULL,
  `woltdrive_zpozdene_ks` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `doruceno_vcas_pomer` decimal(10,6) DEFAULT NULL,
  `woltdrive_zpozdene_pomer` decimal(10,6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `restia_token`
--

CREATE TABLE `restia_token` (
  `id_restia_token` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `access_token` text NOT NULL,
  `expires_at` datetime(3) NOT NULL,
  `vytvoreno` datetime(3) NOT NULL DEFAULT current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `res_alergen`
--

CREATE TABLE `res_alergen` (
  `id_res_polozka` bigint(20) UNSIGNED NOT NULL,
  `alergen` varchar(10) NOT NULL,
  `vytvoreno` datetime(3) NOT NULL DEFAULT current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `res_cena`
--

CREATE TABLE `res_cena` (
  `id_res_cena` bigint(20) UNSIGNED NOT NULL,
  `id_res_polozka` bigint(20) UNSIGNED NOT NULL,
  `kanal` varchar(20) NOT NULL,
  `size_id` varchar(64) NOT NULL,
  `size_popis` varchar(64) DEFAULT NULL,
  `pos_code` varchar(32) DEFAULT NULL,
  `cena_hl` int(10) UNSIGNED NOT NULL,
  `balne_hl` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `vat` decimal(6,4) DEFAULT NULL,
  `vat_v_restauraci` decimal(6,4) DEFAULT NULL,
  `mena` char(3) NOT NULL DEFAULT 'CZK',
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `vytvoreno` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `zmeneno` datetime(3) NOT NULL DEFAULT current_timestamp(3) ON UPDATE current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `res_kategorie`
--

CREATE TABLE `res_kategorie` (
  `id_res_kategorie` bigint(20) UNSIGNED NOT NULL,
  `id_pob` int(10) UNSIGNED NOT NULL,
  `id_restia_menu` char(36) NOT NULL,
  `id_restia_kategorie` varchar(64) NOT NULL,
  `nazev_kategorie` varchar(255) NOT NULL,
  `poradi_kategorie` smallint(5) UNSIGNED DEFAULT NULL,
  `skryta` tinyint(1) NOT NULL DEFAULT 0,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `vytvoreno` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `zmeneno` datetime(3) NOT NULL DEFAULT current_timestamp(3) ON UPDATE current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `res_polozky`
--

CREATE TABLE `res_polozky` (
  `id_res_polozka` bigint(20) UNSIGNED NOT NULL,
  `id_res_kategorie` bigint(20) UNSIGNED NOT NULL,
  `id_pob` int(10) UNSIGNED NOT NULL,
  `restia_polozka_id` varchar(64) NOT NULL,
  `nazev` varchar(255) NOT NULL,
  `nazev_en` varchar(255) DEFAULT NULL,
  `popis` text DEFAULT NULL,
  `popis_en` text DEFAULT NULL,
  `pos_code` varchar(32) DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `skryta` tinyint(1) NOT NULL DEFAULT 0,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `vytvoreno` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `zmeneno` datetime(3) NOT NULL DEFAULT current_timestamp(3) ON UPDATE current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `set_system`
--

CREATE TABLE `set_system` (
  `id_set` int(11) NOT NULL,
  `restia_online` tinyint(1) NOT NULL DEFAULT 1,
  `on_2fa` tinyint(1) NOT NULL DEFAULT 0,
  `system_logout` int(11) NOT NULL,
  `pauza_obdobi` int(11) NOT NULL COMMENT 'čekání při volbě  období v ms',
  `report_save` int(11) NOT NULL,
  `zamek` tinyint(1) NOT NULL DEFAULT 0,
  `restia_notifikace` tinyint(1) NOT NULL DEFAULT 1,
  `log_akce` tinyint(1) NOT NULL,
  `log_1` tinyint(4) NOT NULL DEFAULT 0,
  `log_2` tinyint(4) NOT NULL DEFAULT 0,
  `log_3` tinyint(4) NOT NULL DEFAULT 0,
  `log_4` tinyint(4) NOT NULL DEFAULT 0,
  `notif_chyby` tinyint(1) NOT NULL DEFAULT 0,
  `notif_bad_login` tinyint(1) NOT NULL DEFAULT 0,
  `rozvoz_sazba` int(11) NOT NULL,
  `uprava_souboru` datetime DEFAULT NULL,
  `verze` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `smeny_aktualizace`
--

CREATE TABLE `smeny_aktualizace` (
  `id_smeny_aktualizace` bigint(20) UNSIGNED NOT NULL,
  `start_day` date NOT NULL COMMENT 'Pondělí týdne, který se právě zpracovává / byl zpracován',
  `id_pob` int(10) UNSIGNED NOT NULL COMMENT 'Pobočka',
  `stav` tinyint(4) NOT NULL,
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `smeny_report`
--

CREATE TABLE `smeny_report` (
  `id_smeny_report` bigint(20) UNSIGNED NOT NULL,
  `datum` date NOT NULL,
  `id_pob` smallint(5) UNSIGNED NOT NULL,
  `jmeno` varchar(50) NOT NULL,
  `prijmeni` varchar(50) NOT NULL,
  `id_slot` tinyint(3) UNSIGNED NOT NULL COMMENT '1=instor, 2=kuryr',
  `cas_od` time DEFAULT NULL,
  `cas_do` time DEFAULT NULL,
  `odpracovano` decimal(5,2) DEFAULT NULL,
  `pauza` time DEFAULT NULL,
  `chyba` int(11) DEFAULT NULL,
  `zdroj` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Zdroj dat',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `user`
--

CREATE TABLE `user` (
  `id_user` int(10) UNSIGNED NOT NULL,
  `jmeno` varchar(60) NOT NULL,
  `prijmeni` varchar(80) NOT NULL,
  `alias` varchar(120) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `telefon` varchar(30) DEFAULT NULL,
  `aktivni` tinyint(1) NOT NULL,
  `in_system` tinyint(1) NOT NULL DEFAULT 0,
  `schvalen` tinyint(1) NOT NULL,
  `admin` tinyint(1) NOT NULL DEFAULT 0,
  `vytvoren_smeny` datetime DEFAULT NULL,
  `visit_smeny` datetime DEFAULT NULL,
  `last_smeny` datetime DEFAULT NULL,
  `id_role` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `user_akce`
--

CREATE TABLE `user_akce` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cas` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `id_user` int(11) NOT NULL,
  `id_login` int(11) DEFAULT NULL,
  `id_karta` int(11) DEFAULT NULL,
  `id_akce` int(11) NOT NULL,
  `hodnota_old` text DEFAULT NULL,
  `hodnota_new` text DEFAULT NULL,
  `detail_json` longtext DEFAULT NULL,
  `vysledek` tinyint(1) NOT NULL DEFAULT 1,
  `err_msg` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `user_akce_db`
--

CREATE TABLE `user_akce_db` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cas_start` datetime(3) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_akce` int(11) DEFAULT NULL,
  `request_uri` varchar(255) DEFAULT NULL,
  `metoda` varchar(10) DEFAULT NULL,
  `request_ms` decimal(12,3) NOT NULL DEFAULT 0.000,
  `sql_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sql_total_ms` decimal(12,3) NOT NULL DEFAULT 0.000,
  `sql_max_ms` decimal(12,3) NOT NULL DEFAULT 0.000,
  `rows_returned` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `rows_affected` bigint(20) NOT NULL DEFAULT 0,
  `bytes_received` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `bytes_sent` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `status` varchar(40) NOT NULL DEFAULT 'ok',
  `err_msg` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `user_akce_db_detail`
--

CREATE TABLE `user_akce_db_detail` (
  `id_detail` bigint(20) UNSIGNED NOT NULL,
  `id_user_akce_db` bigint(20) UNSIGNED NOT NULL,
  `typ` enum('card','dashboard','ajax','db') NOT NULL,
  `nazev` varchar(120) DEFAULT NULL,
  `id_karta` int(11) DEFAULT NULL,
  `soubor` varchar(120) DEFAULT NULL,
  `mode` varchar(20) DEFAULT NULL,
  `usek` varchar(40) DEFAULT NULL,
  `ms` decimal(12,3) DEFAULT NULL,
  `total_ms` decimal(12,3) DEFAULT NULL,
  `step_ms` decimal(12,3) DEFAULT NULL,
  `detail_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detail_json`)),
  `created_at` datetime(3) NOT NULL DEFAULT current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `user_akce_on_off`
--

CREATE TABLE `user_akce_on_off` (
  `id_user` int(10) NOT NULL,
  `log_on` int(11) NOT NULL,
  `log_off` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `user_aktualizace`
--

CREATE TABLE `user_aktualizace` (
  `id_user_aktualizace` int(10) UNSIGNED NOT NULL,
  `spusteno` datetime NOT NULL,
  `dokonceno` datetime DEFAULT NULL,
  `stav` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=bezi, 1=skonceno',
  `stazeno` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `ulozeno_aktualizovano` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `role_pridano` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `role_odebrano` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sloty_pridano` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sloty_odebrano` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `chyby` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `chyba_text` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `user_bad_login`
--

CREATE TABLE `user_bad_login` (
  `id_bad_login` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(150) NOT NULL,
  `heslo` varchar(255) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `screen_w` smallint(5) UNSIGNED DEFAULT NULL,
  `screen_h` smallint(5) UNSIGNED DEFAULT NULL,
  `is_touch` tinyint(1) DEFAULT NULL,
  `kdy` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `user_card_set`
--

CREATE TABLE `user_card_set` (
  `id_user` int(10) UNSIGNED NOT NULL,
  `id_karta` int(10) UNSIGNED NOT NULL,
  `color` varchar(7) DEFAULT NULL,
  `ikon` int(10) UNSIGNED DEFAULT NULL,
  `poradi` int(10) UNSIGNED DEFAULT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp(),
  `upraveno` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `user_login`
--

CREATE TABLE `user_login` (
  `id_login` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `kdy` datetime NOT NULL DEFAULT current_timestamp(),
  `akce` tinyint(1) NOT NULL,
  `duvod` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `ip` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `user_pin`
--

CREATE TABLE `user_pin` (
  `id_user` int(10) UNSIGNED NOT NULL,
  `pin_hash` varchar(255) NOT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp(),
  `aktivni` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `user_pobocka`
--

CREATE TABLE `user_pobocka` (
  `id_user` int(10) UNSIGNED NOT NULL,
  `id_pob` int(10) UNSIGNED NOT NULL,
  `main` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `user_pobocka_set`
--

CREATE TABLE `user_pobocka_set` (
  `id_user` int(10) UNSIGNED NOT NULL,
  `id_pob` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `user_role`
--

CREATE TABLE `user_role` (
  `id_user` int(10) UNSIGNED NOT NULL,
  `id_role` int(10) UNSIGNED NOT NULL,
  `sub_role` int(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `user_set`
--

CREATE TABLE `user_set` (
  `id_user` int(10) UNSIGNED NOT NULL,
  `pocet_sl` tinyint(3) UNSIGNED NOT NULL DEFAULT 4,
  `nano_kde` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=řádek, 1=grid',
  `poradi_mini` text DEFAULT NULL,
  `poradi_nano` text DEFAULT NULL,
  `prodleva` int(11) NOT NULL DEFAULT 3000 COMMENT 'prodleva při volbě období',
  `pismo` tinyint(1) DEFAULT 2 COMMENT '1=male,2=normal,3=vetsi (NULL=default 2)',
  `dark` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=light,1=dark',
  `obdobi_od` datetime DEFAULT NULL,
  `obdobi_do` datetime DEFAULT NULL,
  `obdobi_mode` varchar(20) NOT NULL DEFAULT 'manual',
  `logout_limit` int(11) DEFAULT NULL,
  `kpi` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `user_slot`
--

CREATE TABLE `user_slot` (
  `id_user` int(10) UNSIGNED NOT NULL,
  `id_slot` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `user_spy`
--

CREATE TABLE `user_spy` (
  `id_login` bigint(20) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `screen_w` smallint(5) UNSIGNED DEFAULT NULL,
  `screen_h` smallint(5) UNSIGNED DEFAULT NULL,
  `is_touch` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `zakaznik`
--

CREATE TABLE `zakaznik` (
  `id_zak` int(10) UNSIGNED NOT NULL,
  `jmeno` varchar(100) NOT NULL COMMENT 'customerName',
  `prijmeni` varchar(100) NOT NULL COMMENT 'customerName',
  `telefon` varchar(30) DEFAULT NULL COMMENT 'customerPhone',
  `telefon_norm` varchar(30) DEFAULT NULL COMMENT 'normalizovaný telefon pro rychlé párování zákazníka',
  `email` varchar(150) DEFAULT NULL COMMENT 'customerEmail',
  `ulice` varchar(150) DEFAULT NULL,
  `mesto` varchar(100) DEFAULT NULL,
  `zak_menu` tinyint(1) NOT NULL DEFAULT 0,
  `zak_news` tinyint(1) NOT NULL DEFAULT 0,
  `posledni_obj` datetime DEFAULT NULL,
  `pocet_obj` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'počet objednávek',
  `poznamka` text DEFAULT NULL COMMENT 'customerNote',
  `blokovany` tinyint(1) NOT NULL DEFAULT 0,
  `id_pob` int(10) UNSIGNED NOT NULL,
  `zadano` datetime NOT NULL DEFAULT current_timestamp(),
  `aktivni` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

--
-- Indexy pro exportované tabulky
--

--
-- Indexy pro tabulku `admin_info`
--
ALTER TABLE `admin_info`
  ADD PRIMARY KEY (`id_admin_info`),
  ADD KEY `idx_admin_info_typ_vytvoreno` (`typ`,`vytvoreno`),
  ADD KEY `idx_admin_info_odeslal` (`id_odeslal`);

--
-- Indexy pro tabulku `admin_info_user`
--
ALTER TABLE `admin_info_user`
  ADD PRIMARY KEY (`id_admin_info_user`),
  ADD UNIQUE KEY `uq_admin_info_user_token` (`token`),
  ADD UNIQUE KEY `uq_admin_info_user` (`id_admin_info`,`id_user`),
  ADD KEY `idx_admin_info_user_user` (`id_user`,`odeslano`);

--
-- Indexy pro tabulku `api_restia`
--
ALTER TABLE `api_restia`
  ADD PRIMARY KEY (`id_api_restia`),
  ADD KEY `ix_kdy_start` (`kdy_start`),
  ADD KEY `ix_id_user_kdy` (`id_user`,`kdy_start`),
  ADD KEY `ix_id_login_kdy` (`id_login`,`kdy_start`),
  ADD KEY `ix_active_pos_kdy` (`active_pos_id`,`kdy_start`),
  ADD KEY `ix_endpoint_kdy` (`endpoint`,`kdy_start`),
  ADD KEY `ix_status_kdy` (`http_status`,`kdy_start`);

--
-- Indexy pro tabulku `api_smeny`
--
ALTER TABLE `api_smeny`
  ADD PRIMARY KEY (`id_api_smeny`),
  ADD KEY `idx_api_smeny_user_time` (`id_user`,`kdy_start`),
  ADD KEY `idx_api_smeny_login_time` (`id_login`,`kdy_start`),
  ADD KEY `idx_api_smeny_time` (`kdy_start`);

--
-- Indexy pro tabulku `card_icons`
--
ALTER TABLE `card_icons`
  ADD PRIMARY KEY (`id_ikon`),
  ADD UNIQUE KEY `uq_card_ikons_soubor` (`soubor`),
  ADD KEY `ix_card_ikons_aktivni_poradi` (`aktivni`,`poradi`,`id_ikon`);

--
-- Indexy pro tabulku `cis_akce`
--
ALTER TABLE `cis_akce`
  ADD PRIMARY KEY (`id_akce`);

--
-- Indexy pro tabulku `cis_chyby`
--
ALTER TABLE `cis_chyby`
  ADD PRIMARY KEY (`id_cis_chyba`),
  ADD UNIQUE KEY `uq_cis_chyby_kod` (`kod`),
  ADD KEY `ix_cis_chyby_uroven` (`uroven`),
  ADD KEY `ix_cis_chyby_oblast` (`oblast`),
  ADD KEY `ix_cis_chyby_aktivni` (`aktivni`);

--
-- Indexy pro tabulku `cis_doruceni`
--
ALTER TABLE `cis_doruceni`
  ADD PRIMARY KEY (`id_doruceni`),
  ADD UNIQUE KEY `uq_obj_doruceni_nazev` (`nazev`);

--
-- Indexy pro tabulku `cis_mzda_typ`
--
ALTER TABLE `cis_mzda_typ`
  ADD PRIMARY KEY (`id_mzda_typ`),
  ADD UNIQUE KEY `uq_cis_mzda_typ_kod` (`kod`);

--
-- Indexy pro tabulku `cis_obj_platby`
--
ALTER TABLE `cis_obj_platby`
  ADD PRIMARY KEY (`id_platba`),
  ADD UNIQUE KEY `uq_obj_platba_nazev` (`nazev`);

--
-- Indexy pro tabulku `cis_obj_platforma`
--
ALTER TABLE `cis_obj_platforma`
  ADD PRIMARY KEY (`id_platforma`),
  ADD UNIQUE KEY `uq_platforma_kod` (`kod`);

--
-- Indexy pro tabulku `cis_obj_stav`
--
ALTER TABLE `cis_obj_stav`
  ADD PRIMARY KEY (`id_stav`),
  ADD UNIQUE KEY `uq_obj_stav_nazev` (`nazev`);

--
-- Indexy pro tabulku `cis_polozka_kat`
--
ALTER TABLE `cis_polozka_kat`
  ADD PRIMARY KEY (`id_kat`),
  ADD UNIQUE KEY `uq_kategorie_nazev` (`nazev`);

--
-- Indexy pro tabulku `cis_polozky`
--
ALTER TABLE `cis_polozky`
  ADD PRIMARY KEY (`id_polozka`),
  ADD UNIQUE KEY `uq_polozka_posid` (`pos_id`),
  ADD KEY `ix_polozka_kat` (`id_polozka_kat`);

--
-- Indexy pro tabulku `cis_prac_zarazeni`
--
ALTER TABLE `cis_prac_zarazeni`
  ADD PRIMARY KEY (`id_prac_zar`);

--
-- Indexy pro tabulku `cis_role`
--
ALTER TABLE `cis_role`
  ADD PRIMARY KEY (`id_role`),
  ADD UNIQUE KEY `uq_cis_role_role` (`role`),
  ADD UNIQUE KEY `uq_cis_role_id_role_smeny` (`id_role_smeny`);

--
-- Indexy pro tabulku `cis_slot`
--
ALTER TABLE `cis_slot`
  ADD PRIMARY KEY (`id_slot`),
  ADD UNIQUE KEY `uq_cis_slot_slot` (`slot`);

--
-- Indexy pro tabulku `cis_sloupce`
--
ALTER TABLE `cis_sloupce`
  ADD PRIMARY KEY (`id_nazev_sloupce`);

--
-- Indexy pro tabulku `dr_pracovni`
--
ALTER TABLE `dr_pracovni`
  ADD PRIMARY KEY (`id_dr`),
  ADD UNIQUE KEY `uq_dr_pracovni_pob_datum` (`id_pob`,`datum_reportu`),
  ADD KEY `idx_dr_pracovni_datum` (`datum_reportu`),
  ADD KEY `idx_dr_pracovni_created_by` (`created_by`),
  ADD KEY `idx_dr_pracovni_updated_by` (`updated_by`);

--
-- Indexy pro tabulku `dr_pracovni_osoby`
--
ALTER TABLE `dr_pracovni_osoby`
  ADD PRIMARY KEY (`id_dr_osoby`),
  ADD UNIQUE KEY `uq_dr_pracovni_osoby_user_slot` (`id_dr`,`id_slot`,`id_user`),
  ADD KEY `idx_dr_pracovni_osoby_slot` (`id_dr`,`id_slot`),
  ADD KEY `idx_dr_pracovni_osoby_user` (`id_user`);

--
-- Indexy pro tabulku `helpdesk`
--
ALTER TABLE `helpdesk`
  ADD PRIMARY KEY (`id_helpdesk`),
  ADD KEY `ix_helpdesk_stav` (`stav`,`vytvoreno`),
  ADD KEY `ix_helpdesk_user_zalozil` (`id_user_zalozil`,`vytvoreno`),
  ADD KEY `ix_helpdesk_verejny` (`verejny`,`stav`,`vytvoreno`);

--
-- Indexy pro tabulku `helpdesk_notifikace`
--
ALTER TABLE `helpdesk_notifikace`
  ADD PRIMARY KEY (`id_helpdesk_notifikace`),
  ADD KEY `ix_helpdesk_notifikace_user` (`id_user`,`precteno`,`vytvoreno`),
  ADD KEY `ix_helpdesk_notifikace_helpdesk` (`id_helpdesk`,`vytvoreno`),
  ADD KEY `ix_helpdesk_notifikace_zprava` (`id_helpdesk_zprava`);

--
-- Indexy pro tabulku `helpdesk_priloha`
--
ALTER TABLE `helpdesk_priloha`
  ADD PRIMARY KEY (`id_helpdesk_priloha`),
  ADD KEY `ix_helpdesk_priloha_helpdesk` (`id_helpdesk`,`vytvoreno`),
  ADD KEY `ix_helpdesk_priloha_zprava` (`id_helpdesk_zprava`),
  ADD KEY `ix_helpdesk_priloha_user` (`id_user`,`vytvoreno`);

--
-- Indexy pro tabulku `helpdesk_read`
--
ALTER TABLE `helpdesk_read`
  ADD PRIMARY KEY (`id_helpdesk_read`),
  ADD UNIQUE KEY `uq_helpdesk_read_helpdesk_user` (`id_helpdesk`,`id_user`),
  ADD KEY `ix_helpdesk_read_user` (`id_user`,`precteno`);

--
-- Indexy pro tabulku `helpdesk_sledujici`
--
ALTER TABLE `helpdesk_sledujici`
  ADD PRIMARY KEY (`id_helpdesk_sledujici`),
  ADD UNIQUE KEY `uq_helpdesk_sledujici` (`id_helpdesk`,`id_user`),
  ADD KEY `ix_helpdesk_sledujici_user` (`id_user`,`vytvoreno`);

--
-- Indexy pro tabulku `helpdesk_snapshot`
--
ALTER TABLE `helpdesk_snapshot`
  ADD PRIMARY KEY (`id_helpdesk_snapshot`),
  ADD UNIQUE KEY `uq_helpdesk_snapshot_zprava` (`id_helpdesk_zprava`),
  ADD KEY `ix_helpdesk_snapshot_helpdesk` (`id_helpdesk`,`vytvoreno`),
  ADD KEY `ix_helpdesk_snapshot_user` (`id_user`,`vytvoreno`);

--
-- Indexy pro tabulku `helpdesk_zprava`
--
ALTER TABLE `helpdesk_zprava`
  ADD PRIMARY KEY (`id_helpdesk_zprava`),
  ADD KEY `ix_helpdesk_zprava_helpdesk` (`id_helpdesk`,`vytvoreno`),
  ADD KEY `ix_helpdesk_zprava_user` (`id_user`,`vytvoreno`);

--
-- Indexy pro tabulku `hr_adresa`
--
ALTER TABLE `hr_adresa`
  ADD PRIMARY KEY (`id_adresa`),
  ADD KEY `idx_hr_adresa_person` (`id_person`),
  ADD KEY `idx_hr_adresa_platna` (`id_person`,`typ_adresy`,`platny`),
  ADD KEY `idx_hr_adresa_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_akce`
--
ALTER TABLE `hr_akce`
  ADD PRIMARY KEY (`id_akce`),
  ADD KEY `idx_hr_akce_user` (`id_user`,`vytvoreno`),
  ADD KEY `idx_hr_akce_person` (`id_person`,`vytvoreno`),
  ADD KEY `idx_hr_akce_dokument` (`id_dokument`,`verze`,`vytvoreno`),
  ADD KEY `idx_hr_akce_typ` (`akce`,`vytvoreno`);

--
-- Indexy pro tabulku `hr_cis_pozadavek_stav`
--
ALTER TABLE `hr_cis_pozadavek_stav`
  ADD PRIMARY KEY (`id_stav`);

--
-- Indexy pro tabulku `hr_dokument`
--
ALTER TABLE `hr_dokument`
  ADD PRIMARY KEY (`id_dokument`,`verze`),
  ADD KEY `idx_hr_dokument_person` (`id_person`),
  ADD KEY `idx_hr_dokument_dotaznik` (`id_uchazec_dotaznik`),
  ADD KEY `idx_hr_dokument_typ` (`id_dokument_typ`),
  ADD KEY `idx_hr_dokument_stav` (`id_dokument_stav`),
  ADD KEY `idx_hr_dokument_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_dokument_soubor`
--
ALTER TABLE `hr_dokument_soubor`
  ADD PRIMARY KEY (`id_dokument_soubor`),
  ADD KEY `idx_hr_dokument_soubor_dokument` (`id_dokument`,`verze`);

--
-- Indexy pro tabulku `hr_dokument_stav`
--
ALTER TABLE `hr_dokument_stav`
  ADD PRIMARY KEY (`id_dokument_stav`);

--
-- Indexy pro tabulku `hr_dokument_typ`
--
ALTER TABLE `hr_dokument_typ`
  ADD PRIMARY KEY (`id_dokument_typ`);

--
-- Indexy pro tabulku `hr_dotaznik_typ`
--
ALTER TABLE `hr_dotaznik_typ`
  ADD PRIMARY KEY (`id_dotaznik_typ`),
  ADD UNIQUE KEY `uq_hr_dotaznik_typ_kod_verze` (`kod`,`verze`),
  ADD KEY `idx_hr_dotaznik_typ_poradi` (`aktivni`,`poradi`),
  ADD KEY `idx_hr_dotaznik_typ_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_email`
--
ALTER TABLE `hr_email`
  ADD PRIMARY KEY (`id_email`),
  ADD KEY `idx_hr_email_person` (`id_person`),
  ADD KEY `idx_hr_email_typ` (`id_email_typ`),
  ADD KEY `idx_hr_email_platny` (`id_person`,`platny`),
  ADD KEY `idx_hr_email_email` (`email`),
  ADD KEY `idx_hr_email_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_email_typ`
--
ALTER TABLE `hr_email_typ`
  ADD PRIMARY KEY (`id_email_typ`),
  ADD UNIQUE KEY `uq_hr_email_typ_nazev` (`nazev`),
  ADD KEY `idx_hr_email_typ_poradi` (`aktivni`,`poradi`),
  ADD KEY `idx_hr_email_typ_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_mzdy_mesic`
--
ALTER TABLE `hr_mzdy_mesic`
  ADD PRIMARY KEY (`id_hr_mzda_mesic`),
  ADD KEY `idx_hr_mzdy_obdobi` (`rok`,`mesic`),
  ADD KEY `idx_hr_mzdy_user_obdobi` (`id_user`,`rok`,`mesic`),
  ADD KEY `idx_hr_mzdy_import_jmeno` (`import_jmeno`);

--
-- Indexy pro tabulku `hr_osobni_udaje`
--
ALTER TABLE `hr_osobni_udaje`
  ADD PRIMARY KEY (`id_osobni_udaje`),
  ADD KEY `idx_hr_osobni_udaje_person` (`id_person`),
  ADD KEY `idx_hr_osobni_udaje_platny` (`id_person`,`platny`),
  ADD KEY `idx_hr_osobni_udaje_prijmeni` (`prijmeni`,`jmeno`),
  ADD KEY `idx_hr_osobni_udaje_rodne_cislo` (`rodne_cislo`),
  ADD KEY `idx_hr_osobni_udaje_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_person`
--
ALTER TABLE `hr_person`
  ADD PRIMARY KEY (`id_person`),
  ADD UNIQUE KEY `uq_hr_person_user` (`id_user`),
  ADD UNIQUE KEY `uq_hr_person_osobni_cislo` (`osobni_cislo`),
  ADD KEY `idx_hr_person_vztah` (`vztah`),
  ADD KEY `idx_hr_person_stav` (`id_uchazec_stav`),
  ADD KEY `idx_hr_person_zdroj` (`id_uchazec_zdroj`),
  ADD KEY `idx_hr_person_jmeno` (`prijmeni`,`jmeno`),
  ADD KEY `idx_hr_person_posledni_aktivita` (`posledni_aktivita`),
  ADD KEY `idx_hr_person_duplicita` (`id_person_duplicita`),
  ADD KEY `idx_hr_person_slot` (`id_slot`),
  ADD KEY `idx_hr_person_zadal` (`zadal`),
  ADD KEY `idx_hr_person_upravil` (`upravil`);

--
-- Indexy pro tabulku `hr_person_pracoviste`
--
ALTER TABLE `hr_person_pracoviste`
  ADD PRIMARY KEY (`id_person_pracoviste`),
  ADD KEY `idx_hr_person_pracoviste_person` (`id_person`,`platny`),
  ADD KEY `idx_hr_person_pracoviste_pob` (`id_pob`),
  ADD KEY `idx_hr_person_pracoviste_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_person_zarazeni`
--
ALTER TABLE `hr_person_zarazeni`
  ADD PRIMARY KEY (`id_person_zarazeni`),
  ADD KEY `idx_hr_person_zarazeni_person` (`id_person`,`platny`),
  ADD KEY `idx_hr_person_zarazeni_slot` (`id_slot`),
  ADD KEY `idx_hr_person_zarazeni_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_pozadavek`
--
ALTER TABLE `hr_pozadavek`
  ADD PRIMARY KEY (`id_hr_pozadavek`),
  ADD KEY `idx_hr_pozadavek_otevrene` (`stav`,`id_pob`,`id_slot`,`zadano`),
  ADD KEY `idx_hr_pozadavek_pob_slot` (`id_pob`,`id_slot`),
  ADD KEY `idx_hr_pozadavek_person` (`id_person`),
  ADD KEY `idx_hr_pozadavek_zadal` (`zadal`),
  ADD KEY `idx_hr_pozadavek_vyridil` (`vyridil`),
  ADD KEY `idx_hr_pozadavek_zrusil` (`zrusil`),
  ADD KEY `fk_hr_pozadavek_slot` (`id_slot`);

--
-- Indexy pro tabulku `hr_pracovni_vztah`
--
ALTER TABLE `hr_pracovni_vztah`
  ADD PRIMARY KEY (`id_pracovni_vztah`),
  ADD KEY `idx_hr_pracovni_vztah_person` (`id_person`),
  ADD KEY `idx_hr_pracovni_vztah_typ` (`id_pracovni_vztah_typ`),
  ADD KEY `idx_hr_pracovni_vztah_platny` (`id_person`,`platny`),
  ADD KEY `idx_hr_pracovni_vztah_datum` (`datum_nastupu`,`datum_ukonceni`),
  ADD KEY `idx_hr_pracovni_vztah_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_pracovni_vztah_typ`
--
ALTER TABLE `hr_pracovni_vztah_typ`
  ADD PRIMARY KEY (`id_pracovni_vztah_typ`),
  ADD UNIQUE KEY `uq_hr_pracovni_vztah_typ_kod` (`kod`),
  ADD KEY `idx_hr_pracovni_vztah_typ_poradi` (`aktivni`,`poradi`),
  ADD KEY `idx_hr_pracovni_vztah_typ_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_sazby`
--
ALTER TABLE `hr_sazby`
  ADD PRIMARY KEY (`id_hr_sazba`),
  ADD KEY `idx_hr_sazby_user_od` (`id_user`,`platnost_od`),
  ADD KEY `idx_hr_sazby_user_do` (`id_user`,`platnost_do`),
  ADD KEY `idx_hr_sazby_import_jmeno` (`import_jmeno`),
  ADD KEY `idx_hr_sazby_mzda_typ` (`id_mzda_typ`);

--
-- Indexy pro tabulku `hr_telefon`
--
ALTER TABLE `hr_telefon`
  ADD PRIMARY KEY (`id_telefon`),
  ADD KEY `idx_hr_telefon_person` (`id_person`),
  ADD KEY `idx_hr_telefon_typ` (`id_telefon_typ`),
  ADD KEY `idx_hr_telefon_platny` (`id_person`,`platny`),
  ADD KEY `idx_hr_telefon_normalizovany` (`telefon_normalizovany`),
  ADD KEY `idx_hr_telefon_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_telefon_typ`
--
ALTER TABLE `hr_telefon_typ`
  ADD PRIMARY KEY (`id_telefon_typ`),
  ADD UNIQUE KEY `uq_hr_telefon_typ_nazev` (`nazev`),
  ADD KEY `idx_hr_telefon_typ_poradi` (`aktivni`,`poradi`),
  ADD KEY `idx_hr_telefon_typ_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_uchazec_aktivita`
--
ALTER TABLE `hr_uchazec_aktivita`
  ADD PRIMARY KEY (`id_uchazec_aktivita`),
  ADD KEY `idx_hr_uchazec_aktivita_person` (`id_person`,`zadano`),
  ADD KEY `idx_hr_uchazec_aktivita_typ` (`id_uchazec_aktivita_typ`),
  ADD KEY `idx_hr_uchazec_aktivita_plan` (`planovano_na`),
  ADD KEY `idx_hr_uchazec_aktivita_dalsi_krok` (`dalsi_krok_do`),
  ADD KEY `idx_hr_uchazec_aktivita_user` (`id_user`);

--
-- Indexy pro tabulku `hr_uchazec_aktivita_typ`
--
ALTER TABLE `hr_uchazec_aktivita_typ`
  ADD PRIMARY KEY (`id_uchazec_aktivita_typ`),
  ADD UNIQUE KEY `uq_hr_uchazec_aktivita_typ_kod` (`kod`),
  ADD KEY `idx_hr_uchazec_aktivita_typ_poradi` (`aktivni`,`poradi`),
  ADD KEY `idx_hr_uchazec_aktivita_typ_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_uchazec_dotaznik`
--
ALTER TABLE `hr_uchazec_dotaznik`
  ADD PRIMARY KEY (`id_uchazec_dotaznik`),
  ADD KEY `idx_hr_uchazec_dotaznik_person` (`id_person`,`zadano`),
  ADD KEY `idx_hr_uchazec_dotaznik_typ` (`id_dotaznik_typ`),
  ADD KEY `idx_hr_uchazec_dotaznik_stav` (`stav`),
  ADD KEY `idx_hr_uchazec_dotaznik_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_uchazec_podminky`
--
ALTER TABLE `hr_uchazec_podminky`
  ADD PRIMARY KEY (`id_uchazec_podminky`),
  ADD KEY `idx_hr_uchazec_podminky_person` (`id_person`,`platny`),
  ADD KEY `idx_hr_uchazec_podminky_slot` (`id_slot`),
  ADD KEY `idx_hr_uchazec_podminky_pob` (`id_pob`),
  ADD KEY `idx_hr_uchazec_podminky_vztah` (`id_pracovni_vztah_typ`),
  ADD KEY `idx_hr_uchazec_podminky_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_uchazec_pozice`
--
ALTER TABLE `hr_uchazec_pozice`
  ADD PRIMARY KEY (`id_uchazec_pozice`),
  ADD UNIQUE KEY `uq_hr_uchazec_pozice` (`id_person`,`id_slot`),
  ADD KEY `idx_hr_uchazec_pozice_slot` (`id_slot`),
  ADD KEY `idx_hr_uchazec_pozice_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_uchazec_pracoviste`
--
ALTER TABLE `hr_uchazec_pracoviste`
  ADD PRIMARY KEY (`id_uchazec_pracoviste`),
  ADD UNIQUE KEY `uq_hr_uchazec_pracoviste` (`id_person`,`id_pob`),
  ADD KEY `idx_hr_uchazec_pracoviste_pob` (`id_pob`),
  ADD KEY `idx_hr_uchazec_pracoviste_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_uchazec_stav`
--
ALTER TABLE `hr_uchazec_stav`
  ADD PRIMARY KEY (`id_uchazec_stav`),
  ADD UNIQUE KEY `uq_hr_uchazec_stav_kod` (`kod`),
  ADD KEY `idx_hr_uchazec_stav_poradi` (`aktivni`,`poradi`),
  ADD KEY `idx_hr_uchazec_stav_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_uchazec_token`
--
ALTER TABLE `hr_uchazec_token`
  ADD PRIMARY KEY (`id_uchazec_token`),
  ADD UNIQUE KEY `uq_hr_uchazec_token_hash` (`token_hash`),
  ADD KEY `idx_hr_uchazec_token_person` (`id_person`),
  ADD KEY `idx_hr_uchazec_token_dotaznik` (`id_uchazec_dotaznik`),
  ADD KEY `idx_hr_uchazec_token_platnost` (`aktivni`,`platny_do`),
  ADD KEY `idx_hr_uchazec_token_zadal` (`zadal`);

--
-- Indexy pro tabulku `hr_uchazec_zdroj`
--
ALTER TABLE `hr_uchazec_zdroj`
  ADD PRIMARY KEY (`id_uchazec_zdroj`),
  ADD UNIQUE KEY `uq_hr_uchazec_zdroj_nazev` (`nazev`),
  ADD KEY `idx_hr_uchazec_zdroj_poradi` (`aktivni`,`poradi`),
  ADD KEY `idx_hr_uchazec_zdroj_zadal` (`zadal`);

--
-- Indexy pro tabulku `init_scripty`
--
ALTER TABLE `init_scripty`
  ADD PRIMARY KEY (`id_init_script`),
  ADD KEY `idx_oblast_krok_poradi` (`hlavni_oblast`,`krok`);

--
-- Indexy pro tabulku `karty`
--
ALTER TABLE `karty`
  ADD PRIMARY KEY (`id_karta`),
  ADD KEY `ix_karty_aktivni_poradi` (`aktivni`,`poradi`,`id_karta`);

--
-- Indexy pro tabulku `kontrolni_prehledy`
--
ALTER TABLE `kontrolni_prehledy`
  ADD PRIMARY KEY (`id_prehled`),
  ADD UNIQUE KEY `uq_kontrolni_prehledy_pob_rok_mesic` (`id_pob`,`rok`,`mesic`),
  ADD KEY `idx_kontrolni_prehledy_stav` (`stav`),
  ADD KEY `idx_kontrolni_prehledy_pob` (`id_pob`);

--
-- Indexy pro tabulku `loader_json`
--
ALTER TABLE `loader_json`
  ADD PRIMARY KEY (`loader_key`);

--
-- Indexy pro tabulku `log_chyby`
--
ALTER TABLE `log_chyby`
  ADD PRIMARY KEY (`id_log_chyby`),
  ADD KEY `idx_kdy` (`kdy`),
  ADD KEY `idx_id_user` (`id_user`),
  ADD KEY `idx_modul` (`modul`),
  ADD KEY `idx_kod` (`kod`),
  ADD KEY `idx_vyreseno` (`vyreseno`);

--
-- Indexy pro tabulku `objednavky_kontrola`
--
ALTER TABLE `objednavky_kontrola`
  ADD PRIMARY KEY (`id_kontrola`),
  ADD UNIQUE KEY `uq_objednavky_kontrola_mesic_pob` (`rok`,`mesic`,`id_pob`),
  ADD KEY `idx_objednavky_kontrola_rozdil` (`rozdil`),
  ADD KEY `idx_objednavky_kontrola_pob` (`id_pob`);

--
-- Indexy pro tabulku `objednavky_restia`
--
ALTER TABLE `objednavky_restia`
  ADD PRIMARY KEY (`id_obj`),
  ADD UNIQUE KEY `uq_obj_bk` (`id_pob`,`id_platforma`,`rest_obj`),
  ADD UNIQUE KEY `uq_objednavky_restia_restia_id_obj` (`restia_id_obj`),
  ADD KEY `ix_obj_pob` (`id_pob`),
  ADD KEY `ix_obj_platforma` (`id_platforma`),
  ADD KEY `ix_obj_stav` (`id_stav`),
  ADD KEY `ix_obj_platba` (`id_platba`),
  ADD KEY `ix_obj_doruceni` (`id_doruceni`),
  ADD KEY `ix_obj_zak` (`id_zak`),
  ADD KEY `ix_objednavky_restia_pob_stav_import` (`id_pob`,`id_stav`),
  ADD KEY `ix_objednavky_restia_pob_platforma_import` (`id_pob`,`id_platforma`),
  ADD KEY `ix_objednavky_restia_restia_order_number` (`restia_order_number`),
  ADD KEY `idx_objednavky_restia_created_at` (`restia_created_at`),
  ADD KEY `idx_objednavky_restia_idpob_created_at` (`id_pob`,`restia_created_at`),
  ADD KEY `idx_objednavky_restia_report` (`report`),
  ADD KEY `idx_objednavky_restia_idpob_report` (`id_pob`,`report`);

--
-- Indexy pro tabulku `obj_adresa`
--
ALTER TABLE `obj_adresa`
  ADD PRIMARY KEY (`id_obj`),
  ADD KEY `ix_obj_adresa_mesto` (`mesto`),
  ADD KEY `ix_obj_adresa_psc` (`psc`);

--
-- Indexy pro tabulku `obj_casy`
--
ALTER TABLE `obj_casy`
  ADD PRIMARY KEY (`id_obj`),
  ADD KEY `ix_obj_casy_report` (`report`),
  ADD KEY `ix_obj_casy_report_id` (`report`,`id_obj`),
  ADD KEY `ix_obj_casy_vytvor` (`cas_vytvor`),
  ADD KEY `ix_obj_casy_doruc` (`cas_doruc`);

--
-- Indexy pro tabulku `obj_ceny`
--
ALTER TABLE `obj_ceny`
  ADD PRIMARY KEY (`id_obj`);

--
-- Indexy pro tabulku `obj_import`
--
ALTER TABLE `obj_import`
  ADD PRIMARY KEY (`id_import`),
  ADD UNIQUE KEY `uq_obj_import_typ_pob_interval` (`typ_importu`,`id_pob`,`datum_od`,`datum_do`),
  ADD KEY `ix_obj_import_stav_spusteno` (`stav`,`spusteno`),
  ADD KEY `ix_obj_import_pob_datum` (`id_pob`,`datum_od`,`datum_do`);

--
-- Indexy pro tabulku `obj_kuryr`
--
ALTER TABLE `obj_kuryr`
  ADD PRIMARY KEY (`id_obj_kuryr`),
  ADD KEY `ix_obj_kuryr_obj` (`id_obj`),
  ADD KEY `ix_obj_kuryr_provider` (`provider`),
  ADD KEY `ix_obj_kuryr_externi_id` (`externi_id`);

--
-- Indexy pro tabulku `obj_polozka_kds_tag`
--
ALTER TABLE `obj_polozka_kds_tag`
  ADD PRIMARY KEY (`id_obj_polozka_kds_tag`),
  ADD KEY `ix_obj_polozka_kds_tag_obj_pol` (`id_obj_polozka`),
  ADD KEY `ix_obj_polozka_kds_tag_tag` (`tag`);

--
-- Indexy pro tabulku `obj_polozka_mod`
--
ALTER TABLE `obj_polozka_mod`
  ADD PRIMARY KEY (`id_obj_polozka_mod`),
  ADD KEY `ix_obj_polozka_mod_obj_pol` (`id_obj_polozka`),
  ADD KEY `ix_obj_polozka_mod_restia_mod_id` (`restia_mod_id`),
  ADD KEY `ix_obj_polozka_mod_pos_id` (`pos_id`);

--
-- Indexy pro tabulku `obj_polozky`
--
ALTER TABLE `obj_polozky`
  ADD PRIMARY KEY (`id_obj_polozka`),
  ADD KEY `ix_obj_polozka_obj` (`id_obj`),
  ADD KEY `ix_obj_polozky_res_polozka` (`id_res_polozka`),
  ADD KEY `ix_obj_polozky_obj_res` (`id_obj`,`id_res_polozka`);

--
-- Indexy pro tabulku `obj_restia_detail`
--
ALTER TABLE `obj_restia_detail`
  ADD PRIMARY KEY (`id_obj`),
  ADD KEY `idx_obj_restia_detail_hash` (`restia_payload_hash`);

--
-- Indexy pro tabulku `obj_sluzba`
--
ALTER TABLE `obj_sluzba`
  ADD PRIMARY KEY (`id_obj_sluzba`),
  ADD KEY `ix_obj_sluzba_obj` (`id_obj`),
  ADD KEY `ix_obj_sluzba_provider` (`provider`),
  ADD KEY `ix_obj_sluzba_externi_id` (`externi_id`);

--
-- Indexy pro tabulku `online_restia`
--
ALTER TABLE `online_restia`
  ADD PRIMARY KEY (`id_akce`),
  ADD KEY `idx_aktivni` (`aktivni`),
  ADD KEY `idx_konec` (`konec`);

--
-- Indexy pro tabulku `online_restia_pobocky`
--
ALTER TABLE `online_restia_pobocky`
  ADD PRIMARY KEY (`id_akce`,`id_pob`),
  ADD KEY `idx_online_restia_pobocky_id_pob` (`id_pob`);

--
-- Indexy pro tabulku `pobocka`
--
ALTER TABLE `pobocka`
  ADD PRIMARY KEY (`id_pob`),
  ADD UNIQUE KEY `uq_pobocka_kod` (`kod`),
  ADD KEY `ix_pobocka_restia_activePosId` (`restia_activePosId`),
  ADD KEY `idx_pobocka_oblast` (`oblast`);

--
-- Indexy pro tabulku `pob_email`
--
ALTER TABLE `pob_email`
  ADD PRIMARY KEY (`id_pob`,`email`);

--
-- Indexy pro tabulku `pob_manager`
--
ALTER TABLE `pob_manager`
  ADD PRIMARY KEY (`id_pob`,`id_user`),
  ADD KEY `fk_pob_manager_user` (`id_user`);

--
-- Indexy pro tabulku `pob_povoleni`
--
ALTER TABLE `pob_povoleni`
  ADD PRIMARY KEY (`id_user`,`id_pob`),
  ADD KEY `fk_pob_povoleni_pob` (`id_pob`);

--
-- Indexy pro tabulku `pob_povoleni_hist`
--
ALTER TABLE `pob_povoleni_hist`
  ADD PRIMARY KEY (`id_povoleni`),
  ADD KEY `ix_pob_povoleni_hist_id_user` (`id_user`),
  ADD KEY `ix_pob_povoleni_hist_id_pob` (`id_pob`);

--
-- Indexy pro tabulku `pob_tel`
--
ALTER TABLE `pob_tel`
  ADD PRIMARY KEY (`id_pob`,`telefon`);

--
-- Indexy pro tabulku `push_audit`
--
ALTER TABLE `push_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_user` (`id_user`),
  ADD KEY `ix_zarizeni` (`id_zarizeni`);

--
-- Indexy pro tabulku `push_login_2fa`
--
ALTER TABLE `push_login_2fa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_token` (`token`),
  ADD KEY `ix_user_stav` (`id_user`,`stav`),
  ADD KEY `fk_2fa_zarizeni` (`id_zarizeni`);

--
-- Indexy pro tabulku `push_parovani`
--
ALTER TABLE `push_parovani`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_push_parovani_token_hash` (`token_hash`),
  ADD KEY `idx_push_parovani_user` (`id_user`),
  ADD KEY `idx_push_parovani_aktivni` (`aktivni`),
  ADD KEY `idx_push_parovani_expi` (`expirace`);

--
-- Indexy pro tabulku `push_zarizeni`
--
ALTER TABLE `push_zarizeni`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_endpoint` (`id_user`,`endpoint_hash`),
  ADD KEY `ix_user` (`id_user`);

--
-- Indexy pro tabulku `reporty`
--
ALTER TABLE `reporty`
  ADD PRIMARY KEY (`id_reportu`),
  ADD UNIQUE KEY `uq_reporty_pob_datum` (`id_pob`,`datum_reportu`),
  ADD KEY `idx_reporty_datum` (`datum_reportu`),
  ADD KEY `idx_reporty_zdroj` (`zdroj`),
  ADD KEY `idx_reporty_oteviral` (`oteviral`),
  ADD KEY `idx_reporty_zaviral` (`zaviral`);

--
-- Indexy pro tabulku `reporty_is`
--
ALTER TABLE `reporty_is`
  ADD PRIMARY KEY (`id_reportu`),
  ADD UNIQUE KEY `uq_reporty_is_pob_datum` (`id_pob`,`datum_reportu`,`platny_unique`),
  ADD KEY `idx_reporty_is_datum` (`datum_reportu`),
  ADD KEY `idx_reporty_is_zdroj` (`zdroj`),
  ADD KEY `idx_reporty_is_oteviral` (`oteviral`),
  ADD KEY `idx_reporty_is_zaviral` (`zaviral`);

--
-- Indexy pro tabulku `reporty_is_osoby`
--
ALTER TABLE `reporty_is_osoby`
  ADD PRIMARY KEY (`id_reporty_osoby`),
  ADD KEY `idx_reporty_is_osoby_report` (`id_reportu`),
  ADD KEY `idx_reporty_is_osoby_user` (`id_user`),
  ADD KEY `idx_reporty_is_osoby_report_slot` (`id_reportu`),
  ADD KEY `idx_reporty_is_osoby_slot` (`slot`);

--
-- Indexy pro tabulku `reporty_is_pokladna`
--
ALTER TABLE `reporty_is_pokladna`
  ADD PRIMARY KEY (`id_reportu`);

--
-- Indexy pro tabulku `reporty_is_restia`
--
ALTER TABLE `reporty_is_restia`
  ADD PRIMARY KEY (`id_reportu`);

--
-- Indexy pro tabulku `reporty_osoby`
--
ALTER TABLE `reporty_osoby`
  ADD PRIMARY KEY (`id_reporty_osoby`),
  ADD KEY `idx_reporty_osoby_report` (`id_reportu`),
  ADD KEY `idx_reporty_osoby_user` (`id_user`),
  ADD KEY `idx_reporty_osoby_report_slot` (`id_reportu`),
  ADD KEY `idx_reporty_osoby_slot` (`slot`);

--
-- Indexy pro tabulku `reporty_pokladna`
--
ALTER TABLE `reporty_pokladna`
  ADD PRIMARY KEY (`id_reportu`);

--
-- Indexy pro tabulku `reporty_restia`
--
ALTER TABLE `reporty_restia`
  ADD PRIMARY KEY (`id_reportu`);

--
-- Indexy pro tabulku `restia_token`
--
ALTER TABLE `restia_token`
  ADD PRIMARY KEY (`id_restia_token`),
  ADD KEY `ix_expires_at` (`expires_at`);

--
-- Indexy pro tabulku `res_alergen`
--
ALTER TABLE `res_alergen`
  ADD PRIMARY KEY (`id_res_polozka`,`alergen`),
  ADD KEY `ix_res_alergen_alergen` (`alergen`);

--
-- Indexy pro tabulku `res_cena`
--
ALTER TABLE `res_cena`
  ADD PRIMARY KEY (`id_res_cena`),
  ADD KEY `ix_res_cena_polozka` (`id_res_polozka`),
  ADD KEY `ix_res_cena_kanal` (`kanal`),
  ADD KEY `ix_res_cena_poscode` (`pos_code`);

--
-- Indexy pro tabulku `res_kategorie`
--
ALTER TABLE `res_kategorie`
  ADD PRIMARY KEY (`id_res_kategorie`),
  ADD KEY `ix_res_kategorie_pob` (`id_pob`),
  ADD KEY `ix_res_kategorie_menu` (`id_restia_menu`);

--
-- Indexy pro tabulku `res_polozky`
--
ALTER TABLE `res_polozky`
  ADD PRIMARY KEY (`id_res_polozka`),
  ADD KEY `ix_res_polozka_kat` (`id_res_kategorie`),
  ADD KEY `ix_res_polozka_poscode` (`pos_code`),
  ADD KEY `ix_res_polozky_pob` (`id_pob`);

--
-- Indexy pro tabulku `set_system`
--
ALTER TABLE `set_system`
  ADD PRIMARY KEY (`id_set`);

--
-- Indexy pro tabulku `smeny_aktualizace`
--
ALTER TABLE `smeny_aktualizace`
  ADD PRIMARY KEY (`id_smeny_aktualizace`),
  ADD KEY `idx_akt_stav` (`stav`),
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
-- Indexy pro tabulku `smeny_report`
--
ALTER TABLE `smeny_report`
  ADD PRIMARY KEY (`id_smeny_report`),
  ADD KEY `idx_datum` (`datum`),
  ADD KEY `idx_pob_datum` (`id_pob`,`datum`),
  ADD KEY `idx_user_datum` (`jmeno`,`datum`),
  ADD KEY `idx_slot_datum` (`id_slot`,`datum`),
  ADD KEY `idx_pob_user_datum` (`id_pob`,`jmeno`,`datum`);

--
-- Indexy pro tabulku `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `uq_user_email` (`email`),
  ADD KEY `fk_user_role_1` (`id_role`);

--
-- Indexy pro tabulku `user_akce`
--
ALTER TABLE `user_akce`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_akce_cas` (`cas`),
  ADD KEY `idx_user_akce_user_cas` (`id_user`,`cas`),
  ADD KEY `idx_user_akce_akce_cas` (`id_akce`,`cas`),
  ADD KEY `idx_user_akce_karta_cas` (`id_karta`,`cas`),
  ADD KEY `idx_user_akce_login_cas` (`id_login`,`cas`);

--
-- Indexy pro tabulku `user_akce_db`
--
ALTER TABLE `user_akce_db`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_akce_db_cas` (`cas_start`),
  ADD KEY `idx_user_akce_db_user_cas` (`id_user`,`cas_start`),
  ADD KEY `idx_user_akce_db_akce_cas` (`id_akce`,`cas_start`);

--
-- Indexy pro tabulku `user_akce_db_detail`
--
ALTER TABLE `user_akce_db_detail`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `idx_uadd_request` (`id_user_akce_db`),
  ADD KEY `idx_uadd_typ_request` (`typ`,`id_user_akce_db`),
  ADD KEY `idx_uadd_karta_request` (`id_karta`,`id_user_akce_db`),
  ADD KEY `idx_uadd_created` (`created_at`);

--
-- Indexy pro tabulku `user_aktualizace`
--
ALTER TABLE `user_aktualizace`
  ADD PRIMARY KEY (`id_user_aktualizace`),
  ADD KEY `idx_user_aktualizace_spusteno` (`spusteno`),
  ADD KEY `idx_user_aktualizace_stav` (`stav`);

--
-- Indexy pro tabulku `user_bad_login`
--
ALTER TABLE `user_bad_login`
  ADD PRIMARY KEY (`id_bad_login`),
  ADD KEY `ix_user_bad_login_kdy` (`kdy`),
  ADD KEY `ix_user_bad_login_email_kdy` (`email`,`kdy`);

--
-- Indexy pro tabulku `user_card_set`
--
ALTER TABLE `user_card_set`
  ADD PRIMARY KEY (`id_user`,`id_karta`),
  ADD KEY `ix_user_card_set_karta` (`id_karta`),
  ADD KEY `ix_user_card_set_ikon` (`ikon`),
  ADD KEY `ix_user_card_set_user_pos` (`id_user`),
  ADD KEY `ix_user_card_set_user_poradi` (`id_user`,`poradi`,`id_karta`);

--
-- Indexy pro tabulku `user_login`
--
ALTER TABLE `user_login`
  ADD PRIMARY KEY (`id_login`),
  ADD KEY `ix_user_login_user_kdy` (`id_user`,`kdy`),
  ADD KEY `ix_user_login_session_kdy` (`kdy`);

--
-- Indexy pro tabulku `user_pin`
--
ALTER TABLE `user_pin`
  ADD PRIMARY KEY (`id_user`);

--
-- Indexy pro tabulku `user_pobocka`
--
ALTER TABLE `user_pobocka`
  ADD PRIMARY KEY (`id_user`,`id_pob`),
  ADD KEY `idx_user_pobocka_id_pob` (`id_pob`);

--
-- Indexy pro tabulku `user_pobocka_set`
--
ALTER TABLE `user_pobocka_set`
  ADD PRIMARY KEY (`id_user`,`id_pob`),
  ADD KEY `idx_user_pobocka_set_id_pob` (`id_pob`);

--
-- Indexy pro tabulku `user_role`
--
ALTER TABLE `user_role`
  ADD PRIMARY KEY (`id_user`,`id_role`),
  ADD KEY `fk_user_role_role` (`id_role`);

--
-- Indexy pro tabulku `user_set`
--
ALTER TABLE `user_set`
  ADD PRIMARY KEY (`id_user`);

--
-- Indexy pro tabulku `user_slot`
--
ALTER TABLE `user_slot`
  ADD PRIMARY KEY (`id_user`,`id_slot`),
  ADD KEY `ix_user_slot_id_slot` (`id_slot`);

--
-- Indexy pro tabulku `user_spy`
--
ALTER TABLE `user_spy`
  ADD PRIMARY KEY (`id_login`),
  ADD KEY `ix_user_spy_user` (`id_user`);

--
-- Indexy pro tabulku `zakaznik`
--
ALTER TABLE `zakaznik`
  ADD PRIMARY KEY (`id_zak`),
  ADD KEY `ix_zakaznik_id_pob` (`id_pob`),
  ADD KEY `idx_zakaznik_telefon` (`telefon`),
  ADD KEY `idx_zakaznik_telefon_norm` (`telefon_norm`);

--
-- AUTO_INCREMENT pro tabulky
--

--
-- AUTO_INCREMENT pro tabulku `admin_info`
--
ALTER TABLE `admin_info`
  MODIFY `id_admin_info` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `admin_info_user`
--
ALTER TABLE `admin_info_user`
  MODIFY `id_admin_info_user` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `api_restia`
--
ALTER TABLE `api_restia`
  MODIFY `id_api_restia` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `api_smeny`
--
ALTER TABLE `api_smeny`
  MODIFY `id_api_smeny` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `card_icons`
--
ALTER TABLE `card_icons`
  MODIFY `id_ikon` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `cis_akce`
--
ALTER TABLE `cis_akce`
  MODIFY `id_akce` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `cis_chyby`
--
ALTER TABLE `cis_chyby`
  MODIFY `id_cis_chyba` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `cis_doruceni`
--
ALTER TABLE `cis_doruceni`
  MODIFY `id_doruceni` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `cis_obj_platby`
--
ALTER TABLE `cis_obj_platby`
  MODIFY `id_platba` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `cis_obj_platforma`
--
ALTER TABLE `cis_obj_platforma`
  MODIFY `id_platforma` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `cis_obj_stav`
--
ALTER TABLE `cis_obj_stav`
  MODIFY `id_stav` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `cis_polozka_kat`
--
ALTER TABLE `cis_polozka_kat`
  MODIFY `id_kat` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `cis_polozky`
--
ALTER TABLE `cis_polozky`
  MODIFY `id_polozka` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `dr_pracovni`
--
ALTER TABLE `dr_pracovni`
  MODIFY `id_dr` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `dr_pracovni_osoby`
--
ALTER TABLE `dr_pracovni_osoby`
  MODIFY `id_dr_osoby` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `helpdesk`
--
ALTER TABLE `helpdesk`
  MODIFY `id_helpdesk` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `helpdesk_notifikace`
--
ALTER TABLE `helpdesk_notifikace`
  MODIFY `id_helpdesk_notifikace` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `helpdesk_priloha`
--
ALTER TABLE `helpdesk_priloha`
  MODIFY `id_helpdesk_priloha` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `helpdesk_read`
--
ALTER TABLE `helpdesk_read`
  MODIFY `id_helpdesk_read` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `helpdesk_sledujici`
--
ALTER TABLE `helpdesk_sledujici`
  MODIFY `id_helpdesk_sledujici` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `helpdesk_snapshot`
--
ALTER TABLE `helpdesk_snapshot`
  MODIFY `id_helpdesk_snapshot` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `helpdesk_zprava`
--
ALTER TABLE `helpdesk_zprava`
  MODIFY `id_helpdesk_zprava` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_adresa`
--
ALTER TABLE `hr_adresa`
  MODIFY `id_adresa` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_akce`
--
ALTER TABLE `hr_akce`
  MODIFY `id_akce` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_cis_pozadavek_stav`
--
ALTER TABLE `hr_cis_pozadavek_stav`
  MODIFY `id_stav` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_dokument`
--
ALTER TABLE `hr_dokument`
  MODIFY `id_dokument` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_dokument_soubor`
--
ALTER TABLE `hr_dokument_soubor`
  MODIFY `id_dokument_soubor` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_dokument_stav`
--
ALTER TABLE `hr_dokument_stav`
  MODIFY `id_dokument_stav` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_dokument_typ`
--
ALTER TABLE `hr_dokument_typ`
  MODIFY `id_dokument_typ` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_dotaznik_typ`
--
ALTER TABLE `hr_dotaznik_typ`
  MODIFY `id_dotaznik_typ` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_email`
--
ALTER TABLE `hr_email`
  MODIFY `id_email` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_email_typ`
--
ALTER TABLE `hr_email_typ`
  MODIFY `id_email_typ` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_mzdy_mesic`
--
ALTER TABLE `hr_mzdy_mesic`
  MODIFY `id_hr_mzda_mesic` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_osobni_udaje`
--
ALTER TABLE `hr_osobni_udaje`
  MODIFY `id_osobni_udaje` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_person`
--
ALTER TABLE `hr_person`
  MODIFY `id_person` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_person_pracoviste`
--
ALTER TABLE `hr_person_pracoviste`
  MODIFY `id_person_pracoviste` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_person_zarazeni`
--
ALTER TABLE `hr_person_zarazeni`
  MODIFY `id_person_zarazeni` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_pozadavek`
--
ALTER TABLE `hr_pozadavek`
  MODIFY `id_hr_pozadavek` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_pracovni_vztah`
--
ALTER TABLE `hr_pracovni_vztah`
  MODIFY `id_pracovni_vztah` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_pracovni_vztah_typ`
--
ALTER TABLE `hr_pracovni_vztah_typ`
  MODIFY `id_pracovni_vztah_typ` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_sazby`
--
ALTER TABLE `hr_sazby`
  MODIFY `id_hr_sazba` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_telefon`
--
ALTER TABLE `hr_telefon`
  MODIFY `id_telefon` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_telefon_typ`
--
ALTER TABLE `hr_telefon_typ`
  MODIFY `id_telefon_typ` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_uchazec_aktivita`
--
ALTER TABLE `hr_uchazec_aktivita`
  MODIFY `id_uchazec_aktivita` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_uchazec_aktivita_typ`
--
ALTER TABLE `hr_uchazec_aktivita_typ`
  MODIFY `id_uchazec_aktivita_typ` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_uchazec_dotaznik`
--
ALTER TABLE `hr_uchazec_dotaznik`
  MODIFY `id_uchazec_dotaznik` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_uchazec_podminky`
--
ALTER TABLE `hr_uchazec_podminky`
  MODIFY `id_uchazec_podminky` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_uchazec_pozice`
--
ALTER TABLE `hr_uchazec_pozice`
  MODIFY `id_uchazec_pozice` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_uchazec_pracoviste`
--
ALTER TABLE `hr_uchazec_pracoviste`
  MODIFY `id_uchazec_pracoviste` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_uchazec_stav`
--
ALTER TABLE `hr_uchazec_stav`
  MODIFY `id_uchazec_stav` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_uchazec_token`
--
ALTER TABLE `hr_uchazec_token`
  MODIFY `id_uchazec_token` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `hr_uchazec_zdroj`
--
ALTER TABLE `hr_uchazec_zdroj`
  MODIFY `id_uchazec_zdroj` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `init_scripty`
--
ALTER TABLE `init_scripty`
  MODIFY `id_init_script` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `karty`
--
ALTER TABLE `karty`
  MODIFY `id_karta` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `kontrolni_prehledy`
--
ALTER TABLE `kontrolni_prehledy`
  MODIFY `id_prehled` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `log_chyby`
--
ALTER TABLE `log_chyby`
  MODIFY `id_log_chyby` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `objednavky_kontrola`
--
ALTER TABLE `objednavky_kontrola`
  MODIFY `id_kontrola` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `objednavky_restia`
--
ALTER TABLE `objednavky_restia`
  MODIFY `id_obj` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `obj_import`
--
ALTER TABLE `obj_import`
  MODIFY `id_import` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `obj_kuryr`
--
ALTER TABLE `obj_kuryr`
  MODIFY `id_obj_kuryr` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `obj_polozka_kds_tag`
--
ALTER TABLE `obj_polozka_kds_tag`
  MODIFY `id_obj_polozka_kds_tag` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `obj_polozka_mod`
--
ALTER TABLE `obj_polozka_mod`
  MODIFY `id_obj_polozka_mod` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `obj_polozky`
--
ALTER TABLE `obj_polozky`
  MODIFY `id_obj_polozka` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `obj_sluzba`
--
ALTER TABLE `obj_sluzba`
  MODIFY `id_obj_sluzba` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `online_restia`
--
ALTER TABLE `online_restia`
  MODIFY `id_akce` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `pobocka`
--
ALTER TABLE `pobocka`
  MODIFY `id_pob` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `pob_povoleni_hist`
--
ALTER TABLE `pob_povoleni_hist`
  MODIFY `id_povoleni` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `push_audit`
--
ALTER TABLE `push_audit`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `push_login_2fa`
--
ALTER TABLE `push_login_2fa`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `push_parovani`
--
ALTER TABLE `push_parovani`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `push_zarizeni`
--
ALTER TABLE `push_zarizeni`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `reporty`
--
ALTER TABLE `reporty`
  MODIFY `id_reportu` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `reporty_is`
--
ALTER TABLE `reporty_is`
  MODIFY `id_reportu` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `reporty_is_osoby`
--
ALTER TABLE `reporty_is_osoby`
  MODIFY `id_reporty_osoby` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `reporty_osoby`
--
ALTER TABLE `reporty_osoby`
  MODIFY `id_reporty_osoby` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `res_cena`
--
ALTER TABLE `res_cena`
  MODIFY `id_res_cena` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `res_kategorie`
--
ALTER TABLE `res_kategorie`
  MODIFY `id_res_kategorie` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `res_polozky`
--
ALTER TABLE `res_polozky`
  MODIFY `id_res_polozka` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `set_system`
--
ALTER TABLE `set_system`
  MODIFY `id_set` int(11) NOT NULL AUTO_INCREMENT;

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

--
-- AUTO_INCREMENT pro tabulku `smeny_report`
--
ALTER TABLE `smeny_report`
  MODIFY `id_smeny_report` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `user_akce`
--
ALTER TABLE `user_akce`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `user_akce_db`
--
ALTER TABLE `user_akce_db`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `user_akce_db_detail`
--
ALTER TABLE `user_akce_db_detail`
  MODIFY `id_detail` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `user_aktualizace`
--
ALTER TABLE `user_aktualizace`
  MODIFY `id_user_aktualizace` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `user_bad_login`
--
ALTER TABLE `user_bad_login`
  MODIFY `id_bad_login` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `user_login`
--
ALTER TABLE `user_login`
  MODIFY `id_login` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `zakaznik`
--
ALTER TABLE `zakaznik`
  MODIFY `id_zak` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Omezení pro exportované tabulky
--

--
-- Omezení pro tabulku `admin_info_user`
--
ALTER TABLE `admin_info_user`
  ADD CONSTRAINT `fk_admin_info_user_info` FOREIGN KEY (`id_admin_info`) REFERENCES `admin_info` (`id_admin_info`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `cis_polozky`
--
ALTER TABLE `cis_polozky`
  ADD CONSTRAINT `fk_cis_polozka_kat` FOREIGN KEY (`id_polozka_kat`) REFERENCES `cis_polozka_kat` (`id_kat`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Omezení pro tabulku `dr_pracovni`
--
ALTER TABLE `dr_pracovni`
  ADD CONSTRAINT `fk_dr_pracovni_pob` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `dr_pracovni_osoby`
--
ALTER TABLE `dr_pracovni_osoby`
  ADD CONSTRAINT `fk_dr_pracovni_osoby_report` FOREIGN KEY (`id_dr`) REFERENCES `dr_pracovni` (`id_dr`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `helpdesk_notifikace`
--
ALTER TABLE `helpdesk_notifikace`
  ADD CONSTRAINT `fk_helpdesk_notifikace_helpdesk` FOREIGN KEY (`id_helpdesk`) REFERENCES `helpdesk` (`id_helpdesk`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_helpdesk_notifikace_zprava` FOREIGN KEY (`id_helpdesk_zprava`) REFERENCES `helpdesk_zprava` (`id_helpdesk_zprava`) ON DELETE SET NULL;

--
-- Omezení pro tabulku `helpdesk_priloha`
--
ALTER TABLE `helpdesk_priloha`
  ADD CONSTRAINT `fk_helpdesk_priloha_helpdesk` FOREIGN KEY (`id_helpdesk`) REFERENCES `helpdesk` (`id_helpdesk`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_helpdesk_priloha_zprava` FOREIGN KEY (`id_helpdesk_zprava`) REFERENCES `helpdesk_zprava` (`id_helpdesk_zprava`) ON DELETE SET NULL;

--
-- Omezení pro tabulku `helpdesk_read`
--
ALTER TABLE `helpdesk_read`
  ADD CONSTRAINT `fk_helpdesk_read_helpdesk` FOREIGN KEY (`id_helpdesk`) REFERENCES `helpdesk` (`id_helpdesk`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `helpdesk_sledujici`
--
ALTER TABLE `helpdesk_sledujici`
  ADD CONSTRAINT `fk_helpdesk_sledujici_helpdesk` FOREIGN KEY (`id_helpdesk`) REFERENCES `helpdesk` (`id_helpdesk`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `helpdesk_snapshot`
--
ALTER TABLE `helpdesk_snapshot`
  ADD CONSTRAINT `fk_helpdesk_snapshot_helpdesk` FOREIGN KEY (`id_helpdesk`) REFERENCES `helpdesk` (`id_helpdesk`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_helpdesk_snapshot_zprava` FOREIGN KEY (`id_helpdesk_zprava`) REFERENCES `helpdesk_zprava` (`id_helpdesk_zprava`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `helpdesk_zprava`
--
ALTER TABLE `helpdesk_zprava`
  ADD CONSTRAINT `fk_helpdesk_zprava_helpdesk` FOREIGN KEY (`id_helpdesk`) REFERENCES `helpdesk` (`id_helpdesk`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `hr_adresa`
--
ALTER TABLE `hr_adresa`
  ADD CONSTRAINT `fk_hr_adresa_person` FOREIGN KEY (`id_person`) REFERENCES `hr_person` (`id_person`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_adresa_zadal` FOREIGN KEY (`zadal`) REFERENCES `user` (`id_user`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_akce`
--
ALTER TABLE `hr_akce`
  ADD CONSTRAINT `fk_hr_akce_dokument` FOREIGN KEY (`id_dokument`,`verze`) REFERENCES `hr_dokument` (`id_dokument`, `verze`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_akce_person` FOREIGN KEY (`id_person`) REFERENCES `hr_person` (`id_person`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_akce_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_dokument`
--
ALTER TABLE `hr_dokument`
  ADD CONSTRAINT `fk_hr_dokument_dotaznik` FOREIGN KEY (`id_uchazec_dotaznik`) REFERENCES `hr_uchazec_dotaznik` (`id_uchazec_dotaznik`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_dokument_person` FOREIGN KEY (`id_person`) REFERENCES `hr_person` (`id_person`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_dokument_stav` FOREIGN KEY (`id_dokument_stav`) REFERENCES `hr_dokument_stav` (`id_dokument_stav`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_dokument_typ` FOREIGN KEY (`id_dokument_typ`) REFERENCES `hr_dokument_typ` (`id_dokument_typ`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_dokument_zadal` FOREIGN KEY (`zadal`) REFERENCES `user` (`id_user`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_dokument_soubor`
--
ALTER TABLE `hr_dokument_soubor`
  ADD CONSTRAINT `fk_hr_dokument_soubor_dokument` FOREIGN KEY (`id_dokument`,`verze`) REFERENCES `hr_dokument` (`id_dokument`, `verze`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_dotaznik_typ`
--
ALTER TABLE `hr_dotaznik_typ`
  ADD CONSTRAINT `fk_hr_dotaznik_typ_zadal` FOREIGN KEY (`zadal`) REFERENCES `user` (`id_user`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_email`
--
ALTER TABLE `hr_email`
  ADD CONSTRAINT `fk_hr_email_person` FOREIGN KEY (`id_person`) REFERENCES `hr_person` (`id_person`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_email_typ` FOREIGN KEY (`id_email_typ`) REFERENCES `hr_email_typ` (`id_email_typ`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_email_typ`
--
ALTER TABLE `hr_email_typ`
  ADD CONSTRAINT `fk_hr_email_typ_zadal` FOREIGN KEY (`zadal`) REFERENCES `user` (`id_user`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_osobni_udaje`
--
ALTER TABLE `hr_osobni_udaje`
  ADD CONSTRAINT `fk_hr_osobni_udaje_person` FOREIGN KEY (`id_person`) REFERENCES `hr_person` (`id_person`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_person`
--
ALTER TABLE `hr_person`
  ADD CONSTRAINT `fk_hr_person_duplicita` FOREIGN KEY (`id_person_duplicita`) REFERENCES `hr_person` (`id_person`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_person_slot` FOREIGN KEY (`id_slot`) REFERENCES `cis_slot` (`id_slot`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_person_stav` FOREIGN KEY (`id_uchazec_stav`) REFERENCES `hr_uchazec_stav` (`id_uchazec_stav`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_person_upravil` FOREIGN KEY (`upravil`) REFERENCES `user` (`id_user`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_person_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_person_zdroj` FOREIGN KEY (`id_uchazec_zdroj`) REFERENCES `hr_uchazec_zdroj` (`id_uchazec_zdroj`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_person_pracoviste`
--
ALTER TABLE `hr_person_pracoviste`
  ADD CONSTRAINT `fk_hr_person_pracoviste_person` FOREIGN KEY (`id_person`) REFERENCES `hr_person` (`id_person`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_person_pracoviste_pob` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_person_zarazeni`
--
ALTER TABLE `hr_person_zarazeni`
  ADD CONSTRAINT `fk_hr_person_zarazeni_person` FOREIGN KEY (`id_person`) REFERENCES `hr_person` (`id_person`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_person_zarazeni_slot` FOREIGN KEY (`id_slot`) REFERENCES `cis_slot` (`id_slot`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_pozadavek`
--
ALTER TABLE `hr_pozadavek`
  ADD CONSTRAINT `fk_hr_pozadavek_person` FOREIGN KEY (`id_person`) REFERENCES `hr_person` (`id_person`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_pozadavek_pob` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_pozadavek_slot` FOREIGN KEY (`id_slot`) REFERENCES `cis_slot` (`id_slot`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_pozadavek_stav` FOREIGN KEY (`stav`) REFERENCES `hr_cis_pozadavek_stav` (`id_stav`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_pracovni_vztah`
--
ALTER TABLE `hr_pracovni_vztah`
  ADD CONSTRAINT `fk_hr_pracovni_vztah_person` FOREIGN KEY (`id_person`) REFERENCES `hr_person` (`id_person`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_pracovni_vztah_typ` FOREIGN KEY (`id_pracovni_vztah_typ`) REFERENCES `hr_pracovni_vztah_typ` (`id_pracovni_vztah_typ`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_pracovni_vztah_typ`
--
ALTER TABLE `hr_pracovni_vztah_typ`
  ADD CONSTRAINT `fk_hr_pracovni_vztah_typ_zadal` FOREIGN KEY (`zadal`) REFERENCES `user` (`id_user`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_telefon`
--
ALTER TABLE `hr_telefon`
  ADD CONSTRAINT `fk_hr_telefon_person` FOREIGN KEY (`id_person`) REFERENCES `hr_person` (`id_person`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_telefon_typ` FOREIGN KEY (`id_telefon_typ`) REFERENCES `hr_telefon_typ` (`id_telefon_typ`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_telefon_typ`
--
ALTER TABLE `hr_telefon_typ`
  ADD CONSTRAINT `fk_hr_telefon_typ_zadal` FOREIGN KEY (`zadal`) REFERENCES `user` (`id_user`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_uchazec_aktivita`
--
ALTER TABLE `hr_uchazec_aktivita`
  ADD CONSTRAINT `fk_hr_uchazec_aktivita_person` FOREIGN KEY (`id_person`) REFERENCES `hr_person` (`id_person`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_uchazec_aktivita_typ` FOREIGN KEY (`id_uchazec_aktivita_typ`) REFERENCES `hr_uchazec_aktivita_typ` (`id_uchazec_aktivita_typ`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_uchazec_aktivita_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_uchazec_aktivita_typ`
--
ALTER TABLE `hr_uchazec_aktivita_typ`
  ADD CONSTRAINT `fk_hr_uchazec_aktivita_typ_zadal` FOREIGN KEY (`zadal`) REFERENCES `user` (`id_user`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_uchazec_dotaznik`
--
ALTER TABLE `hr_uchazec_dotaznik`
  ADD CONSTRAINT `fk_hr_uchazec_dotaznik_person` FOREIGN KEY (`id_person`) REFERENCES `hr_person` (`id_person`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_uchazec_dotaznik_typ` FOREIGN KEY (`id_dotaznik_typ`) REFERENCES `hr_dotaznik_typ` (`id_dotaznik_typ`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_uchazec_podminky`
--
ALTER TABLE `hr_uchazec_podminky`
  ADD CONSTRAINT `fk_hr_uchazec_podminky_person` FOREIGN KEY (`id_person`) REFERENCES `hr_person` (`id_person`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_uchazec_podminky_pob` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_uchazec_podminky_slot` FOREIGN KEY (`id_slot`) REFERENCES `cis_slot` (`id_slot`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_uchazec_podminky_vztah` FOREIGN KEY (`id_pracovni_vztah_typ`) REFERENCES `hr_pracovni_vztah_typ` (`id_pracovni_vztah_typ`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_uchazec_podminky_zadal` FOREIGN KEY (`zadal`) REFERENCES `user` (`id_user`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_uchazec_pozice`
--
ALTER TABLE `hr_uchazec_pozice`
  ADD CONSTRAINT `fk_hr_uchazec_pozice_person` FOREIGN KEY (`id_person`) REFERENCES `hr_person` (`id_person`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_uchazec_pozice_slot` FOREIGN KEY (`id_slot`) REFERENCES `cis_slot` (`id_slot`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_uchazec_pozice_zadal` FOREIGN KEY (`zadal`) REFERENCES `user` (`id_user`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_uchazec_pracoviste`
--
ALTER TABLE `hr_uchazec_pracoviste`
  ADD CONSTRAINT `fk_hr_uchazec_pracoviste_person` FOREIGN KEY (`id_person`) REFERENCES `hr_person` (`id_person`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_uchazec_pracoviste_pob` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_uchazec_pracoviste_zadal` FOREIGN KEY (`zadal`) REFERENCES `user` (`id_user`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_uchazec_stav`
--
ALTER TABLE `hr_uchazec_stav`
  ADD CONSTRAINT `fk_hr_uchazec_stav_zadal` FOREIGN KEY (`zadal`) REFERENCES `user` (`id_user`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_uchazec_token`
--
ALTER TABLE `hr_uchazec_token`
  ADD CONSTRAINT `fk_hr_uchazec_token_dotaznik` FOREIGN KEY (`id_uchazec_dotaznik`) REFERENCES `hr_uchazec_dotaznik` (`id_uchazec_dotaznik`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_uchazec_token_person` FOREIGN KEY (`id_person`) REFERENCES `hr_person` (`id_person`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_uchazec_token_zadal` FOREIGN KEY (`zadal`) REFERENCES `user` (`id_user`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `hr_uchazec_zdroj`
--
ALTER TABLE `hr_uchazec_zdroj`
  ADD CONSTRAINT `fk_hr_uchazec_zdroj_zadal` FOREIGN KEY (`zadal`) REFERENCES `user` (`id_user`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `objednavky_restia`
--
ALTER TABLE `objednavky_restia`
  ADD CONSTRAINT `fk_obj_doruceni` FOREIGN KEY (`id_doruceni`) REFERENCES `cis_doruceni` (`id_doruceni`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_obj_platba` FOREIGN KEY (`id_platba`) REFERENCES `cis_obj_platby` (`id_platba`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_obj_platforma` FOREIGN KEY (`id_platforma`) REFERENCES `cis_obj_platforma` (`id_platforma`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_obj_pob` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_obj_stav` FOREIGN KEY (`id_stav`) REFERENCES `cis_obj_stav` (`id_stav`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_obj_zak` FOREIGN KEY (`id_zak`) REFERENCES `zakaznik` (`id_zak`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Omezení pro tabulku `obj_adresa`
--
ALTER TABLE `obj_adresa`
  ADD CONSTRAINT `fk_obj_adresa_obj` FOREIGN KEY (`id_obj`) REFERENCES `objednavky_restia` (`id_obj`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `obj_casy`
--
ALTER TABLE `obj_casy`
  ADD CONSTRAINT `fk_obj_casy_objednavky_restia` FOREIGN KEY (`id_obj`) REFERENCES `objednavky_restia` (`id_obj`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `obj_ceny`
--
ALTER TABLE `obj_ceny`
  ADD CONSTRAINT `fk_obj_ceny_objednavky_restia` FOREIGN KEY (`id_obj`) REFERENCES `objednavky_restia` (`id_obj`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `obj_import`
--
ALTER TABLE `obj_import`
  ADD CONSTRAINT `fk_obj_import_pob` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Omezení pro tabulku `obj_kuryr`
--
ALTER TABLE `obj_kuryr`
  ADD CONSTRAINT `fk_obj_kuryr_obj` FOREIGN KEY (`id_obj`) REFERENCES `objednavky_restia` (`id_obj`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `obj_polozka_kds_tag`
--
ALTER TABLE `obj_polozka_kds_tag`
  ADD CONSTRAINT `fk_obj_polozka_kds_tag_obj_polozka` FOREIGN KEY (`id_obj_polozka`) REFERENCES `obj_polozky` (`id_obj_polozka`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `obj_polozka_mod`
--
ALTER TABLE `obj_polozka_mod`
  ADD CONSTRAINT `fk_obj_polozka_mod_obj_polozka` FOREIGN KEY (`id_obj_polozka`) REFERENCES `obj_polozky` (`id_obj_polozka`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `obj_polozky`
--
ALTER TABLE `obj_polozky`
  ADD CONSTRAINT `fk_obj_polozky_objednavky_restia` FOREIGN KEY (`id_obj`) REFERENCES `objednavky_restia` (`id_obj`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_obj_polozky_res_polozky` FOREIGN KEY (`id_res_polozka`) REFERENCES `res_polozky` (`id_res_polozka`);

--
-- Omezení pro tabulku `obj_restia_detail`
--
ALTER TABLE `obj_restia_detail`
  ADD CONSTRAINT `fk_obj_restia_detail_obj` FOREIGN KEY (`id_obj`) REFERENCES `objednavky_restia` (`id_obj`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `obj_sluzba`
--
ALTER TABLE `obj_sluzba`
  ADD CONSTRAINT `fk_obj_sluzba_obj` FOREIGN KEY (`id_obj`) REFERENCES `objednavky_restia` (`id_obj`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `online_restia_pobocky`
--
ALTER TABLE `online_restia_pobocky`
  ADD CONSTRAINT `fk_online_restia_pobocky_akce` FOREIGN KEY (`id_akce`) REFERENCES `online_restia` (`id_akce`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_online_restia_pobocky_pobocka` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `pob_email`
--
ALTER TABLE `pob_email`
  ADD CONSTRAINT `fk_pob_email_pob` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `pob_manager`
--
ALTER TABLE `pob_manager`
  ADD CONSTRAINT `fk_pob_manager_pob` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pob_manager_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `pob_povoleni`
--
ALTER TABLE `pob_povoleni`
  ADD CONSTRAINT `fk_pob_povoleni_pob` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pob_povoleni_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `pob_povoleni_hist`
--
ALTER TABLE `pob_povoleni_hist`
  ADD CONSTRAINT `fk_pob_povoleni_hist_pob` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pob_povoleni_hist_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `pob_tel`
--
ALTER TABLE `pob_tel`
  ADD CONSTRAINT `fk_pob_tel_pob` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `push_audit`
--
ALTER TABLE `push_audit`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_audit_zarizeni` FOREIGN KEY (`id_zarizeni`) REFERENCES `push_zarizeni` (`id`) ON DELETE SET NULL;

--
-- Omezení pro tabulku `push_login_2fa`
--
ALTER TABLE `push_login_2fa`
  ADD CONSTRAINT `fk_2fa_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_2fa_zarizeni` FOREIGN KEY (`id_zarizeni`) REFERENCES `push_zarizeni` (`id`) ON DELETE SET NULL;

--
-- Omezení pro tabulku `push_zarizeni`
--
ALTER TABLE `push_zarizeni`
  ADD CONSTRAINT `fk_push_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `reporty`
--
ALTER TABLE `reporty`
  ADD CONSTRAINT `fk_reporty_pob` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `reporty_is`
--
ALTER TABLE `reporty_is`
  ADD CONSTRAINT `fk_reporty_is_pob` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `reporty_is_osoby`
--
ALTER TABLE `reporty_is_osoby`
  ADD CONSTRAINT `fk_reporty_is_osoby_report` FOREIGN KEY (`id_reportu`) REFERENCES `reporty_is` (`id_reportu`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `reporty_is_pokladna`
--
ALTER TABLE `reporty_is_pokladna`
  ADD CONSTRAINT `fk_reporty_is_pokladna_report` FOREIGN KEY (`id_reportu`) REFERENCES `reporty_is` (`id_reportu`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `reporty_is_restia`
--
ALTER TABLE `reporty_is_restia`
  ADD CONSTRAINT `fk_reporty_is_restia_report` FOREIGN KEY (`id_reportu`) REFERENCES `reporty_is` (`id_reportu`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `reporty_osoby`
--
ALTER TABLE `reporty_osoby`
  ADD CONSTRAINT `fk_reporty_osoby_report` FOREIGN KEY (`id_reportu`) REFERENCES `reporty` (`id_reportu`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `reporty_pokladna`
--
ALTER TABLE `reporty_pokladna`
  ADD CONSTRAINT `fk_reporty_pokladna_report` FOREIGN KEY (`id_reportu`) REFERENCES `reporty` (`id_reportu`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `reporty_restia`
--
ALTER TABLE `reporty_restia`
  ADD CONSTRAINT `fk_reporty_restia_report` FOREIGN KEY (`id_reportu`) REFERENCES `reporty` (`id_reportu`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `res_alergen`
--
ALTER TABLE `res_alergen`
  ADD CONSTRAINT `fk_res_alergen_polozka` FOREIGN KEY (`id_res_polozka`) REFERENCES `res_polozky` (`id_res_polozka`);

--
-- Omezení pro tabulku `res_cena`
--
ALTER TABLE `res_cena`
  ADD CONSTRAINT `fk_res_cena_polozka` FOREIGN KEY (`id_res_polozka`) REFERENCES `res_polozky` (`id_res_polozka`);

--
-- Omezení pro tabulku `res_kategorie`
--
ALTER TABLE `res_kategorie`
  ADD CONSTRAINT `fk_res_kategorie_pob` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`);

--
-- Omezení pro tabulku `res_polozky`
--
ALTER TABLE `res_polozky`
  ADD CONSTRAINT `fk_res_polozka_kategorie` FOREIGN KEY (`id_res_kategorie`) REFERENCES `res_kategorie` (`id_res_kategorie`),
  ADD CONSTRAINT `fk_res_polozky_pob` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`);

--
-- Omezení pro tabulku `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `fk_user_role_1` FOREIGN KEY (`id_role`) REFERENCES `cis_role` (`id_role`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Omezení pro tabulku `user_akce_db_detail`
--
ALTER TABLE `user_akce_db_detail`
  ADD CONSTRAINT `fk_uadd_user_akce_db` FOREIGN KEY (`id_user_akce_db`) REFERENCES `user_akce_db` (`id`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `user_card_set`
--
ALTER TABLE `user_card_set`
  ADD CONSTRAINT `fk_user_card_set_ikon` FOREIGN KEY (`ikon`) REFERENCES `card_icons` (`id_ikon`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_card_set_karta` FOREIGN KEY (`id_karta`) REFERENCES `karty` (`id_karta`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_card_set_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `user_login`
--
ALTER TABLE `user_login`
  ADD CONSTRAINT `fk_user_login_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `user_pobocka`
--
ALTER TABLE `user_pobocka`
  ADD CONSTRAINT `fk_user_pobocka_pobocka` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_pobocka_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `user_pobocka_set`
--
ALTER TABLE `user_pobocka_set`
  ADD CONSTRAINT `fk_user_pobocka_set_pob` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_pobocka_set_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `user_role`
--
ALTER TABLE `user_role`
  ADD CONSTRAINT `fk_user_role_role` FOREIGN KEY (`id_role`) REFERENCES `cis_role` (`id_role`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_role_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `user_set`
--
ALTER TABLE `user_set`
  ADD CONSTRAINT `fk_user_set_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `user_slot`
--
ALTER TABLE `user_slot`
  ADD CONSTRAINT `fk_user_slot_slot` FOREIGN KEY (`id_slot`) REFERENCES `cis_slot` (`id_slot`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_slot_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `user_spy`
--
ALTER TABLE `user_spy`
  ADD CONSTRAINT `fk_user_spy_login` FOREIGN KEY (`id_login`) REFERENCES `user_login` (`id_login`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_spy_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `zakaznik`
--
ALTER TABLE `zakaznik`
  ADD CONSTRAINT `fk_zakaznik_pob` FOREIGN KEY (`id_pob`) REFERENCES `pobocka` (`id_pob`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
