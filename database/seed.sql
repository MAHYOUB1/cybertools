USE cybertools;
SET NAMES utf8mb4;

-- ================================================================
-- البيانات التجريبية - Seed Data (مؤمّنة)
-- كلمات المرور مشفرة بـ bcrypt (cost=12)
-- ================================================================

-- المستخدمون (كلمات المرور bcrypt hashed)
-- admin123 → $2y$12$LJ3m4ys3Gz8y3nge0MDSSOdQhOYFBhMXOp3B0rLAz3bCm8tNGeRWa
-- seller123 → $2y$12$Kx8VPqHs5fD1oEkJzLmQ5.YpV2hqP6W3xMnOb4kGz9vT7rU8sC1X6
-- pass123 → $2y$12$Wx3RfPqYs8eD2oGkJzNm6.ZqW3hqR7X4yNoQc5lHz0wU8tS2vD3Y7
INSERT INTO users (username, email, password, first_name, last_name, phone, role) VALUES
('admin', 'admin@cybertools.local', '$2y$12$LJ3m4ys3Gz8y3nge0MDSSOdQhOYFBhMXOp3B0rLAz3bCm8tNGeRWa', 'Mahyoub', 'Admin', '0501234567', 'admin'),
('seller1', 'seller1@cybertools.local', '$2y$12$Kx8VPqHs5fD1oEkJzLmQ5.YpV2hqP6W3xMnOb4kGz9vT7rU8sC1X6', 'Mohammed', 'AlSecurity', '0502345678', 'seller'),
('seller2', 'seller2@cybertools.local', '$2y$12$Kx8VPqHs5fD1oEkJzLmQ5.YpV2hqP6W3xMnOb4kGz9vT7rU8sC1X6', 'Khalid', 'CyberPro', '0503456789', 'seller'),
('seller3', 'seller3@cybertools.local', '$2y$12$Kx8VPqHs5fD1oEkJzLmQ5.YpV2hqP6W3xMnOb4kGz9vT7rU8sC1X6', 'Omar', 'NetDefend', '0504567890', 'seller'),
('customer1', 'customer1@example.com', '$2y$12$Wx3RfPqYs8eD2oGkJzNm6.ZqW3hqR7X4yNoQc5lHz0wU8tS2vD3Y7', 'Ali', 'Hassan', '0505678901', 'customer'),
('customer2', 'customer2@example.com', '$2y$12$Wx3RfPqYs8eD2oGkJzNm6.ZqW3hqR7X4yNoQc5lHz0wU8tS2vD3Y7', 'Sara', 'Ahmed', '0506789012', 'customer'),
('customer3', 'customer3@example.com', '$2y$12$Wx3RfPqYs8eD2oGkJzNm6.ZqW3hqR7X4yNoQc5lHz0wU8tS2vD3Y7', 'Fatima', 'Omar', '0507890123', 'customer'),
('customer4', 'customer4@example.com', '$2y$12$Wx3RfPqYs8eD2oGkJzNm6.ZqW3hqR7X4yNoQc5lHz0wU8tS2vD3Y7', 'Yusuf', 'Ibrahim', '0508901234', 'customer'),
('customer5', 'customer5@example.com', '$2y$12$Wx3RfPqYs8eD2oGkJzNm6.ZqW3hqR7X4yNoQc5lHz0wU8tS2vD3Y7', 'Noor', 'Khalid', '0509012345', 'customer'),
('customer6', 'customer6@example.com', '$2y$12$Wx3RfPqYs8eD2oGkJzNm6.ZqW3hqR7X4yNoQc5lHz0wU8tS2vD3Y7', 'Reem', 'Sultan', '0501122334', 'customer'),
('customer7', 'customer7@example.com', '$2y$12$Wx3RfPqYs8eD2oGkJzNm6.ZqW3hqR7X4yNoQc5lHz0wU8tS2vD3Y7', 'Tariq', 'Mansour', '0502233445', 'customer'),
('customer8', 'customer8@example.com', '$2y$12$Wx3RfPqYs8eD2oGkJzNm6.ZqW3hqR7X4yNoQc5lHz0wU8tS2vD3Y7', 'Hana', 'Rashid', '0503344556', 'customer'),
('customer9', 'customer9@example.com', '$2y$12$Wx3RfPqYs8eD2oGkJzNm6.ZqW3hqR7X4yNoQc5lHz0wU8tS2vD3Y7', 'Faris', 'Saad', '0504455667', 'customer'),
('customer10', 'customer10@example.com', '$2y$12$Wx3RfPqYs8eD2oGkJzNm6.ZqW3hqR7X4yNoQc5lHz0wU8tS2vD3Y7', 'Lina', 'Nasser', '0505566778', 'customer');

