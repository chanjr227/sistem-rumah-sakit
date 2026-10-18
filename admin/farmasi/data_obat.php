<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

function obatEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$keyword = trim((string) ($_GET['q'] ?? ''));

$medicines = [];
$totalMedicines = 0;
$lowStock = 0;
$errorMessage = null;

try {
    $connection = getDatabaseConnection();

    // Menghitung total jenis obat.
    $totalStatement = $connection->query(
        'SELECT COUNT(*) FROM medicines'
    );

    $totalMedicines = (int) $totalStatement->fetchColumn();

    // Menghitung obat yang stoknya menipis.
    $lowStockStatement = $connection->query(
        'SELECT COUNT(*)
         FROM medicines
         WHERE stock <= minimum_stock'
    );

    $lowStock = (int) $lowStockStatement->fetchColumn();

    // Mengambil data obat berdasarkan pencarian.
    if ($keyword !== '') {
        $statement = $connection->prepare(
            'SELECT *
             FROM medicines
             WHERE name LIKE :name
                OR code LIKE :code
                OR category LIKE :category
             ORDER BY id DESC'
        );

        $searchValue = '%' . $keyword . '%';

        $statement->execute([
            'name' => $searchValue,
            'code' => $searchValue,
            'category' => $searchValue,
        ]);
    } else {
        $statement = $connection->query(
            'SELECT *
             FROM medicines
             ORDER BY id DESC'
        );
    }

    $medicines = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log(
        'Load medicines error: ' . $exception->getMessage()
    );

    $errorMessage =
        'Data obat gagal dimuat. Periksa tabel medicines dan koneksi database.';
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Data Obat - RS Medika Sehat</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="../../css/base.css">

    <link
        rel="stylesheet"
        href="../../css/pages/admin.css">

    <script
        src="../../js/pages/admin.js"
        defer>
    </script>

    <style>
        /* ==============================
           STATISTIK OBAT
        ============================== */

        .obat-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .obat-stat-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 24px;
            background: #fff;
            border: 1px solid #e7edf2;
            border-radius: 14px;
            min-width: 0;
        }

        .obat-stat-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border-radius: 12px;
            font-size: 22px;
            font-weight: 700;
        }

        .obat-stat-icon.blue {
            color: #1769aa;
            background: #eaf4ff;
        }

        .obat-stat-icon.orange {
            color: #b54708;
            background: #fff0e7;
        }

        .obat-stat-info p {
            margin: 0 0 7px;
            color: #667085;
            font-size: 13px;
        }

        .obat-stat-info h2 {
            margin: 0 0 5px;
            color: #17212f;
            font-size: 26px;
            font-weight: 800;
        }

        .obat-stat-info small {
            color: #667085;
            font-size: 12px;
        }

        /* ==============================
           TOOLBAR DAN PENCARIAN
        ============================== */

        .obat-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .obat-search {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 240px;
            min-height: 44px;
            padding: 0 14px;
            background: #fff;
            border: 1px solid #d9e1e8;
            border-radius: 9px;
        }

        .obat-search-icon {
            color: #667085;
            font-size: 20px;
            flex-shrink: 0;
        }

        .obat-search input {
            width: 100%;
            min-width: 0;
            padding: 10px 0;
            border: none;
            outline: none;
            background: transparent;
            color: #17212f;
            font: inherit;
            font-size: 14px;
        }

        .obat-search:focus-within {
            border-color: #1769aa;
            box-shadow: 0 0 0 3px rgba(23, 105, 170, .10);
        }

        .obat-toolbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        /* ==============================
           TABEL OBAT
        ============================== */

        .obat-table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e7edf2;
            border-radius: 10px;
        }

        .obat-table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
            background: #fff;
        }

        .obat-table th,
        .obat-table td {
            padding: 15px 13px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid #e7edf2;
        }

        .obat-table th {
            color: #667085;
            background: #f8fafc;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            white-space: nowrap;
        }

        .obat-table td {
            color: #344054;
            font-size: 13px;
        }

        .obat-table tbody tr:last-child td {
            border-bottom: none;
        }

        .obat-table tbody tr:hover:not(.obat-empty-row) {
            background: #f9fbfd;
        }

        .obat-number {
            color: #667085 !important;
            width: 48px;
        }

        .obat-code {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 6px;
            background: #eaf4ff;
            color: #1769aa;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .obat-name {
            color: #17212f;
            font-weight: 700;
        }

        .obat-price {
            color: #17212f !important;
            font-weight: 600;
            white-space: nowrap;
        }

        /* ==============================
           STATUS STOK
        ============================== */

        .obat-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .obat-status::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .obat-status.available {
            color: #18794e;
            background: #e7f8ef;
        }

        .obat-status.low {
            color: #b54708;
            background: #fff0e7;
        }

        /* ==============================
           DATA KOSONG DAN FOOTER TABEL
        ============================== */

        .obat-empty-row:hover {
            background: #fff !important;
        }

        .obat-empty {
            padding: 44px 20px !important;
            text-align: center !important;
            color: #667085 !important;
        }

        .obat-empty-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 54px;
            height: 54px;
            margin: 0 auto 14px;
            border-radius: 14px;
            background: #f0f5fa;
            color: #667085;
            font-size: 24px;
        }

        .obat-empty strong {
            display: block;
            color: #17212f;
            font-size: 15px;
        }

        .obat-empty p {
            margin: 8px 0 0;
            color: #667085;
            font-size: 13px;
            font-weight: 400;
        }

        .obat-table-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding-top: 18px;
            color: #667085;
            font-size: 13px;
        }

        .obat-alert {
            margin-bottom: 22px;
            padding: 16px 18px;
            border: 1px solid #f3c7c7;
            border-radius: 10px;
            background: #fff1f1;
            color: #a4262c;
            font-size: 14px;
        }

        /* ==============================
           RESPONSIVE
        ============================== */

        @media (max-width: 900px) {
            .obat-table {
                min-width: 950px;
            }
        }

        @media (max-width: 640px) {
            .obat-stats {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .obat-stat-card {
                padding: 19px;
            }

            .obat-toolbar {
                align-items: stretch;
            }

            .obat-search {
                min-width: 0;
                width: 100%;
                box-sizing: border-box;
            }

            .obat-toolbar-actions {
                width: 100%;
            }

            .obat-toolbar-actions .btn {
                flex: 1;
                text-align: center;
                justify-content: center;
            }
        }
    </style>
</head>

<body class="admin-page">

    <?php require __DIR__ . '/../partials/header.php'; ?>

    <div class="admin-layout">

        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="admin-main" id="dashboard">

            <!-- JUDUL HALAMAN -->

            <div class="admin-page-heading">
                <div>
                    <p class="eyebrow">Modul farmasi</p>

                    <h1>Data Obat</h1>

                    <p class="admin-intro">
                        Kelola data dan ketersediaan obat RS Medika Sehat.
                    </p>
                </div>

                <a
                    class="btn btn-primary"
                    href="tambah_obat.php">
                    + Tambah Obat
                </a>
            </div>

            <?php require __DIR__ . '/../partials/flash_message.php'; ?>

            <?php if ($errorMessage !== null): ?>

                <section class="admin-panel obat-alert" role="alert">
                    <?= obatEscape($errorMessage) ?>
                </section>

            <?php else: ?>

                <!-- STATISTIK -->

                <section class="obat-stats">

                    <article class="obat-stat-card">
                        <div class="obat-stat-icon blue">▤</div>

                        <div class="obat-stat-info">
                            <p>Total Jenis Obat</p>

                            <h2>
                                <?= number_format($totalMedicines, 0, ',', '.') ?>
                            </h2>

                            <small>Jenis obat terdaftar</small>
                        </div>
                    </article>

                    <article class="obat-stat-card">
                        <div class="obat-stat-icon orange">!</div>

                        <div class="obat-stat-info">
                            <p>Stok Menipis</p>

                            <h2>
                                <?= number_format($lowStock, 0, ',', '.') ?>
                            </h2>

                            <small>Perlu diperiksa</small>
                        </div>
                    </article>

                </section>

                <!-- DAFTAR OBAT -->

                <section class="admin-panel">

                    <div class="section-heading">
                        <div>
                            <h2>Daftar Obat</h2>

                            <p>
                                Informasi stok dan persediaan obat.
                            </p>
                        </div>
                    </div>

                    <!-- FORM PENCARIAN -->

                    <form
                        method="get"
                        action="data_obat.php"
                        class="obat-toolbar">

                        <div class="obat-search">
                            <span
                                class="obat-search-icon"
                                aria-hidden="true">⌕</span>

                            <input
                                type="search"
                                name="q"
                                placeholder="Cari nama, kode, atau kategori obat..."
                                value="<?= obatEscape($keyword) ?>"
                                aria-label="Cari obat">
                        </div>

                        <div class="obat-toolbar-actions">

                            <button
                                type="submit"
                                class="btn btn-primary">
                                Cari
                            </button>

                            <?php if ($keyword !== ''): ?>
                                <a
                                    href="data_obat.php"
                                    class="btn btn-secondary">
                                    Reset
                                </a>
                            <?php endif; ?>

                        </div>
                    </form>

                    <!-- TABEL OBAT -->

                    <div class="obat-table-wrap">

                        <table class="obat-table">

                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Kode Obat</th>
                                    <th>Nama Obat</th>
                                    <th>Kategori</th>
                                    <th>Satuan</th>
                                    <th>Stok</th>
                                    <th>Harga</th>
                                    <th>Kedaluwarsa</th>
                                    <th>Status Stok</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if ($medicines === []): ?>

                                    <tr class="obat-empty-row">
                                        <td colspan="10" class="obat-empty">

                                            <div class="obat-empty-icon">
                                                ▤
                                            </div>

                                            <strong>
                                                <?= $keyword !== ''
                                                    ? 'Obat tidak ditemukan'
                                                    : 'Belum ada data obat' ?>
                                            </strong>

                                            <p>
                                                <?= $keyword !== ''
                                                    ? 'Coba gunakan kata kunci yang berbeda.'
                                                    : 'Tambahkan data obat untuk mulai mengelola persediaan.' ?>
                                            </p>

                                        </td>
                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($medicines as $index => $medicine): ?>

                                        <?php
                                        $stock = (int) $medicine['stock'];
                                        $minimumStock = (int) $medicine['minimum_stock'];

                                        $isLow = $stock <= $minimumStock;

                                        $expiry = (string) (
                                            $medicine['expiry_date'] ?? ''
                                        );

                                        $expiryTimestamp = $expiry !== ''
                                            ? strtotime($expiry)
                                            : false;
                                        ?>

                                        <tr>

                                            <td class="obat-number">
                                                <?= $index + 1 ?>
                                            </td>

                                            <td>
                                                <span class="obat-code">
                                                    <?= obatEscape($medicine['code'] ?? '') ?>
                                                </span>
                                            </td>

                                            <td>
                                                <strong class="obat-name">
                                                    <?= obatEscape($medicine['name'] ?? '') ?>
                                                </strong>
                                            </td>

                                            <td>
                                                <?= obatEscape($medicine['category'] ?? '-') ?>
                                            </td>

                                            <td>
                                                <?= obatEscape($medicine['unit'] ?? '-') ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= number_format($stock, 0, ',', '.') ?>
                                                </strong>
                                            </td>

                                            <td class="obat-price">
                                                Rp <?= number_format(
                                                        (float) $medicine['price'],
                                                        0,
                                                        ',',
                                                        '.'
                                                    ) ?>
                                            </td>

                                            <td>
                                                <?= $expiryTimestamp !== false
                                                    ? date('d-m-Y', $expiryTimestamp)
                                                    : '-' ?>
                                            </td>

                                            <td>
                                                <span class="obat-status <?= $isLow
                                                                                ? 'low'
                                                                                : 'available' ?>">
                                                    <?= $isLow
                                                        ? 'Stok Menipis'
                                                        : 'Tersedia' ?>
                                                </span>
                                            </td>

                                            <td>
                                                <a
                                                    href="edit_obat.php?id=<?= (int) $medicine['id'] ?>"
                                                    class="btn btn-secondary btn-sm">
                                                    Edit
                                                </a>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>
                        </table>
                    </div>

                    <!-- FOOTER TABEL -->

                    <footer class="obat-table-footer">

                        <span>
                            Menampilkan <?= count($medicines) ?> data obat
                        </span>

                        <?php if ($keyword !== ''): ?>
                            <span>
                                Pencarian:
                                <strong><?= obatEscape($keyword) ?></strong>
                            </span>
                        <?php endif; ?>

                    </footer>

                </section>

            <?php endif; ?>

            <!-- FOOTER HALAMAN -->

            <footer class="page-footer">
                © <?= date('Y') ?> RS Medika Sehat.
                Sistem Informasi Rumah Sakit.
            </footer>

        </main>
    </div>

</body>

</html>