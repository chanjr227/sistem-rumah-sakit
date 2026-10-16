<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

$statuses = [
    'available' => 'Tersedia',
    'busy' => 'Penuh',
];

$notice = $_SESSION['admin_notice'] ?? null;
unset($_SESSION['admin_notice']);

$doctors = [];

try {
    $connection = getDatabaseConnection();

    $doctors = $connection->query(
        'SELECT id, name, specialty, work_days, start_time,
                end_time, room, status
         FROM doctors
         ORDER BY name ASC'
    )->fetchAll();
} catch (PDOException $exception) {
    error_log('Admin doctor list error: ' . $exception->getMessage());
    $notice = [
        'type' => 'error',
        'message' => 'Daftar dokter tidak dapat dimuat. Periksa koneksi database.'
    ];
}

$csrfToken = adminCsrfToken();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Dokter - RS Medika Sehat</title>

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">
    <script src="../../js/pages/admin.js" defer></script>
</head>

<body class="admin-page">
    <header class="admin-header">
        <a class="admin-brand" href="index.php">
            RS Medika Sehat <span>Dashboard Admin</span>
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

            <a class="admin-back-link" href="../index.php">Lihat Beranda</a>

            <form method="post" action="logout.php">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escapeHtml($csrfToken) ?>">
                <button class="btn btn-secondary" type="submit">Keluar</button>
            </form>
        </div>
    </header>

    <div class="admin-layout">
        <aside class="admin-sidebar" id="adminSidebar">
            <p class="admin-sidebar-label">Menu Admin</p>

            <nav class="admin-sidebar-nav" aria-label="Navigasi dashboard admin">
                <a href="index.php#dashboard" class="admin-nav-link">
                    <span class="admin-nav-icon">⌂</span>
                    <span>Dashboard</span>
                </a>

                <div class="admin-nav-group open" data-nav-group>
                    <button
                        type="button"
                        class="admin-nav-parent active"
                        aria-expanded="true"
                        aria-controls="doctorSubmenu"
                        data-submenu-toggle>
                        <span class="admin-nav-icon">✚</span>
                        <span class="admin-nav-text">Dokter</span>
                        <span class="admin-nav-arrow">⌄</span>
                    </button>

                    <div class="admin-submenu" id="doctorSubmenu">
                        <a href="data_dokter.php"
                            class="admin-submenu-link active">
                            Data Dokter
                        </a>

                        <a href="tambah_dokter.php"
                            class="admin-submenu-link">
                            Tambah Dokter
                        </a>

                        <a href="data_dokter.php#jadwal-praktik"
                            class="admin-submenu-link">
                            Jadwal Praktik
                        </a>
                    </div>
                </div>
            </nav>

            <a class="admin-sidebar-home" href="../index.php">
                Kembali ke beranda
            </a>
        </aside>

        <main class="admin-main" id="dashboard">
            <div class="admin-page-heading">
                <div>
                    <p class="eyebrow">Manajemen rumah sakit</p>
                    <h1>Data Dokter</h1>
                    <p class="admin-intro">
                        Kelola informasi dokter dan jadwal praktik rumah sakit.
                    </p>
                </div>

                <span class="admin-count">
                    <?= count($doctors) ?> dokter
                </span>
            </div>

            <?php if (is_array($notice) && isset($notice['message'])): ?>
                <p
                    class="admin-alert <?= escapeHtml((string) ($notice['type'] ?? 'error')) ?>"
                    role="status">
                    <?= escapeHtml((string) $notice['message']) ?>
                </p>
            <?php endif; ?>

            <section class="admin-panel admin-table-panel" id="jadwal-praktik">
                <div class="admin-page-heading">
                    <div>
                        <h2>Daftar Dokter</h2>
                    </div>

                    <a class="btn btn-primary" href="dokter/tambah_dokter.php">
                        + Tambah Dokter
                    </a>
                </div>

                <?php if ($doctors === []): ?>
                    <p class="admin-empty-state">
                        Belum ada data dokter. Silakan tambahkan dokter terlebih dahulu.
                    </p>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Dokter</th>
                                    <th>Jadwal Praktik</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($doctors as $doctor): ?>
                                    <tr>
                                        <td>
                                            <strong>
                                                <?= escapeHtml((string) $doctor['name']) ?>
                                            </strong>
                                            <span>
                                                <?= escapeHtml((string) $doctor['specialty']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= escapeHtml((string) $doctor['work_days']) ?><br>
                                            <?= escapeHtml(substr((string) $doctor['start_time'], 0, 5)) ?>
                                            -
                                            <?= escapeHtml(substr((string) $doctor['end_time'], 0, 5)) ?><br>
                                            Ruang <?= escapeHtml((string) $doctor['room']) ?>
                                        </td>

                                        <td>
                                            <span class="admin-status <?= escapeHtml((string) $doctor['status']) ?>">
                                                <?= escapeHtml($statuses[(string) $doctor['status']] ?? 'Tidak diketahui') ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="admin-row-actions">
                                                <a
                                                    class="admin-edit-link"
                                                    href="index.php?edit=<?= (int) $doctor['id'] ?>">
                                                    Ubah
                                                </a>

                                                <form
                                                    method="post"
                                                    action="index.php"
                                                    data-delete-doctor>
                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= escapeHtml($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int) $doctor['id'] ?>">

                                                    <button
                                                        class="admin-delete-button"
                                                        type="submit">
                                                        Hapus
                                                    </button>
                                                </form>
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