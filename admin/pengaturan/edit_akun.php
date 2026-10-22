<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();
startAdminSession();

function editAkunEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$connection = getDatabaseConnection();
$accountId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$accountId || $accountId < 1) {
    header('Location: akun.php');
    exit;
}

$roles = [
    'admin' => 'Administrator',
    'dokter' => 'Dokter',
    'farmasi' => 'Farmasi',
    'perawat' => 'Perawat',
];

$errorMessage = null;
$account = null;
$doctors = [];

try {
    $statement = $connection->prepare(
        'SELECT id, username, role, doctor_id
         FROM admins
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $accountId]);
    $account = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        header('Location: akun.php');
        exit;
    }

    $doctorStatement = $connection->query(
        'SELECT id, name, specialty
         FROM doctors
         ORDER BY name ASC'
    );
    $doctors = $doctorStatement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('Load account for edit error: ' . $exception->getMessage());
    $errorMessage = 'Data akun gagal dimuat.';
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && $account !== null
) {
    $token = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['admin_csrf_token'] ?? '';

    if (
        !is_string($token)
        || !is_string($sessionToken)
        || $sessionToken === ''
        || !hash_equals($sessionToken, $token)
    ) {
        $errorMessage = 'Token keamanan tidak valid. Muat ulang halaman.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $role = (string) ($_POST['role'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $doctorInput = trim((string) ($_POST['doctor_id'] ?? ''));

        $doctorId = null;

        if ($role === 'dokter' && $doctorInput !== '') {
            $validatedId = filter_var(
                $doctorInput,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($validatedId !== false) {
                $doctorId = (int) $validatedId;
            }
        }

        if (!preg_match('/^[a-zA-Z0-9._-]{3,100}$/', $username)) {
            $errorMessage = 'Username harus 3–100 karakter dan hanya menggunakan huruf, angka, titik, garis bawah, atau tanda hubung.';
        } elseif (!isset($roles[$role])) {
            $errorMessage = 'Jenis akun tidak valid.';
        } elseif ($password !== '' && strlen($password) < 8) {
            $errorMessage = 'Password baru minimal 8 karakter.';
        } elseif ($role === 'dokter' && $doctorId === null) {
            $errorMessage = 'Pilih data dokter untuk akun dokter.';
        } elseif (
            (int) $account['id'] === (int) ($_SESSION['admin_id'] ?? 0)
            && $role !== 'admin'
        ) {
            $errorMessage = 'Jenis akun Administrator yang sedang digunakan tidak boleh diubah dari halaman ini.';
        } else {
            try {
                if ($role === 'dokter') {
                    $checkDoctor = $connection->prepare(
                        'SELECT id FROM doctors WHERE id = :id LIMIT 1'
                    );
                    $checkDoctor->execute(['id' => $doctorId]);

                    if (!$checkDoctor->fetchColumn()) {
                        throw new DomainException(
                            'Data dokter yang dipilih tidak ditemukan.'
                        );
                    }
                }

                $connection->beginTransaction();

                $sql = '
                    UPDATE admins
                    SET username = :username,
                        role = :role,
                        doctor_id = :doctor_id
                ';

                $params = [
                    'username' => $username,
                    'role' => $role,
                    'doctor_id' => $role === 'dokter' ? $doctorId : null,
                    'id' => $accountId,
                ];

                if ($password !== '') {
                    $passwordHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    if ($passwordHash === false) {
                        throw new RuntimeException(
                            'Password gagal diproses.'
                        );
                    }

                    $sql .= ', password_hash = :password_hash';
                    $params['password_hash'] = $passwordHash;
                }

                $sql .= ' WHERE id = :id';

                $update = $connection->prepare($sql);
                $update->execute($params);

                $connection->commit();

                $_SESSION['akun_flash_success'] =
                    'Akun berhasil diperbarui.';

                header('Location: akun.php');
                exit;
            } catch (DomainException $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                $errorMessage = $exception->getMessage();
            } catch (PDOException $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }

                error_log('Update account error: ' . $exception->getMessage());

                $errorMessage = $exception->getCode() === '23000'
                    ? 'Username sudah digunakan atau dokter sudah memiliki akun lain.'
                    : 'Akun gagal diperbarui. Periksa struktur tabel admins.';
            } catch (RuntimeException $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }

                error_log('Update account password error: ' . $exception->getMessage());
                $errorMessage = 'Password gagal diproses.';
            }
        }

        // Pertahankan isian saat validasi gagal.
        $account['username'] = $username;
        $account['role'] = $role;
        $account['doctor_id'] = $doctorId;
    }
}

