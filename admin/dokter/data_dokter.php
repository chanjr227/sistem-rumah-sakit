<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

$statuses = [
    'available' => 'Tersedia',
    'busy' => 'Penuh',
];

$specialties = [
    'Jantung',
    'Anak',
    'Neurologi',
    'Bedah',
    'Umum',
];

$notice = $_SESSION['admin_notice'] ?? null;
unset($_SESSION['admin_notice']);

$keyword = trim($_GET['q'] ?? '');
$specialty = trim($_GET['specialty'] ?? '');
$status = trim($_GET['status'] ?? '');

$doctors = [];

try {
    $connection = getDatabaseConnection();

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
        WHERE 1 = 1
    ';

    $params = [];

    if ($keyword !== '') {
        $sql .= ' AND d.name LIKE :keyword';
        $params['keyword'] = '%' . $keyword . '%';
    }

    if (in_array($specialty, $specialties, true)) {
        $sql .= ' AND d.specialty = :specialty';
        $params['specialty'] = $specialty;
    }

    if (array_key_exists($status, $statuses)) {
        $sql .= ' AND d.status = :status';
        $params['status'] = $status;
    }

    $sql .= ' ORDER BY d.name ASC';

    $stmt = $connection->prepare($sql);
    $stmt->execute($params);

    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log(
        'Admin doctor list error: ' . $exception->getMessage()
    );

    $notice = [
        'type' => 'error',
        'message' => 'Daftar dokter tidak dapat dimuat. Periksa koneksi database.',
    ];
}

$csrfToken = adminCsrfToken();

function doctorEscape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/**
 * Foto diasumsikan tersimpan sebagai path relatif
 * dari folder utama project, misalnya:
 * uploads/doctors/dokter1.jpg
 */
function doctorPhotoUrl(?string $photo): ?string
{
    if ($photo === null || trim($photo) === '') {
        return null;
    }

    $photo = trim($photo);

    // Mendukung URL gambar eksternal atau path absolut.
    if (preg_match('~^(https?://|/)~i', $photo)) {
        return $photo;
    }

    return '../../' . ltrim($photo, '/');
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Data Dokter - RS Medika Sehat</title>

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">

    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .doctor-filter-form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .doctor-filter-form input,
        .doctor-filter-form select {
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid #dce3ea;
            border-radius: 9px;
            background: #fff;
            color: #263445;
            font: inherit;
        }

        .doctor-filter-form input {
            flex: 1;
            min-width: 180px;
        }

        .doctor-profile-cell {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            min-width: 230px;
        }

        .doctor-photo {
            display: block;
            flex-shrink: 0;
            width: 58px;
            height: 68px;
            object-fit: cover;
            border-radius: 10px;
            background: #edf2f7;
        }

        .doctor-photo-placeholder {
            display: flex;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            width: 58px;
            height: 68px;
            border-radius: 10px;
            background: #e6f5f2;
            color: #087e73;
            font-size: 12px;
            text-align: center;
        }

        .doctor-profile-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 0;
        }

        .doctor-profile-info strong {
            color: #243447;
        }

        .doctor-profile-info small {
            color: #718096;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .doctor-detail {
            min-width: 150px;
            max-width: 260px;
            white-space: normal;
            overflow-wrap: anywhere;
            line-height: 1.7;
        }

        .doctor-table-scroll {
            overflow-x: auto;
            width: 100%;
        }

        .doctor-table-scroll .admin-table {
            min-width: 1050px;
        }

        .doctor-status {
            white-space: nowrap;
        }

        @media (max-width: 640px) {
            .doctor-filter-form {
                align-items: stretch;
                flex-direction: column;
            }

            .doctor-filter-form input,
            .doctor-filter-form select {
                width: 100%;
                box-sizing: border-box;
            }
        }
    </style>
</head>

