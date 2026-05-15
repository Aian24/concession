<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Concession System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/concessiontab.png">
</head>
<body class="bg-slate-900 text-white font-outfit min-h-screen flex items-center justify-center relative overflow-hidden">
    <!-- Animated background glowing orbs -->
    <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-purple-600 rounded-full mix-blend-multiply filter blur-3xl opacity-50 animate-blob"></div>
    <div class="absolute top-[-10%] right-[-10%] w-96 h-96 bg-blue-600 rounded-full mix-blend-multiply filter blur-3xl opacity-50 animate-blob animation-delay-2000"></div>
    <div class="absolute bottom-[-20%] left-[20%] w-96 h-96 bg-pink-600 rounded-full mix-blend-multiply filter blur-3xl opacity-50 animate-blob animation-delay-4000"></div>

    <div class="glass-panel p-8 md:p-12 w-full max-w-md relative z-10 mx-4">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-pink-400 to-purple-600 mb-2">Create Account</h1>
            <p class="text-gray-400">Join the concession system</p>
        </div>

        <form action="index.php" method="POST" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Username</label>
                <input type="text" name="username" required class="input-modern w-full" placeholder="Choose a username">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                <input type="password" name="password" required class="input-modern w-full" placeholder="Create a strong password">
            </div>
            
            <button type="submit" name="register" class="btn-primary w-full py-3 rounded-xl font-semibold text-lg hover:-translate-y-1 transition-transform shadow-lg shadow-pink-500/30">
                Register Now
            </button>
        </form>

        <p class="mt-6 text-center text-gray-400">
            Already have an account? <a href="index.php" class="text-pink-400 hover:text-pink-300 transition-colors font-medium relative after:absolute after:bottom-0 after:left-0 after:h-[2px] after:w-0 after:bg-pink-400 after:transition-all hover:after:w-full">Sign In</a>
        </p>
    </div>
</body>
</html>
