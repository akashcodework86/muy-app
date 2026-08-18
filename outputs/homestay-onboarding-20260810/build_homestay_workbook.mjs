import fs from "node:fs/promises";
import { execFileSync } from "node:child_process";
import { SpreadsheetFile, Workbook } from "@oai/artifact-tool";

const outputDir = "C:/xampp/htdocs/muy-app/outputs/homestay-onboarding-20260810";
const mysqlExe = "C:/xampp/mysql/bin/mysql.exe";
const expectedName = "Homestay_Yearwise_Districtwise_Onboarding_with_Mobiles.xlsx";

const legacySql = String.raw`
SELECT JSON_OBJECT(
  'source', 'Legacy ukrbiin_rbi',
  'source_id', ID,
  'application_no', COALESCE(ApplicationNumber, ''),
  'name', COALESCE(FullName, ''),
  'mobile', COALESCE(CAST(MobileNumber AS CHAR), ''),
  'district_raw', COALESCE(FatherName, ''),
  'onboard_date_raw', COALESCE(onboard_date, ''),
  'application_date', COALESCE(DATE_FORMAT(ApplicationDate, '%Y-%m-%d'), ''),
  'self_declaration', COALESCE(self_declaration, ''),
  'status', COALESCE(onboard, ''),
  'business_category', COALESCE(idea, ''),
  'business_detail', TRIM(CONCAT_WS(' | ', NULLIF(idea2, ''), NULLIF(other_idea, '')))
)
FROM codex_hist_rbi.tblapplication
WHERE LOWER(TRIM(onboard)) = 'yes'
  AND (
    LOWER(COALESCE(idea, '')) LIKE '%homestay%'
    OR LOWER(COALESCE(idea2, '')) LIKE '%homestay%'
    OR LOWER(COALESCE(other_idea, '')) LIKE '%homestay%'
  )
ORDER BY ID`;

const phase2Sql = String.raw`
SELECT JSON_OBJECT(
  'source', 'Phase 2 rbiphase2',
  'source_id', a.id,
  'application_no', COALESCE(a.application_no, ''),
  'name', COALESCE(d.applicant_name, ''),
  'mobile', COALESCE(d.phone, ''),
  'district_raw', COALESCE(d.district, ''),
  'onboard_date_raw', COALESCE(DATE_FORMAT(ob.onboarding_date, '%Y-%m-%d'), DATE_FORMAT(oa.onboarded_at, '%Y-%m-%d'), ''),
  'application_date', COALESCE(DATE_FORMAT(a.submission_date, '%Y-%m-%d'), ''),
  'self_declaration', '',
  'status', COALESCE(oa.status, ''),
  'business_category', COALESCE(a.business_category, ''),
  'business_detail', TRIM(CONCAT_WS(' | ', NULLIF(a.product, ''), NULLIF(a.other_product, '')))
)
FROM codex_phase2.rbi_applications a
JOIN codex_phase2.rbi_applicant_details d ON d.application_id = a.id
JOIN codex_phase2.rbi_onboarded_applicants oa ON oa.application_id = a.id
LEFT JOIN codex_phase2.rbi_onboarding_batches ob ON ob.id = oa.onboarding_batch_id
WHERE oa.status IS NOT NULL
  AND oa.status <> ''
  AND a.submission_date >= '2025-04-02'
  AND a.submission_date < '2026-04-02'
  AND (
    LOWER(TRIM(COALESCE(a.business_category, ''))) = 'homestay'
    OR LOWER(TRIM(COALESCE(a.product, ''))) = 'homestay'
    OR LOWER(COALESCE(a.other_product, '')) LIKE '%homestay%'
  )
ORDER BY a.id, oa.onboarded_at`;

function queryJsonLines(sql) {
  const text = execFileSync(mysqlExe, [
    "-u", "root", "--default-character-set=utf8mb4", "-N", "-B", "-e", sql,
  ], { encoding: "utf8", maxBuffer: 64 * 1024 * 1024 });

  return text
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => JSON.parse(line));
}

