<?php

declare(strict_types=1);

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$currentScript = str_replace(
    '\\',
    '/',
    $_SERVER['SCRIPT_NAME'] ?? ''
);

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

// Deteksi halaman Dokter
$isDoctorPage = $isDoctorDirectory && in_array(
    $currentPage,
    [
        'data_dokter.php',
        'jadwal_dokter.php',
        'tambah_dokter.php',
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
    ],
    true
);

// Deteksi halaman Detail Pasien
$isPatientDetail = $isPatientDirectory
    && $currentPage === 'detail_pasien.php';

// Deteksi halaman Riwayat Kunjungan
$isVisitHistory = $isPatientDirectory
    && $currentPage === 'riwayat_kunjungan.php';

// Deteksi Dashboard
$isDashboardPage = !$isPatientDirectory
    && !$isPharmacyDirectory
    && !$isDoctorDirectory
    && $currentPage === 'index.php';

// URL navigasi
$adminUrl = '/rumah_sakit/admin/';
$doctorUrl = $adminUrl . 'dokter/';
$pharmacyUrl = $adminUrl . 'farmasi/';
$patientUrl = $adminUrl . 'pasien/';
$homeUrl = '/rumah_sakit/index.php';

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

        <!-- PASIEN -->
        <div
            class="admin-nav-group <?= $isPatientPage ? 'open' : '' ?>"
            data-nav-group>

            <button
                type="button"
                class="admin-nav-parent <?= $isPatientPage ? 'active' : '' ?>"
                aria-expanded="<?= $isPatientPage ? 'true' : 'false' ?>"
                aria-controls="patientSubmenu"
                data-submenu-toggle>

                <span class="admin-nav-icon">♙</span>
                <span class="admin-nav-text">Pasien</span>
                <span class="admin-nav-arrow">⌄</span>
            </button>

            <div class="admin-submenu" id="patientSubmenu">

                <a
                    href="<?= $patientUrl ?>index.php"
                    class="admin-submenu-link <?= $isPatientPage && $currentPage === 'index.php' ? 'active' : '' ?>"
                    <?= $isPatientPage && $currentPage === 'index.php' ? 'aria-current="page"' : '' ?>>
                    Data Pasien
                </a>

                <a
                    href="<?= $patientUrl ?>riwayat_kunjungan.php"
                    class="admin-submenu-link <?= $isVisitHistory ? 'active' : '' ?>"
                    <?= $isVisitHistory ? 'aria-current="page"' : '' ?>>
                    Riwayat Kunjungan
                </a>

            </div>
        </div>

    </nav>

    <a class="admin-sidebar-home" href="<?= $homeUrl ?>">
        Kembali ke beranda
    </a>

</aside>