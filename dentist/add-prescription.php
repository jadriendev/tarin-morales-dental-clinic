<?php
require_once 'config.php';
session_start();

$patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT) ?: 0;
$appointment_id = filter_input(INPUT_GET, 'appointment_id', FILTER_VALIDATE_INT) ?: 0;
$appointment = null;
$treatment = null;
$error = '';
$success = '';

$appointment_sql = "SELECT a.appointment_id, a.patient_id, a.dentist_id, a.procedure_name,
        a.appointment_date, a.appointment_time, a.reason,
        CONCAT_WS(' ', p.first_name, NULLIF(p.middle_name, ''), p.last_name) AS patient_name
    FROM tbl_appointments a
    INNER JOIN tbl_patients p ON p.patient_id = a.patient_id
    WHERE a.appointment_id = ? AND a.patient_id = ? LIMIT 1";
$appointment_stmt = mysqli_prepare($link, $appointment_sql);
if ($appointment_stmt) {
    mysqli_stmt_bind_param($appointment_stmt, 'ii', $appointment_id, $patient_id);
    mysqli_stmt_execute($appointment_stmt);
    $appointment_result = mysqli_stmt_get_result($appointment_stmt);
    $appointment = mysqli_fetch_assoc($appointment_result) ?: null;
    mysqli_stmt_close($appointment_stmt);
}