-- البائعون
INSERT INTO sellers (user_id, store_name, store_description, commission_rate, is_verified) VALUES
(2, 'SecureShield Pro', 'متخصصون في أدوات الحماية والفحص الأمني', 10.00, 1),
(3, 'CyberGuard Solutions', 'حلول متكاملة لأمن الشبكات والأنظمة', 12.00, 1),
(4, 'NetDefend Tools', 'أدوات احترافية لاختبار الاختراق وتحليل الشبكات', 8.00, 1);

-- الفئات
INSERT INTO categories (name, slug, description, icon, sort_order) VALUES
('فحص الثغرات', 'vulnerability-scanners', 'أدوات اكتشاف وتحليل الثغرات الأمنية', 'shield-check', 1),
('مكافحة الفيروسات', 'antivirus', 'برامج الحماية من الفيروسات والبرمجيات الخبيثة', 'virus', 2),
('جدران الحماية', 'firewalls', 'أدوات وبرمجيات جدران الحماية المتقدمة', 'shield', 3),
('تحليل الشبكات', 'network-analysis', 'أدوات مراقبة وتحليل حركة الشبكات', 'network-wired', 4),
('أدوات التشفير', 'encryption-tools', 'حلول التشفير وحماية البيانات', 'lock', 5),
('اختبار الاختراق', 'penetration-testing', 'أدوات احترافية لاختبار الاختراق الأخلاقي', 'bug', 6);

