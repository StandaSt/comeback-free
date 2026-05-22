function sumHoursForNames() {
  var rootFolderId = '19k1tRgQT6xYPVbuVr5pkywQ6QwAPJd4O';
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var mzdySheet = ss.getSheetByName('Mzdy');
  var seznamPobocekSheet = ss.getSheetByName('Seznam poboček');

  var COL_HOURS = 21; // U
  var COL_SUM   = 19; // S

  var MAX_ATTEMPTS = 8;
  var WAIT_MS = 2500;

  if (!mzdySheet || !seznamPobocekSheet) {
    SpreadsheetApp.getUi().alert('Nelze najít list "Mzdy" nebo "Seznam poboček".');
    return;
  }

  var dateCellValue = mzdySheet.getRange('U1').getValue();
  var targetDate = new Date(dateCellValue);

  if (isNaN(targetDate)) {
    SpreadsheetApp.getUi().alert('Neplatné datum v buňce U1 na listu "Mzdy".');
    return;
  }

  var targetMonthIndex = targetDate.getMonth();
  var targetYear = targetDate.getFullYear();

  var englishMonths = [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"
  ];

  var monthName = englishMonths[targetMonthIndex];
  var targetSheetName = monthName + " " + targetYear;

  var namesAndRows = getNamesFromMzdySheet(mzdySheet);
  var namesAndRowsForK = getNamesFromMzdySheetForK(mzdySheet);

  var branchesValues = seznamPobocekSheet.getRange('A2:A').getValues();
  var branches = [];

  for (var i = 0; i < branchesValues.length; i++) {
    if (!branchesValues[i][0]) break;
    branches.push(branchesValues[i][0].toString().trim());
  }

  var nameTotalMinutes = {};
  namesAndRows.forEach(function(item) {
    var key = normalizeNameKey(item.name);
    nameTotalMinutes[key] = 0;
  });

  var nameTotalMValues = {};
  namesAndRowsForK.forEach(function(item) {
    var key = normalizeNameKey(item.name);
    nameTotalMValues[key] = 0;
  });

  var failedBranches = [];

  branches.forEach(function(branchName) {
    var ok = false;

    for (var attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
      try {
        Logger.log('Pobočka: ' + branchName + ' | pokus ' + attempt);

        processBranch_(
          rootFolderId,
          branchName,
          targetYear,
          targetSheetName,
          namesAndRows,
          namesAndRowsForK,
          nameTotalMinutes,
          nameTotalMValues
        );

        ok = true;
        Logger.log('OK: ' + branchName);
        break;

      } catch (e) {
        Logger.log('CHYBA: ' + branchName + ' | pokus ' + attempt + ' | ' + e.message);

        if (attempt < MAX_ATTEMPTS) {
          Utilities.sleep(WAIT_MS);
        }
      }
    }

    if (!ok) {
      failedBranches.push(branchName);
    }
  });

  if (failedBranches.length > 0) {
    SpreadsheetApp.getUi().alert(
      'Výpočet nebyl zapsán. Nepodařilo se načíst pobočky: ' + failedBranches.join(', ')
    );
    return;
  }

  namesAndRows.forEach(function(item) {
    var key = normalizeNameKey(item.name);
    var dec = Math.round((nameTotalMinutes[key] / 60) * 100) / 100;

    var cell = mzdySheet.getRange(item.row, COL_HOURS);
    cell.setNumberFormat("0.00");
    cell.setValue(dec);
  });

  namesAndRowsForK.forEach(function(item) {
    var key = normalizeNameKey(item.name);
    var sumVal = nameTotalMValues[key];

    var cell = mzdySheet.getRange(item.row, COL_SUM);

    if (sumVal > 0) {
      cell.setValue(sumVal);
    } else {
      cell.clearContent();
    }
  });

  SpreadsheetApp.getUi().alert('Výpočet dokončen.');
}


