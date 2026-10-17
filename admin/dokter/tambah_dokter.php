<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

$connection = getDatabaseConnection();

function dokterEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function dokterValidTime(string $time): bool
{
    return (bool) preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time);
}

/**
 * Hanya mengizinkan penghapusan foto di uploads/doctors/.
 */
function dokterPhotoAbsolutePath(
    string $relativePath,
    string $projectRoot
): ?string {
    $prefix = 'uploads/doctors/';

    if (!str_starts_with($relativePath, $prefix)) {
        return null;
    }

    $filename = substr($relativePath, strlen($prefix));

    if (
        $filename === ''
        || basename($filename) !== $filename
        || !preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/', $filename)
    ) {
        return null;
    }

    return $projectRoot . DIRECTORY_SEPARATOR
        . 'uploads' . DIRECTORY_SEPARATOR
        . 'doctors' . DIRECTORY_SEPARATOR
        . $filename;
}

$specialties = ['Jantung', 'Anak', 'Neurologi', 'Bedah', 'Umum'];

$statuses = [
    'available' => 'Tersedia',
    'busy' => 'Penuh',
];

$projectRoot = realpath(__DIR__ . '/../..');

if ($projectRoot === false) {
    http_response_code(500);
    exit('Folder proyek tidak ditemukan.');
}

$uploadDirectory = $projectRoot . DIRECTORY_SEPARATOR
    . 'uploads' . DIRECTORY_SEPARATOR
    . 'doctors';

$doctor = [
    'id' => null,
    'name' => '',
    'specialty' => '',
    'work_days' => '',
    'start_time' => '',
    'end_time' => '',
    'room' => '',
    'status' => 'available',
    'photo' => '',
    'education' => '',
    'experience' => '',
    'biography' => '',
];

$errorMessage = '';
$isEditing = false;
$oldPhotoPath = '';
$newPhotoPath = '';

/*
|--------------------------------------------------------------------------
| Ambil data dokter saat halaman edit dibuka.
|--------------------------------------------------------------------------
*/
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $rawEditId = $_GET['id'] ?? null;

    if ($rawEditId !== null) {
        $editId = filter_var($rawEditId, FILTER_VALIDATE_INT);

        if ($editId === false || $editId < 1) {
            http_response_code(400);
            exit('ID dokter tidak valid.');
        }

        $stmt = $connection->prepare(
            'SELECT
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
             FROM doctors d
             LEFT JOIN doctor_profiles dp ON dp.doctor_id = d.id
             WHERE d.id = :id
             LIMIT 1'
        );

        $stmt->execute(['id' => $editId]);
        $existingDoctor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingDoctor) {
            http_response_code(404);
            exit('Data dokter tidak ditemukan.');
        }

        $isEditing = true;
        $doctor = array_merge($doctor, $existingDoctor);
        $oldPhotoPath = (string) ($doctor['photo'] ?? '');
    }
}

