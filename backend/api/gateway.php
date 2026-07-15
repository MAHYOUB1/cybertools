<?php
// ================================================================
// api/gateway.php - نقطة الدخول المركزية المشفرة
// جميع طلبات الفرونت إند تمر عبر هذا الملف
// يخفي مسارات API الحقيقية عن Network Tab
// ================================================================
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/security.php';
setHeaders();

// معالجة الطلب عبر البوابة المشفرة
APIGateway::handleRequest();
