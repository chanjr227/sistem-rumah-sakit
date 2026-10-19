<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function kedaluwarsaEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$today = new DateTimeImmutable('today');
$warningLimit = $today->modify('+30 days');

$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? 'semua');

$allowedStatuses = [
    'semua',
    'kedaluwarsa',
    'segera',
    'aman',
    'belum_ada',
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'semua';
}

$medicines = [];
$filteredMedicines = [];
$errors = [];

$summary = [
    'total' => 0,
    'kedaluwarsa' => 0,
    'segera' => 0,
    'aman' => 0,
    'belum_ada' => 0,
];

try {
    $connection = getDatabaseConnection();

    $sql = 'SELECT
                id,
                code,
                name,
                category,
                unit,
                stock,
                minimum_stock,
                expiry_date
            FROM medicines';

    $params = [];

    if ($search !== '') {
        $sql .= ' WHERE (
                    code LIKE :search_code
                    OR name LIKE :search_name
                    OR category LIKE :search_category
                  )';

        $keyword = '%' . $search . '%';

        $params = [
            'search_code' => $keyword,
            'search_name' => $keyword,
            'search_category' => $keyword,
        ];
    }

    $sql .= ' ORDER BY
                CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END ASC,
                expiry_date ASC,
                name ASC';

    $statement = $connection->prepare($sql);
    $statement->execute($params);

    $medicines = $statement->fetchAll(PDO::FETCH_ASSOC);

    foreach ($medicines as $medicine) {
        $expiryDate = null;
        $daysRemaining = null;

        if (empty($medicine['expiry_date'])) {
            $medicineStatus = 'belum_ada';
            $statusLabel = 'Tanggal belum diisi';
        } else {
            $expiryDate = new DateTimeImmutable(
                (string) $medicine['expiry_date']
            );

            $daysRemaining = (int) $today->diff($expiryDate)->format('%r%a');

            if ($expiryDate < $today) {
                $medicineStatus = 'kedaluwarsa';
                $statusLabel = 'Kedaluwarsa';
            } elseif ($expiryDate <= $warningLimit) {
                $medicineStatus = 'segera';
                $statusLabel = 'Segera kedaluwarsa';
            } else {
                $medicineStatus = 'aman';
                $statusLabel = 'Masih aman';
            }
        }

        $summary['total']++;
        $summary[$medicineStatus]++;

        if (
            $statusFilter !== 'semua'
            && $statusFilter !== $medicineStatus
        ) {
            continue;
        }

        $medicine['expiry_status'] = $medicineStatus;
        $medicine['status_label'] = $statusLabel;
        $medicine['days_remaining'] = $daysRemaining;

        $filteredMedicines[] = $medicine;
    }
} catch (Throwable $exception) {
    error_log(
        'Obat kedaluwarsa error: ' . $exception->getMessage()
    );

    http_response_code(500);

    $errors[] = 'Data obat gagal dimuat. Periksa koneksi database dan struktur tabel medicines.';
}

