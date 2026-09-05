<?php
require_once 'config.php';
require_once 'session-checker.php';

$appointment_id = $_GET['id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notes = trim($_POST['notes']);
    $outcome = trim($_POST['outcome']);

    $sql = "UPDATE tbl_appointments SET status = 'Completed', notes = ?, outcome = ? WHERE id = ?";
    $stmt = mysqli_prepare($link, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssi", $notes, $outcome, $appointment_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_msg'] = "Appointment marked as complete.";
            header("location: dentist-dashboard.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complete Appointment - Tarin-Morales Dental Clinic</title>
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
<div class="w-full min-h-screen bg-white overflow-hidden flex flex-col md:flex-row">
    <aside class="w-full md:w-72 bg-white border-r border-slate-100 flex flex-col justify-between p-6">
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
                <a href="patient-profile.php?id=1" class="nav-option flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-600 font-medium transition"><i class="fa-solid fa-user-group w-5 text-blue-600"></i><span>Patients</span></a>
                <a href="add-prescription.php?patient_id=1" class="nav-option flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-600 font-medium transition"><i class="fa-solid fa-prescription w-5 text-blue-600"></i><span>Prescription</span></a>
            </nav>
        </div>
        <div class="pt-6 border-t border-slate-100"><a href="logout.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-red-600 hover:bg-red-50 font-medium transition"><i class="fa-solid fa-right-from-bracket w-5"></i><span>Logout</span></a></div>
    </aside>
    <main class="flex-1 flex flex-col bg-slate-50/50">
        <header class="bg-white border-b border-slate-100 px-6 py-4 min-h-[104px] flex items-center justify-between shadow-sm">
            <h2 class="text-2xl md:text-3xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Complete Appointment</h2>
            <div class="flex items-center gap-5">
                <button class="notification-circle w-14 h-14 rounded-full flex items-center justify-center text-brandPurple transition relative"><i class="fa-regular fa-bell text-xl"></i><span class="absolute top-2 right-2 w-3 h-3 bg-purple-600 rounded-full ring-2 ring-white"></span></button>
                <div class="dentist-profile flex items-center gap-3 px-1.5 py-1 rounded-full"><div class="dentist-avatar w-11 h-11 rounded-full flex items-center justify-center text-lg shadow-sm"><i class="fa-solid fa-user-doctor"></i></div><span class="dentist-label font-bold text-base pr-4">Dentist</span></div>
            </div>
        </header>
        <div class="p-6 md:p-8 flex-1 overflow-y-auto">
            <div class="max-w-3xl mx-auto bg-white rounded-2xl border border-blue-100 shadow-md p-6 md:p-8">
                <div class="flex items-center gap-3 pb-5 border-b border-slate-100 mb-6">
                    <a href="dentist-dashboard.php" class="w-10 h-10 rounded-xl icon-blue flex items-center justify-center hover:bg-blue-100 transition"><i class="fa-solid fa-arrow-left"></i></a>
                    <div><p class="text-xs font-bold uppercase tracking-wider text-blue-600">Appointment Record</p><p class="text-sm text-slate-500 mt-1">Add the final notes and outcome for this visit.</p></div>
                </div>

    <form method="POST" class="space-y-4">
        <div>
            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Patient</label>
            <input type="text" value="Juan Dela Cruz" readonly class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Notes (Optional)</label>
            <textarea name="notes" rows="4" placeholder="Enter notes..." class="w-full p-4 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition"></textarea>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Outcome</label>
            <select name="outcome" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none">
                <option value="">Select outcome</option>
                <option value="Successful Procedure">Successful Procedure</option>
                <option value="Follow-up Required">Follow-up Required</option>
                <option value="Rescheduled">Rescheduled</option>
            </select>
        </div>

        <div class="pt-4 text-center">
            <button type="submit" class="w-full py-3 btn-gradient text-white font-medium rounded-xl shadow-md hover:shadow-lg transition">Mark as Complete</button>
        </div>
    </form>
            </div>
        </div>
    </main>
</div>
</body>
</html>