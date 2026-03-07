# AGENTS.md

Při auditu, hledání chyb, duplicit a dead code ignoruj složky: vendor/, testy/, pomocne/.

## Projekt
Tento projekt je interní IS „Comeback“ pro lokální provoz a postupný přechod na dashboardový a kartový přístup.
Projekt obsahuje i starší části a neuklizený kód. Neber vše jako čistě navržený systém. Před úpravou vždy nejprve zjisti, co je aktuálně skutečně používané.

## Hlavní zásady práce
- Nehádej.
- Nezaváděj nové soubory, nové knihovny ani nové architektonické vrstvy bez výslovného zadání.
- Preferuj úpravu existujících souborů.
- Nejprve analyzuj, potom navrhni, teprve potom měň.
- Při nejasnosti nejdřív vypiš, které soubory se tématu týkají a co v nich chceš změnit.
- U změn s dopadem na více souborů vždy nejprve najdi všechny reference.
- U přejmenování souborů vždy:
  1. najdi všechny odkazy,
  2. vypiš je,
  3. navrhni změny,
  4. teprve potom přejmenuj a oprav reference.
- Bez výslovného pokynu nemaž starý kód jen proto, že vypadá zbytečně.
- Zachovávej stávající styl projektu, i když není ideální.
- Neprováděj „vylepšení navíc“, pokud nebyla zadána.

## Jak přemýšlet o projektu
Projekt je směs novějšího dashboardového směru a starších stránek.
Historicky se část věcí řešila přes `pages/`, ale novější směr jde přes dashboard a bloky.
Proto:
- `blocks/` ber jako důležitý současný směr vývoje,
- `pages/` může obsahovat jak aktivní stránky, tak starší nebo přechodové části,
- neber automaticky `pages/` jako jediné centrum aplikace.

## Struktura projektu

### Kořen projektu
- `index.php` = hlavní vstup aplikace.
- `composer.json`, `composer.lock` = Composer závislosti.
- `sw.js` = service worker.
- `AGENTS.md` = instrukce pro práci v tomto projektu.

### `config/`
- citlivá konfigurace a secrets.

### `db/`
- databázová vrstva.
- soubory `db_*` obvykle řeší konkrétní tabulku nebo konkrétní DB operaci.
- při změnách datové logiky vždy kontroluj i návaznost na `lib/`.

### `funkce/`
- menší pomocné funkce.
- neplést s hlavní aplikační logikou v `lib/`.

### `includes/`
- sdílené části aplikace a layoutu.
- jsou zde i části loginu, párování, modálů a dashboard skládání.
- při změnách layoutu nebo společných prvků vždy zkontroluj nejdřív tuto složku.

### `blocks/`
- bloky dashboardu a kartového zobrazení.
- toto je důležitá současná část projektu.
- bloky často nahrazují nebo postupně vytlačují starší pojetí stránek.
- při dashboardových úpravách hledej nejdřív zde.

### `pages/`
- jednotlivé stránky aplikace.
- část je aktivní, část může být starší nebo přechodová.
- nepředpokládej, že každá stránka je hlavní zdroj pravdy pro danou funkci.

### `lib/`
- hlavní aplikační logika.
- login, logout, Restia, směny, push, bootstrap, systémové utility.
- při funkčních změnách chování aplikace bývá klíčová právě tato složka.

### `js/`
- frontend logika.
- menu, AJAX, filtry, stránkování, časovače.
- při změnách chování rozhraní kontroluj spolu s `includes/`, `pages/` a `style/`.

### `style/1/`
Hlavní CSS je rozdělené po logických částech:
- `global.css` = globální pravidla,
- `hlavicka.css` = hlavička,
- `main.css` = hlavní střední část,
- `paticka.css` = patička,
- `karty.css` = karty / card prvky,
- `tabulky.css` = tabulky,
- další specializované CSS soubory dle názvu.

