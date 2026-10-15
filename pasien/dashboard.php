<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

requirePatientLogin();

$connection = getDatabaseConnection();
$patientStatement = $connection->prepare(
    'SELECT id, name, email, phone FROM patients WHERE id = :id LIMIT 1'
);
$patientStatement->execute(['id' => (int) $_SESSION['patient_id']]);
$patient = $patientStatement->fetch();

if (!$patient) {
    unset($_SESSION['patient_id'], $_SESSION['patient_name'], $_SESSION['patient_email']);
    header('Location: login.php');
    exit;
}

$notice = $_SESSION['patient_notice'] ?? null;
unset($_SESSION['patient_notice']);
$bookableSpecialties = $connection->query(
    "SELECT DISTINCT specialty FROM doctors WHERE status = 'available' ORDER BY specialty ASC"
)->fetchAll(PDO::FETCH_COLUMN);
$appointmentsStatement = $connection->prepare(
    'SELECT id, specialty, visit_date, status, created_at
     FROM appointments WHERE patient_id = :patient_id
     ORDER BY visit_date DESC, id DESC'
);
$appointmentsStatement->execute(['patient_id' => (int) $patient['id']]);
$appointments = $appointmentsStatement->fetchAll();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyPatientCsrfToken()) {
        http_response_code(403);
        $notice = ['type' => 'error', 'message' => 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.'];
    } else {
        $specialtyValue = $_POST['specialty'] ?? '';
        $dateValue = $_POST['visit_date'] ?? '';
        $specialty = is_string($specialtyValue) ? trim($specialtyValue) : '';
        $visitDate = is_string($dateValue) ? trim($dateValue) : '';
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $visitDate);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if (!in_array($specialty, $bookableSpecialties, true)) {
            http_response_code(422);
            $notice = ['type' => 'error', 'message' => 'Poli tersebut tidak tersedia untuk pemesanan.'];
        } elseif (
            $parsedDate === false
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $parsedDate->format('Y-m-d') !== $visitDate
            || $visitDate < (new DateTimeImmutable('today'))->format('Y-m-d')
        ) {
            http_response_code(422);
            $notice = ['type' => 'error', 'message' => 'Tanggal konsultasi tidak valid atau sudah berlalu.'];
        } else {
            $insertStatement = $connection->prepare(
                'INSERT INTO appointments (patient_id, name, email, specialty, visit_date)
                 VALUES (:patient_id, :name, :email, :specialty, :visit_date)'
            );
            $insertStatement->execute([
                'patient_id' => (int) $patient['id'],
                'name' => $patient['name'],
                'email' => $patient['email'],
                'specialty' => $specialty,
                'visit_date' => $visitDate,
            ]);

            $_SESSION['patient_notice'] = [
                'type' => 'success',
                'message' => 'Permintaan konsultasi berhasil dikirim.',
            ];
            header('Location: dashboard.php');
            exit;
        }
    }
}

