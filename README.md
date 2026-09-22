# Secure OTP Verification for WooCommerce

**Stop fake accounts, account-sharing logins, and fraudulent orders — verify every customer's email with a one-time code, right inside WooCommerce.**

[![WordPress Plugin Version](https://img.shields.io/badge/version-1.1.0-blue.svg)](https://github.com/ahmedxanteltech/secure-otp-verification-for-woocommerce/releases)
[![License: GPL v2](https://img.shields.io/badge/license-GPLv2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-5.6%2B-blue.svg)](https://wordpress.org)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-required-96588a.svg)](https://woocommerce.com)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://php.net)

Built by [Xantel Technologies](https://xanteltech.com).

---

## Why this plugin

Fake registrations, throwaway emails, and typo'd addresses at checkout quietly cost every WooCommerce store money — bounced order confirmations, chargebacks nobody can trace, spam accounts clogging your user list. This plugin closes that gap by requiring a 6-digit email code at the moment it matters most: registration, login, or checkout — your choice, per-store.

## Screenshots

> _Screenshots coming soon — placeholders below, will be replaced with real captures._

| Registration OTP | Login with OTP | Checkout Verification | Admin Dashboard |
|---|---|---|---|
| ![Registration screenshot placeholder](docs/screenshots/registration.png) | ![Login screenshot placeholder](docs/screenshots/login.png) | ![Checkout screenshot placeholder](docs/screenshots/checkout.png) | ![Admin screenshot placeholder](docs/screenshots/admin.png) |

## Features

- ✅ **Email OTP at Registration** — every new WooCommerce account is email-verified before it's created
- ✅ **Email OTP at Login** — optional OTP-based login, alongside or instead of passwords
- ✅ **Email OTP at Checkout** — verify the billing email before an order is placed for guest checkout; logged-in customers skip it automatically (togglable)
- ✅ **Trusted-device login for password sign-in** — a device that already passed password + OTP isn't asked again for an admin-configurable number of days (the password-less OTP-only tab always requires a fresh code)
- ✅ **Force OTP-only login** — disable password login for all non-admin users store-wide
- ✅ **Per-store configuration** — turn OTP on for registration only, registration + login, or all three
- ✅ **Admin dashboard** — OTPs sent, verified, and blocked, at a glance, with a recent-activity log
- ✅ **Users list integration** — see email-verification status directly in **Users**, with bulk mark verified/unverified and bulk "forget trusted devices"
- ✅ **Rate-limited & brute-force resistant** — capped OTP sends and capped verification attempts, out of the box
- ✅ **No external services required** — uses your site's own `wp_mail()`, no third-party API keys needed for the free tier
- ✅ **Clean uninstall** — removes its own database tables and settings when you remove the plugin

## Installation

### From WordPress.org (recommended)
1. In your WordPress admin, go to **Plugins → Add New**
2. Search for **"Secure OTP Verification for WooCommerce"**
3. Click **Install Now**, then **Activate**
4. Go to **Settings → Secure OTP** to configure

### Manual installation
1. Download the latest release from the [Releases page](https://github.com/ahmedxanteltech/secure-otp-verification-for-woocommerce/releases)
2. In your WordPress admin, go to **Plugins → Add New → Upload Plugin**
3. Upload the `.zip` file and click **Install Now**, then **Activate**
4. Go to **Settings → Secure OTP** to configure

**Requirements:** WordPress 5.6+, WooCommerce (active), PHP 7.4+, and outgoing email (SMTP) configured on your site.

## Configuration

Head to **Settings → Secure OTP** after activation:

- **Enable OTP For** — choose between Registration Only, Registration + Login, or Registration + Login + Checkout
- **Disable Password Login** — force all non-admin users through OTP-only login (admins are always exempt, so you never get locked out)
- **Skip Checkout OTP for Logged-In Customers** — on by default; guest checkouts always require OTP regardless of this setting
- **Remember Login Devices For (days)** — how long a device stays trusted after a successful login OTP before being asked again

The dashboard on the same screen shows total OTPs sent, verified, blocked, and a recent-activity table.

## Frequently asked questions

**Does this work without WooCommerce?**
No — this plugin is built specifically around WooCommerce's registration, login, and checkout hooks.

**Will this slow down checkout?**
The OTP step adds one extra field and one extra email round-trip before an order is placed. There's no impact on page load or server performance otherwise.

**What if a customer doesn't receive the OTP email?**
Make sure your site has a working SMTP setup — `wp_mail()` without SMTP is unreliable on many hosts and is the most common cause of "I didn't get my code."

**Can I disable OTP for logged-in returning customers at checkout?**
Yes, by default. Logged-in customers already proved their account at registration or login, so checkout skips OTP for them automatically — guest checkouts always require it, since there's no account behind them to trust. This is different from "verify once, skip forever regardless of login state": it's tied to an active, authenticated session, not a permanent flag on the email address, so it doesn't create a standing bypass for disposable-email signups. Turn it off in Settings → Secure OTP if you'd rather require a fresh OTP at every checkout.

**Do customers have to enter an OTP every time they log in?**
Not when logging in with a password. After a successful password + OTP login, that device is remembered for an admin-configurable number of days (Settings → Secure OTP, default 30) — each use extends the window; a new device, a cleared cookie, or an expired window still requires OTP. This applies to password-based login only — the password-less "Login with OTP" tab always requires a fresh OTP, since without a password there's nothing else confirming identity. You can force re-verification for a specific customer via Users → Bulk Actions → "Forget Trusted Login Devices."

## Roadmap — Pro version (coming soon)

The free version covers email OTP end-to-end. A Pro tier is in development for stores that need more:

- 📱 WhatsApp / SMS OTP via Twilio
- 🔗 Magic-link login (passwordless, no code to type)
- 📊 Login & verification analytics dashboard
- 🏷️ White-label / custom branding for OTP emails

Watch this repo or [get in touch](mailto:support@xanteltech.com) to be notified when Pro launches.

## Security

Version 1.0.1 included a security hardening pass: OTP verification is checked server-side on every flow (registration, login, checkout), with a brute-force-attempt limit on verification. See [CHANGELOG](#changelog) below. If you discover a security issue, please email **support@xanteltech.com** directly rather than opening a public issue.

## Changelog

**1.1.0**
- Renamed plugin to "Secure OTP Verification for WooCommerce" (previously "Xantel Email OTP")
- Added: skip checkout OTP for logged-in customers (guests always stay verified); togglable in settings
- Added: trusted-device login for password-based sign-in — a device that already passed password + OTP isn't asked again for an admin-configurable number of days. The password-less OTP-only login tab always requires a fresh OTP, since it has no password backstop.
- Added: "Forget Trusted Login Devices" bulk action on the Users screen

**1.0.1**
- Security: server-side OTP verification enforced on registration, login, and checkout (previously relied in part on a client-supplied flag)
- Security: added a 5-attempts-per-10-minutes limit on OTP verification to prevent brute-force guessing
- Security: OTP codes now generated with `wp_rand()` instead of `rand()`
- Added `uninstall.php` for clean removal of plugin tables and settings

**1.0.0**
- Initial release

## Support

- 🐛 Found a bug? [Open an issue](https://github.com/ahmedxanteltech/secure-otp-verification-for-woocommerce/issues)
- 💬 Questions or feature requests: **support@xanteltech.com**
- 🌐 More about Xantel Technologies: [xanteltech.com](https://xanteltech.com)

## License

GPL v2 or later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
