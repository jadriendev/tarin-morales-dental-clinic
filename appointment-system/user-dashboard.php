<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header("Location: login.php?error=unauthorized");
    exit();
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT p.patient_id, p.first_name, p.middle_name, p.last_name, p.birth_date, p.sex,
           p.contact_number, p.address, p.date_registered,
           p.remaining_balance, p.remaining_session,
           u.email
    FROM tbl_patients p
    LEFT JOIN tbl_users u ON u.user_id = p.user_id
    WHERE p.user_id = :user_id
    LIMIT 1
");
$stmt->execute(['user_id' => $userId]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header("Location: login.php?error=unauthorized");
    exit();
}

// remaining_balance is a varchar and can be empty
$remainingBalance = is_numeric($patient['remaining_balance'] ?? null)
    ? (float)$patient['remaining_balance']
    : 0.0;

// the column is remaining_session (singular) and is a varchar
$remainingSessions = (int)($patient['remaining_session'] ?? 0);

$appointmentStmt = $pdo->prepare("
    SELECT appointment_date, appointment_time, procedure_name, status
    FROM tbl_appointments
    WHERE patient_id = :patient_id
      AND appointment_date >= CURDATE()
      AND status IN ('pending', 'confirmed', 'for_dentist')
    ORDER BY appointment_date ASC, appointment_time ASC
    LIMIT 1
");
$appointmentStmt->execute(['patient_id' => $patient['patient_id']]);
$nextAppointment = $appointmentStmt->fetch(PDO::FETCH_ASSOC);

$nextStatusLabel = $nextAppointment
    ? ucwords(str_replace('_', ' ', (string)$nextAppointment['status']))
    : 'No appointment';

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

$teethGood   = $teethConcernCount === 0;
$teethStatus = $teethGood ? 'Good' : 'Needs Attention';

$fullName = trim(
    $patient['first_name'] . ' ' .
    (!empty($patient['middle_name']) ? $patient['middle_name'] . ' ' : '') .
    $patient['last_name']
);

$displayName = $patient['first_name'];

$patientCode = 'P-' . str_pad((string)$patient['patient_id'], 4, '0', STR_PAD_LEFT);

$birthDate      = !empty($patient['birth_date']) ? date('M d, Y', strtotime($patient['birth_date'])) : 'N/A';
$dateRegistered = !empty($patient['date_registered']) ? date('M d, Y', strtotime($patient['date_registered'])) : 'N/A';
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
                    <span><?php echo e($displayName); ?></span>
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
                <h1>Welcome, <?php echo e($displayName); ?>!</h1>
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
                <div class="status-good<?php echo $teethGood ? '' : ' needs-attention'; ?>">
                    <span></span>
                    <?php echo e($teethStatus); ?>
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
                <strong class="card-number">₱ <?php echo number_format($remainingBalance, 2); ?></strong>
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
                    <span class="appointment-status"><?php echo e($nextStatusLabel); ?></span>
                </div>
                <h3>Next Appointment</h3>
                <?php if ($nextAppointment): ?>
                    <strong class="card-number">
                        <?php echo e(date('M d, Y', strtotime($nextAppointment['appointment_date']))); ?>
                    </strong>
                    <p>
                        <?php echo e(date('h:i A', strtotime($nextAppointment['appointment_time']))); ?>
                        -
                        <?php echo e($nextAppointment['procedure_name']); ?>
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
                <strong><?php echo e($patientCode); ?></strong>
            </div>
            <div>
                <span>Full Name</span>
                <strong><?php echo e($fullName); ?></strong>
            </div>
            <div>
                <span>Birth Date</span>
                <strong><?php echo e($birthDate); ?></strong>
            </div>
            <div>
                <span>Sex</span>
                <strong><?php echo e($patient['sex']); ?></strong>
            </div>
            <div>
                <span>Contact Number</span>
                <strong><?php echo e($patient['contact_number']); ?></strong>
            </div>
            <div>
                <span>Address</span>
                <strong><?php echo e($patient['address']); ?></strong>
            </div>
            <div>
                <span>Email</span>
                <strong><?php echo e($patient['email'] ?? 'N/A'); ?></strong>
            </div>
            <div>
                <span>Date Registered</span>
                <strong><?php echo e($dateRegistered); ?></strong>
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
                <strong class="<?php echo $teethGood ? 'good-text' : 'attention-text'; ?>">
                    <?php echo e($teethStatus); ?>
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
        <h2>Next Appointment</h2>
        <?php if ($nextAppointment): ?>
            <div class="information">
                <div>
                    <span>Date</span>
                    <strong><?php echo e(date('M d, Y', strtotime($nextAppointment['appointment_date']))); ?></strong>
                </div>
                <div>
                    <span>Time</span>
                    <strong><?php echo e(date('h:i A', strtotime($nextAppointment['appointment_time']))); ?></strong>
                </div>
                <div>
                    <span>Procedure</span>
                    <strong><?php echo e($nextAppointment['procedure_name']); ?></strong>
                </div>
                <div>
                    <span>Status</span>
                    <strong><?php echo e($nextStatusLabel); ?></strong>
                </div>
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
    </div>
</div>

<script src="user.js"></script>
</body>
</html>