const districtAliases = new Map([
  ["almora", "Almora"], ["bageshwar", "Bageshwar"], ["chamoli", "Chamoli"],
  ["champawat", "Champawat"], ["dehradun", "Dehradun"], ["doon", "Dehradun"],
  ["haridwar", "Haridwar"], ["hardwar", "Haridwar"], ["nainital", "Nainital"],
  ["pauri", "Pauri Garhwal"], ["pauri garhwal", "Pauri Garhwal"],
  ["pauri_garhwal", "Pauri Garhwal"], ["pithoragarh", "Pithoragarh"],
  ["rudraprayag", "Rudraprayag"], ["tehri", "Tehri Garhwal"],
  ["tehri garhwal", "Tehri Garhwal"], ["tehri_garhwal", "Tehri Garhwal"],
  ["udham singh nagar", "Udham Singh Nagar"], ["udham singh nagr", "Udham Singh Nagar"],
  ["us nagar", "Udham Singh Nagar"], ["u s nagar", "Udham Singh Nagar"],
  ["u.s. nagar", "Udham Singh Nagar"], ["u s n", "Udham Singh Nagar"],
  ["us_nagar", "Udham Singh Nagar"], ["uttarkashi", "Uttarkashi"],
]);

function canonicalDistrict(value) {
  const raw = String(value ?? "").trim();
  return districtAliases.get(raw.toLowerCase()) ?? (raw || "District NA");
}

function parseDate(value) {
  const raw = String(value ?? "").trim();
  if (!raw) return null;
  let year;
  let month;
  let day;
  let match;
  if ((match = raw.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/))) {
    [, year, month, day] = match.map(Number);
  } else if ((match = raw.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/))) {
    month = Number(match[1]); day = Number(match[2]); year = Number(match[3]);
  } else if ((match = raw.match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/))) {
    day = Number(match[1]); month = Number(match[2]); year = Number(match[3]);
  } else {
    return null;
  }
  const date = new Date(Date.UTC(year, month - 1, day));
  return Number.isNaN(date.getTime()) ? null : date;
}

function fiscalYear(date) {
  const year = date.getUTCFullYear();
  return date.getUTCMonth() >= 3 ? `${year}-${String(year + 1).slice(-2)}` : `${year - 1}-${String(year).slice(-2)}`;
}

function normalizeMobile(value) {
  return String(value ?? "").replace(/\D/g, "");
}

function normalizeApplication(value) {
  return String(value ?? "").trim().toLowerCase();
}

function prepareRow(raw) {
  const actualDate = parseDate(raw.onboard_date_raw);
  const isLegacy = raw.source === "Legacy ukrbiin_rbi";
  const selfDeclared = isLegacy && String(raw.self_declaration).trim().toLowerCase() === "yes";
  const fy = isLegacy
    ? (selfDeclared ? "2021-22" : (actualDate ? fiscalYear(actualDate) : "Date NA"))
    : "2025-26";
  const sortDate = selfDeclared ? new Date(Date.UTC(2021, 3, 1)) : (actualDate ?? new Date(Date.UTC(9999, 11, 31)));

  return {
    ...raw,
    application_no: String(raw.application_no ?? "").trim(),
    name: String(raw.name ?? "").trim(),
    mobile: normalizeMobile(raw.mobile),
    district: canonicalDistrict(raw.district_raw),
    onboarding_date: actualDate,
    application_date_typed: parseDate(raw.application_date),
    fy,
    sortDate,
    assignment_basis: selfDeclared
      ? "self_declaration=Yes → FY 2021-22"
      : (actualDate ? "Onboarding date FY" : "Onboarding date missing"),
  };
}

const rawRows = [...queryJsonLines(legacySql), ...queryJsonLines(phase2Sql)].map(prepareRow);
rawRows.sort((a, b) => a.sortDate - b.sortDate || a.source.localeCompare(b.source) || Number(a.source_id) - Number(b.source_id));

const exclusions = [];
const appSeen = new Map();
const appUnique = [];
for (const row of rawRows) {
  const normalized = normalizeApplication(row.application_no);
  const key = normalized || `${row.source}:${row.source_id}`;
  if (appSeen.has(key)) {
    const kept = appSeen.get(key);
    exclusions.push({ excluded: row, kept, reason: "Duplicate application number" });
  } else {
    appSeen.set(key, row);
    appUnique.push(row);
  }
}

