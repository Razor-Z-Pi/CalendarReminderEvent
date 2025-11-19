<?php
require_once 'config/db.php';
require_once 'includes/auth_check.php';
require_once 'includes/header.php';

// Проверяем, является ли пользователь администратором
if ($_SESSION['user_role'] != 'admin') {
    header("Location: dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Обработка изменения роли пользователя
if (isset($_POST['change_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['role'];
    
    // Проверяем, что не меняем роль самого себя
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error'] = "Вы не можете изменить свою собственную роль";
    } else {
        $query = "UPDATE users SET role = :role WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':role', $new_role);
        $stmt->bindParam(':id', $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Роль пользователя успешно изменена";
        } else {
            $_SESSION['error'] = "Ошибка при изменении роли пользователя";
        }
    }
    header("Location: users.php");
    exit();
}

// Обработка удаления пользователя
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // Проверяем, что не удаляем самого себя
    if ($delete_id == $_SESSION['user_id']) {
        $_SESSION['error'] = "Вы не можете удалить свой собственный аккаунт";
    } else {
        $check_query = "SELECT * FROM users WHERE id = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $delete_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $delete_query = "DELETE FROM users WHERE id = :id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':id', $delete_id);
            
            if ($delete_stmt->execute()) {
                $_SESSION['success'] = "Пользователь успешно удален";
            } else {
                $_SESSION['error'] = "Ошибка при удалении пользователя";
            }
        } else {
            $_SESSION['error'] = "Пользователь не найден";
        }
    }
    header("Location: users.php");
    exit();
}

// Получение параметров поиска и фильтрации
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';

// Базовый запрос для получения пользователей
$query = "SELECT id, username, email, role, created_at FROM users WHERE 1=1";
$params = [];

// Применяем фильтры
if (!empty($search)) {
    $query .= " AND (username LIKE :search OR email LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($role_filter)) {
    $query .= " AND role = :role";
    $params[':role'] = $role_filter;
}

// Сортировка
$sort_options = [
    'username' => 'username ASC',
    'username_desc' => 'username DESC',
    'email' => 'email ASC',
    'role' => 'role ASC',
    'created_at' => 'created_at DESC',
    'created_at_asc' => 'created_at ASC'
];
$query .= " ORDER BY " . ($sort_options[$sort_by] ?? 'created_at DESC');

// Выполнение запроса
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Статистика пользователей
$stats_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as user_count,
    MIN(created_at) as first_registration,
    MAX(created_at) as last_registration
    FROM users";
