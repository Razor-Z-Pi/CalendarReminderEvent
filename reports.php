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
    
    // Валидация
    if (empty($title)) {
        $errors[] = "Название отчета обязательно";
    }
    
    if (empty($event_id)) {
        $errors[] = "Выберите событие";
    }
    
    // Проверка загрузки файлов
    $uploaded_files = [];
    if (!empty($_FILES['report_files']['name'][0])) {
        $upload_dir = 'uploads/reports/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                $errors[] = "Не удалось создать директорию для загрузки файлов";
            }
        }
        
        if (is_dir($upload_dir)) {
            foreach ($_FILES['report_files']['name'] as $key => $name) {
                if ($_FILES['report_files']['error'][$key] === UPLOAD_ERR_OK) {
                    // Проверка типа файла
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                    $file_type = $_FILES['report_files']['type'][$key];
                    
                    if (!in_array($file_type, $allowed_types)) {
                        $errors[] = "Недопустимый тип файла: $name";
                        continue;
                    }
                    
                    // Проверка размера файла (максимум 5MB)
                    if ($_FILES['report_files']['size'][$key] > 5 * 1024 * 1024) {
                        $errors[] = "Файл слишком большой: $name (максимум 5MB)";
                        continue;
                    }
                    
                    $tmp_name = $_FILES['report_files']['tmp_name'][$key];
                    $filename = time() . '_' . uniqid() . '_' . basename($name);
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($tmp_name, $filepath)) {
                        $uploaded_files[] = [
                            'name' => $name,
                            'path' => $filepath
                        ];
                    } else {
                        $errors[] = "Ошибка при загрузке файла: $name";
                    }
                } elseif ($_FILES['report_files']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                    $errors[] = "Ошибка загрузки файла: $name (код ошибки: " . $_FILES['report_files']['error'][$key] . ")";
                }
            }
        }
    }
    
    if (empty($errors)) {
        try {
            // Начало транзакции
            $db->beginTransaction();
            
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
                
                // Сохранение информации о файлах
                if (!empty($uploaded_files)) {
                    $file_query = "INSERT INTO report_files (report_id, filename, filepath) VALUES (:report_id, :filename, :filepath)";
                    $file_stmt = $db->prepare($file_query);
                    
                    foreach ($uploaded_files as $file) {
                        $file_stmt->bindParam(':report_id', $report_id);
                        $file_stmt->bindParam(':filename', $file['name']);
                        $file_stmt->bindParam(':filepath', $file['path']);
                        $file_stmt->execute();
                    }
                }
                
                // Подтверждение транзакции
                $db->commit();
                
                $_SESSION['success'] = "Отчет успешно добавлен" . 
                    (!empty($uploaded_files) ? " (загружено файлов: " . count($uploaded_files) . ")" : "");
                header("Location: reports.php");
                exit();
            } else {
                throw new Exception("Ошибка при добавлении отчета");
            }
            
        } catch (Exception $e) {
            // Откат транзакции при ошибке
            $db->rollBack();
            
            // Удаление загруженных файлов при ошибке
            foreach ($uploaded_files as $file) {
                if (file_exists($file['path'])) {
                    unlink($file['path']);
                }
            }
            
            $errors[] = "Ошибка при добавлении отчета: " . $e->getMessage();
        }
    }
}

// Получение отчетов
try {
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
} catch (Exception $e) {
    $reports = [];
    $errors[] = "Ошибка при загрузке отчетов: " . $e->getMessage();
}

