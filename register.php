<?php
require_once "db.php";
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, address, age, username, password)
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['first_name'], $_POST['last_name'], $_POST['address'],
                        $_POST['age'], $_POST['username'], $hash]);
        header("Location: login.php");
        exit;
    } catch (PDOException $e) {
        $error = "Username already taken.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Mendez Cafe</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
    <div class="auth-box">
        <h1>Mendez Cafe</h1>
        <p class="subtitle">Create an account</p>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="post">
            <input name="first_name" placeholder="First name" required>
            <input name="last_name" placeholder="Last name" required>
            <input name="address" placeholder="Address">
            <input name="age" type="number" placeholder="Age">
            <input name="username" placeholder="Username" required>
            <input name="password" type="password" placeholder="Password" required>
            <button type="submit">Register</button>
        </form>
        <p class="switch">Already have an account? <a href="login.php">Login</a></p>
    </div>
</body>
</html>