function processBranch_(
  rootFolderId,
  branchName,
  targetYear,
  targetSheetName,
  namesAndRows,
  namesAndRowsForK,
  nameTotalMinutes,
  nameTotalMValues
) {
  var databaseFile = getDatabaseFileInBranchFolder(rootFolderId, branchName, targetYear);

  if (!databaseFile) {
    Logger.log('Soubor nenalezen: ' + branchName);
    return;
  }

  var dbSheet = SpreadsheetApp.open(databaseFile).getSheetByName(targetSheetName);

  if (!dbSheet) {
    Logger.log('List nenalezen: ' + branchName + ' / ' + targetSheetName);
    return;
  }

  var lastRow = dbSheet.getLastRow();

  if (lastRow < 2) {
    Logger.log('Prázdný list: ' + branchName);
    return;
  }

  var data = dbSheet.getRange(2, 1, lastRow - 1, 50).getValues();

  data.forEach(function(row) {
    var nameC = row[2];
    var hoursG = row[6];
    var valM = row[12];
    var valN = row[13];

    if (nameC) {
      namesAndRows.forEach(function(item) {
        if (namesAreEqual(nameC, item.name)) {
          var key = normalizeNameKey(item.name);
          nameTotalMinutes[key] += parseTimeToMinutes(hoursG);
        }
      });

      if ((valN === true || valN === 'TRUE') && typeof valM === 'number') {
        var key2 = normalizeNameKey(nameC);

        if (nameTotalMValues.hasOwnProperty(key2)) {
          nameTotalMValues[key2] += valM;
        }
      }
    }

    if (normalizeNameKey(branchName) !== 'vyroba') {
      var nameH = row[7];
      var hoursL = row[11];

      if (nameH) {
        namesAndRows.forEach(function(item) {
          if (namesAreEqual(nameH, item.name)) {
            var keyH = normalizeNameKey(item.name);
            nameTotalMinutes[keyH] += parseTimeToMinutes(hoursL);
          }
        });

        if ((valN === true || valN === 'TRUE') && typeof valM === 'number') {
          var keyH2 = normalizeNameKey(nameH);

          if (nameTotalMValues.hasOwnProperty(keyH2)) {
            nameTotalMValues[keyH2] += valM;
          }
        }
      }
    }
  });
}


// ——— Pomocné funkce —————————————————————————————————

function namesAreEqual(name1, name2) {
  if (typeof name1 !== 'string' || typeof name2 !== 'string') return false;

  var parts1 = normalizeNameKey(name1).split(' ');
  var parts2 = normalizeNameKey(name2).split(' ');

  return parts1.every(function(p) {
    return parts2.indexOf(p) !== -1;
  }) && parts2.every(function(p) {
    return parts1.indexOf(p) !== -1;
  });
}


function getNamesFromMzdySheet(sheet) {
  var namesAndRows = [];
  var startRows = [5, 73];
  var nonEmp = ["End", "Suma instor"];

  startRows.forEach(function(sr) {
    var empty = 0;
    var r = sr;

    while (empty < 5) {
      var v = sheet.getRange(r, 2).getValue();

      if (v !== '' && v != null) {
        var t = v.toString().trim();

        if (nonEmp.indexOf(t) === -1) {
          namesAndRows.push({ name: t, row: r });
        }

        empty = 0;
      } else {
        empty++;
      }

      r++;
    }
  });

  return namesAndRows;
}


function getNamesFromMzdySheetForK(sheet) {
  var namesAndRows = [];
  var r = 73;
  var empty = 0;
  var nonEmp = ["End", "Suma instor"];

  while (empty < 5) {
    var v = sheet.getRange(r, 2).getValue();

    if (v !== '' && v != null) {
      var t = v.toString().trim();

      if (nonEmp.indexOf(t) === -1) {
        namesAndRows.push({ name: t, row: r });
      }

      empty = 0;
    } else {
      empty++;
    }

    r++;
  }

  return namesAndRows;
}


function normalizeNameKey(str) {
  return removeAccents(str.toString().trim().toLowerCase());
}


function removeAccents(str) {
  return (typeof str === 'string')
    ? str.normalize("NFD").replace(/[\u0300-\u036f]/g, "")
    : str;
}


function getDatabaseFileInBranchFolder(rootFolderId, branchName, year) {
  var root = DriveApp.getFolderById(rootFolderId);

  var folders = root.getFoldersByName(branchName);
  if (!folders.hasNext()) return null;

  var branchFolder = folders.next();

  var fileName = "Databaze " + branchName + " " + year;
  var files = branchFolder.getFilesByName(fileName);

  return files.hasNext() ? files.next() : null;
}


function parseTimeToMinutes(timeValue) {
  if (typeof timeValue === 'string') {
    if (timeValue.startsWith("'")) {
      timeValue = timeValue.substring(1);
    }

    if (timeValue.indexOf(':') !== -1) {
      var p = timeValue.split(':');
      return parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
    }

    return 0;
  }

  if (typeof timeValue === 'number') {
    return timeValue * 24 * 60;
  }

  return 0;
}