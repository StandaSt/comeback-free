function createFilePickerRestia() {
  var html = HtmlService.createHtmlOutputFromFile('FilePicker')
      .setWidth(600)
      .setHeight(425);
  SpreadsheetApp.getUi().showModalDialog(html, 'Select a file to import');
}

function importCSVRestia(csv) {
  try {
    var csvData = Utilities.parseCsv(csv, ';');
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('RestiaCSV');
    
    if (!sheet) {
      Logger.log("Error during importCSVRestia: 'RestiaCSV' sheet not found.");
      return;
    }
    
    sheet.clear();

    if (csvData && csvData.length > 0) {
      sheet.getRange(1, 1, csvData.length, csvData[0].length).setValues(csvData);
    } else {
      Logger.log("Error during importCSVRestia: CSV data is empty or not parsed correctly.");
      return;
    }

    SpreadsheetApp.flush();
    processSheetRestia();
  } catch (error) {
    Logger.log("Error during importCSVRestia: " + error.message);
    throw new Error("Failed to import CSV: " + error.message);
  }
}

function processSheetRestia() {
  try {
    Logger.log("Starting processSheetRestia");
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('RestiaCSV');
    if (!sheet) {
      Logger.log("Sheet 'RestiaCSV' not found");
      return;
    }
    var data = sheet.getDataRange().getValues();
    if (!data || data.length === 0) {
      Logger.log("Error during processSheetRestia: No data found in 'RestiaCSV'.");
      return;
    }
    Logger.log("Data range retrieved with length: " + data.length);

    data = removeRowsAndColumnsRestia(data);
    Logger.log("Data after removeRowsAndColumnsRestia with length: " + data.length);

    sheet.clear(); // Vymaže původní obsah listu
    sheet.getRange(1, 1, data.length, data[0].length).setValues(data); // Zapíše zpět vyfiltrovaná data

    addTimeDifferenceColumnHIRestia(sheet); // Výpočet rozdílu mezi H a I, výsledek do sloupce N
    addTimeDifferenceColumnHKRestia(sheet); // Výpočet rozdílu mezi H a K, výsledek do sloupce M

    matchAndFormatNamesRestia();
    Logger.log("matchAndFormatNamesRestia function called");

    insertFormulaInReport(); // Vloží vzorec do I2:K4 na listu Report

    // Nová funkce pro přidání nadpisů
    addColumnHeadersRestia();

    SpreadsheetApp.flush();
    Logger.log("Finished processSheetRestia");
  } catch (error) {
    Logger.log("Error during processSheetRestia: " + error.message);
    throw new Error("Failed to process sheet: " + error.message);
  }
}

function addColumnHeadersRestia() {
  try {
    Logger.log("Adding 'Přiřazení WoltDrivu' and 'Make Time' headers to RestiaCSV sheet");

    var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName("RestiaCSV");
    if (sheet) {
      sheet.getRange("M1").setValue("Přiřazení WoltDrivu");
      sheet.getRange("N1").setValue("Make Time");

      Logger.log("Headers 'Přiřazení WoltDrivu' and 'Make Time' added to M1 and N1.");
    } else {
      Logger.log("Sheet 'RestiaCSV' not found. Could not add headers.");
    }
  } catch (error) {
    Logger.log("Error while adding headers to RestiaCSV: " + error.message);
    throw new Error("Failed to add headers to RestiaCSV: " + error.message);
  }
}