/*
|--------------------------------------------------------------------------
| Proses form tambah atau edit dokter.
|--------------------------------------------------------------------------
*/
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyAdminCsrfToken()) {
        http_response_code(403);
        exit('Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
    }

    $rawPostedId = $_POST['id'] ?? null;
    $postedId = null;

    if ($rawPostedId !== null && $rawPostedId !== '') {
        $postedId = filter_var($rawPostedId, FILTER_VALIDATE_INT);

        if ($postedId === false || $postedId < 1) {
            http_response_code(400);
            exit('ID dokter tidak valid.');
        }
    }

    $isEditing = $postedId !== null;
    $doctor['id'] = $postedId;

    if ($isEditing) {
        $stmt = $connection->prepare(
            'SELECT id FROM doctors WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $postedId]);

        if (!$stmt->fetchColumn()) {
            http_response_code(404);
            exit('Data dokter tidak ditemukan.');
        }
    }

    $doctor['name'] = trim((string) ($_POST['name'] ?? ''));
    $doctor['specialty'] = trim((string) ($_POST['specialty'] ?? ''));
    $doctor['work_days'] = trim((string) ($_POST['work_days'] ?? ''));
    $doctor['start_time'] = trim((string) ($_POST['start_time'] ?? ''));
    $doctor['end_time'] = trim((string) ($_POST['end_time'] ?? ''));
    $doctor['room'] = trim((string) ($_POST['room'] ?? ''));
    $doctor['status'] = trim((string) ($_POST['status'] ?? 'available'));
    $doctor['education'] = trim((string) ($_POST['education'] ?? ''));
    $doctor['experience'] = trim((string) ($_POST['experience'] ?? ''));
    $doctor['biography'] = trim((string) ($_POST['biography'] ?? ''));

    /*
     * Ambil foto lama langsung dari database.
     */
    $oldPhotoPath = '';

    if ($isEditing) {
        $stmt = $connection->prepare(
            'SELECT photo FROM doctor_profiles
             WHERE doctor_id = :doctor_id
             LIMIT 1'
        );
        $stmt->execute(['doctor_id' => $postedId]);
        $oldPhotoPath = (string) ($stmt->fetchColumn() ?: '');
    }

    $doctor['photo'] = $oldPhotoPath;

    /*
    |--------------------------------------------------------------------------
    | Validasi data.
    |--------------------------------------------------------------------------
    */
    if ($doctor['name'] === '') {
        $errorMessage = 'Nama dokter wajib diisi.';
    } elseif (mb_strlen($doctor['name']) > 150) {
        $errorMessage = 'Nama dokter maksimal 150 karakter.';
    } elseif (!in_array($doctor['specialty'], $specialties, true)) {
        $errorMessage = 'Silakan pilih spesialisasi yang valid.';
    } elseif ($doctor['work_days'] === '') {
        $errorMessage = 'Hari praktik wajib diisi.';
    } elseif (mb_strlen($doctor['work_days']) > 255) {
        $errorMessage = 'Jadwal hari praktik maksimal 255 karakter.';
    } elseif (
        !dokterValidTime($doctor['start_time'])
        || !dokterValidTime($doctor['end_time'])
    ) {
        $errorMessage = 'Format jam praktik tidak valid.';
    } elseif ($doctor['start_time'] >= $doctor['end_time']) {
        $errorMessage = 'Jam selesai harus lebih besar daripada jam mulai.';
    } elseif ($doctor['room'] === '') {
        $errorMessage = 'Ruangan dokter wajib diisi.';
    } elseif (mb_strlen($doctor['room']) > 100) {
        $errorMessage = 'Nama ruangan maksimal 100 karakter.';
    } elseif (!array_key_exists($doctor['status'], $statuses)) {
        $errorMessage = 'Status dokter tidak valid.';
    } elseif (mb_strlen($doctor['education']) > 255) {
        $errorMessage = 'Pendidikan maksimal 255 karakter.';
    } elseif (mb_strlen($doctor['experience']) > 255) {
        $errorMessage = 'Pengalaman maksimal 255 karakter.';
    } elseif (mb_strlen($doctor['biography']) > 5000) {
        $errorMessage = 'Biografi maksimal 5000 karakter.';
    }

    /*
    |--------------------------------------------------------------------------
    | Validasi dan upload foto.
    |--------------------------------------------------------------------------
    */
    $uploadedPhoto = $_FILES['photo'] ?? null;

    if ($errorMessage === '' && is_array($uploadedPhoto)) {
        $uploadError = (int) (
            $uploadedPhoto['error'] ?? UPLOAD_ERR_NO_FILE
        );

        if ($uploadError !== UPLOAD_ERR_NO_FILE) {
            if (
                $uploadError === UPLOAD_ERR_INI_SIZE
                || $uploadError === UPLOAD_ERR_FORM_SIZE
            ) {
                $errorMessage = 'Ukuran foto maksimal 2 MB.';
            } elseif ($uploadError !== UPLOAD_ERR_OK) {
                $errorMessage = 'Upload foto gagal. Silakan coba lagi.';
            } elseif (
                (int) ($uploadedPhoto['size'] ?? 0) > 2 * 1024 * 1024
            ) {
                $errorMessage = 'Ukuran foto maksimal 2 MB.';
            } elseif (
                empty($uploadedPhoto['tmp_name'])
                || !is_uploaded_file((string) $uploadedPhoto['tmp_name'])
            ) {
                $errorMessage = 'File upload tidak valid.';
            } else {
                $tmpFile = (string) $uploadedPhoto['tmp_name'];

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($tmpFile);

                $allowedTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];

                $imageInfo = @getimagesize($tmpFile);

                if (
                    !is_string($mimeType)
                    || !isset($allowedTypes[$mimeType])
                    || $imageInfo === false
                    || ($imageInfo['mime'] ?? '') !== $mimeType
                ) {
                    $errorMessage =
                        'Foto harus berupa gambar JPG, PNG, atau WEBP yang valid.';
                } else {
                    if (
                        !is_dir($uploadDirectory)
                        && !mkdir($uploadDirectory, 0755, true)
                        && !is_dir($uploadDirectory)
                    ) {
                        $errorMessage =
                            'Folder uploads/doctors tidak dapat dibuat.';
                    } else {
                        $filename = bin2hex(random_bytes(16))
                            . '.' . $allowedTypes[$mimeType];

                        $destination = $uploadDirectory
                            . DIRECTORY_SEPARATOR . $filename;

                        if (move_uploaded_file($tmpFile, $destination)) {
                            $newPhotoPath = 'uploads/doctors/' . $filename;
                            $doctor['photo'] = $newPhotoPath;
                        } else {
                            $errorMessage =
                                'Foto gagal disimpan. Periksa izin folder uploads.';
                        }
                    }
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Simpan data ke database.
    |--------------------------------------------------------------------------
    */
    if ($errorMessage === '') {
        try {
            $connection->beginTransaction();

            if ($isEditing) {
                $stmt = $connection->prepare(
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

                $stmt->execute([
                    'name' => $doctor['name'],
                    'specialty' => $doctor['specialty'],
                    'work_days' => $doctor['work_days'],
                    'start_time' => $doctor['start_time'],
                    'end_time' => $doctor['end_time'],
                    'room' => $doctor['room'],
                    'status' => $doctor['status'],
                    'id' => $postedId,
                ]);

                $doctorId = (int) $postedId;
            } else {
                $stmt = $connection->prepare(
                    'INSERT INTO doctors
                        (name, specialty, work_days, start_time,
                         end_time, room, status)
                     VALUES
                        (:name, :specialty, :work_days, :start_time,
                         :end_time, :room, :status)'
                );

                $stmt->execute([
                    'name' => $doctor['name'],
                    'specialty' => $doctor['specialty'],
                    'work_days' => $doctor['work_days'],
                    'start_time' => $doctor['start_time'],
                    'end_time' => $doctor['end_time'],
                    'room' => $doctor['room'],
                    'status' => $doctor['status'],
                ]);

                $doctorId = (int) $connection->lastInsertId();
            }

            $stmt = $connection->prepare(
                'SELECT id FROM doctor_profiles
                 WHERE doctor_id = :doctor_id
                 LIMIT 1'
            );
            $stmt->execute(['doctor_id' => $doctorId]);
            $profileId = $stmt->fetchColumn();

            if ($profileId) {
                if ($newPhotoPath !== '') {
                    $stmt = $connection->prepare(
                        'UPDATE doctor_profiles
                         SET photo = :photo,
                             education = :education,
                             experience = :experience,
                             biography = :biography,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE doctor_id = :doctor_id'
                    );

                    $stmt->execute([
                        'photo' => $newPhotoPath,
                        'education' => $doctor['education'],
                        'experience' => $doctor['experience'],
                        'biography' => $doctor['biography'],
                        'doctor_id' => $doctorId,
                    ]);
                } else {
                    $stmt = $connection->prepare(
                        'UPDATE doctor_profiles
                         SET education = :education,
                             experience = :experience,
                             biography = :biography,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE doctor_id = :doctor_id'
                    );

                    $stmt->execute([
                        'education' => $doctor['education'],
                        'experience' => $doctor['experience'],
                        'biography' => $doctor['biography'],
                        'doctor_id' => $doctorId,
                    ]);
                }
            } else {
                if ($newPhotoPath !== '') {
                    $stmt = $connection->prepare(
                        'INSERT INTO doctor_profiles
                            (doctor_id, photo, education, experience,
                             biography, created_at, updated_at)
                         VALUES
                            (:doctor_id, :photo, :education, :experience,
                             :biography, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
                    );

                    $stmt->execute([
                        'doctor_id' => $doctorId,
                        'photo' => $newPhotoPath,
                        'education' => $doctor['education'],
                        'experience' => $doctor['experience'],
                        'biography' => $doctor['biography'],
                    ]);
                } else {
                    $stmt = $connection->prepare(
                        'INSERT INTO doctor_profiles
                            (doctor_id, education, experience, biography,
                             created_at, updated_at)
                         VALUES
                            (:doctor_id, :education, :experience, :biography,
                             CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
                    );

                    $stmt->execute([
                        'doctor_id' => $doctorId,
                        'education' => $doctor['education'],
                        'experience' => $doctor['experience'],
                        'biography' => $doctor['biography'],
                    ]);
                }
            }

            $connection->commit();

            /*
             * Hapus foto lama hanya setelah transaksi berhasil.
             */
            if (
                $newPhotoPath !== ''
                && $oldPhotoPath !== ''
                && $oldPhotoPath !== $newPhotoPath
            ) {
                $oldAbsolutePath = dokterPhotoAbsolutePath(
                    $oldPhotoPath,
                    $projectRoot
                );

                if ($oldAbsolutePath !== null && is_file($oldAbsolutePath)) {
                    @unlink($oldAbsolutePath);
                }
            }

            header('Location: data_dokter.php?success=1');
            exit;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            $errorMessage =
                'Data dokter gagal disimpan. Periksa struktur tabel database dan coba lagi.';
        }
    }

    /*
     * Jika gagal, hapus hanya file foto baru.
     */
    if ($errorMessage !== '' && $newPhotoPath !== '') {
        $newAbsolutePath = dokterPhotoAbsolutePath(
            $newPhotoPath,
            $projectRoot
        );

        if ($newAbsolutePath !== null && is_file($newAbsolutePath)) {
            @unlink($newAbsolutePath);
        }

        $newPhotoPath = '';
        $doctor['photo'] = $oldPhotoPath;
    }
}

$pageTitle = $isEditing ? 'Edit Dokter' : 'Tambah Dokter';
$csrfToken = adminCsrfToken();

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= dokterEscape($pageTitle) ?> | RS Medika Sehat</title>

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">

    <style>
        /* Tampilan halaman tambah/edit dokter */
        .doctor-edit-page {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
        }

        .doctor-edit-heading {
            margin-bottom: 24px;
        }

        .doctor-edit-heading h1 {
            margin: 0 0 8px;
            color: #1e293b;
            font-size: 30px;
            font-weight: 700;
        }

        .doctor-edit-heading p {
            margin: 0;
            color: #64748b;
            line-height: 1.7;
        }

        /* Form utama berwarna putih */
        .doctor-edit-form {
            width: 100%;
            padding: 28px;
            box-sizing: border-box;
            color: #1e293b;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }

        .doctor-edit-form .doctor-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .doctor-edit-form .form-group {
            display: flex;
            flex-direction: column;
            gap: 9px;
            min-width: 0;
            margin: 0 0 22px;
        }

        .doctor-edit-form .doctor-form-grid .form-group {
            margin: 0;
        }

        .doctor-edit-form .form-group label {
            color: #334155;
            font-size: 14px;
            font-weight: 600;
        }

        /* Input, pilihan, dan textarea */
        .doctor-edit-form .form-group input:not([type="file"]),
        .doctor-edit-form .form-group select,
        .doctor-edit-form .form-group textarea {
            width: 100%;
            min-height: 46px;
            box-sizing: border-box;
            padding: 12px 14px;
            color: #1e293b;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            font: inherit;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .doctor-edit-form .form-group select {
            cursor: pointer;
        }

        .doctor-edit-form .form-group textarea {
            min-height: 130px;
            resize: vertical;
        }

        .doctor-edit-form .form-group input:focus,
        .doctor-edit-form .form-group select:focus,
        .doctor-edit-form .form-group textarea:focus {
            background: #ffffff;
            border-color: #0891b2;
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.12);
        }

        /* Upload foto */
        .doctor-edit-form .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
            color: #334155;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 9px;
        }

        .doctor-edit-form .form-group small {
            color: #64748b;
            font-size: 12px;
            line-height: 1.7;
        }

        /* Pemisah dan judul profil */
        .doctor-edit-form .doctor-form-divider {
            margin: 28px 0;
            border: 0;
            border-top: 1px solid #e2e8f0;
        }

        .doctor-edit-form .doctor-section-title {
            margin: 0 0 22px;
            color: #0891b2;
            font-size: 20px;
            font-weight: 700;
        }

        /* Pratinjau foto */
        .doctor-edit-form .doctor-photo-preview {
            margin-bottom: 12px;
        }

        .doctor-edit-form .doctor-photo-preview img {
            display: block;
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
        }

        .doctor-edit-form .doctor-photo-preview p {
            color: #64748b;
            font-size: 12px;
            line-height: 1.7;
        }

        /* Tombol */
        .doctor-edit-form .form-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid #e2e8f0;
        }

        .doctor-edit-form .form-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 22px;
            box-sizing: border-box;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
        }

        .doctor-edit-form .form-actions .btn-primary {
            color: #ffffff;
            background: #0891b2;
            border: 1px solid #0891b2;
        }

        .doctor-edit-form .form-actions .btn-primary:hover {
            background: #0e7490;
            border-color: #0e7490;
        }

        .doctor-edit-form .form-actions .btn-secondary {
            color: #334155;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
        }

        .doctor-edit-form .form-actions .btn-secondary:hover {
            background: #e2e8f0;
        }

        /* Pesan kesalahan */
        .doctor-edit-error {
            margin-bottom: 20px;
            padding: 14px 16px;
            color: #991b1b;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
        }

        /* Tampilan HP */
        @media (max-width: 700px) {
            .doctor-edit-heading h1 {
                font-size: 25px;
            }

            .doctor-edit-form {
                padding: 20px 16px;
            }

            .doctor-edit-form .doctor-form-grid {
                grid-template-columns: minmax(0, 1fr);
                gap: 18px;
            }

            .doctor-edit-form .form-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .doctor-edit-form .form-actions .btn {
                width: 100%;
            }
        }
    </style>

    <script src="../../js/pages/admin.js" defer></script>
</head>

<body class="admin-page">

    <?php require __DIR__ . '/../partials/header.php'; ?>

    <div class="admin-layout">
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="admin-main" id="dashboard">
            <div class="doctor-edit-page">

                <div class="doctor-edit-heading">
                    <h1><?= dokterEscape($pageTitle) ?></h1>
                    <p>Lengkapi informasi dokter dan foto profilnya.</p>
                </div>

                <?php if ($errorMessage !== ''): ?>
                    <div class="doctor-edit-error" role="alert">
                        <?= dokterEscape($errorMessage) ?>
                    </div>
                <?php endif; ?>

                <form
                    method="post"
                    enctype="multipart/form-data"
                    class="admin-form doctor-edit-form">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= dokterEscape($csrfToken) ?>">

                    <?php if ($isEditing && $doctor['id']): ?>
                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $doctor['id'] ?>">
                    <?php endif; ?>

                    <div class="doctor-form-grid">
                        <div class="form-group">
                            <label for="name">Nama dokter</label>
                            <input
                                id="name"
                                type="text"
                                name="name"
                                maxlength="150"
                                value="<?= dokterEscape($doctor['name']) ?>"
                                placeholder="Masukkan nama dokter"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="specialty">Spesialisasi</label>
                            <select id="specialty" name="specialty" required>
                                <option value="">Pilih spesialisasi</option>

                                <?php foreach ($specialties as $specialty): ?>
                                    <option
                                        value="<?= dokterEscape($specialty) ?>"
                                        <?= $doctor['specialty'] === $specialty
                                            ? 'selected'
                                            : '' ?>>
                                        <?= dokterEscape($specialty) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="work_days">Hari praktik</label>
                            <input
                                id="work_days"
                                type="text"
                                name="work_days"
                                maxlength="255"
                                placeholder="Contoh: Senin, Rabu, Jumat"
                                value="<?= dokterEscape($doctor['work_days']) ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="start_time">Jam mulai</label>
                            <input
                                id="start_time"
                                type="time"
                                name="start_time"
                                value="<?= dokterEscape(
                                            substr((string) $doctor['start_time'], 0, 5)
                                        ) ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="end_time">Jam selesai</label>
                            <input
                                id="end_time"
                                type="time"
                                name="end_time"
                                value="<?= dokterEscape(
                                            substr((string) $doctor['end_time'], 0, 5)
                                        ) ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="room">Ruangan</label>
                            <input
                                id="room"
                                type="text"
                                name="room"
                                maxlength="100"
                                placeholder="Contoh: Ruang 201"
                                value="<?= dokterEscape($doctor['room']) ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" required>
                                <?php foreach ($statuses as $value => $label): ?>
                                    <option
                                        value="<?= dokterEscape($value) ?>"
                                        <?= $doctor['status'] === $value
                                            ? 'selected'
                                            : '' ?>>
                                        <?= dokterEscape($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <hr class="doctor-form-divider">

                    <h2 class="doctor-section-title">Profil dokter</h2>

                    <div class="form-group">
                        <label for="photo">Foto dokter</label>

                        <?php if (!empty($doctor['photo'])): ?>
                            <div class="doctor-photo-preview">
                                <img
                                    src="/rumah_sakit/<?= dokterEscape(
                                                            $doctor['photo']
                                                        ) ?>"
                                    alt="Foto <?= dokterEscape($doctor['name']) ?>">
                                <p>
                                    Foto saat ini. Pilih file baru jika ingin
                                    menggantinya.
                                </p>
                            </div>
                        <?php endif; ?>

                        <input
                            id="photo"
                            type="file"
                            name="photo"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                        <small>
                            Format JPG, PNG, atau WEBP. Maksimal 2 MB.
                            Kosongkan jika tidak ingin mengganti foto.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="education">Pendidikan</label>
                        <input
                            id="education"
                            type="text"
                            name="education"
                            maxlength="255"
                            placeholder="Contoh: S1 Kedokteran, Spesialis Jantung"
                            value="<?= dokterEscape($doctor['education']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="experience">Pengalaman</label>
                        <input
                            id="experience"
                            type="text"
                            name="experience"
                            maxlength="255"
                            placeholder="Contoh: 8 tahun"
                            value="<?= dokterEscape($doctor['experience']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="biography">Biografi</label>
                        <textarea
                            id="biography"
                            name="biography"
                            rows="5"
                            maxlength="5000"
                            placeholder="Tuliskan profil singkat dokter"><?= dokterEscape($doctor['biography']) ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?= $isEditing
                                ? 'Simpan Perubahan'
                                : 'Tambah Dokter' ?>
                        </button>

                        <a
                            href="data_dokter.php"
                            class="btn btn-secondary">
                            Batal
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

</body>

</html>