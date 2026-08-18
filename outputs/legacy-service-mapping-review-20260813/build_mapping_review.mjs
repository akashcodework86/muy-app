import fs from "node:fs/promises";
import { SpreadsheetFile, Workbook } from "@oai/artifact-tool";

const outputDir = "C:/xampp/htdocs/muy-app/outputs/legacy-service-mapping-review-20260813";

const rows = [
  ["Phase 1", "Business registration", 1381, "Udyam Registration", "Medium", "Review", "Generic historical label; Udyam is the closest current business-registration service."],
  ["Phase 2", "Business Plan", 1042, "Business Model Canvas (BMC)", "Medium", "Review", "Closest current planning service; confirm that these were BMC/business-plan interventions."],
  ["Phase 1", "Training", 857, "EAP/EDP Sessions", "Medium", "Review", "Generic training label; confirm whether it represents EAP/EDP or another training programme."],
  ["Phase 2", "Support in Application process", 788, "Other Convergence Support", "Low", "Hold", "Too generic to map safely without checking the underlying scheme/application type."],
  ["Phase 2", "Training Package 1", 724, "EAP/EDP Sessions", "Medium", "Review", "Training package; proposed roll-up to the current EAP/EDP service."],
  ["Phase 1", "Loan", 588, "Other Convergence Support", "Low", "Hold", "Loan scheme is not identified; do not force it into MSY/PMEGP/PMFME."],
  ["Phase 2", "Training Package 2", 178, "EAP/EDP Sessions", "Medium", "Review", "Training package; proposed roll-up to the current EAP/EDP service."],
  ["Phase 2", "Training Package 3", 162, "EAP/EDP Sessions", "Medium", "Review", "Training package; proposed roll-up to the current EAP/EDP service."],
  ["Phase 2", "Training", 134, "EAP/EDP Sessions", "Medium", "Review", "Generic training label; confirm whether it represents EAP/EDP."],
  ["Phase 2", "Loan", 95, "Other Convergence Support", "Low", "Hold", "Loan scheme is not identified."],
  ["Phase 2", "Business registration", 89, "Udyam Registration", "Medium", "Review", "Closest current business-registration service."],
  ["Phase 1", "Loan / scheme", 61, "Other Convergence Support", "Low", "Hold", "Scheme is not identified."],
  ["Phase 2", "Other Licensing Support", 44, "Other Convergence Support", "Low", "Hold", "Could include several licences; inspect details before final mapping."],
  ["Phase 2", "Support in process", 42, "Other Convergence Support", "Low", "Hold", "Looks like a workflow/status label rather than a specific service."],
  ["Phase 1", "Business skills training", 40, "EAP/EDP Sessions", "High", "Review", "Strong training-equivalent match."],
  ["Phase 2", "Training Package 4", 37, "EAP/EDP Sessions", "Medium", "Review", "Training package; proposed roll-up to the current EAP/EDP service."],
  ["Phase 1", "Support in business", 33, "Other Convergence Support", "Low", "Hold", "Generic support label; underlying activity is unknown."],
  ["Phase 2", "Trade fair Participation", 31, "Buyer-Seller Meet (BSM)", "Medium", "Review", "Commercial exposure activity; BSM is the closest current service, but confirm."],
  ["Phase 2", "MUDRA", 29, "Other Convergence Support", "Medium", "Review", "Current master has no MUDRA service; keep under convergence support unless a new standard is approved."],
  ["Phase 1", "Unit setup", 27, "Other Convergence Support", "Low", "Hold", "Generic unit-establishment support; inspect details."],
  ["Phase 2", "Legal vetting of documents", 25, "Other Convergence Support", "Medium", "Review", "Current master has no dedicated legal-vetting service."],
  ["Phase 2", "Shop & Establishment", 23, "Shop establishment", "High", "Safe", "Clear spelling/capitalization equivalent."],
  ["Phase 2", "Product Diversification", 18, "Identification and Submission of Proposal for New Product Development", "Medium", "Review", "Closest product-development standard; master entry is currently inactive."],
  ["Phase 2", "Trade fair Particiepataion", 17, "Buyer-Seller Meet (BSM)", "Medium", "Review", "Misspelling of Trade fair Participation."],
  ["Phase 2", "MSME", 16, "Udyam Registration", "Medium", "Review", "MSME registration is normally represented by Udyam; confirm historical meaning."],
  ["Phase 2", "IPR support", 15, "Trademark Application filling", "Medium", "Review", "Closest current IPR-related service; IPR may be broader than trademark."],
  ["Phase 2", "Other Service", 12, "Other Support Services - Labelling, Packaging, Logo Designing etc.", "Low", "Hold", "Generic label; inspect details before mapping."],
  ["Phase 2", "Photoshoot", 8, "Other Support Services - Labelling, Packaging, Logo Designing etc.", "Medium", "Review", "Branding-support activity; closest current support-service bucket."],
  ["Phase 1", "Business plan", 7, "Business Model Canvas (BMC)", "Medium", "Review", "Same historical concept as Business Plan; confirm BMC roll-up."],
  ["Phase 1", "Support in Application process", 7, "Other Convergence Support", "Low", "Hold", "Too generic to map safely."],
  ["Phase 1", "Training Package 1", 6, "EAP/EDP Sessions", "Medium", "Review", "Training package; proposed roll-up to EAP/EDP."],
  ["Phase 2", "Content Writing", 4, "Other Support Services - Labelling, Packaging, Logo Designing etc.", "Medium", "Review", "Branding/communication support; closest current support-service bucket."],
  ["Phase 2", "Catalogue Development", 4, "Other Support Services - Labelling, Packaging, Logo Designing etc.", "Medium", "Review", "Branding/marketing material support."],
  ["Phase 1", "Other RBI support", 4, "Other Convergence Support", "Low", "Hold", "Generic historical support label."],
  ["Phase 2", "Loan / scheme", 3, "Other Convergence Support", "Low", "Hold", "Scheme is not identified."],
  ["Phase 1", "Prior business support", 3, "Other Convergence Support", "Low", "Hold", "Generic historical support label."],
  ["Phase 2", "Fire NOC", 2, "Other Convergence Support", "Medium", "Review", "Current master has no dedicated Fire NOC service."],
  ["Phase 2", "Ayush Licence", 1, "Other Convergence Support", "Medium", "Review", "Current master has no dedicated AYUSH licence service."],
  ["Phase 2", "Unit setup", 1, "Other Convergence Support", "Low", "Hold", "Generic unit-establishment support."],
  ["Phase 1", "Support in process", 1, "Other Convergence Support", "Low", "Hold", "Looks like a workflow/status label rather than a specific service."],
  ["Phase 1", "Training Package 2", 1, "EAP/EDP Sessions", "Medium", "Review", "Training package; proposed roll-up to EAP/EDP."],
  ["Phase 1", "Photoshoot", 1, "Other Support Services - Labelling, Packaging, Logo Designing etc.", "Medium", "Review", "Branding-support activity."],
  ["Phase 1", "Training Package 3", 1, "EAP/EDP Sessions", "Medium", "Review", "Training package; proposed roll-up to EAP/EDP."],
];

