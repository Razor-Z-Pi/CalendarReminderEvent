<?php
require_once 'config/db.php';
require_once 'includes/auth_check.php';
require_once 'includes/header.php';

$database = new Database();
$db = $database->getConnection();

// Обработка добавления отчета
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_report'])) {
    $event_id = $_POST['event_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Название отчета обязательно";
    }
    
    if (empty($event_id)) {
        $errors[] = "Выберите событие";
    }
    
    if (empty($errors)) {
        // Добавление отчета
        $query = "INSERT INTO reports (event_id, title, description, author_id) 
                  VALUES (:event_id, :title, :description, :author_id)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':event_id', $event_id);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':author_id', $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $report_id = $db->lastInsertId();
            
            // Обработка загрузки файлов
            if (!empty($_FILES['report_files']['name'][0])) {
                $upload_dir = 'uploads/reports/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                foreach ($_FILES['report_files']['name'] as $key => $name) {
                    if ($_FILES['report_files']['error'][$key] === UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['report_files']['tmp_name'][$key];
                        $filename = time() . '_' . basename($name);
                        $filepath = $upload_dir . $filename;
                        
                        if (move_uploaded_file($tmp_name, $filepath)) {
                            $file_query = "INSERT INTO report_files (report_id, filename, filepath) VALUES (:report_id, :filename, :filepath)";
                            $file_stmt = $db->prepare($file_query);
                            $file_stmt->bindParam(':report_id', $report_id);
                            $file_stmt->bindParam(':filename', $name);
                            $file_stmt->bindParam(':filepath', $filepath);
                            $file_stmt->execute();
                        }
                    }
                }
            }
            
            $_SESSION['success'] = "Отчет успешно добавлен";
            header("Location: reports.php");
            exit();
        } else {
            $errors[] = "Ошибка при добавлении отчета";
        }
    }
}

// Получение отчетов
$query = "SELECT r.*, e.title as event_title, u.username as author_name, 
                 GROUP_CONCAT(rf.filename) as filenames,
                 GROUP_CONCAT(rf.filepath) as filepaths
          FROM reports r 
          LEFT JOIN events e ON r.event_id = e.id 
          LEFT JOIN users u ON r.author_id = u.id
          LEFT JOIN report_files rf ON r.id = rf.report_id
          GROUP BY r.id
          ORDER BY r.created_at DESC";
          
$reports_stmt = $db->query($query);
$reports = $reports_stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение событий для выпадающего списка
$events_query = "SELECT id, title FROM events WHERE author_id = :user_id OR responsible_users LIKE :username 
                 ORDER BY event_date DESC";
$events_stmt = $db->prepare($events_query);
$events_stmt->bindParam(':user_id', $_SESSION['user_id']);
$username = '%' . $_SESSION['username'] . '%';
$events_stmt->bindParam(':username', $username);
$events_stmt->execute();
$events = $events_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container">
    <div class="section active">
        <div class="section-header">
            <h2 class="section-title">Отчёты по событиям</h2>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($errors) && !empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Форма добавления отчета -->
        <div class="section">
            <h3 class="section-title">Добавить отчет</h3>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label for="title">Название отчета *</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="event_id">Событие *</label>
                        <select id="event_id" name="event_id" class="form-control" required>
                            <option value="">Выберите событие</option>
                            <?php foreach ($events as $event): ?>
                            <option value="<?php echo $event['id']; ?>"><?php echo htmlspecialchars($event['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Описание отчета</label>
                    <textarea id="description" name="description" class="form-control" rows="4"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="report_files">Прикрепить файлы (фотографии)</label>
                    <input type="file" id="report_files" name="report_files[]" class="form-control" multiple accept="image/*,.pdf,.doc,.docx">
                    <small>Можно выбрать несколько файлов</small>
                </div>
                
                <button type="submit" name="add_report" class="btn btn-primary">Добавить отчет</button>
            </form>
        </div>

        <!-- Список отчетов -->
        <div class="section">
            <h3 class="section-title">Все отчеты</h3>
            
            <?php if (empty($reports)): ?>
                <div class="alert">Отчеты не найдены.</div>
            <?php else: ?>
                <?php foreach ($reports as $report): ?>
                <div class="report-item">
                    <div class="event-header">
                        <div class="event-title"><?php echo htmlspecialchars($report['title']); ?></div>
                        <div class="event-category">К событию: <?php echo htmlspecialchars($report['event_title']); ?></div>
                    </div>
                    
                    <div class="event-details">
                        <div><strong>Автор:</strong> <?php echo htmlspecialchars($report['author_name']); ?></div>
                        <div><strong>Дата создания:</strong> <?php echo date('d.m.Y H:i', strtotime($report['created_at'])); ?></div>
                    </div>
                    
                    <?php if (!empty($report['description'])): ?>
                    <div class="event-description"><?php echo nl2br(htmlspecialchars($report['description'])); ?></div>
                    <?php endif; ?>
                    
                    <!-- Прикрепленные файлы -->
                    <?php if (!empty($report['filenames'])): ?>
                    <div class="report-images">
                        <?php 
                        $filenames = explode(',', $report['filenames']);
                        $filepaths = explode(',', $report['filepaths']);
                        ?>
                        <?php foreach ($filenames as $index => $filename): ?>
                            <?php if (file_exists($filepaths[$index])): ?>
                                <?php if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $filename)): ?>
                                    <img src="<?php echo $filepaths[$index]; ?>" alt="<?php echo htmlspecialchars($filename); ?>" class="report-image">
                                <?php else: ?>
                                    <div style="text-align: center;">
                                        <div style="width: 100px; height: 100px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                            <span><?php echo pathinfo($filename, PATHINFO_EXTENSION); ?></span>
                                        </div>
                                        <small><?php echo htmlspecialchars($filename); ?></small>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>