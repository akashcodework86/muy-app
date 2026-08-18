import { FileBlob, SpreadsheetFile } from "@oai/artifact-tool";

const workbookPath = "outputs/qpr-mis-apr-jun-2026/MUY_QPR_vs_Live_MIS_Apr_Jun_2026.xlsx";
const input = await FileBlob.load(workbookPath);
const workbook = await SpreadsheetFile.importXlsx(input);

const sheets = await workbook.inspect({ kind: "sheet", include: "id,name", maxChars: 4000 });
const q1 = await workbook.inspect({ kind: "region", sheetId: "Q1 Reconciliation", range: "A6:J50", maxChars: 9000, tableMaxRows: 50, tableMaxCols: 10 });
const monthly = await workbook.inspect({ kind: "region", sheetId: "Monthly Detail", range: "A6:O50", maxChars: 9000, tableMaxRows: 12, tableMaxCols: 15 });
const errors = await workbook.inspect({
  kind: "match",
  searchTerm: "#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A",
  options: { useRegex: true, maxResults: 100 },
  maxChars: 5000,
});

console.log("SHEETS");
console.log(sheets.ndjson ?? sheets);
console.log("Q1");
console.log(q1.ndjson ?? q1);
console.log("MONTHLY");
console.log(monthly.ndjson ?? monthly);
console.log("ERROR_SCAN");
console.log(errors.ndjson ?? errors);
