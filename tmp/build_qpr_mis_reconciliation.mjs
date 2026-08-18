import fs from "node:fs/promises";
import path from "node:path";
import { SpreadsheetFile, Workbook } from "@oai/artifact-tool";

const outputDir = path.resolve("outputs/qpr-mis-apr-jun-2026");
const outputPath = path.join(outputDir, "MUY_QPR_vs_Live_MIS_Apr_Jun_2026.xlsx");
const previewDir = path.join(outputDir, "previews");

const COLORS = {
  navy: "#17365D",
  blue: "#2F75B5",
  lightBlue: "#D9EAF7",
  teal: "#0F766E",
  lightTeal: "#DDF4EE",
  green: "#107C41",
  lightGreen: "#E2F0D9",
  red: "#C00000",
  lightRed: "#FCE4D6",
  amber: "#BF6B00",
  lightAmber: "#FFF2CC",
  gray: "#667085",
  lightGray: "#F2F4F7",
  border: "#D0D5DD",
  white: "#FFFFFF",
  ink: "#101828",
};

const rows = [
  ["1.1", "Call for Application", "Key Indicator", 15190, 18994, 18994, 3, "Exact Q1 match."],
  ["1.2", "District Level Workshops", "Key Indicator", 13, 13, 15, 3, "Live MIS contains 2 additional June workshops."],
  ["1.3", "Awareness cum Outreach activities for SHG members/Potential Lakhpati Didis/SHGs/CBOs", "Non-Key", 254, 472, 500, 3, "28 more activities are now in live MIS."],
  ["1.3.1", "Participants in Awareness cum Outreach activities", "Non-Key", 1620, 7770, 8022, 3, "252 more participants are now in live MIS."],
  ["1.4", "EAP/EDP Sessions", "Key Indicator", 39, 86, 86, 3, "Exact Q1 and month-wise match."],
  ["1.5", "Outreach through Community Organizations", "Non-Key", 4, 8, 10, 3, "2 additional activities are in live MIS."],
  ["2.1", "Incubatees Onboarded", "Key Indicator", 1900, 2847, 2847, 3, "Exact Q1 match."],
  ["2.1.1", "Onboarding of Potential Lakhpati Didi/SHG Members/CBOs", "Key Indicator", 525, 1205, 1246, 3, "41 more qualifying onboardings are now in live MIS."],
  ["3.1", "Business Skills Training Sessions", "Key Indicator", 52, 49, 53, 3, "4 more sessions are now in live MIS."],
  ["3.2", "Incubatees taken Part in Business Modules Training", "Non-Key", 1200, 1337, 1513, 3, "Live Q1 applies period-level participant logic; month sum is higher due to repeats."],
  ["3.3", "Technical Trainings to Incubatees", "Non-Key", 2, 9, 9, 4, "Exact Q1 and month-wise match."],
  ["3.3.1", "Technical Trainings to Potential Lakhpati Didis/SHG Members/CBOs", "Non-Key", "Need Based", 6, 7, 4, "Live MIS has 1 additional qualifying session."],
  ["3.4", "Capacity Building of stakeholders (REAP, USRLM, Other Line department staff)", "Key Indicator", 0, 0, 0, 4, "Exact Q1 match."],
  ["4.1.1", "Business Registration", "Key Indicator", 1025, 1297, 1297, 4, "Exact Q1 match."],
  ["4.2.1", "Artisan Card", "Key Indicator", 52, 98, 98, 4, "Exact Q1 match."],
  ["4.2.2", "FSSAI", "Non-Key", 130, 175, 173, 4, "Live MIS is 2 below the QPR figure."],
  ["4.2.3", "UTDB", "Non-Key", 0, 22, 22, 4, "Exact Q1 match."],
  ["4.2.4", "GST Registration", "Non-Key", 35, 41, 41, 4, "Exact Q1 match."],
  ["4.2.5", "Trademark application filling", "Non-Key", 0, 3, 3, 4, "Exact Q1 match."],
  ["4.2.6", "GI Seller Registration", "Non-Key", 45, 6, 6, 4, "Exact Q1 match."],
  ["4.2.7", "Advance Licensing Support (Mandi Licensing, Lab Test etc.)", "Non-Key", "Need Based", 10, 10, 4, "Exact Q1 match."],
  ["5.1", "Specialized Mentorship Support", "Key Indicator", 65, 84, 84, 4, "Exact Q1 match."],
  ["5.2", "Mentorship Support through online portal", "Non-Key", 0, 0, 0, 4, "Exact Q1 match."],
  ["6.1", "No. of Partners outreach — Partnership & Forward Linkages", "Non-Key", 30, 7, 7, 4, "Exact Q1 match."],
  ["6.2", "Marketing Partners Onboarded through LoA/LoI/MoU", "Non-Key", 20, 0, 3, 4, "3 partners are now recorded in live MIS."],
  ["6.3", "Incubatees linked to online/offline Market", "Key Indicator", 150, 135, 157, 4, "22 more unique linkages are now in live MIS."],
  ["7.1", "No. of Partners outreach — Business Acceleration", "Non-Key", 6, 3, 0, 4, "Current MIS has no Q1 records under this indicator."],
  ["7.2", "Initiation of acceleration and co-incubation services", "Key Indicator", 200, 667, 0, 4, "Major definition/source mismatch: QPR reports 667; current MIS Q1 reports 0."],
  ["8.1", "Schematic Convergence", "Key Indicator", 515, 408, 408, 5, "Exact Q1 match."],
  ["8.2", "Support to MUY Incubatee through REAP", "Key Indicator", 50, 22, 28, 5, "6 more approved cases are now in live MIS."],
  ["8.3", "Incubatees Pitch Deck Preparation", "Key Indicator", 20, 30, 30, 5, "Exact Q1 match."],
  ["8.4", "Demo Days", "Key Indicator", 1, 2, 0, 5, "QPR reports 2; current MIS Q1 reports 0."],
  ["8.5", "No. of Partners outreach — Funding & Schematic Convergence", "Non-Key", 5, 1, 0, 5, "QPR reports 1; current MIS Q1 reports 0."],
  ["9.1", "Business Model Canvas", "Key Indicator", 1025, 1550, 1550, 5, "Exact Q1 match."],
  ["9.2", "Other Support Services — Labelling, Packaging, Logo Designing, etc.", "Non-Key", 0, 15, 15, 5, "Exact Q1 match."],
  ["10.1", "Social Media Post", "Key Indicator", 30, 28, 28, 5, "Exact Q1 match."],
  ["10.2", "Preparation of Case Studies and Testimonials", "Key Indicator", 10, 13, 13, 5, "Exact Q1 match."],
  ["10.3", "MUY Newsletter", "Key Indicator", 0, 0, 0, 5, "Exact Q1 match; live target is blank but achievement is 0."],
  ["10.4", "IEC & Promotional Activities for MUY", "Non-Key", 1, 0, 0, 5, "Exact achievement match; QPR used the former indicator name."],
  ["10.5", "Buyer-Seller Meet", "Key Indicator", 2, 0, 0, 5, "Exact Q1 match."],
  ["10.6", "Events/Seminars/Workshops", "Key Indicator", 0, 0, 0, 5, "Exact Q1 match."],
  ["11.1", "Identification and Submission of Proposal for New Product Development", "Non-Key", 0, 0, 0, 5, "Exact Q1 match; current MIS labels this as Key."],
  ["12.1", "Stakeholder Consultation Workshop", "Non-Key", 0, 0, 0, 5, "Exact Q1 match; current MIS labels this as Key."],
  ["12.2", "Meeting of staff with Line Department at Spoke/Hub/State Level", "Non-Key", 5, 19, 84, 5, "65 additional/backfilled meetings are now counted in live MIS."],
].map(([serial, indicator, type, qprTarget, qprAchievement, misQ1, page, note]) => ({ serial, indicator, type, qprTarget, qprAchievement, misQ1, page, note }));

