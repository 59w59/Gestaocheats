/**
 * Downloads functionality for the dashboard
 */

// Main object for download functionality
const DownloadManager = {
    /**
     * Initialize download listeners
     */
    init: function() {
        this.setupDownloadButtons();
        this.setupDownloadFilters();
    },

    /**
     * Setup event listeners for download buttons
     */
    setupDownloadButtons: function() {
        const downloadButtons = document.querySelectorAll('.download-button');
        
        downloadButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                // Para propósitos de debug
                console.log('Download button clicked', this.href);
                
                const cheatId = this.getAttribute('data-cheat-id');
                const cheatName = this.getAttribute('data-cheat-name');
                
                // Show confirmation dialog
                if (confirm(`Você está prestes a baixar ${cheatName}. Continuar?`)) {
                    // Track event (for analytics, if implemented)
                    if (typeof gtag === 'function') {
                        gtag('event', 'cheat_download', {
                            'cheat_id': cheatId,
                            'cheat_name': cheatName
                        });
                    }
                    
                    // Deixar o evento prosseguir sem preventDefault()
                    return true;
                } else {
                    // Evitar o navegador de seguir o link se o usuário cancelar
                    e.preventDefault();
                    return false;
                }
            });
        });
    },

    /**
     * Setup filters for download history
     */
    setupDownloadFilters: function() {
        const gameFilter = document.getElementById('game');
        const cheatFilter = document.getElementById('cheat');
        
        // If we have a game filter dropdown, listen for changes
        if (gameFilter) {
            gameFilter.addEventListener('change', function() {
                const gameId = this.value;
                
                // Reset cheat filter
                if (cheatFilter) {
                    cheatFilter.innerHTML = '<option value="0">Todos os cheats</option>';
                    cheatFilter.disabled = true;
                    
                    if (gameId > 0) {
                        // Get cheats for this game
                        fetch(`get_game_cheats.php?id=${gameId}`)
                            .then(response => response.json())
                            .then(cheats => {
                                // Enable and populate cheat filter
                                cheats.forEach(cheat => {
                                    const option = document.createElement('option');
                                    option.value = cheat.id;
                                    option.textContent = cheat.name;
                                    cheatFilter.appendChild(option);
                                });
                                cheatFilter.disabled = false;
                            })
                            .catch(error => {
                                console.error('Erro ao carregar cheats:', error);
                            });
                    }
                }
            });
        }

        // Date filter change event (if exists)
        const dateFilter = document.getElementById('date');
        if (dateFilter) {
            dateFilter.addEventListener('change', function() {
                document.querySelector('form.download-filters').submit();
            });
        }

        // Sort filter change event (if exists)
        const sortFilter = document.getElementById('sort');
        if (sortFilter) {
            sortFilter.addEventListener('change', function() {
                document.querySelector('form.download-filters').submit();
            });
        }
    },

    /**
     * Show download details in a modal
     */
    showDetails: function(downloadId) {
        fetch(`get_download_details.php?id=${downloadId}`)
            .then(response => response.json())
            .then(data => {
                // If you have a modal system, show details in it
                if (typeof showModal === 'function') {
                    showModal('Download Details', `
                        <div class="download-details">
                            <p><strong>Cheat:</strong> ${data.cheat_name} v${data.version}</p>
                            <p><strong>Game:</strong> ${data.game_name}</p>
                            <p><strong>Data:</strong> ${data.download_date}</p>
                            <p><strong>IP:</strong> ${data.ip_address}</p>
                            <p><strong>User Agent:</strong> ${data.user_agent}</p>
                        </div>
                    `);
                } else {
                    // Fallback to alert if no modal system
                    alert(`Download: ${data.cheat_name} v${data.version} (${data.game_name})\nData: ${data.download_date}`);
                }
            })
            .catch(error => {
                console.error('Erro ao carregar detalhes:', error);
                alert('Erro ao carregar detalhes do download');
            });
    }
};

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    DownloadManager.init();
});