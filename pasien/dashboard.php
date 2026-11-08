<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

requirePatientLogin();

function patientWorkdayNumbers(string $workDays): array
{
    $dayNumbers = [
        'senin' => 1,
        'selasa' => 2,
        'rabu' => 3,
        'kamis' => 4,
        'jumat' => 5,
        'sabtu' => 6,
        'minggu' => 7,
        'sen' => 1,
        'sel' => 2,
        'rab' => 3,
        'kam' => 4,
        'jum' => 5,
        'sab' => 6,
        'min' => 7,
    ];

    preg_match_all(
        '/senin|selasa|rabu|kamis|jumat|sabtu|minggu|sen|sel|rab|kam|jum|sab|min/i',
        $workDays,
        $matches,
        PREG_OFFSET_CAPTURE
    );

    $days = [];
    $matchedDays = $matches[0];

    for ($index = 0, $count = count($matchedDays); $index < $count; $index++) {
        $currentDay = strtolower($matchedDays[$index][0]);
        $currentNumber = $dayNumbers[$currentDay];
        $nextMatch = $matchedDays[$index + 1] ?? null;

        if ($nextMatch !== null) {
            $separatorStart = $matchedDays[$index][1] + strlen($matchedDays[$index][0]);
            $separator = substr(
                $workDays,
                $separatorStart,
                $nextMatch[1] - $separatorStart
            );

            if (preg_match('/^\s*(?:-|–|—|sampai|hingga|s\/d)\s*$/i', $separator)) {
                $endNumber = $dayNumbers[strtolower($nextMatch[0])];
                $rangeLength = (($endNumber - $currentNumber + 7) % 7) + 1;

                for ($offset = 0; $offset < $rangeLength; $offset++) {
                    $days[] = (($currentNumber - 1 + $offset) % 7) + 1;
                }

                $index++;
                continue;
            }
        }

        $days[] = $currentNumber;
    }

    return array_values(array_unique($days));
}

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
$availableMenus = [
    'overview' => 'Ringkasan',
    'profile' => 'Profil Pasien',
    'booking' => 'Pendaftaran Rawat Jalan',
    'schedule' => 'Jadwal Dokter',
    'history' => 'Riwayat Pendaftaran',
];
$currentMenu = $_GET['menu'] ?? 'overview';
if (!is_string($currentMenu) || !isset($availableMenus[$currentMenu])) {
    $currentMenu = 'overview';
}

$bookableDoctors = $connection->query(
    "SELECT id, name, specialty, work_days
     FROM doctors
     WHERE status = 'available'
     ORDER BY name ASC"
)->fetchAll();
$doctorSchedules = $connection->query(
    'SELECT id, name, specialty, work_days, start_time, end_time, room, status
     FROM doctors
     ORDER BY
        CASE WHEN status = \'available\' THEN 0 ELSE 1 END,
        name ASC'
)->fetchAll();
$assignedDoctorStatement = $connection->prepare(
    "SELECT doctor_id
     FROM appointments
     WHERE patient_id = :patient_id
       AND status IN ('pending', 'confirmed')
       AND doctor_id IS NOT NULL
     ORDER BY id ASC
     LIMIT 1"
);
$assignedDoctorStatement->execute(['patient_id' => (int) $patient['id']]);
$assignedDoctorId = $assignedDoctorStatement->fetchColumn();

if ($assignedDoctorId !== false) {
    $bookableDoctors = array_values(array_filter(
        $bookableDoctors,
        static fn (array $doctor): bool =>
            (int) $doctor['id'] === (int) $assignedDoctorId
    ));
}

