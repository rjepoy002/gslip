<?php
require_once __DIR__.'/display_format.php';
// Shared Reports Module gas-slip SELECT and CSV presentation. Filtering stays
// with each caller; monitoring uses its existing record-level permission policy.
function gasSlipReportSelectSql(): string
{
    return <<<'SQL'

SELECT
    gs.id,
    d.name AS department,
    a.area_name AS area,
    gs.gas_slip_id,
    gs.date_issued,
    gs.validity_until,
    gs.requested_by,
    gs.purpose,
    gs.status,
    gs.printed_at,

    gs.recommended_at,
    gs.approved_at,
    gs.rejected_at,
    gs.rejection_reason,

    v.plate_no,

    CONCAT(
        COALESCE(rec.first_name,''),
        ' ',
        COALESCE(rec.last_name,'')
    ) AS recommender_name,

    CONCAT(
        COALESCE(app.first_name,''),
        ' ',
        COALESCE(app.last_name,'')
    ) AS approver_name,

    CONCAT(
        COALESCE(rej.first_name,''),
        ' ',
        COALESCE(rej.last_name,'')
    ) AS rejector_name,

    CONCAT(
        MIN(r.origin),
        ' -> ',
        GROUP_CONCAT(
            r.destination
            ORDER BY gsr.id
            SEPARATOR ' -> '
        )
    ) AS route_path,

    fuel.fuel,
    fuel.liters,
    fuel.fuel_items,
    fuel.fuel_quantities

FROM gas_slips gs

LEFT JOIN vehicles v
    ON v.id = gs.vehicle_id

LEFT JOIN gas_slip_routes gsr
    ON gsr.gas_slip_id = gs.id

LEFT JOIN routes r
    ON r.id = gsr.route_id

LEFT JOIN (
    SELECT
        fr.gas_slip_id,

        GROUP_CONCAT(
            CASE
                WHEN fi.name IN ('Unleaded','Diesel')
                THEN fi.name
            END
            ORDER BY fi.name
            SEPARATOR ', '
        ) AS fuel,

        GROUP_CONCAT(
            CASE
                WHEN fi.name IN ('Unleaded','Diesel')
                THEN fr.quantity
            END
            ORDER BY fi.name
            SEPARATOR ', '
        ) AS liters,

        GROUP_CONCAT(
            CASE
                WHEN fi.name NOT IN ('Unleaded','Diesel')
                THEN fi.name
            END
            ORDER BY fi.name
            SEPARATOR ', '
        ) AS fuel_items,

        GROUP_CONCAT(
            CASE
                WHEN fi.name NOT IN ('Unleaded','Diesel')
                THEN fr.quantity
            END
            ORDER BY fi.name
            SEPARATOR ', '
        ) AS fuel_quantities

    FROM fuel_requests fr
    JOIN fuel_items fi
        ON fi.id = fr.fuel_item_id

    GROUP BY fr.gas_slip_id
) fuel

ON fuel.gas_slip_id = gs.id

LEFT JOIN users u
    ON u.id = gs.user_id

LEFT JOIN departments d ON d.id = u.department_id
LEFT JOIN areas a ON a.id = gs.area_id

LEFT JOIN users rec
    ON rec.id = gs.recommended_by

LEFT JOIN users app
    ON app.id = gs.approved_by

LEFT JOIN users rej
    ON rej.id = gs.rejected_by

SQL;
}

function gasSlipReportHeaders(bool $monitoring = false): array
{
    if ($monitoring) return array_merge([
        'Gas Slip No.', 'Date Issued', 'Department', 'Area', 'Requested By', 'Purpose', 'Routes', 'Status'
    ], array_values(array_diff(gasSlipReportHeaders(), ['Gas Slip No.', 'Date Issued', 'Requested By', 'Purpose', 'Route', 'Status'])));
    return [
    'Gas Slip No.',
    'Date Issued',
    'Validity Until',
    'Vehicle',
    'Route',

    'Fuel',
    'Liters',
    'Fuel Items',
    'Quantities',

    'Purpose',
    'Requested By',

    'Recommended By',
    'Recommended At',

    'Approved By',
    'Approved At',

    'Rejected By',
    'Rejected At',
    'Rejection Reason',

    'Printed At',
    'Status'
];
}

function gasSlipReportValues(array $row, bool $monitoring = false): array
{
    $values = [

        $row['gas_slip_id'],

        $row['date_issued'],

        $row['validity_until'],

        $row['plate_no'],

        formatRoute($row['route_path']),

        $row['fuel'],
        $row['liters'],
        $row['fuel_items'],
        $row['fuel_quantities'],

        formatPurpose($row['purpose']),

        formatRequestedBy($row['requested_by']),

        trim($row['recommender_name']),
        $row['recommended_at'],

        trim($row['approver_name']),
        $row['approved_at'],

        trim($row['rejector_name']),
        $row['rejected_at'],

        $row['rejection_reason'],

        $row['printed_at'],

        ucfirst($row['status'])

    ];
    if (!$monitoring) return $values;
    $byHeader = array_combine(gasSlipReportHeaders(), $values);
    $byHeader['Department'] = $row['department'];
    $byHeader['Area'] = $row['area'];
    $byHeader['Routes'] = formatRoute($row['route_path']);
    return array_map(fn($header) => $byHeader[$header], gasSlipReportHeaders(true));
}

function gasSlipReportSafeCell($value): string
{
    $value = (string)($value ?? '');
    // Protect text even when a spreadsheet ignores leading spaces/control bytes.
    if (preg_match('/^[\s\p{Z}\x00-\x20\x{FEFF}]*[=+@\-]/u', $value) || preg_match('/^[\t\r\n]/', $value)) return "'" . $value;
    return $value;
}

function gasSlipReportCsvStart($output, bool $monitoring = false): void
{
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, gasSlipReportHeaders($monitoring));
}

function gasSlipReportCsvRow($output, array $row, bool $monitoring = false): void
{
    $values = gasSlipReportValues($row, $monitoring);
    if ($monitoring) $values = array_map('gasSlipReportSafeCell', $values);
    fputcsv($output, $values);
}

function gasSlipReportDownloadHeaders(): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="gas_slips_' . date('Ymd_His') . '.csv"');
}
