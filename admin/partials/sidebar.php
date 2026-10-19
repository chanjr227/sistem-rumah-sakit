<?php

declare(strict_types=1);

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

// Deteksi halaman Dokter
$isDoctorPage = in_array(
    $currentPage,
    [
        'data_dokter.php',
        'jadwal_dokter.php',
        'tambah_dokter.php'
    ],
    true
);

// Deteksi halaman Farmasi
$isPharmacyPage = in_array(
    $currentPage,
    [
        'data_obat.php',
        'tambah_obat.php',
        'edit_obat.php',
        'transaksi_stok.php',
        'obat_kedaluwarsa.php',
        'laporan_farmasi.php'
    ],
    true
);

// Deteksi dashboard
$isDashboardPage = $currentPage === 'index.php'
    && !$isDoctorPage
    && !$isPharmacyPage;

// URL absolut berdasarkan folder proyek
$adminUrl = '/rumah_sakit/admin/';
$doctorUrl = $adminUrl . 'dokter/';
$pharmacyUrl = $adminUrl . 'farmasi/';
$homeUrl = '/rumah_sakit/index.php';

?>

<aside class="admin-sidebar" id="adminSidebar">

    <p class="admin-sidebar-label">Menu Admin</p>

    <nav
        class="admin-sidebar-nav"
        aria-label="Navigasi dashboard admin">

        <!-- DASHBOARD -->
        <a
            href="<?= $adminUrl ?>index.php#dashboard"
            class="admin-nav-link <?= $isDashboardPage ? 'active' : '' ?>"
            <?= $isDashboardPage ? 'aria-current="page"' : '' ?>>

            <span class="admin-nav-icon">⌂</span>
            <span>Dashboard</span>
        </a>

        <!-- MENU DOKTER -->
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
                    class="admin-submenu-link <?= $currentPage === 'data_dokter.php' ? 'active' : '' ?>"
                    <?= $currentPage === 'data_dokter.php' ? 'aria-current="page"' : '' ?>>
                    Data Dokter
                </a>

                <a
                    href="<?= $doctorUrl ?>jadwal_dokter.php"
                    class="admin-submenu-link <?= $currentPage === 'jadwal_dokter.php' ? 'active' : '' ?>"
                    <?= $currentPage === 'jadwal_dokter.php' ? 'aria-current="page"' : '' ?>>
                    Jadwal Dokter
                </a>

                <a
                    href="<?= $doctorUrl ?>tambah_dokter.php"
                    class="admin-submenu-link <?= $currentPage === 'tambah_dokter.php' ? 'active' : '' ?>"
                    <?= $currentPage === 'tambah_dokter.php' ? 'aria-current="page"' : '' ?>>
                    Tambah Dokter
                </a>

            </div>
        </div>

        <!-- MENU FARMASI -->
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
                    class="admin-submenu-link <?= $currentPage === 'data_obat.php' ? 'active' : '' ?>"
                    <?= $currentPage === 'data_obat.php' ? 'aria-current="page"' : '' ?>>
                    Data Obat
                </a>
                <a
                    href="<?= $pharmacyUrl ?>transaksi_stok.php?jenis=masuk"
                    class="admin-submenu-link <?= $currentPage === 'transaksi_stok.php' && ($_GET['jenis'] ?? 'masuk') === 'masuk' ? 'active' : '' ?>">
                    Stok Masuk
                </a>

                <a
                    href="<?= $pharmacyUrl ?>transaksi_stok.php?jenis=keluar"
                    class="admin-submenu-link <?= $currentPage === 'transaksi_stok.php' && ($_GET['jenis'] ?? '') === 'keluar' ? 'active' : '' ?>">
                    Stok Keluar
                </a>

                <a
                    href="<?= $pharmacyUrl ?>obat_kedaluwarsa.php"
                    class="admin-submenu-link <?= $currentPage === 'obat_kedaluwarsa.php' ? 'active' : '' ?>"
                    <?= $currentPage === 'obat_kedaluwarsa.php' ? 'aria-current="page"' : '' ?>>
                    Obat Kedaluwarsa
                </a>

                <a
                    href="<?= $pharmacyUrl ?>laporan_farmasi.php"
                    class="admin-submenu-link <?= $currentPage === 'laporan_farmasi.php' ? 'active' : '' ?>"
                    <?= $currentPage === 'laporan_farmasi.php' ? 'aria-current="page"' : '' ?>>
                    Laporan Farmasi
                </a>

            </div>
        </div>

    </nav>

    <!-- KEMBALI KE BERANDA -->
    <a
        class="admin-sidebar-home"
        href="<?= $homeUrl ?>">
        Kembali ke beranda
    </a>

</aside>