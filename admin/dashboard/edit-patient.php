<?php
$page_title = "Edit Patient";
$header_title = "Edit Patient Record";

include __DIR__ . '/includes/header.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_message = '';
$error_message   = '';

$patient_id     = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$patient_id) {
    header("Location: patient.php");
    exit;
}

// Fetch existing patient details
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.email, u.status AS account_status 
        FROM tbl_patients p
        JOIN tbl_users u ON p.user_id = u.user_id
        WHERE p.patient_id = ?
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        header("Location: patient.php");
        exit;
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    $error_message = "Failed to load patient record.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $first_name     = trim($_POST['first_name'] ?? '');
        $middle_name    = trim($_POST['middle_name'] ?? '');
        $last_name      = trim($_POST['last_name'] ?? '');
        $birth_date     = trim($_POST['birth_date'] ?? '');
        $sex            = trim($_POST['sex'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        $address        = trim($_POST['address'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $account_status = trim($_POST['account_status'] ?? 'Active');

        if ($first_name === '' || $last_name === '' || $birth_date === '' || $sex === '' || $email === '') {
            $error_message = "Please fill in all required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            try {
                $stmtCheck = $pdo->prepare("SELECT user_id FROM tbl_users WHERE email = ? AND user_id != ?");
                $stmtCheck->execute([$email, $patient['user_id']]);

                if ($stmtCheck->fetch()) {
                    $error_message = "The email address is already in use by another account.";
                } else {
                    $pdo->beginTransaction();

                    $stmtUser = $pdo->prepare("UPDATE tbl_users SET email = ?, status = ? WHERE user_id = ?");
                    $stmtUser->execute([$email, strtolower($account_status), $patient['user_id']]);

                    $stmtPatient = $pdo->prepare("
                        UPDATE tbl_patients 
                        SET first_name = ?, middle_name = ?, last_name = ?, birth_date = ?, sex = ?, contact_number = ?, address = ?
                        WHERE patient_id = ?
                    ");
                    $stmtPatient->execute([
                        $first_name,
                        $middle_name !== '' ? $middle_name : null,
                        $last_name,
                        $birth_date,
                        $sex,
                        $contact_number !== '' ? $contact_number : null,
                        $address !== '' ? $address : null,
                        $patient_id
                    ]);

                    $pdo->commit();
                    $success_message = "Patient updated successfully!";

                    $patient['first_name']     = $first_name;
                    $patient['middle_name']    = $middle_name;
                    $patient['last_name']      = $last_name;
                    $patient['birth_date']     = $birth_date;
                    $patient['sex']            = $sex;
                    $patient['contact_number'] = $contact_number;
                    $patient['address']        = $address;
                    $patient['email']          = $email;
                    $patient['account_status'] = strtolower($account_status);
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log($e->getMessage());
                $error_message = "An error occurred while updating. Please try again.";
            }
        }
    }
}
?>

<style>
  .welcome {
    margin-bottom: 24px;
  }

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

  .form-grid-3 {
    grid-column: span 2;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
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
    .form-grid,
    .form-grid-3 {
      grid-template-columns: 1fr;
    }
    .form-group.full-width,
    .form-grid-3 {
      grid-column: span 1;
    }
  }
</style>

<div class="welcome">
  <h2>Edit Patient</h2>
  <p>Update patient information below.</p>
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
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

    <div class="form-grid">
      <div class="form-grid-3">
        <div class="form-group">
          <label for="first_name">First Name *</label>
          <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($patient['first_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="form-group">
          <label for="middle_name">Middle Name</label>
          <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($patient['middle_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="form-group">
          <label for="last_name">Last Name *</label>
          <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($patient['last_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label for="birth_date">Birth Date *</label>
        <input type="date" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars($patient['birth_date'], ENT_QUOTES, 'UTF-8'); ?>" required max="<?php echo date('Y-m-d'); ?>">
      </div>

      <div class="form-group">
        <label for="sex">Sex *</label>
        <select id="sex" name="sex" required>
          <option value="Male" <?php echo ($patient['sex'] === 'Male') ? 'selected' : ''; ?>>Male</option>
          <option value="Female" <?php echo ($patient['sex'] === 'Female') ? 'selected' : ''; ?>>Female</option>
        </select>
      </div>

      <div class="form-group">
        <label for="contact_number">Contact Number</label>
        <input type="text" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($patient['contact_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="form-group">
        <label for="email">Email Address *</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($patient['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
      </div>

      <div class="form-group full-width">
        <label for="account_status">Account Status</label>
        <select id="account_status" name="account_status">
          <option value="Active" <?php echo (strtolower($patient['account_status']) === 'active') ? 'selected' : ''; ?>>Active</option>
          <option value="Inactive" <?php echo (strtolower($patient['account_status']) === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
        </select>
      </div>

      <div class="form-group full-width">
        <label for="address">Residential Address</label>
        <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($patient['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
      </div>
    </div>

    <div class="form-actions">
      <a href="patients.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-floppy-disk"></i> Save Changes
      </button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>