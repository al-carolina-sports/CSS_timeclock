=== CSS Time Clock Addon ===
Contributors: css
Tags: time clock, kiosk, pin, employee, aio time clock
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.3.0
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

= 1.3.0 =
* After a PIN is accepted (including name-kiosk PIN confirm), the kiosk asks for a facility, then a location, then Clock in or Clock out. This release also includes the 1.2.2 keyboard / numpad PIN entry and the admin PIN eye.
* Facilities: Carolina Sports and Spine, BioFunctionalMed, TrueRadiance Medispa, Other. Locations: Rocky Mount, Wilson, Raleigh. Any facility can be paired with any location. Lists are editable under Kiosk settings (one name per line) and through the `css_tc_facilities` and `css_tc_locations` filters.
* Each punch stores `css_tc_facility` and `css_tc_location` on the shift. The facility label is also written to AIO's `department` meta so Real Time Monitoring still shows the business. The `css_tc_aio_department` filter can remap that department value.
* The last facility and location are remembered per employee and highlighted on the next punch. They can still be changed every time, including on clock-out (that updates the open shift's pair).
* Who's-working board shows facility and location on a second line under each working name. Open shifts are still found with the 1.2.1 PHP empty clock-out check.

= 1.2.2 =
* PIN kiosk and name-kiosk PIN confirm accept a physical keyboard or USB numpad: digit keys and numpad 0–9 append (still limited to the configured PIN length), Backspace and Delete remove the last digit, and Enter submits the same way as Continue.
* Escape on the PIN screen clears the digits and stays on that screen (same as Clear). On the name kiosk, the on-screen Cancel button still returns to the name list. Escape on the Clock in / Clock out screen cancels, and Escape on the success screen returns to idle immediately.
* Keystrokes are ignored in real text fields (the name search box), while a request is in progress, when the kiosk is turned off, and when the visible screen is not PIN entry (except Escape on the action and success screens).
* Employee PINs: the Set PIN field stays masked, with an eye button to show or hide the digits being typed. Saving still stores only a WordPress password hash.

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
