<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings | Tarin-Morales Dental Clinic</title>

    <link rel="stylesheet" href="account-settings.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css">
</head>

<body>

<header class="navbar">

    <div class="navbar-container">

        <a href="index.php" class="logo">
            <img src="../images/logo.jpg" alt="Tarin-Morales Dental Clinic">
        </a>

        <div class="menu-container">

            <button type="button" class="menu-button" id="menuButton">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="top-menu" id="topMenu">

                <a href="index.php">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>

                <a href="appointments.php">
                    <i class="fa-regular fa-calendar-days"></i>
                    <span>Appointments</span>
                </a>

                <a href="profile.php">
                    <i class="fa-regular fa-user"></i>
                    <span>Profile</span>
                </a>

            </div>

        </div>

        <div class="account">

            <div class="account-icon">
                <i class="fa-solid fa-user"></i>
            </div>

            <div class="account-info">
                <span>RenManSal</span>
                <small>Patient</small>
            </div>

            <i class="fa-solid fa-chevron-down account-arrow"></i>

        </div>

    </div>

</header>

<main class="main-content">

    <div class="content">

        <section class="welcome">

            <div>
                <span class="welcome-label">ACCOUNT SETTINGS</span>

                <h1>Account Settings</h1>

                <p>Manage your account settings.</p>
            </div>

            <div class="welcome-icon">
                <i class="fa-solid fa-gear"></i>
            </div>

        </section>

        <div class="section-title">

            <h2>Account</h2>

            <p>View and manage your account information.</p>

        </div>

        <div class="dashboard-grid">

            <div class="dashboard-card" id="accountInfoCard">

                <div class="card-top">

                    <div class="card-icon personal">
                        <i class="fa-solid fa-user"></i>
                    </div>

                </div>

                <h3>Account Information</h3>

                <p>
                    Manage your username and email address.
                </p>

                <div class="card-footer">
                    <span>Manage account</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>

            </div>

            <div class="dashboard-card" id="passwordCard">

                <div class="card-top">

                    <div class="card-icon balance">
                        <i class="fa-solid fa-lock"></i>
                    </div>

                </div>

                <h3>Password</h3>

                <p>
                    Change your account password.
                </p>

                <div class="card-footer">
                    <span>Change password</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>

            </div>

        </div>

    </div>

</main>

<div class="settings-modal" id="accountInfoModal">

    <div class="settings-modal-content">

        <button type="button" class="close-modal" id="closeAccountInfo">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <h2>Account Information</h2>

        <p>Update your username and email address.</p>

        <form>

            <label for="username">Username</label>

            <input
                type="text"
                id="username"
                value="RenManSal"
            >

            <label for="email">Email Address</label>

            <input
                type="email"
                id="email"
                placeholder="Enter your email"
            >

            <button type="button" class="save-button">
                Save Changes
            </button>

        </form>

    </div>

</div>

<div class="settings-modal" id="passwordModal">

    <div class="settings-modal-content">

        <button type="button" class="close-modal" id="closePassword">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <h2>Change Password</h2>

        <p>Update your account password.</p>

        <form>

            <label for="currentPassword">Current Password</label>

            <input
                type="password"
                id="currentPassword"
                placeholder="Enter current password"
            >

            <label for="newPassword">New Password</label>

            <input
                type="password"
                id="newPassword"
                placeholder="Enter new password"
            >

            <label for="confirmPassword">Confirm New Password</label>

            <input
                type="password"
                id="confirmPassword"
                placeholder="Confirm new password"
            >

            <button type="button" class="save-button">
                Change Password
            </button>

        </form>

    </div>

</div>

<script src="account-settings.js"></script>

</body>
</html>