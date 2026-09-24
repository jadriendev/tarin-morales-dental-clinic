<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

$page_title = "Settings";
$header_title = "Account Settings";

$admins = [];
$dentists = [];
$patients = [];
$error_message = '';

try {
    $sql_admins = "
        SELECT
            admin_id,
            username,
            CONCAT(first_name, ' ', last_name) AS full_name,
            status
        FROM tbl_admins
        ORDER BY admin_id DESC
    ";

    $stmt_admins = $pdo->prepare($sql_admins);
    $stmt_admins->execute();
    $admins = $stmt_admins->fetchAll(PDO::FETCH_ASSOC);

    $sql_dentists = "
        SELECT
            dentist_id,
            username,
            CONCAT(first_name, ' ', last_name) AS full_name,
            license_no,
            specialization,
            status
        FROM tbl_dentists
        ORDER BY dentist_id DESC
    ";

    $stmt_dentists = $pdo->prepare($sql_dentists);
    $stmt_dentists->execute();
    $dentists = $stmt_dentists->fetchAll(PDO::FETCH_ASSOC);

    $sql_patients = "
        SELECT
            p.patient_id,
            u.email,
            u.username,
            CONCAT(
                p.first_name,
                ' ',
                IFNULL(p.middle_name, ''),
                ' ',
                p.last_name
            ) AS full_name,
            p.contact_number,
            p.date_registered,
            u.status
        FROM tbl_patients p
        INNER JOIN tbl_users u
            ON p.user_id = u.user_id
        ORDER BY p.patient_id DESC
    ";

    $stmt_patients = $pdo->prepare($sql_patients);
    $stmt_patients->execute();
    $patients = $stmt_patients->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Settings Database Error: " . $e->getMessage());
    $error_message = "Unable to load account information. Please try again.";
}
?>

<?php include __DIR__ . '/includes/header.php'; ?>

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
    padding: 12px;
  }

  .table-even th:first-child,
  .table-even td:first-child {
    padding-left: 24px;
  }

  .col-4-even th,
  .col-4-even td {
    width: 25%;
  }

  .col-6-even th,
  .col-6-even td {
    width: 16.666%;
  }

  @media (max-width: 768px) {
    .table-even {
      table-layout: auto;
      min-width: 650px;
    }

    .col-4-even th,
    .col-4-even td,
    .col-6-even th,
    .col-6-even td {
      width: auto;
    }

    .table-container {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
  }
</style>

<?php if (!empty($error_message)): ?>
  <div
    class="alert alert-danger"
    style="padding: 15px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; margin: 20px;"
  >
    <strong>Database Error:</strong>
    <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
  </div>
<?php endif; ?>

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

            <?php
            $status = strtolower($admin['status'] ?? 'inactive');

            $status_class = in_array(
                $status,
                ['active', 'inactive'],
                true
            ) ? $status : 'inactive';
            ?>

            <tr>
              <td>
                <?php echo htmlspecialchars(
                    (string)$admin['admin_id'],
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
              </td>

              <td>
                <?php echo htmlspecialchars(
                    $admin['username'],
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
              </td>

              <td>
                <?php echo htmlspecialchars(
                    $admin['full_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
              </td>

              <td>
                <span class="status <?php echo htmlspecialchars(
                    $status_class,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>">
                  <?php echo htmlspecialchars(
                      ucfirst($status),
                      ENT_QUOTES,
                      'UTF-8'
                  ); ?>
                </span>
              </td>
            </tr>

          <?php endforeach; ?>

        <?php else: ?>

          <tr>
            <td
              colspan="4"
              style="text-align: center; color: var(--text-muted);"
            >
              No administrator accounts found.
            </td>
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
          <th>Username</th>
          <th>Full Name</th>
          <th>License No.</th>
          <th>Specialization</th>
          <th>Status</th>
        </tr>
      </thead>

      <tbody>
        <?php if (!empty($dentists)): ?>

          <?php foreach ($dentists as $dentist): ?>

            <?php
            $status = strtolower($dentist['status'] ?? 'inactive');

            $status_class = in_array(
                $status,
                ['active', 'inactive'],
                true
            ) ? $status : 'inactive';
            ?>

            <tr>
              <td>
                <?php echo htmlspecialchars(
                    (string)$dentist['dentist_id'],
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
              </td>

              <td>
                <?php echo htmlspecialchars(
                    $dentist['username'],
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
              </td>

              <td>
                <?php echo htmlspecialchars(
                    $dentist['full_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
              </td>

              <td>
                <?php echo htmlspecialchars(
                    $dentist['license_no'],
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
              </td>

              <td>
                <?php echo htmlspecialchars(
                    $dentist['specialization'] ?? 'N/A',
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
              </td>

              <td>
                <span class="status <?php echo htmlspecialchars(
                    $status_class,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>">
                  <?php echo htmlspecialchars(
                      ucfirst($status),
                      ENT_QUOTES,
                      'UTF-8'
                  ); ?>
                </span>
              </td>
            </tr>

          <?php endforeach; ?>

        <?php else: ?>

          <tr>
            <td
              colspan="6"
              style="text-align: center; color: var(--text-muted);"
            >
              No dentist accounts found.
            </td>
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

            <?php
            $status = strtolower($patient['status'] ?? 'inactive');

            $status_class = in_array(
                $status,
                ['active', 'inactive'],
                true
            ) ? $status : 'inactive';

            $full_name = trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    $patient['full_name'] ?? ''
                )
            );
            ?>

            <tr>
              <td>
                <?php echo htmlspecialchars(
                    (string)$patient['patient_id'],
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
              </td>

              <td>
                <?php echo htmlspecialchars(
                    $full_name ?: 'Unknown Patient',
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
              </td>

              <td>
                <?php echo htmlspecialchars(
                    $patient['email'] ?? 'N/A',
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
              </td>

              <td>
                <?php echo htmlspecialchars(
                    $patient['contact_number'] ?? 'N/A',
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
              </td>

              <td>
                <?php
                echo htmlspecialchars(
                    !empty($patient['date_registered'])
                        ? date(
                            'M d, Y',
                            strtotime($patient['date_registered'])
                        )
                        : 'N/A',
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
              </td>

              <td>
                <span class="status <?php echo htmlspecialchars(
                    $status_class,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>">
                  <?php echo htmlspecialchars(
                      ucfirst($status),
                      ENT_QUOTES,
                      'UTF-8'
                  ); ?>
                </span>
              </td>
            </tr>

          <?php endforeach; ?>

        <?php else: ?>

          <tr>
            <td
              colspan="6"
              style="text-align: center; color: var(--text-muted);"
            >
              No patient accounts found.
            </td>
          </tr>

        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>