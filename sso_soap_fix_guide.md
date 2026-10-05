# คู่มือแนวทางการแก้ไขปัญหาการยืนยันตัวตน SSO / SOAP Service มหาวิทยาลัยพะเยา
## ปัญหา: ระบบแจ้งเตือน "ไม่พบข้อมูลสังกัดคณะวิชาของท่านในระบบมหาวิทยาลัย"
**คณะวิทยาศาสตร์การแพทย์ มหาวิทยาลัยพะเยา**

---

## 1. อาการของปัญหา (Problem Symptoms)
ผู้ใช้งาน (บุคลากร) กรอกชื่อผู้ใช้ (Username) และรหัสผ่าน (Password) ถูกต้องทุกประการ แต่ระบบปฏิเสธการเข้าสู่ระบบและแสดงข้อความแจ้งเตือน:
> **"ไม่พบข้อมูลสังกัดคณะวิชาของท่านในระบบมหาวิทยาลัย"**  
> หรือ  
> **"คุณไม่ใช่บุคลากรของคณะวิทยาศาสตร์การแพทย์"**

---

## 2. สาเหตุของปัญหา (Root Causes)
ระบบตรวจสอบสิทธิ์ของมหาวิทยาลัยทำงานผ่าน 2 ขั้นตอน:
1. **AuthenService.asmx (`Login`):** ยืนยัน Username/Password หากถูกต้องจะส่งคืน `SessionID`
2. **StaffService.asmx (`GetStaffInfo`):** ใช้ `SessionID` เพื่อดึงข้อมูลโปรไฟล์บุคลากร (ชื่อ, สกุล, คณะสังกัด ฯลฯ) ในรูปแบบ XML

**สาเหตุที่เกิดข้อผิดพลาด:**
1. **การตรวจสอบแท็กตายตัว (Strict XML Tag Matching):** โค้ดเดิมมักใช้ Regex ค้นหาเฉพาะแท็ก `<Faculty>` แบบตายตัว แต่ในบางครั้ง Web Service ของมหาวิทยาลัยอาจส่งแท็กในรูปแบบอื่น เช่น `<Faculty_TH>`, `<FacultyName>`, `<Department>`, หรือ `<Division>`
2. **มีช่องว่างปนเปื้อน (Whitespace / Trailing Spaces):** ข้อความที่ตอบกลับอาจมีช่องว่าง เช่น `<Faculty>คณะวิทยาศาสตร์การแพทย์ </Faculty>` ซึ่งหากใช้การเปรียบเทียบตรงตัว (`$faculty === 'คณะวิทยาศาสตร์การแพทย์'`) โดยไม่ได้ตัดช่องว่าง (`trim`) จะทำให้ผลลัพธ์เป็นเท็จ
3. **แท็กสังกัดว่างเปล่าจากระบบกลาง:** ในบางกรณี (เช่น ข้อมูลบุคลากรอยู่ระหว่างการปรับปรุงฐานข้อมูลมหาวิทยาลัย) ระบบกลางอาจส่งแท็ก `<Faculty></Faculty>` หรือ `<Faculty />` เป็นค่าว่าง ทำให้ระบบดักจับว่าไม่พบสังกัด
4. **ขาดระบบ Fallback กับฐานข้อมูลท้องถิ่น:** แม้ผู้ใช้จะมีชื่ออยู่ในฐานข้อมูลบุคลากรของคณะฯ อยู่แล้ว และรหัสผ่านยืนยันถูกต้องผ่าน AuthenService แต่ระบบกลับตัดสิทธิ์ทันทีเพียงเพราะข้อมูล XML ขาดหาย

---

## 3. วิธีการแก้ไข (Solution & Implementation)

### 📌 จุดที่ 1: ตรวจสอบแท็กสังกัดแบบยืดหยุ่น (Multi-tag Parsing)
เปลี่ยนจากการค้นหาเฉพาะ `<Faculty>` เป็นการวนลูปตรวจสอบแท็กที่เป็นไปได้ทั้งหมด พร้อมใช้ `trim()` ตัดช่องว่าง:

```php
// Parse Faculty (รองรับหลายรูปแบบ Tag จาก UP SOAP / NUSOP)
$faculty = '';
$faculty_tags = ['Faculty', 'Faculty_TH', 'FacultyName', 'FacultyName_TH', 'Department', 'Department_TH', 'Division', 'Division_TH'];

foreach ($faculty_tags as $tag) {
    if (preg_match('/<' . $tag . '>([^<]+)<\/' . $tag . '>/i', $staff_response, $fac_matches)) {
        $val = trim($fac_matches[1]);
        if (!empty($val)) {
            $faculty = $val;
            break;
        }
    }
}
```