const approvedByName = {
  "business registration": "Business Registration",
  "business plan": "Business Plan",
  "training": "Incubatees taken Part in Business Modules Training",
  "business skills training": "Incubatees taken Part in Business Modules Training",
  "training package 1": "Incubatees taken Part in Business Modules Training",
  "training package 2": "Incubatees taken Part in Business Modules Training",
  "training package 3": "Incubatees taken Part in Business Modules Training",
  "training package 4": "Incubatees taken Part in Business Modules Training",
  "support in application process": "Schematic Convergence",
  "support in process": "Schematic Convergence",
  "loan": "Schematic Convergence",
  "loan / scheme": "Schematic Convergence",
  "mudra": "Schematic Convergence",
  "other licensing support": "Advance Licensing Support (Mandi Licensing, Lab Test etc.)",
  "legal vetting of documents": "Advance Licensing Support (Mandi Licensing, Lab Test etc.)",
  "shop & establishment": "Advance Licensing Support (Mandi Licensing, Lab Test etc.)",
  "fire noc": "Advance Licensing Support (Mandi Licensing, Lab Test etc.)",
  "ayush licence": "Advance Licensing Support (Mandi Licensing, Lab Test etc.)",
  "ipr support": "Advance Licensing Support (Mandi Licensing, Lab Test etc.)",
  "msme": "Business Registration",
  "product diversification": "Identification and Submission of Proposal for New Product Development",
  "unit setup": "Initiation of acceleration and co-incubation services",
  "support in business": "Others",
  "prior business support": "Others",
  "other rbi support": "Others",
  "other service": "Others",
  "photoshoot": "Other Support Services - Labelling, Packaging, Logo Designing etc.",
  "content writing": "Other Support Services - Labelling, Packaging, Logo Designing etc.",
  "catalogue development": "Other Support Services - Labelling, Packaging, Logo Designing etc.",
  "trade fair participation": "Events/ Seminars/ Workshops",
  "trade fair particiepataion": "Events/ Seminars/ Workshops",
};

