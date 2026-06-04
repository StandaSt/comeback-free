function transferDataToControlSheet() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var controlSheet = ss.getSheetByName("Kontrola");

  // Vymazání oblastí ve velkých dávkách
  controlSheet.getRangeList([
    "B10:D30", "F10:I30", "M2:M7", "M9:M11", "M13:M18", "M24", "J21:M21", 
    "J24:M24", "J27:M27", "L27", "L30:M30", "F2", "I2", "J9"
  ]).clearContent();

  // Získání dat z listu Kontrola
  var startDate = new Date(controlSheet.getRange("C1").getValue());
  var endDate = new Date(controlSheet.getRange("E1").getValue());

  if (isNaN(startDate.getTime()) || isNaN(endDate.getTime()) || startDate > endDate) {
    Logger.log("Invalid date range.");
    return;
  }

  // Seznam měsíců mezi startDate a endDate
  var current = new Date(startDate.getFullYear(), startDate.getMonth(), 1);
  var sheetNames = [];
  var monthNames = ["January", "February", "March", "April", "May", "June", 
                    "July", "August", "September", "October", "November", "December"];

  while (current <= endDate) {
    sheetNames.push(monthNames[current.getMonth()] + " " + current.getFullYear());
    current.setMonth(current.getMonth() + 1);
    current.setDate(1); // Nastavení dne na první den nového měsíce
  }

  // Najít soubor s databází pro odpovídající rok
  var scriptFile = DriveApp.getFileById(ss.getId());
  var parentFolder = scriptFile.getParents().next();
  var reportSheet = ss.getSheetByName("Report");
  var branchName = reportSheet.getRange("B1").getValue(); // Získání názvu pobočky z buňky B1
  var year = startDate.getFullYear();
  var databaseFile = getDatabaseFile(parentFolder, branchName, year);

  if (!databaseFile) {
    Logger.log("Database file not found for year: " + year);
    return;
  }

  var dbSpreadsheet = SpreadsheetApp.open(databaseFile);

  // Inicializace proměnných pro součty a průměry
  var sumS = 0, sumT = 0, sumU = 0, sumV = 0, sumW = 0, sumX = 0;
  var sumY = 0, sumZ = 0, sumAA = 0, sumAB = 0, sumAC = 0, sumAE = 0, sumAF = 0;
  var sumAG = 0, sumAH = 0, sumAI = 0, sumAJ = 0, sumAK = 0, sumAL = 0;
  var sumAM = 0, sumAN = 0, sumAO = 0, sumAP = 0, sumAQ = 0, sumAR = 0, sumAT = 0;
  var totalSecondsAM = 0, countAM = 0, totalAS = 0, countAS = 0;
  var totalAU = 0, countAU = 0, totalAV = 0, countAV = 0, sumAD = 0, countAD = 0;

  var nameDaysCount = {};
  var nameTotalMinutes = {};
  var nameDaysCountH = {};
  var nameTotalMinutesH = {};
  var nameSumM = {};

  var uniqueNames = {}; // Pro ukládání součtů hodnot ze sloupce G pro unikátní jména

  // Procházení vybraných listů z databázového souboru
  sheetNames.forEach(function(sheetName) {
    var sheet = dbSpreadsheet.getSheetByName(sheetName);
    if (!sheet) {
      Logger.log("Sheet not found: " + sheetName);
      return;
    }

    Logger.log("Processing sheet: " + sheetName);

    // Načtení všech potřebných dat najednou
    var dataRange = sheet.getRange(2, 1, sheet.getLastRow() - 1, 50).getValues();
    
    dataRange.forEach(function(row, rowIndex) {
      var rawDate = row[0];
      var date;

      if (typeof rawDate === 'string') {
        var dateParts = rawDate.split(".");
        if (dateParts.length === 3) {
          date = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
        } else {
          Logger.log("Invalid date format in row " + (rowIndex + 2) + ": " + rawDate);
          return;
        }
      } else if (rawDate instanceof Date) {
        date = rawDate;
      } else {
        Logger.log("Invalid date found in row " + (rowIndex + 2) + ": " + rawDate);
        return;
      }

      if (isNaN(date.getTime())) {
        Logger.log("Invalid date found in row " + (rowIndex + 2) + ": " + rawDate);
        return;
      }

      var name = row[2];
      var hoursMinutes = row[6];
      var hoursMinutesH = row[11];
      var valueM = row[12];
      var nameFromH = row[7];
      var valueAT = row[45]; // Sloupec AT
      var valueAD = row[29]; // AD je v 30. sloupci (index 29)

      if (date >= startDate && date <= endDate) {
        Logger.log("Processing row " + (rowIndex + 2) + ": " + row);

        if (name) {
          if (!nameDaysCount[name]) {
            nameDaysCount[name] = new Set();
            uniqueNames[name] = 0;
            nameTotalMinutes[name] = 0;
          }
          nameDaysCount[name].add(date.toDateString());
          if (typeof hoursMinutes === 'string' && hoursMinutes.includes(':')) {
            var timeParts = hoursMinutes.split(':');
            var hoursPart = parseInt(timeParts[0], 10);
            var minutesPart = parseInt(timeParts[1], 10);
            nameTotalMinutes[name] += (hoursPart * 60) + minutesPart;
            Logger.log("Added time for " + name + ": " + hoursPart + " hours and " + minutesPart + " minutes.");
          }
        } else {
          Logger.log("Name not found or invalid for row " + (rowIndex + 2));
        }

        if (nameFromH) {
          if (!nameDaysCountH[nameFromH]) {
            nameDaysCountH[nameFromH] = new Set();
            nameTotalMinutesH[nameFromH] = 0;
            nameSumM[nameFromH] = 0;
          }
          nameDaysCountH[nameFromH].add(date.toDateString());
          if (typeof hoursMinutesH === 'string' && hoursMinutesH.includes(':')) {
            var timePartsH = hoursMinutesH.split(':');
            var hoursPartH = parseInt(timePartsH[0], 10);
            var minutesPartH = parseInt(timePartsH[1], 10);
            nameTotalMinutesH[nameFromH] += (hoursPartH * 60) + minutesPartH;
            Logger.log("Added time for " + nameFromH + ": " + hoursPartH + " hours and " + minutesPartH + " minutes.");
          }
          if (typeof valueM === 'number') {
            nameSumM[nameFromH] += valueM;
            Logger.log("Added value for " + nameFromH + ": " + valueM);
          }
        } else {
          Logger.log("nameFromH not found or invalid for row " + (rowIndex + 2));
        }

        // Logování pro součty tržeb
        sumS += (row[18] || 0);
        sumT += (row[19] || 0);
        sumU += (row[20] || 0);
        sumV += (row[21] || 0);
        sumW += (row[22] || 0);
        sumX += (row[23] || 0);
        sumY += (row[24] || 0);
        sumZ += (row[25] || 0);
        sumAA += (row[26] || 0);
        sumAB += (row[27] || 0); // Zápis hodnoty pro I2
        sumAC += (row[28] || 0); // Zápis hodnoty pro J9
        sumAE += (row[30] || 0);
        sumAF += (row[31] || 0);
        sumAG += (row[32] || 0);
        sumAH += (row[33] || 0);
        sumAI += (row[34] || 0);
        sumAJ += (row[35] || 0);
        sumAK += (row[36] || 0);
        sumAL += (row[37] || 0);
        sumAN += (row[39] || 0);
        sumAO += (row[40] || 0);
        sumAP += (row[41] || 0);
        sumAQ += (row[42] || 0);
        sumAR += (row[43] || 0);
        sumAT += (valueAT || 0); // Součet sloupce AT

        if (typeof valueAD === 'number') {
          sumAD += valueAD;
          countAD++;
          Logger.log("Value from column AD added: " + valueAD);
        }

        if (row[38] && typeof row[38] === 'string') {
          var match = row[38].match(/(\d+)\s*min\s*(\d+)\s*s/);
          if (match) {
            var minutes = parseInt(match[1], 10);
            var seconds = parseInt(match[2], 10);
            var totalSeconds = minutes * 60 + seconds;
            totalSecondsAM += totalSeconds;
            countAM++;
          }
        }

        if (typeof row[44] === 'number') {
          totalAS += row[44];
          countAS++;
        }
        if (typeof row[46] === 'number') {
          totalAU += row[46];
          countAU++;
        }
        if (typeof row[47] === 'number') {
          totalAV += row[47];
          countAV++;
        }
      } else {
        Logger.log("Date out of range for row " + (rowIndex + 2) + ": " + date);
      }
    });
  });

  var rowsBtoD = [];
  for (var name in uniqueNames) {
    if (uniqueNames.hasOwnProperty(name)) {
      var totalMinutes = nameTotalMinutes[name];
      var hours = Math.floor(totalMinutes / 60);
      var minutes = totalMinutes % 60;
      rowsBtoD.push([name, nameDaysCount[name] ? nameDaysCount[name].size : 0, hours + ":" + (minutes < 10 ? "0" : "") + minutes]);
    }
  }
  Logger.log("Rows to write to B10:D30: " + JSON.stringify(rowsBtoD));
  if (rowsBtoD.length > 0) {
    controlSheet.getRange("B10:D30").setNumberFormat('@STRING@');  // Nastaví formát buňky na text
    controlSheet.getRange(10, 2, rowsBtoD.length, 3).setValues(rowsBtoD);
  }

  var rowsFtoI = [];
  for (var nameFromH in nameDaysCountH) {
    if (nameDaysCountH.hasOwnProperty(nameFromH)) {
      var totalMinutesH = nameTotalMinutesH[nameFromH];
      var hoursH = Math.floor(totalMinutesH / 60);
      var minutesH = totalMinutesH % 60;
      rowsFtoI.push([nameFromH, nameDaysCountH[nameFromH].size, hoursH + ":" + (minutesH < 10 ? "0" : "") + minutesH, nameSumM[nameFromH]]);
    }
  }
  Logger.log("Rows to write to F10:I30: " + JSON.stringify(rowsFtoI));
  if (rowsFtoI.length > 0) {
    controlSheet.getRange("F10:I30").setNumberFormat('@STRING@');  // Nastaví formát buňky na text
    controlSheet.getRange(10, 6, rowsFtoI.length, 4).setValues(rowsFtoI);
  }

  controlSheet.getRange("M2").setValue(sumS);
  controlSheet.getRange("M3").setValue(sumT);
  controlSheet.getRange("M4").setValue(sumU);
  controlSheet.getRange("M5").setValue(sumV);
  controlSheet.getRange("M6").setValue(sumW);
  controlSheet.getRange("M7").setValue(sumX);
  controlSheet.getRange("M9").setValue(sumY);
  controlSheet.getRange("M10").setValue(sumZ);
  controlSheet.getRange("M11").setValue(sumAA);
  controlSheet.getRange("I2").setValue(sumAB); 
  controlSheet.getRange("J9").setValue(sumAC); 
  controlSheet.getRange("M13").setValue(sumAE);
  controlSheet.getRange("M14").setValue(sumAF);
  controlSheet.getRange("M15").setValue(sumAG);
  controlSheet.getRange("M16").setValue(sumAH);
  controlSheet.getRange("M17").setValue(sumAI);
  controlSheet.getRange("J21").setValue(sumAJ);
  controlSheet.getRange("K21").setValue(sumAK);
  controlSheet.getRange("L21").setValue(sumAL);
  controlSheet.getRange("J24").setValue(sumAN);
  controlSheet.getRange("K24").setValue(sumAO);
  controlSheet.getRange("L24").setValue(sumAP);
  controlSheet.getRange("M24").setValue(sumAQ);
  controlSheet.getRange("J27").setValue(sumAR);
  controlSheet.getRange("M27").setValue(sumAT);

  if (countAM > 0) {
    var avgTotalSecondsAM = totalSecondsAM / countAM;
    var avgMinutesAM = Math.floor(avgTotalSecondsAM / 60);
    var avgSecondsAM = Math.round(avgTotalSecondsAM % 60);
    controlSheet.getRange("M21").setValue(avgMinutesAM + " min " + (avgSecondsAM < 10 ? "0" : "") + avgSecondsAM + " s");
  } else {
    controlSheet.getRange("M21").setValue("0 min 00 s");
  }

  if (countAS > 0) controlSheet.getRange("L27").setValue(totalAS / countAS);
  if (countAU > 0) controlSheet.getRange("L30").setValue(totalAU / countAU);
  if (countAV > 0) controlSheet.getRange("M30").setValue(totalAV / countAV);

  if (countAD > 0) {
    var averageAD = sumAD / countAD;
    Logger.log("Average for column AD to be written to F2: " + averageAD);
    controlSheet.getRange("F2").setValue(averageAD);
  } else {
    controlSheet.getRange("F2").setValue(0);
    Logger.log("No valid data for column AD, writing 0 to F2.");
  }
}

function getDatabaseFile(parentFolder, branchName, year) {
  var fileName = "Databaze " + branchName + " " + year;
  var files = parentFolder.getFilesByName(fileName);
  if (files.hasNext()) {
    return files.next();
  }
  return null;
}
