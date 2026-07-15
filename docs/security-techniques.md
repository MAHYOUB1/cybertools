# 🔒 دليل التقنيات الأمنية المُستخدمة في CyberTools

## نظرة عامة

هذا الملف يوثّق كل التقنيات الأمنية التي تم تطبيقها في إعادة كتابة منصة CyberTools، مع شرح مفصّل لكل تقنية: **أين استُخدمت** و**لماذا** و**ما مميزاتها**.

---

## 📋 فهرس التقنيات

| # | التقنية | الملفات | الثغرة المُصلحة |
|---|---------|---------|-----------------|
| 1 | PDO Prepared Statements | جميع ملفات API | SQL Injection |
| 2 | bcrypt Password Hashing | auth.php, seed.sql | Plain Text Passwords |
| 3 | Session Hardening | functions.php, Dockerfile | Session Hijacking/Fixation |
| 4 | CSRF Token Protection | functions.php, جميع POST endpoints | Cross-Site Request Forgery |
| 5 | XSS Output Sanitization | functions.php, جميع ملفات API | Stored/Reflected XSS |
| 6 | IDOR Prevention | orders.php, users.php, cart.php | Broken Access Control |
| 7 | Role-Based Access Control | admin/stats.php, seller/stats.php | Privilege Escalation |
| 8 | Command Injection Prevention | admin/stats.php | OS Command Injection |
| 9 | Secure File Upload | functions.php, users.php | Malicious File Upload |
| 10 | Rate Limiting | functions.php, nginx.conf | Brute Force Attacks |
| 11 | API Gateway + AES Encryption | gateway.php, crypto.js, security.php | Request Sniffing |
| 12 | Security Headers | apache.conf, nginx.conf, functions.php | Clickjacking, MIME Sniffing |
| 13 | Secure Storage | schema.sql, payments.php | Data Breach |
| 14 | Infrastructure Hardening | Dockerfile, docker-compose.yml | Server Misconfiguration |
| 15 | DOM-based XSS Prevention | main.js, product-details.html | Client-side XSS |

---

## 1. PDO Prepared Statements 🛡️

### ما هي؟
Prepared Statements هي طريقة لتنفيذ استعلامات SQL بشكل آمن عبر فصل **الأمر** عن **البيانات**. قاعدة البيانات تعامل المدخلات كبيانات فقط ولا تنفذها ككود SQL.

### أين استُخدمت؟
- **`backend/includes/db.php`** - تحويل الاتصال من `mysqli` إلى `PDO`
- **`backend/api/auth.php`** - استعلامات تسجيل الدخول والتسجيل
- **`backend/api/products.php`** - البحث والتصفية والإضافة
- **`backend/api/cart.php`** - جميع عمليات السلة
- **`backend/api/orders.php`** - إنشاء وعرض الطلبات
- **`backend/api/reviews.php`** - إضافة وعرض المراجعات
- **`backend/api/users.php`** - عرض وتحديث بيانات المستخدمين
- **`backend/api/payments.php`** - معالجة المدفوعات
- **`backend/api/admin/stats.php`** - إحصائيات الإدارة
- **`backend/api/seller/stats.php`** - إحصائيات البائع

### لماذا اخترناها؟
- **مستحيل الحقن:** PDO يفصل SQL عن البيانات على مستوى driver MySQL
- **`EMULATE_PREPARES = false`:** يضمن أن الـ Prepared Statements تُنفَّذ فعلاً على الخادم (ليس محاكاة PHP)
- **أفضل من `real_escape_string`:** التي يمكن تجاوزها في حالات معينة (multi-byte characters)

### مثال (قبل → بعد):
```php
// ❌ قبل - SQL Injection ممكنة
$query = "SELECT * FROM users WHERE username='$username' AND password='$password'";
$result = $db->query($query);

// ✅ بعد - مستحيل الحقن
$stmt = $db->prepare("SELECT * FROM users WHERE username = :username AND is_active = 1");
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();
```

---

## 2. bcrypt Password Hashing 🔐

### ما هي؟
`bcrypt` هي خوارزمية تجزئة (Hashing) مصممة خصيصاً لكلمات المرور. تتميز بأنها **بطيئة عمداً** (configurable cost) مما يجعل هجمات Brute Force غير عملية.

### أين استُخدمت؟
- **`backend/api/auth.php`** - `password_hash()` عند التسجيل و `password_verify()` عند الدخول
- **`backend/init-passwords.php`** - تحويل كلمات المرور في البيانات التجريبية
- **`backend/includes/config.php`** - `BCRYPT_COST = 12`

