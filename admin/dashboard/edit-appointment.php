<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

$page_title = "Edit Appointment";
$header_title = "Update Appointment Details";

$appointment_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$appointment_id) {
    header("Location: appointments.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_message = '';
$error_message = '';
$appointment = null;
$patients = [];
$dentists = [];

$valid_statuses = [
    'pending',
    'confirmed',
    'for_dentist',
    'in_progress',
    'completed',
    'cancelled',
    'no_show'
];

try {
    $stmt = $pdo->prepare("
        SELECT
            appointment_id,
            patient_id,
            dentist_id,
            admin_id,
            appointment_date,
            appointment_time,
            procedure_name,
            reason,
            status,
            created_at,
            updated_at
        FROM tbl_appointments
        WHERE appointment_id = :appointment_id
        LIMIT 1
    ");

    $stmt->execute([
        ':appointment_id' => $appointment_id
    ]);

    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        header("Location: appointments.php");
        exit;
    }

    $stmtPatients = $pdo->query("
        SELECT
            p.patient_id,
            p.first_name,
            p.last_name
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
            first_name,
            last_name
        FROM tbl_dentists
        WHERE status = 'active'
        ORDER BY last_name ASC, first_name ASC
    ");

    $dentists = $stmtDentists->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database Error (Edit Appointment Load): " . $e->getMessage());
    $error_message = "Failed to load the appointment. Please try again.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $appointment) {

    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {

        $error_message = "Invalid security token. Please refresh and try again.";

    } else {

        $patient_id = filter_input(
            INPUT_POST,
            'patient_id',
            FILTER_VALIDATE_INT
        );

        $dentist_id = filter_input(
            INPUT_POST,
            'dentist_id',
            FILTER_VALIDATE_INT
        );

        $procedure_name = trim(
            $_POST['procedure_name'] ?? ''
        );

        $appointment_date = trim(
            $_POST['appointment_date'] ?? ''
        );

        $appointment_time = trim(
            $_POST['appointment_time'] ?? ''
        );

        $status = trim(
            $_POST['status'] ?? 'pending'
        );

        $reason = trim(
            $_POST['reason'] ?? ''
        );

        if (
            !$patient_id ||
            !$dentist_id ||
            $procedure_name === '' ||
            $appointment_date === '' ||
            $appointment_time === ''
        ) {

            $error_message = "Please fill in all required fields.";

        } elseif (!in_array($status, $valid_statuses, true)) {

            $error_message = "Invalid appointment status.";

        } elseif (strlen($procedure_name) > 150) {

            $error_message = "Procedure name must not exceed 150 characters.";

        } else {

            try {

                $dateObject = DateTime::createFromFormat(
                    'Y-m-d',
                    $appointment_date
                );

                $dateErrors = DateTime::getLastErrors();

                if (
                    !$dateObject ||
                    (
                        $dateErrors !== false &&
                        (
                            $dateErrors['warning_count'] > 0 ||
                            $dateErrors['error_count'] > 0
                        )
                    )
                ) {

                    $error_message = "Please enter a valid appointment date.";

                } else {

                    $timeObject = DateTime::createFromFormat(
                        'H:i',
                        $appointment_time
                    );

                    $timeErrors = DateTime::getLastErrors();

                    if (
                        !$timeObject ||
                        (
                            $timeErrors !== false &&
                            (
                                $timeErrors['warning_count'] > 0 ||
                                $timeErrors['error_count'] > 0
                            )
                        )
                    ) {

                        $error_message = "Please enter a valid appointment time.";

                    } else {

                        $stmtPatient = $pdo->prepare("
                            SELECT p.patient_id
                            FROM tbl_patients p
                            INNER JOIN tbl_users u
                                ON p.user_id = u.user_id
                            WHERE p.patient_id = :patient_id
                            AND u.status = 'active'
                            LIMIT 1
                        ");

                        $stmtPatient->execute([
                            ':patient_id' => $patient_id
                        ]);

                        if (!$stmtPatient->fetch()) {

                            $error_message = "The selected patient is not active or does not exist.";

                        } else {

                            $stmtDentist = $pdo->prepare("
                                SELECT dentist_id
                                FROM tbl_dentists
                                WHERE dentist_id = :dentist_id
                                AND status = 'active'
                                LIMIT 1
                            ");

                            $stmtDentist->execute([
                                ':dentist_id' => $dentist_id
                            ]);

                            if (!$stmtDentist->fetch()) {

                                $error_message = "The selected dentist is not active or does not exist.";

                            } else {

                                $stmtConflict = $pdo->prepare("
                                    SELECT appointment_id
                                    FROM tbl_appointments
                                    WHERE dentist_id = :dentist_id
                                    AND appointment_date = :appointment_date
                                    AND appointment_time = :appointment_time
                                    AND appointment_id != :appointment_id
                                    AND status NOT IN ('cancelled', 'no_show')
                                    LIMIT 1
                                ");

                                $stmtConflict->execute([
                                    ':dentist_id' => $dentist_id,
                                    ':appointment_date' => $appointment_date,
                                    ':appointment_time' => $appointment_time,
                                    ':appointment_id' => $appointment_id
                                ]);

                                if ($stmtConflict->fetch()) {

                                    $error_message = "The selected dentist already has an appointment at this date and time.";

                                } else {

                                    $stmtUpdate = $pdo->prepare("
                                        UPDATE tbl_appointments
                                        SET
                                            patient_id = :patient_id,
                                            dentist_id = :dentist_id,
                                            appointment_date = :appointment_date,
                                            appointment_time = :appointment_time,
                                            procedure_name = :procedure_name,
                                            reason = :reason,
                                            status = :status
                                        WHERE appointment_id = :appointment_id
                                    ");

                                    $stmtUpdate->execute([
                                        ':patient_id' => $patient_id,
                                        ':dentist_id' => $dentist_id,
                                        ':appointment_date' => $appointment_date,
                                        ':appointment_time' => $appointment_time,
                                        ':procedure_name' => $procedure_name,
                                        ':reason' => $reason !== '' ? $reason : null,
                                        ':status' => $status,
                                        ':appointment_id' => $appointment_id
                                    ]);

                                    $success_message = "Appointment updated successfully.";

                                    $appointment['patient_id'] = $patient_id;
                                    $appointment['dentist_id'] = $dentist_id;
                                    $appointment['appointment_date'] = $appointment_date;
                                    $appointment['appointment_time'] = $appointment_time;
                                    $appointment['procedure_name'] = $procedure_name;
                                    $appointment['reason'] = $reason;
                                    $appointment['status'] = $status;
                                }
                            }
                        }
                    }
                }

            } catch (PDOException $e) {

                error_log("Database Error (Edit Appointment): " . $e->getMessage());

                $error_message = "An error occurred while updating the appointment. Please try again.";
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<style>
  .welcome {
    margin-bottom: 24px;
  }

  .form-container {
    background: var(--surface, #fff);
    border: 1px solid var(--border-color, #e5e7eb);
    border-radius: var(--radius-lg, 12px);
    padding: 24px;
    box-shadow: var(--shadow-subtle, 0 1px 3px rgba(0,0,0,0.1));
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
    color: var(--text-muted, #6b7280);
  }

  .form-group input,
  .form-group select,
  .form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border-radius: var(--radius-md, 8px);
    border: 1px solid var(--border-color, #d1d5db);
    background: var(--surface, #fff);
    color: var(--text-main, #111827);
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s ease;
    box-sizing: border-box;
  }

  .form-group input:focus,
  .form-group select:focus,
  .form-group textarea:focus {
    border-color: var(--brand-purple, #9333ea);
  }

  .alert {
    padding: 12px 16px;
    border-radius: var(--radius-md, 8px);
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
    border-radius: var(--radius-md, 8px);
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
    background: var(--gradient-brand, #9333ea);
    color: white;
    box-shadow: 0 4px 12px rgba(147, 51, 234, 0.25);
  }

  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(147, 51, 234, 0.35);
  }

  .btn-secondary {
    background: var(--gradient-subtle, #f3f4f6);
    color: var(--text-main, #374151);
    border: 1px solid var(--border-color, #d1d5db);
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

<div class="welcome">
  <h2>Edit Appointment</h2>
  <p>Modify appointment details below.</p>
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

  <?php if ($appointment): ?>

    <form action="" method="POST">

      <input
        type="hidden"
        name="csrf_token"
        value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"
      >

      <div class="form-grid">

        <div class="form-group">

          <label for="patient_id">Patient *</label>

          <select id="patient_id" name="patient_id" required>

            <option value="">-- Select Patient --</option>

            <?php foreach ($patients as $p): ?>

              <option
                value="<?php echo (int)$p['patient_id']; ?>"
                <?php echo ((int)$appointment['patient_id'] === (int)$p['patient_id']) ? 'selected' : ''; ?>
              >
                <?php
                echo htmlspecialchars(
                    trim($p['first_name'] . ' ' . $p['last_name']),
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
              </option>

            <?php endforeach; ?>

          </select>

        </div>

        <div class="form-group">

          <label for="dentist_id">Dentist *</label>

          <select id="dentist_id" name="dentist_id" required>

            <option value="">-- Select Dentist --</option>

            <?php foreach ($dentists as $d): ?>

              <option
                value="<?php echo (int)$d['dentist_id']; ?>"
                <?php echo ((int)$appointment['dentist_id'] === (int)$d['dentist_id']) ? 'selected' : ''; ?>
              >
                <?php
                echo htmlspecialchars(
                    trim($d['first_name'] . ' ' . $d['last_name']),
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
              </option>

            <?php endforeach; ?>

          </select>

        </div>

        <div class="form-group">

          <label for="procedure_name">Service / Procedure *</label>

          <input
            type="text"
            id="procedure_name"
            name="procedure_name"
            value="<?php echo htmlspecialchars($appointment['procedure_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
            maxlength="150"
            placeholder="e.g. Tooth Extraction, Cleaning, Braces Adjustment"
            required
          >

        </div>

        <div class="form-group">

          <label for="status">Status</label>

          <select id="status" name="status">

            <option value="pending" <?php echo (($appointment['status'] ?? '') === 'pending') ? 'selected' : ''; ?>>
              Pending
            </option>

            <option value="confirmed" <?php echo (($appointment['status'] ?? '') === 'confirmed') ? 'selected' : ''; ?>>
              Confirmed
            </option>

            <option value="for_dentist" <?php echo (($appointment['status'] ?? '') === 'for_dentist') ? 'selected' : ''; ?>>
              For Dentist
            </option>

            <option value="in_progress" <?php echo (($appointment['status'] ?? '') === 'in_progress') ? 'selected' : ''; ?>>
              In Progress
            </option>

            <option value="completed" <?php echo (($appointment['status'] ?? '') === 'completed') ? 'selected' : ''; ?>>
              Completed
            </option>

            <option value="cancelled" <?php echo (($appointment['status'] ?? '') === 'cancelled') ? 'selected' : ''; ?>>
              Cancelled
            </option>

            <option value="no_show" <?php echo (($appointment['status'] ?? '') === 'no_show') ? 'selected' : ''; ?>>
              No Show
            </option>

          </select>

        </div>

        <div class="form-group">

          <label for="appointment_date">Appointment Date *</label>

          <input
            type="date"
            id="appointment_date"
            name="appointment_date"
            value="<?php echo htmlspecialchars($appointment['appointment_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
            required
          >

        </div>

        <div class="form-group">

          <label for="appointment_time">Appointment Time *</label>

          <input
            type="time"
            id="appointment_time"
            name="appointment_time"
            value="<?php echo htmlspecialchars(substr($appointment['appointment_time'] ?? '', 0, 5), ENT_QUOTES, 'UTF-8'); ?>"
            required
          >

        </div>

        <div class="form-group full-width">

          <label for="reason">Reason / Notes</label>

          <textarea
            id="reason"
            name="reason"
            rows="3"
            placeholder="Additional notes or reason for appointment..."
          ><?php echo htmlspecialchars($appointment['reason'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>

        </div>

      </div>

      <div class="form-actions">

        <a href="appointments.php" class="btn btn-secondary">
          Cancel
        </a>

        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-floppy-disk"></i>
          Save Changes
        </button>

      </div>

    </form>

  <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>