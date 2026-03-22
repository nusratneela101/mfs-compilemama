/**
 * MFS Compilemama — Main JavaScript
 * Handles: Toasts, OTP timer, Form validation, AJAX, Loading states
 */

'use strict';

// ============================================================
// Toast Notification System
// ============================================================
const Toast = {
    show(message, type = 'info', duration = 4000) {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const icons = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' };
        const id     = 'toast_' + Date.now();

        const el = document.createElement('div');
        el.id    = id;
        el.className = `toast mfs-toast toast-${type} show align-items-center mb-2`;
        el.setAttribute('role', 'alert');
        el.innerHTML = `
            <div class="d-flex align-items-center p-3">
                <span class="me-2 fs-5">${icons[type] || 'ℹ️'}</span>
                <div class="me-auto">${message}</div>
                <button type="button" class="btn-close ms-2" onclick="document.getElementById('${id}').remove()"></button>
            </div>`;

        container.appendChild(el);

        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transition = 'opacity 0.4s';
            setTimeout(() => el.remove(), 400);
        }, duration);
    },

    success(msg, d)  { this.show(msg, 'success', d); },
    error(msg, d)    { this.show(msg, 'error', d); },
    info(msg, d)     { this.show(msg, 'info', d); },
    warning(msg, d)  { this.show(msg, 'warning', d); },
};

// ============================================================
// Page Loader
// ============================================================
window.addEventListener('load', () => {
    const loader = document.getElementById('pageLoader');
    if (loader) {
        loader.classList.add('hide');
        setTimeout(() => loader.remove(), 600);
    }
});

// ============================================================
// CSRF Token helper
// ============================================================
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// ============================================================
// AJAX Helper
// ============================================================
async function apiRequest(url, data = {}, method = 'POST') {
    try {
        const body     = new FormData();
        body.append('csrf_token', getCsrfToken());
        for (const [k, v] of Object.entries(data)) body.append(k, v);

        const res  = await fetch(url, { method, body, credentials: 'same-origin' });
        const json = await res.json();
        return json;
    } catch (e) {
        console.error('API request failed:', e);
        return { success: false, message: 'Network error. Please try again.' };
    }
}

// ============================================================
// Button Loading State
// ============================================================
function setButtonLoading(btn, loading, originalText = null) {
    if (loading) {
        btn.dataset.originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-primary"></span> অপেক্ষা করুন...';
        btn.disabled  = true;
    } else {
        btn.innerHTML = originalText || btn.dataset.originalText || btn.innerHTML;
        btn.disabled  = false;
    }
}

// ============================================================
// OTP Countdown Timer
// ============================================================
function startOTPTimer(seconds, displayId, resendBtnId) {
    const display   = document.getElementById(displayId);
    const resendBtn = document.getElementById(resendBtnId);
    if (!display) return;

    let remaining = seconds;

    if (resendBtn) resendBtn.disabled = true;

    const interval = setInterval(() => {
        remaining--;
        const m = Math.floor(remaining / 60);
        const s = remaining % 60;
        display.textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;

        if (remaining <= 0) {
            clearInterval(interval);
            display.textContent = '00:00';
            display.style.color = '#d63031';
            if (resendBtn) {
                resendBtn.disabled = false;
                resendBtn.textContent = 'পুনরায় OTP পাঠান';
            }
        }
    }, 1000);

    return interval;
}

// ============================================================
// OTP Input Box Navigation
// ============================================================
function initOTPInputs() {
    const inputs = document.querySelectorAll('.otp-input');
    if (!inputs.length) return;

    inputs.forEach((input, i) => {
        input.addEventListener('input', e => {
            e.target.value = e.target.value.replace(/\D/g, '').slice(-1);
            if (e.target.value && inputs[i + 1]) inputs[i + 1].focus();
            collectOTP();
        });

        input.addEventListener('keydown', e => {
            if (e.key === 'Backspace' && !e.target.value && inputs[i - 1]) {
                inputs[i - 1].focus();
            }
        });

        input.addEventListener('paste', e => {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'');
            [...pasted].slice(0, inputs.length - i).forEach((ch, j) => {
                if (inputs[i + j]) inputs[i + j].value = ch;
            });
            const next = inputs[Math.min(i + pasted.length, inputs.length - 1)];
            if (next) next.focus();
            collectOTP();
        });
    });
}

function collectOTP() {
    const inputs = document.querySelectorAll('.otp-input');
    const hidden = document.getElementById('otpHidden');
    if (hidden) hidden.value = [...inputs].map(i => i.value).join('');
}

// ============================================================
// Bangladesh Phone Validation
// ============================================================
function validateBDPhone(phone) {
    return /^01[3-9]\d{8}$/.test(phone.trim());
}

// ============================================================
// Form Validation
// ============================================================
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', e => {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Phone number validation
    document.querySelectorAll('input[type="tel"], input[name="phone"]').forEach(input => {
        input.addEventListener('blur', () => {
            const valid = validateBDPhone(input.value);
            input.classList.toggle('is-invalid', !valid && input.value !== '');
            input.classList.toggle('is-valid', valid);
        });
    });

    // PIN confirmation
    const pinInput    = document.getElementById('pin');
    const pinConfirm  = document.getElementById('pin_confirm');
    if (pinInput && pinConfirm) {
        pinConfirm.addEventListener('input', () => {
            const match = pinInput.value === pinConfirm.value;
            pinConfirm.classList.toggle('is-invalid', !match);
            pinConfirm.classList.toggle('is-valid', match);
        });
    }
}

