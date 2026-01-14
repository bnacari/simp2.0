// ================================
// CONFIGURAÇÕES GLOBAIS
// ================================
const CONFIG = {
    emailPattern: /@cesan\.com\.br/i,
    minPasswordLength: 6,
    validationDelay: 500,
    animationDuration: 300,
    useAjaxLogin: true // Mude para false se quiser usar submit tradicional
};

// ================================
// GERENCIAMENTO DO MODAL
// ================================
class Modal {
    constructor(overlayId) {
        this.overlay = document.getElementById(overlayId);
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Fechar ao clicar no overlay
        this.overlay?.addEventListener('click', (e) => {
            if (e.target === this.overlay) {
                this.close();
            }
        });

        // Fechar com ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen()) {
                this.close();
            }
        });
    }

    open() {
        this.overlay?.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Focus no primeiro input
        const firstInput = this.overlay?.querySelector('input');
        setTimeout(() => firstInput?.focus(), CONFIG.animationDuration);
    }

    close() {
        this.overlay?.classList.remove('active');
        document.body.style.overflow = '';
        
        // Limpar formulário
        const form = this.overlay?.querySelector('form');
        if (form) {
            form.reset();
            this.clearMessages(form);
        }
    }

    isOpen() {
        return this.overlay?.classList.contains('active');
    }

    clearMessages(form) {
        form.querySelectorAll('.input-message').forEach(msg => {
            msg.textContent = '';
            msg.className = 'input-message';
        });
    }
}

// ================================
// VALIDAÇÕES DE CAMPO
// ================================
class FieldValidator {
    constructor(inputId, messageId) {
        this.input = document.getElementById(inputId);
        this.message = document.getElementById(messageId);
        this.timeout = null;
    }

    showMessage(text, type = 'error') {
        if (this.message) {
            this.message.textContent = text;
            this.message.className = `input-message ${type}`;
        }
    }

    clearMessage() {
        if (this.message) {
            this.message.textContent = '';
            this.message.className = 'input-message';
        }
    }

    validate(validationFn, delay = CONFIG.validationDelay) {
        clearTimeout(this.timeout);
        this.timeout = setTimeout(() => {
            const result = validationFn(this.input.value);
            if (result.isValid) {
                this.clearMessage();
            } else if (result.message) {
                this.showMessage(result.message, result.type || 'error');
            }
        }, delay);
    }

    setError(message) {
        this.input.classList.add('error');
        this.showMessage(message, 'error');
        shakeElement(this.input);
    }

    setSuccess(message = '') {
        this.input.classList.remove('error');
        if (message) {
            this.showMessage(message, 'success');
        } else {
            this.clearMessage();
        }
    }
}

// ================================
// VALIDAÇÕES ESPECÍFICAS
// ================================
const Validations = {
    login: (value) => {
        if (!value.trim()) {
            return { isValid: false, message: '' };
        }

        // Verifica se contém @cesan.com.br
        if (CONFIG.emailPattern.test(value)) {
            return {
                isValid: false,
                message: 'Usuários CESAN devem usar apenas o login sem @cesan.com.br',
                type: 'warning'
            };
        }

        return { isValid: true };
    },

    password: (value) => {
        if (!value) {
            return { isValid: false, message: '' };
        }

        if (value.length < CONFIG.minPasswordLength) {
            return {
                isValid: false,
                message: `A senha deve ter no mínimo ${CONFIG.minPasswordLength} caracteres`,
                type: 'warning'
            };
        }

        return { isValid: true };
    },

    email: (value) => {
        if (!value.trim()) {
            return { isValid: false, message: '' };
        }

        // Validação básica de email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            return {
                isValid: false,
                message: 'Digite um e-mail válido',
                type: 'error'
            };
        }

        // Verifica se é email CESAN
        if (CONFIG.emailPattern.test(value)) {
            return {
                isValid: false,
                message: 'Usuários CESAN devem usar login/senha do AD para acessar',
                type: 'warning'
            };
        }

        return { isValid: true };
    }
};

// ================================
// TOGGLE DE SENHA
// ================================
class PasswordToggle {
    constructor(buttonId, inputId) {
        this.button = document.getElementById(buttonId);
        this.input = document.getElementById(inputId);
        this.isVisible = false;
        this.setupEventListener();
    }

    setupEventListener() {
        this.button?.addEventListener('click', () => this.toggle());
    }

