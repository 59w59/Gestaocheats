/**
 * Main.js - Funções principais do sistema
 * Arquivo principal de JavaScript para o site
 */

document.addEventListener('DOMContentLoaded', function() {
    // Inicialização do sistema
    initPasswordToggle();
    initAlertDismiss();
    enhanceFormInteractions();
    setupAccessibilityFeatures();

    // Se estiver na página de autenticação, adicionar efeitos específicos
    if (document.querySelector('.auth-page')) {
        enhanceAuthPage();
    }
});

/**
 * Inicializa o toggle de visualização de senha
 */
function initPasswordToggle() {
    const passwordFields = document.querySelectorAll('input[type="password"]');

    passwordFields.forEach(field => {
        // Criar o botão de toggle apenas se não existir
        if (!field.parentElement.querySelector('.password-toggle')) {
            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'password-toggle';
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
            toggleBtn.setAttribute('aria-label', 'Mostrar senha');
            toggleBtn.style.position = 'absolute';
            toggleBtn.style.right = '12px';
            toggleBtn.style.top = '50%';
            toggleBtn.style.transform = 'translateY(-50%)';
            toggleBtn.style.background = 'transparent';
            toggleBtn.style.border = 'none';
            toggleBtn.style.color = 'var(--primary)';
            toggleBtn.style.cursor = 'pointer';
            toggleBtn.style.fontSize = '1rem';
            toggleBtn.style.zIndex = '2';

            // Posicionar relativamente se estiver dentro de um input-group
            const inputParent = field.parentElement;
            if (!inputParent.style.position || inputParent.style.position === 'static') {
                inputParent.style.position = 'relative';
            }

            // Adicionar depois do campo
            field.parentNode.appendChild(toggleBtn);

            // Adicionar espaço à direita no campo para não sobrepor o ícone
            field.style.paddingRight = '40px';

            // Adicionar evento de clique
            toggleBtn.addEventListener('click', function() {
                const isPassword = field.type === 'password';
                field.type = isPassword ? 'text' : 'password';
                toggleBtn.innerHTML = isPassword ?
                    '<i class="fas fa-eye-slash"></i>' :
                    '<i class="fas fa-eye"></i>';
                toggleBtn.setAttribute('aria-label',
                    isPassword ? 'Esconder senha' : 'Mostrar senha');
            });
        }
    });
}

/**
 * Inicializa a funcionalidade de fechar alertas
 */
function initAlertDismiss() {
    const alerts = document.querySelectorAll('.alert');

    alerts.forEach(alert => {
        // Não adicionar botão de fechar se o alerta for temporário
        if (alert.classList.contains('alert-auto-dismiss')) {
            // Configurar timeout para remover automaticamente
            setTimeout(() => {
                fadeOutAndRemove(alert);
            }, 5000); // 5 segundos
            return;
        }

        // Adicionar botão de fechar se ainda não existir
        if (!alert.querySelector('.alert-close')) {
            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'alert-close';
            closeBtn.innerHTML = '&times;';
            closeBtn.setAttribute('aria-label', 'Fechar');
            closeBtn.style.position = 'absolute';
            closeBtn.style.right = '10px';
            closeBtn.style.top = '50%';
            closeBtn.style.transform = 'translateY(-50%)';
            closeBtn.style.background = 'transparent';
            closeBtn.style.border = 'none';
            closeBtn.style.color = 'inherit';
            closeBtn.style.fontSize = '1.2rem';
            closeBtn.style.opacity = '0.7';
            closeBtn.style.cursor = 'pointer';
            closeBtn.style.padding = '0 10px';

            // Posicionar relativamente
            if (!alert.style.position || alert.style.position === 'static') {
                alert.style.position = 'relative';
            }

            closeBtn.addEventListener('click', function() {
                fadeOutAndRemove(alert);
            });

            alert.appendChild(closeBtn);
        }
    });
}

/**
 * Anima a remoção de um elemento com fade out
 */
function fadeOutAndRemove(element) {
    element.style.transition = 'opacity 0.3s ease-out';
    element.style.opacity = '0';

    setTimeout(() => {
        if (element.parentNode) {
            element.parentNode.removeChild(element);
        }
    }, 300);
}

/**
 * Melhora interações de formulário
 */
