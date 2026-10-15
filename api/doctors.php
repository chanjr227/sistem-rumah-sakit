<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['message' => 'Metode permintaan tidak diizinkan.']);
    exit;
}

try {
    $connection = getDatabaseConnection();
    $doctors = $connection->query(
        'SELECT id, name, specialty, work_days, start_time, end_time, room, status
         FROM doctors
         ORDER BY name ASC'
    )->fetchAll();

    echo json_encode($doctors, JSON_UNESCAPED_UNICODE);
} catch (PDOException $exception) {
    error_log('Public doctor list database error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Daftar dokter belum dapat dimuat.']);
}
