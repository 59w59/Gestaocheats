function confirmDelete(id) {
    if (confirm('Tem certeza que deseja excluir este cheat? Esta ação não pode ser desfeita.')) {
        window.location.href = `cheat_delete.php?id=${id}`;
    }
}

function previewImage(input, previewElement) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const preview = document.getElementById(previewElement);
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Add to existing JavaScript in cheat_add.php
document.getElementById('image').addEventListener('change', function() {
    previewImage(this, 'imagePreview');
});

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const adminSidebar = document.querySelector('.admin-sidebar');
    
    if (sidebarToggle && adminSidebar) {
        sidebarToggle.addEventListener('click', function() {
            adminSidebar.classList.toggle('collapsed');
        });
    }

    // Sort order toggle
    const sortLinks = document.querySelectorAll('[data-sort]');
    sortLinks.forEach(link => {
        if (link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const sort = this.dataset.sort;
                const currentOrder = new URLSearchParams(window.location.search).get('order') || 'DESC';
                const newOrder = currentOrder === 'DESC' ? 'ASC' : 'DESC';
                
                const url = new URL(window.location.href);
                url.searchParams.set('sort', sort);
                url.searchParams.set('order', newOrder);
                window.location.href = url.toString();
            });
        }
    });
});