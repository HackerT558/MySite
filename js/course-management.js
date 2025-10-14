// js/course-management.js

document.addEventListener('DOMContentLoaded', function() {
    // Инициализация управления курсами
    initializeCourseManagement();
});

function initializeCourseManagement() {
    // Активируем вкладку из URL параметров
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab') || 'info';
    
    if (document.querySelector('.tabs-nav')) {
        switchTab(activeTab);
    }
    
    // Автоматическое скрытие уведомлений
    setTimeout(() => {
        const notices = document.querySelectorAll('.notice, .error');
        notices.forEach(notice => {
            notice.style.opacity = '0';
            setTimeout(() => notice.remove(), 300);
        });
    }, 5000);
    
    // Инициализация автосохранения контента
    initAutoSave();
}

// Переключение вкладок
function switchTab(tabName) {
    // Обновляем URL без перезагрузки
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.replaceState({}, '', url);
    
    // Скрываем все вкладки
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Убираем активный класс со всех кнопок
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Показываем активную вкладку
    const activeContent = document.getElementById(`tab-${tabName}`);
    if (activeContent) {
        activeContent.classList.add('active');
    }
    
    // Активируем соответствующую кнопку
    const activeBtn = document.querySelector(`.tab-btn[onclick*="${tabName}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');
    }
}

// Редактирование урока
function editLesson(lessonId) {
    const lessonItem = document.getElementById(`lesson-${lessonId}`);
    if (lessonItem) {
        lessonItem.querySelector('.lesson-display').style.display = 'none';
        lessonItem.querySelector('.lesson-edit').style.display = 'block';
        
        // Фокус на первое поле
        const firstInput = lessonItem.querySelector('.lesson-edit input[type="text"]');
        if (firstInput) {
            firstInput.focus();
        }
    }
}

// Отмена редактирования урока
function cancelEditLesson(lessonId) {
    const lessonItem = document.getElementById(`lesson-${lessonId}`);
    if (lessonItem) {
        lessonItem.querySelector('.lesson-display').style.display = 'block';
        lessonItem.querySelector('.lesson-edit').style.display = 'none';
    }
}

// Редактирование вопроса
function editQuestion(questionId) {
    const questionItem = document.getElementById(`question-${questionId}`);
    if (questionItem) {
        questionItem.querySelector('.question-display').style.display = 'none';
        questionItem.querySelector('.question-edit').style.display = 'block';
        
        // Фокус на текстовое поле вопроса
        const questionField = questionItem.querySelector('.question-edit textarea[name="question_text"]');
        if (questionField) {
            questionField.focus();
        }
    }
}

// Отмена редактирования вопроса
function cancelEditQuestion(questionId) {
    const questionItem = document.getElementById(`question-${questionId}`);
    if (questionItem) {
        questionItem.querySelector('.question-display').style.display = 'block';
        questionItem.querySelector('.question-edit').style.display = 'none';
    }
}

// Автосохранение контента в localStorage
function initAutoSave() {
    const textareas = document.querySelectorAll('textarea');
    const inputs = document.querySelectorAll('input[type="text"]');
    
    [...textareas, ...inputs].forEach(field => {
        const key = `autosave_${field.name || field.id}`;
        
        // Загружаем сохраненное значение
        const savedValue = localStorage.getItem(key);
        if (savedValue && !field.value) {
            field.value = savedValue;
            field.style.backgroundColor = '#fff8e1'; // Индикатор автосохранения
            
            setTimeout(() => {
                field.style.backgroundColor = '';
            }, 2000);
        }
        
        // Сохраняем при вводе
        field.addEventListener('input', debounce(() => {
            if (field.value.trim()) {
                localStorage.setItem(key, field.value);
                showAutoSaveIndicator(field);
            }
        }, 1000));
        
        // Очищаем после успешной отправки формы
        field.closest('form')?.addEventListener('submit', () => {
            localStorage.removeItem(key);
        });
    });
}

function showAutoSaveIndicator(field) {
    // Временно меняем цвет фона для индикации автосохранения
    const originalBg = field.style.backgroundColor;
    field.style.backgroundColor = '#e8f5e8';
    
    setTimeout(() => {
        field.style.backgroundColor = originalBg;
    }, 500);
}

// Утилита debounce для задержки автосохранения
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Валидация форм
function validateLessonForm(form) {
    const title = form.querySelector('[name="lesson_title"]').value.trim();
    const content = form.querySelector('[name="lesson_content"]').value.trim();
    const order = parseInt(form.querySelector('[name="lesson_order"]').value);
    
    if (!title || !content || !order || order < 1) {
        alert('Заполните все обязательные поля урока');
        return false;
    }
    
    if (content.length < 50) {
        if (!confirm('Содержание урока довольно короткое. Продолжить?')) {
            return false;
        }
    }
    
    return true;
}

function validateQuestionForm(form) {
    const question = form.querySelector('[name="question_text"]').value.trim();
    const optionA = form.querySelector('[name="option_a"]').value.trim();
    const optionB = form.querySelector('[name="option_b"]').value.trim();
    const optionC = form.querySelector('[name="option_c"]').value.trim();
    const optionD = form.querySelector('[name="option_d"]').value.trim();
    const correctAnswer = form.querySelector('[name="correct_answer"]').value;
    
    if (!question || !optionA || !optionB || !optionC || !optionD || !correctAnswer) {
        alert('Заполните все поля вопроса');
        return false;
    }
    
    // Проверка на уникальность вариантов ответов
    const options = [optionA, optionB, optionC, optionD];
    const uniqueOptions = [...new Set(options.map(opt => opt.toLowerCase()))];
    
    if (uniqueOptions.length !== options.length) {
        alert('Варианты ответов должны быть уникальными');
        return false;
    }
    
    return true;
}

// Добавление обработчиков валидации к формам
document.addEventListener('DOMContentLoaded', function() {
    // Валидация форм уроков
    document.querySelectorAll('.lesson-form, .lesson-edit form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateLessonForm(this)) {
                e.preventDefault();
            }
        });
    });
    
    // Валидация форм вопросов
    document.querySelectorAll('.question-form, .question-edit form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateQuestionForm(this)) {
                e.preventDefault();
            }
        });
    });
});

// Предварительный просмотр урока
function previewLesson(lessonId) {
    // Можно добавить модальное окно для предпросмотра
    console.log('Preview lesson:', lessonId);
}

// Предварительный просмотр вопроса
function previewQuestion(questionId) {
    // Можно добавить модальное окно для предпросмотра вопроса
    console.log('Preview question:', questionId);
}

// Сортировка уроков drag-and-drop
function initLessonSorting() {
    const lessonsContainer = document.querySelector('.lessons-management');
    if (!lessonsContainer) return;
    
    let draggedElement = null;
    
    // Добавляем возможность перетаскивания к урокам
    document.querySelectorAll('.lesson-management-item').forEach(item => {
        item.setAttribute('draggable', 'true');
        
        item.addEventListener('dragstart', function(e) {
            draggedElement = this;
            this.style.opacity = '0.5';
        });
        
        item.addEventListener('dragend', function() {
            this.style.opacity = '';
            draggedElement = null;
        });
        
        item.addEventListener('dragover', function(e) {
            e.preventDefault();
        });
        
        item.addEventListener('drop', function(e) {
            e.preventDefault();
            
            if (draggedElement && draggedElement !== this) {
                const rect = this.getBoundingClientRect();
                const midY = rect.top + rect.height / 2;
                
                if (e.clientY < midY) {
                    this.parentNode.insertBefore(draggedElement, this);
                } else {
                    this.parentNode.insertBefore(draggedElement, this.nextSibling);
                }
                
                // Обновляем порядок уроков
                updateLessonOrder();
            }
        });
    });
}

function updateLessonOrder() {
    const lessonItems = document.querySelectorAll('.lesson-management-item');
    lessonItems.forEach((item, index) => {
        const orderInput = item.querySelector('[name="lesson_order"]');
        if (orderInput) {
            orderInput.value = index + 1;
        }
    });
    
    // Здесь можно добавить AJAX-запрос для сохранения нового порядка
    console.log('Lesson order updated');
}

// Клонирование вопроса
function cloneQuestion(questionId) {
    const questionItem = document.getElementById(`question-${questionId}`);
    if (questionItem) {
        // Получаем данные вопроса
        const questionText = questionItem.querySelector('.question-text').textContent.trim();
        const options = Array.from(questionItem.querySelectorAll('.option')).map(opt => 
            opt.textContent.replace(/^[A-D]\.\s*/, '').trim()
        );
        
        // Заполняем форму добавления вопроса
        const form = document.querySelector('.question-form');
        if (form) {
            form.querySelector('[name="question_text"]').value = questionText + ' (копия)';
            form.querySelector('[name="option_a"]').value = options[0] || '';
            form.querySelector('[name="option_b"]').value = options[1] || '';
            form.querySelector('[name="option_c"]').value = options[2] || '';
            form.querySelector('[name="option_d"]').value = options[3] || '';
            
            // Переключаемся на вкладку вопросов и скроллим к форме
            switchTab('questions');
            form.scrollIntoView({ behavior: 'smooth' });
            form.querySelector('[name="question_text"]').focus();
        }
    }
}

// Экспорт функций в глобальную область
window.switchTab = switchTab;
window.editLesson = editLesson;
window.cancelEditLesson = cancelEditLesson;
window.editQuestion = editQuestion;
window.cancelEditQuestion = cancelEditQuestion;
window.previewLesson = previewLesson;
window.previewQuestion = previewQuestion;
window.cloneQuestion = cloneQuestion;

// Инициализация сортировки при загрузке
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(initLessonSorting, 500);
});