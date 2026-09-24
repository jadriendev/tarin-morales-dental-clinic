<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

$page_title = "Edit Dentist";
$header_title = "Edit Dentist Profile";

$dentist_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$dentist_id) {
    header("Location: dentists.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_message = '';
$error_message = '';
$dentist = null;

try {

    $stmt = $pdo->prepare("
        SELECT
            dentist_id,
            username,
            password,
            first_name,
            last_name,
            license_no,
            specialization,
            status
        FROM tbl_dentists
        WHERE dentist_id = :dentist_id
        LIMIT 1
    ");

    $stmt->execute([
        ':dentist_id' => $dentist_id
    ]);

    $dentist = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dentist) {
        $error_message = "Dentist record not found.";
    }

} catch (PDOException $e) {

    error_log("Database Error (Load Dentist): " . $e->getMessage());

    $error_message = "Failed to load dentist record. Please try again.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dentist) {

    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {

        $error_message = "Invalid security token. Please refresh and try again.";

    } else {

        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $license_no = trim($_POST['license_no'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        $password = $_POST['password'] ?? '';

        if (
            $first_name === '' ||
            $last_name === '' ||
            $license_no === ''
        ) {

            $error_message = "Please fill in all required fields.";

        } elseif (strlen($first_name) > 100) {

            $error_message = "First name must not exceed 100 characters.";

        } elseif (strlen($last_name) > 100) {

            $error_message = "Last name must not exceed 100 characters.";

        } elseif (strlen($license_no) > 50) {

            $error_message = "License number must not exceed 50 characters.";

        } elseif (strlen($specialization) > 100) {

            $error_message = "Specialization must not exceed 100 characters.";

        } elseif (!in_array($status, ['active', 'inactive'], true)) {

            $error_message = "Invalid account status.";

        } else {

            try {

                $stmtLicense = $pdo->prepare("
                    SELECT dentist_id
                    FROM tbl_dentists
                    WHERE license_no = :license_no
                    AND dentist_id != :dentist_id
                    LIMIT 1
                ");

                $stmtLicense->execute([
                    ':license_no' => $license_no,
                    ':dentist_id' => $dentist_id
                ]);

                if ($stmtLicense->fetch()) {

                    $error_message = "The license number is already assigned to another dentist.";

                } else {

                    if ($password !== '' && strlen($password) < 8) {

                        $error_message = "Password must be at least 8 characters long.";

                    } else {

                        $pdo->beginTransaction();

                        $stmtUpdate = $pdo->prepare("
                            UPDATE tbl_dentists
                            SET
                                first_name = :first_name,
                                last_name = :last_name,
                                specialization = :specialization,
                                license_no = :license_no,
                                status = :status
                            WHERE dentist_id = :dentist_id
                        ");

                        $stmtUpdate->execute([
                            ':first_name' => $first_name,
                            ':last_name' => $last_name,
                            ':specialization' => $specialization !== ''
                                ? $specialization
                                : null,
                            ':license_no' => $license_no,
                            ':status' => $status,
                            ':dentist_id' => $dentist_id
                        ]);

                        if ($password !== '') {

                            $hashed_password = password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );

                            $stmtPassword = $pdo->prepare("
                                UPDATE tbl_dentists
                                SET password = :password
                                WHERE dentist_id = :dentist_id
                            ");

                            $stmtPassword->execute([
                                ':password' => $hashed_password,
                                ':dentist_id' => $dentist_id
                            ]);
                        }

                        $pdo->commit();

                        $success_message = "Dentist updated successfully.";

                        $dentist['first_name'] = $first_name;
                        $dentist['last_name'] = $last_name;
                        $dentist['specialization'] = $specialization;
                        $dentist['license_no'] = $license_no;
                        $dentist['status'] = $status;
                    }
                }

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log("Database Error (Update Dentist): " . $e->getMessage());

                $error_message = "An error occurred while updating the dentist. Please try again.";
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
        value="<?php
        echo htmlspecialchars(
            $_SESSION['csrf_token'],
            ENT_QUOTES,
            'UTF-8'
        );
        ?>"
      >

      <div class="form-grid">

        <div class="form-group">

          <label for="first_name">
            First Name *
          </label>

          <input
            type="text"
            id="first_name"
            name="first_name"
            value="<?php
            echo htmlspecialchars(
                $dentist['first_name'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            );
            ?>"
            maxlength="100"
            required
          >

        </div>

        <div class="form-group">

          <label for="last_name">
            Last Name *
          </label>

          <input
            type="text"
            id="last_name"
            name="last_name"
            value="<?php
            echo htmlspecialchars(
                $dentist['last_name'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            );
            ?>"
            maxlength="100"
            required
          >

        </div>

        <div class="form-group">

          <label for="specialization">
            Specialization / Services
          </label>

          <input
            type="text"
            id="specialization"
            name="specialization"
            value="<?php
            echo htmlspecialchars(
                $dentist['specialization'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            );
            ?>"
            maxlength="100"
            placeholder="e.g. General Dentistry, Orthodontics, Root Canal"
          >

        </div>

        <div class="form-group">

          <label for="license_no">
            License Number *
          </label>

          <input
            type="text"
            id="license_no"
            name="license_no"
            value="<?php
            echo htmlspecialchars(
                $dentist['license_no'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            );
            ?>"
            maxlength="50"
            placeholder="e.g. PRC-0123456"
            required
          >

        </div>

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
              <?php
              echo (($dentist['status'] ?? 'active') === 'active')
                  ? 'selected'
                  : '';
              ?>
            >
              Active
            </option>

            <option
              value="inactive"
              <?php
              echo (($dentist['status'] ?? '') === 'inactive')
                  ? 'selected'
                  : '';
              ?>
            >
              Inactive
            </option>

          </select>

        </div>

        <div class="form-group">

          <label for="password">
            New Password
          </label>

          <input
            type="password"
            id="password"
            name="password"
            minlength="8"
            placeholder="Leave blank to keep current password"
          >

          <span class="form-hint">
            Leave blank if you do not want to change the password.
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

<?php include __DIR__ . '/includes/footer.php'; ?>