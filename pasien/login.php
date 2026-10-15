<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

startPatientSession();

if (isset($_SESSION['patient_id'])) {
  header('Location: dashboard.php');
  exit;
}

$errorMessage = '';
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $emailValue = $_POST['email'] ?? '';
  $passwordValue = $_POST['password'] ?? '';

  $email = is_string($emailValue)
    ? mb_strtolower(trim($emailValue), 'UTF-8')
    : '';

  $password = is_string($passwordValue) ? $passwordValue : '';

  if (!verifyPatientCsrfToken()) {
    http_response_code(403);
    $errorMessage = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
  } elseif (
    $email === ''
    || strlen($email) > 254
    || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    || $password === ''
  ) {
    http_response_code(422);
    $errorMessage = 'Masukkan email dan kata sandi yang valid.';
  } else {
    try {
      $connection = getDatabaseConnection();

      $statement = $connection->prepare(
        'SELECT id, name, email, password_hash
                 FROM patients
                 WHERE email = :email
                 LIMIT 1'
      );

      $statement->execute(['email' => $email]);
      $patient = $statement->fetch();

      if (
        $patient
        && password_verify($password, $patient['password_hash'])
      ) {
        session_regenerate_id(true);

        $_SESSION['patient_id'] = (int) $patient['id'];
        $_SESSION['patient_name'] = $patient['name'];
        $_SESSION['patient_email'] = $patient['email'];

        unset($_SESSION['patient_csrf_token']);

        header('Location: dashboard.php');
        exit;
      }

      http_response_code(401);
      $errorMessage = 'Email atau kata sandi tidak sesuai.';
    } catch (PDOException $exception) {
      error_log(
        'Patient login database error: '
          . $exception->getMessage()
      );

      http_response_code(503);
      $errorMessage = 'Login belum dapat diproses. Silakan coba lagi nanti.';
    }
  }
}

$csrfToken = patientCsrfToken();
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <meta name="theme-color" content="#f3f7fc">

  <title>Login Pasien - RS Medika Sehat</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">

  <link rel="stylesheet" href="../css/base.css">
  <link rel="stylesheet" href="../css/pages/patient.css">
</head>

