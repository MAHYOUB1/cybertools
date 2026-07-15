// ================================================================
// main.js - JavaScript مشترك لجميع الصفحات (نسخة آمنة)
// CyberTools Store - يستخدم Google Material Icons
// Encrypted API Client + XSS-safe DOM manipulation
// ================================================================

const API_BASE = '/api';

// ────────────────────────────────────────────────────────────────
// إدارة الجلسة الآمنة
// ────────────────────────────────────────────────────────────────
const Auth = {
  getUser() {
    try { return JSON.parse(sessionStorage.getItem('ct_user')); }
    catch { return null; }
  },
  setUser(u) {
    // تعقيم بيانات المستخدم قبل التخزين
    if (u) {
      u.name     = DOMPurify.sanitize(u.name || '');
      u.username = DOMPurify.sanitize(u.username || '');
    }
    sessionStorage.setItem('ct_user', JSON.stringify(u));
  },
  clear() {
    sessionStorage.removeItem('ct_user');
    sessionStorage.removeItem('csrf_token');
  },
  isLoggedIn() { return !!this.getUser(); },
  setCSRF(token) { sessionStorage.setItem('csrf_token', token); },
  getCSRF() { return sessionStorage.getItem('csrf_token') || ''; },
};

// ────────────────────────────────────────────────────────────────
// DOMPurify مبسّط - لتعقيم HTML قبل الإدراج
// ────────────────────────────────────────────────────────────────
const DOMPurify = {
  sanitize(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }
};

// ────────────────────────────────────────────────────────────────
// Toast Notifications - Material Icons
// ────────────────────────────────────────────────────────────────
function showToast(message, type = 'info', duration = 3500) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }
  const icons = {
    success: '<span class="icon icon-green">check_circle</span>',
    error:   '<span class="icon icon-red">error</span>',
    info:    '<span class="icon icon-cyan">info</span>',
    warning: '<span class="icon icon-yellow">warning</span>',
  };
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  // تعقيم الرسالة ضد XSS
  const safeMsg = DOMPurify.sanitize(message);
  toast.innerHTML = `${icons[type] || icons.info}<span>${safeMsg}</span>`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.animation = 'slideIn 0.3s ease reverse';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

// ────────────────────────────────────────────────────────────────
// HTTP Client - يدعم الوضعين: مباشر ومشفر عبر Gateway
// ────────────────────────────────────────────────────────────────

/**
 * طلب API مباشر (للتوافق مع الصفحات الحالية)
 * يمر عبر /api/ مع إضافة CSRF token تلقائياً
 */