<body class="admin-page">

    <header class="admin-header">
        <a class="admin-brand" href="../index.php">
            RS Medika Sehat
            <span>Dashboard Admin</span>
        </a>

        <div class="admin-header-actions">
            <button
                class="admin-menu-toggle"
                type="button"
                aria-expanded="false"
                aria-controls="adminSidebar"
                data-sidebar-toggle>
                Menu
            </button>

            <a class="admin-back-link" href="../index.php">
                Lihat Beranda
            </a>

            <form method="post" action="../logout.php">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= doctorEscape($csrfToken) ?>">

                <button class="btn btn-secondary" type="submit">
                    Keluar
                </button>
            </form>
        </div>
    </header>

    <div class="admin-layout">

        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <!-- KONTEN UTAMA -->
        <main class="admin-main" id="dashboard">

            <div class="admin-page-heading">
                <div>
                    <p class="eyebrow">Manajemen rumah sakit</p>
                    <h1>Data Dokter</h1>

                    <p class="admin-intro">
                        Kelola profil, informasi, dan data dokter
                        RS Medika Sehat.
                    </p>
                </div>

                <span class="admin-count">
                    <?= count($doctors) ?> dokter
                </span>
            </div>

            <!-- NOTIFIKASI -->
            <?php if (is_array($notice) && isset($notice['message'])): ?>

                <p
                    class="admin-alert <?= doctorEscape(
                                            (string) ($notice['type'] ?? 'error')
                                        ) ?>"
                    role="status">

                    <?= doctorEscape((string) $notice['message']) ?>

                </p>

            <?php endif; ?>

            <section class="admin-panel admin-table-panel">

                <div class="admin-page-heading">
                    <div>
                        <h2>Daftar Dokter</h2>

                        <p class="admin-intro">
                            Informasi profil, pendidikan, pengalaman,
                            dan jadwal praktik dokter.
                        </p>
                    </div>

                    <a
                        class="btn btn-primary"
                        href="tambah_dokter.php">
                        + Tambah Dokter
                    </a>
                </div>

                <!-- PENCARIAN DAN FILTER -->
                <form
                    method="get"
                    action="data_dokter.php"
                    class="doctor-filter-form">

                    <input
                        type="search"
                        name="q"
                        placeholder="Cari nama dokter..."
                        value="<?= doctorEscape($keyword) ?>"
                        aria-label="Cari nama dokter">

                    <select
                        name="specialty"
                        aria-label="Filter spesialisasi">

                        <option value="">Semua spesialisasi</option>

                        <?php foreach ($specialties as $item): ?>
                            <option
                                value="<?= doctorEscape($item) ?>"
                                <?= $specialty === $item ? 'selected' : '' ?>>

                                <?= doctorEscape($item) ?>

                            </option>
                        <?php endforeach; ?>

                    </select>

                    <select
                        name="status"
                        aria-label="Filter status dokter">

                        <option value="">Semua status</option>

                        <?php foreach ($statuses as $key => $label): ?>
                            <option
                                value="<?= doctorEscape($key) ?>"
                                <?= $status === $key ? 'selected' : '' ?>>

                                <?= doctorEscape($label) ?>

                            </option>
                        <?php endforeach; ?>

                    </select>

                    <button class="btn btn-primary" type="submit">
                        Cari
                    </button>

                    <a
                        class="btn btn-secondary"
                        href="data_dokter.php">
                        Reset
                    </a>

                </form>

                <!-- TABEL DOKTER -->
                <?php if ($doctors === []): ?>

                    <p class="admin-empty-state">
                        Belum ada data dokter yang sesuai dengan pencarian.
                    </p>

                <?php else: ?>

                    <div class="admin-table-wrap doctor-table-scroll">

                        <table class="admin-table">

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

                                <?php foreach ($doctors as $doctor): ?>

                                    <tr>

                                        <!-- FOTO DAN NAMA -->
                                        <td>
                                            <div class="doctor-profile-cell">

                                                <?php
                                                $photoUrl = doctorPhotoUrl(
                                                    isset($doctor['photo'])
                                                        ? (string) $doctor['photo']
                                                        : null
                                                );
                                                ?>

                                                <?php if ($photoUrl !== null): ?>

                                                    <img
                                                        class="doctor-photo"
                                                        src="<?= doctorEscape($photoUrl) ?>"
                                                        alt="Foto <?= doctorEscape(
                                                                        (string) $doctor['name']
                                                                    ) ?>"
                                                        loading="lazy">

                                                <?php else: ?>

                                                    <div
                                                        class="doctor-photo-placeholder"
                                                        aria-label="Foto belum tersedia">
                                                        No Photo
                                                    </div>

                                                <?php endif; ?>

                                                <div class="doctor-profile-info">

                                                    <strong>
                                                        <?= doctorEscape(
                                                            (string) $doctor['name']
                                                        ) ?>
                                                    </strong>

                                                    <small>
                                                        <?= doctorEscape(
                                                            (string) (
                                                                $doctor['biography']
                                                                ?? 'Biografi belum tersedia.'
                                                            )
                                                        ) ?>
                                                    </small>

                                                </div>

                                            </div>
                                        </td>

                                        <!-- SPESIALISASI -->
                                        <td>
                                            <?= doctorEscape(
                                                (string) $doctor['specialty']
                                            ) ?>
                                        </td>

                                        <!-- PENDIDIKAN -->
                                        <td>
                                            <div class="doctor-detail">
                                                <?= doctorEscape(
                                                    (string) (
                                                        $doctor['education']
                                                        ?: 'Belum diisi'
                                                    )
                                                ) ?>
                                            </div>
                                        </td>

                                        <!-- PENGALAMAN -->
                                        <td>
                                            <div class="doctor-detail">
                                                <?= doctorEscape(
                                                    (string) (
                                                        $doctor['experience']
                                                        ?: 'Belum diisi'
                                                    )
                                                ) ?>
                                            </div>
                                        </td>

                                        <!-- JADWAL PRAKTIK -->
                                        <td>
                                            <div class="doctor-detail">

                                                <?= doctorEscape(
                                                    (string) (
                                                        $doctor['work_days']
                                                        ?? '-'
                                                    )
                                                ) ?>

                                                <br>

                                                <?= doctorEscape(
                                                    substr(
                                                        (string) $doctor['start_time'],
                                                        0,
                                                        5
                                                    )
                                                ) ?>

                                                -

                                                <?= doctorEscape(
                                                    substr(
                                                        (string) $doctor['end_time'],
                                                        0,
                                                        5
                                                    )
                                                ) ?>

                                                <br>

                                                Ruang
                                                <?= doctorEscape(
                                                    (string) (
                                                        $doctor['room'] ?? '-'
                                                    )
                                                ) ?>

                                            </div>
                                        </td>

                                        <!-- STATUS -->
                                        <td>
                                            <span class="admin-status <?= doctorEscape(
                                                                            (string) $doctor['status']
                                                                        ) ?> doctor-status">

                                                <?= doctorEscape(
                                                    $statuses[(string) $doctor['status']] ?? 'Tidak diketahui'
                                                ) ?>

                                            </span>
                                        </td>

                                        <!-- AKSI -->
                                        <td>
                                            <div class="admin-row-actions">

                                                <a
                                                    class="admin-edit-link"
                                                    href="../index.php?edit=<?= (int) $doctor['id'] ?>">

                                                    Ubah

                                                </a>

                                            </div>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </section>

        </main>

    </div>

</body>

</html>