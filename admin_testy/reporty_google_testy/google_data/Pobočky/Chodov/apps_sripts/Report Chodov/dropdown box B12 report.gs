function createDropdownsWithValidation() {
  // Smaže staré logy
  clearLogs();
  
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var reportSheet = ss.getSheetByName("Report");
  var hrSheet = ss.getSheetByName("HR");

  // Načtení dat pro dropdowny
  var instoreNames = hrSheet.getRange("B5:B73").getValues().flat().filter(name => typeof name === 'string' && name !== '');
  var courierNames = hrSheet.getRange("B74:B169").getValues().flat().filter(name => typeof name === 'string' && name !== '');
  var allValidNames = hrSheet.getRange("B5:B169").getValues().flat().filter(name => typeof name === 'string' && name !== '');

  // Vytvoření pravidel pro ověřování dat pro dropdowny s povolením neplatných hodnot
  var instoreRule = SpreadsheetApp.newDataValidation().requireValueInList(instoreNames).setAllowInvalid(true).build();
  var courierRule = SpreadsheetApp.newDataValidation().requireValueInList(courierNames).setAllowInvalid(true).build();

  // Nastavení ověřování dat pro dropdowny v listu Report
  reportSheet.getRange("B12:B21").setDataValidation(instoreRule);
  reportSheet.getRange("B25:B31").setDataValidation(courierRule);

  // Vytvoření rozbalovacích seznamů pro sloučené buňky G11:I13 a G19:I21
  var dropdownValues = reportSheet.getRange("B12:B21").getValues().flat().filter(name => typeof name === 'string' && name !== '');

  var dropdownRule = SpreadsheetApp.newDataValidation().requireValueInList(dropdownValues).setAllowInvalid(true).build();

  // Nastavení ověřování dat pro sloučené buňky
  reportSheet.getRange("G11:I13").setDataValidation(dropdownRule);
  reportSheet.getRange("G19:I21").setDataValidation(dropdownRule);

  // Vytvoření zaškrtávacích políček v rozsahu H25:H31
  var checkboxRange = reportSheet.getRange("H25:H31");
  checkboxRange.insertCheckboxes();

  // Přidání onEdit triggeru pro ověřování zadaných hodnot
  addOrUpdateTrigger();

  Logger.log("Dropdown seznamy a zaškrtávací políčka byly vytvořeny s validací.");
}

function addOrUpdateTrigger() {
  // Odstraní všechny existující triggery pro funkci 'validateEntry'
  deleteExistingTriggers();
  
  // Vytvoří nový trigger
  ScriptApp.newTrigger("validateEntry")
    .forSpreadsheet(SpreadsheetApp.getActiveSpreadsheet())
    .onEdit()
    .create();
}

function deleteExistingTriggers() {
  var allTriggers = ScriptApp.getProjectTriggers();
  
  for (var i = 0; i < allTriggers.length; i++) {
    if (allTriggers[i].getHandlerFunction() === 'validateEntry') {
      ScriptApp.deleteTrigger(allTriggers[i]);
    }
  }
}

// Přidána nová funkce pro mazání starých logů
function clearLogs() {
  Logger.clear();
  Logger.log("Staré logy byly vymazány.");
}
