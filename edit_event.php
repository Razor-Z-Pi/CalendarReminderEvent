<?php
require_once 'config/db.php';
require_once 'includes/auth_check.php';
require_once 'includes/header.php';

$database = new Database();
$db = $database->getConnection();

// Получение ID события для редактирования
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID события не указан";
    header("Location: events.php");
    exit();
}

$event_id = $_GET['id'];

// Проверяем, принадлежит ли событие пользователю или пользователь - администратор
$check_query = "SELECT e.*, c.name as category_name, c.color as category_color 
                FROM events e 
                LEFT JOIN categories c ON e.category_id = c.id 
                WHERE e.id = :id AND (e.author_id = :user_id OR :is_admin = 1)";
$check_stmt = $db->prepare($check_query);
$check_stmt->bindParam(':id', $event_id);
$check_stmt->bindParam(':user_id', $_SESSION['user_id']);
$is_admin = ($_SESSION['user_role'] == 'admin') ? 1 : 0;
$check_stmt->bindParam(':is_admin', $is_admin);
$check_stmt->execute();

if ($check_stmt->rowCount() == 0) {
    $_SESSION['error'] = "Событие не найдено или у вас нет прав для его редактирования";
    header("Location: events.php");
    exit();
}

$event = $check_stmt->fetch(PDO::FETCH_ASSOC);

// Получение категорий для выпадающего списка
$categories_query = "SELECT * FROM categories";
$categories_stmt = $db->query($categories_query);
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_event'])) {
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
        $update_query = "UPDATE events 
                        SET title = :title, 
                            description = :description, 
                            event_date = :event_date, 
                            event_type = :event_type, 
                            category_id = :category_id, 
                            responsible_users = :responsible_users 
                        WHERE id = :id AND (author_id = :user_id OR :is_admin = 1)";
        
        $stmt = $db->prepare($update_query);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':event_date', $event_date);
        $stmt->bindParam(':event_type', $event_type);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->bindParam(':responsible_users', $responsible_users);
        $stmt->bindParam(':id', $event_id);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':is_admin', $is_admin);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Событие успешно обновлено";
            header("Location: events.php");
            exit();
        } else {
            $errors[] = "Ошибка при обновлении события";
        }
    }
}
?>