const monthlyMis = {
  "1.1":[5168,6973,6853], "1.2":[1,5,9], "1.3":[95,178,227], "1.3.1":[1829,2638,3555],
  "1.4":[25,32,29], "1.5":[0,2,8], "2.1":[764,773,1310], "2.1.1":[221,294,731],
  "3.1":[0,22,31], "3.2":[0,583,997], "3.3":[1,4,4], "3.3.1":[0,1,6], "3.4":[0,0,0],
  "4.1.1":[10,446,841], "4.2.1":[0,25,73], "4.2.2":[1,71,101], "4.2.3":[1,5,16],
  "4.2.4":[0,9,32], "4.2.5":[0,0,3], "4.2.6":[0,0,6], "4.2.7":[0,3,7],
  "5.1":[0,6,78], "5.2":[0,0,0], "6.1":[2,0,5], "6.2":[0,0,3], "6.3":[0,25,139],
  "7.1":[0,0,0], "7.2":[0,0,0], "8.1":[0,65,343], "8.2":[0,0,28], "8.3":[0,0,30],
  "8.4":[0,0,0], "8.5":[0,0,0], "9.1":[0,377,1173], "9.2":[0,3,12], "10.1":[8,10,10],
  "10.2":[0,0,13], "10.3":[0,0,0], "10.4":[0,0,0], "10.5":[0,0,0], "10.6":[0,0,0],
  "11.1":[0,0,0], "12.1":[0,0,0], "12.2":[10,58,16],
};

