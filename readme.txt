=== Secure OTP Verification for WooCommerce ===
Contributors: xanteltechnologies
Tags: otp, email verification, woocommerce, login, registration, checkout, security, anti-spam
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
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

= Do returning customers have to enter an OTP at every checkout? =

By default, logged-in customers skip checkout OTP entirely, since they already proved their account at registration or login. Guest checkouts always require OTP, since there's no account behind them to trust. You can turn this off in Settings → Secure OTP if you'd rather require a fresh OTP at every checkout regardless of login status.

= Do returning customers have to enter an OTP at every login? =

Not when logging in with a password. After a successful password + OTP login, that device is remembered for a number of days you set in Settings → Secure OTP (default 30), and each use extends that window — a new device, a cleared cookie, or an expired window still requires OTP. This only applies to password-based login: the password-less "Login with OTP" tab always requires a fresh OTP, since without a password there's nothing else confirming who's signing in. You can force re-verification for a specific user via Users → Bulk Actions → "Forget Trusted Login Devices."

= Does this plugin send any data to third-party servers? =

No. The free version only uses your site's own WordPress database and your site's own `wp_mail()` function to send OTP emails. No external API calls, no third-party services, no data leaves your server.

= Is a Pro version available? =

A Pro version with WhatsApp/SMS OTP, magic-link login, and an analytics dashboard is in development. The current version is fully functional on its own for email-based OTP verification.

== Screenshots ==

1. Email OTP field on the WooCommerce registration form
2. OTP login tab alongside password login
3. Checkout email verification step
4. Admin settings and OTP activity dashboard
5. Email Verified column on the Users screen

== Changelog ==

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

= 1.1.0 =
Adds optional checkout OTP skip for logged-in customers and trusted-device login. Review the new settings after updating.

= 1.0.1 =
Important security fix — updates the OTP verification logic for registration, login and checkout. Please update as soon as possible.

= 1.0.0 =
Initial release by Xantel Technologies.
