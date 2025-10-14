// js/courses.js

document.addEventListener('DOMContentLoaded', function() {
    // Инициализация системы курсов
    initializeCourses();
});

function initializeCourses() {
    // Обработка клика по карточкам курсов для быстрого перехода
    const courseCards = document.querySelectorAll('.course-card');
    courseCards.forEach(card => {
        // Добавляем возможность клика по всей карточке
        card.addEventListener('click', function(e) {
            // Игнорируем клики по кнопкам и ссылкам
            if (e.target.matches('.btn, .btn *, a, a *')) {
                return;
            }
            
            const courseLink = card.querySelector('.course-actions .btn');
            if (courseLink) {
                window.location.href = courseLink.href;
            }
        });
    });
    
    // Анимация прогресс-баров
    animateProgressBars();
    
    // Инициализация уведомлений о дедлайнах
    checkDeadlines();
    
    // Инициализация счетчиков статистики
    animateStatCounters();
}

function animateProgressBars() {
    const progressBars = document.querySelectorAll('.progress-fill');
    
    // Используем Intersection Observer для анимации при появлении в viewport
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const progressFill = entry.target;
                const targetWidth = progressFill.style.width;
                
                // Сброс ширины для анимации
                progressFill.style.width = '0%';
                progressFill.style.transition = 'width 1.5s ease-out';
                
                // Запуск анимации через небольшую задержку
                setTimeout(() => {
                    progressFill.style.width = targetWidth;
                }, 100);
                
                // Удаляем наблюдение после анимации
                observer.unobserve(progressFill);
            }
        });
    }, {
        threshold: 0.1
    });
    
    progressBars.forEach(bar => observer.observe(bar));
}

function checkDeadlines() {
    const courseCards = document.querySelectorAll('.course-card');
    
    courseCards.forEach(card => {
        const deadlineElement = card.querySelector('.deadline');
        if (!deadlineElement) return;
        
        const deadlineText = deadlineElement.textContent;
        const deadlineMatch = deadlineText.match(/(\d{2})\.(\d{2})\.(\d{4})/);
        
        if (deadlineMatch) {
            const [, day, month, year] = deadlineMatch;
            const deadline = new Date(year, month - 1, day);
            const now = new Date();
            const diffDays = Math.ceil((deadline - now) / (1000 * 60 * 60 * 24));
            
            // Добавляем предупреждения о приближающихся дедлайнах
            if (diffDays <= 3 && diffDays > 0) {
                card.classList.add('deadline-warning');
                deadlineElement.style.color = '#f57c00';
                deadlineElement.style.fontWeight = '600';
                
                // Показываем уведомление для критических дедлайнов
                if (diffDays <= 1) {
                    showNotification(`Срок выполнения курса истекает через ${diffDays} день!`, 'warning');
                }
            } else if (diffDays <= 0) {
                card.classList.add('deadline-expired');
                deadlineElement.style.color = '#c62828';
                deadlineElement.style.fontWeight = '600';
                deadlineElement.innerHTML = deadlineElement.innerHTML.replace('До', 'Просрочен с');
            }
        }
    });
}

function animateStatCounters() {
    const statValues = document.querySelectorAll('.stat-value');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const statElement = entry.target;
                const finalValue = parseInt(statElement.textContent) || 0;
                
                if (finalValue > 0) {
                    animateCounter(statElement, 0, finalValue, 1500);
                }
                
                observer.unobserve(statElement);
            }
        });
    }, {
        threshold: 0.5
    });
    
    statValues.forEach(stat => {
        // Только для числовых значений
        if (/^\d+$/.test(stat.textContent.trim())) {
            observer.observe(stat);
        }
    });
}

function animateCounter(element, start, end, duration) {
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= end) {
            element.textContent = end;
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(current);
        }
    }, 16);
}

