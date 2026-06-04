function onEdit(e) {
  if (!e || !e.range) {
    Logger.log("Edit event is missing required properties.");
    return;
  }

  var sheet = e.range.getSheet();
  var sheetName = sheet.getName();
  var editedRow = e.range.getRow();
  var editedColumn = e.range.getColumn();

  Logger.log("Edited row: " + editedRow + ", Edited column: " + editedColumn);
  
  if (sheetName === 'Report') {
    // Sledujte změny ve sloupcích G a H, které mohou ovlivnit hodnoty v I25:I31
    if ((editedColumn === 7 || editedColumn === 8) && (editedRow >= 25 && editedRow <= 31)) {
      // Funkce updateJ26 odstraněna
    }

    // Kontrola a formátování hodnot ve sloupcích C, D, E
    if ((editedRow >= 12 && editedRow <= 21) || (editedRow >= 25 && editedRow <= 31)) {
      if (editedColumn === 3 || editedColumn === 4 || editedColumn === 5) {
        formatAndSetValue(sheet, editedRow, editedColumn);
      }

      // Kontrola a odstranění neplatných hodnot ve sloupci B
      if (editedColumn === 2) {
        checkValidNamesAndRemoveInvalid(e);  // Přidána validace pro B12:B21 a B25:B31
        optimizedCheckForDuplicatesAndUpdateDropdowns(sheet);
        updateDropdownsGandI(); // Aktualizace dropdownů v G11:I13 a G19:I21
      }
    }

    // Kontrola zaškrtávacích políček a krok zpět, pokud byla provedena jiná akce než zaškrtnutí/odškrtnutí
    if (editedColumn === 8 && (editedRow >= 25 && editedRow <= 31)) {
      var oldValue = e.oldValue;

      // Pokud nová hodnota není TRUE nebo FALSE, obnovíme starou hodnotu jako zaškrtávací políčko
      if (e.value !== 'TRUE' && e.value !== 'FALSE') {
        e.range.insertCheckboxes();
        e.range.setValue(oldValue === 'TRUE');
      }
    }
  }
}

// Kontrola platných jmen a odstranění neplatných z B12:B21 a B25:B31
function checkValidNamesAndRemoveInvalid(e) {
  var sheet = e.range.getSheet();
  var hrSheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName("HR");

  // Načti platná jména ze sloupce D na listu HR
  var validNames = hrSheet.getRange("D5:D169").getValues().flat().filter(name => typeof name === 'string' && name !== '');

  var editedValue = e.value;
  var cell = e.range;

  // Pokud hodnota není v seznamu platných jmen, smažeme ji
  if (editedValue && !validNames.includes(editedValue)) {
    cell.setValue('');  // Vymaže neplatnou hodnotu
    SpreadsheetApp.getActiveSpreadsheet().toast("Zadané jméno není platné a bylo vymazáno.");  // Upozornění pro uživatele
  }
}

function optimizedCheckForDuplicatesAndUpdateDropdowns(sheet) {
  var instoreRange = sheet.getRange("B12:B21");
  var courierRange = sheet.getRange("B25:B31");

  var instoreValues = instoreRange.getValues().flat();
  var courierValues = courierRange.getValues().flat();

  checkAndRemoveIntraRangeDuplicates(instoreRange, instoreValues);
  checkAndRemoveIntraRangeDuplicates(courierRange, courierValues);

  checkAndHighlightInterRangeDuplicates(instoreRange, instoreValues, courierRange, courierValues);

  // Aktualizace dropdownů v G11:I13 a G19:I21
  updateDropdownsGandI();
}

function checkAndRemoveIntraRangeDuplicates(range, values) {
  var uniqueValues = new Set();
  values.forEach((value, i) => {
    if (value && uniqueValues.has(value.toLowerCase())) {
      range.getCell(i + 1, 1).setValue(''); // Smazání duplicitní hodnoty
      range.getCell(i + 1, 1).setBackground('#c9daf8'); // Nastavení zpět na původní barvu
    } else {
      uniqueValues.add(value.toLowerCase());
    }
  });
}

function checkAndHighlightInterRangeDuplicates(instoreRange, instoreValues, courierRange, courierValues) {
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
    cell.setBackground('#E6E6FA'); // Lehký odstín fialové
  });
}

function updateDropdownsGandI() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var reportSheet = ss.getSheetByName("Report");

  // Získání hodnot pro dropdowny z B12:B21
  var dropdownValues = reportSheet.getRange("B12:B21").getValues().flat().filter(name => typeof name === 'string' && name !== '');

  // Vytvoření pravidla pro ověřování dat
  var dropdownRule = SpreadsheetApp.newDataValidation().requireValueInList(dropdownValues).setAllowInvalid(true).build();

  // Nastavení ověřování dat pro sloučené buňky
  reportSheet.getRange("G11:I13").setDataValidation(dropdownRule);
  reportSheet.getRange("G19:I21").setDataValidation(dropdownRule);

  Logger.log("Dropdown seznamy v G11:I13 a G19:I21 byly aktualizovány.");
}

function formatAndSetValue(sheet, row, column) {
  var range = sheet.getRange(row, column);
  var value = range.getValue();
  Logger.log("Original value: " + value);
  var formattedValue = null;

  if (column === 3 || column === 4) {
    formattedValue = formatTimeInput(value);
  } else if (column === 5) {
    formattedValue = formatPauseInput(value);
  }

  if (formattedValue) {
    Logger.log("Formatted value: " + formattedValue);
    range.setValue(formattedValue);
    range.setNumberFormat('@STRING@');
  } else {
    Logger.log("Invalid value format: " + value);
  }
}

