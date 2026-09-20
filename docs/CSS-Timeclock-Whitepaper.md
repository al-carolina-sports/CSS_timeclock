# Carolina Sports and Spine Timeclock  
## Technical Whitepaper for Developers

**Document version:** 1.0  
**Date:** September 19, 2026  
**Audience:** Internal developers and technical collaborators  
**Status:** Phase 1 live on WP Engine sandbox; Phases 2–5 planned  

---

## 1. Executive summary

Carolina Sports and Spine (CSS), BioFunctional, and related practices need an employee punch clock that:

1. Makes it easy for staff to clock in/out at a shared desk tablet (so people stop forgetting).
2. Lets supervisors see **who is currently clocked in** in real time.
3. Runs on the practice’s existing **WP Engine** WordPress hosting (not a separate VPS or SaaS).
4. Can be customized in Cursor and shared via a private GitHub repo under `al-carolina-sports`.

**Current production ADP timeclock** is too primitive for those needs. **Phase 1** of a custom WordPress add-on is live on the WP Engine sandbox `carolinaspodev` and has been punch-tested end-to-end.

**Approach:** Keep [All in One Time Clock Lite](https://wordpress.org/plugins/aio-time-clock-lite/) (GPL, Codebangers) as the system of record for shifts and Real Time Monitoring. Ship a separate plugin, **CSS Time Clock Addon** (`css-timeclock-addon` v1.0.0), that adds shared-tablet kiosks without forking or editing AIO Lite’s files.

---

## 2. Problem statement

| Pain | Detail |
| --- | --- |
| Forgotten punches | Staff routinely forget to clock in; ADP does little to prevent that at the door. |
| No live visibility | Supervisors cannot reliably see who is currently working. |
| Hosting constraint | The business already runs WordPress sites on WP Engine; a standalone PHP/Node app is a poor operational fit. |
| Prior tool | A classic PHP Timeclock was previously hosted on Bluehost; that stack is abandoned, has known SQLi/XSS issues, and is unsupported on WP Engine. |

Practices in scope for the product vision: **Carolina Sports and Spine**, **BioFunctional**, **TrueRadianceMedispa**, plus an **Other** facility bucket; physical locations **Rocky Mount**, **Wilson**, and **Raleigh** (tied to facility in Phase 2).

---

## 3. Goals and non-goals

### Goals
- Shared **PIN kiosk** and **name-list / quick-pick kiosk** (no full WordPress login per punch).
- Live **who’s working** for supervisors (via AIO Real Time Monitoring today; dedicated boards in Phase 3).
- Multi-facility / multi-location punches (Phase 2).
- Bulletin / announcements + daily working board (Phase 3).
- Employee self-correction of times with **supervisor approval** and audit trail (Phase 4).
- Office **IP allowlist** so punches only succeed from clinic networks (Phase 5).
- Source of truth in a **private GitHub** repo for collaborators; deploy as a WordPress plugin to WP Engine.

### Non-goals (v1 / near term)
- Facial recognition, RFID/NFC hardware, native offline mobile apps.
- Full HRIS / PTO accruals / payroll engine.
- Replacing ADP payroll export workflows in Phase 1.
- Installing on live marketing sites (`carolinasportsandspine.com`, `biofunctionalmed.com`, `trueradiancemedispa.com`, etc.).

---

## 4. Alternatives considered

| Option | Verdict | Why |
| --- | --- | --- |
| Classic PHP Timeclock (SourceForge / forks) | Rejected for WP Engine | Abandoned lineage, known SQLi/XSS, unsupported as a non-WP app on WP Engine. |
| Kimai | Rejected for this host | Strong OSS timesheet product; Composer/console; live who’s-in / kiosk not free; project-time mental model. |
| IceHrm / OrangeHRM | Rejected | Full HR suites; IceHrm had unpatched SQLi (CVE-2026-15478 as of research window). |
| OpenTimeClock (opentimeclock.com) | Rejected as platform | Feature-rich **SaaS**, not self-hostable open source; “Open” is branding. |
| Time Clock (Scott Paterson) WP plugin | Not used as SoR | GPLv2+, small codebase, but plaintext employee passwords in free, no locations, almost no punch hooks; “currently working” UI is Pro-only. |
| AIO Time Clock Lite + custom add-on | **Selected** | GPL free plugin already has Real Time Monitoring; WP Engine–friendly; custom add-on can add kiosks without forking vendor files. |
| Custom plugin from scratch only | Deferred | More build time; AIO already stores shifts and monitoring. |

**Licensing note:** AIO Time Clock Lite and Time Clock free are both **GPLv2 or later** and unencoded (no ionCube) in the wordpress.org distributions inspected. Pro editions are separate paid products and must not be assumed forkable.

---

## 5. Architecture

```
┌─────────────────────────────────────────────────────────────┐
│ WP Engine environment: carolinaspodev (PHP 8.2, sandbox)    │
│                                                             │
│  ┌──────────────────────┐    ┌────────────────────────────┐ │
│  │ AIO Time Clock Lite  │◄───│ CSS Time Clock Addon       │ │
│  │ (system of record)   │    │ (kiosk + PIN layer)        │ │
│  │                      │    │                            │ │
│  │ • shift CPT          │    │ • PIN / name kiosks        │ │
│  │ • Real Time Monitor  │    │ • hashed employee PINs     │ │
│  │ • employees / roles  │    │ • writes same shift meta   │ │
│  │ • reports            │    │ • no WP login for punch    │ │
│  └──────────────────────┘    └────────────────────────────┘ │
│                                                             │
│  Pages: /time-clock/  /pin-time-clock/  /name-time-clock/   │
└─────────────────────────────────────────────────────────────┘
```

### Design principles
1. **Do not edit AIO Lite files** — WordPress.org updates must not wipe customizations.
2. **Compatible punch writes** — Kiosk punches create/update the same `shift` posts AIO’s Real Time Monitoring already reads.
3. **WP Engine–safe** — No `exec`/`shell_exec`, no ionCube, no PHP in `uploads`, request work under the 60s process killer, prefer Alternate Cron for future scheduled jobs.
4. **Staff stay out of wp-admin** for punching; supervisors use monitoring / future boards.

### Why kiosks write shifts directly
AIO Lite’s front-end AJAX is registered for logged-in users and uses `get_current_user_id()`. A shared tablet must stay logged out as an employee. The add-on therefore writes AIO’s data model:

| Field | Value |
| --- | --- |
| Post type | `shift` |
| `post_title` | `Employee Shift` |
| `post_status` | `publish` |
| `post_author` | Employee WP user ID |
| `employee_clock_in_time` | `Y-m-d H:i:s` site timezone |
| `employee_clock_out_time` | Empty while working; set on clock-out |
| `department` | From AIO department taxonomy when present |
| `ip_address_in` / `ip_address_out` | Tablet IP |

An employee is **Working** when a shift has clock-in set and clock-out empty.

---

## 6. Current deployment (Phase 1)

### Environment
| Item | Value |
| --- | --- |
| WP Engine env | `carolinaspodev` only |
| URL | https://carolinaspodev.wpenginepowered.com/ |
| PHP | 8.2 |
| Site title (sandbox) | Carolina Sports and Spine Dev Timeclock |
| Live sites | **Not touched** (CSS, BioFunctional, True Radiance, etc.) |

### Installed software
| Component | Version / notes |
| --- | --- |
| All in One Time Clock Lite | 2.1.0 — active |
| CSS Time Clock Addon | 1.0.0 — active |
| License | GPLv2 or later |

### Public / admin URLs
| Purpose | URL |
| --- | --- |
| PIN kiosk | https://carolinaspodev.wpenginepowered.com/pin-time-clock/ |
| Name-list kiosk | https://carolinaspodev.wpenginepowered.com/name-time-clock/ |
| Logged-in AIO clock | https://carolinaspodev.wpenginepowered.com/time-clock/ |
| Real Time Monitoring | `/wp-admin/admin.php?page=aio-monitoring-sub` |
| Kiosk & PINs admin | Time Clock Lite → **Kiosk & PINs** |

### Shortcodes
- `[css_tc_pin_kiosk]` — large PIN pad → Clock in / Clock out  
- `[css_tc_name_kiosk]` — alphabetical name list → PIN confirm → Clock in / Clock out  

### Phase 1 security behaviors
- PINs stored with `wp_hash_password()`; verified with `wp_check_password()`; never stored or displayed in plaintext.
- Failed PIN attempts rate-limited by tablet IP (default 5 tries / 15 minutes).
- After punch: success UI, then idle kiosk — **no employee WordPress session**.
- Name-list requires PIN confirmation in v1.
- Employees without a PIN do not appear on the name list.

### Verification performed
1. PIN kiosk: Staff One clock in → Monitoring **Working** → clock out → Total 0.  
2. Name kiosk: Staff Two + PIN clock in → Monitoring **Working** → clock out → Total 0.  
No UI/JS errors observed during those tests.

### Plugin layout (developers)
```
css-timeclock-addon/
  css-timeclock-addon.php      # bootstrap
  uninstall.php
  includes/
    class-plugin.php
    class-admin.php
    class-ajax.php
    class-employees.php
    class-pins.php
    class-punches.php
    class-shortcodes.php
  admin/                       # settings UI + assets
  public/                      # kiosk views, CSS, JS
  bin/make-zip.sh              # WP Engine upload zip
  readme.txt / README.md
```

---

## 7. AIO Lite roles (context)

AIO Lite registers roles used for clockable staff and admins, including approximately:

- `employee`, `volunteer`, `manager`, `contractor` (and `aio_tc_*` aliases)
- `time_clock_admin` for timeclock administration

Employees are WordPress users with those roles. The add-on’s name list is driven from the same role set. Supervisors use WordPress admin (or `time_clock_admin`) for Real Time Monitoring today.

---

## 8. WP Engine constraints (must-know)

| Constraint | Implication |
| --- | --- |
| WordPress-shaped hosting | No Docker / Node / Laravel app on the WP plan; timeclock must be a plugin. |
| 60s max execution | No long-running punch jobs. |
| Disabled `exec` family | No shelling out. |
| No ionCube | Encoded Pro plugins will fail — confirm before buying encoded software. |
| No true server cron | Use Alternate Cron (hits `wp-cron.php`) for future missed-punch emails. |
| GitPush / GitHub Action | Preferred deploy path for plugin subdirectory under `wp-content/plugins/`. |
| Subdomain vs subdirectory | Prefer a dedicated WP environment / subdomain for a staff clock; subdirectory multi-site patterns are discouraged. |

Standalone PHP Timeclock beside WordPress on WP Engine is **unsupported** and should not be attempted.

---

## 9. Source control and collaboration

| Item | Status |
| --- | --- |
| Intended private repo | https://github.com/al-carolina-sports/CSS_timeclock |
| Account | `al-carolina-sports` |
| Intended layout | Repo **is** the plugin (or `css-timeclock-addon/` folder + `dist/*.zip`) |
| Current gap | GitHub PAT used by automation lacked **Contents: Write** at last push attempt; sandbox install used a local zip. Collaborators should treat the repo as the long-term home once write access is fixed. |

**Recommended collaborator workflow (target state):**
1. Develop on a branch in `CSS_timeclock`.
2. Build zip with `./bin/make-zip.sh`.
3. Deploy to `carolinaspodev` only (upload or GitHub Action → WP Engine).
4. Never deploy experimental builds to live marketing environments without an explicit go-ahead.

---

## 10. Product roadmap

| Phase | Scope | Status |
| --- | --- | --- |
| **1** | PIN kiosk + name-list/quick-pick; hashed PINs; AIO-compatible punches | **Done** on `carolinaspodev` |
| **2** | Multi-facility (CSS, BioFunctional, TrueRadianceMedispa, Other) + multi-location (Rocky Mount, Wilson, Raleigh) tied to facility; punch stores pair | Planned |
| **3** | Bulletin / announcement board + daily who’s-working board (staff-facing, not full wp-admin) | Planned |
| **4** | Employee dashboard; self-correction requests; supervisor approve/deny; audit of original vs corrected | Planned |
| **5** | Office IP / CIDR allowlist per location (or global); clear reject message off-network | Planned |

Suggested build order after Phase 1: **2 → 5 → 3 → 4** (or 3 before 5 if boards are needed sooner). Missed-punch email alerts are a natural add-on after facilities/locations exist.

Optional commercial shortcut: AIO **Pro** (~$40/year) documents PIN/QR/locations/CSV — only if the Pro package is **not** ionCube-encoded on WP Engine. Prefer custom add-on control for clinic-specific facility/location rules and approval workflows.

---

## 11. Data model (Phase 2 sketch)

- **Facilities:** Carolina Sports and Spine · BioFunctional · TrueRadianceMedispa · Other  
- **Locations:** Rocky Mount · Wilson · Raleigh  
- **Constraint:** Only valid facility↔location pairs (configured in admin).  
- **Punch:** Every kiosk punch stores facility + location (+ optional kiosk/device id).  
- Tablets may be locked to a default pair so staff do not pick the wrong site.

---

## 12. Security and compliance notes

- Employee attendance punches are generally **not PHI**; still keep the clock off public marketing pages and use a sandbox / dedicated staff environment.
- Prefer hashed PINs (done), HTTPS (WP Engine), and future IP allowlists (Phase 5).
- Do not log raw PINs.
- Supervisor corrections (Phase 4) must retain an audit trail of who changed what and when.
- Review AIO Lite and the add-on after each WordPress core/plugin update on the sandbox before promoting.

---

## 13. How to extend (developer quickstart)

1. Confirm AIO Lite + CSS Time Clock Addon are active on `carolinaspodev`.  
2. Clone / open `CSS_timeclock` (once populated) or work from the plugin folder.  
3. Keep new features in the **add-on** (or a second add-on), not in patched AIO files.  
4. For punch-related features, read `includes/class-punches.php` and AIO’s `shift` meta conventions.  
5. For kiosk UI, start from `public/views/*`, `public/js/kiosk.js`, `public/css/kiosk.css`.  
6. Test against **Real Time Monitoring** after every punch-path change.  
7. Package with `./bin/make-zip.sh`; install only on the sandbox until sign-off.

---

## 14. Open items / risks

| Item | Risk / action |
| --- | --- |
| GitHub write access | Fix PAT `repo` / Contents: Write so source lives in `CSS_timeclock`. |
| Theme chrome on kiosks | Default theme sidebar still visible; Phase 3+ may add a kiosk template that hides chrome. |
| AIO upgrade compatibility | Soft dependency; re-verify shift meta after AIO updates. |
| Promotion to live | Requires separate decision; never auto-deploy to marketing prod. |
| Facility/location matrix | Product owner must confirm which locations apply to which facilities before Phase 2 coding freezes. |

---

## 15. Document history

| Version | Date | Notes |
| --- | --- | --- |
| 1.0 | 2026-09-19 | Initial whitepaper for developer sharing; reflects Phase 1 live on carolinaspodev. |

---

*Prepared for internal technical collaboration. Sandbox credentials and test PINs are intentionally omitted from this document; request them through the project owner if needed for QA.*
