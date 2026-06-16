#!/usr/bin/env python3
"""One-time generator: reads bundled Excel files and writes PHP config (no server upload)."""
import zipfile
import xml.etree.ElementTree as ET
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
STATE_XLSX = ROOT / 'State Target Month Wise.xlsx'
DISTRICT_XLSX = ROOT / 'District Target Month Wise.xlsx'

DISTRICT_NAME_ONLY = {
    'almora', 'bageshwar', 'chamoli', 'champawat', 'dehradun', 'pauri-garhwal',
    'haridwar', 'nainital', 'pithoragarh', 'rudraprayag', 'tehri-garhwal',
    'udham-singh-nagar', 'uttarkashi', 'total',
}

DISTRICT_ALIASES = {
    'Almora': 'almora', 'Bageshwar': 'bageshwar', 'Chamoli': 'chamoli', 'Champawat': 'champawat',
    'Dehradun': 'dehradun', 'Pauri': 'pauri-garhwal', 'Haridwar': 'haridwar', 'Nainital': 'nainital',
    'Pithoragarh': 'pithoragarh', 'Rudraprayag': 'rudraprayag', 'Tehri': 'tehri-garhwal',
    'U.S Nagar': 'udham-singh-nagar', 'Uttarkashi': 'uttarkashi',
}

HUB_ALIASES = {'Kumaon': 'kumaon', 'Garhwal': 'garhwal'}

DISTRICT_BLOCK_MIS_SERIAL = {
    'call for application': '1.1',
    'district level workshops': '1.2',
    'no. of awareness cum outreach activities': '1.3',
    'participants in awareness cum outreach activities': '1.3.1',
    'eap/edp sessions': '1.4',
    'onboarding': '2.1',
    'onboarding of potential lakhpati': '2.1.1',
    'business skills training sessions': '3.1',
    'incubatees taken part in business modules training': '3.2',
    'technical trainings to incubatees': '3.3',
    'business registration': '4.1.1',
    'artisan card': '4.2.1',
    'fssai': '4.2.2',
    'utdb': '4.2.3',
    'gst registration': '4.2.4',
    'trademark': '4.2.5',
    'gi seller': '4.2.6',
    'incubatees linked to online/offline market': '6.3',
    'marketing support': '6.3',
    'schematic convergence': '8.1',
    'business model canvas': '9.1',
    'bmc': '9.1',
}


