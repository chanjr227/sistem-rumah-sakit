<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();

if (session_status() !== PHP_SESSION_ACTIVE) {
    startAdminSession();
}

function akunEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$connection = getDatabaseConnection();

$errorMessage = null;
$successMessage = null;
$accounts = [];
$doctors = [];

$search = trim((string) ($_GET['search'] ?? ''));
$roleFilter = (string) ($_GET['role'] ?? '');

$allowedRoles = ['admin', 'dokter', 'farmasi', 'perawat'];

if (!in_array($roleFilter, $allowedRoles, true)) {
    $roleFilter = '';
}

$roleLabels = [
    'admin' => 'Administrator',
    'dokter' => 'Dokter',
    'farmasi' => 'Farmasi',
    'perawat' => 'Perawat',
];

/*
|--------------------------------------------------------------------------
| Ambil daftar dokter untuk pilihan akun dokter
|--------------------------------------------------------------------------
*/

try {
    $doctorStatement = $connection->query(
        'SELECT id, name, specialty
         FROM doctors
         ORDER BY name ASC'
    );

    $doctors = $doctorStatement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('Load doctors for accounts error: ' . $exception->getMessage());
    $errorMessage = 'Daftar dokter gagal dimuat. Periksa tabel doctors.';
}

/*
|--------------------------------------------------------------------------
| Tambah akun
|--------------------------------------------------------------------------
*/

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && isset($_POST['create_account'])
) {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['admin_csrf_token'] ?? '';

    if (
        !is_string($submittedToken)
        || !is_string($sessionToken)
        || $sessionToken === ''
        || !hash_equals($sessionToken, $submittedToken)
    ) {
        $errorMessage = 'Sesi formulir tidak valid. Silakan muat ulang halaman.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? '');
        $doctorIdInput = trim((string) ($_POST['doctor_id'] ?? ''));

        $doctorId = null;

        if ($role === 'dokter' && $doctorIdInput !== '') {
            $validatedDoctorId = filter_var(
                $doctorIdInput,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($validatedDoctorId !== false) {
                $doctorId = (int) $validatedDoctorId;
            }
        }

        if (!preg_match('/^[a-zA-Z0-9._-]{3,100}$/', $username)) {
            $errorMessage =
                'Username harus 3–100 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.';
        } elseif (strlen($password) < 8) {
            $errorMessage = 'Password minimal harus 8 karakter.';
        } elseif (!in_array($role, $allowedRoles, true)) {
            $errorMessage = 'Jenis akun tidak valid.';
        } elseif ($role === 'dokter' && $doctorId === null) {
            $errorMessage = 'Pilih dokter yang akan menggunakan akun ini.';
        } else {
            try {
                /*
                 * Pastikan dokter yang dipilih memang ada.
                 */
                if ($role === 'dokter') {
                    $checkDoctor = $connection->prepare(
                        'SELECT id FROM doctors WHERE id = :id LIMIT 1'
                    );

                    $checkDoctor->execute(['id' => $doctorId]);

                    if (!$checkDoctor->fetchColumn()) {
                        throw new DomainException(
                            'Dokter yang dipilih tidak ditemukan.'
                        );
                    }
                }

                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($passwordHash === false) {
                    throw new RuntimeException(
                        'Password gagal diproses.'
                    );
                }

                $insert = $connection->prepare(
                    'INSERT INTO admins
                        (username, password_hash, role, doctor_id)
                     VALUES
                        (:username, :password_hash, :role, :doctor_id)'
                );

                $insert->execute([
                    'username' => $username,
                    'password_hash' => $passwordHash,
                    'role' => $role,
                    'doctor_id' => $role === 'dokter' ? $doctorId : null,
                ]);

                $_SESSION['akun_flash_success'] =
                    'Akun ' . $roleLabels[$role] . ' berhasil dibuat.';

                header('Location: akun.php');
                exit;
            } catch (DomainException $exception) {
                $errorMessage = $exception->getMessage();
            } catch (PDOException $exception) {
                error_log('Create account error: ' . $exception->getMessage());

                if ($exception->getCode() === '23000') {
                    if ($role === 'dokter') {
                        $errorMessage =
                            'Username sudah digunakan atau dokter tersebut sudah memiliki akun.';
                    } else {
                        $errorMessage =
                            'Username sudah digunakan. Silakan gunakan username lain.';
                    }
                } else {
                    $errorMessage =
                        'Akun gagal dibuat. Periksa struktur tabel admins.';
                }
            } catch (RuntimeException $exception) {
                error_log('Account password error: ' . $exception->getMessage());
                $errorMessage = 'Password gagal diproses. Silakan coba lagi.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Pesan setelah pengalihan
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['akun_flash_success'])) {
    $successMessage = (string) $_SESSION['akun_flash_success'];
    unset($_SESSION['akun_flash_success']);
}

/*
|--------------------------------------------------------------------------
| Statistik akun
|--------------------------------------------------------------------------
*/

$totalAccounts = 0;
$totalDoctors = 0;
$totalPharmacy = 0;
$totalNurses = 0;

try {
    $totalAccounts = (int) $connection->query(
        'SELECT COUNT(*) FROM admins'
    )->fetchColumn();

    $totalDoctors = (int) $connection->query(
        "SELECT COUNT(*) FROM admins WHERE role = 'dokter'"
    )->fetchColumn();

    $totalPharmacy = (int) $connection->query(
        "SELECT COUNT(*) FROM admins WHERE role = 'farmasi'"
    )->fetchColumn();

    $totalNurses = (int) $connection->query(
        "SELECT COUNT(*) FROM admins WHERE role = 'perawat'"
    )->fetchColumn();

    /*
     * Daftar akun dengan pencarian dan filter jenis akun.
     */
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(
            a.username LIKE :search_username
            OR d.name LIKE :search_doctor
        )';

        $params['search_username'] = '%' . $search . '%';
        $params['search_doctor'] = '%' . $search . '%';
    }

    if ($roleFilter !== '') {
        $where[] = 'a.role = :role';
        $params['role'] = $roleFilter;
    }

    $sql = '
        SELECT
            a.id,
            a.username,
            a.role,
            a.doctor_id,
            a.created_at,
            d.name AS doctor_name,
            d.specialty AS doctor_specialty
        FROM admins a
        LEFT JOIN doctors d ON d.id = a.doctor_id
    ';

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY a.id DESC';

    $statement = $connection->prepare($sql);
    $statement->execute($params);

    $accounts = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('Load accounts error: ' . $exception->getMessage());
    $errorMessage ??=
        'Data akun gagal dimuat. Periksa struktur tabel admins dan doctors.';
}

$csrfToken = adminCsrfToken();

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pengaturan Akun - RS Medika Sehat</title>

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">
    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .akun-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .akun-stat {
            padding: 20px;
            background: var(--panel, #fff);
            border: 1px solid var(--border, #e5e7eb);
            border-radius: 12px;
        }

        .akun-stat-label {
            color: #667085;
            font-size: 13px;
        }

        .akun-stat-number {
            display: block;
            margin-top: 8px;
            font-size: 28px;
            font-weight: 800;
            color: #17212f;
        }

        .akun-toolbar {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .akun-filters {
            display: flex;
            flex: 1;
            flex-wrap: wrap;
            gap: 10px;
        }

        .akun-filters input,
        .akun-filters select,
        .akun-form input,
        .akun-form select {
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid var(--border, #d9e1e8);
            border-radius: 8px;
            font: inherit;
            background: #fff;
            color: #17212f;
        }

        .akun-filters input {
            flex: 1;
            min-width: 200px;
        }

        .akun-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .akun-table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        .akun-table th,
        .akun-table td {
            padding: 13px 12px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid #e7edf2;
        }

        .akun-table th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #667085;
            background: #f8fafc;
        }

        .akun-name {
            font-weight: 700;
            color: #17212f;
        }

        .akun-muted {
            margin-top: 4px;
            font-size: 12px;
            color: #697586;
        }

        .akun-badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .akun-admin {
            color: #175cd3;
            background: #eff8ff;
        }

        .akun-dokter {
            color: #18794e;
            background: #e7f8ef;
        }

        .akun-farmasi {
            color: #b54708;
            background: #fff0e7;
        }

        .akun-perawat {
            color: #6941c6;
            background: #f4ebff;
        }

        .akun-empty {
            padding: 35px 16px !important;
            text-align: center !important;
            color: #667085;
        }

        .akun-section-title {
            margin: 0 0 6px;
            color: #17212f;
            font-size: 18px;
            font-weight: 800;
        }

        .akun-section-description {
            margin: 0 0 20px;
            color: #667085;
            font-size: 13px;
            line-height: 1.6;
        }

        .akun-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .akun-form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .akun-form-group label {
            color: #344054;
            font-size: 13px;
            font-weight: 700;
        }

        .akun-form-group small {
            color: #667085;
            font-size: 12px;
            line-height: 1.5;
        }

        .akun-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 22px;
        }

        .akun-alert {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 9px;
            font-size: 14px;
            line-height: 1.6;
        }

        .akun-alert-error {
            color: #b42318;
            background: #fee4e2;
        }

        .akun-alert-success {
            color: #18794e;
            background: #e7f8ef;
        }

        .akun-note {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 9px;
            background: #eff8ff;
            color: #175cd3;
            font-size: 13px;
            line-height: 1.6;
        }

        .akun-password-wrap {
            display: flex;
            gap: 8px;
        }

        .akun-password-wrap input {
            flex: 1;
            min-width: 0;
        }

        .akun-password-toggle {
            padding: 0 12px;
            border: 1px solid var(--border, #d9e1e8);
            border-radius: 8px;
            background: #fff;
            color: #344054;
            cursor: pointer;
        }

        @media (max-width: 1000px) {
            .akun-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {

            .akun-stats,
            .akun-form-grid {
                grid-template-columns: 1fr;
            }

            .akun-filters {
                width: 100%;
            }

            .akun-filters input,
            .akun-filters select {
                width: 100%;
                min-width: 0;
            }

            .akun-form-actions {
                flex-direction: column;
            }

            .akun-form-actions .btn {
                width: 100%;
                text-align: center;
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
                    <h1>Pengaturan Akun</h1>
                    <p class="admin-intro">
                        Kelola akun pengguna dan hak akses sistem RS Medika Sehat.
                    </p>
                </div>

                <a class="btn btn-primary" href="#form-akun">
                    + Tambah Akun
                </a>
            </div>

            <?php if ($successMessage !== null): ?>
                <div class="akun-alert akun-alert-success" role="status">
                    <?= akunEscape($successMessage) ?>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage !== null): ?>
                <div class="akun-alert akun-alert-error" role="alert">
                    <?= akunEscape($errorMessage) ?>
                </div>
            <?php endif; ?>

            <div class="akun-stats">

                <div class="akun-stat">
                    <span class="akun-stat-label">Total akun</span>
                    <strong class="akun-stat-number">
                        <?= number_format($totalAccounts) ?>
                    </strong>
                </div>

                <div class="akun-stat">
                    <span class="akun-stat-label">Akun dokter</span>
                    <strong class="akun-stat-number">
                        <?= number_format($totalDoctors) ?>
                    </strong>
                </div>

                <div class="akun-stat">
                    <span class="akun-stat-label">Akun farmasi</span>
                    <strong class="akun-stat-number">
                        <?= number_format($totalPharmacy) ?>
                    </strong>
                </div>

                <div class="akun-stat">
                    <span class="akun-stat-label">Akun perawat</span>
                    <strong class="akun-stat-number">
                        <?= number_format($totalNurses) ?>
                    </strong>
                </div>

            </div>

            <!-- FORM TAMBAH AKUN -->
            <section class="admin-panel" id="form-akun">

                <h2 class="akun-section-title">Tambah Akun Pengguna</h2>

                <p class="akun-section-description">
                    Buat akun baru dan tentukan jenis akses sesuai tugas pengguna.
                    Password akan disimpan dalam bentuk hash.
                </p>

                <form class="akun-form" method="post" action="akun.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= akunEscape($csrfToken) ?>">

                    <input type="hidden" name="create_account" value="1">

                    <div class="akun-form-grid">

                        <div class="akun-form-group">
                            <label for="username">Username</label>

                            <input
                                type="text"
                                id="username"
                                name="username"
                                minlength="3"
                                maxlength="100"
                                pattern="[a-zA-Z0-9._-]{3,100}"
                                autocomplete="username"
                                placeholder="Contoh: dokter.andi"
                                required>

                            <small>
                                Gunakan 3–100 karakter: huruf, angka, titik,
                                garis bawah, atau tanda hubung.
                            </small>
                        </div>

                        <div class="akun-form-group">
                            <label for="password">Password</label>

                            <div class="akun-password-wrap">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    minlength="8"
                                    autocomplete="new-password"
                                    placeholder="Minimal 8 karakter"
                                    required>

                                <button
                                    class="akun-password-toggle"
                                    type="button"
                                    id="toggle-password"
                                    aria-label="Tampilkan password">
                                    Lihat
                                </button>
                            </div>

                            <small>
                                Gunakan password yang kuat dan jangan membagikannya
                                kepada pengguna lain.
                            </small>
                        </div>

                        <div class="akun-form-group">
                            <label for="role">Jenis Akun</label>

                            <select id="role" name="role" required>
                                <option value="">Pilih jenis akun</option>
                                <option value="admin">Administrator</option>
                                <option value="dokter">Dokter</option>
                                <option value="farmasi">Farmasi</option>
                                <option value="perawat">Perawat</option>
                            </select>

                            <small>
                                Jenis akun menentukan akses yang dapat diberikan
                                oleh sistem.
                            </small>
                        </div>

                        <div
                            class="akun-form-group"
                            id="doctor-field"
                            hidden>

                            <label for="doctor_id">Pilih Data Dokter</label>

                            <select id="doctor_id" name="doctor_id">
                                <option value="">Pilih dokter</option>

                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?= (int) $doctor['id'] ?>">
                                        <?= akunEscape($doctor['name']) ?>
                                        — <?= akunEscape($doctor['specialty']) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>

                            <small>
                                Akun dokter akan dihubungkan dengan data dokter
                                yang dipilih.
                            </small>
                        </div>

                    </div>

                    <div class="akun-form-actions">
                        <button
                            class="btn btn-primary"
                            type="submit">
                            Simpan Akun
                        </button>

                        <button
                            class="btn btn-secondary"
                            type="reset"
                            id="reset-account-form">
                            Reset Form
                        </button>
                    </div>

                </form>

                <div class="akun-note">
                    <strong>Informasi:</strong>
                    halaman ini digunakan untuk membuat dan melihat akun.
                    Akun Farmasi dan Perawat masih memerlukan halaman serta
                    aturan akses khusus agar dapat menggunakan menu sesuai
                    tugas masing-masing.
                </div>

            </section>

            <!-- DAFTAR AKUN -->
            <section class="admin-panel" style="margin-top: 22px;">

                <h2 class="akun-section-title">Daftar Akun Pengguna</h2>

                <p class="akun-section-description">
                    Cari akun berdasarkan username atau nama dokter,
                    lalu filter berdasarkan jenis akun.
                </p>

                <div class="akun-toolbar">

                    <form
                        class="akun-filters"
                        method="get"
                        action="akun.php">

                        <input
                            type="search"
                            name="search"
                            placeholder="Cari username atau nama dokter..."
                            value="<?= akunEscape($search) ?>">

                        <select name="role" aria-label="Filter jenis akun">
                            <option value="">Semua jenis akun</option>

                            <?php foreach ($roleLabels as $role => $label): ?>
                                <option
                                    value="<?= akunEscape($role) ?>"
                                    <?= $roleFilter === $role ? 'selected' : '' ?>>
                                    <?= akunEscape($label) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>

                        <button class="btn btn-secondary" type="submit">
                            Cari
                        </button>

                        <a class="btn btn-secondary" href="akun.php">
                            Reset
                        </a>

                    </form>

                </div>

                <div class="akun-table-wrap">

                    <table class="akun-table">

                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Username</th>
                                <th>Jenis Akun</th>
                                <th>Data Dokter</th>
                                <th>Tanggal Dibuat</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if ($accounts === []): ?>

                                <tr>
                                    <td colspan="5" class="akun-empty">
                                        Tidak ada akun yang sesuai dengan pencarian.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($accounts as $index => $account): ?>
                                    <?php
                                    $role = (string) $account['role'];
                                    $roleLabel = $roleLabels[$role] ?? ucfirst($role);
                                    $badgeClass = 'akun-' . $role;

                                    $createdAt = $account['created_at'] ?? '';
                                    $createdLabel = '-';

                                    if ($createdAt !== '') {
                                        $timestamp = strtotime((string) $createdAt);

                                        $createdLabel = $timestamp !== false
                                            ? date('d-m-Y H:i', $timestamp)
                                            : (string) $createdAt;
                                    }
                                    ?>

                                    <tr>

                                        <td><?= $index + 1 ?></td>

                                        <td>
                                            <div class="akun-name">
                                                <?= akunEscape($account['username']) ?>
                                            </div>
                                            <div class="akun-muted">
                                                ID akun: <?= (int) $account['id'] ?>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="akun-badge <?= akunEscape($badgeClass) ?>">
                                                <?= akunEscape($roleLabel) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php if ($role === 'dokter'): ?>

                                                <?php if (!empty($account['doctor_name'])): ?>
                                                    <div class="akun-name">
                                                        <?= akunEscape($account['doctor_name']) ?>
                                                    </div>
                                                    <div class="akun-muted">
                                                        <?= akunEscape($account['doctor_specialty'] ?? '-') ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="akun-muted">
                                                        Data dokter belum terhubung
                                                    </span>
                                                <?php endif; ?>

                                            <?php else: ?>

                                                <span class="akun-muted">-</span>

                                            <?php endif; ?>
                                        </td>

                                        <td><?= akunEscape($createdLabel) ?></td>

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
            const roleSelect = document.getElementById('role');
            const doctorField = document.getElementById('doctor-field');
            const doctorSelect = document.getElementById('doctor_id');
            const passwordInput = document.getElementById('password');
            const togglePassword = document.getElementById('toggle-password');
            const resetButton = document.getElementById('reset-account-form');

            function updateDoctorField() {
                const isDoctor = roleSelect.value === 'dokter';

                doctorField.hidden = !isDoctor;
                doctorSelect.required = isDoctor;

                if (!isDoctor) {
                    doctorSelect.value = '';
                }
            }

            roleSelect.addEventListener('change', updateDoctorField);

            togglePassword.addEventListener('click', function() {
                const showPassword = passwordInput.type === 'password';

                passwordInput.type = showPassword ? 'text' : 'password';
                togglePassword.textContent = showPassword ? 'Sembunyikan' : 'Lihat';
                togglePassword.setAttribute(
                    'aria-label',
                    showPassword ? 'Sembunyikan password' : 'Tampilkan password'
                );
            });

            resetButton.addEventListener('click', function() {
                window.setTimeout(function() {
                    updateDoctorField();
                    passwordInput.type = 'password';
                    togglePassword.textContent = 'Lihat';
                }, 0);
            });

            updateDoctorField();
        });
    </script>

</body>

</html>