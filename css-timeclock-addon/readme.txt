=== CSS Time Clock Addon ===
Contributors: css
Tags: time clock, kiosk, pin, employee, aio time clock
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

PIN pad and name-list kiosk add-on for All in One Time Clock Lite. Clock in without a WordPress login.

== Description ==

Shared tablet kiosks for All in One Time Clock Lite. Employees enter a PIN (or pick their name, then confirm with a PIN) and clock in or out. Punches write the same shift posts AIO Real Time Monitoring already reads.

This plugin does not modify aio-time-clock-lite files.

== Installation ==

1. Upload the `css-timeclock-addon` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Activate All in One Time Clock Lite.
4. Set employee PINs under Time Clock Lite → Kiosk & PINs.

== Changelog ==

= 1.2.1 =
* Who's-working board finds open shifts with the same PHP empty clock-out check as punch status and AIO monitoring (no WP_Query empty-string meta_query).
* Punch AJAX returns a fresh board payload so the kiosk paints Who's working immediately (no second roster request).
* Public roster transient is skipped so WP Engine cannot serve a stale empty board.

= 1.2.0 =
* Employee My Time Clock page: view own punches by day and suggest edits.
* Supervisor Corrections queue: approve (writes AIO shift + audit) or reject.

= 1.1.0 =
* Public live who's-working board on PIN and name-list kiosk pages (AJAX refresh).

= 1.0.0 =
* Phase 1: PIN kiosk, name-list kiosk, hashed PIN management, AIO-compatible punches.
