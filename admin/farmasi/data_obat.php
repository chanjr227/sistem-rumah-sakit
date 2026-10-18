<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

function dataObatEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$search = trim((string) ($_GET['search'] ?? ''));
$stockFilter = (string) ($_GET['stock'] ?? '');

$allowedStockFilters = ['normal', 'low', 'empty'];

if (!in_array($stockFilter, $allowedStockFilters, true)) {
    $stockFilter = '';
}

$connection = getDatabaseConnection();

$medicines = [];
$totalMedicines = 0;
$totalLowStock = 0;
$totalEmptyStock = 0;
$errorMessage = null;

try {
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(name LIKE :search_name
                     OR code LIKE :search_code
                     OR category LIKE :search_category)';
        $params['search_name'] = '%' . $search . '%';
        $params['search_code'] = '%' . $search . '%';
        $params['search_category'] = '%' . $search . '%';
    }

    if ($stockFilter === 'low') {
        $where[] = 'stock > 0 AND stock <= minimum_stock';
    } elseif ($stockFilter === 'empty') {
        $where[] = 'stock = 0';
    } elseif ($stockFilter === 'normal') {
        $where[] = 'stock > minimum_stock';
    }

    $sql = '
        SELECT
            id,
            code,
            name,
            category,
            unit,
            stock,
            minimum_stock,
            price,
            expiry_date
        FROM medicines
    ';

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY name ASC';

    $statement = $connection->prepare($sql);
    $statement->execute($params);
    $medicines = $statement->fetchAll(PDO::FETCH_ASSOC);

    $totalMedicines = (int) $connection->query(
        'SELECT COUNT(*) FROM medicines'
    )->fetchColumn();

    $totalLowStock = (int) $connection->query(
        'SELECT COUNT(*) FROM medicines
         WHERE stock > 0 AND stock <= minimum_stock'
    )->fetchColumn();

    $totalEmptyStock = (int) $connection->query(
        'SELECT COUNT(*) FROM medicines WHERE stock = 0'
    )->fetchColumn();
} catch (PDOException $exception) {
    error_log('Load medicines error: ' . $exception->getMessage());
    $errorMessage = 'Data obat gagal dimuat. Periksa struktur tabel medicines.';
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Obat - RS Medika Sehat</title>

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">
    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .obat-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .obat-stat {
            padding: 20px;
            background: var(--panel, #fff);
            border: 1px solid var(--border, #e5e7eb);
            border-radius: 12px;
        }

        .obat-stat-label {
            color: #667085;
            font-size: 13px;
        }

        .obat-stat-number {
            display: block;
            margin-top: 8px;
            font-size: 28px;
            font-weight: 800;
            color: #17212f;
        }

        .obat-toolbar {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .obat-filters {
            display: flex;
            flex: 1;
            flex-wrap: wrap;
            gap: 10px;
        }

        .obat-filters input,
        .obat-filters select {
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid var(--border, #d9e1e8);
            border-radius: 8px;
            font: inherit;
        }

        .obat-filters input {
            flex: 1;
            min-width: 200px;
        }

        .obat-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .obat-table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse;
        }

        .obat-table th,
        .obat-table td {
            padding: 13px 12px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid #e7edf2;
        }

        .obat-table th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #667085;
            background: #f8fafc;
        }

        .obat-name {
            font-weight: 700;
            color: #17212f;
        }

        .obat-muted {
            margin-top: 4px;
            font-size: 12px;
            color: #697586;
        }

        .obat-badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .obat-normal {
            color: #18794e;
            background: #e7f8ef;
        }

        .obat-low {
            color: #b54708;
            background: #fff0e7;
        }

        .obat-empty-stock {
            color: #b42318;
            background: #fee4e2;
        }

        .obat-empty {
            padding: 35px 16px !important;
            text-align: center !important;
            color: #667085;
        }

        .obat-action {
            white-space: nowrap;
        }

        .obat-action .btn {
            padding: 8px 12px;
        }

        .obat-note {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 9px;
            background: #eff8ff;
            color: #175cd3;
            font-size: 13px;
            line-height: 1.6;
        }

        .obat-error {
            padding: 16px;
            border-radius: 9px;
            background: #fee4e2;
            color: #b42318;
        }

        @media (max-width: 700px) {
            .obat-stats {
                grid-template-columns: 1fr;
            }

            .obat-filters {
                width: 100%;
            }

            .obat-filters input,
            .obat-filters select {
                width: 100%;
                min-width: 0;
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
                    <h1>Data Obat</h1>
                    <p class="admin-intro">
                        Kelola identitas obat dan pantau persediaan farmasi.
                    </p>
                </div>

                <a class="btn btn-primary" href="tambah_obat.php">
                    + Tambah Obat
                </a>
            </div>

            <?php require __DIR__ . '/../partials/flash_message.php'; ?>

            <?php if ($errorMessage !== null): ?>
                <section class="admin-panel obat-error" role="alert">
                    <?= dataObatEscape($errorMessage) ?>
                </section>
            <?php else: ?>

                <div class="obat-stats">
                    <div class="obat-stat">
                        <span class="obat-stat-label">Total jenis obat</span>
                        <strong class="obat-stat-number">
                            <?= number_format($totalMedicines) ?>
                        </strong>
                    </div>

                    <div class="obat-stat">
                        <span class="obat-stat-label">Stok menipis</span>
                        <strong class="obat-stat-number">
                            <?= number_format($totalLowStock) ?>
                        </strong>
                    </div>

                    <div class="obat-stat">
                        <span class="obat-stat-label">Stok habis</span>
                        <strong class="obat-stat-number">
                            <?= number_format($totalEmptyStock) ?>
                        </strong>
                    </div>
                </div>

                <section class="admin-panel">

                    <div class="obat-toolbar">
                        <form class="obat-filters" method="get"
                            action="data_obat.php">

                            <input
                                type="search"
                                name="search"
                                placeholder="Cari kode, nama, atau kategori obat..."
                                value="<?= dataObatEscape($search) ?>">

                            <select name="stock" aria-label="Filter kondisi stok">
                                <option value="">Semua kondisi stok</option>
                                <option value="normal"
                                    <?= $stockFilter === 'normal' ? 'selected' : '' ?>>
                                    Stok normal
                                </option>
                                <option value="low"
                                    <?= $stockFilter === 'low' ? 'selected' : '' ?>>
                                    Stok menipis
                                </option>
                                <option value="empty"
                                    <?= $stockFilter === 'empty' ? 'selected' : '' ?>>
                                    Stok habis
                                </option>
                            </select>

                            <button class="btn btn-secondary" type="submit">
                                Cari
                            </button>

                            <a class="btn btn-secondary" href="data_obat.php">
                                Reset
                            </a>
                        </form>
                    </div>

                    <div class="obat-table-wrap">
                        <table class="obat-table">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Kode Obat</th>
                                    <th>Nama Obat</th>
                                    <th>Kategori</th>
                                    <th>Satuan</th>
                                    <th>Stok Terkini</th>
                                    <th>Harga</th>
                                    <th>Kedaluwarsa</th>
                                    <th>Status Stok</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if ($medicines === []): ?>
                                    <tr>
                                        <td colspan="10" class="obat-empty">
                                            Tidak ada data obat yang sesuai.
                                        </td>
                                    </tr>
                                <?php else: ?>

                                    <?php foreach ($medicines as $index => $medicine): ?>
                                        <?php
                                        $stock = (int) $medicine['stock'];
                                        $minimumStock = (int) $medicine['minimum_stock'];

                                        if ($stock === 0) {
                                            $stockLabel = 'Stok habis';
                                            $stockClass = 'obat-empty-stock';
                                        } elseif ($stock <= $minimumStock) {
                                            $stockLabel = 'Stok menipis';
                                            $stockClass = 'obat-low';
                                        } else {
                                            $stockLabel = 'Normal';
                                            $stockClass = 'obat-normal';
                                        }

                                        $expiryDate = $medicine['expiry_date'] ?? '';
                                        $expiryLabel = '-';

                                        if ($expiryDate !== '' && $expiryDate !== null) {
                                            $expiryTimestamp = strtotime((string) $expiryDate);
                                            $expiryLabel = $expiryTimestamp !== false
                                                ? date('d-m-Y', $expiryTimestamp)
                                                : (string) $expiryDate;
                                        }
                                        ?>

                                        <tr>
                                            <td><?= $index + 1 ?></td>

                                            <td><?= dataObatEscape($medicine['code']) ?></td>

                                            <td>
                                                <div class="obat-name">
                                                    <?= dataObatEscape($medicine['name']) ?>
                                                </div>
                                            </td>

                                            <td><?= dataObatEscape($medicine['category']) ?></td>

                                            <td><?= dataObatEscape($medicine['unit']) ?></td>

                                            <td>
                                                <strong><?= number_format($stock) ?></strong>
                                                <div class="obat-muted">
                                                    Minimum: <?= number_format($minimumStock) ?>
                                                </div>
                                            </td>

                                            <td>
                                                Rp <?= number_format(
                                                        (float) $medicine['price'],
                                                        0,
                                                        ',',
                                                        '.'
                                                    ) ?>
                                            </td>

                                            <td><?= dataObatEscape($expiryLabel) ?></td>

                                            <td>
                                                <span class="obat-badge <?= $stockClass ?>">
                                                    <?= dataObatEscape($stockLabel) ?>
                                                </span>
                                            </td>

                                            <td class="obat-action">
                                                <a
                                                    class="btn btn-secondary"
                                                    href="edit_obat.php?id=<?= (int) $medicine['id'] ?>">
                                                    Edit
                                                </a>
                                            </td>
                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="obat-note">
                        <strong>Informasi:</strong>
                        stok pada halaman ini hanya menampilkan jumlah terkini.
                        Gunakan menu <strong>Stok Masuk</strong> untuk menambah stok
                        dan <strong>Stok Keluar</strong> untuk mengurangi stok agar
                        setiap perubahan tercatat dalam riwayat transaksi.
                    </div>

                </section>

            <?php endif; ?>

        </main>
    </div>

</body>

</html>