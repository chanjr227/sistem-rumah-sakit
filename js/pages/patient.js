document.addEventListener("DOMContentLoaded", () => {
  const sidebarToggle = document.querySelector("[data-sidebar-toggle]");
  const sidebar = document.getElementById("patientSidebar");

  if (!sidebarToggle || !sidebar) {
    return;
  }

  sidebarToggle.addEventListener("click", () => {
    const isExpanded = sidebarToggle.getAttribute("aria-expanded") === "true";
    sidebarToggle.setAttribute("aria-expanded", String(!isExpanded));
    sidebar.classList.toggle("is-open", !isExpanded);
  });
});
