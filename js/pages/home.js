document.addEventListener("DOMContentLoaded", () => {
  // ==============================
  // TAHUN OTOMATIS
  // ==============================
  const yearElement = document.getElementById("year");

  if (yearElement) {
    yearElement.textContent = new Date().getFullYear();
  }

  // ==============================
  // FILTER DOKTER
  // ==============================
  const filterContainer = document.querySelector(".doctor-filter");
  const doctorCards = document.querySelectorAll(".doctor-data-card");

  filterContainer?.querySelectorAll(".filter-btn").forEach((button) => {
    button.addEventListener("click", () => {
      const selectedFilter = button.dataset.filter;

      filterContainer
        .querySelectorAll(".filter-btn")
        .forEach((filterButton) => {
          filterButton.classList.toggle("active", filterButton === button);
        });

      doctorCards.forEach((card) => {
        const shouldShow =
          selectedFilter === "all" || card.dataset.specialty === selectedFilter;

        card.classList.toggle("hidden", !shouldShow);
      });
    });
  });

  // ==============================
  // FORMULIR JANJI TEMU
  // ==============================
  const form = document.getElementById("appointmentForm");
  const formMessage = document.querySelector(".form-message");

  if (form instanceof HTMLFormElement) {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();

      const submitButton = form.querySelector('button[type="submit"]');

      if (submitButton) {
        submitButton.disabled = true;
      }

      if (formMessage) {
        formMessage.textContent = "Mengirim permintaan...";
      }

      try {
        const response = await fetch(form.action, {
          method: form.method,
          body: new FormData(form),
          headers: {
            Accept: "application/json",
          },
        });

        const result = await response.json();

        if (!response.ok) {
          throw new Error(result.message || "Permintaan belum dapat diproses.");
        }

        if (formMessage) {
          formMessage.textContent =
            result.message || "Permintaan berhasil dikirim.";
        }

        form.reset();
      } catch (error) {
        if (formMessage) {
          formMessage.textContent =
            error instanceof Error
              ? error.message
              : "Terjadi kesalahan saat mengirim permintaan. Silakan coba lagi.";
        }
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
        }
      }
    });
  }

  // ==============================
  // DROPDOWN LOGIN
  // Hanya terbuka saat diklik
  // ==============================
  const dropdown = document.querySelector(".login-dropdown");
  const toggle = document.querySelector(".login-toggle");

  if (dropdown && toggle) {
    const closeDropdown = () => {
      dropdown.classList.remove("open");
      toggle.setAttribute("aria-expanded", "false");
    };

    toggle.addEventListener("click", (event) => {
      event.stopPropagation();

      const isOpen = dropdown.classList.toggle("open");

      toggle.setAttribute("aria-expanded", String(isOpen));
    });

    document.addEventListener("click", (event) => {
      if (!dropdown.contains(event.target)) {
        closeDropdown();
      }
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        closeDropdown();
        toggle.focus();
      }
    });
  }
});
