function updateBranchList() {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName("Seznam poboček");
  if (!sheet) {
    Logger.log("List 'Seznam poboček' nebyl nalezen.");
    return;
  }

  // Vyčistí list před aktualizací
  sheet.clear();

  // Název složky Google Drive, kde jsou umístěny složky poboček
  var folderId = "19k1tRgQT6xYPVbuVr5pkywQ6QwAPJd4O";
  var folder = DriveApp.getFolderById(folderId);
  var subFolders = folder.getFolders();

  // Vloží záhlaví do buňky A1
  sheet.getRange("A1").setValue("Název střediska");

  var row = 2; // Počáteční řádek pro názvy poboček

  // Prochází všechny podsložky ve složce
  while (subFolders.hasNext()) {
    var subFolder = subFolders.next();
    var branchName = subFolder.getName();

    // Přeskakuje složku "Předloha"
    if (branchName === "Předloha") {
      continue;
    }

    // Zapisuje název pobočky do listu
    sheet.getRange(row, 1).setValue(branchName);
    row++;
  }

  Logger.log("Seznam poboček byl aktualizován.");
}
