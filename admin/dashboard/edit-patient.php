<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

$page_title = "Edit Patient";
$header_title = "Edit Patient Record";

$patient_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$patient_id) {
    header("Location: patients.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_message = '';
$error_message = '';
$patient = null;

try {

    $stmt = $pdo->prepare("
        SELECT
            p.patient_id,
            p.user_id,
            p.first_name,
            p.middle_name,
            p.last_name,
            p.birth_date,
            p.sex,
            p.contact_number,
            p.address,
            p.date_registered,
            u.email,
            u.username,
            u.status AS account_status
        FROM tbl_patients p
        INNER JOIN tbl_users u
            ON p.user_id = u.user_id
        WHERE p.patient_id = :patient_id
        LIMIT 1
    ");

    $stmt->execute([
        ':patient_id' => $patient_id
    ]);

    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        header("Location: patients.php");
        exit;
    }

} catch (PDOException $e) {

    error_log("Database Error (Load Patient): " . $e->getMessage());

    $error_message = "Failed to load patient record. Please try again.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $patient) {

    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {

        $error_message = "Invalid security token. Please refresh and try again.";

    } else {

        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $birth_date = trim($_POST['birth_date'] ?? '');
        $sex = trim($_POST['sex'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $account_status = trim($_POST['account_status'] ?? 'active');

        if (
            $first_name === '' ||
            $last_name === '' ||
            $birth_date === '' ||
            $sex === '' ||
            $contact_number === '' ||
            $address === '' ||
            $email === ''
        ) {

            $error_message = "Please fill in all required fields.";

        } elseif (strlen($first_name) > 100) {

            $error_message = "First name must not exceed 100 characters.";

        } elseif (strlen($middle_name) > 100) {

            $error_message = "Middle name must not exceed 100 characters.";

        } elseif (strlen($last_name) > 100) {

            $error_message = "Last name must not exceed 100 characters.";

        } elseif (strlen($contact_number) > 20) {

            $error_message = "Contact number must not exceed 20 characters.";

        } elseif (!in_array($sex, ['Male', 'Female'], true)) {

            $error_message = "Invalid sex selected.";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error_message = "Please enter a valid email address.";

        } elseif (!in_array($account_status, ['active', 'inactive'], true)) {

            $error_message = "Invalid account status.";

        } else {

            $dateObject = DateTime::createFromFormat(
                'Y-m-d',
                $birth_date
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

                $error_message = "Please enter a valid birth date.";

            } elseif ($birth_date > date('Y-m-d')) {

                $error_message = "Birth date cannot be in the future.";

            } else {

                try {

                    $stmtCheck = $pdo->prepare("
                        SELECT user_id
                        FROM tbl_users
                        WHERE email = :email
                        AND user_id != :user_id
                        LIMIT 1
                    ");

                    $stmtCheck->execute([
                        ':email' => $email,
                        ':user_id' => $patient['user_id']
                    ]);

                    if ($stmtCheck->fetch()) {

                        $error_message = "The email address is already in use by another account.";

                    } else {

                        $pdo->beginTransaction();

                        $stmtUser = $pdo->prepare("
                            UPDATE tbl_users
                            SET
                                email = :email,
                                status = :status
                            WHERE user_id = :user_id
                        ");

                        $stmtUser->execute([
                            ':email' => $email,
                            ':status' => $account_status,
                            ':user_id' => $patient['user_id']
                        ]);

                        $stmtPatient = $pdo->prepare("
                            UPDATE tbl_patients
                            SET
                                first_name = :first_name,
                                middle_name = :middle_name,
                                last_name = :last_name,
                                birth_date = :birth_date,
                                sex = :sex,
                                contact_number = :contact_number,
                                address = :address
                            WHERE patient_id = :patient_id
                        ");

                        $stmtPatient->execute([
                            ':first_name' => $first_name,
                            ':middle_name' => $middle_name !== ''
                                ? $middle_name
                                : null,
                            ':last_name' => $last_name,
                            ':birth_date' => $birth_date,
                            ':sex' => $sex,
                            ':contact_number' => $contact_number,
                            ':address' => $address,
                            ':patient_id' => $patient_id
                        ]);

                        $pdo->commit();

                        $success_message = "Patient updated successfully.";

                        $patient['first_name'] = $first_name;
                        $patient['middle_name'] = $middle_name;
                        $patient['last_name'] = $last_name;
                        $patient['birth_date'] = $birth_date;
                        $patient['sex'] = $sex;
                        $patient['contact_number'] = $contact_number;
                        $patient['address'] = $address;
                        $patient['email'] = $email;
                        $patient['account_status'] = $account_status;
                    }

                } catch (PDOException $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log("Database Error (Update Patient): " . $e->getMessage());

                    $error_message = "An error occurred while updating the patient. Please try again.";
                }
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

  <?php if ($patient): ?>

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

        <div class="form-grid-3">

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
                  $patient['first_name'] ?? '',
                  ENT_QUOTES,
                  'UTF-8'
              );
              ?>"
              maxlength="100"
              required
            >

          </div>

          <div class="form-group">

            <label for="middle_name">
              Middle Name
            </label>

            <input
              type="text"
              id="middle_name"
              name="middle_name"
              value="<?php
              echo htmlspecialchars(
                  $patient['middle_name'] ?? '',
                  ENT_QUOTES,
                  'UTF-8'
              );
              ?>"
              maxlength="100"
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
                  $patient['last_name'] ?? '',
                  ENT_QUOTES,
                  'UTF-8'
              );
              ?>"
              maxlength="100"
              required
            >

          </div>

        </div>

        <div class="form-group">

          <label for="birth_date">
            Birth Date *
          </label>

          <input
            type="date"
            id="birth_date"
            name="birth_date"
            value="<?php
            echo htmlspecialchars(
                $patient['birth_date'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            );
            ?>"
            max="<?php echo date('Y-m-d'); ?>"
            required
          >

        </div>

        <div class="form-group">

          <label for="sex">
            Sex *
          </label>

          <select
            id="sex"
            name="sex"
            required
          >

            <option
              value="Male"
              <?php echo (($patient['sex'] ?? '') === 'Male')
                ? 'selected'
                : ''; ?>
            >
              Male
            </option>

            <option
              value="Female"
              <?php echo (($patient['sex'] ?? '') === 'Female')
                ? 'selected'
                : ''; ?>
            >
              Female
            </option>

          </select>

        </div>

        <div class="form-group">

          <label for="contact_number">
            Contact Number *
          </label>

          <input
            type="text"
            id="contact_number"
            name="contact_number"
            value="<?php
            echo htmlspecialchars(
                $patient['contact_number'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            );
            ?>"
            maxlength="20"
            required
          >

        </div>

        <div class="form-group">

          <label for="email">
            Email Address *
          </label>

          <input
            type="email"
            id="email"
            name="email"
            value="<?php
            echo htmlspecialchars(
                $patient['email'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            );
            ?>"
            maxlength="255"
            required
          >

        </div>

        <div class="form-group full-width">

          <label for="account_status">
            Account Status
          </label>

          <select
            id="account_status"
            name="account_status"
          >

            <option
              value="active"
              <?php echo (($patient['account_status'] ?? '') === 'active')
                ? 'selected'
                : ''; ?>
            >
              Active
            </option>

            <option
              value="inactive"
              <?php echo (($patient['account_status'] ?? '') === 'inactive')
                ? 'selected'
                : ''; ?>
            >
              Inactive
            </option>

          </select>

        </div>

        <div class="form-group full-width">

          <label for="address">
            Residential Address *
          </label>

          <textarea
            id="address"
            name="address"
            rows="3"
            required
          ><?php
          echo htmlspecialchars(
              $patient['address'] ?? '',
              ENT_QUOTES,
              'UTF-8'
          );
          ?></textarea>

        </div>

      </div>

      <div class="form-actions">

        <a
          href="patients.php"
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

  <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>