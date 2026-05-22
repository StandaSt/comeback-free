function debugOnly_SelectedName_Prosinec2025() {
  var TARGET_NAME = "Ženíšek Lukáš";

  // natvrdo: prosinec 2025
  var monthName = "December";
  var year = 2025;

  var rootFolderId = "19k1tRgQT6xYPVbuVr5pkywQ6QwAPJd4O";
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var mzdySheet = ss.getSheetByName("Mzdy");
  var seznamPobocekSheet = ss.getSheetByName("Seznam poboček");
  if (!mzdySheet || !seznamPobocekSheet) throw new Error('Nelze najít list "Mzdy" nebo "Seznam poboček".');

  // stejné sloupce jako produkce (jen pro informaci v logu)
  var COL_HOURS = 21; // U
  var COL_SUM = 19;   // S

  // načti jména a řádky stejně jako produkční skript
  var namesAndRows = getNamesFromMzdySheet(mzdySheet);
  var namesAndRowsForK = getNamesFromMzdySheetForK(mzdySheet);

  // najdi všechny řádky v Mzdy, které se považují za "TARGET" (stejná logika = namesAreEqual)
  var targetMzdyRowsHours = namesAndRows.filter(x => namesAreEqual(x.name, TARGET_NAME));
  var targetMzdyRowsSum = namesAndRowsForK.filter(x => namesAreEqual(x.name, TARGET_NAME));

  if (targetMzdyRowsHours.length === 0) {
    Logger.log("Nenalezeno v Mzdy (hodiny) pro: %s", TARGET_NAME);
    return;
  }

  Logger.log("=== MZDY řádky pro '%s' (hodiny do U=%s) ===", TARGET_NAME, COL_HOURS);
  targetMzdyRowsHours.forEach(x => Logger.log("row %s | '%s'", x.row, x.name));

  Logger.log("=== MZDY řádky pro '%s' (S-součet do S=%s) ===", TARGET_NAME, COL_SUM);
  if (targetMzdyRowsSum.length === 0) Logger.log("(žádné)");
  else targetMzdyRowsSum.forEach(x => Logger.log("row %s | '%s'", x.row, x.name));

  // vytvoř produkční klíče pro TARGET řádky (pozor: může jich být víc, např. kurýr + instore)
  var targetKeysHours = {};
  targetMzdyRowsHours.forEach(x => {
    var k = removeAccents(x.name.toString().trim().toLowerCase());
    targetKeysHours[k] = { name: x.name, row: x.row, totalMin: 0 };
  });

  var targetKeysSum = {};
  targetMzdyRowsSum.forEach(x => {
    var k = removeAccents(x.name.toString().trim().toLowerCase());
    targetKeysSum[k] = { name: x.name, row: x.row, totalM: 0 };
  });

  // pobočky
  var branches = seznamPobocekSheet.getRange("A2:A").getValues().flat().filter(Boolean);

  // projdi databáze a LOGUJ pouze přičtení, která by šla do TARGET řádků v Mzdy
  branches.forEach(branchName => {
    var databaseFile = getDatabaseFileInBranchFolder(rootFolderId, branchName, year);
    if (!databaseFile) return;

    var dbSheetName = monthName + " " + year;
    var dbSheet = SpreadsheetApp.open(databaseFile).getSheetByName(dbSheetName);
    if (!dbSheet) return;

    var lastRow = dbSheet.getLastRow();
    if (lastRow < 2) return;

    var data = dbSheet.getRange(2, 1, lastRow - 1, 50).getValues();

    data.forEach((row, i) => {
      var dbRow = i + 2;

      var nameC = row[2], hoursG = row[6], valM = row[12], valN = row[13];
      var nameH = row[7], hoursL = row[11];

      // C+G: přičtení do TARGET (jen pokud DB jméno se shoduje s konkrétním TARGET řádkem v Mzdy)
      if (nameC) {
        targetMzdyRowsHours.forEach(t => {
          if (namesAreEqual(nameC, t.name)) {
            var key = removeAccents(t.name.toString().trim().toLowerCase());
            var add = parseTimeToMinutes(hoursG);
            targetKeysHours[key].totalMin += add;

            Logger.log(
              "[%s | %s | %s | row %s] C+G -> MZDY row %s ('%s') | DBname='%s' raw='%s' +%s min | total=%s min",
              branchName, databaseFile.getName(), dbSheetName, dbRow,
              t.row, t.name, nameC, hoursG, add, targetKeysHours[key].totalMin
            );
          }
        });

        // M při N=TRUE (C): produkčně se klíč tvoří z DB jména -> tady logujeme jen když to spadne do TARGET sum řádků
        if ((valN === true || valN === "TRUE") && typeof valM === "number" && targetMzdyRowsSum.length > 0) {
          targetMzdyRowsSum.forEach(t => {
            // v produkci se kontroluje hasOwnProperty(key2) kde key2 je z DB jména,
            // takže musíme udělat stejný klíč a porovnat s klíčem TARGET řádku v Mzdy.
            var keyMzdy = removeAccents(t.name.toString().trim().toLowerCase());
            var keyDb = removeAccents(nameC.toString().trim().toLowerCase());
            if (keyDb === keyMzdy) {
              targetKeysSum[keyMzdy].totalM += valM;
              Logger.log(
                "[%s | %s | %s | row %s] M (N=TRUE) via C -> MZDY row %s ('%s') | DBname='%s' +%s | totalM=%s",
                branchName, databaseFile.getName(), dbSheetName, dbRow,
                t.row, t.name, nameC, valM, targetKeysSum[keyMzdy].totalM
              );
            }
          });
        }
      }

      // H+L (mimo výrobu)
      if (branchName.toLowerCase() !== "výroba" && nameH) {
        targetMzdyRowsHours.forEach(t => {
          if (namesAreEqual(nameH, t.name)) {
            var keyH = removeAccents(t.name.toString().trim().toLowerCase());
            var addL = parseTimeToMinutes(hoursL);
            targetKeysHours[keyH].totalMin += addL;

            Logger.log(
              "[%s | %s | %s | row %s] H+L -> MZDY row %s ('%s') | DBname='%s' raw='%s' +%s min | total=%s min",
              branchName, databaseFile.getName(), dbSheetName, dbRow,
              t.row, t.name, nameH, hoursL, addL, targetKeysHours[keyH].totalMin
            );
          }
        });

        // M při N=TRUE (H) – stejné pravidlo jako výše (klíč z DB jména)
        if ((valN === true || valN === "TRUE") && typeof valM === "number" && targetMzdyRowsSum.length > 0) {
          targetMzdyRowsSum.forEach(t => {
            var keyMzdy = removeAccents(t.name.toString().trim().toLowerCase());
            var keyDb = removeAccents(nameH.toString().trim().toLowerCase());
            if (keyDb === keyMzdy) {
              targetKeysSum[keyMzdy].totalM += valM;
              Logger.log(
                "[%s | %s | %s | row %s] M (N=TRUE) via H -> MZDY row %s ('%s') | DBname='%s' +%s | totalM=%s",
                branchName, databaseFile.getName(), dbSheetName, dbRow,
                t.row, t.name, nameH, valM, targetKeysSum[keyMzdy].totalM
              );
            }
          });
        }
      }
    });
  });

  // závěr: co by se zapsalo (ale NIC nezapisuje)
  Logger.log("=== SHRNUtÍ (NIC SE NEZAPISUJE) ===");
  Object.keys(targetKeysHours).forEach(k => {
    var rec = targetKeysHours[k];
    var hours = Math.round((rec.totalMin / 60) * 100) / 100;
    Logger.log("Mzdy row %s ('%s') -> U: %s hodin (=%s min) | key='%s'", rec.row, rec.name, hours, rec.totalMin, k);
  });

  Object.keys(targetKeysSum).forEach(k => {
    var rec = targetKeysSum[k];
    Logger.log("Mzdy row %s ('%s') -> S: %s | key='%s'", rec.row, rec.name, rec.totalM, k);
  });
}
