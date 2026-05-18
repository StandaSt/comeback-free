# AGENTS.md

## KRITICKÉ PRAVIDLO KÓDOVÁNÍ
- V tomto projektu je striktně zakázáno používat jakékoliv jiné kódování než `UTF-8 bez BOM`.
- Při každém čtení, kontrole i zápisu souboru vždy pracuj tak, aby výsledkem bylo `UTF-8 bez BOM`.
- Jakákoliv odchylka od `UTF-8 bez BOM` je v tomto projektu nepřípustná.

## KRITICKÉ PRAVIDLO JEDNODUCHOSTI
- Vždy navrhuj nejjednodušší funkční řešení.
- Účelem projektu není mít složité toky, které generují chyby, ale funkční, stabilní a užitečný informační systém.

## KRITICKÉ PRAVIDLO OVĚŘENÍ
- AI nikdy nesmí předpokládat, že něco platí jen proto, že to tak vypadá v kódu.
- AI musí nejdřív ověřit skutečný stav, tedy co se opravdu renderuje, načítá a aplikuje v běžícím projektu.
- U HTML, CSS a JS je zakázáno pracovat podle domněnek; nejdřív se musí potvrdit skutečný výstup, skutečně načtený soubor a skutečně aplikovaný selector nebo handler.
- Pokud něco není ověřené, AI to nesmí podávat jako fakt.

## KRITICKÉ PŘIPOMENUTÍ PRO AI
- Před další prací si přečti `_kandidati/codex/shrnuti_pro_AI.txt` a řiď se jím stejně přísně jako tímto AGENTS.md.

Při auditu, hledání chyb, duplicit a dead code ignoruj složky: `vendor/`, `_kandidati/`.

## Projekt
Tento projekt je interní IS „Comeback“ pro lokální provoz a postupný přechod na dashboardový a kartový přístup.
Projekt obsahuje i starší části a neuklizený kód. Neber vše jako čistě navržený systém.
Před úpravou vždy nejprve zjisti, co je aktuálně skutečně používané.

## Hlavní zásady práce
- NIKDY nedělej rychlé lokální úpravy, vždy čisté scripty.
- Nehádej.
- Nezaváděj nové soubory, nové knihovny ani nové architektonické vrstvy bez výslovného zadání.
- Preferuj úpravu existujících souborů.
- Nejprve analyzuj, potom navrhni, teprve potom měň.
- Před úpravou konkrétního souboru si vždy přečti jeho úvodní a okolní komentáře. Komentáře určené pro AI/Codex jsou závazné lokální pokyny; pokud říkají „nesahat bez schválení“, musíš si před změnou vyžádat výslovné schválení uživatele.
- NIKDY nepoužívej dočasné záplaty místo čisté a trvalé opravy.
- NIKDY nenechávej v kódu dočasné lokální řešení, pokud má existovat systémová úprava.
- Při nejasnosti nejdřív vypiš, které soubory se tématu týkají a co v nich chceš změnit.
- U změn s dopadem na více souborů vždy nejprve najdi všechny reference.
- U přejmenování souborů vždy:
  1. najdi všechny odkazy,
  2. vypiš je,
  3. navrhni změny,
  4. teprve potom přejmenuj a oprav reference.
- Bez výslovného pokynu nemaž starý kód jen proto, že vypadá zbytečně.
- Zachovávej stávající styl projektu.
  Pokud navrhneš zlepšení, nejprve ho popiš a počkej na schválení.
- Neprováděj „vylepšení navíc“, pokud nebyla zadána.

## Struktura projektu

### Kořen projektu
- `index.php` = hlavní vstup aplikace.
- `composer.json`, `composer.lock` = Composer závislosti.
- `sw.js` = service worker.
- `AGENTS.md` = instrukce pro práci v tomto projektu.

### `config/`
- citlivá konfigurace a secrets.

