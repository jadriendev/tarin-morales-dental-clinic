<?php
require_once 'config.php';
session_start();

$patient_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$selected_appointment_id = filter_input(INPUT_GET, 'appointment_id', FILTER_VALIDATE_INT) ?: 0;
$patient = null;
$appointments = [];
$selected_appointment = null;

$patient_sql = "SELECT p.*, u.email,
        CONCAT_WS(' ', p.first_name, NULLIF(p.middle_name, ''), p.last_name) AS patient_name,
        TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) AS age
    FROM tbl_patients p
    LEFT JOIN tbl_users u ON u.user_id = p.user_id
    WHERE p.patient_id = ? LIMIT 1";
$patient_stmt = mysqli_prepare($link, $patient_sql);
if ($patient_stmt) {
    mysqli_stmt_bind_param($patient_stmt, 'i', $patient_id);
    mysqli_stmt_execute($patient_stmt);
    $patient_result = mysqli_stmt_get_result($patient_stmt);
    $patient = mysqli_fetch_assoc($patient_result) ?: null;
    mysqli_stmt_close($patient_stmt);
}

if ($patient) {
    $history_sql = "SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.procedure_name,
            a.reason, a.status AS appointment_status,
            t.treatment_id, t.treatment_notes, t.prescription, t.remarks AS treatment_remarks,
            t.status AS treatment_status, dr.diagnosis, dr.treatment_summary, dr.remarks AS record_remarks
        FROM tbl_appointments a
        LEFT JOIN tbl_treatments t ON t.appointment_id = a.appointment_id
        LEFT JOIN tbl_dental_records dr ON dr.appointment_id = a.appointment_id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    $history_stmt = mysqli_prepare($link, $history_sql);
    if ($history_stmt) {
        mysqli_stmt_bind_param($history_stmt, 'i', $patient_id);
        mysqli_stmt_execute($history_stmt);
        $history_result = mysqli_stmt_get_result($history_stmt);
        while ($appointment = mysqli_fetch_assoc($history_result)) {
            $appointments[] = $appointment;
            if ((int) $appointment['appointment_id'] === $selected_appointment_id) {
                $selected_appointment = $appointment;
            }
        }
        mysqli_stmt_close($history_stmt);
    }

    if (!$selected_appointment && $appointments) {
        $selected_appointment = $appointments[0];
        $selected_appointment_id = (int) $selected_appointment['appointment_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon">
    <title>Patient Profile - Tarin-Morales Dental Clinic</title>
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
                <h2 class="text-2xl md:text-3xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Patient Details</h2>
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
                <h3 class="text-3xl md:text-4xl font-bold text-black tracking-normal"><?= $patient ? 'Patient Details' : 'Patient Not Found' ?></h3>
                <p class="text-slate-500 text-sm mt-1">Patient profile, scheduled procedures, and dental visit notes.</p>
            </div>

            <?php if (!$patient): ?>
                <div class="bg-white border border-amber-200 rounded-2xl p-6 text-amber-800">This patient record could not be found. <a href="patients.php" class="underline font-semibold">Return to patients</a>.</div>
            <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="relative bg-white p-6 rounded-2xl border border-blue-200/60 shadow-md hover:shadow-lg transition overflow-hidden group">
                    <div class="flex flex-col items-center justify-center relative z-10">
                        <div class="w-20 h-20 rounded-full bg-white text-blue-400 flex items-center justify-center text-3xl mb-4 shadow-md border border-blue-300">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <h3 class="font-bold text-lg text-slate-800 text-center"><?= htmlspecialchars($patient['patient_name'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <span class="text-xs text-slate-400 font-medium mt-1">Patient ID: <?= (int) $patient_id ?></span>
                    </div>
                </div>

                <div class="md:col-span-2 relative bg-white p-6 rounded-2xl border border-blue-200/60 shadow-md hover:shadow-lg transition overflow-hidden group">
                    <h4 class="text-sm font-bold uppercase tracking-wider text-blue-600 mb-4 relative z-10">Basic Information</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm relative z-10">
                        <div><span class="text-slate-400 block text-xs font-medium">Full Name</span><span class="font-semibold text-slate-800 mt-1"><?= htmlspecialchars($patient['patient_name'], ENT_QUOTES, 'UTF-8') ?></span></div>
                        <div><span class="text-slate-400 block text-xs font-medium">Age</span><span class="font-semibold text-slate-800 mt-1"><?= (int) $patient['age'] ?> years old</span></div>
                        <div><span class="text-slate-400 block text-xs font-medium">Birth Date</span><span class="font-semibold text-slate-800 mt-1"><?= htmlspecialchars(date('F j, Y', strtotime($patient['birth_date'])), ENT_QUOTES, 'UTF-8') ?></span></div>
                        <div><span class="text-slate-400 block text-xs font-medium">Sex</span><span class="font-semibold text-slate-800 mt-1"><?= htmlspecialchars($patient['sex'], ENT_QUOTES, 'UTF-8') ?></span></div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div class="relative bg-white p-6 rounded-2xl border border-blue-200/60 shadow-md hover:shadow-lg transition overflow-hidden group">
                    <div class="flex items-center justify-between mb-4 relative z-10">
                        <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Contact Information</span>
                        <div class="w-10 h-10 rounded-xl bg-white text-blue-400 flex items-center justify-center shadow-md border border-blue-300">
                            <i class="fa-solid fa-phone text-sm"></i>
                        </div>
                    </div>
                    <div class="space-y-3 relative z-10">
                        <div><span class="text-slate-400 block text-xs font-medium">Phone Number</span><span class="font-semibold text-slate-800 mt-1"><?= htmlspecialchars($patient['contact_number'], ENT_QUOTES, 'UTF-8') ?></span></div>
                        <div><span class="text-slate-400 block text-xs font-medium">Email</span><span class="font-semibold text-slate-800 mt-1 break-all"><?= htmlspecialchars($patient['email'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span></div>
                    </div>
                </div>

                <div class="relative bg-white p-6 rounded-2xl border border-blue-200/60 shadow-md hover:shadow-lg transition overflow-hidden group">
                    <div class="flex items-center justify-between mb-4 relative z-10">
                        <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Current / Recent Session</span>
                        <div class="w-10 h-10 rounded-xl bg-white text-blue-400 flex items-center justify-center shadow-md border border-blue-300">
                            <i class="fa-solid fa-exclamation text-sm"></i>
                        </div>
                    </div>
                    <div class="relative z-10">
                        <?php if ($selected_appointment): ?>
                            <span class="text-slate-400 block text-xs font-medium">Procedure</span>
                            <span class="font-semibold text-blue-600 mt-1 text-lg"><?= htmlspecialchars($selected_appointment['procedure_name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <p class="text-sm text-slate-600 mt-2"><?= htmlspecialchars($selected_appointment['reason'] ?: 'No visit reason recorded.', ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-xs text-slate-400 mt-2"><?= htmlspecialchars(date('M j, Y', strtotime($selected_appointment['appointment_date'])), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $selected_appointment['appointment_status'])), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php else: ?>
                            <span class="font-medium text-slate-500 mt-1">No appointment or procedure recorded yet.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <section class="mt-8 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100"><h4 class="text-lg font-bold text-slate-800">Appointment &amp; Treatment History</h4><p class="text-xs text-slate-400 mt-1">Session procedure, visit reason, treatment notes, and prescription for this patient.</p></div>
                <?php if ($appointments): ?>
                    <div class="divide-y divide-slate-100">
                        <?php foreach ($appointments as $appointment): ?>
                            <?php $is_selected = (int) $appointment['appointment_id'] === $selected_appointment_id; ?>
                            <article class="p-6 <?= $is_selected ? 'bg-blue-50/50' : '' ?>">
                                <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap"><h5 class="font-bold text-slate-800"><?= htmlspecialchars($appointment['procedure_name'], ENT_QUOTES, 'UTF-8') ?></h5><span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $appointment['appointment_status'])), ENT_QUOTES, 'UTF-8') ?></span></div>
                                        <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars(date('M j, Y', strtotime($appointment['appointment_date'])), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(date('g:i A', strtotime($appointment['appointment_time'])), ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-sm text-slate-600 mt-3"><span class="font-semibold">Visit reason:</span> <?= htmlspecialchars($appointment['reason'] ?: 'Not recorded.', ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php if ($appointment['diagnosis']): ?><p class="text-sm text-slate-600 mt-2"><span class="font-semibold">Diagnosis:</span> <?= nl2br(htmlspecialchars($appointment['diagnosis'], ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?>
                                        <?php if ($appointment['treatment_notes'] || $appointment['treatment_summary']): ?><p class="text-sm text-slate-600 mt-2"><span class="font-semibold">Session notes:</span> <?= nl2br(htmlspecialchars($appointment['treatment_notes'] ?: $appointment['treatment_summary'], ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?>
                                        <?php if ($appointment['prescription']): ?><p class="text-sm text-purple-700 mt-2"><span class="font-semibold">Prescription:</span> <?= nl2br(htmlspecialchars($appointment['prescription'], ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?>
                                    </div>
                                    <a href="add-prescription.php?patient_id=<?= (int) $patient_id ?>&amp;appointment_id=<?= (int) $appointment['appointment_id'] ?>" class="shrink-0 inline-flex items-center justify-center gap-2 px-4 py-2 btn-gradient text-white text-sm font-medium rounded-xl shadow-sm hover:shadow-md transition"><i class="fa-solid fa-prescription"></i> Add Prescription</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="px-6 py-10 text-center text-slate-400">No appointments or treatment sessions are recorded for this patient.</p>
                <?php endif; ?>
            </section>

            <div class="flex gap-3 mt-8"><a href="patients.php" class="flex items-center gap-2 px-6 py-3 bg-slate-100 text-slate-700 font-medium rounded-xl hover:bg-slate-200 transition"><i class="fa-solid fa-arrow-left"></i> Back to Patients</a></div>
            <?php endif; ?>

        </div>
    </main>
</div>

</body>
</html>