# PC Maintenance & Diagnostic System (PCMS)

A web-based system for predicting PC/computer issues and managing preventive
maintenance for a fleet of 15–30 units. Built with HTML, CSS, vanilla JS,
PHP (PDO), and MySQL — designed to run on XAMPP.

## Features
- **Login system** with admin / technician roles
- **Unit inventory** — register each PC with specs, location, status
- **Rule-based diagnostic engine** — pick observed symptoms (slow boot,
  overheating, blue screen, no display, clicking noise, etc.) and the system
  scores and ranks the most likely causes with a recommended action and a
  confidence percentage
- **Preventive maintenance risk score** per unit (0–100), based on how
  overdue it is for scheduled maintenance and how many unresolved issues
  it has open
- **Maintenance logging** — record cleaning/inspection/repair history,
  auto-updates the unit's "last maintenance date" and restores its status
- **Diagnostic log tracking** — open / in progress / resolved workflow
- **Analytics dashboard** — fleet status breakdown, most common predicted
  causes, symptom categories, monthly issue trend, maintenance compliance
  (Chart.js, loaded from CDN)
- **User account management** (admin only)

## Requirements
- XAMPP with PHP **8.0+** (uses `match` expressions) and MySQL/MariaDB
- A modern browser — the whole system, **including the analytics charts**,
  runs 100% offline. Charts are drawn with a small custom Canvas 2D script
  (`assets/js/charts.js`), not an external library, so no internet
  connection or CDN access is required at any point.

## Setup
1. Copy the `pcms` folder into your XAMPP `htdocs` directory, so you have
   `htdocs/pcms/...`.
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`) and import
   `database/schema.sql`. This creates the `pcms_db` database, all tables,
   the symptom checklist, diagnosis rules, and some sample units/logs so
   you can see the system working right away.
4. If your MySQL root user has a password, edit `config/database.php` and
   set `DB_PASS` accordingly (default XAMPP has no password).
5. Visit `http://localhost/pcms/setup_admin.php` **once** — this sets a
   working password for the default `admin` account (this avoids shipping
   a hardcoded password hash that might not match your PHP build).
   Default login becomes: **admin / admin123**. Delete `setup_admin.php`
   afterwards.
6. Go to `http://localhost/pcms/login.php` and log in.

## Adjusting to your fleet size (15–30 units)
Sample data ships with 15 units. Add the rest either through **PC Units →
Add Unit** in the UI, or by inserting more rows into the `units` table
directly (see the `INSERT INTO units ...` block in `schema.sql` for the
format).

## How the prediction engine works
Each symptom in the checklist is linked to one or more **probable causes**
in `diagnosis_rules`, each with a weight and a severity. When you select
symptoms on the **Run Diagnosis** page, the system:
1. Looks up every rule matching the selected symptoms
2. Groups them by probable cause and sums the weights (more matching
   symptoms pointing to the same cause = higher score)
3. Ranks causes by score and shows a confidence percentage relative to the
   top cause
4. Saving the result creates a diagnostic log against that unit and can
   automatically flag the unit as `at_risk` or `critical` if the top
   predicted cause is high/critical severity

You can extend the rule set at any time by adding rows to `symptoms` and
`diagnosis_rules` in phpMyAdmin — no code changes required.

## Troubleshooting blank/empty charts
If a chart card still looks empty:
1. Open the browser console (F12 → Console) and check for red errors.
2. Visit `http://localhost/pcms/api/stats.php?type=status_distribution`
   directly in the browser — you should see raw JSON like
   `[{"status":"healthy","c":9}, ...]`. If you get a PHP error instead,
   your PHP version is likely older than 8.0 (this project uses `match`
   expressions in `includes/functions.php`) — update PHP in XAMPP, or ask
   for a PHP 7.4-compatible version of that file.
3. If the JSON looks fine but the canvas is still blank, hard-refresh the
   page (Ctrl+Shift+R) to make sure the old cached `charts.js` isn't being
   reused.

## Folder structure
```
pcms/
├── api/stats.php              JSON endpoints for the analytics charts
├── assets/css/style.css       All styling (design tokens at the top)
├── assets/js/main.js          Small UI helpers (confirm dialogs, filters)
├── assets/js/charts.js        Chart.js rendering
├── config/database.php        PDO connection settings
├── database/schema.sql        Full schema + seed data
├── includes/                  auth.php, functions.php, header/footer.php
├── index.php                  Dashboard
├── units.php / unit_form.php / unit_view.php / unit_delete.php
├── diagnose.php                Run Diagnosis (the prediction engine UI)
├── logs.php / log_update.php   Diagnostic log history + status updates
├── maintenance.php / maintenance_add.php
├── reports.php                 Analytics page
├── users.php                   Admin: manage accounts
└── setup_admin.php              One-time password bootstrap (delete after use)
```
