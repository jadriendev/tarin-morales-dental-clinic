<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    $_SESSION['admin_id'] = 1; 
}

$db_host = '127.0.0.1';
$db_name = 'tarin_morales_dental_clinic';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database Connection Failed: " . htmlspecialchars($e->getMessage()));
}

$admin_id = $_SESSION['admin_id'];
$stmtAdmin = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM tbl_admins WHERE admin_id = ?");
$stmtAdmin->execute([$admin_id]);
$admin_data = $stmtAdmin->fetch();
$admin_name = $admin_data['full_name'] ?? "Admin";

$total_patients = (int) $pdo->query("SELECT COUNT(*) FROM tbl_patients")->fetchColumn();
$todays_appointments_count = (int) $pdo->query("SELECT COUNT(*) FROM tbl_appointments WHERE appointment_date = CURDATE()")->fetchColumn();
$active_dentists = (int) $pdo->query("SELECT COUNT(*) FROM tbl_dentists WHERE status = 'active'")->fetchColumn();
$todays_walkins = (int) $pdo->query("SELECT COUNT(*) FROM tbl_appointments WHERE appointment_date = CURDATE() AND reason LIKE '%walk-in%'")->fetchColumn();

$active_patients = (int) $pdo->query("
    SELECT COUNT(DISTINCT patient_id) 
    FROM tbl_appointments 
    WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
")->fetchColumn();

$completed_cases = (int) $pdo->query("
    SELECT COUNT(DISTINCT patient_id) 
    FROM tbl_dental_records
")->fetchColumn();

$inactive_patients = max(0, $total_patients - $active_patients);
$total_overview = $total_patients;

if ($total_overview > 0) {
    $deg_active = round(($active_patients / $total_overview) * 360);
    $deg_completed = $deg_active + round(($completed_cases / $total_overview) * 360);
    $deg_inactive = $deg_completed + round(($inactive_patients / $total_overview) * 360);
} else {
    $deg_active = 0;
    $deg_completed = 0;
    $deg_inactive = 360;
}

$stmtApp = $pdo->query("
    SELECT 
        CONCAT(p.first_name, ' ', p.last_name) AS patient,
        TIME_FORMAT(a.appointment_time, '%h:%i %p') AS time,
        CONCAT('Dr. ', d.last_name) AS dentist,
        a.procedure_name AS procedure_title,
        a.status
    FROM tbl_appointments a
    LEFT JOIN tbl_patients p ON a.patient_id = p.patient_id
    LEFT JOIN tbl_dentists d ON a.dentist_id = d.dentist_id
    ORDER BY a.appointment_date DESC, a.appointment_time ASC
    LIMIT 5
");
$appointments = $stmtApp->fetchAll();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tarin-Morales Dental Clinic Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-main: #f0f4f8;
      --surface: #ffffff;
      --brand-cyan: #0284c7;
      --brand-blue: #2563eb;
      --brand-purple: #9333ea;
      --brand-magenta: #c026d3;
      --gradient-brand: linear-gradient(135deg, #0284c7 0%, #2563eb 45%, #9333ea 80%, #c026d3 100%);
      --gradient-subtle: linear-gradient(135deg, #e0f2fe 0%, #f3e8ff 100%);
      --text-main: #0f172a;
      --text-muted: #64748b;
      --border-color: #e2e8f0;
      --border-hover: #cbd5e1;
      --radius-lg: 16px;
      --radius-md: 12px;
      --radius-sm: 8px;
      --shadow-subtle: 0 4px 20px -2px rgba(147, 51, 234, 0.06);
      --shadow-hover: 0 10px 25px -5px rgba(147, 51, 234, 0.2);
      
      --status-confirmed-bg: #dcfce7;
      --status-confirmed-text: #15803d;
      --status-pending-bg: #fef9c3;
      --status-pending-text: #a16207;
      --status-completed-bg: #f3e8ff;
      --status-completed-text: #7e22ce;
      --status-cancelled-bg: #fef2f2;
      --status-cancelled-text: #b91c1c;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--bg-main);
      color: var(--text-main);
      display: flex;
      justify-content: center;
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
    }

    .app-container {
      width: 100%;
      max-width: 1600px;
      display: flex;
      position: relative;
      background: var(--bg-main);
      box-shadow: 0 0 50px rgba(0, 0, 0, 0.05);
    }

    .side {
      position: sticky;
      top: 0;
      width: 260px;
      height: 100vh;
      background: var(--surface);
      border-right: 1px solid var(--border-color);
      padding: 32px 20px;
      display: flex;
      flex-direction: column;
      flex-shrink: 0;
      z-index: 100;
      transition: all 0.3s ease;
      box-shadow: 4px 0 24px rgba(147, 51, 234, 0.03);
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 36px;
      padding: 0 8px;
    }

    .brand-logo-img {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      object-fit: cover;
      filter: drop-shadow(0 4px 6px rgba(147, 51, 234, 0.15));
    }

    .brand-text h2 {
      font-size: 15px;
      font-weight: 800;
      letter-spacing: -0.3px;
      background: var(--gradient-brand);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      line-height: 1.2;
    }

    .brand-text small {
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 1.2px;
      color: var(--brand-purple);
      text-transform: uppercase;
    }

    .nav {
      display: flex;
      flex-direction: column;
      gap: 8px;
      flex-grow: 1;
    }

    .nav a, .logout a {
      display: flex;
      align-items: center;
      gap: 14px;
      height: 46px;
      padding: 0 16px;
      text-decoration: none;
      color: var(--text-muted);
      font-size: 13px;
      font-weight: 600;
      border-radius: var(--radius-md);
      transition: all 0.2s ease;
    }

    .nav a:hover {
      background: var(--gradient-subtle);
      color: var(--brand-purple);
    }

    .nav a.active {
      background: var(--gradient-brand);
      color: white;
      box-shadow: 0 6px 16px rgba(147, 51, 234, 0.3);
    }

    .nav i, .logout i {
      font-size: 16px;
      width: 20px;
      text-align: center;
    }

    .logout {
      border-top: 1px solid var(--border-color);
      padding-top: 16px;
    }

    .logout a {
      color: #ef4444;
    }

    .logout a:hover {
      background: #fef2f2;
    }

    .main {
      flex-grow: 1;
      display: flex;
      flex-direction: column;
      min-width: 0;
    }

    .top {
      height: 80px;
      background: var(--surface);
      border-bottom: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 40px;
      position: sticky;
      top: 0;
      z-index: 90;
    }

    .top h1 {
      font-size: 22px;
      font-weight: 800;
      letter-spacing: -0.5px;
      background: var(--gradient-brand);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .admin {
      display: flex;
      align-items: center;
      gap: 16px;
      position: relative;
    }

    .icon-btn {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--bg-main);
      border: 1px solid var(--border-color);
      display: grid;
      place-items: center;
      color: var(--brand-purple);
      cursor: pointer;
      position: relative;
      transition: all 0.2s;
    }

    .icon-btn:hover {
      background: var(--gradient-subtle);
      border-color: var(--brand-purple);
    }

    .badge {
      position: absolute;
      top: 6px;
      right: 6px;
      width: 10px;
      height: 10px;
      background: var(--brand-magenta);
      border-radius: 50%;
      border: 2px solid white;
    }

    .profile-menu {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 6px 14px 6px 6px;
      border-radius: 30px;
      background: var(--gradient-subtle);
      border: 1px solid rgba(147, 51, 234, 0.2);
      cursor: pointer;
      position: relative;
    }

    .avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--gradient-brand);
      color: white;
      display: grid;
      place-items: center;
      font-weight: 700;
      font-size: 13px;
      box-shadow: 0 2px 6px rgba(147, 51, 234, 0.3);
    }

    .profile-menu span {
      font-size: 13px;
      font-weight: 700;
      color: var(--brand-purple);
    }

    .dropdown-panel {
      position: absolute;
      top: 55px;
      right: 0;
      width: 220px;
      background: var(--surface);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-hover);
      padding: 12px;
      display: none;
      flex-direction: column;
      gap: 6px;
      z-index: 1000;
    }

    .dropdown-panel.show {
      display: flex;
    }

    .dropdown-panel a {
      text-decoration: none;
      color: var(--text-main);
      font-size: 13px;
      font-weight: 600;
      padding: 8px 12px;
      border-radius: var(--radius-sm);
      display: flex;
      align-items: center;
      gap: 10px;
      transition: background 0.2s;
    }

    .dropdown-panel a:hover {
      background: var(--gradient-subtle);
      color: var(--brand-purple);
    }

    .content {
      padding: 40px;
      width: 100%;
    }

    .welcome {
      margin-bottom: 32px;
    }

    .welcome h2 {
      font-size: 26px;
      font-weight: 800;
      letter-spacing: -0.5px;
      margin-bottom: 4px;
      color: var(--text-main);
    }

    .welcome p {
      font-size: 14px;
      color: var(--text-muted);
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
      padding: 24px;
      box-shadow: var(--shadow-subtle);
      transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
      position: relative;
      overflow: hidden;
    }

    .card::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--gradient-brand);
      opacity: 0.8;
    }

    .card:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-hover);
      border-color: rgba(147, 51, 234, 0.3);
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

    .stat-icon.cyan { background: #e0f2fe; color: var(--brand-cyan); }
    .stat-icon.purple { background: #f3e8ff; color: var(--brand-purple); }
    .stat-icon.magenta { background: #fae8ff; color: var(--brand-magenta); }
    .stat-icon.amber { background: #fef3c7; color: #d97706; }

    .card .title {
      font-size: 13px;
      font-weight: 600;
      color: var(--text-muted);
    }

    .card .num {
      font-size: 34px;
      font-weight: 800;
      letter-spacing: -1px;
      margin-bottom: 12px;
      color: var(--text-main);
    }

    .card .link {
      font-size: 12px;
      font-weight: 700;
      color: var(--brand-cyan);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: gap 0.2s;
    }

    .card .link:hover {
      gap: 10px;
      color: var(--brand-purple);
    }

    .actions {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      margin-bottom: 32px;
    }

    .action {
      background: var(--surface);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-lg);
      padding: 20px;
      display: flex;
      align-items: center;
      gap: 16px;
      text-decoration: none;
      color: var(--text-main);
      box-shadow: var(--shadow-subtle);
      transition: all 0.2s ease;
    }

    .action:hover {
      background: var(--gradient-brand);
      color: white;
      border-color: transparent;
      transform: translateY(-3px);
      box-shadow: 0 10px 22px rgba(147, 51, 234, 0.3);
    }

    .action i {
      font-size: 18px;
      width: 44px;
      height: 44px;
      border-radius: var(--radius-md);
      background: var(--gradient-subtle);
      color: var(--brand-purple);
      display: grid;
      place-items: center;
      transition: all 0.2s ease;
    }

    .action:hover i {
      background: rgba(255, 255, 255, 0.25);
      color: white;
    }

    .action span {
      font-size: 14px;
      font-weight: 700;
    }

    .grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 20px;
    }

    .head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .head h3 {
      font-size: 16px;
      font-weight: 700;
    }

    .head a {
      font-size: 12px;
      font-weight: 700;
      color: var(--brand-purple);
      text-decoration: none;
    }

    .head a:hover {
      text-decoration: underline;
    }

    .table-container {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }

    th {
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--brand-purple);
      padding: 12px 16px;
      border-bottom: 2px solid var(--gradient-subtle);
      background: #faf5ff;
    }

    td {
      font-size: 13px;
      font-weight: 500;
      padding: 16px;
      border-bottom: 1px solid var(--border-color);
    }

    tr:last-child td {
      border-bottom: none;
    }

    tr:hover td {
      background: var(--gradient-subtle);
    }

    .patient-cell {
      font-weight: 700;
      color: var(--text-main);
    }

    .status {
      font-size: 11px;
      font-weight: 700;
      border-radius: 20px;
      padding: 4px 10px;
      display: inline-block;
      text-transform: capitalize;
    }

    .status.confirmed { background: var(--status-confirmed-bg); color: var(--status-confirmed-text); }
    .status.pending { background: var(--status-pending-bg); color: var(--status-pending-text); }
    .status.completed, .status.in_progress, .status.for_dentist { background: var(--status-completed-bg); color: var(--status-completed-text); }
    .status.cancelled, .status.no_show { background: var(--status-cancelled-bg); color: var(--status-cancelled-text); }

    .overview {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 10px 0;
      gap: 24px;
    }

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

    .donut:after {
      content: "<?php echo $total_overview; ?>\A Total";
      white-space: pre;
      text-align: center;
      font-weight: 800;
      font-size: 20px;
      line-height: 1.1;
      color: var(--text-main);
      width: 110px;
      height: 110px;
      background: var(--surface);
      border-radius: 50%;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      box-shadow: inset 0 2px 4px rgba(0,0,0,0.04);
    }

    .legend {
      width: 100%;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .legend-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 12px;
      color: var(--text-muted);
      font-weight: 600;
    }

    .legend-info {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background: var(--brand-magenta);
    }

    .dot.active { background: var(--brand-purple); }
    .dot.completed { background: var(--brand-cyan); }
    .dot.inactive { background: #cbd5e1; }

    .legend-item b {
      color: var(--text-main);
      font-weight: 800;
    }

    @media (max-width: 1200px) {
      .stats, .actions { grid-template-columns: repeat(2, 1fr); }
      .grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
      .side { width: 80px; padding: 24px 12px; }
      .brand-text, .nav span, .logout span { display: none; }
      .brand { justify-content: center; padding: 0; }
      .content { padding: 24px 16px; }
      .top { padding: 0 20px; }
      .stats, .actions { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

  <div class="app-container">
    <aside class="side">
      <div class="brand">
        <img src="brand-logo.png" alt="Tarin-Morales Logo" class="brand-logo-img" onerror="this.src='https://via.placeholder.com/48?text=TM'">
        <div class="brand-text">
          <h2>TARIN-MORALES</h2>
          <small>Dental Clinic</small>
        </div>
      </div>
      
      <nav class="nav">
        <a class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php"><i class="fa-solid fa-chart-pie"></i><span>Dashboard</span></a>
        <a class="<?php echo $current_page == 'patients.php' ? 'active' : ''; ?>" href="patients.php"><i class="fa-solid fa-user-group"></i><span>Patients</span></a>
        <a class="<?php echo $current_page == 'appointments.php' ? 'active' : ''; ?>" href="appointments.php"><i class="fa-regular fa-calendar-check"></i><span>Appointments</span></a>
        <a class="<?php echo $current_page == 'dentists.php' ? 'active' : ''; ?>" href="dentists.php"><i class="fa-solid fa-user-doctor"></i><span>Dentists</span></a>
        <a class="<?php echo $current_page == 'history.php' ? 'active' : ''; ?>" href="history.php"><i class="fa-solid fa-clock-rotate-left"></i><span>Patient History</span></a>
        <a class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" href="reports.php"><i class="fa-solid fa-chart-line"></i><span>Reports</span></a>
        <a class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>" href="settings.php"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
      </nav>

      <div class="logout">
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
      </div>
    </aside>

    <main class="main">
      <header class="top">
        <h1>Dashboard Overview</h1>
        <div class="admin">
          <button class="icon-btn" id="bellBtn">
            <i class="fa-regular fa-bell"></i>
            <span class="badge"></span>
          </button>

          <div class="profile-menu" id="profileMenuBtn">
            <div class="avatar"><i class="fa-solid fa-user"></i></div>
            <span><?php echo htmlspecialchars($admin_name); ?></span>
          </div>

          <div class="dropdown-panel" id="profileDropdown">
            <a href="settings.php"><i class="fa-solid fa-gear"></i> Account Settings</a>
          </div>
        </div>
      </header>

      <section class="content">
        <div class="welcome">
          <h2>Welcome back, <?php echo htmlspecialchars($admin_name); ?>! </h2>
          <p>Here's what's happening at your clinic today.</p>
        </div>

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
            <a class="link" href="walkins.php">View list <i class="fa-solid fa-arrow-right"></i></a>
          </div>
        </div>

        <div class="actions">
          <a class="action" href="add-patient.php">
            <i class="fa-solid fa-user-plus"></i>
            <span>Add Patient</span>
          </a>
          <a class="action" href="add-appointment.php">
            <i class="fa-solid fa-calendar-plus"></i>
            <span>New Appointment</span>
          </a>
          <a class="action" href="history.php">
            <i class="fa-solid fa-file-medical"></i>
            <span>Patient History</span>
          </a>
          <a class="action" href="reports.php">
            <i class="fa-solid fa-chart-column"></i>
            <span>View Reports</span>
          </a>
        </div>

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
                        <td class="patient-cell"><?php echo htmlspecialchars($app['patient'] ?? 'Unknown Patient'); ?></td>
                        <td><?php echo htmlspecialchars($app['time'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($app['dentist'] ?? 'Unassigned'); ?></td>
                        <td><?php echo htmlspecialchars($app['procedure_title'] ?? 'N/A'); ?></td>
                        <td>
                          <?php $status_class = strtolower($app['status'] ?? 'pending'); ?>
                          <span class="status <?php echo htmlspecialchars($status_class); ?>">
                            <?php echo htmlspecialchars(str_replace('_', ' ', $app['status'] ?? 'Pending')); ?>
                          </span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="5" style="text-align: center; color: var(--text-muted);">No appointments found.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <div class="head">
              <h3>Patient Overview</h3>
              <a href="reports.php">View Report</a>
            </div>
            <div class="overview">
              <div class="donut"></div>
              <div class="legend">
                <div class="legend-item">
                  <div class="legend-info">
                    <span class="dot active"></span>
                    <span>Active Patients</span>
                  </div>
                  <b><?php echo $active_patients; ?></b>
                </div>
                <div class="legend-item">
                  <div class="legend-info">
                    <span class="dot completed"></span>
                    <span>Completed Cases</span>
                  </div>
                  <b><?php echo $completed_cases; ?></b>
                </div>
                <div class="legend-item">
                  <div class="legend-info">
                    <span class="dot inactive"></span>
                    <span>Inactive</span>
                  </div>
                  <b><?php echo $inactive_patients; ?></b>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const profileMenuBtn = document.getElementById('profileMenuBtn');
      const profileDropdown = document.getElementById('profileDropdown');
      const bellBtn = document.getElementById('bellBtn');

      profileMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
      });

      document.addEventListener('click', () => {
        if (profileDropdown.classList.contains('show')) {
          profileDropdown.classList.remove('show');
        }
      });

      bellBtn.addEventListener('click', () => {
        alert('No new unread notifications.');
      });
    });
  </script>
</body>
</html>