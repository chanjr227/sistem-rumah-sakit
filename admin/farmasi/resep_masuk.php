<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requirePharmacyLogin();

$connection = getDatabaseConnection();
$errorMessage = '';
$successMessage = '';

function resepFarmasiEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function resepFarmasiTanggal(mixed $value): string
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime((string) $value);

    return $timestamp
        ? date('d-m-Y H:i', $timestamp)
        : '-';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $prescriptionId = filter_input(
        INPUT_POST,
        'prescription_id',
        FILTER_VALIDATE_INT
    );

    try {
        if (!verifyAdminCsrfToken()) {
            throw new RuntimeException(
                'Sesi formulir tidak valid. Silakan muat ulang halaman.'
            );
        }

        if (!$prescriptionId || $prescriptionId < 1) {
            throw new RuntimeException('Resep yang dipilih tidak valid.');
        }

        $connection->beginTransaction();

        $prescriptionQuery = $connection->prepare(
            'SELECT id, patient_id, status
             FROM prescriptions
             WHERE id = :id
             FOR UPDATE'
        );
        $prescriptionQuery->execute(['id' => $prescriptionId]);
        $prescription = $prescriptionQuery->fetch(PDO::FETCH_ASSOC);

        if (!$prescription) {
            throw new RuntimeException('Resep tidak ditemukan.');
        }

        if ($prescription['status'] !== 'Menunggu Farmasi') {
            throw new RuntimeException(
                'Resep ini sudah diproses atau tidak menunggu farmasi.'
            );
        }

        $itemsQuery = $connection->prepare(
            'SELECT medicine_id, quantity
             FROM prescription_items
             WHERE prescription_id = :prescription_id
             ORDER BY medicine_id ASC'
        );
        $itemsQuery->execute(['prescription_id' => $prescriptionId]);
        $items = $itemsQuery->fetchAll(PDO::FETCH_ASSOC);

        if (!$items) {
            throw new RuntimeException('Resep tidak memiliki rincian obat.');
        }

        $quantitiesByMedicine = [];
        foreach ($items as $item) {
            $medicineId = (int) $item['medicine_id'];
            $quantity = (int) $item['quantity'];

            if ($medicineId < 1 || $quantity < 1) {
                throw new RuntimeException(
                    'Rincian jumlah obat dalam resep tidak valid.'
                );
            }

            $quantitiesByMedicine[$medicineId] =
                ($quantitiesByMedicine[$medicineId] ?? 0) + $quantity;
        }

        ksort($quantitiesByMedicine, SORT_NUMERIC);

        $medicineQuery = $connection->prepare(
            'SELECT id, name, stock, expiry_date
             FROM medicines
             WHERE id = :id
             FOR UPDATE'
        );
        $updateStockQuery = $connection->prepare(
            'UPDATE medicines
             SET stock = stock - :quantity
             WHERE id = :id
               AND stock >= :minimum_stock'
        );
        $insertTransactionQuery = $connection->prepare(
            'INSERT INTO medicine_transactions
                (medicine_id, transaction_type, quantity, notes)
             VALUES
                (:medicine_id, :transaction_type, :quantity, :notes)'
        );

        foreach ($quantitiesByMedicine as $medicineId => $quantity) {
            $medicineQuery->execute(['id' => $medicineId]);
            $medicine = $medicineQuery->fetch(PDO::FETCH_ASSOC);

            if (!$medicine) {
                throw new RuntimeException(
                    'Salah satu obat dalam resep tidak ditemukan.'
                );
            }

            if ((int) $medicine['stock'] < $quantity) {
                throw new RuntimeException(
                    'Stok obat ' . $medicine['name'] .
                    ' tidak mencukupi. Tersedia: ' .
                    (int) $medicine['stock'] . '.'
                );
            }

            if (
                !empty($medicine['expiry_date']) &&
                $medicine['expiry_date'] < date('Y-m-d')
            ) {
                throw new RuntimeException(
                    'Obat ' . $medicine['name'] . ' sudah kedaluwarsa.'
                );
            }

            $updateStockQuery->execute([
                'quantity' => $quantity,
                'id' => $medicineId,
                'minimum_stock' => $quantity,
            ]);

            if ($updateStockQuery->rowCount() !== 1) {
                throw new RuntimeException(
                    'Stok obat ' . $medicine['name'] .
                    ' berubah. Silakan muat ulang dan coba kembali.'
                );
            }

            $insertTransactionQuery->execute([
                'medicine_id' => $medicineId,
                'transaction_type' => 'keluar',
                'quantity' => $quantity,
                'notes' => 'Penyerahan resep #' . $prescriptionId .
                    ' untuk pasien #' . (int) $prescription['patient_id'],
            ]);
        }

        $updatePrescriptionQuery = $connection->prepare(
            "UPDATE prescriptions
             SET status = 'Sudah Diserahkan'
             WHERE id = :id
               AND status = 'Menunggu Farmasi'"
        );
        $updatePrescriptionQuery->execute(['id' => $prescriptionId]);

        if ($updatePrescriptionQuery->rowCount() !== 1) {
            throw new RuntimeException(
                'Status resep berubah. Silakan muat ulang dan periksa antrean.'
            );
        }

        $connection->commit();
        $_SESSION['resep_farmasi_flash'] =
            'Obat pada resep berhasil diserahkan dan stok telah diperbarui.';

        header('Location: resep_masuk.php');
        exit;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        if ($exception instanceof RuntimeException) {
            $errorMessage = $exception->getMessage();
        } else {
            error_log(
                'Dispense prescription error: ' . $exception->getMessage()
            );
            $errorMessage =
                'Resep gagal diproses. Periksa koneksi dan struktur database.';
        }
    }
}