---

### 📌 จุดที่ 2: ใช้การค้นหาคำบางส่วน (Partial String Matching)
แทนที่จะเปรียบเทียบด้วย `===` ให้ใช้ `mb_strpos()` เพื่อตรวจหาคำสำคัญ เช่น `'วิทยาศาสตร์การแพทย์'`:

```php
$is_medsci = false;

// 1. ตรวจสอบจากค่า Faculty ที่ดึงได้
if (!empty($faculty) && mb_strpos($faculty, 'วิทยาศาสตร์การแพทย์') !== false) {
    $is_medsci = true;
} 
// 2. ค้นหาคำว่า วิทยาศาสตร์การแพทย์ จากข้อความ XML ทั้งหมดที่ตอบกลับ
elseif (mb_strpos($staff_response, 'วิทยาศาสตร์การแพทย์') !== false) {
    $is_medsci = true;
}
```

---

### 📌 จุดที่ 3: เพิ่มระบบ Fallback ตรวจสอบกับฐานข้อมูลท้องถิ่น (Local DB Verification)
หากผู้ใช้ผ่านการตรวจสอบรหัสผ่านจาก `AuthenService.asmx` แล้ว (ได้ `SessionID` ที่ถูกต้อง) และ**มีรายชื่ออยู่ในฐานข้อมูลบุคลากรของคณะฯ** ให้ถือว่ามีสิทธิ์เข้าใช้งานได้ทันที:

```php
// ตรวจสอบว่ามีรายชื่อในฐานข้อมูลของคณะฯ หรือไม่
global $db;
$is_local_user = false;

if ($db) {
    try {
        $chk_local = $db->prepare("SELECT id_user FROM user WHERE username = :username");
        $chk_local->execute(['username' => $username]);
        if ($chk_local->fetch()) {
            $is_local_user = true;
        }
    } catch (Exception $e) {
        // จัดการกรณีเกิด Exception
    }
}

// หากรหัสผ่านถูกต้อง และมีชื่อในฐานข้อมูลคณะอยู่แล้ว ให้อนุญาตเข้าสู่ระบบได้
if ($is_local_user) {
    $is_medsci = true;
}
```

---

### 📌 จุดที่ 4: เพิ่มระบบบันทึก Log ข้อมูลดิบ (Raw XML Debug Log)
เพิ่มการเขียน Log เพื่อให้ผู้ดูแลระบบสามารถเปิดดู XML ที่มหาวิทยาลัยตอบกลับมาจริงได้ตลอดเวลา:

```php
$log_dir = __DIR__;
$log_entry = sprintf(
    "[%s] USER: %s | SESSION: %s\nRAW SOAP RESPONSE:\n%s\n--------------------------------------------------\n",
    date('Y-m-d H:i:s'),
    $username,
    $sid,
    $staff_response
);
@file_put_contents($log_dir . '/soap_debug.log', $log_entry, FILE_APPEND);
```

---

## 4. โค้ดฉบับสมบูรณ์ (Complete Reference Code)

