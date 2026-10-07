<?php
require_once 'config.php';
session_start();

$today = date('Y-m-d');
$search_term = trim($_GET['txtsearch'] ?? '');
$today_appointments = 0;
$completed_appointments = 0;
$pending_appointments = 0;
$appointment_rows = [];

$stats_sql = "SELECT
        SUM(LOWER(status) NOT IN ('cancelled', 'no_show')) AS total,
        SUM(LOWER(status) = 'completed') AS completed,
        SUM(LOWER(status) IN ('pending', 'confirmed', 'for_dentist', 'in_progress')) AS pending
    FROM tbl_appointments
    WHERE appointment_date = ?";
$stats_stmt = mysqli_prepare($link, $stats_sql);
if ($stats_stmt) {
    mysqli_stmt_bind_param($stats_stmt, 's', $today);
    mysqli_stmt_execute($stats_stmt);
    $stats_result = mysqli_stmt_get_result($stats_stmt);
    if ($stats = mysqli_fetch_assoc($stats_result)) {
        $today_appointments = (int) ($stats['total'] ?? 0);
        $completed_appointments = (int) ($stats['completed'] ?? 0);
        $pending_appointments = (int) ($stats['pending'] ?? 0);
    }
    mysqli_stmt_close($stats_stmt);
}

$completion_rate = $today_appointments > 0 ? round(($completed_appointments / $today_appointments) * 100) : 0;
$pending_rate = $today_appointments > 0 ? round(($pending_appointments / $today_appointments) * 100) : 0;

$appointments_sql = "SELECT a.appointment_id, a.patient_id, a.appointment_date, a.appointment_time,
        a.procedure_name, a.reason, a.status,
        CONCAT_WS(' ', p.first_name, NULLIF(p.middle_name, ''), p.last_name) AS patient_name
    FROM tbl_appointments a
    INNER JOIN tbl_patients p ON p.patient_id = a.patient_id
    WHERE a.appointment_date = ?
        AND (? = '' OR CONCAT_WS(' ', p.first_name, NULLIF(p.middle_name, ''), p.last_name) LIKE ?)
    ORDER BY a.appointment_time ASC";