$appointmentsStatement = $connection->prepare(
    'SELECT
        a.id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.created_at,
        d.name AS doctor_name,
        d.specialty AS doctor_specialty
     FROM appointments a
     LEFT JOIN doctors d ON d.id = a.doctor_id
     WHERE a.patient_id = :patient_id
     ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.id DESC'
);
$appointmentsStatement->execute(['patient_id' => (int) $patient['id']]);
$appointments = $appointmentsStatement->fetchAll();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyPatientCsrfToken()) {
        http_response_code(403);
        $notice = ['type' => 'error', 'message' => 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.'];
    } else {
        $doctorIdValue = $_POST['doctor_id'] ?? '';
        $dateValue = $_POST['visit_date'] ?? '';
        $timeValue = $_POST['appointment_time'] ?? '';
        $doctorId = filter_var($doctorIdValue, FILTER_VALIDATE_INT);
        $visitDate = is_string($dateValue) ? trim($dateValue) : '';
        $appointmentTime = is_string($timeValue) ? trim($timeValue) : '';
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $visitDate);
        $dateErrors = DateTimeImmutable::getLastErrors();

        $selectedDoctor = null;
        foreach ($bookableDoctors as $bookableDoctor) {
            if ((int) $bookableDoctor['id'] === $doctorId) {
                $selectedDoctor = $bookableDoctor;
                break;
            }
        }

        if (!$doctorId || !$selectedDoctor) {
            http_response_code(422);
            $notice = ['type' => 'error', 'message' => 'Dokter yang dipilih tidak tersedia.'];
        } elseif (
            $parsedDate === false
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $parsedDate->format('Y-m-d') !== $visitDate
            || $visitDate < (new DateTimeImmutable('today'))->format('Y-m-d')
        ) {
            http_response_code(422);
            $notice = ['type' => 'error', 'message' => 'Tanggal konsultasi tidak valid atau sudah berlalu.'];
        } elseif (
            !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $appointmentTime)
        ) {
            http_response_code(422);
            $notice = ['type' => 'error', 'message' => 'Jam konsultasi tidak valid.'];
        } else {
            try {
                $connection->beginTransaction();

                $patientLock = $connection->prepare(
                    'SELECT id
                     FROM patients
                     WHERE id = :patient_id
                     FOR UPDATE'
                );
                $patientLock->execute(['patient_id' => (int) $patient['id']]);

                $doctorLock = $connection->prepare(
                    "SELECT id, specialty, work_days, start_time, end_time
                     FROM doctors
                     WHERE id = :doctor_id
                       AND status = 'available'
                     FOR UPDATE"
                );
                $doctorLock->execute(['doctor_id' => $doctorId]);
                $currentDoctor = $doctorLock->fetch();

                if (!$currentDoctor) {
                    throw new RuntimeException(
                        'Dokter yang dipilih tidak lagi tersedia.'
                    );
                }

                $doctorWorkdays = patientWorkdayNumbers(
                    (string) $currentDoctor['work_days']
                );

                if (!$doctorWorkdays) {
                    throw new RuntimeException(
                        'Jadwal hari praktik dokter belum diatur. Silakan hubungi rumah sakit.'
                    );
                }

                if (!in_array((int) $parsedDate->format('N'), $doctorWorkdays, true)) {
                    throw new RuntimeException(
                        'Tanggal pendaftaran harus sesuai hari praktik dokter: ' .
                        $currentDoctor['work_days'] . '.'
                    );
                }

                if (
                    $appointmentTime < substr((string) $currentDoctor['start_time'], 0, 5)
                    || $appointmentTime > substr((string) $currentDoctor['end_time'], 0, 5)
                ) {
                    throw new RuntimeException(
                        'Jam konsultasi harus berada dalam jam praktik dokter.'
                    );
                }

                $assignedDoctor = $connection->prepare(
                    "SELECT doctor_id
                     FROM appointments
                     WHERE patient_id = :patient_id
                       AND status IN ('pending', 'confirmed')
                       AND doctor_id IS NOT NULL
                     ORDER BY id ASC
                     LIMIT 1"
                );
                $assignedDoctor->execute(['patient_id' => (int) $patient['id']]);
                $existingDoctorId = $assignedDoctor->fetchColumn();

                if (
                    $existingDoctorId !== false
                    && (int) $existingDoctorId !== $doctorId
                ) {
                    throw new RuntimeException(
                        'Pasien sudah terdaftar pada dokter lain. Hubungi admin jika ingin mengganti dokter.'
                    );
                }

                $insertStatement = $connection->prepare(
                    'INSERT INTO appointments
                        (patient_id, doctor_id, appointment_date, appointment_time)
                     VALUES
                        (:patient_id, :doctor_id, :appointment_date, :appointment_time)'
                );
                $insertStatement->execute([
                    'patient_id' => (int) $patient['id'],
                    'doctor_id' => $doctorId,
                    'appointment_date' => $visitDate,
                    'appointment_time' => $appointmentTime,
                ]);
                $connection->commit();

                $_SESSION['patient_notice'] = [
                    'type' => 'success',
                    'message' => 'Permintaan konsultasi berhasil dikirim.',
                ];
                header('Location: dashboard.php?menu=booking');
                exit;
            } catch (Throwable $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }

                if ($exception instanceof RuntimeException) {
                    $notice = ['type' => 'error', 'message' => $exception->getMessage()];
                } else {
                    error_log('Patient appointment error: ' . $exception->getMessage());
                    $notice = [
                        'type' => 'error',
                        'message' => 'Permintaan konsultasi gagal disimpan. Silakan coba lagi.',
                    ];
                }
            }
        }
    }
}

