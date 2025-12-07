<?php
require_once 'config/db.php';
require_once 'includes/auth_check.php';
require_once 'includes/header.php';
require_once 'vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

$database = new Database();
$db = $database->getConnection();

// Обработка добавления отчета
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_report'])) {
    $event_id = $_POST['event_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $report_status = isset($_POST['status']) ? $_POST['status'] : 'draft';
    
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
        $upload_dir = 'uploads/reports/' . date('Y/m/d') . '/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                $errors[] = "Не удалось создать директорию для загрузки файлов";
            }
        }
        
        if (is_dir($upload_dir)) {
            foreach ($_FILES['report_files']['name'] as $key => $name) {
                if ($_FILES['report_files']['error'][$key] === UPLOAD_ERR_OK) {
                    // Проверка типа файла
                    $allowed_types = [
                        'image/jpeg', 'image/png', 'image/gif', 
                        'application/pdf', 'application/msword', 
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv'
                    ];
                    $file_type = $_FILES['report_files']['type'][$key];
                    
                    if (!in_array($file_type, $allowed_types)) {
                        $errors[] = "Недопустимый тип файла: $name";
                        continue;
                    }
                    
                    // Проверка размера файла (максимум 10MB)
                    if ($_FILES['report_files']['size'][$key] > 10 * 1024 * 1024) {
                        $errors[] = "Файл слишком большой: $name (максимум 10MB)";
                        continue;
                    }
                    
                    $tmp_name = $_FILES['report_files']['tmp_name'][$key];
                    $filename = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($tmp_name, $filepath)) {
                        $uploaded_files[] = [
                            'name' => $name,
                            'path' => $filepath,
                            'type' => $file_type
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
            $db->beginTransaction();
            
            // ДОБАВЛЕНИЕ ОТЧЕТА В БАЗУ ДАННЫХ
            $query = "INSERT INTO reports (event_id, title, description, author_id, status, created_at) 
                      VALUES (:event_id, :title, :description, :author_id, :status, NOW())";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':event_id', $event_id);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':author_id', $_SESSION['user_id']);
            $stmt->bindParam(':status', $report_status);
            
            if ($stmt->execute()) {
                $report_id = $db->lastInsertId();
                
                // СОХРАНЕНИЕ ИНФОРМАЦИИ О ПРИКРЕПЛЕННЫХ ФАЙЛАХ
                if (!empty($uploaded_files)) {
                    $file_query = "INSERT INTO report_files (report_id, filename, filepath, file_type, uploaded_at) 
                                   VALUES (:report_id, :filename, :filepath, :file_type, NOW())";
                    $file_stmt = $db->prepare($file_query);
                    
                    foreach ($uploaded_files as $file) {
                        $file_stmt->bindParam(':report_id', $report_id);
                        $file_stmt->bindParam(':filename', $file['name']);
                        $file_stmt->bindParam(':filepath', $file['path']);
                        $file_stmt->bindParam(':file_type', $file['type']);
                        $file_stmt->execute();
                    }
                }
                
                // СОЗДАНИЕ ЛОКАЛЬНЫХ ОТЧЕТОВ В ФОРМАТАХ EXCEL И CSV
                try {
                    createLocalReports($db, $report_id, $event_id, $title, $description, $_SESSION['user_id']);
                } catch (Exception $e) {
                    error_log("Ошибка создания локальных отчетов: " . $e->getMessage());
                }
                
                $log_query = "INSERT INTO report_logs (report_id, action, user_id, created_at) 
                              VALUES (:report_id, 'created', :user_id, NOW())";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->bindParam(':report_id', $report_id);
                $log_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $log_stmt->execute();
                
                $db->commit();
                
                $_SESSION['success'] = "Отчет успешно добавлен" . 
                    (!empty($uploaded_files) ? " (загружено файлов: " . count($uploaded_files) . ")" : "") .
                    ". Отчеты в форматах Excel и CSV сохранены локально.";
                header("Location: reports.php");
                exit();
            } else {
                throw new Exception("Ошибка при добавлении отчета в базу данных");
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            
            foreach ($uploaded_files as $file) {
                if (file_exists($file['path'])) {
                    @unlink($file['path']);
                }
            }
            
            $report_dir = 'exports/reports/' . $report_id . '/';
            if (isset($report_id) && is_dir($report_dir)) {
                array_map('unlink', glob($report_dir . "/*"));
                @rmdir($report_dir);
            }
            
            $errors[] = "Ошибка при добавлении отчета: " . $e->getMessage();
        }
    }
}

/**
 * ФУНКЦИЯ ДЛЯ СОЗДАНИЯ ЛОКАЛЬНЫХ ОТЧЕТОВ В EXCEL И CSV
 */
function createLocalReports($db, $report_id, $event_id, $title, $description, $author_id) {
    // Создаем директорию для отчетов если она не существует
    $export_dir = 'exports/reports/' . $report_id . '/';
    if (!is_dir($export_dir)) {
        if (!mkdir($export_dir, 0755, true)) {
            throw new Exception("Не удалось создать директорию для отчетов");
        }
    }
    
    try {
        // ПОЛУЧАЕМ ДОПОЛНИТЕЛЬНУЮ ИНФОРМАЦИЮ ДЛЯ ОТЧЕТА
        $info_query = "SELECT 
                        e.title as event_title,
                        e.event_date,
                        e.location,
                        u.username as author_name,
                        u.email as author_email
                      FROM reports r
                      LEFT JOIN events e ON r.event_id = e.id
                      LEFT JOIN users u ON r.author_id = u.id
                      WHERE r.id = :report_id";
        
        $stmt = $db->prepare($info_query);
        $stmt->bindParam(':report_id', $report_id);
        $stmt->execute();
        $report_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$report_info) {
            $report_info = [
                'event_title' => 'Неизвестное событие',
                'event_date' => date('Y-m-d'),
                'location' => 'Не указано',
                'author_name' => 'Неизвестный автор',
                'author_email' => ''
            ];
        }
        
        // 1. СОЗДАНИЕ ОТЧЕТА В ФОРМАТЕ EXCEL (XLSX)
        if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Заголовки отчета
            $sheet->setCellValue('A1', 'ОТЧЕТ ПО СОБЫТИЮ');
            $sheet->mergeCells('A1:D1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');
            
            // Основная информация
            $sheet->setCellValue('A3', 'Название отчета:');
            $sheet->setCellValue('B3', $title);
            
            $sheet->setCellValue('A4', 'Событие:');
            $sheet->setCellValue('B4', $report_info['event_title']);
            
            $sheet->setCellValue('A5', 'Дата события:');
            $sheet->setCellValue('B5', date('d.m.Y', strtotime($report_info['event_date'])));
            
            $sheet->setCellValue('A6', 'Место проведения:');
            $sheet->setCellValue('B6', $report_info['location']);
            
            $sheet->setCellValue('A7', 'Автор отчета:');
            $sheet->setCellValue('B7', $report_info['author_name'] . ' (' . $report_info['author_email'] . ')');
            
            $sheet->setCellValue('A8', 'Дата создания отчета:');
            $sheet->setCellValue('B8', date('d.m.Y H:i:s'));
            
            // Описание отчета
            $sheet->setCellValue('A10', 'Описание:');
            $sheet->mergeCells('A10:D10');
            $sheet->getStyle('A10')->getFont()->setBold(true);
            
            $sheet->setCellValue('A11', $description);
            $sheet->mergeCells('A11:D20');
            $sheet->getStyle('A11')->getAlignment()->setWrapText(true);
            
            // Настройка ширины столбцов
            $sheet->getColumnDimension('A')->setWidth(25);
            $sheet->getColumnDimension('B')->setWidth(40);
            $sheet->getColumnDimension('C')->setWidth(25);
            $sheet->getColumnDimension('D')->setWidth(25);
            
            // Сохранение Excel файла
            $excel_filename = $export_dir . 'report_' . $report_id . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $writer->save($excel_filename);
        }
        
        // 2. СОЗДАНИЕ ОТЧЕТА В ФОРМАТЕ CSV (альтернатива если нет PhpSpreadsheet)
        $csv_filename = $export_dir . 'report_' . $report_id . '.csv';
        $csv_content = "Отчет по событию\n\n";
        $csv_content .= "Название отчета," . $title . "\n";
        $csv_content .= "Событие," . $report_info['event_title'] . "\n";
        $csv_content .= "Дата события," . date('d.m.Y', strtotime($report_info['event_date'])) . "\n";
        $csv_content .= "Место проведения," . $report_info['location'] . "\n";
        $csv_content .= "Автор отчета," . $report_info['author_name'] . "\n";
        $csv_content .= "Email автора," . $report_info['author_email'] . "\n";
        $csv_content .= "Дата создания отчета," . date('d.m.Y H:i:s') . "\n\n";
        $csv_content .= "Описание:\n" . $description . "\n";
        
        file_put_contents($csv_filename, $csv_content);
        
        // 3. СОЗДАНИЕ ПРОСТОГО ТЕКСТОВОГО ОТЧЕТА
        $txt_filename = $export_dir . 'report_' . $report_id . '.txt';
        $txt_content = "===================================\n";
        $txt_content .= "ОТЧЕТ ПО СОБЫТИЮ\n";
        $txt_content .= "===================================\n\n";
        $txt_content .= "Название отчета: " . $title . "\n";
        $txt_content .= "Событие: " . $report_info['event_title'] . "\n";
        $txt_content .= "Дата события: " . date('d.m.Y', strtotime($report_info['event_date'])) . "\n";
        $txt_content .= "Место проведения: " . $report_info['location'] . "\n";
        $txt_content .= "Автор отчета: " . $report_info['author_name'] . "\n";
        $txt_content .= "Email автора: " . $report_info['author_email'] . "\n";
        $txt_content .= "Дата создания отчета: " . date('d.m.Y H:i:s') . "\n\n";
        $txt_content .= "ОПИСАНИЕ:\n";
        $txt_content .= str_repeat("-", 50) . "\n";
        $txt_content .= $description . "\n";
        $txt_content .= str_repeat("-", 50) . "\n";
        
        file_put_contents($txt_filename, $txt_content);
        
        // 4. СОХРАНЕНИЕ ИНФОРМАЦИИ О СОЗДАННЫХ ФАЙЛАХ В БАЗУ ДАННЫХ
        $files_query = "INSERT INTO report_exports (report_id, file_type, file_path, created_at) 
                        VALUES (:report_id, :file_type, :file_path, NOW())";
        $files_stmt = $db->prepare($files_query);
        
        $export_files = [
            ['type' => 'excel', 'path' => isset($excel_filename) ? $excel_filename : ''],
            ['type' => 'csv', 'path' => $csv_filename],
            ['type' => 'txt', 'path' => $txt_filename]
        ];
        
        foreach ($export_files as $file) {
            if (!empty($file['path'])) {
                $files_stmt->bindParam(':report_id', $report_id);
                $files_stmt->bindParam(':file_type', $file['type']);
                $files_stmt->bindParam(':file_path', $file['path']);
                $files_stmt->execute();
            }
        }
        
    } catch (Exception $e) {
        throw new Exception("Ошибка создания локальных отчетов: " . $e->getMessage());
    }
}

