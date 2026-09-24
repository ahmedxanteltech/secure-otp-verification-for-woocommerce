jQuery(function ($) {
    'use strict';

    var timers          = {};
    var passwordDisabled = xeo_ajax.disable_password_login === '1';

    // ── Tab switcher ─────────────────────────────────────────────────────────
    window.xeoSwitchTab = function (tab) {
        if (tab === 'password') {
            style('#xeo-tab-password', { color: '#1a1a2e', borderBottomColor: '#1a1a2e' });
            style('#xeo-tab-otp',      { color: '#999',    borderBottomColor: 'transparent' });
            $('#username, #password').closest('.woocommerce-form-row').show();
            $('.lost_password, .woocommerce-form__label-for-checkbox').show();
            $('[name="login"][type="submit"]').closest('.form-row').show();
            $('#xeo-password-login-otp-section, #xeo-login-send-btn-row').show();
            $('#xeo-otp-only-login').hide();
        } else {
            style('#xeo-tab-otp',      { color: '#1a1a2e', borderBottomColor: '#1a1a2e' });
            style('#xeo-tab-password', { color: '#999',    borderBottomColor: 'transparent' });
            $('#username, #password').closest('.woocommerce-form-row').hide();
            $('.lost_password, .woocommerce-form__label-for-checkbox').hide();
            $('[name="login"][type="submit"]').closest('.form-row').hide();
            $('#xeo-password-login-otp-section, #xeo-login-send-btn-row').hide();
            $('#xeo-otp-only-login').show();
        }
    };

    function style(sel, css) { $(sel).css(css); }

    // Many WooCommerce themes replace the default account/checkout markup
    // with their own popup or block-based forms, and don't always keep
    // WooCommerce's default field ids (#reg_email, #billing_email, #username).
    // WooCommerce core itself relies on the field NAME to process
    // registration/checkout/login, so virtually every theme keeps those —
    // fall back to name-based lookup within the same form when the id we
    // expected isn't on the page.
    function resolveField(btn, selector) {
        var $field = selector ? $(selector) : $();
        if ($field.length) return $field;

        var $form = btn.closest('form');
        if (!$form.length) return $field;

        var purpose = btn.data('purpose');
        var candidates;
        if (purpose === 'registration') {
            candidates = 'input[name="email"], input[type="email"]';
        } else if (purpose === 'checkout') {
            candidates = 'input[name="billing_email"], input[type="email"]';
        } else if (purpose === 'login') {
            candidates = 'input[name="username"], input[name="log"], input[type="email"]';
        } else {
            candidates = 'input[type="email"]';
        }
        return $form.find(candidates).first();
    }

    // ── Auto-submit once all 6 digits are entered ──────────────────────────
    // Scoped to login and checkout-verify only — not registration, since
    // that form has other required fields (name, password, etc.) and
    // auto-submitting the whole thing the moment OTP hits 6 digits could
    // fire before the user's finished the rest of the form. Login and
    // checkout-verify are single-purpose "enter code → confirm" steps,
    // so immediate submission is exactly what's expected there. Also
    // removes reliance on the submit/verify button being visible — the
    // buttons stay as a manual fallback, but most users won't need them.
    var autoSubmitted = {};

    $(document).on('input', '#xeo_login_otp, #xeo_otp_login_code, #xeo_checkout_otp', function () {
        var $field = $(this);
        var id     = $field.attr('id');
        var val    = ($field.val() || '').trim();

        if (!/^\d{6}$/.test(val) || !$field.is(':visible')) return;
        if (autoSubmitted[id]) return;
        autoSubmitted[id] = true;

        if (id === 'xeo_checkout_otp') {
            $('#xeo-checkout-verify-otp').trigger('click');
        } else {
            // Both login OTP fields live inside WooCommerce's own outer
            // login <form> (there's no separate form to target), so this
            // submits the whole login form — exactly what clicking the
            // visible submit button would do. Using the native DOM method
            // rather than jQuery's trigger('submit'), which isn't reliable
            // for actually invoking browser form submission.
            var formEl = $field.closest('form')[0];
            if (formEl) {
                if (typeof formEl.requestSubmit === 'function') formEl.requestSubmit();
                else formEl.submit();
            }
        }
    });

    // Allow auto-submit to fire again if the user re-focuses the field
    // after a failed attempt (e.g. wrong code) and retypes.
    $(document).on('focus', '#xeo_login_otp, #xeo_otp_login_code, #xeo_checkout_otp', function () {
        autoSubmitted[$(this).attr('id')] = false;
    });

    // ── Send OTP ─────────────────────────────────────────────────────────────
    $(document).on('click', '.xeo-send-otp-btn', function (e) {
        e.preventDefault();
        var btn        = $(this);
        var purpose    = btn.data('purpose');
        var showInput  = btn.data('show-input');
        var $field     = resolveField(btn, btn.data('email-field'));
        var email      = ($field.val() || '').trim();

        if (!email) { xeoShowMsg(purpose, xeo_ajax.messages.enter_email, 'error'); return; }

        btn.prop('disabled', true).text('Sending OTP…');

        $.ajax({
            url:  xeo_ajax.ajax_url,
            type: 'POST',
            data: { action: 'xeo_send_otp', nonce: xeo_ajax.nonce, email: email, purpose: purpose },
            success: function (res) {
                if (res.success) {
                    if (showInput) $('#' + showInput).show();

                    if (purpose === 'login' && !$('#xeo-otp-login-form').is(':visible')) {
                        $('#xeo-password-login-otp-section').show();
                        $('#xeo-login-send-btn-row').hide();
                    }
                    if (purpose === 'login' && $('#xeo-otp-login-form').is(':visible')) {
                        $('#xeo-otp-login-otp-row').show();
                        $('#xeo-otp-login-send-row').hide();
                        $('#xeo-otp-login-submit-row').show();
                    }
                    if (purpose === 'checkout') {
                        $('#xeo-checkout-send-section').hide();
                        $('#xeo-checkout-verify-section').show();
                    }

                    startTimer(purpose);
                    xeoShowMsg(purpose, res.data.message, 'success');
                    btn.text('Resend OTP').prop('disabled', false);
                } else {
                    xeoShowMsg(purpose, res.data.message, 'error');
                    btn.text('Send OTP to Email').prop('disabled', false);
                }
            },
            error: function () {
                xeoShowMsg(purpose, 'Something went wrong. Please try again.', 'error');
                btn.text('Send OTP to Email').prop('disabled', false);
            }
        });
    });

    // ── Checkout inline verify ────────────────────────────────────────────────
    $(document).on('click', '#xeo-checkout-verify-otp', function (e) {
        e.preventDefault();
        var btn   = $(this);
        var $field = resolveField(btn, btn.data('email-field'));
        var email = ($field.val() || '').trim();
        var otp   = $('#xeo_checkout_otp').val();

        if (!otp || otp.length !== 6) { xeoShowMsg('checkout', 'Please enter a valid 6-digit OTP.', 'error'); return; }

        btn.prop('disabled', true).text('Verifying…');

        $.ajax({
            url:  xeo_ajax.ajax_url,
            type: 'POST',
            data: { action: 'xeo_verify_otp', nonce: xeo_ajax.nonce, email: email, otp: otp, purpose: 'checkout' },
            success: function (res) {
                if (res.success) {
                    $('#xeo_checkout_verified').val('1');
                    $('#xeo-checkout-verify-section').hide();
                    $('#xeo-checkout-send-section').hide();
                    $('#xeo-checkout-verified-msg').show();
                    stopTimer('checkout');
                } else {
                    xeoShowMsg('checkout', res.data.message, 'error');
                    btn.prop('disabled', false).text('Verify');
                }
            }
        });
    });

    // ── Resend OTP ───────────────────────────────────────────────────────────
    $(document).on('click', '.xeo-resend-otp', function (e) {
        e.preventDefault();
        var link       = $(this);
        var purpose    = link.data('purpose');
        var $field     = resolveField(link, link.data('email-field'));
        var email      = ($field.val() || '').trim();

        link.hide();
        stopTimer(purpose);

        $.ajax({
            url:  xeo_ajax.ajax_url,
            type: 'POST',
            data: { action: 'xeo_send_otp', nonce: xeo_ajax.nonce, email: email, purpose: purpose },
            success: function (res) {
                if (res.success) { startTimer(purpose); xeoShowMsg(purpose, xeo_ajax.messages.otp_resent, 'success'); }
                else             { xeoShowMsg(purpose, res.data.message, 'error'); link.show(); }
            }
        });
    });

    // ── Timer ────────────────────────────────────────────────────────────────
    function startTimer(purpose) {
        stopTimer(purpose);
        var seconds  = parseInt(xeo_ajax.expiry, 10);
        var isOtpForm = purpose === 'login' && $('#xeo-otp-login-form').is(':visible');
        var timerId  = isOtpForm ? '#xeo-otp-login-timer'  : '#xeo-' + purpose + '-timer';
        var resendId = isOtpForm ? '#xeo-otp-login-resend' : '#xeo-' + purpose + '-resend';

        $(resendId).hide();
        timers[purpose] = setInterval(function () {
            seconds--;
            var m = Math.floor(seconds / 60), s = seconds % 60;
            $(timerId).text('Expires in ' + m + ':' + (s < 10 ? '0' : '') + s);
            if (seconds <= 0) { stopTimer(purpose); $(timerId).text('OTP expired.'); $(resendId).show(); }
        }, 1000);
    }

    function stopTimer(purpose) {
        if (timers[purpose]) { clearInterval(timers[purpose]); timers[purpose] = null; }
    }

    // ── Messages ─────────────────────────────────────────────────────────────
    function xeoShowMsg(purpose, message, type) {
        var color = type === 'success' ? '#27ae60' : '#e74c3c';
        var bg    = type === 'success' ? '#eafaf1' : '#fdf2f0';
        var id    = 'xeo-msg-' + purpose;
        var el    = $('#' + id);

        if (!el.length) {
            var anchor = purpose === 'checkout'
                ? '.xeo-checkout-otp-wrapper'
                : '#xeo-otp-login-send-row, #xeo-' + purpose + '-send-btn-row';
            $(anchor).first().before('<div id="' + id + '" style="padding:10px 15px;border-radius:4px;margin:10px 0;font-size:14px;"></div>');
            el = $('#' + id);
        }
        el.css({ background: bg, color: color, border: '1px solid ' + color }).html(message).show();
        setTimeout(function () { el.fadeOut(400); }, 5000);
    }
});
