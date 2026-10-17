<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$isDoctorPage = in_array(
    $currentPage,
    ['data_dokter.php', 'jadwal_dokter.php', 'tambah_dokter.php'],
    true
);
$sidebarPath = $isDoctorPage ? '' : 'dokter/';
$dashboardPath = $isDoctorPage ? '../index.php' : 'index.php';
$homePath = $isDoctorPage ? '../../index.php' : '../index.php';
?>

<aside class="admin-sidebar" id="adminSidebar">
    <p class="admin-sidebar-label">Menu Admin</p>

    <nav class="admin-sidebar-nav" aria-label="Navigasi dashboard admin">

        <a href="<?= $dashboardPath ?>#dashboard"
            class="admin-nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>"
            <?= $currentPage === 'index.php' ? 'aria-current="page"' : '' ?>>
            <span class="admin-nav-icon">⌂</span>
            <span>Dashboard</span>
        </a>

        <div class="admin-nav-group open" data-nav-group>

            <button
                type="button"
                class="admin-nav-parent <?= $isDoctorPage ? 'active' : '' ?>"
                aria-expanded="true"
                aria-controls="doctorSubmenu"
                data-submenu-toggle>
                <span class="admin-nav-icon">✚</span>
                <span class="admin-nav-text">Dokter</span>
                <span class="admin-nav-arrow">⌄</span>
            </button>

            <div class="admin-submenu" id="doctorSubmenu">
                <a href="<?= $sidebarPath ?>data_dokter.php"
                    class="admin-submenu-link <?= $currentPage === 'data_dokter.php' ? 'active' : '' ?>"
                    <?= $currentPage === 'data_dokter.php' ? 'aria-current="page"' : '' ?>>
                    Data Dokter
                </a>

                <a href="<?= $sidebarPath ?>jadwal_dokter.php"
                    class="admin-submenu-link <?= $currentPage === 'jadwal_dokter.php' ? 'active' : '' ?>"
                    <?= $currentPage === 'jadwal_dokter.php' ? 'aria-current="page"' : '' ?>>
                    Jadwal Dokter
                </a>

                <a href="<?= $sidebarPath ?>tambah_dokter.php"
                    class="admin-submenu-link <?= $currentPage === 'tambah_dokter.php' ? 'active' : '' ?>"
                    <?= $currentPage === 'tambah_dokter.php' ? 'aria-current="page"' : '' ?>>
                    Tambah Dokter
                </a>
            </div>
        </div>

    </nav>

    <a class="admin-sidebar-home" href="<?= $homePath ?>">
        Kembali ke beranda
    </a>
</aside>