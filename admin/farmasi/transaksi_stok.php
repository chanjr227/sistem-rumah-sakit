<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

$connection = getDatabaseConnection();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$jenis = $_GET['jenis'] ?? 'masuk';

if (!in_array($jenis, ['masuk', 'keluar'], true)) {
    $jenis = 'masuk';
}

$isMasuk = $jenis === 'masuk';
$pageTitle = $isMasuk ? 'Stok Masuk' : 'Stok Keluar';

function stokEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_stok'])) {
    $_SESSION['csrf_stok'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_stok'];
$successMessage = '';
$errorMessage = '';

if (!empty($_SESSION['stok_flash'])) {
    $successMessage = (string) $_SESSION['stok_flash'];
    unset($_SESSION['stok_flash']);
}

/*
|--------------------------------------------------------------------------
| Proses transaksi dan pembaruan stok
|--------------------------------------------------------------------------
*/
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $medicineId = filter_input(
        INPUT_POST,
        'medicine_id',
        FILTER_VALIDATE_INT
    );

    $quantity = filter_input(
        INPUT_POST,
        'quantity',
        FILTER_VALIDATE_INT
    );

    $notes = trim((string) ($_POST['notes'] ?? ''));
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    try {
        if (!hash_equals($csrfToken, $postedToken)) {
            throw new RuntimeException(
                'Sesi formulir tidak valid. Silakan muat ulang halaman.'
            );
        }

        if (!$medicineId || $medicineId < 1) {
            throw new RuntimeException('Pilih obat terlebih dahulu.');
        }

        if ($quantity === false || $quantity === null || $quantity < 1) {
            throw new RuntimeException(
                'Jumlah transaksi minimal 1.'
            );
        }

        if (mb_strlen($notes) > 255) {
            throw new RuntimeException(
                'Keterangan maksimal 255 karakter.'
            );
        }

        $connection->beginTransaction();

        // Kunci baris obat untuk mencegah perubahan stok bersamaan.
        $medicineQuery = $connection->prepare(
            'SELECT id, name, stock
             FROM medicines
             WHERE id = :id
             FOR UPDATE'
        );

        $medicineQuery->execute(['id' => $medicineId]);
        $medicine = $medicineQuery->fetch(PDO::FETCH_ASSOC);

        if (!$medicine) {
            throw new RuntimeException('Data obat tidak ditemukan.');
        }

        $currentStock = (int) $medicine['stock'];

        if (!$isMasuk && $quantity > $currentStock) {
            throw new RuntimeException(
                'Stok ' . $medicine['name'] .
                    ' tidak mencukupi. Stok tersedia: ' .
                    $currentStock . '.'
            );
        }

        $newStock = $isMasuk
            ? $currentStock + $quantity
            : $currentStock - $quantity;

        if ($newStock > 2147483647) {
            throw new RuntimeException('Jumlah stok terlalu besar.');
        }

        $updateQuery = $connection->prepare(
            'UPDATE medicines
             SET stock = :stock
             WHERE id = :id'
        );

        $updateQuery->execute([
            'stock' => $newStock,
            'id' => $medicineId,
        ]);

        $insertQuery = $connection->prepare(
            'INSERT INTO medicine_transactions
                (medicine_id, transaction_type, quantity, notes)
             VALUES
                (:medicine_id, :transaction_type, :quantity, :notes)'
        );

        $insertQuery->execute([
            'medicine_id' => $medicineId,
            'transaction_type' => $jenis,
            'quantity' => $quantity,
            'notes' => $notes !== '' ? $notes : null,
        ]);

        $connection->commit();

        $_SESSION['stok_flash'] =
            'Transaksi ' . strtolower($pageTitle) .
            ' berhasil disimpan. Stok terbaru: ' . $newStock . '.';

        header('Location: transaksi_stok.php?jenis=' . $jenis);
        exit;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        $errorMessage = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'Transaksi gagal disimpan. Periksa koneksi dan struktur database.';

        if (!$exception instanceof RuntimeException) {
            error_log($exception->getMessage());
        }
    }
}

