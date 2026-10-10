<?php
require_once 'config.php';
session_start();

$patient_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$selected_appointment_id = filter_input(INPUT_GET, 'appointment_id', FILTER_VALIDATE_INT) ?: 0;
$dentist_id = filter_var($_SESSION['dentist_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$patient = null;
$selected_appointment = null;
$error = '';
$finished = isset($_GET['finished']) && $_GET['finished'] === '1';
if (empty($_SESSION['finish_session_token'])) {
    $_SESSION['finish_session_token'] = bin2hex(random_bytes(32));
}

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

if ($patient && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finish_session'])) {
    $posted_appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT) ?: 0;
    $posted_token = $_POST['finish_session_token'] ?? '';

    if ($dentist_id < 1 || $posted_appointment_id < 1
        || !is_string($posted_token)
        || !hash_equals($_SESSION['finish_session_token'], $posted_token)) {
        $error = 'This session could not be finished. Please reload the patient profile and try again.';
    } else {
        try {
            mysqli_begin_transaction($link);

            $appointment_stmt = mysqli_prepare($link, "SELECT appointment_id, patient_id, dentist_id, procedure_name, reason, status
                FROM tbl_appointments
                WHERE appointment_id = ? AND patient_id = ?
                LIMIT 1 FOR UPDATE");
            if (!$appointment_stmt) {
                throw new RuntimeException('Could not load the appointment.');
            }
            mysqli_stmt_bind_param($appointment_stmt, 'ii', $posted_appointment_id, $patient_id);
            mysqli_stmt_execute($appointment_stmt);
            $appointment_result = mysqli_stmt_get_result($appointment_stmt);
            $appointment_to_finish = mysqli_fetch_assoc($appointment_result) ?: null;
            mysqli_stmt_close($appointment_stmt);

            $finishable_statuses = ['pending', 'confirmed', 'for_dentist', 'in_progress', 'completed'];
            if (!$appointment_to_finish
                || !in_array(strtolower($appointment_to_finish['status']), $finishable_statuses, true)) {
                throw new RuntimeException('This appointment is no longer available to finish.');
            }

            $treatment_stmt = mysqli_prepare($link, 'SELECT treatment_id, treatment_notes FROM tbl_treatments WHERE appointment_id = ? LIMIT 1 FOR UPDATE');
            if (!$treatment_stmt) {
                throw new RuntimeException('Could not load the treatment details.');
            }
            mysqli_stmt_bind_param($treatment_stmt, 'i', $posted_appointment_id);
            mysqli_stmt_execute($treatment_stmt);
            $treatment_result = mysqli_stmt_get_result($treatment_stmt);
            $treatment = mysqli_fetch_assoc($treatment_result) ?: null;
            mysqli_stmt_close($treatment_stmt);

            if ($treatment) {
                $treatment_id = (int) $treatment['treatment_id'];
                $update_treatment_stmt = mysqli_prepare($link, "UPDATE tbl_treatments
                    SET status = 'completed', completed_at = COALESCE(completed_at, NOW()), updated_at = NOW()
                    WHERE treatment_id = ?");
                if (!$update_treatment_stmt) {
                    throw new RuntimeException('Could not complete the treatment.');
                }
                mysqli_stmt_bind_param($update_treatment_stmt, 'i', $treatment_id);
                mysqli_stmt_execute($update_treatment_stmt);
                mysqli_stmt_close($update_treatment_stmt);
                $treatment_notes = trim($treatment['treatment_notes'] ?? '');
            } else {
                $treatment_notes = '';
                $insert_treatment_stmt = mysqli_prepare($link, "INSERT INTO tbl_treatments
                    (appointment_id, dentist_id, procedure_name, status, started_at, completed_at)
                    VALUES (?, ?, ?, 'completed', NOW(), NOW())");
                if (!$insert_treatment_stmt) {
                    throw new RuntimeException('Could not create the treatment record.');
                }
                $appointment_dentist_id = (int) $appointment_to_finish['dentist_id'];
                $procedure_name = $appointment_to_finish['procedure_name'];
                mysqli_stmt_bind_param($insert_treatment_stmt, 'iis', $posted_appointment_id, $appointment_dentist_id, $procedure_name);
                mysqli_stmt_execute($insert_treatment_stmt);
                $treatment_id = (int) mysqli_insert_id($link);
                mysqli_stmt_close($insert_treatment_stmt);
            }

            $record_stmt = mysqli_prepare($link, 'SELECT record_id FROM tbl_dental_records WHERE appointment_id = ? LIMIT 1 FOR UPDATE');
            if (!$record_stmt) {
                throw new RuntimeException('Could not check the patient history.');
            }
            mysqli_stmt_bind_param($record_stmt, 'i', $posted_appointment_id);
            mysqli_stmt_execute($record_stmt);
            $record_result = mysqli_stmt_get_result($record_stmt);
            $record = mysqli_fetch_assoc($record_result) ?: null;
            mysqli_stmt_close($record_stmt);

            if (!$record) {
                $diagnosis = null;
                $treatment_summary = $treatment_notes !== ''
                    ? $treatment_notes
                    : 'Completed procedure: ' . $appointment_to_finish['procedure_name'] . '.';
                $remarks = trim($appointment_to_finish['reason'] ?? '');
                $remarks = $remarks !== '' ? $remarks : null;
                $insert_record_stmt = mysqli_prepare($link, 'INSERT INTO tbl_dental_records
                    (patient_id, appointment_id, dentist_id, treatment_id, diagnosis, treatment_summary, remarks)
                    VALUES (?, ?, ?, ?, ?, ?, ?)');
                if (!$insert_record_stmt) {
                    throw new RuntimeException('Could not add the visit to patient history.');
                }
                $appointment_dentist_id = (int) $appointment_to_finish['dentist_id'];
                mysqli_stmt_bind_param($insert_record_stmt, 'iiiisss', $patient_id, $posted_appointment_id, $appointment_dentist_id, $treatment_id, $diagnosis, $treatment_summary, $remarks);
                mysqli_stmt_execute($insert_record_stmt);
                mysqli_stmt_close($insert_record_stmt);
            }

            $complete_appointment_stmt = mysqli_prepare($link, "UPDATE tbl_appointments SET status = 'completed', updated_at = NOW() WHERE appointment_id = ?");
            if (!$complete_appointment_stmt) {
                throw new RuntimeException('Could not update the appointment status.');
            }
            mysqli_stmt_bind_param($complete_appointment_stmt, 'i', $posted_appointment_id);
            mysqli_stmt_execute($complete_appointment_stmt);
            mysqli_stmt_close($complete_appointment_stmt);

            mysqli_commit($link);
            header('Location: patient-profile.php?id=' . $patient_id . '&appointment_id=' . $posted_appointment_id . '&finished=1');
            exit();
        } catch (mysqli_sql_exception $exception) {
            mysqli_rollback($link);
            error_log('Finish session failed: ' . $exception->getMessage());
            $error = 'Could not finish the session or save it to patient history. Please try again.';
        } catch (RuntimeException $exception) {
            mysqli_rollback($link);
            $error = $exception->getMessage();
        }
    }
}

$active_statuses = ['pending', 'confirmed', 'for_dentist', 'in_progress'];
if ($patient) {
    $selected_sql = "SELECT appointment_id, dentist_id, appointment_date, appointment_time, procedure_name,
            reason, status AS appointment_status
        FROM tbl_appointments
        WHERE patient_id = ? AND (? = 0 OR appointment_id = ?)
        ORDER BY CASE WHEN status IN ('pending', 'confirmed', 'for_dentist', 'in_progress') THEN 0 ELSE 1 END,
            appointment_date DESC, appointment_time DESC
        LIMIT 1";
    $selected_stmt = mysqli_prepare($link, $selected_sql);
    if ($selected_stmt) {
        mysqli_stmt_bind_param($selected_stmt, 'iii', $patient_id, $selected_appointment_id, $selected_appointment_id);
        mysqli_stmt_execute($selected_stmt);
        $selected_result = mysqli_stmt_get_result($selected_stmt);
        $selected_appointment = mysqli_fetch_assoc($selected_result) ?: null;
        mysqli_stmt_close($selected_stmt);
        if ($selected_appointment) {
            $selected_appointment_id = (int) $selected_appointment['appointment_id'];
        }
    }
}
$can_finish_session = $selected_appointment
    && $dentist_id > 0
    && in_array(strtolower($selected_appointment['appointment_status']), $active_statuses, true);
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
                <p class="text-slate-500 text-sm mt-1">Patient profile and current appointment details.</p>
            </div>

            <?php if (!$patient): ?>
                <div class="bg-white border border-amber-200 rounded-2xl p-6 text-amber-800">This patient record could not be found. <a href="patients.php" class="underline font-semibold">Return to patients</a>.</div>
            <?php else: ?>
            <?php if ($finished): ?>
                <div class="mb-6 bg-green-50 border border-green-200 rounded-2xl p-4 text-green-800" role="status">Session finished and added to the admin patient history.</div>
            <?php elseif ($error !== ''): ?>
                <div class="mb-6 bg-red-50 border border-red-200 rounded-2xl p-4 text-red-800" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
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
                            <?php if ($can_finish_session): ?>
                                <div class="flex flex-wrap gap-3 mt-5">
                                    <a href="add-prescription.php?patient_id=<?= (int) $patient_id ?>&amp;appointment_id=<?= (int) $selected_appointment_id ?>" class="inline-flex items-center justify-center gap-2 px-4 py-2 btn-gradient text-white text-sm font-medium rounded-xl shadow-sm hover:shadow-md transition"><i class="fa-solid fa-prescription"></i> Add Prescription</a>
                                    <form method="POST" onsubmit="return confirm('Finish this session and add it to the admin patient history?');">
                                        <input type="hidden" name="finish_session_token" value="<?= htmlspecialchars($_SESSION['finish_session_token'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="appointment_id" value="<?= (int) $selected_appointment_id ?>">
                                        <button type="submit" name="finish_session" value="1" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-xl shadow-sm hover:bg-green-700 transition"><i class="fa-solid fa-check"></i> Finish Session</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="font-medium text-slate-500 mt-1">No appointment or procedure recorded yet.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-8"><a href="patients.php" class="flex items-center gap-2 px-6 py-3 bg-slate-100 text-slate-700 font-medium rounded-xl hover:bg-slate-200 transition"><i class="fa-solid fa-arrow-left"></i> Back to Patients</a></div>
            <?php endif; ?>

        </div>
    </main>
</div>

</body>
</html>