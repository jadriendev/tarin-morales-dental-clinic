<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = "Edit Dentist";
$header_title = "Edit Dentist Profile";

// Validate Dentist ID
$dentist_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
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

// Header
$header_paths = [
    __DIR__ . '/includes/header.php',
    __DIR__ . '/header.php',
    __DIR__ . '/../includes/header.php',
];

foreach ($header_paths as $path) {
    if (file_exists($path)) {
        include_once $path;
        break;
    }
}

$success_message = '';
$error_message = '';
$dentist = null;

// Redirect if ID is invalid
if (!$dentist_id) {
    header("Location: dentists.php");
    exit;
}

// Check database
if (!isset($pdo)) {
    $error_message = "Database connection unavailable.";
} else {

    // Fetch dentist
    try {

        $stmt = $pdo->prepare("
            SELECT *
            FROM tbl_dentists
            WHERE dentist_id = ?
        ");

        $stmt->execute([$dentist_id]);

        $dentist = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dentist) {
            $error_message = "Dentist record not found.";
        }

    } catch (PDOException $e) {

        error_log($e->getMessage());
        $error_message = "Failed to load dentist record: " . $e->getMessage();
    }
}


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dentist && isset($pdo)) {

    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {

        $error_message = "Invalid security token. Please refresh and try again.";

    } else {

        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $license_no = trim($_POST['license_no'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        $password = trim($_POST['password'] ?? '');

        // Validate required fields
        if ($first_name === '' || $last_name === '' || $license_no === '') {

            $error_message = "Please fill in all required fields.";

        } elseif (!in_array($status, ['active', 'inactive'], true)) {

            $error_message = "Invalid account status.";

        } else {

            try {

                $pdo->beginTransaction();

                /*
                 * Update dentist
                 *
                 * IMPORTANT:
                 * Database uses license_no, NOT license_number.
                 */
                $stmt = $pdo->prepare("
                    UPDATE tbl_dentists
                    SET
                        first_name = ?,
                        last_name = ?,
                        specialization = ?,
                        license_no = ?,
                        status = ?
                    WHERE dentist_id = ?
                ");

                $stmt->execute([
                    $first_name,
                    $last_name,
                    $specialization !== '' ? $specialization : null,
                    $license_no,
                    $status,
                    $dentist_id
                ]);


                /*
                 * Update dentist password
                 *
                 * tbl_dentists itself contains:
                 * username
                 * password
                 *
                 * So we update the password directly here.
                 */
                if ($password !== '') {

                    $hashed_password = password_hash(
                        $password,
                        PASSWORD_BCRYPT
                    );

                    $stmt = $pdo->prepare("
                        UPDATE tbl_dentists
                        SET password = ?
                        WHERE dentist_id = ?
                    ");

                    $stmt->execute([
                        $hashed_password,
                        $dentist_id
                    ]);
                }


                $pdo->commit();

                $success_message = "Dentist updated successfully!";


                // Update displayed values
                $dentist['first_name'] = $first_name;
                $dentist['last_name'] = $last_name;
                $dentist['specialization'] = $specialization;
                $dentist['license_no'] = $license_no;
                $dentist['status'] = $status;


            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log($e->getMessage());

                $error_message =
                    "An error occurred while updating: " .
                    $e->getMessage();
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

  .form-group label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-muted, #6b7280);
  }

  .form-group input,
  .form-group select {
    width: 100%;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid var(--border-color, #d1d5db);
    background: var(--surface, #fff);
    color: var(--text-main, #111827);
    font-size: 14px;
    outline: none;
    box-sizing: border-box;
  }

  .form-group input:focus,
  .form-group select:focus {
    border-color: var(--brand-purple, #9333ea);
  }

  .form-hint {
    font-size: 11px;
    color: var(--text-muted, #9ca3af);
    margin-top: -4px;
  }

  .alert {
    padding: 12px 16px;
    border-radius: 8px;
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
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  .btn-primary {
    background: #9333ea;
    color: white;
  }

  .btn-primary:hover {
    background: #7e22ce;
  }

  .btn-secondary {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
  }

  @media (max-width: 768px) {

    .form-grid {
      grid-template-columns: 1fr;
    }

  }
</style>

<div class="welcome">
  <h2>Edit Dentist</h2>
  <p>Update dentist profile details below.</p>
</div>

<div class="form-container">

  <?php if (!empty($success_message)): ?>

    <div class="alert alert-success">
      <i class="fa-solid fa-circle-check"></i>
      <?php
      echo htmlspecialchars(
          $success_message,
          ENT_QUOTES,
          'UTF-8'
      );
      ?>
    </div>

  <?php endif; ?>


  <?php if (!empty($error_message)): ?>

    <div class="alert alert-danger">
      <i class="fa-solid fa-circle-exclamation"></i>
      <?php
      echo htmlspecialchars(
          $error_message,
          ENT_QUOTES,
          'UTF-8'
      );
      ?>
    </div>

  <?php endif; ?>


  <?php if ($dentist): ?>

    <form action="" method="POST">

      <input
        type="hidden"
        name="csrf_token"
        value="<?php echo htmlspecialchars(
            $_SESSION['csrf_token'],
            ENT_QUOTES,
            'UTF-8'
        ); ?>"
      >

      <div class="form-grid">

        <!-- First Name -->
        <div class="form-group">

          <label for="first_name">
            First Name *
          </label>

          <input
            type="text"
            id="first_name"
            name="first_name"
            value="<?php echo htmlspecialchars(
                $dentist['first_name'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
            required
          >

        </div>


        <!-- Last Name -->
        <div class="form-group">

          <label for="last_name">
            Last Name *
          </label>

          <input
            type="text"
            id="last_name"
            name="last_name"
            value="<?php echo htmlspecialchars(
                $dentist['last_name'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
            required
          >

        </div>


        <!-- Specialization -->
        <div class="form-group">

          <label for="specialization">
            Specialization / Services
          </label>

          <input
            type="text"
            id="specialization"
            name="specialization"
            value="<?php echo htmlspecialchars(
                $dentist['specialization'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
            placeholder="e.g. General Dentistry, Orthodontics, Root Canal"
          >

        </div>


        <!-- License -->
        <div class="form-group">

          <label for="license_no">
            License Number *
          </label>

          <input
            type="text"
            id="license_no"
            name="license_no"
            value="<?php echo htmlspecialchars(
                $dentist['license_no'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
            placeholder="e.g. PRC-0123456"
            required
          >

        </div>


        <!-- Status -->
        <div class="form-group">

          <label for="status">
            Active Status
          </label>

          <select
            id="status"
            name="status"
          >

            <option
              value="active"
              <?php echo (($dentist['status'] ?? 'active') === 'active')
                ? 'selected'
                : ''; ?>
            >
              Active
            </option>

            <option
              value="inactive"
              <?php echo (($dentist['status'] ?? '') === 'inactive')
                ? 'selected'
                : ''; ?>
            >
              Inactive
            </option>

          </select>

        </div>


        <!-- Password -->
        <div class="form-group">

          <label for="password">
            New Password
          </label>

          <input
            type="password"
            id="password"
            name="password"
            placeholder="Leave blank to keep current password"
          >

          <span class="form-hint">
            Only enter a new password if you want to change it.
          </span>

        </div>

      </div>


      <div class="form-actions">

        <a
          href="dentists.php"
          class="btn btn-secondary"
        >
          Cancel
        </a>

        <button
          type="submit"
          class="btn btn-primary"
        >
          <i class="fa-solid fa-floppy-disk"></i>
          Save Changes
        </button>

      </div>

    </form>

  <?php else: ?>

    <div class="form-actions">

      <a
        href="dentists.php"
        class="btn btn-secondary"
      >
        Return to Dentist List
      </a>

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