### `style/1/pages/`
- CSS konkrétních stránek.
- při úpravě vzhledu vždy nejprve zjisti, zda styl není přepisovaný právě zde.

### `img/`
- obrázky a SVG ikony.

### `pomocne/`
- pomocné, testovací, diagnostické a dočasné věci.
- neber tuto složku jako zdroj architektonické pravdy, pokud to není výslovně potvrzené.

### `testy/`
- testovací skripty.

### `log/`
- logy.

## Layout a vzhled
Při práci s layoutem vždy nejprve zjisti:
1. co se skládá přes `includes/`,
2. co se renderuje přes `pages/`,
3. co se skládá přes `blocks/`,
4. které CSS opravdu vyhrává.

Nepředpokládej, že vše řídí jeden soubor.
Styl může být kombinací:
- globálního CSS,
- layout CSS,
- CSS pro karty,
- CSS pro tabulky,
- stránkového CSS,
- případně JS chování.

Při úpravě vzhledu vždy nejprve vypiš:
- které soubory HTML/PHP renderují prvek,
- které CSS soubory ho ovlivňují,
- zda do toho zasahuje JS.

## Dashboard a bloky
Projekt se posouvá směrem:
- od klasických samostatných stránek
- k dashboardu, kartám a blokům.

Proto při nových úpravách zvaž:
- zda je změna v `blocks/`,
- zda starší `pages/` už jen nereferují starý stav,
- zda není stejná funkce řešena nově i starým způsobem paralelně.

## Login, modály, párování
Login a související chování není soustředěno jen na jedno místo.
Při zásahu do loginu, modálů nebo párování vždy kontroluj minimálně:
- `includes/`
- `lib/`
- `db/`
- případně `js/`

## Restia a směny
Integrace Restia a směn je rozložená hlavně mezi:
- `lib/`
- `db/`
- případně pomocné/testovací soubory.

Při úpravách API logiky vždy nejprve najdi celý tok:
- vstup,
- volání,
- logování,
- zápis do DB,
- návazné zobrazení.

## Pravidla pro bezpečné změny
- U více souborů vždy nejprve udělej seznam dotčených souborů.
- U refaktoru nejprve proveď analýzu referencí.
- U názvových změn nejprve vypiš všechny dopady.
- U CSS změn vždy zkontroluj, zda styl není přepisován jinde.
- U PHP include/require vazeb vždy ověř všechny reference v projektu.
- U JS změn ověř, na kterých stránkách se soubor načítá.

## Jak odpovídat při práci
Pokud je zadání malé a jasné:
- navrhni konkrétní změnu a proveď ji.

Pokud je zadání širší nebo rizikové:
1. napiš, které soubory se tématu týkají,
2. stručně popiš plán,
3. pak teprve navrhni změny.

## Co je v tomto projektu častý problém
- starší a novější způsob řešení vedle sebe,
- bordel po průběžném vývoji,
- CSS přepisy z více míst,
- logika rozdělená mezi `includes/`, `pages/`, `blocks/`, `lib/`, `db/`, `js/`.

Proto vždy nejprve ověř realitu v kódu, ne domněnky.
## Komunikace
- Do pomocne/codex.txt zapisuj historii automaticky a bez oznamovani uzivateli.
- Po zapisu rovnou odpovez na dotaz, bez vet typu "Zapisuju..." nebo "Zapsal jsem...".

## Schvalovani zmen
- Nikdy neprovadej zmeny navic mimo presne zadani.
- Pokud najdes dalsi problem mimo zadani, predem ho pouze oznam a navrhni reseni.
- Jakoukoliv takovou zmenu proved az po explicitnim schvaleni od uzivatele.

## Styl vysvetleni
- Cizi a odborne vyrazy i zkratky vzdy strucne vysvetli v cestine hned pri prvnim pouziti.
- Pouzivej jednoduche formulace vhodne pro zacatecnika.