$csrfToken = patientCsrfToken();
$statusLabels = [
    'pending' => 'Menunggu konfirmasi',
    'confirmed' => 'Dikonfirmasi',
    'cancelled' => 'Dibatalkan',
];
?>
<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard Pasien - RS Medika Sehat</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="../css/base.css" />
    <link rel="stylesheet" href="../css/pages/patient.css" />
    <script src="../js/pages/patient.js" defer></script>
  </head>
  <body class="patient-page">
    <header class="patient-header">
      <a class="patient-brand" href="../index.php">RS Medika Sehat <span>Portal Pasien</span></a>
      <div class="patient-header-actions">
        <button
          class="patient-menu-toggle"
          type="button"
          aria-expanded="false"
          aria-controls="patientSidebar"
          data-sidebar-toggle
        >Menu</button>
        <a class="patient-back-link" href="../index.php">Lihat beranda</a>
        <form method="post" action="logout.php">
          <input type="hidden" name="csrf_token" value="<?= escapePatientHtml($csrfToken) ?>" />
          <button class="btn btn-secondary" type="submit">Keluar</button>
        </form>
      </div>
    </header>

    <div class="patient-layout">
      <aside class="patient-sidebar" id="patientSidebar">
        <p class="patient-sidebar-label">Menu Pasien</p>
        <nav class="patient-sidebar-nav" aria-label="Navigasi dashboard pasien">
          <a href="#dashboard" aria-current="page">Ringkasan</a>
          <a href="#profile">Profil Pasien</a>
          <a href="#booking">Buat Janji Konsultasi</a>
          <a href="#appointments">Janji Konsultasi Saya</a>
        </nav>
        <a class="patient-sidebar-home" href="../index.php">Kembali ke beranda</a>
      </aside>

      <main class="patient-dashboard" id="dashboard">
        <section class="patient-dashboard-heading">
        <div>
          <p class="eyebrow">Portal pasien</p>
          <h1>Halo, <?= escapePatientHtml((string) $patient['name']) ?></h1>
          <p class="patient-intro">Kelola janji konsultasi dan informasi akun Anda.</p>
        </div>
      </section>

      <?php if (is_array($notice) && isset($notice['message'])): ?>
        <p class="patient-alert <?= escapePatientHtml((string) ($notice['type'] ?? 'error')) ?>" role="status">
          <?= escapePatientHtml((string) $notice['message']) ?>
        </p>
      <?php endif; ?>

      <div class="patient-dashboard-grid">
        <section class="patient-panel" id="profile">
          <h2>Profil pasien</h2>
          <dl class="patient-profile">
            <div><dt>Nama</dt><dd><?= escapePatientHtml((string) $patient['name']) ?></dd></div>
            <div><dt>Email</dt><dd><?= escapePatientHtml((string) $patient['email']) ?></dd></div>
            <div><dt>Telepon</dt><dd><?= escapePatientHtml((string) ($patient['phone'] ?: '-')) ?></dd></div>
          </dl>
        </section>

        <section class="patient-panel" id="booking">
          <h2>Buat janji konsultasi</h2>
          <?php if ($bookableSpecialties === []): ?>
            <p class="patient-empty-state">Belum ada dokter tersedia untuk pemesanan saat ini.</p>
          <?php else: ?>
            <form class="patient-form" method="post" action="dashboard.php">
              <input type="hidden" name="csrf_token" value="<?= escapePatientHtml($csrfToken) ?>" />
              <label>
                Poli tujuan
                <select name="specialty" required>
                  <option value="">Pilih poli</option>
                  <?php foreach ($bookableSpecialties as $specialty): ?>
                    <option value="<?= escapePatientHtml((string) $specialty) ?>">
                      <?= escapePatientHtml((string) $specialty) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>
                Tanggal konsultasi
                <input type="date" name="visit_date" min="<?= escapePatientHtml((new DateTimeImmutable('today'))->format('Y-m-d')) ?>" required />
              </label>
              <button class="btn btn-primary full-width" type="submit">Kirim permintaan</button>
            </form>
          <?php endif; ?>
        </section>

        <section class="patient-panel patient-appointments-panel" id="appointments">
          <h2>Janji konsultasi saya</h2>
          <?php if ($appointments === []): ?>
            <p class="patient-empty-state">Belum ada janji konsultasi. Gunakan formulir di atas untuk membuat janji.</p>
          <?php else: ?>
            <div class="patient-table-wrap">
              <table class="patient-table">
                <thead>
                  <tr>
                    <th>Poli</th>
                    <th>Tanggal</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($appointments as $appointment): ?>
                    <tr>
                      <td><?= escapePatientHtml((string) $appointment['specialty']) ?></td>
                      <td><?= escapePatientHtml((string) $appointment['visit_date']) ?></td>
                      <td><?= escapePatientHtml($statusLabels[(string) $appointment['status']] ?? 'Tidak diketahui') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>
      </div>
      </main>
    </div>
  </body>
</html>
