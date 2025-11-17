<?php
session_start();
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database -> getConnection();
    
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Валидация
    if (empty($username)) {
        $errors[] = "Имя пользователя обязательно";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Введите корректный email";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "Пароль должен содержать минимум 6 символов";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Пароли не совпадают";
    }
    
    // Проверка существующего пользователя
    $check_query = "SELECT id FROM users WHERE username = :username OR email = :email";
    $check_stmt = $db->prepare($check_query);
    $check_stmt -> bindParam(':username', $username);
    $check_stmt -> bindParam(':email', $email);
    $check_stmt -> execute();
    
    if ($check_stmt -> rowCount() > 0) {
        $errors[] = "Пользователь с таким именем или email уже существует";
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, 'user')";
        $stmt = $db -> prepare($query);
        $stmt -> bindParam(':username', $username);
        $stmt -> bindParam(':email', $email);
        $stmt -> bindParam(':password', $hashed_password);
        
        if ($stmt -> execute()) {
            $_SESSION['success'] = "Регистрация успешна! Теперь вы можете войти.";
            header("Location: login.php");
            exit();
        } else {
            $errors[] = "Ошибка при регистрации";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - Календарь событий</title>
    <link rel="stylesheet" href="./style/normalize.css">
    <link rel="stylesheet" href="./style/style.css">
</head>
<body>
    <div class="login-container">
        <h2 class="login-title">Регистрация</h2>
        
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Имя пользователя</label>
                <input type="text" id="username" name="username" class="form-control" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Подтверждение пароля</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Зарегистрироваться</button>
        </form>
        
        <div class="login-redirect">
            <p>Уже есть аккаунт? <a href="login.php">Войдите</a></p>
        </div>
    </div>
</body>
</html>