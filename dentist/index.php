<?php
session_start();
require_once 'config.php';

$error = '';

if (isset($_POST['signin'])) {

    $username = trim($_POST['dentist_username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {

        $error = "Please enter your username and password.";

    } else {

        $username = mysqli_real_escape_string($link, $username);

        $sql = "SELECT * FROM tbl_dentists
                WHERE username = '$username'
                AND status = 'active'
                LIMIT 1";

        $result = mysqli_query($link, $sql);

        if ($result && mysqli_num_rows($result) === 1) {

            $dentist = mysqli_fetch_assoc($result);

            // Verify hashed password
            if (password_verify($password, $dentist['password'])) {

                $_SESSION['dentist_id'] = $dentist['dentist_id'];
                $_SESSION['dentist_username'] = $dentist['username'];
                $_SESSION['dentist_name'] =
                    $dentist['first_name'] . ' ' . $dentist['last_name'];

                header("Location: dentist-dashboard.php");
                exit();

            } else {

                $error = "Incorrect username or password.";

            }

        } else {

            $error = "Incorrect username or password.";

        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Logo -->
     <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon">
    <!-- Fonts Sora -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Bebas+Neue&family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Manrope:wght@200..800&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Quattrocento:wght@400;700&family=Roboto+Mono:ital,wght@0,100..700;1,100..700&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <!-- Fonts Manrope -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Bebas+Neue&family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Manrope:wght@200..800&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Quattrocento:wght@400;700&family=Roboto+Mono:ital,wght@0,100..700;1,100..700&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <!-- Material Icon -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.0/css/all.min.css" integrity="sha512-ApSLB1Pd3/bZN8fWB/RG9YhN/7bd9Hkf3AGaE2mPfebjrxagjuBtx2GcgdqIlJkUzwylBo61r9Xa9NmgBI0swA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Tailwind CSS -->
     <script src="https://cdn.tailwindcss.com"></script>
    <title>Dentist Login | Tarin Morales Dental Clinic</title>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen px-4 py-6 sm:px-6" style="font-family: 'Manrope';">
    <form action="" method="POST" class="bg-white rounded-lg shadow-lg py-6 px-4 w-full max-w-md sm:py-8 sm:px-5">
        <!-- Logo/Header -->
        <div class="">
            <div class="flex items-center justify-center">
                <img class="w-16 sm:w-20 rounded-full object-contain shadow-lg" src="../images/logo.jpg" alt="Tarin Morales Dental Clinic Logo">
            </div>

            <h1 class="text-lg sm:text-xl mt-2 text-center font-bold bg-gradient-to-r from-blue-600 to-indigo-700 bg-clip-text text-transparent">
                <span class="block">TARIN-MORALES</span>
                <span class="block text-[.80rem] text-gray-400 font-semibold">DENTAL CLINIC</span>
            </h1>

            <h3 class="text-[.85rem] sm:text-[.90rem] mt-2 text-center">
                <span class="text-gray-700 font-bold block">Welcome Back!</span>
                <span class="block text-[.80rem] text-gray-500">Please sign in to continue.</span>
            </h3>
        </div>

        <!-- Inputs -->
        <div class="mt-2">
            <div class="">
                <label for="dentist_username" class="text-[.80rem] text-gray-600 font-semibold">Dentist Username</label>
                <div class="relative mt-1">
                    <i class="text-[1.1rem] text-gray-500 top-3 left-[.40rem] absolute fa fa-user"></i>
                    <input class="border py-[.60rem] pl-9 w-full rounded-md text-[.90rem] focus:outline-none focus:ring-2 focus:ring-blue-600" type="text" name="dentist_username" id="dentist_username" placeholder="Enter your username" required>
                </div>
            </div>

            <div class="mt-3">
                <label for="dentist_password" class="text-[.80rem] text-gray-600 font-semibold">Password</label>
                <div class="relative mt-1">
                    <span class="text-[1.5rem] text-gray-500 top-[.60rem] left-[.30rem] absolute material-symbols-outlined">lock</span>
                    <input class="border py-[.60rem] pl-9 w-full rounded-md text-[.90rem] focus:outline-none focus:ring-2 focus:ring-blue-600" type="password" name="password" id="dentist_password" placeholder="Enter your password" required>
                    <button type="button" id="togglePassword" class="text-[1.4rem] top-[.70rem] right-3 text-gray-500 absolute material-symbols-outlined">visibility</button>
                </div>
            </div>

            <?php if (!empty($error)): ?>

    <div class="mt-4 bg-red-50 border border-red-200 text-red-600 text-[.80rem] font-semibold rounded-md px-3 py-2 text-center">
        <?= htmlspecialchars($error) ?>
    </div>

<?php endif; ?>
        </div>

        <!-- Login Button -->
        <button type="submit" name="signin" class="relative overflow-hidden mt-5 bg-gradient-to-r from-blue-600 to-violet-600 w-full font-semibold cursor-pointer rounded-md text-white text-[.90rem] mt-4 py-2 before:absolute before:inset-0 before:bg-gradient-to-r before:from-violet-600 before:to-blue-600 before:opacity-0 hover:before:opacity-100 before:transition-opacity before:duration-500 before:ease-in-out">
            <span class="relative z-10">Sign in</span>
        </button>
    
        <!-- Note -->
        <p class="text-[.70rem] text-gray-500 font-semibold mt-7 text-center">Dentist access only. Non-admin accounts will be denied.</p>
    </form>

<script src="../scripts/mata.js"></script>
</body>
</html>