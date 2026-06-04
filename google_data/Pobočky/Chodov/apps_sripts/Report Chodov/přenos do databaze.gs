function completeDataTransferAndFill() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var reportSheet = ss.getSheetByName("Report");

  var dateValue = reportSheet.getRange("D1").getValue();
  var aValue = reportSheet.getRange("B3").getValue();
  var branchName = reportSheet.getRange("B1").getValue(); // Získání názvu pobočky z buňky B1
  var currentDate = new Date(dateValue);
  var monthName = Utilities.formatDate(currentDate, Session.getScriptTimeZone(), "MMMM yyyy");
  var year = currentDate.getFullYear();

  // Získání ID aktuální složky, kde je umístěn tento skript
  var scriptFile = DriveApp.getFileById(ss.getId());
  var parentFolder = scriptFile.getParents().next();

  // Získání nebo vytvoření databázového souboru pro aktuální rok
  var databaseFile = getOrCreateDatabaseFile(parentFolder, branchName, year);
  var databaseSheet = getOrCreateMonthlySheet(databaseFile, monthName);
  
  Logger.log("Using sheet: " + databaseSheet.getName());

  // Přenos dat pro směny, mzdy a rozvozy
  transferShiftData(reportSheet, databaseSheet, dateValue, aValue, 'B12:F21', ['C', 'D', 'E', 'F', 'G']);
  transferShiftData(reportSheet, databaseSheet, dateValue, aValue, 'B25:I31', ['H', 'I', 'J', 'K', 'L', 'M', 'N', 'O']);

  // Přenos ostatních dat včetně dalších buněk z rozsahu J23:M32
  transferOtherData(reportSheet, databaseSheet, dateValue, aValue);

  // Uložení všech změn do listu
  SpreadsheetApp.flush();

  // Zpoždění pro zajištění, že všechny předchozí operace byly dokončeny
  Utilities.sleep(3000); // 3 sekundy pauza

  // Skrytí a zamknutí listu
  lockAndHideSheet(databaseSheet);
}

function transferShiftData(reportSheet, databaseSheet, dateValue, aValue, dataRange, targetColumns) {
  var data = reportSheet.getRange(dataRange).getValues();
  
  var firstEmptyRow = databaseSheet.getLastRow() + 1;
  var formattedDate = Utilities.formatDate(new Date(dateValue), Session.getScriptTimeZone(), "dd.MM.yyyy");
  
  for (var i = 0; i < data.length; i++) {
    if (data[i][0]) {  // Pokud jméno není prázdné
      var cell = databaseSheet.getRange(firstEmptyRow, 1);
      cell.setValue("'" + formattedDate); // Nastavení formátovaného datumu jako text, aby nedošlo k přepsání formátu
      databaseSheet.getRange(firstEmptyRow, 2).setValue(aValue); // Nastavení hodnoty v sloupci B
      
      for (var j = 0; j < targetColumns.length; j++) {
        var targetColumn = columnToNumber(targetColumns[j]);
        var cellValue = data[i][j];

        // Kontrola, zda je hodnota řetězec obsahující čas a uchování jako text
        if (typeof cellValue === 'string' && cellValue.includes(':')) {
          databaseSheet.getRange(firstEmptyRow, targetColumn).setValue("'" + cellValue); // Předchází konverzi na Date
        } else if (cellValue instanceof Date) {
          var formattedTime = Utilities.formatDate(cellValue, Session.getScriptTimeZone(), "HH:mm");
          databaseSheet.getRange(firstEmptyRow, targetColumn).setValue(formattedTime);
        } else {
          databaseSheet.getRange(firstEmptyRow, targetColumn).setValue(cellValue || "");
        }
      }
      firstEmptyRow++;
    }
  }
}

function transferOtherData(reportSheet, databaseSheet, dateValue, aValue) {
  var formattedDate = Utilities.formatDate(new Date(dateValue), Session.getScriptTimeZone(), "dd.MM.yyyy");

  var otherData = [
    [
      "'" + formattedDate, aValue, "", "", "", "", "", "", "", "", "", "", "", "", "",   // Sloupce A až P
      reportSheet.getRange("G32").getValue(), // Sloupec Q
      reportSheet.getRange("G11").getValue(), // Sloupec R
      reportSheet.getRange("G19").getValue(), // Sloupec S
      reportSheet.getRange("M2").getValue(), // Sloupec T
      reportSheet.getRange("M3").getValue(), // Sloupec U
      reportSheet.getRange("M4").getValue(), // Sloupec V
      reportSheet.getRange("M5").getValue(), // Sloupec W
      reportSheet.getRange("M6").getValue(), // Sloupec X
      reportSheet.getRange("M7").getValue(), // Sloupec Y
      reportSheet.getRange("M9").getValue(), // Sloupec Z
      reportSheet.getRange("M10").getValue(), // Sloupec AA
      reportSheet.getRange("M11").getValue(), // Sloupec AB
      reportSheet.getRange("I2").getValue(), // Sloupec AC
      reportSheet.getRange("J11").getValue(), // Sloupec AD
      reportSheet.getRange("F2").getValue(), // Sloupec AE
      reportSheet.getRange("M13").getValue(), // Sloupec AF
      reportSheet.getRange("M14").getValue(), // Sloupec AG
      reportSheet.getRange("M15").getValue(), // Sloupec AH
      reportSheet.getRange("M16").getValue(), // Sloupec AI
      reportSheet.getRange("M17").getValue(), // Sloupec AJ
      reportSheet.getRange("J23").getValue(), // Sloupec AK
      reportSheet.getRange("K23").getValue(), // Sloupec AL
      reportSheet.getRange("L23").getValue(), // Sloupec AM
      reportSheet.getRange("M23").getValue(), // Sloupec AN
      reportSheet.getRange("J26").getValue(), // Sloupec AO
      reportSheet.getRange("K26").getValue(), // Sloupec AP
      reportSheet.getRange("L26").getValue(), // Sloupec AQ
      reportSheet.getRange("M26").getValue(), // Sloupec AR
      reportSheet.getRange("J29").getValue(), // Sloupec AS
      reportSheet.getRange("L29").getValue(), // Sloupec AT
      reportSheet.getRange("M29").getValue(), // Sloupec AU
      reportSheet.getRange("L32").getValue(), // Sloupec AV
      reportSheet.getRange("M32").getValue()  // Sloupec AW
    ]
  ];

  var firstEmptyRow = databaseSheet.getLastRow() + 1;
  databaseSheet.getRange(firstEmptyRow, 1, 1, otherData[0].length).setValues(otherData);
}