<main class="container">
    <div class="section active">
        <div class="section-header">
            <h2 class="section-title">Редактирование события</h2>
            <a href="events.php" class="btn btn-secondary">← Назад к списку</a>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($errors) && !empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Информация о событии -->
        <div class="section" style="background: linear-gradient(135deg, <?php echo $event['category_color']; ?>20, transparent); border-left: 4px solid <?php echo $event['category_color']; ?>;">
            <h3 style="margin-bottom: 1rem; color: <?php echo $event['category_color']; ?>;">Текущая информация о событии</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <strong>Текущее название:</strong><br>
                    <?php echo htmlspecialchars($event['title']); ?>
                </div>
                <div>
                    <strong>Текущая дата:</strong><br>
                    <?php echo date('d.m.Y', strtotime($event['event_date'])); ?>
                </div>
                <div>
                    <strong>Текущая категория:</strong><br>
                    <span style="color: <?php echo $event['category_color']; ?>;"><?php echo $event['category_name']; ?></span>
                </div>
                <div>
                    <strong>Тип мероприятия:</strong><br>
                    <?php echo htmlspecialchars($event['event_type']); ?>
                </div>
            </div>
        </div>

        <!-- Форма редактирования -->
        <div class="section">
            <h3 class="section-title">Редактировать событие</h3>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label for="title">Название события *</label>
                        <input type="text" id="title" name="title" class="form-control" 
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : htmlspecialchars($event['title']); ?>" 
                               required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="event_date">Дата *</label>
                        <input type="date" id="event_date" name="event_date" class="form-control" 
                               value="<?php echo isset($_POST['event_date']) ? $_POST['event_date'] : $event['event_date']; ?>" 
                               required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="category_id">Категория *</label>
                        <select id="category_id" name="category_id" class="form-control" required>
                            <option value="">Выберите категорию</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                <?php 
                                if (isset($_POST['category_id'])) {
                                    echo $_POST['category_id'] == $category['id'] ? 'selected' : '';
                                } else {
                                    echo $event['category_id'] == $category['id'] ? 'selected' : '';
                                }
                                ?>
                                style="color: <?php echo $category['color']; ?>;">
                                <?php echo $category['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="event_type">Вид мероприятия *</label>
                        <select id="event_type" name="event_type" class="form-control" required>
                            <option value="">Выберите вид</option>
                            <option value="Встреча" <?php echo (isset($_POST['event_type']) && $_POST['event_type'] == 'Встреча') || $event['event_type'] == 'Встреча' ? 'selected' : ''; ?>>Встреча</option>
                            <option value="Конференция" <?php echo (isset($_POST['event_type']) && $_POST['event_type'] == 'Конференция') || $event['event_type'] == 'Конференция' ? 'selected' : ''; ?>>Конференция</option>
                            <option value="Праздник" <?php echo (isset($_POST['event_type']) && $_POST['event_type'] == 'Праздник') || $event['event_type'] == 'Праздник' ? 'selected' : ''; ?>>Праздник</option>
                            <option value="Задача" <?php echo (isset($_POST['event_type']) && $_POST['event_type'] == 'Задача') || $event['event_type'] == 'Задача' ? 'selected' : ''; ?>>Задача</option>
                            <option value="Напоминание" <?php echo (isset($_POST['event_type']) && $_POST['event_type'] == 'Напоминание') || $event['event_type'] == 'Напоминание' ? 'selected' : ''; ?>>Напоминание</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="responsible_users">Ответственные</label>
                    <input type="text" id="responsible_users" name="responsible_users" class="form-control" 
                           value="<?php echo isset($_POST['responsible_users']) ? htmlspecialchars($_POST['responsible_users']) : htmlspecialchars($event['responsible_users']); ?>"
                           placeholder="Введите имена через запятую">
                </div>
                
                <div class="form-group">
                    <label for="description">Описание</label>
                    <textarea id="description" name="description" class="form-control" rows="4" 
                              placeholder="Подробное описание события..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : htmlspecialchars($event['description']); ?></textarea>
                </div>
                
                <div class="form-actions" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                    <button type="submit" name="update_event" class="btn btn-primary">
                        Сохранить изменения
                    </button>
                    <a href="events.php" class="btn btn-secondary">Отмена</a>
                    <div style="margin-left: auto; display: flex; gap: 0.5rem;">
                        <span class="event-category" style="background-color: <?php echo $event['category_color']; ?>20; color: <?php echo $event['category_color']; ?>;">
                            ID: #<?php echo $event['id']; ?>
                        </span>
                        <span class="event-category" style="background-color: #f0f0f0; color: #666;">
                            Создано: <?php echo date('d.m.Y', strtotime($event['created_at'])); ?>
                        </span>
                    </div>
                </div>
            </form>
        </div>

        <!-- Быстрые действия -->
        <div class="section">
            <h3 class="section-title">Быстрые действия</h3>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <button type="button" class="btn" onclick="duplicateEvent()" style="background: var(--gradient-success); color: white;">
                    Дублировать событие
                </button>
                <button type="button" class="btn" onclick="changeDateToToday()" style="background: var(--gradient-secondary); color: white;">
                    Установить сегодняшнюю дату
                </button>
                <button type="button" class="btn" onclick="clearForm()" style="background: #f5576c; color: white;">
                    Очистить форму
                </button>
            </div>
        </div>
    </div>
</main>

<script>
// Функция для дублирования события
function duplicateEvent() {
    if (confirm('Создать копию этого события?')) {
        // Здесь можно добавить AJAX запрос для дублирования
        // Пока просто покажем сообщение
        alert('Функция дублирования будет реализована в будущем');
    }
}

// Функция для установки сегодняшней даты
function changeDateToToday() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('event_date').value = today;
}

// Функция для очистки формы
function clearForm() {
    if (confirm('Очистить все поля формы? Текущие данные будут потеряны.')) {
        document.getElementById('title').value = '';
        document.getElementById('event_date').value = '';
        document.getElementById('category_id').selectedIndex = 0;
        document.getElementById('event_type').selectedIndex = 0;
        document.getElementById('responsible_users').value = '';
        document.getElementById('description').value = '';
    }
}

// Подсказки при наведении на поля
document.addEventListener('DOMContentLoaded', function() {
    const fields = {
        'title': 'Введите понятное название события',
        'event_date': 'Выберите дату проведения события',
        'category_id': 'Выберите подходящую категорию',
        'event_type': 'Укажите тип мероприятия',
        'responsible_users': 'Перечислите участников через запятую',
        'description': 'Опишите детали события'
    };

    for (const [id, tooltip] of Object.entries(fields)) {
        const element = document.getElementById(id);
        if (element) {
            element.title = tooltip;
        }
    }

    // Автосохранение при изменении полей (опционально)
    const form = document.querySelector('form');
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        input.addEventListener('change', function() {
            // Можно добавить автосохранение через AJAX
            console.log('Поле изменено:', this.name);
        });
    });
});

// Валидация даты (нельзя выбрать прошедшие даты)
document.getElementById('event_date').addEventListener('change', function() {
    const selectedDate = new Date(this.value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (selectedDate < today) {
        if (!confirm('Вы выбрали прошедшую дату. Вы уверены?')) {
            this.value = '';
        }
    }
});
</script>


<?php require_once 'includes/footer.php'; ?>