function removeRowsAndColumnsRestia(data) {
  try {
    Logger.log("Starting removeRowsAndColumnsRestia");
    var reportSheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('Report');
    var reportDate = reportSheet.getRange('D1').getValue();
    var reportDateObj = new Date(reportDate);

    // Nastavení začátku na 4:00 aktuálního dne
    var startTime = new Date(reportDateObj);
    startTime.setHours(4, 0, 0, 0);

    // Nastavení konce na 4:00 následujícího dne
    var endTime = new Date(startTime);
    endTime.setDate(startTime.getDate() + 1);

    Logger.log("Start time: " + startTime + ", End time: " + endTime);

    var newData = [];
    if (data && data.length > 0) {
      newData.push(data[0]); // Přidá hlavičku

      for (var i = 1; i < data.length; i++) {
       var rowDateStr = data[i][19]; // Očekává, že datum je ve sloupci T (index 19)


        // Odebrání časové zóny (+02:00) z formátu ISO 8601 a převod na Date objekt
        var rowDate = new Date(rowDateStr.replace(/(\+|-)\d{2}:\d{2}/, ''));

        // Logování datumu pro každý řádek
        Logger.log("Row " + (i + 1) + ": Original Date: " + rowDateStr + ", Parsed Date: " + rowDate);

        // Kontrola, zda datum není neplatné
        if (!isNaN(rowDate.getTime())) {
          // Zkontrolovat, zda je datum mezi startTime a endTime
          if (rowDate >= startTime && rowDate < endTime) {
            newData.push(data[i]);
            Logger.log("Row " + (i + 1) + " is within range and kept.");
          } else {
            Logger.log("Row " + (i + 1) + " is out of range and removed.");
          }
        } else {
          Logger.log("Invalid date format in row " + (i + 1) + ": " + rowDateStr);
        }
      }

      // Odstranění nepotřebných sloupců
      var columnsToRemove = [1, 2, 3, 6, 7, 8, 9, 10, 11, 16, 17, 18, 20, 21, 24, 25];

      for (var i = 0; i < newData.length; i++) {
        for (var j = columnsToRemove.length - 1; j >= 0; j--) {
          newData[i].splice(columnsToRemove[j], 1);
        }
      }
    } else {
      Logger.log("Error during removeRowsAndColumnsRestia: Input data is empty or undefined.");
    }

    Logger.log("Finished removeRowsAndColumnsRestia");
    return newData;
  } catch (error) {
    Logger.log("Error during removeRowsAndColumnsRestia: " + error.message);
    throw new Error("Failed to remove rows and columns: " + error.message);
  }
}

function addTimeDifferenceColumnHIRestia(sheet) {
  try {
    Logger.log("Starting addTimeDifferenceColumnHIRestia");

    var timeHColumn = sheet.getRange("H2:H" + sheet.getLastRow()).getValues();
    var timeIColumn = sheet.getRange("I2:I" + sheet.getLastRow()).getValues();

    if (timeHColumn.length > 0 && timeIColumn.length > 0) {
      Logger.log("Data in H column: " + timeHColumn.length + " rows");
      Logger.log("Data in I column: " + timeIColumn.length + " rows");

      var newColumnData = [];

      for (var i = 0; i < timeHColumn.length; i++) {
        var timeH = timeHColumn[i][0];
        var timeI = timeIColumn[i][0];

        if (timeH && timeI && timeH !== "" && timeI !== "") {
          var dateH = new Date(timeH);
          var dateI = new Date(timeI);
          if (!isNaN(dateH.getTime()) && !isNaN(dateI.getTime())) {
            var diff = (dateI - dateH) / (1000 * 60); // Výpočet rozdílu v minutách
            newColumnData.push([diff]); // Ukládá jako číselnou hodnotu v minutách
          } else {
            newColumnData.push([""]);
          }
        } else {
          newColumnData.push([""]);
        }
      }

      sheet.getRange(2, 14, newColumnData.length, 1).setValues(newColumnData); // Uloží výsledek do sloupce N
    } else {
      Logger.log("Error during addTimeDifferenceColumnHIRestia: Input data is empty or has insufficient rows.");
    }

    Logger.log("Finished addTimeDifferenceColumnHIRestia");
  } catch (error) {
    Logger.log("Error during addTimeDifferenceColumnHIRestia: " + error.message);
    throw new Error("Failed to add time difference column HI: " + error.message);
  }
}