function getOrCreateDatabaseFile(parentFolder, branchName, year) {
  var fileName = "Databaze " + branchName + " " + year;
  var files = parentFolder.getFilesByName(fileName);
  if (files.hasNext()) {
    return SpreadsheetApp.open(files.next());
  } else {
    // Vytvoření nového souboru, pokud neexistuje
    var newSpreadsheet = SpreadsheetApp.create(fileName);
    var file = DriveApp.getFileById(newSpreadsheet.getId());
    parentFolder.addFile(file);
    DriveApp.getRootFolder().removeFile(file); // Odebrání z kořenové složky My Drive
    return newSpreadsheet;
  }
}

function getOrCreateMonthlySheet(ss, monthName) {
  var sheet = ss.getSheetByName(monthName);
  if (!sheet) {
    Logger.log("Creating new sheet: " + monthName);
    sheet = ss.insertSheet(monthName);

    // Vytvoření specifických názvů sloupců s vložením "Vlastní vůz" do sloupce N a posunutím všech dalších sloupců doprava
    var columnHeaders = [
      "Datum", "Název", "Jména Instor", "Začátek směny Instor", "Konec směny Instor", "Pauza Instor", 
      "Počet hodin Instor", "Jména Kurýr", "Začátek směny Kurýr", "Konec směny Kurýr", "Pauza Kurýr", 
      "Počet hodin Kurýr", "Počet rozvozů Kurýr", "Vlastní vůz",  "Vyplatit PHM", "Wolt drive", "Otevíral", "Zavíral", 
      "Online Platby - Wolt", "Online Platby - Bolt", "Online Platby - Dáme Jídlo", "Vlastní API", 
      "Online Platby - Wolt cash", "Online Platby - DJ Cash", "Terminál", "Stravenky", "Hotovost", "Tržba", 
      "Pokladna - VÝSLEDEK", "COL", "Benzín", "Auta", "Suroviny", "Ostatní", "PHM - soukromé",
      "Zrušené obj. ks", "Zrušené obj. $", "Počet zpožděných objednávek z webu +5 min", "Průměrný make Time",
      "Počet vydajových dokladů", "Počet nezrušených celkem", "Počet našich rozvozů", "Počet pozdě přiřazeným WoltDrive 5+",
      "Woltrdrive zpožděný kvůli našemu pozdnímu přiřazení", "% zpožděných našich rozvozů +5 min", "Počet zpožděných Woltdrivem",
      "% doručených včas", "% zpožděných Woltdrivem"
    ];

    Logger.log("Setting column headers");
    sheet.getRange("A1:" + columnToLetter(columnHeaders.length) + "1").setValues([columnHeaders]); // Přiřazení nových hlaviček

    // Přidání zpoždění pro zajištění, že list je připraven
    Utilities.sleep(1000);
  } else {
    Logger.log("Using existing sheet: " + monthName);
  }
  return sheet;
}

function columnToNumber(columnLetter) {
  var sum = 0;
  for (var i = 0; i < columnLetter.length; i++) {
    sum = sum * 26 + (columnLetter.charCodeAt(i) - 'A'.charCodeAt(0) + 1);
  }
  return sum;
}

function columnToLetter(columnNumber) {
  var columnLetter = '';
  var temp;
  while (columnNumber > 0) {
    temp = (columnNumber - 1) % 26;
    columnLetter = String.fromCharCode(temp + 65) + columnLetter;
    columnNumber = (columnNumber - temp - 1) / 26;
  }
  return columnLetter;
}

function lockAndHideSheet(sheet) {
  // Skrytí listu
  sheet.hideSheet();
  
  // Uzamčení listu
  var protection = sheet.protect().setDescription('Měsíční data');
  protection.removeEditors(protection.getEditors());
  protection.addEditor(Session.getEffectiveUser().getEmail()); // Přidání aktuálního uživatele jako editor
}
