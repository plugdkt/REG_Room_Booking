# ระบบขออนุมัติใช้ห้องเรียน → DMS

ผู้ใช้ล็อกอินด้วย UP Account กรอกแบบฟอร์มขอใช้ห้อง แล้วกด **ส่งเข้าระบบ DMS** ระบบจะสร้าง PDF ตามแบบฟอร์มกระดาษ แล้วพาผู้ใช้ไปลงทะเบียนเอกสารใน DMS ตาม [DMS_Connect.md](DMS_Connect.md)

แบบฟอร์มที่รองรับ
- `alc` — แบบฟอร์มการขออนุมัติใช้ห้อง Active Learning Classroom
- `classroom` — แบบฟอร์มการขออนุมัติใช้ห้องเรียน / ห้องเรียน Hybrid Classroom

## ลำดับการทำงาน

1. ผู้ใช้กรอกฟอร์ม → `booking_form.php` (ตรวจว่าต้องจองล่วงหน้า ≥ 3 วันทำการ, เวลาเริ่ม < เวลาสิ้นสุด)
2. กด "ส่งเข้าระบบ DMS" ที่ `booking_view.php` → `POST print_booking_pdf.php?ref=..&generate=1`
   รัน Chrome Headless เปิด `print_booking.php` (URL มีลายเซ็น HMAC) แล้วบันทึก `uploads/dms/{ref}.pdf` → ตอบ `{"success":true,"redirect":...}`
3. เบราว์เซอร์ redirect ไป `https://dms.up.ac.th/dms_main/data/ck_link_conect.aspx?ref={ref}&con=..&sub=..`
4. DMS เรียก Connect Path `print_booking_pdf.php?ref={ref}` → ส่งไฟล์ PDF ที่สร้างไว้ทันที (ถ้าไม่มีไฟล์จะสร้างใหม่เป็นระบบสำรอง)
   เมื่อ DMS ดึงไฟล์แล้ว เอกสารจะถูกล็อก แก้ไขหรือลบไม่ได้

## ติดตั้งบน IIS

1. Clone โค้ดลงโฟลเดอร์เว็บไซต์ (PHP 8.1 ขึ้นไป, เปิด `pdo_mysql`, `mbstring` และ `ldap` ถ้าใช้ LDAP)
   ```
   cd C:\inetpub\wwwroot
   git clone https://github.com/plugdkt/REG_Room_Booking.git eform
   ```
   อัปเดตครั้งถัดไปใช้ `git pull` (ไม่กระทบ `config.php` และไฟล์ PDF ใน `uploads/dms` เพราะอยู่ใน `.gitignore`)
2. สร้างฐานข้อมูล: `mysql -u root -p < sql/schema.sql` (ใช้กับ MariaDB ได้เหมือนกัน) แล้วสร้าง user ที่มีสิทธิ์เฉพาะ DB นี้
3. `copy config.sample.php config.php` แล้วแก้ค่า
   - `base_url` — URL สาธารณะ, `internal_base_url` — URL ที่เซิร์ฟเวอร์เปิดหาตัวเองได้ (ปกติ `http://localhost/eform`)
   - `app_secret` — ค่าสุ่มยาว เช่นจาก `php -r "echo bin2hex(random_bytes(32));"`
   - `auth` — ตั้งค่า LDAP ของมหาวิทยาลัย (ขอค่า `uri`, `bind_format`, `base_dn` จากศูนย์เทคโนโลยีสารสนเทศ)
   - `dms.forms.*.con/sub` — รับค่าจากหน้าตั้งค่าการเชื่อมต่อ DMS https://dms.up.ac.th/dms_main/data/connect_edit.aspx (ดูขั้นตอนที่ 5 ใน DMS_Connect.md) ปุ่มส่งจะไม่แสดงจนกว่าจะกรอก
   - `holidays` — วันหยุดราชการที่ไม่นับเป็นวันทำการ
4. ให้สิทธิ์ **Modify** กับ `uploads\dms` (รวม `chrome_profile`) แก่ `IIS_IUSRS` / identity ของ App Pool
   ```
   icacls "C:\inetpub\wwwroot\eform\uploads\dms" /grant "IIS_IUSRS:(OI)(CI)M"
   ```
5. ติดตั้งฟอนต์ **TH Niramit AS** บนเซิร์ฟเวอร์ (Install for all users) — เป็นฟอนต์เดียวกับแบบฟอร์มต้นฉบับ ใช้พิมพ์ข้อมูลที่ผู้ใช้กรอก
6. FastCGI ของ PHP ต้องมี `maxInstances` มากกว่า 1 เพราะระหว่างสร้าง PDF จะมี request ซ้อน (Chrome เรียก `print_booking.php` กลับมา)
7. ลงทะเบียน Connect Path กับ DMS ที่ https://dms.up.ac.th/dms_main/data/connect_edit.aspx สำหรับทั้งสองแบบฟอร์ม:
   `https://www.medsci.up.ac.th/eform/print_booking_pdf.php` (DMS เรียกเป็น `?ref={ref}`)

`web.config` บล็อกการเข้าถึง `inc/`, `sql/`, `uploads/` และ `config.php` จากเว็บไว้แล้ว

## แบบฟอร์ม (ตรงตามต้นฉบับ)

PDF ใช้แบบฟอร์ม Word ต้นฉบับเป็นพื้นหลังทั้งหน้า (`templates/alc.svg`, `templates/classroom.svg` แปลงจาก `templates/*.docx` ด้วย Microsoft Word)
แล้ววางข้อมูลที่ผู้ใช้กรอกลงบนเส้นจุดตามพิกัดใน `inc/form_layout.php` — ข้อความที่ยาวเกินเส้นจุดจะถูกบีบให้พอดีช่อง

ถ้าต้องแก้แบบฟอร์ม: แก้ไฟล์ใน `templates/*.docx` แล้วรัน (ต้องมี Word และ `pip install pymupdf`)

```bash
python tools/build_templates.py --fields
```

ถ้าตำแหน่งเส้นจุดเปลี่ยน ให้นำพิกัดที่พิมพ์ออกมาไปแก้ใน `inc/form_layout.php`

## ทดสอบบนเครื่อง (XAMPP)

ตั้ง `auth.mode = 'dev'` (ล็อกอินชื่ออะไรก็ได้) และใส่ con/sub เป็นค่าทดสอบ แล้วรัน PHP server 2 ตัว
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
| `booking_form.php` | สร้าง/แก้ไขเอกสาร |
| `booking_view.php` | รายละเอียด, ปุ่มส่ง DMS, แก้ไข, ลบ |
| `print_booking.php` | หน้าเอกสาร A4 (แบบฟอร์มต้นฉบับ + ข้อมูล) สำหรับพิมพ์และให้ Chrome แปลงเป็น PDF |
| `inc/form_layout.php` | พิกัดช่องกรอกบนแบบฟอร์มต้นฉบับ |
| `templates/` | แบบฟอร์มต้นฉบับ (docx) และภาพพื้นหลัง (svg) |
| `print_booking_pdf.php` | สร้าง PDF (AJAX) และ Connect Path ให้ DMS ดึงไฟล์ |
| `inc/booking.php` | ข้อมูลแบบฟอร์ม, validation, DB, Chrome Headless |
| `inc/auth.php` | ล็อกอิน UP Account (LDAP) |
| `config.sample.php` | ตัวอย่างการตั้งค่า |
