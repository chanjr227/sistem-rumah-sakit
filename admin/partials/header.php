<?php
require_once __DIR__ . '/../auth.php';
$csrfToken = adminCsrfToken();
$logoutAction = basename($_SERVER['PHP_SELF'] ?? '') === 'tambah_dokter.php'
    ? '../logout.php'
    : 'logout.php';
?>

<header class="admin-header">
    <a class="admin-brand" href="index.php">
        RS Medika Sehat <span>Dashboard Admin</span>
    </a>

    <div class="admin-header-actions">
        <button
            class="admin-menu-toggle"
            type="button"
            aria-expanded="false"
            aria-controls="adminSidebar"
            data-sidebar-toggle>
            Menu
        </button>

        <a class="admin-back-link" href="../index.php">
            Lihat Beranda
        </a>

        <form method="post" action="<?= $logoutAction ?>">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapeHtml($csrfToken) ?>" />
            <button class="btn btn-secondary" type="submit">
                Keluar
            </button>
        </form>
    </div>
</header>