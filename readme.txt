=== Secure OTP Verification for WooCommerce ===
Contributors: xanteltechnologies
Tags: otp, email verification, woocommerce, login, registration, checkout, security, anti-spam
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.2
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
* Email Verified column in Dashboard → Users
* Bulk mark users as verified/unverified, and bulk "forget" a user's trusted login devices
* Rate limiting (max 3 OTPs per 10 minutes per email)
* Automatic OTP expiry (2 minutes)
* Hourly cleanup of expired OTPs
* Works with any SMTP plugin (Easy WP SMTP, WP Mail SMTP, etc.)
* Branded email template

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

= Does this plugin send any data to third-party servers? =

No. The free version only uses your site's own WordPress database and your site's own `wp_mail()` function to send OTP emails. No external API calls, no third-party services, no data leaves your server.

= Does this work with Elementor, Divi, or other page builders? =

Yes, when the builder is used to style or lay out a page around WooCommerce's own registration, login, or checkout form (its own shortcodes/blocks) — this is the vast majority of page-builder usage, and the plugin's field detection adapts to custom markup automatically. It does not currently integrate with separate custom-form tools that replace WooCommerce's own forms entirely — for example Elementor Pro's own Login widget, Gravity Forms' or WPForms' user-registration add-ons, or membership plugins like Ultimate Member — since these process registration/login through their own systems rather than WooCommerce's. Support for specific tools may be added in future based on demand.

= Is a Pro version available? =

A Pro version with WhatsApp/SMS OTP, magic-link login, and an analytics dashboard is in development. The current version is fully functional on its own for email-based OTP verification.

== Screenshots ==

1. Email OTP field on the WooCommerce registration form
2. OTP login tab alongside password login
3. Checkout email verification step
4. Admin settings and OTP activity dashboard
5. Email Verified column on the Users screen

== Changelog ==

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
