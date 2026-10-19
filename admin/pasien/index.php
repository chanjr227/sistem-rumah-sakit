<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$connection = getDatabaseConnection();

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function pasienEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function pasienRedirect(string $url = 'index.php'): never
{
    header('Location: ' . $url);
    exit;
}

function pasienValidId(mixed $value): int
{
    $id = filter_var(
        $value,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false || $id === null) {
        throw new InvalidArgumentException(
            'ID pasien tidak valid.'
        );
    }

    return $id;
}

/*
|--------------------------------------------------------------------------
| KONFIGURASI FIELD PASIEN
|--------------------------------------------------------------------------
*/

$fields = [
    'name' => [
        'label' => 'Nama lengkap',
        'type' => 'text',
        'required' => true,
        'aliases' => ['name', 'nama', 'nama_pasien'],
    ],

    'medical_record_number' => [
        'label' => 'Nomor rekam medis',
        'type' => 'text',
        'required' => false,
        'aliases' => [
            'medical_record_number',
            'no_rekam_medis',
            'nomor_rekam_medis',
            'no_rm',
        ],
    ],

    'nik' => [
        'label' => 'NIK',
        'type' => 'text',
        'required' => false,
        'aliases' => ['nik', 'no_nik'],
    ],

    'email' => [
        'label' => 'Email',
        'type' => 'email',
        'required' => true,
        'aliases' => ['email'],
    ],

    'phone' => [
        'label' => 'Nomor telepon',
        'type' => 'tel',
        'required' => false,
        'aliases' => [
            'phone',
            'no_hp',
            'telepon',
            'phone_number',
        ],
    ],

    'gender' => [
        'label' => 'Jenis kelamin',
        'type' => 'select',
        'required' => false,
        'aliases' => ['gender', 'jenis_kelamin'],
    ],

    'date_of_birth' => [
        'label' => 'Tanggal lahir',
        'type' => 'date',
        'required' => false,
        'aliases' => [
            'date_of_birth',
            'tanggal_lahir',
            'birth_date',
        ],
    ],

    'address' => [
        'label' => 'Alamat lengkap',
        'type' => 'textarea',
        'required' => false,
        'aliases' => ['address', 'alamat'],
    ],
];

/*
|--------------------------------------------------------------------------
| STATE HALAMAN
|--------------------------------------------------------------------------
*/

$notice = $_SESSION['admin_notice'] ?? null;
unset($_SESSION['admin_notice']);

$errors = [];
$formValues = [];
$patients = [];
$columns = [];
$mappedFields = [];
$patient = null;

$mode = 'list';
$search = trim((string) ($_GET['q'] ?? ''));
$formId = null;
$formAction = 'Tambah Pasien';
$csrfToken = adminCsrfToken();

/*
|--------------------------------------------------------------------------
| PROSES DATABASE
|--------------------------------------------------------------------------
*/

