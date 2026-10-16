document.addEventListener("DOMContentLoaded", () => {
  const adminPage = document.querySelector(".admin-page");
  const sidebar = document.getElementById("adminSidebar");
  const menuToggle = document.querySelector("[data-sidebar-toggle]");

  // SIDEBAR MOBILE
  if (adminPage && sidebar && menuToggle) {
    const closeSidebar = () => {
      adminPage.classList.remove("sidebar-open");
      menuToggle.setAttribute("aria-expanded", "false");
    };

    menuToggle.addEventListener("click", () => {
      const isOpen = adminPage.classList.toggle("sidebar-open");

      menuToggle.setAttribute("aria-expanded", String(isOpen));
    });

    // Tutup sidebar saat pengguna memilih menu
    sidebar.querySelectorAll(".admin-sidebar-nav a").forEach((link) => {
      link.addEventListener("click", () => {
        sidebar.querySelectorAll(".admin-sidebar-nav a").forEach((item) => {
          item.classList.remove("active");
          item.removeAttribute("aria-current");
        });

        link.classList.add("active");
        link.setAttribute("aria-current", "page");

        closeSidebar();
      });
    });

    // Tutup jika klik di luar sidebar dan tombol Menu
    document.addEventListener("click", (event) => {
      if (
        adminPage.classList.contains("sidebar-open") &&
        !sidebar.contains(event.target) &&
        !menuToggle.contains(event.target)
      ) {
        closeSidebar();
      }
    });

    // Tutup dengan Escape
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        closeSidebar();
        menuToggle.focus();
      }
    });

    // Reset tampilan ketika kembali ke desktop
    window.addEventListener("resize", () => {
      if (window.innerWidth > 760) {
        closeSidebar();
      }
    });
  }

  // KONFIRMASI HAPUS DOKTER
  document.querySelectorAll("[data-delete-doctor]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      const confirmed = window.confirm(
        "Apakah kamu yakin ingin menghapus data dokter ini?",
      );

      if (!confirmed) {
        event.preventDefault();
      }
    });
  });

  // SUBMENU SIDEBAR
  const navGroups = document.querySelectorAll("[data-nav-group]");

  navGroups.forEach((group) => {
    const button = group.querySelector("[data-submenu-toggle]");

    if (!button) return;

    // Submenu Dokter terbuka otomatis pada halaman dashboard dokter.
    group.classList.add("open");
    button.setAttribute("aria-expanded", "true");

    button.addEventListener("click", () => {
      const isOpen = group.classList.toggle("open");

      button.setAttribute("aria-expanded", String(isOpen));
    });
  });

  // Tandai submenu sesuai bagian halaman yang dipilih.
  const submenuLinks = document.querySelectorAll(".admin-submenu-link");

  function updateActiveSubmenu() {
    const currentHash = window.location.hash;

    submenuLinks.forEach((link) => {
      link.classList.remove("active");
    });

    const matchingLink = Array.from(submenuLinks).find((link) => {
      return currentHash && link.hash === currentHash;
    });

    if (matchingLink) {
      matchingLink.classList.add("active");
    }
  }

  updateActiveSubmenu();
  window.addEventListener("hashchange", updateActiveSubmenu);
});
