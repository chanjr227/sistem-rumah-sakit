<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

function inputRekamEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$connection = getDatabaseConnection();

$patients = [];
$doctors = [];
$errorMessage = null;
$successMessage = null;

$patientId = '';
$doctorId = '';
$visitDate = date('Y-m-d');
$complaint = '';
$diagnosis = '';
$notes = '';
$status = 'Selesai';

$allowedStatuses = [
    'Menunggu',
    'Sedang Diperiksa',
    'Selesai',
    'Dibatalkan',
];

try {
    $patients = $connection->query(
        'SELECT id, name, medical_record_number
         FROM patients
         ORDER BY name ASC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $doctors = $connection->query(
        "SELECT id, name, specialty
         FROM doctors
         WHERE status = 'available'
         ORDER BY name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('Load medical record form error: ' . $exception->getMessage());
    $errorMessage = 'Data pasien atau dokter gagal dimuat. Periksa struktur tabel.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $patientId = trim((string) ($_POST['patient_id'] ?? ''));
    $doctorId = trim((string) ($_POST['doctor_id'] ?? ''));
    $visitDate = trim((string) ($_POST['visit_date'] ?? ''));
    $complaint = trim((string) ($_POST['complaint'] ?? ''));
    $diagnosis = trim((string) ($_POST['diagnosis'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? ''));

    if (
        !ctype_digit($patientId)
        || !ctype_digit($doctorId)
        || (int) $patientId < 1
        || (int) $doctorId < 1
    ) {
        $errorMessage = 'Pilih pasien dan dokter yang valid.';
    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $visitDate)
        || !checkdate(
            (int) substr($visitDate, 5, 2),
            (int) substr($visitDate, 8, 2),
            (int) substr($visitDate, 0, 4)
        )
    ) {
        $errorMessage = 'Tanggal pemeriksaan tidak valid.';
    } elseif ($complaint === '') {
        $errorMessage = 'Keluhan pasien wajib diisi.';
    } elseif ($diagnosis === '') {
        $errorMessage = 'Diagnosis wajib diisi.';
    } elseif (!in_array($status, $allowedStatuses, true)) {
        $errorMessage = 'Status pemeriksaan tidak valid.';
    } else {
        try {
            $patientCheck = $connection->prepare(
                'SELECT id FROM patients WHERE id = :id'
            );
            $patientCheck->execute(['id' => (int) $patientId]);

            $doctorCheck = $connection->prepare(
                "SELECT id FROM doctors
                 WHERE id = :id AND status = 'available'"
            );
            $doctorCheck->execute(['id' => (int) $doctorId]);

            if (!$patientCheck->fetchColumn()) {
                $errorMessage = 'Pasien tidak ditemukan.';
            } elseif (!$doctorCheck->fetchColumn()) {
                $errorMessage = 'Dokter tidak ditemukan atau tidak aktif.';
            } else {
                $insert = $connection->prepare(
                    'INSERT INTO patient_visits (
                        patient_id,
                        doctor_id,
                        visit_date,
                        complaint,
                        diagnosis,
                        status,
                        notes
                    ) VALUES (
                        :patient_id,
                        :doctor_id,
                        :visit_date,
                        :complaint,
                        :diagnosis,
                        :status,
                        :notes
                    )'
                );

                $insert->execute([
                    'patient_id' => (int) $patientId,
                    'doctor_id' => (int) $doctorId,
                    'visit_date' => $visitDate,
                    'complaint' => $complaint,
                    'diagnosis' => $diagnosis,
                    'status' => $status,
                    'notes' => $notes !== '' ? $notes : null,
                ]);

                $successMessage = 'Rekam medis berhasil disimpan.';

                $patientId = '';
                $doctorId = '';
                $visitDate = date('Y-m-d');
                $complaint = '';
                $diagnosis = '';
                $notes = '';
                $status = 'Selesai';
            }
        } catch (PDOException $exception) {
            error_log('Save medical record error: ' . $exception->getMessage());
            $errorMessage = 'Rekam medis gagal disimpan. Silakan periksa database.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Input Rekam Medis - RS Medika Sehat</title>

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">
    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .rekam-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .rekam-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .rekam-field-full {
            grid-column: 1 / -1;
        }

        .rekam-field label {
            font-size: 14px;
            font-weight: 700;
            color: #344054;
        }

        .rekam-field input,
        .rekam-field select,
        .rekam-field textarea {
            width: 100%;
            min-height: 44px;
            padding: 11px 12px;
            border: 1px solid #d0d5dd;
            border-radius: 8px;
            background: #fff;
            color: #17212f;
            font: inherit;
        }

        .rekam-field textarea {
            min-height: 115px;
            resize: vertical;
        }

        .rekam-field input:focus,
        .rekam-field select:focus,
        .rekam-field textarea:focus {
            outline: 2px solid #b2ddff;
            border-color: #1570ef;
        }

        .rekam-form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            grid-column: 1 / -1;
            padding-top: 8px;
        }

        .rekam-alert {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 9px;
            line-height: 1.6;
        }

        .rekam-alert-error {
            color: #b42318;
            background: #fee4e2;
        }

        .rekam-alert-success {
            color: #18794e;
            background: #e7f8ef;
        }

        .rekam-info {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 9px;
            background: #eff8ff;
            color: #175cd3;
            font-size: 13px;
            line-height: 1.6;
        }

        @media (max-width: 650px) {
            .rekam-form {
                grid-template-columns: 1fr;
            }

            .rekam-field-full,
            .rekam-form-actions {
                grid-column: auto;
            }
        }
    </style>
</head>

<body class="admin-page">

    <?php require __DIR__ . '/../partials/header.php'; ?>

    <div class="admin-layout">

        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="admin-main" id="dashboard">

            <div class="admin-page-heading">
                <div>
                    <p class="eyebrow">Manajemen rumah sakit</p>
                    <h1>Input Rekam Medis</h1>
                    <p class="admin-intro">
                        Catat hasil pemeriksaan dan diagnosis pasien.
                    </p>
                </div>
            </div>

            <?php require __DIR__ . '/../partials/flash_message.php'; ?>

            <?php if ($errorMessage !== null): ?>
                <div class="rekam-alert rekam-alert-error" role="alert">
                    <?= inputRekamEscape($errorMessage) ?>
                </div>
            <?php endif; ?>

            <?php if ($successMessage !== null): ?>
                <div class="rekam-alert rekam-alert-success" role="status">
                    <?= inputRekamEscape($successMessage) ?>
                    <a href="../pasien/rekam_medis.php">
                        Lihat Rekam Medis
                    </a>
                </div>
            <?php endif; ?>

            <div class="rekam-info">
                <strong>Petunjuk:</strong>
                pilih pasien dan dokter, lalu lengkapi keluhan, diagnosis,
                serta catatan pemeriksaan. Data akan tersimpan pada riwayat
                kunjungan pasien.
            </div>

            <section class="admin-panel">

                <form method="post" action="input_rekam_medis.php" class="rekam-form">

                    <div class="rekam-field">
                        <label for="patient_id">Pasien *</label>

                        <select id="patient_id" name="patient_id" required>
                            <option value="">-- Pilih Pasien --</option>

                            <?php foreach ($patients as $patient): ?>
                                <option
                                    value="<?= (int) $patient['id'] ?>"
                                    <?= $patientId === (string) $patient['id'] ? 'selected' : '' ?>>
                                    <?= inputRekamEscape($patient['name']) ?>
                                    (<?= inputRekamEscape(
                                            $patient['medical_record_number'] ?: 'Tanpa No. RM'
                                        ) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="rekam-field">
                        <label for="doctor_id">Dokter Pemeriksa *</label>

                        <select id="doctor_id" name="doctor_id" required>
                            <option value="">-- Pilih Dokter --</option>

                            <?php foreach ($doctors as $doctor): ?>
                                <option
                                    value="<?= (int) $doctor['id'] ?>"
                                    <?= $doctorId === (string) $doctor['id'] ? 'selected' : '' ?>>
                                    <?= inputRekamEscape($doctor['name']) ?>
                                    - <?= inputRekamEscape($doctor['specialty']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="rekam-field">
                        <label for="visit_date">Tanggal Pemeriksaan *</label>

                        <input
                            type="date"
                            id="visit_date"
                            name="visit_date"
                            value="<?= inputRekamEscape($visitDate) ?>"
                            required>
                    </div>

                    <div class="rekam-field">
                        <label for="status">Status Pemeriksaan *</label>

                        <select id="status" name="status" required>
                            <?php foreach ($allowedStatuses as $allowedStatus): ?>
                                <option
                                    value="<?= inputRekamEscape($allowedStatus) ?>"
                                    <?= $status === $allowedStatus ? 'selected' : '' ?>>
                                    <?= inputRekamEscape($allowedStatus) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="rekam-field rekam-field-full">
                        <label for="complaint">Keluhan Pasien *</label>

                        <textarea
                            id="complaint"
                            name="complaint"
                            placeholder="Tuliskan keluhan yang disampaikan pasien..."
                            required><?= inputRekamEscape($complaint) ?></textarea>
                    </div>

                    <div class="rekam-field rekam-field-full">
                        <label for="diagnosis">Diagnosis *</label>

                        <textarea
                            id="diagnosis"
                            name="diagnosis"
                            placeholder="Tuliskan diagnosis berdasarkan pemeriksaan..."
                            required><?= inputRekamEscape($diagnosis) ?></textarea>
                    </div>

                    <div class="rekam-field rekam-field-full">
                        <label for="notes">Catatan Pemeriksaan</label>

                        <textarea
                            id="notes"
                            name="notes"
                            placeholder="Tuliskan hasil pemeriksaan atau catatan tambahan..."><?= inputRekamEscape($notes) ?></textarea>
                    </div>

                    <div class="rekam-form-actions">
                        <button
                            type="submit"
                            class="btn btn-primary">
                            Simpan Rekam Medis
                        </button>

                        <a
                            href="input_rekam_medis.php"
                            class="btn btn-secondary">
                            Reset Form
                        </a>

                        <a
                            href="../pasien/rekam_medis.php"
                            class="btn btn-secondary">
                            Lihat Rekam Medis
                        </a>
                    </div>

                </form>

            </section>

        </main>
    </div>

</body>

</html>