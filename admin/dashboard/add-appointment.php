<?php
$page_title = "New Appointment";
$header_title = "Schedule Appointment";

include __DIR__ . '/includes/header.php';

$success_message = '';
$error_message   = '';

// Retain submitted values across form re-renders on validation failure
$patient_id       = $_POST['patient_id'] ?? '';
$dentist_id       = $_POST['dentist_id'] ?? '';
$appointment_date = $_POST['appointment_date'] ?? '';
$appointment_time = $_POST['appointment_time'] ?? '';
$procedure_name   = $_POST['procedure_name'] ?? '';
$reason           = $_POST['reason'] ?? '';
$status           = $_POST['status'] ?? 'pending';

// Allowed statuses for strict backend validation
$allowed_statuses = ['pending', 'confirmed', 'completed', 'cancelled'];

// Fetch dropdown data with exception handling
try {
    $stmtPatients = $pdo->query("SELECT patient_id, CONCAT(first_name, ' ', last_name) AS full_name FROM tbl_patients ORDER BY last_name ASC");
    $patients = $stmtPatients->fetchAll(PDO::FETCH_ASSOC);

    $stmtDentists = $pdo->query("SELECT dentist_id, CONCAT('Dr. ', first_name, ' ', last_name) AS full_name FROM tbl_dentists WHERE status = 'active' ORDER BY last_name ASC");
    $dentists = $stmtDentists->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error (Fetch Dropdowns): " . $e->getMessage());
    $error_message = "Unable to load page data. Please try again later.";
    $patients = [];
    $dentists = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and trim inputs
    $patient_id       = trim($patient_id);
    $dentist_id       = trim($dentist_id);
    $appointment_date = trim($appointment_date);
    $appointment_time = trim($appointment_time);
    $procedure_name   = trim($procedure_name);
    $reason           = trim($reason);
    $status           = trim($status);

    // Backend Form Validation
    if (empty($patient_id) || empty($appointment_date) || empty($appointment_time) || empty($procedure_name)) {
        $error_message = "Please fill in all required fields (Patient, Date, Time, and Procedure).";
    } elseif (!in_array($status, $allowed_statuses, true)) {
        $error_message = "Invalid appointment status selected.";
    } else {
        try {
            // Optional: Prevent Double-Booking (Check dentist availability at the specified date & time)
            if (!empty($dentist_id)) {
                $checkStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM tbl_appointments 
                    WHERE dentist_id = :dentist_id 
                      AND appointment_date = :appointment_date 
                      AND appointment_time = :appointment_time 
                      AND status != 'cancelled'
                ");
                $checkStmt->execute([
                    ':dentist_id'       => $dentist_id,
                    ':appointment_date' => $appointment_date,
                    ':appointment_time' => $appointment_time
                ]);

                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception("The selected dentist is already booked for this date and time.");
                }
            }

            // Insert Record
            $stmt = $pdo->prepare("
                INSERT INTO tbl_appointments 
                    (patient_id, dentist_id, appointment_date, appointment_time, procedure_name, reason, status, created_at) 
                VALUES 
                    (:patient_id, :dentist_id, :appointment_date, :appointment_time, :procedure_name, :reason, :status, NOW())
            ");

            $stmt->execute([
                ':patient_id'       => $patient_id,
                ':dentist_id'       => $dentist_id ?: null,
                ':appointment_date' => $appointment_date,
                ':appointment_time' => $appointment_time,
                ':procedure_name'   => $procedure_name,
                ':reason'           => $reason ?: null,
                ':status'           => $status
            ]);

            // PRG Pattern: Redirect to prevent duplicate submission on page refresh
            header("Location: appointments.php?msg=success");
            exit;

        } catch (Exception $e) {
            // Log full exception to server logs without exposing internal database details to users
            error_log("Database Error (Insert Appointment): " . $e->getMessage());
            $error_message = ($e instanceof PDOException) 
                ? "An error occurred while saving the appointment. Please try again." 
                : $e->getMessage();
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
  <p>Schedule a dental procedure or consultation for a patient.</p>
</div>

<div class="form-container">
  <?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
      <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger">
      <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php endif; ?>

  <form action="" method="POST">
    <div class="form-grid">
      
      <div class="form-group">
        <label for="patient_id">Select Patient *</label>
        <select id="patient_id" name="patient_id" required>
          <option value="">-- Choose Patient --</option>
          <?php foreach ($patients as $p): ?>
            <option value="<?php echo htmlspecialchars($p['patient_id'], ENT_QUOTES, 'UTF-8'); ?>"
              <?php echo ($patient_id == $p['patient_id']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($p['full_name'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="dentist_id">Assigned Dentist</label>
        <select id="dentist_id" name="dentist_id">
          <option value="">-- Choose Dentist --</option>
          <?php foreach ($dentists as $d): ?>
            <option value="<?php echo htmlspecialchars($d['dentist_id'], ENT_QUOTES, 'UTF-8'); ?>"
              <?php echo ($dentist_id == $d['dentist_id']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($d['full_name'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="appointment_date">Date *</label>
        <input type="date" id="appointment_date" name="appointment_date" required 
               min="<?php echo date('Y-m-d'); ?>" 
               value="<?php echo htmlspecialchars($appointment_date, ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="form-group">
        <label for="appointment_time">Time *</label>
        <input type="time" id="appointment_time" name="appointment_time" required 
               value="<?php echo htmlspecialchars($appointment_time, ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="form-group">
        <label for="procedure_name">Procedure *</label>
        <input type="text" id="procedure_name" name="procedure_name" required 
               placeholder="e.g. Tooth Extraction, Cleaning, Braces Adjustment" 
               value="<?php echo htmlspecialchars($procedure_name, ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
          <?php foreach ($allowed_statuses as $st): ?>
            <option value="<?php echo $st; ?>" <?php echo ($status === $st) ? 'selected' : ''; ?>>
              <?php echo ucfirst($st); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group full-width">
        <label for="reason">Reason / Notes (Include 'walk-in' here if applicable)</label>
        <textarea id="reason" name="reason" rows="3" placeholder="Specify any symptoms or special instructions..."><?php echo htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'); ?></textarea>
      </div>

    </div>

    <div class="form-actions">
      <a href="appointments.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-calendar-plus"></i> Save Appointment
      </button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>