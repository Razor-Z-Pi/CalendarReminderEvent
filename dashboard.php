<?php
require_once 'config/db.php';
require_once 'includes/auth_check.php';
require_once 'includes/header.php';

$database = new Database(); 
$db = $database->getConnection();

// Получение событий для текущего пользователя
$user_id = $_SESSION['user_id'];
$query = "SELECT e.*, c.name as category_name, c.color as category_color 
          FROM events e 
          LEFT JOIN categories c ON e.category_id = c.id 
          WHERE e.author_id = :user_id OR e.responsible_users LIKE :username 
          ORDER BY e.event_date";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$username = '%' . $_SESSION['username'] . '%';
$stmt->bindParam(':username', $username);
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение категорий
$categories_query = "SELECT * FROM categories";
$categories_stmt = $db->query($categories_query);
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container">
    <div class="section active">
        <div class="section-header">
            <h2 class="section-title">Календарь событий</h2>
            <div class="calendar-nav">
                <button class="btn btn-primary" id="prev-month">←</button>
                <button class="btn btn-primary" id="next-month">→</button>
            </div>
        </div>
        
        <div class="calendar-container">
            <div class="calendar-header">
                <h3 id="current-month"></h3>
                <div class="search-container">
                    <input type="text" id="event-search" class="form-control" placeholder="Поиск по названию...">
                    <button class="btn btn-primary" id="search-btn">Найти</button>
                </div>
            </div>
            <div class="calendar-grid" id="calendar-grid">
            </div>
        </div>
        
        <div class="section">
            <h3 class="section-title">Быстрое добавление события</h3>
            <form id="quick-event-form" action="add_event.php" method="POST">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label for="title">Название события</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="event_date">Дата</label>
                        <input type="date" id="event_date" name="event_date" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="category_id">Категория</label>
                        <select id="category_id" name="category_id" class="form-control" required>
                            <option value="">Выберите категорию</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="event_type">Вид мероприятия</label>
                        <select id="event_type" name="event_type" class="form-control" required>
                            <option value="">Выберите вид</option>
                            <option value="Встреча">Встреча</option>
                            <option value="Конференция">Конференция</option>
                            <option value="Праздник">Праздник</option>
                            <option value="Задача">Задача</option>
                            <option value="Напоминание">Напоминание</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="responsible_users">Ответственные</label>
                    <input type="text" id="responsible_users" name="responsible_users" class="form-control" 
                           placeholder="Введите имена через запятую">
                </div>
                <div class="form-group">
                    <label for="description">Описание</label>
                    <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Добавить событие</button>
            </form>
        </div>
    </div>
</main>

<script src="./script/script.js"></script>
<script src="./script/calendari.js"></script>
<script>
    // Инициализация календаря с событиями из PHP
    const eventsData = <?php echo json_encode($events); ?>;
    initializeCalendar(eventsData);
</script>

<?php require_once 'includes/footer.php'; ?>