// Получение отчетов
try {
    $query = "SELECT r.*, e.title as event_title, u.username as author_name, 
                     GROUP_CONCAT(rf.filename) as filenames,
                     GROUP_CONCAT(rf.filepath) as filepaths,
                     GROUP_CONCAT(re.file_type) as export_types
              FROM reports r 
              LEFT JOIN events e ON r.event_id = e.id 
              LEFT JOIN users u ON r.author_id = u.id
              LEFT JOIN report_files rf ON r.id = rf.report_id
              LEFT JOIN report_exports re ON r.id = re.report_id
              WHERE r.author_id = :user_id OR :user_role = 'admin'
              GROUP BY r.id
              ORDER BY r.created_at DESC";
              
    $reports_stmt = $db->prepare($query);
    $reports_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
    $reports_stmt->bindParam(':user_role', $user_role);
    $reports_stmt->execute();
    $reports = $reports_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $reports = [];
    $errors[] = "Ошибка при загрузке отчетов: " . $e->getMessage();
}

try {
    $events_query = "SELECT id, title, event_date FROM events 
                     WHERE author_id = :user_id OR responsible_users LIKE :username 
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
            <button class="btn btn-secondary" onclick="showHelp()">
                <i class="fas fa-question-circle"></i> Справка
            </button>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button class="close-btn" onclick="this.parentElement.style.display='none'">×</button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errors) && !empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                <button class="close-btn" onclick="this.parentElement.style.display='none'">×</button>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- ФОРМА ДОБАВЛЕНИЯ ОТЧЕТА -->
        <div class="section card">
            <h3 class="section-title"><i class="fas fa-plus-circle"></i> Добавить отчет</h3>
            <form method="POST" action="" enctype="multipart/form-data" id="report-form">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label for="title"><i class="fas fa-heading"></i> Название отчета *</label>
                        <input type="text" id="title" name="title" class="form-control" required 
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                               placeholder="Введите название отчета">
                        <small class="form-hint">Обязательное поле, минимум 5 символов</small>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="event_id"><i class="fas fa-calendar-alt"></i> Событие *</label>
                        <select id="event_id" name="event_id" class="form-control" required>
                            <option value="">Выберите событие</option>
                            <?php foreach ($events as $event): ?>
                            <option value="<?php echo $event['id']; ?>" 
                                <?php echo (isset($_POST['event_id']) && $_POST['event_id'] == $event['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($event['title']) . ' (' . date('d.m.Y', strtotime($event['event_date'])) . ')'; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="status"><i class="fas fa-tag"></i> Статус отчета</label>
                        <select id="status" name="status" class="form-control">
                            <option value="draft" <?php echo (isset($_POST['status']) && $_POST['status'] == 'draft') ? 'selected' : ''; ?>>Черновик</option>
                            <option value="published" <?php echo (isset($_POST['status']) && $_POST['status'] == 'published') ? 'selected' : ''; ?>>Опубликован</option>
                            <option value="archived" <?php echo (isset($_POST['status']) && $_POST['status'] == 'archived') ? 'selected' : ''; ?>>Архив</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description"><i class="fas fa-align-left"></i> Описание отчета</label>
                    <textarea id="description" name="description" class="form-control" rows="5" 
                              placeholder="Подробное описание отчета..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    <small class="form-hint">Можно использовать форматирование</small>
                </div>
                
                <div class="form-group">
                    <label for="report_files"><i class="fas fa-paperclip"></i> Прикрепить файлы</label>
                    <div class="file-upload-area" id="file-upload-area">
                        <input type="file" id="report_files" name="report_files[]" class="file-input" multiple 
                               accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.gif">
                        <div class="file-upload-text">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Перетащите файлы сюда или нажмите для выбора</p>
                            <small>Максимум 10 файлов, не более 10MB каждый</small>
                        </div>
                    </div>
                    <div id="file-preview" class="file-preview-container"></div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="add_report" class="btn btn-primary" id="submit-btn">
                        <i class="fas fa-save"></i> Добавить отчет и создать экспорт
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">
                        <i class="fas fa-redo"></i> Очистить форму
                    </button>
                </div>
                
                <div class="form-info">
                    <p><i class="fas fa-info-circle"></i> При нажатии кнопки "Добавить отчет" будут:</p>
                    <ul>
                        <li>Создана запись в базе данных</li>
                        <li>Сохранены прикрепленные файлы</li>
                        <li>Созданы локальные отчеты в форматах Excel, CSV и TXT</li>
                        <li>Добавлена запись в журнал действий</li>
                    </ul>
                </div>
            </form>
        </div>

        <!-- СПИСОК ОТЧЕТОВ -->
        <div class="section">
            <h3 class="section-title"><i class="fas fa-list"></i> Все отчеты (<?php echo count($reports); ?>)</h3>
            
            <?php if (empty($reports)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt fa-3x"></i>
                    <h4>Отчеты не найдены</h4>
                    <p>Создайте первый отчет используя форму выше</p>
                </div>
            <?php else: ?>
                <div class="reports-grid">
                <?php foreach ($reports as $report): ?>
                <div class="report-card">
                    <div class="report-header">
                        <div class="report-title"><?php echo htmlspecialchars($report['title']); ?></div>
                        <div class="report-status status-<?php echo $report['status']; ?>">
                            <?php 
                            $status_labels = [
                                'draft' => 'Черновик',
                                'published' => 'Опубликован',
                                'archived' => 'Архив'
                            ];
                            echo $status_labels[$report['status']] ?? $report['status'];
                            ?>
                        </div>
                    </div>
                    
                    <div class="report-meta">
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span>Событие: <?php echo htmlspecialchars($report['event_title']); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-user"></i>
                            <span>Автор: <?php echo htmlspecialchars($report['author_name']); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            <span><?php echo date('d.m.Y H:i', strtotime($report['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <?php if (!empty($report['description'])): ?>
                    <div class="report-description">
                        <?php echo nl2br(htmlspecialchars(substr($report['description'], 0, 200) . (strlen($report['description']) > 200 ? '...' : ''))); ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- ПРИКРЕПЛЕННЫЕ ФАЙЛЫ -->
                    <?php if (!empty($report['filenames'])): ?>
                    <div class="report-files">
                        <h5><i class="fas fa-paperclip"></i> Прикрепленные файлы:</h5>
                        <div class="files-grid">
                        <?php 
                        $filenames = explode(',', $report['filenames']);
                        $filepaths = explode(',', $report['filepaths']);
                        ?>
                        <?php foreach ($filenames as $index => $filename): ?>
                            <?php if (isset($filepaths[$index])): ?>
                            <div class="file-item">
                                <?php if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $filename)): ?>
                                    <div class="file-icon image-file" onclick="openImageModal('<?php echo $filepaths[$index]; ?>')">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php elseif (preg_match('/\.pdf$/i', $filename)): ?>
                                    <div class="file-icon pdf-file" onclick="openFile('<?php echo $filepaths[$index]; ?>')">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                <?php elseif (preg_match('/\.(doc|docx)$/i', $filename)): ?>
                                    <div class="file-icon doc-file" onclick="openFile('<?php echo $filepaths[$index]; ?>')">
                                        <i class="fas fa-file-word"></i>
                                    </div>
                                <?php elseif (preg_match('/\.(xls|xlsx|csv)$/i', $filename)): ?>
                                    <div class="file-icon excel-file" onclick="openFile('<?php echo $filepaths[$index]; ?>')">
                                        <i class="fas fa-file-excel"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="file-icon other-file" onclick="openFile('<?php echo $filepaths[$index]; ?>')">
                                        <i class="fas fa-file"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="file-name" title="<?php echo htmlspecialchars($filename); ?>">
                                    <?php echo htmlspecialchars($filename); ?>
                                </div>
                                <div class="file-actions">
                                    <button onclick="downloadFile('<?php echo $filepaths[$index]; ?>', '<?php echo htmlspecialchars($filename); ?>')">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- ЭКСПОРТИРОВАННЫЕ ФАЙЛЫ -->
                    <?php if (!empty($report['export_types'])): ?>
                    <div class="report-exports">
                        <h5><i class="fas fa-file-export"></i> Экспортированные отчеты:</h5>
                        <div class="export-buttons">
                            <?php 
                            $export_types = explode(',', $report['export_types']);
                            $export_files = [
                                'excel' => ['icon' => 'fa-file-excel', 'label' => 'Excel', 'color' => 'success'],
                                'csv' => ['icon' => 'fa-file-csv', 'label' => 'CSV', 'color' => 'info'],
                                'txt' => ['icon' => 'fa-file-alt', 'label' => 'TXT', 'color' => 'secondary']
                            ];
                            ?>
                            <?php foreach ($export_types as $type): ?>
                                <?php if (isset($export_files[$type])): ?>
                                <button class="btn btn-sm btn-<?php echo $export_files[$type]['color']; ?>" 
                                        onclick="downloadExport(<?php echo $report['id']; ?>, '<?php echo $type; ?>')">
                                    <i class="fas <?php echo $export_files[$type]['icon']; ?>"></i> 
                                    <?php echo $export_files[$type]['label']; ?>
                                </button>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="report-actions">
                        <button class="btn btn-sm btn-primary" onclick="viewReport(<?php echo $report['id']; ?>)">
                            <i class="fas fa-eye"></i> Просмотр
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="editReport(<?php echo $report['id']; ?>)">
                            <i class="fas fa-edit"></i> Редактировать
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteReport(<?php echo $report['id']; ?>)">
                            <i class="fas fa-trash"></i> Удалить
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- МОДАЛЬНОЕ ОКНО ДЛЯ ПРОСМОТРА ИЗОБРАЖЕНИЙ -->
<div id="imageModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeImageModal()">&times;</span>
        <img id="modalImage" src="" alt="">
    </div>
</div>

<!-- СПРАВКА -->
<div id="helpModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close-modal" onclick="closeHelp()">&times;</span>
        <h3><i class="fas fa-question-circle"></i> Справка по работе с отчетами</h3>
        <div class="help-content">
            <h4>Как добавить отчет:</h4>
            <ol>
                <li>Заполните обязательные поля (название и событие)</li>
                <li>Добавьте описание отчета (необязательно)</li>
                <li>Прикрепите файлы при необходимости</li>
                <li>Нажмите "Добавить отчет"</li>
            </ol>
            
            <h4>Что происходит при создании отчета:</h4>
            <ul>
                <li>Запись сохраняется в базе данных</li>
                <li>Прикрепленные файлы загружаются на сервер</li>
                <li>Автоматически создаются отчеты в форматах Excel, CSV и TXT</li>
                <li>Запись добавляется в журнал действий</li>
            </ul>
            
            <h4>Доступные форматы экспорта:</h4>
            <ul>
                <li><strong>Excel (.xlsx)</strong> - для работы в Microsoft Excel</li>
                <li><strong>CSV (.csv)</strong> - для импорта в другие системы</li>
                <li><strong>TXT (.txt)</strong> - простой текстовый формат</li>
            </ul>
        </div>
    </div>
</div>

<script src="./script/files.js"></script>

<style>
/* ДОПОЛНИТЕЛЬНЫЕ СТИЛИ */
.file-upload-area {
    border: 2px dashed #ccc;
    border-radius: 8px;
    padding: 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #4a5568;
}

.file-upload-area:hover {
    border-color: #007bff;
    background: #6e7e8dff;
}

.file-upload-area.dragover {
    border-color: #28a745;
    background: #4a5568;
}

.file-upload-area.has-files {
    border-color: #17a2b8;
    background: #4a5568;
}

.file-upload-text i {
    font-size: 48px;
    color: #6c757d;
    margin-bottom: 15px;
}

.file-preview-container {
    margin-top: 20px;
}

.selected-files {
    background: #4a5568;
    border: 1px solid #4a5568;
    border-radius: 8px;
    padding: 15px;
}

.files-header {
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.files-list {
    max-height: 200px;
    overflow-y: auto;
}

.file-item-preview {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    border-bottom: 1px solid #f8f9fa;
}

.file-item-preview:last-child {
    border-bottom: none;
}

.file-item-preview.error {
    background: #4a5568;
    color: #dc3545;
}

.file-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.file-icon {
    width: 24px;
    text-align: center;
    color: #6c757d;
}

.file-name {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.total-size {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #eee;
    text-align: right;
    font-weight: bold;
}

.file-error {
    margin-top: 10px;
    padding: 10px;
    background: #4a5568;
    color: #dc3545;
    border-radius: 4px;
    border-left: 4px solid #dc3545;
}

.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.report-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: 1px solid #e9ecef;
    transition: transform 0.2s, box-shadow 0.2s;
}

.report-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.report-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.report-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #212529;
    flex: 1;
}

.report-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-draft {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.status-published {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.status-archived {
    background: #f8f9fa;
    color: #6c757d;
    border: 1px solid #e9ecef;
}

.report-meta {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 15px;
    font-size: 0.9rem;
    color: #6c757d;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.report-description {
    margin: 15px 0;
    padding: 15px;
    background: #4a5568;
    border-radius: 8px;
    border-left: 4px solid #007bff;
}

.report-files, .report-exports {
    margin: 15px 0;
    padding: 15px;
    background: #4a5568;
    border-radius: 8px;
}

.files-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.file-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    background: white;
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #4a5568;
    min-width: 100px;
    cursor: pointer;
    transition: all 0.2s;
}

.file-item:hover {
    border-color: #007bff;
    box-shadow: 0 2px 5px rgba(0,123,255,0.2);
}

.file-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 24px;
    margin-bottom: 8px;
}

.image-file {
    background: #4a5568;
    color: #1976d2;
}

.pdf-file {
    background: #4a5568;
    color: #d32f2f;
}

.doc-file {
    background: #4a5568;
    color: #388e3c;
}

.excel-file {
    background: #4a5568;
    color: #f57c00;
}

.other-file {
    background: #4a5568;
    color: #757575;
}

.file-name {
    font-size: 0.8rem;
    text-align: center;
    max-width: 100px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.file-actions button {
    margin-top: 5px;
    padding: 2px 8px;
    border: none;
    background: #f8f9fa;
    border-radius: 4px;
    cursor: pointer;
    color: #6c757d;
}

.file-actions button:hover {
    background: #e9ecef;
    color: #495057;
}

.export-buttons {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.report-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.empty-state i {
    margin-bottom: 20px;
    color: #adb5bd;
}

.form-actions {
    display: flex;
    gap: 15px;
    margin: 20px 0;
}

.form-info {
    background: #4a5568;
    border-radius: 8px;
    padding: 15px;
    margin-top: 20px;
    border-left: 4px solid #1976d2;
}

.form-info ul {
    margin: 10px 0 0 20px;
}

.form-info li {
    margin-bottom: 5px;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    z-index: 10000;
    justify-content: center;
    align-items: center;
}

.modal-content {
    position: relative;
    background: white;
    border-radius: 12px;
    padding: 30px;
    max-width: 800px;
    max-height: 80vh;
    overflow-y: auto;
}

.close-modal {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 28px;
    cursor: pointer;
    color: #6c757d;
}

.close-modal:hover {
    color: #343a40;
}

#modalImage {
    max-width: 100%;
    max-height: 70vh;
    border-radius: 8px;
}

@media (max-width: 768px) {
    .reports-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        flex-direction: column;
    }
    
    .report-actions {
        flex-wrap: wrap;
    }
    
    .export-buttons {
        flex-wrap: wrap;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>