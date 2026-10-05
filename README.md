# ระบบแบบฟอร์มออนไลน์ (E-Form) → DMS

ผู้ใช้ล็อกอินด้วย UP Account (SSO MSC_ACC) เลือกแบบฟอร์ม กรอกข้อมูล แล้วกด **ส่งเข้าระบบ DMS**
ระบบจะสร้าง PDF บนแบบฟอร์มกระดาษต้นฉบับ แล้วพาผู้ใช้ไปลงทะเบียนเอกสารใน DMS ตาม [DMS_Connect.md](DMS_Connect.md)
ผู้ดูแลระบบดู ค้นหา และส่งออกเอกสารของทุกคนได้ที่ `admin/`

แบบฟอร์มที่มีตอนนี้ (`forms/`)
- `room_alc` — แบบฟอร์มการขออนุมัติใช้ห้อง Active Learning Classroom
- `room_classroom` — แบบฟอร์มการขออนุมัติใช้ห้องเรียน / ห้องเรียน Hybrid Classroom

## ลำดับการทำงาน

1. ผู้ใช้กรอกฟอร์ม → `form.php?form=<code>` (ตรวจช่องบังคับ, จองล่วงหน้า N วันทำการ, เวลาเริ่ม < เวลาสิ้นสุด ตามที่ฟอร์มกำหนด)
2. ดูตัวอย่างที่ `print.php?ref=..` แล้วกด "ส่งเข้าระบบ DMS" → `POST dms_pdf.php?ref=..&generate=1`
   ตรวจเงื่อนไขซ้ำ → รัน Chrome Headless เปิด `print.php` แล้วบันทึก `uploads/dms/{ref}.pdf` → ตอบ `{"success":true,"redirect":...}`
3. เบราว์เซอร์ไปที่ `https://dms.up.ac.th/dms_main/data/ck_link_conect.aspx?ref={ref}&con=..&sub=..` (con/sub ของฟอร์มนั้น)
4. DMS เรียก Connect Path `dms_pdf.php?ref={ref}` → ส่งไฟล์ PDF ที่สร้างไว้ทันที
   เมื่อ DMS ดึงไฟล์แล้ว เอกสารจะถูกล็อก แก้ไขหรือลบไม่ได้

## เพิ่มแบบฟอร์มใหม่

1. สร้างโฟลเดอร์ `forms/<code>/` (`code` เป็นภาษาอังกฤษตัวเล็ก/ตัวเลข/`_` เช่น `leave_request`) แล้ววางไฟล์ Word ต้นฉบับเป็น `template.docx` (1 หน้า A4)
2. สร้างภาพพื้นหลังและร่างพิกัดเส้นจุด (ต้องมี Microsoft Word และ `pip install pymupdf`):
   ```bash
   python tools/build_templates.py leave_request --scaffold
   ```
   ได้ `template.svg` และร่าง `'layout' => [...]` ของทุกเส้นจุด/วงเล็บ ( ) พร้อมข้อความหน้าช่องเป็นคอมเมนต์
3. คัดลอก `forms/room_alc/form.php` เป็น `forms/<code>/form.php` แล้วแก้:
   - `title`, `short`, `category`, `order`, `dms` (con/sub ที่ได้จาก `connect_edit.aspx` ดูขั้นตอนที่ 5 ใน DMS_Connect.md)
   - `sections` → ช่องที่ให้ผู้ใช้กรอก (ใช้ `requester_fields()` สำหรับข้อมูลผู้ขอ) — ชนิดช่องและตัวเลือกดูหัวไฟล์ `inc/forms.php`
   - `layout` → วางร่างจากข้อ 2 ตั้งชื่อ key ใหม่ และลบช่องของผู้อนุมัติ/เจ้าหน้าที่ออก
   - `values` → ฟังก์ชันแปลงข้อมูลที่กรอกเป็นค่าตาม key ใน `layout` (เช่น `'box_' . $d['purpose'] => '✓'`)
   - `summary` → ข้อความสั้นที่แสดงในรายการเอกสาร
4. เปิดหน้าแรก ฟอร์มใหม่จะขึ้นเอง ลองกรอกแล้วกด "ดูตัวอย่างเอกสาร" ปรับพิกัดใน `layout` จนข้อความลงเส้นจุดพอดี
5. `git add forms/<code>` แล้ว commit/push — บนเซิร์ฟเวอร์ `git pull` อย่างเดียว ไม่ต้องแก้ฐานข้อมูล

ถ้าแก้ไฟล์ Word ของฟอร์มเดิม รัน `python tools/build_templates.py <code> --fields` แล้วตรวจว่าพิกัดเส้นจุดยังตรงกับ `layout`

## ติดตั้งบน IIS

1. Clone โค้ดลงโฟลเดอร์เว็บไซต์ (PHP 8.1 ขึ้นไป, เปิด `pdo_mysql`, `mbstring`, `curl`)
   ```
   cd C:\inetpub\wwwroot
   git clone https://github.com/plugdkt/REG_Room_Booking.git eform
   ```
   อัปเดตครั้งถัดไปใช้ `git pull` (ไม่กระทบ `config.php` และไฟล์ PDF ใน `uploads/dms` เพราะอยู่ใน `.gitignore`)
