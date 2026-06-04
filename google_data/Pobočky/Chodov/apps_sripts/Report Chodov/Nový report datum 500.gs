function mainScript() {
  copyDataToDatabaseRestiaScript();
  clearRestiaCSVSheetScript(); // Tato funkce maže data z listu RestiaCSV po jejich zkopírování
  clearCellsBasedOnBackgroundColorScript();
  updateDateCellScript();
  copyScheduleToReportWithValidationScript();
}

function copyDataToDatabaseRestiaScript() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var reportSheet = ss.getSheetByName("Report");
  var restiaSheet = ss.getSheetByName("RestiaCSV");

  if (!restiaSheet) {
    Logger.log("Sheet 'RestiaCSV' does not exist.");
    return;
  }
  
  var data = restiaSheet.getDataRange().getValues();
  if (data.length === 0) {
    Logger.log("No data found in 'RestiaCSV'.");
    return;
  }

  var branchName = reportSheet.getRange("B1").getValue();
  var dateValue = reportSheet.getRange("D1").getValue();
  var currentDate = new Date(dateValue);
  var year = currentDate.getFullYear();
  var monthName = Utilities.formatDate(currentDate, Session.getScriptTimeZone(), "MMMM yyyy");

  var mainFolderId = "19k1tRgQT6xYPVbuVr5pkywQ6QwAPJd4O";
  var mainFolder = DriveApp.getFolderById(mainFolderId);

  var branchFolder = getOrCreateBranchFolderScript(mainFolder, branchName);

  var databaseFile = getOrCreateDatabaseFileScript(branchFolder, branchName, year);
  var databaseSheet = getOrCreateMonthlySheetScript(databaseFile, monthName);
  
  Logger.log("Using sheet: " + databaseSheet.getName());

  var formattedDate = "'" + Utilities.formatDate(currentDate, Session.getScriptTimeZone(), "dd.MM.yyyy");

  var dataWithDate = [];
  for (var i = 0; i < data.length; i++) { 
    if (i === 0) {
      var row = ["Datum"].concat(data[i]); // Přidání nadpisu "Datum" do prvního řádku
    } else {
      var row = [formattedDate].concat(data[i]); // Přidání textového data do dalších řádků
    }
    dataWithDate.push(row);
  }

  // Zkontrolujte, zda je první řádek ve vytvořeném listu stejný jako první řádek v RestiaCSV
  if (databaseSheet.getLastRow() === 0) {
    // Pokud je list nový, zkopírujeme první řádek
    databaseSheet.getRange(1, 1, 1, dataWithDate[0].length).setValues([dataWithDate[0]]);
  } else {
    // Pokud list existuje, zkontrolujeme první řádek
    var existingFirstRow = databaseSheet.getRange(1, 1, 1, dataWithDate[0].length).getValues()[0];
    if (!arraysEqual(existingFirstRow, dataWithDate[0])) {
      // Pokud se liší, aktualizujeme ho
      databaseSheet.getRange(1, 1, 1, dataWithDate[0].length).setValues([dataWithDate[0]]);
    }
  }

  if (dataWithDate.length > 1) {
    var firstEmptyRow = databaseSheet.getLastRow() + 1;
    databaseSheet.getRange(firstEmptyRow, 1, dataWithDate.length - 1, dataWithDate[0].length).setValues(dataWithDate.slice(1));
  } else {
    Logger.log("No additional data to copy to the database sheet.");
  }

  lockAndHideSheetScript(databaseSheet);
}

function arraysEqual(arr1, arr2) {
  if (arr1.length !== arr2.length) return false;
  for (var i = 0; i < arr1.length; i++) {
    if (arr1[i] !== arr2[i]) return false;
  }
  return true;
}

function getOrCreateBranchFolderScript(parentFolder, branchName) {
  var folders = parentFolder.getFoldersByName(branchName);
  if (folders.hasNext()) {
    var folder = folders.next();
    Logger.log("Using existing branch folder: " + folder.getName());
    return folder;
  } else {
    var newFolder = parentFolder.createFolder(branchName);
    Logger.log("Created new branch folder: " + newFolder.getName());
    return newFolder;
  }
}

