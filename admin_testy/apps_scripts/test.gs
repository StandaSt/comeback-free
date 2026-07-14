function testJednaPobocka() {
  var rootFolderId = '19k1tRgQT6xYPVbuVr5pkywQ6QwAPJd4O';
  var branchName = 'Libuš'; // ← sem vždy napiš jednu pobočku
  var year = 2026;

  try {
    Logger.log('TEST: ' + branchName);

    var root = DriveApp.getFolderById(rootFolderId);

    var folders = root.getFoldersByName(branchName);
    if (!folders.hasNext()) {
      Logger.log('❌ složka neexistuje');
      return;
    }

    var folder = folders.next();

    var fileName = 'Databaze ' + branchName + ' ' + year;
    var files = folder.getFilesByName(fileName);

    if (!files.hasNext()) {
      Logger.log('❌ soubor neexistuje');
      return;
    }

    var file = files.next();
    SpreadsheetApp.openById(file.getId());

    Logger.log('✅ OK');

  } catch (e) {
    Logger.log('❌ CHYBA: ' + e.message);
  }
}
