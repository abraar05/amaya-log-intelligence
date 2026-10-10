# Amaya Log Intelligence — InfinityFree Edition

A polished, responsive operational reporting portal starter for consolidating multi-department Google Sheets and Excel/CSV data.

## Important fidelity note
The supplied public URL (`https://amayadash-ffk7jl4y.manus.space/`) exposes the page title **Amaya Log Intelligence**, but its page content is not available to this build environment. This package recreates the likely operational reporting purpose using the requirements already described in the conversation; it is not a pixel-perfect copy of inaccessible source pages.

## Features
- Executive overview with KPI cards, trend chart, department health and activity feed.
- Department/source registry and per-source configuration.
- CSV import (Excel can be exported as CSV first) with header mapping and row preview.
- Google Sheets CSV URL registry for published/exportable sheets.
- Reconciliation workspace with IMEI/serial matching status.
- Data-quality checks: missing identifiers, duplicate identifiers, unmapped records.
- Reports, filters, and CSV export.
- Responsive sidebar, search, date range selector, theme switch.
- PHP + MySQL backend intended for standard shared hosting.
- Demo mode works without a database, so the UI can be previewed immediately.

## Requirements
- PHP 8.1+ recommended (PDO MySQL, fileinfo, mbstring).
- MySQL/MariaDB database.
- Hosting that supports PHP and MySQL. InfinityFree plans/features can change; verify your current plan supports the required PHP/MySQL setup and outbound access before relying on remote Google Sheets synchronization.

## Deploy
1. Create a MySQL database and user in your hosting control panel.
2. Upload the contents of `public/` to your website's document root.
3. Copy `config.example.php` to `config.php` and fill in the database values.
4. Visit `/install.php` once to create tables. Remove `install.php` after successful setup.
5. Sign in with the initial account only if installation is configured to create it; otherwise create the first user directly using the documented SQL or ask your developer to add the setup flow.
6. Keep `config.php` private and do not upload it to public repositories.

## Data and Google Sheets
- For reliable imports, publish a sheet as CSV or use a Google Sheets CSV export URL, then register it under Sources.
- Some shared hosts restrict outbound requests. If the host cannot fetch remote URLs, download the CSV and import it manually.
- Never expose private Google Sheets credentials in browser JavaScript. This starter deliberately does not embed Google API secrets.
- Excel `.xlsx` import is not native in this lightweight PHP package. Export to CSV in Excel/Google Sheets and import the CSV.
- IMEI values should be treated as text to preserve leading zeroes and avoid numeric formatting.

## Production hardening checklist
- Add password reset and email verification.
- Enforce HTTPS.
- Configure scheduled database backups and test restores.
- Add role-based permissions for admin, department manager, analyst, and viewer.
- Add CSRF protection, rate limiting, session timeout, and audit retention according to your organization's policy.
- Review privacy, access controls, and data retention before importing employee/customer data.
