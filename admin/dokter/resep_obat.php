<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

$connection = getDatabaseConnection();

function resepEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function resepTanggal(mixed $value): string
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime((string) $value);

    return $timestamp
        ? date('d-m-Y H:i', $timestamp)
        : '-';
}

$errorMessage = '';
$successMessage = '';

$accountRole = (string) ($_SESSION['account_role'] ?? '');
$doctorId = (int) ($_SESSION['doctor_id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Pastikan akun dokter memiliki doctor_id
|--------------------------------------------------------------------------
*/

if ($accountRole === 'dokter' && $doctorId <= 0) {
    http_response_code(403);
    exit('Akun dokter belum terhubung dengan data dokter.');
}

/*
|--------------------------------------------------------------------------
| Simpan resep
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyAdminCsrfToken()) {

        $errorMessage = 'Sesi formulir tidak valid. Silakan muat ulang halaman.';
    } else {

        $visitId = filter_input(
            INPUT_POST,
            'patient_visit_id',
            FILTER_VALIDATE_INT
        );

        $notes = trim((string) ($_POST['notes'] ?? ''));

        $medicineIds = $_POST['medicine_id'] ?? [];
        $dosages = $_POST['dosage'] ?? [];
        $frequencies = $_POST['frequency'] ?? [];
        $durations = $_POST['duration'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $instructions = $_POST['instructions'] ?? [];

        if (
            !is_array($medicineIds) ||
            !is_array($dosages) ||
            !is_array($frequencies) ||
            !is_array($durations) ||
            !is_array($quantities) ||
            !is_array($instructions)
        ) {
            $errorMessage = 'Format data obat tidak valid.';
        } elseif (!$visitId) {

            $errorMessage = 'Silakan pilih pemeriksaan pasien.';
        } elseif (count($medicineIds) === 0) {

            $errorMessage = 'Tambahkan minimal satu obat.';
        } else {

            try {

                $connection->beginTransaction();

                /*
                 * Ambil pemeriksaan pasien.
                 * Jika akun dokter, hanya pemeriksaan milik dokter tersebut.
                 */

                $visitSql = '
                    SELECT
                        pv.id,
                        pv.patient_id,
                        pv.doctor_id
                    FROM patient_visits pv
                    WHERE pv.id = :visit_id
                ';

                $visitParams = [
                    'visit_id' => $visitId,
                ];

                if ($accountRole === 'dokter') {
                    $visitSql .= ' AND pv.doctor_id = :doctor_id';
                    $visitParams['doctor_id'] = $doctorId;
                }

                $visitSql .= ' LIMIT 1';

                $visitStatement = $connection->prepare($visitSql);
                $visitStatement->execute($visitParams);

                $visit = $visitStatement->fetch(PDO::FETCH_ASSOC);

                if (!$visit) {
                    throw new RuntimeException(
                        'Pemeriksaan pasien tidak ditemukan atau bukan milik dokter ini.'
                    );
                }

                /*
                 * Validasi setiap obat.
                 */

                $items = [];

                foreach ($medicineIds as $index => $medicineIdValue) {

                    $medicineId = filter_var(
                        $medicineIdValue,
                        FILTER_VALIDATE_INT
                    );

                    $dosage = trim((string) ($dosages[$index] ?? ''));
                    $frequency = trim((string) ($frequencies[$index] ?? ''));
                    $duration = trim((string) ($durations[$index] ?? ''));
                    $quantity = filter_var(
                        $quantities[$index] ?? null,
                        FILTER_VALIDATE_INT
                    );
                    $instruction = trim(
                        (string) ($instructions[$index] ?? '')
                    );

                    if (
                        !$medicineId ||
                        $dosage === '' ||
                        $frequency === '' ||
                        !$quantity ||
                        $quantity < 1
                    ) {
                        throw new RuntimeException(
                            'Lengkapi obat, dosis, frekuensi, dan jumlah dengan benar.'
                        );
                    }

                    $medicineStatement = $connection->prepare(
                        'SELECT id, name, stock, price, expiry_date
                         FROM medicines
                         WHERE id = :id
                         LIMIT 1'
                    );

                    $medicineStatement->execute([
                        'id' => $medicineId,
                    ]);

                    $medicine = $medicineStatement->fetch(PDO::FETCH_ASSOC);

                    if (!$medicine) {
                        throw new RuntimeException(
                            'Salah satu obat tidak ditemukan.'
                        );
                    }

                    if ((int) $medicine['stock'] < $quantity) {
                        throw new RuntimeException(
                            'Stok obat ' . $medicine['name'] . ' tidak mencukupi.'
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

                    $items[] = [
                        'medicine_id' => $medicineId,
                        'dosage' => $dosage,
                        'frequency' => $frequency,
                        'duration' => $duration,
                        'quantity' => $quantity,
                        'instructions' => $instruction,
                        'medicine_price' => (float) $medicine['price'],
                    ];
                }

                /*
                 * Simpan data utama resep.
                 */

                $prescriptionStatement = $connection->prepare(
                    'INSERT INTO prescriptions (
                        patient_visit_id,
                        patient_id,
                        doctor_id,
                        prescription_date,
                        notes,
                        status
                    ) VALUES (
                        :visit_id,
                        :patient_id,
                        :doctor_id,
                        NOW(),
                        :notes,
                        :status
                    )'
                );

                $prescriptionStatement->execute([
                    'visit_id' => $visit['id'],
                    'patient_id' => $visit['patient_id'],
                    'doctor_id' => $visit['doctor_id'],
                    'notes' => $notes !== '' ? $notes : null,
                    'status' => 'Menunggu Farmasi',
                ]);

                $prescriptionId = (int) $connection->lastInsertId();

                /*
                 * Simpan rincian obat resep.
                 */

                $itemStatement = $connection->prepare(
                    'INSERT INTO prescription_items (
                        prescription_id,
                        medicine_id,
                        dosage,
                        frequency,
                        duration,
                        quantity,
                        instructions,
                        medicine_price
                    ) VALUES (
                        :prescription_id,
                        :medicine_id,
                        :dosage,
                        :frequency,
                        :duration,
                        :quantity,
                        :instructions,
                        :medicine_price
                    )'
                );

                foreach ($items as $item) {

                    $itemStatement->execute([
                        'prescription_id' => $prescriptionId,
                        'medicine_id' => $item['medicine_id'],
                        'dosage' => $item['dosage'],
                        'frequency' => $item['frequency'],
                        'duration' => $item['duration'] !== ''
                            ? $item['duration']
                            : null,
                        'quantity' => $item['quantity'],
                        'instructions' => $item['instructions'] !== ''
                            ? $item['instructions']
                            : null,
                        'medicine_price' => $item['medicine_price'],
                    ]);
                }

                $connection->commit();

                header(
                    'Location: resep_obat.php?success=1'
                );
                exit;
            } catch (Throwable $exception) {

                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }

                error_log(
                    'Save prescription error: ' . $exception->getMessage()
                );

                $errorMessage = $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'Resep gagal disimpan. Periksa struktur tabel database.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Ambil daftar pemeriksaan pasien
|--------------------------------------------------------------------------
*/

$visits = [];
$medicines = [];
$prescriptions = [];

try {

    $visitSql = '
        SELECT
            pv.id,
            pv.visit_date,
            pv.complaint,
            pv.diagnosis,
            p.name AS patient_name,
            p.medical_record_number,
            d.name AS doctor_name
        FROM patient_visits pv
        INNER JOIN patients p ON p.id = pv.patient_id
        INNER JOIN doctors d ON d.id = pv.doctor_id
    ';

    if ($accountRole === 'dokter') {
        $visitSql .= ' WHERE pv.doctor_id = :doctor_id';
    }

    $visitSql .= ' ORDER BY pv.visit_date DESC';

    $visitStatement = $connection->prepare($visitSql);

    $visitStatement->execute(
        $accountRole === 'dokter'
            ? ['doctor_id' => $doctorId]
            : []
    );

    $visits = $visitStatement->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Obat yang tersedia dan belum kedaluwarsa.
     */

    $medicineStatement = $connection->query(
        'SELECT id, code, name, unit, stock, price, expiry_date
         FROM medicines
         WHERE stock > 0
         AND (
             expiry_date IS NULL
             OR expiry_date >= CURDATE()
         )
         ORDER BY name ASC'
    );

    $medicines = $medicineStatement->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Riwayat resep.
     */

    $historySql = '
        SELECT
            pr.id,
            pr.prescription_date,
            pr.status,
            p.name AS patient_name,
            p.medical_record_number,
            d.name AS doctor_name,
            (
                SELECT COUNT(*)
                FROM prescription_items pi
                WHERE pi.prescription_id = pr.id
            ) AS total_medicines
        FROM prescriptions pr
        INNER JOIN patients p ON p.id = pr.patient_id
        INNER JOIN doctors d ON d.id = pr.doctor_id
    ';

    if ($accountRole === 'dokter') {
        $historySql .= ' WHERE pr.doctor_id = :doctor_id';
    }

    $historySql .= ' ORDER BY pr.prescription_date DESC';

    $historyStatement = $connection->prepare($historySql);

    $historyStatement->execute(
        $accountRole === 'dokter'
            ? ['doctor_id' => $doctorId]
            : []
    );

    $prescriptions = $historyStatement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {

    error_log('Load prescription error: ' . $exception->getMessage());

    $errorMessage = 'Data resep atau pemeriksaan gagal dimuat. Periksa struktur tabel.';
}

if (isset($_GET['success'])) {
    $successMessage = 'Resep obat berhasil disimpan.';
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Resep Obat - RS Medika Sehat</title>

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">

    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .prescription-card {
            background: #fff;
            border: 1px solid #e5eaf0;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 22px;
        }

        .prescription-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .prescription-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .prescription-field.full {
            grid-column: 1 / -1;
        }

        .prescription-field label {
            font-size: 13px;
            font-weight: 600;
            color: #344054;
        }

        .prescription-field input,
        .prescription-field select,
        .prescription-field textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 11px 12px;
            border: 1px solid #d7e0e8;
            border-radius: 8px;
            font: inherit;
            color: #17212f;
            background: #fff;
        }

        .prescription-field textarea {
            min-height: 80px;
            resize: vertical;
        }

        .prescription-item {
            border: 1px solid #e5eaf0;
            border-radius: 10px;
            padding: 16px;
            margin-top: 14px;
            background: #f9fbfc;
        }

        .prescription-item-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .prescription-item-heading strong {
            color: #102a36;
        }

        .prescription-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .prescription-button {
            border: 0;
            border-radius: 8px;
            padding: 11px 17px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .prescription-button.primary {
            background: #087f8c;
            color: #fff;
        }

        .prescription-button.secondary {
            background: #eaf3f5;
            color: #087f8c;
        }

        .prescription-button.danger {
            background: #fee4e2;
            color: #b42318;
        }

        .prescription-alert {
            padding: 13px 16px;
            border-radius: 9px;
            margin-bottom: 18px;
        }

        .prescription-alert.error {
            background: #fee4e2;
            color: #b42318;
        }

        .prescription-alert.success {
            background: #e7f8ef;
            color: #18794e;
        }

        .prescription-table-wrap {
            overflow-x: auto;
        }

        .prescription-table {
            width: 100%;
            min-width: 750px;
            border-collapse: collapse;
        }

        .prescription-table th,
        .prescription-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e7edf2;
            font-size: 13px;
        }

        .prescription-table th {
            background: #f8fafc;
            color: #667085;
            white-space: nowrap;
        }

        .prescription-muted {
            color: #667085;
            font-size: 12px;
        }

        @media (max-width: 700px) {
            .prescription-grid {
                grid-template-columns: 1fr;
            }

            .prescription-field.full {
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
                    <p class="eyebrow">Pelayanan medis</p>
                    <h1>Resep Obat</h1>
                    <p class="admin-intro">
                        Buat dan kelola resep obat berdasarkan pemeriksaan pasien.
                    </p>
                </div>
            </div>

            <?php if ($errorMessage !== ''): ?>
                <div class="prescription-alert error">
                    <?= resepEscape($errorMessage) ?>
                </div>
            <?php endif; ?>

            <?php if ($successMessage !== ''): ?>
                <div class="prescription-alert success">
                    <?= resepEscape($successMessage) ?>
                </div>
            <?php endif; ?>

            <section class="prescription-card">

                <h2>Buat Resep Baru</h2>

                <p class="prescription-muted">
                    Pilih pemeriksaan pasien terlebih dahulu sebelum menambahkan obat.
                </p>

                <form method="POST" action="resep_obat.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= resepEscape(adminCsrfToken()) ?>">

                    <div class="prescription-grid">

                        <div class="prescription-field full">
                            <label for="patient_visit_id">
                                Pemeriksaan Pasien *
                            </label>

                            <select
                                name="patient_visit_id"
                                id="patient_visit_id"
                                required>
                                <option value="">-- Pilih pemeriksaan pasien --</option>

                                <?php foreach ($visits as $visit): ?>
                                    <option value="<?= (int) $visit['id'] ?>">
                                        <?= resepEscape($visit['patient_name']) ?>
                                        -
                                        <?= resepEscape($visit['medical_record_number']) ?>
                                        -
                                        <?= resepEscape(resepTanggal($visit['visit_date'])) ?>
                                        -
                                        Dokter: <?= resepEscape($visit['doctor_name']) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </div>

                        <div class="prescription-field full">
                            <label for="notes">Catatan Resep</label>

                            <textarea
                                name="notes"
                                id="notes"
                                placeholder="Catatan tambahan untuk Farmasi..."></textarea>
                        </div>

                    </div>

                    <div style="margin-top:24px;">
                        <h3>Daftar Obat</h3>

                        <div id="prescription-items">

                            <div class="prescription-item">

                                <div class="prescription-item-heading">
                                    <strong>Obat 1</strong>
                                </div>

                                <div class="prescription-grid">

                                    <div class="prescription-field full">
                                        <label>Nama Obat *</label>

                                        <select name="medicine_id[]" required>
                                            <option value="">-- Pilih obat --</option>

                                            <?php foreach ($medicines as $medicine): ?>
                                                <option value="<?= (int) $medicine['id'] ?>">
                                                    <?= resepEscape($medicine['name']) ?>
                                                    (Stok: <?= (int) $medicine['stock'] ?>
                                                    <?= resepEscape($medicine['unit']) ?>)
                                                </option>
                                            <?php endforeach; ?>

                                        </select>
                                    </div>

                                    <div class="prescription-field">
                                        <label>Dosis *</label>
                                        <input
                                            type="text"
                                            name="dosage[]"
                                            placeholder="Contoh: 500 mg"
                                            required>
                                    </div>

                                    <div class="prescription-field">
                                        <label>Frekuensi *</label>
                                        <input
                                            type="text"
                                            name="frequency[]"
                                            placeholder="Contoh: 3 kali sehari"
                                            required>
                                    </div>

                                    <div class="prescription-field">
                                        <label>Durasi</label>
                                        <input
                                            type="text"
                                            name="duration[]"
                                            placeholder="Contoh: 5 hari">
                                    </div>

                                    <div class="prescription-field">
                                        <label>Jumlah *</label>
                                        <input
                                            type="number"
                                            name="quantity[]"
                                            min="1"
                                            placeholder="Jumlah obat"
                                            required>
                                    </div>

                                    <div class="prescription-field full">
                                        <label>Aturan Pemakaian</label>
                                        <textarea
                                            name="instructions[]"
                                            placeholder="Contoh: Sesudah makan"></textarea>
                                    </div>

                                </div>
                            </div>

                        </div>

                        <div class="prescription-actions">

                            <button
                                type="button"
                                class="prescription-button secondary"
                                id="add-medicine">
                                + Tambah Obat
                            </button>

                            <button
                                type="submit"
                                class="prescription-button primary">
                                Simpan Resep
                            </button>

                        </div>

                    </div>

                </form>

            </section>

            <section class="prescription-card">

                <h2>Riwayat Resep Obat</h2>

                <p class="prescription-muted">
                    Daftar resep yang sudah dibuat.
                </p>

                <div class="prescription-table-wrap">

                    <table class="prescription-table">

                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Tanggal</th>
                                <th>Pasien</th>
                                <th>No. Rekam Medis</th>
                                <th>Dokter</th>
                                <th>Jumlah Obat</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (empty($prescriptions)): ?>

                                <tr>
                                    <td colspan="7" style="text-align:center;">
                                        Belum ada data resep obat.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($prescriptions as $index => $prescription): ?>

                                    <tr>
                                        <td><?= $index + 1 ?></td>

                                        <td>
                                            <?= resepEscape(
                                                resepTanggal($prescription['prescription_date'])
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= resepEscape($prescription['patient_name']) ?>
                                        </td>

                                        <td>
                                            <?= resepEscape($prescription['medical_record_number']) ?>
                                        </td>

                                        <td>
                                            <?= resepEscape($prescription['doctor_name']) ?>
                                        </td>

                                        <td>
                                            <?= (int) $prescription['total_medicines'] ?> obat
                                        </td>

                                        <td>
                                            <?= resepEscape($prescription['status']) ?>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        </main>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const container = document.getElementById('prescription-items');
            const addButton = document.getElementById('add-medicine');

            function updateItemNumbers() {
                const items = container.querySelectorAll('.prescription-item');

                items.forEach(function(item, index) {

                    const heading = item.querySelector(
                        '.prescription-item-heading strong'
                    );

                    if (heading) {
                        heading.textContent = 'Obat ' + (index + 1);
                    }
                });
            }

            addButton.addEventListener('click', function() {

                const firstItem = container.querySelector('.prescription-item');
                const newItem = firstItem.cloneNode(true);

                newItem.querySelectorAll('input, textarea').forEach(function(field) {
                    field.value = '';
                });

                newItem.querySelectorAll('select').forEach(function(field) {
                    field.selectedIndex = 0;
                });

                const heading = newItem.querySelector(
                    '.prescription-item-heading'
                );

                const removeButton = document.createElement('button');

                removeButton.type = 'button';
                removeButton.className = 'prescription-button danger';
                removeButton.textContent = 'Hapus';

                removeButton.addEventListener('click', function() {
                    newItem.remove();
                    updateItemNumbers();
                });

                heading.appendChild(removeButton);

                container.appendChild(newItem);

                updateItemNumbers();
            });

        });
    </script>

</body>

</html>