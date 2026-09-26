<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

$page_title = "Account Settings";
$header_title = "Account Settings";

if (empty($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php?error=unauthorized");
    exit;
}

$admin_id = (int) $_SESSION['admin_id'];

$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

$success_message = '';
$error_message = '';

$stmt = $pdo->prepare("
    SELECT
        admin_id,
        username,
        first_name,
        last_name,
        status
    FROM tbl_admins
    WHERE admin_id = :admin_id
    LIMIT 1
");

$stmt->execute([
    'admin_id' => $admin_id
]);

$admin = $stmt->fetch();

if (!$admin) {
    session_destroy();
    header("Location: ../login.php?error=unauthorized");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submitted_token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
        $error_message = "Invalid security token. Please refresh the page and try again.";
    } else {

        $username = trim($_POST['username'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($username === '') {
            $error_message = "Username is required.";
        } elseif (strlen($username) > 50) {
            $error_message = "Username must not exceed 50 characters.";
        } elseif ($first_name === '') {
            $error_message = "First name is required.";
        } elseif (strlen($first_name) > 100) {
            $error_message = "First name must not exceed 100 characters.";
        } elseif ($last_name === '') {
            $error_message = "Last name is required.";
        } elseif (strlen($last_name) > 100) {
            $error_message = "Last name must not exceed 100 characters.";
        } elseif ($password !== '' && strlen($password) < 8) {
            $error_message = "New password must be at least 8 characters.";
        } elseif ($password !== '' && $password !== $confirm_password) {
            $error_message = "New password and confirm password do not match.";
        } else {

            $stmtCheck = $pdo->prepare("
                SELECT admin_id
                FROM tbl_admins
                WHERE username = :username
                AND admin_id != :admin_id
                LIMIT 1
            ");

            $stmtCheck->execute([
                'username' => $username,
                'admin_id' => $admin_id
            ]);

            if ($stmtCheck->fetch()) {
                $error_message = "That username is already being used.";
            } else {

                try {

                    $pdo->beginTransaction();

                    if ($password !== '') {

                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                        $stmtUpdate = $pdo->prepare("
                            UPDATE tbl_admins
                            SET
                                username = :username,
                                first_name = :first_name,
                                last_name = :last_name,
                                password = :password
                            WHERE admin_id = :admin_id
                        ");

                        $stmtUpdate->execute([
                            'username' => $username,
                            'first_name' => $first_name,
                            'last_name' => $last_name,
                            'password' => $hashed_password,
                            'admin_id' => $admin_id
                        ]);

                    } else {

                        $stmtUpdate = $pdo->prepare("
                            UPDATE tbl_admins
                            SET
                                username = :username,
                                first_name = :first_name,
                                last_name = :last_name
                            WHERE admin_id = :admin_id
                        ");

                        $stmtUpdate->execute([
                            'username' => $username,
                            'first_name' => $first_name,
                            'last_name' => $last_name,
                            'admin_id' => $admin_id
                        ]);
                    }

                    $pdo->commit();

                    $admin['username'] = $username;
                    $admin['first_name'] = $first_name;
                    $admin['last_name'] = $last_name;

                    $_SESSION['admin_name'] = $first_name . ' ' . $last_name;

                    $success_message = "Account information updated successfully.";

                } catch (PDOException $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log("Account Settings Update Error: " . $e->getMessage());
                    $error_message = "Unable to update your account. Please try again.";
                }
            }
        }
    }
}

include __DIR__ . '/includes/header.php';

?>

<style>
  .settings-wrapper {
    max-width: 900px;
    margin: 0 auto;
  }

  .settings-card {
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 30px;
    box-shadow: var(--shadow-subtle);
    position: relative;
    overflow: hidden;
  }

  .settings-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient-brand);
  }

  .settings-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 28px;
  }

  .settings-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: var(--gradient-subtle);
    color: var(--brand-purple);
    display: grid;
    place-items: center;
    font-size: 20px;
  }

  .settings-header h2 {
    font-size: 20px;
    font-weight: 800;
    margin-bottom: 4px;
  }

  .settings-header p {
    font-size: 13px;
    color: var(--text-muted);
  }

  .alert {
    padding: 13px 16px;
    border-radius: var(--radius-sm);
    margin-bottom: 20px;
    font-size: 13px;
    font-weight: 600;
  }

  .alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
  }

  .alert-error {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
  }

  .form-section {
    margin-bottom: 28px;
  }

  .form-section:last-of-type {
    margin-bottom: 0;
  }

  .section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 15px;
    font-weight: 800;
    color: var(--text-main);
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
  }

  .section-title i {
    color: var(--brand-purple);
  }

  .form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
  }

  .form-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
  }

  .form-group.full {
    grid-column: 1 / -1;
  }

  .form-group label {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-main);
  }

  .form-group input {
    width: 100%;
    height: 44px;
    padding: 0 13px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    background: #fff;
    color: var(--text-main);
    font-family: inherit;
    font-size: 13px;
    outline: none;
    transition: all 0.2s ease;
  }

  .form-group input:focus {
    border-color: var(--brand-purple);
    box-shadow: 0 0 0 3px rgba(147, 51, 234, 0.08);
  }

  .form-group input[readonly] {
    background: #f8fafc;
    color: var(--text-muted);
    cursor: not-allowed;
  }

  .form-help {
    font-size: 11px;
    color: var(--text-muted);
  }

  .status-display {
    display: flex;
    align-items: center;
    gap: 8px;
    height: 44px;
  }

  .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
  }

  .status-badge.active {
    background: #dcfce7;
    color: #15803d;
  }

  .status-badge.inactive {
    background: #fef2f2;
    color: #b91c1c;
  }

  .status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
  }

  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
  }

  .btn {
    min-height: 42px;
    padding: 0 18px;
    border-radius: var(--radius-sm);
    border: 1px solid transparent;
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s ease;
  }

  .btn-cancel {
    background: #f8fafc;
    color: var(--text-muted);
    border-color: var(--border-color);
  }

  .btn-cancel:hover {
    background: #f1f5f9;
    color: var(--text-main);
  }

  .btn-primary {
    background: var(--gradient-brand);
    color: white;
    box-shadow: 0 5px 14px rgba(147, 51, 234, 0.2);
  }

  .btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 18px rgba(147, 51, 234, 0.3);
  }

  @media (max-width: 700px) {
    .settings-card {
      padding: 22px;
    }

    .form-grid {
      grid-template-columns: 1fr;
    }

    .form-group.full {
      grid-column: auto;
    }
  }

  @media (max-width: 480px) {
    .settings-card {
      padding: 18px;
    }

    .settings-header {
      align-items: flex-start;
    }

    .settings-icon {
      width: 46px;
      height: 46px;
      flex-shrink: 0;
    }

    .form-actions {
      flex-direction: column-reverse;
    }

    .btn {
      width: 100%;
    }
  }
