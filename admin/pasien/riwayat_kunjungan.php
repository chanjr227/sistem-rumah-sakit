<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

function riwayatEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$connection = getDatabaseConnection();

$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$dateFilter = trim((string) ($_GET['tanggal'] ?? ''));

$allowedStatuses = [
    'Menunggu',
    'Sedang Diperiksa',
    'Selesai',
    'Dibatalkan'
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

if (
    $dateFilter !== ''
    && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)
) {
    $dateFilter = '';
}

$visits = [];
$totalVisits = 0;
$totalWaiting = 0;
$totalExamining = 0;
$totalCompleted = 0;
$errorMessage = null;

try {
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(
            p.name LIKE :patient_name
            OR p.medical_record_number LIKE :medical_record
            OR d.name LIKE :doctor_name
            OR pv.complaint LIKE :complaint
        )';

        $keyword = '%' . $search . '%';

        $params['patient_name'] = $keyword;
        $params['medical_record'] = $keyword;
        $params['doctor_name'] = $keyword;
        $params['complaint'] = $keyword;
    }

    if ($statusFilter !== '') {
        $where[] = 'pv.status = :status';
        $params['status'] = $statusFilter;
    }

    if ($dateFilter !== '') {
        $where[] = 'pv.visit_date = :visit_date';
        $params['visit_date'] = $dateFilter;
    }

    $sql = '
        SELECT
            pv.id,
            pv.patient_id,
            pv.doctor_id,
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
    ';

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY pv.visit_date DESC, pv.id DESC';

    $statement = $connection->prepare($sql);
    $statement->execute($params);

    $visits = $statement->fetchAll(PDO::FETCH_ASSOC);

    $totalVisits = (int) $connection->query(
        'SELECT COUNT(*) FROM patient_visits'
    )->fetchColumn();

    $totalWaiting = (int) $connection->query(
        "SELECT COUNT(*) FROM patient_visits
         WHERE status = 'Menunggu'"
    )->fetchColumn();

    $totalExamining = (int) $connection->query(
        "SELECT COUNT(*) FROM patient_visits
         WHERE status = 'Sedang Diperiksa'"
    )->fetchColumn();

    $totalCompleted = (int) $connection->query(
        "SELECT COUNT(*) FROM patient_visits
         WHERE status = 'Selesai'"
    )->fetchColumn();
} catch (PDOException $exception) {
    error_log('Load patient visits error: ' . $exception->getMessage());
    $errorMessage = 'Riwayat kunjungan gagal dimuat. Periksa tabel patient_visits, patients, dan doctors.';
}

function riwayatStatusClass(string $status): string
{
    return match ($status) {
        'Menunggu' => 'riwayat-waiting',
        'Sedang Diperiksa' => 'riwayat-examining',
        'Selesai' => 'riwayat-completed',
        'Dibatalkan' => 'riwayat-cancelled',
        default => 'riwayat-waiting',
    };
}