$appointments_stmt = mysqli_prepare($link, $appointments_sql);
if ($appointments_stmt) {
    $search_like = '%' . $search_term . '%';
    mysqli_stmt_bind_param($appointments_stmt, 'sss', $today, $search_term, $search_like);
    mysqli_stmt_execute($appointments_stmt);
    $appointments_result = mysqli_stmt_get_result($appointments_stmt);
    while ($appointment = mysqli_fetch_assoc($appointments_result)) {
        $appointment_rows[] = $appointment;
    }
    mysqli_stmt_close($appointments_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon">
    <title>Dentist Dashboard - Tarin-Morales Dental Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brandBlue: '#2563eb',
                        brandPurple: '#9333ea',
                        brandPink: '#ec4899',
                    }
                }
            }
        }
    </script>
    <style>
        .login-gradient-bg { background: #f8fafc; }
        .btn-gradient { background: linear-gradient(135deg, #2563eb 0%, #9333ea 50%, #ec4899 100%); }
        .btn-gradient:hover { background: linear-gradient(135deg, #1d4ed8 0%, #7e22ce 50%, #db2777 100%); }
        .icon-blue { background-color: rgb(37 99 235 / 0.14); color: #2563eb; }
        .icon-purple { background-color: rgb(147 51 234 / 0.14); color: #9333ea; }
        .nav-option:hover {
            background: linear-gradient(135deg, #2563eb 0%, #9333ea 50%, #ec4899 100%) !important;
            color: #ffffff !important;
            box-shadow: 0 6px 14px rgb(37 99 235 / 0.2);
            transform: translateX(3px);
        }
        .nav-option i { transition: transform 180ms ease; }
        .nav-option:hover i {
            color: #ffffff !important;
            transform: scale(1.08);
        }
        .dentist-profile {
            border: 1px solid transparent;
            background: linear-gradient(#f7f7ff, #f7f7ff) padding-box,
                linear-gradient(135deg, rgb(37 99 235 / 0.38), rgb(147 51 234 / 0.55)) border-box;
            box-shadow: 0 4px 12px rgb(99 102 241 / 0.12);
        }
        .dentist-avatar {
            background: linear-gradient(135deg, #2563eb 0%, #9333ea 100%);
            color: #ffffff;
        }
        .dentist-label {
            color: #5b21b6;
        }
        .notification-circle {
            border: 1px solid transparent;
            background: linear-gradient(#f8fafc, #f8fafc) padding-box,
                linear-gradient(135deg, rgb(37 99 235 / 0.35), rgb(147 51 234 / 0.5)) border-box;
            box-shadow: 0 0 0 2px rgb(147 51 234 / 0.08), 0 0 10px rgb(37 99 235 / 0.18);
        }
        .notification-circle:hover {
            background: linear-gradient(#f1f5f9, #f1f5f9) padding-box,
                linear-gradient(135deg, rgb(37 99 235 / 0.5), rgb(147 51 234 / 0.65)) border-box;
        }
    </style>
</head>
<body class="login-gradient-bg min-h-screen font-sans text-slate-800">

<div class="w-full min-h-screen md:h-screen bg-white overflow-hidden flex flex-col md:flex-row">
    
    <aside class="w-full md:w-72 md:h-screen md:sticky md:top-0 md:shrink-0 bg-white border-r border-slate-100 flex flex-col justify-between p-6">
        <div>
            <div class="flex items-center gap-3.5 pb-6 border-b border-slate-100 mb-6">
                <div class="w-14 h-14 rounded-full bg-purple-50 border-2 border-purple-200 flex items-center justify-center overflow-hidden shadow-md flex-shrink-0">
                    <img src="logo-img.jpg" alt="Clinic Logo" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='https://placehold.co/100?text=Logo';">
                </div>
                <div>
                    <h1 class="font-black tracking-wide text-xs bg-gradient-to-r from-blue-600 via-purple-600 to-pink-600 bg-clip-text text-transparent">TARIN-MORALES</h1>
                    <p class="text-[10px] text-purple-600 font-semibold tracking-wider uppercase mt-0.5">DENTAL CLINIC</p>
                </div>
            </div>

            <nav class="space-y-1.5">
                <a href="dentist-dashboard.php" class="nav-option flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-600 font-medium transition">
                    <i class="fa-solid fa-house-chimney w-5 text-blue-600"></i>
                    <span>Dashboard</span>
                </a>
                <a href="appointments.php" class="nav-option flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-600 font-medium transition">
                    <i class="fa-solid fa-calendar-days w-5 text-blue-600"></i>
                    <span>Appointments</span>
                </a>
                <a href="patients.php" class="nav-option flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-600 font-medium transition">
                    <i class="fa-solid fa-user-group w-5 text-blue-600"></i>
                    <span>Patients</span>
                </a>
            </nav>
        </div>

        <div class="pt-6 border-t border-slate-100">
            <a href="logout.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-red-600 hover:bg-red-50 font-medium transition">
                <i class="fa-solid fa-right-from-bracket w-5"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="flex-1 min-h-0 md:h-screen md:overflow-hidden flex flex-col bg-slate-50/50">
        <header class="bg-white border-b border-slate-100 px-6 py-4 min-h-[104px] flex items-center justify-between shadow-sm">
            <div>
                <h2 class="text-2xl md:text-3xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Dentist Dashboard</h2>
            </div>

            <div class="flex items-center gap-5">
                <div class="relative">
                    <button class="notification-circle w-14 h-14 rounded-full flex items-center justify-center text-brandPurple transition relative">
                        <i class="fa-regular fa-bell text-xl"></i>
                        <span class="absolute top-2 right-2 w-3 h-3 bg-purple-600 rounded-full ring-2 ring-white"></span>
                    </button>
                </div>

                <div class="dentist-profile flex items-center gap-3 px-1.5 py-1 rounded-full">
                    <div class="dentist-avatar w-11 h-11 rounded-full flex items-center justify-center text-lg shadow-sm">
                        <i class="fa-solid fa-user-doctor"></i>
                    </div>
                    <span class="dentist-label font-bold text-base pr-4">Dentist</span>
                </div>
            </div>
        </header>

        <div class="p-6 md:p-8 flex-1 min-h-0 overflow-y-auto">
            <div class="mb-8">
                <h3 class="text-3xl md:text-4xl font-bold text-black tracking-normal">Welcome Back!</h3>
                <p class="text-slate-500 text-sm mt-1">Here is the overview of today's appointment schedule and status.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
                <div class="relative bg-gradient-to-br from-white via-white to-blue-50/50 p-6 rounded-2xl border border-blue-200/60 shadow-md hover:shadow-lg transition overflow-hidden group">
                    <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-blue-500/15 rounded-full blur-xl group-hover:bg-blue-500/25 transition"></div>
                    <div class="flex items-center justify-between mb-3 relative z-10">
                        <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Today's Schedule</span>
                        <div class="w-10 h-10 rounded-xl icon-blue flex items-center justify-center shadow-md">
                            <i class="fa-solid fa-calendar-days text-sm"></i>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2 relative z-10">
                        <h4 class="text-4xl font-black text-slate-900"><?= $today_appointments ?></h4>
                        <span class="text-xs text-slate-400 font-medium">Scheduled</span>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 relative z-10">
                        <span>Active Queue</span>
                        <span class="font-semibold text-blue-600"><i class="fa-solid fa-bolt mr-1"></i> Live</span>
                    </div>
                </div>

                <div class="relative bg-gradient-to-br from-white via-white to-blue-50/50 p-6 rounded-2xl border border-blue-200/60 shadow-md hover:shadow-lg transition overflow-hidden group">
                    <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-blue-500/15 rounded-full blur-xl group-hover:bg-blue-500/25 transition"></div>
                    <div class="flex items-center justify-between mb-3 relative z-10">
                        <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Completed</span>
                        <div class="w-10 h-10 rounded-xl icon-blue flex items-center justify-center shadow-md">
                            <i class="fa-solid fa-circle-check text-sm"></i>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-3 relative z-10">
                        <h4 class="text-4xl font-black text-slate-900"><?= $completed_appointments ?></h4>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700"><?= $completion_rate ?>% Done</span>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 relative z-10">
                        <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                            <div class="bg-blue-600 h-full rounded-full transition-all duration-500" style="width: <?= $completion_rate ?>%;"></div>
                        </div>
                    </div>
                </div>

                <div class="relative bg-gradient-to-br from-white via-white to-blue-50/50 p-6 rounded-2xl border border-blue-200/60 shadow-md hover:shadow-lg transition overflow-hidden group">
                    <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-blue-500/15 rounded-full blur-xl group-hover:bg-blue-500/25 transition"></div>
                    <div class="flex items-center justify-between mb-3 relative z-10">
                        <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Pending</span>
                        <div class="w-10 h-10 rounded-xl icon-blue flex items-center justify-center shadow-md">
                            <i class="fa-solid fa-clock text-sm"></i>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-3 relative z-10">
                        <h4 class="text-4xl font-black text-slate-900"><?= $pending_appointments ?></h4>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700"><?= $pending_rate ?>% Left</span>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 relative z-10">
                        <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                            <div class="bg-blue-600 h-full rounded-full transition-all duration-500" style="width: <?= $pending_rate ?>%;"></div>
                        </div>
                    </div>
                </div>

            </div>

            <div id="appointment-schedule" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Today's Appointment Schedule</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Manage patient queues and status for <?= date("F d, Y") ?></p>
                    </div>
                    
                    <form method="GET" class="flex items-center gap-2">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">
                                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            </span>
                            <input type="text" name="txtsearch" value="<?= htmlspecialchars($search_term) ?>" placeholder="Search patient..." class="pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brandBlue/50">
                        </div>
                        <button type="submit" class="px-4 py-2 btn-gradient text-white text-sm font-medium rounded-xl shadow-sm transition">Search</button>
                    </form>
                </div>

                <div class="max-h-[300px] overflow-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="sticky top-0 z-10 bg-white border-b border-slate-100 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-4">Patient Name</th>
                                <th class="py-3 px-4">Time</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php if ($appointment_rows): ?>
                                <?php foreach ($appointment_rows as $appointment): ?>
                                    <?php $status = ucfirst(str_replace('_', ' ', strtolower($appointment['status'] ?? 'pending'))); ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="py-4 px-4 font-medium text-blue-600"><?= htmlspecialchars($appointment['patient_name']) ?></td>
                                        <td class="py-4 px-4 text-blue-600"><?= htmlspecialchars(date('g:i A', strtotime($appointment['appointment_time']))) ?></td>
                                        <td class="py-4 px-4">
                                            <span class="px-3 py-1 rounded-full text-xs font-semibold border <?= strtolower($appointment['status']) === 'completed' ? 'bg-green-50 text-green-700 border-green-200/60' : (in_array(strtolower($appointment['status']), ['cancelled', 'no_show'], true) ? 'bg-red-50 text-red-700 border-red-200/60' : 'bg-blue-50 text-blue-700 border-blue-200/60') ?>">
                                                <?= htmlspecialchars($status) ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-right">
                                            <a href="patient-profile.php?id=<?= (int) $appointment['patient_id'] ?>&amp;appointment_id=<?= (int) $appointment['appointment_id'] ?>" class="inline-flex items-center gap-2 text-xs bg-blue-50 text-brandBlue px-3 py-1.5 rounded-lg font-medium hover:bg-blue-100 transition"><i class="fa-regular fa-eye"></i> View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center py-6 text-slate-400">No appointments found for today.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

</body>
</html>