for (const row of rows) {
  row[3] = approvedByName[row[1].toLowerCase()] ?? row[3];
  row[4] = "Client approved";
  row[5] = "Final";
  row[6] = `Approved reporting mapping: ${row[3]}. Original historical name remains visible in exports.`;
}

const phase3Master = [
  ["Business Registration", "Deliverable indicator"], ["Business Plan", "Historical service"],
  ["Incubatees taken Part in Business Modules Training", "Deliverable indicator"],
  ["Technical Trainings to Incubatees", "Deliverable indicator"], ["Schematic Convergence", "Deliverable indicator"],
  ["Advance Licensing Support (Mandi Licensing, Lab Test etc.)", "Deliverable indicator"],
  ["Initiation of acceleration and co-incubation services", "Deliverable indicator"], ["Others", "Reporting bucket"],
  ["Already Registered", "Active"], ["Artisan Card", "Active"], ["Business Model Canvas (BMC)", "Active"],
  ["Buyer-Seller Meet (BSM)", "Active"], ["Company Registration", "Active"], ["Cooperative registration", "Active"],
  ["Deen Dayal Upadhyay Grah Awas Vikas Yojana (DDUGAVY)- Homestay", "Active"], ["Demo Days", "Active"],
  ["District Level Workshops", "Active"], ["EAP/EDP Sessions", "Active"], ["Events/ Seminars/ Workshops", "Active"],
  ["FSSAI Registration/Renewal", "Active"], ["GI Seller Registration", "Active"], ["GST", "Active"],
  ["Identification and Submission of Proposal for New Product Development", "Inactive"],
  ["Incubatees linked to online/offline Market", "Inactive"], ["Lab testing", "Active"], ["MSY 2.0", "Active"],
  ["MUY Newsletter", "Inactive"], ["Mandi License", "Active"], ["Marketing Partners Onboarded through (LoA/LoI/MoU)", "Active"],
  ["Meeting of staff with Line Department at Spoke/Hub/State Level", "Inactive"],
  ["Newspaper Ads and Radio promotion campaigns", "Inactive"], ["No of Partners outreach", "Inactive"],
  ["No of Partners outreach For Business Accleration", "Inactive"], ["Other Convergence Support", "Active"],
  ["Other Support Services - Labelling, Packaging, Logo Designing etc.", "Active"],
  ["Outreach through Community Organizations", "Active"], ["PMEGP", "Active"], ["PMFME", "Active"], ["Pan Card", "Active"],
  ["Pitch Decks", "Active"], ["Preparation of Case Studies and Testimonials", "Active"], ["Seed License", "Active"],
  ["Shop establishment", "Active"], ["Social Media Post", "Active"], ["Specialized Mentorship Support", "Active"],
  ["Stakeholder Consultation Workshop", "Inactive"], ["Support to MUY Incubatee through Reap", "Active"],
  ["Trademark Application filling", "Active"], ["UK Firm Registration", "Active"], ["UTDB Registration", "Active"],
  ["Udyam Registration", "Active"], ["Veer Chandra Singh Garhwali Self Employment Scheme", "Active"],
];

const workbook = Workbook.create();
workbook.comments.setSelf({ displayName: "User" });
const summary = workbook.worksheets.add("Summary");
const review = workbook.worksheets.add("Mapping Review");
const master = workbook.worksheets.add("Reporting Standards");

for (const sheet of [summary, review, master]) {
  sheet.showGridLines = false;
}