def read_sheet(path: Path):
    with zipfile.ZipFile(path) as z:
        shared = []
        root = ET.fromstring(z.read('xl/sharedStrings.xml'))
        ns = {'m': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
        for si in root.findall('.//m:si', ns):
            shared.append(''.join((t.text or '') for t in si.findall('.//m:t', ns)))
        root = ET.fromstring(z.read('xl/worksheets/sheet1.xml'))
        rows = []
        for row in root.findall('.//m:sheetData/m:row', ns):
            vals = []
            for c in row.findall('m:c', ns):
                t = c.get('t')
                v = c.find('m:v', ns)
                val = v.text if v is not None else ''
                if t == 's' and val != '':
                    val = shared[int(val)]
                vals.append(str(val).replace('\xa0', ' ').strip())
            rows.append(vals)
    return rows


def parse_int(v):
    v = str(v).strip()
    if not v:
        return None
    if re.match(r'^-?\d+(\.\d+)?$', v):
        return int(round(float(v)))
    return None


def clean_serial(s):
    s = str(s).strip()
    if not s:
        return ''
    try:
        f = float(s)
        text = f"{f:.12f}".rstrip('0').rstrip('.')
        if '.' in text:
            a, b = text.split('.', 1)
            return f"{int(a)}.{b}"
        return str(int(f))
    except ValueError:
        return s


def norm_name(s: str) -> str:
    return re.sub(r'\s+', ' ', s.lower().strip().replace('*', ''))


def php_str(s: str) -> str:
    return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"


def php_export(data, indent=0) -> str:
    sp = ' ' * indent
    if isinstance(data, dict):
        if not data:
            return '[]'
        lines = ['[']
        for k, v in data.items():
            key = php_str(str(k)) if isinstance(k, str) else str(k)
            lines.append(f"{sp}    {key} => {php_export(v, indent + 4)},")
        lines.append(f"{sp}]")
        return '\n'.join(lines)
    if isinstance(data, list):
        if not data:
            return '[]'
        if all(isinstance(x, (int, float)) for x in data):
            return '[' + ', '.join(str(int(x)) for x in data) + ']'
        lines = ['[']
        for item in data:
            lines.append(f"{sp}    {php_export(item, indent + 4)},")
        lines.append(f"{sp}]")
        return '\n'.join(lines)
    if isinstance(data, bool):
        return 'true' if data else 'false'
    if isinstance(data, int):
        return str(data)
    if data is None:
        return 'null'
    return php_str(str(data))


def parse_state():
    rows_in = read_sheet(STATE_XLSX)
    out = []
    for r in rows_in[1:]:
        if len(r) < 10:
            continue
        if r[1].isdigit() and parse_int(r[3]) is None and '.' not in r[1]:
            out.append({'row_type': 'category', 'serial': r[1], 'name': r[2]})
            continue
        if '.' in r[1] and parse_int(r[3]) is None and all(parse_int(x) is None for x in r[5:17]):
            out.append({'row_type': 'subcategory', 'serial': clean_serial(r[1]), 'name': r[2]})
            continue
        if r[0].isdigit() and '.' in r[1] and parse_int(r[3]) is not None:
            months = [parse_int(x) or 0 for x in (r[5:17] + ['0'] * 12)[:12]]
            out.append({
                'row_type': 'leaf',
                'sn': r[0],
                'serial': clean_serial(r[1]),
                'name': r[2],
                'total': parse_int(r[3]),
                'indicator_type': r[4],
                'months': months,
                'remark': r[17] if len(r) > 17 else '',
            })
    return out


def guess_mis_serial(name: str) -> str:
    n = norm_name(name)
    for key, serial in DISTRICT_BLOCK_MIS_SERIAL.items():
        if key in n:
            return serial
    return ''


def parse_district():
    rows_in = read_sheet(DISTRICT_XLSX)
    blocks = []
    i = 0
    while i < len(rows_in):
        r = rows_in[i]
        if len(r) > 1 and r[1] == 'District':
            title_serial = ''
            title_name = ''
            for j in range(i - 1, max(i - 6, -1), -1):
                prev = rows_in[j]
                if len(prev) < 2:
                    continue
                if prev[1] in ('District', 'Total') or prev[1].startswith('M1'):
                    continue
                if prev[0].isdigit() and prev[1]:
                    title_serial = prev[0]
                    title_name = prev[1]
                    break
                if prev[1] and not parse_int(prev[2] if len(prev) > 2 else ''):
                    title_name = prev[1]
                    if prev[0].isdigit():
                        title_serial = prev[0]
                    break
            block = {
                'excel_sn': title_serial,
                'mis_serial': guess_mis_serial(title_name),
                'name': title_name.strip(),
                'scope': 'district',
                'districts': {},
                'hubs': {},
            }
            i += 1
            while i < len(rows_in):
                rr = rows_in[i]
                if len(rr) < 2 or rr[1] == '':
                    break
                if rr[1] == 'District':
                    break
                if rr[1] == 'Total':
                    i += 1
                    continue
                months = [parse_int(x) or 0 for x in (rr[2:14] + [''] * 12)[:12]]
                dn = rr[1]
                if dn in HUB_ALIASES:
                    block['hubs'][HUB_ALIASES[dn]] = months
                    block['scope'] = 'hub'
                elif dn in DISTRICT_ALIASES:
                    block['districts'][DISTRICT_ALIASES[dn]] = months
                i += 1
            title_norm = norm_name(title_name)
            if title_norm in DISTRICT_NAME_ONLY or title_norm.replace(' ', '-') in DISTRICT_NAME_ONLY:
                continue
            if not block['mis_serial'] and title_serial.isdigit():
                block['mis_serial'] = guess_mis_serial(title_name)
            if block['districts'] or block['hubs']:
                if block['hubs'] and not block['districts']:
                    block['scope'] = 'hub'
                if block['mis_serial']:
                    blocks.append(block)
            continue
        i += 1

    state_rows = []
    partner_outreach_count = 0
    for r in rows_in:
        if len(r) < 15:
            continue
        if not r[0].isdigit() or parse_int(r[2]) is None:
            continue
        name = r[1]
        if name in DISTRICT_ALIASES or name in ('District', 'Total', 'M1', ''):
            continue
        months = [parse_int(x) or 0 for x in (r[2:14] + [''] * 12)[:12]]
        level = r[15] if len(r) > 15 else ''
        n = norm_name(name)
        mis_serial = ''
        if 'partners outreach' in n:
            partner_outreach_count += 1
            mis_serial = ['6.1', '7.1', '8.5'][partner_outreach_count - 1] if partner_outreach_count <= 3 else ''
        elif 'marketing partners onboarded' in n:
            mis_serial = '6.2'
        elif 'acceleration and co-incubation' in n:
            mis_serial = '7.2'
        elif 'reap' in n:
            mis_serial = '8.2'
        elif 'pitch deck' in n:
            mis_serial = '8.3'
        elif 'demo days' in n:
            mis_serial = '8.4'
        elif 'social media' in n:
            mis_serial = '10.1'
        elif 'case studies' in n:
            mis_serial = '10.2'
        elif 'product development' in n or 'proposal for new product' in n:
            mis_serial = '11.1'
        elif 'stakeholder consultation' in n:
            mis_serial = '12.1'
        elif 'line department' in n:
            mis_serial = '12.2'
        state_rows.append({
            'excel_sn': r[0],
            'mis_serial': mis_serial,
            'name': name,
            'months': months,
            'total': parse_int(r[14]) or sum(months),
            'level': level,
        })
    return blocks, state_rows


def write_config(path: Path, header: str, data):
    body = php_export(data, 0)
    path.write_text(
        "<?php\n\n"
        f"/**\n * {header}\n"
        " * Generated from official Excel — do not upload Excel on server.\n"
        " * Regenerate: python3 scripts/generate_official_monthly_configs.py\n"
        " */\n"
        f"return {body};\n"
    )


def main():
    state_rows = parse_state()
    district_blocks, state_only = parse_district()
    write_config(
        ROOT / 'config/official_state_monthly_targets.php',
        'Official state monthly targets (M1–M12) — State Target Month Wise.xlsx',
        {'rows': state_rows},
    )
    write_config(
        ROOT / 'config/official_district_monthly_targets.php',
        'Official district/hub monthly targets — District Target Month Wise.xlsx',
        {'district_blocks': district_blocks, 'state_only_rows': state_only},
    )
    print('Wrote configs:', len(state_rows), 'state rows,', len(district_blocks), 'district blocks,', len(state_only), 'state-only rows')


if __name__ == '__main__':
    main()
