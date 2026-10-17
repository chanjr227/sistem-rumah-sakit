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
$connection = null;

$doctor = [
    'name' => '',
    'specialty' => '',
    'work_days' => '',
    'start_time' => '',
    'end_time' => '',
    'room' => '',
    'status' => 'available',
    'education' => '',
    'experience' => '',
    'biography' => '',
];

function tambahDokterEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function tambahDokterValidTime(string $time): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!H:i', $time);
    $errors = DateTimeImmutable::getLastErrors();

    return $parsed !== false
        && (
            $errors === false
            || (
                $errors['warning_count'] === 0
                && $errors['error_count'] === 0
            )
        )
        && $parsed->format('H:i') === $time;
}

/*
|--------------------------------------------------------------------------
| Ambil data dokter saat mode edit
|--------------------------------------------------------------------------
*/

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && isset($_GET['edit'])
) {
    $validatedId = filter_var(
        $_GET['edit'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($validatedId === false) {
        $notice = 'ID dokter tidak valid.';
    } else {
        try {
            $connection = getDatabaseConnection();

            $statement = $connection->prepare(
                "SELECT
                    d.id,
                    d.name,
                    d.specialty,
                    d.work_days,
                    d.start_time,
                    d.end_time,
                    d.room,
                    d.status,
                    COALESCE(dp.education, '') AS education,
                    COALESCE(dp.experience, '') AS experience,
                    COALESCE(dp.biography, '') AS biography
                 FROM doctors AS d
                 LEFT JOIN doctor_profiles AS dp
                    ON dp.doctor_id = d.id
                 WHERE d.id = :id
                 LIMIT 1"
            );

            $statement->execute(['id' => $validatedId]);
            $result = $statement->fetch();

            if (!$result) {
                $notice = 'Data dokter tidak ditemukan.';
            } else {
                $doctorId = (int) $result['id'];
                $isEditing = true;

                foreach ($doctor as $key => $value) {
                    $doctor[$key] = (string) $result[$key];
                }

                $doctor['start_time'] = substr(
                    $doctor['start_time'],
                    0,
                    5
                );

                $doctor['end_time'] = substr(
                    $doctor['end_time'],
                    0,
                    5
                );
            }
        } catch (PDOException $exception) {
            error_log(
                'Load doctor error: ' . $exception->getMessage()
            );

            $notice = 'Data dokter gagal dimuat. Periksa struktur database.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Proses penyimpanan tambah dan edit dokter
|--------------------------------------------------------------------------
*/

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!verifyAdminCsrfToken()) {
            http_response_code(403);

            throw new InvalidArgumentException(
                'Sesi formulir tidak valid. Silakan muat ulang halaman.'
            );
        }

        /*
        |--------------------------------------------------------------
        | Validasi ID dokter
        |--------------------------------------------------------------
        */

        $postedId = $_POST['id'] ?? '';

        if (!is_string($postedId)) {
            throw new InvalidArgumentException(
                'ID dokter tidak valid.'
            );
        }

        if ($postedId !== '') {
            $validatedId = filter_var(
                $postedId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($validatedId === false) {
                throw new InvalidArgumentException(
                    'ID dokter tidak valid.'
                );
            }

            $doctorId = (int) $validatedId;
            $isEditing = true;
        } else {
            $doctorId = null;
            $isEditing = false;
        }

        /*
        |--------------------------------------------------------------
        | Ambil nilai formulir
        |--------------------------------------------------------------
        */

        foreach ($doctor as $key => $value) {
            $postedValue = $_POST[$key] ?? '';

            if (!is_string($postedValue)) {
                throw new InvalidArgumentException(
                    'Data formulir tidak valid.'
                );
            }

            $doctor[$key] = trim($postedValue);
        }

        /*
        |--------------------------------------------------------------
        | Validasi data utama dokter
        |--------------------------------------------------------------
        */

        $fields = [
            'name' => ['Nama dokter', 255],
            'specialty' => ['Spesialisasi', 100],
            'work_days' => ['Hari praktik', 100],
            'start_time' => ['Jam mulai', 5],
            'end_time' => ['Jam selesai', 5],
            'room' => ['Ruang praktik', 100],
        ];

        foreach ($fields as $key => [$label, $maxLength]) {
            if (
                $doctor[$key] === ''
                || mb_strlen($doctor[$key], 'UTF-8') > $maxLength
            ) {
                throw new InvalidArgumentException(
                    $label . ' wajib diisi dan maksimal '
                        . $maxLength . ' karakter.'
                );
            }
        }

        if (!in_array($doctor['specialty'], $specialties, true)) {
            throw new InvalidArgumentException(
                'Spesialisasi dokter tidak valid.'
            );
        }

        if (!array_key_exists($doctor['status'], $statuses)) {
            throw new InvalidArgumentException(
                'Status praktik dokter tidak valid.'
            );
        }

        if (
            !tambahDokterValidTime($doctor['start_time'])
            || !tambahDokterValidTime($doctor['end_time'])
            || $doctor['start_time'] >= $doctor['end_time']
        ) {
            throw new InvalidArgumentException(
                'Jam selesai harus setelah jam mulai.'
            );
        }

        /*
        |--------------------------------------------------------------
        | Validasi data profil dokter
        |--------------------------------------------------------------
        */

        $profileFields = [
            'education' => ['Pendidikan', 1000],
            'experience' => ['Pengalaman', 1000],
            'biography' => ['Biografi', 3000],
        ];

        foreach ($profileFields as $key => [$label, $maxLength]) {
            if (
                mb_strlen($doctor[$key], 'UTF-8') > $maxLength
            ) {
                throw new InvalidArgumentException(
                    $label . ' maksimal '
                        . $maxLength . ' karakter.'
                );
            }
        }

        /*
        |--------------------------------------------------------------
        | Simpan data menggunakan transaksi
        |--------------------------------------------------------------
        */

        $connection = getDatabaseConnection();
        $connection->beginTransaction();

        if ($isEditing) {
            /*
             * Pastikan dokter yang akan diedit benar-benar ada.
             */

            $check = $connection->prepare(
                'SELECT id
                 FROM doctors
                 WHERE id = :id
                 LIMIT 1'
            );

            $check->execute(['id' => $doctorId]);

            if (!$check->fetchColumn()) {
                throw new InvalidArgumentException(
                    'Data dokter tidak ditemukan.'
                );
            }

            /*
             * Perbarui tabel doctors.
             */

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
        } else {
            /*
             * Tambahkan data ke tabel doctors.
             */

            $statement = $connection->prepare(
                'INSERT INTO doctors
                    (
                        name,
                        specialty,
                        work_days,
                        start_time,
                        end_time,
                        room,
                        status
                    )
                 VALUES
                    (
                        :name,
                        :specialty,
                        :work_days,
                        :start_time,
                        :end_time,
                        :room,
                        :status
                    )'
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

            /*
             * Ambil ID dokter yang baru saja dibuat.
             */

            $doctorId = (int) $connection->lastInsertId();
        }

        /*
        |--------------------------------------------------------------
        | Simpan pendidikan, pengalaman, dan biografi
        | ke tabel doctor_profiles
        |--------------------------------------------------------------
        */

        $profileCheck = $connection->prepare(
            'SELECT id
             FROM doctor_profiles
             WHERE doctor_id = :doctor_id
             LIMIT 1'
        );

        $profileCheck->execute([
            'doctor_id' => $doctorId,
        ]);

        $profileId = $profileCheck->fetchColumn();

        if ($profileId !== false) {
            /*
             * Profil sudah ada, jadi perbarui profil tersebut.
             * Foto dokter tidak diubah.
             */

            $profileStatement = $connection->prepare(
                'UPDATE doctor_profiles
                 SET education = :education,
                     experience = :experience,
                     biography = :biography,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE doctor_id = :doctor_id'
            );
        } else {
            /*
             * Profil belum ada, jadi buat profil baru.
             * Kolom photo tidak diubah dan mengikuti default database.
             */

            $profileStatement = $connection->prepare(
                'INSERT INTO doctor_profiles
                    (
                        doctor_id,
                        education,
                        experience,
                        biography,
                        created_at,
                        updated_at
                    )
                 VALUES
                    (
                        :doctor_id,
                        :education,
                        :experience,
                        :biography,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    )'
            );
        }

        $profileStatement->execute([
            'doctor_id' => $doctorId,
            'education' => $doctor['education'],
            'experience' => $doctor['experience'],
            'biography' => $doctor['biography'],
        ]);

        /*
        |--------------------------------------------------------------
        | Selesaikan transaksi
        |--------------------------------------------------------------
        */

        $connection->commit();

        $_SESSION['admin_notice'] = [
            'type' => 'success',
            'message' => $isEditing
                ? 'Data dokter berhasil diperbarui.'
                : 'Dokter berhasil ditambahkan.',
        ];

        header('Location: data_dokter.php');
        exit;
    } catch (InvalidArgumentException $exception) {
        if (
            $connection instanceof PDO
            && $connection->inTransaction()
        ) {
            $connection->rollBack();
        }

        $notice = $exception->getMessage();
    } catch (PDOException $exception) {
        if (
            $connection instanceof PDO
            && $connection->inTransaction()
        ) {
            $connection->rollBack();
        }

        error_log(
            'Save doctor error: ' . $exception->getMessage()
        );

        $notice = 'Data dokter gagal disimpan. Periksa database dan struktur tabel.';
    }
}

$pageTitle = $isEditing ? 'Edit Dokter' : 'Tambah Dokter';

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= tambahDokterEscape($pageTitle) ?> - RS Medika Sehat
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

                    <h1><?= tambahDokterEscape($pageTitle) ?></h1>

                    <p class="admin-intro">
                        <?= $isEditing
                            ? 'Perbarui informasi, profil, dan jadwal praktik dokter.'
                            : 'Masukkan informasi, profil, dan jadwal praktik dokter baru.' ?>
                    </p>
                </div>

                <a class="btn btn-secondary" href="data_dokter.php">
                    Kembali ke Data Dokter
                </a>
            </div>

            <?php if ($notice !== null): ?>
                <div
                    class="admin-panel"
                    role="alert"
                    style="margin-bottom:20px; border-left:4px solid #dc5353;">
                    <?= tambahDokterEscape($notice) ?>
                </div>
            <?php endif; ?>

            <?php require __DIR__ . '/../partials/flash_message.php'; ?>

            <section class="admin-panel">

                <h2>Informasi Dokter</h2>

                <form
                    class="admin-form"
                    method="post"
                    action="tambah_dokter.php<?= $isEditing && $doctorId !== null
                                                    ? '?edit=' . $doctorId
                                                    : '' ?>">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= tambahDokterEscape($csrfToken) ?>">

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
                            value="<?= tambahDokterEscape($doctor['name']) ?>"
                            required>
                    </label>

                    <label>
                        Spesialisasi
                        <select name="specialty" required>
                            <option value="">Pilih spesialisasi</option>

                            <?php foreach ($specialties as $specialty): ?>
                                <option
                                    value="<?= tambahDokterEscape($specialty) ?>"
                                    <?= $doctor['specialty'] === $specialty
                                        ? 'selected'
                                        : '' ?>>
                                    <?= tambahDokterEscape($specialty) ?>
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
                            value="<?= tambahDokterEscape($doctor['work_days']) ?>"
                            required>
                    </label>

                    <div class="admin-time-fields">

                        <label>
                            Jam mulai
                            <input
                                type="time"
                                name="start_time"
                                value="<?= tambahDokterEscape($doctor['start_time']) ?>"
                                required>
                        </label>

                        <label>
                            Jam selesai
                            <input
                                type="time"
                                name="end_time"
                                value="<?= tambahDokterEscape($doctor['end_time']) ?>"
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
                            value="<?= tambahDokterEscape($doctor['room']) ?>"
                            required>
                    </label>

                    <label>
                        Status praktik
                        <select name="status" required>
                            <?php foreach ($statuses as $value => $label): ?>
                                <option
                                    value="<?= tambahDokterEscape($value) ?>"
                                    <?= $doctor['status'] === $value
                                        ? 'selected'
                                        : '' ?>>
                                    <?= tambahDokterEscape($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div class="admin-form-section">
                        <h2>Profil Dokter</h2>
                        <p class="admin-intro">
                            Informasi berikut akan ditampilkan pada profil dokter.
                        </p>
                    </div>

                    <label>
                        Pendidikan
                        <textarea
                            name="education"
                            rows="3"
                            maxlength="1000"
                            placeholder="Contoh: S1 Kedokteran Universitas Indonesia, Spesialis Jantung"><?= tambahDokterEscape($doctor['education']) ?></textarea>
                    </label>

                    <label>
                        Pengalaman
                        <textarea
                            name="experience"
                            rows="3"
                            maxlength="1000"
                            placeholder="Contoh: 5 tahun pengalaman di bidang kardiologi"><?= tambahDokterEscape($doctor['experience']) ?></textarea>
                    </label>

                    <label>
                        Biografi dokter
                        <textarea
                            name="biography"
                            rows="5"
                            maxlength="3000"
                            placeholder="Tuliskan profil singkat, keahlian, dan informasi dokter lainnya."><?= tambahDokterEscape($doctor['biography']) ?></textarea>
                    </label>

                    <div class="admin-form-actions">

                        <button class="btn btn-primary" type="submit">
                            <?= $isEditing
                                ? 'Simpan Perubahan'
                                : 'Simpan Dokter' ?>
                        </button>

                        <a class="btn btn-secondary" href="data_dokter.php">
                            Batal
                        </a>

                    </div>

                </form>

            </section>

        </main>
    </div>

</body>

</html>