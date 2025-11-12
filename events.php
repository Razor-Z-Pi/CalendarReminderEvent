<?php
require_once 'config/db.php';
require_once 'includes/auth_check.php';
require_once 'includes/header.php';

$database = new Database();
$db = $database->getConnection();

// Обработка удаления события
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // Проверяем, принадлежит ли событие пользователю или пользователь - администратор
    $check_query = "SELECT * FROM events WHERE id = :id AND (author_id = :user_id OR :is_admin = 1)";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $delete_id);
    $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $is_admin = ($_SESSION['user_role'] == 'admin') ? 1 : 0;
    $check_stmt->bindParam(':is_admin', $is_admin);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $delete_query = "DELETE FROM events WHERE id = :id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $delete_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['success'] = "Событие успешно удалено";
        } else {
            $_SESSION['error'] = "Ошибка при удалении события";
        }
    } else {
        $_SESSION['error'] = "У вас нет прав для удаления этого события";
    }
    
    header("Location: events.php");
    exit();
}

// Получение параметров фильтрации и поиска
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$author_filter = isset($_GET['author']) ? $_GET['author'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'event_date';

// Базовый запрос
$query = "SELECT e.*, c.name as category_name, c.color as category_color, u.username as author_name 
          FROM events e 
          LEFT JOIN categories c ON e.category_id = c.id 
          LEFT JOIN users u ON e.author_id = u.id 
          WHERE 1=1";

$params = [];

// Применяем фильтры
if (!empty($search)) {
    $query .= " AND (e.title LIKE :search OR e.description LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($category_filter)) {
    $query .= " AND e.category_id = :category_id";
    $params[':category_id'] = $category_filter;
}

if (!empty($author_filter)) {
    $query .= " AND e.author_id = :author_id";
    $params[':author_id'] = $author_filter;
}

// Если пользователь не администратор, показываем только его события
if ($_SESSION['user_role'] != 'admin') {
    $query .= " AND (e.author_id = :user_id OR e.responsible_users LIKE :username)";
    $params[':user_id'] = $_SESSION['user_id'];
    $params[':username'] = '%' . $_SESSION['username'] . '%';
}

// Сортировка
$sort_options = [
    'event_date' => 'e.event_date ASC',
    'event_date_desc' => 'e.event_date DESC',
    'title' => 'e.title ASC',
    'category' => 'c.name ASC',
    'author' => 'u.username ASC'
];
$query .= " ORDER BY " . ($sort_options[$sort_by] ?? 'e.event_date ASC');

// Выполнение запроса
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение категорий для фильтра
$categories_query = "SELECT * FROM categories";
$categories_stmt = $db->query($categories_query);
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение авторов для фильтра
$authors_query = "SELECT id, username FROM users";
$authors_stmt = $db->query($authors_query);
$authors = $authors_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container">
    <div class="section active">
        <div class="section-header">
            <h2 class="section-title">Управление событиями</h2>
            <a href="add_event.php" class="btn btn-primary">Добавить событие</a>
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
                <input type="text" name="search" class="form-control" placeholder="Поиск по названию..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Найти</button>
                <a href="events.php" class="btn">Сбросить</a>
            </div>
            
            <div class="filter-container">
                <select name="category" class="form-control">
                    <option value="">Все категории</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                        <?php echo $category['name']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <?php if ($_SESSION['user_role'] == 'admin'): ?>
                <select name="author" class="form-control">
                    <option value="">Все авторы</option>
                    <?php foreach ($authors as $author): ?>
                    <option value="<?php echo $author['id']; ?>" <?php echo $author_filter == $author['id'] ? 'selected' : ''; ?>>
                        <?php echo $author['username']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                
                <select name="sort" class="form-control">
                    <option value="event_date" <?php echo $sort_by == 'event_date' ? 'selected' : ''; ?>>Дата (по возрастанию)</option>
                    <option value="event_date_desc" <?php echo $sort_by == 'event_date_desc' ? 'selected' : ''; ?>>Дата (по убыванию)</option>
                    <option value="title" <?php echo $sort_by == 'title' ? 'selected' : ''; ?>>Название</option>
                    <option value="category" <?php echo $sort_by == 'category' ? 'selected' : ''; ?>>Категория</option>
                    <option value="author" <?php echo $sort_by == 'author' ? 'selected' : ''; ?>>Автор</option>
                </select>
                
                <button type="submit" class="btn btn-primary">Применить фильтры</button>
            </div>
        </form>

        <!-- Список событий -->
        <div class="events-list">
            <?php if (empty($events)): ?>
                <div class="alert">События не найдены.</div>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                <div class="event-card" style="border-left-color: <?php echo $event['category_color']; ?>">
                    <div class="event-header">
                        <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                        <div>
                            <span class="event-category" style="background-color: <?php echo $event['category_color']; ?>20; color: <?php echo $event['category_color']; ?>">
                                <?php echo $event['category_name']; ?>
                            </span>
                            <?php if ($_SESSION['user_role'] == 'admin' || $event['author_id'] == $_SESSION['user_id']): ?>
                            <div class="event-actions">
                                <a href="edit_event.php?id=<?php echo $event['id']; ?>" class="btn btn-primary">Редактировать</a>
                                <a href="events.php?delete_id=<?php echo $event['id']; ?>" class="btn btn-danger" 
                                   onclick="return confirm('Вы уверены, что хотите удалить это событие?')">Удалить</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="event-details">
                        <div><strong>Дата:</strong> <?php echo date('d.m.Y', strtotime($event['event_date'])); ?></div>
                        <div><strong>Вид:</strong> <?php echo htmlspecialchars($event['event_type']); ?></div>
                        <div><strong>Автор:</strong> <?php echo htmlspecialchars($event['author_name']); ?></div>
                        <div><strong>Ответственные:</strong> <?php echo htmlspecialchars($event['responsible_users']); ?></div>
                    </div>
                    <?php if (!empty($event['description'])): ?>
                    <div class="event-description"><?php echo nl2br(htmlspecialchars($event['description'])); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>