// Получение событий для выпадающего списка
try {
    $events_query = "SELECT id, title FROM events WHERE author_id = :user_id OR responsible_users LIKE :username 
                     ORDER BY event_date DESC";
    $events_stmt = $db->prepare($events_query);
    $events_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $username = '%' . $_SESSION['username'] . '%';
    $events_stmt->bindParam(':username', $username);
    $events_stmt->execute();
    $events = $events_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $events = [];
    $errors[] = "Ошибка при загрузке событий: " . $e->getMessage();
}
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
            <form method="POST" action="" enctype="multipart/form-data" id="report-form">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label for="title">Название отчета *</label>
                        <input type="text" id="title" name="title" class="form-control" required 
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="event_id">Событие *</label>
                        <select id="event_id" name="event_id" class="form-control" required>
                            <option value="">Выберите событие</option>
                            <?php foreach ($events as $event): ?>
                            <option value="<?php echo $event['id']; ?>" 
                                <?php echo (isset($_POST['event_id']) && $_POST['event_id'] == $event['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($event['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Описание отчета</label>
                    <textarea id="description" name="description" class="form-control" rows="4"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="report_files">Прикрепить файлы (фотографии, PDF, Word)</label>
                    <input type="file" id="report_files" name="report_files[]" class="form-control" multiple 
                           accept="image/*,.pdf,.doc,.docx,.jpg,.jpeg,.png,.gif">
                    <small>Можно выбрать несколько файлов. Максимальный размер файла: 5MB. Допустимые форматы: JPG, PNG, GIF, PDF, DOC, DOCX</small>
                    <div id="file-preview" style="margin-top: 10px;"></div>
                </div>
                
                <button type="submit" name="add_report" class="btn btn-primary" id="submit-btn">
                    Добавить отчет
                </button>
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
                            <?php if (isset($filepaths[$index]) && file_exists($filepaths[$index])): ?>
                                <?php if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $filename)): ?>
                                    <div class="file-item" style="text-align: center;">
                                        <img src="<?php echo $filepaths[$index]; ?>" alt="<?php echo htmlspecialchars($filename); ?>" class="report-image"
                                             style="cursor: pointer;" onclick="openImageModal('<?php echo $filepaths[$index]; ?>')">
                                        <div style="margin-top: 5px; font-size: 0.8rem;">
                                            <?php echo htmlspecialchars($filename); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="file-item" style="text-align: center;">
                                        <div style="width: 100px; height: 100px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px; cursor: pointer;"
                                             onclick="downloadFile('<?php echo $filepaths[$index]; ?>', '<?php echo htmlspecialchars($filename); ?>')">
                                            <span style="font-weight: bold; color: #666;">
                                                <?php echo strtoupper(pathinfo($filename, PATHINFO_EXTENSION)); ?>
                                            </span>
                                        </div>
                                        <div style="margin-top: 5px; font-size: 0.8rem;">
                                            <?php echo htmlspecialchars($filename); ?>
                                        </div>
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

<!-- Модальное окно для просмотра изображений -->
<div id="imageModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; justify-content: center; align-items: center;">
    <div style="position: relative; max-width: 90%; max-height: 90%;">
        <img id="modalImage" src="" alt="" style="max-width: 100%; max-height: 100%; border-radius: 8px;">
        <button onclick="closeImageModal()" style="position: absolute; top: -40px; right: 0; background: #f5576c; color: white; border: none; padding: 10px; border-radius: 50%; cursor: pointer;">×</button>
    </div>
</div>

<script>
// Предпросмотр выбранных файлов
document.getElementById('report_files').addEventListener('change', function(e) {
    const preview = document.getElementById('file-preview');
    preview.innerHTML = '';
    
    if (this.files.length > 0) {
        const fileList = document.createElement('div');
        fileList.style.marginTop = '10px';
        fileList.innerHTML = '<strong>Выбранные файлы:</strong>';
        
        const list = document.createElement('ul');
        list.style.marginTop = '5px';
        list.style.paddingLeft = '20px';
        
        for (let file of this.files) {
            const item = document.createElement('li');
            item.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
            list.appendChild(item);
        }
        
        fileList.appendChild(list);
        preview.appendChild(fileList);
    }
});

// Функции для работы с файлами
function openImageModal(src) {
    document.getElementById('modalImage').src = src;
    document.getElementById('imageModal').style.display = 'flex';
}

function closeImageModal() {
    document.getElementById('imageModal').style.display = 'none';
}

function downloadFile(filepath, filename) {
    const link = document.createElement('a');
    link.href = filepath;
    link.download = filename;
    link.click();
}

// Закрытие модального окна по клику на фон
document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeImageModal();
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>