function getOrCreateDatabaseFileScript(branchFolder, branchName, year) {
  var fileName = "Data.Restia " + branchName + " " + year;
  
  var files = branchFolder.getFilesByName(fileName);
  if (files.hasNext()) {
    return SpreadsheetApp.open(files.next());
  } else {
    Logger.log("Creating new database file: " + fileName);
    var newSpreadsheet = SpreadsheetApp.create(fileName);
    var file = DriveApp.getFileById(newSpreadsheet.getId());
    branchFolder.addFile(file);
    DriveApp.getRootFolder().removeFile(file);
    Logger.log("Created and moved database file: " + fileName);
    return newSpreadsheet;
  }
}

function getOrCreateMonthlySheetScript(ss, monthName) {
  var sheet = ss.getSheetByName(monthName);
  if (!sheet) {
    Logger.log("Creating new sheet: " + monthName);
    sheet = ss.insertSheet(monthName);
    Utilities.sleep(1000);
  } else {
    Logger.log("Using existing sheet: " + monthName);
  }
  return sheet;
}

function columnToLetterScript(columnNumber) {
  var columnLetter = '';
  var temp;
  while (columnNumber > 0) {
    temp = (columnNumber - 1) % 26;
    columnLetter = String.fromCharCode(temp + 65) + columnLetter;
    columnNumber = (columnNumber - temp - 1) / 26;
  }
  return columnLetter;
}

function lockAndHideSheetScript(sheet) {
  sheet.hideSheet();
  var protection = sheet.protect().setDescription('Měsíční data');
  protection.removeEditors(protection.getEditors());
  protection.addEditor(Session.getEffectiveUser().getEmail());
}

function clearRestiaCSVSheetScript() {
  var spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = spreadsheet.getSheetByName("RestiaCSV");
  if (sheet) {
    var range = sheet.getDataRange();
    range.clearContent();
    Logger.log("Content of sheet 'RestiaCSV' has been cleared.");
  } else {
    Logger.log("Sheet 'RestiaCSV' does not exist.");
  }
}

function clearCellsBasedOnBackgroundColorScript() {
  var spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = spreadsheet.getSheetByName("Report");
  var range = sheet.getDataRange();
  var backgrounds = range.getBackgrounds();

  for (var i = 0; i < backgrounds.length; i++) {
    for (var j = 0; j < backgrounds[i].length; j++) {
      if (backgrounds[i][j] == '#c9daf8') {
        sheet.getRange(i + 1, j + 1).clearContent();
      }
    }
  }
}

function updateDateCellScript() {
  var spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = spreadsheet.getSheetByName("Report");
  var cell = sheet.getRange("D1");
  var now = new Date();
  if (now.getHours() < 5) {
    now.setDate(now.getDate() - 1);
  }
  var formattedDate = ('0' + now.getDate()).slice(-2) + '-' + ('0' + (now.getMonth() + 1)).slice(-2) + '-' + now.getFullYear();
  cell.setValue(formattedDate);
}

