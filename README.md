# CyberTools Store 🛡️

> **⚠️ تحذير مهم: هذا المشروع للأغراض التعليمية فقط!**
> يحتوي على ثغرات أمنية حقيقية مقصودة. لا تستخدمه في بيئة إنتاجية.

## نظرة عامة

**CyberTools** منصة تعليمية عملية لفهم ثغرات OWASP Top 10 عبر تطبيق متجر إلكتروني واقعي.

---

## 🐳 التشغيل بـ Docker (مُوصى به)

```bash
# استنساخ المشروع
git clone <repo-url>
cd cybertools

# تشغيل جميع الخدمات
docker compose up -d

# الخدمات المتاحة:
# Frontend (Nginx):  http://localhost:3000
# Backend (PHP API): http://localhost:8080
# Database (MySQL):  localhost:3306
```

### الخدمات في Docker:
| الخدمة | الصورة | المنفذ | الدور |
|--------|--------|--------|-------|
| `frontend` | nginx:alpine | 3000 | صفحات HTML |
| `backend` | php:8.1-apache | 8080 | REST API |
| `db` | mysql:8.0 | 3306 | قاعدة البيانات |

---

## 🔑 بيانات الدخول

| الدور | المستخدم | كلمة المرور |
|-------|----------|-------------|
| Admin | `admin` | `admin123` |
| Seller 1 | `seller1` | `seller123` |
| Seller 2 | `seller2` | `seller123` |
| Customer | `customer1` | `pass123` |

### بطاقات الدفع التجريبية:
| النوع | الرقم | الانتهاء | CVV |
|-------|-------|----------|-----|
| Visa | `4532015112830366` | 12/25 | 123 |
| Mastercard | `5425233430109903` | 12/25 | 123 |
| Amex | `374245455400126` | 12/25 | 1234 |

---

## 🚨 الثغرات المضمنة

| # | الثغرة | الموقع | الاستغلال |
|---|--------|--------|-----------|
| 1 | SQL Injection | `api/auth.php` | `admin'--` في حقل اسم المستخدم |
| 2 | SQL Injection | `api/products.php` | `?search=' UNION SELECT...` |
| 3 | SQL Injection | `api/products.php` | `?category=1 OR 1=1` |
| 4 | XSS Stored | `api/reviews.php` | `<script>alert(1)</script>` في التعليق |
| 5 | XSS Reflected | `products.html` | `?search=<script>alert(1)</script>` |
| 6 | CSRF | `checkout.html` | نموذج دفع بدون token |
| 7 | Command Injection | `api/admin/stats.php` | `?ip=8.8.8.8; cat /etc/passwd` |
| 8 | IDOR | `api/orders.php` | `?user_id=1` لرؤية طلبات أي مستخدم |
| 9 | Plain Text Passwords | `api/auth.php` | كلمات مرور مخزنة بدون تشفير |
| 10 | Broken Access Control | `api/admin/stats.php` | أي مستخدم يصل للوحة المسؤول |
| 11 | Insecure File Upload | `api/users.php` | رفع ملف PHP كـ webshell |

---

## 📁 هيكل المشروع

```
cybertools/
├── docker-compose.yml          # تنسيق Docker
├── database/
│   ├── schema.sql              # هيكل قاعدة البيانات
│   └── seed.sql                # البيانات التجريبية
├── backend/                    # PHP API Container
│   ├── Dockerfile
│   ├── apache.conf
│   ├── api/                    # نقاط API الـ 18
│   └── includes/               # ملفات مشتركة
└── frontend/                   # Nginx Container
    ├── Dockerfile
    ├── nginx.conf
    ├── pages/                  # 11 صفحة HTML
    └── assets/                 # CSS + JS
```

---

## 🛠️ المتطلبات

- Docker Desktop
- Docker Compose v2+