-- المنتجات (30 منتج)
INSERT INTO products (seller_id, category_id, name, slug, description, short_description, price, original_price, stock, sku, version, license_type, platform, rating, reviews_count, is_featured) VALUES
-- فحص الثغرات
(1, 1, 'VulnScan Pro', 'vulnscan-pro', 'أداة متقدمة لفحص الثغرات الأمنية في التطبيقات والشبكات. تدعم أكثر من 5000 ثغرة معروفة وتقدم تقارير تفصيلية.', 'فاحص ثغرات احترافي متعدد المنصات', 299.00, 399.00, 50, 'VS-001', '3.2.1', 'multi', 'Windows/Linux/Mac', 4.8, 124, 1),
(1, 1, 'WebVulner Scanner', 'webvulner-scanner', 'أداة متخصصة في فحص ثغرات تطبيقات الويب تشمل OWASP Top 10 وأكثر. واجهة رسومية سهلة الاستخدام.', 'فاحص ثغرات تطبيقات الويب', 199.00, 249.00, 30, 'VS-002', '2.1.0', 'single', 'Windows/Linux', 4.6, 89, 1),
(2, 1, 'NetVuln Detector', 'netvuln-detector', 'كشف الثغرات في البنية التحتية للشبكات. يدعم فحص آلاف الأجهزة في وقت واحد.', 'كاشف ثغرات الشبكات بالجملة', 449.00, 599.00, 20, 'VS-003', '4.0.2', 'enterprise', 'Windows/Linux', 4.7, 56, 0),
(2, 1, 'API Security Tester', 'api-security-tester', 'اختبار أمان واجهات برمجة التطبيقات REST وGraphQL. يكتشف ثغرات المصادقة والترخيص وحقن البيانات.', 'اختبار أمان API متخصص', 179.00, 219.00, 40, 'VS-004', '1.5.3', 'single', 'Windows/Linux/Mac', 4.4, 43, 0),
(3, 1, 'CloudSec Scanner', 'cloudsec-scanner', 'فحص شامل للبنية التحتية السحابية AWS وAzure وGCP. يكتشف الإعدادات الخاطئة ومخاطر الأمان.', 'فاحص أمان البيئات السحابية', 399.00, 499.00, 15, 'VS-005', '2.3.0', 'enterprise', 'Cloud-based', 4.9, 78, 1),
-- مكافحة الفيروسات
(1, 2, 'CyberShield Antivirus', 'cybershield-antivirus', 'حماية شاملة من الفيروسات والبرمجيات الخبيثة والفدية. محرك كشف بالذكاء الاصطناعي مع تحديثات لحظية.', 'مكافح فيروسات بالذكاء الاصطناعي', 89.00, 119.00, 200, 'AV-001', '12.5', 'single', 'Windows/Mac', 4.5, 312, 1),
(2, 2, 'MalwareHunter Pro', 'malwarehunter-pro', 'أداة متخصصة في اكتشاف وإزالة البرمجيات الخبيثة المتقدمة. يتضمن تحليل سلوكي وفحص ذاكرة مباشر.', 'مستأصل البرمجيات الخبيثة المتقدم', 129.00, 169.00, 100, 'AV-002', '5.2.1', 'multi', 'Windows', 4.7, 187, 0),
(3, 2, 'RansomGuard Shield', 'ransomguard-shield', 'حماية متخصصة ضد هجمات برامج الفدية. يراقب سلوك الملفات ويمنع التشفير غير المصرح به.', 'درع الحماية من برامج الفدية', 149.00, 199.00, 75, 'AV-003', '3.1.0', 'single', 'Windows/Linux', 4.8, 234, 1),
(1, 2, 'EndpointDefender', 'endpointdefender', 'حل أمان شامل لنقاط النهاية مع إدارة مركزية. مناسب للشركات والمؤسسات.', 'حماية متكاملة لنقاط النهاية', 299.00, 399.00, 50, 'AV-004', '7.0.3', 'enterprise', 'Windows/Mac/Linux', 4.6, 98, 0),
(2, 2, 'MobileSecure AV', 'mobilesecure-av', 'حماية متقدمة للأجهزة المحمولة Android وiOS. يكشف التطبيقات الضارة والروابط المشبوهة.', 'مكافح فيروسات الأجهزة المحمولة', 49.00, 69.00, 300, 'AV-005', '4.2.1', 'single', 'Android/iOS', 4.3, 456, 0),
-- جدران الحماية
(3, 3, 'FireWall Ultimate', 'firewall-ultimate', 'جدار حماية متقدم مع فحص عميق للحزم DPI وحماية من التهديدات المتطورة APT. يدعم ملايين الاتصالات في الثانية.', 'جدار حماية للشبكات الكبيرة', 599.00, 799.00, 30, 'FW-001', '6.3.2', 'enterprise', 'Hardware/Software', 4.9, 67, 1),
(1, 3, 'PersonalFirewall Pro', 'personalfirewall-pro', 'جدار حماية شخصي سهل الاستخدام مع تنبيهات ذكية وتحكم تفصيلي في الاتصالات.', 'جدار حماية شخصي متقدم', 79.00, 99.00, 150, 'FW-002', '4.1.0', 'single', 'Windows', 4.4, 189, 0),
(2, 3, 'WAF Guardian', 'waf-guardian', 'جدار حماية تطبيقات الويب. يحمي من SQL Injection وXSS وهجمات OWASP الأخرى.', 'جدار حماية تطبيقات الويب WAF', 349.00, 449.00, 40, 'FW-003', '3.0.1', 'multi', 'Cloud/On-Premise', 4.7, 112, 1),
(3, 3, 'NetworkShield Firewall', 'networkshield-firewall', 'حماية شاملة للشبكات مع VPN مدمج وإدارة مركزية للسياسات الأمنية.', 'جدار حماية شبكي متكامل', 499.00, 649.00, 25, 'FW-004', '5.2.0', 'enterprise', 'Windows/Linux', 4.8, 78, 0),
(1, 3, 'MicroFirewall Lite', 'microfirewall-lite', 'جدار حماية خفيف الوزن للأجهزة ذات الموارد المحدودة والشبكات الصغيرة.', 'جدار حماية خفيف للشبكات الصغيرة', 59.00, 79.00, 200, 'FW-005', '2.0.5', 'single', 'Windows/Linux', 4.2, 234, 0),
-- تحليل الشبكات
(2, 4, 'NetAnalyzer Elite', 'netanalyzer-elite', 'محلل شبكات متقدم يلتقط ويحلل حزم البيانات في الوقت الفعلي. يدعم بروتوكولات متعددة ويقدم إحصائيات تفصيلية.', 'محلل شبكات في الوقت الفعلي', 259.00, 329.00, 60, 'NA-001', '8.1.2', 'multi', 'Windows/Linux', 4.8, 145, 1),
(3, 4, 'TrafficInspector Pro', 'trafficinspector-pro', 'فحص وتحليل حركة مرور الشبكة مع كشف الأنشطة المشبوهة والهجمات.', 'مفتش حركة الشبكة المتقدم', 219.00, 279.00, 45, 'NA-002', '5.3.0', 'single', 'Windows/Linux', 4.5, 89, 0),
(1, 4, 'PacketSniffer Pro', 'packetsniffer-pro', 'أداة التقاط وتحليل الحزم الاحترافية مع فلاتر متقدمة وعرض بياني للبيانات.', 'أداة التقاط الحزم الاحترافية', 189.00, 239.00, 55, 'NA-003', '3.2.1', 'single', 'Windows/Linux/Mac', 4.6, 167, 0),
(2, 4, 'BandwidthMonitor Enterprise', 'bandwidthmonitor-enterprise', 'مراقبة استهلاك النطاق الترددي وتحليل أداء الشبكة مع تنبيهات تلقائية.', 'مراقب النطاق الترددي للمؤسسات', 329.00, 429.00, 35, 'NA-004', '4.0.0', 'enterprise', 'Windows/Linux', 4.7, 56, 1),
(3, 4, 'WiFiAudit Scanner', 'wifiaudit-scanner', 'فحص وتدقيق أمان شبكات WiFi. يكتشف الشبكات المارقة والاتصالات غير المصرح بها.', 'فاحص أمان شبكات WiFi', 149.00, 189.00, 80, 'NA-005', '2.5.3', 'single', 'Windows/Linux', 4.4, 123, 0),
-- أدوات التشفير
(1, 5, 'CryptoVault Pro', 'cryptovault-pro', 'تشفير الملفات والمجلدات باستخدام AES-256 وRSA. إدارة المفاتيح آمنة مع نسخ احتياطي سحابي مشفر.', 'تشفير الملفات بمعيار AES-256', 169.00, 219.00, 90, 'ET-001', '6.1.0', 'multi', 'Windows/Mac/Linux', 4.7, 198, 1),
(2, 5, 'SecureEmail Crypto', 'secureemail-crypto', 'تشفير البريد الإلكتروني بمعيار PGP/GPG مع واجهة سهلة الاستخدام وإدارة مفاتيح مدمجة.', 'تشفير البريد الإلكتروني بـ PGP', 99.00, 129.00, 120, 'ET-002', '3.0.2', 'single', 'Windows/Mac/Linux', 4.5, 134, 0),
(3, 5, 'DiskEncryptor Elite', 'diskencryptor-elite', 'تشفير كامل للقرص الصلب مع إمكانية الإنكار المعقول. يدعم أنظمة التشغيل المتعددة.', 'تشفير كامل للقرص الصلب', 229.00, 299.00, 70, 'ET-003', '4.2.1', 'single', 'Windows/Linux', 4.8, 245, 1),
(1, 5, 'PasswordVault Manager', 'passwordvault-manager', 'مدير كلمات مرور آمن مع تشفير AES-256 وتزامن سحابي ومولد كلمات مرور قوية.', 'مدير كلمات المرور الآمن', 79.00, 99.00, 250, 'ET-004', '5.3.0', 'multi', 'Windows/Mac/iOS/Android', 4.6, 567, 0),
(2, 5, 'VPN CipherShield', 'vpn-ciphershield', 'شبكة VPN مشفرة بالكامل مع بروتوكولات WireGuard وOpenVPN. لا سجلات، حماية DNS.', 'VPN مشفر بدون سجلات', 129.00, 169.00, 180, 'ET-005', '2.1.5', 'single', 'Windows/Mac/Linux/Mobile', 4.9, 389, 1),
-- اختبار الاختراق
(3, 6, 'PenTest Framework Pro', 'pentest-framework-pro', 'إطار عمل شامل لاختبار الاختراق الأخلاقي. يتضمن أكثر من 200 أداة مدمجة واستغلال آلي للثغرات.', 'إطار عمل اختبار الاختراق الشامل', 799.00, 999.00, 25, 'PT-001', '5.0.1', 'enterprise', 'Linux/Kali', 4.9, 312, 1),
(1, 6, 'WebPenTester Suite', 'webpentester-suite', 'مجموعة أدوات متكاملة لاختبار اختراق تطبيقات الويب. يشمل Spider وScanner وExploiter.', 'مجموعة اختبار اختراق الويب', 399.00, 499.00, 40, 'PT-002', '3.5.0', 'multi', 'Windows/Linux', 4.7, 189, 1),
(2, 6, 'NetworkPenTest Pro', 'networkpentest-pro', 'اختبار اختراق الشبكات مع مسح المنافذ واستغلال الثغرات وتقارير تفصيلية.', 'اختبار اختراق الشبكات المتقدم', 499.00, 649.00, 30, 'PT-003', '4.1.2', 'enterprise', 'Linux/Windows', 4.8, 145, 0),
(3, 6, 'SocialEng Toolkit', 'socialeng-toolkit', 'أداة تعليمية لمحاكاة هجمات الهندسة الاجتماعية والتصيد. للاستخدام في بيئات الاختبار فقط.', 'محاكي هجمات الهندسة الاجتماعية', 249.00, 319.00, 50, 'PT-004', '2.0.3', 'single', 'Windows/Linux', 4.5, 98, 0),
(1, 6, 'WiFiCrack Auditor', 'wificrack-auditor', 'أداة تدقيق أمان WiFi باستخدام تقنيات متقدمة. للاستخدام على الشبكات المملوكة فقط.', 'مدقق أمان شبكات WiFi المتقدم', 349.00, 449.00, 35, 'PT-005', '3.2.0', 'single', 'Linux/Kali', 4.6, 167, 1);