async function api(endpoint, options = {}) {
  try {
    const csrfToken = Auth.getCSRF();
    const headers = {
      'Content-Type': 'application/json',
      ...options.headers,
    };
    if (csrfToken) {
      headers['X-CSRF-Token'] = csrfToken;
    }

    const res = await fetch(API_BASE + endpoint, {
      headers,
      credentials: 'include',
      ...options,
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'حدث خطأ في الاتصال');

    // حفظ CSRF token إذا تم إرجاعه
    if (data.csrf_token) {
      Auth.setCSRF(data.csrf_token);
    }

    return data;
  } catch (err) {
    // لا نكشف تفاصيل الخطأ في وحدة التحكم في الإنتاج
    if (window.location.hostname === 'localhost') {
      console.warn('[API]', err.message);
    }
    throw err;
  }
}

/**
 * طلب API مشفر عبر Gateway
 * يخفي اسم الـ endpoint والبيانات في Network Tab
 */
async function secureApi(opId, options = {}) {
  if (typeof GatewayClient !== 'undefined') {
    return GatewayClient.send(opId, options);
  }
  // fallback إلى API المباشر إذا لم يتم تحميل crypto.js
  return api(options._fallbackEndpoint || '', options);
}

// ────────────────────────────────────────────────────────────────
// إدارة السلة (تستخدم sessionStorage بدلاً من localStorage)
// ────────────────────────────────────────────────────────────────
const Cart = {
  get() {
    try { return JSON.parse(sessionStorage.getItem('ct_cart')) || []; }
    catch { return []; }
  },
  save(items) {
    sessionStorage.setItem('ct_cart', JSON.stringify(items));
    this.updateBadge();
  },
  count() { return this.get().reduce((s, i) => s + i.quantity, 0); },
  total() { return this.get().reduce((s, i) => s + i.price * i.quantity, 0); },
  updateBadge() {
    document.querySelectorAll('.cart-badge').forEach(badge => {
      const n = this.count();
      badge.textContent = n;
      badge.style.display = n > 0 ? 'flex' : 'none';
    });
  },
  add(product, qty = 1) {
    const items = this.get();
    const idx   = items.findIndex(i => i.id === product.id);
    if (idx >= 0) items[idx].quantity += qty;
    else items.push({ ...product, quantity: qty });
    this.save(items);
    showToast(`تمت إضافة "${DOMPurify.sanitize(product.name)}" للسلة`, 'success');
  },
  remove(id) { this.save(this.get().filter(i => i.id !== id)); },
  clear()    { this.save([]); },
};

// ────────────────────────────────────────────────────────────────
// تحديث Navbar الآمن - يستخدم textContent بدلاً من innerHTML حيثما أمكن
// ────────────────────────────────────────────────────────────────
function updateNavbar() {
  const user    = Auth.getUser();
  const actions = document.querySelector('.navbar-actions');
  if (!actions) return;

  Cart.updateBadge();

  const cartBtn = `
    <a href="/cart.html" class="cart-btn" id="nav-cart">
      <span class="icon">shopping_cart</span>
      <span>السلة</span>
      <span class="cart-badge" style="display:${Cart.count() > 0 ? 'flex' : 'none'}">${Cart.count()}</span>
    </a>`;

  if (user) {
    // تعقيم اسم المستخدم لمنع XSS
    const safeName = DOMPurify.sanitize(user.name || user.username);
    const adminLink = user.role === 'admin'
      ? `<a href="/admin-dashboard.html" class="dropdown-item"><span class="icon icon-sm">admin_panel_settings</span> لوحة المسؤول</a>` : '';
    const sellerLink = user.role === 'seller'
      ? `<a href="/seller-dashboard.html" class="dropdown-item"><span class="icon icon-sm">storefront</span> لوحة البائع</a>` : '';

    actions.innerHTML = `
      ${cartBtn}
      <div class="user-menu" style="position:relative">
        <button onclick="toggleUserMenu()" class="user-menu-btn">
          <span class="icon icon-sm">account_circle</span>
          <span>${safeName}</span>
          <span class="icon icon-sm">expand_more</span>
        </button>
        <div id="user-dropdown" style="display:none;position:absolute;right:0;top:calc(100% + 8px);background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:0.5rem;min-width:190px;z-index:1000;box-shadow:0 8px 32px rgba(0,0,0,0.4)">
          <a href="/profile.html" class="dropdown-item"><span class="icon icon-sm">manage_accounts</span> الملف الشخصي</a>
          ${adminLink}
          ${sellerLink}
          <hr style="border-color:var(--border);margin:0.4rem 0">
          <button onclick="logout()" class="dropdown-item" style="width:100%;background:none;border:none;cursor:pointer;color:var(--accent-red)">
            <span class="icon icon-sm">logout</span> تسجيل الخروج
          </button>
        </div>
      </div>`;
  } else {
    actions.innerHTML = `
      ${cartBtn}
      <a href="/login.html" class="btn-login">تسجيل الدخول</a>
      <a href="/register.html" class="btn-register">إنشاء حساب</a>`;
  }
}

function toggleUserMenu() {
  const d = document.getElementById('user-dropdown');
  if (d) d.style.display = d.style.display === 'none' ? 'block' : 'none';
}

async function logout() {
  try { await api('/auth.php?action=logout', { method: 'POST' }); } catch {}
  Auth.clear();
  Cart.clear();
  showToast('تم تسجيل الخروج بنجاح', 'info');
  setTimeout(() => window.location.href = '/index.html', 800);
}

// ────────────────────────────────────────────────────────────────
// تنسيق العملة
// ────────────────────────────────────────────────────────────────
function formatPrice(n) {
  return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(n);
}

// ────────────────────────────────────────────────────────────────
// Stars Rating - Material Icons
// ────────────────────────────────────────────────────────────────
function renderStars(rating) {
  const full  = Math.floor(rating);
  const half  = rating % 1 >= 0.5;
  const empty = 5 - full - (half ? 1 : 0);
  let stars   = '';
  for (let i = 0; i < full;  i++) stars += '<span class="icon icon-yellow icon-sm">star</span>';
  if (half)                        stars += '<span class="icon icon-yellow icon-sm">star_half</span>';
  for (let i = 0; i < empty; i++) stars += '<span class="icon icon-muted icon-sm">star_border</span>';
  return stars;
}

// ────────────────────────────────────────────────────────────────
// أداة آمنة لإنشاء عناصر DOM (بديل innerHTML)
// ────────────────────────────────────────────────────────────────
function safeElement(tag, attrs = {}, text = '') {
  const el = document.createElement(tag);
  Object.entries(attrs).forEach(([k, v]) => el.setAttribute(k, v));
  if (text) el.textContent = text;
  return el;
}

// ────────────────────────────────────────────────────────────────
// تهيئة الصفحة
// ────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  updateNavbar();

  // إغلاق القائمة عند الضغط خارجها
  document.addEventListener('click', e => {
    const d = document.getElementById('user-dropdown');
    if (d && !e.target.closest('.user-menu')) d.style.display = 'none';
  });

  // Fade-in animation عند التمرير
  const observer = new IntersectionObserver(
    entries => entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('fade-in'); }),
    { threshold: 0.1 }
  );
  document.querySelectorAll('.product-card, .stat-card, .card').forEach(el => observer.observe(el));

  // جلب CSRF token عند تحميل الصفحة إذا كان المستخدم مسجّلاً
  if (Auth.isLoggedIn() && !Auth.getCSRF()) {
    api('/auth.php?action=csrf', { method: 'GET' }).catch(() => {});
  }
});
