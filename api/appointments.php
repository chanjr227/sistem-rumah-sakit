<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../pasien/auth.php';

startPatientSession();

function respond(int $statusCode, array $body): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    respond(405, ['message' => 'Metode permintaan tidak diizinkan.']);
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$doctorId = filter_var(
    $_POST['doctor_id'] ?? null,
    FILTER_VALIDATE_INT
);
$date = trim((string) ($_POST['date'] ?? ''));

if ($name === '' || strlen($name) > 255) {
    respond(422, ['message' => 'Nama wajib diisi dan maksimal 255 byte.']);
}

if (
    $email === ''
    || strlen($email) > 254
    || filter_var($email, FILTER_VALIDATE_EMAIL) === false
) {
    respond(422, ['message' => 'Alamat email tidak valid.']);
}

if (!$doctorId || $doctorId < 1) {
    respond(422, ['message' => 'Dokter yang dipilih tidak valid.']);
}

$visitDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
$dateErrors = DateTimeImmutable::getLastErrors();

if (
    $visitDate === false
    || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
    || $visitDate->format('Y-m-d') !== $date
    || $date < (new DateTimeImmutable('today'))->format('Y-m-d')
) {
    respond(422, ['message' => 'Tanggal konsultasi tidak valid atau sudah berlalu.']);
}

try {
    $connection = getDatabaseConnection();
    $doctorStatement = $connection->prepare(
        "SELECT id, specialty
         FROM doctors
         WHERE id = :id AND status = 'available'
         LIMIT 1"
    );
    $doctorStatement->execute(['id' => $doctorId]);
    $doctor = $doctorStatement->fetch();

    if (!$doctor) {
        respond(422, ['message' => 'Dokter yang dipilih tidak tersedia.']);
    }

    $connection->beginTransaction();
    $patientId = isset($_SESSION['patient_id'])
        ? (int) $_SESSION['patient_id']
        : null;

    if ($patientId !== null) {
        $patientStatement = $connection->prepare(
            'SELECT id, name, email
             FROM patients
             WHERE id = :id
             FOR UPDATE'
        );
        $patientStatement->execute(['id' => $patientId]);
        $patient = $patientStatement->fetch();

        if (!$patient) {
            $connection->rollBack();
            respond(401, ['message' => 'Sesi pasien tidak valid. Silakan login kembali.']);
        }

        $assignedDoctorStatement = $connection->prepare(
            "SELECT doctor_id
             FROM appointments
             WHERE patient_id = :patient_id
               AND status IN ('pending', 'confirmed')
               AND doctor_id IS NOT NULL
             ORDER BY id ASC
             LIMIT 1"
        );
        $assignedDoctorStatement->execute(['patient_id' => $patientId]);
        $assignedDoctorId = $assignedDoctorStatement->fetchColumn();

        if (
            $assignedDoctorId !== false
            && (int) $assignedDoctorId !== $doctorId
        ) {
            $connection->rollBack();
            respond(
                409,
                ['message' => 'Pasien sudah terdaftar pada dokter lain. Hubungi admin jika ingin mengganti dokter.']
            );
        }

        $name = $patient['name'];
        $email = $patient['email'];
    }

    $statement = $connection->prepare(
        'INSERT INTO appointments
            (patient_id, name, email, doctor_id, visit_date)
         VALUES
            (:patient_id, :name, :email, :doctor_id, :visit_date)'
    );
    $statement->execute([
        'patient_id' => $patientId,
        'name' => $name,
        'email' => $email,
        'doctor_id' => $doctorId,
        'visit_date' => $date,
    ]);
    $connection->commit();
} catch (PDOException $exception) {
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }

    error_log('Appointment database error: ' . $exception->getMessage());
    respond(500, ['message' => 'Permintaan belum dapat disimpan. Silakan coba lagi nanti.']);
}

respond(201, ['message' => 'Permintaan konsultasi Anda berhasil dikirim.']);
