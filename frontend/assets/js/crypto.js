// ================================================================
// crypto.js - طبقة التشفير للفرونت إند
// AES-256-GCM via Web Crypto API + Request Obfuscation
// ================================================================

/**
 * ══════════════════════════════════════════════════════════════
 * SecureCrypto: تشفير وفك تشفير الطلبات باستخدام Web Crypto API
 * يجعل البيانات في Network Tab غير مقروءة
 * ══════════════════════════════════════════════════════════════
 */
const SecureCrypto = {
  // مفتاح التشفير (يتم مشاركته مع الباك إند عبر env)
  _keyHex: 'c7b3a9f1e2d4b6a8c0e2f4d6b8a0c2e4f6d8a0b2c4e6f8a0b2c4d6e8f0a2b4',
  _cryptoKey: null,

  /**
   * تهيئة مفتاح التشفير من hex string
   */
  async init() {
    if (this._cryptoKey) return;
    const keyBytes = new Uint8Array(this._keyHex.match(/.{2}/g).map(b => parseInt(b, 16)));
    this._cryptoKey = await crypto.subtle.importKey(
      'raw', keyBytes, { name: 'AES-GCM' }, false, ['encrypt', 'decrypt']
    );
  },

  /**
   * تشفير البيانات بـ AES-256-GCM
   * @param {string} plaintext النص المراد تشفيره
   * @returns {string} Base64(nonce + ciphertext + tag)
   */
  async encrypt(plaintext) {
    await this.init();
    const nonce      = crypto.getRandomValues(new Uint8Array(12));
    const encoded    = new TextEncoder().encode(plaintext);
    const cipherBuf  = await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv: nonce, tagLength: 128 },
      this._cryptoKey, encoded
    );
    // GCM يضيف الـ tag في نهاية ciphertext تلقائياً
    const cipher = new Uint8Array(cipherBuf);
    // الترتيب: nonce (12) + tag (16) + ciphertext (N-16)
    const tag        = cipher.slice(-16);
    const ciphertext = cipher.slice(0, -16);
    const combined   = new Uint8Array(12 + 16 + ciphertext.length);
    combined.set(nonce, 0);
    combined.set(tag, 12);
    combined.set(ciphertext, 28);
    return btoa(String.fromCharCode(...combined));
  },

  /**
   * فك تشفير البيانات
   * @param {string} encoded Base64(nonce + tag + ciphertext)
   * @returns {string} النص الأصلي
   */
  async decrypt(encoded) {
    await this.init();
    const raw        = Uint8Array.from(atob(encoded), c => c.charCodeAt(0));
    const nonce      = raw.slice(0, 12);
    const tag        = raw.slice(12, 28);
    const ciphertext = raw.slice(28);
    // إعادة تجميع ciphertext + tag لـ Web Crypto API
    const combined = new Uint8Array(ciphertext.length + 16);
    combined.set(ciphertext, 0);
    combined.set(tag, ciphertext.length);
    const plainBuf = await crypto.subtle.decrypt(
      { name: 'AES-GCM', iv: nonce, tagLength: 128 },
      this._cryptoKey, combined
    );
    return new TextDecoder().decode(plainBuf);
  },

  /**
   * توليد nonce عشوائي لمنع replay attacks
   */
  generateNonce() {
    return btoa(String.fromCharCode(...crypto.getRandomValues(new Uint8Array(16))));
  },
};

/**
 * ══════════════════════════════════════════════════════════════
 * خريطة عمليات API - تخفي أسماء الـ endpoints الحقيقية
 * في Network Tab يظهر فقط POST /api/gateway.php مع operation ID مبهم
 * ══════════════════════════════════════════════════════════════
 */
const OP = {
  // Auth
  LOGIN:         'ax01',
  REGISTER:      'ax02',
  LOGOUT:        'ax03',
  CSRF:          'ax04',
  // Products
  PRODUCTS:      'px01',
  PRODUCT:       'px02',
  // Cart
  CART:          'cx01',
  // Orders
  ORDERS:        'ox01',
  // Payments
  PAYMENT:       'fx01',
  // Reviews
  REVIEWS:       'rx01',
  // Users
  USER:          'ux01',
  // Admin
  ADMIN_STATS:   'dx01',
  ADMIN_USERS:   'dx02',
  ADMIN_PING:    'dx03',
  // Seller
  SELLER_STATS:  'sx01',
};

/**
 * ══════════════════════════════════════════════════════════════
 * GatewayClient: يرسل الطلبات عبر API Gateway المشفر
 * بدلاً من fetch('/api/auth.php?action=login')
 * يرسل POST /api/gateway.php مع payload مشفر
 * ══════════════════════════════════════════════════════════════
 */
const GatewayClient = {
  /**
   * إرسال طلب مشفر عبر البوابة
   * @param {string} opId   معرّف العملية (من OP)
   * @param {object} options  { method, body, query }
   * @returns {object} الاستجابة المفكّكة
   */
  async send(opId, options = {}) {
    const payload = {
      method: options.method || 'GET',
      body:   options.body   || null,
      query:  options.query  || {},
    };

    // تشفير الـ payload
    const encrypted = await SecureCrypto.encrypt(JSON.stringify(payload));

    const envelope = {
      op: opId,
      d:  encrypted,
      n:  SecureCrypto.generateNonce(),
      t:  Math.floor(Date.now() / 1000),
    };

    const csrfToken = sessionStorage.getItem('csrf_token') || '';

    const res = await fetch('/api/gateway.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
        'X-Request-ID': envelope.n,
      },
      credentials: 'include',
      body: JSON.stringify(envelope),
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'حدث خطأ في الاتصال');
    return data;
  },
};