const mobileSeen = new Map();
const uniqueRows = [];
for (const row of appUnique) {
  const validMobile = /^\d{10}$/.test(row.mobile);
  const key = validMobile ? row.mobile : `app:${normalizeApplication(row.application_no)}:${row.source}:${row.source_id}`;
  if (mobileSeen.has(key)) {
    const kept = mobileSeen.get(key);
    exclusions.push({ excluded: row, kept, reason: "Duplicate mobile after application-number check" });
  } else {
    mobileSeen.set(key, row);
    uniqueRows.push(row);
  }
}

const districtOrder = [
  "Almora", "Bageshwar", "Chamoli", "Champawat", "Dehradun", "Haridwar", "Nainital",
  "Pauri Garhwal", "Pithoragarh", "Rudraprayag", "Tehri Garhwal", "Udham Singh Nagar", "Uttarkashi",
];
const fyOrder = ["2021-22", "2022-23", "2023-24", "2024-25", "2025-26", "Date NA"];
const districtRank = new Map(districtOrder.map((value, index) => [value, index]));
const fyRank = new Map(fyOrder.map((value, index) => [value, index]));
uniqueRows.sort((a, b) => (districtRank.get(a.district) ?? 99) - (districtRank.get(b.district) ?? 99)
  || (fyRank.get(a.fy) ?? 99) - (fyRank.get(b.fy) ?? 99)
  || a.sortDate - b.sortDate
  || a.name.localeCompare(b.name));

if (uniqueRows.length !== 1005) {
  throw new Error(`Expected 1005 unique rows, found ${uniqueRows.length}`);
}

const workbook = Workbook.create();
const summary = workbook.worksheets.add("Summary");
const details = workbook.worksheets.add("Details");
const excluded = workbook.worksheets.add("Duplicate Exclusions");
const rules = workbook.worksheets.add("Methodology");

for (const sheet of [summary, details, excluded, rules]) {
  sheet.showGridLines = false;
}

const navy = "#17365D";
const teal = "#0F766E";
const paleTeal = "#DDF4F0";
const paleBlue = "#EAF2F8";
const gold = "#F4B183";
const lightGray = "#E7E6E6";
const textGray = "#52606D";

// Summary sheet
summary.getRange("A1:H1").merge();
summary.getRange("A1").values = [["Homestay Onboarding — Year-wise & District-wise"]];
summary.getRange("A1:H1").format = { fill: navy, font: { bold: true, color: "#FFFFFF", size: 16 }, rowHeight: 30, verticalAlignment: "center" };
summary.getRange("A2:H2").merge();
summary.getRange("A2").values = [["Legacy ukrbiin_rbi + Phase 2 only | Phase 3 excluded | Unique by application number, then mobile"]];
summary.getRange("A2:H2").format = { fill: paleBlue, font: { italic: true, color: textGray }, rowHeight: 22 };

summary.getRange("A4:H4").values = [["Unique Total", null, "Legacy Unique", null, "Phase 2 Unique", null, "Date NA", null]];
summary.getRange("A4:H4").format = { fill: teal, font: { bold: true, color: "#FFFFFF" }, horizontalAlignment: "center" };
const detailsLastRow = uniqueRows.length + 3;
summary.getRange("A5:H5").formulas = [[
  `=COUNTA('Details'!$B$4:$B$${detailsLastRow})`, "",
  `=COUNTIF('Details'!$H$4:$H$${detailsLastRow},"Legacy ukrbiin_rbi")`, "",
  `=COUNTIF('Details'!$H$4:$H$${detailsLastRow},"Phase 2 rbiphase2")`, "",
  `=COUNTIF('Details'!$F$4:$F$${detailsLastRow},"Date NA")`, "",
]];
summary.getRange("A5:H5").format = { fill: paleTeal, font: { bold: true, color: navy, size: 14 }, horizontalAlignment: "center", numberFormat: "#,##0", rowHeight: 26 };
summary.getRange("A4:B5").format.borders = { preset: "outside", style: "thin", color: "#9FBAD0" };
summary.getRange("C4:D5").format.borders = { preset: "outside", style: "thin", color: "#9FBAD0" };
summary.getRange("E4:F5").format.borders = { preset: "outside", style: "thin", color: "#9FBAD0" };
summary.getRange("G4:H5").format.borders = { preset: "outside", style: "thin", color: "#9FBAD0" };

