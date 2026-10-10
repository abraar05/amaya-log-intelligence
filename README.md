# Amaya Log Intelligence — Static Demo

A browser-only demonstration of the **Amaya Log Intelligence** operational reporting portal.

> **This is a static demo, not the production system.** It runs entirely in your browser with no
> server, no database, and no authentication. Do not enter real company, customer, or employee data.

## What this demo does

- Register data sources (stored in `localStorage`)
- Import CSV files with header auto-mapping and tolerant field aliases
- Reconcile IMEI/serial identifiers — matched, unmatched, and duplicate detection
- Run data-quality scans (duplicates, missing identifiers, missing invoices, stale sources, incomplete rows)
- Filter, sort, and export tables as CSV
- Light/dark theme, responsive layout

## What it deliberately cannot do

| Capability | Status | Why |
|---|---|---|
| Real authentication / roles | Not included | Requires a server |
| Shared multi-user database | Not included | GitHub Pages runs no PHP/MySQL |
| Remote Google Sheets sync | Not included | Static sites can't make server-side fetches |
| Central audit trail | Not included | Data is per-browser, not centralized |

## Data handling

Everything you import stays in **this browser's** `localStorage` under the key `amaya.li.v1`.
Nothing is transmitted anywhere. Clearing site data — or **Settings → Reset demo data** — erases it permanently.

## Reconciliation rules implemented

1. Identifier strings are trimmed and compared exactly; never coerced to integers (preserves leading zeroes)
2. Duplicates are tracked separately from unmatched identifiers
3. An identifier is `Matched` when it appears exactly once **and** carries an invoice reference
4. A repeated identifier is `Duplicate` regardless of invoice state
5. Quality score = 100 − 6 per distinct issue category, floored at 0

## CSV format

Headers are matched case-insensitively with common aliases. A column named `imei`, `serial`, or
`serial_number` is required; everything else is optional.

```csv
imei,device_model,invoice_number,department,record_date,warehouse
868436084232096,Redmi Note 13,TR-0001,Logistics,2026-10-10,Central
```

## The production version

The full PHP + MySQL build — with server-side CSV ingestion, source CRUD, scheduled refreshes,
reconciliation, quality scans, and real authentication — lives in the `php-mysql/` branch of this
repository and targets standard shared hosting (e.g. InfinityFree).

## Attribution

Built for Amaya Distribution operations. Design and data handling follow the project's documented
internal guidance in `docs/DATA_MODEL.md`.
