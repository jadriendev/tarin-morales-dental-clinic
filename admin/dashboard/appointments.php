<?php
$page_title = "Appointments";
$header_title = "Appointment Schedule";

include __DIR__ . '/includes/header.php';

$stmtApp = $pdo->query("
    SELECT 
        a.appointment_id,
        CONCAT(p.first_name, ' ', p.last_name) AS patient,
        a.appointment_date,
        TIME_FORMAT(a.appointment_time, '%h:%i %p') AS time,
        CONCAT('Dr. ', d.last_name) AS dentist,
        a.procedure_name,
        a.status
    FROM tbl_appointments a
    LEFT JOIN tbl_patients p ON a.patient_id = p.patient_id
    LEFT JOIN tbl_dentists d ON a.dentist_id = d.dentist_id
    ORDER BY a.appointment_date DESC, a.appointment_time ASC
");
$all_appointments = $stmtApp->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card">
  <div class="head">
    <h3>All Appointments</h3>
    <a href="add-appointment.php">+ Book Appointment</a>
  </div>
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Patient</th>
          <th>Date</th>
          <th>Time</th>
          <th>Dentist</th>
          <th>Procedure</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($all_appointments)): ?>
          <?php foreach ($all_appointments as $app): ?>
            <tr>
              <td><?php echo htmlspecialchars((string)$app['appointment_id'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td class="patient-cell"><?php echo htmlspecialchars($app['patient'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($app['appointment_date'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($app['time'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($app['dentist'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($app['procedure_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php $status_class = strtolower($app['status'] ?? 'pending'); ?>
                <span class="status <?php echo htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $app['status'] ?? 'Pending')), ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </td>
              <td>
                <a href="edit-appointment.php?id=<?php echo (int)$app['appointment_id']; ?>" style="color: var(--brand-purple, #9333ea); text-decoration: none; font-weight: 600;">Edit</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="8" style="text-align: center; color: var(--text-muted);">No appointments recorded.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>