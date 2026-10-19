<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function laporanFarmasiEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$today = new DateTimeImmutable('today');
$defaultStart = $today->modify('first day of this month');

$startDate = (string) ($_GET['tanggal_mulai'] ?? $defaultStart->format('Y-m-d'));
$endDate = (string) ($_GET['tanggal_akhir'] ?? $today->format('Y-m-d'));

$errors = [];
$medicines = [];
$transactions = [];

$summary = [
    'total_obat' => 0,
    'total_stok' => 0,
    'stok_menipis' => 0,
    'kedaluwarsa' => 0,
    'masuk' => 0,
    'keluar' => 0,
    'jumlah_transaksi_masuk' => 0,
    'jumlah_transaksi_keluar' => 0,
];

$validStart = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
$validEnd = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);

$startErrors = DateTimeImmutable::getLastErrors();
$endErrors = DateTimeImmutable::getLastErrors();

$startValid = $validStart !== false
    && ($startErrors === false || (
        $startErrors['warning_count'] === 0
        && $startErrors['error_count'] === 0
    ))
    && $validStart->format('Y-m-d') === $startDate;

$endValid = $validEnd !== false
    && ($endErrors === false || (
        $endErrors['warning_count'] === 0
        && $endErrors['error_count'] === 0
    ))
    && $validEnd->format('Y-m-d') === $endDate;

if (!$startValid || !$endValid) {
    $errors[] = 'Tanggal laporan tidak valid.';
} elseif ($startDate > $endDate) {
    $errors[] = 'Tanggal mulai tidak boleh melewati tanggal akhir.';
}

