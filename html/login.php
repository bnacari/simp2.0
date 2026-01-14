<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se já está logado, redireciona para dashboard
if (isset($_SESSION['sucesso']) && $_SESSION['sucesso'] == 1) {
    header('Location: dashboard.php');
    exit();
}

// Captura mensagem de sessão (erro de login, etc.)
$msgSessao = null;
$tipoMsg = 'erro';

if (isset($_SESSION['msg']) && !empty($_SESSION['msg'])) {
    $msgSessao = $_SESSION['msg'];
    $tipoMsg = $_SESSION['msg_tipo'] ?? 'erro';
    unset($_SESSION['msg']);
    unset($_SESSION['msg_tipo']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIMP | CESAN</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="imagens/favicon.png">
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Ion Icons -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    
    <style>
        /* ============================================
           CSS Variables
           ============================================ */
        :root {
            --primary-500: #3b82f6;
            --primary-600: #2563eb;
            --primary-100: rgba(59, 130, 246, 0.1);
            
            --dark-50: #f8fafc;
            --dark-100: #f1f5f9;
            --dark-200: #e2e8f0;
            --dark-300: #cbd5e1;
            --dark-500: #64748b;
            --dark-700: #334155;
            --dark-800: #1e293b;
            --dark-900: #0f172a;
            
            --success-100: #dcfce7;
            --success-500: #22c55e;
            --success-700: #15803d;
            
            --error-100: #fee2e2;
            --error-500: #ef4444;
            --error-700: #b91c1c;
            
            --warning-100: #fef3c7;
            --warning-500: #f59e0b;
            --warning-700: #b45309;
            
            --info-100: #dbeafe;
            --info-500: #3b82f6;
            --info-700: #1d4ed8;
            
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-2xl: 24px;
            
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            
            --transition-fast: 150ms ease;
            --transition-normal: 250ms ease;
        }

        /* ============================================
           Reset & Base
           ============================================ */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--dark-50);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ============================================
           Login Page
           ============================================ */
        .login-page {
            min-height: 100vh;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
        }

        .login-card {
            background: #ffffff;
            border: 1px solid var(--dark-200);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-md);
            padding: 48px;
            width: 100%;
            max-width: 420px;
            transition: transform var(--transition-normal), box-shadow var(--transition-normal);
        }

        .login-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .logoLogin {
            width: 64px;
            height: auto;
            border-radius: var(--radius-lg);
            display: block;
            margin: 0 auto 24px auto;
        }

        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-header h2 {
            font-weight: 800;
            color: var(--dark-900);
            font-size: 28px;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .login-header p {
            color: var(--dark-500);
            font-size: 14px;
            margin: 0;
        }

        /* Form Controls */
        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .input-group i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--dark-300);
            font-size: 16px;
            transition: color var(--transition-fast);
        }

        .input-group:focus-within i {
            color: var(--primary-500);
        }

        .form-control {
            width: 100%;
            background-color: var(--dark-50);
            border: 1px solid var(--dark-300);
            border-radius: var(--radius-lg);
            padding: 14px 16px 14px 44px;
            font-family: inherit;
            font-size: 14px;
            color: var(--dark-700);
            transition: all var(--transition-fast);
        }

        .form-control::placeholder {
            color: var(--dark-300);
        }

        .form-control:focus {
            outline: none;
            background-color: #ffffff;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px var(--primary-100);
        }

        /* Help Link */
        .form-help {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 24px;
        }

        .form-help a {
            font-size: 13px;
            color: var(--primary-500);
            text-decoration: none;
            font-weight: 600;
            transition: color var(--transition-fast);
        }

        .form-help a:hover {
            color: var(--primary-600);
        }

        /* Button */
        .btn-login {
            width: 100%;
            background: var(--dark-900);
            color: #ffffff;
            border: none;
            border-radius: var(--radius-lg);
            padding: 14px 24px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all var(--transition-fast);
        }

        .btn-login:hover {
            background-color: var(--dark-800);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Footer */
        .footer-text {
            text-align: center;
            margin-top: 32px;
            font-size: 12px;
            color: var(--dark-300);
        }

        /* ============================================
           Toast Notifications
           ============================================ */
        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            pointer-events: none;
        }

        .toast {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px 18px;
            background: #ffffff;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg), 0 0 0 1px rgba(0,0,0,0.05);
            min-width: 320px;
            max-width: 420px;
            pointer-events: auto;
            animation: toastSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            border-left: 4px solid var(--info-500);
        }

        .toast.sucesso { border-left-color: var(--success-500); }
        .toast.erro { border-left-color: var(--error-500); }
        .toast.alerta { border-left-color: var(--warning-500); }
        .toast.info { border-left-color: var(--info-500); }

        .toast-icon {
            width: 28px;
            height: 28px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 16px;
        }

        .toast.sucesso .toast-icon { background: var(--success-100); color: var(--success-700); }
        .toast.erro .toast-icon { background: var(--error-100); color: var(--error-700); }
        .toast.alerta .toast-icon { background: var(--warning-100); color: var(--warning-700); }
        .toast.info .toast-icon { background: var(--info-100); color: var(--info-700); }

        .toast-content {
            flex: 1;
            padding-top: 2px;
        }

        .toast-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark-900);
            margin: 0 0 2px 0;
        }

        .toast-message {
            font-size: 13px;
            color: var(--dark-500);
            margin: 0;
            line-height: 1.5;
        }

        .toast-close {
            background: none;
            border: none;
            color: var(--dark-300);
            cursor: pointer;
            padding: 4px;
            font-size: 18px;
            line-height: 1;
            transition: color var(--transition-fast);
            margin: -4px -4px -4px 0;
        }

        .toast-close:hover {
            color: var(--dark-700);
        }

        @keyframes toastSlideIn {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes toastSlideOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100px);
            }
        }

        .toast.hiding {
            animation: toastSlideOut 0.3s ease forwards;
        }

        /* ============================================
           Modal
           ============================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .modal-container {
            background: #ffffff;
            border-radius: var(--radius-2xl);
            border: 1px solid var(--dark-200);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 480px;
            width: 100%;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(-10px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-header {
            padding: 24px 28px;
            border-bottom: 1px solid var(--dark-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: var(--dark-900);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h3 i {
            color: var(--primary-500);
        }

        .modal-close {
            background: var(--dark-100);
            border: none;
            color: var(--dark-500);
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .modal-close:hover {
            background: var(--dark-200);
            color: var(--dark-900);
        }

        .modal-body {
            padding: 32px 28px;
        }

        .modal-body p {
            font-size: 15px;
            color: var(--dark-500);
            line-height: 1.7;
            margin: 0;
            text-align: center;
        }

        .modal-footer {
            padding: 20px 28px;
            background: var(--dark-50);
            border-top: 1px solid var(--dark-200);
            border-radius: 0 0 var(--radius-2xl) var(--radius-2xl);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-modal {
            padding: 12px 24px;
            border-radius: var(--radius-lg);
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .btn-modal-primary {
            background: var(--dark-900);
            color: white;
            border: none;
        }

        .btn-modal-primary:hover {
            background: var(--dark-800);
            transform: translateY(-1px);
        }

        .btn-modal-secondary {
            background: #ffffff;
            color: var(--dark-500);
            border: 1px solid var(--dark-200);
        }

        .btn-modal-secondary:hover {
            background: var(--dark-100);
            color: var(--dark-700);
        }

        /* ============================================
           Responsive
           ============================================ */
        @media (max-width: 480px) {
            .login-card {
                padding: 32px 24px;
            }

            .login-header h2 {
                font-size: 24px;
            }

            .toast {
                min-width: auto;
                max-width: calc(100vw - 48px);
            }

            .toast-container {
                left: 24px;
                right: 24px;
            }
        }
    </style>
