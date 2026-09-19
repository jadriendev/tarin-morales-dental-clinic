<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header("Location: login.php?error=unauthorized");
    exit();
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT patient_id, first_name, middle_name, last_name, birth_date, sex, contact_number, address, date_registered
    FROM tbl_patients
    WHERE user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'user_id' => $userId
]);

$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header("Location: login.php?error=unauthorized");
    exit();
}

$fullName = trim(
    $patient['first_name'] . ' ' .
    ($patient['middle_name'] ? $patient['middle_name'] . ' ' : '') .
    $patient['last_name']
);

$patientId = 'P-' . str_pad($patient['patient_id'], 4, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Profile | Tarin-Morales Dental Clinic</title>

    <link rel="stylesheet" href="profile.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">

    <link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css"
      crossorigin="anonymous"
      referrerpolicy="no-referrer">
</head>

<body>

<header class="navbar">
    <div class="navbar-container">
        <a href="../user-dashboard.php" class="logo">
            <img src="../../images/logo.jpg" alt="Tarin-Morales Dental Clinic">
        </a>
        <div class="menu-container">
            <button type="button" class="menu-button" onclick="toggleMenu()">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="top-menu" id="topMenu">
                <a href="../user-dashboard.php">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <a href="../appointments.php">
                    <i class="fa-regular fa-calendar-days"></i>
                    <span>Appointments</span>
                </a>
                <a href="profile.php">
                    <i class="fa-regular fa-user"></i>
                    <span>Profile</span>
                </a>
            </div>
        </div>
        <div class="account-container">
            <div class="account" onclick="toggleAccountMenu()">
                <div class="account-icon">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div class="account-info">
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <small>Patient</small>
                </div>
                <i class="fa-solid fa-chevron-down account-arrow"></i>
            </div>
            <div class="account-menu" id="accountMenu">
                <a href="account-settings.php">
                    <i class="fa-solid fa-gear"></i>
                    <span>Account Settings</span>
                </a>
            </div>
        </div>
    </div>
</header>


<main class="main-content">
    <div class="content">
        <section class="profile-header">
            <div class="profile-icon">
                <i class="fa-solid fa-user"></i>
            </div>
            <div>
                <span class="profile-label">PATIENT PROFILE</span>
                <h1><?php echo htmlspecialchars($fullName); ?></h1>
                <p>
                    View your personal and patient information.
                </p>
            </div>
        </section>

        <section class="profile-card">
            <div class="section-title">
                <h2>Personal Information</h2>
                <p>Your registered patient information.</p>
            </div>
            <div class="information">
                <div>
                    <span>Patient ID</span>
                    <strong><?php echo htmlspecialchars($patientId); ?></strong>
                </div>
                <div>
                    <span>First Name</span>
                    <strong><?php echo htmlspecialchars($patient['first_name']); ?></strong>
                </div>
                <div>
                    <span>Middle Name</span>
                    <strong>
                        <?php echo htmlspecialchars($patient['middle_name'] ?: 'N/A'); ?>
                    </strong>
                </div>
                <div>
                    <span>Last Name</span>
                    <strong><?php echo htmlspecialchars($patient['last_name']); ?></strong>
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
                    <strong><?php echo htmlspecialchars($_SESSION['email']); ?></strong>
                </div>
                <div>
                    <span>Date Registered</span>
                    <strong><?php echo htmlspecialchars($patient['date_registered']); ?></strong>
                    </div>
            </div>
        </section>
    </div>
</main>

<script src="profile.js"></script>

</body>
</html>