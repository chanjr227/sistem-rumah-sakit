<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/pasien/auth.php';

startPatientSession();

$doctors = [];
$doctorLoadError = false;

try {
  $connection = getDatabaseConnection();
  $doctors = $connection->query(
    'SELECT id, name, specialty, work_days, start_time, end_time, room, status
         FROM doctors
         ORDER BY name ASC'
  )->fetchAll();
} catch (PDOException $exception) {
  error_log('Home doctor list database error: ' . $exception->getMessage());
  $doctorLoadError = true;
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>RS Medika Sehat</title>
  <meta
    name="description"
    content="RS Medika Sehat menyediakan layanan kesehatan modern, dokter berpengalaman, rawat inap, dan konsultasi online untuk keluarga Anda." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="css/base.css" />
  <link rel="stylesheet" href="css/pages/home.css" />
</head>

<body>
  <header class="topbar">
    <div class="container nav-wrap">
      <div class="brand">
        <div class="brand-mark">✚</div>
        <div>
          <strong>RS Medika Sehat</strong>
          <span>Care for Life</span>
        </div>
      </div>

      <nav class="main-nav" aria-label="Navigasi utama">
        <a href="#home">Beranda</a>
        <a href="#about">Tentang</a>
        <a href="#services">Layanan</a>
        <a href="#doctors">Dokter</a>
        <a href="#contact">Kontak</a>

        <div class="login-dropdown">
          <button
            type="button"
            class="login-toggle"
            aria-haspopup="true"
            aria-expanded="false">
            Login <span class="login-arrow">⌄</span>
          </button>

          <div class="login-menu">
            <a href="admin/login.php">
              <span class="login-icon">🛡️</span>
              <span class="login-text">
                <strong>Login Admin</strong>
                <small>Masuk ke panel admin</small>
              </span>
            </a>

            <a href="pasien/login.php">
              <span class="login-icon">👤</span>
              <span class="login-text">
                <strong>Login Pasien</strong>
                <small>Masuk ke akun pasien</small>
              </span>
            </a>
          </div>
        </div>

      </nav>

      <a class="btn btn-primary" href="#appointment">Jadwal Konsultasi</a>
    </div>
  </header>

  <main>
    <section class="hero" id="home">
      <div class="container hero-grid">
        <div class="hero-copy">
          <p class="eyebrow">Pelayanan kesehatan terpercaya</p>
          <h1>Membawa kesehatan dan harapan untuk keluarga Anda.</h1>
          <p class="lead">
            RS Medika Sehat menghadirkan layanan medis modern, tenaga profesional,
            dan pendekatan manusiawi untuk menjaga kesehatan Anda dan orang terkasih.
          </p>
          <div class="hero-actions">
            <a class="btn btn-primary" href="#appointment">Buat Janji</a>
            <a class="btn btn-secondary" href="#services">Lihat Layanan</a>
          </div>

          <div class="hero-badges" aria-label="Keunggulan rumah sakit">
            <div>
              <strong>24/7</strong>
              <span>Emergency</span>
            </div>
            <div>
              <strong>18+</strong>
              <span>Spesialis</span>
            </div>
            <div>
              <strong>12.5K</strong>
              <span>Pasien Pulih</span>
            </div>
          </div>
        </div>

        <div class="hero-visual" aria-label="Foto rumah sakit">
          <div class="image-card main-card">
            <img
              src="https://images.unsplash.com/photo-1584515933487-779824d29309?auto=format&fit=crop&w=900&q=80"
              alt="Dokter berdiri di rumah sakit" />
          </div>
          <div class="floating-note note-one">
            <span class="icon">✓</span>
            <div>
              <strong>Tim Medis Profesional</strong>
              <small>Dokter berpengalaman</small>
            </div>
          </div>
          <div class="floating-note note-two">
            <span class="icon">🩺</span>
            <div>
              <strong>Diagnosis Akurat</strong>
              <small>Teknologi canggih</small>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="stats">
      <div class="container stats-grid">
        <div class="stat-item">
          <strong>25+</strong>
          <span>Tahun pengalaman</span>
        </div>
        <div class="stat-item">
          <strong>35K+</strong>
          <span>Pasien ditangani</span>
        </div>
        <div class="stat-item">
          <strong>98%</strong>
          <span>Tingkat kepuasan</span>
        </div>
        <div class="stat-item">
          <strong>150+</strong>
          <span>Tenaga kesehatan</span>
        </div>
      </div>
    </section>

    <section class="section" id="about">
      <div class="container split-layout">
        <div class="image-panel">
          <img
            src="https://images.unsplash.com/photo-1538108149393-fbbd81895907?auto=format&fit=crop&w=1000&q=80"
            alt="Dokter sedang memeriksa pasien" />
        </div>
        <div class="content-panel">
          <p class="eyebrow accent">Tentang kami</p>
          <h2>Komitmen kami adalah kesejahteraan Anda setiap hari.</h2>
          <p>
            RS Medika Sehat adalah rumah sakit modern yang menggabungkan teknologi
            mutakhir, pendekatan klinis yang tepat, serta layanan humanis untuk
            setiap pasien. Kami siap mendukung kebutuhan kesehatan keluarga Anda mulai
            dari pencegahan, diagnosis, hingga pemulihan.
          </p>
          <ul class="check-list">
            <li>Dokter spesialis dan perawat yang terampil</li>
            <li>Laboratorium dan radiologi modern</li>
            <li>Fasilitas rawat inap nyaman dan aman</li>
            <li>Pelayanan 24 jam untuk kebutuhan darurat</li>
          </ul>
        </div>
      </div>
    </section>

    <section class="section alt-bg" id="services">
      <div class="container">
        <div class="section-heading">
          <p class="eyebrow accent">Layanan unggulan</p>
          <h2>Solusi kesehatan komprehensif untuk Anda</h2>
        </div>

        <div class="service-grid">
          <article class="service-card">
            <div class="service-icon">🫀</div>
            <h3>Jantung & Pembuluh Darah</h3>
            <p>Diagnosis, terapi, dan perawatan jantung dengan pendekatan yang terintegrasi.</p>
          </article>
          <article class="service-card">
            <div class="service-icon">🧠</div>
            <h3>Neurologi</h3>
            <p>Perawatan neurologi untuk keluhan otak, saraf, dan sistem saraf pusat.</p>
          </article>
          <article class="service-card">
            <div class="service-icon">👶</div>
            <h3>Kesehatan Anak</h3>
            <p>Perawatan anak yang aman dan nyaman, mulai dari bayi hingga remaja.</p>
          </article>
          <article class="service-card">
            <div class="service-icon">🩺</div>
            <h3>Poli Umum</h3>
            <p>Pemeriksaan rutin, konsultasi, dan pengelolaan kesehatan harian keluarga.</p>
          </article>
          <article class="service-card">
            <div class="service-icon">🧪</div>
            <h3>Laboratorium</h3>
            <p>Uji laboratorium cepat, akurat, dan terstandar dengan hasil terjamin.</p>
          </article>
          <article class="service-card">
            <div class="service-icon">🚑</div>
            <h3>IGD 24 Jam</h3>
            <p>Tim darurat siap membantu dalam kondisi kritis dan situasi mendesak.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="section" id="doctors">
      <div class="container">
        <div class="section-heading">
          <p class="eyebrow accent">Tim dokter kami</p>
          <h2>Ahli yang siap membantu hidup Anda lebih sehat</h2>
        </div>

        <div class="doctor-grid">
          <article class="doctor-card">
            <img
              src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&w=700&q=80"
              alt="Dr. Amelia Putri" />
            <div class="doctor-info">
              <h3>Dr. Amelia Putri</h3>
              <p>Spesialis Jantung</p>
            </div>
          </article>
          <article class="doctor-card">
            <img
              src="https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&w=700&q=80"
              alt="Dr. Budi Santoso" />
            <div class="doctor-info">
              <h3>Dr. Budi Santoso</h3>
              <p>Spesialis Anak</p>
            </div>
          </article>
          <article class="doctor-card">
            <img
              src="https://images.unsplash.com/photo-1594824476967-48c8b964273f?auto=format&fit=crop&w=700&q=80"
              alt="Dr. Citra Anggraini" />
            <div class="doctor-info">
              <h3>Dr. Citra Anggraini</h3>
              <p>Spesialis Bedah</p>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="section alt-bg doctor-data-section" id="doctor-data">
      <div class="container">
        <div class="section-heading">
          <p class="eyebrow accent">Data dokter</p>
          <h2>Informasi jadwal dan spesialisasi dokter</h2>
        </div>

        <?php if (!$doctorLoadError && $doctors !== []): ?>
          <div class="doctor-filter" aria-label="Filter dokter">
            <button class="filter-btn active" type="button" data-filter="all">Semua</button>
            <?php foreach (array_unique(array_column($doctors, 'specialty')) as $specialty): ?>
              <button
                class="filter-btn"
                type="button"
                data-filter="<?= htmlspecialchars((string) $specialty, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) $specialty, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="doctor-data-grid">
          <?php if ($doctorLoadError): ?>
            <p class="doctor-list-message" role="alert">Daftar dokter belum dapat dimuat. Silakan coba lagi nanti.</p>
          <?php elseif ($doctors === []): ?>
            <p class="doctor-list-message" role="status">Belum ada data dokter.</p>
          <?php else: ?>
            <?php foreach ($doctors as $doctor): ?>
              <?php
              $nameParts = preg_split('/\s+/u', trim((string) $doctor['name']), -1, PREG_SPLIT_NO_EMPTY);
              $initials = '';
              foreach (array_slice($nameParts ?: [], 0, 2) as $namePart) {
                $initials .= mb_strtoupper(mb_substr($namePart, 0, 1, 'UTF-8'), 'UTF-8');
              }
              $specialty = (string) $doctor['specialty'];
              $name = (string) $doctor['name'];
              $status = (string) $doctor['status'];
              $statusLabel = $status === 'available' ? 'Tersedia' : 'Penuh';
              $escape = static fn(string $value): string => htmlspecialchars(
                $value,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
              );
              ?>
              <article class="doctor-data-card" data-specialty="<?= $escape($specialty) ?>">
                <div class="doctor-avatar"><?= $escape($initials) ?></div>
                <div>
                  <span class="doctor-role">Spesialis <?= $escape($specialty) ?></span>
                  <h3><?= $escape($name) ?></h3>
                  <p>RS Medika Sehat</p>
                </div>
                <ul>
                  <li>Hari: <?= $escape((string) $doctor['work_days']) ?></li>
                  <li>Jam: <?= $escape(substr((string) $doctor['start_time'], 0, 5)) ?> - <?= $escape(substr((string) $doctor['end_time'], 0, 5)) ?></li>
                  <li>Ruang: <?= $escape((string) $doctor['room']) ?></li>
                </ul>
                <span class="status <?= $escape($status) ?>"><?= $escape($statusLabel) ?></span>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section class="section testimonials">
      <div class="container">
        <div class="section-heading">
          <p class="eyebrow accent">Testimoni</p>
          <h2>Apa kata pasien kami</h2>
        </div>

        <div class="testimonial-grid">
          <blockquote>
            "Pelayanan sangat ramah dan dokter menjelaskan kondisi saya dengan jelas. Prosesnya cepat dan nyaman."
            <footer>— Rina, pasien rawat jalan</footer>
          </blockquote>
          <blockquote>
            "Fasilitasnya bersih dan modern. Tim medisnya sangat profesional dan sigap saat darurat."
            <footer>— Andi, pasien IGD</footer>
          </blockquote>
          <blockquote>
            "Saya puas dengan layanan kesehatan anak di sini. Proses konsultasi sangat mudah dan tenang."
            <footer>— Della, orang tua pasien</footer>
          </blockquote>
        </div>
      </div>
    </section>

    <section class="section appointment-section" id="appointment">
      <div class="container appointment-wrap">
        <div class="appointment-copy">
          <p class="eyebrow accent">Reservasi</p>
          <h2>Jadwalkan konsultasi sekarang</h2>
          <p>
            Tim kami siap membantu Anda menentukan jadwal paling cocok dengan kebutuhan kesehatan Anda.
          </p>
          <div class="contact-points">
            <div>
              <strong>Telepon</strong>
              <span>(021) 555-0123</span>
            </div>
            <div>
              <strong>Jam</strong>
              <span>Senin - Sabtu, 08.00 - 20.00</span>
            </div>
          </div>
        </div>

        <form class="appointment-form" id="appointmentForm" action="api/appointments.php" method="post">
          <label>
            Nama lengkap
            <input type="text" name="name" placeholder="Masukkan nama Anda" required />
          </label>
          <label>
            Email
            <input type="email" name="email" placeholder="Masukkan email Anda" required />
          </label>
          <label>
            Poli tujuan
            <select name="specialty" required>
              <option value="">Pilih poli</option>
              <option>Poli Umum</option>
              <option>Kesehatan Anak</option>
              <option>Jantung</option>
              <option>Neurologi</option>
              <option>Laboratorium</option>
            </select>
          </label>
          <label>
            Tanggal
            <input type="date" name="date" required />
          </label>
          <button type="submit" class="btn btn-primary full-width">Kirim Permintaan</button>
          <p class="form-message" aria-live="polite"></p>
        </form>
      </div>
    </section>
  </main>

  <footer class="site-footer" id="contact">
    <div class="container footer-grid">
      <div>
        <div class="brand footer-brand">
          <div class="brand-mark">✚</div>
          <div>
            <strong>RS Medika Sehat</strong>
            <span>Care for Life</span>
          </div>
        </div>
        <p>Memberikan pelayanan kesehatan yang aman, cepat, dan manusiawi untuk seluruh keluarga.</p>
      </div>

      <div>
        <h4>Kontak</h4>
        <ul>
          <li>Jl. Melati No. 45, Jakarta</li>
          <li>(021) 555-0123</li>
          <li>hello@rsmedikasehat.id</li>
        </ul>
      </div>

      <div>
        <h4>Jam operasional</h4>
        <ul>
          <li>Poliklinik: 08.00 - 20.00</li>
          <li>IGD: 24 jam</li>
          <li>Ambulan: Siap melayani</li>
        </ul>
      </div>
    </div>
    <div class="container copyright">© <span id="year"></span> RS Medika Sehat. Semua hak dilindungi.</div>
  </footer>

  <script src="js/pages/home.js"></script>
</body>

</html>