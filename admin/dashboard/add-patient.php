<?php
$page_title = "Add Patient";
$header_title = "Register New Patient";

include __DIR__ . '/includes/header.php';

// Ensure session is started for CSRF (if not already started in header.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_message = '';
$error_message   = '';

// Standard form values initialization
$first_name     = '';
$middle_name    = '';
$last_name      = '';
$birth_date     = '';
$sex            = '';
$contact_number = '';
$address        = '';
$email          = '';
$account_status = 'Active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
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
        $password       = $_POST['password'] ?? '';
        $account_status = trim($_POST['account_status'] ?? 'Active');

        // Explicit validation checks
        if ($first_name === '' || $last_name === '' || $birth_date === '' || $sex === '' || $email === '' || $password === '') {
            $error_message = "Please fill in all required fields (First Name, Last Name, Birth Date, Sex, Email, and Password).";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } elseif (strlen($password) < 6) {
            $error_message = "Password must be at least 6 characters long.";
        } else {
            try {
                // Check if email already exists
                $stmtCheck = $pdo->prepare("SELECT user_id FROM tbl_users WHERE email = ?");
                $stmtCheck->execute([$email]);

                if ($stmtCheck->fetch()) {
                    $error_message = "The email address is already registered.";
                } else {
                    $pdo->beginTransaction();

                    // 1. Create user account
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmtUser = $pdo->prepare("
                        INSERT INTO tbl_users (email, password, role, status, created_at) 
                        VALUES (?, ?, 'patient', ?, NOW())
                    ");
                    $stmtUser->execute([$email, $hashed_password, strtolower($account_status)]);
                    $user_id = $pdo->lastInsertId();

                    // 2. Create patient profile
                    $stmtPatient = $pdo->prepare("
                        INSERT INTO tbl_patients (user_id, first_name, middle_name, last_name, birth_date, sex, contact_number, address, date_registered) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmtPatient->execute([
                        $user_id,
                        $first_name,
                        $middle_name !== '' ? $middle_name : null,
                        $last_name,
                        $birth_date,
                        $sex,
                        $contact_number !== '' ? $contact_number : null,
                        $address !== '' ? $address : null
                    ]);

                    $pdo->commit();
                    $success_message = "Patient registered successfully!";

                    // Reset values after successful creation
                    $first_name = $middle_name = $last_name = $birth_date = $sex = $contact_number = $address = $email = '';
                    $account_status = 'Active';
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // Log actual error to server logs and show friendly message to user
                error_log($e->getMessage());
                $error_message = "An error occurred while saving. Please try again.";
            }
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

<div class="welcome" style="margin-bottom: 24px;">
  <h2>Add New Patient</h2>
  <p>Fill out the details below to register a new patient in the system.</p>
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
    <!-- CSRF Token Hidden Input -->
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

    <div class="form-grid">
      
      <!-- Name Section -->
      <div class="form-grid-3">
        <div class="form-group">
          <label for="first_name">First Name *</label>
          <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8'); ?>" required placeholder="e.g. Juan">
        </div>

        <div class="form-group">
          <label for="middle_name">Middle Name</label>
          <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($middle_name, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Santos">
        </div>

        <div class="form-group">
          <label for="last_name">Last Name *</label>
          <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8'); ?>" required placeholder="e.g. Dela Cruz">
        </div>
      </div>

      <!-- Demographics -->
      <div class="form-group">
        <label for="birth_date">Birth Date *</label>
        <input type="date" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars($birth_date, ENT_QUOTES, 'UTF-8'); ?>" required max="<?php echo date('Y-m-d'); ?>">
      </div>

      <div class="form-group">
        <label for="sex">Sex *</label>
        <select id="sex" name="sex" required>
          <option value="">-- Select Sex --</option>
          <option value="Male" <?php echo ($sex === 'Male') ? 'selected' : ''; ?>>Male</option>
          <option value="Female" <?php echo ($sex === 'Female') ? 'selected' : ''; ?>>Female</option>
        </select>
      </div>

      <!-- Contact & Account -->
      <div class="form-group">
        <label for="contact_number">Contact Number</label>
        <input type="text" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($contact_number, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 09123456789">
      </div>

      <div class="form-group">
        <label for="email">Email Address *</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required placeholder="e.g. patient@example.com">
      </div>

      <div class="form-group">
        <label for="password">Account Password *</label>
        <input type="password" id="password" name="password" required placeholder="Minimum 6 characters">
      </div>

      <div class="form-group">
        <label for="account_status">Account Status</label>
        <select id="account_status" name="account_status">
          <option value="Active" <?php echo ($account_status === 'Active') ? 'selected' : ''; ?>>Active</option>
          <option value="Inactive" <?php echo ($account_status === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
        </select>
      </div>

      <!-- Address -->
      <div class="form-group full-width">
        <label for="address">Residential Address</label>
        <textarea id="address" name="address" rows="3" placeholder="Enter complete home address..."><?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?></textarea>
      </div>

    </div>

    <div class="form-actions">
      <a href="patient.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-user-plus"></i> Save Patient
      </button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>