try {
    $connection = getDatabaseConnection();

    // Ringkasan kondisi persediaan saat ini.
    $inventoryQuery = $connection->query(
        'SELECT
            COUNT(*) AS total_obat,
            COALESCE(SUM(stock), 0) AS total_stok,
            COALESCE(SUM(
                CASE WHEN stock <= minimum_stock THEN 1 ELSE 0 END
            ), 0) AS stok_menipis,
            COALESCE(SUM(
                CASE
                    WHEN expiry_date IS NOT NULL
                     AND expiry_date < CURDATE()
                    THEN 1 ELSE 0
                END
            ), 0) AS kedaluwarsa
         FROM medicines'
    );

    $inventory = $inventoryQuery->fetch(PDO::FETCH_ASSOC);

    if ($inventory) {
        $summary['total_obat'] = (int) $inventory['total_obat'];
        $summary['total_stok'] = (int) $inventory['total_stok'];
        $summary['stok_menipis'] = (int) $inventory['stok_menipis'];
        $summary['kedaluwarsa'] = (int) $inventory['kedaluwarsa'];
    }

    // Laporan transaksi hanya dijalankan jika rentang tanggal valid.
    if ($errors === []) {
        $summaryQuery = $connection->prepare(
            'SELECT
                COALESCE(SUM(
                    CASE WHEN transaction_type = :type_masuk
                    THEN quantity ELSE 0 END
                ), 0) AS total_masuk,
                COALESCE(SUM(
                    CASE WHEN transaction_type = :type_keluar
                    THEN quantity ELSE 0 END
                ), 0) AS total_keluar,
                COALESCE(SUM(
                    CASE WHEN transaction_type = :count_masuk
                    THEN 1 ELSE 0 END
                ), 0) AS jumlah_masuk,
                COALESCE(SUM(
                    CASE WHEN transaction_type = :count_keluar
                    THEN 1 ELSE 0 END
                ), 0) AS jumlah_keluar
             FROM medicine_transactions
             WHERE transaction_date >= :start_date
               AND transaction_date < :end_date'
        );

        $summaryQuery->execute([
            'type_masuk' => 'masuk',
            'type_keluar' => 'keluar',
            'count_masuk' => 'masuk',
            'count_keluar' => 'keluar',
            'start_date' => $startDate . ' 00:00:00',
            'end_date' => $validEnd->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
        ]);

        $transactionSummary = $summaryQuery->fetch(PDO::FETCH_ASSOC);

        if ($transactionSummary) {
            $summary['masuk'] = (int) $transactionSummary['total_masuk'];
            $summary['keluar'] = (int) $transactionSummary['total_keluar'];
            $summary['jumlah_transaksi_masuk'] =
                (int) $transactionSummary['jumlah_masuk'];
            $summary['jumlah_transaksi_keluar'] =
                (int) $transactionSummary['jumlah_keluar'];
        }

        // Rincian seluruh transaksi pada periode terpilih.
        $detailQuery = $connection->prepare(
            'SELECT
                mt.id,
                mt.transaction_type,
                mt.quantity,
                mt.notes,
                mt.transaction_date,
                m.code,
                m.name,
                m.unit
             FROM medicine_transactions mt
             INNER JOIN medicines m ON m.id = mt.medicine_id
             WHERE mt.transaction_date >= :start_date
               AND mt.transaction_date < :end_date
             ORDER BY mt.transaction_date DESC, mt.id DESC'
        );

        $detailQuery->execute([
            'start_date' => $startDate . ' 00:00:00',
            'end_date' => $validEnd->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
        ]);

        $transactions = $detailQuery->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $exception) {
    error_log('Laporan farmasi error: ' . $exception->getMessage());

    http_response_code(500);

    $errors[] =
        'Laporan gagal dimuat. Periksa koneksi database dan tabel medicine_transactions.';
}

function laporanFarmasiTanggal(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    try {
        return (new DateTimeImmutable($value))->format('d-m-Y H:i');
    } catch (Throwable) {
        return '-';
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporan Farmasi - RS Medika Sehat</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">
    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .laporan-content {
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .laporan-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
        }

        .laporan-card {
            padding: 20px;
            background: #fff;
            border: 1px solid #e4eaf0;
            border-radius: 13px;
            min-width: 0;
        }

        .laporan-card p {
            margin: 0 0 12px;
            color: #667085;
            font-size: 12px;
            font-weight: 600;
        }

        .laporan-card h2 {
            margin: 0;
            color: #17212f;
            font-size: 27px;
            font-weight: 800;
        }

        .laporan-card small {
            display: block;
            margin-top: 8px;
            color: #667085;
            font-size: 11px;
        }

        .laporan-filter {
            display: flex;
            align-items: flex-end;
            gap: 14px;
            flex-wrap: wrap;
        }

        .laporan-filter-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
            min-width: 180px;
        }

        .laporan-filter-field label {
            color: #344054;
            font-size: 12px;
            font-weight: 700;
        }

        .laporan-filter-field input {
            min-height: 43px;
            box-sizing: border-box;
            padding: 10px 12px;
            border: 1px solid #d0d5dd;
            border-radius: 8px;
            background: #fff;
            color: #17212f;
            font: inherit;
            font-size: 13px;
        }

        .laporan-filter-actions {
            display: flex;
            gap: 9px;
            flex-wrap: wrap;
        }

        .laporan-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .laporan-table {
            width: 100%;
            min-width: 800px;
            border-collapse: collapse;
        }

        .laporan-table th {
            padding: 14px;
            background: #f8fafc;
            border-bottom: 1px solid #e4eaf0;
            color: #667085;
            font-size: 11px;
            font-weight: 800;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .laporan-table td {
            padding: 14px;
            border-bottom: 1px solid #edf0f4;
            color: #344054;
            font-size: 12px;
            vertical-align: top;
        }

        .laporan-table tbody tr:hover {
            background: #fafcff;
        }

        .laporan-badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .laporan-masuk {
            background: #e8f8f0;
            color: #16794e;
        }

        .laporan-keluar {
            background: #fff0f0;
            color: #bd3030;
        }

        .laporan-empty {
            padding: 35px !important;
            text-align: center;
            color: #667085 !important;
        }

        .laporan-error {
            padding: 15px 18px;
            border: 1px solid #f3c7c7;
            border-radius: 10px;
            background: #fff1f1;
            color: #a4262c;
        }

        .laporan-print-heading {
            display: none;
        }

        @media (max-width: 1000px) {
            .laporan-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .laporan-summary {
                gap: 10px;
            }

            .laporan-card {
                padding: 15px;
            }

            .laporan-card h2 {
                font-size: 23px;
            }

            .laporan-filter-field {
                flex: 1 1 100%;
            }
        }

        @media print {
            @media print {
                @page {
                    size: A4 landscape;
                    margin: 7mm;
                }

                html,
                body {
                    width: 100%;
                    margin: 0 !important;
                    padding: 0 !important;
                    background: #fff !important;
                    color: #000 !important;
                    font-family: Arial, sans-serif !important;
                    font-size: 9px !important;
                }

                .admin-header,
                .admin-sidebar,
                .admin-page-heading,
                .laporan-filter-panel,
                .laporan-no-print,
                .page-footer,
                .admin-sidebar-home {
                    display: none !important;
                }

                .admin-layout,
                .admin-main,
                .laporan-content,
                .admin-panel {
                    display: block !important;
                    width: 100% !important;
                    max-width: none !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    border: 0 !important;
                    box-shadow: none !important;
                    background: #fff !important;
                }

                .laporan-print-heading {
                    display: block !important;
                    margin: 0 0 8px !important;
                }

                .laporan-print-heading h1 {
                    margin: 0 0 3px !important;
                    font-size: 16px !important;
                }

                .laporan-print-heading h2 {
                    margin: 0 0 4px !important;
                    font-size: 12px !important;
                }

                .laporan-print-heading p {
                    margin: 2px 0 !important;
                    font-size: 9px !important;
                }

                .laporan-summary {
                    display: grid !important;
                    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
                    gap: 5px !important;
                    margin: 8px 0 !important;
                }

                .laporan-card {
                    padding: 7px !important;
                    border: 1px solid #bbb !important;
                    border-radius: 0 !important;
                    break-inside: avoid;
                }

                .laporan-card p {
                    margin: 0 0 4px !important;
                    font-size: 8px !important;
                }

                .laporan-card h2 {
                    margin: 0 !important;
                    font-size: 14px !important;
                }

                .laporan-card small {
                    margin-top: 3px !important;
                    font-size: 7px !important;
                }

                .section-heading {
                    margin: 8px 0 5px !important;
                    padding: 0 !important;
                }

                .section-heading h2 {
                    margin: 0 !important;
                    font-size: 11px !important;
                }

                .section-heading p {
                    margin: 3px 0 !important;
                    font-size: 8px !important;
                }

                .laporan-table-wrap {
                    width: 100% !important;
                    overflow: visible !important;
                }

                .laporan-table {
                    width: 100% !important;
                    min-width: 0 !important;
                    border-collapse: collapse !important;
                    table-layout: auto !important;
                }

                .laporan-table thead {
                    display: table-header-group;
                }

                .laporan-table tr {
                    break-inside: avoid;
                    page-break-inside: avoid;
                }

                .laporan-table th,
                .laporan-table td {
                    padding: 4px 5px !important;
                    border: 1px solid #ccc !important;
                    font-size: 8px !important;
                    line-height: 1.2 !important;
                    color: #000 !important;
                    overflow-wrap: anywhere;
                }

                .laporan-badge {
                    padding: 2px 4px !important;
                    border: 1px solid #aaa !important;
                    border-radius: 0 !important;
                    background: #fff !important;
                    color: #000 !important;
                    font-size: 7px !important;
                }

                .laporan-content {
                    gap: 0 !important;
                }
            }

            @media print {
                @page {
                    size: A4 landscape;
                    margin: 5mm;
                }

                html,
                body {
                    width: 100% !important;
                    height: auto !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    background: #fff !important;
                    color: #000 !important;
                    font-family: Arial, sans-serif !important;
                    font-size: 8px !important;
                }

                * {
                    box-sizing: border-box !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }

                .admin-header,
                .admin-sidebar,
                .admin-page-heading,
                .laporan-filter-panel,
                .laporan-no-print,
                .page-footer,
                .admin-sidebar-home {
                    display: none !important;
                }

                .admin-layout,
                .admin-main,
                .laporan-content,
                .admin-panel {
                    display: block !important;
                    width: 100% !important;
                    max-width: none !important;
                    min-height: 0 !important;
                    height: auto !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    border: 0 !important;
                    box-shadow: none !important;
                    background: #fff !important;
                    overflow: visible !important;
                }

                .laporan-print-heading {
                    display: block !important;
                    margin: 0 0 5px !important;
                }

                .laporan-print-heading h1 {
                    margin: 0 0 3px !important;
                    font-size: 19px !important;
                    line-height: 1.1 !important;
                }

                .laporan-print-heading h2 {
                    margin: 0 0 4px !important;
                    font-size: 14px !important;
                }

                .laporan-print-heading p {
                    margin: 2px 0 !important;
                    font-size: 8px !important;
                }

                .laporan-summary {
                    display: grid !important;
                    grid-template-columns: repeat(6, minmax(0, 1fr)) !important;
                    gap: 5px !important;
                    margin: 7px 0 !important;
                }

                .laporan-card {
                    min-width: 0 !important;
                    padding: 6px !important;
                    border: 1px solid #bbb !important;
                    border-radius: 5px !important;
                    break-inside: avoid !important;
                    page-break-inside: avoid !important;
                }

                .laporan-card p {
                    margin: 0 0 4px !important;
                    font-size: 7px !important;
                }

                .laporan-card h2 {
                    margin: 0 !important;
                    font-size: 12px !important;
                }

                .laporan-card small {
                    margin-top: 3px !important;
                    font-size: 6px !important;
                }

                .section-heading {
                    margin: 7px 0 4px !important;
                    padding: 0 !important;
                }

                .section-heading h2 {
                    margin: 0 !important;
                    font-size: 10px !important;
                }

                .section-heading p {
                    margin: 3px 0 !important;
                    font-size: 7px !important;
                }

                .laporan-table-wrap {
                    width: 100% !important;
                    max-height: none !important;
                    height: auto !important;
                    overflow: visible !important;
                }

                .laporan-table {
                    width: 100% !important;
                    min-width: 0 !important;
                    border-collapse: collapse !important;
                    table-layout: fixed !important;
                    font-size: 7px !important;
                }

                .laporan-table thead {
                    display: table-header-group !important;
                }

                .laporan-table tr {
                    break-inside: avoid !important;
                    page-break-inside: avoid !important;
                }

                .laporan-table th,
                .laporan-table td {
                    padding: 3px 4px !important;
                    border: 1px solid #ccc !important;
                    font-size: 7px !important;
                    line-height: 1.15 !important;
                    color: #000 !important;
                    overflow-wrap: anywhere !important;
                }

                .laporan-badge {
                    padding: 2px 3px !important;
                    border: 1px solid #aaa !important;
                    border-radius: 3px !important;
                    background: #fff !important;
                    color: #000 !important;
                    font-size: 6px !important;
                }

                .laporan-content {
                    gap: 0 !important;
                }
            }


            body {
                background: #fff !important;
                color: #000 !important;
                font-family: Arial, sans-serif !important;
            }

            .admin-header,
            .admin-sidebar,
            .admin-page-heading,
            .laporan-filter-panel,
            .laporan-no-print,
            .page-footer,
            .admin-sidebar-home {
                display: none !important;
            }

            .admin-layout,
            .admin-main,
            .laporan-content,
            .admin-panel {
                display: block !important;
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                border: 0 !important;
                box-shadow: none !important;
                background: #fff !important;
            }

            .laporan-print-heading {
                display: block !important;
                margin-bottom: 18px;
            }

            .laporan-summary {
                grid-template-columns: repeat(4, 1fr);
                gap: 8px;
                margin: 16px 0;
            }

            .laporan-card {
                padding: 10px;
                border: 1px solid #bbb;
                break-inside: avoid;
            }

            .laporan-card h2 {
                font-size: 19px;
            }

            .laporan-table-wrap {
                overflow: visible;
            }

            .laporan-table {
                min-width: 0;
            }

            .laporan-table th,
            .laporan-table td {
                padding: 7px;
                font-size: 10px;
                color: #000 !important;
            }

            .laporan-table tr {
                break-inside: avoid;
            }

            .laporan-badge {
                background: transparent !important;
                color: #000 !important;
                border: 1px solid #aaa;
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
                    <p class="eyebrow">Modul Farmasi</p>
                    <h1>Laporan Farmasi</h1>
                    <p class="admin-intro">
                        Pantau persediaan obat dan rekap transaksi farmasi.
                    </p>
                </div>
            </div>

            <div class="laporan-content">

                <?php if ($errors !== []): ?>
                    <section class="laporan-error" role="alert">
                        <strong>Laporan tidak dapat ditampilkan.</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= laporanFarmasiEscape($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php else: ?>

                    <section class="admin-panel laporan-filter-panel">
                        <div class="section-heading">
                            <div>
                                <h2>Filter Periode Laporan</h2>
                                <p>Pilih tanggal untuk melihat transaksi pada periode tertentu.</p>
                            </div>
                        </div>

                        <form method="get"
                            action="laporan_farmasi.php"
                            class="laporan-filter">

                            <div class="laporan-filter-field">
                                <label for="tanggal_mulai">Tanggal Mulai</label>
                                <input
                                    type="date"
                                    id="tanggal_mulai"
                                    name="tanggal_mulai"
                                    value="<?= laporanFarmasiEscape($startDate) ?>"
                                    required>
                            </div>

                            <div class="laporan-filter-field">
                                <label for="tanggal_akhir">Tanggal Akhir</label>
                                <input
                                    type="date"
                                    id="tanggal_akhir"
                                    name="tanggal_akhir"
                                    value="<?= laporanFarmasiEscape($endDate) ?>"
                                    required>
                            </div>

                            <div class="laporan-filter-actions">
                                <button type="submit" class="btn btn-primary">
                                    Tampilkan Laporan
                                </button>

                                <a href="laporan_farmasi.php"
                                    class="btn btn-secondary">
                                    Bulan Ini
                                </a>
                            </div>

                        </form>
                    </section>

                    <section class="laporan-print-heading">
                        <h1>RS Medika Sehat</h1>
                        <h2>Laporan Farmasi</h2>
                        <p>
                            Periode:
                            <?= laporanFarmasiEscape($validStart->format('d-m-Y')) ?>
                            sampai
                            <?= laporanFarmasiEscape($validEnd->format('d-m-Y')) ?>
                        </p>
                        <p>
                            Dicetak pada:
                            <?= date('d-m-Y H:i') ?>
                        </p>
                    </section>

                    <section class="laporan-summary">

                        <article class="laporan-card">
                            <p>Jenis Obat Terdaftar</p>
                            <h2><?= number_format($summary['total_obat']) ?></h2>
                            <small>Total data obat</small>
                        </article>

                        <article class="laporan-card">
                            <p>Total Persediaan</p>
                            <h2><?= number_format($summary['total_stok']) ?></h2>
                            <small>Jumlah unit stok saat ini</small>
                        </article>

                        <article class="laporan-card">
                            <p>Total Stok Masuk</p>
                            <h2><?= number_format($summary['masuk']) ?></h2>
                            <small>
                                <?= number_format($summary['jumlah_transaksi_masuk']) ?>
                                transaksi pada periode
                            </small>
                        </article>

                        <article class="laporan-card">
                            <p>Total Stok Keluar</p>
                            <h2><?= number_format($summary['keluar']) ?></h2>
                            <small>
                                <?= number_format($summary['jumlah_transaksi_keluar']) ?>
                                transaksi pada periode
                            </small>
                        </article>

                        <article class="laporan-card">
                            <p>Obat Stok Menipis</p>
                            <h2><?= number_format($summary['stok_menipis']) ?></h2>
                            <small>Stok sama dengan atau di bawah batas minimum</small>
                        </article>

                        <article class="laporan-card">
                            <p>Obat Kedaluwarsa</p>
                            <h2><?= number_format($summary['kedaluwarsa']) ?></h2>
                            <small>Berdasarkan tanggal saat ini</small>
                        </article>

                    </section>

                    <section class="admin-panel">

                        <div class="section-heading">
                            <div>
                                <h2>Rincian Transaksi Stok</h2>
                                <p>
                                    Periode
                                    <?= laporanFarmasiEscape($validStart->format('d-m-Y')) ?>
                                    –
                                    <?= laporanFarmasiEscape($validEnd->format('d-m-Y')) ?>
                                </p>
                            </div>

                            <button
                                type="button"
                                class="btn btn-primary laporan-no-print"
                                onclick="window.print()">
                                Cetak Laporan
                            </button>
                        </div>

                        <div class="laporan-table-wrap">
                            <table class="laporan-table">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Tanggal Transaksi</th>
                                        <th>Kode Obat</th>
                                        <th>Nama Obat</th>
                                        <th>Jenis Transaksi</th>
                                        <th>Jumlah</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php if ($transactions === []): ?>
                                        <tr>
                                            <td colspan="7" class="laporan-empty">
                                                Tidak ada transaksi pada periode ini.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($transactions as $index => $transaction): ?>
                                            <?php
                                            $isIncoming =
                                                $transaction['transaction_type'] === 'masuk';
                                            ?>

                                            <tr>
                                                <td><?= $index + 1 ?></td>

                                                <td>
                                                    <?= laporanFarmasiEscape(
                                                        laporanFarmasiTanggal(
                                                            (string) $transaction['transaction_date']
                                                        )
                                                    ) ?>
                                                </td>

                                                <td>
                                                    <?= laporanFarmasiEscape($transaction['code']) ?>
                                                </td>

                                                <td>
                                                    <?= laporanFarmasiEscape($transaction['name']) ?>
                                                </td>

                                                <td>
                                                    <span class="laporan-badge <?= $isIncoming
                                                                                    ? 'laporan-masuk'
                                                                                    : 'laporan-keluar' ?>">
                                                        <?= $isIncoming ? 'Stok Masuk' : 'Stok Keluar' ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <?= $isIncoming ? '+' : '-' ?>
                                                    <?= number_format((int) $transaction['quantity']) ?>
                                                    <?= laporanFarmasiEscape($transaction['unit']) ?>
                                                </td>

                                                <td>
                                                    <?= laporanFarmasiEscape(
                                                        $transaction['notes'] ?: '-'
                                                    ) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    </section>

                    <p class="laporan-no-print" style="color:#667085;font-size:12px;">
                        Catatan: ringkasan persediaan menunjukkan kondisi stok saat ini,
                        sedangkan jumlah stok masuk dan keluar mengikuti periode yang dipilih.
                    </p>

                <?php endif; ?>

                <footer class="page-footer">
                    © <?= date('Y') ?> RS Medika Sehat. Sistem Informasi Rumah Sakit.
                </footer>

            </div>

        </main>
    </div>

</body>

</html>