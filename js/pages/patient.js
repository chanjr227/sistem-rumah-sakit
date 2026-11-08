document.addEventListener("DOMContentLoaded", () => {
  const appointmentForm = document.querySelector(
    'form[action="dashboard.php?menu=booking"]',
  );
  const doctorSelect = appointmentForm?.querySelector(
    'select[name="doctor_id"]',
  );
  const appointmentDate = appointmentForm?.querySelector(
    'input[name="visit_date"]',
  );

  if (
    doctorSelect instanceof HTMLSelectElement &&
    appointmentDate instanceof HTMLInputElement
  ) {
    const validateAppointmentDate = () => {
      const selectedDoctor = doctorSelect.selectedOptions[0];
      const workDays = selectedDoctor?.dataset.workdays ?? "";
      const selectedDate = appointmentDate.value;

      if (!selectedDate) {
        appointmentDate.setCustomValidity("");
        return;
      }

      if (!workDays) {
        appointmentDate.setCustomValidity(
          "Jadwal hari praktik dokter belum diatur. Hubungi rumah sakit.",
        );
        return;
      }

      const date = new Date(`${selectedDate}T12:00:00`);
      const weekday = date.getDay() === 0 ? 7 : date.getDay();
      const allowedDays = workDays.split(",").map(Number);

      appointmentDate.setCustomValidity(
        allowedDays.includes(weekday)
          ? ""
          : `Dokter praktik pada hari: ${selectedDoctor.textContent.trim()}. Pilih tanggal sesuai jadwal.`,
      );
    };

    doctorSelect.addEventListener("change", validateAppointmentDate);
    appointmentDate.addEventListener("change", validateAppointmentDate);
    appointmentForm.addEventListener("submit", (event) => {
      validateAppointmentDate();

      if (!appointmentDate.checkValidity()) {
        event.preventDefault();
        appointmentDate.reportValidity();
      }
    });
  }

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