function copyScheduleToReportWithValidationScript() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var scheduleSheet = ss.getSheetByName("Směny");
  var reportSheet = ss.getSheetByName("Report");

  var dayOfWeek = reportSheet.getRange("B3").getValue();
  var days = scheduleSheet.getRange("B2:H2").getValues()[0];
  var columnIndex = days.indexOf(dayOfWeek) + 2;

  if (columnIndex >= 2) {
    var scheduleData = scheduleSheet.getRange(3, columnIndex, scheduleSheet.getLastRow() - 2).getValues();

    var hrSheet = ss.getSheetByName("HR");
    if (hrSheet) {
      var validNames = hrSheet.getRange("B5:B" + hrSheet.getLastRow()).getValues().flat().filter(name => typeof name === 'string' && name !== '');

      var additionalNamesRange = hrSheet.getRange("C5:C" + hrSheet.getLastRow());
      var additionalNames = additionalNamesRange.getValues().flat().filter(name => typeof name === 'string' && name !== '');
      validNames = validNames.concat(additionalNames);

      function findFirstEmptyRow(range) {
        var values = range.getValues();
        for (var row = 0; row < values.length; row++) {
          if (values[row][0] === "") {
            return row + range.getRow();
          }
        }
        return -1;
      }

      var instoreRange = reportSheet.getRange('B12:D21');
      var courierRange = reportSheet.getRange('B25:D31');

      for (var i = 0; i < scheduleData.length; i++) {
        var cellData = scheduleData[i][0];
        var times = cellData.match(/(\d{1,2}:\d{2}) - (\d{1,2}:\d{2})/);
        if (times) {
          var nameValue = scheduleSheet.getRange('A' + (i + 3)).getValue();
          if (validNames.includes(nameValue)) {
            if (cellData.includes("Instor")) {
              var instoreRow = findFirstEmptyRow(instoreRange);
              if (instoreRow === -1) {
                instoreRow = findFirstEmptyRow(courierRange);
              }
              if (instoreRow !== -1) {
                reportSheet.getRange('B' + instoreRow).setValue(nameValue);
                reportSheet.getRange('C' + instoreRow).setValue(times[1]);
                reportSheet.getRange('D' + instoreRow).setValue(times[2]);
              }
            } else if (cellData.includes("Kurýr")) {
              var courierRow = findFirstEmptyRow(courierRange);
              if (courierRow === -1) {
                courierRow = findFirstEmptyRow(instoreRange);
              }
              if (courierRow !== -1) {
                reportSheet.getRange('B' + courierRow).setValue(nameValue);
                reportSheet.getRange('C' + courierRow).setValue(times[1]);
                reportSheet.getRange('D' + courierRow).setValue(times[2]);
              }
            }
          }
        }
      }
    }
  }

  updateDropdownsGandIScript();
  checkForDuplicatesAndUpdateHighlightScript();
}

function updateDropdownsGandIScript() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var reportSheet = ss.getSheetByName("Report");

  var dropdownValues = reportSheet.getRange("B12:B21").getValues().flat().filter(name => typeof name === 'string' && name !== '');

  var dropdownRule = SpreadsheetApp.newDataValidation().requireValueInList(dropdownValues).setAllowInvalid(true).build();

  reportSheet.getRange("G11:I13").setDataValidation(dropdownRule);
  reportSheet.getRange("G19:I21").setDataValidation(dropdownRule);
}

function checkForDuplicatesAndUpdateHighlightScript() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var reportSheet = ss.getSheetByName("Report");

  var instoreRange = reportSheet.getRange("B12:B21");
  var courierRange = reportSheet.getRange("B25:B31");

  var instoreValues = instoreRange.getValues().flat();
  var courierValues = courierRange.getValues().flat();

  checkAndRemoveIntraRangeDuplicatesScript(instoreRange, instoreValues);
  checkAndRemoveIntraRangeDuplicatesScript(courierRange, courierValues);

  checkAndHighlightInterRangeDuplicatesScript(instoreRange, instoreValues, courierRange, courierValues);
}

function checkAndRemoveIntraRangeDuplicatesScript(range, values) {
  var uniqueValues = new Set();
  values.forEach((value, i) => {
    if (value && uniqueValues.has(value.toLowerCase())) {
      range.getCell(i + 1, 1).setValue('');
      range.getCell(i + 1, 1).setBackground('#c9daf8');
    } else {
      uniqueValues.add(value.toLowerCase());
    }
  });
}

function checkAndHighlightInterRangeDuplicatesScript(instoreRange, instoreValues, courierRange, courierValues) {
  var duplicates = [];

  instoreValues.forEach((instoreValue, i) => {
    if (instoreValue && courierValues.some(courierValue => courierValue.toLowerCase() === instoreValue.toLowerCase())) {
      duplicates.push(instoreRange.getCell(i + 1, 1));
    }
  });

  courierValues.forEach((courierValue, i) => {
    if (courierValue && instoreValues.some(instoreValue => instoreValue.toLowerCase() === courierValue.toLowerCase())) {
      duplicates.push(courierRange.getCell(i + 1, 1));
    }
  });

  instoreRange.setBackground('#c9daf8');
  courierRange.setBackground('#c9daf8');

  duplicates.forEach(cell => {
    cell.setBackground('#E6E6FA');
  });
}
