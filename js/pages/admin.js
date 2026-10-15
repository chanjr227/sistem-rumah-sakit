document.addEventListener("DOMContentLoaded", () => {
  const sidebarToggle = document.querySelector("[data-sidebar-toggle]");
  const sidebar = document.getElementById("adminSidebar");

  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener("click", () => {
      const isExpanded = sidebarToggle.getAttribute("aria-expanded") === "true";
      sidebarToggle.setAttribute("aria-expanded", String(!isExpanded));
      sidebar.classList.toggle("is-open", !isExpanded);
    });
  }

  document.querySelectorAll("[data-delete-doctor]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      if (!window.confirm("Yakin ingin menghapus data dokter ini?")) {
        event.preventDefault();
      }
    });
  });
});