2. สร้างฐานข้อมูล: `mysql -u root -p < sql/schema.sql` (ใช้กับ MariaDB ได้) แล้วสร้าง user ที่มีสิทธิ์เฉพาะ DB นี้
3. `copy config.sample.php config.php` แล้วแก้ค่า
   - `base_url` — URL สาธารณะ, `internal_base_url` — เว้นว่างไว้ (ใช้ `base_url`)
   - `app_secret` — ค่าสุ่มยาว เช่นจาก `php -r "echo bin2hex(random_bytes(32));"`
   - `auth` — `sso` (MSC_ACC: ใส่ `client_id`/`client_secret`) หรือ `ldap`
   - `admins` — UP Account ที่เข้าหน้าผู้ดูแลระบบได้
   - `holidays` — วันหยุดราชการที่ไม่นับเป็นวันทำการ (เพิ่มทุกปี), `faculty` — สังกัดที่พิมพ์ในแบบฟอร์ม
4. ให้สิทธิ์ **Modify** กับ `uploads\dms` (รวม `chrome_profile`) แก่ `IIS_IUSRS` / identity ของ App Pool
   ```
   icacls "C:\inetpub\wwwroot\eform\uploads\dms" /grant "IIS_IUSRS:(OI)(CI)M"
   ```
5. ติดตั้ง Google Chrome — ฟอนต์ TH Sarabun New ฝังมากับระบบแล้ว (`assets/fonts`) ไม่ต้องติดตั้งบนเซิร์ฟเวอร์
6. FastCGI ของ PHP ต้องมี `maxInstances` มากกว่า 1 เพราะระหว่างสร้าง PDF จะมี request ซ้อน (Chrome เรียก `print.php` กลับมา)
7. ลงทะเบียน Connect Path กับ DMS ที่ https://dms.up.ac.th/dms_main/data/connect_edit.aspx (ทุกแบบฟอร์มใช้ path เดียวกัน):
   `https://www.medsci.up.ac.th/eform/dms_pdf.php` (DMS เรียกเป็น `?ref={ref}`) — path เดิม `print_booking_pdf.php` ยังใช้ได้

`web.config` บล็อกการเข้าถึง `inc/`, `forms/`, `sql/`, `tools/`, `uploads/` และ `config.php` จากเว็บไว้แล้ว

## อัปเกรดจากระบบขอใช้ห้องเรียนเดิม (ตาราง bookings)

```bash
cd C:/inetpub/wwwroot/eform && git pull
```

```bash
php tools/migrate_v2.php
```

ตรวจรายการที่จะย้าย แล้วรันจริง (รันซ้ำได้ ข้ามรายการที่ย้ายแล้ว และไม่ลบตาราง bookings เดิม):

```bash
php tools/migrate_v2.php --run
```

จากนั้นเพิ่ม `admins`, `app_name`, `app_org` ใน `config.php` (ดู `config.sample.php`) — ค่า `dms.forms.alc/classroom` เดิมไม่ใช้แล้ว
เพราะ con/sub ย้ายไปอยู่ใน `forms/<code>/form.php` (ถ้าจะทับค่าใน config ให้ใช้รหัสฟอร์มใหม่ เช่น `dms.forms.room_alc`)

## ทดสอบบนเครื่อง (XAMPP)

ตั้ง `auth.mode = 'dev'` (ล็อกอินชื่ออะไรก็ได้) แล้วรัน PHP server 2 ตัว
(PHP built-in server บน Windows รับได้ทีละ request ตัวที่สองจึงให้ Chrome ใช้ผ่าน `internal_base_url`)

```bash
C:/xampp/php/php.exe -S localhost:8090 -t .
```

```bash
C:/xampp/php/php.exe -S 127.0.0.1:8091 -t .
```

## โครงสร้างไฟล์

| ไฟล์ | หน้าที่ |
|---|---|
| `index.php` | เลือกแบบฟอร์ม + รายการเอกสารของฉัน |
| `form.php` | สร้าง/แก้ไขเอกสาร (สร้างช่องกรอกจากนิยามฟอร์ม) |
| `view.php` | รายละเอียด, แก้ไข, ลบ |
| `print.php` | หน้าเอกสาร A4 (แบบฟอร์มต้นฉบับ + ข้อมูล) ปุ่มส่ง DMS และหน้าที่ Chrome แปลงเป็น PDF |
| `dms_pdf.php` | สร้าง PDF (AJAX) และ Connect Path ให้ DMS ดึงไฟล์ |
| `admin/` | หน้าผู้ดูแลระบบ: ค้นหา/กรองเอกสารทั้งหมด, ส่งออก Excel (`export.php`) |
| `forms/<code>/` | นิยามแบบฟอร์ม (`form.php`) + ต้นฉบับ (`template.docx/.pdf/.svg`) |
| `forms/_requester.php` | ช่องข้อมูลผู้ขอที่ใช้ร่วมกันทุกฟอร์ม |
| `inc/forms.php` | โหลดฟอร์ม, สร้างช่องกรอก, ตรวจข้อมูล |
| `inc/submissions.php` | เอกสาร (ตาราง submissions), DMS, สร้าง PDF, สิทธิ์ผู้ดูแล |
| `inc/auth.php`, `sso_callback.php` | ล็อกอิน (SSO MSC_ACC / LDAP) |
| `tools/build_templates.py` | แปลง Word → พื้นหลัง SVG และหาพิกัดเส้นจุด |
| `tools/migrate_v2.php` | ย้ายข้อมูลจากตาราง bookings เดิม |
| `print_booking*.php`, `booking_*.php` | URL เดิม ส่งต่อไปหน้าใหม่ (คงไว้เพราะ Connect Path ของ DMS) |
