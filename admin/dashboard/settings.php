<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = "Settings";
$header_title = "Account Settings";

include __DIR__ . '/includes/header.php';

$conn = $conn ?? $pdo ?? null;

if (!$conn) {
    echo '<div class="alert alert-danger" style="padding: 15px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; margin: 20px;">
            <strong>Database Error:</strong> Could not find an active database connection.
          </div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

try {
    $sql_admins = "SELECT admin_id, username, CONCAT(first_name, ' ', last_name) AS full_name, status FROM tbl_admins ORDER BY admin_id DESC";
    $stmt_admins = $conn->prepare($sql_admins);
    $stmt_admins->execute();
    $admins = $stmt_admins->fetchAll(PDO::FETCH_ASSOC);

    $sql_dentists = "SELECT d.dentist_id, u.email, CONCAT(d.first_name, ' ', d.last_name) AS full_name, d.license_no, d.specialization, d.status 
                    FROM tbl_dentists d 
                    JOIN tbl_users u ON d.user_id = u.user_id 
                    ORDER BY d.dentist_id DESC";
    $stmt_dentists = $conn->prepare($sql_dentists);
    $stmt_dentists->execute();
    $dentists = $stmt_dentists->fetchAll(PDO::FETCH_ASSOC);

    $sql_patients = "SELECT p.patient_id, u.email, CONCAT(p.first_name, ' ', IFNULL(p.middle_name, ''), ' ', p.last_name) AS full_name, p.contact_number, p.date_registered, u.status 
                     FROM tbl_patients p 
                     JOIN tbl_users u ON p.user_id = u.user_id 
                     ORDER BY p.patient_id DESC";
    $stmt_patients = $conn->prepare($sql_patients);
    $stmt_patients->execute();
    $patients = $stmt_patients->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo '<div class="alert alert-danger" style="padding: 15px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; margin: 20px;">
            <strong>Query Error:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '
          </div>';
    $admins = $dentists = $patients = [];
}
?>

<style>
  .table-even {
    table-layout: fixed;
    width: 100%;
  }
  .table-even th, 
  .table-even td {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    vertical-align: middle;
    padding-left: 12px;
    padding-right: 12px;
  }
  
  .table-even th:first-child,
  .table-even td:first-child {
    padding-left: 24px;
  }
  
  .col-4-even th, .col-4-even td { width: 25%; }
  .col-6-even th, .col-6-even td { width: 16.666%; }

  .copy-btn {
    background: #f0f0f0;
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 2px 6px;
    font-size: 11px;
    cursor: pointer;
    margin-left: 6px;
    flex-shrink: 0;
    transition: all 0.2s ease;
  }
  .copy-btn:hover {
    background: #e0e0e0;
  }
  .copy-btn.copied {
    background: #28a179;
    color: #fff;
    border-color: #28a179;
  }
  .copy-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    overflow: hidden;
    gap: 8px;
  }
  .copy-container span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  /* Mobile Responsive Breakpoint */
  @media (max-width: 768px) {
    .table-even {
      table-layout: auto;
      min-width: 650px;
    }
    .col-4-even th, .col-4-even td,
    .col-6-even th, .col-6-even td {
      width: auto;
    }
    .table-container {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
  }
</style>

<div class="card mb-4">
  <div class="head">
    <h3>Administrators</h3>
  </div>
  <div class="table-container">
    <table class="table-even col-4-even">
      <thead>
        <tr>
          <th>ID</th>
          <th>Username</th>
          <th>Full Name</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($admins)): ?>
          <?php foreach ($admins as $admin): ?>
            <tr>
              <td>
                <div class="copy-container">
                  <span>#<?php echo htmlspecialchars((string)$admin['admin_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                  <button type="button" class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars((string)$admin['admin_id'], ENT_QUOTES, 'UTF-8'); ?>', this)">Copy</button>
                </div>
              </td>
              <td>
                <div class="copy-container">
                  <span><?php echo htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                  <button type="button" class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8'); ?>', this)">Copy</button>
                </div>
              </td>
              <td><?php echo htmlspecialchars($admin['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php $status_class = strtolower($admin['status'] ?? 'active'); ?>
                <span class="status <?php echo htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $admin['status'] ?? 'Active')), ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="4" style="text-align: center; color: var(--text-muted);">No administrator accounts found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mb-4">
  <div class="head">
    <h3>Dentists</h3>
  </div>
  <div class="table-container">
    <table class="table-even col-6-even">
      <thead>
        <tr>
          <th>ID</th>
          <th>Full Name</th>
          <th>Email</th>
          <th>License No.</th>
          <th>Specialization</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($dentists)): ?>
          <?php foreach ($dentists as $dentist): ?>
            <tr>
              <td>
                <div class="copy-container">
                  <span>#<?php echo htmlspecialchars((string)$dentist['dentist_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                  <button type="button" class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars((string)$dentist['dentist_id'], ENT_QUOTES, 'UTF-8'); ?>', this)">Copy</button>
                </div>
              </td>
              <td><?php echo htmlspecialchars($dentist['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <div class="copy-container">
                  <span><?php echo htmlspecialchars($dentist['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                  <button type="button" class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($dentist['email'], ENT_QUOTES, 'UTF-8'); ?>', this)">Copy</button>
                </div>
              </td>
              <td>
                <div class="copy-container">
                  <span><?php echo htmlspecialchars($dentist['license_no'], ENT_QUOTES, 'UTF-8'); ?></span>
                  <button type="button" class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($dentist['license_no'], ENT_QUOTES, 'UTF-8'); ?>', this)">Copy</button>
                </div>
              </td>
              <td><?php echo htmlspecialchars($dentist['specialization'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php $status_class = strtolower($dentist['status'] ?? 'active'); ?>
                <span class="status <?php echo htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $dentist['status'] ?? 'Active')), ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" style="text-align: center; color: var(--text-muted);">No dentist accounts found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mb-4">
  <div class="head">
    <h3>Patients</h3>
  </div>
  <div class="table-container">
    <table class="table-even col-6-even">
      <thead>
        <tr>
          <th>ID</th>
          <th>Full Name</th>
          <th>Email</th>
          <th>Contact Number</th>
          <th>Date Registered</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($patients)): ?>
          <?php foreach ($patients as $patient): ?>
            <tr>
              <td>
                <div class="copy-container">
                  <span>#<?php echo htmlspecialchars((string)$patient['patient_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                  <button type="button" class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars((string)$patient['patient_id'], ENT_QUOTES, 'UTF-8'); ?>', this)">Copy</button>
                </div>
              </td>
              <td><?php echo htmlspecialchars(trim($patient['full_name']), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <div class="copy-container">
                  <span><?php echo htmlspecialchars($patient['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                  <button type="button" class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($patient['email'], ENT_QUOTES, 'UTF-8'); ?>', this)">Copy</button>
                </div>
              </td>
              <td>
                <div class="copy-container">
                  <span><?php echo htmlspecialchars($patient['contact_number'], ENT_QUOTES, 'UTF-8'); ?></span>
                  <button type="button" class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($patient['contact_number'], ENT_QUOTES, 'UTF-8'); ?>', this)">Copy</button>
                </div>
              </td>
              <td><?php echo htmlspecialchars(date('M d, Y', strtotime($patient['date_registered'])), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php $status_class = strtolower($patient['status'] ?? 'active'); ?>
                <span class="status <?php echo htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $patient['status'] ?? 'Active')), ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" style="text-align: center; color: var(--text-muted);">No patient accounts found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function copyToClipboard(text, buttonElement) {
  navigator.clipboard.writeText(text).then(function() {
    const originalText = buttonElement.innerText;
    buttonElement.innerText = "Copied!";
    buttonElement.classList.add("copied");
    setTimeout(function() {
      buttonElement.innerText = originalText;
      buttonElement.classList.remove("copied");
    }, 1500);
  }).catch(function(err) {
    console.error('Failed to copy: ', err);
  });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>