if (!empty($_SESSION['resep_farmasi_flash'])) {
    $successMessage = (string) $_SESSION['resep_farmasi_flash'];
    unset($_SESSION['resep_farmasi_flash']);
}

$prescriptions = [];
$itemsByPrescription = [];

try {
    $prescriptions = $connection->query(
        'SELECT
            pr.id,
            pr.prescription_date,
            pr.notes,
            pr.status,
            p.name AS patient_name,
            p.medical_record_number,
            d.name AS doctor_name
         FROM prescriptions pr
         INNER JOIN patients p ON p.id = pr.patient_id
         INNER JOIN doctors d ON d.id = pr.doctor_id
         ORDER BY
            CASE WHEN pr.status = \'Menunggu Farmasi\' THEN 0 ELSE 1 END,
            pr.prescription_date DESC
         LIMIT 100'
    )->fetchAll(PDO::FETCH_ASSOC);

    if ($prescriptions) {
        $prescriptionIds = array_map(
            static fn (array $prescription): int => (int) $prescription['id'],
            $prescriptions
        );
        $placeholders = [];
        $parameters = [];

        foreach ($prescriptionIds as $index => $id) {
            $placeholder = 'prescription_' . $index;
            $placeholders[] = ':' . $placeholder;
            $parameters[$placeholder] = $id;
        }

        $itemsQuery = $connection->prepare(
            'SELECT
                pi.prescription_id,
                pi.dosage,
                pi.frequency,
                pi.duration,
                pi.quantity,
                pi.instructions,
                m.name AS medicine_name,
                m.unit AS medicine_unit
             FROM prescription_items pi
             INNER JOIN medicines m ON m.id = pi.medicine_id
             WHERE pi.prescription_id IN (' .
                implode(', ', $placeholders) . ')
             ORDER BY pi.prescription_id ASC, m.name ASC'
        );
        $itemsQuery->execute($parameters);

        foreach ($itemsQuery->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $itemsByPrescription[(int) $item['prescription_id']][] = $item;
        }
    }
} catch (PDOException $exception) {
    error_log('Load pharmacy prescriptions error: ' . $exception->getMessage());
    $errorMessage =
        'Daftar resep gagal dimuat. Periksa struktur tabel resep dan obat.';
}

$csrfToken = adminCsrfToken();

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resep Masuk | RS Medika Sehat</title>

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">

    <style>
        .resep-farmasi-page {
            max-width: 1200px;
            margin: 0 auto;
        }

        .resep-farmasi-card {
            padding: 22px;
            margin-bottom: 18px;
            background: var(--panel, #fff);
            border: 1px solid var(--border, #e5e7eb);
            border-radius: 14px;
        }

        .resep-farmasi-heading,
        .resep-farmasi-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .resep-farmasi-meta,
        .resep-farmasi-muted {
            color: var(--muted, #6b7280);
        }

        .resep-farmasi-items {
            width: 100%;
            margin: 18px 0;
            border-collapse: collapse;
        }

        .resep-farmasi-items th,
        .resep-farmasi-items td {
            padding: 10px 8px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid var(--border, #e5e7eb);
        }

        .resep-farmasi-items th {
            font-size: 13px;
        }

        .resep-farmasi-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            background: #fef3c7;
            color: #92400e;
            font-size: 12px;
            font-weight: 700;
        }

        .resep-farmasi-status.selesai {
            background: #dcfce7;
            color: #166534;
        }

        .resep-farmasi-alert {
            padding: 13px 16px;
            margin-bottom: 18px;
            border-radius: 9px;
        }

        .resep-farmasi-success {
            background: #dcfce7;
            color: #166534;
        }

        .resep-farmasi-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .resep-farmasi-empty {
            padding: 24px;
            text-align: center;
        }

        @media (max-width: 700px) {
            .resep-farmasi-card {
                padding: 16px;
            }

            .resep-farmasi-items {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>

<body class="admin-page">

    <?php require __DIR__ . '/../partials/header.php'; ?>

    <div class="admin-layout">
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="admin-main">
            <div class="resep-farmasi-page">
                <div class="admin-page-heading">
                    <div>
                        <h1>Resep Masuk</h1>
                        <p class="admin-intro">
                            Periksa resep dokter dan serahkan obat untuk memperbarui stok secara otomatis.
                        </p>
                    </div>
                </div>

                <?php if ($successMessage !== ''): ?>
                    <div class="resep-farmasi-alert resep-farmasi-success">
                        <?= resepFarmasiEscape($successMessage) ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage !== ''): ?>
                    <div class="resep-farmasi-alert resep-farmasi-error">
                        <?= resepFarmasiEscape($errorMessage) ?>
                    </div>
                <?php endif; ?>

                <?php if (!$prescriptions): ?>
                    <section class="resep-farmasi-card resep-farmasi-empty">
                        Belum ada resep yang masuk.
                    </section>
                <?php else: ?>
                    <?php foreach ($prescriptions as $prescription): ?>
                        <?php
                        $prescriptionId = (int) $prescription['id'];
                        $isWaiting = $prescription['status'] === 'Menunggu Farmasi';
                        ?>
                        <section class="resep-farmasi-card">
                            <div class="resep-farmasi-heading">
                                <div>
                                    <h2>Resep #<?= $prescriptionId ?></h2>
                                    <div class="resep-farmasi-meta">
                                        <?= resepFarmasiEscape(
                                            resepFarmasiTanggal($prescription['prescription_date'])
                                        ) ?>
                                        · Pasien:
                                        <?= resepFarmasiEscape($prescription['patient_name']) ?>
                                        (<?= resepFarmasiEscape(
                                            $prescription['medical_record_number'] ?: '-'
                                        ) ?>)
                                        · Dokter:
                                        <?= resepFarmasiEscape($prescription['doctor_name']) ?>
                                    </div>
                                </div>
                                <span class="resep-farmasi-status <?= $isWaiting ? '' : 'selesai' ?>">
                                    <?= resepFarmasiEscape($prescription['status']) ?>
                                </span>
                            </div>

                            <?php if (!empty($prescription['notes'])): ?>
                                <p><strong>Catatan:</strong>
                                    <?= resepFarmasiEscape($prescription['notes']) ?>
                                </p>
                            <?php endif; ?>

                            <div style="overflow-x:auto">
                                <table class="resep-farmasi-items">
                                    <thead>
                                        <tr>
                                            <th>Obat</th>
                                            <th>Dosis / Frekuensi</th>
                                            <th>Durasi</th>
                                            <th>Jumlah</th>
                                            <th>Aturan pemakaian</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($itemsByPrescription[$prescriptionId] ?? [] as $item): ?>
                                            <tr>
                                                <td><?= resepFarmasiEscape($item['medicine_name']) ?></td>
                                                <td>
                                                    <?= resepFarmasiEscape($item['dosage']) ?>
                                                    / <?= resepFarmasiEscape($item['frequency']) ?>
                                                </td>
                                                <td><?= resepFarmasiEscape($item['duration'] ?: '-') ?></td>
                                                <td>
                                                    <?= (int) $item['quantity'] ?>
                                                    <?= resepFarmasiEscape($item['medicine_unit']) ?>
                                                </td>
                                                <td><?= resepFarmasiEscape($item['instructions'] ?: '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($itemsByPrescription[$prescriptionId])): ?>
                                            <tr>
                                                <td colspan="5">Rincian obat resep tidak tersedia.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($isWaiting): ?>
                                <div class="resep-farmasi-actions">
                                    <span class="resep-farmasi-muted">
                                        Stok akan berkurang setelah obat diserahkan.
                                    </span>
                                    <form method="POST" action="resep_masuk.php"
                                        onsubmit="return confirm('Konfirmasi obat pada resep ini sudah diserahkan kepada pasien?')">
                                        <input type="hidden" name="csrf_token"
                                            value="<?= resepFarmasiEscape($csrfToken) ?>">
                                        <input type="hidden" name="prescription_id"
                                            value="<?= $prescriptionId ?>">
                                        <button type="submit" class="btn">
                                            Serahkan Obat
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>
