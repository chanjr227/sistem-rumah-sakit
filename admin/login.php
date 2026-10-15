<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

startAdminSession();

$errorMessage = '';
$loginSuccess = false;

if (!empty($_SESSION['admin_authenticated'])) {
  header('Location: index.php');
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  if (!verifyAdminCsrfToken()) {
    http_response_code(403);
    $errorMessage = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
  } else {
    $usernameValue = $_POST['username'] ?? '';
    $passwordValue = $_POST['password'] ?? '';

    $username = is_string($usernameValue) ? trim($usernameValue) : '';
    $password = is_string($passwordValue) ? $passwordValue : '';

    if ($username === '' || $password === '') {
      http_response_code(422);
      $errorMessage = 'Nama pengguna dan kata sandi wajib diisi.';
    } else {
      try {
        $connection = getDatabaseConnection();

        $statement = $connection->prepare(
          'SELECT id, username, password_hash
                     FROM admins
                     WHERE username = :username
                     LIMIT 1'
        );

        $statement->execute(['username' => $username]);
        $admin = $statement->fetch();

        if (
          $admin &&
          password_verify($password, $admin['password_hash'])
        ) {
          session_regenerate_id(true);

          $_SESSION['admin_authenticated'] = true;
          $_SESSION['admin_id'] = (int) $admin['id'];
          $_SESSION['admin_username'] = $admin['username'];

          unset($_SESSION['admin_csrf_token']);

          $loginSuccess = true;
        } else {
          http_response_code(401);
          $errorMessage = 'Nama pengguna atau kata sandi tidak sesuai.';
        }
      } catch (PDOException $exception) {
        error_log(
          'Admin login database error: ' . $exception->getMessage()
        );

        http_response_code(503);
        $errorMessage = 'Login belum dapat diproses. Silakan coba lagi nanti.';
      }
    }
  }
}

if ($loginSuccess):
?>
  <!DOCTYPE html>
  <html lang="id">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0b7775">
    <meta http-equiv="refresh" content="2;url=index.php">

    <title>Login Berhasil - RS Medika Sehat</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet">

    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/pages/admin.css">
  </head>

  <body class="admin-page admin-login-modern">
    <main class="admin-success-wrap">
      <section class="admin-success-card" role="status" aria-live="polite">
        <div class="success-check">
          <svg viewBox="0 0 52 52" aria-hidden="true">
            <circle class="success-circle" cx="26" cy="26" r="23" />
            <path class="success-tick" d="M14 27 L22 35 L39 17" />
          </svg>
        </div>

        <p class="eyebrow">RS MEDIKA SEHAT</p>
        <h1>Login Berhasil!</h1>
        <p>
          Selamat datang kembali, Admin.
          Anda akan diarahkan ke dashboard.
        </p>

        <div class="success-loading" aria-hidden="true">
          <span></span>
          <span></span>
          <span></span>
        </div>

        <a class="success-manual-link" href="index.php">
          Masuk sekarang →
        </a>
      </section>
    </main>

    <script>
      window.setTimeout(() => {
        window.location.replace('index.php');
      }, 1600);
    </script>
  </body>

  </html>
<?php
  exit;
endif;

$csrfToken = adminCsrfToken();
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#0b7775">

  <title>Login Admin - RS Medika Sehat</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">

  <link rel="stylesheet" href="../css/base.css">
  <link rel="stylesheet" href="../css/pages/admin.css">
</head>