### لماذا اخترناها؟
- **Cost Factor = 12:** كل محاولة تأخذ ~250ms مما يجعل brute force (مثلاً 1 مليون محاولة) تأخذ ~70 ساعة
- **Salt تلقائي:** `password_hash()` يضيف salt عشوائي مع كل hash
- **مقاومة لـ Rainbow Tables:** لأن كل مستخدم له salt مختلف
- **معيار الصناعة:** OWASP توصي بها كأفضل خيار لكلمات المرور

### مثال:
```php
// التشفير عند التسجيل
$hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// التحقق عند الدخول (timing-safe comparison)
if (!password_verify($inputPassword, $storedHash)) {
    // كلمة المرور خاطئة
}
```

---

## 3. Session Hardening 🔒

### ما هي؟
مجموعة إعدادات تحمي الجلسات (Sessions) من الاختطاف والتثبيت والتسريب.

### أين استُخدمت؟
- **`backend/includes/functions.php`** - دالة `startSession()`
- **`backend/Dockerfile`** - إعدادات PHP في `php.ini`
- **`backend/api/auth.php`** - `session_regenerate_id(true)` بعد الدخول

### الإعدادات المُطبقة:

| الإعداد | القيمة | السبب |
|---------|--------|-------|
| `cookie_httponly` | `1` | منع JavaScript من قراءة session cookie (حماية XSS) |
| `cookie_samesite` | `Strict` | منع إرسال الكوكي في طلبات cross-site (حماية CSRF) |
| `use_strict_mode` | `1` | رفض session IDs لم يُنشئها الخادم |
| `use_only_cookies` | `1` | منع تمرير session ID في URL |
| `sid_length` | `48` | طول session ID كبير يصعب التخمين |
| `sid_bits_per_character` | `6` | تنوع أكبر في الأحرف |
| `session_regenerate_id(true)` | بعد Login | منع Session Fixation |

### لماذا اخترناها؟
- **الحماية من Session Hijacking:** حتى لو حصل المهاجم على XSS لا يستطيع قراءة الكوكي
- **الحماية من Session Fixation:** تجديد ID بعد الدخول يُبطل أي session ID مسبق
- **Defense in Depth:** طبقات حماية متعددة

---

## 4. CSRF Token Protection 🔄

### ما هي؟
CSRF Token هو رمز عشوائي فريد لكل جلسة، يُرسل مع كل طلب يُحدث تغييراً (POST/PUT/DELETE) للتأكد من أن الطلب صادر من موقعنا وليس من موقع خبيث.

### أين استُخدمت؟
- **`backend/includes/functions.php`** - `generateCSRFToken()` و `validateCSRFToken()`
- **`backend/api/auth.php`** - يُولّد ويُرجع token عند Login
- **`backend/api/products.php`** - يتحقق من Token في POST/PUT/DELETE
- **`backend/api/cart.php`** - يتحقق من Token في POST/PUT/DELETE
- **`backend/api/orders.php`** - يتحقق من Token في POST
- **`backend/api/payments.php`** - يتحقق من Token في POST
- **`backend/api/reviews.php`** - يتحقق من Token في POST
- **`backend/api/users.php`** - يتحقق من Token في PUT
- **`frontend/assets/js/main.js`** - يُرسل Token تلقائياً مع كل طلب

### لماذا اخترناها؟
- **`hash_equals()`:** مقارنة timing-safe تمنع timing attacks
- **Token عشوائي:** `random_bytes(32)` = 256 bits من العشوائية
- **صلاحية محدودة:** انتهاء بعد ساعة واحدة
- **Header-based:** أسلم من hidden field لأن المهاجم لا يستطيع تعيين headers في cross-site requests

---

## 5. XSS Output Sanitization 🕷️

### ما هي؟
تعقيم (Sanitization) المخرجات لتحويل أكواد HTML/JavaScript إلى نص عادي غير قابل للتنفيذ.

### أين استُخدمت؟
- **`backend/includes/functions.php`** - `sanitize()` و `sanitizeOutput()`
- **`backend/api/reviews.php`** - تعقيم المراجعات عند العرض
- **`backend/api/products.php`** - تعقيم أسماء وأوصاف المنتجات
- **`backend/api/users.php`** - تعقيم بيانات المستخدمين
- **`frontend/assets/js/main.js`** - `DOMPurify.sanitize()` في الفرونت إند
- **`frontend/pages/product-details.html`** - `textContent` بدلاً من `innerHTML`
- **`frontend/pages/admin-dashboard.html`** - `textContent` في ping output

