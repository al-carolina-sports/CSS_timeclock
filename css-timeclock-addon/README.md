# CSS Time Clock Addon

This GitHub repository **is** the WordPress plugin. `css-timeclock-addon.php` is at the repo root (`https://github.com/al-carolina-sports/CSS_timeclock`). Default branch: `main`.

WP Engine upload zip (plugin wrapped in a `css-timeclock-addon/` folder):

- In-repo: [`dist/css-timeclock-addon.zip`](dist/css-timeclock-addon.zip)
- Rebuild: `./bin/make-zip.sh`

Add-on for **All in One Time Clock Lite** (Codebangers, slug `aio-time-clock-lite`). It adds shared-tablet kiosks so employees can clock in and out **without a WordPress login**, plus a logged-in employee times page with supervisor-approved corrections.

This plugin does **not** fork or edit AIO Lite. It writes the same `shift` posts and meta AIO already uses, so **Time Clock Lite → Real Time Monitoring** still shows who is working.

| Requirement | Status |
| --- | --- |
| PHP | 7.4+ (WP Engine sandbox is 8.2) |
| WordPress | 5.0+ |
| License | GPLv2 or later |
| AIO Lite | Soft dependency (admin notice if missing) |

## What this plugin includes

1. **PIN kiosk** — `[css_tc_pin_kiosk]` — large PIN pad, then facility, then location, then Clock in / Clock out.
2. **Name-list kiosk** — `[css_tc_name_kiosk]` — alphabetical employees, tap a name, **confirm with PIN**, then facility, then location, then Clock in / Clock out.
3. **Who’s working board** — on both kiosk pages (logged-out visitors). Side panel on wide screens; stacks under the pad on tablet widths. Lists **Working now** (with clock-in time) and **Not clocked in**. Refreshes every 20 seconds and immediately after a successful punch.
4. **My Time Clock** — `[css_tc_my_times]` — logged-in employees (AIO employee roles) see their recent punches by day and can **suggest an edit**. Suggestions stay pending until a supervisor reviews them.
5. Admin **Kiosk & PINs** screen (under Time Clock Lite when AIO is active, otherwise Settings), including a **Corrections** queue. Approve writes the AIO-compatible shift and keeps an audit (original times, who suggested, who approved). Reject leaves punches unchanged.
6. Per-employee PINs stored with `wp_hash_password()` / checked with `wp_check_password()`. Never plaintext.
7. Failed-PIN rate limit by tablet IP.
8. After a punch, a success message, then the kiosk returns to idle. No employee WordPress session is created.

Facility and location (1.3.0) are chosen on every punch. Later: IP allowlist, bulletin / announcements, Pro features.

## How punches reach AIO Lite

AIO Lite’s front-end AJAX (`aio_time_clock_lite_js`) is registered as `wp_ajax_` only and calls `get_current_user_id()`. A logged-out kiosk cannot use that action without impersonating a user.

This add-on therefore writes AIO’s data model directly (same path Real Time Monitoring already reads):

| Field | Value |
| --- | --- |
| Post type | `shift` |
| `post_title` | `Employee Shift` |
| `post_status` | `publish` |
| `post_author` | the employee’s WordPress user ID |
| `employee_clock_in_time` | `Y-m-d H:i:s` in the site timezone (`wp_date`, same as AIO 2.1) |
| `employee_clock_out_time` | empty while working; set on clock-out |
| `department` | Facility label chosen on the punch (AIO reads this meta). If the facility prompt is turned off, the employee’s AIO department taxonomy name, when present |
| `css_tc_facility` | Facility label (`Carolina Sports and Spine`, `BioFunctionalMed`, `TrueRadiance Medispa`, `Other`, or an edited list) |
| `css_tc_location` | Location label (`Rocky Mount`, `Wilson`, `Raleigh`, or an edited list) |
| `ip_address_in` / `ip_address_out` | tablet IP |

An employee is **Working** in AIO monitoring when a shift has a clock-in time and an empty clock-out time.

## Install on WP Engine (carolinaspodev)