$csrfToken = adminCsrfToken();

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Akun - RS Medika Sehat</title>

    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/pages/admin.css">
    <script src="../../js/pages/admin.js" defer></script>

    <style>
        .akun-edit-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .akun-edit-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .akun-edit-field label {
            font-size: 13px;
            font-weight: 700;
            color: #344054;
        }

        .akun-edit-field input,
        .akun-edit-field select {
            box-sizing: border-box;
            width: 100%;
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid var(--border, #d9e1e8);
            border-radius: 8px;
            background: #fff;
            color: #17212f;
            font: inherit;
        }

        .akun-edit-field small {
            color: #667085;
            font-size: 12px;
            line-height: 1.5;
        }

        .akun-edit-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 22px;
        }

        .akun-edit-error {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 9px;
            color: #b42318;
            background: #fee4e2;
        }

        @media (max-width: 700px) {
            .akun-edit-form {
                grid-template-columns: 1fr;
            }

            .akun-edit-actions {
                flex-direction: column;
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
                    <h1>Edit Akun</h1>
                    <p class="admin-intro">
                        Perbarui username, jenis akses, data dokter, atau password akun.
                    </p>
                </div>

                <a class="btn btn-secondary" href="akun.php">
                    Kembali
                </a>
            </div>

            <?php if ($errorMessage !== null): ?>
                <div class="akun-edit-error" role="alert">
                    <?= editAkunEscape($errorMessage) ?>
                </div>
            <?php endif; ?>

            <?php if ($account !== null): ?>
                <section class="admin-panel">

                    <form method="post" action="edit_akun.php?id=<?= $accountId ?>">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= editAkunEscape($csrfToken) ?>">

                        <div class="akun-edit-form">

                            <div class="akun-edit-field">
                                <label for="username">Username</label>
                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    minlength="3"
                                    maxlength="100"
                                    pattern="[a-zA-Z0-9._-]{3,100}"
                                    value="<?= editAkunEscape($account['username']) ?>"
                                    required>
                            </div>

                            <div class="akun-edit-field">
                                <label for="role">Jenis Akun</label>
                                <select id="role" name="role" required>
                                    <?php foreach ($roles as $value => $label): ?>
                                        <option
                                            value="<?= editAkunEscape($value) ?>"
                                            <?= $account['role'] === $value ? 'selected' : '' ?>>
                                            <?= editAkunEscape($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="akun-edit-field" id="doctor-field" hidden>
                                <label for="doctor_id">Data Dokter</label>
                                <select id="doctor_id" name="doctor_id">
                                    <option value="">Pilih dokter</option>
                                    <?php foreach ($doctors as $doctor): ?>
                                        <option
                                            value="<?= (int) $doctor['id'] ?>"
                                            <?= (string) ($account['doctor_id'] ?? '') === (string) $doctor['id'] ? 'selected' : '' ?>>
                                            <?= editAkunEscape($doctor['name']) ?>
                                            — <?= editAkunEscape($doctor['specialty']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="akun-edit-field">
                                <label for="password">Password Baru (opsional)</label>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    minlength="8"
                                    autocomplete="new-password"
                                    placeholder="Kosongkan jika tidak diganti">
                                <small>
                                    Password lama tetap digunakan jika kolom ini dikosongkan.
                                </small>
                            </div>

                        </div>

                        <div class="akun-edit-actions">
                            <a class="btn btn-secondary" href="akun.php">
                                Batal
                            </a>
                            <button class="btn btn-primary" type="submit">
                                Simpan Perubahan
                            </button>
                        </div>

                    </form>

                </section>
            <?php endif; ?>

        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role');
            const doctorField = document.getElementById('doctor-field');
            const doctorSelect = document.getElementById('doctor_id');

            function updateDoctorField() {
                const isDoctor = roleSelect.value === 'dokter';

                doctorField.hidden = !isDoctor;
                doctorSelect.required = isDoctor;

                if (!isDoctor) {
                    doctorSelect.value = '';
                }
            }

            roleSelect.addEventListener('change', updateDoctorField);
            updateDoctorField();
        });
    </script>

</body>

</html>