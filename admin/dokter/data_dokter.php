<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

function dataDokterEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');

$allowedStatuses = [
    'available' => 'Tersedia',
    'busy' => 'Penuh',
];

if (!array_key_exists($statusFilter, $allowedStatuses)) {
    $statusFilter = '';
}

$doctors = [];
$errorMessage = null;
$totalDoctors = 0;

try {
    $connection = getDatabaseConnection();

    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(d.name LIKE :search_name
                     OR d.specialty LIKE :search_specialty)';
        $params['search_name'] = '%' . $search . '%';
        $params['search_specialty'] = '%' . $search . '%';
    }

    if ($statusFilter !== '') {
        $where[] = 'd.status = :status';
        $params['status'] = $statusFilter;
    }

    $sql = '
        SELECT
            d.id,
            d.name,
            d.specialty,
            d.work_days,
            d.start_time,
            d.end_time,
            d.room,
            d.status,
            dp.photo,
            dp.education,
            dp.experience,
            dp.biography
        FROM doctors AS d
        LEFT JOIN doctor_profiles AS dp
            ON dp.doctor_id = d.id
    ';

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY d.name ASC';

    $statement = $connection->prepare($sql);
    $statement->execute($params);
    $doctors = $statement->fetchAll();

    $countStatement = $connection->query(
        'SELECT COUNT(*) FROM doctors'
    );
    $totalDoctors = (int) $countStatement->fetchColumn();
} catch (PDOException $exception) {
    error_log('Load doctors list error: ' . $exception->getMessage());
    $errorMessage = 'Data dokter gagal dimuat. Periksa struktur tabel database.';
}