</head>
<body>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Login Page -->
<div class="login-page">
    <div class="login-card">
        <div>
            <img src="imagens/logo_icon.png" class="logoLogin" alt="Logo SIMP">
        </div>

        <div class="login-header">
            <h2>SIMP</h2>
            <p>Sistema Integrado de Macromedição e Pitometria</p>
        </div>

        <form action="bd/ldap.php" method="POST" id="formLogin">
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" 
                       name="login" 
                       class="form-control" 
                       placeholder="Seu usuário ou email corporativo" 
                       required 
                       autocomplete="username"
                       autofocus>
            </div>

            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" 
                       name="senha" 
                       class="form-control" 
                       placeholder="Sua senha" 
                       required
                       autocomplete="current-password">
            </div>

            <div class="form-help">
                <a href="#" onclick="openModal(event)">
                    <i class="fas fa-question-circle"></i>
                    Precisa de ajuda?
                </a>
            </div>

            <button type="submit" class="btn-login" id="btnLogin">
                <span>Entrar no Sistema</span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <div class="footer-text">
            © <?= date('Y') ?> CESAN. Todos os direitos reservados.
        </div>
    </div>
</div>

<!-- Modal Ajuda de Acesso -->
<div id="modalAjuda" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>
                <i class="fas fa-info-circle"></i>
                Ajuda de Acesso
            </h3>
            <button type="button" class="modal-close" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body">
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="width: 64px; height: 64px; background: var(--info-100); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 28px;">
                    🔐
                </div>
            </div>
            <p>
                Utilize o mesmo <strong>usuário</strong> (nome.sobrenome) e <strong>senha</strong> de acesso ao computador da rede CESAN.
            </p>
            <p style="margin-top: 16px; font-size: 13px; color: var(--dark-400);">
                Em caso de problemas, entre em contato com a equipe de TI.
            </p>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn-modal btn-modal-primary" onclick="closeModal()">
                Entendi
            </button>
        </div>
    </div>