function addTimeDifferenceColumnHKRestia(sheet) {
  try {
    Logger.log("Starting addTimeDifferenceColumnHKRestia");

    var timeHColumn = sheet.getRange("H2:H" + sheet.getLastRow()).getValues();
    var timeKColumn = sheet.getRange("K2:K" + sheet.getLastRow()).getValues();

    if (timeHColumn.length > 0 && timeKColumn.length > 0) {
      Logger.log("Data in H column: " + timeHColumn.length + " rows");
      Logger.log("Data in K column: " + timeKColumn.length + " rows");

      var newColumnData = [];

      for (var i = 0; i < timeHColumn.length; i++) {
        var timeH = timeHColumn[i][0];
        var timeK = timeKColumn[i][0];

        if (timeH && timeK && timeH !== "" && timeK !== "") {
          var dateH = new Date(timeH);
          var dateK = new Date(timeK);
          if (!isNaN(dateH.getTime()) && !isNaN(dateK.getTime())) {
            var diff = (dateK - dateH) / (1000 * 60); // Výpočet rozdílu v minutách
            newColumnData.push([diff]); // Ukládá jako číselnou hodnotu v minutách
          } else {
            newColumnData.push([""]);
          }
        } else {
          newColumnData.push([""]);
        }
      }

      sheet.getRange(2, 13, newColumnData.length, 1).setValues(newColumnData); // Uloží výsledek do sloupce M
    } else {
      Logger.log("Error during addTimeDifferenceColumnHKRestia: Input data is empty or has insufficient rows.");
    }

    Logger.log("Finished addTimeDifferenceColumnHKRestia");
  } catch (error) {
    Logger.log("Error during addTimeDifferenceColumnHKRestia: " + error.message);
    throw new Error("Failed to add time difference column HK: " + error.message);
  }
}

function matchAndFormatNamesRestia() {
  try {
    Logger.log("Starting matchAndFormatNamesRestia");

    var spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
    var scheduleSheet = spreadsheet.getSheetByName("RestiaCSV");
    var peopleSheet = spreadsheet.getSheetByName("HR");

    if (!scheduleSheet) {
      Logger.log("Schedule sheet 'RestiaCSV' not found.");
      return;
    }
    if (!peopleSheet) {
      Logger.log("People sheet 'HR' not found.");
      return;
    }

    var scheduleData = scheduleSheet.getRange("E2:E" + scheduleSheet.getLastRow()).getValues();
    var peopleData = peopleSheet.getRange("B2:B" + peopleSheet.getLastRow()).getValues();

    if (scheduleData.length === 0 || peopleData.length === 0) {
      Logger.log("No data found in one of the sheets.");
      return;
    }

    for (var i = 0; i < scheduleData.length; i++) {
      var name = scheduleData[i][0];

      if (name && name.trim() !== "") {
        var bestMatch = null;
        var maxScore = 0;

        for (var j = 0; j < peopleData.length; j++) {
          var fullName = peopleData[j] ? peopleData[j][0] : '';
          if (fullName && fullName.trim() !== "") {
            var score = compareNamesRestia(name, fullName);

            if (score > maxScore && score >= 50) {
              maxScore = score;
              bestMatch = fullName;
            }
          }
        }

        if (bestMatch) {
          scheduleSheet.getRange(i + 2, 5).setValue(bestMatch);
        } else {
          scheduleSheet.getRange(i + 2, 5).setValue(name);
        }
      } else {
        scheduleSheet.getRange(i + 2, 5).setValue(name);
      }
    }

    Logger.log("Finished matchAndFormatNamesRestia");
  } catch (error) {
    Logger.log("Error during matchAndFormatNamesRestia: " + error.message);
    throw new Error("Failed to match and format names: " + error.message);
  }
}

function compareNamesRestia(name1, name2) {
  var normalized1 = normalizeStringRestia(name1);
  var normalized2 = normalizeStringRestia(name2);
  
  var score = 0;
  if (normalized1 === normalized2) {
    score = 100;
  } else {
    var parts1 = normalized1.split(" ");
    var parts2 = normalized2.split(" ");
    
    for (var i = 0; i < parts1.length; i++) {
      if (parts2.indexOf(parts1[i]) > -1) {
        score += 50;
      }
    }
  }
  return score;
}

function normalizeStringRestia(str) {
  if (typeof str !== 'string') {
    return '';
  }
  return str
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/[^a-zA-Z0-9\s]/g, '');
}

function protectSheetRestia() {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('RestiaCSV');
  if (sheet) {
    sheet.protect().setWarningOnly(true);
    Logger.log("Sheet protected with warning only.");
  } else {
    Logger.log("Sheet 'RestiaCSV' not found. Could not protect.");
  }
}

function insertFormulaInReport() {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName("Report");
  if (sheet) {
    var range = sheet.getRange("I2:K4");
    var formula = '=SUM(RestiaCSV!C:C) - SUMIFS(RestiaCSV!C:C; RestiaCSV!B:B; "canceled")';
    range.setFormula(formula);
    Logger.log("Formula inserted in I2:K4 of Report sheet.");
  } else {
    Logger.log("Sheet 'Report' not found.");
  }
}