1. Zip the plugin folder so the archive contains `css-timeclock-addon/css-timeclock-addon.php` (not a pile of loose files). From this repo:

   ```bash
   ./bin/make-zip.sh
   ```

   That writes `dist/css-timeclock-addon.zip`.

2. WP Engine → the `carolinaspodev` environment → **WordPress Admin** → **Plugins → Add New → Upload Plugin**.
3. Upload the zip, then **Activate**.
4. Confirm **All in One Time Clock Lite** is also installed and active.
5. WP Engine rules this plugin follows: no `exec` / shell, no ionCube, no code written to `uploads`, requests stay well under 60 seconds.

On activation the plugin creates pages if they do not already exist:

- `/pin-time-clock/` → `[css_tc_pin_kiosk]`
- `/name-time-clock/` → `[css_tc_name_kiosk]`
- `/my-time-clock/` → `[css_tc_my_times]`

You can recreate them from **Time Clock Lite → Kiosk & PINs → Create or restore kiosk and times pages**.

## Set employee PINs

1. Create WordPress users with AIO roles (`employee`, `volunteer`, `manager`, `contractor`, or the `aio_tc_*` / `time_clock_admin` aliases).
2. Open **Time Clock Lite → Kiosk & PINs → Employee PINs** (administrators can also use **Settings → Time Clock Kiosk**).
3. Enter a 4–8 digit PIN (unique per employee) and **Save PIN**. The field is masked. The eye button on the field shows the digits while you type, and click it again to hide them.
4. The digits are hashed immediately with `wp_hash_password()`. A saved PIN can only be replaced or cleared. The eye never reads a stored PIN back.

Employees without a PIN do not appear on the name-list kiosk. The PIN kiosk only resolves people who have a hashed PIN.

## Use the kiosks

1. Open the PIN or name page on a shared tablet (Safari / Chrome). Bookmark it; the tablet can stay logged out of WordPress.
2. Enable the matching kiosk on the settings tab if a page says it is turned off.
3. PIN kiosk: enter PIN → **Continue** → facility → location → Clock in or Clock out.
4. Name kiosk: tap a name → enter that person’s PIN → facility → location → Clock in or Clock out.
5. Facility and location use large tap targets. The employee’s last choice is highlighted; **Continue** keeps it, or tap a different one. **Back** on the location screen returns to facilities. **Cancel** (and Escape) leaves the punch and returns to idle. Escape on the PIN screen still only clears digits.
6. A USB keyboard or numeric keypad works on the PIN screen (both kiosks). Digit keys and numpad 0–9 append, Backspace or Delete removes the last digit, and Enter submits (same as **Continue**). Length still follows the configured minimum and maximum. Escape on the PIN screen clears the digits and stays on that screen (same as **Clear**). On the name kiosk, the on-screen **Cancel** button is what returns to the name list. On the facility, location, and Clock in / Clock out screens, Escape cancels back to idle. On the location screen, the on-screen **Back** button returns to facilities (Escape does not). On the success screen (which has no Cancel button), Escape returns to the idle screen immediately. Keys are ignored while the cursor is in the name search field, while a request is in progress, while the kiosk is turned off, and off the PIN screen (Escape on the facility, location, action, and success screens still works). On-screen pad buttons are unchanged.
7. The **Who’s working** board on the same page shows who is in or out, with facility and location under each working name. It updates after a punch without reloading the page.
8. Wait for the success screen. The kiosk resets by itself (default 8 seconds).

## Facility and location

Employees float between businesses. After the PIN resolves, every punch asks:

1. **Facility** — Carolina Sports and Spine, BioFunctionalMed, TrueRadiance Medispa, Other
2. **Location** — Rocky Mount, Wilson, Raleigh
3. **Clock in / Clock out** — the chosen pair is shown on that screen

Turn the prompt off, or edit the lists, under **Time Clock Lite → Kiosk & PINs → Kiosk settings**. One name per line. A blank box restores the defaults above. `css_tc_facilities` and `css_tc_locations` can still replace the lists in code. `css_tc_aio_department` can change the string written to AIO’s `department` meta (default: the facility label).

