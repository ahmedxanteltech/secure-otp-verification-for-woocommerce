=== Secure OTP Verification for WooCommerce ===
Contributors: xanteltechnologies
Tags: otp, email verification, woocommerce, login, registration, checkout, security, anti-spam
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure email OTP verification for WooCommerce — Registration, Login and Checkout.

== Description ==

Secure OTP Verification for WooCommerce adds an email-based One Time Password (OTP) verification layer to your WooCommerce store.
Stop spam registrations, brute-force logins, and fraudulent checkouts with a simple 6-digit OTP sent to the user's email.

**Key Features:**

* OTP verification for Registration, Login and/or Checkout
* Passwordless (OTP-only) login option
* Admin toggle to completely disable password login
* Skip checkout OTP for logged-in customers, while guest checkouts always stay verified (togglable)
* Trusted-device login for password-based sign-in — a device that already passed password + OTP isn't asked for OTP again for an admin-configurable number of days (the password-less OTP-only tab always requires a fresh code, since it has no password backstop)
* Checkout OTP Behavior: require OTP before placing an order, or flag unverified orders for admin review instead of blocking checkout — your choice, with an Email Verified column on the Orders list
* Built-in debug log — every send/verify attempt is recorded with the exact reason for any failure (rate limit, wrong code, mail delivery error), viewable in Settings → Secure OTP without needing server access
* Auto-submit on login/checkout OTP once all 6 digits are entered — no need to click a submit button, though it stays available as a fallback
* Email Verified column in Dashboard → Users
* Bulk mark users as verified/unverified, and bulk "forget" a user's trusted login devices
* Rate limiting (max 3 OTPs per 10 minutes per email)
* Automatic OTP expiry (2 minutes)
* Hourly cleanup of expired OTPs
* Works with any SMTP plugin (Easy WP SMTP, WP Mail SMTP, etc.)
* Clean, responsive OTP email that uses your store's name

== Installation ==

1. Upload the `secure-otp-verification-for-woocommerce` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Secure OTP to configure
4. Ensure your SMTP plugin is configured to send emails

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes. Secure OTP Verification for WooCommerce hooks directly into WooCommerce's registration, login, and checkout flows, so an active WooCommerce installation is required.

= Will OTP emails actually get delivered? =

That depends on your site's outgoing email setup. Plain `wp_mail()` without SMTP is unreliable on many hosts. We recommend pairing this plugin with a dedicated SMTP plugin (Easy WP SMTP, WP Mail SMTP, or your host's own mail service) so OTP emails land reliably and don't get marked as spam.

= Can I require OTP for only one of registration, login, or checkout? =

Yes. Go to Settings → Secure OTP and choose "Registration Only," "Registration + Login," or "Registration + Login + Checkout."

= What happens if a customer enters the wrong OTP too many times? =

After 5 incorrect attempts for the same email and purpose within 10 minutes, further verification attempts are blocked until the window resets. This protects against brute-force guessing.

= Does disabling password login lock me out of my own site? =

No. Administrators (users who can manage_options) are always exempt from the OTP-only login requirement, so you can never lock yourself out.

= Why don't my customers see a password field when registering? =

This is a WooCommerce setting, not this plugin — check WooCommerce → Settings → Accounts & Privacy → "When creating an account, automatically generate an account password." If that's checked, WooCommerce hides the password field and only ever tells the customer their password by email. Combined with OTP verification, this can create a complete lockout with no fallback: if that one email is missed or fails to deliver, the customer has no password, and password reset is also email-based. We recommend unchecking it so customers set their own password at registration, with OTP as the added verification layer on top. This plugin shows a warning in wp-admin automatically if this setting is on.

= Do returning customers have to enter an OTP at every checkout? =

By default, logged-in customers skip checkout OTP entirely, since they already proved their account at registration or login. Guest checkouts always require OTP, since there's no account behind them to trust. You can turn this off in Settings → Secure OTP if you'd rather require a fresh OTP at every checkout regardless of login status.

= Do returning customers have to enter an OTP at every login? =

Not when logging in with a password. After a successful password + OTP login, that device is remembered for a number of days you set in Settings → Secure OTP (default 30), and each use extends that window — a new device, a cleared cookie, or an expired window still requires OTP. This only applies to password-based login: the password-less "Login with OTP" tab always requires a fresh OTP, since without a password there's nothing else confirming who's signing in. You can force re-verification for a specific user via Users → Bulk Actions → "Forget Trusted Login Devices."

= What happens to checkout if OTP emails stop sending (e.g. an SMTP outage)? =

