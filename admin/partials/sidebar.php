<?php

declare(strict_types=1);

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$currentScript = str_replace(
    '\\',
    '/',
    $_SERVER['SCRIPT_NAME'] ?? ''
);

// URL navigasi: definisikan terlebih dahulu
$adminUrl = '/rumah_sakit/admin/';
$doctorUrl = $adminUrl . 'dokter/';
$pharmacyUrl = $adminUrl . 'farmasi/';
$patientUrl = $adminUrl . 'pasien/';
$nurseUrl = $adminUrl . 'perawat/';
$homeUrl = '/rumah_sakit/index.php';

// Deteksi lokasi halaman
$isPatientDirectory = str_contains(
    $currentScript,
    '/admin/pasien/'
);

$isPharmacyDirectory = str_contains(
    $currentScript,
    '/admin/farmasi/'
);

$isDoctorDirectory = str_contains(
    $currentScript,
    '/admin/dokter/'
);

$isNurseDirectory = str_contains(
    $currentScript,
    '/admin/perawat/'
);

// Deteksi halaman Dokter
$isDoctorPage = $isDoctorDirectory && in_array(
    $currentPage,
    [
        'data_dokter.php',
        'jadwal_dokter.php',
        'tambah_dokter.php',
        'input_rekam_medis.php',
    ],
    true
);

// Deteksi halaman Perawat
$isNursePage = $isNurseDirectory && in_array(
    $currentPage,
    [
        'index.php',
        'data_pasien.php',
        'detail_pasien.php',
        'pemeriksaan.php',
        'catatan_keperawatan.php',
        'pemantauan_pasien.php',
        'laporan.php',
    ],
    true
);

// Deteksi halaman Farmasi
$isPharmacyPage = $isPharmacyDirectory && in_array(
    $currentPage,
    [
        'data_obat.php',
        'tambah_obat.php',
        'edit_obat.php',
        'transaksi_stok.php',
        'obat_kedaluwarsa.php',
        'laporan_farmasi.php',
    ],
    true
);

// Deteksi halaman Pasien
$isPatientPage = $isPatientDirectory && in_array(
    $currentPage,
    [
        'index.php',
        'tambah_pasien.php',
        'edit_pasien.php',
        'detail_pasien.php',
        'riwayat_kunjungan.php',
        'rekam_medis.php',
    ],
    true
);

// Deteksi halaman Rekam Medis
$isMedicalRecordPage = $isPatientDirectory
    && $currentPage === 'rekam_medis.php';

// Deteksi halaman Detail Pasien
$isPatientDetail = $isPatientDirectory
    && $currentPage === 'detail_pasien.php';

// Deteksi halaman Riwayat Kunjungan
$isVisitHistory = $isPatientDirectory
    && $currentPage === 'riwayat_kunjungan.php';

// Deteksi Dashboard Admin
$isDashboardPage = !$isPatientDirectory
    && !$isPharmacyDirectory
    && !$isDoctorDirectory
    && !$isNurseDirectory
    && $currentPage === 'index.php';

// Deteksi halaman Pengaturan Akun
$isSettingsPage = str_contains(
    $currentScript,
    '/admin/pengaturan/'
);

?>

