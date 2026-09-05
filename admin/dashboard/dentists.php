<?php
$page_title = "Dentists";
$header_title = "Dentist Directory";

include __DIR__ . '/includes/header.php';

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
              <td><?php echo htmlspecialchars((string)$d['dentist_id'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($d['username'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
              <td class="patient-cell">Dr. <?php echo htmlspecialchars($d['first_name'] . ' ' . $d['last_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($d['license_no'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(!empty($d['specialization']) ? $d['specialization'] : 'General Dentistry', ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php $status_class = strtolower($d['status'] ?? 'active'); ?>
                <span class="status <?php echo htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars(ucfirst($d['status'] ?? 'Active'), ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </td>
              <td>
                <a href="edit-dentist.php?id=<?php echo (int)$d['dentist_id']; ?>" style="color: var(--brand-purple, #9333ea); text-decoration: none; font-weight: 600;">Edit</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="7" style="text-align: center; color: var(--text-muted);">No dentists registered yet.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>