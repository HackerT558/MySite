// dashboard/js/test.js

let currentQuestionIndex = 1;
const totalQuestions = document.querySelectorAll('.question-block').length;
const answeredQuestions = new Set(); // Отслеживаем отвеченные вопросы

/**
 * Показывает вопрос по индексу
 */
function showQuestion(index) {
    // Скрываем все вопросы
    document.querySelectorAll('.question-block').forEach(block => {
        block.style.display = 'none';
    });

    // Показываем нужный вопрос
    const questionBlock = document.querySelector(`[data-question-index="${index}"]`);
    if (questionBlock) {
        questionBlock.style.display = 'block';
        currentQuestionIndex = index;
        updateCurrentQuestion();
        updateNavigationButtons();
    }
}

/**
 * Проверяет, ответил ли пользователь на текущий вопрос
 */
function isCurrentQuestionAnswered() {
    const currentBlock = document.querySelector(`[data-question-index="${currentQuestionIndex}"]`);
    if (!currentBlock) return false;
    
    const checked = currentBlock.querySelector('input[type="radio"]:checked');
    return checked !== null;
}

/**
 * Показывает следующий вопрос (только если текущий отвечен)
 */
function showNextQuestion() {
    // Проверяем, ответил ли пользователь на текущий вопрос
    if (!isCurrentQuestionAnswered()) {
        alert('Пожалуйста, ответьте на вопрос перед тем, как перейти к следующему.');
        return;
    }

    // Добавляем текущий вопрос в отвеченные
    answeredQuestions.add(currentQuestionIndex);

    if (currentQuestionIndex < totalQuestions) {
        showQuestion(currentQuestionIndex + 1);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

/**
 * Показывает предыдущий вопрос (ЗАПРЕЩЕНО - кнопка скрыта)
 */
function showPreviousQuestion() {
    // Эта функция больше не используется - кнопка скрыта
    return;
}

/**
 * Обновляет текущий номер вопроса на панели информации
 */
function updateCurrentQuestion() {
    const currentQElement = document.getElementById('currentQuestion');
    if (currentQElement) {
        currentQElement.textContent = currentQuestionIndex;
    }
}

/**
 * Обновляет состояние кнопок навигации
 */
function updateNavigationButtons() {
    const prevBtn = document.querySelector('.nav-btn-prev');
    const nextBtn = document.querySelector('.nav-btn-next');
    const submitBtn = document.querySelector('.nav-btn-submit');

    // Всегда скрываем кнопку "Назад" - возврата нет!
    if (prevBtn) {
        prevBtn.style.display = 'none';
    }

    if (nextBtn) {
        if (currentQuestionIndex === totalQuestions) {
            nextBtn.style.display = 'none';
        } else {
            nextBtn.style.display = 'inline-flex';
        }
    }

    if (submitBtn) {
        if (currentQuestionIndex === totalQuestions) {
            submitBtn.style.display = 'inline-flex';
        } else {
            submitBtn.style.display = 'none';
        }
    }
}

/**
 * Инициализация при загрузке страницы
 */
document.addEventListener('DOMContentLoaded', function() {
    // Показываем первый вопрос
    showQuestion(1);

    // Добавляем обработчик отправки формы
    const testForm = document.getElementById('testForm');
    if (testForm) {
        testForm.addEventListener('submit', function(e) {
            // Проверяем, что последний вопрос отвечен
            if (!isCurrentQuestionAnswered()) {
                e.preventDefault();
                alert('Пожалуйста, ответьте на вопрос перед завершением теста.');
                return false;
            }

            // Показываем подтверждение
            if (!confirm('Вы уверены? После отправки теста изменить ответы будет невозможно.')) {
                e.preventDefault();
                return false;
            }
        });
    }

    // Добавляем обработчик изменения радиокнопок для отслеживания ответов
    document.querySelectorAll('input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const block = this.closest('.question-block');
            if (block) {
                const index = parseInt(block.getAttribute('data-question-index'));
                answeredQuestions.add(index);
            }
        });
    });
});