### التقنيات المستخدمة:

| التقنية | المستوى | الاستخدام |
|---------|---------|----------|
| `htmlspecialchars(ENT_QUOTES, 'UTF-8')` | Backend | تحويل `<>"'&` إلى entities |
| `strip_tags()` | Backend | إزالة جميع وسوم HTML |
| `textContent` | Frontend | إدراج نص بدون تنفيذ HTML |
| `DOMPurify.sanitize()` | Frontend | تعقيم HTML قبل الإدراج |

### لماذا اخترناها؟
- **طبقتين:** التعقيم في Backend + Frontend (Defense in Depth)
- **`ENT_QUOTES | ENT_HTML5`:** يغطي جميع أنواع الاقتباسات
- **`textContent`:** الطريقة الأكثر أماناً لعرض محتوى المستخدمين

---

## 6. IDOR Prevention (Broken Access Control) 🚪

### ما هي؟
IDOR (Insecure Direct Object Reference) هي ثغرة تسمح للمستخدم بالوصول لبيانات مستخدمين آخرين بتغيير المعرف (ID) في الطلب.

### أين استُخدمت؟
- **`backend/api/orders.php`** - مقارنة `user_id` المطلوب مع الجلسة
- **`backend/api/users.php`** - المستخدم يرى/يعدّل بياناته فقط
- **`backend/api/cart.php`** - التحقق من ملكية عنصر السلة
- **`backend/api/products.php`** - التحقق من ملكية المنتج للبائع
- **`backend/api/payments.php`** - التحقق أن الطلب يخص المستخدم

### الأسلوب:
```php
// كل عملية تتحقق أن المستخدم يملك المورد
if ($targetUserId !== $user['id'] && $user['role'] !== 'admin') {
    jsonError('غير مصرح', 403);
}
```

### لماذا اخترناها؟
- **Server-Side Validation:** لا نثق بأي ID يأتي من الفرونت إند
- **Admin Exception:** المسؤول فقط يمكنه الوصول لبيانات الآخرين
- **Consistent Pattern:** نفس النمط في كل ملف

---

## 7. Role-Based Access Control (RBAC) 👑

### ما هي؟
نظام صلاحيات يحدد ما يمكن لكل دور (admin/seller/customer) فعله.

### أين استُخدمت؟
- **`backend/includes/functions.php`** - `requireRole($role)`
- **`backend/api/admin/stats.php`** - `requireRole('admin')` بدلاً من `requireAuth()`
- **`backend/api/seller/stats.php`** - `requireRole('seller')`

### لماذا اخترناها؟
- **مركزية التحقق:** دالة واحدة تُستدعى في كل endpoint
- **Admin override:** المسؤول يمكنه الوصول لصلاحيات البائعين أيضاً
- **Fail-safe:** الافتراضي هو رفض الوصول

---

## 8. Command Injection Prevention 💻

### ما هي؟
منع حقن أوامر نظام التشغيل عبر مدخلات المستخدم.

### أين استُخدمت؟
- **`backend/api/admin/stats.php`** - أداة Ping

### التقنيات:
```php
// 1. التحقق من صحة عنوان IP
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    jsonError('عنوان IP غير صالح');
}
// 2. escapeshellarg لمنع حقن الأوامر
$safeIP = escapeshellarg($ip);
$result = shell_exec("ping -c 2 $safeIP 2>&1");
```

### لماذا اخترناها؟
- **`FILTER_VALIDATE_IP`:** يرفض أي شيء ليس IP صالح (يمنع `; cat /etc/passwd`)
- **`escapeshellarg()`:** يحيط القيمة بعلامات اقتباس مفردة ويهرّب أي اقتباسات داخلية
- **طبقتين:** Validation أولاً ثم Escaping (Defense in Depth)

---

## 9. Secure File Upload 📁

### ما هي؟
نظام رفع ملفات آمن يمنع رفع ملفات خبيثة (Web Shells).

### أين استُخدمت؟
- **`backend/includes/functions.php`** - `secureUploadFile()`
- **`backend/api/users.php`** - رفع صورة المستخدم
- **`backend/apache.conf`** - منع تنفيذ PHP في مجلد uploads

### الحمايات المطبقة:

