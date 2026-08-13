<?php
// config/constants.php
declare(strict_types=1);

// Vychozi stranka pro neprihlaseneho uzivatele.
const CB_DEFAULT_PAGE_GUEST = 'uvod_demo_rotace';

// Vychozi stranka pro prihlaseneho uzivatele.
const CB_DEFAULT_PAGE_USER = 'uvod';

// Limit pro potvrzeni 2FA prihlaseni v sekundach.
const CB_2FA_LIMIT_SEC = 300;

// Interval kontroly 2FA stavu v milisekundach.
const CB_2FA_POLL_MS = 2000;

// Tabulka pro log uzivatelskych akci v nove architekture.
const CB_USER_AKCE_TABLE = 'user_akce_new';

// Ciselnik typu uzivatelskych akci v nove architekture.
const CB_USER_AKCE_CISELNIK_TABLE = 'cis_user_akce_new';

// Pocet objednavek na jednu stranku pri online stahovani Restie.
const CB_RESTIA_ONLINE_LIMIT = 100;

// Pocet objednavek na jednu stranku pri historickem stahovani Restie.
const CB_RESTIA_HIST_LIMIT = 200;

// Vychozi ID menu Restie pro import menu.
const CB_RESTIA_MENU_DEFAULT_ID = '762f8daa-ca39-4d8f-ae4a-d22b4d106e88';

// GraphQL endpoint systemu Smeny.
const CB_SMENY_GQL_URL = 'https://smeny.pizzacomeback.cz/graphql';

// Pocet uzivatelu na jednu stranku pri importu uzivatelu ze Smen.
const CB_SMENY_USER_PAGE_LIMIT = 100;

// Nazev DB locku pro import uzivatelu ze Smen.
const CB_SMENY_USER_LOCK_NAME = 'cb_plnime_smeny_user';

// Nejstarsi datum, od ktereho import planu smen pracuje.
const CB_SMENY_PLAN_MIN_DATE = '2020-10-26';

// Timeout GraphQL requestu pri importu planu smen v sekundach.
const CB_SMENY_PLAN_REQUEST_TIMEOUT_SEC = 60;

// Pauza mezi tydny pri importu planu smen v mikrosekundach.
const CB_SMENY_PLAN_SLEEP_BETWEEN_WEEKS_US = 500000;

// Specialni hodnota pro volbu stazeni vsech pobocek v importu planu smen.
const CB_SMENY_PLAN_UPDATE_ALL_ID = -1;
