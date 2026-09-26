<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header("Location: login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validCsrf = hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token'] ?? ''
    );

    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$validCsrf || $login === '' || $password === '') {
        header("Location: login.php?error=invalid_credentials");
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT user_id, email, username, password, role, status
        FROM tbl_users
        WHERE email = :login
        OR username = :login
        LIMIT 1
    ");

    $stmt->execute([
        'login' => $login
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        header("Location: login.php?error=invalid_credentials");
        exit();
    }

    if ($user['role'] !== 'patient') {
        header("Location: login.php?error=invalid_credentials");
        exit();
    }

    if ($user['status'] !== 'active') {
        header("Location: login.php?error=account_inactive");
        exit();
    }

    session_regenerate_id(true);

    unset($_SESSION['csrf_token']);

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    header("Location: user-dashboard.php");
    exit();
}

$errors = [
    'account_inactive' => 'Your account is inactive. Please contact the system administrator.',
    'invalid_credentials' => 'Invalid email/username or password.',
    'unauthorized' => 'Please sign in to access the user portal.'
];

$error = isset($_GET['error'])
    ? ($errors[$_GET['error']] ?? 'An error occurred. Please try again.')
    : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login | Tarin-Morales Dental Clinic</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css"
          integrity="sha512-QeR2VH+lsBE5LSAe1Q5EnTBbe7XTBubt8dG93Y7gidSgdMCr8nVqKcfKAMyN96SV8KDbZVTDXChatu5G2KQGzg=="
          crossorigin="anonymous"
          referrerpolicy="no-referrer">
    <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Manrope', sans-serif;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-white px-4">
<div class="w-full max-w-md bg-white/90 backdrop-blur rounded-2xl shadow-xl border border-[#5FC0F0]/40 p-8">
    <div class="flex flex-col items-center mb-6">
        <img src="../images/logo.jpg"
             alt="Tarin Morales Dental Clinic"
             class="w-[150px] h-[150px] rounded-full object-cover mx-auto">
        <h1 class="text-2xl font-bold">
            <span class="text-[#1B6FB0]">TARIN-MORALES</span>
        </h1>
        <p class="text-sm tracking-widest text-gray-500">
            DENTAL CLINIC
        </p>
    </div>
    <h2 class="text-center font-semibold text-gray-800 mb-1">
        Welcome Back!
    </h2>
    <p class="text-center text-sm text-gray-500 mb-6">
        Please sign in to continue.
    </p>
    <?php if ($error): ?>
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-2">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    <form method="POST" action="login.php" class="space-y-4">
        <input type="hidden"
               name="csrf_token"
               value="<?php echo htmlspecialchars($csrfToken); ?>">
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">
                Email or Username
            </label>
            <div class="relative flex items-center">
                <i class="fa-solid fa-user absolute left-3.5 text-gray-400 text-lg pointer-events-none"></i>
                <input type="text"
                       name="login"
                       required
                       autocomplete="username"
                       class="w-full rounded-lg border border-gray-300 pl-10 pr-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#2E9FE0] focus:border-transparent transition"
                       placeholder="Enter email or username">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">
                Password
            </label>
            <div class="relative flex items-center">
                <i class="fa-solid fa-lock absolute left-3.5 text-gray-400 text-lg pointer-events-none"></i>
                <input type="password"
                       name="password"
                       id="password"
                       required
                       autocomplete="current-password"
                       class="w-full rounded-lg border border-gray-300 pl-10 pr-11 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#2E9FE0] focus:border-transparent transition"
                       placeholder="Enter password">
                <button type="button"
                        onclick="togglePassword()"
                        class="absolute right-3.5 text-gray-400 hover:text-[#2E9FE0] transition"
                        aria-label="Show password">
                    <i id="passwordIcon" class="fa-solid fa-eye"></i>
                </button>
            </div>
        </div>
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 text-gray-600">
                <input type="checkbox"
                       class="rounded border-gray-300 text-[#2E9FE0] focus:ring-[#2E9FE0]">
                Remember me
            </label>
            <a href="#" class="text-[#9A2FC9] hover:underline">
                Forgot password?
            </a>
        </div>
        <button type="submit"
                class="w-full py-2.5 rounded-lg text-white font-medium hover:opacity-90 transition shadow-md"
                style="background: linear-gradient(to right, #2E9FE0, #9A2FC9);">
            Sign In
        </button>

    </form>
</div>

<script>
function togglePassword() {
    const password = document.getElementById('password');
    const icon = document.getElementById('passwordIcon');

    if (password.type === 'password') {
        password.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        password.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

</body>
</html>