<body class="admin-page admin-login-modern">

  <main class="admin-login-wrap">
    <section class="admin-login-shell">

      <!-- PANEL KIRI -->
      <aside class="admin-login-visual">
        <a class="admin-visual-brand" href="../index.php">
          <span class="admin-visual-logo" aria-hidden="true">
            <svg viewBox="0 0 48 48">
              <path d="M24 8v32M8 24h32" />
            </svg>
          </span>

          <span>
            <strong>Medika Sehat</strong>
            <small>Hospital Management</small>
          </span>
        </a>

        <div class="admin-visual-copy">
          <span class="admin-visual-tag">
            <span></span> ADMINISTRATOR PORTAL
          </span>

          <h1>Mengelola layanan kesehatan, lebih mudah.</h1>

          <p>
            Akses panel administrator untuk mengelola data dokter
            dan mendukung pelayanan RS Medika Sehat.
          </p>
        </div>

        <div class="admin-medical-art" aria-hidden="true">
          <div class="medical-orbit orbit-one"></div>
          <div class="medical-orbit orbit-two"></div>

          <div class="medical-cross-card">
            <svg viewBox="0 0 120 120">
              <rect x="38" y="12" width="44" height="96" rx="13" />
              <rect x="12" y="38" width="96" height="44" rx="13" />
            </svg>
          </div>

          <div class="medical-float-card float-card-one">
            <span class="medical-float-icon">+</span>
            <span>
              <strong>Pelayanan</strong>
              <small>Terintegrasi</small>
            </span>
          </div>

          <div class="medical-float-card float-card-two">
            <span class="medical-status-dot"></span>
            <span>
              <strong>Sistem Admin</strong>
              <small>Akses terlindungi</small>
            </span>
          </div>
        </div>

        <div class="admin-visual-bottom">
          <span>Peduli · Profesional · Terpercaya</span>
          <span class="admin-visual-line"></span>
        </div>
      </aside>

      <!-- PANEL KANAN -->
      <div class="admin-login-content">

        <a class="admin-back-link" href="../index.php">
          <span aria-hidden="true">←</span>
          Kembali ke beranda
        </a>

        <div class="admin-mobile-brand">
          <span class="admin-visual-logo" aria-hidden="true">
            <svg viewBox="0 0 48 48">
              <path d="M24 8v32M8 24h32" />
            </svg>
          </span>
          <strong>RS Medika Sehat</strong>
        </div>

        <div class="admin-login-heading">
          <span class="admin-heading-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
              <rect x="4" y="10" width="16" height="11" rx="2" />
              <path d="M8 10V7a4 4 0 0 1 8 0v3" />
            </svg>
          </span>

          <p class="eyebrow">AREA ADMINISTRATOR</p>
          <h2>Selamat datang!</h2>
          <p class="admin-intro">
            Masuk menggunakan akun administrator untuk melanjutkan.
          </p>
        </div>

        <?php if ($errorMessage !== ''): ?>
          <p class="admin-alert error" role="alert">
            <span aria-hidden="true">!</span>
            <?= escapeHtml($errorMessage) ?>
          </p>
        <?php endif; ?>

        <form
          class="admin-form admin-form-modern"
          method="post"
          action="login.php">

          <input
            type="hidden"
            name="csrf_token"
            value="<?= escapeHtml($csrfToken) ?>">

          <label for="username">Nama pengguna</label>

          <div class="admin-input-wrap">
            <span class="admin-input-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <circle cx="12" cy="8" r="4" />
                <path d="M4 21a8 8 0 0 1 16 0" />
              </svg>
            </span>

            <input
              id="username"
              type="text"
              name="username"
              placeholder="Masukkan nama pengguna"
              autocomplete="username"
              required
              autofocus>
          </div>

          <label for="password">Kata sandi</label>

          <div class="admin-input-wrap">
            <span class="admin-input-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <rect x="4" y="10" width="16" height="11" rx="2" />
                <path d="M8 10V7a4 4 0 0 1 8 0v3" />
              </svg>
            </span>

            <input
              id="password"
              type="password"
              name="password"
              placeholder="Masukkan kata sandi"
              autocomplete="current-password"
              required>

            <button
              class="password-toggle"
              type="button"
              aria-label="Tampilkan kata sandi"
              aria-pressed="false">
              Lihat
            </button>
          </div>

          <button
            class="btn btn-primary full-width admin-submit"
            type="submit">
            <span>Masuk ke Dashboard</span>
            <span class="admin-submit-arrow" aria-hidden="true">→</span>
          </button>
        </form>

        <div class="admin-security-note">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 3 20 6v5c0 5-3.4 8.3-8 10-4.6-1.7-8-5-8-10V6z" />
            <path d="m9 12 2 2 4-4" />
          </svg>
          <span>Akses khusus administrator</span>
        </div>

        <p class="admin-login-footer">
          &copy; <?= date('Y') ?> RS Medika Sehat
          <span>·</span> Hak cipta dilindungi
        </p>
      </div>

    </section>
  </main>

  <script>
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.querySelector('.password-toggle');
    const loginForm = document.querySelector('.admin-form-modern');
    const submitButton = document.querySelector('.admin-submit');

    passwordToggle.addEventListener('click', () => {
      const showing = passwordInput.type === 'text';

      passwordInput.type = showing ? 'password' : 'text';
      passwordToggle.textContent = showing ? 'Lihat' : 'Sembunyi';

      passwordToggle.setAttribute(
        'aria-label',
        showing ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi'
      );

      passwordToggle.setAttribute('aria-pressed', String(!showing));
    });

    loginForm.addEventListener('submit', () => {
      submitButton.classList.add('is-loading');
      submitButton.querySelector('span:first-child').textContent =
        'Memeriksa akun...';
      submitButton.disabled = true;
    });
  </script>

</body>

</html>