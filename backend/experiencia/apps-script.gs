/**
 * Pega este archivo en Extensiones → Apps Script de la hoja de premios.
 * El quiosco envía JSON con Content-Type text/plain para evitar el preflight.
 */
function doPost(e) {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheets()[0];
  var data = JSON.parse(e.postData.contents);
  var headers = [
    "fecha", "tablet", "rol", "nombre", "apellido", "email", "colegio", "cargo",
    "whatsapp", "problema", "puntaje", "premio", "premio_id", "codigo", "consentimiento"
  ];
  if (sheet.getLastRow() === 0) {
    sheet.appendRow(headers);
  }
  sheet.appendRow([
    data.createdAt || "",
    data.tabletId || "",
    data.rol || "",
    data.nombre || "",
    data.apellido || "",
    data.email || "",
    data.colegio || "",
    data.cargo || "",
    data.whatsapp || "",
    data.problema || data.reto_principal || "",
    data.puntaje || "",
    data.premio || "",
    data.premio_id || "",
    data.codigo || "",
    data.consentimiento || ""
  ]);
  return ContentService
    .createTextOutput(JSON.stringify({ ok: true }))
    .setMimeType(ContentService.MimeType.JSON);
}