| الحماية | التفصيل |
|---------|---------|
| **MIME Validation** | `finfo_file()` يفحص المحتوى الحقيقي (ليس الامتداد فقط) |
| **Extension Whitelist** | فقط: jpg, jpeg, png, gif, webp |
| **UUID Naming** | `bin2hex(random_bytes(16))` بدلاً من اسم الملف الأصلي |
| **Size Limit** | 2MB حد أقصى |
| **PHP Execution Block** | Apache يمنع تنفيذ PHP في `/uploads/` |
| **Directory Permissions** | `chmod 750` بدلاً من `777` |

### لماذا اخترناها؟
- **UUID:** يمنع Path Traversal (مثل `../../config.php`)
- **MIME Check:** يمنع رفع ملف PHP بامتداد `.jpg`
- **Apache Block:** حتى لو تم رفع ملف PHP بطريقة ما، لن يُنفَّذ

---

## 10. Rate Limiting ⏱️

### ما هي؟
تقييد عدد الطلبات من نفس العنوان/المستخدم خلال فترة زمنية محددة.

### أين استُخدمت؟
- **`backend/includes/functions.php`** - `checkRateLimit()` (مستوى التطبيق)
- **`backend/api/auth.php`** - 5 محاولات / 15 دقيقة لتسجيل الدخول
- **`frontend/nginx.conf`** - Rate limiting على مستوى الخادم (30 طلب/ثانية)

### لماذا اخترناها؟
- **طبقتين:** Nginx (مستوى الشبكة) + PHP (مستوى التطبيق)
- **Nginx:** يمنع DDoS قبل الوصول للتطبيق
- **PHP:** حماية دقيقة per-action (login, register)
- **`Retry-After` header:** يخبر المتصفح متى يمكنه المحاولة مرة أخرى

---

## 11. API Gateway + AES-256-GCM Encryption 🔐

### ما هي؟
بوابة مركزية مشفرة تخفي مسارات API الحقيقية وتشفر البيانات المرسلة.

### أين استُخدمت؟
- **`backend/includes/security.php`** - `SecureEncryption` + `APIGateway`
- **`backend/api/gateway.php`** - نقطة الدخول المركزية
- **`frontend/assets/js/crypto.js`** - `SecureCrypto` + `GatewayClient`
- **`frontend/nginx.conf`** - توجيه `/api/gateway.php`

### كيف يعمل الإخفاء:

```
المتصفح → Network Tab يرى:
  POST /api/gateway.php
  Body: {"op":"ax01","d":"U2FsdGVkX1+...","n":"abc123","t":1714456800}

بدلاً من:
  POST /api/auth.php?action=login
  Body: {"username":"admin","password":"admin123"}
```

### المكونات:

| المكون | الدور |
|--------|-------|
| **Operation IDs** | `ax01` بدلاً من `/auth.php?action=login` |
| **AES-256-GCM** | تشفير payload كامل (Web Crypto API في JS) |
| **Nonce** | رمز عشوائي لمنع Replay Attacks |
| **Timestamp** | رفض الطلبات الأقدم من 5 دقائق |

### لماذا AES-256-GCM؟
- **GCM Mode:** يوفر تشفير + مصادقة (Authenticated Encryption) في عملية واحدة
- **256-bit key:** أقوى مستوى تشفير AES
- **Web Crypto API:** مدمجة في المتصفح (لا حاجة لمكتبات خارجية)
- **12-byte nonce:** الحجم الأمثل لـ GCM

---

## 12. Security Headers 🛡️

### ما هي؟
HTTP headers ترسلها الخوادم لتوجيه المتصفح لتطبيق حمايات إضافية.

### أين استُخدمت؟
- **`backend/includes/functions.php`** - `setHeaders()`
- **`backend/apache.conf`** - على مستوى Apache
- **`frontend/nginx.conf`** - على مستوى Nginx

### الـ Headers المطبقة:

| Header | القيمة | الحماية |
|--------|--------|---------|
| `X-Content-Type-Options` | `nosniff` | منع MIME Sniffing |
| `X-Frame-Options` | `DENY` | منع Clickjacking |
| `X-XSS-Protection` | `1; mode=block` | حماية XSS إضافية |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | حماية الخصوصية |
| `Permissions-Policy` | `camera=(), microphone=()` | منع الوصول للأجهزة |
| `Content-Security-Policy` | `default-src 'self'` | منع تحميل موارد خارجية |
| `Server` / `X-Powered-By` | **محذوفان** | إخفاء تقنيات الخادم |

---

