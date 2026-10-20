<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

function rekamEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$connection = getDatabaseConnection();

$search = trim((string) ($_GET['search'] ?? ''));

$records = [];
$totalRecords = 0;
$totalPatients = 0;
$errorMessage = null;

try {
    $where = '';
    $params = [];

    if ($search !== '') {
        $where = '
            WHERE p.name LIKE :patient_name
               OR p.medical_record_number LIKE :medical_record
               OR d.name LIKE :doctor_name
               OR pv.diagnosis LIKE :diagnosis
        ';

        $keyword = '%' . $search . '%';

        $params = [
            'patient_name' => $keyword,
            'medical_record' => $keyword,
            'doctor_name' => $keyword,
            'diagnosis' => $keyword,
        ];
    }

    $sql = "
        SELECT
            pv.id,
            pv.visit_date,
            pv.complaint,
            pv.diagnosis,
            pv.notes,
            pv.status,
            p.id AS patient_id,
            p.name AS patient_name,
            p.medical_record_number,
            p.gender,
            p.date_of_birth,
            d.name AS doctor_name,
            d.specialty
        FROM patient_visits pv
        INNER JOIN patients p ON p.id = pv.patient_id
        INNER JOIN doctors d ON d.id = pv.doctor_id
        $where
        ORDER BY pv.visit_date DESC, pv.id DESC
    ";

    $statement = $connection->prepare($sql);
    $statement->execute($params);

    $records = $statement->fetchAll(PDO::FETCH_ASSOC);

    $totalRecords = (int) $connection->query(
        'SELECT COUNT(*) FROM patient_visits'
    )->fetchColumn();

    $totalPatients = (int) $connection->query(
        'SELECT COUNT(DISTINCT patient_id) FROM patient_visits'
    )->fetchColumn();
} catch (PDOException $exception) {
    error_log('Load medical records error: ' . $exception->getMessage());

    $errorMessage =
        'Data rekam medis gagal dimuat. Periksa struktur tabel pasien, dokter, dan kunjungan.';
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Rekam Medis - RS Medika Sehat</title>

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">
    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .rekam-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .rekam-stat {
            padding: 20px;
            background: var(--panel, #fff);
            border: 1px solid var(--border, #e5e7eb);
            border-radius: 12px;
        }

        .rekam-stat-label {
            color: #667085;
            font-size: 13px;
        }

        .rekam-stat-number {
            display: block;
            margin-top: 8px;
            font-size: 28px;
            font-weight: 800;
            color: #17212f;
        }

        .rekam-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .rekam-search {
            display: flex;
            flex: 1;
            flex-wrap: wrap;
            gap: 10px;
        }

        .rekam-search input {
            flex: 1;
            min-width: 200px;
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid var(--border, #d9e1e8);
            border-radius: 8px;
            font: inherit;
        }

        .rekam-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .rekam-table {
            width: 100%;
            min-width: 1100px;
            border-collapse: collapse;
        }

        .rekam-table th,
        .rekam-table td {
            padding: 13px 12px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #e7edf2;
        }

        .rekam-table th {
            background: #f8fafc;
            color: #667085;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .rekam-patient {
            font-weight: 700;
            color: #17212f;
        }

        .rekam-muted {
            margin-top: 4px;
            color: #697586;
            font-size: 12px;
        }

        .rekam-badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            background: #e7f8ef;
            color: #18794e;
            font-size: 12px;
            font-weight: 700;
        }

        .rekam-empty {
            padding: 35px 16px !important;
            text-align: center !important;
            color: #667085;
        }

        .rekam-error {
            padding: 16px;
            border-radius: 9px;
            background: #fee4e2;
            color: #b42318;
        }

        .rekam-note {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 9px;
            background: #eff8ff;
            color: #175cd3;
            font-size: 13px;
            line-height: 1.6;
        }

        @media (max-width: 600px) {
            .rekam-stats {
                grid-template-columns: 1fr;
            }

            .rekam-search {
                width: 100%;
            }

            .rekam-search input {
                min-width: 0;
                width: 100%;
            }
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 10mm;
            }

            .admin-header,
            .admin-sidebar,
            .rekam-toolbar,
            .rekam-note {
                display: none !important;
            }

            .admin-layout,
            .admin-main {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .rekam-table-wrap {
                overflow: visible;
            }

            .rekam-table {
                min-width: 0;
                font-size: 9px;
            }

            .rekam-table th,
            .rekam-table td {
                padding: 6px;
                border: 1px solid #ccc;
            }

            .rekam-stat {
                padding: 10px;
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
                    <h1>Rekam Medis</h1>
                    <p class="admin-intro">
                        Lihat catatan pemeriksaan, diagnosis, dan riwayat medis pasien.
                    </p>
                </div>

                <button
                    type="button"
                    class="btn btn-primary"
                    onclick="window.print()">
                    Cetak Rekam Medis
                </button>
            </div>

            <?php require __DIR__ . '/../partials/flash_message.php'; ?>

            <?php if ($errorMessage !== null): ?>

                <section class="admin-panel rekam-error" role="alert">
                    <?= rekamEscape($errorMessage) ?>
                </section>

            <?php else: ?>

                <div class="rekam-stats">

                    <div class="rekam-stat">
                        <span class="rekam-stat-label">
                            Total catatan pemeriksaan
                        </span>

                        <strong class="rekam-stat-number">
                            <?= number_format($totalRecords) ?>
                        </strong>
                    </div>

                    <div class="rekam-stat">
                        <span class="rekam-stat-label">
                            Pasien dengan kunjungan
                        </span>

                        <strong class="rekam-stat-number">
                            <?= number_format($totalPatients) ?>
                        </strong>
                    </div>

                </div>

                <section class="admin-panel">

                    <div class="rekam-toolbar">
                        <form
                            class="rekam-search"
                            method="get"
                            action="rekam_medis.php">

                            <input
                                type="search"
                                name="search"
                                placeholder="Cari nama pasien, nomor rekam medis, dokter, diagnosis..."
                                value="<?= rekamEscape($search) ?>">

                            <button class="btn btn-secondary" type="submit">
                                Cari
                            </button>

                            <a
                                class="btn btn-secondary"
                                href="rekam_medis.php">
                                Reset
                            </a>
                        </form>
                    </div>

                    <div class="rekam-table-wrap">

                        <table class="rekam-table">

                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Tanggal</th>
                                    <th>Nomor Rekam Medis</th>
                                    <th>Identitas Pasien</th>
                                    <th>Dokter</th>
                                    <th>Keluhan</th>
                                    <th>Diagnosis</th>
                                    <th>Catatan Pemeriksaan</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if ($records === []): ?>

                                    <tr>
                                        <td colspan="9" class="rekam-empty">
                                            Belum ada catatan rekam medis yang sesuai.
                                        </td>
                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($records as $index => $record): ?>

                                        <?php
                                        $birthDate = $record['date_of_birth'] ?? '';
                                        $birthLabel = '-';

                                        if ($birthDate !== '') {
                                            $timestamp = strtotime((string) $birthDate);

                                            if ($timestamp !== false) {
                                                $birthLabel = date('d-m-Y', $timestamp);
                                            }
                                        }

                                        $visitDate = strtotime(
                                            (string) $record['visit_date']
                                        );

                                        $visitDateLabel = $visitDate !== false
                                            ? date('d-m-Y', $visitDate)
                                            : '-';
                                        ?>

                                        <tr>
                                            <td><?= $index + 1 ?></td>

                                            <td>
                                                <?= rekamEscape($visitDateLabel) ?>
                                            </td>

                                            <td>
                                                <?= rekamEscape(
                                                    $record['medical_record_number'] ?: '-'
                                                ) ?>
                                            </td>

                                            <td>
                                                <div class="rekam-patient">
                                                    <?= rekamEscape($record['patient_name']) ?>
                                                </div>

                                                <div class="rekam-muted">
                                                    Jenis kelamin:
                                                    <?= rekamEscape($record['gender'] ?: '-') ?>
                                                </div>

                                                <div class="rekam-muted">
                                                    Tanggal lahir:
                                                    <?= rekamEscape($birthLabel) ?>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="rekam-patient">
                                                    <?= rekamEscape($record['doctor_name']) ?>
                                                </div>

                                                <div class="rekam-muted">
                                                    <?= rekamEscape($record['specialty'] ?: '-') ?>
                                                </div>
                                            </td>

                                            <td>
                                                <?= rekamEscape($record['complaint'] ?: '-') ?>
                                            </td>

                                            <td>
                                                <?= rekamEscape($record['diagnosis'] ?: '-') ?>
                                            </td>

                                            <td>
                                                <?= rekamEscape($record['notes'] ?: '-') ?>
                                            </td>

                                            <td>
                                                <span class="rekam-badge">
                                                    <?= rekamEscape($record['status']) ?>
                                                </span>
                                            </td>
                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                    <div class="rekam-note">
                        <strong>Informasi:</strong>
                        halaman ini menampilkan catatan dari kunjungan pasien.
                        Diagnosis dan catatan pemeriksaan akan tersedia jika
                        datanya sudah diisi pada kunjungan terkait.
                    </div>

                </section>

            <?php endif; ?>

        </main>
    </div>

</body>

</html>