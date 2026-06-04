function calculateAndWriteValue() {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('Report');
  var hrSheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('HR');
  
  var b12 = sheet.getRange('B12').getValue();
  var c12 = sheet.getRange('C12').getDisplayValue();
  var d12 = sheet.getRange('D12').getDisplayValue();
  var e12 = sheet.getRange('E12').getDisplayValue();
  
  if (!b12 || !d12 || d12 == 0) {
    sheet.getRange('A12').setValue(0);
    return;
  }
  
  var c12Time = parseTime(c12);
  var d12Time = parseTime(d12);
  var e12Time = parseTime(e12);
  
  var timeDiff = (d12Time - c12Time - e12Time);
  if (d12Time >= parseTime("00:00") && d12Time <= parseTime("06:00")) {
    timeDiff += 1;
  }
  
  var hrValues = hrSheet.getRange('A:W').getValues();
  var rate = 0;
  for (var i = 0; i < hrValues.length; i++) {
    if (hrValues[i][0] == b12) {
      rate = 0;
      break;
    } else if (hrValues[i][1] == b12) {
      rate = hrValues[i][22];
      break;
    }
  }
  
  var result = timeDiff * rate;
  sheet.getRange('A12').setValue(result);
}

function parseTime(timeStr) {
  var parts = timeStr.split(":");
  return parseFloat(parts[0]) + parseFloat(parts[1]) / 60;
}
