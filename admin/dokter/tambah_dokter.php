<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();
$specialties = ['Jantung', 'Anak', 'Neurologi', 'Bedah', 'Umum'];

$statuses = [
    'available' => 'Tersedia',
    'busy' => 'Penuh',
];

$csrfToken = adminCsrfToken();
$notice = null;
$isEditing = false;
$doctorId = null;

$doctor = [
    'name' => '',
    'specialty' => '',
    'work_days' => '',
    'start_time' => '',
    'end_time' => '',
    'room' => '',
    'status' => 'available',
];

function tambahDokterValidTime(string $time): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!H:i', $time);
    $errors = DateTimeImmutable::getLastErrors();

    return $parsed !== false
        && ($errors === false || (
            $errors['warning_count'] === 0
            && $errors['error_count'] === 0
        ))
        && $parsed->format('H:i') === $time;
}

/*
|--------------------------------------------------------------------------
| Ambil data dokter ketika halaman digunakan untuk edit
|--------------------------------------------------------------------------
*/

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['edit'])) {
    $validatedId = filter_var(
        $_GET['edit'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($validatedId === false) {
        $notice = [
            'type' => 'error',
            'message' => 'ID dokter tidak valid.',
        ];
    } else {
        try {
            $connection = getDatabaseConnection();

            $statement = $connection->prepare(
                'SELECT id, name, specialty, work_days, start_time,
                        end_time, room, status
                 FROM doctors
                 WHERE id = :id'
            );

            $statement->execute(['id' => $validatedId]);
            $result = $statement->fetch();

            if (!$result) {
                $notice = [
                    'type' => 'error',
                    'message' => 'Data dokter tidak ditemukan.',
                ];
            } else {
                $doctorId = (int) $result['id'];
                $isEditing = true;

                foreach ($doctor as $key => $value) {
                    $doctor[$key] = (string) $result[$key];
                }
            }
        } catch (PDOException $exception) {
            error_log('Load doctor error: ' . $exception->getMessage());

            $notice = [
                'type' => 'error',
                'message' => 'Data dokter gagal dimuat.',
            ];
        }
    }
}

/*
|--------------------------------------------------------------------------
| Proses tambah atau edit dokter
|--------------------------------------------------------------------------
*/

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedId = $_POST['id'] ?? '';

    if (is_string($postedId) && $postedId !== '') {
        $validatedId = filter_var(
            $postedId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($validatedId !== false) {
            $doctorId = (int) $validatedId;
            $isEditing = true;
        }
    }

    // Pertahankan nilai formulir jika validasi gagal.
    foreach ($doctor as $key => $value) {
        if (isset($_POST[$key]) && is_string($_POST[$key])) {
            $doctor[$key] = trim($_POST[$key]);
        }
    }

    if (!verifyAdminCsrfToken()) {
        http_response_code(403);

        $notice = [
            'type' => 'error',
            'message' => 'Sesi formulir tidak valid. Muat ulang halaman.',
        ];
    } else {
        try {
            if ($isEditing && $doctorId === null) {
                throw new InvalidArgumentException('ID dokter tidak valid.');
            }

            foreach (
                [
                    'name' => ['Nama dokter', 255],
                    'specialty' => ['Spesialisasi', 100],
                    'work_days' => ['Hari praktik', 100],
                    'start_time' => ['Jam mulai', 5],
                    'end_time' => ['Jam selesai', 5],
                    'room' => ['Ruang praktik', 100],
                ] as $key => [$label, $maxLength]
            ) {
                if (
                    $doctor[$key] === ''
                    || mb_strlen($doctor[$key], 'UTF-8') > $maxLength
                ) {
                    throw new InvalidArgumentException(
                        $label . ' wajib diisi, maksimal '
                            . $maxLength . ' karakter.'
                    );
                }
            }

            if (!in_array($doctor['specialty'], $specialties, true)) {
                throw new InvalidArgumentException(
                    'Spesialisasi dokter tidak valid.'
                );
            }

            $status = $_POST['status'] ?? '';

            if (!is_string($status) || !array_key_exists($status, $statuses)) {
                throw new InvalidArgumentException('Status dokter tidak valid.');
            }

            $doctor['status'] = $status;

            if (
                !tambahDokterValidTime($doctor['start_time'])
                || !tambahDokterValidTime($doctor['end_time'])
                || $doctor['start_time'] >= $doctor['end_time']
            ) {
                throw new InvalidArgumentException(
                    'Jam praktik tidak valid. Jam selesai harus setelah jam mulai.'
                );
            }

            $connection = getDatabaseConnection();

            if ($isEditing) {
                $statement = $connection->prepare(
                    'UPDATE doctors
                     SET name = :name,
                         specialty = :specialty,
                         work_days = :work_days,
                         start_time = :start_time,
                         end_time = :end_time,
                         room = :room,
                         status = :status
                     WHERE id = :id'
                );

                $statement->execute([
                    'name' => $doctor['name'],
                    'specialty' => $doctor['specialty'],
                    'work_days' => $doctor['work_days'],
                    'start_time' => $doctor['start_time'],
                    'end_time' => $doctor['end_time'],
                    'room' => $doctor['room'],
                    'status' => $doctor['status'],
                    'id' => $doctorId,
                ]);

                $check = $connection->prepare(
                    'SELECT 1 FROM doctors WHERE id = :id'
                );
                $check->execute(['id' => $doctorId]);

                if (!$check->fetchColumn()) {
                    throw new InvalidArgumentException(
                        'Data dokter tidak ditemukan.'
                    );
                }

                $_SESSION['admin_notice'] = [
                    'type' => 'success',
                    'message' => 'Data dokter berhasil diperbarui.',
                ];
            } else {
                $statement = $connection->prepare(
                    'INSERT INTO doctors
                     (name, specialty, work_days, start_time,
                      end_time, room, status)
                     VALUES
                     (:name, :specialty, :work_days, :start_time,
                      :end_time, :room, :status)'
                );

                $statement->execute([
                    'name' => $doctor['name'],
                    'specialty' => $doctor['specialty'],
                    'work_days' => $doctor['work_days'],
                    'start_time' => $doctor['start_time'],
                    'end_time' => $doctor['end_time'],
                    'room' => $doctor['room'],
                    'status' => $doctor['status'],
                ]);

                $_SESSION['admin_notice'] = [
                    'type' => 'success',
                    'message' => 'Data dokter berhasil ditambahkan.',
                ];
            }

            header('Location: index.php');
            exit;
        } catch (InvalidArgumentException $exception) {
            $notice = [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ];
        } catch (PDOException $exception) {
            error_log('Save doctor error: ' . $exception->getMessage());

            $notice = [
                'type' => 'error',
                'message' => 'Data dokter gagal disimpan. Periksa koneksi database.',
            ];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= $isEditing ? 'Edit Dokter' : 'Tambah Dokter' ?> - RS Medika Sehat
    </title>

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
                    <p class="eyebrow">Manajemen rumah sakit</p>

                    <h1>
                        <?= $isEditing ? 'Edit Dokter' : 'Tambah Dokter' ?>
                    </h1>

                    <p class="admin-intro">
                        <?= $isEditing
                            ? 'Perbarui informasi dan jadwal praktik dokter.'
                            : 'Tambahkan informasi dokter baru ke sistem RS Medika Sehat.' ?>
                    </p>
                </div>

                <a class="btn btn-secondary" href="index.php#doctor-list">
                    Kembali ke Daftar
                </a>
            </div>

            <?php require __DIR__ . '/../partials/flash_message.php'; ?>

            <section class="admin-panel" id="doctor-form">

                <h2>Informasi Dokter</h2>

                <form
                    class="admin-form"
                    method="post"
                    action="tambah_dokter.php<?= $isEditing && $doctorId !== null ? '?edit=' . $doctorId : '' ?>">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escapeHtml($csrfToken) ?>">

                    <?php if ($isEditing && $doctorId !== null): ?>
                        <input
                            type="hidden"
                            name="id"
                            value="<?= $doctorId ?>">
                    <?php endif; ?>

                    <label>
                        Nama dokter
                        <input
                            type="text"
                            name="name"
                            maxlength="255"
                            placeholder="Contoh: dr. Andi Pratama"
                            value="<?= escapeHtml($doctor['name']) ?>"
                            required>
                    </label>

                    <label>
                        Spesialisasi
                        <select name="specialty" required>
                            <option value="">Pilih spesialisasi</option>

                            <?php foreach ($specialties as $specialty): ?>
                                <option
                                    value="<?= escapeHtml($specialty) ?>"
                                    <?= $doctor['specialty'] === $specialty
                                        ? 'selected'
                                        : '' ?>>
                                    <?= escapeHtml($specialty) ?>
                                </option>
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
                            value="<?= escapeHtml($doctor['work_days']) ?>"
                            required>
                    </label>

                    <div class="admin-time-fields">
                        <label>
                            Jam mulai
                            <input
                                type="time"
                                name="start_time"
                                value="<?= escapeHtml($doctor['start_time']) ?>"
                                required>
                        </label>

                        <label>
                            Jam selesai
                            <input
                                type="time"
                                name="end_time"
                                value="<?= escapeHtml($doctor['end_time']) ?>"
                                required>
                        </label>
                    </div>

                    <label>
                        Ruang praktik
                        <input
                            type="text"
                            name="room"
                            maxlength="100"
                            placeholder="Contoh: J-204"
                            value="<?= escapeHtml($doctor['room']) ?>"
                            required>
                    </label>

                    <label>
                        Status praktik
                        <select name="status" required>
                            <?php foreach ($statuses as $value => $label): ?>
                                <option
                                    value="<?= escapeHtml($value) ?>"
                                    <?= $doctor['status'] === $value
                                        ? 'selected'
                                        : '' ?>>
                                    <?= escapeHtml($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div class="admin-form-actions">
                        <button class="btn btn-primary" type="submit">
                            <?= $isEditing ? 'Simpan Perubahan' : 'Simpan Dokter' ?>
                        </button>

                        <a class="btn btn-secondary" href="index.php#doctor-list">
                            Batal
                        </a>
                    </div>

                </form>

            </section>

        </main>
    </div>

</body>

</html>