summary.getRange("A7:H7").values = [["District", ...fyOrder, "Total"]];
summary.getRange("A7:H7").format = { fill: navy, font: { bold: true, color: "#FFFFFF" }, horizontalAlignment: "center", wrapText: true, rowHeight: 26 };
const summaryRows = districtOrder.map((district, index) => {
  const excelRow = 8 + index;
  return [district,
    ...fyOrder.map((fy, fyIndex) => `=COUNTIFS('Details'!$E$4:$E$${detailsLastRow},$A${excelRow},'Details'!$F$4:$F$${detailsLastRow},${String.fromCharCode(66 + fyIndex)}$7)`),
    `=SUM(B${excelRow}:G${excelRow})`,
  ];
});
summary.getRange(`A8:H${7 + districtOrder.length}`).values = summaryRows.map((row) => [row[0], null, null, null, null, null, null, null]);
summary.getRange(`B8:H${7 + districtOrder.length}`).formulas = summaryRows.map((row) => row.slice(1));
const totalRow = 8 + districtOrder.length;
summary.getRange(`A${totalRow}:H${totalRow}`).values = [["Grand Total", null, null, null, null, null, null, null]];
summary.getRange(`B${totalRow}:H${totalRow}`).formulas = [[...Array.from({ length: 7 }, (_, i) => `=SUM(${String.fromCharCode(66 + i)}8:${String.fromCharCode(66 + i)}${totalRow - 1})`)]];
summary.getRange(`A${totalRow}:H${totalRow}`).format = { fill: gold, font: { bold: true, color: navy }, numberFormat: "#,##0" };
summary.getRange(`A8:H${totalRow}`).format.borders = { preset: "inside", style: "thin", color: "#D9E2F3" };
summary.getRange(`B8:H${totalRow}`).format = { numberFormat: "#,##0", horizontalAlignment: "right" };
summary.getRange(`A7:H${totalRow}`).format.borders = { preset: "outside", style: "thin", color: "#9FBAD0" };
summary.freezePanes.freezeRows(7);
summary.getRange("A:A").format.columnWidth = 22;
summary.getRange("B:H").format.columnWidth = 12;

// Detail sheet
details.getRange("A1:M1").merge();
details.getRange("A1").values = [["Unique Homestay Onboarding Details (with Mobile Numbers)"]];
details.getRange("A1:M1").format = { fill: navy, font: { bold: true, color: "#FFFFFF", size: 15 }, rowHeight: 28 };
details.getRange("A2:M2").merge();
details.getRange("A2").values = [["Unique rule: application number first, then valid 10-digit mobile. FY 2021-22 uses self_declaration=Yes; missing dates remain Date NA."]];
details.getRange("A2:M2").format = { fill: paleBlue, font: { italic: true, color: textGray }, rowHeight: 22 };
const detailHeaders = ["S.No.", "Application Number", "Beneficiary Name", "Mobile Number", "District", "FY", "Onboarding Date", "Source Database", "Source Record ID", "Business Category", "Product / Business Detail", "Self Declaration", "FY Assignment Basis"];
details.getRange("A3:M3").values = [detailHeaders];
details.getRange("A3:M3").format = { fill: teal, font: { bold: true, color: "#FFFFFF" }, horizontalAlignment: "center", wrapText: true, rowHeight: 30 };
const detailValues = uniqueRows.map((row, index) => [
  index + 1,
  row.application_no,
  row.name,
  row.mobile,
  row.district,
  row.fy,
  row.onboarding_date,
  row.source,
  Number(row.source_id),
  String(row.business_category ?? ""),
  String(row.business_detail ?? ""),
  String(row.self_declaration ?? ""),
  row.assignment_basis,
]);
details.getRange(`A4:M${detailsLastRow}`).values = detailValues;
details.getRange(`A4:A${detailsLastRow}`).format.numberFormat = "#,##0";
details.getRange(`B4:B${detailsLastRow}`).format.numberFormat = "@";
details.getRange(`D4:D${detailsLastRow}`).format.numberFormat = "@";
details.getRange(`G4:G${detailsLastRow}`).format.numberFormat = "yyyy-mm-dd";
details.getRange(`I4:I${detailsLastRow}`).format.numberFormat = "0";
details.getRange(`A3:M${detailsLastRow}`).format.borders = { preset: "inside", style: "thin", color: "#E6E6E6" };
details.tables.add(`A3:M${detailsLastRow}`, true, "HomestayDetailsTable").style = "TableStyleMedium2";
details.freezePanes.freezeRows(3);
details.freezePanes.freezeColumns(2);
const detailWidths = [8, 18, 26, 16, 20, 11, 16, 22, 14, 20, 34, 17, 30];
detailWidths.forEach((width, index) => details.getRangeByIndexes(0, index, detailsLastRow, 1).format.columnWidth = width);
details.getRange(`C4:M${detailsLastRow}`).format.verticalAlignment = "center";
details.getRange(`K4:M${detailsLastRow}`).format.wrapText = true;