try {
    /*
    |--------------------------------------------------------------------------
    | DETEKSI KOLOM DATABASE
    |--------------------------------------------------------------------------
    */

    $columnRows = $connection
        ->query('SHOW COLUMNS FROM `patients`')
        ->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columnRows as $columnRow) {
        $columnName = (string) $columnRow['Field'];
        $columns[$columnName] = $columnRow;
    }

    if (!isset($columns['id'])) {
        throw new RuntimeException(
            'Tabel patients harus memiliki kolom id.'
        );
    }

    foreach ($fields as $key => $definition) {
        foreach ($definition['aliases'] as $alias) {
            if (isset($columns[$alias])) {
                $mappedFields[$key] = $alias;
                break;
            }
        }
    }

    if (!isset($mappedFields['name'])) {
        throw new RuntimeException(
            'Kolom nama pasien tidak ditemukan.'
        );
    }

    if (!isset($mappedFields['email'])) {
        throw new RuntimeException(
            'Kolom email tidak ditemukan.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN TAMBAH / EDIT PASIEN
    |--------------------------------------------------------------------------
    */

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $mode = 'form';

        if (!verifyAdminCsrfToken()) {
            throw new RuntimeException(
                'Token keamanan tidak valid. Muat ulang halaman.'
            );
        }

        if (($_POST['action'] ?? '') !== 'save') {
            throw new InvalidArgumentException(
                'Aksi yang diminta tidak dikenal.'
            );
        }

        $rawId = $_POST['id'] ?? '';

        if ($rawId !== '') {
            $formId = pasienValidId($rawId);
            $formAction = 'Perbarui Pasien';
        } else {
            $formAction = 'Tambah Pasien';
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDASI INPUT
        |--------------------------------------------------------------------------
        */

        foreach ($mappedFields as $key => $column) {
            $value = $_POST[$key] ?? '';

            if (!is_string($value)) {
                throw new InvalidArgumentException(
                    'Format data formulir tidak valid.'
                );
            }

            $value = trim($value);

            if (
                ($fields[$key]['required'] ?? false)
                && $value === ''
            ) {
                throw new InvalidArgumentException(
                    $fields[$key]['label'] . ' wajib diisi.'
                );
            }

            if (mb_strlen($value, 'UTF-8') > 1000) {
                throw new InvalidArgumentException(
                    $fields[$key]['label'] . ' terlalu panjang.'
                );
            }

            if (
                $key === 'email'
                && $value !== ''
                && !filter_var(
                    $value,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                throw new InvalidArgumentException(
                    'Format email tidak valid.'
                );
            }

            if (
                $key === 'nik'
                && $value !== ''
                && !preg_match('/^[0-9]{16}$/', $value)
            ) {
                throw new InvalidArgumentException(
                    'NIK harus terdiri dari 16 angka.'
                );
            }

            if (
                $key === 'gender'
                && $value !== ''
                && !in_array(
                    $value,
                    ['Laki-laki', 'Perempuan', 'L', 'P'],
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'Jenis kelamin tidak valid.'
                );
            }

            if (
                $key === 'date_of_birth'
                && $value !== ''
            ) {
                $date = DateTimeImmutable::createFromFormat(
                    '!Y-m-d',
                    $value
                );

                $dateErrors = DateTimeImmutable::getLastErrors();

                if (
                    $date === false
                    || (
                        $dateErrors !== false
                        && (
                            $dateErrors['warning_count'] > 0
                            || $dateErrors['error_count'] > 0
                        )
                    )
                    || $date->format('Y-m-d') !== $value
                    || $value > date('Y-m-d')
                ) {
                    throw new InvalidArgumentException(
                        'Tanggal lahir tidak valid.'
                    );
                }
            }

            $formValues[$key] = $value;
        }

        /*
        |--------------------------------------------------------------------------
        | CEK EMAIL, NIK, DAN NOMOR RM DUPLIKAT
        |--------------------------------------------------------------------------
        */

        foreach (
            ['email', 'nik', 'medical_record_number'] as $key
        ) {
            if (
                !isset($mappedFields[$key])
                || ($formValues[$key] ?? '') === ''
            ) {
                continue;
            }

            $column = $mappedFields[$key];

            $sql = 'SELECT `id`
                    FROM `patients`
                    WHERE `' . $column . '` = :value';

            $params = [
                'value' => $formValues[$key],
            ];

            if ($formId !== null) {
                $sql .= ' AND `id` <> :id';
                $params['id'] = $formId;
            }

            $check = $connection->prepare($sql);
            $check->execute($params);

            if ($check->fetchColumn()) {
                throw new InvalidArgumentException(
                    $fields[$key]['label']
                        . ' sudah digunakan pasien lain.'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SUSUN DATA UNTUK DATABASE
        |--------------------------------------------------------------------------
        */

        $data = [];

        foreach ($mappedFields as $key => $column) {
            $value = $formValues[$key] ?? '';

            // Kolom DATE harus NULL jika tidak diisi.
            if (
                $key === 'date_of_birth'
                && $value === ''
            ) {
                $value = null;
            }

            $data[$column] = $value;
        }

        /*
        |--------------------------------------------------------------------------
        | PASSWORD SEMENTARA UNTUK PASIEN BARU
        |--------------------------------------------------------------------------
        */

        if (
            isset($columns['password_hash'])
            && $formId === null
        ) {
            $temporarySecret = bin2hex(random_bytes(32));

            $data['password_hash'] = password_hash(
                $temporarySecret,
                PASSWORD_DEFAULT
            );
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE PASIEN
        |--------------------------------------------------------------------------
        */

        if ($formId !== null) {
            $checkPatient = $connection->prepare(
                'SELECT `id`
                 FROM `patients`
                 WHERE `id` = :id'
            );

            $checkPatient->execute(['id' => $formId]);

            if (!$checkPatient->fetchColumn()) {
                throw new InvalidArgumentException(
                    'Data pasien tidak ditemukan.'
                );
            }

            $sets = [];
            $params = ['id' => $formId];

            foreach ($data as $column => $value) {
                $sets[] = '`' . $column . '` = :' . $column;
                $params[$column] = $value;
            }

            $sql = 'UPDATE `patients`
                    SET ' . implode(', ', $sets) . '
                    WHERE `id` = :id';

            $statement = $connection->prepare($sql);
            $statement->execute($params);

            $_SESSION['admin_notice'] = [
                'type' => 'success',
                'message' => 'Data pasien berhasil diperbarui.',
            ];
        } else {
            /*
            |--------------------------------------------------------------------------
            | INSERT PASIEN BARU
            |--------------------------------------------------------------------------
            */

            $insertColumns = array_keys($data);

            $placeholders = array_map(
                static fn(string $column): string =>
                ':' . $column,
                $insertColumns
            );

            $sql = 'INSERT INTO `patients` (`'
                . implode('`, `', $insertColumns)
                . '`) VALUES ('
                . implode(', ', $placeholders)
                . ')';

            $statement = $connection->prepare($sql);
            $statement->execute($data);

            $_SESSION['admin_notice'] = [
                'type' => 'success',
                'message' => 'Pasien berhasil ditambahkan.',
            ];
        }

        pasienRedirect();
    }

    /*
    |--------------------------------------------------------------------------
    | TAMPILKAN FORM EDIT
    |--------------------------------------------------------------------------
    */ elseif (isset($_GET['edit'])) {
        $formId = pasienValidId($_GET['edit']);

        $statement = $connection->prepare(
            'SELECT *
             FROM `patients`
             WHERE `id` = :id'
        );

        $statement->execute(['id' => $formId]);

        $patient = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$patient) {
            throw new InvalidArgumentException(
                'Data pasien tidak ditemukan.'
            );
        }

        foreach ($mappedFields as $key => $column) {
            $formValues[$key] = (string) (
                $patient[$column] ?? ''
            );
        }

        $mode = 'form';
        $formAction = 'Perbarui Pasien';
    }

    /*
    |--------------------------------------------------------------------------
    | TAMPILKAN FORM TAMBAH
    |--------------------------------------------------------------------------
    */ elseif (isset($_GET['tambah'])) {
        $mode = 'form';
        $formAction = 'Tambah Pasien';
    }

    /*
    |--------------------------------------------------------------------------
    | DAFTAR DAN PENCARIAN PASIEN
    |--------------------------------------------------------------------------
    */ else {
        $selectColumns = array_unique(
            array_merge(
                ['id'],
                array_values($mappedFields)
            )
        );

        $sql = 'SELECT `'
            . implode('`, `', $selectColumns)
            . '` FROM `patients`';

        $params = [];

        if ($search !== '') {
            $searchable = [];

            foreach (
                [
                    'name',
                    'email',
                    'nik',
                    'medical_record_number',
                    'phone',
                ] as $key
            ) {
                if (isset($mappedFields[$key])) {
                    $searchable[] = '`'
                        . $mappedFields[$key]
                        . '` LIKE :search_' . $key;

                    $params['search_' . $key] =
                        '%' . $search . '%';
                }
            }

            if ($searchable !== []) {
                $sql .= ' WHERE '
                    . implode(' OR ', $searchable);
            }
        }

        $sql .= ' ORDER BY `id` DESC LIMIT 500';

        $statement = $connection->prepare($sql);
        $statement->execute($params);

        $patients = $statement->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (InvalidArgumentException $exception) {
    $errors[] = $exception->getMessage();
} catch (PDOException $exception) {
    error_log(
        'Admin patients database error: '
            . $exception->getMessage()
    );

    $errors[] = 'Data pasien gagal diproses. '
        . 'Periksa struktur tabel dan koneksi database.';
} catch (RuntimeException $exception) {
    $errors[] = $exception->getMessage();
}

/*
|--------------------------------------------------------------------------
| PERTAHANKAN INPUT JIKA VALIDASI GAGAL
|--------------------------------------------------------------------------
*/

if (
    $errors !== []
    && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
) {
    $mode = 'form';

    foreach ($mappedFields as $key => $column) {
        $posted = $_POST[$key] ?? '';

        if (is_string($posted)) {
            $formValues[$key] = $posted;
        }
    }

    if ($formId !== null) {
        $formAction = 'Perbarui Pasien';

        // Ambil kembali data asli agar ID tetap valid.
        if ($patient === null) {
            try {
                $statement = $connection->prepare(
                    'SELECT *
                     FROM `patients`
                     WHERE `id` = :id'
                );

                $statement->execute(['id' => $formId]);

                $patient = $statement->fetch(PDO::FETCH_ASSOC)
                    ?: null;
            } catch (PDOException $exception) {
                error_log($exception->getMessage());
            }
        }
    } else {
        $formAction = 'Tambah Pasien';
    }
}

$isEditing = $formId !== null;

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        <?= $mode === 'form'
            ? pasienEscape($formAction)
            : 'Data Pasien' ?>
        - RS Medika Sehat
    </title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect"
        href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">

    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .patient-search {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .patient-search input {
            flex: 1;
            min-width: 180px;
            padding: 12px 14px;
            border: 1px solid #dce9eb;
            border-radius: 9px;
            font: inherit;
        }

        .patient-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .patient-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 11px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            background: #e8f7f5;
            color: #087e80;
        }

        .patient-link:hover {
            background: #d3eeeb;
        }

        .patient-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .patient-form-grid label {
            display: flex;
            flex-direction: column;
            gap: 7px;
            font-size: 13px;
            font-weight: 600;
            color: #173b43;
        }

        .patient-form-grid input,
        .patient-form-grid select,
        .patient-form-grid textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid #dce9eb;
            border-radius: 8px;
            font: inherit;
            font-weight: 400;
            background: #fff;
            color: #173b43;
        }

        .patient-form-grid input:focus,
        .patient-form-grid select:focus,
        .patient-form-grid textarea:focus {
            outline: 2px solid #a5e3df;
            border-color: #087e80;
        }

        .patient-form-grid textarea {
            min-height: 110px;
            resize: vertical;
        }

        .patient-full {
            grid-column: 1 / -1;
        }

        .patient-message {
            padding: 13px 16px;
            border-radius: 9px;
            margin-bottom: 18px;
            font-size: 13px;
        }

        .patient-error {
            background: #fff0ef;
            color: #a52e2a;
        }

        .patient-success {
            background: #e8f7f0;
            color: #176c4b;
        }

        .patient-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .patient-table {
            width: 100%;
            min-width: 750px;
        }

        .patient-empty {
            padding: 30px 16px !important;
            text-align: center;
            color: #667085;
        }

        .patient-form-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            margin-top: 24px;
        }

        .patient-required {
            color: #c0392b;
        }

        .patient-form-note {
            color: #758b91;
            font-size: 12px;
            margin-top: 18px;
        }

        @media (max-width: 700px) {
            .patient-form-grid {
                grid-template-columns: 1fr;
            }

            .patient-full {
                grid-column: auto;
            }

            .patient-form-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .patient-form-actions .btn {
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
                    <p class="eyebrow">Manajemen rumah sakit</p>

                    <h1>
                        <?= $mode === 'form'
                            ? pasienEscape($formAction)
                            : 'Data Pasien' ?>
                    </h1>

                    <p class="admin-intro">
                        Kelola informasi dan data administrasi
                        pasien RS Medika Sehat.
                    </p>
                </div>

                <?php if ($mode === 'list'): ?>
                    <a
                        class="btn btn-primary"
                        href="index.php?tambah=1">
                        + Tambah Pasien
                    </a>
                <?php endif; ?>
            </div>

            <!-- PESAN BERHASIL -->

            <?php if (
                is_array($notice)
                && isset($notice['message'])
            ): ?>
                <div
                    class="patient-message patient-success"
                    role="status">

                    <?= pasienEscape($notice['message']) ?>
                </div>
            <?php endif; ?>

            <!-- PESAN ERROR -->

            <?php foreach (array_unique($errors) as $error): ?>
                <div
                    class="patient-message patient-error"
                    role="alert">

                    <?= pasienEscape($error) ?>
                </div>
            <?php endforeach; ?>

            <!-- ==================================================
                 DAFTAR PASIEN
            =================================================== -->

            <?php if ($mode === 'list'): ?>

                <section class="admin-panel">

                    <h2>Daftar Pasien</h2>

                    <form
                        method="get"
                        action="index.php"
                        class="patient-search">

                        <input
                            type="search"
                            name="q"
                            value="<?= pasienEscape($search) ?>"
                            placeholder="Cari nama, email, NIK, atau nomor RM..."
                            aria-label="Cari pasien">

                        <button
                            class="btn btn-primary"
                            type="submit">
                            Cari Pasien
                        </button>

                        <?php if ($search !== ''): ?>
                            <a
                                class="btn btn-secondary"
                                href="index.php">
                                Reset
                            </a>
                        <?php endif; ?>

                    </form>

                    <p class="admin-intro">
                        Maksimal 500 data ditampilkan.
                        Gunakan pencarian untuk menemukan pasien.
                    </p>

                    <div class="patient-table-wrap">

                        <table class="admin-table patient-table">

                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Nama Pasien</th>

                                    <?php if (
                                        isset($mappedFields['medical_record_number'])
                                    ): ?>
                                        <th>Nomor Rekam Medis</th>
                                    <?php endif; ?>

                                    <th>Email</th>

                                    <?php if (
                                        isset($mappedFields['phone'])
                                    ): ?>
                                        <th>Nomor Telepon</th>
                                    <?php endif; ?>

                                    <th>Aksi</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if ($patients === []): ?>

                                    <tr>
                                        <td
                                            colspan="6"
                                            class="patient-empty">
                                            Belum ada data pasien
                                            yang ditemukan.
                                        </td>
                                    </tr>

                                <?php else: ?>

                                    <?php foreach (
                                        $patients as $index => $row
                                    ): ?>

                                        <tr>
                                            <td>
                                                <?= $index + 1 ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= pasienEscape(
                                                        $row[$mappedFields['name']] ?? ''
                                                    ) ?>
                                                </strong>
                                            </td>

                                            <?php if (
                                                isset($mappedFields['medical_record_number'])
                                            ): ?>
                                                <td>
                                                    <?= pasienEscape(
                                                        $row[$mappedFields['medical_record_number']] ?? ''
                                                    ) ?>
                                                </td>
                                            <?php endif; ?>

                                            <td>
                                                <?= pasienEscape(
                                                    $row[$mappedFields['email']] ?? ''
                                                ) ?>
                                            </td>

                                            <?php if (
                                                isset($mappedFields['phone'])
                                            ): ?>
                                                <td>
                                                    <?= pasienEscape(
                                                        $row[$mappedFields['phone']] ?? ''
                                                    ) ?>
                                                </td>
                                            <?php endif; ?>

                                            <td>
                                                <div class="patient-actions">

                                                    <a
                                                        class="patient-link"
                                                        href="detail_pasien.php?id=<?= (int) $row['id'] ?>">
                                                        Detail
                                                    </a>

                                                    <a
                                                        class="patient-link"
                                                        href="index.php?edit=<?= (int) $row['id'] ?>">
                                                        Edit
                                                    </a>

                                                </div>
                                            </td>
                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>
                    </div>

                </section>

                <!-- ==================================================
                 FORM TAMBAH / EDIT PASIEN
            =================================================== -->

            <?php elseif ($mode === 'form'): ?>

                <section class="admin-panel">

                    <h2><?= pasienEscape($formAction) ?></h2>

                    <p class="admin-intro">
                        <?= $isEditing
                            ? 'Perbarui informasi pasien pada formulir berikut.'
                            : 'Masukkan informasi pasien untuk menambahkan data baru.' ?>
                    </p>

                    <form
                        class="admin-form"
                        method="post"
                        action="index.php<?= $isEditing
                                                ? '?edit=' . (int) $formId
                                                : '?tambah=1' ?>">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= pasienEscape($csrfToken) ?>">

                        <input
                            type="hidden"
                            name="action"
                            value="save">

                        <?php if ($isEditing): ?>
                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int) $formId ?>">
                        <?php endif; ?>

                        <div class="patient-form-grid">

                            <?php foreach (
                                $mappedFields as $key => $column
                            ): ?>

                                <?php
                                $definition = $fields[$key];

                                $value = $formValues[$key]
                                    ?? ($patient[$column] ?? '');

                                $isFull =
                                    $definition['type'] === 'textarea';
                                ?>

                                <label
                                    class="<?= $isFull ? 'patient-full' : '' ?>">

                                    <span>
                                        <?= pasienEscape($definition['label']) ?>

                                        <?php if ($definition['required']): ?>
                                            <span class="patient-required">*</span>
                                        <?php endif; ?>
                                    </span>

                                    <?php if (
                                        $definition['type'] === 'textarea'
                                    ): ?>

                                        <textarea
                                            name="<?= pasienEscape($key) ?>"
                                            maxlength="1000"
                                            placeholder="Masukkan <?= pasienEscape(
                                                                        strtolower($definition['label'])
                                                                    ) ?>"
                                            <?= $definition['required']
                                                ? 'required'
                                                : '' ?>><?= pasienEscape($value) ?></textarea>

                                    <?php elseif (
                                        $definition['type'] === 'select'
                                    ): ?>

                                        <select name="<?= pasienEscape($key) ?>">

                                            <option value="">
                                                Pilih jenis kelamin
                                            </option>

                                            <?php
                                            $genderValue = match ($value) {
                                                'L' => 'Laki-laki',
                                                'P' => 'Perempuan',
                                                default => $value,
                                            };
                                            ?>

                                            <option
                                                value="Laki-laki"
                                                <?= $genderValue === 'Laki-laki'
                                                    ? 'selected'
                                                    : '' ?>>
                                                Laki-laki
                                            </option>

                                            <option
                                                value="Perempuan"
                                                <?= $genderValue === 'Perempuan'
                                                    ? 'selected'
                                                    : '' ?>>
                                                Perempuan
                                            </option>

                                        </select>

                                    <?php else: ?>

                                        <input
                                            type="<?= pasienEscape($definition['type']) ?>"
                                            name="<?= pasienEscape($key) ?>"
                                            value="<?= pasienEscape(
                                                        $definition['type'] === 'date'
                                                            ? substr((string) $value, 0, 10)
                                                            : $value
                                                    ) ?>"
                                            maxlength="1000"
                                            <?= $key === 'nik'
                                                ? 'inputmode="numeric" pattern="[0-9]{16}"'
                                                : '' ?>
                                            <?= $definition['required']
                                                ? 'required'
                                                : '' ?>
                                            <?= $key === 'date_of_birth'
                                                ? 'max="' . date('Y-m-d') . '"'
                                                : '' ?>
                                            placeholder="<?= $key === 'nik'
                                                                ? 'Masukkan 16 digit NIK'
                                                                : (
                                                                    $definition['type'] === 'date'
                                                                    ? ''
                                                                    : 'Masukkan ' . strtolower($definition['label'])
                                                                ) ?>">

                                    <?php endif; ?>

                                </label>

                            <?php endforeach; ?>

                        </div>

                        <p class="patient-form-note">
                            Kolom bertanda
                            <span class="patient-required">*</span>
                            wajib diisi.
                        </p>

                        <div class="patient-form-actions">

                            <button
                                class="btn btn-primary"
                                type="submit">
                                <?= pasienEscape($formAction) ?>
                            </button>

                            <a
                                class="btn btn-secondary"
                                href="index.php">
                                Batal
                            </a>

                        </div>

                    </form>

                </section>

            <?php endif; ?>

        </main>
    </div>

</body>

</html>