-- مراجعات تجريبية
INSERT INTO reviews (product_id, user_id, rating, title, comment) VALUES
(1, 5, 5, 'أداة ممتازة', 'استخدمتها في بيئة الاختبار وكانت النتائج دقيقة جداً'),
(1, 6, 4, 'جيدة مع بعض التحسينات', 'الواجهة ممتازة لكن تحتاج لتحسين في سرعة الفحص'),
(6, 7, 5, 'أفضل مكافح فيروسات', 'لم تمر أي ثغرة دون اكتشافها، رائع!'),
(11, 8, 5, 'جدار حماية مذهل', 'يوفر حماية متكاملة بدون تأثير على الأداء'),
(16, 9, 4, 'محلل شبكات ممتاز', 'يوفر رؤية واضحة لكل حركة الشبكة');

-- طلبات تجريبية
INSERT INTO orders (user_id, order_number, status, subtotal, tax, total, billing_name, billing_email) VALUES
(5, 'ORD-2025-001', 'delivered', 299.00, 44.85, 343.85, 'Ali Hassan', 'customer1@example.com'),
(6, 'ORD-2025-002', 'processing', 199.00, 29.85, 228.85, 'Sara Ahmed', 'customer2@example.com'),
(7, 'ORD-2025-003', 'pending', 449.00, 67.35, 516.35, 'Fatima Omar', 'customer3@example.com');

INSERT INTO order_items (order_id, product_id, seller_id, product_name, quantity, price, total) VALUES
(1, 1, 1, 'VulnScan Pro', 1, 299.00, 299.00),
(2, 2, 1, 'WebVulner Scanner', 1, 199.00, 199.00),
(3, 3, 2, 'NetVuln Detector', 1, 449.00, 449.00);