// Duplicate exclusions sheet
excluded.getRange("A1:L1").merge();
excluded.getRange("A1").values = [["Duplicate Rows Excluded from Unique Count"]];
excluded.getRange("A1:L1").format = { fill: "#9C0006", font: { bold: true, color: "#FFFFFF", size: 15 }, rowHeight: 28 };
excluded.getRange("A2:L2").merge();
excluded.getRange("A2").values = [["Audit trail only. These rows are not included in Summary or Details totals."]];
excluded.getRange("A2:L2").format = { fill: "#FCE4D6", font: { italic: true, color: "#9C0006" }, rowHeight: 22 };
const exclusionHeaders = ["Excluded Source", "Excluded Record ID", "Application Number", "Beneficiary Name", "Mobile", "District", "FY", "Onboarding Date", "Exclusion Reason", "Retained Application", "Retained Name", "Retained Source"];
excluded.getRange("A3:L3").values = [exclusionHeaders];
excluded.getRange("A3:L3").format = { fill: "#C65911", font: { bold: true, color: "#FFFFFF" }, horizontalAlignment: "center", wrapText: true, rowHeight: 30 };
const exclusionValues = exclusions.map(({ excluded: row, kept, reason }) => [
  row.source, Number(row.source_id), row.application_no, row.name, row.mobile, row.district, row.fy,
  row.onboarding_date, reason, kept.application_no, kept.name, kept.source,
]);
const exclusionLastRow = exclusionValues.length + 3;
excluded.getRange(`A4:L${exclusionLastRow}`).values = exclusionValues;
excluded.getRange(`C4:C${exclusionLastRow}`).format.numberFormat = "@";
excluded.getRange(`E4:E${exclusionLastRow}`).format.numberFormat = "@";
excluded.getRange(`H4:H${exclusionLastRow}`).format.numberFormat = "yyyy-mm-dd";
excluded.tables.add(`A3:L${exclusionLastRow}`, true, "DuplicateExclusionsTable").style = "TableStyleMedium9";
excluded.freezePanes.freezeRows(3);
[20, 14, 18, 24, 16, 20, 11, 16, 38, 20, 24, 22].forEach((width, index) => excluded.getRangeByIndexes(0, index, exclusionLastRow, 1).format.columnWidth = width);
excluded.getRange(`I4:I${exclusionLastRow}`).format.wrapText = true;

