// js/users.js

(function(){
    const modal = document.getElementById('userModal');
    const closeBtn = modal.querySelector('.modal__close');
    const btnClose = document.getElementById('btnClose');
    const form = document.getElementById('editForm');
    const avatarImg = document.getElementById('f_avatar_img');
    const avatarPH = document.getElementById('f_avatar_placeholder');

    function openModal(){
        modal.classList.add('open');
        modal.setAttribute('aria-hidden','false');
    }

    function closeModal(){
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden','true');
        form.reset();
        avatarImg.style.display = 'none';
        avatarPH.style.display = 'inline';
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.js-edit');
        if (btn) {
            const tr = btn.closest('tr');
            const data = JSON.parse(tr.getAttribute('data-user'));
            const isSelf = tr.getAttribute('data-self') === '1';
            const canManage = tr.getAttribute('data-manage') === '1';

            document.getElementById('f_id').value = data.id;
            document.getElementById('f_username').value = data.username ?? '';
            document.getElementById('f_role').value = data.role ?? 'user';
            document.getElementById('f_position').value = data.position ?? '';
            document.getElementById('f_last_name').value = data.last_name ?? '';
            document.getElementById('f_first_name').value = data.first_name ?? '';
            document.getElementById('f_middle_name').value = data.middle_name ?? '';
            document.getElementById('f_phone').value = data.phone ?? '';
            document.getElementById('f_email').value = data.email ?? '';
            document.getElementById('f_password').value = '';

            const roleSel = document.getElementById('f_role');
            if (isSelf || !canManage) {
                roleSel.disabled = true;
                roleSel.title = isSelf
                    ? 'Нельзя менять роль самому себе'
                    : 'Недостаточно прав для смены роли';
            } else {
                roleSel.disabled = false;
                roleSel.title = '';
            }

            if (data.avatar) {
                avatarImg.src = '/MySite/avatar-uploads/' + data.avatar.split('/').pop();
                avatarImg.style.display = 'block';
                avatarPH.style.display = 'none';
            } else {
                avatarImg.style.display = 'none';
                avatarPH.style.display = 'inline';
            }

            openModal();
        }
    });

    closeBtn.addEventListener('click', closeModal);
    btnClose.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    document.getElementById('f_avatar').addEventListener('change', function(){
        const file = this.files[0];
        if (file) {
            const url = URL.createObjectURL(file);
            avatarImg.src = url;
            avatarImg.style.display = 'block';
            avatarPH.style.display = 'none';
        }
    });
})();
