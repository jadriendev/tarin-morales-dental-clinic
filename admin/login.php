<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

// Logout
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header("Location: login.php");
    exit();
}

// CSRF token (generate once per session)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validCsrf    = hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '');
    $inputAdminId = trim($_POST['username'] ?? '');
    $password     = $_POST['password'] ?? '';

    if (!$validCsrf || $inputAdminId === '' || !ctype_digit($inputAdminId) || $password === '') {
        header("Location: login.php?error=invalid_credentials");
        exit();
    }

    $stmt = $pdo->prepare("SELECT admin_id, user_id, first_name, last_name, password, status FROM tbl_admins WHERE admin_id = :admin_id LIMIT 1");
    $stmt->execute(['admin_id' => $inputAdminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || !password_verify($password, $admin['password'])) {
        header("Location: login.php?error=invalid_credentials");
        exit();
    }

    if ($admin['status'] !== 'active') {
        header("Location: login.php?error=account_inactive");
        exit();
    }

    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);

    $_SESSION['admin_id']  = $admin['admin_id'];
    $_SESSION['user_id']   = $admin['user_id'];
    $_SESSION['full_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
    $_SESSION['role']      = 'admin';

    header("Location: admindashboard.php");
    exit();
}

// Error message for the view
$errors = [
    'account_inactive'    => 'Your account is inactive. Please contact the system administrator.',
    'invalid_credentials' => 'Invalid Admin ID or password.',
    'unauthorized'        => 'Please sign in to access the admin portal.',
];
$error = isset($_GET['error']) ? ($errors[$_GET['error']] ?? 'An error occurred. Please try again.') : '';

$isLoggedInAdmin = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | Tarin-Morales Dental Clinic</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Bebas+Neue&family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Manrope:wght@200..800&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Quattrocento:wght@400;700&family=Roboto+Mono:ital,wght@0,100..700;1,100..700&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css" integrity="sha512-QeR2VH+lsBE5LSAe1Q5EnTBbe7XTBubt8dG93Y7gidSgdMCr8nVqKcfKAMyN96SV8KDbZVTDXChatu5G2KQGzg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>

<style>
    body { font-family: 'Manrope', sans-serif; }
</style>

</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#5FC0F0] via-white to-[#C24FE0] px-4">

<div class="w-full max-w-md bg-white/90 backdrop-blur rounded-2xl shadow-xl border border-[#5FC0F0]/40 p-8">

    <div class="flex flex-col items-center mb-6">
        <img src="image/tarin.svg"
        alt="Tarin Morales Dental Clinic"
        class="flex items-center justify-center w-[150px] h-[150px] rounded-full object-cover mb-3 mx-auto"
        style="image-rendering: -webkit-optimize-contrast; image-rendering: crisp-edges;">
        <h1 class="text-2xl font-bold">
            <span class="text-[#1B6FB0]">TARIN-MORALES</span>
        </h1>
        <p class="text-sm tracking-widest text-gray-500">DENTAL CLINIC</p>
    </div>

    <?php if ($isLoggedInAdmin): ?>

        <div class="text-center">
            <div class="mx-auto mb-4 w-14 h-14 rounded-full bg-green-100 flex items-center justify-center">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h2 class="text-lg font-semibold text-gray-800 mb-1">Welcome, Admin!</h2>
            <p class="text-gray-500 mb-4 break-all"><?php echo htmlspecialchars($_SESSION["full_name"] ?? 'Admin'); ?></p>

            <div class="space-y-2">
                <a href="admindashboard.php"
                   class="inline-block w-full py-2.5 rounded-lg text-white font-medium hover:opacity-90 transition text-center"
                   style="background: linear-gradient(to right, #2E9FE0, #9A2FC9);">
                   Go to Dashboard
                </a>
                <a href="login.php?logout=1"
                   class="inline-block w-full py-2.5 rounded-lg bg-gray-200 text-gray-700 font-medium hover:bg-gray-300 transition text-center">
                   Log Out
                </a>
            </div>
        </div>

    <?php else: ?>

        <h2 class="text-center font-semibold text-gray-800 mb-1">Welcome Back!</h2>
        <p class="text-center text-sm text-gray-500 mb-6">Please sign in to continue.</p>

        <?php if ($error): ?>
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-2">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET["status"]) && $_GET["status"] === "success"): ?>
            <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-600 text-sm px-4 py-2">
                Signed in successfully.
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Admin ID</label>
                <div class="relative flex items-center">
                    <i class="fa-solid fa-user absolute left-3.5 text-gray-400 text-lg pointer-events-none"></i>
                    <input type="text" name="username" required
                        class="w-full rounded-lg border border-gray-300 pl-10 pr-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#2E9FE0] focus:border-transparent transition"
                        placeholder="Enter Admin ID">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Password</label>
                <div class="relative flex items-center">
                <i class="fa-solid fa-lock absolute left-3.5 text-gray-400 text-lg pointer-events-none"></i>
                <input type="password" name="password" required
                        class="w-full rounded-lg border border-gray-300 pl-10 pr-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#2E9FE0] focus:border-transparent transition"
                        placeholder="Enter password">
                </div>
            </div>

            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2 text-gray-600">
                    <input type="checkbox" class="rounded border-gray-300 text-[#2E9FE0] focus:ring-[#2E9FE0]">
                    Remember me
                </label>
                <a href="#" class="text-[#9A2FC9] hover:underline">Forgot password?</a>
            </div>

            <button type="submit"
                    class="w-full py-2.5 rounded-lg text-white font-medium hover:opacity-90 transition shadow-md"
                    style="background: linear-gradient(to right, #2E9FE0, #9A2FC9);">
                Sign In
            </button>

            <p class="text-center text-xs text-gray-400 pt-2">
                Admin access only. Non-admin accounts will be denied.
            </p>
        </form>

    <?php endif; ?>

</div>

</body>
</html>