Depends on your Checkout OTP Behavior setting. With "Require OTP" (the default), checkout is blocked until email delivery is working again — strictest, but any outage stops orders entirely. With "Flag for review," checkout never blocks: orders go through as normal, and each one is marked "Email Verified: Yes/No" on the Orders list and order screen based on whether the billing email has a verified email on file, so you can review unverified ones after the fact instead of losing the sale. We recommend "Flag for review" for stores where checkout downtime directly costs revenue.

= Does OTP also protect wp-login.php, XML-RPC, or the REST API? =

No. Login OTP is enforced on WooCommerce's own login form (My Account and the checkout login box). WordPress's built-in `wp-login.php`, XML-RPC and REST/application-password logins don't show an OTP field, so they are not covered when password login is allowed — treat this plugin as email verification and passwordless login for your storefront, not as a replacement for two-factor authentication on wp-admin. With "Force OTP-only login" enabled, password logins through those routes are refused for everyone except administrators. To harden admin access, pair this plugin with a dedicated 2FA or login-security plugin.

= Does this plugin send any data to third-party servers? =

No. The free version only uses your site's own WordPress database and your site's own `wp_mail()` function to send OTP emails. No external API calls, no third-party services, no data leaves your server.

= What personal data does the plugin store? =

Email addresses and one-time codes (valid for 2 minutes, cleaned up hourly), a short activity log with email address, event and timestamp (deleted after 7 days), the date an account's email was verified, a Yes/No "email verified" marker on orders, and — only if a customer chooses to remember a device at login — a hashed token (the device keeps the matching random value in a cookie). A suggested paragraph is added to Settings → Privacy → Policy Guide. Deleting the plugin keeps this data unless you tick "Delete all plugin data" in Settings → Secure OTP first.

= After logging in, the home page still shows me as logged out. Why? =

Some caching or performance plugins (for example WP Rocket's "Preload Links" or similar link-prefetching features) load pages in the background while a visitor is still on the login screen. The browser can then keep showing that older logged-out copy for a short time after login; a hard refresh (Ctrl+Shift+R) shows the correct page. It affects any login method, not only OTP, and the plugin cannot control it. Turn off link preloading/prefetching in your caching plugin, and make sure your page cache is not serving cached pages to logged-in users.

= Does this work with Elementor, Divi, or other page builders? =

Yes, when the builder is used to style or lay out a page around WooCommerce's own registration, login, or checkout form (its own shortcodes/blocks) — this is the vast majority of page-builder usage, and the plugin's field detection adapts to custom markup automatically. It does not currently integrate with separate custom-form tools that replace WooCommerce's own forms entirely — for example Elementor Pro's own Login widget, Gravity Forms' or WPForms' user-registration add-ons, or membership plugins like Ultimate Member — since these process registration/login through their own systems rather than WooCommerce's. Support for specific tools may be added in future based on demand.

= Is a Pro version available? =

A Pro version with WhatsApp/SMS OTP, magic-link login, and an analytics dashboard is in development. The current version is fully functional on its own for email-based OTP verification.

== Screenshots ==

1. Email OTP field on the WooCommerce registration form
2. Login page with password + OTP and OTP-only tabs, plus trusted-device login
3. Checkout email verification step for guest checkout
4. Admin settings — OTP mode, checkout behavior, trusted-device duration
5. Email Verified column on the Orders list (Flag for review mode)
6. Built-in debug log showing send/verify activity and failure reasons

== Changelog ==

= 1.2.9 =
* Changed: internal prefix renamed from "xeo_" to the longer, unique "xsotp_" (WordPress.org requires at least four characters). Existing settings carry over automatically on update; customers who use "remember this device" will be asked for a login OTP once more, and existing orders keep their Verified/Unverified badge.
* Changed: the OTP email sent to customers now carries your store's name in its header, body and footer instead of the plugin author's name and link, since the email is yours to send.
* Fixed: the "max 3 OTP emails per 10 minutes per email address" limit never actually triggered, so an address could be sent unlimited codes. It is now enforced (per email address and purpose) and refused attempts appear in the Debug Log.
* Security hardening ahead of the WordPress.org listing: every request value is now unslashed before sanitizing; the OTP Mode setting is validated against its allowed values on save; admin-only notices and the dismiss handler now require the manage_options capability; settings-page output is escaped; OTP-status messages are inserted into the page as plain text rather than HTML.
* Fixed: the hourly cleanup job was scheduled on every page load and never removed. It is now scheduled on activation (and re-checked after updates), cleared on deactivation, and removed on uninstall even when you keep your data. Cleanup cutoffs now use the same clock as the stored timestamps instead of the database server's clock.
* Fixed: dates are generated with gmdate() so they can't shift if another plugin changes PHP's default timezone.
* Added: Requires at least / Requires PHP / Requires Plugins headers, a suggested privacy-policy paragraph under Settings → Privacy, and FAQ entries covering what login OTP does and doesn't protect (wp-login.php, XML-RPC, REST) and what data the plugin stores.

= 1.2.8.3 =
* Fixed: on themes that wrap the entire login form's contents in their own container (observed on the Nasa theme: a `wrap-all` div kept at `display:none` until the theme's own toggle opens it), the OTP-only fields stayed invisible on the Checkout page's "Click here to login" box even though 1.2.8.2 was correctly setting their own display to visible — a hidden ancestor hides its descendants regardless of the descendant's own display setting. The OTP-only block is now moved out to be a direct child of the login `<form>` itself before anything is shown, so it's never trapped inside a theme's own hidden wrapper.