summary.getRange("A1:F1").merge();
summary.getRange("A1").values = [["Legacy Service Mapping Review"]];
summary.getRange("A2:F2").merge();
summary.getRange("A2").values = [["Phase 1/2 historical names → approved reporting standards | Final decisions | 13 Aug 2026"]];
summary.getRange("A4:B8").values = [
  ["Live historical names", 179],
  ["Already mapped", 136],
  ["Names needing mapping", null],
  ["Affected service records", null],
  ["Client-approved mappings", null],
];
summary.getRange("B6").formulas = [["=COUNTA('Mapping Review'!B5:B47)"]];
summary.getRange("B7").formulas = [["=SUM('Mapping Review'!D5:D47)"]];
summary.getRange("B8").formulas = [["=COUNTIF('Mapping Review'!I5:I47,\"Approved\")"]];
summary.getRange("D4:E8").values = [
  ["Approved by reviewer", null],
  ["Pending", null],
  ["Hold", null],
  ["Rejected", null],
  ["Local snapshot note", "28 names / 4,818 records"],
];
summary.getRange("E4").formulas = [["=COUNTIF('Mapping Review'!I5:I47,\"Approved\")"]];
summary.getRange("E5").formulas = [["=COUNTIF('Mapping Review'!I5:I47,\"Pending\")"]];
summary.getRange("E6").formulas = [["=COUNTIF('Mapping Review'!I5:I47,\"Hold\")"]];
summary.getRange("E7").formulas = [["=COUNTIF('Mapping Review'!I5:I47,\"Rejected\")"]];

summary.getRange("A10:F10").merge();
summary.getRange("A10").values = [["How to use this review"]];
summary.getRange("A11:F15").values = [
  ["1", "Review the proposed Phase 3 name in the Mapping Review sheet.", null, null, null, null],
  ["2", "Final names are controlled by the Reporting Standards dropdown.", null, null, null, null],
  ["3", "All 43 reviewed names are marked Approved from the one-by-one client review.", null, null, null, null],
  ["4", "Original historical names remain visible for bifurcation and audit.", null, null, null, null],
  ["5", "This workbook does not modify any Phase 1, Phase 2 or Phase 3 database record.", null, null, null, null],
];
summary.getRange("A17:F20").values = [
  ["Important finding", null, null, null, null, null],
  ["FSSAI is already standardized as “FSSAI Registration/Renewal”. Its 271 vs 220 mismatch is a beneficiary-linkage issue, not a service-name mismatch.", null, null, null, null, null],
  ["Source", "Live Legacy Service Mappings page + local database inventory", null, null, null, null],
  ["Rule", "Phase 3 names are final; Phase 1/2 records are treated as approved for reporting.", null, null, null, null],
];
summary.getRange("A17:F17").merge();
summary.getRange("A18:F18").merge();
summary.getRange("B19:F19").merge();
summary.getRange("B20:F20").merge();

const headers = [["Sr.", "Source phase", "Historical service name", "Records", "Proposed Phase 3 standard", "Confidence", "Recommendation", "Final approved Phase 3 name", "Decision", "Review notes"]];
review.getRange("A1:J1").merge();
review.getRange("A1").values = [["Legacy Service Name Mapping — Approval Sheet"]];
review.getRange("A2:J2").merge();
review.getRange("A2").values = [["Blue cells are reviewer inputs. No mapping becomes final until Decision = Approved."]];
review.getRange("A4:J4").values = headers;
const data = rows.map((r, idx) => [idx + 1, ...r.slice(0, 6), r[3], "Approved", r[6]]);
review.getRange(`A5:J${4 + data.length}`).values = data;
review.getRange(`H5:H${4 + data.length}`).dataValidation = { rule: { type: "list", formula1: "'Reporting Standards'!$A$5:$A$60" } };
review.getRange(`I5:I${4 + data.length}`).dataValidation = { rule: { type: "list", values: ["Pending", "Approved", "Hold", "Rejected"] } };
review.freezePanes.freezeRows(4);
review.freezePanes.freezeColumns(2);
const mappingTable = review.tables.add(`A4:J${4 + data.length}`, true, "LegacyMappingReview");
mappingTable.style = "TableStyleMedium2";
mappingTable.showBandedRows = true;

