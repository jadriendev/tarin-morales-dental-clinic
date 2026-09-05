<?php
$page_title = "Patient History";
$header_title = "Medical & Dental History";

include __DIR__ . '/includes/header.php';

$stmtHistory = $pdo->query("
    SELECT 
        r.record_id,
        CONCAT(p.first_name, ' ', p.last_name) AS patient,
        r.diagnosis,
        r.treatment_summary,
        r.remarks,
        r.record_date,
        CONCAT('Dr. ', d.last_name) AS dentist
    FROM tbl_dental_records r
    LEFT JOIN tbl_patients p ON r.patient_id = p.patient_id
    LEFT JOIN tbl_dentists d ON r.dentist_id = d.dentist_id
    ORDER BY r.record_date DESC
");
$history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card">
  <div class="head">
    <h3>Dental Treatment Records</h3>
  </div>
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>Record ID</th>
          <th>Patient Name</th>
          <th>Record Date</th>
          <th>Attending Dentist</th>
          <th>Diagnosis</th>
          <th>Treatment Summary</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($history)): ?>
          <?php foreach ($history as $h): ?>
            <tr>
              <td>#<?php echo htmlspecialchars((string)$h['record_id'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td class="patient-cell"><?php echo htmlspecialchars($h['patient'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(!empty($h['record_date']) ? date('M d, Y g:i A', strtotime($h['record_date'])) : 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($h['dentist'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($h['diagnosis'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($h['treatment_summary'] ?? 'No summary available', ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <a href="edit-history.php?id=<?php echo (int)$h['record_id']; ?>" style="color: var(--brand-purple, #9333ea); text-decoration: none; font-weight: 600;">Edit</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="7" style="text-align: center; color: var(--text-muted);">No medical records found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>