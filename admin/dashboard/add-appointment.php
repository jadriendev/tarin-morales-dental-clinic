<?php
ob_start();

require_once __DIR__ . '/../config.php';

$page_title = "New Appointment";
$header_title = "Schedule Appointment";

include __DIR__ . '/includes/header.php';

$success_message = '';
$error_message = '';

$admin_id = $_SESSION['admin_id'] ?? null;

$patient_id       = $_POST['patient_id'] ?? '';
$dentist_id       = $_POST['dentist_id'] ?? '';
$appointment_date = $_POST['appointment_date'] ?? '';
$appointment_time = $_POST['appointment_time'] ?? '';
$procedure_name   = $_POST['procedure_name'] ?? '';
$reason           = $_POST['reason'] ?? '';

try {

    $stmtPatients = $pdo->query("
        SELECT
            p.patient_id,
            CONCAT(
                p.first_name,
                ' ',
                COALESCE(CONCAT(p.middle_name, ' '), ''),
                p.last_name
            ) AS full_name
        FROM tbl_patients p
        INNER JOIN tbl_users u
            ON p.user_id = u.user_id
        WHERE u.status = 'active'
        ORDER BY p.last_name ASC, p.first_name ASC
    ");

    $patients = $stmtPatients->fetchAll(PDO::FETCH_ASSOC);

    $stmtDentists = $pdo->query("
        SELECT
            dentist_id,
            CONCAT('Dr. ', first_name, ' ', last_name) AS full_name
        FROM tbl_dentists
        WHERE status = 'active'
        ORDER BY last_name ASC, first_name ASC
    ");

    $dentists = $stmtDentists->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log("Database Error (Fetch Dropdowns): " . $e->getMessage());

    $error_message = "Unable to load patients and dentists. Please try again later.";

    $patients = [];
    $dentists = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $patient_id       = trim($patient_id);
    $dentist_id       = trim($dentist_id);
    $appointment_date = trim($appointment_date);
    $appointment_time = trim($appointment_time);
    $procedure_name   = trim($procedure_name);
    $reason           = trim($reason);

    if (!$admin_id) {

        $error_message = "Admin session not found. Please log in again.";

    } elseif (
        empty($patient_id) ||
        empty($dentist_id) ||
        empty($appointment_date) ||
        empty($appointment_time) ||
        empty($procedure_name)
    ) {

        $error_message = "Please fill in all required fields (Patient, Dentist, Date, Time, and Procedure).";

    } else {

        try {

            $adminCheck = $pdo->prepare("
                SELECT admin_id
                FROM tbl_admins
                WHERE admin_id = :admin_id
                  AND status = 'active'
                LIMIT 1
            ");

            $adminCheck->execute([
                ':admin_id' => $admin_id
            ]);

            if (!$adminCheck->fetch()) {
                throw new Exception("The logged-in admin account is not available.");
            }

            $patientCheck = $pdo->prepare("
                SELECT p.patient_id
                FROM tbl_patients p
                INNER JOIN tbl_users u
                    ON p.user_id = u.user_id
                WHERE p.patient_id = :patient_id
                  AND u.status = 'active'
                LIMIT 1
            ");

            $patientCheck->execute([
                ':patient_id' => $patient_id
            ]);

            if (!$patientCheck->fetch()) {
                throw new Exception("The selected patient does not exist or is inactive.");
            }

            $dentistCheck = $pdo->prepare("
                SELECT dentist_id
                FROM tbl_dentists
                WHERE dentist_id = :dentist_id
                  AND status = 'active'
                LIMIT 1
            ");

            $dentistCheck->execute([
                ':dentist_id' => $dentist_id
            ]);

            if (!$dentistCheck->fetch()) {
                throw new Exception("The selected dentist is not available.");
            }

            $checkStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM tbl_appointments
                WHERE dentist_id = :dentist_id
                  AND appointment_date = :appointment_date
                  AND appointment_time = :appointment_time
                  AND status NOT IN ('cancelled', 'no_show')
            ");

            $checkStmt->execute([
                ':dentist_id'       => $dentist_id,
                ':appointment_date' => $appointment_date,
                ':appointment_time' => $appointment_time
            ]);

            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception(
                    "The selected dentist is already booked for this date and time."
                );
            }

            $stmt = $pdo->prepare("
                INSERT INTO tbl_appointments (
                    admin_id,
                    patient_id,
                    dentist_id,
                    appointment_date,
                    appointment_time,
                    procedure_name,
                    reason,
                    status
                )
                VALUES (
                    :admin_id,
                    :patient_id,
                    :dentist_id,
                    :appointment_date,
                    :appointment_time,
                    :procedure_name,
                    :reason,
                    'for_dentist'
                )
            ");

            $stmt->execute([
                ':admin_id'         => $admin_id,
                ':patient_id'       => $patient_id,
                ':dentist_id'       => $dentist_id,
                ':appointment_date' => $appointment_date,
                ':appointment_time' => $appointment_time,
                ':procedure_name'   => $procedure_name,
                ':reason'           => $reason !== '' ? $reason : null
            ]);

            header("Location: appointments.php?msg=success");
            exit;

        } catch (PDOException $e) {

            error_log("Database Error (Insert Appointment): " . $e->getMessage());

            $error_message =
                "An error occurred while sending the appointment. Please try again.";

        } catch (Exception $e) {

            error_log("Appointment Error: " . $e->getMessage());

            $error_message = $e->getMessage();
        }
    }
}
?>

<style>
  .form-container {
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 24px;
    box-shadow: var(--shadow-subtle);
    max-width: 800px;
    margin: 0 auto;
  }

  .form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 24px;
  }

  .form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .form-group.full-width {
    grid-column: span 2;
  }

  .form-group label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-muted);
  }

  .form-group input,
  .form-group select,
  .form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border-radius: var(--radius-md);
    border: 1px solid var(--border-color);
    background: var(--surface);
    color: var(--text-main);
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s ease;
  }

  .form-group input:focus,
  .form-group select:focus,
  .form-group textarea:focus {
    border-color: var(--brand-purple);
  }

  .alert {
    padding: 12px 16px;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 20px;
  }

  .alert-success {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
  }

  .alert-danger {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
  }

  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
  }

  .btn {
    padding: 10px 20px;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    transition: all 0.2s ease;
  }

  .btn-primary {
    background: var(--gradient-brand);
    color: white;
    box-shadow: 0 4px 12px rgba(147, 51, 234, 0.25);
  }

  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(147, 51, 234, 0.35);
  }

  .btn-secondary {
    background: var(--gradient-subtle);
    color: var(--text-main);
    border: 1px solid var(--border-color);
  }

  @media (max-width: 768px) {
    .form-grid {
      grid-template-columns: 1fr;
    }

    .form-group.full-width {
      grid-column: span 1;
    }
  }
