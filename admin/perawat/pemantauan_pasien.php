<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

startAdminSession();

if (empty($_SESSION['admin_authenticated'])) {
    header('Location: /rumah_sakit/admin/login.php');
    exit;
}

$connection = getDatabaseConnection();

function pemantauanEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function pemantauanFormatTanggal(mixed $tanggal): string
{
    $timestamp = strtotime((string) $tanggal);

    return $timestamp
        ? date('d-m-Y H:i', $timestamp)
        : '-';
}

$errorMessage = '';
$successMessage = isset($_GET['success'])
    ? 'Data pemantauan berhasil disimpan.'
    : '';

$search = trim((string) ($_GET['search'] ?? ''));

/*
|--------------------------------------------------------------------------
| Membuat tabel pemantauan jika belum tersedia
|--------------------------------------------------------------------------
*/

$connection->exec("
    CREATE TABLE IF NOT EXISTS patient_monitoring (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        patient_id BIGINT UNSIGNED NOT NULL,
        nurse_name VARCHAR(150) NOT NULL,
        monitoring_date DATETIME NOT NULL,
        blood_pressure VARCHAR(30) NULL,
        temperature DECIMAL(4,1) NULL,
        pulse INT NULL,
        respiratory_rate INT NULL,
        consciousness VARCHAR(100) NULL,
        condition_notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_monitoring_patient (patient_id),
        INDEX idx_monitoring_date (monitoring_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/*
|--------------------------------------------------------------------------
| Proses penyimpanan data
|--------------------------------------------------------------------------
*/

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyAdminCsrfToken()) {
        $errorMessage = 'Sesi formulir tidak valid. Silakan muat ulang halaman.';
    } else {
        $patientId = filter_input(
            INPUT_POST,
            'patient_id',
            FILTER_VALIDATE_INT
        );

        $nurseName = trim(
            (string) ($_POST['nurse_name'] ?? '')
        );

        $dateInput = trim(
            (string) ($_POST['monitoring_date'] ?? '')
        );

        $dateObject = DateTime::createFromFormat(
            '!Y-m-d\TH:i',
            $dateInput
        );

        $bloodPressure = trim(
            (string) ($_POST['blood_pressure'] ?? '')
        );

        $temperatureInput = trim(
            (string) ($_POST['temperature'] ?? '')
        );

        $pulseInput = trim(
            (string) ($_POST['pulse'] ?? '')
        );

        $respiratoryInput = trim(
            (string) ($_POST['respiratory_rate'] ?? '')
        );

        $consciousness = trim(
            (string) ($_POST['consciousness'] ?? '')
        );

        $conditionNotes = trim(
            (string) ($_POST['condition_notes'] ?? '')
        );

        if (!$patientId || $nurseName === '') {
            $errorMessage = 'Pasien dan nama perawat wajib diisi.';
        } elseif (
            !$dateObject ||
            $dateObject->format('Y-m-d\TH:i') !== $dateInput
        ) {
            $errorMessage = 'Tanggal dan waktu pemantauan tidak valid.';
        } elseif (
            ($temperatureInput !== '' &&
                (!is_numeric($temperatureInput) ||
                    (float) $temperatureInput < 25 ||
                    (float) $temperatureInput > 45)) ||
            ($pulseInput !== '' &&
                (filter_var($pulseInput, FILTER_VALIDATE_INT) === false ||
                    (int) $pulseInput < 0 ||
                    (int) $pulseInput > 300)) ||
            ($respiratoryInput !== '' &&
                (filter_var($respiratoryInput, FILTER_VALIDATE_INT) === false ||
                    (int) $respiratoryInput < 0 ||
                    (int) $respiratoryInput > 100))
        ) {
            $errorMessage = 'Periksa kembali nilai suhu, nadi, atau frekuensi napas.';
        } else {
            $patientCheck = $connection->prepare(
                'SELECT id FROM patients WHERE id = :id LIMIT 1'
            );

            $patientCheck->execute([
                'id' => $patientId,
            ]);

            if (!$patientCheck->fetch()) {
                $errorMessage = 'Data pasien tidak ditemukan.';
            } else {
                try {
                    $statement = $connection->prepare("
                        INSERT INTO patient_monitoring (
                            patient_id,
                            nurse_name,
                            monitoring_date,
                            blood_pressure,
                            temperature,
                            pulse,
                            respiratory_rate,
                            consciousness,
                            condition_notes
                        ) VALUES (
                            :patient_id,
                            :nurse_name,
                            :monitoring_date,
                            :blood_pressure,
                            :temperature,
                            :pulse,
                            :respiratory_rate,
                            :consciousness,
                            :condition_notes
                        )
                    ");

                    $statement->execute([
                        'patient_id' => $patientId,
                        'nurse_name' => $nurseName,
                        'monitoring_date' => $dateObject->format(
                            'Y-m-d H:i:s'
                        ),
                        'blood_pressure' => $bloodPressure !== ''
                            ? $bloodPressure
                            : null,
                        'temperature' => $temperatureInput !== ''
                            ? (float) $temperatureInput
                            : null,
                        'pulse' => $pulseInput !== ''
                            ? (int) $pulseInput
                            : null,
                        'respiratory_rate' => $respiratoryInput !== ''
                            ? (int) $respiratoryInput
                            : null,
                        'consciousness' => $consciousness !== ''
                            ? $consciousness
                            : null,
                        'condition_notes' => $conditionNotes !== ''
                            ? $conditionNotes
                            : null,
                    ]);

                    header(
                        'Location: pemantauan_pasien.php?success=1'
                    );
                    exit;
                } catch (PDOException $exception) {
                    error_log(
                        'Pemantauan pasien: ' .
                            $exception->getMessage()
                    );

                    $errorMessage = 'Data gagal disimpan. Periksa struktur database.';
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Mengambil data pasien
|--------------------------------------------------------------------------
*/

$patients = $connection->query("
    SELECT id, name, medical_record_number
    FROM patients
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Mengambil riwayat pemantauan
|--------------------------------------------------------------------------
*/

$rows = [];

try {
    $keyword = '%' . $search . '%';

    $statement = $connection->prepare("
        SELECT
            m.*,
            p.name AS patient_name,
            p.medical_record_number
        FROM patient_monitoring m
        INNER JOIN patients p ON p.id = m.patient_id
        WHERE (
            :empty_search = ''
            OR p.name LIKE :patient_name
            OR p.medical_record_number LIKE :medical_record
            OR m.nurse_name LIKE :nurse_name
        )
        ORDER BY m.monitoring_date DESC, m.id DESC
        LIMIT 200
    ");

    $statement->execute([
        'empty_search' => $search,
        'patient_name' => $keyword,
        'medical_record' => $keyword,
        'nurse_name' => $keyword,
    ]);

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log(
        'Riwayat pemantauan pasien: ' .
            $exception->getMessage()
    );

    $errorMessage = 'Riwayat pemantauan gagal dimuat.';
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Pemantauan Pasien - RS Medika Sehat</title>

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">

    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .monitor-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .monitor-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .monitor-field.full {
            grid-column: 1 / -1;
        }

        .monitor-field label {
            font-size: 13px;
            font-weight: 600;
            color: #344054;
        }

        .monitor-field input,
        .monitor-field select,
        .monitor-field textarea {
            box-sizing: border-box;
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #d7e0e8;
            border-radius: 8px;
            background: #fff;
            color: #17212f;
            font: inherit;
        }

        .monitor-field textarea {
            min-height: 90px;
            resize: vertical;
        }

        .monitor-actions {
            margin-top: 18px;
        }

        .monitor-alert {
            padding: 13px 16px;
            border-radius: 9px;
            margin-bottom: 18px;
        }

        .monitor-alert.error {
            background: #fee4e2;
            color: #b42318;
        }

        .monitor-alert.success {
            background: #e7f8ef;
            color: #18794e;
        }

        .monitor-search {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .monitor-search input {
            flex: 1;
            min-width: 220px;
            padding: 10px 12px;
            border: 1px solid #d7e0e8;
            border-radius: 8px;
        }

        .monitor-table-wrap {
            overflow-x: auto;
        }

        .monitor-table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
        }

        .monitor-table th,
        .monitor-table td {
            padding: 12px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #e7edf2;
            font-size: 13px;
            line-height: 1.5;
        }

        .monitor-table th {
            background: #f8fafc;
            color: #667085;
            font-size: 12px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .monitor-muted {
            font-size: 12px;
            color: #667085;
        }

        @media (max-width: 700px) {
            .monitor-grid {
                grid-template-columns: 1fr;
            }

            .monitor-field.full {
                grid-column: auto;
            }

            .monitor-search input {
                min-width: 100%;
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
                    <p class="eyebrow">Layanan keperawatan</p>
                    <h1>Pemantauan Pasien</h1>
                    <p class="admin-intro">
                        Catat tanda vital dan perkembangan kondisi pasien
                        secara berkala.
                    </p>
                </div>
            </div>

            <?php require __DIR__ . '/../partials/flash_message.php'; ?>

            <?php if ($successMessage !== ''): ?>
                <div class="monitor-alert success" role="status">
                    <?= pemantauanEscape($successMessage) ?>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage !== ''): ?>
                <div class="monitor-alert error" role="alert">
                    <?= pemantauanEscape($errorMessage) ?>
                </div>
            <?php endif; ?>

            <section class="admin-panel">

                <h2>Input Pemantauan</h2>

                <p class="admin-intro">
                    Isi sesuai hasil pengukuran yang benar-benar dilakukan.
                </p>

                <form method="POST" action="pemantauan_pasien.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= pemantauanEscape(adminCsrfToken()) ?>">

                    <div class="monitor-grid">

                        <div class="monitor-field">
                            <label for="patient_id">Pasien *</label>

                            <select
                                id="patient_id"
                                name="patient_id"
                                required>
                                <option value="">Pilih pasien</option>

                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?= (int) $patient['id'] ?>">
                                        <?= pemantauanEscape($patient['name']) ?>

                                        <?= !empty($patient['medical_record_number'])
                                            ? ' — ' . pemantauanEscape(
                                                $patient['medical_record_number']
                                            )
                                            : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="monitor-field">
                            <label for="nurse_name">Nama Perawat *</label>

                            <input
                                type="text"
                                id="nurse_name"
                                name="nurse_name"
                                maxlength="150"
                                required>
                        </div>

                        <div class="monitor-field">
                            <label for="monitoring_date">
                                Tanggal dan Waktu *
                            </label>

                            <input
                                type="datetime-local"
                                id="monitoring_date"
                                name="monitoring_date"
                                value="<?= date('Y-m-d\TH:i') ?>"
                                required>
                        </div>

                        <div class="monitor-field">
                            <label for="blood_pressure">
                                Tekanan Darah (mmHg)
                            </label>

                            <input
                                type="text"
                                id="blood_pressure"
                                name="blood_pressure"
                                maxlength="30"
                                placeholder="Contoh: 120/80">
                        </div>

                        <div class="monitor-field">
                            <label for="temperature">Suhu Tubuh (°C)</label>

                            <input
                                type="number"
                                id="temperature"
                                name="temperature"
                                min="25"
                                max="45"
                                step="0.1"
                                placeholder="Contoh: 36.5">
                        </div>

                        <div class="monitor-field">
                            <label for="pulse">
                                Denyut Nadi (kali/menit)
                            </label>

                            <input
                                type="number"
                                id="pulse"
                                name="pulse"
                                min="0"
                                max="300"
                                placeholder="Contoh: 80">
                        </div>

                        <div class="monitor-field">
                            <label for="respiratory_rate">
                                Frekuensi Napas (kali/menit)
                            </label>

                            <input
                                type="number"
                                id="respiratory_rate"
                                name="respiratory_rate"
                                min="0"
                                max="100"
                                placeholder="Contoh: 18">
                        </div>

                        <div class="monitor-field">
                            <label for="consciousness">Kesadaran</label>

                            <select id="consciousness" name="consciousness">
                                <option value="">Pilih kondisi</option>
                                <option value="Sadar penuh">Sadar penuh</option>
                                <option value="Merespons suara">
                                    Merespons suara
                                </option>
                                <option value="Merespons nyeri">
                                    Merespons nyeri
                                </option>
                                <option value="Tidak merespons">
                                    Tidak merespons
                                </option>
                                <option value="Perlu penilaian lanjutan">
                                    Perlu penilaian lanjutan
                                </option>
                            </select>
                        </div>

                        <div class="monitor-field full">
                            <label for="condition_notes">
                                Catatan Perkembangan
                            </label>

                            <textarea
                                id="condition_notes"
                                name="condition_notes"
                                placeholder="Tuliskan perubahan atau keluhan yang diamati"></textarea>
                        </div>

                    </div>

                    <div class="monitor-actions">
                        <button class="btn btn-primary" type="submit">
                            Simpan Pemantauan
                        </button>
                    </div>

                </form>
            </section>

            <section class="admin-panel">

                <h2>Riwayat Pemantauan Pasien</h2>

                <form
                    class="monitor-search"
                    method="GET"
                    action="pemantauan_pasien.php">
                    <input
                        type="search"
                        name="search"
                        value="<?= pemantauanEscape($search) ?>"
                        placeholder="Cari nama pasien, nomor rekam medis, atau perawat">

                    <button class="btn btn-secondary" type="submit">
                        Cari
                    </button>

                    <a
                        class="btn btn-secondary"
                        href="pemantauan_pasien.php">
                        Reset
                    </a>
                </form>

                <div class="monitor-table-wrap">
                    <table class="monitor-table">

                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Pasien</th>
                                <th>Perawat</th>
                                <th>Tekanan Darah</th>
                                <th>Suhu</th>
                                <th>Nadi</th>
                                <th>Napas</th>
                                <th>Kesadaran / Catatan</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (!$rows): ?>

                                <tr>
                                    <td
                                        colspan="8"
                                        style="text-align:center;padding:28px">
                                        Belum ada data pemantauan.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($rows as $row): ?>

                                    <tr>
                                        <td>
                                            <?= pemantauanEscape(
                                                pemantauanFormatTanggal(
                                                    $row['monitoring_date']
                                                )
                                            ) ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= pemantauanEscape(
                                                    $row['patient_name']
                                                ) ?>
                                            </strong>

                                            <div class="monitor-muted">
                                                No. RM:
                                                <?= pemantauanEscape(
                                                    $row['medical_record_number'] ?: '-'
                                                ) ?>
                                            </div>
                                        </td>

                                        <td>
                                            <?= pemantauanEscape(
                                                $row['nurse_name']
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= pemantauanEscape(
                                                $row['blood_pressure'] ?: '-'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= pemantauanEscape(
                                                $row['temperature'] !== null
                                                    ? $row['temperature'] . ' °C'
                                                    : '-'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= pemantauanEscape(
                                                $row['pulse'] ?? '-'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= pemantauanEscape(
                                                $row['respiratory_rate'] ?? '-'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= pemantauanEscape(
                                                $row['consciousness'] ?: '-'
                                            ) ?>

                                            <div class="monitor-muted">
                                                <?= nl2br(
                                                    pemantauanEscape(
                                                        $row['condition_notes'] ?: ''
                                                    )
                                                ) ?>
                                            </div>
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

</body>

</html>