master.getRange("A1:C1").merge();
master.getRange("A1").values = [["Approved Reporting Standards — Deliverables + Phase 3 services"]];
master.getRange("A2:C2").merge();
master.getRange("A2").values = [["Includes deliverable indicators, historical-only services and current Phase 3 service names."]];
master.getRange("A4:C4").values = [["Phase 3 service name", "Status", "Notes"]];
master.getRange(`A5:C${4 + phase3Master.length}`).values = phase3Master.map(([name, status]) => [name, status, status === "Inactive" ? "Available in master but inactive" : ""]);
master.freezePanes.freezeRows(4);
const masterTable = master.tables.add(`A4:C${4 + phase3Master.length}`, true, "Phase3MasterServices");
masterTable.style = "TableStyleMedium4";

const titleFormat = { fill: "#4338CA", font: { bold: true, color: "#FFFFFF", size: 18 }, verticalAlignment: "center" };
const subtitleFormat = { fill: "#EEF2FF", font: { color: "#475569", italic: true }, verticalAlignment: "center" };
for (const sheet of [summary, review, master]) {
  sheet.getRange("A1:J1").format = titleFormat;
  sheet.getRange("A2:J2").format = subtitleFormat;
  sheet.getRange("A1:J1").format.rowHeight = 30;
  sheet.getRange("A2:J2").format.rowHeight = 24;
}

summary.getRange("A4:A8").format = { fill: "#E0E7FF", font: { bold: true, color: "#312E81" } };
summary.getRange("B4:B8").format = { fill: "#FFFFFF", font: { bold: true, color: "#111827", size: 14 }, numberFormat: "#,##0" };
summary.getRange("D4:D8").format = { fill: "#D1FAE5", font: { bold: true, color: "#065F46" } };
summary.getRange("E4:E8").format = { fill: "#FFFFFF", font: { bold: true, color: "#111827" }, numberFormat: "#,##0" };
summary.getRange("A4:B8").format.borders = { preset: "outside", style: "thin", color: "#C7D2FE" };
summary.getRange("D4:E8").format.borders = { preset: "outside", style: "thin", color: "#A7F3D0" };
summary.getRange("A10:F10").format = { fill: "#0F766E", font: { bold: true, color: "#FFFFFF" } };
summary.getRange("A11:A15").format = { font: { bold: true, color: "#4338CA" }, horizontalAlignment: "center" };
summary.getRange("B11:F15").merge(true);
summary.getRange("A11:F15").format = { wrapText: true, verticalAlignment: "center" };
summary.getRange("A17:F17").format = { fill: "#F59E0B", font: { bold: true, color: "#FFFFFF" } };
summary.getRange("A18:F20").format = { fill: "#FFFBEB", wrapText: true, font: { color: "#78350F" } };
summary.getRange("A1:F20").format.font = { name: "Aptos" };
summary.getRange("A:A").format.columnWidth = 25;
summary.getRange("B:B").format.columnWidth = 28;
summary.getRange("C:C").format.columnWidth = 3;
summary.getRange("D:D").format.columnWidth = 22;
summary.getRange("E:E").format.columnWidth = 28;
summary.getRange("F:F").format.columnWidth = 14;
summary.getRange("A11:F15").format.rowHeight = 28;
summary.getRange("A18:F20").format.rowHeight = 34;

