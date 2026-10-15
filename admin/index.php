<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdminLogin();

$specialties = ['Jantung', 'Anak', 'Neurologi', 'Bedah', 'Umum'];
$statuses = [
    'available' => 'Tersedia',
    'busy' => 'Penuh',
];
$notice = $_SESSION['admin_notice'] ?? null;
unset($_SESSION['admin_notice']);
$formValues = [];
$editingDoctor = null;
$doctors = [];

function redirectToDashboard(): never
{
    header('Location: index.php');
    exit;
}

function requiredPostValue(string $key, string $label, int $maxLength): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        throw new InvalidArgumentException($label . ' tidak valid.');
    }

    $value = trim($value);
    if ($value === '' || mb_strlen($value, 'UTF-8') > $maxLength) {
        throw new InvalidArgumentException($label . ' wajib diisi (maksimal ' . $maxLength . ' karakter).');
    }

    return $value;
}

function validTime(string $time): bool
{
    $parsedTime = DateTimeImmutable::createFromFormat('!H:i', $time);
    $errors = DateTimeImmutable::getLastErrors();

    return $parsedTime !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $parsedTime->format('H:i') === $time;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyAdminCsrfToken()) {
        http_response_code(403);
        $notice = ['type' => 'error', 'message' => 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.'];
    } else {
        $action = $_POST['action'] ?? '';

        try {
            $connection = getDatabaseConnection();

            if ($action === 'save') {
                $name = requiredPostValue('name', 'Nama dokter', 255);
                $specialty = requiredPostValue('specialty', 'Spesialisasi', 100);
                $workDays = requiredPostValue('work_days', 'Hari praktik', 100);
                $startTime = requiredPostValue('start_time', 'Jam mulai', 5);
                $endTime = requiredPostValue('end_time', 'Jam selesai', 5);
                $room = requiredPostValue('room', 'Ruang praktik', 100);
                $status = $_POST['status'] ?? '';

                if (!in_array($specialty, $specialties, true)) {
                    throw new InvalidArgumentException('Spesialisasi yang dipilih tidak valid.');
                }
                if (!in_array($status, array_keys($statuses), true)) {
                    throw new InvalidArgumentException('Status dokter yang dipilih tidak valid.');
                }
                if (!validTime($startTime) || !validTime($endTime) || $startTime >= $endTime) {
                    throw new InvalidArgumentException('Rentang jam praktik tidak valid.');
                }

                $doctorId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]);

                if ($doctorId !== false && $doctorId !== null) {
                    $statement = $connection->prepare(
                        'UPDATE doctors
                         SET name = :name, specialty = :specialty, work_days = :work_days,
                             start_time = :start_time, end_time = :end_time, room = :room, status = :status
                         WHERE id = :id'
                    );
                    $statement->execute([
                        'name' => $name,
                        'specialty' => $specialty,
                        'work_days' => $workDays,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'room' => $room,
                        'status' => $status,
                        'id' => $doctorId,
                    ]);
                    if ($statement->rowCount() === 0) {
                        $exists = $connection->prepare('SELECT 1 FROM doctors WHERE id = :id');
                        $exists->execute(['id' => $doctorId]);
                        if (!$exists->fetchColumn()) {
                            throw new InvalidArgumentException('Data dokter tidak ditemukan.');
                        }
                    }
                    $_SESSION['admin_notice'] = ['type' => 'success', 'message' => 'Data dokter berhasil diperbarui.'];
                } else {
                    if (isset($_POST['id']) && $_POST['id'] !== '') {
                        throw new InvalidArgumentException('ID dokter tidak valid.');
                    }

                    $statement = $connection->prepare(
                        'INSERT INTO doctors (name, specialty, work_days, start_time, end_time, room, status)
                         VALUES (:name, :specialty, :work_days, :start_time, :end_time, :room, :status)'
                    );
                    $statement->execute([
                        'name' => $name,
                        'specialty' => $specialty,
                        'work_days' => $workDays,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'room' => $room,
                        'status' => $status,
                    ]);
                    $_SESSION['admin_notice'] = ['type' => 'success', 'message' => 'Data dokter berhasil ditambahkan.'];
                }

                redirectToDashboard();
            }

            if ($action === 'delete') {
                $doctorId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]);
                if ($doctorId === false || $doctorId === null) {
                    throw new InvalidArgumentException('ID dokter tidak valid.');
                }

                $statement = $connection->prepare('DELETE FROM doctors WHERE id = :id');
                $statement->execute(['id' => $doctorId]);
                if ($statement->rowCount() === 0) {
                    throw new InvalidArgumentException('Data dokter tidak ditemukan.');
                }

                $_SESSION['admin_notice'] = ['type' => 'success', 'message' => 'Data dokter berhasil dihapus.'];
                redirectToDashboard();
            }

            throw new InvalidArgumentException('Aksi yang diminta tidak dikenal.');
        } catch (InvalidArgumentException $exception) {
            $notice = ['type' => 'error', 'message' => $exception->getMessage()];
            $formValues = $_POST;
        } catch (PDOException $exception) {
            error_log('Admin doctor database error: ' . $exception->getMessage());
            $notice = ['type' => 'error', 'message' => 'Data dokter tidak dapat disimpan. Periksa koneksi database dan coba lagi.'];
            $formValues = $_POST;
        }
    }
}