= 1.2.8.2 =
* Fixed: with "Force OTP-only login" enabled, opening the "Click here to login" box on the Checkout page could show an empty/unmodified login form instead of the OTP-only fields. WooCommerce's checkout login box uses the exact same markup class and hooks as the My Account login page, and the script that swaps in the OTP-only fields was using a page-wide "first match" lookup instead of checking each login form individually — now every matching login form on the page is handled on its own, and a form that doesn't contain our OTP markup is left untouched instead of having its fields hidden with nothing shown in their place.

= 1.2.8.1 =
* Fixed: pressing Enter while focused in the OTP-only login's email/code fields could trigger WooCommerce's own native form submission (since those fields still live inside WooCommerce's shared login form), producing a "Username is required" notice. Enter is now explicitly intercepted and routed to the same action as clicking Send OTP / Login with OTP.
* Fixed: on some setups, the page landed on after a successful OTP-only login could still show the login form despite a valid, correctly-set session cookie. Root cause: the browser's own speculative prefetching (e.g. Chrome's "Preload pages" feature) can fetch the destination page before login completes, then serve that stale pre-login copy from its local prefetch cache instead of requesting the page again — bypassing the server (and any server-side cache) entirely. The post-login redirect URL now includes a unique parameter so the browser can't satisfy it from a prefetched copy.

