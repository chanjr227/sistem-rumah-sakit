<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$isDoctorPage = in_array(
    $currentPage,
    ['index.php', 'tambah_dokter.php'],
    true
);
?>

<aside class="admin-sidebar" id="adminSidebar">
    <p class="admin-sidebar-label">Menu Admin</p>

    <nav class="admin-sidebar-nav" aria-label="Navigasi dashboard admin">
        <a
            href="index.php#dashboard"
            class="admin-nav-link <?= $currentPage === 'index.php' && !isset($_GET['edit']) ? 'active' : '' ?>">
            <span class="admin-nav-icon">⌂</span>
            <span>Dashboard</span>
        </a>

        <div class="admin-nav-group <?= $isDoctorPage ? 'open' : '' ?>" data-nav-group>
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
                    href="index.php#doctor-list"
                    class="admin-submenu-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">
                    Daftar Dokter
                </a>

                <a
                    href="tambah_dokter.php"
                    class="admin-submenu-link <?= $currentPage === 'tambah_dokter.php' ? 'active' : '' ?>">
                    Tambah Dokter
                </a>

                <a
                    href="index.php#doctor-list"
                    class="admin-submenu-link">
                    Jadwal Praktik
                </a>
            </div>
        </div>
    </nav>

    <a class="admin-sidebar-home" href="../index.php">
        Kembali ke beranda
    </a>
</aside>