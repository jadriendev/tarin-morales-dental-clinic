<?php
require_once __DIR__ . '/../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin_display_name = $_SESSION['admin_name'] ?? $admin_name ?? 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo isset($page_title) ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') . ' - Tarin-Morales Dental Clinic' : 'Tarin-Morales Dental Clinic'; ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="shortcut icon" href="../../images/logo.jpg" type="image/x-icon">
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
      --status-progress-bg: #e0f2fe;
      --status-progress-text: #0369a1;
      --status-completed-bg: #f3e8ff;
      --status-completed-text: #7e22ce;
      --status-cancelled-bg: #fef2f2;
      --status-cancelled-text: #b91c1c;
    }

    *, *::before, *::after {
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
      overflow-x: hidden;
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
      transition: width 0.3s ease, padding 0.3s ease;
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
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
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

    .card {
      background: var(--surface);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-lg);
      padding: 24px;
      box-shadow: var(--shadow-subtle);
      transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
      position: relative;
      overflow: hidden;
      width: 100%;
      max-width: 100%;
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

    /* Table Container Responsiveness */
    .table-container {
      width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      border-radius: var(--radius-md);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      white-space: nowrap;
    }

    th {
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 12px 16px;
      border-bottom: 2px solid var(--gradient-subtle);
      background: #faf5ff;
      background-image: linear-gradient(90deg, var(--brand-blue) 0%, var(--brand-purple) 100%);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
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

    /* Status Badges */
    .status.active, .status.confirmed { background: var(--status-confirmed-bg); color: var(--status-confirmed-text); }
    .status.pending { background: var(--status-pending-bg); color: var(--status-pending-text); }
    .status.in_progress, .status.for_dentist { background: var(--status-progress-bg); color: var(--status-progress-text); }
    .status.completed { background: var(--status-completed-bg); color: var(--status-completed-text); }
    .status.cancelled, .status.no_show, .status.inactive { background: var(--status-cancelled-bg); color: var(--status-cancelled-text); }

    /* ==========================================================================
       RESPONSIVE BREAKPOINTS (Laptops, Tablets, Mobile)
       ========================================================================== */
    @media (max-width: 1200px) {
      .stats {
        grid-template-columns: repeat(2, 1fr) !important;
      }
      .grid {
        grid-template-columns: 1fr !important;
      }
    }

    @media (max-width: 992px) {
      .top { padding: 0 24px; }
      .content { padding: 24px 20px; }
    }

    @media (max-width: 768px) {
      .side { width: 72px; padding: 20px 8px; }
      .brand-text, .nav span, .logout span { display: none; }
      .brand { justify-content: center; padding: 0; }
      .nav a, .logout a { justify-content: center; padding: 0; }
      .top h1 { font-size: 18px; }
      .profile-menu span { display: none; }
      .profile-menu { padding: 4px; }
      
      .actions {
        grid-template-columns: 1fr !important;
      }
      .head {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
      }
      .head a, .head button, .head .btn-primary {
        width: 100%;
        text-align: center;
      }
      th, td {
        padding: 10px 12px;
      }
    }

    @media (max-width: 480px) {
      .top { padding: 0 12px; }
      .content { padding: 16px 12px; }
      .stats {
        grid-template-columns: 1fr !important;
      }
      .card {
        padding: 16px;
      }
      .card .num {
        font-size: 26px !important;
      }
    }
  </style>
</head>
<body>

  <div class="app-container">
    <aside class="side">
      <div class="brand">
        <img src="../../images/logo.jpg" alt="Tarin-Morales Logo" class="brand-logo-img" onerror="this.src='https://via.placeholder.com/48?text=TM'">
        <div class="brand-text">
          <h2>TARIN-MORALES</h2>
          <small>Dental Clinic</small>
        </div>
      </div>
      
      <nav class="nav">
        <a class="<?php echo isset($current_page) && $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php" title="Dashboard"><i class="fa-solid fa-chart-pie"></i><span>Dashboard</span></a>
        <a class="<?php echo isset($current_page) && $current_page == 'patients.php' ? 'active' : ''; ?>" href="patients.php" title="Patients"><i class="fa-solid fa-user-group"></i><span>Patients</span></a>
        <a class="<?php echo isset($current_page) && $current_page == 'appointments.php' ? 'active' : ''; ?>" href="appointments.php" title="Appointments"><i class="fa-regular fa-calendar-check"></i><span>Appointments</span></a>
        <a class="<?php echo isset($current_page) && $current_page == 'dentists.php' ? 'active' : ''; ?>" href="dentists.php" title="Dentists"><i class="fa-solid fa-user-doctor"></i><span>Dentists</span></a>
        <a class="<?php echo isset($current_page) && $current_page == 'history.php' ? 'active' : ''; ?>" href="history.php" title="Patient History"><i class="fa-solid fa-clock-rotate-left"></i><span>Patient History</span></a>
        <a class="<?php echo isset($current_page) && $current_page == 'settings.php' ? 'active' : ''; ?>" href="settings.php" title="Settings"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
      </nav>

      <div class="logout">
        <a href="logout.php" title="Logout"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
      </div>
    </aside>

    <main class="main">
      <header class="top">
        <h1><?php echo isset($header_title) ? htmlspecialchars($header_title, ENT_QUOTES, 'UTF-8') : 'Dashboard Overview'; ?></h1>
        <div class="admin">
          <button class="icon-btn" id="bellBtn" title="Notifications">
            <i class="fa-regular fa-bell fa-lg"></i>
            <span class="badge"></span>
          </button>

          <div class="profile-menu" id="profileMenuBtn">
            <div class="avatar"><i class="fa-solid fa-user"></i></div>
            <span><?php echo htmlspecialchars($admin_display_name, ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
        </div>
      </header>

      <section class="content">