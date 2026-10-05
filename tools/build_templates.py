"""
สร้างภาพพื้นหลังแบบฟอร์ม (templates/*.svg) จากไฟล์ Word ต้นฉบับ
ใช้เมื่อมีการแก้แบบฟอร์ม: วางไฟล์ใหม่ทับ templates/alc.docx หรือ templates/classroom.docx แล้วรัน

    python tools/build_templates.py

ต้องการ: Windows + Microsoft Word (แปลง docx→pdf ให้ตรงต้นฉบับ), pip install pymupdf
ถ้าตำแหน่งช่องในแบบฟอร์มเปลี่ยน ให้ปรับพิกัดใน inc/form_layout.php ด้วย (รันด้วย --fields เพื่อดูพิกัดเส้นจุด)
"""
import os, re, subprocess, sys
import fitz  # PyMuPDF

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TPL = os.path.join(ROOT, 'templates')


def docx_to_pdf(docx, pdf):
    # เรียก Word ผ่าน PowerShell COM (17 = wdExportFormatPDF)
    ps = ("$w = New-Object -ComObject Word.Application; $w.Visible = $false; "
          f"try {{ $d = $w.Documents.Open('{docx}', $false, $true); $d.ExportAsFixedFormat('{pdf}', 17); $d.Close($false) }} "
          "finally { $w.Quit() }")
    subprocess.run(['powershell', '-NoProfile', '-Command', ps], check=True)


def print_fields(page):
    for b in page.get_text('rawdict')['blocks']:
        for l in b.get('lines', []):
            for s in l['spans']:
                ch = s['chars']
                t = ''.join(c['c'] for c in ch)
                for m in re.finditer(r'\.{3,}|\(\s*\)', t):
                    a, z = m.start(), m.end() - 1
                    print(f"baseline {ch[a]['origin'][1]:6.1f}  x {ch[a]['bbox'][0]:6.1f}-{ch[z]['bbox'][2]:6.1f}  "
                          f"{'BOX' if t[a] == '(' else 'DOT'}  after: {t[max(0, a - 16):a].strip()}")


for name in ('alc', 'classroom'):
    docx = os.path.join(TPL, name + '.docx')
    pdf = os.path.join(TPL, name + '.pdf')
    docx_to_pdf(docx, pdf)
    page = fitz.open(pdf)[0]
    svg = page.get_svg_image(text_as_path=True)
    with open(os.path.join(TPL, name + '.svg'), 'w', encoding='utf-8') as f:
        f.write(svg)
    print(f'{name}: {page.rect.width:.2f}x{page.rect.height:.2f}pt -> templates/{name}.svg ({len(svg) // 1024} KB)')
    if '--fields' in sys.argv:
        print_fields(page)