// Only these indicators have a dated month-wise split in the QPR itself.
const monthlyQpr = {
  "1.2":[1,5,7],
  "1.4":[25,32,29],
  "1.5":[0,1,7],
  "3.3":[1,4,4],
};

const monthlyEvidence = {
  "1.2":"QPR p.11 — dated district workshop table",
  "1.4":"QPR pp.13–16 — 86 dated EAP/EDP sessions",
  "1.5":"QPR p.16 — 8 dated community-organization activities",
  "3.3":"QPR p.19 — 9 dated technical training sessions",
};

const workbook = Workbook.create();
const exec = workbook.worksheets.add("Executive Summary");
const q1 = workbook.worksheets.add("Q1 Reconciliation");
const monthly = workbook.worksheets.add("Monthly Detail");
const method = workbook.worksheets.add("Method & Sources");

function setTitle(sheet, title, subtitle, endCol) {
  const titleRange = sheet.getRange(`A1:${endCol}1`);
  titleRange.merge();
  titleRange.values = [[title]];
  titleRange.format = {
    fill: COLORS.navy,
    font: { bold: true, color: COLORS.white, size: 18 },
    verticalAlignment: "center",
  };
  titleRange.format.rowHeight = 34;

  const subRange = sheet.getRange(`A2:${endCol}2`);
  subRange.merge();
  subRange.values = [[subtitle]];
  subRange.format = {
    fill: COLORS.lightBlue,
    font: { color: COLORS.navy, italic: true, size: 10 },
    verticalAlignment: "center",
    wrapText: true,
  };
  subRange.format.rowHeight = 30;
  sheet.showGridLines = false;
}

function styleHeader(range) {
  range.format = {
    fill: COLORS.blue,
    font: { bold: true, color: COLORS.white, size: 10 },
    horizontalAlignment: "center",
    verticalAlignment: "center",
    wrapText: true,
    borders: { preset: "all", style: "thin", color: COLORS.border },
  };
  range.format.rowHeight = 30;
}

function styleBody(range) {
  range.format = {
    font: { color: COLORS.ink, size: 10 },
    verticalAlignment: "top",
    wrapText: true,
    borders: { preset: "all", style: "thin", color: COLORS.border },
  };
}

setTitle(
  exec,
  "MUY QPR vs Live MIS — Reconciliation",
  "Q1 FY 2026–27 (April–June 2026) | QPR issued 10 Jul 2026 | Live MIS read 17 Aug 2026, 04:53 PM IST",
  "J",
);

exec.getRange("A4:J4").merge();
exec.getRange("A4:J4").values = [["At a glance"]];
exec.getRange("A4:J4").format = { fill: COLORS.teal, font: { bold: true, color: COLORS.white, size: 12 } };