$csrfToken = patientCsrfToken();
$statusLabels = [
    'pending' => 'Menunggu konfirmasi',
    'confirmed' => 'Dikonfirmasi',
    'completed' => 'Selesai',
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
  <body class="patient-page patient-portal-page">
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
          <?php foreach ($availableMenus as $menuKey => $menuLabel): ?>
            <a
              href="dashboard.php?menu=<?= escapePatientHtml($menuKey) ?>"
              <?= $currentMenu === $menuKey ? 'aria-current="page"' : '' ?>>
              <?= escapePatientHtml($menuLabel) ?>
            </a>
          <?php endforeach; ?>
        </nav>
        <a class="patient-sidebar-home" href="../index.php">Kembali ke beranda</a>
      </aside>

      <main class="patient-dashboard" id="dashboard">
        <section class="patient-dashboard-heading">
          <div>
            <p class="eyebrow">Portal pasien</p>
            <h1><?= escapePatientHtml($availableMenus[$currentMenu]) ?></h1>
            <p class="patient-intro">
              <?= $currentMenu === 'overview'
                  ? 'Halo, ' . escapePatientHtml((string) $patient['name']) . '. Pilih menu untuk mengakses layanan pasien.'
                  : 'Halo, ' . escapePatientHtml((string) $patient['name']) . '.' ?>
            </p>
          </div>
        </section>

      <?php if (is_array($notice) && isset($notice['message'])): ?>
        <p class="patient-alert <?= escapePatientHtml((string) ($notice['type'] ?? 'error')) ?>" role="status">
          <?= escapePatientHtml((string) $notice['message']) ?>
        </p>
      <?php endif; ?>

      <?php if ($currentMenu === 'overview'): ?>
        <section class="patient-panel patient-menu-panel">
          <h2>Selamat datang, <?= escapePatientHtml((string) $patient['name']) ?></h2>
          <p class="patient-section-intro">
            Gunakan menu di samping untuk mengelola akun dan layanan rawat jalan Anda.
          </p>
          <div class="patient-menu-cards">
            <?php foreach (array_slice($availableMenus, 1, null, true) as $menuKey => $menuLabel): ?>
              <a class="patient-menu-card" href="dashboard.php?menu=<?= escapePatientHtml($menuKey) ?>">
                <strong><?= escapePatientHtml($menuLabel) ?></strong>
                <span>Lihat <?= escapePatientHtml(strtolower($menuLabel)) ?> <span aria-hidden="true">→</span></span>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php elseif ($currentMenu === 'profile'): ?>
        <section class="patient-panel">
          <h2>Informasi Akun</h2>
          <dl class="patient-profile">
            <div><dt>Nama</dt><dd><?= escapePatientHtml((string) $patient['name']) ?></dd></div>
            <div><dt>Email</dt><dd><?= escapePatientHtml((string) $patient['email']) ?></dd></div>
            <div><dt>Telepon</dt><dd><?= escapePatientHtml((string) ($patient['phone'] ?: '-')) ?></dd></div>
          </dl>
        </section>
      <?php elseif ($currentMenu === 'booking'): ?>
        <section class="patient-panel">
          <h2>Pendaftaran Rawat Jalan</h2>
          <p class="patient-section-intro">
            Pilih dokter dan waktu kunjungan. Pendaftaran akan menunggu konfirmasi rumah sakit.
          </p>
          <?php if ($bookableDoctors === []): ?>
            <p class="patient-empty-state">Belum ada dokter tersedia untuk pemesanan saat ini.</p>
          <?php else: ?>
            <form class="patient-form" method="post" action="dashboard.php?menu=booking">
              <input type="hidden" name="csrf_token" value="<?= escapePatientHtml($csrfToken) ?>" />
              <label>
                Dokter pilihan
                <select name="doctor_id" required>
                  <option value="">Pilih dokter</option>
                  <?php foreach ($bookableDoctors as $bookableDoctor): ?>
                    <option
                      value="<?= (int) $bookableDoctor['id'] ?>"
                      data-workdays="<?= escapePatientHtml(
                          implode(',', patientWorkdayNumbers((string) $bookableDoctor['work_days']))
                      ) ?>">
                      <?= escapePatientHtml((string) $bookableDoctor['name']) ?>
                      — <?= escapePatientHtml((string) $bookableDoctor['specialty']) ?>
                      (<?= escapePatientHtml((string) $bookableDoctor['work_days']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <p class="patient-empty-state">
                Selama janji masih aktif, pasien terdaftar pada dokter yang dipilih dan tidak dapat berpindah ke dokter lain.
              </p>
              <label>
                Tanggal konsultasi
                <input
                  type="date"
                  name="visit_date"
                  id="appointmentDate"
                  min="<?= escapePatientHtml((new DateTimeImmutable('today'))->format('Y-m-d')) ?>"
                  required />
              </label>
              <label>
                Jam konsultasi
                <input type="time" name="appointment_time" required />
              </label>
              <button class="btn btn-primary full-width" type="submit">Kirim pendaftaran</button>
            </form>
          <?php endif; ?>
        </section>
      <?php elseif ($currentMenu === 'schedule'): ?>
        <section class="patient-panel">
          <div class="patient-section-heading">
            <div>
              <h2>Jadwal Dokter</h2>
              <p class="patient-section-intro">
                Cek hari dan jam praktik sebelum mengirim pendaftaran rawat jalan.
              </p>
            </div>
            <span class="patient-count"><?= count($doctorSchedules) ?> dokter</span>
          </div>

          <?php if ($doctorSchedules === []): ?>
            <p class="patient-empty-state">Jadwal dokter belum tersedia.</p>
          <?php else: ?>
            <div class="patient-table-wrap">
              <table class="patient-table">
                <thead>
                  <tr>
                    <th>Dokter</th>
                    <th>Hari Praktik</th>
                    <th>Jam</th>
                    <th>Ruang</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($doctorSchedules as $doctorSchedule): ?>
                    <tr>
                      <td>
                        <strong><?= escapePatientHtml((string) $doctorSchedule['name']) ?></strong>
                        <br />
                        <small><?= escapePatientHtml((string) $doctorSchedule['specialty']) ?></small>
                      </td>
                      <td><?= escapePatientHtml((string) $doctorSchedule['work_days']) ?></td>
                      <td>
                        <?= escapePatientHtml(substr((string) $doctorSchedule['start_time'], 0, 5)) ?>
                        –
                        <?= escapePatientHtml(substr((string) $doctorSchedule['end_time'], 0, 5)) ?>
                      </td>
                      <td><?= escapePatientHtml((string) $doctorSchedule['room']) ?></td>
                      <td>
                        <span class="patient-doctor-status <?= $doctorSchedule['status'] === 'available' ? 'is-available' : 'is-busy' ?>">
                          <?= $doctorSchedule['status'] === 'available' ? 'Tersedia' : 'Tidak tersedia' ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>
      <?php elseif ($currentMenu === 'history'): ?>
        <section class="patient-panel">
          <h2>Riwayat Pendaftaran Rawat Jalan</h2>
          <?php if ($appointments === []): ?>
            <p class="patient-empty-state">
              Belum ada pendaftaran. Gunakan menu pendaftaran rawat jalan untuk memulai.
            </p>
          <?php else: ?>
            <div class="patient-table-wrap">
              <table class="patient-table">
                <thead>
                  <tr>
                    <th>Dokter</th>
                    <th>Tanggal</th>
                    <th>Jam</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($appointments as $appointment): ?>
                    <tr>
                      <td>
                        <?= escapePatientHtml((string) ($appointment['doctor_name'] ?? 'Dokter belum ditentukan')) ?>
                        <br />
                        <small><?= escapePatientHtml((string) ($appointment['doctor_specialty'] ?? '-')) ?></small>
                      </td>
                      <td><?= escapePatientHtml((string) $appointment['appointment_date']) ?></td>
                      <td><?= escapePatientHtml(substr((string) $appointment['appointment_time'], 0, 5)) ?></td>
                      <td><?= escapePatientHtml($statusLabels[(string) $appointment['status']] ?? 'Tidak diketahui') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
      </main>
    </div>
  </body>
</html>
