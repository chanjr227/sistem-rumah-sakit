<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

startAdminSession();

// Pemeriksaan login sementara.
// Hak akses khusus perawat akan disempurnakan pada tahap pengamanan akhir.
if (empty($_SESSION['admin_authenticated'])) {
    header('Location: /rumah_sakit/admin/login.php');
    exit;
}

$connection = getDatabaseConnection();

function catatanEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$search = trim((string) ($_GET['search'] ?? ''));
$errorMessage = null;
$successMessage = null;

// Simpan catatan keperawatan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyAdminCsrfToken()) {
        $errorMessage = 'Sesi formulir tidak valid. Silakan muat ulang halaman.';
    } else {
        $patientId = filter_input(
            INPUT_POST,
            'patient_id',
            FILTER_VALIDATE_INT
        );

        $nurseName = trim((string) ($_POST['nurse_name'] ?? ''));
        $noteDate = trim((string) ($_POST['note_date'] ?? ''));
        $condition = trim((string) ($_POST['patient_condition'] ?? ''));
        $complaints = trim((string) ($_POST['complaints'] ?? ''));
        $action = trim((string) ($_POST['nursing_action'] ?? ''));
        $evaluation = trim((string) ($_POST['evaluation'] ?? ''));

        if (
            !$patientId ||
            $nurseName === '' ||
            $condition === '' ||
            $action === ''
        ) {
            $errorMessage = 'Pasien, nama perawat, kondisi, dan tindakan wajib diisi.';
        } else {
            try {
                $patientStatement = $connection->prepare(
                    'SELECT id FROM patients WHERE id = :id LIMIT 1'
                );
                $patientStatement->execute(['id' => $patientId]);

                if (!$patientStatement->fetch()) {
                    $errorMessage = 'Data pasien tidak ditemukan.';
                } else {
                    if ($noteDate === '') {
                        $noteDate = date('Y-m-d\TH:i');
                    }

                    $dateObject = DateTime::createFromFormat(
                        '!Y-m-d\TH:i',
                        $noteDate
                    );

                    if (
                        !$dateObject ||
                        $dateObject->format('Y-m-d\TH:i') !== $noteDate
                    ) {
                        throw new InvalidArgumentException(
                            'Tanggal dan waktu catatan tidak valid.'
                        );
                    }

                    $insert = $connection->prepare(
                        'INSERT INTO nursing_notes (
                            patient_id,
                            nurse_name,
                            note_date,
                            patient_condition,
                            complaints,
                            nursing_action,
                            evaluation
                        ) VALUES (
                            :patient_id,
                            :nurse_name,
                            :note_date,
                            :patient_condition,
                            :complaints,
                            :nursing_action,
                            :evaluation
                        )'
                    );

                    $insert->execute([
                        'patient_id' => $patientId,
                        'nurse_name' => $nurseName,
                        'note_date' => $dateObject->format('Y-m-d H:i:s'),
                        'patient_condition' => $condition,
                        'complaints' => $complaints !== '' ? $complaints : null,
                        'nursing_action' => $action,
                        'evaluation' => $evaluation !== '' ? $evaluation : null,
                    ]);

                    header('Location: catatan_keperawatan.php?success=1');
                    exit;
                }
            } catch (InvalidArgumentException $exception) {
                $errorMessage = $exception->getMessage();
            } catch (PDOException $exception) {
                error_log(
                    'Save nursing note error: ' . $exception->getMessage()
                );
                $errorMessage = 'Catatan gagal disimpan. Periksa tabel nursing_notes.';
            }
        }
    }
}

if (isset($_GET['success'])) {
    $successMessage = 'Catatan keperawatan berhasil disimpan.';
}

// Ambil daftar pasien
$patients = [];
$notes = [];
$totalNotes = 0;
$todayNotes = 0;