const cards = [
  ["A5:B5", "A6:B7", "Indicators reviewed", `=COUNTA('Q1 Reconciliation'!$A$7:$A$50)`],
  ["C5:D5", "C6:D7", "Q1 exact matches", `=COUNTIF('Q1 Reconciliation'!$H$7:$H$50,"Match")`],
  ["E5:F5", "E6:F7", "Q1 mismatches", `=COUNTIF('Q1 Reconciliation'!$H$7:$H$50,"Mismatch")`],
  ["G5:H5", "G6:H7", "Comparable month checks", `=COUNTIF('Monthly Detail'!$E$7:$E$50,"Match")+COUNTIF('Monthly Detail'!$E$7:$E$50,"Mismatch")+COUNTIF('Monthly Detail'!$H$7:$H$50,"Match")+COUNTIF('Monthly Detail'!$H$7:$H$50,"Mismatch")+COUNTIF('Monthly Detail'!$K$7:$K$50,"Match")+COUNTIF('Monthly Detail'!$K$7:$K$50,"Mismatch")`],
  ["I5:J5", "I6:J7", "Month checks matched", `=COUNTIF('Monthly Detail'!$E$7:$E$50,"Match")+COUNTIF('Monthly Detail'!$H$7:$H$50,"Match")+COUNTIF('Monthly Detail'!$K$7:$K$50,"Match")`],
];
for (const [labelRange, valueRange, label, formula] of cards) {
  exec.getRange(labelRange).merge();
  exec.getRange(labelRange).values = [[label]];
  exec.getRange(labelRange).format = { fill: COLORS.lightGray, font: { bold: true, color: COLORS.gray }, horizontalAlignment: "center", verticalAlignment: "center" };
  exec.getRange(valueRange).merge();
  exec.getRange(valueRange).formulas = [[formula]];
  exec.getRange(valueRange).format = { fill: COLORS.white, font: { bold: true, color: COLORS.navy, size: 22 }, horizontalAlignment: "center", verticalAlignment: "center", borders: { preset: "outside", style: "medium", color: COLORS.border } };
}

exec.getRange("A9:J9").merge();
exec.getRange("A9:J9").values = [["Priority findings"]];
exec.getRange("A9:J9").format = { fill: COLORS.teal, font: { bold: true, color: COLORS.white, size: 12 } };
exec.getRange("A10:J15").values = [
  ["Finding", "Detail", null, null, null, null, null, null, null, null],
  ["Largest gap", "7.2 Acceleration/co-incubation: QPR 667 vs live MIS Q1 0. This requires a definition/source review, not a simple data correction.", null, null, null, null, null, null, null, null],
  ["High post-QPR growth", "12.2 Line-department meetings increased from 19 in QPR to 84 in live Q1 (+65).", null, null, null, null, null, null, null, null],
  ["Core pipeline stable", "CFA (18,994), total onboarding (2,847), business registration (1,297), BMC (1,550) and schematic convergence (408) match exactly.", null, null, null, null, null, null, null, null],
  ["Small count exceptions", "FSSAI is 173 live vs 175 QPR; REAP support is 28 live vs 22 QPR; district workshops are 15 live vs 13 QPR.", null, null, null, null, null, null, null, null],
  ["Monthly limitation", "The QPR publishes month-wise dated evidence for only 4 indicators. Other monthly comparisons are marked “Not reported” rather than inferred.", null, null, null, null, null, null, null, null],
];
exec.getRange("A10:A15").format = { fill: COLORS.lightGray, font: { bold: true, color: COLORS.navy }, wrapText: true, borders: { preset: "all", style: "thin", color: COLORS.border } };
for (let r = 10; r <= 15; r++) {
  exec.getRange(`B${r}:J${r}`).merge();
  exec.getRange(`B${r}:J${r}`).format = { wrapText: true, verticalAlignment: "center", borders: { preset: "all", style: "thin", color: COLORS.border } };
}
exec.getRange("A10:J10").format = { fill: COLORS.blue, font: { bold: true, color: COLORS.white }, borders: { preset: "all", style: "thin", color: COLORS.border } };
exec.getRange("A17:J17").merge();
exec.getRange("A17:J17").values = [["Interpretation: “Match” means the QPR achievement equals the live MIS Q1 result under the same FY/quarter scope. Live values may have changed after QPR issuance because approved/backdated records were added or reporting logic evolved."]];
exec.getRange("A17:J17").format = { fill: COLORS.lightAmber, font: { color: COLORS.amber, italic: true }, wrapText: true, borders: { preset: "outside", style: "thin", color: COLORS.amber } };
exec.getRange("A:A").format.columnWidth = 19;
exec.getRange("B:J").format.columnWidth = 15;
exec.getRange("A10:A15").format.columnWidth = 24;
exec.getRange("A10:J15").format.rowHeight = 34;
exec.freezePanes.freezeRows(2);

