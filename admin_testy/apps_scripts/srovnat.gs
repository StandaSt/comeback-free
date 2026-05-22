function srovnatMzdy_A_AD() {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('Mzdy');

  srovnatBlokPresTemp_(sheet, 5, 57);     // Instore
  srovnatBlokPresTemp_(sheet, 75, 193);   // Kurýři
}

function srovnatBlokPresTemp_(sheet, startRow, endRow) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var startCol = 1;   // A
  var numCols = 30;   // A až AD
  var nameCol = 2;    // B

  var tempName = '__TEMP_SORT_MZDY__';
  var temp = ss.getSheetByName(tempName);

  if (!temp) {
    temp = ss.insertSheet(tempName);
    temp.hideSheet();
  }

  temp.clear();

  var filledRows = [];
  var emptyRows = [];

  for (var r = startRow; r <= endRow; r++) {
    var name = sheet.getRange(r, nameCol).getValue();

    if (name !== '' && name !== null) {
      filledRows.push(r);
    } else {
      emptyRows.push(r);
    }
  }

  var order = filledRows.concat(emptyRows);

  for (var i = 0; i < order.length; i++) {
    var source = sheet.getRange(order[i], startCol, 1, numCols);
    var target = temp.getRange(i + 1, startCol, 1, numCols);
    source.copyTo(target, {contentsOnly: false});
  }

  var targetRange = sheet.getRange(startRow, startCol, endRow - startRow + 1, numCols);
  temp.getRange(1, startCol, order.length, numCols)
      .copyTo(targetRange, {contentsOnly: false});

  temp.clear();
}