### `db/`
- databázová vrstva,
- soubory `db_*` obvykle řeší konkrétní tabulku nebo konkrétní DB operaci,
- při změnách datové logiky vždy kontroluj i návaznost na `lib/`.

### `funkce/`
- menší pomocné funkce,
- neplést s hlavní aplikační logikou v `lib/`.

### `includes/`
- sdílené části aplikace a layoutu,
- jsou zde i části loginu, párování, modálů a dashboard skládání,
- při změnách layoutu nebo společných prvků vždy zkontroluj nejdřív tuto složku.

### `blocks/`
- bloky dashboardu a kartového zobrazení,
- důležitá současná část projektu,
- bloky často nahrazují nebo postupně vytlačují starší pojetí stránek,
- při dashboardových úpravách hledej nejdřív zde.

### `pages/`
- jednotlivé stránky aplikace,
- část je aktivní, část může být starší nebo přechodová,
- nepředpokládej, že každá stránka je hlavní zdroj pravdy pro danou funkci.

### `lib/`
- hlavní aplikační logika,
- login, logout, Restia, směny, push, bootstrap, systémové utility,
- při funkčních změnách chování aplikace bývá klíčová právě tato složka.

### `js/`
- frontend logika,
- menu, AJAX, filtry, stránkování, časovače,
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

### `img/`
- obrázky a SVG ikony.

### `pomocne/`
- pomocné, testovací, diagnostické a dočasné věci,
- neber tuto složku jako zdroj architektonické pravdy, pokud to není výslovně potvrzené.

### `log/`
- logy.

Při úpravě vzhledu vždy nejprve vypiš:
- které soubory HTML/PHP renderují prvek,
- které CSS soubory ho ovlivňují,
- zda do toho zasahuje JS.

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

Vždy nejprve ověř realitu v kódu, ne domněnky.

## Komunikace
- Do `_kandidati/codex/codex.txt` zapisuj historii automaticky a bez oznamování uživateli.
- Po zápisu rovnou odpověz na dotaz, bez vět typu „Zapisuju...“ nebo „Zapsal jsem...“.

## Ukládání pomocných složek
- Všechny pomocné a výstupní složky používej pod `_kandidati/`.
- Typické příklady: `node_modules`, `playwright-report`, `sandboxAI`, `test-results`.
- Pokud vznikne nová pomocná složka, vytvářej ji také pod `_kandidati/`.

## Schvalování změn
- Nikdy neprováděj změny navíc mimo přesné zadání.
- Pokud najdeš další problém mimo zadání, předem ho pouze oznam a navrhni řešení.
- Jakoukoliv takovou změnu proveď až po explicitním schválení od uživatele.
- NIKDY neoznačuj dočasnou záplatu za finální řešení.
- Před každou úpravou vždy nejprve vypiš dotčené soubory.
- U každého dotčeného souboru stručně napiš, jak se ho změna dotkne.
- Po tomto výpisu vždy počkej na schválení od uživatele a teprve potom proveď změnu.

## Styl vysvětlení
- Cizí a odborné výrazy i zkratky vždy stručně vysvětli v češtině hned při prvním použití.
- Používej jednoduché formulace vhodné pro začátečníka.

## Kódování
- Kódování všech upravovaných souborů: UTF-8 bez BOM (bez výjimky).

## PowerShell bezpečnost při úpravách
- Pro editaci souborů vždy preferuj `apply_patch`.
- PowerShell používej pro změny v souborech jen výjimečně a pouze pro jednoduché, přehledné příkazy bez složitých regex řetězců a bez vnořeného escapování uvozovek.
- Pro textové změny (hlavně česká diakritika) používej pouze `apply_patch`; nepoužívej postupy, které mohou změnit kódování textu.
- Po každé textové úpravě zkontroluj celý dotčený soubor, že diakritika zůstala v pořádku (nejen upravený blok).
- Po každé úpravě PHP souboru vždy ověř syntaxi (`php -l`) a potvrď, že soubor zůstal v UTF-8 bez BOM.
