<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = "Edit Dentist";
$header_title = "Edit Dentist Profile";

// 1. Validate Dentist ID early
$dentist_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// 2. Generate CSRF token if missing
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 3. Fix Include Paths
$header_path = dirname(__DIR__) . '/includes/header.php';
$footer_path = dirname(__DIR__) . '/includes/footer.php';

if (file_exists($header_path)) {
    include $header_path;
} elseif (file_exists(__DIR__ . '/includes/header.php')) {
    include __DIR__ . '/includes/header.php';
}

$success_message = '';
$error_message   = '';
$dentist         = null;

// Redirect if ID is missing or invalid
if (!$dentist_id) {
    echo "<script>window.location.href='dentist.php';</script>";
    exit;
}

// 4. Fetch existing dentist details
try {
    if (isset($pdo)) {
        // Attempt JOIN with tbl_users or fetch directly from tbl_dentists
        $stmt = $pdo->prepare("
            SELECT d.*, u.status AS account_status, u.user_id 
            FROM tbl_dentists d
            LEFT JOIN tbl_users u ON u.dentist_id = d.dentist_id
            WHERE d.dentist_id = ?
        ");
        $stmt->execute([$dentist_id]);
        $dentist = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fallback: Fetch directly from tbl_dentists if no user match
        if (!$dentist || empty($dentist['first_name'])) {
            $stmt = $pdo->prepare("SELECT * FROM tbl_dentists WHERE dentist_id = ?");
            $stmt->execute([$dentist_id]);
            $dentist = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    if (!$dentist) {
        $error_message = "Failed to load dentist record. The specified ID was not found.";
    }
} catch (Exception $e) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM tbl_dentists WHERE dentist_id = ?");
        $stmt->execute([$dentist_id]);
        $dentist = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $ex) {
        error_log($ex->getMessage());
        $error_message = "Failed to load dentist record: " . $ex->getMessage();
    }
}

// 5. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dentist) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $first_name     = trim($_POST['first_name'] ?? '');
        $last_name      = trim($_POST['last_name'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $license_number = trim($_POST['license_number'] ?? '');
        $account_status = trim($_POST['account_status'] ?? 'Active');
        $password       = trim($_POST['password'] ?? '');

        if ($first_name === '' || $last_name === '') {
            $error_message = "Please fill in all required fields.";
        } else {
            try {
                $pdo->beginTransaction();

                // Update tbl_dentists (includes license_number)
                $stmtDentist = $pdo->prepare("
                    UPDATE tbl_dentists 
                    SET first_name = ?, last_name = ?, specialization = ?, license_number = ?
                    WHERE dentist_id = ?
                ");
                $stmtDentist->execute([
                    $first_name,
                    $last_name,
                    $specialization !== '' ? $specialization : null,
                    $license_number !== '' ? $license_number : null,
                    $dentist_id
                ]);

                // Update tbl_users status and/or password if user record exists
                if (!empty($dentist['user_id'])) {
                    if ($password !== '') {
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                        $stmtUser = $pdo->prepare("UPDATE tbl_users SET status = ?, password = ? WHERE user_id = ?");
                        $stmtUser->execute([strtolower($account_status), $hashed_password, $dentist['user_id']]);
                    } else {
                        $stmtUser = $pdo->prepare("UPDATE tbl_users SET status = ? WHERE user_id = ?");
                        $stmtUser->execute([strtolower($account_status), $dentist['user_id']]);
                    }
                }

                $pdo->commit();
                $success_message = "Dentist updated successfully!";

                $dentist['first_name']     = $first_name;
                $dentist['last_name']      = $last_name;
                $dentist['specialization'] = $specialization;
                $dentist['license_number'] = $license_number;
                $dentist['account_status'] = strtolower($account_status);

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log($e->getMessage());
                $error_message = "An error occurred while updating: " . $e->getMessage();
            }
        }
    }
}
?>

<style>
  .welcome { margin-bottom: 24px; }
  .form-container {
    background: var(--surface, #fff);
    border: 1px solid var(--border-color, #e5e7eb);
    border-radius: var(--radius-lg, 12px);
    padding: 24px;
    box-shadow: var(--shadow-subtle, 0 1px 3px rgba(0,0,0,0.1));
    max-width: 800px;
    margin: 0 auto;
  }
  .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 24px; }
  .form-group { display: flex; flex-direction: column; gap: 8px; }
  .form-group label { font-size: 13px; font-weight: 600; color: var(--text-muted, #6b7280); }
  .form-group input, .form-group select {
    width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color, #d1d5db); font-size: 14px;
  }
  .form-hint { font-size: 11px; color: var(--text-muted, #9ca3af); margin-top: -4px; }
  .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; margin-bottom: 20px; }
  .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
  .alert-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
  .form-actions { display: flex; justify-content: flex-end; gap: 12px; }
  .btn { padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none; border: none; }
  .btn-primary { background: #9333ea; color: white; }
  .btn-secondary { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
  @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
</style>

<div class="welcome">
  <h2>Edit Dentist</h2>
  <p>Update dentist profile details below.</p>
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

  <?php if ($dentist): ?>
    <form action="" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

      <div class="form-grid">
        <div class="form-group">
          <label for="first_name">First Name *</label>
          <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($dentist['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="form-group">
          <label for="last_name">Last Name *</label>
          <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($dentist['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="form-group">
          <label for="specialization">Specialization / Services</label>
          <input type="text" id="specialization" name="specialization" value="<?php echo htmlspecialchars($dentist['specialization'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. General Dentistry, Orthodontics, Root Canal">
        </div>

        <div class="form-group">
          <label for="license_number">License Number</label>
          <input type="text" id="license_number" name="license_number" value="<?php echo htmlspecialchars($dentist['license_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. PRC-0123456">
        </div>

        <div class="form-group">
          <label for="account_status">Active Status</label>
          <select id="account_status" name="account_status">
            <option value="Active" <?php echo (strtolower($dentist['account_status'] ?? $dentist['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
            <option value="Inactive" <?php echo (strtolower($dentist['account_status'] ?? $dentist['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
          </select>
        </div>

        <div class="form-group">
          <label for="password">New Password</label>
          <input type="password" id="password" name="password" placeholder="Leave blank to keep current password">
          <span class="form-hint">Only enter a new password if you want to change it.</span>
        </div>
      </div>

      <div class="form-actions">
        <a href="dentists.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-floppy-disk"></i> Save Changes
        </button>
      </div>
    </form>
  <?php else: ?>
    <div class="form-actions">
      <a href="dentists.php" class="btn btn-secondary">Return to Dentist List</a>
    </div>
  <?php endif; ?>
</div>

<?php 
if (file_exists($footer_path)) {
    include $footer_path; 
} elseif (file_exists(__DIR__ . '/includes/footer.php')) {
    include __DIR__ . '/includes/footer.php';
}
?>