if ($appointment) {
    $treatment_stmt = mysqli_prepare($link, 'SELECT treatment_id, treatment_notes, prescription FROM tbl_treatments WHERE appointment_id = ? LIMIT 1');
    if ($treatment_stmt) {
        mysqli_stmt_bind_param($treatment_stmt, 'i', $appointment_id);
        mysqli_stmt_execute($treatment_stmt);
        $treatment_result = mysqli_stmt_get_result($treatment_stmt);
        $treatment = mysqli_fetch_assoc($treatment_result) ?: null;
        mysqli_stmt_close($treatment_stmt);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $notes = trim($_POST['notes'] ?? '');
        $prescription = trim($_POST['prescription'] ?? '');
        $dentist_id = (int) $appointment['dentist_id'];

        if ($treatment) {
            $save_sql = 'UPDATE tbl_treatments SET treatment_notes = ?, prescription = ?, updated_at = NOW() WHERE treatment_id = ?';
            $save_stmt = mysqli_prepare($link, $save_sql);
            if ($save_stmt) {
                mysqli_stmt_bind_param($save_stmt, 'ssi', $notes, $prescription, $treatment['treatment_id']);
            }
        } else {
            $save_sql = "INSERT INTO tbl_treatments (appointment_id, dentist_id, procedure_name, treatment_notes, prescription, status, started_at)
                VALUES (?, ?, ?, ?, ?, 'in_progress', NOW())";
            $save_stmt = mysqli_prepare($link, $save_sql);
            if ($save_stmt) {
                mysqli_stmt_bind_param($save_stmt, 'iisss', $appointment_id, $dentist_id, $appointment['procedure_name'], $notes, $prescription);
            }
        }

        if (!empty($save_stmt) && mysqli_stmt_execute($save_stmt)) {
            header('Location: patient-profile.php?id=' . $patient_id . '&appointment_id=' . $appointment_id . '&saved=1');
            exit();
        }
        $error = 'Could not save the treatment details. Please try again.';
        if (!empty($save_stmt)) {
            mysqli_stmt_close($save_stmt);
        }
        $treatment['treatment_notes'] = $notes;
        $treatment['prescription'] = $prescription;
    }
} else {
    $error = 'Choose a valid appointment from a patient profile before adding a prescription.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon">
    <title>Patient Prescription - Tarin-Morales Dental Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <a href="appointments.php" class="nav-option flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-600 font-medium transition"><i class="fa-solid fa-calendar-days w-5 text-blue-600"></i><span>Appointments</span></a>
                <a href="patients.php" class="nav-option flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-600 font-medium transition"><i class="fa-solid fa-user-group w-5 text-blue-600"></i><span>Patients</span></a>
            </nav>
        </div>
        <div class="pt-6 border-t border-slate-100"><a href="logout.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-red-600 hover:bg-red-50 font-medium transition"><i class="fa-solid fa-right-from-bracket w-5"></i><span>Logout</span></a></div>
    </aside>
    <main class="flex-1 min-h-0 md:h-screen md:overflow-hidden flex flex-col bg-slate-50/50">
        <header class="bg-white border-b border-slate-100 px-6 py-4 min-h-[104px] flex items-center justify-between shadow-sm">
            <h2 class="text-2xl md:text-3xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Patient Prescription</h2>
            <div class="flex items-center gap-5">
                <button class="notification-circle w-14 h-14 rounded-full flex items-center justify-center text-brandPurple transition relative"><i class="fa-regular fa-bell text-xl"></i><span class="absolute top-2 right-2 w-3 h-3 bg-purple-600 rounded-full ring-2 ring-white"></span></button>
                <div class="dentist-profile flex items-center gap-3 px-1.5 py-1 rounded-full"><div class="dentist-avatar w-11 h-11 rounded-full flex items-center justify-center text-lg shadow-sm"><i class="fa-solid fa-user-doctor"></i></div><span class="dentist-label font-bold text-base pr-4">Dentist</span></div>
            </div>
        </header>
        <div class="p-6 md:p-8 flex-1 min-h-0 overflow-y-auto">
            <div class="max-w-3xl mx-auto bg-white rounded-2xl border border-blue-100 shadow-md p-6 md:p-8">
                <div class="flex items-center gap-3 pb-5 border-b border-slate-100 mb-6">
                    <a href="<?= $appointment ? 'patient-profile.php?id=' . (int) $patient_id . '&appointment_id=' . (int) $appointment_id : 'patients.php' ?>" class="w-10 h-10 rounded-xl icon-blue flex items-center justify-center hover:bg-blue-100 transition"><i class="fa-solid fa-arrow-left"></i></a>
                    <div><p class="text-xs font-bold uppercase tracking-wider text-blue-600">Treatment Record</p><p class="text-sm text-slate-500 mt-1">Record session notes and the patient’s prescription.</p></div>
                </div>

    <?php if ($error): ?><div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($appointment): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <div class="rounded-xl bg-slate-50 p-4"><span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Patient</span><span class="mt-1 block font-semibold text-slate-800"><?= htmlspecialchars($appointment['patient_name'], ENT_QUOTES, 'UTF-8') ?></span></div>
            <div class="rounded-xl bg-slate-50 p-4"><span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Session</span><span class="mt-1 block font-semibold text-slate-800"><?= htmlspecialchars($appointment['procedure_name'], ENT_QUOTES, 'UTF-8') ?></span></div>
            <div class="rounded-xl bg-slate-50 p-4"><span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Appointment date</span><span class="mt-1 block font-semibold text-slate-800"><?= htmlspecialchars(date('F j, Y', strtotime($appointment['appointment_date'])), ENT_QUOTES, 'UTF-8') ?></span></div>
            <div class="rounded-xl bg-slate-50 p-4"><span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Visit reason</span><span class="mt-1 block font-semibold text-slate-800"><?= htmlspecialchars($appointment['reason'] ?: 'Not recorded.', ENT_QUOTES, 'UTF-8') ?></span></div>
        </div>
        <form method="POST" class="space-y-4">
            <div><label for="notes" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Session / Treatment Notes</label><textarea id="notes" name="notes" rows="4" placeholder="For example: adjusted upper braces; patient tolerated the procedure well." class="w-full p-4 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition"><?= htmlspecialchars($treatment['treatment_notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></div>
            <div><label for="prescription" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Prescription</label><textarea id="prescription" name="prescription" rows="4" placeholder="Enter prescribed medication or instructions..." class="w-full p-4 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition"><?= htmlspecialchars($treatment['prescription'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></div>
            <div class="pt-2"><button type="submit" class="w-full py-3 btn-gradient text-white font-medium rounded-xl shadow-md hover:shadow-lg transition"><i class="fa-solid fa-floppy-disk mr-2"></i> Save Treatment &amp; Prescription</button></div>
        </form>
    <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>