$stats_stmt = $db->query($stats_query);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<main class="container">
    <div class="section active">
        <div class="section-header">
            <h2 class="section-title">Управление пользователями</h2>
            <div class="user-stats" style="display: flex; gap: 1rem; align-items: center;">
                <div style="background: var(--gradient-primary); color: white; padding: 0.5rem 1rem; border-radius: var(--border-radius); font-size: 0.9rem;">
                    Всего: <strong><?php echo $stats['total_users']; ?></strong>
                </div>
                <div style="background: var(--gradient-secondary); color: white; padding: 0.5rem 1rem; border-radius: var(--border-radius); font-size: 0.9rem;">
                    Админы: <strong><?php echo $stats['admin_count']; ?></strong>
                </div>
                <div style="background: var(--gradient-success); color: white; padding: 0.5rem 1rem; border-radius: var(--border-radius); font-size: 0.9rem;">
                    Пользователи: <strong><?php echo $stats['user_count']; ?></strong>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- Фильтры и поиск -->
        <form method="GET" action="">
            <div class="search-container">
                <input type="text" name="search" class="form-control" placeholder="Поиск по имени или email..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Найти</button>
                <a href="users.php" class="btn">Сбросить</a>
            </div>
            
            <div class="filter-container">
                <select name="role" class="form-control">
                    <option value="">Все роли</option>
                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Администраторы</option>
                    <option value="user" <?php echo $role_filter == 'user' ? 'selected' : ''; ?>>Пользователи</option>
                </select>
                
                <select name="sort" class="form-control">
                    <option value="username" <?php echo $sort_by == 'username' ? 'selected' : ''; ?>>Имя (А-Я)</option>
                    <option value="username_desc" <?php echo $sort_by == 'username_desc' ? 'selected' : ''; ?>>Имя (Я-А)</option>
                    <option value="email" <?php echo $sort_by == 'email' ? 'selected' : ''; ?>>Email</option>
                    <option value="role" <?php echo $sort_by == 'role' ? 'selected' : ''; ?>>Роль</option>
                    <option value="created_at" <?php echo $sort_by == 'created_at' ? 'selected' : ''; ?>>Дата регистрации (новые)</option>
                    <option value="created_at_asc" <?php echo $sort_by == 'created_at_asc' ? 'selected' : ''; ?>>Дата регистрации (старые)</option>
                </select>
                
                <button type="submit" class="btn btn-primary">Применить фильтры</button>
            </div>
        </form>

        <!-- Список пользователей -->
        <div class="users-list">
            <?php if (empty($users)): ?>
                <div class="alert">Пользователи не найдены.</div>
            <?php else: ?>
                <div class="users-grid" style="display: grid; gap: 1.5rem;">
                    <?php foreach ($users as $user): ?>
                    <div class="user-card" style="
                        background: #2d3748;
                        border-radius: var(--border-radius);
                        padding: 1.5rem;
                        box-shadow: var(--shadow-soft);
                        border-left: 4px solid <?php echo $user['role'] == 'admin' ? 'var(--primary-color)' : 'var(--success-color)'; ?>;
                        transition: var(--transition);
                        animation: slideInUp 0.5s ease;
                    ">
                        <div class="user-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                            <div class="user-info-main" style="display: flex; align-items: center; gap: 1rem;">
                                <div class="user-avatar-large" style="
                                    width: 60px;
                                    height: 60px;
                                    border-radius: 50%;
                                    background: <?php echo $user['role'] == 'admin' ? 'var(--gradient-primary)' : 'var(--gradient-success)'; ?>;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    font-weight: 700;
                                    color: white;
                                    font-size: 1.2rem;
                                    box-shadow: var(--shadow-soft);
                                ">
                                    <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                                </div>
                                <div>
                                    <div class="user-name" style="font-weight: 700; font-size: 1.25rem; color: var(--dark-color);">
                                        <?php echo htmlspecialchars($user['username']); ?>
                                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                            <span style="color: var(--primary-color); font-size: 0.8rem; margin-left: 0.5rem;">(Вы)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-email" style="color: var(--text-color); font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="user-role-badge" style="
                                padding: 0.5rem 1rem;
                                border-radius: 20px;
                                font-size: 0.75rem;
                                font-weight: 600;
                                text-transform: uppercase;
                                letter-spacing: 0.5px;
                                background: <?php echo $user['role'] == 'admin' ? 'rgba(102, 126, 234, 0.1)' : 'rgba(79, 172, 254, 0.1)'; ?>;
                                color: <?php echo $user['role'] == 'admin' ? 'var(--primary-color)' : 'var(--success-color)'; ?>;
                                border: 1px solid <?php echo $user['role'] == 'admin' ? 'var(--primary-color)' : 'var(--success-color)'; ?>;
                            ">
                                <?php echo $user['role'] == 'admin' ? 'Администратор' : 'Пользователь'; ?>
                            </div>
                        </div>
                        
                        <div class="user-details" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; font-size: 0.9rem; color: var(--text-color);">
                            <div>
                                <strong>ID:</strong> #<?php echo $user['id']; ?>
                            </div>
                            <div>
                                <strong>Зарегистрирован:</strong> 
                                <?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?>
                            </div>
                            <div>
                                <strong>Статус:</strong> 
                                <span style="color: <?php echo $user['role'] == 'admin' ? 'var(--primary-color)' : 'var(--success-color)'; ?>;">
                                    <?php echo $user['role'] == 'admin' ? 'Администратор' : 'Пользователь'; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Форма изменения роли -->
                        <form method="POST" action="" class="role-form" style="
                            display: flex;
                            gap: 1rem;
                            align-items: center;
                            padding-top: 1rem;
                            border-top: 1px solid var(--border-color);
                        ">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <div style="flex: 1;">
                                <select name="role" class="form-control" style="padding: 0.5rem;" <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                    <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>Пользователь</option>
                                    <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Администратор</option>
                                </select>
                            </div>
                            <div class="user-actions" style="display: flex; gap: 0.5rem;">
                                <button type="submit" name="change_role" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.9rem;" 
                                    <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                    Изменить роль
                                </button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <a href="users.php?delete_id=<?php echo $user['id']; ?>" class="btn btn-danger" style="padding: 0.5rem 1rem; font-size: 0.9rem;"
                                   onclick="return confirm('Вы уверены, что хотите удалить пользователя <?php echo htmlspecialchars($user['username']); ?>?')">
                                    Удалить
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Статистика регистраций -->
        <div class="section" style="margin-top: 2rem;">
            <h3 class="section-title" style="font-size: 1.5rem;">Статистика регистраций</h3>
            <div class="registration-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-top: 1rem;">
                <div class="stat-card" style="
                    background: #2d3748;
                    padding: 1.5rem;
                    border-radius: var(--border-radius);
                    box-shadow: var(--shadow-soft);
                    text-align: center;
                    border-top: 4px solid var(--primary-color);
                ">
                    <div class="stat-number" style="font-size: 2rem; font-weight: 700; color: var(--primary-color);">
                        <?php echo $stats['total_users']; ?>
                    </div>
                    <div class="stat-label" style="color: var(--text-color); font-size: 0.9rem;">
                        Всего пользователей
                    </div>
                </div>
                <div class="stat-card" style="
                    background: #2d3748;
                    padding: 1.5rem;
                    border-radius: var(--border-radius);
                    box-shadow: var(--shadow-soft);
                    text-align: center;
                    border-top: 4px solid var(--secondary-color);
                ">
                    <div class="stat-number" style="font-size: 2rem; font-weight: 700; color: var(--secondary-color);">
                        <?php echo $stats['admin_count']; ?>
                    </div>
                    <div class="stat-label" style="color: var(--text-color); font-size: 0.9rem;">
                        Администраторов
                    </div>
                </div>
                <div class="stat-card" style="
                    background: #2d3748;
                    padding: 1.5rem;
                    border-radius: var(--border-radius);
                    box-shadow: var(--shadow-soft);
                    text-align: center;
                    border-top: 4px solid var(--success-color);
                ">
                    <div class="stat-number" style="font-size: 2rem; font-weight: 700; color: var(--success-color);">
                        <?php echo $stats['user_count']; ?>
                    </div>
                    <div class="stat-label" style="color: var(--text-color); font-size: 0.9rem;">
                        Обычных пользователей
                    </div>
                </div>
                <div class="stat-card" style="
                    background: #2d3748;
                    padding: 1.5rem;
                    border-radius: var(--border-radius);
                    box-shadow: var(--shadow-soft);
                    text-align: center;
                    border-top: 4px solid var(--accent-color);
                ">
                    <div class="stat-number" style="font-size: 1.5rem; font-weight: 700; color: var(--accent-color);">
                        <?php echo date('d.m.Y', strtotime($stats['first_registration'])); ?>
                    </div>
                    <div class="stat-label" style="color: var(--text-color); font-size: 0.9rem;">
                        Первая регистрация
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require_once 'includes/footer.php'; ?>