function enhanceFormInteractions() {
    // Adicionar efeitos de foco para campos de formulário
    const formControls = document.querySelectorAll('.form-control');

    formControls.forEach(control => {
        // Adicionar classe quando o campo recebe foco
        control.addEventListener('focus', function() {
            const inputGroup = this.closest('.input-group');
            if (inputGroup) {
                inputGroup.classList.add('focused');
            }
        });

        // Remover classe quando o campo perde o foco
        control.addEventListener('blur', function() {
            const inputGroup = this.closest('.input-group');
            if (inputGroup) {
                inputGroup.classList.remove('focused');
            }
        });

        // Verificar se o campo tem valor para manter label acima
        control.addEventListener('input', function() {
            const inputGroup = this.closest('.input-group');
            if (inputGroup) {
                if (this.value.trim() !== '') {
                    inputGroup.classList.add('has-value');
                } else {
                    inputGroup.classList.remove('has-value');
                }
            }
        });

        // Verificar valor inicial
        if (control.value.trim() !== '') {
            const inputGroup = control.closest('.input-group');
            if (inputGroup) {
                inputGroup.classList.add('has-value');
            }
        }
    });

    // Melhorar interação de botões
    const buttons = document.querySelectorAll('.btn, button[type="submit"]');

    buttons.forEach(button => {
        button.addEventListener('click', function() {
            // Adicionar efeito de ondulação ao clicar
            if (!this.querySelector('.ripple-effect')) {
                this.style.position = 'relative';
                this.style.overflow = 'hidden';

                const ripple = document.createElement('span');
                ripple.className = 'ripple-effect';
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.backgroundColor = 'rgba(255, 255, 255, 0.3)';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple 0.6s linear';
                ripple.style.pointerEvents = 'none';

                this.appendChild(ripple);

                // Remover após a animação
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            }
        });
    });

    // Adicionar estilo CSS para efeito de ondulação se ainda não existir
    if (!document.getElementById('ripple-style')) {
        const style = document.createElement('style');
        style.id = 'ripple-style';
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            .ripple-effect {
                width: 100px;
                height: 100px;
                top: calc(50% - 50px);
                left: calc(50% - 50px);
                opacity: 1;
            }
        `;
        document.head.appendChild(style);
    }
}

/**
 * Configurar recursos de acessibilidade
 */
function setupAccessibilityFeatures() {
    // Adicionar atributos ARIA onde necessário
    const requiredFields = document.querySelectorAll('input[required], select[required], textarea[required]');

    requiredFields.forEach(field => {
        field.setAttribute('aria-required', 'true');
    });

    // Melhorar acessibilidade de links
    const links = document.querySelectorAll('a');

    links.forEach(link => {
        // Adicionar atributo para links externos
        if (link.hostname !== window.location.hostname &&
            !link.hasAttribute('rel')) {
            link.setAttribute('rel', 'noopener noreferrer');
        }

        // Melhorar acessibilidade para links sem texto
        if (!link.textContent.trim() && !link.getAttribute('aria-label')) {
            const linkImage = link.querySelector('img');
            if (linkImage && linkImage.getAttribute('alt')) {
                link.setAttribute('aria-label', linkImage.getAttribute('alt'));
            } else {
                link.setAttribute('aria-label', 'Link');
            }
        }
    });
}

/**
 * Melhorias específicas para a página de autenticação
 */
function enhanceAuthPage() {
    // Detectar se já existe script de partículas e inserir se necessário
    let particlesScript = document.querySelector('script[src*="particles.js"]');

    if (!particlesScript) {
        particlesScript = document.createElement('script');
        particlesScript.src = '../assets/js/particles.js';
        document.body.appendChild(particlesScript);
    }

    // Adicionar efeitos de transição de formulário
    const formGroups = document.querySelectorAll('.auth-form .form-group');

    formGroups.forEach((group, index) => {
        group.style.opacity = '0';
        group.style.transform = 'translateY(10px)';
        group.style.transition = 'opacity 0.3s ease, transform 0.3s ease';

        // Atrasar a animação com base no índice
        setTimeout(() => {
            group.style.opacity = '1';
            group.style.transform = 'translateY(0)';
        }, 100 + (index * 50));
    });

    // Melhorar botão de envio
    const submitBtn = document.querySelector('.auth-form .btn-block');
    if (submitBtn) {
        submitBtn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = 'var(--shadow-primary)';
        });

        submitBtn.addEventListener('mouseleave', function() {
            this.style.transform = '';
            this.style.boxShadow = '';
        });
    }
}