# Data model and reconciliation guidance

## Recommended common fields
- `department`
- `source_name`
- `record_date` (ISO date preferred)
- `imei` (string, never numeric)
- `serial_number` (string)
- `invoice_number`
- `movement_type` (inbound, outbound, transfer, sale, return, reject)
- `quantity`
- `warehouse`
- `status`
- `notes`

## Reconciliation logic
1. Normalize identifier strings by trimming spaces and removing display-only separators.
2. Do not coerce IMEIs to integers; preserve the exact string.
3. Match exact IMEI against invoice/transfer records.
4. Flag duplicate IMEIs separately from unmatched identifiers.
5. Preserve source row, source timestamp, and import batch for traceability.
6. Require human review for ambiguous matches; do not silently auto-assign invoices.

## KPI definitions
- Reconciliation rate = matched eligible IMEI records / all eligible IMEI records × 100.
- Stock turnover / movement = count of outbound device units over a selected time window; distinguish movement volume from accounting stock-turnover ratio.
- Unmapped IMEI count = physical identifiers without validated master/invoice assignment.
- Source freshness = current time minus last successful refresh.
- Data quality score should disclose its weighting and issue counts, rather than being treated as an audited financial metric.
