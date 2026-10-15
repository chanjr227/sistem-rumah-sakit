<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

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
$specialty = trim((string) ($_POST['specialty'] ?? ''));
$date = trim((string) ($_POST['date'] ?? ''));

$allowedSpecialties = [
    'Poli Umum',
    'Kesehatan Anak',
    'Jantung',
    'Neurologi',
    'Laboratorium',
];

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

if (!in_array($specialty, $allowedSpecialties, true)) {
    respond(422, ['message' => 'Poli tujuan yang dipilih tidak valid.']);
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
    $statement = $connection->prepare(
        'INSERT INTO appointments (name, email, specialty, visit_date)
         VALUES (:name, :email, :specialty, :visit_date)'
    );
    $statement->execute([
        'name' => $name,
        'email' => $email,
        'specialty' => $specialty,
        'visit_date' => $date,
    ]);
} catch (PDOException $exception) {
    error_log('Appointment database error: ' . $exception->getMessage());
    respond(500, ['message' => 'Permintaan belum dapat disimpan. Silakan coba lagi nanti.']);
}

respond(201, ['message' => 'Permintaan konsultasi Anda berhasil dikirim.']);
