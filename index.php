<?php
session_start();

// Если пользователь уже авторизован, перенаправляем в dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
} else {
    // Если не авторизован, показываем приветственную страницу
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Календарь напоминаний событий</title>
    <link rel="stylesheet" href="./style/style.css">
</head>
<body>
    <!-- Главная навигация -->
    <header>
        <div class="container header-content">
            <div class="logo">Календарь событий</div>
            <nav>
                <ul class="nav-tabs" style="background: transparent; padding: 0;">
                    <li><a href="#features">Возможности</a></li>
                    <li><a href="#demo">Демо</a></li>
                    <li><a href="#about">О системе</a></li>
                    <li><a href="login.php" class="btn btn-primary">Войти</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Герой секция -->
    <section class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">Умный календарь для управления событиями</h1>
            <p class="hero-subtitle">
                Организуйте встречи, задачи и напоминания в одном месте. 
                Создавайте отчеты, назначайте ответственных и никогда не пропускайте важные события.
            </p>
            <div class="hero-buttons">
                <a href="register.php" class="btn-hero btn-hero-primary">Начать бесплатно</a>
                <a href="#demo" class="btn-hero btn-hero-secondary">Посмотреть демо</a>
            </div>
        </div>
    </section>

    <!-- Секция возможностей -->
    <section id="features" class="features-section">
        <div class="container">
            <h2 style="text-align: center; font-size: 2.5rem; margin-bottom: 20px; color: var(--dark-color);">
                Мощные возможности
            </h2>
            <p style="text-align: center; font-size: 1.2rem; color: #666; max-width: 600px; margin: 0 auto 50px;">
                Все инструменты для эффективного управления событиями и задачами
            </p>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">📅</div>
                    <h3 class="feature-title">Умный календарь</h3>
                    <p class="feature-description">
                        Наглядное отображение всех событий с цветовым кодированием по категориям. 
                        Просмотр по дням, неделям и месяцам.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3 class="feature-title">Детальные отчеты</h3>
                    <p class="feature-description">
                        Создавайте отчеты по мероприятиям с прикреплением фотографий и документов. 
                        Полная история всех выполненных событий.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3 class="feature-title">Командная работа</h3>
                    <p class="feature-description">
                        Назначайте ответственных за события, отслеживайте прогресс выполнения. 
                        Идеально для рабочих групп и проектов.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🔍</div>
                    <h3 class="feature-title">Умный поиск</h3>
                    <p class="feature-description">
                        Быстрый поиск по названиям событий, фильтрация по категориям, авторам 
                        и датам. Находите нужную информацию за секунды.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🛡️</div>
                    <h3 class="feature-title">Безопасность</h3>
                    <p class="feature-description">
                        Надежная система авторизации с разделением ролей. 
                        Администраторы имеют расширенные права управления.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3 class="feature-title">Адаптивный дизайн</h3>
                    <p class="feature-description">
                        Удобный интерфейс, который отлично работает на компьютерах, 
                        планшетах и мобильных устройствах.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Демо секция -->
    <section id="demo" class="demo-section">
        <div class="container">
            <h2 style="text-align: center; font-size: 2.5rem; margin-bottom: 20px; color: var(--dark-color);">
                Как это работает
            </h2>
            <p style="text-align: center; font-size: 1.2rem; color: #666; max-width: 600px; margin: 0 auto 50px;">
                Посмотрите на основные возможности системы в действии
            </p>
            
            <div class="demo-container">
                <div class="demo-image">
                    <div>
                        <h3 style="margin-bottom: 20px;">Интерфейс календаря событий</h3>
                        <p>Наглядное отображение мероприятий с цветовыми категориями</p>
                        <p style="margin-top: 20px;">
                            <a href="register.php" class="btn btn-primary">Попробовать бесплатно</a>
                        </p>
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 50px;">
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 2rem; margin-bottom: 15px;">1</div>
                    <h4>Регистрация</h4>
                    <p>Создайте аккаунт за 2 минуты</p>
                </div>
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 2rem; margin-bottom: 15px;">2</div>
                    <h4>Добавление событий</h4>
                    <p>Создавайте события с категориями</p>
                </div>
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 2rem; margin-bottom: 15px;">3</div>
                    <h4>Назначение ответственных</h4>
                    <p>Распределяйте задачи в команде</p>
                </div>
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 2rem; margin-bottom: 15px;">4</div>
                    <h4>Создание отчетов</h4>
                    <p>Фиксируйте результаты с фото</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Статистика -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div>
                    <div class="stat-number">1000+</div>
                    <div class="stat-label">Событий создано</div>
                </div>
                <div>
                    <div class="stat-number">99%</div>
                    <div class="stat-label">Успешных мероприятий</div>
                </div>
                <div>
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Доступность системы</div>
                </div>
                <div>
                    <div class="stat-number">50+</div>
                    <div class="stat-label">Активных пользователей</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Секция "О системе" -->
    <section id="about" style="padding: 80px 0; background: white;">
        <div class="container">
            <div style="max-width: 800px; margin: 0 auto; text-align: center;">
                <h2 style="font-size: 2.5rem; margin-bottom: 30px; color: var(--dark-color);">
                    О системе "Календарь событий"
                </h2>
                <p style="font-size: 1.2rem; line-height: 1.8; color: #666; margin-bottom: 40px;">
                    Наша система разработана для эффективного управления мероприятиями, задачами и напоминаниями. 
                    Мы понимаем, как важно организовать рабочий процесс и никогда не пропускать важные события. 
                    С нашим календарем вы получаете полный контроль над расписанием и задачами вашей команды.
                </p>
                
                <div style="display: flex; gap: 30px; justify-content: center; flex-wrap: wrap; margin-top: 50px;">
                    <div style="text-align: center;">
                        <div style="font-weight: bold; font-size: 1.2rem; margin-bottom: 10px;">Для бизнеса</div>
                        <p>Организация встреч, контроль задач, отчетность</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-weight: bold; font-size: 1.2rem; margin-bottom: 10px;">Для личного использования</div>
                        <p>Планирование дня, напоминания, личные события</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-weight: bold; font-size: 1.2rem; margin-bottom: 10px;">Для команд</div>
                        <p>Совместная работа, распределение задач, общие события</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA секция -->
    <section style="background: var(--secondary-color); color: white; padding: 80px 0; text-align: center;">
        <div class="container">
            <h2 style="font-size: 2.5rem; margin-bottom: 20px;">Готовы начать?</h2>
            <p style="font-size: 1.2rem; margin-bottom: 40px; max-width: 600px; margin-left: auto; margin-right: auto;">
                Присоединяйтесь к тысячам пользователей, которые уже используют наш календарь для организации своих событий
            </p>
            <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                <a href="register.php" class="btn" style="background: white; color: var(--primary-color); padding: 15px 30px; font-size: 1.1rem;">
                    Создать аккаунт
                </a>
                <a href="login.php" class="btn" style="background: transparent; border: 2px solid white; color: white; padding: 15px 30px; font-size: 1.1rem;">
                    Войти в систему
                </a>
            </div>
        </div>
    </section>

    <!-- Футер -->
    <footer style="background: var(--dark-color); color: white; padding: 40px 0;">
        <div class="container">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 40px; margin-bottom: 30px;">
                <div>
                    <h3 style="margin-bottom: 20px;">Календарь событий</h3>
                    <p>Умная система для управления мероприятиями, задачами и напоминаниями.</p>
                </div>
                <div>
                    <h3 style="margin-bottom: 20px;">Быстрые ссылки</h3>
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin-bottom: 10px;"><a href="login.php" style="color: white; text-decoration: none;">Вход</a></li>
                        <li style="margin-bottom: 10px;"><a href="register.php" style="color: white; text-decoration: none;">Регистрация</a></li>
                        <li style="margin-bottom: 10px;"><a href="#features" style="color: white; text-decoration: none;">Возможности</a></li>
                    </ul>
                </div>
                <div>
                    <h3 style="margin-bottom: 20px;">Контакты</h3>
                    <p>Email: info@calendar.ru</p>
                    <p>Телефон: +7 (999) 123-45-67</p>
                </div>
            </div>
            <div style="text-align: center; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                <p>&copy; <?php echo date('Y'); ?> Календарь напоминаний событий. Все права защищены.</p>
            </div>
        </div>
    </footer>

    <script>
        // Плавная прокрутка для навигации
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Анимация появления элементов при скролле
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Наблюдаем за карточками возможностей
        document.querySelectorAll('.feature-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });
    </script>
</body>
</html>