<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdminLogin();

$csrfToken = adminCsrfToken();
$notice = $_SESSION['admin_notice'] ?? null;
unset($_SESSION['admin_notice']);

$stats = [
  'total_doctors' => 0,
  'available_doctors' => 0,
  'busy_doctors' => 0,
  'specialties' => 0,
];

$recentDoctors = [];
$databaseError = false;

try {
  $connection = getDatabaseConnection();

  // Ringkasan data dokter
  $statement = $connection->query(
    "SELECT
            COUNT(*) AS total_doctors,
            COALESCE(SUM(status = 'available'), 0) AS available_doctors,
            COALESCE(SUM(status = 'busy'), 0) AS busy_doctors,
            COUNT(DISTINCT specialty) AS specialties
         FROM doctors"
  );

  $result = $statement->fetch();

  if ($result) {
    $stats = [
      'total_doctors' => (int) $result['total_doctors'],
      'available_doctors' => (int) $result['available_doctors'],
      'busy_doctors' => (int) $result['busy_doctors'],
      'specialties' => (int) $result['specialties'],
    ];
  }

  // Lima dokter terbaru berdasarkan ID
  $recentDoctors = $connection->query(
    "SELECT id, name, specialty, work_days,
                start_time, end_time, room, status
         FROM doctors
         ORDER BY id DESC
         LIMIT 5"
  )->fetchAll();
} catch (PDOException $exception) {
  error_log('Admin dashboard database error: ' . $exception->getMessage());
  $databaseError = true;
}

$statuses = [
  'available' => 'Tersedia',
  'busy' => 'Penuh',
];

?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Dashboard Admin - RS Medika Sehat</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">

  <link rel="stylesheet" href="../css/base.css">
  <link rel="stylesheet" href="../css/pages/admin.css">

  <script src="../js/pages/admin.js" defer></script>
</head>

