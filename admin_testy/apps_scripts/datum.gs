function copySheetEveryMonth() {
  const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  const currentDate = new Date();

  // Získání předchozího měsíce a aktuálního roku
  const previousMonthDate = new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1);
  const monthNames = ["Leden", "Únor", "Březen", "Duben", "Květen", "Červen", "Červenec", "Srpen", "Září", "Říjen", "Listopad", "Prosinec"];
  const previousMonthName = monthNames[previousMonthDate.getMonth()];
  const year = previousMonthDate.getFullYear();

  // Nový název pro zkopírovaný list
  const newSheetName = `Mzdy ${previousMonthName} ${year}`;

  // Zkopírování listu "Mzdy" a pojmenování podle předchozího měsíce
  const originalSheet = spreadsheet.getSheetByName("Mzdy");
  if (originalSheet) {
    const newSheet = originalSheet.copyTo(spreadsheet);
    newSheet.setName(newSheetName);

    // Získání data, které je aktuálně v buňce X1, a nastavení nového data
    const originalDateInX1 = originalSheet.getRange("X1").getValue();
    const nextMonthDate = new Date(originalDateInX1.getFullYear(), originalDateInX1.getMonth() + 1, 1);
    
    // Nastavení hodnoty X1 ve formátu MM/DD/YYYY
    originalSheet.getRange("X1").setValue(Utilities.formatDate(nextMonthDate, Session.getScriptTimeZone(), "MM/dd/yyyy"));
  } else {
    throw new Error("List 'Mzdy' nebyl nalezen.");
  }
}
