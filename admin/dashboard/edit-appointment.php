<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Include Database Connection dynamically across possible project structures
$db_paths = [
    __DIR__ . '/db.php',
    __DIR__ . '/includes/db.php',
    __DIR__ . '/../db.php',
    __DIR__ . '/../includes/db.php',
];

foreach ($db_paths as $path) {
    if (file_exists($path)) {
        include_once $path;
        break;
    }
}

$page_title = "Edit Appointment";
$header_title = "Update Appointment Details";

// 2. Validate Appointment ID
$appointment_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$appointment_id) {
    header("Location: appointment.php");
    exit;
}

// 3. Generate CSRF token if missing
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_message = '';
$error_message   = '';
$appointment     = null;
$patients        = [];
$dentists        = [];

// 4. Verify Database Connection & Fetch Data
if (!isset($pdo)) {
    $error_message = "Database connection unavailable. Please ensure db.php exists and contains a valid \$pdo connection.";
} else {
    try {
        // Fetch appointment details
        $stmt = $pdo->prepare("SELECT * FROM tbl_appointments WHERE appointment_id = ?");
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$appointment) {
            header("Location: appointment.php");
            exit;
        }

        // Fetch dropdown options
        $patients = $pdo->query("SELECT patient_id, first_name, last_name FROM tbl_patients ORDER BY last_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $dentists = $pdo->query("SELECT dentist_id, first_name, last_name FROM tbl_dentists ORDER BY last_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log($e->getMessage());
        $error_message = "Failed to load record: " . $e->getMessage();
    }
}

// 5. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $appointment && isset($pdo)) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $patient_id       = trim($_POST['patient_id'] ?? '');
        $dentist_id       = trim($_POST['dentist_id'] ?? '');
        $service          = trim($_POST['service'] ?? '');
        $appointment_date = trim($_POST['appointment_date'] ?? '');
        $appointment_time = trim($_POST['appointment_time'] ?? '');
        $status           = trim($_POST['status'] ?? 'Scheduled');
        $notes            = trim($_POST['notes'] ?? '');

        if ($patient_id === '' || $dentist_id === '' || $service === '' || $appointment_date === '' || $appointment_time === '') {
            $error_message = "Please fill in all required fields.";
        } else {
            try {
                // Dynamically detect column name used for service in tbl_appointments
                $columns_stmt = $pdo->query("SHOW COLUMNS FROM tbl_appointments");
                $columns = $columns_stmt->fetchAll(PDO::FETCH_COLUMN);

                if (in_array('service', $columns)) {
                    $stmt = $pdo->prepare("
                        UPDATE tbl_appointments 
                        SET patient_id = ?, dentist_id = ?, service = ?, appointment_date = ?, appointment_time = ?, status = ?, notes = ?
                        WHERE appointment_id = ?
                    ");
                } elseif (in_array('service_name', $columns)) {
                    $stmt = $pdo->prepare("
                        UPDATE tbl_appointments 
                        SET patient_id = ?, dentist_id = ?, service_name = ?, appointment_date = ?, appointment_time = ?, status = ?, notes = ?
                        WHERE appointment_id = ?
                    ");
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE tbl_appointments 
                        SET patient_id = ?, dentist_id = ?, service_id = ?, appointment_date = ?, appointment_time = ?, status = ?, notes = ?
                        WHERE appointment_id = ?
                    ");
                }

                $stmt->execute([$patient_id, $dentist_id, $service, $appointment_date, $appointment_time, $status, $notes, $appointment_id]);

                $success_message = "Appointment updated successfully!";

                // Update $appointment array for form display
                $appointment['patient_id']       = $patient_id;
                $appointment['dentist_id']       = $dentist_id;
                $appointment['service']          = $service;
                $appointment['service_name']     = $service;
                $appointment['service_id']       = $service;
                $appointment['appointment_date'] = $appointment_date;
                $appointment['appointment_time'] = $appointment_time;
                $appointment['status']           = $status;
                $appointment['notes']            = $notes;
            } catch (Exception $e) {
                error_log($e->getMessage());
                $error_message = "An error occurred while updating: " . $e->getMessage();
            }
        }
    }
}

// 6. Include Header dynamically
$header_paths = [
    __DIR__ . '/includes/header.php',
    __DIR__ . '/header.php',
    __DIR__ . '/../includes/header.php',
];

foreach ($header_paths as $h_path) {
    if (file_exists($h_path)) {
        include_once $h_path;
        break;
    }
}
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
      <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger">
      <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php endif; ?>

  <?php if ($appointment): ?>
    <form action="" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

      <div class="form-grid">
        <div class="form-group">
          <label for="patient_id">Patient *</label>
          <select id="patient_id" name="patient_id" required>
            <option value="">-- Select Patient --</option>
            <?php foreach ($patients as $p): ?>
              <option value="<?php echo $p['patient_id']; ?>" <?php echo ($appointment['patient_id'] == $p['patient_id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="dentist_id">Dentist *</label>
          <select id="dentist_id" name="dentist_id" required>
            <option value="">-- Select Dentist --</option>
            <?php foreach ($dentists as $d): ?>
              <option value="<?php echo $d['dentist_id']; ?>" <?php echo ($appointment['dentist_id'] == $d['dentist_id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($d['first_name'] . ' ' . $d['last_name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="service">Service / Procedure *</label>
          <input type="text" id="service" name="service" value="<?php echo htmlspecialchars($appointment['service'] ?? $appointment['service_name'] ?? $appointment['service_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Tooth Extraction, Cleaning, Braces Adjustment" required>
        </div>

        <div class="form-group">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="Scheduled" <?php echo (($appointment['status'] ?? '') === 'Scheduled') ? 'selected' : ''; ?>>Scheduled</option>
            <option value="Completed" <?php echo (($appointment['status'] ?? '') === 'Completed') ? 'selected' : ''; ?>>Completed</option>
            <option value="Cancelled" <?php echo (($appointment['status'] ?? '') === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
          </select>
        </div>

        <div class="form-group">
          <label for="appointment_date">Appointment Date *</label>
          <input type="date" id="appointment_date" name="appointment_date" value="<?php echo htmlspecialchars($appointment['appointment_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="form-group">
          <label for="appointment_time">Appointment Time *</label>
          <input type="time" id="appointment_time" name="appointment_time" value="<?php echo htmlspecialchars($appointment['appointment_time'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="form-group full-width">
          <label for="notes">Notes</label>
          <textarea id="notes" name="notes" rows="3" placeholder="Additional notes or instructions..."><?php echo htmlspecialchars($appointment['notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
      </div>

      <div class="form-actions">
        <a href="appointments.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-floppy-disk"></i> Save Changes
        </button>
      </div>
    </form>
  <?php else: ?>
    <div class="form-actions">
      <a href="appointment.php" class="btn btn-secondary">Return to Appointments List</a>
    </div>
  <?php endif; ?>
</div>

<?php 
$footer_paths = [
    __DIR__ . '/includes/footer.php',
    __DIR__ . '/footer.php',
    __DIR__ . '/../includes/footer.php',
];

foreach ($footer_paths as $f_path) {
    if (file_exists($f_path)) {
        include_once $f_path;
        break;
    }
}
?>