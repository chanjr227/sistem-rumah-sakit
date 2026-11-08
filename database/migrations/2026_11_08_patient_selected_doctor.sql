USE rumah_sakit;

ALTER TABLE appointments
  ADD COLUMN doctor_id BIGINT UNSIGNED NULL AFTER patient_id,
  ADD KEY idx_appointments_doctor_id (doctor_id),
  ADD CONSTRAINT fk_appointments_doctor
    FOREIGN KEY (doctor_id) REFERENCES doctors (id)
    ON DELETE SET NULL;

UPDATE appointments a
INNER JOIN doctors d
  ON d.specialty = a.specialty
LEFT JOIN doctors other
  ON other.specialty = a.specialty
  AND other.id <> d.id
SET a.doctor_id = d.id
WHERE a.doctor_id IS NULL
  AND other.id IS NULL;
