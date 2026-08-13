# AGENTS.md

## KRITICKÉ PRAVIDLO KÓDOVÁNÍ
- V tomto projektu je striktně zakázáno používat jakékoliv jiné kódování než `UTF-8 bez BOM`.
- Při každém čtení, kontrole i zápisu souboru vždy pracuj tak, aby výsledkem bylo `UTF-8 bez BOM`.
- Jakákoliv odchylka od `UTF-8 bez BOM` je v tomto projektu nepřípustná.

## KRITICKÉ PRAVIDLO JEDNODUCHOSTI
- Vždy navrhuj nejjednodušší funkční řešení.
- Účelem projektu není mít složité toky, které generují chyby, ale funkční, stabilní a užitečný informační systém.
- Ideálně jednoúčelové scripty a ne slepence které dělají mnoho různých věcí

## KRITICKÉ PRAVIDLO OVĚŘENÍ
- AI nikdy nesmí předpokládat, že něco platí jen proto, že to tak vypadá v kódu.
- AI musí nejdřív ověřit skutečný stav, tedy co se opravdu renderuje, načítá a aplikuje v běžícím projektu.
- U HTML, CSS a JS je zakázáno pracovat podle domněnek; nejdřív se musí potvrdit skutečný výstup, skutečně načtený soubor a skutečně aplikovaný selector nebo handler.
- Pokud něco není ověřené, AI to nesmí podávat jako fakt.


Při auditu, hledání chyb, duplicit a dead code ignoruj složky: `vendor/`, `_kandidati/` a `admin_testy/`.

## Přístup do DB
Codex má permanentně povolený přístup do DB pro čtení struktury a obsahu. Pouze pro čtení.
Přístupové údaje jsou v common/config/secrets.php
Pro čtení DB použij mysql přes uživatele codex

## Projekt
Tento projekt je IS, informační systém Comeback. Skládá se u 5 modulů které budou všechny postavené na stejné databázi.
Projekt obsahuje i starší části a neuklizený kód. Neber vše jako čistě navržený systém.
Před úpravou vždy nejprve zjisti, co je aktuálně skutečně používané.

## Hlavní zásady práce
- NIKDY nedělej rychlé lokální úpravy, vždy čisté scripty.
- Nehádej.
- Nezaváděj nové soubory, nové knihovny ani nové architektonické vrstvy bez výslovného zadání.
- Nejprve analyzuj, potom navrhni, po schválení upravuj.
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
- Neprováděj nikdy „vylepšení navíc“, pokud nebyla zadána.

## Struktura projektu

### Kořen projektu
- `index.php` = hlavní vstup aplikace.
- `composer.json`, `composer.lock` = Composer závislosti.
- `sw.js` = service worker.
- `AGENTS.md` = instrukce pro práci v tomto projektu.

Všechny moduly mají stejnou strukturu ale vždy ve svém vlastním adresáři.

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
Pokud je zadání jasné - navrhni konkrétní změnu a vyčkej na schválení.
Pokud je několik možností řešení, hledej nejefektivnější a nejjdednodušší variantu
Pokud máš nejasnosti - zeptej se na detaily
Důležité: Na otázky odpovídej stručně, uživatel se detaily vyžádá pokud bude chtít !!

Vždy nejprve ověř realitu v kódu, ne domněnky.

## Komunikace
- Do `_kandidati/codex/codex.txt` zapisuj historii automaticky a bez oznamování uživateli. Zapisuj i datum a čas zápisu.
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





