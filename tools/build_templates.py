"""
สร้างภาพพื้นหลังแบบฟอร์ม (forms/<code>/template.svg) จากไฟล์ Word ต้นฉบับ (forms/<code>/template.docx)

    python tools/build_templates.py                       ทุกแบบฟอร์ม
    python tools/build_templates.py leave_request         เฉพาะฟอร์มที่ระบุ
    python tools/build_templates.py leave_request --fields     แสดงพิกัดเส้นจุด/วงเล็บ ( ) ทั้งหมด
    python tools/build_templates.py leave_request --scaffold   ร่าง 'layout' สำหรับวางใน form.php
    เพิ่ม --no-word  ใช้ template.pdf ที่มีอยู่ (ไม่แปลงจาก docx ใหม่)

ต้องการ: Windows + Microsoft Word (แปลง docx→pdf ให้ตรงต้นฉบับ), pip install pymupdf
"""
import glob, os, re, subprocess, sys
import fitz  # PyMuPDF

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FORMS = os.path.join(ROOT, 'forms')


def docx_to_pdf(docx, pdf):
    # เรียก Word ผ่าน PowerShell COM (17 = wdExportFormatPDF)
    ps = ("$w = New-Object -ComObject Word.Application; $w.Visible = $false; "
          f"try {{ $d = $w.Documents.Open('{docx}', $false, $true); $d.ExportAsFixedFormat('{pdf}', 17); $d.Close($false) }} "
          "finally { $w.Quit() }")
    subprocess.run(['powershell', '-NoProfile', '-Command', ps], check=True)


def find_fields(page):
    """เส้นจุด (DOT) และวงเล็บ ( ) (BOX) พร้อมข้อความที่อยู่หน้าช่อง — หน่วย pt"""
    out = []
    for b in page.get_text('rawdict')['blocks']:
        for l in b.get('lines', []):
            for s in l['spans']:
                ch = s['chars']
                t = ''.join(c['c'] for c in ch)
                for m in re.finditer(r'\.{3,}|\(\s*\)', t):
                    a, z = m.start(), m.end() - 1
                    out.append({
                        'kind': 'BOX' if t[a] == '(' else 'DOT',
                        'x0': ch[a]['bbox'][0], 'x1': ch[z]['bbox'][2], 'y': ch[a]['origin'][1],
                        'label': re.sub(r'[.\s]+$', '', t[max(0, a - 24):a]).strip(' .()'),
                    })
    return sorted(out, key=lambda f: (round(f['y']), f['x0']))


def print_fields(fields):
    for f in fields:
        print(f"  baseline {f['y']:6.1f}  x {f['x0']:6.1f}-{f['x1']:6.1f}  {f['kind']}  after: {f['label']}")


def print_scaffold(code, fields):
    print(f"\n    // ร่างจาก tools/build_templates.py --scaffold {code} — ตั้งชื่อ key ให้ตรงกับค่าที่ 'values' คืนมา")
    print("    // แล้วลบช่องที่เป็นของเจ้าหน้าที่/ผู้อนุมัติ (ไม่ต้องให้ผู้ขอกรอก) ออก")
    print("    'layout' => [")
    for i, f in enumerate(fields, 1):
        key = ('box_%02d' if f['kind'] == 'BOX' else 'field_%02d') % i
        pad = 2 if f['kind'] == 'DOT' else 0  # เว้นระยะจากข้อความหน้าช่องเล็กน้อย
        align = 'c' if f['kind'] == 'BOX' else 'l'
        print(f"        '{key}' => [{f['x0'] + pad:.1f}, {f['x1']:.1f}, {f['y']:.1f}, '{align}'],  // {f['label']}")
    print("    ],")


args = [a for a in sys.argv[1:] if not a.startswith('--')]
dirs = [os.path.join(FORMS, c) for c in args] or sorted(d for d in glob.glob(os.path.join(FORMS, '*')) if os.path.isdir(d))

for d in dirs:
    code = os.path.basename(d)
    docx, pdf = os.path.join(d, 'template.docx'), os.path.join(d, 'template.pdf')
    if '--no-word' not in sys.argv and os.path.isfile(docx):
        docx_to_pdf(docx, pdf)
    if not os.path.isfile(pdf):
        print(f'{code}: ข้าม — ไม่มี template.docx หรือ template.pdf')
        continue
    doc = fitz.open(pdf)
    if len(doc) != 1:
        print(f'{code}: คำเตือน — แบบฟอร์มมี {len(doc)} หน้า ระบบรองรับหน้าเดียว ใช้หน้าแรก')
    page = doc[0]
    svg = page.get_svg_image(text_as_path=True)
    with open(os.path.join(d, 'template.svg'), 'w', encoding='utf-8') as f:
        f.write(svg)
    print(f'{code}: {page.rect.width:.2f}x{page.rect.height:.2f}pt -> forms/{code}/template.svg ({len(svg) // 1024} KB)')
    fields = find_fields(page)
    if '--fields' in sys.argv:
        print_fields(fields)
    if '--scaffold' in sys.argv:
        print_scaffold(code, fields)
