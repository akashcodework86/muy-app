from pathlib import Path
from pypdf import PdfReader

pdf_path = Path(r"C:\xampp\htdocs\muy-app\MUY QPR April - June 2026.pdf")
out_path = Path(r"C:\xampp\htdocs\muy-app\tmp\qpr_apr_jun_2026.txt")

reader = PdfReader(str(pdf_path))
parts = []
for index, page in enumerate(reader.pages, start=1):
    parts.append(f"\n\n===== PAGE {index} =====\n")
    parts.append(page.extract_text() or "")

out_path.parent.mkdir(parents=True, exist_ok=True)
out_path.write_text("".join(parts), encoding="utf-8")
print(f"Wrote {out_path} ({len(reader.pages)} pages)")