function formatTimeInput(input) {
  Logger.log("Formatting time input: " + input);
  var formattedInput = input.toString().trim();
  var parts = formattedInput.split(':');

  if (parts.length === 2) {
    var hours = parseInt(parts[0], 10);
    var minutes = parseInt(parts[1], 10);
    if (!isNaN(hours) && !isNaN(minutes) && hours >= 0 && hours <= 23 && minutes >= 0 && minutes <= 59) {
      return (hours < 10 ? '0' : '') + hours + ':' + (minutes < 10 ? '0' : '') + minutes;
    }
  } else {
    var num = parseInt(formattedInput, 10);
    if (!isNaN(num)) {
      if (num < 100) {
        return num < 24 ? (num < 10 ? '0' : '') + num + ':00' : '00:' + (num < 10 ? '0' : '') + num;
      } else {
        var hours = Math.floor(num / 100);
        var minutes = num % 100;
        if (hours < 24 && minutes < 60) {
          return (hours < 10 ? '0' : '') + hours + ':' + (minutes < 10 ? '0' : '') + minutes;
        }
      }
    }
  }
  return null;
}

function formatPauseInput(input) {
  return formatTimeInput(input);
}

function copyScheduleToReportWithValidation() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var scheduleSheet = ss.getSheetByName("Směny");
  var reportSheet = ss.getSheetByName("Report");

  var dayOfWeek = reportSheet.getRange("B3").getValue(); // Den pro hledání
  var days = scheduleSheet.getRange("B2:H2").getValues()[0]; // Dny v týdnu ve Směnách
  var columnIndex = days.indexOf(dayOfWeek) + 2; // Získání indexu sloupce pro den (+2 protože začínáme od B)

  if (columnIndex >= 2) { // Pokud byl den nalezen
    var scheduleData = scheduleSheet.getRange(3, columnIndex, scheduleSheet.getLastRow() - 2).getValues(); // Získání dat pro den

    // Načíst validní jména z listu HR
    var hrSheet = ss.getSheetByName("HR");
    if (hrSheet) {
      var validNames = hrSheet.getRange("B5:B" + hrSheet.getLastRow()).getValues().flat().filter(name => typeof name === 'string' && name !== '');

      // Přidat jména z druhého rozsahu tabulky
      var additionalNamesRange = hrSheet.getRange("C5:C" + hrSheet.getLastRow());
      var additionalNames = additionalNamesRange.getValues().flat().filter(name => typeof name === 'string' && name !== '');
      validNames = validNames.concat(additionalNames);

      // Funkce pro nalezení prvního prázdného řádku v daném rozsahu
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
        var times = cellData.match(/(\d{1,2}:\d{2}) - (\d{1,2}:\d{2})/); // Regex pro extrakci časů
        if (times) {
          var nameValue = scheduleSheet.getRange('A' + (i + 3)).getValue();
          if (validNames.includes(nameValue)) { // Kontrola, zda je jméno v seznamu pro ověřování dat
            if (cellData.includes("Instor")) { // Pokud data obsahují "Instor"
              var instoreRow = findFirstEmptyRow(instoreRange);
              if (instoreRow === -1) { // Pokud není dostupný prázdný řádek v Instor, zkusíme Kurýr
                instoreRow = findFirstEmptyRow(courierRange);
              }
              if (instoreRow !== -1) {
                Logger.log("Pokus o zápis do B" + instoreRow + ": " + nameValue + ", " + times[1] + ", " + times[2]);
                reportSheet.getRange('B' + instoreRow).setValue(nameValue); // Jméno
                reportSheet.getRange('C' + instoreRow).setValue(times[1]); // Začátek směny
                reportSheet.getRange('D' + instoreRow).setValue(times[2]); // Konec směny
              } else {
                Logger.log("Není dostupný prázdný řádek pro Instor ani Kurýr.");
              }
            } else if (cellData.includes("Kurýr")) { // Pokud data obsahují "Kurýr"
              var courierRow = findFirstEmptyRow(courierRange);
              if (courierRow === -1) { // Pokud není dostupný prázdný řádek v Kurýr, zkusíme Instor
                courierRow = findFirstEmptyRow(instoreRange);
              }
              if (courierRow !== -1) {
                Logger.log("Pokus o zápis do B" + courierRow + ": " + nameValue + ", " + times[1] + ", " + times[2]);
                reportSheet.getRange('B' + courierRow).setValue(nameValue); // Jméno
                reportSheet.getRange('C' + courierRow).setValue(times[1]); // Začátek směny
                reportSheet.getRange('D' + courierRow).setValue(times[2]); // Konec směny
              } else {
                Logger.log("Není dostupný prázdný řádek pro Kurýr ani Instor.");
              }
            }
          } else {
            Logger.log("Jméno " + nameValue + " není v seznamu pro ověřování dat.");
          }
        }
      }

      // Po zapsání dat aktualizujeme dropdowny v G11:I13 a G19:I21
      updateDropdownsGandI();
    } else {
      Logger.log("List s názvem 'HR' nebyl nalezen.");
    }
  } else {
    Logger.log("Den nebyl nalezen.");
  }
}