// ============================================================
// Payment method selection
// ============================================================
function initPaymentMethod() {
    const cards = document.querySelectorAll('.payment-method-card');
    const input = document.getElementById('paymentMethodInput');

    cards.forEach(card => {
        card.addEventListener('click', () => {
            cards.forEach(c => c.classList.remove('active'));
            card.classList.add('active');
            if (input) input.value = card.dataset.method;
        });
    });
}

// ============================================================
// MFS Portal: Tab action switching
// ============================================================
function initPortalTabs() {
    const recipientGroup = document.getElementById('recipientGroup');
    const amountGroup    = document.getElementById('amountGroup');
    const actionInput    = document.getElementById('actionType');
    const amountLabel    = document.getElementById('amountLabel');
    const recipientLabel = document.getElementById('recipientLabel');

    document.querySelectorAll('.portal-action-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.portal-action-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const action = btn.dataset.action;
            if (actionInput) actionInput.value = action;

            const hideRecipient = action === 'balance';

            if (recipientGroup) recipientGroup.style.display = hideRecipient ? 'none' : '';
            if (amountGroup)    amountGroup.style.display    = hideRecipient ? 'none' : '';

            if (action === 'balance') {
                document.getElementById('submitActionBtn').textContent = '💰 ব্যালেন্স চেক করুন';
            } else if (action === 'send') {
                document.getElementById('submitActionBtn').textContent = '📤 সেন্ড মানি';
                if (recipientLabel) recipientLabel.textContent = 'প্রাপকের নম্বর';
            } else if (action === 'cashout') {
                document.getElementById('submitActionBtn').textContent = '🏧 ক্যাশ আউট';
                if (recipientLabel) recipientLabel.textContent = 'এজেন্ট নম্বর';
            } else if (action === 'recharge') {
                document.getElementById('submitActionBtn').textContent = '📱 রিচার্জ করুন';
                if (recipientLabel) recipientLabel.textContent = 'মোবাইল নম্বর';
            } else if (action === 'payment') {
                document.getElementById('submitActionBtn').textContent = '🏪 পেমেন্ট করুন';
                if (recipientLabel) recipientLabel.textContent = 'মার্চেন্ট নম্বর / ID';
            }
        });
    });
}

// ============================================================
// Transaction form AJAX submit
// ============================================================
function initTransactionForm() {
    const form = document.getElementById('transactionForm');
    if (!form) return;

    form.addEventListener('submit', async e => {
        e.preventDefault();
        const btn    = form.querySelector('[type="submit"]');
        const result = document.getElementById('txResult');

        setButtonLoading(btn, true);
        if (result) result.innerHTML = '';

        const data = {
            mfs_provider: form.querySelector('[name="mfs_provider"]')?.value || '',
            type:         form.querySelector('[name="type"]')?.value || 'send',
            amount:       form.querySelector('[name="amount"]')?.value || '0',
            recipient:    form.querySelector('[name="recipient"]')?.value || '',
        };

        const res = await apiRequest('/api/transaction.php', data);
        setButtonLoading(btn, false);

        if (result) {
            const cls   = res.success ? 'alert-success' : 'alert-danger';
            const icon  = res.success ? '✅' : '❌';
            let extra   = '';
            if (res.reference) extra = `<br><small class="text-muted">Reference: <strong>${res.reference}</strong></small>`;
            if (res.balance)   extra += `<br><small class="text-muted">Balance: <strong>৳${res.balance}</strong></small>`;
            result.innerHTML = `<div class="alert ${cls} mt-3">${icon} ${res.message}${extra}</div>`;
        }

        if (res.success) {
            Toast.success(res.message);
            setTimeout(() => location.reload(), 3000);
        } else {
            Toast.error(res.message || 'লেনদেন ব্যর্থ হয়েছে।');
        }
    });
}

// ============================================================
// Auto-dismiss alerts
// ============================================================
function initAlertAutoDismiss() {
    document.querySelectorAll('.alert-auto-dismiss').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity    = '0';
            setTimeout(() => el.remove(), 500);
        }, 4000);
    });
}

// ============================================================
// Mobile admin sidebar toggle
// ============================================================
function initAdminSidebar() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.admin-sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    }
}

// ============================================================
// Number input: allow only digits
// ============================================================
function initNumericInputs() {
    document.querySelectorAll('input[data-numeric]').forEach(input => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/[^\d.]/g, '');
        });
    });
}

// ============================================================
// Init all
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    initFormValidation();
    initOTPInputs();
    initPaymentMethod();
    initPortalTabs();
    initTransactionForm();
    initAlertAutoDismiss();
    initAdminSidebar();
    initNumericInputs();

    // OTP timer (if present)
    const timerEl = document.getElementById('otpTimer');
    if (timerEl) {
        startOTPTimer(300, 'otpTimer', 'resendOtpBtn');
    }
});
