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

    // Nastavení hodnoty buňky P1 v novém listu
    const originalDateInP1 = originalSheet.getRange("X1").getValue();
    newSheet.getRange("X1").setValue(originalDateInP1);
  } else {
    throw new Error("List 'Mzdy' nebyl nalezen.");
  }

  // Uložení původního formátu buňky X1
  const originalFormat = originalSheet.getRange("X1").getNumberFormat();

  // Nastavení hodnoty X1 na první den aktuálního měsíce bez časové složky
  const firstDayOfCurrentMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
  originalSheet.getRange("X1").setValue(firstDayOfCurrentMonth); // Nastavíme pouze datum

  // Obnovení původního formátu buňky P1
  originalSheet.getRange("X1").setNumberFormat(originalFormat);
}