function riwayatTanggal(mixed $date): string
{
    $timestamp = strtotime((string) $date);

    return $timestamp !== false
        ? date('d-m-Y', $timestamp)
        : '-';
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Riwayat Kunjungan - RS Medika Sehat</title>

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">
    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .riwayat-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .riwayat-stat {
            padding: 20px;
            background: var(--panel, #fff);
            border: 1px solid var(--border, #e5e7eb);
            border-radius: 12px;
        }

        .riwayat-stat-label {
            color: #667085;
            font-size: 13px;
        }

        .riwayat-stat-number {
            display: block;
            margin-top: 8px;
            font-size: 28px;
            font-weight: 800;
            color: #17212f;
        }

        .riwayat-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .riwayat-filters {
            display: flex;
            flex: 1;
            flex-wrap: wrap;
            gap: 10px;
        }

        .riwayat-filters input,
        .riwayat-filters select {
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid var(--border, #d9e1e8);
            border-radius: 8px;
            font: inherit;
            background: #fff;
            color: #17212f;
        }

        .riwayat-filters input[type="search"] {
            flex: 1;
            min-width: 200px;
        }

        .riwayat-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .riwayat-table {
            width: 100%;
            min-width: 1250px;
            border-collapse: collapse;
        }

        .riwayat-table th,
        .riwayat-table td {
            padding: 13px 12px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid #e7edf2;
        }

        .riwayat-table th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #667085;
            background: #f8fafc;
            white-space: nowrap;
        }

        .riwayat-patient-name {
            font-weight: 700;
            color: #17212f;
        }

        .riwayat-muted {
            margin-top: 4px;
            font-size: 12px;
            color: #697586;
        }

        .riwayat-badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .riwayat-waiting {
            color: #b54708;
            background: #fff0e7;
        }

        .riwayat-examining {
            color: #175cd3;
            background: #eff8ff;
        }

        .riwayat-completed {
            color: #18794e;
            background: #e7f8ef;
        }

        .riwayat-cancelled {
            color: #b42318;
            background: #fee4e2;
        }

        .riwayat-empty {
            padding: 35px 16px !important;
            text-align: center !important;
            color: #667085;
        }

        .riwayat-error {
            padding: 16px;
            border-radius: 9px;
            background: #fee4e2;
            color: #b42318;
        }

        .riwayat-complaint {
            max-width: 220px;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .riwayat-print-only {
            display: none;
        }

        @media (max-width: 900px) {
            .riwayat-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .riwayat-stats {
                grid-template-columns: 1fr;
            }

            .riwayat-filters {
                width: 100%;
            }

            .riwayat-filters input,
            .riwayat-filters select {
                width: 100%;
                min-width: 0;
            }
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 10mm;
            }

            body {
                background: #fff !important;
                color: #000 !important;
                font-size: 10px;
            }

            .admin-header,
            .admin-sidebar,
            .riwayat-toolbar,
            .admin-page-heading>a,
            .admin-page-heading .btn,
            .riwayat-no-print,
            .admin-panel .riwayat-note {
                display: none !important;
            }

            .admin-layout,
            .admin-main {
                display: block !important;
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .admin-page-heading {
                margin-bottom: 12px !important;
            }

            .admin-page-heading h1 {
                font-size: 20px !important;
            }

            .riwayat-print-only {
                display: block !important;
                margin-bottom: 12px;
            }

            .riwayat-stats {
                grid-template-columns: repeat(4, 1fr);
                gap: 8px;
                margin-bottom: 12px;
            }

            .riwayat-stat {
                padding: 10px;
                border: 1px solid #bbb;
            }

            .riwayat-stat-number {
                font-size: 18px;
            }

            .admin-panel {
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
            }

            .riwayat-table-wrap {
                overflow: visible;
            }

            .riwayat-table {
                width: 100%;
                min-width: 0;
                font-size: 9px;
            }

            .riwayat-table th,
            .riwayat-table td {
                padding: 6px;
                border: 1px solid #ccc;
            }

            .riwayat-table th {
                background: #eee !important;
                color: #000 !important;
            }

            .riwayat-badge {
                padding: 0;
                color: #000 !important;
                background: transparent !important;
            }

            tr {
                break-inside: avoid;
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
                    <p class="eyebrow">Manajemen rumah sakit</p>

                    <h1>Riwayat Kunjungan</h1>

                    <p class="admin-intro">
                        Pantau data kunjungan pasien, dokter yang menangani,
                        keluhan, dan status pemeriksaan.
                    </p>
                </div>

                <button
                    class="btn btn-primary riwayat-no-print"
                    type="button"
                    onclick="window.print()">
                    Cetak Riwayat
                </button>
            </div>

            <?php require __DIR__ . '/../partials/flash_message.php'; ?>

            <?php if ($errorMessage !== null): ?>

                <section class="admin-panel riwayat-error" role="alert">
                    <?= riwayatEscape($errorMessage) ?>
                </section>

            <?php else: ?>

                <div class="riwayat-print-only">
                    <strong>RS Medika Sehat</strong>
                    <p>Laporan riwayat kunjungan pasien</p>
                    <p>
                        Tanggal cetak:
                        <?= date('d-m-Y H:i') ?>
                    </p>
                </div>

                <div class="riwayat-stats">

                    <div class="riwayat-stat">
                        <span class="riwayat-stat-label">
                            Total kunjungan
                        </span>

                        <strong class="riwayat-stat-number">
                            <?= number_format($totalVisits) ?>
                        </strong>
                    </div>

                    <div class="riwayat-stat">
                        <span class="riwayat-stat-label">
                            Menunggu
                        </span>

                        <strong class="riwayat-stat-number">
                            <?= number_format($totalWaiting) ?>
                        </strong>
                    </div>

                    <div class="riwayat-stat">
                        <span class="riwayat-stat-label">
                            Sedang diperiksa
                        </span>

                        <strong class="riwayat-stat-number">
                            <?= number_format($totalExamining) ?>
                        </strong>
                    </div>

                    <div class="riwayat-stat">
                        <span class="riwayat-stat-label">
                            Selesai
                        </span>

                        <strong class="riwayat-stat-number">
                            <?= number_format($totalCompleted) ?>
                        </strong>
                    </div>

                </div>

                <section class="admin-panel">

                    <div class="riwayat-toolbar riwayat-no-print">

                        <form
                            class="riwayat-filters"
                            method="get"
                            action="riwayat_kunjungan.php">

                            <input
                                type="search"
                                name="search"
                                placeholder="Cari pasien, nomor rekam medis, dokter..."
                                value="<?= riwayatEscape($search) ?>"
                                aria-label="Cari riwayat kunjungan">

                            <select name="status" aria-label="Filter status">
                                <option value="">Semua status</option>

                                <?php foreach ($allowedStatuses as $status): ?>
                                    <option
                                        value="<?= riwayatEscape($status) ?>"
                                        <?= $statusFilter === $status ? 'selected' : '' ?>>
                                        <?= riwayatEscape($status) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <input
                                type="date"
                                name="tanggal"
                                value="<?= riwayatEscape($dateFilter) ?>"
                                aria-label="Filter tanggal kunjungan">

                            <button class="btn btn-secondary" type="submit">
                                Cari
                            </button>

                            <a
                                class="btn btn-secondary"
                                href="riwayat_kunjungan.php">
                                Reset
                            </a>

                        </form>

                    </div>

                    <div class="riwayat-table-wrap">

                        <table class="riwayat-table">

                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Tanggal Kunjungan</th>
                                    <th>Pasien</th>
                                    <th>No. Rekam Medis</th>
                                    <th>Dokter</th>
                                    <th>Keluhan</th>
                                    <th>Diagnosis</th>
                                    <th>Status</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if ($visits === []): ?>

                                    <tr>
                                        <td colspan="9" class="riwayat-empty">
                                            Tidak ada riwayat kunjungan yang sesuai.
                                        </td>
                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($visits as $index => $visit): ?>

                                        <?php
                                        $status = (string) $visit['status'];
                                        ?>

                                        <tr>
                                            <td><?= $index + 1 ?></td>

                                            <td>
                                                <?= riwayatEscape(
                                                    riwayatTanggal($visit['visit_date'])
                                                ) ?>
                                            </td>

                                            <td>
                                                <div class="riwayat-patient-name">
                                                    <?= riwayatEscape($visit['patient_name']) ?>
                                                </div>
                                            </td>

                                            <td>
                                                <?= riwayatEscape(
                                                    $visit['medical_record_number'] ?: '-'
                                                ) ?>
                                            </td>

                                            <td>
                                                <div class="riwayat-patient-name">
                                                    <?= riwayatEscape($visit['doctor_name']) ?>
                                                </div>

                                                <div class="riwayat-muted">
                                                    <?= riwayatEscape(
                                                        $visit['specialty'] ?: '-'
                                                    ) ?>
                                                </div>
                                            </td>

                                            <td class="riwayat-complaint">
                                                <?= riwayatEscape(
                                                    $visit['complaint'] ?: '-'
                                                ) ?>
                                            </td>

                                            <td class="riwayat-complaint">
                                                <?= riwayatEscape(
                                                    $visit['diagnosis'] ?: '-'
                                                ) ?>
                                            </td>

                                            <td>
                                                <span class="riwayat-badge <?= riwayatEscape(
                                                                                riwayatStatusClass($status)
                                                                            ) ?>">
                                                    <?= riwayatEscape($status) ?>
                                                </span>
                                            </td>

                                            <td class="riwayat-complaint">
                                                <?= riwayatEscape(
                                                    $visit['notes'] ?: '-'
                                                ) ?>
                                            </td>
                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </section>

            <?php endif; ?>

        </main>
    </div>

</body>

</html>