```php
<?php
/**
 * ฟังก์ชันยืนยันตัวตนผ่าน UP SOAP Service พร้อมระบบ Fallback และบันทึก Log
 */
function authenticate_soap($username, $password) {
    $user_b64 = base64_encode($username);
    $pass_b64 = base64_encode($password);
    
    // -------------------------------------------------------------
    // ขั้นตอนที่ 1: ตรวจสอบ Username & Password
    // -------------------------------------------------------------
    $authen_url = "https://ws.up.ac.th/mobile/AuthenService.asmx";
    $authen_xml = '<?xml version="1.0" encoding="utf-8"?>
    <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
      <soap:Body>
        <Login xmlns="http://tempuri.org/">
          <username>' . $user_b64 . '</username>
          <password>' . $pass_b64 . '</password>
          <ProductName>voiceofstudentstaff</ProductName>
        </Login>
      </soap:Body>
    </soap:Envelope>';

    $ch = curl_init($authen_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $authen_xml);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: text/xml; charset=utf-8",
        "SOAPAction: \"http://tempuri.org/Login\"",
        "Content-Length: " . strlen($authen_xml)
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        curl_close($ch);
        return ['status' => false, 'message' => 'ไม่สามารถเชื่อมต่อระบบเครือข่ายของมหาวิทยาลัยได้'];
    }
    curl_close($ch);

    // ดึง Session ID
    $sid = '';
    if (preg_match('/<LoginResult>([^<]+)<\/LoginResult>/', $response, $matches)) {
        $sid = trim($matches[1]);
    }

    if (empty($sid) || $sid === 'SESSION_LOCK') {
        return ['status' => false, 'message' => 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง'];
    }
    
    // -------------------------------------------------------------
    // ขั้นตอนที่ 2: ดึงข้อมูลโปรไฟล์บุคลากร
    // -------------------------------------------------------------
    $staff_url = "https://ws.up.ac.th/mobile/StaffService.asmx";
    $staff_xml = '<?xml version="1.0" encoding="utf-8"?>
    <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
      <soap:Body>
        <GetStaffInfo xmlns="http://tempuri.org/">
          <sessionID>' . $sid . '</sessionID>
        </GetStaffInfo>
      </soap:Body>
    </soap:Envelope>';

    $ch = curl_init($staff_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $staff_xml);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: text/xml; charset=utf-8",
        "SOAPAction: \"http://tempuri.org/GetStaffInfo\"",
        "Content-Length: " . strlen($staff_xml)
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $staff_response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        curl_close($ch);
        return ['status' => false, 'message' => 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ข้อมูลบุคลากรของมหาวิทยาลัยได้'];
    }
    curl_close($ch);

    if (empty($staff_response)) {
        return ['status' => false, 'message' => 'เซิร์ฟเวอร์มหาวิทยาลัยไม่ส่งข้อมูลบุคลากรกลับมา'];
    }

    // บันทึก Raw XML เพื่อตรวจสอบ
    @file_put_contents(__DIR__ . '/soap_debug.log', sprintf(
        "[%s] USER: %s | SESSION: %s\n%s\n----------------------------------------\n",
        date('Y-m-d H:i:s'), $username, $sid, $staff_response
    ), FILE_APPEND);

    // ดึงข้อมูลสังกัด (รองรับหลายชื่อแท็ก)
    $faculty = '';
    $faculty_tags = ['Faculty', 'Faculty_TH', 'FacultyName', 'FacultyName_TH', 'Department', 'Department_TH', 'Division', 'Division_TH'];
    foreach ($faculty_tags as $tag) {
        if (preg_match('/<' . $tag . '>([^<]+)<\/' . $tag . '>/i', $staff_response, $fac_matches)) {
            $val = trim($fac_matches[1]);
            if (!empty($val)) {
                $faculty = $val;
                break;
            }
        }
    }

    // ดึงชื่อและนามสกุล
    $first_name = '';
    $last_name = '';
    if (preg_match('/<FirstName_TH>([^<]+)<\/FirstName_TH>/i', $staff_response, $fn_matches)) $first_name = trim($fn_matches[1]);
    if (preg_match('/<LastName_TH>([^<]+)<\/LastName_TH>/i', $staff_response, $ln_matches)) $last_name = trim($ln_matches[1]);

    // ตรวจสอบกับฐานข้อมูลท้องถิ่น
    global $db;
    $is_local_user = false;
    if ($db) {
        try {
            $chk_local = $db->prepare("SELECT id_user FROM user WHERE username = :username");
            $chk_local->execute(['username' => $username]);
            if ($chk_local->fetch()) {
                $is_local_user = true;
            }
        } catch (Exception $e) {}
    }

    // ตรวจสอบสิทธิ์การเป็นบุคลากรคณะฯ
    $is_medsci = false;
    if (!empty($faculty) && mb_strpos($faculty, 'วิทยาศาสตร์การแพทย์') !== false) {
        $is_medsci = true;
    } elseif (mb_strpos($staff_response, 'วิทยาศาสตร์การแพทย์') !== false) {
        $is_medsci = true;
    } elseif ($is_local_user) {
        $is_medsci = true;
    }

    if (!$is_medsci) {
        if (empty($faculty)) {
            return ['status' => false, 'message' => 'ไม่พบข้อมูลสังกัดคณะวิชาของท่านในระบบมหาวิทยาลัย'];
        }
        return ['status' => false, 'message' => 'คุณไม่ใช่บุคลากรของคณะวิทยาศาสตร์การแพทย์ (สังกัดจริง: ' . $faculty . ')'];
    }

    return [
        'status'     => true,
        'message'    => 'เข้าสู่ระบบสำเร็จ',
        'username'   => $username,
        'first_name' => $first_name,
        'last_name'  => $last_name
    ];
}
