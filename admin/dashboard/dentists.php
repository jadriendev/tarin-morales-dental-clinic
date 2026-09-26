<?php
require_once __DIR__ . '/../config.php';

$page_title = "Dentists";
$header_title = "Dentist Directory";

include __DIR__ . '/includes/header.php';

$dentists = [];
$error_message = '';

try {
    $stmtDentists = $pdo->query("
        SELECT 
            dentist_id,
            username,
            first_name,
            last_name,
            license_no,
            specialization,
            status
        FROM tbl_dentists
        ORDER BY dentist_id ASC
    ");

    $dentists = $stmtDentists->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database Error (Dentists): " . $e->getMessage());
    $error_message = "Unable to load the dentist directory. Please try again.";
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
    padding: 12px;
  }

  .table-even th:first-child,
  .table-even td:first-child {
    padding-left: 24px;
  }

  .status.active {
    background: #dcfce7;
    color: #15803d;
  }

  .status.inactive {
    background: #fee2e2;
    color: #b91c1c;
  }

  .alert-danger {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    padding: 12px 16px;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 20px;
  }

  @media (max-width: 768px) {
    .table-even {
      table-layout: auto;
      min-width: 650px;
    }

    .table-container {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
  }
</style>

<?php if (!empty($error_message)): ?>
  <div class="alert-danger">
    <i class="fa-solid fa-circle-exclamation"></i>
    <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
  </div>
<?php endif; ?>

<div class="card">

  <div class="head">
    <h3>Active & Inactive Dentists</h3>
    <a href="add-dentist.php">+ Add Dentist</a>
  </div>

  <div class="table-container">

    <table class="table-even">

      <thead>
        <tr>
          <th>ID</th>
          <th>Username</th>
          <th>Name</th>
          <th>License No.</th>
          <th>Specialization</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody>

        <?php if (!empty($dentists)): ?>

          <?php foreach ($dentists as $d): ?>

            <tr>

              <td>
                <?php
                echo htmlspecialchars(
                    (string)$d['dentist_id'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
              </td>

              <td>
                <?php
                echo htmlspecialchars(
                    $d['username'] ?? 'N/A',
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
              </td>

              <td class="patient-cell">
                Dr.
                <?php
                echo htmlspecialchars(
                    trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')),
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
              </td>

              <td>
                <?php
                echo htmlspecialchars(
                    $d['license_no'] ?? 'N/A',
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
              </td>

              <td>
                <?php
                echo htmlspecialchars(
                    !empty($d['specialization'])
                        ? $d['specialization']
                        : 'Not specified',
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
              </td>

              <td>

                <?php
                $status = $d['status'] ?? 'active';
                $status_class = strtolower($status);
                ?>

                <span class="status <?php echo htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php
                  echo htmlspecialchars(
                      ucfirst($status),
                      ENT_QUOTES,
                      'UTF-8'
                  );
                  ?>
                </span>

              </td>

              <td>

                <a
                  href="edit-dentist.php?id=<?php echo (int)$d['dentist_id']; ?>"
                  style="color: var(--brand-purple, #9333ea); text-decoration: none; font-weight: 600;"
                >
                  Edit
                </a>

              </td>

            </tr>

          <?php endforeach; ?>

        <?php else: ?>

          <tr>
            <td
              colspan="7"
              style="text-align: center; color: var(--text-muted);"
            >
              No dentists registered yet.
            </td>
          </tr>

        <?php endif; ?>

      </tbody>

    </table>

  </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>