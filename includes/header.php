<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Календарь напоминаний событий</title>
    <link rel="stylesheet" href="./style/style.css">
</head>
<body>
    <header>
        <div class="container header-content">
            <div class="logo">Календарь событий</div>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?></div>
                <div><?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo $_SESSION['user_role']; ?>)</div>
                <a href="logout.php" class="btn btn-danger">Выйти</a>
            </div>
        </div>
    </header>

    <nav>
        <div class="container">
            <ul class="nav-tabs">
                <li><a href="dashboard.php">Календарь</a></li>
                <li><a href="events.php">События</a></li>
                <li><a href="reports.php">Отчёты</a></li>
                <?php if ($_SESSION['user_role'] == 'admin'): ?>
                <li><a href="users.php">Пользователи</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>