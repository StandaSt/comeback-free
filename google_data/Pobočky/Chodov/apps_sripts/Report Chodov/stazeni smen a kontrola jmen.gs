function showUploadDialog() {
  var htmlOutput = HtmlService.createHtmlOutputFromFile('UploadFile')
      .setWidth(400)
      .setHeight(300);
  SpreadsheetApp.getUi().showModalDialog(htmlOutput, 'Nahrát nový soubor');
}

function uploadData(data) {
  var spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = spreadsheet.getSheetByName('Směny');
  
  var rows = data.split('\n');
  var expandedValues = [];

  rows.forEach(function(row) {
    var cells = row.split(';').map(function(cell) {
      return cell ? cell.replace(/"/g, '') : ''; // Odstranění uvozovek a kontrola na prázdnou hodnotu
    });

    // Primární řádek s první směnou pro každý den
    var primaryRow = new Array(cells.length).fill('');
    primaryRow[0] = cells[0]; // Zkopírujeme jméno zaměstnance do prvního sloupce

    // Procházíme jednotlivé buňky a zpracujeme směny
    for (var i = 1; i < cells.length; i++) { // Začínáme od indexu 1, protože index 0 je jméno
      var shifts = cells[i].split('\n'); // Rozdělení směn pomocí nového řádku
      
      // První směna zůstane v původním řádku
      primaryRow[i] = shifts[0];

      // Každou další směnu vložíme do nového řádku
      for (var j = 1; j < shifts.length; j++) {
        var additionalRow = new Array(cells.length).fill(''); // Vytvoříme nový prázdný řádek
        additionalRow[0] = cells[0]; // Zkopírujeme jméno zaměstnance do prvního sloupce
        additionalRow[i] = shifts[j]; // Vložíme druhou a další směnu do stejného sloupce jako první směna

        expandedValues.push(additionalRow); // Přidáme nový řádek do výsledného pole
      }
    }

    expandedValues.push(primaryRow); // Přidáme primární řádek na konec
  });

  // Doplníme každý řádek na přesně 8 sloupců, pokud by měl méně
  expandedValues = expandedValues.map(row => {
    while (row.length < 8) {
      row.push('');
    }
    return row;
  });

  // Vyčistíme list od A2 a nastavíme nová data
  sheet.getRange('A2:Z').clear(); // Zrušíme vše od A2 dolů
  sheet.getRange(2, 1, expandedValues.length, 8).setValues(expandedValues); // Zapisujeme přesně 8 sloupců

  adjustNewlyCreatedRows(sheet); // Spustíme funkci na posunutí řádků doprava

  matchAndFormatNames(); // Spuštění skriptu pro kontrolu a změnu jmen
}

// Funkce pro posunutí nově vytvořených řádků o jeden sloupec doprava a zkopírování jména do sloupce A
function adjustNewlyCreatedRows(sheet) {
  var dataRange = sheet.getDataRange();
  var data = dataRange.getValues();

  // Regulární výraz pro kontrolu, zda buňka obsahuje pouze písmena
  var lettersOnlyPattern = /^[a-zA-Zá-žÁ-ŽěščřžýáíéúůťňďóäöüÄÖÜ\s]+$/;

  for (var i = 1; i < data.length; i++) { // Začínáme od druhého řádku (index 1)
    var currentCellValue = data[i][0];
    Logger.log("Checking row " + (i + 1) + ": " + currentCellValue); // Logujeme hodnotu v aktuálním řádku

    var isLettersOnly = lettersOnlyPattern.test(currentCellValue);
    Logger.log("Is Letters Only: " + isLettersOnly); // Logujeme výsledek testu pro písmena

    if ((!data[i][0] || /^\d+$/.test(data[i][0]) || !isLettersOnly) && data[i].some(cell => cell)) { 
      // Pokud je sloupec A prázdný, obsahuje pouze čísla, nebo neobsahuje pouze písmena
      Logger.log("Condition met, shifting row..."); // Logujeme, že podmínka byla splněna a řádek bude posunut

      var originalValue = data[i][0]; // Uložíme původní hodnotu ze sloupce A, kterou chceme přesunout do B
      data[i][0] = data[i - 1][0]; // Zkopírujeme hodnotu z buňky nad do sloupce A aktuálního řádku

      // Posuneme hodnoty v řádku o jeden sloupec doprava, začínáme od konce řádku a končíme ve sloupci C
      for (var j = data[i].length - 1; j > 1; j--) {
        data[i][j] = data[i][j - 1];
      }

      data[i][1] = originalValue; // Vložíme původní hodnotu ze sloupce A do sloupce B
    }
  }

  dataRange.setValues(data); // Aktualizujeme data v tabulce
}

function matchAndFormatNames() {
  var spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  var scheduleSheet = spreadsheet.getSheetByName("Směny");
  var peopleSheet = spreadsheet.getSheetByName("HR");
  
  var scheduleData = scheduleSheet.getRange("A2:A" + scheduleSheet.getLastRow()).getValues(); // Získání dat ze seznamu směn
  var peopleData = peopleSheet.getRange("B2:B" + peopleSheet.getLastRow()).getValues(); // Získání dat ze seznamu lidí
  
  // Projde všechna jména v seznamu směn
  for (var i = 0; i < scheduleData.length; i++) {
    var name = scheduleData[i][0]; // Jméno ze seznamu směn
    var formattedName = findAndFormatName(name, peopleData); // Hledání a formátování jména
    
    if (formattedName) {
      // Aktualizace jména v seznamu směn
      scheduleSheet.getRange(i + 2, 1).setValue(formattedName); // +2 protože začínáme od řádku 2
    }
  }
}

// Funkce pro nalezení a formátování jména podle seznamu lidí
function findAndFormatName(name, peopleData) {
  var bestMatch = null;
  var maxScore = 0;
  
  // Projde všechny jména v seznamu lidí
  for (var j = 0; j < peopleData.length; j++) {
    var fullName = peopleData[j][0]; // Jméno ze seznamu lidí
    var score = compareNames(name, fullName); // Porovnávání jmen
    
    // Uloží nejlepší shodu
    if (score > maxScore && score >= 50) {
      maxScore = score;
      bestMatch = fullName;
    }
  }
  
  return bestMatch;
}

// Funkce pro porovnání jmen
function compareNames(name1, name2) {
  // Normalizace jmen
  var normalized1 = normalizeString(name1);
  var normalized2 = normalizeString(name2);
  
  // Rozdělení jmen na slova
  var words1 = normalized1.split(' ');
  var words2 = normalized2.split(' ');
  
  // Počet shodných slov (přímá shoda)
  var commonWords = words1.filter(word => words2.includes(word)).length;
  
  // Počet shodných slov (shoda jméno/příjmení v opačném pořadí)
  var commonWordsReversed = words1.filter(word => words2.reverse().includes(word)).length;
  
  // Výpočet procentuální shody (vyšší z obou možností)
  var score = Math.max((commonWords / Math.max(words1.length, words2.length)) * 100,
                       (commonWordsReversed / Math.max(words1.length, words2.length)) * 100);
  
  return score;
}

// Funkce pro normalizaci řetězce - odstraní diakritiku, interpunkci a převede na malá písmena
function normalizeString(str) {
  return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[.,\/#!$%\^&\*;:{}=\-_`~()]/g, "").toLowerCase();
}

