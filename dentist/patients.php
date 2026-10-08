<?php
require_once 'config.php';
session_start();

$search_term = trim($_GET['txtsearch'] ?? '');
$patients = [];
$patient_count = 0;

$count_result = mysqli_query($link, 'SELECT COUNT(*) AS total FROM tbl_patients');
if ($count_result && $count = mysqli_fetch_assoc($count_result)) {
    $patient_count = (int) ($count['total'] ?? 0);
}

$patients_sql = "SELECT p.patient_id, p.first_name, p.middle_name, p.last_name, p.birth_date,
        p.sex, p.contact_number, p.address, u.email,
        CONCAT_WS(' ', p.first_name, NULLIF(p.middle_name, ''), p.last_name) AS patient_name,
        TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) AS age
    FROM tbl_patients p
    LEFT JOIN tbl_users u ON u.user_id = p.user_id
    WHERE (? = '' OR CONCAT_WS(' ', p.first_name, NULLIF(p.middle_name, ''), p.last_name) LIKE ?
        OR p.contact_number LIKE ? OR u.email LIKE ?)
    ORDER BY p.last_name ASC, p.first_name ASC";
$patients_stmt = mysqli_prepare($link, $patients_sql);
if ($patients_stmt) {
    $search_like = '%' . $search_term . '%';
    mysqli_stmt_bind_param($patients_stmt, 'ssss', $search_term, $search_like, $search_like, $search_like);
    mysqli_stmt_execute($patients_stmt);
    $patients_result = mysqli_stmt_get_result($patients_stmt);
    while ($patient = mysqli_fetch_assoc($patients_result)) {
        $patients[] = $patient;
    }
    mysqli_stmt_close($patients_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon">
    <title>Patients - Tarin-Morales Dental Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-bg { background: #f8fafc; }
        .btn-gradient { background: linear-gradient(135deg, #2563eb 0%, #9333ea 50%, #ec4899 100%); }
        .nav-option:hover { background: linear-gradient(135deg, #2563eb 0%, #9333ea 50%, #ec4899 100%) !important; color: #fff !important; box-shadow: 0 6px 14px rgb(37 99 235 / 0.2); transform: translateX(3px); }
        .nav-option i { transition: transform 180ms ease; }
        .nav-option:hover i { color: #fff !important; transform: scale(1.08); }
    </style>
</head>
<body class="page-bg min-h-screen font-sans text-slate-800">
<div class="w-full min-h-screen md:h-screen bg-white overflow-hidden flex flex-col md:flex-row">
    <aside class="w-full md:w-72 md:h-screen md:sticky md:top-0 md:shrink-0 bg-white border-r border-slate-100 flex flex-col justify-between p-6">
        <div>
            <div class="flex items-center gap-3.5 pb-6 border-b border-slate-100 mb-6">
                <div class="w-14 h-14 rounded-full bg-purple-50 border-2 border-purple-200 flex items-center justify-center overflow-hidden shadow-md flex-shrink-0"><img src="logo-img.jpg" alt="Clinic Logo" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='../images/logo.jpg';"></div>
                <div><h1 class="font-black tracking-wide text-xs bg-gradient-to-r from-blue-600 via-purple-600 to-pink-600 bg-clip-text text-transparent">TARIN-MORALES</h1><p class="text-[10px] text-purple-600 font-semibold tracking-wider uppercase mt-0.5">DENTAL CLINIC</p></div>
            </div>
            <nav class="space-y-1.5">
                <a href="dentist-dashboard.php" class="nav-option flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-600 font-medium transition"><i class="fa-solid fa-house-chimney w-5 text-blue-600"></i><span>Dashboard</span></a>
                <a href="appointments.php" class="nav-option flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-600 font-medium transition"><i class="fa-solid fa-calendar-days w-5 text-blue-600"></i><span>Appointments</span></a>
                <a href="patients.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl btn-gradient text-white font-medium shadow-md transition"><i class="fa-solid fa-user-group w-5"></i><span>Patients</span></a>
            </nav>
        </div>
        <div class="pt-6 border-t border-slate-100"><a href="logout.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-red-600 hover:bg-red-50 font-medium transition"><i class="fa-solid fa-right-from-bracket w-5"></i><span>Logout</span></a></div>
    </aside>
    <main class="flex-1 min-h-0 md:h-screen md:overflow-hidden flex flex-col bg-slate-50/50">
        <header class="bg-white border-b border-slate-100 px-6 py-4 min-h-[104px] flex items-center justify-between shadow-sm">
            <div><h2 class="text-2xl md:text-3xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Patients</h2><p class="text-xs text-slate-400 mt-1">Patient directory and dental visit records.</p></div>
            <div class="flex items-center gap-3"><div class="w-11 h-11 rounded-full bg-gradient-to-br from-blue-600 to-purple-600 text-white flex items-center justify-center"><i class="fa-solid fa-user-doctor"></i></div><span class="font-bold text-purple-800">Dentist</span></div>
        </header>
        <div class="p-6 md:p-8 flex-1 min-h-0 overflow-y-auto">
            <section class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div><p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600">Patient records</p><h3 class="text-2xl font-bold text-slate-900 mt-1">All patients <span class="text-base font-medium text-slate-400">(<?= $patient_count ?>)</span></h3></div>
                    <form method="GET" class="flex items-center gap-2"><input type="search" name="txtsearch" value="<?= htmlspecialchars($search_term, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search name, phone, or email" class="w-full sm:w-72 px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50"><button type="submit" class="px-4 py-2 btn-gradient text-white text-sm font-medium rounded-xl">Search</button></form>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead><tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-400 uppercase tracking-wider"><th class="py-3 px-5">Patient</th><th class="py-3 px-5">Age / Sex</th><th class="py-3 px-5">Contact</th><th class="py-3 px-5">Email</th><th class="py-3 px-5 text-right">Details</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php if ($patients): ?>
                                <?php foreach ($patients as $patient): ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="py-4 px-5"><div class="font-semibold text-slate-800"><?= htmlspecialchars($patient['patient_name'], ENT_QUOTES, 'UTF-8') ?></div><div class="text-xs text-slate-400 mt-1">Patient #<?= (int) $patient['patient_id'] ?></div></td>
                                        <td class="py-4 px-5 text-slate-600"><?= htmlspecialchars((string) ($patient['age'] ?? '—')) ?> / <?= htmlspecialchars($patient['sex'] ?? '—') ?></td>
                                        <td class="py-4 px-5 text-slate-600"><?= htmlspecialchars($patient['contact_number'] ?? '—') ?></td>
                                        <td class="py-4 px-5 text-slate-600"><?= htmlspecialchars($patient['email'] ?? '—') ?></td>
                                        <td class="py-4 px-5 text-right"><a href="patient-profile.php?id=<?= (int) $patient['patient_id'] ?>" class="inline-flex items-center gap-2 text-xs bg-blue-50 text-blue-700 px-3 py-1.5 rounded-lg font-medium hover:bg-blue-100 transition"><i class="fa-regular fa-eye"></i> View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-12 text-slate-400">No patients<?= $search_term === '' ? ' have been registered yet.' : ' match your search.' ?></td></tr>
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
