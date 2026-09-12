<?php
ob_start();

$page_title = "Dashboard";
$header_title = "Dashboard Overview";

include __DIR__ . '/includes/header.php';

// Resolve Admin display name safely
$admin_display_name = $_SESSION['admin_name'] ?? $admin_name ?? 'Admin';

$total_patients            = 0;
$todays_appointments_count = 0;
$active_dentists           = 0;
$todays_walkins            = 0;
$active_patients           = 0;
$completed_cases           = 0;

$appointments  = [];
$error_message = '';

try {
    // 1. Fetch Summary Statistics
    $statsStmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM tbl_patients) AS total_patients,
            (SELECT COUNT(*) FROM tbl_appointments WHERE appointment_date = CURDATE()) AS todays_appointments,
            (SELECT COUNT(*) FROM tbl_dentists WHERE LOWER(status) = 'active') AS active_dentists,
            (SELECT COUNT(*) FROM tbl_appointments WHERE appointment_date = CURDATE() AND LOWER(reason) LIKE '%walk-in%') AS todays_walkins,
            (SELECT COUNT(DISTINCT patient_id) FROM tbl_appointments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)) AS active_patients,
            (SELECT COUNT(DISTINCT patient_id) FROM tbl_dental_records) AS completed_cases
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    if ($stats) {
        $total_patients            = (int) ($stats['total_patients'] ?? 0);
        $todays_appointments_count = (int) ($stats['todays_appointments'] ?? 0);
        $active_dentists           = (int) ($stats['active_dentists'] ?? 0);
        $todays_walkins            = (int) ($stats['todays_walkins'] ?? 0);
        $active_patients           = (int) ($stats['active_patients'] ?? 0);
        $completed_cases           = (int) ($stats['completed_cases'] ?? 0);
    }

    // 2. Fetch Recent Appointments
    $stmtApp = $pdo->query("
        SELECT 
            CONCAT(p.first_name, ' ', p.last_name) AS patient,
            TIME_FORMAT(a.appointment_time, '%h:%i %p') AS time,
            CASE 
                WHEN d.last_name IS NOT NULL AND d.last_name != '' THEN CONCAT('Dr. ', d.first_name, ' ', d.last_name)
                ELSE 'Unassigned'
            END AS dentist,
            a.procedure_name AS procedure_title,
            a.status
        FROM tbl_appointments a
        LEFT JOIN tbl_patients p ON a.patient_id = p.patient_id
        LEFT JOIN tbl_dentists d ON a.dentist_id = d.dentist_id
        ORDER BY a.appointment_date DESC, a.appointment_time ASC
        LIMIT 5
    ");
    $appointments = $stmtApp->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database Error (Dashboard): " . $e->getMessage());
    $error_message = "Unable to load dashboard metrics. Please try refreshing the page.";
}

// Donut Chart Math
$inactive_patients = max(0, $total_patients - $active_patients);
$total_overview    = $total_patients;

if ($total_overview > 0) {
    $deg_active    = min(360, (int) round(($active_patients / $total_overview) * 360));
    $deg_completed = min(360, $deg_active + (int) round(($completed_cases / $total_overview) * 360));
} else {
    $deg_active    = 0;
    $deg_completed = 0;
}
?>