// Methodology sheet
rules.getRange("A1:C1").merge();
rules.getRange("A1").values = [["Methodology & Source Notes"]];
rules.getRange("A1:C1").format = { fill: navy, font: { bold: true, color: "#FFFFFF", size: 15 }, rowHeight: 28 };
const ruleRows = [
  ["Scope", "Legacy ukrbiin_rbi + Phase 2 rbiphase2", "Phase 3 excluded per user instruction"],
  ["Homestay filter — legacy", "onboard=yes and idea/idea2/other_idea contains Homestay", "Includes combined values such as Restaurant and Hotel/homestay"],
  ["Homestay filter — Phase 2", "Verified rbi_onboarded_applicants plus Homestay category/product", "Submission window: 2025-04-02 to 2026-04-01"],
  ["FY 2021-22", "Legacy self_declaration=Yes", "Locked business rule supplied by user"],
  ["Later legacy FY", "Derived from onboard_date", "Slash format treated as m/d/yyyy; hyphen format as d-m-yyyy"],
  ["Missing date", "Assigned to Date NA", "6 unique records"],
  ["District", "Beneficiary/application home district", "Legacy FatherName aliases normalized to canonical district names"],
  ["Deduplication", "Application number first, then valid 10-digit mobile", "Earliest onboarding record retained; exclusions listed separately"],
  ["Source dump", "ukrbiin_rbi.sql", "Generated 2026-08-04 23:31"],
  ["Source dump", "rbiphase2 (1).sql", "Generated 2026-08-04 23:30"],
  ["Raw matched rows", String(rawRows.length), "Legacy + Phase 2 before deduplication"],
  ["Unique retained rows", String(uniqueRows.length), "Final workbook count"],
  ["Excluded duplicate rows", String(exclusions.length), "See Duplicate Exclusions sheet"],
];
rules.getRange("A3:C3").values = [["Rule / Source", "Applied Logic", "Notes"]];
rules.getRange("A3:C3").format = { fill: teal, font: { bold: true, color: "#FFFFFF" }, horizontalAlignment: "center" };
rules.getRange(`A4:C${ruleRows.length + 3}`).values = ruleRows;
rules.getRange(`A3:C${ruleRows.length + 3}`).format.borders = { preset: "inside", style: "thin", color: "#D9E2F3" };
rules.getRange(`A4:C${ruleRows.length + 3}`).format.wrapText = true;
rules.getRange("A:A").format.columnWidth = 26;
rules.getRange("B:B").format.columnWidth = 58;
rules.getRange("C:C").format.columnWidth = 48;
rules.freezePanes.freezeRows(3);

// Compact verification before export.
const summaryCheck = await workbook.inspect({
  kind: "table",
  range: `Summary!A1:H${totalRow}`,
  include: "values,formulas",
  tableMaxRows: 25,
  tableMaxCols: 10,
  maxChars: 12000,
});
console.log(summaryCheck.ndjson);

const detailsCheck = await workbook.inspect({
  kind: "table",
  range: "Details!A1:M10",
  include: "values,formulas",
  tableMaxRows: 10,
  tableMaxCols: 13,
  maxChars: 8000,
});
console.log(detailsCheck.ndjson);

const errors = await workbook.inspect({
  kind: "match",
  searchTerm: "#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A",
  options: { useRegex: true, maxResults: 300 },
  summary: "final formula error scan",
});
console.log(errors.ndjson);

await fs.mkdir(outputDir, { recursive: true });
for (const [sheetName, range, fileName] of [
  ["Summary", `A1:H${totalRow}`, "preview_summary.png"],
  ["Details", "A1:M25", "preview_details.png"],
  ["Duplicate Exclusions", `A1:L${exclusionLastRow}`, "preview_exclusions.png"],
  ["Methodology", `A1:C${ruleRows.length + 3}`, "preview_methodology.png"],
]) {
  const preview = await workbook.render({ sheetName, range, scale: 1.2, format: "png" });
  await fs.writeFile(`${outputDir}/${fileName}`, new Uint8Array(await preview.arrayBuffer()));
}

const output = await SpreadsheetFile.exportXlsx(workbook);
await output.save(`${outputDir}/${expectedName}`);

console.log(JSON.stringify({
  output: `${outputDir}/${expectedName}`,
  rawRows: rawRows.length,
  uniqueRows: uniqueRows.length,
  exclusions: exclusions.length,
  detailsLastRow,
  summaryTotalRow: totalRow,
}, null, 2));