function kedaluwarsaDateLabel(?string $date): string
{
    if (empty($date)) {
        return '-';
    }

    try {
        return (new DateTimeImmutable($date))->format('d-m-Y');
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

    <title>Obat Kedaluwarsa - RS Medika Sehat</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">
    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .kedaluwarsa-content {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .kedaluwarsa-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .kedaluwarsa-card {
            padding: 20px;
            border: 1px solid #e4eaf0;
            border-radius: 14px;
            background: #fff;
            min-width: 0;
        }

        .kedaluwarsa-card-label {
            margin: 0 0 12px;
            color: #667085;
            font-size: 13px;
            font-weight: 600;
        }

        .kedaluwarsa-card-value {
            margin: 0;
            color: #17212f;
            font-size: 29px;
            font-weight: 800;
            line-height: 1.2;
        }

        .kedaluwarsa-card-danger {
            border-top: 3px solid #dc4444;
        }

        .kedaluwarsa-card-warning {
            border-top: 3px solid #e6a23c;
        }

        .kedaluwarsa-card-safe {
            border-top: 3px solid #20a477;
        }

        .kedaluwarsa-card-muted {
            border-top: 3px solid #98a2b3;
        }

        .kedaluwarsa-notice {
            padding: 15px 18px;
            border: 1px solid #b9e5f5;
            border-radius: 11px;
            background: #effaff;
            color: #185d78;
            font-size: 13px;
            line-height: 1.7;
        }

        .kedaluwarsa-errors {
            padding: 16px 20px;
            border: 1px solid #f3c7c7;
            border-radius: 10px;
            background: #fff1f1;
            color: #a4262c;
        }

        .kedaluwarsa-toolbar {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .kedaluwarsa-filter {
            display: grid;
            grid-template-columns: minmax(200px, 1fr) 210px auto;
            gap: 12px;
            width: 100%;
        }

        .kedaluwarsa-filter input,
        .kedaluwarsa-filter select {
            box-sizing: border-box;
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #d0d5dd;
            border-radius: 9px;
            background: #fff;
            color: #17212f;
            font: inherit;
            font-size: 13px;
        }

        .kedaluwarsa-filter input:focus,
        .kedaluwarsa-filter select:focus {
            outline: none;
            border-color: #1769aa;
            box-shadow: 0 0 0 3px rgba(23, 105, 170, .12);
        }

        .kedaluwarsa-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .kedaluwarsa-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        .kedaluwarsa-table th {
            padding: 14px 16px;
            background: #f8fafc;
            color: #667085;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .5px;
            text-align: left;
            text-transform: uppercase;
            border-bottom: 1px solid #e4eaf0;
            white-space: nowrap;
        }

        .kedaluwarsa-table td {
            padding: 15px 16px;
            color: #344054;
            font-size: 13px;
            border-bottom: 1px solid #edf0f4;
            vertical-align: middle;
        }

        .kedaluwarsa-table tbody tr:hover {
            background: #fafcff;
        }

        .kedaluwarsa-obat-name {
            color: #17212f;
            font-weight: 700;
        }

        .kedaluwarsa-obat-code {
            display: block;
            margin-top: 5px;
            color: #98a2b3;
            font-size: 11px;
        }

        .kedaluwarsa-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .kedaluwarsa-badge-danger {
            background: #fff0f0;
            color: #bd3030;
        }

        .kedaluwarsa-badge-warning {
            background: #fff6e5;
            color: #9a6100;
        }

        .kedaluwarsa-badge-safe {
            background: #e8f8f0;
            color: #16794e;
        }

        .kedaluwarsa-badge-muted {
            background: #eef1f5;
            color: #596579;
        }

        .kedaluwarsa-empty {
            padding: 44px 20px !important;
            text-align: center;
            color: #667085 !important;
        }

        .kedaluwarsa-empty-icon {
            display: block;
            margin-bottom: 12px;
            font-size: 28px;
        }

        .kedaluwarsa-result {
            color: #667085;
            font-size: 12px;
        }

        .kedaluwarsa-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        @media (max-width: 1000px) {
            .kedaluwarsa-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .kedaluwarsa-filter {
                grid-template-columns: 1fr 1fr;
            }

            .kedaluwarsa-filter .kedaluwarsa-search {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 600px) {
            .kedaluwarsa-summary {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .kedaluwarsa-card {
                padding: 15px;
            }

            .kedaluwarsa-card-value {
                font-size: 24px;
            }

            .kedaluwarsa-filter {
                grid-template-columns: 1fr;
            }

            .kedaluwarsa-filter .kedaluwarsa-search {
                grid-column: auto;
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
                    <h1>Obat Kedaluwarsa</h1>
                    <p class="admin-intro">
                        Pantau tanggal kedaluwarsa dan tindak lanjuti obat yang perlu diperiksa.
                    </p>
                </div>

                <a href="data_obat.php" class="btn btn-secondary">
                    Kembali ke Data Obat
                </a>
            </div>

            <div class="kedaluwarsa-content">

                <?php if ($errors !== []): ?>
                    <section class="kedaluwarsa-errors" role="alert">
                        <strong>Terjadi kesalahan.</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= kedaluwarsaEscape($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php else: ?>

                    <section class="kedaluwarsa-summary">

                        <article class="kedaluwarsa-card kedaluwarsa-card-danger">
                            <p class="kedaluwarsa-card-label">
                                Sudah Kedaluwarsa
                            </p>
                            <p class="kedaluwarsa-card-value">
                                <?= number_format($summary['kedaluwarsa']) ?>
                            </p>
                        </article>

                        <article class="kedaluwarsa-card kedaluwarsa-card-warning">
                            <p class="kedaluwarsa-card-label">
                                Segera Kedaluwarsa
                            </p>
                            <p class="kedaluwarsa-card-value">
                                <?= number_format($summary['segera']) ?>
                            </p>
                        </article>

                        <article class="kedaluwarsa-card kedaluwarsa-card-safe">
                            <p class="kedaluwarsa-card-label">
                                Masih Aman
                            </p>
                            <p class="kedaluwarsa-card-value">
                                <?= number_format($summary['aman']) ?>
                            </p>
                        </article>

                        <article class="kedaluwarsa-card kedaluwarsa-card-muted">
                            <p class="kedaluwarsa-card-label">
                                Tanggal Belum Diisi
                            </p>
                            <p class="kedaluwarsa-card-value">
                                <?= number_format($summary['belum_ada']) ?>
                            </p>
                        </article>

                    </section>

                    <div class="kedaluwarsa-notice">
                        <strong>Informasi:</strong>
                        Obat yang sudah kedaluwarsa perlu dipisahkan dan ditindaklanjuti sesuai prosedur farmasi.
                        Halaman ini tidak mengubah jumlah stok secara otomatis.
                        Peringatan segera kedaluwarsa menggunakan rentang 30 hari ke depan.
                    </div>

                    <section class="admin-panel">

                        <div class="section-heading">
                            <div>
                                <h2>Daftar Pemantauan Obat</h2>
                                <p>
                                    Cari obat dan filter berdasarkan status kedaluwarsa.
                                </p>
                            </div>
                        </div>

                        <div class="kedaluwarsa-toolbar">

                            <form method="get"
                                action="obat_kedaluwarsa.php"
                                class="kedaluwarsa-filter">

                                <div class="kedaluwarsa-search">
                                    <input
                                        type="search"
                                        name="search"
                                        placeholder="Cari kode, nama, atau kategori obat..."
                                        value="<?= kedaluwarsaEscape($search) ?>">
                                </div>

                                <div>
                                    <select name="status" aria-label="Filter status kedaluwarsa">
                                        <option value="semua"
                                            <?= $statusFilter === 'semua' ? 'selected' : '' ?>>
                                            Semua Status
                                        </option>

                                        <option value="kedaluwarsa"
                                            <?= $statusFilter === 'kedaluwarsa' ? 'selected' : '' ?>>
                                            Sudah Kedaluwarsa
                                        </option>

                                        <option value="segera"
                                            <?= $statusFilter === 'segera' ? 'selected' : '' ?>>
                                            Segera Kedaluwarsa
                                        </option>

                                        <option value="aman"
                                            <?= $statusFilter === 'aman' ? 'selected' : '' ?>>
                                            Masih Aman
                                        </option>

                                        <option value="belum_ada"
                                            <?= $statusFilter === 'belum_ada' ? 'selected' : '' ?>>
                                            Tanggal Belum Diisi
                                        </option>
                                    </select>
                                </div>

                                <div class="kedaluwarsa-actions">
                                    <button type="submit" class="btn btn-primary">
                                        Terapkan
                                    </button>

                                    <a href="obat_kedaluwarsa.php"
                                        class="btn btn-secondary">
                                        Reset
                                    </a>
                                </div>

                            </form>

                            <p class="kedaluwarsa-result">
                                Menampilkan
                                <strong><?= number_format(count($filteredMedicines)) ?></strong>
                                dari
                                <strong><?= number_format(count($medicines)) ?></strong>
                                data hasil pencarian.
                            </p>

                        </div>

                        <div class="kedaluwarsa-table-wrap">
                            <table class="kedaluwarsa-table">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Informasi Obat</th>
                                        <th>Kategori</th>
                                        <th>Stok</th>
                                        <th>Tanggal Kedaluwarsa</th>
                                        <th>Sisa Waktu</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php if ($filteredMedicines === []): ?>
                                        <tr>
                                            <td colspan="7" class="kedaluwarsa-empty">
                                                <span class="kedaluwarsa-empty-icon">📦</span>
                                                <strong>Data obat tidak ditemukan</strong>
                                                <p>
                                                    Coba ubah kata kunci atau filter status.
                                                </p>
                                            </td>
                                        </tr>
                                    <?php else: ?>

                                        <?php foreach ($filteredMedicines as $index => $medicine): ?>

                                            <?php
                                            $badgeClass = match ($medicine['expiry_status']) {
                                                'kedaluwarsa' => 'kedaluwarsa-badge-danger',
                                                'segera' => 'kedaluwarsa-badge-warning',
                                                'aman' => 'kedaluwarsa-badge-safe',
                                                default => 'kedaluwarsa-badge-muted',
                                            };

                                            if ($medicine['expiry_status'] === 'kedaluwarsa') {
                                                $remainingLabel = abs((int) $medicine['days_remaining'])
                                                    . ' hari terlewat';
                                            } elseif ($medicine['expiry_status'] === 'segera') {
                                                $remainingLabel = (int) $medicine['days_remaining'] === 0
                                                    ? 'Kedaluwarsa hari ini'
                                                    : (int) $medicine['days_remaining'] . ' hari lagi';
                                            } elseif ($medicine['expiry_status'] === 'aman') {
                                                $remainingLabel = (int) $medicine['days_remaining']
                                                    . ' hari lagi';
                                            } else {
                                                $remainingLabel = '-';
                                            }
                                            ?>

                                            <tr>
                                                <td><?= $index + 1 ?></td>

                                                <td>
                                                    <span class="kedaluwarsa-obat-name">
                                                        <?= kedaluwarsaEscape($medicine['name']) ?>
                                                    </span>

                                                    <span class="kedaluwarsa-obat-code">
                                                        <?= kedaluwarsaEscape($medicine['code']) ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <?= kedaluwarsaEscape($medicine['category']) ?>
                                                </td>

                                                <td>
                                                    <strong>
                                                        <?= number_format((int) $medicine['stock']) ?>
                                                    </strong>
                                                    <?= kedaluwarsaEscape($medicine['unit']) ?>
                                                </td>

                                                <td>
                                                    <?= kedaluwarsaDateLabel(
                                                        $medicine['expiry_date'] !== null
                                                            ? (string) $medicine['expiry_date']
                                                            : null
                                                    ) ?>
                                                </td>

                                                <td>
                                                    <?= kedaluwarsaEscape($remainingLabel) ?>
                                                </td>

                                                <td>
                                                    <span class="kedaluwarsa-badge <?= $badgeClass ?>">
                                                        <?= kedaluwarsaEscape($medicine['status_label']) ?>
                                                    </span>
                                                </td>
                                            </tr>

                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                </tbody>
                            </table>
                        </div>

                    </section>

                <?php endif; ?>

                <footer class="page-footer">
                    © <?= date('Y') ?> RS Medika Sehat. Sistem Informasi Rumah Sakit.
                </footer>

            </div>

        </main>
    </div>

</body>

</html>