setTitle(
  q1,
  "Q1 Reconciliation — QPR vs Live MIS",
  "QPR quantitative table (pp. 3–5) compared with live MIS filter: FY 2026–27 + Q1 (Apr–Jun 2026)",
  "J",
);
q1.getRange("A4:J4").merge();
q1.getRange("A4:J4").values = [["Live data snapshot: 17 Aug 2026, 04:53 PM IST | Variance = Live MIS Q1 − QPR achievement"]];
q1.getRange("A4:J4").format = { fill: COLORS.lightAmber, font: { color: COLORS.amber, italic: true }, wrapText: true };
q1.getRange("A6:J6").values = [["S.N.", "Indicator", "Type", "QPR Target", "QPR Achievement", "Live MIS Q1", "Variance", "Status", "QPR page", "Finding / interpretation"]];
styleHeader(q1.getRange("A6:J6"));

const q1Data = rows.map(r => [r.serial, r.indicator, r.type, r.qprTarget, r.qprAchievement, r.misQ1, null, null, r.page, r.note]);
q1.getRange(`A7:J${6 + rows.length}`).values = q1Data;
styleBody(q1.getRange(`A7:J${6 + rows.length}`));
for (let i = 0; i < rows.length; i++) {
  const er = 7 + i;
  q1.getRange(`G${er}`).formulas = [[`=F${er}-E${er}`]];
  q1.getRange(`H${er}`).formulas = [[`=IF(G${er}=0,"Match","Mismatch")`]];
  const isMatch = rows[i].misQ1 === rows[i].qprAchievement;
  q1.getRange(`H${er}`).format = { fill: isMatch ? COLORS.lightGreen : COLORS.lightRed, font: { bold: true, color: isMatch ? COLORS.green : COLORS.red }, horizontalAlignment: "center", borders: { preset: "all", style: "thin", color: COLORS.border } };
  if (!isMatch) q1.getRange(`A${er}:J${er}`).format.fill = i % 2 ? "#FFF8F4" : "#FFF4EF";
}
q1.getRange(`D7:G${6 + rows.length}`).format.numberFormat = "#,##0;[Red]-#,##0";
q1.getRange(`A7:A${6 + rows.length}`).format.horizontalAlignment = "center";
q1.getRange(`C7:C${6 + rows.length}`).format.horizontalAlignment = "center";
q1.getRange(`I7:I${6 + rows.length}`).format.horizontalAlignment = "center";
q1.getRange("A:A").format.columnWidth = 9;
q1.getRange("B:B").format.columnWidth = 40;
q1.getRange("C:C").format.columnWidth = 14;
q1.getRange("D:H").format.columnWidth = 14;
q1.getRange("I:I").format.columnWidth = 10;
q1.getRange("J:J").format.columnWidth = 44;
q1.getRange(`A7:J${6 + rows.length}`).format.autofitRows();
q1.freezePanes.freezeRows(6);
q1.freezePanes.freezeColumns(2);

