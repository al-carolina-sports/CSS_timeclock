# CSS Time Clock Addon

This GitHub repository **is** the WordPress plugin. `css-timeclock-addon.php` is at the repo root (`https://github.com/al-carolina-sports/CSS_timeclock`). Default branch: `main`.

WP Engine upload zip (plugin wrapped in a `css-timeclock-addon/` folder):

- In-repo: [`dist/css-timeclock-addon.zip`](dist/css-timeclock-addon.zip)
- Rebuild: `./bin/make-zip.sh`

Phase 1 add-on for **All in One Time Clock Lite** (Codebangers, slug `aio-time-clock-lite`). It adds two shared-tablet kiosks so employees can clock in and out **without a WordPress login**.

This plugin does **not** fork or edit AIO Lite. It writes the same `shift` posts and meta AIO already uses, so **Time Clock Lite → Real Time Monitoring** still shows who is working.

| Requirement | Status |
| --- | --- |
| PHP | 7.4+ (WP Engine sandbox is 8.2) |
| WordPress | 5.0+ |
| License | GPLv2 or later |
| AIO Lite | Soft dependency (admin notice if missing) |

## What Phase 1 includes

1. **PIN kiosk** — `[css_tc_pin_kiosk]` — large PIN pad, then Clock in / Clock out.
2. **Name-list kiosk** — `[css_tc_name_kiosk]` — alphabetical employees, tap a name, **confirm with PIN**, then Clock in / Clock out.
3. Admin **Kiosk & PINs** screen (under Time Clock Lite when AIO is active, otherwise Settings).
4. Per-employee PINs stored with `wp_hash_password()` / checked with `wp_check_password()`. Never plaintext.
5. Failed-PIN rate limit by tablet IP.
6. After a punch, a success message, then the kiosk returns to idle. No employee WordPress session is created.

Phase 2+ (not in this build): multi-facility, locations, IP allowlist, bulletin board, self-corrections, Pro features.

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
| `department` | AIO `department` user taxonomy, when present |
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

On activation the plugin creates two pages if they do not already exist:

- `/pin-time-clock/` → `[css_tc_pin_kiosk]`
- `/name-time-clock/` → `[css_tc_name_kiosk]`

You can recreate them from **Time Clock Lite → Kiosk & PINs → Create or restore kiosk pages**.

## Set employee PINs

1. Create WordPress users with AIO roles (`employee`, `volunteer`, `manager`, `contractor`, or the `aio_tc_*` / `time_clock_admin` aliases).
2. Open **Time Clock Lite → Kiosk & PINs → Employee PINs** (administrators can also use **Settings → Time Clock Kiosk**).
3. Enter a 4–8 digit PIN (unique per employee) and **Save PIN**.
4. The digits are hashed immediately. They cannot be viewed later — only replaced or cleared.

Employees without a PIN do not appear on the name-list kiosk. The PIN kiosk only resolves people who have a hashed PIN.

## Use the kiosks

1. Open the PIN or name page on a shared tablet (Safari / Chrome). Bookmark it; the tablet can stay logged out of WordPress.
2. Enable the matching kiosk on the settings tab if a page says it is turned off.
3. PIN kiosk: enter PIN → **Continue** → Clock in or Clock out.
4. Name kiosk: tap a name → enter that person’s PIN → Clock in or Clock out.
5. Wait for the success screen. The kiosk resets by itself (default 8 seconds).

## Verify against AIO monitoring

1. Clock an employee **in** on a kiosk.
2. In wp-admin open **Time Clock Lite → Real Time Monitoring**.
3. That employee should appear under **Employees Currently Working** with a clock-in time.
4. Clock the same employee **out** on the kiosk.
5. Refresh monitoring — they should leave the working list. The closed shift remains under **Shifts** / reports.

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

- Public AJAX uses a nonce (`css_tc_kiosk`). Admin PIN screens require `edit_posts` when AIO is present (`manage_options` otherwise) plus an admin nonce.
- Failed PINs are counted per client IP (transient). After the configured limit the IP is locked for the window (default 5 failures / 15 minutes).
- PIN lookup errors are generic (“That PIN was not recognized”).
- All kiosk output is escaped; all input is sanitized. PINs are digits-only before hashing.
- The kiosk never calls `wp_set_auth_cookie` / `wp_signon`.

## Folder structure

```
css-timeclock-addon.php    Plugin bootstrap
includes/                  Employees, PINs, punches, AJAX, admin, shortcodes
admin/                     Settings UI
public/                    Kiosk markup, CSS, JS
uninstall.php              Removes settings and hashed PINs only
```

## Local zip

```bash
./bin/make-zip.sh
```

Requires `zip`. The archive excludes `.git`, `dist`, `preview`, and this documentation’s tooling files.