try {
    $connection = getDatabaseConnection();
    $doctors = $connection->query(
        'SELECT id, name, specialty, work_days, start_time, end_time, room, status
         FROM doctors
         ORDER BY name ASC'
    )->fetchAll();

    $editId = $_GET['edit'] ?? null;
    if (is_string($editId) && $editId !== '') {
        $validatedEditId = filter_var($editId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($validatedEditId === false) {
            $notice = ['type' => 'error', 'message' => 'ID dokter tidak valid.'];
        } else {
            $statement = $connection->prepare(
                'SELECT id, name, specialty, work_days, start_time, end_time, room, status
                 FROM doctors WHERE id = :id'
            );
            $statement->execute(['id' => $validatedEditId]);
            $editingDoctor = $statement->fetch() ?: null;
            if ($editingDoctor === null) {
                $notice = ['type' => 'error', 'message' => 'Data dokter tidak ditemukan.'];
            }
        }
    }
} catch (PDOException $exception) {
    error_log('Admin doctor list database error: ' . $exception->getMessage());
    $notice = ['type' => 'error', 'message' => 'Daftar dokter tidak dapat dimuat. Periksa koneksi database.'];
}

if ($formValues !== [] && ($formValues['action'] ?? '') === 'save') {
    $editingDoctor = [
        'id' => is_string($formValues['id'] ?? null) ? $formValues['id'] : '',
        'name' => is_string($formValues['name'] ?? null) ? $formValues['name'] : '',
        'specialty' => is_string($formValues['specialty'] ?? null) ? $formValues['specialty'] : '',
        'work_days' => is_string($formValues['work_days'] ?? null) ? $formValues['work_days'] : '',
        'start_time' => is_string($formValues['start_time'] ?? null) ? $formValues['start_time'] : '',
        'end_time' => is_string($formValues['end_time'] ?? null) ? $formValues['end_time'] : '',
        'room' => is_string($formValues['room'] ?? null) ? $formValues['room'] : '',
        'status' => is_string($formValues['status'] ?? null) ? $formValues['status'] : 'available',
    ];
}

$csrfToken = adminCsrfToken();
$isEditing = $editingDoctor !== null;
$formAction = $isEditing ? 'Perbarui Dokter' : 'Tambah Dokter';
?>
<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard Dokter - RS Medika Sehat</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="../css/base.css" />
    <link rel="stylesheet" href="../css/pages/admin.css" />
    <script src="../js/pages/admin.js" defer></script>
  </head>
  <body class="admin-page">
    <header class="admin-header">
      <a class="admin-brand" href="index.php">RS Medika Sehat <span>Dashboard Admin</span></a>
      <div class="admin-header-actions">
        <button
          class="admin-menu-toggle"
          type="button"
          aria-expanded="false"
          aria-controls="adminSidebar"
          data-sidebar-toggle
        >Menu</button>
        <a class="admin-back-link" href="../index.php">Lihat Beranda</a>
        <form method="post" action="logout.php">
          <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>" />
          <button class="btn btn-secondary" type="submit">Keluar</button>
        </form>
      </div>
    </header>

    <div class="admin-layout">
      <aside class="admin-sidebar" id="adminSidebar">
        <p class="admin-sidebar-label">Menu Admin</p>
        <nav class="admin-sidebar-nav" aria-label="Navigasi dashboard admin">
          <a href="#dashboard" aria-current="page">Ringkasan</a>
          <a href="#doctor-form">Tambah dokter</a>
          <a href="#doctor-list">Daftar dokter</a>
        </nav>
        <a class="admin-sidebar-home" href="../index.php">Kembali ke beranda</a>
      </aside>

      <main class="admin-main" id="dashboard">
        <div class="admin-page-heading">
        <div>
          <p class="eyebrow">Manajemen rumah sakit</p>
          <h1>Data Dokter</h1>
          <p class="admin-intro">Tambah dan kelola informasi spesialisasi serta jadwal praktik dokter.</p>
        </div>
        <span class="admin-count"><?= count($doctors) ?> dokter</span>
      </div>

      <?php if (is_array($notice) && isset($notice['message'])): ?>
        <p class="admin-alert <?= escapeHtml((string) ($notice['type'] ?? 'error')) ?>" role="status">
          <?= escapeHtml((string) $notice['message']) ?>
        </p>
      <?php endif; ?>

        <div class="admin-content-grid">
        <section class="admin-panel" id="doctor-form">
          <h2><?= escapeHtml($formAction) ?></h2>
          <form class="admin-form" method="post" action="index.php">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>" />
            <input type="hidden" name="action" value="save" />
            <?php if ($isEditing): ?>
              <input type="hidden" name="id" value="<?= escapeHtml((string) $editingDoctor['id']) ?>" />
            <?php endif; ?>
            <label>
              Nama dokter
              <input
                type="text"
                name="name"
                maxlength="255"
                value="<?= escapeHtml((string) ($editingDoctor['name'] ?? '')) ?>"
                required
              />
            </label>
            <label>
              Spesialisasi
              <select name="specialty" required>
                <option value="">Pilih spesialisasi</option>
                <?php foreach ($specialties as $specialty): ?>
                  <option
                    value="<?= escapeHtml($specialty) ?>"
                    <?= ($editingDoctor['specialty'] ?? '') === $specialty ? 'selected' : '' ?>
                  ><?= escapeHtml($specialty) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Hari praktik
              <input
                type="text"
                name="work_days"
                maxlength="100"
                placeholder="Contoh: Senin - Kamis"
                value="<?= escapeHtml((string) ($editingDoctor['work_days'] ?? '')) ?>"
                required
              />
            </label>
            <div class="admin-time-fields">
              <label>
                Jam mulai
                <input
                  type="time"
                  name="start_time"
                  value="<?= escapeHtml(substr((string) ($editingDoctor['start_time'] ?? ''), 0, 5)) ?>"
                  required
                />
              </label>
              <label>
                Jam selesai
                <input
                  type="time"
                  name="end_time"
                  value="<?= escapeHtml(substr((string) ($editingDoctor['end_time'] ?? ''), 0, 5)) ?>"
                  required
                />
              </label>
            </div>
            <label>
              Ruang praktik
              <input
                type="text"
                name="room"
                maxlength="100"
                placeholder="Contoh: J-204"
                value="<?= escapeHtml((string) ($editingDoctor['room'] ?? '')) ?>"
                required
              />
            </label>
            <label>
              Status
              <select name="status" required>
                <?php foreach ($statuses as $value => $label): ?>
                  <option
                    value="<?= escapeHtml($value) ?>"
                    <?= ($editingDoctor['status'] ?? 'available') === $value ? 'selected' : '' ?>
                  ><?= escapeHtml($label) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <div class="admin-form-actions">
              <button class="btn btn-primary" type="submit"><?= escapeHtml($formAction) ?></button>
              <?php if ($isEditing): ?>
                <a class="btn btn-secondary" href="index.php">Batal</a>
              <?php endif; ?>
            </div>
          </form>
        </section>

        <section class="admin-panel admin-table-panel" id="doctor-list">
          <h2>Daftar Dokter</h2>
          <?php if ($doctors === []): ?>
            <p class="admin-empty-state">Belum ada data dokter. Gunakan formulir untuk menambahkan dokter pertama.</p>
          <?php else: ?>
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Dokter</th>
                    <th>Jadwal praktik</th>
                    <th>Status</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($doctors as $doctor): ?>
                    <tr>
                      <td>
                        <strong><?= escapeHtml((string) $doctor['name']) ?></strong>
                        <span><?= escapeHtml((string) $doctor['specialty']) ?></span>
                      </td>
                      <td>
                        <?= escapeHtml((string) $doctor['work_days']) ?><br />
                        <?= escapeHtml(substr((string) $doctor['start_time'], 0, 5)) ?>
                        - <?= escapeHtml(substr((string) $doctor['end_time'], 0, 5)) ?><br />
                        Ruang <?= escapeHtml((string) $doctor['room']) ?>
                      </td>
                      <td>
                        <span class="admin-status <?= escapeHtml((string) $doctor['status']) ?>">
                          <?= escapeHtml($statuses[(string) $doctor['status']] ?? 'Tidak diketahui') ?>
                        </span>
                      </td>
                      <td>
                        <div class="admin-row-actions">
                          <a class="admin-edit-link" href="index.php?edit=<?= (int) $doctor['id'] ?>">Ubah</a>
                          <form method="post" action="index.php" data-delete-doctor>
                            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>" />
                            <input type="hidden" name="action" value="delete" />
                            <input type="hidden" name="id" value="<?= (int) $doctor['id'] ?>" />
                            <button class="admin-delete-button" type="submit">Hapus</button>
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
        </div>
      </main>
    </div>
  </body>
</html>