<body class="patient-page">

  <main class="patient-auth-wrap">

    <!-- Panel visual -->
    <aside class="patient-visual" aria-label="Informasi RS Medika Sehat">

      <a class="patient-brand" href="../index.php">
        <span class="patient-brand-icon" aria-hidden="true">
          <svg viewBox="0 0 48 48" fill="none">
            <rect x="3" y="3" width="42" height="42" rx="14"
              fill="white" fill-opacity=".15" />
            <path d="M24 12V36M12 24H36"
              stroke="white" stroke-width="6"
              stroke-linecap="round" />
          </svg>
        </span>

        <span class="patient-brand-text">
          <strong>Medika Sehat</strong>
          <small>Rumah Sakit</small>
        </span>
      </a>

      <div class="patient-visual-content">
        <span class="patient-pill">
          <span class="patient-status-dot"></span>
          Layanan kesehatan digital
        </span>

        <h2>
          Kesehatan Anda,<br>
          <span>prioritas kami.</span>
        </h2>

        <p>
          Akses informasi akun dan janji konsultasi Anda
          dengan lebih mudah, nyaman, dan praktis.
        </p>

        <div class="patient-illustration" aria-hidden="true">
          <div class="illustration-orbit orbit-one"></div>
          <div class="illustration-orbit orbit-two"></div>

          <div class="illustration-card illustration-card-left">
            <span class="illustration-mini-icon">+</span>
            <span>
              <strong>Peduli</strong>
              <small>Setiap saat</small>
            </span>
          </div>

          <div class="illustration-cross">
            <svg viewBox="0 0 180 180" fill="none">
              <rect x="25" y="25" width="130" height="130"
                rx="38" fill="white" fill-opacity=".14" />
              <rect x="67" y="47" width="46" height="86"
                rx="15" fill="white" />
              <rect x="47" y="67" width="86" height="46"
                rx="15" fill="white" />
              <circle cx="90" cy="90" r="14" fill="#58BFEA" />
            </svg>
          </div>

          <div class="illustration-card illustration-card-right">
            <span class="illustration-check">✓</span>
            <span>
              <strong>Lebih mudah</strong>
              <small>Akses layanan</small>
            </span>
          </div>
        </div>
      </div>

      <div class="patient-visual-footer">
        <span class="footer-shield">✓</span>
        Layanan pasien yang praktis dan terpercaya
      </div>
    </aside>

    <!-- Form login -->
    <section class="patient-form-side">
      <div class="patient-form-container">

        <a class="patient-back-link" href="../index.php">
          <span aria-hidden="true">←</span>
          Kembali ke beranda
        </a>

        <div class="patient-mobile-brand">
          <span class="mobile-brand-icon">+</span>
          <span>RS Medika Sehat</span>
        </div>

        <div class="patient-form-heading">
          <span class="patient-heading-label">
            <span class="heading-label-line"></span>
            PORTAL PASIEN
          </span>

          <h1>Selamat datang<span>.</span></h1>

          <p>
            Masuk ke akun Anda untuk melihat janji
            konsultasi dan informasi pasien.
          </p>
        </div>

        <?php if ($errorMessage !== ''): ?>
          <div class="patient-alert error" role="alert">
            <span class="alert-icon" aria-hidden="true">!</span>
            <span>
              <?= escapePatientHtml($errorMessage) ?>
            </span>
          </div>
        <?php endif; ?>

        <form
          class="patient-form"
          method="post"
          action="login.php"
          id="patientLoginForm">
          <input
            type="hidden"
            name="csrf_token"
            value="<?= escapePatientHtml($csrfToken) ?>">

          <label class="patient-field">
            <span class="patient-field-label">Alamat email</span>

            <span class="patient-input-wrap">
              <svg class="field-icon" viewBox="0 0 24 24"
                fill="none" aria-hidden="true">
                <rect x="3" y="5" width="18" height="14"
                  rx="3" stroke="currentColor"
                  stroke-width="1.7" />
                <path d="M4 7L12 13L20 7"
                  stroke="currentColor"
                  stroke-width="1.7"
                  stroke-linecap="round"
                  stroke-linejoin="round" />
              </svg>

              <input
                type="email"
                name="email"
                placeholder="nama@email.com"
                maxlength="254"
                autocomplete="email"
                value="<?= escapePatientHtml($email) ?>"
                required>
            </span>
          </label>

          <label class="patient-field">
            <span class="patient-field-label">Kata sandi</span>

            <span class="patient-input-wrap">
              <svg class="field-icon" viewBox="0 0 24 24"
                fill="none" aria-hidden="true">
                <rect x="4" y="10" width="16" height="11"
                  rx="3" stroke="currentColor"
                  stroke-width="1.7" />
                <path d="M8 10V7a4 4 0 0 1 8 0v3"
                  stroke="currentColor"
                  stroke-width="1.7"
                  stroke-linecap="round" />
                <circle cx="12" cy="15" r="1.5"
                  fill="currentColor" />
              </svg>

              <input
                type="password"
                name="password"
                id="patientPassword"
                placeholder="Masukkan kata sandi"
                autocomplete="current-password"
                required>

              <button
                type="button"
                class="password-toggle"
                id="passwordToggle"
                aria-label="Tampilkan kata sandi"
                aria-pressed="false">
                <svg viewBox="0 0 24 24" fill="none"
                  aria-hidden="true">
                  <path d="M2.5 12S6 5.5 12 5.5
                                             21.5 12 21.5 12
                                             18 18.5 12 18.5
                                             2.5 12 2.5 12Z"
                    stroke="currentColor"
                    stroke-width="1.7"
                    stroke-linejoin="round" />
                  <circle cx="12" cy="12" r="3"
                    stroke="currentColor"
                    stroke-width="1.7" />
                </svg>
              </button>
            </span>
          </label>

          <button
            class="patient-submit"
            type="submit"
            id="patientSubmit">
            <span class="submit-label">Masuk ke akun</span>

            <span class="submit-loader" aria-hidden="true"></span>

            <svg class="submit-arrow" viewBox="0 0 24 24"
              fill="none" aria-hidden="true">
              <path d="M5 12H19M12 5L19 12L12 19"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round" />
            </svg>
          </button>
        </form>

        <div class="patient-form-divider">
          <span></span>
          <small>Belum terdaftar?</small>
          <span></span>
        </div>

        <a class="patient-register-link" href="daftar.php">
          Buat akun pasien
          <span aria-hidden="true">↗</span>
        </a>

        <p class="patient-privacy-note">
          <span aria-hidden="true">⌑</span>
          Jaga kerahasiaan email dan kata sandi Anda.
        </p>

      </div>
    </section>
  </main>

  <script>
    (() => {
      const form = document.getElementById('patientLoginForm');
      const password = document.getElementById('patientPassword');
      const toggle = document.getElementById('passwordToggle');
      const submit = document.getElementById('patientSubmit');

      toggle.addEventListener('click', () => {
        const isHidden = password.type === 'password';

        password.type = isHidden ? 'text' : 'password';
        toggle.setAttribute(
          'aria-label',
          isHidden ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'
        );
        toggle.setAttribute('aria-pressed', String(isHidden));
      });

      form.addEventListener('submit', () => {
        submit.classList.add('is-loading');
        submit.setAttribute('aria-busy', 'true');
        submit.querySelector('.submit-label').textContent =
          'Memeriksa akun...';
      });
    })();
  </script>

</body>

</html>