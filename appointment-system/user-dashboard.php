<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header("Location: login.php?error=unauthorized");
    exit();
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT patient_id, first_name, middle_name, last_name, birth_date, sex,
           contact_number, address, date_registered, remaining_balance, remaining_session
    FROM tbl_patients
    WHERE user_id = :user_id
    LIMIT 1
");
$stmt->execute(['user_id' => $userId]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header("Location: login.php?error=unauthorized");
    exit();
}

$remainingBalance  = (float) ($patient['remaining_balance'] ?? 0);
$remainingSessions = (int) ($patient['remaining_session'] ?? 0);

$sessionStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_dental_records WHERE patient_id = :patient_id");
$sessionStmt->execute(['patient_id' => $patient['patient_id']]);
$completedSessions = (int) $sessionStmt->fetchColumn();

$appointmentStmt = $pdo->prepare("
    SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.procedure_name,
           a.reason, a.status, d.first_name AS dentist_first, d.last_name AS dentist_last
    FROM tbl_appointments a
    LEFT JOIN tbl_dentists d ON d.dentist_id = a.dentist_id
    WHERE a.patient_id = :patient_id
      AND a.appointment_date >= CURDATE()
      AND a.status IN ('pending', 'confirmed', 'for_dentist', 'in_progress')
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$appointmentStmt->execute(['patient_id' => $patient['patient_id']]);
$upcomingAppointments = $appointmentStmt->fetchAll(PDO::FETCH_ASSOC);
$nextAppointment = $upcomingAppointments[0] ?? null;
$moreAppointments = max(0, count($upcomingAppointments) - 1);

function statusLabel($status)
{
    return ucfirst(str_replace('_', ' ', (string) $status));
}

$historyStmt = $pdo->prepare("
    SELECT a.appointment_date, a.appointment_time, a.procedure_name,
           a.reason, a.status, d.first_name AS dentist_first, d.last_name AS dentist_last
    FROM tbl_appointments a
    LEFT JOIN tbl_dentists d ON d.dentist_id = a.dentist_id
    WHERE a.patient_id = :patient_id
      AND (a.appointment_date < CURDATE() OR a.status IN ('completed', 'cancelled', 'no_show'))
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$historyStmt->execute(['patient_id' => $patient['patient_id']]);
$appointmentHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
$historyCount = count($appointmentHistory);
$completedCount = 0;
foreach ($appointmentHistory as $past) {
    if ($past['status'] === 'completed') {
        $completedCount++;
    }
}

$teethStmt = $pdo->prepare("
    SELECT tooth_condition
    FROM tbl_patient_teeth
    WHERE patient_id = :patient_id
");
$teethStmt->execute(['patient_id' => $patient['patient_id']]);
$teethRecords = $teethStmt->fetchAll(PDO::FETCH_ASSOC);

$teethConcernCount = 0;
foreach ($teethRecords as $tooth) {
    if (!empty($tooth['tooth_condition']) && strtolower($tooth['tooth_condition']) !== 'good') {
        $teethConcernCount++;
    }
}
$teethStatus      = $teethConcernCount > 0 ? 'Needs Attention' : 'Good';
$teethStatusClass = $teethConcernCount > 0 ? 'status-warning' : 'status-good';

$fullName = trim(
    $patient['first_name'] . ' ' .
    ($patient['middle_name'] ? $patient['middle_name'] . ' ' : '') .
    $patient['last_name']
);
$displayName = $patient['first_name'];
$patientId   = 'P-' . str_pad($patient['patient_id'], 4, '0', STR_PAD_LEFT);
$email       = $_SESSION['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard | Tarin-Morales Dental Clinic</title>

    <link rel="stylesheet" href="user.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css"
        crossorigin="anonymous"
        referrerpolicy="no-referrer">
    <style>
    .appointment-list {
        max-height: 60vh;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 0;
        padding-right: 4px;
    }

    .appointment-item {
        flex-shrink: 0;
        margin: 16px 0;
    }

    .appointment-item-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .appointment-item-head strong {
        font-size: 13px;
    }

    .appointment-item .information {
        gap: 0;
        margin-top: 6px;
    }

    .appointment-item .information div:last-child {
        border-bottom: none;
    }

    .appointment-status.status-completed {
        background: #edf9f2;
        color: #25854d;
    }

    .appointment-status.status-cancelled,
    .appointment-status.status-no_show {
        background: #fdeeee;
        color: #c0392b;
    }

    .status-warning {
        width: fit-content;
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 5px 9px;
        margin-bottom: 7px;
        border-radius: 6px;
        background: #fff4e5;
        color: #b45f06;
        font-size: 10px;
        font-weight: 800;
    }

    .status-warning span {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #f39c12;
    }

    .warning-text {
        color: #b45f06 !important;
    }
    </style>
</head>

<body>
<header class="navbar">
    <div class="navbar-container">
        <a class="logo">
            <img src="../images/logo.jpg" alt="Tarin-Morales Dental Clinic">
        </a>
        <div class="account-container">
            <div class="account" onclick="toggleAccountMenu()">
                <div class="account-icon">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div class="account-info">
                    <span><?php echo htmlspecialchars($displayName); ?></span>
                    <small>Patient</small>
                </div>
                <i class="fa-solid fa-chevron-down account-arrow"></i>
            </div>
            <div class="account-menu" id="accountMenu">
                <a href="profile/profile.php">
                    <i class="fa-solid fa-user-gear"></i>
                    <span>Profile Settings</span>
                </a>
                <a href="login.php?logout=1" title="Logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</header>

<main class="main-content">
    <div class="content">
        <section class="welcome">
            <div>
                <span class="welcome-label">PATIENT DASHBOARD</span>
                <h1>Welcome, <?php echo htmlspecialchars($displayName); ?>!</h1>
                <p>View your appointments and dental information.</p>
            </div>
            <div class="welcome-icon">
                <i class="fa-solid fa-tooth"></i>
            </div>
        </section>

        <div class="section-title">
            <h2>Your Information</h2>
            <p>View your dental and account information.</p>
        </div>

        <section class="dashboard-grid">
            <div class="dashboard-card" onclick="openModal('personalModal')">
                <div class="card-top">
                    <div class="card-icon personal">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square card-arrow"></i>
                </div>
                <h3>Personal Information</h3>
                <p>View your personal information and patient details.</p>
                <div class="card-footer">
                    <span>View information</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </div>

            <div class="dashboard-card" onclick="openModal('teethModal')">
                <div class="card-top">
                    <div class="card-icon teeth">
                        <i class="fa-solid fa-tooth"></i>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square card-arrow"></i>
                </div>
                <h3>Teeth Condition Status</h3>
                <div class="<?php echo $teethStatusClass; ?>">
                    <span></span>
                    <?php echo htmlspecialchars($teethStatus); ?>
                </div>
                <p>View your current teeth condition.</p>
                <div class="card-footer">
                    <span>View condition</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </div>

            <div class="dashboard-card" onclick="openModal('balanceModal')">
                <div class="card-top">
                    <div class="card-icon balance">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square card-arrow"></i>
                </div>
                <h3>Remaining Balance</h3>
                <strong class="card-number">
                    ₱ <?php echo number_format($remainingBalance, 2); ?>
                </strong>
                <p>Outstanding balance.</p>
                <div class="card-footer">
                    <span>View balance</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </div>

            <div class="dashboard-card" onclick="openModal('sessionsModal')">
                <div class="card-top">
                    <div class="card-icon sessions">
                        <i class="fa-solid fa-clipboard-list"></i>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square card-arrow"></i>
                </div>
                <h3>Remaining Sessions</h3>
                <strong class="card-number">
                    <?php echo $remainingSessions; ?>
                    <?php echo $remainingSessions === 1 ? 'Session' : 'Sessions'; ?>
                </strong>
                <p>Sessions left to complete.</p>
                <div class="card-footer">
                    <span>View sessions</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </div>

            <div class="dashboard-card" onclick="openModal('appointmentModal')">
                <div class="card-top">
                    <div class="card-icon appointment">
                        <i class="fa-regular fa-calendar-days"></i>
                    </div>
                    <span class="appointment-status">
                        <?php echo $nextAppointment
                            ? htmlspecialchars(statusLabel($nextAppointment['status']))
                            : 'No appointment'; ?>
                    </span>
                </div>
                <h3>Next Appointment</h3>
                <?php if ($nextAppointment): ?>
                    <strong class="appointment-empty">
                        <?php echo date('M d, Y', strtotime($nextAppointment['appointment_date'])); ?>
                    </strong>
                    <p>
                        <?php echo date('h:i A', strtotime($nextAppointment['appointment_time'])); ?>
                        -
                        <?php echo htmlspecialchars($nextAppointment['procedure_name']); ?>
                        <?php if ($moreAppointments > 0): ?>
                            <br>+<?php echo $moreAppointments; ?> more upcoming
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <strong class="appointment-empty">No upcoming appointment</strong>
                    <p>Your appointment will be scheduled by the clinic.</p>
                <?php endif; ?>
                <div class="card-footer">
                    <span>View appointment</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </div>
            <div class="dashboard-card" onclick="openModal('historyModal')">
                <div class="card-top">
                    <div class="card-icon history">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square card-arrow"></i>
                </div>
                <h3>Appointment History</h3>
                <strong class="card-number">
                    <?php echo $historyCount; ?>
                    <?php echo $historyCount === 1 ? 'Record' : 'Records'; ?>
                </strong>
                <p><?php echo $completedCount; ?> completed appointment<?php echo $completedCount === 1 ? '' : 's'; ?>.</p>
                <div class="card-footer">
                    <span>View history</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </div>
        </section>
    </div>
</main>

<div class="modal" id="personalModal">
    <div class="modal-box">
        <button class="close-button" onclick="closeModal('personalModal')">&times;</button>
        <div class="modal-icon personal">
            <i class="fa-solid fa-user"></i>
        </div>
        <h2>Personal Information</h2>
        <div class="information">
            <div>
                <span>Patient ID</span>
                <strong><?php echo htmlspecialchars($patientId); ?></strong>
            </div>
            <div>
                <span>Full Name</span>
                <strong><?php echo htmlspecialchars($fullName); ?></strong>
            </div>
            <div>
                <span>Birth Date</span>
                <strong><?php echo htmlspecialchars($patient['birth_date']); ?></strong>
            </div>
            <div>
                <span>Sex</span>
                <strong><?php echo htmlspecialchars($patient['sex']); ?></strong>
            </div>
            <div>
                <span>Contact Number</span>
                <strong><?php echo htmlspecialchars($patient['contact_number']); ?></strong>
            </div>
            <div>
                <span>Address</span>
                <strong><?php echo htmlspecialchars($patient['address']); ?></strong>
            </div>
            <div>
                <span>Email</span>
                <strong><?php echo htmlspecialchars($email); ?></strong>
            </div>
            <div>
                <span>Date Registered</span>
                <strong><?php echo htmlspecialchars($patient['date_registered']); ?></strong>
            </div>
        </div>
        <a href="profile/profile.php" class="modal-button">View Full Profile</a>
    </div>
</div>

<div class="modal" id="teethModal">
    <div class="modal-box">
        <button class="close-button" onclick="closeModal('teethModal')">&times;</button>
        <div class="modal-icon teeth">
            <i class="fa-solid fa-tooth"></i>
        </div>
        <h2>Teeth Condition</h2>
        <div class="information">
            <div>
                <span>Current Status</span>
                <strong class="<?php echo $teethConcernCount > 0 ? 'warning-text' : 'good-text'; ?>">
                    <?php echo htmlspecialchars($teethStatus); ?>
                </strong>
            </div>
            <div>
                <span>Teeth with concerns</span>
                <strong><?php echo $teethConcernCount; ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="appointmentModal">
    <div class="modal-box">
        <button class="close-button" onclick="closeModal('appointmentModal')">&times;</button>
        <div class="modal-icon appointment">
            <i class="fa-regular fa-calendar-days"></i>
        </div>
        <h2>Upcoming Appointments</h2>
        <?php if ($upcomingAppointments): ?>
            <div class="appointment-list">
                <?php foreach ($upcomingAppointments as $appt): ?>
                    <div class="appointment-item">
                        <div class="appointment-item-head">
                            <strong><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></strong>
                            <span class="appointment-status"><?php echo htmlspecialchars(statusLabel($appt['status'])); ?></span>
                        </div>
                        <div class="information">
                            <div>
                                <span>Time</span>
                                <strong><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></strong>
                            </div>
                            <div>
                                <span>Procedure</span>
                                <strong><?php echo htmlspecialchars($appt['procedure_name']); ?></strong>
                            </div>
                            <?php if (!empty($appt['dentist_last'])): ?>
                                <div>
                                    <span>Dentist</span>
                                    <strong>Dr. <?php echo htmlspecialchars(trim($appt['dentist_first'] . ' ' . $appt['dentist_last'])); ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($appt['reason'])): ?>
                                <div>
                                    <span>Reason</span>
                                    <strong><?php echo htmlspecialchars($appt['reason']); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-message">
                <i class="fa-regular fa-calendar-xmark"></i>
                <p>No upcoming appointment.</p>
                <small>You currently don't have a scheduled appointment.</small>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal" id="historyModal">
    <div class="modal-box">
        <button class="close-button" onclick="closeModal('historyModal')">&times;</button>
        <div class="modal-icon history">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <h2>Appointment History</h2>
        <?php if ($appointmentHistory): ?>
            <div class="appointment-list">
                <?php foreach ($appointmentHistory as $past): ?>
                    <div class="appointment-item">
                        <div class="appointment-item-head">
                            <strong><?php echo date('M d, Y', strtotime($past['appointment_date'])); ?></strong>
                            <span class="appointment-status status-<?php echo htmlspecialchars($past['status']); ?>">
                                <?php echo htmlspecialchars(statusLabel($past['status'])); ?>
                            </span>
                        </div>
                        <div class="information">
                            <div>
                                <span>Time</span>
                                <strong><?php echo date('h:i A', strtotime($past['appointment_time'])); ?></strong>
                            </div>
                            <div>
                                <span>Procedure</span>
                                <strong><?php echo htmlspecialchars($past['procedure_name']); ?></strong>
                            </div>
                            <?php if (!empty($past['dentist_last'])): ?>
                                <div>
                                    <span>Dentist</span>
                                    <strong>Dr. <?php echo htmlspecialchars(trim($past['dentist_first'] . ' ' . $past['dentist_last'])); ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($past['reason'])): ?>
                                <div>
                                    <span>Reason</span>
                                    <strong><?php echo htmlspecialchars($past['reason']); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-message">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <p>No appointment history yet.</p>
                <small>Your past appointments will appear here.</small>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal" id="balanceModal">
    <div class="modal-box">
        <button class="close-button" onclick="closeModal('balanceModal')">&times;</button>
        <div class="modal-icon balance">
            <i class="fa-solid fa-wallet"></i>
        </div>
        <h2>Remaining Balance</h2>
        <div class="balance-display">₱ <?php echo number_format($remainingBalance, 2); ?></div>
        <p class="modal-note">Outstanding balance</p>
    </div>
</div>

<div class="modal" id="sessionsModal">
    <div class="modal-box">
        <button class="close-button" onclick="closeModal('sessionsModal')">&times;</button>
        <div class="modal-icon sessions">
            <i class="fa-solid fa-clipboard-list"></i>
        </div>
        <h2>Remaining Sessions</h2>
        <div class="balance-display">
            <?php echo $remainingSessions; ?>
            <?php echo $remainingSessions === 1 ? 'Session' : 'Sessions'; ?>
        </div>
        <p class="modal-note">Completed so far: <?php echo $completedSessions; ?></p>
    </div>
</div>

<script src="user.js"></script>
</body>
</html>