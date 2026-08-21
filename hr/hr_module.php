<?php
/*
 * Ucel souboru: Pripravna deklarace modulu HR pro budouci jednotnou architekturu.
 * Soubor popisuje data modulu, ale v tomto kroku jej aplikace jeste nenacita.
 * Neobsahuje HTML, DB logiku, layout, hlavicku, PP ani zpracovani pozadavku.
 */
declare(strict_types=1);

/*
 * Vraci nemennou konfiguraci HR modulu.
 * Stranky zde obsahuji stabilni identitu a titulek. Rozlozeni a bloky se
 * doplnuji postupne pri samostatne migraci kazde konkretni stranky.
 */
return [
    'key' => 'hr',
    'title' => 'Personalistika',
    'menu' => [
        ['page' => 'prehled', 'label' => 'Přehled'],
        ['page' => 'nabor', 'label' => 'Nábor'],
        ['page' => 'zamestnanci', 'label' => 'Zaměstnanci'],
        ['page' => 'pozadavky', 'label' => 'Požadavky'],
        ['page' => 'pracovni_pomery', 'label' => 'Pracovní poměry'],
        ['page' => 'dokumenty', 'label' => 'Dokumenty'],
        ['page' => 'skoleni', 'label' => 'Školení'],
        ['page' => 'prohlidky', 'label' => 'Lékařské prohlídky'],
        ['page' => 'dovolene', 'label' => 'Dovolené'],
        ['page' => 'reporty', 'label' => 'Reporty'],
    ],
    'pages' => [
        'prehled' => [
            'title' => 'Přehled',
            'root_class' => 'hr_pp',
            'layout' => [
                'type' => 'grid',
                'columns' => 3,
            ],
            'header_controls' => [
                [
                    'key' => 'hr_header_hledani',
                    'file' => __DIR__ . '/hr_includes/hr_header_hledani.php',
                ],
            ],
            /*
             * Poskytovatel vraci pouze data pro bloky prehledu.
             * Neni soucasti layoutu ani spolecneho PP rendereru.
             */
            'context_provider' => 'hr_prehled_data',
            'context_file' => __DIR__ . '/hr_lib/hr_prehled_data.php',
            /*
             * Kazdy blok odpovida jedne samostatne casti soucasneho prehledu.
             * V tomto kroku jde jen o deklaraci; soucasna stranka je jeste
             * nevykresluje pres spolecny renderer.
             */
            'blocks' => [
                [
                    'key' => 'hr_prehled_statistiky',
                    'file' => __DIR__ . '/hr_blocks/hr_prehled_statistiky.php',
                    'span' => 3,
                ],
                [
                    'key' => 'hr_prehled_agendy',
                    'file' => __DIR__ . '/hr_blocks/hr_prehled_agendy.php',
                    'span' => 3,
                ],
                [
                    'key' => 'hr_prehled_posledni_zamestnanci',
                    'file' => __DIR__ . '/hr_blocks/hr_prehled_posledni_zamestnanci.php',
                    'span' => 2,
                ],
                [
                    'key' => 'hr_prehled_rychle_odkazy',
                    'file' => __DIR__ . '/hr_blocks/hr_prehled_rychle_odkazy.php',
                    'span' => 1,
                ],
                [
                    'key' => 'hr_prehled_tabulka_zamestnancu',
                    'file' => __DIR__ . '/hr_blocks/hr_prehled_tabulka_zamestnancu.php',
                    'span' => 3,
                ],
            ],
        ],
        'nabor' => ['title' => 'Nábor'],
        'zamestnanci' => ['title' => 'Zaměstnanci'],
        'zamestnanec' => ['title' => 'Karta zaměstnance'],
        'novy_zamestnanec' => [
            'title' => 'Nový zaměstnanec',
            'root_class' => 'hr_pp',
            'layout' => 'stack',
            'header_controls' => [
                [
                    'key' => 'hr_header_hledani',
                    'file' => __DIR__ . '/hr_includes/hr_header_hledani.php',
                ],
            ],
            'context_provider' => 'hr_novy_zamestnanec_data',
            'context_file' => __DIR__ . '/hr_lib/hr_novy_zamestnanec_data.php',
            'blocks' => [
                [
                    'key' => 'hr_novy_zamestnanec_formular',
                    'file' => __DIR__ . '/hr_blocks/hr_novy_zamestnanec_formular.php',
                    'span' => 1,
                ],
            ],
        ],
        'pozadavky' => [
            'title' => 'Požadavky',
            'root_class' => 'hr_pp',
            'layout' => 'stack',
            'header_controls' => [
                [
                    'key' => 'hr_header_hledani',
                    'file' => __DIR__ . '/hr_includes/hr_header_hledani.php',
                ],
            ],
            'context_provider' => 'hr_pozadavky_data',
            'context_file' => __DIR__ . '/hr_lib/hr_pozadavky_data.php',
            'blocks' => [
                [
                    'key' => 'hr_pozadavky_zadani',
                    'file' => __DIR__ . '/hr_blocks/hr_pozadavky_zadani.php',
                    'span' => 1,
                ],
                [
                    'key' => 'hr_pozadavky_nove',
                    'file' => __DIR__ . '/hr_blocks/hr_pozadavky_nove.php',
                    'span' => 1,
                ],
                [
                    'key' => 'hr_pozadavky_vyresene',
                    'file' => __DIR__ . '/hr_blocks/hr_pozadavky_vyresene.php',
                    'span' => 1,
                ],
                [
                    'key' => 'hr_pozadavky_expirovane',
                    'file' => __DIR__ . '/hr_blocks/hr_pozadavky_expirovane.php',
                    'span' => 1,
                ],
                [
                    'key' => 'hr_pozadavky_zrusene',
                    'file' => __DIR__ . '/hr_blocks/hr_pozadavky_zrusene.php',
                    'span' => 1,
                ],
            ],
        ],
        'pracovni_pomery' => ['title' => 'Pracovní poměry'],
        'dokumenty' => ['title' => 'Dokumenty'],
        'skoleni' => ['title' => 'Školení'],
        'prohlidky' => ['title' => 'Lékařské prohlídky'],
        'dovolene' => ['title' => 'Dovolené'],
        'reporty' => ['title' => 'Reporty'],
        'uprava_profilu' => ['title' => 'Úprava profilu'],
    ],
    'actions' => [
        'hr_nabor_ulozit_akci',
        [
            'key' => 'hr_pozadavek_vytvorit',
            'handler' => 'hr_post_pozadavek_vytvorit',
        ],
        [
            'key' => 'hr_pozadavek_zrusit',
            'handler' => 'hr_post_pozadavek_zrusit',
        ],
        [
            'key' => 'hr_zamestnanec_ulozit',
            'handler' => 'hr_post_zamestnanec',
        ],
    ],
    'assets' => [
        'css' => ['style/hr.css'],
        'js' => ['hr_js/hr.js'],
    ],
];
