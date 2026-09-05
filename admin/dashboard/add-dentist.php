<?php
ob_start();

$page_title = "Add Dentist";
$header_title = "Add New Dentist";

include __DIR__ . '/includes/header.php';

$success_message = '';
$error_message   = '';

$allowed_statuses = ['Active', 'Inactive'];

$username       = $_POST['username'] ?? '';
$first_name     = $_POST['first_name'] ?? '';
$last_name      = $_POST['last_name'] ?? '';
$license_no     = $_POST['license_no'] ?? '';
$specialization = $_POST['specialization'] ?? '';
$status         = $_POST['status'] ?? 'Active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username       = trim($username);
    $first_name     = trim($first_name);
    $last_name      = trim($last_name);
    $license_no     = trim($license_no);
    $specialization = trim($specialization);
    $password       = $_POST['password'] ?? '';
    $status         = trim($status);

    if (empty($username) || empty($first_name) || empty($last_name) || empty($license_no) || empty($password)) {
        $error_message = "Please fill in all required fields (Username, First Name, Last Name, License No., and Password).";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } elseif (!in_array($status, $allowed_statuses, true)) {
        $error_message = "Invalid status selected.";
    } else {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmtDentist = $pdo->prepare("
                INSERT INTO tbl_dentists (username, password, first_name, last_name, license_no, specialization, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtDentist->execute([
                $username,
                $hashed_password,
                $first_name,
                $last_name,
                $license_no,
                $specialization ?: 'General Dentistry',
                $status
            ]);

            header("Location: dentists.php?msg=added");
            exit;

        } catch (PDOException $e) {
            error_log("Database Error (Add Dentist): " . $e->getMessage());

            // Check for MySQL duplicate key error (1062)
            if ($e->getCode() === '23000' && strpos($e->getMessage(), '1062') !== false) {
                if (strpos($e->getMessage(), 'username') !== false) {
                    $error_message = "The username '{$username}' is already registered.";
                } elseif (strpos($e->getMessage(), 'license_no') !== false) {
                    $error_message = "The license number '{$license_no}' is already registered.";
                } else {
                    $error_message = "A record with this information already exists.";
                }
            } else {
                $error_message = "Database Error: " . $e->getMessage();
            }
        } catch (Exception $e) {
            error_log("System Error (Add Dentist): " . $e->getMessage());
            $error_message = "System Error: " . $e->getMessage();
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
  <h2>Add New Dentist</h2>
  <p>Fill out the details below to register a new dentist in the system.</p>
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
        <label for="username">Username *</label>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" required placeholder="e.g. drsmith">
      </div>

      <div class="form-group">
        <label for="password">Account Password *</label>
        <input type="password" id="password" name="password" required placeholder="Minimum 6 characters">
      </div>

      <div class="form-group">
        <label for="first_name">First Name *</label>
        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8'); ?>" required placeholder="e.g. Jane">
      </div>

      <div class="form-group">
        <label for="last_name">Last Name *</label>
        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8'); ?>" required placeholder="e.g. Smith">
      </div>

      <div class="form-group">
        <label for="license_no">License No. *</label>
        <input type="text" id="license_no" name="license_no" value="<?php echo htmlspecialchars($license_no, ENT_QUOTES, 'UTF-8'); ?>" required placeholder="e.g. DENT-12345">
      </div>

      <div class="form-group">
        <label for="specialization">Specialization</label>
        <input type="text" id="specialization" name="specialization" value="<?php echo htmlspecialchars($specialization, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Orthodontics, General Dentistry">
      </div>

      <div class="form-group full-width">
        <label for="status">Status</label>
        <select id="status" name="status">
          <?php foreach ($allowed_statuses as $st): ?>
            <option value="<?php echo $st; ?>" <?php echo ($status === $st) ? 'selected' : ''; ?>>
              <?php echo $st; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-actions">
      <a href="dentists.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-user-doctor"></i> Save Dentist
      </button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>