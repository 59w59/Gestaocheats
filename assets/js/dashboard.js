$(document).ready(function() {
    // Toggle user dropdown
    $('.user-info').on('click', function() {
        $('.user-dropdown').toggleClass('active');
    });

    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.user-menu').length) {
            $('.user-dropdown').removeClass('active');
        }
    });

    // Mobile navigation toggle
    $('.mobile-nav-toggle').on('click', function() {
        $('.dashboard-nav').toggleClass('active');
        $(this).toggleClass('active');
    });
    
    // Função para exibir notificações (disponível globalmente)
    window.showNotification = function(message, type = "info") {
        let bgColor = "#1a73e8"; // Padrão (info)
        
        if (type === "success") {
            bgColor = "#28a745";
        } else if (type === "error") {
            bgColor = "#dc3545";
        } else if (type === "warning") {
            bgColor = "#ffc107";
        }
        
        Toastify({
            text: message,
            duration: 3000,
            close: true,
            gravity: "top",
            position: "right",
            backgroundColor: bgColor,
            stopOnFocus: true
        }).showToast();
    };
});