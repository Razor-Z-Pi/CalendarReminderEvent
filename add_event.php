<?php
require_once 'config/db.php';
require_once 'includes/auth_check.php';
require_once 'includes/header.php';

$database = new Database();
$db = $database -> getConnection();

// Получение категорий
$categories_query = "SELECT * FROM categories";
$categories_stmt = $db->query($categories_query);
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date'];
    $event_type = $_POST['event_type'];
    $category_id = $_POST['category_id'];
    $responsible_users = trim($_POST['responsible_users']);
    
    $errors = [];
    
    // Валидация
    if (empty($title)) {
        $errors[] = "Название события обязательно";
    }
    
    if (empty($event_date)) {
        $errors[] = "Дата события обязательна";
    }
    
    if (empty($event_type)) {
        $errors[] = "Вид мероприятия обязателен";
    }
    
    if (empty($category_id)) {
        $errors[] = "Категория обязательна";
    }
    
    if (empty($errors)) {
        $query = "INSERT INTO events (title, description, event_date, event_type, category_id, author_id, responsible_users) 
                  VALUES (:title, :description, :event_date, :event_type, :category_id, :author_id, :responsible_users)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':event_date', $event_date);
        $stmt->bindParam(':event_type', $event_type);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->bindParam(':author_id', $_SESSION['user_id']);
        $stmt->bindParam(':responsible_users', $responsible_users);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Событие успешно добавлено";
            header("Location: events.php");
            exit();
        } else {
            $errors[] = "Ошибка при добавлении события";
        }
    }
}
?>

<main class="container">
    <div class="section active">
        <div class="section-header">
            <h2 class="section-title">Добавить новое событие</h2>
            <a href="events.php" class="btn">Назад к списку</a>
        </div>

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label for="title">Название события *</label>
                    <input type="text" id="title" name="title" class="form-control" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="event_date">Дата *</label>
                    <input type="date" id="event_date" name="event_date" class="form-control" value="<?php echo isset($_POST['event_date']) ? $_POST['event_date'] : date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label for="category_id">Категория *</label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <option value="">Выберите категорию</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo isset($_POST['category_id']) && $_POST['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo $category['name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="event_type">Вид мероприятия *</label>
                    <select id="event_type" name="event_type" class="form-control" required>
                        <option value="">Выберите вид</option>
                        <option value="Встреча" <?php echo isset($_POST['event_type']) && $_POST['event_type'] == 'Встреча' ? 'selected' : ''; ?>>Встреча</option>
                        <option value="Конференция" <?php echo isset($_POST['event_type']) && $_POST['event_type'] == 'Конференция' ? 'selected' : ''; ?>>Конференция</option>
                        <option value="Праздник" <?php echo isset($_POST['event_type']) && $_POST['event_type'] == 'Праздник' ? 'selected' : ''; ?>>Праздник</option>
                        <option value="Задача" <?php echo isset($_POST['event_type']) && $_POST['event_type'] == 'Задача' ? 'selected' : ''; ?>>Задача</option>
                        <option value="Напоминание" <?php echo isset($_POST['event_type']) && $_POST['event_type'] == 'Напоминание' ? 'selected' : ''; ?>>Напоминание</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="responsible_users">Ответственные</label>
                <input type="text" id="responsible_users" name="responsible_users" class="form-control" 
                       value="<?php echo isset($_POST['responsible_users']) ? htmlspecialchars($_POST['responsible_users']) : ''; ?>"
                       placeholder="Введите имена через запятую">
            </div>
            
            <div class="form-group">
                <label for="description">Описание</label>
                <textarea id="description" name="description" class="form-control" rows="4"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Добавить событие</button>
            <a href="events.php" class="btn">Отмена</a>
        </form>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>