<body class="admin-page">

  <header class="admin-header">
    <a class="admin-brand" href="index.php">
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

      <form method="post" action="logout.php">
        <input
          type="hidden"
          name="csrf_token"
          value="<?= escapeHtml($csrfToken) ?>">

        <button class="btn btn-secondary" type="submit">
          Keluar
        </button>
      </form>
    </div>
  </header>

  <div class="admin-layout">

    <aside class="admin-sidebar" id="adminSidebar">
      <p class="admin-sidebar-label">Menu Admin</p>

      <nav class="admin-sidebar-nav" aria-label="Navigasi dashboard admin">

        <a href="index.php#dashboard"
          class="admin-nav-link active">
          <span class="admin-nav-icon">⌂</span>
          <span>Dashboard</span>
        </a>

        <div class="admin-nav-group open" data-nav-group>
          <button
            type="button"
            class="admin-nav-parent"
            aria-expanded="true"
            aria-controls="doctorSubmenu"
            data-submenu-toggle>

            <span class="admin-nav-icon">✚</span>
            <span class="admin-nav-text">Dokter</span>
            <span class="admin-nav-arrow">⌄</span>
          </button>

          <div class="admin-submenu" id="doctorSubmenu">
            <a href="dokter/data_dokter.php"
              class="admin-submenu-link">
              Data Dokter
            </a>

            <a href="dokter/tambah_dokter.php"
              class="admin-submenu-link">
              Tambah Dokter
            </a>

            <a href="data_dokter.php#jadwal-praktik"
              class="admin-submenu-link">
              Jadwal Praktik
            </a>
          </div>
        </div>

        <!-- Menu berikutnya dapat diaktifkan setelah halamannya dibuat. -->
        <div class="admin-nav-group" data-nav-group>
          <button
            type="button"
            class="admin-nav-parent"
            aria-expanded="false"
            aria-controls="hospitalSubmenu"
            data-submenu-toggle>

            <span class="admin-nav-icon">▤</span>
            <span class="admin-nav-text">Data Rumah Sakit</span>
            <span class="admin-nav-arrow">⌄</span>
          </button>

          <div class="admin-submenu" id="hospitalSubmenu">
            <a href="../index.php"
              class="admin-submenu-link">
              Informasi Rumah Sakit
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
          <p class="eyebrow">Pusat kendali administrasi</p>
          <h1>Dashboard Utama</h1>
          <p class="admin-intro">
            Selamat datang di panel administrasi RS Medika Sehat.
            Pantau ringkasan data rumah sakit dari satu halaman.
          </p>
        </div>
      </div>

      <?php if (is_array($notice) && isset($notice['message'])): ?>
        <p
          class="admin-alert <?= escapeHtml((string) ($notice['type'] ?? 'error')) ?>"
          role="status">
          <?= escapeHtml((string) $notice['message']) ?>
        </p>
      <?php endif; ?>

      <?php if ($databaseError): ?>
        <p class="admin-alert error" role="alert">
          Data dashboard tidak dapat dimuat. Periksa koneksi database.
        </p>
      <?php endif; ?>

      <section class="admin-dashboard-stats" aria-label="Ringkasan data dokter">

        <article class="admin-panel admin-stat-card">
          <div class="admin-stat-top">
            <span class="admin-stat-icon">♙</span>
            <span class="admin-stat-label">Total Dokter</span>
          </div>

          <p class="admin-stat-number">
            <?= number_format($stats['total_doctors'], 0, ',', '.') ?>
          </p>

          <p class="admin-stat-description">
            Dokter terdaftar dalam sistem
          </p>

          <a href="data_dokter.php" class="admin-stat-link">
            Lihat data dokter →
          </a>
        </article>

        <article class="admin-panel admin-stat-card">
          <div class="admin-stat-top">
            <span class="admin-stat-icon">✓</span>
            <span class="admin-stat-label">Dokter Tersedia</span>
          </div>

          <p class="admin-stat-number">
            <?= number_format($stats['available_doctors'], 0, ',', '.') ?>
          </p>

          <p class="admin-stat-description">
            Status dokter tersedia
          </p>

          <a href="data_dokter.php" class="admin-stat-link">
            Lihat ketersediaan →
          </a>
        </article>

        <article class="admin-panel admin-stat-card">
          <div class="admin-stat-top">
            <span class="admin-stat-icon">◷</span>
            <span class="admin-stat-label">Dokter Penuh</span>
          </div>

          <p class="admin-stat-number">
            <?= number_format($stats['busy_doctors'], 0, ',', '.') ?>
          </p>

          <p class="admin-stat-description">
            Status dokter penuh
          </p>

          <a href="data_dokter.php" class="admin-stat-link">
            Lihat status →
          </a>
        </article>

        <article class="admin-panel admin-stat-card">
          <div class="admin-stat-top">
            <span class="admin-stat-icon">✚</span>
            <span class="admin-stat-label">Spesialisasi</span>
          </div>

          <p class="admin-stat-number">
            <?= number_format($stats['specialties'], 0, ',', '.') ?>
          </p>

          <p class="admin-stat-description">
            Jenis spesialisasi dokter
          </p>

          <a href="data_dokter.php" class="admin-stat-link">
            Lihat spesialisasi →
          </a>
        </article>

      </section>

      <section class="admin-panel admin-table-panel admin-dashboard-recent">
        <div class="admin-page-heading">
          <div>
            <p class="eyebrow">Informasi terbaru</p>
            <h2>Dokter Terbaru</h2>
            <p class="admin-intro">
              Lima data dokter terakhir yang ditambahkan ke sistem.
            </p>
          </div>

          <a href="data_dokter.php" class="btn btn-primary">
            Semua Dokter
          </a>
        </div>

        <?php if (!$databaseError && $recentDoctors === []): ?>
          <p class="admin-empty-state">
            Belum ada data dokter. Data yang ditambahkan akan
            ditampilkan di bagian ini.
          </p>

        <?php elseif (!$databaseError): ?>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Dokter</th>
                  <th>Spesialisasi</th>
                  <th>Jadwal Praktik</th>
                  <th>Status</th>
                </tr>
              </thead>

              <tbody>
                <?php foreach ($recentDoctors as $doctor): ?>
                  <tr>
                    <td>
                      <strong>
                        <?= escapeHtml((string) $doctor['name']) ?>
                      </strong>
                      <span>
                        Ruang <?= escapeHtml((string) $doctor['room']) ?>
                      </span>
                    </td>

                    <td>
                      <?= escapeHtml((string) $doctor['specialty']) ?>
                    </td>

                    <td>
                      <?= escapeHtml((string) $doctor['work_days']) ?><br>
                      <?= escapeHtml(substr((string) $doctor['start_time'], 0, 5)) ?>
                      –
                      <?= escapeHtml(substr((string) $doctor['end_time'], 0, 5)) ?>
                    </td>

                    <td>
                      <span class="admin-status <?= escapeHtml((string) $doctor['status']) ?>">
                        <?= escapeHtml(
                          $statuses[(string) $doctor['status']]
                            ?? 'Tidak diketahui'
                        ) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <section class="admin-panel admin-dashboard-welcome">
        <div>
          <p class="eyebrow">Pengelolaan rumah sakit</p>
          <h2>Kelola data dengan lebih mudah</h2>
          <p>
            Gunakan menu di sebelah kiri untuk membuka halaman
            pengelolaan dokter dan informasi rumah sakit.
            Bagian ini dapat dikembangkan untuk menampilkan
            data pasien, poli, kamar, dan informasi lainnya.
          </p>
        </div>

        <a href="data_dokter.php" class="btn btn-primary">
          Buka Data Dokter
        </a>
      </section>

    </main>
  </div>

</body>

</html>