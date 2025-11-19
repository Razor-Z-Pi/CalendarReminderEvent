/**
 * Инициализация календаря с событиями
 * @param {Array} eventsData - Массив событий из PHP
 */
function initializeCalendar(eventsData) {
    let currentDate = new Date();
    let currentMonth = currentDate.getMonth();
    let currentYear = currentDate.getFullYear();
    let events = eventsData || [];

    // Инициализация календаря при загрузке страницы
    document.addEventListener('DOMContentLoaded', function() {
        renderCalendar(currentMonth, currentYear);
        
        // Установка текущей даты в форме
        const eventDateInput = document.getElementById('event_date');
        if (eventDateInput) {
            eventDateInput.valueAsDate = new Date();
        }
        
        // Добавление обработчиков событий
        setupEventHandlers();
    });

    /**
     * Настройка обработчиков событий для календаря
     */
    function setupEventHandlers() {
        const prevMonthBtn = document.getElementById('prev-month');
        const nextMonthBtn = document.getElementById('next-month');
        const searchBtn = document.getElementById('search-btn');
        const eventSearch = document.getElementById('event-search');

        if (prevMonthBtn) {
            prevMonthBtn.addEventListener('click', prevMonth);
        }

        if (nextMonthBtn) {
            nextMonthBtn.addEventListener('click', nextMonth);
        }

        if (searchBtn && eventSearch) {
            searchBtn.addEventListener('click', searchEvents);
            eventSearch.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchEvents();
                }
            });
        }

        // Обработка формы быстрого добавления события
        const quickEventForm = document.getElementById('quick-event-form');
        if (quickEventForm) {
            quickEventForm.addEventListener('submit', function(e) {
                // Форма будет отправлена обычным способом на PHP
                showNotification('Событие добавляется...', 'success');
            });
        }
    }

    /**
     * Отрисовка календаря для указанного месяца и года
     * @param {number} month - Месяц (0-11)
     * @param {number} year - Год
     */
    function renderCalendar(month, year) {
        const calendarGrid = document.getElementById('calendar-grid');
        const currentMonthElement = document.getElementById('current-month');

        // Очистка календаря
        if (calendarGrid) {
            calendarGrid.innerHTML = '';
        }

        // Установка текста текущего месяца
        const monthNames = [
            "Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", 
            "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"
        ];
        
        if (currentMonthElement) {
            currentMonthElement.textContent = `${monthNames[month]} ${year}`;
        }

        // Создание заголовков дней недели
        const dayNames = ["Пн", "Вт", "Ср", "Чт", "Пт", "Сб", "Вс"];
        dayNames.forEach(day => {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day-header';
            dayElement.textContent = day;
            if (calendarGrid) {
                calendarGrid.appendChild(dayElement);
            }
        });

        // Получение первого дня месяца и количества дней
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        // Корректировка для понедельника как первого дня недели
        let startDay = firstDay === 0 ? 6 : firstDay - 1;

        // Добавление пустых ячеек для дней до первого дня месяца
        for (let i = 0; i < startDay; i++) {
            const emptyDay = document.createElement('div');
            emptyDay.className = 'calendar-day other-month';
            if (calendarGrid) {
                calendarGrid.appendChild(emptyDay);
            }
        }

        // Добавление дней месяца
        const today = new Date();
        for (let day = 1; day <= daysInMonth; day++) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            
            // Проверка, является ли этот день сегодняшним
            if (day === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                dayElement.classList.add('today');
            }
            
            // Добавление номера дня
            const dayNumber = document.createElement('div');
            dayNumber.style.fontWeight = 'bold';
            dayNumber.style.marginBottom = '5px';
            dayNumber.textContent = day;
            dayElement.appendChild(dayNumber);
            
            // Добавление событий для этого дня
            const dateStr = `${year}-${(month + 1).toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
            const dayEvents = events.filter(event => event.event_date === dateStr);
            
            dayEvents.forEach(event => {
                const eventElement = document.createElement('div');
                eventElement.className = 'event-item';
                eventElement.style.borderLeft = `3px solid ${event.category_color || '#667eea'}`;
                eventElement.innerHTML = `
                    <span class="event-indicator"></span>
                    ${event.title}
                `;
                eventElement.title = `${event.title} (${event.event_type})`;
                
                // Добавление обработчика клика по событию
                eventElement.addEventListener('click', function(e) {
                    e.stopPropagation();
                    showEventDetails(event);
                });
                
                dayElement.appendChild(eventElement);
            });

            // Добавление обработчика клика по дню
            dayElement.addEventListener('click', function() {
                showDayEvents(day, month, year, dayEvents);
            });

            if (calendarGrid) {
                calendarGrid.appendChild(dayElement);
            }
        }
    }

    /**
     * Переход к предыдущему месяцу
     */
    function prevMonth() {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        renderCalendar(currentMonth, currentYear);
    }

    /**
     * Переход к следующему месяцу
     */
    function nextMonth() {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        renderCalendar(currentMonth, currentYear);
    }

    /**
     * Поиск событий по названию
     */
    function searchEvents() {
        const searchTerm = document.getElementById('event-search').value.toLowerCase().trim();
        if (searchTerm) {
            // Фильтрация событий по поисковому запросу
            const filteredEvents = events.filter(event => 
                event.title.toLowerCase().includes(searchTerm) ||
                event.description.toLowerCase().includes(searchTerm)
            );
            
            if (filteredEvents.length > 0) {
                // Подсветка найденных событий в календаре
                highlightEvents(filteredEvents);
                showNotification(`Найдено событий: ${filteredEvents.length}`, 'success');
            } else {
                showNotification('События не найдены', 'error');
            }
        } else {
            showNotification('Введите поисковый запрос', 'error');
        }
    }

    /**
     * Подсветка найденных событий в календаре
     * @param {Array} filteredEvents - Отфильтрованные события
     */
    function highlightEvents(filteredEvents) {
        // Сброс предыдущей подсветки
        const previousHighlights = document.querySelectorAll('.event-item.highlighted');
        previousHighlights.forEach(item => {
            item.classList.remove('highlighted');
        });

        // Подсветка найденных событий
        filteredEvents.forEach(event => {
            const eventDate = new Date(event.event_date);
            const eventElements = document.querySelectorAll('.event-item');
            eventElements.forEach(element => {
                if (element.textContent.includes(event.title)) {
                    element.classList.add('highlighted');
                    element.style.animation = 'pulse 2s infinite';
                }
            });
        });
    }

    /**
     * Показать детали события
     * @param {Object} event - Объект события
     */
    function showEventDetails(event) {
        const modalHtml = `
            <div class="modal-overlay" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10000;
            ">
                <div class="modal-content" style="
                    background: #2d3748;
                    padding: 2rem;
                    border-radius: var(--border-radius-lg);
                    max-width: 500px;
                    width: 90%;
                    max-height: 80vh;
                    overflow-y: auto;
                ">
                    <h3 style="margin-bottom: 1rem; color: var(--dark-color);">${event.title}</h3>
                    <div style="margin-bottom: 1rem;">
                        <strong>Дата:</strong> ${new Date(event.event_date).toLocaleDateString('ru-RU')}
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <strong>Категория:</strong> 
                        <span class="event-category" style="background-color: ${event.category_color}20; color: ${event.category_color};">
                            ${event.category_name}
                        </span>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <strong>Тип:</strong> ${event.event_type}
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <strong>Ответственные:</strong> ${event.responsible_users}
                    </div>
                    ${event.description ? `
                    <div style="margin-bottom: 1rem;">
                        <strong>Описание:</strong>
                        <p>${event.description}</p>
                    </div>
                    ` : ''}
                    <button onclick="this.closest('.modal-overlay').remove()" 
                            class="btn btn-primary" 
                            style="margin-top: 1rem;">
                        Закрыть
                    </button>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    /**
     * Показать события дня
     * @param {number} day - День
     * @param {number} month - Месяц
     * @param {number} year - Год
     * @param {Array} dayEvents - События дня
     */
    function showDayEvents(day, month, year, dayEvents) {
        const dateStr = `${day.toString().padStart(2, '0')}.${(month + 1).toString().padStart(2, '0')}.${year}`;
        
        if (dayEvents.length > 0) {
            let eventsHtml = '<h4 style="margin-bottom: 1rem;">События на ' + dateStr + ':</h4>';
            dayEvents.forEach(event => {
                eventsHtml += `
                    <div class="event-item" style="margin-bottom: 0.5rem; cursor: pointer;" 
                         onclick="showEventDetails(${JSON.stringify(event).replace(/"/g, '&quot;')})">
                        <strong>${event.title}</strong> (${event.event_type})
                    </div>
                `;
            });
            
            showNotification(eventsHtml, 'success');
        } else {
            showNotification(`На ${dateStr} событий нет`, 'info');
        }
    }

    /**
     * Показать уведомление
     * @param {string} message - Сообщение
     * @param {string} type - Тип уведомления (success, error, info)
     */
    window.showNotification = function(message, type = 'success') {
        // Удаление предыдущих уведомлений
        const existingNotifications = document.querySelectorAll('.custom-notification');
        existingNotifications.forEach(notification => {
            notification.remove();
        });

        const notification = document.createElement('div');
        notification.className = `custom-notification alert alert-${type}`;
        notification.innerHTML = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
            animation: slideInRight 0.5s ease;
        `;

        document.body.appendChild(notification);

        // Автоматическое скрытие через 5 секунд
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.animation = 'slideInRight 0.5s ease reverse';
                setTimeout(() => notification.remove(), 500);
            }
        }, 5000);
    };

    // Сделаем функции глобальными для использования в HTML
    window.prevMonth = prevMonth;
    window.nextMonth = nextMonth;
    window.searchEvents = searchEvents;
    window.initializeCalendar = initializeCalendar;
}

/**
 * Дополнительные утилиты для улучшения UX
 */
document.addEventListener('DOMContentLoaded', function() {
    // Инициализация плавающих labels
    initFloatingLabels();
    
    // Инициализация обработчиков форм
    initFormHandlers();
    
    // Добавление обработчиков для адаптивности
    initResponsiveHandlers();
});


/**
 * Инициализация обработчиков форм
 */
function initFormHandlers() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            
            if (submitBtn) {
                // Добавление состояния загрузки
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="loading"></span> Обработка...';
                
                // В реальном приложении здесь будет AJAX запрос
                setTimeout(() => {
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 2000);
            }
        });
    });
}

/**
 * Инициализация обработчиков для адаптивности
 */
function initResponsiveHandlers() {
    // Обработка изменения размера окна
    window.addEventListener('resize', function() {
        // Перерисовка календаря при изменении размера окна
        const calendarGrid = document.getElementById('calendar-grid');
        if (calendarGrid && calendarGrid.children.length > 0) {
            // Можно добавить дополнительную логику для адаптации
        }
    });
    
    // Обработка касаний для мобильных устройств
    if ('ontouchstart' in window) {
        document.addEventListener('touchstart', function() {}, {passive: true});
    }
}

/**
 * Вспомогательная функция для форматирования даты
 */
function formatDate(date) {
    return new Date(date).toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}