= 1.2.7 =
* Changed: OTP-only login ("Login with OTP" tab / forced OTP-only mode) is now handled entirely over AJAX instead of a native form submission. This avoids a class of theme-compatibility issues where a theme's own hidden-but-required fields block browser form validation, a theme's own JavaScript intercepts the login form submission, or a caching layer serves a stale page after the redirect that followed a successful login. The redirect to My Account now happens client-side, after the session cookie is already set.
* Added: a cache-compatibility admin notice that detects common caching plugins (LiteSpeed Cache, WP Rocket, W3 Total Cache, WP Fastest Cache, WP Super Cache) and warns if My Account / checkout may need to be excluded from page caching, with plugin-specific guidance.
* Fixed: all admin notices (password-login-disabled, WooCommerce auto-password warning, and the new cache-compatibility notice) are now properly dismissible and stay dismissed per-admin, instead of reappearing on every admin page with no way to close them.
* Added: expanded debug logging around the login flow so a login failure (or an auth cookie that doesn't persist) can be diagnosed from the Debug Log rather than needing browser DevTools or server access.
* Hardened: sends no-cache response headers during OTP-only login processing as a safety net against full-page caching plugins that don't recognize a custom login flow.

= 1.2.5 =
* Fixed: submitting the OTP-only login form could also trigger WooCommerce's own native login processing in the background (since both share the same outer form), producing a confusing "Username is required" notice even on a successful OTP login. WooCommerce's own login handler is now explicitly skipped for OTP-only submissions.

= 1.2.4 =
* Added: OTP fields on login (both tabs) and checkout verification now auto-submit once all 6 digits are entered — no click required, though the submit/verify button remains as a manual fallback. Not applied to registration, since that form has other required fields.

= 1.2.3 =
* Fixed: the "Login with OTP" submit button could fail to appear after entering the code, specifically in "Force OTP-only login" mode. Caused by an invalid nested <form> element that browsers silently drop during parsing; the OTP-only login fields no longer use their own <form> tag, since they were already inside WooCommerce's own login form.

= 1.2.2 =
* Added: a warning in wp-admin if WooCommerce's "automatically generate account password" setting is enabled — combined with this plugin, that setting can create a complete customer lockout with no fallback (no known password, and password reset is also email-based).
* Settings page header simplified.

= 1.2.1 =
* Added: a built-in Debug Log on the settings page. Every OTP send/verify attempt is now recorded with the exact reason for any failure (rate limit hit, wrong/expired code, mail delivery error with detail, invalid request, etc.), so "Send OTP isn't working" can be diagnosed from wp-admin without browser DevTools or server log access. Auto-clears entries older than 7 days; includes a manual "Clear Log" button.
* The database schema now auto-migrates on version update (no need to deactivate/reactivate) — this also means future table/column additions will apply automatically when files are updated in place.

= 1.2.0 =
* Added: "Checkout OTP Behavior" setting — choose between "Require OTP before placing order" (existing behavior) or "Flag unverified orders for review" (checkout is never blocked; orders are marked Email Verified: Yes/No on the Orders list and order screen instead, for HPOS and legacy order storage).

= 1.1.3 =
* Added: "On Plugin Deletion" setting — by default, deleting the plugin now preserves all data (OTP logs, verified users, trusted devices, settings) so a reinstall picks up where you left off. Opt in to full cleanup if you actually want it wiped.
* Hardened: the plugin now checks that WooCommerce is active before doing anything, and shows an admin notice instead of a fatal error if WooCommerce is deactivated.
* Declared compatibility with WooCommerce's High-Performance Order Storage (HPOS).

= 1.1.2 =
* When an OTP email fails to send, admins now see the actual underlying mail error (e.g. from wp_mail_failed) alongside the generic message, instead of a generic "check your SMTP settings" with no detail. Non-admin users still only see the generic message.

= 1.1.1 =
* Fixed: "Send OTP" could fail with a JavaScript error on themes that customize the registration/login/checkout markup and don't keep WooCommerce's default field ids. The plugin now falls back to WooCommerce's standard field names when the expected id isn't found.

= 1.1.0 =
* Renamed plugin to "Secure OTP Verification for WooCommerce" (previously "Xantel Email OTP").
* Added: option to skip checkout OTP for logged-in customers (guests always stay verified); togglable in Settings → Secure OTP.
* Added: trusted-device login for password-based sign-in — a device that already passed password + OTP isn't asked again for an admin-configurable number of days. The password-less OTP-only login tab always requires a fresh OTP, since it has no password to back it up.
* Added: "Forget Trusted Login Devices" bulk action on the Users screen.

= 1.0.1 =
* Security: OTP verification is now always checked server-side for registration, login and checkout; a client-supplied flag could previously skip OTP verification entirely.
* Security: added a per-email attempt limit (5 tries / 10 minutes) on OTP verification to prevent brute-force guessing.
* Security: OTP codes are now generated with wp_rand() instead of rand().
* Added uninstall.php to clean up plugin tables and settings on uninstall.

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.2.9 =
Security hardening and a cleanup-scheduling fix. The OTP email now shows your store's name instead of the plugin author's branding. Recommended update.

= 1.2.8.3 =
Fixes the Checkout "Click here to login" box still showing empty on themes (e.g. Nasa) that wrap login-form content in their own hidden container. Recommended update if 1.2.8.2 didn't fully resolve it for you.

= 1.2.8.2 =
Fixes the Checkout page's "Click here to login" box showing an empty form when "Force OTP-only login" is enabled. Recommended update if you use that setting.

= 1.2.8.1 =
Fixes two remaining OTP-only login issues: Enter key triggering a spurious WooCommerce error, and the destination page sometimes showing logged-out due to browser-side prefetch caching. Recommended update if you use OTP-only login.

= 1.2.7 =
OTP-only login is now AJAX-based for much better compatibility with custom themes and caching plugins, admin notices are now properly dismissible, and a new notice warns about caching plugins that may need My Account excluded. Recommended update, especially if you use "Force OTP-only login" mode or a page caching plugin.

= 1.2.5 =
Fixes a confusing spurious "Username is required" message that could appear after a successful OTP-only login. Recommended update if you use that mode.

= 1.2.4 =
Login and checkout OTP now auto-submit after 6 digits — one less click, one less thing that can go wrong on cached pages.

= 1.2.3 =
Fixes a missing submit button in "Force OTP-only login" mode. Important update if you use that mode.

= 1.2.2 =
Adds a warning for a WooCommerce setting that can silently cause customer lockouts when combined with this plugin.

= 1.2.1 =
Adds a built-in debug log for diagnosing OTP send/verify failures directly from wp-admin. Recommended update.

= 1.2.0 =
Adds a "Flag for review" checkout mode so an OTP delivery outage never has to block orders. Default behavior is unchanged unless you opt in.

= 1.1.3 =
Adds a data-preservation option for plugin deletion, and hardens the plugin against WooCommerce being deactivated. Recommended update.

= 1.1.2 =
Admins now see the real reason behind a failed OTP email send, for easier SMTP troubleshooting.

= 1.1.1 =
Fixes "Send OTP" failing on themes with a customized registration/login/checkout form. Recommended update.

= 1.1.0 =
Adds optional checkout OTP skip for logged-in customers and trusted-device login. Review the new settings after updating.

= 1.0.1 =
Important security fix — updates the OTP verification logic for registration, login and checkout. Please update as soon as possible.

= 1.0.0 =
Initial release by Xantel Technologies.
