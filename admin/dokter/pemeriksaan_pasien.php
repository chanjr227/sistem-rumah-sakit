<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireDoctorLogin();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$connection = getDatabaseConnection();
$doctorId = (int) $_SESSION['doctor_id'];

function pemeriksaanEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$errors = [];
$success = '';

$search = trim($_GET['search'] ?? '');
$selectedPatient = (int) ($_GET['patient_id'] ?? 0);

// Ambil daftar pasien
try {
    $sql = "
        SELECT p.id, p.name, p.medical_record_number
        FROM patients p
        WHERE EXISTS (
            SELECT 1
            FROM appointments a
            WHERE a.patient_id = p.id
              AND a.doctor_id = :doctor_id
              AND a.status IN ('pending', 'confirmed')
        )
    ";

    $params = ['doctor_id' => $doctorId];

    if ($search !== '') {
        $sql .= "
            AND (
                p.name LIKE :search
                OR p.medical_record_number LIKE :search
            )
        ";

        $params['search'] = '%' . $search . '%';
    }

    $sql .= " ORDER BY p.name ASC";

    $stmt = $connection->prepare($sql);
    $stmt->execute($params);

    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $patients = [];
    $errors[] = 'Gagal mengambil data pasien. Periksa struktur tabel patients.';
}

// Ambil pasien yang dipilih
$patient = null;

if ($selectedPatient > 0) {
    try {
        $stmt = $connection->prepare(
            "SELECT p.id, p.name, p.medical_record_number
             FROM patients p
             WHERE p.id = :patient_id
               AND EXISTS (
                   SELECT 1
                   FROM appointments a
                   WHERE a.patient_id = p.id
                     AND a.doctor_id = :doctor_id
                     AND a.status IN ('pending', 'confirmed')
               )
             LIMIT 1"
        );

        $stmt->execute([
            'patient_id' => $selectedPatient,
            'doctor_id' => $doctorId,
        ]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$patient) {
            $errors[] = 'Pasien tidak ditemukan atau tidak terdaftar pada dokter Anda.';
        }
    } catch (PDOException $e) {
        $errors[] = 'Gagal mengambil detail pasien.';
    }
}

