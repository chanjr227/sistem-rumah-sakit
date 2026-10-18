<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function editObatEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$errors = [];

if (!$id || $id < 1) {
    header('Location: data_obat.php');
    exit;
}

if (empty($_SESSION['csrf_edit_obat'])) {
    $_SESSION['csrf_edit_obat'] = bin2hex(random_bytes(32));
}

$form = [];

try {
    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'SELECT * FROM medicines WHERE id = :id LIMIT 1'
    );
    $statement->execute(['id' => $id]);

    $medicine = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$medicine) {
        header('Location: data_obat.php');
        exit;
    }

    $form = [
        'code' => (string) $medicine['code'],
        'name' => (string) $medicine['name'],
        'category' => (string) $medicine['category'],
        'unit' => (string) $medicine['unit'],
        'stock' => (string) $medicine['stock'],
        'minimum_stock' => (string) $medicine['minimum_stock'],
        'price' => (string) $medicine['price'],
        'expiry_date' => (string) ($medicine['expiry_date'] ?? ''),
    ];

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        foreach ($form as $field => $value) {
            $form[$field] = trim((string) ($_POST[$field] ?? ''));
        }

        $token = (string) ($_POST['_token'] ?? '');

        if (!hash_equals($_SESSION['csrf_edit_obat'], $token)) {
            $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
        }

        if ($form['code'] === '' || strlen($form['code']) > 50) {
            $errors[] = 'Kode obat wajib diisi dan maksimal 50 karakter.';
        }

        if ($form['name'] === '' || strlen($form['name']) > 150) {
            $errors[] = 'Nama obat wajib diisi dan maksimal 150 karakter.';
        }

        if ($form['category'] === '') {
            $errors[] = 'Kategori obat wajib dipilih.';
        }

        if ($form['unit'] === '') {
            $errors[] = 'Satuan obat wajib dipilih.';
        }

        foreach (
            [
                'stock' => 'Stok',
                'minimum_stock' => 'Batas minimum stok'
            ] as $field => $label
        ) {
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
            $date = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $form['expiry_date']
            );

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
            $check = $connection->prepare(
                'SELECT COUNT(*)
                 FROM medicines
                 WHERE code = :code AND id <> :id'
            );

            $check->execute([
                'code' => $form['code'],
                'id' => $id,
            ]);

            if ((int) $check->fetchColumn() > 0) {
                $errors[] = 'Kode obat sudah digunakan oleh obat lain.';
            } else {
                $update = $connection->prepare(
                    'UPDATE medicines SET
                        code = :code,
                        name = :name,
                        category = :category,
                        unit = :unit,
                        stock = :stock,
                        minimum_stock = :minimum_stock,
                        price = :price,
                        expiry_date = :expiry_date
                     WHERE id = :id'
                );

                $update->execute([
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
                    'id' => $id,
                ]);

                $_SESSION['csrf_edit_obat'] = bin2hex(random_bytes(32));
                $_SESSION['flash_message'] = 'Data obat berhasil diperbarui.';
                $_SESSION['flash_type'] = 'success';

                header('Location: data_obat.php');
                exit;
            }
        }
    }
} catch (PDOException $exception) {
    error_log('Edit medicine error: ' . $exception->getMessage());
    http_response_code(500);
    $errors[] = 'Terjadi kesalahan saat memproses data obat. Periksa koneksi database.';
}

$categories = [
    'Tablet',
    'Kapsul',
    'Sirup',
    'Cairan',
    'Salep',
    'Injeksi',
    'Tetes',
    'Alat Kesehatan',
    'Lainnya'
];

$units = [
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
];

// Jika data lama memakai kategori atau satuan di luar pilihan,
// tetap tampilkan nilai tersebut agar tidak hilang saat diedit.
if (
    isset($form['category']) && $form['category'] !== ''
    && !in_array($form['category'], $categories, true)
) {
    $categories[] = $form['category'];
}