$csrfToken = adminCsrfToken();

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Dokter - RS Medika Sehat</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">
    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .doctor-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .doctor-filters {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            flex: 1;
        }

        .doctor-filters input,
        .doctor-filters select {
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid var(--border, #d9e1e8);
            border-radius: 8px;
            font: inherit;
        }

        .doctor-filters input {
            min-width: 220px;
            flex: 1;
        }

        .doctor-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .doctor-table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
        }

        .doctor-table th,
        .doctor-table td {
            padding: 14px 12px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid #e7edf2;
        }

        .doctor-table th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #667085;
            background: #f8fafc;
        }

        .doctor-profile-cell {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 210px;
        }

        .doctor-photo {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            background: #e8eef3;
        }

        .doctor-photo-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #1769aa;
        }

        .doctor-name {
            font-weight: 700;
            color: #17212f;
        }

        .doctor-subtext {
            margin-top: 4px;
            font-size: 12px;
            line-height: 1.5;
            color: #697586;
        }

        .doctor-status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .doctor-status.available {
            background: #e7f8ef;
            color: #18794e;
        }

        .doctor-status.busy {
            background: #fff0e7;
            color: #b54708;
        }

        .doctor-action {
            white-space: nowrap;
        }

        .doctor-empty {
            text-align: center;
            padding: 38px 16px;
            color: #667085;
        }

        .doctor-count {
            margin-bottom: 18px;
            color: #667085;
            font-size: 14px;
        }

        @media (max-width: 640px) {
            .doctor-filters {
                width: 100%;
            }

            .doctor-filters input,
            .doctor-filters select {
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
                    <h1>Data Dokter</h1>
                    <p class="admin-intro">
                        Kelola informasi dokter dan jadwal praktik RS Medika Sehat.
                    </p>
                </div>

                <a class="btn btn-primary" href="tambah_dokter.php">
                    + Tambah Dokter
                </a>
            </div>

            <?php require __DIR__ . '/../partials/flash_message.php'; ?>

            <?php if ($errorMessage !== null): ?>
                <section class="admin-panel" role="alert">
                    <?= dataDokterEscape($errorMessage) ?>
                </section>
            <?php else: ?>

                <section class="admin-panel">

                    <div class="doctor-toolbar">
                        <form class="doctor-filters" method="get"
                            action="data_dokter.php">

                            <input
                                type="search"
                                name="search"
                                placeholder="Cari nama atau spesialisasi..."
                                value="<?= dataDokterEscape($search) ?>">

                            <select name="status" aria-label="Filter status dokter">
                                <option value="">Semua status</option>
                                <?php foreach ($allowedStatuses as $value => $label): ?>
                                    <option
                                        value="<?= dataDokterEscape($value) ?>"
                                        <?= $statusFilter === $value
                                            ? 'selected'
                                            : '' ?>>
                                        <?= dataDokterEscape($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button class="btn btn-secondary" type="submit">
                                Cari
                            </button>

                            <a class="btn btn-secondary" href="data_dokter.php">
                                Reset
                            </a>
                        </form>
                    </div>

                    <p class="doctor-count">
                        Total dokter terdaftar:
                        <strong><?= $totalDoctors ?></strong>
                        &middot;
                        Hasil pencarian:
                        <strong><?= count($doctors) ?></strong>
                    </p>

                    <div class="doctor-table-wrap">
                        <table class="doctor-table">
                            <thead>
                                <tr>
                                    <th>Profil Dokter</th>
                                    <th>Spesialisasi</th>
                                    <th>Pendidikan</th>
                                    <th>Pengalaman</th>
                                    <th>Jadwal Praktik</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if ($doctors === []): ?>
                                    <tr>
                                        <td colspan="7" class="doctor-empty">
                                            Belum ada data dokter yang sesuai.
                                            <br>
                                            <a href="tambah_dokter.php">
                                                Tambahkan dokter baru
                                            </a>
                                        </td>
                                    </tr>
                                <?php else: ?>

                                    <?php foreach ($doctors as $item): ?>
                                        <?php
                                        $status = (string) $item['status'];
                                        $statusLabel =
                                            $allowedStatuses[$status] ?? 'Tidak diketahui';

                                        $photo = trim((string) ($item['photo'] ?? ''));

                                        if (
                                            $photo !== ''
                                            && !preg_match('~^(https?://|/)~i', $photo)
                                        ) {
                                            $photo = '../../' . ltrim($photo, '/');
                                        }

                                        $startTime = substr(
                                            (string) $item['start_time'],
                                            0,
                                            5
                                        );

                                        $endTime = substr(
                                            (string) $item['end_time'],
                                            0,
                                            5
                                        );
                                        ?>

                                        <tr>
                                            <td>
                                                <div class="doctor-profile-cell">

                                                    <?php if ($photo !== ''): ?>
                                                        <img
                                                            class="doctor-photo"
                                                            src="<?= dataDokterEscape($photo) ?>"
                                                            alt="Foto <?= dataDokterEscape((string) $item['name']) ?>"
                                                            loading="lazy">
                                                    <?php else: ?>
                                                        <div class="doctor-photo doctor-photo-placeholder">
                                                            <?= dataDokterEscape(
                                                                strtoupper(
                                                                    mb_substr(
                                                                        (string) $item['name'],
                                                                        0,
                                                                        1,
                                                                        'UTF-8'
                                                                    )
                                                                )
                                                            ) ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div>
                                                        <div class="doctor-name">
                                                            <?= dataDokterEscape((string) $item['name']) ?>
                                                        </div>
                                                        <div class="doctor-subtext">
                                                            <?= dataDokterEscape((string) ($item['biography'] ?? '') !== ''
                                                                ? mb_strimwidth(
                                                                    (string) $item['biography'],
                                                                    0,
                                                                    65,
                                                                    '...',
                                                                    'UTF-8'
                                                                )
                                                                : 'Belum ada biografi') ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <?= dataDokterEscape((string) $item['specialty']) ?>
                                            </td>

                                            <td>
                                                <?= dataDokterEscape(
                                                    (string) (($item['education'] ?? '') ?: '-')
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= dataDokterEscape(
                                                    (string) (($item['experience'] ?? '') ?: '-')
                                                ) ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= dataDokterEscape((string) $item['work_days']) ?>
                                                </strong>
                                                <div class="doctor-subtext">
                                                    <?= dataDokterEscape($startTime) ?>
                                                    –
                                                    <?= dataDokterEscape($endTime) ?>
                                                </div>
                                                <div class="doctor-subtext">
                                                    Ruang:
                                                    <?= dataDokterEscape((string) $item['room']) ?>
                                                </div>
                                            </td>

                                            <td>
                                                <span class="doctor-status <?= dataDokterEscape(
                                                                                in_array($status, ['available', 'busy'], true)
                                                                                    ? $status
                                                                                    : ''
                                                                            ) ?>">
                                                    <?= dataDokterEscape($statusLabel) ?>
                                                </span>
                                            </td>

                                            <td class="doctor-action">
                                                <a
                                                    class="btn btn-secondary"
                                                    href="tambah_dokter.php?edit=<?= (int) $item['id'] ?>">
                                                    Edit
                                                </a>
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