## 13. Secure Data Storage 💾

### ما هي؟
حماية البيانات الحساسة في قاعدة البيانات.

### أين استُخدمت؟
- **`database/schema.sql`** - `card_last_four VARCHAR(4)` بدلاً من `card_number VARCHAR(20)`
- **`backend/api/payments.php`** - تخزين آخر 4 أرقام فقط

### التغييرات:
```sql
-- ❌ قبل - رقم البطاقة الكامل
card_number VARCHAR(20)    -- يخزّن: 4532015112830366

-- ✅ بعد - آخر 4 أرقام فقط
card_last_four VARCHAR(4)  -- يخزّن: 0366
```

### لماذا؟
- **PCI DSS Compliance:** معيار الصناعة يمنع تخزين رقم البطاقة الكامل
- **Damage Limitation:** حتى لو تم اختراق DB، البطاقات آمنة

---

## 14. Infrastructure Hardening 🏗️

### ما هي؟
تأمين البنية التحتية (Docker, Apache, Nginx, PHP).

### أين استُخدمت؟
- **`backend/Dockerfile`** - إعدادات PHP الآمنة
- **`docker-compose.yml`** - عزل الشبكة + resource limits

### التغييرات الرئيسية:

| الإعداد | قبل | بعد | السبب |
|---------|------|------|-------|
| `display_errors` | `On` | `Off` | إخفاء تفاصيل الأخطاء |
| `expose_php` | افتراضي | `Off` | إخفاء إصدار PHP |
| `session.cookie_httponly` | `0` | `1` | حماية من XSS |
| `allow_url_include` | افتراضي | `Off` | منع RFI attacks |
| `disable_functions` | لا شيء | `exec,passthru,system,...` | منع تنفيذ أوامر |
| MySQL port | `3306:3306` (مكشوف) | `expose: 3306` (داخلي) | حماية DB |
| Backend port | `8080:80` (مكشوف) | `expose: 80` (داخلي) | الوصول فقط عبر Nginx |
| uploads permission | `chmod 777` | `chmod 750` | أقل صلاحيات ممكنة |
| `Options` | `Indexes` | `-Indexes` | منع عرض قائمة الملفات |

---

## 15. DOM-based XSS Prevention 🌐

### ما هي؟
حماية من هجمات XSS على مستوى المتصفح (client-side).

### أين استُخدمت؟
- **`frontend/assets/js/main.js`** - `DOMPurify.sanitize()` + `textContent`
- **`frontend/pages/product-details.html`** - `textContent` للمراجعات
- **`frontend/pages/admin-dashboard.html`** - `textContent` للـ ping output

### التقنيات:
```javascript
// ❌ قبل - XSS ممكنة
element.innerHTML = userInput;       // <script>alert(1)</script> يتم تنفيذه!

// ✅ بعد - آمن
element.textContent = userInput;     // يُعرض كنص عادي
element.innerHTML = DOMPurify.sanitize(userInput); // HTML آمن فقط
```

### لماذا `sessionStorage` بدلاً من `localStorage`؟
- **`sessionStorage`:** يُمسح عند إغلاق التبويب (session scope)
- **`localStorage`:** يبقى دائماً ويمكن قراءته بـ XSS
- **الفائدة:** حتى لو حدث XSS لحظة، البيانات تختفي بعد إغلاق التبويب

---

## ملخص التحسينات

### الثغرات المُغلقة: 18+ ثغرة OWASP

| الثغرة | العدد | الحالة |
|--------|-------|--------|
| SQL Injection | 6 | ✅ مُغلقة |
| XSS (Stored + Reflected) | 4 | ✅ مُغلقة |
| IDOR / Broken Access Control | 5 | ✅ مُغلقة |
| Command Injection | 1 | ✅ مُغلقة |
| CSRF | 2 | ✅ مُغلقة |
| Cryptographic Failures | 2 | ✅ مُغلقة |
| Security Misconfiguration | 3+ | ✅ مُغلقة |
| Insecure File Upload | 1 | ✅ مُغلقة |

### النهج الأمني: Defense in Depth
كل ثغرة مُغلقة بطبقات متعددة:
- **Input Validation** → التحقق من المدخلات
- **Parameterized Queries** → فصل SQL عن البيانات  
- **Output Encoding** → تعقيم المخرجات
- **Access Control** → التحقق من الصلاحيات
- **Infrastructure** → تأمين الخوادم والشبكة

---

**آخر تحديث:** 2026-05-09
