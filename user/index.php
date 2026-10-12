<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard | Tarin-Morales Dental Clinic</title>
    <link rel="stylesheet" href="user.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css"
          integrity="sha512-QeR2VH+lsBE5LSAe1Q5EnTBbe7XTBubt8dG93Y7gidSgdMCr8nVqKcfKAMyN96SV8KDbZVTDXChatu5G2KQGzg=="
          crossorigin="anonymous"
          referrerpolicy="no-referrer">
</head>
<body>

<!-- NAVBAR -->

<header class="navbar">
    <div class="navbar-container">

        <a href="index.php" class="logo">
            <img src="../images/logo.jpg" alt="Tarin-Morales Dental Clinic">
        </a>

        <div class="menu-container">
            <button type="button" class="menu-button" onclick="toggleMenu()">
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

        <div class="account-container">

            <div class="account" onclick="toggleAccountMenu()">

                <div class="account-icon">
                    <i class="fa-solid fa-user"></i>
                </div>

                <div class="account-info">
                    <span>RenManSal</span>
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

    <!-- MAIN CONTENT -->

    <main class="main-content">
        <div class="content">
            <section class="welcome">
                <div>
                    <span class="welcome-label">PATIENT DASHBOARD</span>
                    <h1>Welcome, RenManSal!</h1>
                    <p>
                        Manage your appointments and view your dental information.
                    </p>
                </div>
                <div class="welcome-icon">
                    <i class="fa-solid fa-tooth"></i>
                </div>
            </section>

            <!-- INFORMATION -->

            <div class="section-title">
                <h2>Your Information</h2>
                <p>View your dental and account information.</p>
            </div>

            <section class="dashboard-grid">

                <!-- PERSONAL INFORMATION -->
                
                <div class="dashboard-card" onclick="openModal('personalModal')">
                    <div class="card-top">
                        <div class="card-icon personal">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <i class="fa-solid fa-arrow-up-right-from-square card-arrow"></i>
                    </div>
                    <h3>Personal Information</h3>
                    <p>
                        View your personal information and patient details.
                    </p>
                    <div class="card-footer">
                        <span>View information</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </div>
                </div>

                <!-- TEETH CONDITION -->

                <div class="dashboard-card" onclick="openModal('teethModal')">
                    <div class="card-top">
                        <div class="card-icon teeth">
                            <i class="fa-solid fa-tooth"></i>
                        </div>
                        <i class="fa-solid fa-arrow-up-right-from-square card-arrow"></i>
                    </div>
                    <h3>Teeth Condition Status</h3>
                    <div class="status-good">
                        <span></span>
                        Good
                    </div>
                    <p>
                        View your current teeth condition.
                    </p>
                    <div class="card-footer">
                        <span>View condition</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </div>

                </div>

                <!-- BALANCE -->

                <div class="dashboard-card" onclick="openModal('balanceModal')">
                    <div class="card-top">
                        <div class="card-icon balance">
                            <i class="fa-solid fa-wallet"></i>
                        </div>
                        <i class="fa-solid fa-arrow-up-right-from-square card-arrow"></i>
                    </div>
                    <h3>Remaining Balance</h3>
                    <strong class="card-number">₱ 6,900.00</strong>
                    <p>
                        Outstanding balance.
                    </p>
                    <div class="card-footer">
                        <span>View balance</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </div>
                </div>

                <!-- SESSIONS -->

                <div class="dashboard-card" onclick="openModal('sessionsModal')">
                    <div class="card-top">
                        <div class="card-icon sessions">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </div>
                        <i class="fa-solid fa-arrow-up-right-from-square card-arrow"></i>
                    </div>
                    <h3>Remaining Sessions</h3>
                    <strong class="card-number">2 Sessions</strong>
                    <p>
                        Sessions left to complete.
                    </p>
                    <div class="card-footer">
                        <span>View history</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </div>
                </div>

                <!-- NEXT APPOINTMENT -->

                <div class="dashboard-card" onclick="openModal('appointmentModal')">
                    <div class="card-top">
                        <div class="card-icon appointment">
                            <i class="fa-regular fa-calendar-days"></i>
                        </div>
                        <span class="appointment-status">No appointment</span>
                    </div>
                    <h3>Next Appointment</h3>
                    <strong class="appointment-empty">
                        No upcoming appointment
                    </strong>
                    <p>
                        Schedule your next dental visit.
                    </p>
                    <div class="card-footer">
                        <span>Manage appointment</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- PERSONAL INFORMATION MODAL -->

    <div class="modal" id="personalModal">
        <div class="modal-box">
            <button class="close-button" onclick="closeModal('personalModal')">
                &times;
            </button>
            <div class="modal-icon personal">
                <i class="fa-solid fa-user"></i>
            </div>
            <h2>Personal Information</h2>
            <div class="information">
                <div>
                    <span>Patient ID</span>
                    <strong>P-0069</strong>
                </div>
                <div>
                    <span>Full Name</span>
                    <strong>RenManSal</strong>
                </div>
                <div>
                    <span>Contact Number</span>
                    <strong>09 999 999</strong>
                </div>
                <div>
                    <span>Email</span>
                    <strong>cherrygacha196@gmail.com</strong>
                </div>
            </div>
            <a href="profile.php" class="modal-button">
                View Full Profile
            </a>
        </div>
    </div>

    <!-- TEETH CONDITION MODAL -->

    <div class="modal" id="teethModal">
        <div class="modal-box">
            <button class="close-button" onclick="closeModal('teethModal')">
                &times;
            </button>
            <div class="modal-icon teeth">
                <i class="fa-solid fa-tooth"></i>
            </div>
            <h2>Teeth Condition</h2>
            <div class="information">
                <div>
                    <span>Current Status</span>
                    <strong class="good-text">Good</strong>
                </div>
                <div>
                    <span>Teeth with concerns</span>
                    <strong>0</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- APPOINTMENT MODAL -->

    <div class="modal" id="appointmentModal">
        <div class="modal-box">
            <button class="close-button" onclick="closeModal('appointmentModal')">
                &times;
            </button>
            <div class="modal-icon appointment">
                <i class="fa-regular fa-calendar-days"></i>
            </div>
            <h2>Next Appointment</h2>
            <div class="empty-message">
                <i class="fa-regular fa-calendar-xmark"></i>
                <p>No upcoming appointment.</p>
                <small>
                    You currently don't have a scheduled appointment.
                </small>
                <a href="appointments.php" class="modal-button">
                    <i class="fa-solid fa-calendar-plus"></i>
                    Book an Appointment
                </a>
            </div>
        </div>
    </div>

    <!-- BALANCE MODAL -->

    <div class="modal" id="balanceModal">
        <div class="modal-box">
            <button class="close-button" onclick="closeModal('balanceModal')">
                &times;
            </button>
            <div class="modal-icon balance">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <h2>Remaining Balance</h2>
            <div class="balance-display">
                ₱ 6,900.00
            </div>
            <p class="modal-note">
                Outstanding balance
            </p>
        </div>
    </div>

    <!-- SESSIONS MODAL -->

    <div class="modal" id="sessionsModal">
        <div class="modal-box">
            <button class="close-button" onclick="closeModal('sessionsModal')">
                &times;
            </button>
            <div class="modal-icon sessions">
                <i class="fa-solid fa-clipboard-list"></i>
            </div>
            <h2>Remaining Sessions</h2>
            <div class="balance-display">
                2 Sessions
            </div>
            <p class="modal-note">
                Sessions left to complete
            </p>
        </div>
    </div>
    <script src="user.js"></script>
</body>
</html>