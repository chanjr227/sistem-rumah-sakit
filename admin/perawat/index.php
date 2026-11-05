<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

requireNurseLogin();

$nurseName = (string) ($_SESSION['admin_username'] ?? 'Perawat');

function dashboardPerawatEscape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Perawat - RS Medika Sehat</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">

    <script src="../../js/pages/admin.js" defer></script>
</head>

<body class="admin-page">
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <div class="admin-layout">
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="admin-main" id="dashboard">
            <div class="admin-page-heading">
                <div>
                    <p class="eyebrow">Panel layanan keperawatan</p>
                    <h1>Dashboard Perawat</h1>
                    <p class="admin-intro">
                        Selamat datang, <?= dashboardPerawatEscape($nurseName) ?>.
                        Pilih menu untuk mengelola layanan keperawatan.
                    </p>
                </div>
            </div>

            <section class="admin-dashboard-stats" aria-label="Menu layanan perawat">
                <article class="admin-panel admin-stat-card">
                    <div class="admin-stat-top">
                        <span class="admin-stat-icon">♙</span>
                        <span class="admin-stat-label">Data Pasien</span>
                    </div>
                    <p class="admin-stat-description">
                        Lihat dan kelola informasi pasien.
                    </p>
                    <a href="data_pasien.php" class="admin-stat-link">
                        Buka data pasien →
                    </a>
                </article>

                <article class="admin-panel admin-stat-card">
                    <div class="admin-stat-top">
                        <span class="admin-stat-icon">✚</span>
                        <span class="admin-stat-label">Catatan Keperawatan</span>
                    </div>
                    <p class="admin-stat-description">
                        Catat kondisi dan tindakan keperawatan pasien.
                    </p>
                    <a href="catatan_keperawatan.php" class="admin-stat-link">
                        Buka catatan keperawatan →
                    </a>
                </article>

                <article class="admin-panel admin-stat-card">
                    <div class="admin-stat-top">
                        <span class="admin-stat-icon">◷</span>
                        <span class="admin-stat-label">Pemantauan Pasien</span>
                    </div>
                    <p class="admin-stat-description">
                        Pantau kondisi pasien dan riwayat pengukuran.
                    </p>
                    <a href="pemantauan_pasien.php" class="admin-stat-link">
                        Buka pemantauan pasien →
                    </a>
                </article>

                <article class="admin-panel admin-stat-card">
                    <div class="admin-stat-top">
                        <span class="admin-stat-icon">▤</span>
                        <span class="admin-stat-label">Laporan Keperawatan</span>
                    </div>
                    <p class="admin-stat-description">
                        Tinjau rekap pemantauan pasien.
                    </p>
                    <a href="laporan.php" class="admin-stat-link">
                        Buka laporan →
                    </a>
                </article>

                <article class="admin-panel admin-stat-card">
                    <div class="admin-stat-top">
                        <span class="admin-stat-icon">▣</span>
                        <span class="admin-stat-label">Riwayat Kunjungan</span>
                    </div>
                    <p class="admin-stat-description">
                        Lihat riwayat kunjungan pasien.
                    </p>
                    <a href="../pasien/riwayat_kunjungan.php" class="admin-stat-link">
                        Buka riwayat kunjungan →
                    </a>
                </article>

                <article class="admin-panel admin-stat-card">
                    <div class="admin-stat-top">
                        <span class="admin-stat-icon">▤</span>
                        <span class="admin-stat-label">Rekam Medis</span>
                    </div>
                    <p class="admin-stat-description">
                        Tinjau rekam medis pasien.
                    </p>
                    <a href="../pasien/rekam_medis.php" class="admin-stat-link">
                        Buka rekam medis →
                    </a>
                </article>
            </section>
        </main>
    </div>
</body>

</html>