<aside class="admin-sidebar" id="adminSidebar">

    <p class="admin-sidebar-label">Menu Admin</p>

    <nav class="admin-sidebar-nav" aria-label="Navigasi dashboard admin">

        <!-- DASHBOARD -->
        <a
            href="<?= $adminUrl ?>index.php#dashboard"
            class="admin-nav-link <?= $isDashboardPage ? 'active' : '' ?>"
            <?= $isDashboardPage ? 'aria-current="page"' : '' ?>>

            <span class="admin-nav-icon">⌂</span>
            <span>Dashboard</span>
        </a>

        <!-- DOKTER -->
        <div
            class="admin-nav-group <?= $isDoctorPage ? 'open' : '' ?>"
            data-nav-group>

            <button
                type="button"
                class="admin-nav-parent <?= $isDoctorPage ? 'active' : '' ?>"
                aria-expanded="<?= $isDoctorPage ? 'true' : 'false' ?>"
                aria-controls="doctorSubmenu"
                data-submenu-toggle>

                <span class="admin-nav-icon">✚</span>
                <span class="admin-nav-text">Dokter</span>
                <span class="admin-nav-arrow">⌄</span>
            </button>

            <div class="admin-submenu" id="doctorSubmenu">

                <a
                    href="<?= $doctorUrl ?>data_dokter.php"
                    class="admin-submenu-link <?= $currentPage === 'data_dokter.php' && $isDoctorDirectory ? 'active' : '' ?>"
                    <?= $currentPage === 'data_dokter.php' && $isDoctorDirectory ? 'aria-current="page"' : '' ?>>
                    Data Dokter
                </a>

                <a
                    href="<?= $doctorUrl ?>jadwal_dokter.php"
                    class="admin-submenu-link <?= $currentPage === 'jadwal_dokter.php' && $isDoctorDirectory ? 'active' : '' ?>"
                    <?= $currentPage === 'jadwal_dokter.php' && $isDoctorDirectory ? 'aria-current="page"' : '' ?>>
                    Jadwal Dokter
                </a>

                <a
                    href="<?= $doctorUrl ?>tambah_dokter.php"
                    class="admin-submenu-link <?= $currentPage === 'tambah_dokter.php' && $isDoctorDirectory ? 'active' : '' ?>"
                    <?= $currentPage === 'tambah_dokter.php' && $isDoctorDirectory ? 'aria-current="page"' : '' ?>>
                    Tambah Dokter
                </a>

                <a
                    href="<?= $doctorUrl ?>input_rekam_medis.php"
                    class="admin-submenu-link <?= $isDoctorDirectory && $currentPage === 'input_rekam_medis.php' ? 'active' : '' ?>"
                    <?= $isDoctorDirectory && $currentPage === 'input_rekam_medis.php' ? 'aria-current="page"' : '' ?>>
                    Input Rekam Medis
                </a>

            </div>
        </div>

        <!-- FARMASI -->
        <div
            class="admin-nav-group <?= $isPharmacyPage ? 'open' : '' ?>"
            data-nav-group>

            <button
                type="button"
                class="admin-nav-parent <?= $isPharmacyPage ? 'active' : '' ?>"
                aria-expanded="<?= $isPharmacyPage ? 'true' : 'false' ?>"
                aria-controls="pharmacySubmenu"
                data-submenu-toggle>

                <span class="admin-nav-icon">⚕</span>
                <span class="admin-nav-text">Farmasi</span>
                <span class="admin-nav-arrow">⌄</span>
            </button>

            <div class="admin-submenu" id="pharmacySubmenu">

                <a
                    href="<?= $pharmacyUrl ?>data_obat.php"
                    class="admin-submenu-link <?= $currentPage === 'data_obat.php' && $isPharmacyDirectory ? 'active' : '' ?>"
                    <?= $currentPage === 'data_obat.php' && $isPharmacyDirectory ? 'aria-current="page"' : '' ?>>
                    Data Obat
                </a>

                <a
                    href="<?= $pharmacyUrl ?>transaksi_stok.php?jenis=masuk"
                    class="admin-submenu-link <?= $currentPage === 'transaksi_stok.php' && ($_GET['jenis'] ?? 'masuk') === 'masuk' && $isPharmacyDirectory ? 'active' : '' ?>"
                    <?= $currentPage === 'transaksi_stok.php' && ($_GET['jenis'] ?? 'masuk') === 'masuk' && $isPharmacyDirectory ? 'aria-current="page"' : '' ?>>
                    Stok Masuk
                </a>

                <a
                    href="<?= $pharmacyUrl ?>transaksi_stok.php?jenis=keluar"
                    class="admin-submenu-link <?= $currentPage === 'transaksi_stok.php' && ($_GET['jenis'] ?? '') === 'keluar' && $isPharmacyDirectory ? 'active' : '' ?>"
                    <?= $currentPage === 'transaksi_stok.php' && ($_GET['jenis'] ?? '') === 'keluar' && $isPharmacyDirectory ? 'aria-current="page"' : '' ?>>
                    Stok Keluar
                </a>

                <a
                    href="<?= $pharmacyUrl ?>obat_kedaluwarsa.php"
                    class="admin-submenu-link <?= $currentPage === 'obat_kedaluwarsa.php' && $isPharmacyDirectory ? 'active' : '' ?>"
                    <?= $currentPage === 'obat_kedaluwarsa.php' && $isPharmacyDirectory ? 'aria-current="page"' : '' ?>>
                    Obat Kedaluwarsa
                </a>

                <a
                    href="<?= $pharmacyUrl ?>laporan_farmasi.php"
                    class="admin-submenu-link <?= $currentPage === 'laporan_farmasi.php' && $isPharmacyDirectory ? 'active' : '' ?>"
                    <?= $currentPage === 'laporan_farmasi.php' && $isPharmacyDirectory ? 'aria-current="page"' : '' ?>>
                    Laporan Farmasi
                </a>

            </div>
        </div>

        <!-- PERAWAT -->
        <div
            class="admin-nav-group <?= $isNursePage ? 'open' : '' ?>"
            data-nav-group>

            <button
                type="button"
                class="admin-nav-parent <?= $isNursePage ? 'active' : '' ?>"
                aria-expanded="<?= $isNursePage ? 'true' : 'false' ?>"
                aria-controls="nurseSubmenu"
                data-submenu-toggle>

                <span class="admin-nav-icon">✚</span>
                <span class="admin-nav-text">Perawat</span>
                <span class="admin-nav-arrow">⌄</span>
            </button>

            <div class="admin-submenu" id="nurseSubmenu">

                <a
                    href="<?= $nurseUrl ?>index.php"
                    class="admin-submenu-link <?= $isNurseDirectory && $currentPage === 'index.php' ? 'active' : '' ?>"
                    <?= $isNurseDirectory && $currentPage === 'index.php' ? 'aria-current="page"' : '' ?>>
                    Dashboard Perawat
                </a>

                <a
                    href="<?= $nurseUrl ?>data_pasien.php"
                    class="admin-submenu-link <?= $isNurseDirectory && $currentPage === 'data_pasien.php' ? 'active' : '' ?>"
                    <?= $isNurseDirectory && $currentPage === 'data_pasien.php' ? 'aria-current="page"' : '' ?>>
                    Data Pasien
                </a>

                <a
                    href="<?= $nurseUrl ?>pemeriksaan.php"
                    class="admin-submenu-link <?= $isNurseDirectory && $currentPage === 'pemeriksaan.php' ? 'active' : '' ?>"
                    <?= $isNurseDirectory && $currentPage === 'pemeriksaan.php' ? 'aria-current="page"' : '' ?>>
                    Pemeriksaan Pasien
                </a>

                <a
                    href="<?= $nurseUrl ?>catatan_keperawatan.php"
                    class="admin-submenu-link <?= $isNurseDirectory && $currentPage === 'catatan_keperawatan.php' ? 'active' : '' ?>"
                    <?= $isNurseDirectory && $currentPage === 'catatan_keperawatan.php' ? 'aria-current="page"' : '' ?>>
                    Catatan Keperawatan
                </a>

                <a
                    href="<?= $nurseUrl ?>pemantauan_pasien.php"
                    class="admin-submenu-link <?= $isNurseDirectory && $currentPage === 'pemantauan_pasien.php' ? 'active' : '' ?>"
                    <?= $isNurseDirectory && $currentPage === 'pemantauan_pasien.php' ? 'aria-current="page"' : '' ?>>
                    Pemantauan Pasien
                </a>

                <a
                    href="<?= $nurseUrl ?>laporan.php"
                    class="admin-submenu-link <?= $isNurseDirectory && $currentPage === 'laporan.php' ? 'active' : '' ?>"
                    <?= $isNurseDirectory && $currentPage === 'laporan.php' ? 'aria-current="page"' : '' ?>>
                    Laporan Keperawatan
                </a>

                <a
                    href="<?= $patientUrl ?>riwayat_kunjungan.php"
                    class="admin-submenu-link <?= $isVisitHistory ? 'active' : '' ?>"
                    <?= $isVisitHistory ? 'aria-current="page"' : '' ?>>
                    Riwayat Kunjungan
                </a>

                <a
                    href="<?= $patientUrl ?>rekam_medis.php"
                    class="admin-submenu-link <?= $isMedicalRecordPage ? 'active' : '' ?>"
                    <?= $isMedicalRecordPage ? 'aria-current="page"' : '' ?>>
                    Rekam Medis
                </a>


            </div>
        </div>


        <!-- PENGATURAN AKUN -->
        <a
            href="/rumah_sakit/admin/pengaturan/akun.php"
            class="admin-nav-link <?= $isSettingsPage ? 'active' : '' ?>"
            <?= $isSettingsPage ? 'aria-current="page"' : '' ?>>
            <span class="admin-nav-icon">⚙</span>
            <span>Pengaturan Akun</span>
        </a>


    </nav>

    <a class="admin-sidebar-home" href="<?= $homeUrl ?>">
        Kembali ke beranda
    </a>

</aside>