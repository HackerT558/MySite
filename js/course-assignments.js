function updateCourseInfo() {
    const select = document.getElementById('course_id');
    const info = document.getElementById('courseInfo');
    const description = document.getElementById('courseDescription');
    const recommended = document.getElementById('recommendedUsers');
    
    if (select.value) {
        const option = select.selectedOptions[0];
        const position = option.dataset.position;
        const desc = option.dataset.description;
        
        description.innerHTML = `<p><strong>Описание:</strong> ${desc}</p>`;
        recommended.innerHTML = `<p><strong>Рекомендуется для:</strong> ${position}</p>`;
        info.style.display = 'block';
        
        // Выделяем соответствующую позицию
        highlightRecommendedPosition(position);
    } else {
        info.style.display = 'none';
        clearHighlights();
    }
}

function highlightRecommendedPosition(position) {
    clearHighlights();
    const positionGroups = document.querySelectorAll('.position-group');
    positionGroups.forEach(group => {
        const checkbox = group.querySelector('.position-checkbox');
        if (checkbox.dataset.position === position) {
            group.classList.add('recommended');
        }
    });
}

function clearHighlights() {
    document.querySelectorAll('.position-group').forEach(group => {
        group.classList.remove('recommended');
    });
}

// Обработка чекбоксов по позициям
document.querySelectorAll('.position-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const position = this.dataset.position;
        const userCheckboxes = document.querySelectorAll(`input[name="user_ids[]"][data-position="${position}"]`);
        
        userCheckboxes.forEach(userCheckbox => {
            userCheckbox.checked = this.checked;
        });
    });
});

// Обновление состояния чекбоксов позиций при изменении пользователей
document.querySelectorAll('input[name="user_ids[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const position = this.dataset.position;
        const positionCheckbox = document.querySelector(`.position-checkbox[data-position="${position}"]`);
        const userCheckboxes = document.querySelectorAll(`input[name="user_ids[]"][data-position="${position}"]`);
        const checkedUsers = document.querySelectorAll(`input[name="user_ids[]"][data-position="${position}"]:checked`);
        
        if (checkedUsers.length === 0) {
            positionCheckbox.indeterminate = false;
            positionCheckbox.checked = false;
        } else if (checkedUsers.length === userCheckboxes.length) {
            positionCheckbox.indeterminate = false;
            positionCheckbox.checked = true;
        } else {
            positionCheckbox.indeterminate = true;
        }
    });
});

function clearSelection() {
    document.querySelectorAll('input[name="user_ids[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
    document.querySelectorAll('.position-checkbox').forEach(checkbox => {
        checkbox.checked = false;
        checkbox.indeterminate = false;
    });
}

function filterAssignments() {
    const searchTerm = document.getElementById('assignmentSearch').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('#assignmentsTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const status = row.dataset.status;
        
        const matchesSearch = text.includes(searchTerm);
        const matchesStatus = !statusFilter || status === statusFilter;
        
        row.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });
}

// Автоматическое скрытие сообщений
setTimeout(function() {
    const notices = document.querySelectorAll('.notice, .error');
    notices.forEach(notice => {
        notice.style.opacity = '0';
        setTimeout(() => notice.remove(), 300);
    });
}, 5000);