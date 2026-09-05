<?php
$page_title = "Dentists";
$header_title = "Dentist Directory";

include __DIR__ . '/includes/header.php';

$stmtDentists = $pdo->query("
    SELECT 
        d.dentist_id,
        d.first_name,
        d.last_name,
        d.license_no,
        d.specialization,
        d.status,
        u.email
    FROM tbl_dentists d
    LEFT JOIN tbl_users u ON d.user_id = u.user_id
    ORDER BY d.dentist_id ASC
");
$dentists = $stmtDentists->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card">
  <div class="head">
    <h3>Active & Inactive Dentists</h3>
    <a href="add-dentist.php">+ Add Dentist</a>
  </div>
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>License No.</th>
          <th>Specialization</th>
          <th>Email</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($dentists)): ?>
          <?php foreach ($dentists as $d): ?>
            <tr>
              <td>#<?php echo htmlspecialchars((string)$d['dentist_id'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td class="patient-cell">Dr. <?php echo htmlspecialchars($d['first_name'] . ' ' . $d['last_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($d['license_no'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(!empty($d['specialization']) ? $d['specialization'] : 'General Dentistry', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($d['email'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
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