setTitle(
  monthly,
  "Month-wise Detail — QPR Evidence vs Live MIS",
  "April, May and June live MIS results for every indicator; QPR month checks only where the report contains dated evidence",
  "O",
);
monthly.getRange("A4:O4").merge();
monthly.getRange("A4:O4").values = [["“Not reported” means the QPR gives only a Q1 total and no defensible month split. MIS month sum can differ from live Q1 where period-level deduplication is used."]];
monthly.getRange("A4:O4").format = { fill: COLORS.lightAmber, font: { color: COLORS.amber, italic: true }, wrapText: true };
monthly.getRange("A6:O6").values = [["S.N.", "Indicator", "Apr QPR", "Apr MIS", "Apr status", "May QPR", "May MIS", "May status", "Jun QPR", "Jun MIS", "Jun status", "MIS month sum", "Live MIS Q1", "Sum vs Q1", "QPR month evidence"]];
styleHeader(monthly.getRange("A6:O6"));

const monthlyData = rows.map(r => {
  const q = monthlyQpr[r.serial] ?? ["Not reported", "Not reported", "Not reported"];
  const m = monthlyMis[r.serial];
  return [r.serial, r.indicator, q[0], m[0], null, q[1], m[1], null, q[2], m[2], null, null, r.misQ1, null, monthlyEvidence[r.serial] ?? "QPR provides Q1 total only"];
});
monthly.getRange(`A7:O${6 + rows.length}`).values = monthlyData;
styleBody(monthly.getRange(`A7:O${6 + rows.length}`));
for (let i = 0; i < rows.length; i++) {
  const er = 7 + i;
  monthly.getRange(`E${er}`).formulas = [[`=IF(ISNUMBER(C${er}),IF(C${er}=D${er},"Match","Mismatch"),"Not reported")`]];
  monthly.getRange(`H${er}`).formulas = [[`=IF(ISNUMBER(F${er}),IF(F${er}=G${er},"Match","Mismatch"),"Not reported")`]];
  monthly.getRange(`K${er}`).formulas = [[`=IF(ISNUMBER(I${er}),IF(I${er}=J${er},"Match","Mismatch"),"Not reported")`]];
  monthly.getRange(`L${er}`).formulas = [[`=SUM(D${er},G${er},J${er})`]];
  monthly.getRange(`N${er}`).formulas = [[`=IF(L${er}=M${er},"Additive","Period dedupe / logic")`]];

  const q = monthlyQpr[rows[i].serial];
  const m = monthlyMis[rows[i].serial];
  for (const [col, qi, mi] of [["E", q?.[0], m[0]], ["H", q?.[1], m[1]], ["K", q?.[2], m[2]]]) {
    const status = q === undefined ? "Not reported" : (qi === mi ? "Match" : "Mismatch");
    monthly.getRange(`${col}${er}`).format = {
      fill: status === "Match" ? COLORS.lightGreen : status === "Mismatch" ? COLORS.lightRed : COLORS.lightGray,
      font: { bold: status !== "Not reported", color: status === "Match" ? COLORS.green : status === "Mismatch" ? COLORS.red : COLORS.gray },
      horizontalAlignment: "center",
      borders: { preset: "all", style: "thin", color: COLORS.border },
    };
  }
}
monthly.getRange(`C7:N${6 + rows.length}`).format.numberFormat = "#,##0;[Red]-#,##0";
monthly.getRange("A:A").format.columnWidth = 9;
monthly.getRange("B:B").format.columnWidth = 38;
monthly.getRange("C:N").format.columnWidth = 13;
monthly.getRange("O:O").format.columnWidth = 34;
monthly.getRange(`A7:O${6 + rows.length}`).format.autofitRows();
monthly.freezePanes.freezeRows(6);
monthly.freezePanes.freezeColumns(2);