/*
|--------------------------------------------------------------------------
| Ambil daftar obat
|--------------------------------------------------------------------------
*/
$medicines = $connection->query(
    'SELECT id, code, name, unit, stock
     FROM medicines
     ORDER BY name ASC'
)->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Ambil 15 transaksi terakhir untuk jenis yang dipilih
|--------------------------------------------------------------------------
*/
$historyQuery = $connection->prepare(
    'SELECT
        t.id,
        t.transaction_type,
        t.quantity,
        t.notes,
        t.transaction_date,
        m.code,
        m.name,
        m.unit
     FROM medicine_transactions t
     INNER JOIN medicines m ON m.id = t.medicine_id
     WHERE t.transaction_type = :jenis
     ORDER BY t.transaction_date DESC, t.id DESC
     LIMIT 15'
);

$historyQuery->execute(['jenis' => $jenis]);
$transactions = $historyQuery->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= stokEscape($pageTitle) ?> | RS Medika Sehat</title>

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">

    <style>
        .stok-page {
            max-width: 1200px;
            margin: 0 auto;
        }

        .stok-panel {
            background: var(--panel, #fff);
            border: 1px solid var(--border, #e5e7eb);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .stok-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .stok-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .stok-field label {
            font-weight: 600;
        }

        .stok-field input,
        .stok-field select,
        .stok-field textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font: inherit;
            background: var(--input-bg, #fff);
            color: var(--text, #111827);
        }

        .stok-field textarea {
            min-height: 85px;
            resize: vertical;
        }

        .stok-full {
            grid-column: 1 / -1;
        }

        .stok-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .stok-notice {
            padding: 13px 16px;
            border-radius: 9px;
            margin-bottom: 18px;
        }

        .stok-success {
            background: #dcfce7;
            color: #166534;
        }

        .stok-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .stok-table-wrap {
            overflow-x: auto;
        }

        .stok-table {
            width: 100%;
            border-collapse: collapse;
        }

        .stok-table th,
        .stok-table td {
            padding: 13px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border, #e5e7eb);
            white-space: nowrap;
        }

        .stok-table th {
            font-size: 13px;
        }

        .stok-badge {
            display: inline-block;
            border-radius: 20px;
            padding: 5px 10px;
            background: <?= $isMasuk ? '#dcfce7' : '#fee2e2' ?>;
            color: <?= $isMasuk ? '#166534' : '#991b1b' ?>;
            font-size: 12px;
            font-weight: 700;
        }

        .stok-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .stok-tabs a {
            padding: 10px 16px;
            border: 1px solid var(--border, #d1d5db);
            border-radius: 8px;
            text-decoration: none;
        }

        .stok-tabs a.active {
            background: #0f766e;
            color: white;
            border-color: #0f766e;
        }

        .stok-muted {
            color: var(--muted, #6b7280);
            font-size: 13px;
        }

        @media (max-width: 700px) {
            .stok-form {
                grid-template-columns: 1fr;
            }

            .stok-full {
                grid-column: auto;
            }

            .stok-panel {
                padding: 16px;
            }
        }
    </style>
</head>

<body class="admin-page">

    <?php require __DIR__ . '/../partials/header.php'; ?>

    <div class="admin-layout">
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="admin-main">
            <div class="stok-page">

                <div class="admin-page-heading">
                    <div>
                        <h1><?= stokEscape($pageTitle) ?></h1>
                        <p class="admin-intro">
                            <?= $isMasuk
                                ? 'Catat penerimaan obat dan tambahkan stok secara otomatis.'
                                : 'Catat pengeluaran obat dan kurangi stok secara otomatis.' ?>
                        </p>
                    </div>
                </div>

                <nav class="stok-tabs" aria-label="Menu transaksi stok">
                    <a href="transaksi_stok.php?jenis=masuk"
                        class="<?= $isMasuk ? 'active' : '' ?>">
                        + Stok Masuk
                    </a>
                    <a href="transaksi_stok.php?jenis=keluar"
                        class="<?= !$isMasuk ? 'active' : '' ?>">
                        − Stok Keluar
                    </a>
                </nav>

                <?php if ($successMessage !== ''): ?>
                    <div class="stok-notice stok-success">
                        <?= stokEscape($successMessage) ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage !== ''): ?>
                    <div class="stok-notice stok-error">
                        <?= stokEscape($errorMessage) ?>
                    </div>
                <?php endif; ?>

                <section class="stok-panel">
                    <div class="section-heading">
                        <h2>Form <?= stokEscape($pageTitle) ?></h2>
                    </div>

                    <?php if (count($medicines) === 0): ?>
                        <p>Belum ada data obat. Tambahkan obat terlebih dahulu.</p>
                        <a class="btn" href="tambah_obat.php">Tambah Obat</a>
                    <?php else: ?>

                        <form method="POST" class="stok-form">
                            <input type="hidden" name="csrf_token"
                                value="<?= stokEscape($csrfToken) ?>">

                            <div class="stok-field stok-full">
                                <label for="medicine_id">Pilih Obat</label>
                                <select id="medicine_id" name="medicine_id" required>
                                    <option value="">-- Pilih obat --</option>
                                    <?php foreach ($medicines as $medicine): ?>
                                        <option
                                            value="<?= (int) $medicine['id'] ?>"
                                            <?= (string) ($_POST['medicine_id'] ?? '') ===
                                                (string) $medicine['id'] ? 'selected' : '' ?>>
                                            <?= stokEscape($medicine['code']) ?> —
                                            <?= stokEscape($medicine['name']) ?>
                                            (Stok: <?= (int) $medicine['stock'] ?>
                                            <?= stokEscape($medicine['unit']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="stok-field">
                                <label for="quantity">Jumlah <?= $isMasuk ? 'Masuk' : 'Keluar' ?></label>
                                <input
                                    type="number"
                                    id="quantity"
                                    name="quantity"
                                    min="1"
                                    step="1"
                                    required
                                    value="<?= stokEscape($_POST['quantity'] ?? '') ?>"
                                    placeholder="Masukkan jumlah">
                            </div>

                            <div class="stok-field">
                                <label for="notes">Keterangan</label>
                                <input
                                    type="text"
                                    id="notes"
                                    name="notes"
                                    maxlength="255"
                                    value="<?= stokEscape($_POST['notes'] ?? '') ?>"
                                    placeholder="<?= $isMasuk
                                                        ? 'Contoh: penerimaan dari pemasok'
                                                        : 'Contoh: obat untuk pelayanan pasien' ?>">
                            </div>

                            <div class="stok-full stok-actions">
                                <button type="submit" class="btn">
                                    Simpan <?= stokEscape($pageTitle) ?>
                                </button>
                                <a href="data_obat.php" class="btn btn-secondary">
                                    Kembali ke Data Obat
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="stok-panel">
                    <div class="section-heading">
                        <h2>Riwayat <?= stokEscape($pageTitle) ?></h2>
                        <p class="stok-muted">Menampilkan maksimal 15 transaksi terbaru.</p>
                    </div>

                    <div class="stok-table-wrap">
                        <table class="stok-table">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Tanggal</th>
                                    <th>Kode Obat</th>
                                    <th>Nama Obat</th>
                                    <th>Jumlah</th>
                                    <th>Jenis</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$transactions): ?>
                                    <tr>
                                        <td colspan="7" style="text-align:center;padding:24px">
                                            Belum ada transaksi <?= stokEscape(strtolower($pageTitle)) ?>.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $index => $transaction): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= stokEscape(date(
                                                    'd-m-Y H:i',
                                                    strtotime($transaction['transaction_date'])
                                                )) ?></td>
                                            <td><?= stokEscape($transaction['code']) ?></td>
                                            <td><?= stokEscape($transaction['name']) ?></td>
                                            <td>
                                                <?= (int) $transaction['quantity'] ?>
                                                <?= stokEscape($transaction['unit']) ?>
                                            </td>
                                            <td>
                                                <span class="stok-badge">
                                                    <?= $isMasuk ? 'Masuk' : 'Keluar' ?>
                                                </span>
                                            </td>
                                            <td><?= stokEscape($transaction['notes'] ?: '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <script src="../../js/pages/admin.js" defer></script>
</body>

</html>