document.addEventListener("DOMContentLoaded", () => {
  const yearElement = document.getElementById("year");
  if (yearElement) {
    yearElement.textContent = new Date().getFullYear();
  }

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

  const form = document.getElementById("appointmentForm");
  const formMessage = document.querySelector(".form-message");

  if (!form) {
    return;
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (!(form instanceof HTMLFormElement)) {
      return;
    }

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
        headers: { Accept: "application/json" },
      });
      const result = await response.json();

      if (!response.ok) {
        throw new Error(result.message || "Permintaan belum dapat diproses.");
      }

      if (formMessage) {
        formMessage.textContent = result.message;
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
});

document.addEventListener("DOMContentLoaded", () => {
  const dropdown = document.querySelector(".login-dropdown");
  const toggle = document.querySelector(".login-toggle");

  if (!dropdown || !toggle) return;

  toggle.addEventListener("click", () => {
    const isOpen = dropdown.classList.toggle("open");
    toggle.setAttribute("aria-expanded", String(isOpen));
  });

  document.addEventListener("click", (event) => {
    if (!dropdown.contains(event.target)) {
      dropdown.classList.remove("open");
      toggle.setAttribute("aria-expanded", "false");
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      dropdown.classList.remove("open");
      toggle.setAttribute("aria-expanded", "false");
      toggle.focus();
    }
  });
});