</style>

<div class="settings-wrapper">

  <div class="settings-card">

    <div class="settings-header">
      <div class="settings-icon">
        <i class="fa-solid fa-user-gear"></i>
      </div>

      <div>
        <h2>Account Settings</h2>
        <p>Update your administrator account information.</p>
      </div>
    </div>

    <?php if ($success_message !== ''): ?>
      <div class="alert alert-success">
        <i class="fa-solid fa-circle-check"></i>
        <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <?php if ($error_message !== ''): ?>
      <div class="alert alert-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="account-settings.php">

      <input
        type="hidden"
        name="csrf_token"
        value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>"
      >

      <div class="form-section">

        <div class="section-title">
          <i class="fa-solid fa-user"></i>
          <span>Personal Information</span>
        </div>

        <div class="form-grid">

          <div class="form-group">
            <label for="first_name">First Name</label>
            <input
              type="text"
              id="first_name"
              name="first_name"
              maxlength="100"
              value="<?php echo htmlspecialchars($admin['first_name'], ENT_QUOTES, 'UTF-8'); ?>"
              required
            >
          </div>

          <div class="form-group">
            <label for="last_name">Last Name</label>
            <input
              type="text"
              id="last_name"
              name="last_name"
              maxlength="100"
              value="<?php echo htmlspecialchars($admin['last_name'], ENT_QUOTES, 'UTF-8'); ?>"
              required
            >
          </div>

        </div>

      </div>

      <div class="form-section">

        <div class="section-title">
          <i class="fa-solid fa-user-lock"></i>
          <span>Login Information</span>
        </div>

        <div class="form-grid">

          <div class="form-group">
            <label for="username">Username</label>
            <input
              type="text"
              id="username"
              name="username"
              maxlength="50"
              value="<?php echo htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8'); ?>"
              required
            >
          </div>

          <div class="form-group">
            <label>Account Status</label>

            <div class="status-display">

              <span class="status-badge <?php echo htmlspecialchars($admin['status'], ENT_QUOTES, 'UTF-8'); ?>">
                <span class="status-dot"></span>

                <?php echo htmlspecialchars(
                    ucfirst($admin['status']),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>

              </span>

            </div>
          </div>

        </div>

      </div>

      <div class="form-section">

        <div class="section-title">
          <i class="fa-solid fa-lock"></i>
          <span>Change Password</span>
        </div>

        <div class="form-grid">

          <div class="form-group">
            <label for="password">New Password</label>

            <input
              type="password"
              id="password"
              name="password"
              minlength="8"
              autocomplete="new-password"
              placeholder="Enter new password"
            >

            <span class="form-help">
              Leave blank if you do not want to change your password.
            </span>
          </div>

          <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>

            <input
              type="password"
              id="confirm_password"
              name="confirm_password"
              minlength="8"
              autocomplete="new-password"
              placeholder="Confirm new password"
            >
          </div>

        </div>

      </div>

      <div class="form-actions">

        <a href="dashboard.php" class="btn btn-cancel">
            Cancel
        </a>

        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-floppy-disk"></i>
          Save Changes
        </button>

      </div>

    </form>

  </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>