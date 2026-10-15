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
$name = '';
$email = '';
$phone = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $nameValue = $_POST['name'] ?? '';
  $emailValue = $_POST['email'] ?? '';
  $phoneValue = $_POST['phone'] ?? '';
  $passwordValue = $_POST['password'] ?? '';
  $passwordConfirmValue = $_POST['password_confirmation'] ?? '';

  $name = is_string($nameValue) ? trim($nameValue) : '';
  $email = is_string($emailValue) ? trim($emailValue) : '';
  $phone = is_string($phoneValue) ? trim($phoneValue) : '';

  $password = is_string($passwordValue) ? $passwordValue : '';
  $passwordConfirmation = is_string($passwordConfirmValue)
    ? $passwordConfirmValue
    : '';

  if (!verifyPatientCsrfToken()) {
    http_response_code(403);
    $errorMessage = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
  } elseif (
    $name === ''
    || mb_strlen($name, 'UTF-8') > 255
    || $email === ''
    || strlen($email) > 254
    || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    || mb_strlen($phone, 'UTF-8') > 30
  ) {
    http_response_code(422);
    $errorMessage = 'Periksa kembali nama, email, dan nomor telepon Anda.';
  } elseif (strlen($password) < 10) {
    http_response_code(422);
    $errorMessage = 'Kata sandi minimal 10 karakter.';
  } elseif (!hash_equals($password, $passwordConfirmation)) {
    http_response_code(422);
    $errorMessage = 'Konfirmasi kata sandi tidak sama.';
  } else {
    try {
      $normalizedEmail = mb_strtolower($email, 'UTF-8');
      $connection = getDatabaseConnection();

      $statement = $connection->prepare(
        'INSERT INTO patients (name, email, phone, password_hash)
                 VALUES (:name, :email, :phone, :password_hash)'
      );

      $statement->execute([
        'name' => $name,
        'email' => $normalizedEmail,
        'phone' => $phone,
        'password_hash' => password_hash(
          $password,
          PASSWORD_DEFAULT
        ),
      ]);

      session_regenerate_id(true);

      $_SESSION['patient_id'] = (int) $connection->lastInsertId();
      $_SESSION['patient_name'] = $name;
      $_SESSION['patient_email'] = $normalizedEmail;

      unset($_SESSION['patient_csrf_token']);

      header('Location: dashboard.php');
      exit;
    } catch (PDOException $exception) {
      if ($exception->getCode() === '23000') {
        http_response_code(409);
        $errorMessage = 'Email tersebut sudah terdaftar. Silakan masuk.';
      } else {
        error_log(
          'Patient registration database error: '
            . $exception->getMessage()
        );

        http_response_code(503);
        $errorMessage = 'Pendaftaran belum dapat diproses. Silakan coba lagi nanti.';
      }
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

  <title>Daftar Pasien - RS Medika Sehat</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">

  <link rel="stylesheet" href="../css/base.css">
  <link rel="stylesheet" href="../css/pages/patient.css">
</head>

<body class="patient-page patient-register-page">

  <main class="patient-auth-wrap patient-register-wrap">

    <!-- Panel visual -->
    <aside class="patient-visual" aria-label="RS Medika Sehat">

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
          Bergabung bersama kami
        </span>

        <h2>
          Mulai langkah<br>
          <span>hidup lebih sehat.</span>
        </h2>

        <p>
          Buat akun pasien untuk mempermudah akses informasi
          akun dan pengelolaan janji konsultasi Anda.
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
              <strong>Mulai sekarang</strong>
              <small>Buat akun Anda</small>
            </span>
          </div>
        </div>
      </div>

      <div class="patient-visual-footer">
        <span class="footer-shield">✓</span>
        Langkah mudah untuk mengakses layanan pasien
      </div>
    </aside>

    <!-- Form pendaftaran -->
    <section class="patient-form-side patient-register-side">
      <div class="patient-form-container patient-register-container">

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
            PENDAFTARAN PASIEN
          </span>

          <h1>Buat akun<span>.</span></h1>

          <p>
            Lengkapi informasi berikut untuk membuat akun
            pasien baru.
          </p>
        </div>

        <?php if ($errorMessage !== ''): ?>
          <div class="patient-alert error" role="alert">
            <span class="alert-icon" aria-hidden="true">!</span>
            <span><?= escapePatientHtml($errorMessage) ?></span>
          </div>
        <?php endif; ?>

        <form
          class="patient-form patient-register-form"
          method="post"
          action="daftar.php"
          id="patientRegisterForm">
          <input
            type="hidden"
            name="csrf_token"
            value="<?= escapePatientHtml($csrfToken) ?>">

          <label class="patient-field">
            <span class="patient-field-label">Nama lengkap</span>

            <span class="patient-input-wrap">
              <svg class="field-icon" viewBox="0 0 24 24"
                fill="none" aria-hidden="true">
                <circle cx="12" cy="8" r="4"
                  stroke="currentColor" stroke-width="1.7" />
                <path d="M4 21V19a8 8 0 0 1 16 0v2"
                  stroke="currentColor" stroke-width="1.7"
                  stroke-linecap="round" />
              </svg>

              <input
                type="text"
                name="name"
                placeholder="Masukkan nama lengkap"
                maxlength="255"
                autocomplete="name"
                value="<?= escapePatientHtml($name) ?>"
                required>
            </span>
          </label>

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
            <span class="patient-field-label">
              Nomor telepon
              <span class="optional-label">Opsional</span>
            </span>

            <span class="patient-input-wrap">
              <svg class="field-icon" viewBox="0 0 24 24"
                fill="none" aria-hidden="true">
                <path d="M7 3H5a2 2 0 0 0-2 2
                                     16 16 0 0 0 16 16
                                     2 2 0 0 0 2-2v-2l-5-2-2 3
                                     a13 13 0 0 1-5-5l3-2-2-5H7Z"
                  stroke="currentColor"
                  stroke-width="1.7"
                  stroke-linecap="round"
                  stroke-linejoin="round" />
              </svg>

              <input
                type="tel"
                name="phone"
                placeholder="Contoh: 081234567890"
                maxlength="30"
                autocomplete="tel"
                value="<?= escapePatientHtml($phone) ?>">
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
                id="registerPassword"
                placeholder="Minimal 10 karakter"
                minlength="10"
                autocomplete="new-password"
                required>

              <button
                type="button"
                class="password-toggle"
                data-password-toggle="registerPassword"
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

            <span class="password-strength" id="passwordStrength"
              aria-live="polite">
              <span class="strength-track">
                <span class="strength-fill"></span>
                <span class="strength-fill"></span>
                <span class="strength-fill"></span>
                <span class="strength-fill"></span>
              </span>
              <small class="strength-text">
                Gunakan minimal 10 karakter.
              </small>
            </span>
          </label>

          <label class="patient-field">
            <span class="patient-field-label">
              Konfirmasi kata sandi
            </span>

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
                name="password_confirmation"
                id="registerPasswordConfirm"
                placeholder="Ulangi kata sandi"
                minlength="10"
                autocomplete="new-password"
                required>

              <button
                type="button"
                class="password-toggle"
                data-password-toggle="registerPasswordConfirm"
                aria-label="Tampilkan konfirmasi kata sandi"
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

            <small class="password-match" id="passwordMatch"
              aria-live="polite"></small>
          </label>

          <button
            class="patient-submit"
            type="submit"
            id="registerSubmit">
            <span class="submit-label">Buat akun pasien</span>
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
          <small>Sudah memiliki akun?</small>
          <span></span>
        </div>

        <a class="patient-register-link" href="login.php">
          Masuk ke akun
          <span aria-hidden="true">↗</span>
        </a>

        <p class="patient-privacy-note">
          <span aria-hidden="true">⌑</span>
          Informasi akun Anda digunakan untuk layanan pasien.
        </p>

      </div>
    </section>

  </main>

  <script>
    (() => {
      const form = document.getElementById('patientRegisterForm');
      const password = document.getElementById('registerPassword');
      const confirmation = document.getElementById('registerPasswordConfirm');
      const strength = document.getElementById('passwordStrength');
      const matchMessage = document.getElementById('passwordMatch');
      const submit = document.getElementById('registerSubmit');

      document.querySelectorAll('[data-password-toggle]').forEach(button => {
        button.addEventListener('click', () => {
          const input = document.getElementById(
            button.dataset.passwordToggle
          );

          const show = input.type === 'password';

          input.type = show ? 'text' : 'password';
          button.setAttribute(
            'aria-pressed',
            String(show)
          );

          button.setAttribute(
            'aria-label',
            show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'
          );
        });
      });

      function updateStrength() {
        const value = password.value;
        const bars = strength.querySelectorAll('.strength-fill');
        const text = strength.querySelector('.strength-text');

        let score = 0;

        if (value.length >= 10) score++;
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
        if (/\d/.test(value)) score++;
        if (/[^A-Za-z0-9]/.test(value)) score++;

        bars.forEach((bar, index) => {
          bar.className = 'strength-fill';

          if (value.length > 0 && index < score) {
            bar.classList.add('active', `level-${score}`);
          }
        });

        if (value.length === 0) {
          text.textContent = 'Gunakan minimal 10 karakter.';
        } else if (value.length < 10) {
          text.textContent = 'Kata sandi belum mencapai 10 karakter.';
        } else if (score <= 2) {
          text.textContent = 'Kekuatan kata sandi: cukup.';
        } else if (score === 3) {
          text.textContent = 'Kekuatan kata sandi: baik.';
        } else {
          text.textContent = 'Kekuatan kata sandi: kuat.';
        }
      }

      function updateMatch() {
        if (confirmation.value === '') {
          matchMessage.textContent = '';
          matchMessage.className = 'password-match';
          confirmation.setCustomValidity('');
          return;
        }

        const matches = password.value === confirmation.value;

        matchMessage.textContent = matches ?
          '✓ Kata sandi cocok.' :
          'Kata sandi belum sama.';

        matchMessage.className = matches ?
          'password-match match-success' :
          'password-match match-error';

        confirmation.setCustomValidity(
          matches ? '' : 'Konfirmasi kata sandi tidak sama.'
        );
      }

      password.addEventListener('input', () => {
        updateStrength();
        updateMatch();
      });

      confirmation.addEventListener('input', updateMatch);

      form.addEventListener('submit', event => {
        updateMatch();

        if (!form.checkValidity()) {
          event.preventDefault();
          form.reportValidity();
          return;
        }

        submit.classList.add('is-loading');
        submit.setAttribute('aria-busy', 'true');
        submit.querySelector('.submit-label').textContent =
          'Membuat akun...';
      });
    })();
  </script>

</body>

</html>