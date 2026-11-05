<?php

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireNurseLogin();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$connection = getDatabaseConnection();

// Inisialisasi variabel
$errors = [];
$dataLaporan = [];

$tanggalAwal = $_GET['tanggal_awal'] ?? date('Y-m-01');
$tanggalAkhir = $_GET['tanggal_akhir'] ?? date('Y-m-d');
$pencarian = trim($_GET['search'] ?? '');

$totalPemantauan = 0;
$totalPasien = 0;

function laporanEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function laporanTanggal(?string $tanggal): string
{
    if (empty($tanggal)) {
        return '-';
    }

    return date('d-m-Y', strtotime($tanggal));
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporan Keperawatan - RS Medika Sehat</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">

    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .report-filter {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .report-filter label {
            display: flex;
            flex-direction: column;
            gap: 7px;
            font-size: 13px;
            font-weight: 600;
            color: #173b43;
        }

        .report-filter input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid #dce9eb;
            border-radius: 8px;
            font: inherit;
            color: #173b43;
        }

        .report-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .report-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .report-summary-card {
            padding: 20px;
            background: #fff;
            border: 1px solid #e5edef;
            border-radius: 10px;
        }

        .report-summary-card span {
            display: block;
            color: #71858b;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .report-summary-card strong {
            display: block;
            color: #087e80;
            font-size: 27px;
            font-weight: 700;
        }

        .report-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .report-table {
            width: 100%;
            min-width: 1100px;
        }

        .report-period {
            color: #71858b;
            font-size: 13px;
            margin-bottom: 18px;
        }

        .report-empty {
            padding: 30px 16px !important;
            text-align: center;
            color: #667085;
        }

        .report-error {
            padding: 13px 16px;
            background: #fff0ef;
            color: #a52e2a;
            border-radius: 9px;
            margin-bottom: 18px;
            font-size: 13px;
        }

        @media (max-width: 700px) {

            .report-filter,
            .report-summary {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            @page {
                size: landscape;
                margin: 10mm;
            }

            .admin-sidebar,
            .sidebar,
            .admin-header,
            .report-filter-panel,
            .no-print {
                display: none !important;
            }

            .admin-layout {
                display: block !important;
            }

            .admin-main {
                width: 100% !important;
                padding: 0 !important;
            }

            .admin-panel {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                break-inside: avoid;
            }

            .report-table {
                min-width: 0;
            }

            .report-table th,
            .report-table td {
                padding: 6px;
                font-size: 9px;
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

                    <h1>Laporan Keperawatan</h1>

                    <p class="admin-intro">
                        Kelola dan lihat rekapitulasi hasil pemantauan
                        kondisi pasien RS Medika Sehat.
                    </p>
                </div>

                <button
                    type="button"
                    class="btn btn-primary no-print"
                    onclick="window.print()">
                    Cetak Laporan
                </button>
            </div>

            <?php if ($errors): ?>
                <div class="report-error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <div><?= laporanEscape($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!$errors): ?>

                <!-- FILTER LAPORAN -->
                <section class="admin-panel report-filter-panel">

                    <h2>Filter Laporan</h2>

                    <p class="admin-intro">
                        Pilih periode laporan dan masukkan kata kunci
                        untuk menemukan data pemantauan.
                    </p>

                    <form method="GET" action="laporan.php">

                        <div class="report-filter">

                            <label>
                                Tanggal Awal
                                <input
                                    type="date"
                                    name="tanggal_awal"
                                    value="<?= laporanEscape($tanggalAwal) ?>"
                                    required>
                            </label>

                            <label>
                                Tanggal Akhir
                                <input
                                    type="date"
                                    name="tanggal_akhir"
                                    value="<?= laporanEscape($tanggalAkhir) ?>"
                                    required>
                            </label>

                            <label>
                                Cari Pasien / Perawat
                                <input
                                    type="search"
                                    name="search"
                                    placeholder="Nama pasien atau nomor RM..."
                                    value="<?= laporanEscape($pencarian) ?>">
                            </label>

                        </div>

                        <div class="report-actions">

                            <button
                                type="submit"
                                class="btn btn-primary">
                                Terapkan Filter
                            </button>

                            <a
                                href="laporan.php"
                                class="btn btn-secondary">
                                Reset
                            </a>

                        </div>

                    </form>

                </section>

                <!-- RINGKASAN -->
                <div class="report-period">
                    Periode laporan:
                    <strong>
                        <?= date('d-m-Y', strtotime($tanggalAwal)) ?>
                        sampai
                        <?= date('d-m-Y', strtotime($tanggalAkhir)) ?>
                    </strong>
                </div>

                <div class="report-summary">

                    <div class="report-summary-card">
                        <span>Total Pemantauan</span>
                        <strong>
                            <?= number_format($totalPemantauan) ?>
                        </strong>
                    </div>

                    <div class="report-summary-card">
                        <span>Jumlah Pasien</span>
                        <strong>
                            <?= number_format($totalPasien) ?>
                        </strong>
                    </div>

                </div>

                <!-- TABEL LAPORAN -->
                <section class="admin-panel">

                    <h2>Rekapitulasi Pemantauan Pasien</h2>

                    <p class="admin-intro">
                        Daftar hasil pemantauan pasien berdasarkan
                        periode yang dipilih.
                    </p>

                    <div class="report-table-wrap">

                        <table class="admin-table report-table">

                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Tanggal</th>
                                    <th>Nama Pasien</th>
                                    <th>No. Rekam Medis</th>
                                    <th>Perawat</th>
                                    <th>Tekanan Darah</th>
                                    <th>Suhu</th>
                                    <th>Nadi</th>
                                    <th>Pernapasan</th>
                                    <th>Kesadaran</th>
                                    <th>Catatan Kondisi</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if (empty($dataLaporan)): ?>

                                    <tr>
                                        <td colspan="11" class="report-empty">
                                            Belum ada data pemantauan
                                            pada periode ini.
                                        </td>
                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($dataLaporan as $index => $item): ?>

                                        <tr>
                                            <td><?= $index + 1 ?></td>

                                            <td>
                                                <?= laporanEscape(
                                                    laporanTanggal($item['monitoring_date'])
                                                ) ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= laporanEscape($item['patient_name']) ?>
                                                </strong>
                                            </td>

                                            <td>
                                                <?= laporanEscape(
                                                    $item['medical_record_number']
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= laporanEscape($item['nurse_name']) ?>
                                            </td>

                                            <td>
                                                <?= laporanEscape(
                                                    $item['blood_pressure'] ?: '-'
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= $item['temperature'] !== null
                                                    ? laporanEscape($item['temperature']) . ' °C'
                                                    : '-' ?>
                                            </td>

                                            <td>
                                                <?= $item['pulse'] !== null
                                                    ? laporanEscape($item['pulse']) . ' bpm'
                                                    : '-' ?>
                                            </td>

                                            <td>
                                                <?= $item['respiratory_rate'] !== null
                                                    ? laporanEscape($item['respiratory_rate']) . ' x/menit'
                                                    : '-' ?>
                                            </td>

                                            <td>
                                                <?= laporanEscape(
                                                    $item['consciousness'] ?: '-'
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= laporanEscape(
                                                    $item['condition_notes'] ?: '-'
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