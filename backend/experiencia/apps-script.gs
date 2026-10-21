/**
 * Pega este archivo en Extensiones → Apps Script de la hoja de premios.
 * El quiosco envía JSON con Content-Type text/plain para evitar el preflight.
 */
function doPost(e) {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheets()[0];
  var data = JSON.parse(e.postData.contents);
  if (sheet.getLastRow() === 0) {
    sheet.appendRow([
      "fecha", "tablet", "rol", "nombre", "colegio", "whatsapp",
      "puntaje", "premio", "codigo", "consentimiento"
    ]);
  }
  sheet.appendRow([
    data.createdAt || "",
    data.tabletId || "",
    data.rol || "",
    data.nombre || "",
    data.colegio || "",
    data.whatsapp || "",
    data.puntaje || "",
    data.premio || "",
    data.codigo || "",
    data.consentimiento || ""
  ]);
  return ContentService
    .createTextOutput(JSON.stringify({ ok: true }))
    .setMimeType(ContentService.MimeType.JSON);
}
