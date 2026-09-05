<?php
require_once 'config.php';
session_start();

$patient_id = $_GET['id'] ?? 1;

$id_column = 'id';
$col_res = mysqli_query($link, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='tbl_patients' AND COLUMN_NAME LIKE '%id%'");
if ($col_res && $col = mysqli_fetch_assoc($col_res)) {
    $id_column = $col['COLUMN_NAME'];
}

$sql = "SELECT * FROM tbl_patients WHERE $id_column = " . intval($patient_id);
$result = mysqli_query($link, $sql);
$patient = mysqli_fetch_assoc($result);

if (!$patient) {
    $patient = [
        'fullname' => 'Juan Dela Cruz',
        'age' => 28,
        'birthday' => '15 March 1998',
        'gender' => 'Male',
        'contact_number' => '0917 123 4567',
        'email' => 'juan.delacruz@email.com',
        'allergies' => 'Penicillin'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                <a href="dentist-dashboard.php" class="nav-option flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-600 font-medium transition">
                    <i class="fa-solid fa-house-chimney w-5 text-blue-600"></i>
                    <span>Dashboard</span>
                </a>
                <a href="appointments.php" class="nav-option flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-600 font-medium transition">
                    <i class="fa-solid fa-calendar-days w-5 text-blue-600"></i>
                    <span>Appointments</span>
                </a>
                <a href="patient-profile.php?id=1" class="nav-option flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-600 font-medium transition">
                    <i class="fa-solid fa-user-group w-5 text-blue-600"></i>
                    <span>Patients</span>
                </a>
                <a href="add-prescription.php?patient_id=1" class="nav-option flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-600 font-medium transition">
                    <i class="fa-solid fa-prescription w-5 text-blue-600"></i>
                    <span>Prescription</span>
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

    <main class="flex-1 flex flex-col bg-slate-50/50">
        <header class="bg-white border-b border-slate-100 px-6 py-4 min-h-[104px] flex items-center justify-between shadow-sm">
            <div>
                <h2 class="text-2xl md:text-3xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Patient Profile</h2>
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

        <div class="p-6 md:p-8 flex-1 overflow-y-auto">
            <div class="mb-8">
                <h3 class="text-3xl md:text-4xl font-bold text-black tracking-normal">Patient Details</h3>
                <p class="text-slate-500 text-sm mt-1">View and manage patient information.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="relative bg-white p-6 rounded-2xl border border-blue-200/60 shadow-md hover:shadow-lg transition overflow-hidden group">
                    <div class="flex flex-col items-center justify-center relative z-10">
                        <div class="w-20 h-20 rounded-full bg-white text-blue-400 flex items-center justify-center text-3xl mb-4 shadow-md border border-blue-300">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <h3 class="font-bold text-lg text-slate-800 text-center"><?= htmlspecialchars($patient['fullname']) ?></h3>
                        <span class="text-xs text-slate-400 font-medium mt-1">Patient ID: <?= $patient_id ?></span>
                    </div>
                </div>

                <div class="md:col-span-2 relative bg-white p-6 rounded-2xl border border-blue-200/60 shadow-md hover:shadow-lg transition overflow-hidden group">
                    <h4 class="text-sm font-bold uppercase tracking-wider text-blue-600 mb-4 relative z-10">Basic Information</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm relative z-10">
                        <div><span class="text-slate-400 block text-xs font-medium">Full Name</span><span class="font-semibold text-slate-800 mt-1"><?= htmlspecialchars($patient['fullname']) ?></span></div>
                        <div><span class="text-slate-400 block text-xs font-medium">Age</span><span class="font-semibold text-slate-800 mt-1"><?= htmlspecialchars($patient['age'] ?? '28') ?></span></div>
                        <div><span class="text-slate-400 block text-xs font-medium">Birthday</span><span class="font-semibold text-slate-800 mt-1"><?= htmlspecialchars($patient['birthday'] ?? '15 March 1998') ?></span></div>
                        <div><span class="text-slate-400 block text-xs font-medium">Gender</span><span class="font-semibold text-slate-800 mt-1"><?= htmlspecialchars($patient['gender'] ?? 'Male') ?></span></div>
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
                        <div><span class="text-slate-400 block text-xs font-medium">Phone Number</span><span class="font-semibold text-slate-800 mt-1"><?= htmlspecialchars($patient['contact_number'] ?? '0917 123 4567') ?></span></div>
                        <div><span class="text-slate-400 block text-xs font-medium">Email</span><span class="font-semibold text-slate-800 mt-1 break-all"><?= htmlspecialchars($patient['email'] ?? 'juan.delacruz@email.com') ?></span></div>
                    </div>
                </div>

                <div class="relative bg-white p-6 rounded-2xl border border-blue-200/60 shadow-md hover:shadow-lg transition overflow-hidden group">
                    <div class="flex items-center justify-between mb-4 relative z-10">
                        <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Medical Notes</span>
                        <div class="w-10 h-10 rounded-xl bg-white text-blue-400 flex items-center justify-center shadow-md border border-blue-300">
                            <i class="fa-solid fa-exclamation text-sm"></i>
                        </div>
                    </div>
                    <div class="relative z-10">
                        <span class="text-slate-400 block text-xs font-medium">Allergies</span>
                        <span class="font-semibold text-blue-600 mt-1 text-lg"><?= htmlspecialchars($patient['allergies'] ?? 'Penicillin') ?></span>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-8">
                <a href="dentist-dashboard.php" class="flex items-center gap-2 px-6 py-3 bg-slate-100 text-slate-700 font-medium rounded-xl hover:bg-slate-200 transition">
                    <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="add-prescription.php?patient_id=<?= $patient_id ?>" class="flex items-center gap-2 px-6 py-3 btn-gradient text-white font-medium rounded-xl shadow-md hover:shadow-lg transition">
                    <i class="fa-solid fa-prescription"></i> Add Prescription
                </a>
            </div>

        </div>
    </main>
</div>

</body>
</html>