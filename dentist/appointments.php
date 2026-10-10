<?php
require_once 'config.php';
session_start();

$search_term = trim($_GET['txtsearch'] ?? '');
$appointment_rows = [];
$appointment_total = 0;
$appointment_completed = 0;
$appointment_pending = 0;
$stats_result = mysqli_query($link, "SELECT COUNT(*) AS total,
    SUM(LOWER(status) = 'completed') AS completed,
    SUM(LOWER(status) IN ('pending', 'confirmed', 'for_dentist', 'in_progress')) AS pending
    FROM tbl_appointments");
if ($stats_result && $stats = mysqli_fetch_assoc($stats_result)) {
    $appointment_total = (int) ($stats['total'] ?? 0);
    $appointment_completed = (int) ($stats['completed'] ?? 0);
    $appointment_pending = (int) ($stats['pending'] ?? 0);
}

$appointments_sql = "SELECT a.appointment_id, a.patient_id, a.appointment_date, a.appointment_time,
        a.procedure_name, a.reason, a.status,
        CONCAT_WS(' ', p.first_name, NULLIF(p.middle_name, ''), p.last_name) AS patient_name
    FROM tbl_appointments a
    INNER JOIN tbl_patients p ON p.patient_id = a.patient_id
    WHERE LOWER(a.status) <> 'completed'
        AND (? = '' OR CONCAT_WS(' ', p.first_name, NULLIF(p.middle_name, ''), p.last_name) LIKE ?
        OR a.procedure_name LIKE ? OR a.status LIKE ?)
    ORDER BY a.appointment_date DESC, a.appointment_time ASC";
$appointments_stmt = mysqli_prepare($link, $appointments_sql);
if ($appointments_stmt) {
    $search_like = '%' . $search_term . '%';
    mysqli_stmt_bind_param($appointments_stmt, 'ssss', $search_term, $search_like, $search_like, $search_like);
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
    <title>Appointments - Tarin-Morales Dental Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brandBlue: '#2563eb',
                        brandPurple: '#9333ea',
                        brandPink: '#ec4899'
                    }
                }
            }
        };
    </script>
    <style>
        .login-gradient-bg { background: #f8fafc; }
        .btn-gradient { background: linear-gradient(135deg, #2563eb 0%, #9333ea 50%, #ec4899 100%); }
        .btn-gradient:hover { background: linear-gradient(135deg, #1d4ed8 0%, #7e22ce 50%, #db2777 100%); }
        .nav-option:hover { background: linear-gradient(135deg, #2563eb 0%, #9333ea 50%, #ec4899 100%) !important; color: #fff !important; box-shadow: 0 6px 14px rgb(37 99 235 / 0.2); transform: translateX(3px); }
        .nav-option i { transition: transform 180ms ease; }
        .nav-option:hover i { color: #fff !important; transform: scale(1.08); }
        .dentist-profile, .notification-circle { border: 1px solid transparent; background: linear-gradient(#f8fafc, #f8fafc) padding-box, linear-gradient(135deg, rgb(37 99 235 / 0.35), rgb(147 51 234 / 0.5)) border-box; box-shadow: 0 0 0 2px rgb(147 51 234 / 0.08), 0 0 10px rgb(37 99 235 / 0.18); }
        .dentist-avatar { background: linear-gradient(135deg, #2563eb 0%, #9333ea 100%); color: #fff; }
        .dentist-label { color: #5b21b6; }
        .notification-circle:hover { background: linear-gradient(#f1f5f9, #f1f5f9) padding-box, linear-gradient(135deg, rgb(37 99 235 / 0.5), rgb(147 51 234 / 0.65)) border-box; }
        .appointment-row { transition: transform 180ms ease, box-shadow 180ms ease; }
        .appointment-row:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgb(37 99 235 / 0.08); }
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
                <a href="dentist-dashboard.php" class="nav-option flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-600 font-medium transition"><i class="fa-solid fa-house-chimney w-5 text-blue-600"></i><span>Dashboard</span></a>
                <a href="appointments.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl btn-gradient text-white font-medium shadow-md transition"><i class="fa-solid fa-calendar-days w-5"></i><span>Appointments</span></a>
                <a href="patients.php" class="nav-option flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-600 font-medium transition"><i class="fa-solid fa-user-group w-5 text-blue-600"></i><span>Patients</span></a>
            </nav>
        </div>
        <div class="pt-6 border-t border-slate-100"><a href="logout.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-red-600 hover:bg-red-50 font-medium transition"><i class="fa-solid fa-right-from-bracket w-5"></i><span>Logout</span></a></div>
    </aside>

    <main class="flex-1 min-h-0 md:h-screen md:overflow-hidden flex flex-col bg-slate-50/50">
        <header class="bg-white border-b border-slate-100 px-6 py-4 min-h-[104px] flex items-center justify-between shadow-sm">
            <div>
                <h2 class="text-2xl md:text-3xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Appointments</h2>
                <p class="text-xs text-slate-400 mt-1">Organize visits and keep every patient on track.</p>
            </div>
            <div class="flex items-center gap-5">
                <button class="notification-circle w-14 h-14 rounded-full flex items-center justify-center text-brandPurple transition relative"><i class="fa-regular fa-bell text-xl"></i><span class="absolute top-2 right-2 w-3 h-3 bg-purple-600 rounded-full ring-2 ring-white"></span></button>
                <div class="dentist-profile flex items-center gap-3 px-1.5 py-1 rounded-full"><div class="dentist-avatar w-11 h-11 rounded-full flex items-center justify-center text-lg shadow-sm"><i class="fa-solid fa-user-doctor"></i></div><span class="dentist-label font-bold text-base pr-4">Dentist</span></div>
            </div>
        </header>

        <div class="p-6 md:p-8 flex-1 min-h-0 overflow-y-auto">
            <div class="mb-8">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600">Schedule overview</p>
                    <h3 class="text-3xl md:text-4xl font-bold text-black tracking-normal mt-2">All appointments</h3>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
                <div class="bg-white border border-blue-100 rounded-2xl p-5 shadow-sm"><div class="flex items-center justify-between"><span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total visits</span><span class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-calendar-check"></i></span></div><p class="text-3xl font-bold text-slate-900 mt-4"><?= $appointment_total ?></p><p class="text-xs text-slate-400 mt-1">Across the appointment list</p></div>
                <div class="bg-white border border-purple-100 rounded-2xl p-5 shadow-sm"><div class="flex items-center justify-between"><span class="text-xs font-bold uppercase tracking-wider text-slate-400">Completed</span><span class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-circle-check"></i></span></div><p class="text-3xl font-bold text-slate-900 mt-4"><?= $appointment_completed ?></p><p class="text-xs text-slate-400 mt-1">Visits already completed</p></div>
                <div class="bg-white border border-pink-100 rounded-2xl p-5 shadow-sm"><div class="flex items-center justify-between"><span class="text-xs font-bold uppercase tracking-wider text-slate-400">Pending</span><span class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-clock"></i></span></div><p class="text-3xl font-bold text-slate-900 mt-4"><?= $appointment_pending ?></p><p class="text-xs text-slate-400 mt-1">Visits needing attention</p></div>
            </div>

            <section class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div><h4 class="text-lg font-bold text-slate-800">Appointment list</h4><p class="text-xs text-slate-400 mt-1">Review schedules, patient details, and next actions.</p></div>
                    <form method="GET" class="flex items-center gap-2">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none"><i class="fa-solid fa-magnifying-glass text-xs"></i></span>
                            <input type="text" name="txtsearch" value="<?= htmlspecialchars($search_term) ?>" placeholder="Search patient or status" class="pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brandBlue/50">
                        </div>
                        <button type="submit" class="px-4 py-2 btn-gradient text-white text-sm font-medium rounded-xl shadow-sm transition">Search</button>
                    </form>
                </div>
                <div class="max-h-[300px] overflow-auto">
                    <table class="w-full text-left border-collapse">
                        <thead><tr class="sticky top-0 z-10 bg-white border-b border-slate-100 text-xs font-semibold text-slate-400 uppercase tracking-wider"><th class="py-3 px-4">Patient Name</th><th class="py-3 px-4">Date</th><th class="py-3 px-4">Time</th><th class="py-3 px-4">Status</th><th class="py-3 px-4 text-right">Actions</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php if (count($appointment_rows) > 0): ?>
                                <?php foreach ($appointment_rows as $appointment): ?>
                                    <?php $status = ucfirst(str_replace('_', ' ', strtolower($appointment['status'] ?? 'pending'))); ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="py-4 px-4 font-medium text-blue-600"><?= htmlspecialchars($appointment['patient_name']) ?></td>
                                        <td class="py-4 px-4 text-slate-600"><?= htmlspecialchars(date('M j, Y', strtotime($appointment['appointment_date']))) ?></td>
                                        <td class="py-4 px-4 text-slate-600"><?= htmlspecialchars(date('g:i A', strtotime($appointment['appointment_time']))) ?></td>
                                        <td class="py-4 px-4"><span class="px-3 py-1 rounded-full text-xs font-semibold <?= strtolower($appointment['status']) === 'completed' ? 'bg-green-50 text-green-700' : (in_array(strtolower($appointment['status']), ['cancelled', 'no_show'], true) ? 'bg-red-50 text-red-700' : 'bg-blue-50 text-blue-700') ?>"><?= htmlspecialchars($status) ?></span></td>
                                        <td class="py-4 px-4 text-right"><a href="patient-profile.php?id=<?= (int) $appointment['patient_id'] ?>&amp;appointment_id=<?= (int) $appointment['appointment_id'] ?>" class="inline-flex items-center gap-2 text-xs bg-blue-50 text-brandBlue px-3 py-1.5 rounded-lg font-medium hover:bg-blue-100 transition"><i class="fa-regular fa-eye"></i> View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-12 text-slate-400">No appointments found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</div>
</body>
</html>
