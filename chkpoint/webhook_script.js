/**
 * Google Apps Script Webhook สำหรับรับคะแนนจากระบบ Quiz
 * วิธีติดตั้ง:
 * 1. เปิด Google Sheets
 * 2. ไปที่เมนู ส่วนขยาย (Extensions) -> Apps Script
 * 3. คัดลอกโค้ดนี้ไปวางทับโค้ดเดิมทั้งหมด
 * 4. กดปุ่ม เผยแพร่ (Deploy) -> การทำให้ใช้งานได้รายการใหม่ (New deployment)
 * 5. เลือกประเภท: เว็บแอป (Web app)
 * 6. สิทธิ์เข้าถึง (Who has access): ทุกคน (Anyone)
 * 7. กด Deploy แล้วคัดลอก "URL ของเว็บแอป" ไปใส่ในหน้าแอดมินของ ChkPoint
 */

function doPost(e) {
  // รับข้อมูล JSON จากการ POST
  try {
    var data = JSON.parse(e.postData.contents);
    var studentId = data.student_id;
    var columnName = data.column_name;
    var score = data.score;
    
    if (!studentId || !columnName || score === undefined) {
      return ContentService.createTextOutput(JSON.stringify({
        "success": false,
        "message": "ข้อมูลไม่ครบถ้วน (ต้องการ student_id, column_name, score)"
      })).setMimeType(ContentService.MimeType.JSON);
    }
    
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
    var dataRange = sheet.getDataRange();
    var values = dataRange.getValues();
    
    if (values.length < 2) {
      return ContentService.createTextOutput(JSON.stringify({
        "success": false,
        "message": "ไม่พบข้อมูลตารางในชีต"
      })).setMimeType(ContentService.MimeType.JSON);
    }
    
    // 1. หาหมายเลขคอลัมน์จากแถวแรก (Headers)
    var headers = values[0]; // แถวที่ 1 (Index 0) หรือแถวที่ 2 ขึ้นอยู่กับไฟล์ของคุณครู
    // ไฟล์ของครู Header อยู่แถวที่ 2 (Index 1) แต่ถ้ามี Merge Cell อาจจะซับซ้อน
    // เพื่อความชัวร์ ให้ค้นหาใน 2 แถวแรก
    var targetColIndex = -1;
    var headerRowIndex = 0;
    
    for (var r = 0; r < 2; r++) {
      if (r >= values.length) break;
      for (var c = 0; c < values[r].length; c++) {
        if (String(values[r][c]).trim() === columnName) {
          targetColIndex = c;
          headerRowIndex = r;
          break;
        }
      }
      if (targetColIndex !== -1) break;
    }
    
    if (targetColIndex === -1) {
      return ContentService.createTextOutput(JSON.stringify({
        "success": false,
        "message": "ไม่พบคอลัมน์ชื่อ '" + columnName + "' ในตาราง"
      })).setMimeType(ContentService.MimeType.JSON);
    }
    
    // 2. หารหัสนักเรียน (สมมติว่ารหัสนักเรียนอยู่ในคอลัมน์ที่ 2 หรือ 3)
    // ค้นหาทั้งไฟล์ในคอลัมน์ B (Index 1) เพื่อหารหัสนักเรียน
    var targetRowIndex = -1;
    for (var r = headerRowIndex + 1; r < values.length; r++) {
      // ลองหาในคอลัมน์ B (Index 1)
      if (String(values[r][1]).trim() === String(studentId).trim()) {
        targetRowIndex = r;
        break;
      }
    }
    
    if (targetRowIndex === -1) {
      return ContentService.createTextOutput(JSON.stringify({
        "success": false,
        "message": "ไม่พบรหัสนักเรียน '" + studentId + "' ในชีต"
      })).setMimeType(ContentService.MimeType.JSON);
    }
    
    // 3. เขียนคะแนนลงไปในช่องที่ตัดกัน (Row, Col)
    // Range ใน Apps Script เริ่มนับที่ 1, Array เริ่มนับที่ 0
    var cell = sheet.getRange(targetRowIndex + 1, targetColIndex + 1);
    cell.setValue(score);
    
    return ContentService.createTextOutput(JSON.stringify({
      "success": true,
      "message": "อัปเดตคะแนนเรียบร้อยแล้ว",
      "updated_row": targetRowIndex + 1,
      "updated_col": targetColIndex + 1
    })).setMimeType(ContentService.MimeType.JSON);
    
  } catch (error) {
    return ContentService.createTextOutput(JSON.stringify({
      "success": false,
      "message": "เกิดข้อผิดพลาด: " + error.toString()
    })).setMimeType(ContentService.MimeType.JSON);
  }
}
