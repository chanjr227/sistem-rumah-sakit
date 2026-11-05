<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

$connection = getDatabaseConnection();

function laporanEscape(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function laporanTanggal(mixed $value): string
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime((string) $value);

    return $timestamp ? date('d-m-Y H:i', $timestamp) : '-';
}

$accountRole = (string) ($_SESSION['account_role'] ?? '');
$doctorId = (int) ($_SESSION['doctor_id'] ?? 0);

if ($accountRole === 'dokter' && $doctorId <= 0) {
    http_response_code(403);
    exit('Akun dokter belum terhubung dengan data dokter.');
}

/*
|--------------------------------------------------------------------------
| Filter laporan
|--------------------------------------------------------------------------
*/

$search = trim((string) ($_GET['search'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));

$allowedStatuses = [
    'menunggu',
    'selesai',
    'dibatalkan',
];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$errorMessage = '';
$reports = [];

$totalExaminations = 0;
$totalCompleted = 0;
$totalWaiting = 0;

try {

    /*
     * Susun kondisi pencarian.
     */

    $conditions = [];
    $params = [];

    if ($accountRole === 'dokter') {
        $conditions[] = 'pv.doctor_id = :doctor_id';
        $params['doctor_id'] = $doctorId;
    }

    if ($search !== '') {
        $conditions[] = '(
            p.name LIKE :patient_name
            OR p.medical_record_number LIKE :medical_number
            OR pv.diagnosis LIKE :diagnosis
            OR pv.complaint LIKE :complaint
        )';

        $keyword = '%' . $search . '%';

        $params['patient_name'] = $keyword;
        $params['medical_number'] = $keyword;
        $params['diagnosis'] = $keyword;
        $params['complaint'] = $keyword;
    }

    if ($dateFrom !== '') {
        $dateObject = DateTime::createFromFormat('!Y-m-d', $dateFrom);

        if (!$dateObject || $dateObject->format('Y-m-d') !== $dateFrom) {
            throw new RuntimeException('Tanggal awal tidak valid.');
        }

        $conditions[] = 'pv.visit_date >= :date_from';
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }

    if ($dateTo !== '') {
        $dateObject = DateTime::createFromFormat('!Y-m-d', $dateTo);

        if (!$dateObject || $dateObject->format('Y-m-d') !== $dateTo) {
            throw new RuntimeException('Tanggal akhir tidak valid.');
        }

        if ($dateFrom !== '' && $dateTo < $dateFrom) {
            throw new RuntimeException(
                'Tanggal akhir tidak boleh lebih awal dari tanggal awal.'
            );
        }

        $conditions[] = 'pv.visit_date <= :date_to';
        $params['date_to'] = $dateTo . ' 23:59:59';
    }

    if ($status !== '') {
        $conditions[] = 'pv.status = :status';
        $params['status'] = $status;
    }

    $whereSql = $conditions
        ? ' WHERE ' . implode(' AND ', $conditions)
        : '';

    /*
     * Ambil laporan pemeriksaan.
     */

    $sql = '
        SELECT
            pv.id,
            pv.visit_date,
            pv.complaint,
            pv.diagnosis,
            pv.status,
            pv.notes,
            p.name AS patient_name,
            p.medical_record_number,
            d.name AS doctor_name,
            d.specialty
        FROM patient_visits pv
        INNER JOIN patients p ON p.id = pv.patient_id
        INNER JOIN doctors d ON d.id = pv.doctor_id
    ' . $whereSql . '
        ORDER BY pv.visit_date DESC
    ';

    $statement = $connection->prepare($sql);
    $statement->execute($params);

    $reports = $statement->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Hitung statistik sesuai hak akses dokter/admin.
     */

    $statParams = [];

    $statWhere = '';

    if ($accountRole === 'dokter') {
        $statWhere = ' WHERE doctor_id = :doctor_id';
        $statParams['doctor_id'] = $doctorId;
    }

    $statStatement = $connection->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status = 'menunggu' THEN 1 ELSE 0 END) AS waiting
         FROM patient_visits
         $statWhere"
    );

    $statStatement->execute($statParams);

    $statistics = $statStatement->fetch(PDO::FETCH_ASSOC);

    $totalExaminations = (int) ($statistics['total'] ?? 0);
    $totalCompleted = (int) ($statistics['completed'] ?? 0);
    $totalWaiting = (int) ($statistics['waiting'] ?? 0);
} catch (Throwable $exception) {

    error_log(
        'Examination report error: ' . $exception->getMessage()
    );

    $errorMessage = $exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'Laporan pemeriksaan gagal dimuat. Periksa struktur tabel patient_visits.';
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporan Pemeriksaan - RS Medika Sehat</title>

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">

    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .report-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .report-stat {
            background: #fff;
            border: 1px solid #e5eaf0;
            border-radius: 12px;
            padding: 20px;
        }

        .report-stat p {
            color: #667085;
            font-size: 13px;
            margin: 0 0 10px;
        }

        .report-stat strong {
            font-size: 27px;
            color: #102a36;
        }

        .report-card {
            background: #fff;
            border: 1px solid #e5eaf0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 22px;
        }

        .report-filter {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto auto;
            gap: 12px;
            align-items: end;
        }

        .report-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
            min-width: 0;
        }

        .report-field label {
            font-size: 12px;
            font-weight: 600;
            color: #344054;
        }

        .report-field input,
        .report-field select {
            width: 100%;
            box-sizing: border-box;
            min-height: 42px;
            padding: 9px 11px;
            border: 1px solid #d7e0e8;
            border-radius: 8px;
            background: #fff;
            font: inherit;
            color: #17212f;
        }

        .report-button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-height: 42px;
            padding: 0 15px;
            border: 0;
            border-radius: 8px;
            background: #087f8c;
            color: #fff;
            text-decoration: none;
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }

        .report-button.light {
            color: #087f8c;
            background: #eaf3f5;
        }

        .report-table-wrap {
            overflow-x: auto;
        }

        .report-table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse;
        }

        .report-table th,
        .report-table td {
            padding: 13px 12px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #e7edf2;
            font-size: 13px;
            line-height: 1.5;
        }

        .report-table th {
            color: #667085;
            background: #f8fafc;
            font-size: 11px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .report-table td strong {
            color: #102a36;
        }

        .report-muted {
            color: #667085;
            font-size: 12px;
        }

        .report-status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            background: #fff4db;
            color: #946200;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .report-status.selesai {
            background: #e7f8ef;
            color: #18794e;
        }

        .report-status.dibatalkan {
            background: #fee4e2;
            color: #b42318;
        }

        .report-alert {
            padding: 13px 16px;
            margin-bottom: 18px;
            border-radius: 9px;
            background: #fee4e2;
            color: #b42318;
        }

        .report-empty {
            padding: 35px 15px;
            text-align: center;
            color: #667085;
        }

        .report-heading-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        @media (max-width: 1050px) {
            .report-filter {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .report-stats {
                grid-template-columns: 1fr;
            }

            .report-filter {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            @page {
                size: landscape;
                margin: 12mm;
            }

            body {
                background: #fff !important;
            }

            .admin-sidebar,
            .topbar,
            .report-filter-card,
            .report-heading-actions {
                display: none !important;
            }

            .admin-layout {
                display: block !important;
            }

            .admin-main {
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .report-card,
            .report-stat {
                border: 0;
                padding: 8px 0;
            }

            .report-table {
                min-width: 0;
            }

            .report-table th,
            .report-table td {
                font-size: 10px;
                padding: 7px;
            }
        }
    </style>
</head>

<body class="admin-page">

    <?php require __DIR__ . '/../partials/header.php'; ?>

    <div class="admin-layout">

        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="admin-main" id="dashboard">

            <div class="admin-page-heading">

                <div>
                    <p class="eyebrow">Manajemen medis</p>

                    <h1>Laporan Pemeriksaan</h1>

                    <p class="admin-intro">
                        Rekapitulasi hasil pemeriksaan pasien yang telah dicatat dokter.
                    </p>
                </div>

                <div class="report-heading-actions">
                    <button
                        type="button"
                        class="report-button"
                        onclick="window.print()">
                        Cetak Laporan
                    </button>
                </div>

            </div>

            <?php if ($errorMessage !== ''): ?>
                <div class="report-alert">
                    <?= laporanEscape($errorMessage) ?>
                </div>
            <?php endif; ?>

            <section class="report-stats">

                <div class="report-stat">
                    <p>Total Pemeriksaan</p>
                    <strong><?= number_format($totalExaminations) ?></strong>
                </div>

                <div class="report-stat">
                    <p>Pemeriksaan Selesai</p>
                    <strong><?= number_format($totalCompleted) ?></strong>
                </div>

                <div class="report-stat">
                    <p>Pemeriksaan Menunggu</p>
                    <strong><?= number_format($totalWaiting) ?></strong>
                </div>

            </section>

            <section class="report-card report-filter-card">

                <h2>Filter Laporan</h2>

                <form method="GET" action="laporan_pemeriksaan.php">

                    <div class="report-filter">

                        <div class="report-field">
                            <label for="search">Cari Data</label>

                            <input
                                type="search"
                                name="search"
                                id="search"
                                value="<?= laporanEscape($search) ?>"
                                placeholder="Nama, nomor rekam medis, diagnosis...">
                        </div>

                        <div class="report-field">
                            <label for="date_from">Dari Tanggal</label>

                            <input
                                type="date"
                                name="date_from"
                                id="date_from"
                                value="<?= laporanEscape($dateFrom) ?>">
                        </div>

                        <div class="report-field">
                            <label for="date_to">Sampai Tanggal</label>

                            <input
                                type="date"
                                name="date_to"
                                id="date_to"
                                value="<?= laporanEscape($dateTo) ?>">
                        </div>

                        <div class="report-field">
                            <label for="status">Status</label>

                            <select name="status" id="status">
                                <option value="">Semua Status</option>

                                <option
                                    value="menunggu"
                                    <?= $status === 'menunggu' ? 'selected' : '' ?>>
                                    Menunggu
                                </option>

                                <option
                                    value="selesai"
                                    <?= $status === 'selesai' ? 'selected' : '' ?>>
                                    Selesai
                                </option>

                                <option
                                    value="dibatalkan"
                                    <?= $status === 'dibatalkan' ? 'selected' : '' ?>>
                                    Dibatalkan
                                </option>
                            </select>
                        </div>

                        <button type="submit" class="report-button">
                            Terapkan
                        </button>

                        <a
                            href="laporan_pemeriksaan.php"
                            class="report-button light">
                            Reset
                        </a>

                    </div>

                </form>

            </section>

            <section class="report-card">

                <h2>Data Pemeriksaan Pasien</h2>

                <p class="report-muted">
                    Jumlah data sesuai filter: <?= count($reports) ?> pemeriksaan.
                </p>

                <div class="report-table-wrap">

                    <table class="report-table">

                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Tanggal</th>
                                <th>Pasien</th>
                                <th>No. Rekam Medis</th>
                                <?php if ($accountRole !== 'dokter'): ?>
                                    <th>Dokter</th>
                                <?php endif; ?>
                                <th>Keluhan</th>
                                <th>Diagnosis</th>
                                <th>Status</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (empty($reports)): ?>

                                <tr>
                                    <td
                                        colspan="<?= $accountRole === 'dokter' ? 8 : 9 ?>"
                                        class="report-empty">
                                        Tidak ada data pemeriksaan yang ditemukan.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($reports as $index => $report): ?>

                                    <?php
                                    $statusClass = strtolower(
                                        (string) $report['status']
                                    );

                                    $statusLabel = ucfirst(
                                        str_replace('_', ' ', $statusClass)
                                    );
                                    ?>

                                    <tr>

                                        <td><?= $index + 1 ?></td>

                                        <td>
                                            <?= laporanEscape(
                                                laporanTanggal($report['visit_date'])
                                            ) ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= laporanEscape($report['patient_name']) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= laporanEscape(
                                                $report['medical_record_number']
                                            ) ?>
                                        </td>

                                        <?php if ($accountRole !== 'dokter'): ?>
                                            <td>
                                                <?= laporanEscape($report['doctor_name']) ?>
                                                <div class="report-muted">
                                                    <?= laporanEscape($report['specialty']) ?>
                                                </div>
                                            </td>
                                        <?php endif; ?>

                                        <td>
                                            <?= laporanEscape($report['complaint']) ?>
                                        </td>

                                        <td>
                                            <?= laporanEscape($report['diagnosis']) ?>
                                        </td>

                                        <td>
                                            <span class="report-status <?= laporanEscape($statusClass) ?>">
                                                <?= laporanEscape($statusLabel) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= laporanEscape($report['notes']) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        </main>

    </div>

</body>

</html>