    toggle() {
        this.isVisible = !this.isVisible;
        this.input.type = this.isVisible ? 'text' : 'password';
        
        const icon = this.button.querySelector('i');
        icon.className = this.isVisible ? 'fas fa-eye-slash' : 'fas fa-eye';
        
        // Feedback visual
        this.button.style.transform = 'scale(1.1)';
        setTimeout(() => {
            this.button.style.transform = '';
        }, 150);
    }
}

// ================================
// LOADING BUTTON
// ================================
class LoadingButton {
    constructor(buttonId) {
        this.button = document.getElementById(buttonId);
        this.textElement = this.button?.querySelector('.btn-text');
        this.loaderElement = this.button?.querySelector('.btn-loader');
    }

    setLoading(isLoading) {
        if (!this.button) return;

        if (isLoading) {
            this.button.classList.add('loading');
            this.button.disabled = true;
            if (this.loaderElement) {
                this.loaderElement.style.display = 'block';
            }
        } else {
            this.button.classList.remove('loading');
            this.button.disabled = false;
            if (this.loaderElement) {
                this.loaderElement.style.display = 'none';
            }
        }
    }
}

// ================================
// NOTIFICAÇÕES
// ================================
function showNotification(message, type = 'error') {
    // Remove notificação anterior se existir
    const existingNotif = document.querySelector('.notification-toast');
    if (existingNotif) {
        existingNotif.remove();
    }

    const notification = document.createElement('div');
    notification.className = `notification-toast ${type}`;
    
    const icon = type === 'success' ? 'fa-check-circle' : 
                 type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle';
    
    notification.innerHTML = `
        <i class="fas ${icon}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    // Animação de entrada
    setTimeout(() => notification.classList.add('show'), 10);
    
    // Auto-remover após 5 segundos
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// ================================
// AUTO-CORREÇÃO DE LOGIN
// ================================
function autoCorrectLogin(input) {
    let value = input.value;
    
    // Remove @cesan.com.br se presente
    if (CONFIG.emailPattern.test(value)) {
        value = value.replace(/@cesan\.com\.br/gi, '');
        input.value = value;
        
        // Feedback visual
        input.style.transform = 'scale(1.02)';
        setTimeout(() => {
            input.style.transform = '';
        }, 200);
    }
}

// ================================
// FORMULÁRIO DE LOGIN
// ================================
function setupLoginForm() {
    const form = document.getElementById('loginForm');
    const loginInput = document.getElementById('login');
    const senhaInput = document.getElementById('senha');
    
    const loginValidator = new FieldValidator('login', 'loginMessage');
    const senhaValidator = new FieldValidator('senha', 'senhaMessage');
    const btnLogin = new LoadingButton('btnLogin');

    // Validação em tempo real do login
    loginInput?.addEventListener('input', (e) => {
        autoCorrectLogin(e.target);
        loginValidator.validate(Validations.login);
    });

    // Validação em tempo real da senha
    senhaInput?.addEventListener('input', () => {
        senhaValidator.validate(Validations.password);
    });

    // Submit do formulário
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // Validações finais
        const loginResult = Validations.login(loginInput.value);
        const senhaResult = Validations.password(senhaInput.value);
        
        if (!loginResult.isValid) {
            loginValidator.setError(loginResult.message || 'Login inválido');
            loginInput.focus();
            return;
        }
        
        if (!senhaResult.isValid) {
            senhaValidator.setError(senhaResult.message || 'Senha inválida');
            senhaInput.focus();
            return;
        }

        // Ativar loading
        btnLogin.setLoading(true);

        if (CONFIG.useAjaxLogin) {
            // Login via AJAX
            try {
                const formData = new FormData(form);
                
                const response = await fetch('bd/ldap.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification(data.message || 'Login realizado com sucesso!', 'success');
                    
                    // Aguardar um pouco antes de redirecionar
                    setTimeout(() => {
                        window.location.href = data.redirect || 'viewIdeia.php';
                    }, 1000);
                } else {
                    btnLogin.setLoading(false);
                    showNotification(data.message || 'Erro ao realizar login', 'error');
                }
            } catch (error) {
                console.error('Erro no login:', error);
                btnLogin.setLoading(false);
                showNotification('Erro ao conectar com o servidor. Tente novamente.', 'error');
            }
        } else {
            // Submit tradicional
            await new Promise(resolve => setTimeout(resolve, 300));
            form.submit();
        }
    });
}

// ================================
// FORMULÁRIO DE RECUPERAÇÃO
// ================================
function setupForgotPasswordForm() {
    const form = document.getElementById('forgotPasswordForm');
    const emailInput = document.getElementById('emailEsqueciSenha');
    
    const emailValidator = new FieldValidator('emailEsqueciSenha', 'emailMessage');
    const btnRecovery = new LoadingButton('btnRecovery');

    // Validação em tempo real
    emailInput?.addEventListener('input', () => {
        emailValidator.validate(Validations.email);
    });

    // Submit do formulário
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const emailResult = Validations.email(emailInput.value);
        
        if (!emailResult.isValid) {
            emailValidator.setError(emailResult.message);
            emailInput.focus();
            return;
        }

        btnRecovery.setLoading(true);
        
        // Delay para mostrar loading
        await new Promise(resolve => setTimeout(resolve, 300));
        
        // Submit real
        form.submit();
    });
}

// ================================
// AUTO-HIDE DE MENSAGENS DE ERRO
// ================================
function setupAutoHideMessages() {
    const serverMessage = document.getElementById('serverMessage');
    
    if (serverMessage) {
        // Adicionar botão de fechar
        const closeBtn = document.createElement('button');
        closeBtn.className = 'alert-close';
        closeBtn.innerHTML = '<i class="fas fa-times"></i>';
        closeBtn.onclick = () => {
            serverMessage.style.animation = 'slideDown 0.3s ease reverse';
            setTimeout(() => serverMessage.remove(), CONFIG.animationDuration);
        };
        serverMessage.appendChild(closeBtn);

        // Auto-hide após 5 segundos
        setTimeout(() => {
            serverMessage.style.animation = 'slideDown 0.3s ease reverse';
            setTimeout(() => {
                serverMessage.remove();
            }, CONFIG.animationDuration);
        }, 5000);
    }
}

// ================================
// ANIMAÇÕES DE ENTRADA
// ================================
function setupAnimations() {
    // Adiciona classes de animação aos elementos
    const animatedElements = document.querySelectorAll('.login-card, .login-info');
    
    animatedElements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.1}s`;
    });
}

