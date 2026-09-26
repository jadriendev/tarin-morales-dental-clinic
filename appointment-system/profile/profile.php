<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT p.patient_id, p.first_name, p.middle_name, p.last_name, p.birth_date,
           p.sex, p.contact_number, p.address, p.date_registered,
           u.username, u.email
    FROM tbl_patients p
    JOIN tbl_users u ON p.user_id = u.user_id
    WHERE p.user_id = :user_id
    LIMIT 1
");

$stmt->execute(['user_id' => $userId]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

$patientId = 'P-' . str_pad($patient['patient_id'], 4, '0', STR_PAD_LEFT);

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($contactNumber === '' || $address === '') {
        $message = 'Contact number and address are required.';
        $messageType = 'error';
    } else {
        try {
            $update = $pdo->prepare("
                UPDATE tbl_patients
                SET contact_number = :contact_number, address = :address
                WHERE user_id = :user_id
            ");

            $update->execute([
                'contact_number' => $contactNumber,
                'address' => $address,
                'user_id' => $userId
            ]);

            $patient['contact_number'] = $contactNumber;
            $patient['address'] = $address;

            $message = 'Contact information updated successfully.';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Unable to update contact information.';
            $messageType = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings | Tarin-Morales Dental Clinic</title>
    <link rel="stylesheet" href="./profile.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
</head>
<body>
<header class="navbar">
    <div class="navbar-container">
        <a href="../user-dashboard.php" class="logo">
            <img src="../../images/logo.jpg" alt="Tarin-Morales Dental Clinic"> </a>
        <div class="account-container">
            <div class="account" onclick="toggleAccountMenu()">
                <div class="account-icon">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div class="account-info">
                    <span><?php echo htmlspecialchars($patient['username']); ?></span>
                    <small>Patient</small>
                </div>
                <i class="fa-solid fa-chevron-down account-arrow"></i>
            </div>
            <div class="account-menu" id="accountMenu">
                <a href="../user-dashboard.php">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span> </a>
                <a href="../login.php?logout=1">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Logout</span> </a>
            </div>
        </div>
    </div>
</header>

<main class="main-content">
    <div class="content">
        <div class="page-header">
            <span>ACCOUNT SETTINGS</span>
            <h1>Account Settings</h1>
            <p>Manage your account and patient information.</p>
        </div>
        <?php if ($message !== ''): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <div class="card mb-4">
            <div class="head">
                <h3>Account Information</h3>
            </div>
            <div class="settings-grid">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" value="<?php echo htmlspecialchars($patient['username']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" value="<?php echo htmlspecialchars($patient['email']); ?>" readonly>
                </div>
            </div>
        </div>
        <div class="card mb-4">
            <div class="head">
                <h3>Contact Information</h3>
            </div>
            <form method="POST">
                <div class="settings-grid">
                    <div class="form-group">
                        <label for="contact_number">Contact Number</label>
                        <input type="text" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($patient['contact_number']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($patient['address']); ?>" required>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit">
                        <i class="fa-solid fa-floppy-disk"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
        <div class="card mb-4">
            <div class="head">
                <h3>Personal Information</h3>
            </div>
            <div class="settings-grid">
                <div class="form-group">
                    <label>Patient ID</label>
                    <input type="text" value="<?php echo htmlspecialchars($patientId); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" value="<?php echo htmlspecialchars($patient['first_name']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" value="<?php echo htmlspecialchars($patient['middle_name'] ?: 'N/A'); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" value="<?php echo htmlspecialchars($patient['last_name']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Birth Date</label>
                    <input type="text" value="<?php echo htmlspecialchars($patient['birth_date']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Sex</label>
                    <input type="text" value="<?php echo htmlspecialchars($patient['sex']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Date Registered</label>
                    <input type="text" value="<?php echo htmlspecialchars($patient['date_registered']); ?>" readonly>
                </div>
            </div>
            <p class="settings-note">Need to correct your personal information? Please contact the clinic.</p>
        </div>
    </div>
</main>

<script src="profile.js"></script>
</body>
</html>