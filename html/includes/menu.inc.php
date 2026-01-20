<?php
//menu.inc.php

// Inclui funções de autenticação e permissões
require_once __DIR__ . '/auth.php';

// Inicia sessão de forma segura (se não iniciada)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$paginaAtual = basename($_SERVER['PHP_SELF'], '.php');

if (strpos($_SERVER['HTTP_HOST'], 'vdesk') !== false) {
    $ambiente = "HOMOLOGAÇÃO";
} else {
    $ambiente = "PRODUÇÃO";
}

if (isset($_SESSION['msg'])) {
    $msgSistema = $_SESSION['msg'];
    unset($_SESSION['msg']);
} else {
    $msgSistema = '';
}
?>

<style>
    /* ============================================
       MENU MODERNO - Com Seções Recolhíveis
       ============================================ */

    /* Header Principal */
    .modern-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 60px;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 20px;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .modern-header-left {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .btn-toggle-menu {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-toggle-menu:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.05);
    }

    .btn-toggle-menu ion-icon {
        font-size: 20px;
    }

    .modern-header-left a {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
    }

    .modern-header-logo {
        width: 32px;
        height: 38px;
        border-radius: 8px;
        object-fit: contain;
    }

    .modern-header-title {
        display: flex;
        flex-direction: column;
        gap: 1px;
    }

    .modern-header-title .brand-name {
        font-size: 16px;
        font-weight: 800;
        color: #ffffff;
        letter-spacing: -0.02em;
        line-height: 1;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .modern-header-title .system-fullname {
        font-size: 10px;
        font-weight: 400;
        color: rgba(255, 255, 255, 0.4);
        letter-spacing: 0.02em;
        line-height: 1;
    }

    .ambiente-badge {
        font-size: 8px;
        font-weight: 700;
        color: #0f172a;
        background: #fbbf24;
        padding: 3px 8px;
        border-radius: 100px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .ambiente-badge.producao {
        background: #22c55e;
        color: white;
    }

    .modern-header-right {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 6px 14px 6px 6px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border-radius: 100px;
        font-size: 13px;
        font-weight: 600;
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }

    .user-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
    }

    .user-avatar.external {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .btn-logout {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.25);
        color: #fca5a5;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .btn-logout:hover {
        background: rgba(239, 68, 68, 0.2);
        border-color: rgba(239, 68, 68, 0.4);
        color: #fef2f2;
    }

    .btn-logout ion-icon {
        font-size: 18px;
    }

    /* ============================================
       Sidebar com Seções Recolhíveis
       ============================================ */
    .modern-sidebar {
        position: fixed;
        top: 60px;
        left: 0;
        width: 220px;
        height: calc(100vh - 60px);
        background: #ffffff;
        border-right: 1px solid #e2e8f0;
        padding: 16px 0;
        z-index: 999;
        overflow-y: auto;
        overflow-x: hidden;
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 2px 0 8px rgba(0, 0, 0, 0.04);
    }

    .modern-sidebar.collapsed {
        width: 70px;
    }

    .modern-sidebar::-webkit-scrollbar {
        width: 4px;
    }

    .modern-sidebar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    .modern-sidebar::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    /* Seção do Menu */
    .sidebar-section {
        margin-bottom: 8px;
    }

    /* Título da Seção - Clicável */
    .sidebar-section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 16px;
        margin: 0 12px 4px 12px;
        font-size: 10px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        cursor: pointer;
        border-radius: 8px;
        transition: all 0.2s ease;
        user-select: none;
    }

    .sidebar-section-title:hover {
        background: #f1f5f9;
        color: #475569;
    }

    .sidebar-section-title .section-icon {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .sidebar-section-title .section-icon ion-icon {
        font-size: 14px;
        opacity: 0.7;
    }

    .sidebar-section-title .toggle-icon {
        font-size: 14px;
        transition: transform 0.3s ease;
        opacity: 0.5;
    }

    .sidebar-section-title.collapsed .toggle-icon {
        transform: rotate(-90deg);
    }

    /* Conteúdo da Seção - Recolhível */
    .sidebar-section-content {
        max-height: 500px;
        overflow: hidden;
        transition: max-height 0.3s ease, opacity 0.2s ease;
        opacity: 1;
    }

    .sidebar-section-content.collapsed {
        max-height: 0;
        opacity: 0;
    }

    /* Menu colapsado - esconde títulos */
    .modern-sidebar.collapsed .sidebar-section-title {
        justify-content: center;
        padding: 8px;
        margin: 0 8px 4px 8px;
    }

    .modern-sidebar.collapsed .sidebar-section-title span,
    .modern-sidebar.collapsed .sidebar-section-title .toggle-icon {
        display: none;
    }

    .modern-sidebar.collapsed .sidebar-section-title .section-icon ion-icon {
        font-size: 18px;
        opacity: 1;
    }

    .modern-sidebar.collapsed .sidebar-section-content {
        max-height: 500px !important;
        opacity: 1 !important;
    }

    /* Nav Links */
    .sidebar-nav {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .sidebar-item {
        padding: 0 12px;
    }

    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 12px;
        border-radius: 8px;
        color: #64748b;
        background: transparent;
        transition: all 0.2s ease;
        text-decoration: none;
        position: relative;
        font-size: 12px;
        font-weight: 500;
    }

    .sidebar-link ion-icon {
        font-size: 18px;
        flex-shrink: 0;
        transition: transform 0.2s ease;
    }

    .sidebar-link-text {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: opacity 0.2s ease;
    }

    .sidebar-link:hover {
        background: #f8fafc;
        color: #3b82f6;
    }

    .sidebar-link:hover ion-icon {
        transform: scale(1.1);
    }

    .sidebar-link.active {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        color: #3b82f6;
        font-weight: 600;
    }

    .sidebar-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 18px;
        background: #3b82f6;
        border-radius: 0 3px 3px 0;
    }

    /* Menu colapsado */
    .modern-sidebar.collapsed .sidebar-link-text {
        opacity: 0;
        width: 0;
    }

    .modern-sidebar.collapsed .sidebar-link {
        justify-content: center;
        padding: 10px;
    }

    .modern-sidebar.collapsed .sidebar-item {
        padding: 0 8px;
    }

    /* Tooltip para menu colapsado */
    .sidebar-link::after {
        content: attr(data-title);
        position: absolute;
        left: 65px;
        top: 50%;
        transform: translateY(-50%);
        background: #0f172a;
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 500;
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transition: all 0.2s ease;
        z-index: 1001;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .sidebar-link::before {
        z-index: 1;
    }

    .modern-sidebar.collapsed .sidebar-link:hover::after {
        opacity: 1;
        left: 70px;
    }

    /* Divisor */
    .sidebar-divider {
        height: 1px;
        background: linear-gradient(to right, transparent, #e2e8f0, transparent);
        margin: 12px 16px;
    }

    /* ============================================
       Body Adjustment
       ============================================ */
    body {
        margin: 0;
        padding: 60px 0 0 220px !important;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
        background-color: #f8fafc !important;
        min-height: 100vh;
        transition: padding-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    body.sidebar-collapsed {
        padding-left: 70px !important;
    }

    /* Container de conteúdo */
    .page-container {
        padding: 20px;
        max-width: 1600px;
        margin: 0 auto;
    }

    /* ============================================
       Toast Notifications
       ============================================ */
    .toast-container {
        position: fixed;
        top: 76px;
        right: 20px;
        z-index: 10000;
        display: flex;
        flex-direction: column;
        gap: 10px;
        pointer-events: none;
    }

    .toast {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 16px;
        background: #ffffff;
        border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 0 0 1px rgba(0, 0, 0, 0.05);
        min-width: 300px;
        max-width: 380px;
        pointer-events: auto;
        animation: toastSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        border-left: 4px solid #3b82f6;
    }

    .toast.sucesso {
        border-left-color: #22c55e;
    }

    .toast.erro {
        border-left-color: #ef4444;
    }

    .toast.alerta {
        border-left-color: #f59e0b;
    }

    .toast.info {
        border-left-color: #3b82f6;
    }

    .toast-icon {
        width: 24px;
        height: 24px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 14px;
    }

    .toast.sucesso .toast-icon {
        background: #dcfce7;
        color: #15803d;
    }

    .toast.erro .toast-icon {
        background: #fee2e2;
        color: #b91c1c;
    }

    .toast.alerta .toast-icon {
        background: #fef3c7;
        color: #b45309;
    }

    .toast.info .toast-icon {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .toast-content {
        flex: 1;
    }

    .toast-message {
        font-size: 13px;
        color: #475569;
        margin: 0;
        line-height: 1.4;
    }

    .toast-close {
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        padding: 2px;
        font-size: 16px;
        line-height: 1;
        transition: color 0.2s ease;
    }

    .toast-close:hover {
        color: #475569;
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

    .toast.hiding {
        animation: toastSlideOut 0.3s ease forwards;
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

    /* ============================================
       Responsividade
       ============================================ */
    @media (max-width: 1024px) {
        .modern-header {
            padding: 0 16px;
        }

        .modern-sidebar {
            width: 70px;
        }

        .modern-sidebar .sidebar-section-title span,
        .modern-sidebar .sidebar-section-title .toggle-icon,
        .modern-sidebar .sidebar-link-text {
            display: none;
            opacity: 0;
        }

        .modern-sidebar .sidebar-section-title {
            justify-content: center;
            padding: 8px;
            margin: 0 8px 4px 8px;
        }

        .modern-sidebar .sidebar-link {
            justify-content: center;
            padding: 10px;
        }

        .modern-sidebar .sidebar-item {
            padding: 0 8px;
        }

        .modern-sidebar .sidebar-section-content {
            max-height: 500px !important;
            opacity: 1 !important;
        }

        body {
            padding-left: 70px !important;
        }

        .user-info span:not(.user-avatar) {
            display: none;
        }

        .user-info {
            padding: 6px;
        }

        .modern-header-title .system-fullname {
            display: none;
        }
    }

    @media (max-width: 768px) {
        .modern-header {
            height: 56px;
            padding: 0 12px;
        }

        .modern-header-title .brand-name {
            font-size: 14px;
        }

        .ambiente-badge {
            display: none;
        }

        .modern-sidebar {
            top: 56px;
            height: calc(100vh - 56px);
            width: 0;
            padding: 0;
            border: none;
        }

        .modern-sidebar.mobile-open {
            width: 260px;
            padding: 16px 0;
            border-right: 1px solid #e2e8f0;
        }

        .modern-sidebar.mobile-open .sidebar-section-title span,
        .modern-sidebar.mobile-open .sidebar-section-title .toggle-icon,
        .modern-sidebar.mobile-open .sidebar-link-text {
            display: flex;
            opacity: 1;
        }

        .modern-sidebar.mobile-open .sidebar-section-title {
            justify-content: space-between;
            padding: 10px 16px;
            margin: 0 12px 4px 12px;
        }

        .modern-sidebar.mobile-open .sidebar-link {
            justify-content: flex-start;
            padding: 9px 12px;
        }

        .modern-sidebar.mobile-open .sidebar-item {
            padding: 0 12px;
        }

        body {
            padding: 56px 0 0 0 !important;
        }

        body.sidebar-collapsed {
            padding-left: 0 !important;
        }

        .page-container {
            padding: 16px;
        }

        .toast-container {
            top: 68px;
            right: 12px;
            left: 12px;
        }

        .toast {
            min-width: auto;
            max-width: 100%;
        }

        /* Overlay para mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 56px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            backdrop-filter: blur(2px);
        }

        .sidebar-overlay.active {
            display: block;
        }
    }

    @media (max-width: 480px) {
        .modern-header-logo {
            width: 28px;
            height: 32px;
        }

        .btn-toggle-menu,
        .btn-logout {
            width: 34px;
            height: 34px;
        }

        .user-avatar {
            width: 28px;
            height: 28px;
            font-size: 10px;
        }
    }

    /* Esconder elementos antigos */
    .header2,
    .sidebar2,
    .content2,
    #check {
        display: none !important;
    }
</style>

<!-- Overlay para mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

<!-- Header Moderno -->
<header class="modern-header">
    <div class="modern-header-left">
        <button class="btn-toggle-menu" onclick="toggleSidebar()" title="Menu">
            <ion-icon name="menu-outline"></ion-icon>
        </button>

        <a href="index.php">
            <img src="imagens/logo_icon.png" class="modern-header-logo" alt="Logo SIMP">
            <div class="modern-header-title">
                <span class="brand-name">
                    SIMP
                    <span
                        class="ambiente-badge <?= $ambiente === 'PRODUÇÃO' ? 'producao' : '' ?>"><?= $ambiente ?></span>
                </span>
                <span class="system-fullname">Sistema Integrado de Macromedição e Pitometria</span>
            </div>
        </a>
    </div>

    <div class="modern-header-right">
        <?php if (isset($_SESSION['login'])) { ?>
            <div class="user-info">
                <div class="user-avatar <?= ($_SESSION['externo'] ?? '') == 'externo' ? 'external' : '' ?>">
                    <?= strtoupper(substr($_SESSION['login'], 0, 2)) ?>
                </div>
                <span><?= explode('@', $_SESSION['login'])[0] ?></span>
            </div>
            <a href="logout.php" class="btn-logout" title="Sair do Sistema">
                <ion-icon name="exit-outline"></ion-icon>
            </a>
        <?php } else { ?>
            <a href="index.php" class="btn-logout" title="Acessar Sistema">
                <ion-icon name="log-in-outline"></ion-icon>
            </a>
        <?php } ?>
    </div>
</header>

<!-- Sidebar Moderna -->
<aside class="modern-sidebar" id="modernSidebar">

    <!-- Seção: Cadastros Básicos -->
    <div class="sidebar-section">
        <div class="sidebar-section-title" onclick="toggleSection(this)" data-section="cadastros-basicos">
            <span class="section-icon">
                <ion-icon name="document-text-outline"></ion-icon>
                <span>Cadastros Básicos</span>
            </span>
            <ion-icon name="chevron-down-outline" class="toggle-icon"></ion-icon>
        </div>
        <div class="sidebar-section-content" id="section-cadastros-basicos">
            <ul class="sidebar-nav">
                <li class="sidebar-item">
                    <a href="cadastrosAuxiliares.php"
                        class="sidebar-link <?= $paginaAtual === 'cadastrosAuxiliares' ? 'active' : '' ?>"
                        data-title="Cadastros Auxiliares">
                        <ion-icon name="list-outline"></ion-icon>
                        <span class="sidebar-link-text">Cadastros Auxiliares</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="sidebar-divider"></div>

    <!-- Seção: Manutenção -->
    <div class="sidebar-section">
        <div class="sidebar-section-title" onclick="toggleSection(this)" data-section="manutencao">
            <span class="section-icon">
                <ion-icon name="build-outline"></ion-icon>
                <span>Manutenção</span>
            </span>
            <ion-icon name="chevron-down-outline" class="toggle-icon"></ion-icon>
        </div>
        <div class="sidebar-section-content" id="section-manutencao">
            <ul class="sidebar-nav">
                <li class="sidebar-item">
                    <a href="programacaoManutencao.php"
                        class="sidebar-link <?= in_array($paginaAtual, ['programacaoManutencao', 'programacaoManutencaoForm', 'programacaoManutencaoView']) ? 'active' : '' ?>"
                        data-title="Programação">
                        <ion-icon name="calendar-outline"></ion-icon>
                        <span class="sidebar-link-text">Programação</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="registroManutencao.php"
                        class="sidebar-link <?= in_array($paginaAtual, ['registroManutencao', 'registroManutencaoForm', 'registroManutencaoView']) ? 'active' : '' ?>"
                        data-title="Registro">
                        <ion-icon name="clipboard-outline"></ion-icon>
                        <span class="sidebar-link-text">Registro</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="sidebar-divider"></div>

    <!-- Seção: Cadastros -->
    <div class="sidebar-section">
        <div class="sidebar-section-title" onclick="toggleSection(this)" data-section="cadastros">
            <span class="section-icon">
                <ion-icon name="folder-outline"></ion-icon>
                <span>Cadastros</span>
            </span>
            <ion-icon name="chevron-down-outline" class="toggle-icon"></ion-icon>
        </div>
        <div class="sidebar-section-content" id="section-cadastros">
            <ul class="sidebar-nav">
                <li class="sidebar-item">
                    <a href="pontoMedicao.php"
                        class="sidebar-link <?= $paginaAtual === 'pontoMedicao' ? 'active' : '' ?>"
                        data-title="Ponto de Medição">
                        <ion-icon name="pin-outline"></ion-icon>
                        <span class="sidebar-link-text">Ponto de Medição</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="motorBomba.php" class="sidebar-link <?= $paginaAtual === 'motorBomba' ? 'active' : '' ?>"
                        data-title="Motor-Bomba">
                        <ion-icon name="cog-outline"></ion-icon>
                        <span class="sidebar-link-text">Motor-Bomba</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="registroVazaoPressao.php"
                        class="sidebar-link <?= $paginaAtual === 'registroVazaoPressao' ? 'active' : '' ?>"
                        data-title="Registro Vazão/Pressão">
                        <ion-icon name="pulse-outline"></ion-icon>
                        <span class="sidebar-link-text">Registro Vazão/Pressão</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="entidade.php" class="sidebar-link <?= $paginaAtual === 'entidade' ? 'active' : '' ?>"
                        data-title="Entidade">
                        <ion-icon name="business-outline"></ion-icon>
                        <span class="sidebar-link-text">Entidade</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="sidebar-divider"></div>

    <!-- Seção: Operação -->
    <div class="sidebar-section">
        <div class="sidebar-section-title" onclick="toggleSection(this)" data-section="operacao">
            <span class="section-icon">
                <ion-icon name="construct-outline"></ion-icon>
                <span>Operação</span>
            </span>
            <ion-icon name="chevron-down-outline" class="toggle-icon"></ion-icon>
        </div>
        <div class="sidebar-section-content" id="section-operacao">
            <ul class="sidebar-nav">
                <li class="sidebar-item">
                    <a href="operacoes.php" class="sidebar-link <?= $paginaAtual === 'operacoes' ? 'active' : '' ?>"
                        data-title="Operações">
                        <ion-icon name="apps-outline"></ion-icon>
                        <span class="sidebar-link-text">Validações</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="sidebar-divider"></div>

    <div class="sidebar-section">
        <div class="sidebar-section-title" onclick="toggleSection(this)" data-section="calculos">
            <span class="section-icon">
                <ion-icon name="calculator-outline"></ion-icon>
                <span>Cálculos</span>
            </span>
            <ion-icon name="chevron-down-outline" class="toggle-icon"></ion-icon>
        </div>
        <div class="sidebar-section-content" id="section-calculos">
            <ul class="sidebar-nav">
                <li class="sidebar-item">
                    <a href="calculoKPC.php"
                        class="sidebar-link <?= in_array($paginaAtual, ['calculoKPC']) ? 'active' : '' ?>"
                        data-title="Coeficiente KPC">
                        <ion-icon name="analytics-outline"></ion-icon>
                        <span class="sidebar-link-text">Coeficiente KPC</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="sidebar-divider"></div>

    <!-- Seção: Administração -->
    <!-- Seção: Administração -->
    <div class="sidebar-section">
        <div class="sidebar-section-title" onclick="toggleSection(this)" data-section="administracao">
            <span class="section-icon">
                <ion-icon name="shield-checkmark-outline"></ion-icon>
                <span>Administração</span>
            </span>
            <ion-icon name="chevron-down-outline" class="toggle-icon"></ion-icon>
        </div>
        <div class="sidebar-section-content" id="section-administracao">
            <ul class="sidebar-nav">
                <li class="sidebar-item">
                    <a href="cadastrosAdministrativos.php"
                        class="sidebar-link <?= $paginaAtual === 'cadastrosAdministrativos' ? 'active' : '' ?>"
                        data-title="Cadastros Administrativos">
                        <ion-icon name="shield-checkmark-outline"></ion-icon>
                        <span class="sidebar-link-text">Cadastros Administrativos</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="iaRegras.php" class="sidebar-link <?= $paginaAtual === 'iaRegras' ? 'active' : '' ?>"
                        data-title="Treinamento IA">
                        <ion-icon name="sparkles-outline"></ion-icon>
                        <span class="sidebar-link-text">Treinamento IA</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
    </div>



</aside>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Scripts -->
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

<script>
    // ============================================
    // Toggle Sidebar (Desktop)
    // ============================================
    function toggleSidebar() {
        const sidebar = document.getElementById('modernSidebar');
        const body = document.body;
        const overlay = document.getElementById('sidebarOverlay');

        // Mobile behavior
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
            return;
        }

        // Desktop behavior
        sidebar.classList.toggle('collapsed');
        body.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    }

    function closeMobileSidebar() {
        const sidebar = document.getElementById('modernSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
    }

    // ============================================
    // Toggle Section (Accordion)
    // ============================================
    function toggleSection(element) {
        // Não funciona em modo colapsado
        const sidebar = document.getElementById('modernSidebar');
        if (sidebar.classList.contains('collapsed') && window.innerWidth > 768) {
            return;
        }

        const sectionId = element.getAttribute('data-section');
        const content = document.getElementById('section-' + sectionId);

        element.classList.toggle('collapsed');
        content.classList.toggle('collapsed');

        // Salvar estado
        const states = JSON.parse(localStorage.getItem('sidebarSections') || '{}');
        states[sectionId] = content.classList.contains('collapsed');
        localStorage.setItem('sidebarSections', JSON.stringify(states));
    }

    // ============================================
    // Restore States on Load
    // ============================================
    document.addEventListener('DOMContentLoaded', function () {
        // Restore sidebar state (desktop only)
        if (window.innerWidth > 768) {
            const sidebarState = localStorage.getItem('sidebarCollapsed');
            if (sidebarState === 'true') {
                document.getElementById('modernSidebar').classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }
        }

        // Restore section states
        const sectionStates = JSON.parse(localStorage.getItem('sidebarSections') || '{}');
        for (const [section, isCollapsed] of Object.entries(sectionStates)) {
            if (isCollapsed) {
                const title = document.querySelector(`[data-section="${section}"]`);
                const content = document.getElementById('section-' + section);
                if (title && content) {
                    title.classList.add('collapsed');
                    content.classList.add('collapsed');
                }
            }
        }
    });

    // ============================================
    // Handle Resize
    // ============================================
    window.addEventListener('resize', function () {
        const sidebar = document.getElementById('modernSidebar');
        const overlay = document.getElementById('sidebarOverlay');

        if (window.innerWidth > 768) {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        }
    });

    // ============================================
    // Toast System
    // ============================================
    function showToast(message, type = 'info', duration = 5000) {
        const container = document.getElementById('toastContainer');

        const icons = {
            sucesso: 'checkmark-circle',
            erro: 'close-circle',
            alerta: 'warning',
            info: 'information-circle'
        };

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <ion-icon name="${icons[type] || icons.info}"></ion-icon>
            </div>
            <div class="toast-content">
                <p class="toast-message">${message}</p>
            </div>
            <button class="toast-close" onclick="closeToast(this)">
                <ion-icon name="close"></ion-icon>
            </button>
        `;

        container.appendChild(toast);

        if (duration > 0) {
            setTimeout(() => {
                if (toast.parentNode) closeToast(toast.querySelector('.toast-close'));
            }, duration);
        }
    }

    function closeToast(button) {
        const toast = button.closest('.toast');
        toast.classList.add('hiding');
        setTimeout(() => toast.remove(), 300);
    }
</script>

<?php if (!empty($msgSistema)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            showToast(<?= json_encode($msgSistema) ?>, 'info');
        });
    </script>
<?php endif; ?>