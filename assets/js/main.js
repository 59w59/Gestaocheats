
(function() {
    'use strict';
    
    // Objeto principal de funcionalidades
    const App = {
        /**
         * Inicialização do aplicativo
         */
        init: function() {
            this.setupEventListeners();
            this.setupScrollEffects();
            this.setupFaqAccordion();
            this.checkVisibleElements();
        },
        
        /**
         * Configurar listeners de eventos
         */
        setupEventListeners: function() {
            // Menu mobile toggle
            const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
            const mobileMenu = document.querySelector('.mobile-menu');
            
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function() {
                    this.classList.toggle('active');
                    mobileMenu.classList.toggle('active');
                    document.body.classList.toggle('menu-open');
                });
                
                // Fechar menu ao clicar em links internos
                const mobileLinks = document.querySelectorAll('.mobile-menu a[href^="#"]');
                mobileLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        mobileMenuToggle.classList.remove('active');
                        mobileMenu.classList.remove('active');
                        document.body.classList.remove('menu-open');
                    });
                });
            }
            
            // Botão voltar ao topo
            const backToTopButton = document.querySelector('.back-to-top');
            if (backToTopButton) {
                backToTopButton.addEventListener('click', function() {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }
        },

        /**
         * Configurar efeitos de scroll
         */
        setupScrollEffects: function () {
            const header = document.querySelector('.header');
            const backToTopButton = document.querySelector('.back-to-top');

            // Verificar inicialmente se o botão deve ser mostrado
            if (backToTopButton) {
                if (window.scrollY > 300) {
                    backToTopButton.classList.add('show');
                } else {
                    backToTopButton.classList.remove('show');
                }
            }

            window.addEventListener('scroll', function () {
                // Header fixo com mudança de estilo
                if (header) {
                    if (window.scrollY > 100) {
                        header.classList.add('scrolled');
                    } else {
                        header.classList.remove('scrolled');
                    }
                }

                // Botão voltar ao topo
                if (backToTopButton) {
                    if (window.scrollY > 300) {
                        backToTopButton.classList.add('show');
                    } else {
                        backToTopButton.classList.remove('show');
                    }
                }

                // Verificar elementos com animação
                App.checkVisibleElements();
            });
        },
        
        /**
         * Configurar accordion da seção FAQ
         */
        setupFaqAccordion: function() {
            const faqQuestions = document.querySelectorAll('.faq-question');
            
            faqQuestions.forEach(question => {
                question.addEventListener('click', () => {
                    const faqItem = question.parentElement;
                    const icon = question.querySelector('.faq-toggle i');
                    
                    // Verifica se este item está ativo
                    const isActive = faqItem.classList.contains('active');
                    
                    // Fecha todos os itens
                    faqQuestions.forEach(q => {
                        const qItem = q.parentElement;
                        const qIcon = q.querySelector('.faq-toggle i');
                        
                        if (qItem !== faqItem) {
                            qItem.classList.remove('active');
                            if (qIcon) qIcon.style.transform = 'rotate(0deg)';
                        }
                    });
                    
                    // Abre ou fecha o item clicado
                    if (isActive) {
                        faqItem.classList.remove('active');
                        if (icon) icon.style.transform = 'rotate(0deg)';
                    } else {
                        faqItem.classList.add('active');
                        if (icon) icon.style.transform = 'rotate(180deg)';
                    }
                });
            });
        },
        
        /**
         * Verificar elementos para aplicar animações ao rolar
         */
        checkVisibleElements: function() {
            const fadeElements = document.querySelectorAll('.fade-in:not(.visible)');
            
            fadeElements.forEach(element => {
                const elementPosition = element.getBoundingClientRect();
                const windowHeight = window.innerHeight;
                
                // Se o elemento estiver visível na janela
                if (elementPosition.top < windowHeight - 100) {
                    element.classList.add('visible');
                }
            });
        }
    };
    
    // Inicializar aplicativo quando o DOM estiver pronto
    document.addEventListener('DOMContentLoaded', function() {
        App.init();
    });
})();