<style>
  .welcome {
    margin-bottom: 24px;
  }

  .stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 32px;
  }

  .card {
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 20px;
    box-shadow: var(--shadow-subtle);
  }

  .stat-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
  }

  .stat-icon {
    width: 46px;
    height: 46px;
    border-radius: var(--radius-md);
    display: grid;
    place-items: center;
    font-size: 20px;
  }

  .stat-icon.cyan { background: #e0f2fe; color: var(--brand-cyan, #0284c7); }
  .stat-icon.purple { background: #f3e8ff; color: var(--brand-purple, #9333ea); }
  .stat-icon.magenta { background: #fae8ff; color: var(--brand-magenta, #d946ef); }
  .stat-icon.amber { background: #fef3c7; color: #d97706; }

  .card .title { font-size: 13px; font-weight: 600; color: var(--text-muted); }
  .card .num { font-size: 34px; font-weight: 800; letter-spacing: -1px; margin-bottom: 12px; color: var(--text-main); }
  .card .link {
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: gap 0.2s;
    background-image: linear-gradient(90deg, var(--brand-blue, #2563eb) 0%, var(--brand-purple, #9333ea) 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
  }
  .card .link:hover { 
    gap: 10px; 
    background-image: linear-gradient(90deg, var(--brand-blue, #2563eb) 0%, var(--brand-magenta, #d946ef) 100%); 
  }

  .actions { 
    display: grid; 
    grid-template-columns: repeat(3, 1fr); 
    gap: 20px; 
    margin-bottom: 24px; 
  }

  .action {
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    text-decoration: none;
    color: var(--text-main);
    box-shadow: var(--shadow-subtle);
    transition: all 0.2s ease;
  }

  .action:hover {
    background: var(--gradient-brand, linear-gradient(135deg, #2563eb, #9333ea));
    color: white;
    border-color: transparent;
    transform: translateY(-3px);
    box-shadow: 0 10px 22px rgba(147, 51, 234, 0.3);
  }

  .action i {
    font-size: 18px;
    width: 40px;
    height: 40px;
    border-radius: var(--radius-md);
    display: grid;
    place-items: center;
    transition: all 0.2s ease;
    background-image: linear-gradient(135deg, var(--brand-blue, #2563eb) 0%, var(--brand-purple, #9333ea) 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .action:hover i { 
    background: rgba(255, 255, 255, 0.25); 
    -webkit-text-fill-color: white; 
    color: white; 
  }
  
  .action span { font-size: 14px; font-weight: 700; }

  .grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
  .overview { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 10px 0; gap: 24px; }
  
  .donut {
    width: 160px;
    height: 160px;
    border-radius: 50%;
    background: conic-gradient(
      #9333ea 0deg <?php echo $deg_active; ?>deg, 
      #0284c7 <?php echo $deg_active; ?>deg <?php echo $deg_completed; ?>deg, 
      #cbd5e1 <?php echo $deg_completed; ?>deg 360deg
    );
    display: grid;
    place-items: center;
    position: relative;
    box-shadow: 0 4px 15px rgba(147, 51, 234, 0.15);
  }

  .donut-label {
    position: absolute;
    width: 110px;
    height: 110px;
    background: var(--surface);
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.04);
    font-weight: 800;
    font-size: 22px;
    line-height: 1.1;
    color: var(--text-main);
    text-align: center;
  }

  .donut-label span {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
  }

  .legend { width: 100%; display: flex; flex-direction: column; gap: 12px; }
  .legend-item { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--text-muted); font-weight: 600; }
  .legend-info { display: flex; align-items: center; gap: 8px; }
  .dot { width: 12px; height: 12px; border-radius: 50%; }
  .dot.active { background: #9333ea; }
  .dot.completed { background: #0284c7; }
  .dot.inactive { background: #cbd5e1; }
  .legend-item b { color: var(--text-main); font-weight: 800; }

  /* Table & Status Badges */
  .head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
  }
  .head h3 { font-size: 16px; font-weight: 700; color: var(--text-main); margin: 0; }
  .head a { font-size: 13px; font-weight: 600; color: var(--brand-purple, #9333ea); text-decoration: none; }

  .table-container { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
  th, td { padding: 12px 14px; border-bottom: 1px solid var(--border-color); }
  th { font-size: 12px; text-transform: uppercase; color: var(--text-muted); font-weight: 600; background: var(--bg-body, #f9fafb); }
  .patient-cell { font-weight: 600; color: var(--text-main); }

  .status {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    display: inline-block;
  }
  .status.confirmed { background: #dcfce7; color: #15803d; }
  .status.pending { background: #fef3c7; color: #b45309; }
  .status.completed { background: #e0f2fe; color: #0369a1; }
  .status.cancelled { background: #fee2e2; color: #b91c1c; }

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

  @media (max-width: 1200px) {
    .stats { grid-template-columns: repeat(2, 1fr); }
    .grid { grid-template-columns: 1fr; }
  }
  @media (max-width: 768px) {
    .stats, .actions { grid-template-columns: 1fr; }
  }
</style>

<!-- Welcome Section -->
<div class="welcome">
  <h2>Welcome back, <?php echo htmlspecialchars($admin_display_name, ENT_QUOTES, 'UTF-8'); ?>!</h2>
  <p>Here's what's happening at your clinic today.</p>
</div>

<?php if (!empty($error_message)): ?>
  <div class="alert-danger">
    <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error_message; ?>
  </div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="actions">
  <a class="action" href="add-patient.php">
    <i class="fa-solid fa-user-plus"></i>
    <span>Add Patient</span>
  </a>
  <a class="action" href="add-appointment.php">
    <i class="fa-solid fa-calendar-plus"></i>
    <span>New Appointment</span>
  </a>
  <a class="action" href="patient-history.php">
    <i class="fa-solid fa-file-medical"></i>
    <span>Patient History</span>
  </a>
</div>

<!-- Key Statistics Grid -->
<div class="stats">
  <div class="card">
    <div class="stat-card-header">
      <span class="title">Total Patients</span>
      <div class="stat-icon cyan"><i class="fa-solid fa-users"></i></div>
    </div>
    <div class="num"><?php echo $total_patients; ?></div>
    <a class="link" href="patients.php">View all patients <i class="fa-solid fa-arrow-right"></i></a>
  </div>

  <div class="card">
    <div class="stat-card-header">
      <span class="title">Today's Appointments</span>
      <div class="stat-icon purple"><i class="fa-regular fa-calendar"></i></div>
    </div>
    <div class="num"><?php echo $todays_appointments_count; ?></div>
    <a class="link" href="appointments.php">View schedule <i class="fa-solid fa-arrow-right"></i></a>
  </div>

  <div class="card">
    <div class="stat-card-header">
      <span class="title">Active Dentists</span>
      <div class="stat-icon magenta"><i class="fa-solid fa-user-doctor"></i></div>
    </div>
    <div class="num"><?php echo $active_dentists; ?></div>
    <a class="link" href="dentists.php">View staff <i class="fa-solid fa-arrow-right"></i></a>
  </div>

  <div class="card">
    <div class="stat-card-header">
      <span class="title">Today's Walk-ins</span>
      <div class="stat-icon amber"><i class="fa-solid fa-person-walking"></i></div>
    </div>
    <div class="num"><?php echo $todays_walkins; ?></div>
    <a class="link" href="appointments.php">View schedule <i class="fa-solid fa-arrow-right"></i></a>
  </div>
</div>

<!-- Main Table and Overview Section -->
<div class="grid">
  <div class="card">
    <div class="head">
      <h3>Recent Appointments</h3>
      <a href="appointments.php">View All</a>
    </div>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Patient</th>
            <th>Time</th>
            <th>Dentist</th>
            <th>Procedure</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($appointments)): ?>
            <?php foreach ($appointments as $app): ?>
              <tr>
                <td class="patient-cell"><?php echo htmlspecialchars($app['patient'] ?? 'Unknown Patient', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($app['time'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($app['dentist'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($app['procedure_title'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                  <?php $status_class = strtolower($app['status'] ?? 'pending'); ?>
                  <span class="status <?php echo htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $app['status'] ?? 'Pending')), ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">No appointments found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="head">
      <h3>Patient Overview</h3>
    </div>
    <div class="overview">
      <div class="donut">
        <div class="donut-label">
          <?php echo $total_overview; ?>
          <span>Total</span>
        </div>
      </div>
      <div class="legend">
        <div class="legend-item">
          <div class="legend-info">
            <span class="dot active"></span>
            <span>Active Patients</span>
          </div>
          <b>
            <?php echo $active_patients; ?> 
            <small style="color: var(--text-muted); font-weight: 600;">
              (<?php echo $total_overview > 0 ? round(($active_patients / $total_overview) * 100) : 0; ?>%)
            </small>
          </b>
        </div>

        <div class="legend-item">
          <div class="legend-info">
            <span class="dot completed"></span>
            <span>Completed Cases</span>
          </div>
          <b>
            <?php echo $completed_cases; ?> 
            <small style="color: var(--text-muted); font-weight: 600;">
              (<?php echo $total_overview > 0 ? round(($completed_cases / $total_overview) * 100) : 0; ?>%)
            </small>
          </b>
        </div>

        <div class="legend-item">
          <div class="legend-info">
            <span class="dot inactive"></span>
            <span>Inactive Patients</span>
          </div>
          <b>
            <?php echo $inactive_patients; ?> 
            <small style="color: var(--text-muted); font-weight: 600;">
              (<?php echo $total_overview > 0 ? round(($inactive_patients / $total_overview) * 100) : 0; ?>%)
            </small>
          </b>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>