try {
    $patientsStatement = $connection->query(
        'SELECT id, name, medical_record_number
         FROM patients
         ORDER BY name ASC'
    );
    $patients = $patientsStatement->fetchAll(PDO::FETCH_ASSOC);

    $totalNotes = (int) $connection->query(
        'SELECT COUNT(*) FROM nursing_notes'
    )->fetchColumn();

    $todayNotes = (int) $connection->query(
        'SELECT COUNT(*)
         FROM nursing_notes
         WHERE note_date >= CURDATE()
           AND note_date < CURDATE() + INTERVAL 1 DAY'
    )->fetchColumn();

    $sql = '
        SELECT
            n.id,
            n.patient_id,
            n.nurse_name,
            n.note_date,
            n.patient_condition,
            n.complaints,
            n.nursing_action,
            n.evaluation,
            p.name AS patient_name,
            p.medical_record_number
        FROM nursing_notes n
        INNER JOIN patients p ON p.id = n.patient_id
    ';

    $params = [];

    if ($search !== '') {
        $sql .= '
            WHERE p.name LIKE :patient_name
               OR p.medical_record_number LIKE :medical_record
               OR n.nurse_name LIKE :nurse_name
        ';

        $keyword = '%' . $search . '%';

        $params = [
            'patient_name' => $keyword,
            'medical_record' => $keyword,
            'nurse_name' => $keyword,
        ];
    }

    $sql .= ' ORDER BY n.note_date DESC, n.id DESC';

    $notesStatement = $connection->prepare($sql);
    $notesStatement->execute($params);
    $notes = $notesStatement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log(
        'Load nursing notes error: ' . $exception->getMessage()
    );
    $errorMessage = 'Data catatan gagal dimuat. Pastikan tabel nursing_notes sudah tersedia.';
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Catatan Keperawatan - RS Medika Sehat</title>

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">
    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .catatan-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .catatan-stat {
            padding: 20px;
            background: var(--panel, #fff);
            border: 1px solid var(--border, #e5e7eb);
            border-radius: 12px;
        }

        .catatan-stat-label {
            color: #667085;
            font-size: 13px;
        }

        .catatan-stat-number {
            display: block;
            margin-top: 8px;
            font-size: 28px;
            font-weight: 800;
            color: #17212f;
        }

        .catatan-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .catatan-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .catatan-field.full {
            grid-column: 1 / -1;
        }

        .catatan-field label {
            font-size: 13px;
            font-weight: 600;
            color: #344054;
        }

        .catatan-field input,
        .catatan-field select,
        .catatan-field textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 11px 12px;
            border: 1px solid var(--border, #d9e1e8);
            border-radius: 8px;
            font: inherit;
            background: #fff;
            color: #17212f;
        }

        .catatan-field textarea {
            min-height: 95px;
            resize: vertical;
        }

        .catatan-toolbar {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .catatan-filters {
            display: flex;
            flex: 1;
            gap: 10px;
            flex-wrap: wrap;
        }

        .catatan-filters input {
            flex: 1;
            min-width: 220px;
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid var(--border, #d9e1e8);
            border-radius: 8px;
            font: inherit;
        }

        .catatan-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .catatan-table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse;
        }

        .catatan-table th,
        .catatan-table td {
            padding: 13px 12px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #e7edf2;
            line-height: 1.6;
            font-size: 13px;
        }

        .catatan-table th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #667085;
            background: #f8fafc;
            white-space: nowrap;
        }

        .catatan-muted {
            margin-top: 4px;
            font-size: 12px;
            color: #697586;
        }

        .catatan-empty {
            padding: 35px 16px !important;
            text-align: center !important;
            color: #667085;
        }

        .catatan-alert {
            padding: 14px 16px;
            margin-bottom: 18px;
            border-radius: 9px;
            line-height: 1.6;
        }

        .catatan-success {
            color: #18794e;
            background: #e7f8ef;
        }

        .catatan-error {
            color: #b42318;
            background: #fee4e2;
        }

        .catatan-form-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        @media (max-width: 700px) {

            .catatan-stats,
            .catatan-form-grid {
                grid-template-columns: 1fr;
            }

            .catatan-field.full {
                grid-column: auto;
            }

            .catatan-filters,
            .catatan-filters input {
                width: 100%;
                min-width: 0;
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
                    <h1>Catatan Keperawatan</h1>
                    <p class="admin-intro">
                        Kelola catatan kondisi, tindakan, dan evaluasi keperawatan pasien.
                    </p>
                </div>

                <a class="btn btn-primary" href="#formCatatan">
                    + Tambah Catatan
                </a>
            </div>

            <?php require __DIR__ . '/../partials/flash_message.php'; ?>

            <?php if ($successMessage !== null): ?>
                <div class="catatan-alert catatan-success" role="status">
                    <?= catatanEscape($successMessage) ?>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage !== null): ?>
                <div class="catatan-alert catatan-error" role="alert">
                    <?= catatanEscape($errorMessage) ?>
                </div>
            <?php endif; ?>

            <div class="catatan-stats">
                <div class="catatan-stat">
                    <span class="catatan-stat-label">Total catatan</span>
                    <strong class="catatan-stat-number">
                        <?= number_format($totalNotes) ?>
                    </strong>
                </div>

                <div class="catatan-stat">
                    <span class="catatan-stat-label">Catatan hari ini</span>
                    <strong class="catatan-stat-number">
                        <?= number_format($todayNotes) ?>
                    </strong>
                </div>
            </div>

            <section class="admin-panel" id="formCatatan">
                <h2>Tambah Catatan Keperawatan</h2>

                <form method="POST" action="catatan_keperawatan.php">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= catatanEscape(adminCsrfToken()) ?>">

                    <div class="catatan-form-grid">

                        <div class="catatan-field">
                            <label for="patient_id">Pasien *</label>
                            <select id="patient_id" name="patient_id" required>
                                <option value="">Pilih pasien</option>

                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?= (int) $patient['id'] ?>">
                                        <?= catatanEscape($patient['name']) ?>
                                        <?php if (!empty($patient['medical_record_number'])): ?>
                                            — <?= catatanEscape($patient['medical_record_number']) ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="catatan-field">
                            <label for="nurse_name">Nama Perawat *</label>
                            <input
                                type="text"
                                id="nurse_name"
                                name="nurse_name"
                                maxlength="150"
                                required
                                placeholder="Masukkan nama perawat">
                        </div>

                        <div class="catatan-field">
                            <label for="note_date">Tanggal dan Waktu</label>
                            <input
                                type="datetime-local"
                                id="note_date"
                                name="note_date"
                                value="<?= date('Y-m-d\TH:i') ?>">
                        </div>

                        <div class="catatan-field">
                            <label for="complaints">Keluhan Pasien</label>
                            <textarea
                                id="complaints"
                                name="complaints"
                                placeholder="Keluhan yang disampaikan pasien"></textarea>
                        </div>

                        <div class="catatan-field full">
                            <label for="patient_condition">Kondisi Pasien *</label>
                            <textarea
                                id="patient_condition"
                                name="patient_condition"
                                required
                                placeholder="Catat kondisi berdasarkan hasil pengamatan"></textarea>
                        </div>

                        <div class="catatan-field full">
                            <label for="nursing_action">Tindakan Keperawatan *</label>
                            <textarea
                                id="nursing_action"
                                name="nursing_action"
                                required
                                placeholder="Catat tindakan yang benar-benar dilakukan"></textarea>
                        </div>

                        <div class="catatan-field full">
                            <label for="evaluation">Evaluasi</label>
                            <textarea
                                id="evaluation"
                                name="evaluation"
                                placeholder="Catat respons pasien dan hasil evaluasi"></textarea>
                        </div>

                    </div>

                    <div class="catatan-form-actions">
                        <button class="btn btn-primary" type="submit">
                            Simpan Catatan
                        </button>

                        <button class="btn btn-secondary" type="reset">
                            Reset Form
                        </button>
                    </div>
                </form>
            </section>

            <section class="admin-panel">
                <h2>Daftar Catatan Keperawatan</h2>

                <div class="catatan-toolbar">
                    <form
                        class="catatan-filters"
                        method="GET"
                        action="catatan_keperawatan.php">
                        <input
                            type="search"
                            name="search"
                            placeholder="Cari nama pasien, nomor rekam medis, atau perawat..."
                            value="<?= catatanEscape($search) ?>">

                        <button class="btn btn-secondary" type="submit">
                            Cari
                        </button>

                        <a
                            class="btn btn-secondary"
                            href="catatan_keperawatan.php">
                            Reset
                        </a>
                    </form>
                </div>

                <div class="catatan-table-wrap">
                    <table class="catatan-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Tanggal</th>
                                <th>Pasien</th>
                                <th>Perawat</th>
                                <th>Kondisi dan Keluhan</th>
                                <th>Tindakan</th>
                                <th>Evaluasi</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if ($notes === []): ?>
                                <tr>
                                    <td colspan="7" class="catatan-empty">
                                        Belum ada catatan keperawatan yang sesuai.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($notes as $index => $note): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>

                                        <td>
                                            <?= catatanEscape(
                                                date(
                                                    'd-m-Y H:i',
                                                    strtotime($note['note_date'])
                                                )
                                            ) ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= catatanEscape($note['patient_name']) ?>
                                            </strong>

                                            <div class="catatan-muted">
                                                No. RM:
                                                <?= catatanEscape(
                                                    $note['medical_record_number'] ?: '-'
                                                ) ?>
                                            </div>
                                        </td>

                                        <td>
                                            <?= catatanEscape($note['nurse_name']) ?>
                                        </td>

                                        <td>
                                            <?= nl2br(
                                                catatanEscape($note['patient_condition'])
                                            ) ?>

                                            <?php if (!empty($note['complaints'])): ?>
                                                <div class="catatan-muted">
                                                    <strong>Keluhan:</strong><br>
                                                    <?= nl2br(
                                                        catatanEscape($note['complaints'])
                                                    ) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?= nl2br(
                                                catatanEscape($note['nursing_action'])
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= !empty($note['evaluation'])
                                                ? nl2br(catatanEscape($note['evaluation']))
                                                : '-' ?>
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