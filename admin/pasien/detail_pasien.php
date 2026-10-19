<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$connection = getDatabaseConnection();

function detailPasienEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$patient = null;
$errorMessage = '';

$fields = [
    'name' => [
        'label' => 'Nama Lengkap',
        'aliases' => ['name', 'nama', 'nama_pasien'],
    ],
    'medical_record_number' => [
        'label' => 'Nomor Rekam Medis',
        'aliases' => [
            'medical_record_number',
            'no_rekam_medis',
            'nomor_rekam_medis',
            'no_rm',
        ],
    ],
    'nik' => [
        'label' => 'NIK',
        'aliases' => ['nik', 'no_nik'],
    ],
    'email' => [
        'label' => 'Email',
        'aliases' => ['email'],
    ],
    'phone' => [
        'label' => 'Nomor Telepon',
        'aliases' => [
            'phone',
            'no_hp',
            'telepon',
            'phone_number',
        ],
    ],
    'gender' => [
        'label' => 'Jenis Kelamin',
        'aliases' => ['gender', 'jenis_kelamin'],
    ],
    'date_of_birth' => [
        'label' => 'Tanggal Lahir',
        'aliases' => [
            'date_of_birth',
            'tanggal_lahir',
            'birth_date',
        ],
    ],
    'address' => [
        'label' => 'Alamat',
        'aliases' => ['address', 'alamat'],
    ],
];

try {
    $id = filter_var(
        $_GET['id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false || $id === null) {
        throw new InvalidArgumentException(
            'ID pasien tidak valid.'
        );
    }

    $columnRows = $connection
        ->query('SHOW COLUMNS FROM `patients`')
        ->fetchAll(PDO::FETCH_ASSOC);

    $columns = [];

    foreach ($columnRows as $columnRow) {
        $columns[] = (string) $columnRow['Field'];
    }

    if (!in_array('id', $columns, true)) {
        throw new RuntimeException(
            'Kolom ID pasien tidak ditemukan.'
        );
    }

    $statement = $connection->prepare(
        'SELECT * FROM `patients` WHERE `id` = :id LIMIT 1'
    );

    $statement->execute(['id' => $id]);
    $patient = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        throw new InvalidArgumentException(
            'Data pasien tidak ditemukan.'
        );
    }
} catch (InvalidArgumentException | RuntimeException $exception) {
    $errorMessage = $exception->getMessage();
} catch (PDOException $exception) {
    error_log('Detail pasien error: ' . $exception->getMessage());

    $errorMessage = 'Data pasien gagal dimuat. Periksa database.';
}

function detailPasienGetValue(
    array $patient,
    array $definition
): string {
    foreach ($definition['aliases'] as $alias) {
        if (array_key_exists($alias, $patient)) {
            $value = trim((string) ($patient[$alias] ?? ''));

            return $value !== '' ? $value : 'Belum diisi';
        }
    }

    return 'Belum diisi';
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Pasien - RS Medika Sehat</title>

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">

    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .patient-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .patient-detail-item {
            padding: 16px;
            background: #f5f9fa;
            border-radius: 10px;
            overflow-wrap: anywhere;
        }

        .patient-detail-item span {
            display: block;
            color: #758b91;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .patient-detail-item strong {
            color: #173b43;
            font-size: 14px;
        }

        .patient-detail-full {
            grid-column: 1 / -1;
        }

        .patient-detail-error {
            padding: 14px;
            margin-bottom: 18px;
            color: #a52e2a;
            background: #fff0ef;
            border-radius: 9px;
        }

        .patient-detail-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 22px;
        }

        @media (max-width: 700px) {
            .patient-detail-grid {
                grid-template-columns: 1fr;
            }

            .patient-detail-full {
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
                    <p class="eyebrow">Manajemen Rumah Sakit</p>
                    <h1>Detail Pasien</h1>
                    <p class="admin-intro">
                        Informasi lengkap pasien RS Medika Sehat.
                    </p>
                </div>
            </div>

            <?php if ($errorMessage !== ''): ?>

                <div class="patient-detail-error" role="alert">
                    <?= detailPasienEscape($errorMessage) ?>
                </div>

                <a class="btn btn-secondary"
                    href="index.php">
                    Kembali ke Data Pasien
                </a>

            <?php elseif ($patient !== null): ?>

                <section class="admin-panel">

                    <h2>Informasi Pasien</h2>

                    <div class="patient-detail-grid">

                        <div class="patient-detail-item">
                            <span>ID Pasien</span>
                            <strong>
                                <?= (int) $patient['id'] ?>
                            </strong>
                        </div>

                        <?php foreach ($fields as $definition): ?>

                            <?php
                            $isAddress = $definition['label'] === 'Alamat';
                            $value = detailPasienGetValue(
                                $patient,
                                $definition
                            );
                            ?>

                            <div class="patient-detail-item
                            <?= $isAddress ? 'patient-detail-full' : '' ?>">

                                <span>
                                    <?= detailPasienEscape(
                                        $definition['label']
                                    ) ?>
                                </span>

                                <strong>
                                    <?= detailPasienEscape($value) ?>
                                </strong>

                            </div>

                        <?php endforeach; ?>

                    </div>

                    <div class="patient-detail-actions">

                        <a class="btn btn-primary"
                            href="index.php?edit=<?= (int) $patient['id'] ?>">
                            Edit Data Pasien
                        </a>

                        <a class="btn btn-secondary"
                            href="index.php">
                            Kembali ke Daftar Pasien
                        </a>

                    </div>

                </section>

            <?php endif; ?>

        </main>
    </div>

</body>

</html>