review.getRange("A4:J4").format = { fill: "#0F766E", font: { bold: true, color: "#FFFFFF" }, wrapText: true, verticalAlignment: "center" };
review.getRange(`D5:D${4 + data.length}`).format.numberFormat = "#,##0";
review.getRange(`H5:I${4 + data.length}`).format = { fill: "#DBEAFE", font: { color: "#1E3A8A" } };
review.getRange(`C5:C${4 + data.length}`).format.wrapText = true;
review.getRange(`E5:J${4 + data.length}`).format.wrapText = true;
review.getRange(`A5:J${4 + data.length}`).format.verticalAlignment = "top";
review.getRange("A:A").format.columnWidth = 7;
review.getRange("B:B").format.columnWidth = 12;
review.getRange("C:C").format.columnWidth = 26;
review.getRange("D:D").format.columnWidth = 11;
review.getRange("E:E").format.columnWidth = 34;
review.getRange("F:F").format.columnWidth = 11;
review.getRange("G:G").format.columnWidth = 14;
review.getRange("H:H").format.columnWidth = 34;
review.getRange("I:I").format.columnWidth = 13;
review.getRange("J:J").format.columnWidth = 52;
review.getRange(`A5:J${4 + data.length}`).format.rowHeight = 42;
review.getRange(`F5:F${4 + data.length}`).conditionalFormats.add("containsText", { text: "High", format: { fill: "#DCFCE7", font: { color: "#166534", bold: true } } });
review.getRange(`F5:F${4 + data.length}`).conditionalFormats.add("containsText", { text: "Low", format: { fill: "#FEE2E2", font: { color: "#991B1B", bold: true } } });
review.getRange(`G5:G${4 + data.length}`).conditionalFormats.add("containsText", { text: "Safe", format: { fill: "#DCFCE7", font: { color: "#166534", bold: true } } });
review.getRange(`G5:G${4 + data.length}`).conditionalFormats.add("containsText", { text: "Hold", format: { fill: "#FEF3C7", font: { color: "#92400E", bold: true } } });
review.getRange(`I5:I${4 + data.length}`).conditionalFormats.add("containsText", { text: "Approved", format: { fill: "#DCFCE7", font: { color: "#166534", bold: true } } });
review.getRange(`I5:I${4 + data.length}`).conditionalFormats.add("containsText", { text: "Hold", format: { fill: "#FEF3C7", font: { color: "#92400E", bold: true } } });
review.getRange(`I5:I${4 + data.length}`).conditionalFormats.add("containsText", { text: "Rejected", format: { fill: "#FEE2E2", font: { color: "#991B1B", bold: true } } });

master.getRange("A4:C4").format = { fill: "#0F766E", font: { bold: true, color: "#FFFFFF" } };
master.getRange("A:A").format.columnWidth = 62;
master.getRange("B:B").format.columnWidth = 14;
master.getRange("C:C").format.columnWidth = 34;
master.getRange(`A5:C${4 + phase3Master.length}`).format.rowHeight = 24;
master.getRange(`B5:B${4 + phase3Master.length}`).conditionalFormats.add("containsText", { text: "Inactive", format: { fill: "#FEE2E2", font: { color: "#991B1B", bold: true } } });

const reviewThread = workbook.comments.addThread({ cell: review.getRange("H5") }, "Select the final approved Phase 3 service from the dropdown only after reviewing the proposed mapping and notes.");
reviewThread.addReply("Rows left Pending will not be treated as approved mappings.");

await fs.mkdir(outputDir, { recursive: true });
const previewSummary = await workbook.render({ sheetName: "Summary", range: "A1:F20", scale: 1.5, format: "png" });
await fs.writeFile(`${outputDir}/summary-preview.png`, new Uint8Array(await previewSummary.arrayBuffer()));
const previewReview = await workbook.render({ sheetName: "Mapping Review", range: "A1:J16", scale: 1, format: "png" });
await fs.writeFile(`${outputDir}/mapping-preview.png`, new Uint8Array(await previewReview.arrayBuffer()));
const previewMaster = await workbook.render({ sheetName: "Reporting Standards", range: "A1:C20", scale: 1.2, format: "png" });
await fs.writeFile(`${outputDir}/master-preview.png`, new Uint8Array(await previewMaster.arrayBuffer()));

const output = await SpreadsheetFile.exportXlsx(workbook);
await output.save(`${outputDir}/Legacy-Service-Mapping-Review-Final.xlsx`);

const check = await workbook.inspect({ kind: "table", range: "Summary!A1:F20", include: "values,formulas", tableMaxRows: 25, tableMaxCols: 8 });
const reviewCheck = await workbook.inspect({ kind: "table", range: "'Mapping Review'!A1:J12", include: "values,formulas", tableMaxRows: 15, tableMaxCols: 12 });
const errors = await workbook.inspect({ kind: "match", searchTerm: "#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A", options: { useRegex: true, maxResults: 100 }, summary: "final formula error scan" });
console.log(JSON.stringify({ rows: rows.length, records: rows.reduce((s, r) => s + r[2], 0), check: check.ndjson, reviewCheck: reviewCheck.ndjson, errors: errors.ndjson }));