The last pair is stored on the user (`css_tc_last_facility`, `css_tc_last_location`) and pre-selected next time. Clocking out can change the pair; that updates the open shift. Shifts opened before 1.3.0 have no place until the next punch.

My Time Clock shows the pair on each punch when it is present.

## Employee times and suggested edits

1. Employees sign in to WordPress (their existing AIO employee user) and open **My Time Clock** (`/my-time-clock/`). This is a front-end page, not wp-admin.
2. They see recent days (default 21), each day’s punches, and **Suggest edit**.
3. A suggestion needs a proposed clock-in and/or clock-out, a required reason, and can mark a missing punch. Status is **pending** until reviewed. Employees only see their own times.
4. A site admin, `time_clock_admin`, or anyone who can manage the kiosk opens **Time Clock Lite → Kiosk & PINs → Corrections**.
5. **Approve** writes the corrected `employee_clock_in_time` / `employee_clock_out_time` on the AIO `shift` (or creates a shift for a missing punch). The suggestion keeps original times, the employee, the reviewer, and timestamps. **Reject** leaves punches unchanged and stores an optional note.

The kiosk who’s-working board still reads the same open-shift rule after an approved correction.

## Verify against AIO monitoring

1. Clock an employee **in** on a kiosk (PIN → facility → location → Clock in).
2. In wp-admin open **Time Clock Lite → Real Time Monitoring**.
3. That employee should appear under **Employees Currently Working** with a clock-in time. The department column should show the facility label.
4. Edit the newest **Shift**. Confirm `employee_clock_in_time`, empty `employee_clock_out_time`, `department` (facility label), `css_tc_facility`, and `css_tc_location`.
5. The kiosk **Who’s working** board should list the same facility and location under that name.
6. Clock the same employee **out** (the last facility and location are highlighted; change them if you want that shift updated).
7. Refresh monitoring — they should leave the working list. The closed shift remains under **Shifts** / reports.

If monitoring is empty after a punch, confirm AIO Lite is active and the shift post type is registered, then edit the newest **Shift** and check `employee_clock_in_time` / `employee_clock_out_time`.

## Testing with sandbox employees

On carolinaspodev, existing AIO employees already have WordPress users. You only need to assign PINs:

1. Note two test employees (Employee role or equivalent).
2. Set distinct PINs (example: `2468` and `1357`) on **Employee PINs**.
3. Open the kiosk in a private window (logged out).
4. Clock in with employee A → confirm A is Working in monitoring.
5. Clock in with employee B from the name list → confirm both are Working.
6. Clock A out → only B remains Working.
7. Confirm a wrong PIN is rejected, and that after several failures the tablet is temporarily locked.

Do not commit real PINs. Treat them like passwords.

## Security

- Public AJAX uses a nonce (`css_tc_kiosk`). The employee times page uses a logged-in nonce (`css_tc_employee`); staff can only load or suggest edits for themselves. Admin PIN and correction screens require `manage_options`, `time_clock_admin`, or `edit_posts` when AIO is present, plus an admin nonce.
- The public roster action (`css_tc_roster`) returns display names, in/out status, and clock-in times only — no PINs, emails, user IDs, or admin data. It is rate-limited separately from the PIN lock (40 requests / minute / IP). The board is not transient-cached; a successful punch returns a fresh board payload.
- Failed PINs are counted per client IP (transient). After the configured limit the IP is locked for the window (default 5 failures / 15 minutes).
- PIN lookup errors are generic (“That PIN was not recognized”).
- All kiosk output is escaped; all input is sanitized. PINs are digits-only before hashing.
- The kiosk never calls `wp_set_auth_cookie` / `wp_signon`.

## Folder structure

```
css-timeclock-addon.php    Plugin bootstrap
includes/                  Employees, PINs, punches, corrections, AJAX, admin, shortcodes
admin/                     Settings UI
public/                    Kiosk markup, CSS, JS
uninstall.php              Removes settings, hashed PINs, and correction posts (AIO shifts stay)
```

## Local zip

```bash
./bin/make-zip.sh
```

Requires `zip`. The archive excludes `.git`, `dist`, `preview`, and this documentation’s tooling files.
