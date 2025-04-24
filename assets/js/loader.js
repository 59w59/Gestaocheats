/**
 * Sistema de carregamento da página
 * Otimizado para garantir que a tela de carregamento seja removida adequadamente
 */
(function() {
    // Função principal para lidar com a tela de carregamento
    function hideLoading() {
        const loading = document.querySelector('.loading');
        if (!loading) return;
        
        // Aplicar classe de transição
        loading.classList.add('hide');
        
        // Remover completamente após a animação terminar
        setTimeout(function() {
            loading.style.display = 'none';
        }, 500);
    }
    
    // Método 1: DOM Content Loaded (executa assim que o DOM estiver pronto)
    document.addEventListener('DOMContentLoaded', hideLoading);
    
    // Método 2: Window Load (backup caso o DOMContentLoaded falhe)
    window.addEventListener('load', hideLoading);
    
    // Método 3: Fallback de segurança (garante que a tela será removida mesmo com erros)
    setTimeout(hideLoading, 3000);
    
    // Método 4: Detectar se a página ficou inativa por muito tempo
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            hideLoading();
        }
    });
})();