<?php
ob_start();

require_once __DIR__ . '/../config.php';

$page_title = "Add Dentist";
$header_title = "Add New Dentist";

include __DIR__ . '/includes/header.php';

$success_message = '';
$error_message = '';

$allowed_statuses = ['active', 'inactive'];

$username       = $_POST['username'] ?? '';
$first_name     = $_POST['first_name'] ?? '';
$last_name      = $_POST['last_name'] ?? '';
$license_no     = $_POST['license_no'] ?? '';
$specialization = $_POST['specialization'] ?? '';
$status         = $_POST['status'] ?? 'active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username       = trim($username);
    $first_name     = trim($first_name);
    $last_name      = trim($last_name);
    $license_no     = trim($license_no);
    $specialization = trim($specialization);
    $password       = $_POST['password'] ?? '';
    $status         = trim($status);

    if (
        empty($username) ||
        empty($first_name) ||
        empty($last_name) ||
        empty($license_no) ||
        empty($password)
    ) {

        $error_message = "Please fill in all required fields (Username, First Name, Last Name, License No., and Password).";

    } elseif (strlen($password) < 6) {

        $error_message = "Password must be at least 6 characters long.";

    } elseif (!in_array($status, $allowed_statuses, true)) {

        $error_message = "Invalid status selected.";

    } else {

        try {

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmtDentist = $pdo->prepare("
                INSERT INTO tbl_dentists (
                    username,
                    password,
                    first_name,
                    last_name,
                    license_no,
                    specialization,
                    status
                )
                VALUES (
                    :username,
                    :password,
                    :first_name,
                    :last_name,
                    :license_no,
                    :specialization,
                    :status
                )
            ");

            $stmtDentist->execute([
                ':username'       => $username,
                ':password'       => $hashed_password,
                ':first_name'     => $first_name,
                ':last_name'      => $last_name,
                ':license_no'     => $license_no,
                ':specialization' => $specialization !== '' ? $specialization : null,
                ':status'         => $status
            ]);

            header("Location: dentists.php?msg=added");
            exit;

        } catch (PDOException $e) {

            error_log("Database Error (Add Dentist): " . $e->getMessage());

            if ($e->getCode() === '23000') {

                if (
                    strpos($e->getMessage(), 'license_no') !== false ||
                    strpos($e->getMessage(), '1062') !== false
                ) {
                    $error_message = "The license number '{$license_no}' is already registered.";
                } else {
                    $error_message = "A database constraint was violated. Please check the information entered.";
                }

            } else {

                $error_message = "An error occurred while saving the dentist. Please try again.";
            }

        } catch (Exception $e) {

            error_log("System Error (Add Dentist): " . $e->getMessage());

            $error_message = "An unexpected error occurred. Please try again.";
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

  .btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
  }

  .btn-secondary {
    background: var(--gradient-subtle);
    color: var(--text-main);
    border: 1px solid var(--border-color);
  }

  .confirm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
    z-index: 9999;
  }

  .confirm-overlay.is-open {
    display: flex;
    animation: confirmFade 0.15s ease;
  }

  .confirm-modal {
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
    width: 100%;
    max-width: 520px;
    max-height: 90vh;
    overflow-y: auto;
    animation: confirmPop 0.2s ease;
  }

  .confirm-header {
    padding: 20px 24px 12px;
    border-bottom: 1px solid var(--border-color);
  }

  .confirm-header h3 {
    margin: 0 0 4px;
    font-size: 18px;
    color: var(--text-main);
  }

  .confirm-header p {
    margin: 0;
    font-size: 13px;
    color: var(--text-muted);
  }

  .confirm-body {
    padding: 8px 24px;
  }

  .confirm-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding: 12px 0;
    border-bottom: 1px dashed var(--border-color);
    font-size: 14px;
  }

  .confirm-row:last-child {
    border-bottom: none;
  }

  .confirm-label {
    flex: 0 0 120px;
    font-weight: 600;
    color: var(--text-muted);
  }

  .confirm-value {
    flex: 1;
    text-align: right;
    color: var(--text-main);
    font-weight: 600;
    word-break: break-word;
  }

  .confirm-value.is-empty {
    color: var(--text-muted);
    font-weight: 400;
    font-style: italic;
  }

  .confirm-value.is-password {
    letter-spacing: 2px;
  }

  .toggle-pass {
    background: none;
    border: none;
    padding: 0 0 0 8px;
    cursor: pointer;
    color: var(--brand-purple);
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0;
  }

  .confirm-badge {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
  }

  .confirm-badge.active {
    background: #dcfce7;
    color: #15803d;
  }

  .confirm-badge.inactive {
    background: #fee2e2;
    color: #b91c1c;
  }

  .confirm-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 24px 20px;
    border-top: 1px solid var(--border-color);
  }

  @keyframes confirmFade {
    from { opacity: 0; }
    to   { opacity: 1; }
  }

  @keyframes confirmPop {
    from { opacity: 0; transform: translateY(12px) scale(0.98); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
  }

  @media (max-width: 768px) {
    .form-grid {
      grid-template-columns: 1fr;
    }

    .form-group.full-width {
      grid-column: span 1;
    }

    .confirm-row {
      flex-direction: column;
      align-items: flex-start;
      gap: 4px;
    }

    .confirm-label {
      flex: none;
    }

    .confirm-value {
      text-align: left;
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

  <form id="dentistForm" action="" method="POST">

    <div class="form-grid">

      <div class="form-group">
        <label for="username">Username *</label>
        <input
          type="text"
          id="username"
          name="username"
          value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
          required
          placeholder="e.g. drsmith"
        >
      </div>

      <div class="form-group">
        <label for="password">Account Password *</label>
        <input
          type="password"
          id="password"
          name="password"
          required
          minlength="6"
          placeholder="Minimum 6 characters"
        >
      </div>

      <div class="form-group">
        <label for="first_name">First Name *</label>
        <input
          type="text"
          id="first_name"
          name="first_name"
          value="<?php echo htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8'); ?>"
          required
          placeholder="e.g. Jane"
        >
      </div>

      <div class="form-group">
        <label for="last_name">Last Name *</label>
        <input
          type="text"
          id="last_name"
          name="last_name"
          value="<?php echo htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8'); ?>"
          required
          placeholder="e.g. Smith"
        >
      </div>

      <div class="form-group">
        <label for="license_no">License No. *</label>
        <input
          type="text"
          id="license_no"
          name="license_no"
          value="<?php echo htmlspecialchars($license_no, ENT_QUOTES, 'UTF-8'); ?>"
          required
          placeholder="e.g. DENT-12345"
        >
      </div>

      <div class="form-group">
        <label for="specialization">Specialization</label>
        <input
          type="text"
          id="specialization"
          name="specialization"
          value="<?php echo htmlspecialchars($specialization, ENT_QUOTES, 'UTF-8'); ?>"
          placeholder="e.g. Orthodontics, General Dentistry"
        >
      </div>

      <div class="form-group full-width">
        <label for="status">Status</label>

        <select id="status" name="status">

          <?php foreach ($allowed_statuses as $st): ?>

            <option
              value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>"
              <?php echo ($status === $st) ? 'selected' : ''; ?>
            >
              <?php echo ucfirst($st); ?>
            </option>

          <?php endforeach; ?>

        </select>
      </div>

    </div>

    <div class="form-actions">

      <a href="dentists.php" class="btn btn-secondary">
        Cancel
      </a>

      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-user-doctor"></i>
        Save Dentist
      </button>

    </div>

  </form>
</div>

<div
  class="confirm-overlay"
  id="confirmOverlay"
  role="dialog"
  aria-modal="true"
  aria-labelledby="confirmTitle"
>
  <div class="confirm-modal">

    <div class="confirm-header">
      <h3 id="confirmTitle">Confirm Dentist Details</h3>
      <p>Please double-check the details below before saving.</p>
    </div>

    <div class="confirm-body">
      <div class="confirm-row">
        <span class="confirm-label">Username</span>
        <span class="confirm-value" id="cfUsername"></span>
      </div>
      <div class="confirm-row">
        <span class="confirm-label">Password</span>
        <span class="confirm-value">
          <span id="cfPassword" class="is-password"></span>
          <button type="button" class="toggle-pass" id="togglePass">Show</button>
        </span>
      </div>
      <div class="confirm-row">
        <span class="confirm-label">First Name</span>
        <span class="confirm-value" id="cfFirstName"></span>
      </div>
      <div class="confirm-row">
        <span class="confirm-label">Last Name</span>
        <span class="confirm-value" id="cfLastName"></span>
      </div>
      <div class="confirm-row">
        <span class="confirm-label">License No.</span>
        <span class="confirm-value" id="cfLicense"></span>
      </div>
      <div class="confirm-row">
        <span class="confirm-label">Specialization</span>
        <span class="confirm-value" id="cfSpecialization"></span>
      </div>
      <div class="confirm-row">
        <span class="confirm-label">Status</span>
        <span class="confirm-value" id="cfStatus"></span>
      </div>
    </div>

    <div class="confirm-footer">
      <button type="button" class="btn btn-secondary" id="confirmEdit">
        <i class="fa-solid fa-pen"></i>
        Edit
      </button>

      <button type="button" class="btn btn-primary" id="confirmSave">
        <i class="fa-solid fa-user-doctor"></i>
        Confirm &amp; Save
      </button>
    </div>

  </div>
</div>

<script>
(function () {
  var form       = document.getElementById('dentistForm');
  var overlay    = document.getElementById('confirmOverlay');
  var btnEdit    = document.getElementById('confirmEdit');
  var btnSave    = document.getElementById('confirmSave');
  var btnToggle  = document.getElementById('togglePass');
  var cfPassword = document.getElementById('cfPassword');

  var passwordVisible = false;

  function setValue(id, text, emptyText) {
    var el = document.getElementById(id);
    var value = (text || '').trim();

    if (value === '') {
      el.textContent = emptyText || '—';
      el.classList.add('is-empty');
    } else {
      el.textContent = value;
      el.classList.remove('is-empty');
    }
  }

  function renderPassword() {
    var pw = form.password.value;

    if (passwordVisible) {
      cfPassword.textContent = pw;
      cfPassword.style.letterSpacing = 'normal';
      btnToggle.textContent = 'Hide';
    } else {
      cfPassword.textContent = '•'.repeat(Math.min(pw.length, 20));
      cfPassword.style.letterSpacing = '2px';
      btnToggle.textContent = 'Show';
    }
  }

  function renderStatus() {
    var el = document.getElementById('cfStatus');
    var value = form.status.value;

    el.textContent = '';

    var badge = document.createElement('span');
    badge.className = 'confirm-badge ' + (value === 'inactive' ? 'inactive' : 'active');
    badge.textContent = value.charAt(0).toUpperCase() + value.slice(1);

    el.appendChild(badge);
  }

  function openModal() {
    setValue('cfUsername',       form.username.value);
    setValue('cfFirstName',      form.first_name.value);
    setValue('cfLastName',       form.last_name.value);
    setValue('cfLicense',        form.license_no.value);
    setValue('cfSpecialization', form.specialization.value, 'None');

    passwordVisible = false;
    renderPassword();
    renderStatus();

    btnSave.disabled = false;
    overlay.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    btnSave.focus();
  }

  function closeModal() {
    overlay.classList.remove('is-open');
    document.body.style.overflow = '';
    passwordVisible = false;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    openModal();
  });

  btnToggle.addEventListener('click', function () {
    passwordVisible = !passwordVisible;
    renderPassword();
  });

  btnEdit.addEventListener('click', closeModal);

  btnSave.addEventListener('click', function () {
    btnSave.disabled = true;
    form.submit();
  });

  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) closeModal();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && overlay.classList.contains('is-open')) {
      closeModal();
    }
  });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>