</style>

<div class="welcome" style="margin-bottom: 24px;">
  <h2>New Appointment</h2>
  <p>Send a patient to a dentist by scheduling an appointment.</p>
</div>

<div class="form-container">

  <?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
      <i class="fa-solid fa-circle-check"></i>
      <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger">
      <i class="fa-solid fa-circle-exclamation"></i>
      <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php endif; ?>

  <form action="" method="POST">

    <div class="form-grid">

      <div class="form-group">
        <label for="patient_id">Select Patient *</label>

        <select id="patient_id" name="patient_id" required>
          <option value="">-- Choose Patient --</option>

          <?php foreach ($patients as $p): ?>
            <option
              value="<?php echo htmlspecialchars($p['patient_id'], ENT_QUOTES, 'UTF-8'); ?>"
              <?php echo ($patient_id == $p['patient_id']) ? 'selected' : ''; ?>
            >
              <?php echo htmlspecialchars($p['full_name'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>

        </select>
      </div>

      <div class="form-group">
        <label for="dentist_id">Assigned Dentist *</label>

        <select id="dentist_id" name="dentist_id" required>
          <option value="">-- Choose Dentist --</option>

          <?php foreach ($dentists as $d): ?>
            <option
              value="<?php echo htmlspecialchars($d['dentist_id'], ENT_QUOTES, 'UTF-8'); ?>"
              <?php echo ($dentist_id == $d['dentist_id']) ? 'selected' : ''; ?>
            >
              <?php echo htmlspecialchars($d['full_name'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>

        </select>
      </div>

      <div class="form-group">
        <label for="appointment_date">Date *</label>

        <input
          type="date"
          id="appointment_date"
          name="appointment_date"
          required
          min="<?php echo date('Y-m-d'); ?>"
          value="<?php echo htmlspecialchars($appointment_date, ENT_QUOTES, 'UTF-8'); ?>"
        >
      </div>

      <div class="form-group">
        <label for="appointment_time">Time *</label>

        <input
          type="time"
          id="appointment_time"
          name="appointment_time"
          required
          value="<?php echo htmlspecialchars($appointment_time, ENT_QUOTES, 'UTF-8'); ?>"
        >
      </div>

      <div class="form-group">
        <label for="procedure_name">Procedure *</label>

        <input
          type="text"
          id="procedure_name"
          name="procedure_name"
          required
          placeholder="e.g. Tooth Extraction, Cleaning, Braces Adjustment"
          value="<?php echo htmlspecialchars($procedure_name, ENT_QUOTES, 'UTF-8'); ?>"
        >
      </div>

      <div class="form-group full-width">
        <label for="reason">Reason / Notes</label>

        <textarea
          id="reason"
          name="reason"
          rows="3"
          placeholder="Specify any symptoms or special instructions..."
        ><?php echo htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'); ?></textarea>
      </div>

    </div>

    <div class="form-actions">

      <a href="appointments.php" class="btn btn-secondary">
        Cancel
      </a>

      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-paper-plane"></i>
        Send to Dentist
      </button>

    </div>

  </form>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