if (
    isset($form['unit']) && $form['unit'] !== ''
    && !in_array($form['unit'], $units, true)
) {
    $units[] = $form['unit'];
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Obat - RS Medika Sehat</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">
    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .edit-obat-panel {
            max-width: 900px;
            margin: 0 auto;
        }

        .edit-obat-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .edit-obat-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .edit-obat-field label {
            color: #344054;
            font-size: 13px;
            font-weight: 700;
        }

        .edit-obat-field input,
        .edit-obat-field select {
            box-sizing: border-box;
            width: 100%;
            min-height: 46px;
            padding: 11px 13px;
            border: 1px solid #d0d5dd;
            border-radius: 9px;
            background: #fff;
            color: #17212f;
            font: inherit;
            font-size: 14px;
        }

        .edit-obat-field input:focus,
        .edit-obat-field select:focus {
            outline: none;
            border-color: #1769aa;
            box-shadow: 0 0 0 3px rgba(23, 105, 170, .12);
        }

        .edit-obat-field small {
            color: #667085;
            font-size: 12px;
        }

        .edit-obat-full,
        .edit-obat-actions {
            grid-column: 1 / -1;
        }

        .edit-obat-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 20px;
            border-top: 1px solid #e7edf2;
        }

        .edit-obat-errors {
            max-width: 900px;
            margin: 0 auto 20px;
            padding: 16px 20px;
            border: 1px solid #f3c7c7;
            border-radius: 10px;
            background: #fff1f1;
            color: #a4262c;
        }

        .edit-obat-errors li {
            margin: 5px 0;
        }

        @media (max-width: 640px) {
            .edit-obat-form {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .edit-obat-full,
            .edit-obat-actions {
                grid-column: auto;
            }

            .edit-obat-actions {
                flex-direction: column-reverse;
            }

            .edit-obat-actions .btn {
                width: 100%;
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
            <div class="admin-page-heading">
                <div>
                    <p class="eyebrow">Modul farmasi</p>
                    <h1>Edit Obat</h1>
                    <p class="admin-intro">
                        Perbarui informasi dan persediaan obat.
                    </p>
                </div>

                <a href="data_obat.php" class="btn btn-secondary">
                    Kembali ke Data Obat
                </a>
            </div>

            <?php if ($errors !== []): ?>
                <section class="edit-obat-errors" role="alert">
                    <strong>Data belum dapat disimpan.</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= editObatEscape($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if ($form !== []): ?>
                <section class="admin-panel edit-obat-panel">
                    <div class="section-heading">
                        <div>
                            <h2>Informasi Obat</h2>
                            <p>Periksa perubahan sebelum menyimpan.</p>
                        </div>
                    </div>

                    <form method="post"
                        action="edit_obat.php?id=<?= (int) $id ?>"
                        class="edit-obat-form">

                        <input type="hidden" name="_token"
                            value="<?= editObatEscape($_SESSION['csrf_edit_obat']) ?>">

                        <div class="edit-obat-field">
                            <label for="code">Kode Obat *</label>
                            <input id="code" name="code" maxlength="50"
                                value="<?= editObatEscape($form['code']) ?>" required>
                        </div>

                        <div class="edit-obat-field">
                            <label for="name">Nama Obat *</label>
                            <input id="name" name="name" maxlength="150"
                                value="<?= editObatEscape($form['name']) ?>" required>
                        </div>

                        <div class="edit-obat-field">
                            <label for="category">Kategori *</label>
                            <select id="category" name="category" required>
                                <option value="">Pilih kategori</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= editObatEscape($category) ?>"
                                        <?= $form['category'] === $category ? 'selected' : '' ?>>
                                        <?= editObatEscape($category) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="edit-obat-field">
                            <label for="unit">Satuan *</label>
                            <select id="unit" name="unit" required>
                                <option value="">Pilih satuan</option>
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?= editObatEscape($unit) ?>"
                                        <?= $form['unit'] === $unit ? 'selected' : '' ?>>
                                        <?= editObatEscape($unit) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="edit-obat-field">
                            <label for="stock">Stok *</label>
                            <input type="number" id="stock" name="stock" min="0" step="1"
                                value="<?= editObatEscape($form['stock']) ?>" required>
                        </div>

                        <div class="edit-obat-field">
                            <label for="minimum_stock">Batas Minimum Stok *</label>
                            <input type="number" id="minimum_stock" name="minimum_stock"
                                min="0" step="1"
                                value="<?= editObatEscape($form['minimum_stock']) ?>" required>
                        </div>

                        <div class="edit-obat-field">
                            <label for="price">Harga (Rp) *</label>
                            <input type="number" id="price" name="price" min="0"
                                max="999999999999.99" step="0.01"
                                value="<?= editObatEscape($form['price']) ?>" required>
                        </div>

                        <div class="edit-obat-field">
                            <label for="expiry_date">Tanggal Kedaluwarsa</label>
                            <input type="date" id="expiry_date" name="expiry_date"
                                value="<?= editObatEscape($form['expiry_date']) ?>">
                        </div>

                        <div class="edit-obat-actions">
                            <a href="data_obat.php" class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary">
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <footer class="page-footer">
                © <?= date('Y') ?> RS Medika Sehat. Sistem Informasi Rumah Sakit.
            </footer>
        </main>
    </div>
</body>

</html>