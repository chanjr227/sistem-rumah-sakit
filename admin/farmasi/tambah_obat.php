<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function tambahObatEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$successMessage = null;

$form = [
    'code' => '',
    'name' => '',
    'category' => '',
    'unit' => '',
    'stock' => '0',
    'minimum_stock' => '10',
    'price' => '',
    'expiry_date' => '',
];

if (empty($_SESSION['csrf_tambah_obat'])) {
    $_SESSION['csrf_tambah_obat'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($form as $field => $default) {
        $form[$field] = trim((string) ($_POST[$field] ?? $default));
    }

    $csrfToken = (string) ($_POST['_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_tambah_obat'], $csrfToken)) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    }

    if ($form['code'] === '') {
        $errors[] = 'Kode obat wajib diisi.';
    } elseif (strlen($form['code']) > 50) {
        $errors[] = 'Kode obat maksimal 50 karakter.';
    }

    if ($form['name'] === '') {
        $errors[] = 'Nama obat wajib diisi.';
    } elseif (strlen($form['name']) > 150) {
        $errors[] = 'Nama obat maksimal 150 karakter.';
    }

    if ($form['category'] === '') {
        $errors[] = 'Kategori obat wajib dipilih.';
    }

    if ($form['unit'] === '') {
        $errors[] = 'Satuan obat wajib dipilih.';
    }

    foreach (['stock' => 'Stok awal', 'minimum_stock' => 'Batas minimum stok'] as $field => $label) {
        if (
            $form[$field] === ''
            || !ctype_digit($form[$field])
            || (float) $form[$field] > 2147483647
        ) {
            $errors[] = $label . ' harus berupa bilangan bulat nonnegatif.';
        }
    }

    $priceInput = str_replace(',', '.', $form['price']);

    if (
        $form['price'] === ''
        || !is_numeric($priceInput)
        || !is_finite((float) $priceInput)
        || (float) $priceInput < 0
        || (float) $priceInput > 999999999999.99
    ) {
        $errors[] = 'Harga harus berupa angka yang valid dan tidak negatif.';
    }

    if ($form['expiry_date'] !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $form['expiry_date']);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($dateErrors !== false && (
                $dateErrors['warning_count'] > 0
                || $dateErrors['error_count'] > 0
            ))
            || $date->format('Y-m-d') !== $form['expiry_date']
        ) {
            $errors[] = 'Tanggal kedaluwarsa tidak valid.';
        }
    }

    if ($errors === []) {
        try {
            $connection = getDatabaseConnection();

            // Cek kode obat agar tidak ganda.
            $check = $connection->prepare(
                'SELECT COUNT(*) FROM medicines WHERE code = :code'
            );
            $check->execute(['code' => $form['code']]);

            if ((int) $check->fetchColumn() > 0) {
                $errors[] = 'Kode obat sudah digunakan. Gunakan kode yang berbeda.';
            } else {
                $insert = $connection->prepare(
                    'INSERT INTO medicines
                        (code, name, category, unit, stock, minimum_stock, price, expiry_date)
                     VALUES
                        (:code, :name, :category, :unit, :stock, :minimum_stock, :price, :expiry_date)'
                );

                $insert->execute([
                    'code' => $form['code'],
                    'name' => $form['name'],
                    'category' => $form['category'],
                    'unit' => $form['unit'],
                    'stock' => (int) $form['stock'],
                    'minimum_stock' => (int) $form['minimum_stock'],
                    'price' => $priceInput,
                    'expiry_date' => $form['expiry_date'] !== ''
                        ? $form['expiry_date']
                        : null,
                ]);

                $_SESSION['csrf_tambah_obat'] = bin2hex(random_bytes(32));
                $_SESSION['flash_message'] = 'Obat berhasil ditambahkan.';
                $_SESSION['flash_type'] = 'success';

                header('Location: data_obat.php');
                exit;
            }
        } catch (PDOException $exception) {
            error_log('Insert medicine error: ' . $exception->getMessage());
            $errors[] = 'Obat gagal disimpan. Periksa struktur tabel medicines dan koneksi database.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Obat - RS Medika Sehat</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">
    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .tambah-obat-panel {
            max-width: 900px;
            margin: 0 auto;
        }

        .tambah-obat-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .tambah-obat-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .tambah-obat-field.full {
            grid-column: 1 / -1;
        }

        .tambah-obat-field label {
            color: #344054;
            font-size: 13px;
            font-weight: 700;
        }

        .tambah-obat-field input,
        .tambah-obat-field select {
            width: 100%;
            min-height: 46px;
            padding: 11px 13px;
            box-sizing: border-box;
            border: 1px solid #d0d5dd;
            border-radius: 9px;
            background: #fff;
            color: #17212f;
            font: inherit;
            font-size: 14px;
        }

        .tambah-obat-field input:focus,
        .tambah-obat-field select:focus {
            outline: none;
            border-color: #1769aa;
            box-shadow: 0 0 0 3px rgba(23, 105, 170, .12);
        }

        .tambah-obat-field small {
            color: #667085;
            font-size: 12px;
            line-height: 1.5;
        }

        .tambah-obat-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 20px;
            border-top: 1px solid #e7edf2;
        }

        .tambah-obat-errors {
            margin-bottom: 20px;
            padding: 16px 20px;
            border: 1px solid #f3c7c7;
            border-radius: 10px;
            background: #fff1f1;
            color: #a4262c;
        }

        .tambah-obat-errors ul {
            margin: 8px 0 0;
            padding-left: 20px;
        }

        .tambah-obat-errors li {
            margin: 5px 0;
        }

        .tambah-obat-note {
            margin-bottom: 22px;
            padding: 14px 16px;
            border-radius: 9px;
            background: #eaf4ff;
            color: #17588a;
            font-size: 13px;
            line-height: 1.6;
        }

        @media (max-width: 640px) {
            .tambah-obat-form {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .tambah-obat-field.full,
            .tambah-obat-actions {
                grid-column: auto;
            }

            .tambah-obat-actions {
                flex-direction: column-reverse;
            }

            .tambah-obat-actions .btn {
                width: 100%;
                justify-content: center;
                text-align: center;
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
                    <p class="eyebrow">Modul farmasi</p>
                    <h1>Tambah Obat</h1>
                    <p class="admin-intro">
                        Tambahkan informasi obat ke dalam persediaan RS Medika Sehat.
                    </p>
                </div>

                <a href="data_obat.php" class="btn btn-secondary">
                    Kembali ke Data Obat
                </a>
            </div>

            <?php if ($errors !== []): ?>
                <section class="tambah-obat-errors" role="alert">
                    <strong>Data belum dapat disimpan.</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= tambahObatEscape($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <section class="admin-panel tambah-obat-panel">
                <div class="section-heading">
                    <div>
                        <h2>Informasi Obat</h2>
                        <p>Isi data obat dengan benar sebelum menyimpan.</p>
                    </div>
                </div>

                <div class="tambah-obat-note">
                    Kolom bertanda * wajib diisi. Tanggal kedaluwarsa boleh dikosongkan
                    jika belum tersedia.
                </div>

                <form method="post" action="tambah_obat.php" class="tambah-obat-form">
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= tambahObatEscape($_SESSION['csrf_tambah_obat']) ?>">

                    <div class="tambah-obat-field">
                        <label for="code">Kode Obat *</label>
                        <input
                            type="text"
                            id="code"
                            name="code"
                            maxlength="50"
                            value="<?= tambahObatEscape($form['code']) ?>"
                            placeholder="Contoh: OBT-001"
                            required>
                    </div>

                    <div class="tambah-obat-field">
                        <label for="name">Nama Obat *</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            maxlength="150"
                            value="<?= tambahObatEscape($form['name']) ?>"
                            placeholder="Masukkan nama obat"
                            required>
                    </div>

                    <div class="tambah-obat-field">
                        <label for="category">Kategori *</label>
                        <select id="category" name="category" required>
                            <option value="">Pilih kategori</option>
                            <?php foreach (
                                [
                                    'Tablet',
                                    'Kapsul',
                                    'Sirup',
                                    'Cairan',
                                    'Salep',
                                    'Injeksi',
                                    'Tetes',
                                    'Alat Kesehatan',
                                    'Lainnya'
                                ] as $category
                            ): ?>
                                <option
                                    value="<?= tambahObatEscape($category) ?>"
                                    <?= $form['category'] === $category ? 'selected' : '' ?>>
                                    <?= tambahObatEscape($category) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="tambah-obat-field">
                        <label for="unit">Satuan *</label>
                        <select id="unit" name="unit" required>
                            <option value="">Pilih satuan</option>
                            <?php foreach (
                                [
                                    'Tablet',
                                    'Kapsul',
                                    'Botol',
                                    'Strip',
                                    'Tube',
                                    'Ampul',
                                    'Vial',
                                    'Sachet',
                                    'Pcs',
                                    'Box'
                                ] as $unit
                            ): ?>
                                <option
                                    value="<?= tambahObatEscape($unit) ?>"
                                    <?= $form['unit'] === $unit ? 'selected' : '' ?>>
                                    <?= tambahObatEscape($unit) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="tambah-obat-field">
                        <label for="stock">Stok Awal *</label>
                        <input
                            type="number"
                            id="stock"
                            name="stock"
                            min="0"
                            step="1"
                            value="<?= tambahObatEscape($form['stock']) ?>"
                            required>
                        <small>Jumlah stok yang tersedia saat obat didaftarkan.</small>
                    </div>

                    <div class="tambah-obat-field">
                        <label for="minimum_stock">Batas Minimum Stok *</label>
                        <input
                            type="number"
                            id="minimum_stock"
                            name="minimum_stock"
                            min="0"
                            step="1"
                            value="<?= tambahObatEscape($form['minimum_stock']) ?>"
                            required>
                        <small>Peringatan muncul saat stok sama dengan atau di bawah batas ini.</small>
                    </div>

                    <div class="tambah-obat-field">
                        <label for="price">Harga (Rp) *</label>
                        <input
                            type="number"
                            id="price"
                            name="price"
                            min="0"
                            max="999999999999.99"
                            step="0.01"
                            value="<?= tambahObatEscape($form['price']) ?>"
                            placeholder="Contoh: 5000"
                            required>
                    </div>

                    <div class="tambah-obat-field">
                        <label for="expiry_date">Tanggal Kedaluwarsa</label>
                        <input
                            type="date"
                            id="expiry_date"
                            name="expiry_date"
                            value="<?= tambahObatEscape($form['expiry_date']) ?>">
                    </div>

                    <div class="tambah-obat-actions">
                        <a href="data_obat.php" class="btn btn-secondary">
                            Batal
                        </a>

                        <button type="submit" class="btn btn-primary">
                            Simpan Obat
                        </button>
                    </div>
                </form>
            </section>

            <footer class="page-footer">
                © <?= date('Y') ?> RS Medika Sehat. Sistem Informasi Rumah Sakit.
            </footer>
        </main>
    </div>
</body>

</html>