// ================================
// INICIALIZAÇÃO
// ================================
document.addEventListener('DOMContentLoaded', () => {
    // Instanciar modal
    const modal = new Modal('modalOverlay');
    
    // Botão para abrir modal
    const forgotPasswordLink = document.getElementById('forgotPasswordLink');
    forgotPasswordLink?.addEventListener('click', (e) => {
        e.preventDefault();
        modal.open();
    });
    
    // Botões para fechar modal
    const closeModalBtn = document.getElementById('closeModal');
    const cancelModalBtn = document.getElementById('cancelModal');
    
    closeModalBtn?.addEventListener('click', () => modal.close());
    cancelModalBtn?.addEventListener('click', () => modal.close());
    
    // Toggle de senha
    new PasswordToggle('togglePassword', 'senha');
    
    // Setup de formulários
    setupLoginForm();
    setupForgotPasswordForm();
    
    // Outras inicializações
    setupAutoHideMessages();
    setupAnimations();
    
    // Focus inicial
    const loginInput = document.getElementById('login');
    loginInput?.focus();
    
    console.log('✅ Login moderno inicializado com sucesso!');
});

// ================================
// PREVENÇÃO DE SUBMIT MÚLTIPLO
// ================================
let isSubmitting = false;

document.addEventListener('submit', (e) => {
    if (isSubmitting && !e.target.classList.contains('allow-multiple')) {
        e.preventDefault();
        return false;
    }
    isSubmitting = true;
    
    // Reset após 5 segundos (fallback)
    setTimeout(() => {
        isSubmitting = false;
    }, 5000);
});

// ================================
// UTILITÁRIOS
// ================================

// Debounce helper
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

// Animação de shake para erro
function shakeElement(element) {
    element.style.animation = 'shake 0.3s ease';
    setTimeout(() => {
        element.style.animation = '';
    }, 300);
}

// Export para uso externo se necessário
window.LoginModule = {
    Modal,
    FieldValidator,
    Validations,
    PasswordToggle,
    LoadingButton,
    debounce,
    shakeElement,
    showNotification
};
