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
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($username === '' || $email === '' || $contactNumber === '' || $address === '') {
        $message = 'All editable fields are required.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } else {
        try {
            $pdo->beginTransaction();

            $updateUser = $pdo->prepare("
                UPDATE tbl_users
                SET username = :username, email = :email
                WHERE user_id = :user_id
            ");

            $updateUser->execute([
                'username' => $username,
                'email' => $email,
                'user_id' => $userId
            ]);

            $updatePatient = $pdo->prepare("
                UPDATE tbl_patients
                SET contact_number = :contact_number, address = :address
                WHERE user_id = :user_id
            ");

            $updatePatient->execute([
                'contact_number' => $contactNumber,
                'address' => $address,
                'user_id' => $userId
            ]);

            $pdo->commit();

            $patient['username'] = $username;
            $patient['email'] = $email;
            $patient['contact_number'] = $contactNumber;
            $patient['address'] = $address;

            $_SESSION['username'] = $username;

            $message = 'Profile information updated successfully.';
            $messageType = 'success';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = 'Unable to update profile information.';
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
    <title>Profile Settings | Tarin-Morales Dental Clinic</title>
    <link rel="stylesheet" href="profile.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
</head>

<body>

<header class="navbar">
    <div class="navbar-container">
        <a class="logo">
            <img src="../../images/logo.jpg" alt="Tarin-Morales Dental Clinic">
        </a>
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
                    <span>Dashboard</span>
                </a>
                <a href="../login.php?logout=1">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</header>

<main class="main-content">
    <div class="content">
        <div class="breadcrumb">
            <a href="../user-dashboard.php">Home</a>
            <span>></span>
            <span>Profile Settings</span>
        </div>
        <div class="page-header">
            <h1>Profile Settings</h1>
            <p>View and update your patient information.</p>
        </div>
        <?php if ($message !== ''): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <form method="POST" id="profileForm">
            <div class="card">
                <div class="head">
                    <h3>Account Information</h3>
                </div>
                <div class="settings-grid">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($patient['username']); ?>" readonly class="editable-field" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($patient['email']); ?>" readonly class="editable-field" required>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="head">
                    <h3>Contact Information</h3>
                </div>
                <div class="settings-grid">
                    <div class="form-group">
                        <label for="contact_number">Contact Number</label>
                        <input type="text" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($patient['contact_number']); ?>" readonly class="editable-field" required>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($patient['address']); ?>" readonly class="editable-field" required>
                    </div>
                </div>
            </div>
            <div class="card">
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
            <div class="form-actions">
                <button type="button" class="edit-button" id="editButton" onclick="enableEditing()">
                    <i class="fa-solid fa-pen"></i> Edit </button>
                <button type="submit" class="save-button" id="saveButton" disabled>
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes </button>
            </div>
        </form>
    </div>
</main>

<script src="profile.js" defer></script>
</body>
</html>