setTitle(
  method,
  "Method, Definitions & Source Notes",
  "Audit trail for the reconciliation workbook",
  "H",
);
method.getRange("A4:H4").merge();
method.getRange("A4:H4").values = [["Sources"]];
method.getRange("A4:H4").format = { fill: COLORS.teal, font: { bold: true, color: COLORS.white, size: 12 } };
method.getRange("A5:B11").values = [
  ["Source", "Use in this workbook"],
  ["MUY QPR April - June 2026.pdf", "QPR targets and Q1 achievements from pp. 3–5."],
  ["QPR p.11", "Month-wise district workshop evidence."],
  ["QPR pp.13–16", "Month-wise EAP/EDP session evidence."],
  ["QPR p.16", "Month-wise community-organization outreach evidence."],
  ["QPR p.19", "Month-wise technical training session evidence."],
  ["Live MIS — /phase3/admin/deliverables", "FY 2026–27; Q1, April, May and June filters; statewide scope."],
];
styleHeader(method.getRange("A5:B5"));
styleBody(method.getRange("A6:B11"));
for (let r = 5; r <= 11; r++) method.getRange(`B${r}:H${r}`).merge();

method.getRange("A13:H13").merge();
method.getRange("A13:H13").values = [["Method and limitations"]];
method.getRange("A13:H13").format = { fill: COLORS.teal, font: { bold: true, color: COLORS.white, size: 12 } };
const methodRows = [
  ["Comparison scope", "Statewide FY 2026–27, Q1 (Apr–Jun 2026), approved/current MIS reporting logic."],
  ["Match rule", "Q1 status is Match only when live MIS Q1 achievement equals the QPR achievement exactly."],
  ["Monthly rule", "Month status is assessed only when the QPR contains dated source rows for that indicator."],
  ["Live data timing", "The QPR was created 10 Jul 2026. Live MIS was read 17 Aug 2026; backdated approvals can legitimately change Q1 totals."],
  ["Period deduplication", "For participant/beneficiary indicators, live Q1 may deduplicate across the quarter, so April+May+June can exceed the Q1 total."],
  ["QPR internal inconsistency", "Page 18 narrative states 1,473 business-training participants, while its table and the official quantitative summary state 1,337. This workbook uses 1,337."],
  ["Indicator naming", "QPR 10.4 'Newspaper Ads and Radio promotion campaigns' maps to live MIS 10.4 'IEC & Promotional Activities for MUY'."],
  ["Indicator metadata", "QPR labels 11.1 and 12.1 Non-Key; the current MIS labels them Key. Achievement comparison is still valid."],
  ["Major review item", "Indicator 7.2 appears to use a different definition/source: QPR 667 vs live MIS Q1 0. Validate its historical counting methodology."],
];
method.getRange(`A14:B${13 + methodRows.length}`).values = methodRows;
styleBody(method.getRange(`A14:B${13 + methodRows.length}`));
for (let r = 14; r <= 13 + methodRows.length; r++) {
  method.getRange(`A${r}`).format = { fill: COLORS.lightGray, font: { bold: true, color: COLORS.navy }, wrapText: true, borders: { preset: "all", style: "thin", color: COLORS.border } };
  method.getRange(`B${r}:H${r}`).merge();
  method.getRange(`B${r}:H${r}`).format = { wrapText: true, verticalAlignment: "top", borders: { preset: "all", style: "thin", color: COLORS.border } };
}
method.getRange("A:A").format.columnWidth = 27;
method.getRange("B:H").format.columnWidth = 16;
method.getRange("A5:H24").format.autofitRows();
method.freezePanes.freezeRows(2);

await fs.mkdir(previewDir, { recursive: true });
for (const sheetName of ["Executive Summary", "Q1 Reconciliation", "Monthly Detail", "Method & Sources"]) {
  const preview = await workbook.render({ sheetName, autoCrop: "all", scale: 1, format: "png" });
  await fs.writeFile(path.join(previewDir, `${sheetName.replaceAll(" ", "_")}.png`), new Uint8Array(await preview.arrayBuffer()));
}

await fs.mkdir(outputDir, { recursive: true });
const output = await SpreadsheetFile.exportXlsx(workbook);
await output.save(outputPath);

const inspect = await workbook.inspect({ kind: "sheet,region,formula", maxChars: 14000, tableMaxRows: 8, tableMaxCols: 15, options: { maxResults: 120 } });
console.log(inspect.ndjson ?? inspect);
console.log(`OUTPUT=${outputPath}`);