// Simpan hasil pemeriksaan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int) ($_POST['patient_id'] ?? 0);

    $complaint = trim($_POST['chief_complaint'] ?? '');
    $bloodPressure = trim($_POST['blood_pressure'] ?? '');
    $temperature = trim($_POST['temperature'] ?? '');
    $pulse = trim($_POST['pulse'] ?? '');
    $respiratoryRate = trim($_POST['respiratory_rate'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $doctorNotes = trim($_POST['doctor_notes'] ?? '');

    if ($patientId <= 0) {
        $errors[] = 'Pasien harus dipilih.';
    }

    if ($diagnosis === '') {
        $errors[] = 'Diagnosis wajib diisi.';
    }

    if ($temperature !== '' && (
        !is_numeric($temperature) ||
        (float) $temperature < 25 ||
        (float) $temperature > 45
    )) {
        $errors[] = 'Suhu tubuh tidak valid.';
    }

    if ($pulse !== '' && (
        !ctype_digit($pulse) ||
        (int) $pulse < 1 ||
        (int) $pulse > 300
    )) {
        $errors[] = 'Denyut nadi tidak valid.';
    }

    if ($respiratoryRate !== '' && (
        !ctype_digit($respiratoryRate) ||
        (int) $respiratoryRate < 1 ||
        (int) $respiratoryRate > 100
    )) {
        $errors[] = 'Frekuensi pernapasan tidak valid.';
    }

    // Pastikan pasien benar-benar tersedia
    if (!$errors) {
        $checkPatient = $connection->prepare(
            "SELECT p.id
             FROM patients p
             WHERE p.id = :patient_id
               AND EXISTS (
                   SELECT 1
                   FROM appointments a
                   WHERE a.patient_id = p.id
                     AND a.doctor_id = :doctor_id
                     AND a.status IN ('pending', 'confirmed')
               )
             LIMIT 1"
        );

        $checkPatient->execute([
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
        ]);

        if (!$checkPatient->fetch()) {
            $errors[] = 'Pasien tidak ditemukan atau tidak terdaftar pada dokter Anda.';
        }
    }

    if (!$errors) {
        try {
            $stmt = $connection->prepare("
                INSERT INTO doctor_examinations (
                    patient_id,
                    chief_complaint,
                    blood_pressure,
                    temperature,
                    pulse,
                    respiratory_rate,
                    diagnosis,
                    treatment,
                    doctor_notes
                ) VALUES (
                    :patient_id,
                    :complaint,
                    :blood_pressure,
                    :temperature,
                    :pulse,
                    :respiratory_rate,
                    :diagnosis,
                    :treatment,
                    :doctor_notes
                )
            ");

            $stmt->execute([
                'patient_id' => $patientId,
                'complaint' => $complaint,
                'blood_pressure' => $bloodPressure,
                'temperature' => $temperature !== '' ? $temperature : null,
                'pulse' => $pulse !== '' ? $pulse : null,
                'respiratory_rate' => $respiratoryRate !== '' ? $respiratoryRate : null,
                'diagnosis' => $diagnosis,
                'treatment' => $treatment,
                'doctor_notes' => $doctorNotes,
            ]);

            header(
                'Location: pemeriksaan_pasien.php?success=1&patient_id=' . $patientId
            );
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Gagal menyimpan hasil pemeriksaan.';
        }
    }

    $selectedPatient = $patientId;
}

if (isset($_GET['success'])) {
    $success = 'Hasil pemeriksaan berhasil disimpan.';
}

// Ambil kembali pasien terpilih jika belum tersedia
if ($selectedPatient > 0 && !$patient) {
    $stmt = $connection->prepare(
        "SELECT p.id, p.name, p.medical_record_number
         FROM patients p
         WHERE p.id = :patient_id
           AND EXISTS (
               SELECT 1
               FROM appointments a
               WHERE a.patient_id = p.id
                 AND a.doctor_id = :doctor_id
                 AND a.status IN ('pending', 'confirmed')
           )
         LIMIT 1"
    );

    $stmt->execute([
        'patient_id' => $selectedPatient,
        'doctor_id' => $doctorId,
    ]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pemeriksaan Pasien | RS Medika Sehat</title>

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
                    <p class="eyebrow">Pelayanan medis</p>
                    <h1>Pemeriksaan Pasien</h1>
                    <p class="admin-intro">
                        Kelola pemeriksaan, diagnosis, dan tindakan medis pasien.
                    </p>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="admin-panel">
                    <p><?= pemeriksaanEscape($success) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="admin-panel">
                    <?php foreach ($errors as $error): ?>
                        <p><?= pemeriksaanEscape($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- DAFTAR PASIEN -->
            <section class="admin-panel">

                <div class="admin-section-heading">
                    <div>
                        <h2>Daftar Pasien</h2>
                        <p class="admin-intro">
                            Pilih pasien untuk melakukan pemeriksaan.
                        </p>
                    </div>
                </div>

                <form method="GET" class="admin-filter-form">

                    <input
                        type="text"
                        name="search"
                        placeholder="Cari nama atau nomor rekam medis..."
                        value="<?= pemeriksaanEscape($search) ?>">

                    <button type="submit" class="btn btn-primary">
                        Cari Pasien
                    </button>

                </form>

                <div class="admin-table-wrapper">
                    <table class="admin-table">

                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nomor Rekam Medis</th>
                                <th>Nama Pasien</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (!$patients): ?>
                                <tr>
                                    <td colspan="4">
                                        Data pasien tidak ditemukan.
                                    </td>
                                </tr>
                            <?php else: ?>

                                <?php foreach ($patients as $index => $row): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>

                                        <td>
                                            <?= pemeriksaanEscape(
                                                $row['medical_record_number']
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= pemeriksaanEscape($row['name']) ?>
                                        </td>

                                        <td>
                                            <a
                                                href="pemeriksaan_pasien.php?patient_id=<?= (int) $row['id'] ?>"
                                                class="btn btn-primary">
                                                Periksa
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>
                </div>

            </section>

            <!-- FORM PEMERIKSAAN -->
            <?php if ($patient): ?>

                <section class="admin-panel">

                    <div class="admin-section-heading">
                        <div>
                            <h2>Formulir Pemeriksaan</h2>
                            <p class="admin-intro">
                                Masukkan hasil pemeriksaan pasien.
                            </p>
                        </div>
                    </div>

                    <div class="admin-panel">
                        <p>
                            <strong>Nama Pasien:</strong>
                            <?= pemeriksaanEscape($patient['name']) ?>
                        </p>

                        <p>
                            <strong>No. Rekam Medis:</strong>
                            <?= pemeriksaanEscape(
                                $patient['medical_record_number']
                            ) ?>
                        </p>
                    </div>

                    <form method="POST" action="pemeriksaan_pasien.php">

                        <input
                            type="hidden"
                            name="patient_id"
                            value="<?= (int) $patient['id'] ?>">

                        <div class="admin-form-grid">

                            <div class="admin-form-group">
                                <label>Keluhan Utama</label>
                                <textarea
                                    name="chief_complaint"
                                    rows="3"
                                    placeholder="Masukkan keluhan pasien..."></textarea>
                            </div>

                            <div class="admin-form-group">
                                <label>Tekanan Darah</label>
                                <input
                                    type="text"
                                    name="blood_pressure"
                                    placeholder="Contoh: 120/80 mmHg">
                            </div>

                            <div class="admin-form-group">
                                <label>Suhu Tubuh (°C)</label>
                                <input
                                    type="number"
                                    name="temperature"
                                    step="0.1"
                                    min="25"
                                    max="45"
                                    placeholder="Contoh: 36.5">
                            </div>

                            <div class="admin-form-group">
                                <label>Denyut Nadi (bpm)</label>
                                <input
                                    type="number"
                                    name="pulse"
                                    min="1"
                                    max="300"
                                    placeholder="Contoh: 80">
                            </div>

                            <div class="admin-form-group">
                                <label>Frekuensi Pernapasan (kali/menit)</label>
                                <input
                                    type="number"
                                    name="respiratory_rate"
                                    min="1"
                                    max="100"
                                    placeholder="Contoh: 20">
                            </div>

                            <div class="admin-form-group">
                                <label>Diagnosis *</label>
                                <textarea
                                    name="diagnosis"
                                    rows="3"
                                    required
                                    placeholder="Masukkan diagnosis..."></textarea>
                            </div>

                            <div class="admin-form-group">
                                <label>Tindakan Medis</label>
                                <textarea
                                    name="treatment"
                                    rows="3"
                                    placeholder="Masukkan tindakan medis..."></textarea>
                            </div>

                            <div class="admin-form-group">
                                <label>Catatan Dokter</label>
                                <textarea
                                    name="doctor_notes"
                                    rows="3"
                                    placeholder="Catatan tambahan..."></textarea>
                            </div>

                        </div>

                        <div class="admin-form-actions">

                            <a
                                href="pemeriksaan_pasien.php"
                                class="btn btn-secondary">
                                Batal
                            </a>

                            <button
                                type="submit"
                                class="btn btn-primary">
                                Simpan Pemeriksaan
                            </button>

                        </div>

                    </form>

                </section>

            <?php endif; ?>

        </main>

    </div>

</body>

</html>