</div>

<script>
    // ============================================
    // Toast System
    // ============================================
    function showToast(message, type = 'info', title = null, duration = 5000) {
        const container = document.getElementById('toastContainer');
        
        const icons = {
            sucesso: 'fa-check',
            erro: 'fa-exclamation',
            alerta: 'fa-exclamation-triangle',
            info: 'fa-info'
        };

        const titles = {
            sucesso: 'Sucesso',
            erro: 'Erro',
            alerta: 'Atenção',
            info: 'Informação'
        };

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas ${icons[type] || icons.info}"></i>
            </div>
            <div class="toast-content">
                <p class="toast-title">${title || titles[type] || titles.info}</p>
                <p class="toast-message">${message}</p>
            </div>
            <button class="toast-close" onclick="closeToast(this)">
                <i class="fas fa-times"></i>
            </button>
        `;

        container.appendChild(toast);

        if (duration > 0) {
            setTimeout(() => {
                if (toast.parentNode) {
                    closeToast(toast.querySelector('.toast-close'));
                }
            }, duration);
        }

        return toast;
    }

    function closeToast(button) {
        const toast = button.closest('.toast');
        toast.classList.add('hiding');
        setTimeout(() => toast.remove(), 300);
    }

    // ============================================
    // Modal
    // ============================================
    function openModal(e) {
        if (e) e.preventDefault();
        const modal = document.getElementById('modalAjuda');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        const modal = document.getElementById('modalAjuda');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Fechar ao clicar fora
    document.getElementById('modalAjuda').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    // Fechar com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    // ============================================
    // Form Submit Loading State
    // ============================================
    document.getElementById('formLogin').addEventListener('submit', function() {
        const btn = document.getElementById('btnLogin');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Autenticando...';
    });

    // ============================================
    // Show Session Message (if exists)
    // ============================================
    <?php if ($msgSessao): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showToast(<?= json_encode($msgSessao) ?>, <?= json_encode($tipoMsg) ?>);
    });
    <?php endif; ?>
</script>

</body>
</html>