const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
const MAX_FILE_COUNT = 10;

// ФУНКЦИЯ ДЛЯ ПРЕДПРОСМОТРА ВЫБРАННЫХ ФАЙЛОВ
document.getElementById('report_files').addEventListener('change', function(e) {
    const preview = document.getElementById('file-preview');
    const uploadArea = document.getElementById('file-upload-area');
    preview.innerHTML = '';
    
    if (this.files.length > 0) {
        // Обновляем текст в области загрузки
        uploadArea.classList.add('has-files');
        uploadArea.querySelector('.file-upload-text p').textContent = `Выбрано файлов: ${this.files.length}`;
        
        // Создаем контейнер для списка файлов
        const fileList = document.createElement('div');
        fileList.className = 'selected-files';
        
        const listHeader = document.createElement('div');
        listHeader.className = 'files-header';
        listHeader.innerHTML = `<strong><i class="fas fa-folder-open"></i> Выбранные файлы (${this.files.length}):</strong>`;
        fileList.appendChild(listHeader);
        
        const list = document.createElement('div');
        list.className = 'files-list';
        
        let totalSize = 0;
        let hasError = false;
        
        for (let i = 0; i < this.files.length; i++) {
            const file = this.files[i];
            totalSize += file.size;
            
            if (file.size > MAX_FILE_SIZE) {
                hasError = true;
            }
            
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item-preview';
            if (file.size > MAX_FILE_SIZE) {
                fileItem.classList.add('error');
            }
            
            const fileIcon = getFileIcon(file.type, file.name);
            fileItem.innerHTML = `
                <div class="file-info">
                    <span class="file-icon">${fileIcon}</span>
                    <span class="file-name" title="${file.name}">${file.name}</span>
                </div>
                <div class="file-size">${formatFileSize(file.size)}</div>
            `;
            
            list.appendChild(fileItem);
        }
        
        fileList.appendChild(list);
        
        // Добавляем информацию о размере
        const sizeInfo = document.createElement('div');
        sizeInfo.className = 'total-size';
        sizeInfo.innerHTML = `<strong>Общий размер:</strong> ${formatFileSize(totalSize)}`;
        fileList.appendChild(sizeInfo);
        
        // Предупреждение если файлы слишком большие
        if (hasError) {
            const errorMsg = document.createElement('div');
            errorMsg.className = 'file-error';
            errorMsg.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Некоторые файлы превышают максимальный размер (10MB)`;
            fileList.appendChild(errorMsg);
        }
        
        preview.appendChild(fileList);
        
        // Проверяем количество файлов
        if (this.files.length > MAX_FILE_COUNT) {
            alert(`Максимальное количество файлов: ${MAX_FILE_COUNT}`);
            this.value = '';
            preview.innerHTML = '';
            uploadArea.classList.remove('has-files');
            uploadArea.querySelector('.file-upload-text p').textContent = 'Перетащите файлы сюда или нажмите для выбора';
        }
    } else {
        uploadArea.classList.remove('has-files');
        uploadArea.querySelector('.file-upload-text p').textContent = 'Перетащите файлы сюда или нажмите для выбора';
    }
});

// ФУНКЦИЯ ДЛЯ ПОЛУЧЕНИЯ ИКОНКИ ФАЙЛА
function getFileIcon(fileType, fileName) {
    if (fileType.startsWith('image/')) {
        return '<i class="fas fa-image"></i>';
    } else if (fileType === 'application/pdf') {
        return '<i class="fas fa-file-pdf"></i>';
    } else if (fileType.includes('word') || fileName.match(/\.(doc|docx)$/i)) {
        return '<i class="fas fa-file-word"></i>';
    } else if (fileType.includes('excel') || fileType.includes('spreadsheet') || fileName.match(/\.(xls|xlsx|csv)$/i)) {
        return '<i class="fas fa-file-excel"></i>';
    } else {
        return '<i class="fas fa-file"></i>';
    }
}

// ФУНКЦИЯ ДЛЯ ФОРМАТИРОВАНИЯ РАЗМЕРА ФАЙЛА
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// ФУНКЦИИ ДЛЯ РАБОТЫ С ФАЙЛАМИ
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
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function openFile(filepath) {
    window.open(filepath, '_blank');
}

// ФУНКЦИЯ ДЛЯ ЗАГРУЗКИ ЭКСПОРТИРОВАННЫХ ФАЙЛОВ
function downloadExport(reportId, fileType) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'download_export.php';
    
    const reportIdInput = document.createElement('input');
    reportIdInput.type = 'hidden';
    reportIdInput.name = 'report_id';
    reportIdInput.value = reportId;
    
    const fileTypeInput = document.createElement('input');
    fileTypeInput.type = 'hidden';
    fileTypeInput.name = 'file_type';
    fileTypeInput.value = fileType;
    
    form.appendChild(reportIdInput);
    form.appendChild(fileTypeInput);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// ФУНКЦИИ ДЛЯ УПРАВЛЕНИЯ ОТЧЕТАМИ
function viewReport(reportId) {
    window.location.href = 'view_report.php?id=' + reportId;
}

function editReport(reportId) {
    window.location.href = 'edit_report.php?id=' + reportId;
}

function deleteReport(reportId) {
    if (confirm('Вы уверены, что хотите удалить этот отчет? Все связанные файлы также будут удалены.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete_report.php';
        
        const reportIdInput = document.createElement('input');
        reportIdInput.type = 'hidden';
        reportIdInput.name = 'report_id';
        reportIdInput.value = reportId;
        
        form.appendChild(reportIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// ФУНКЦИИ ДЛЯ УПРАВЛЕНИЯ ФОРМОЙ
function resetForm() {
    if (confirm('Вы уверены, что хотите очистить форму? Все введенные данные будут потеряны.')) {
        document.getElementById('report-form').reset();
        document.getElementById('file-preview').innerHTML = '';
        document.getElementById('file-upload-area').classList.remove('has-files');
        const uploadArea = document.getElementById('file-upload-area');
        uploadArea.querySelector('.file-upload-text p').textContent = 'Перетащите файлы сюда или нажмите для выбора';
    }
}

// ФУНКЦИИ ДЛЯ ОТОБРАЖЕНИЯ СПРАВКИ
function showHelp() {
    document.getElementById('helpModal').style.display = 'flex';
}

function closeHelp() {
    document.getElementById('helpModal').style.display = 'none';
}

// ДРАГ-ЭНД-ДРОП ДЛЯ ЗАГРУЗКИ ФАЙЛОВ
const fileUploadArea = document.getElementById('file-upload-area');
const fileInput = document.getElementById('report_files');

fileUploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    fileUploadArea.classList.add('dragover');
});

fileUploadArea.addEventListener('dragleave', () => {
    fileUploadArea.classList.remove('dragover');
});

fileUploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    fileUploadArea.classList.remove('dragover');
    
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        const event = new Event('change', { bubbles: true });
        fileInput.dispatchEvent(event);
    }
});

// ЗАКРЫТИЕ МОДАЛЬНЫХ ОКОН ПО КЛИКУ НА ФОН
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
});

// ВАЛИДАЦИЯ ФОРМЫ ПЕРЕД ОТПРАВКОЙ
document.getElementById('report-form').addEventListener('submit', function(e) {
    const title = document.getElementById('title').value.trim();
    const eventId = document.getElementById('event_id').value;
    const files = document.getElementById('report_files').files;
    
    if (!title || title.length < 5) {
        e.preventDefault();
        alert('Название отчета должно содержать минимум 5 символов');
        return;
    }
    
    if (!eventId) {
        e.preventDefault();
        alert('Пожалуйста, выберите событие');
        return;
    }
    
    // Проверка размера файлов
    let totalSize = 0;
    for (let file of files) {
        totalSize += file.size;
        if (file.size > MAX_FILE_SIZE) {
            e.preventDefault();
            alert(`Файл "${file.name}" превышает максимальный размер 10MB`);
            return;
        }
    }
    
    // Показываем индикатор загрузки
    const submitBtn = document.getElementById('submit-btn');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Обработка...';
    submitBtn.disabled = true;
});