// Утилиты для работы с API курсов
const CoursesAPI = {
    // Отметить урок как пройденный
    completeLesson: async function(lessonId, timeSpent = 0) {
        try {
            const response = await fetch('../api/complete-lesson.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    lesson_id: lessonId,
                    time_spent: timeSpent
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Ошибка при завершении урока:', error);
            return { success: false, error: error.message };
        }
    },
    
    // Получить прогресс по курсу
    getCourseProgress: async function(courseId) {
        try {
            const response = await fetch(`../api/course-progress.php?course_id=${courseId}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Ошибка при получении прогресса:', error);
            return { success: false, error: error.message };
        }
    },
    
    // Сохранить результаты теста
    saveTestResults: async function(courseId, answers) {
        try {
            const response = await fetch('../api/save-test-results.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    course_id: courseId,
                    answers: answers
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Ошибка при сохранении результатов теста:', error);
            return { success: false, error: error.message };
        }
    }
};

// Функции для управления курсами (для менеджеров)
const CourseManagement = {
    // Назначить курс пользователю
    assignCourse: async function(userId, courseId, deadline = null) {
        try {
            const response = await fetch('../api/assign-course.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    course_id: courseId,
                    deadline: deadline
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Ошибка при назначении курса:', error);
            return { success: false, error: error.message };
        }
    },
    
    // Получить статистику по курсу
    getCourseStatistics: async function(courseId) {
        try {
            const response = await fetch(`../api/course-statistics.php?course_id=${courseId}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Ошибка при получении статистики:', error);
            return { success: false, error: error.message };
        }
    }
};

// Система уведомлений
function showNotification(message, type = 'info', duration = 5000) {
    // Создаем контейнер для уведомлений, если его нет
    let container = document.getElementById('notifications-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notifications-container';
        container.className = 'notifications-container';
        document.body.appendChild(container);
    }
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    // Иконки для разных типов уведомлений
    const icons = {
        info: 'ℹ️',
        success: '✅',
        warning: '⚠️',
        error: '❌'
    };
    
    notification.innerHTML = `
        <span class="notification-icon">${icons[type] || icons.info}</span>
        <span class="notification-message">${message}</span>
        <button class="notification-close" onclick="hideNotification(this.parentElement)">&times;</button>
    `;
    
    container.appendChild(notification);
    
    // Автоматическое скрытие
    setTimeout(() => {
        hideNotification(notification);
    }, duration);
    
    // Показываем уведомление с анимацией
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    return notification;
}

function hideNotification(notification) {
    notification.classList.remove('show');
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 300);
}

// Валидация форм
function validateTestForm(form) {
    const questions = form.querySelectorAll('.question-item');
    const unanswered = [];
    
    questions.forEach((question, index) => {
        const radios = question.querySelectorAll('input[type="radio"]');
        const isAnswered = Array.from(radios).some(radio => radio.checked);
        
        if (!isAnswered) {
            unanswered.push(index + 1);
            question.classList.add('unanswered');
        } else {
            question.classList.remove('unanswered');
        }
    });
    
    return unanswered;
}

// Сохранение прогресса в localStorage
function saveProgressLocally(key, data) {
    try {
        localStorage.setItem(`courses_${key}`, JSON.stringify({
            data: data,
            timestamp: Date.now()
        }));
    } catch (e) {
        console.warn('Не удалось сохранить прогресс локально:', e);
    }
}

function loadProgressLocally(key, maxAge = 24 * 60 * 60 * 1000) { // 24 часа по умолчанию
    try {
        const stored = localStorage.getItem(`courses_${key}`);
        if (!stored) return null;
        
        const parsed = JSON.parse(stored);
        if (Date.now() - parsed.timestamp > maxAge) {
            localStorage.removeItem(`courses_${key}`);
            return null;
        }
        
        return parsed.data;
    } catch (e) {
        console.warn('Не удалось загрузить прогресс:', e);
        return null;
    }
}

// Добавляем стили для уведомлений
if (!document.querySelector('#notification-styles')) {
    const styles = document.createElement('style');
    styles.id = 'notification-styles';
    styles.textContent = `
        .notifications-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            pointer-events: none;
        }
        
        .notification {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 8px;
            min-width: 300px;
            max-width: 500px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.15);
            transform: translateX(calc(100% + 20px));
            transition: transform 0.3s ease;
            pointer-events: auto;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification-info {
            border-left: 4px solid #17a2b8;
        }
        
        .notification-success {
            border-left: 4px solid #28a745;
        }
        
        .notification-warning {
            border-left: 4px solid #ffc107;
        }
        
        .notification-error {
            border-left: 4px solid #dc3545;
        }
        
        .notification-icon {
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .notification-message {
            flex: 1;
            color: #333;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .notification-close {
            background: none;
            border: none;
            font-size: 18px;
            color: #666;
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .notification-close:hover {
            color: #333;
        }
        
        .question-item.unanswered {
            border-color: #dc3545 !important;
            background: #fff8f8 !important;
        }
        
        /* Deadline warnings */
        .course-card.deadline-warning {
            border-left: 4px solid #ffc107;
        }
        
        .course-card.deadline-expired {
            border-left: 4px solid #dc3545;
            opacity: 0.8;
        }
    `;
    document.head.appendChild(styles);
}

// Экспорт для использования в других скриптах
window.CoursesAPI = CoursesAPI;
window.CourseManagement = CourseManagement;
window.showNotification = showNotification;
window.hideNotification = hideNotification;
window.validateTestForm = validateTestForm;