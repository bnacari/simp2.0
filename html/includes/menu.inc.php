<?php
//menu.inc.php

// Inclui funções de autenticação e permissões
require_once __DIR__ . '/auth.php';

// Inicia sessão de forma segura (se não iniciada)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$paginaAtual = basename($_SERVER['PHP_SELF'], '.php');

// Verifica se o usuário é desenvolvedor
$isDesenvolvedor = temPermissaoTela('Desenvolvedor', ACESSO_LEITURA);

// ============================================
// PERSISTÊNCIA DE AMBIENTE - Cookie + Sessão
// ============================================

// Restaura ambiente do cookie se não existir na sessão (apenas desenvolvedores)
if ($isDesenvolvedor && !isset($_SESSION['ambiente_forcado']) && isset($_COOKIE['simp_ambiente_preferido'])) {
    $ambienteCookie = $_COOKIE['simp_ambiente_preferido'];
    if (in_array($ambienteCookie, ['HOMOLOGAÇÃO', 'PRODUÇÃO'])) {
        $_SESSION['ambiente_forcado'] = $ambienteCookie;
    }
}

// Processa alteração de ambiente via POST (apenas desenvolvedores)
if ($isDesenvolvedor && isset($_POST['alternar_ambiente'])) {
    $novoAmbiente = $_POST['alternar_ambiente'];

    $_SESSION['ambiente_forcado'] = $novoAmbiente;

    // Cookie com secure dinâmico (funciona em HTTP e HTTPS)
    setcookie(
        'simp_ambiente_preferido',
        $novoAmbiente,
        [
            'expires' => time() + (30 * 24 * 60 * 60),
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Determina ambiente baseado no servidor de banco de dados
$dbHost = getenv('DB_HOST') ?: '';
$ambienteReal = (strpos($dbHost, 'sgbd-hom-') !== false) ? "HOMOLOGAÇÃO" : "PRODUÇÃO";

// Se desenvolvedor forçou um ambiente, usa o forçado
if ($isDesenvolvedor && isset($_SESSION['ambiente_forcado'])) {
    $ambiente = $_SESSION['ambiente_forcado'];
    $ambienteAlterado = ($ambiente !== $ambienteReal);
} else {
    $ambiente = $ambienteReal;
    $ambienteAlterado = false;
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

    /* ============================================
   DIFERENCIAÇÃO VISUAL POR AMBIENTE
   ============================================ */

    /* Produção - Azul escuro (padrão) */
    .modern-header.ambiente-producao {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    }

    /* Homologação - Laranja/Âmbar para alertar */
    .modern-header.ambiente-homologacao {
        background: linear-gradient(135deg, #92400e 0%, #b45309 100%);
        box-shadow: 0 4px 12px rgba(180, 83, 9, 0.3);
    }

    /* Ajusta cor dos botões no ambiente de homologação */
    .modern-header.ambiente-homologacao .btn-toggle-menu {
        background: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.2);
    }

    .modern-header.ambiente-homologacao .btn-toggle-menu:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    /* Badge de ambiente no header (para não-desenvolvedores) */
    .modern-header.ambiente-homologacao .ambiente-badge {
        background: #fef3c7;
        color: #92400e;
        animation: pulse-hom 2s infinite;
    }

    @keyframes pulse-hom {

        0%,
        100% {
            opacity: 1;
            box-shadow: 0 0 0 0 rgba(254, 243, 199, 0.7);
        }

        50% {
            opacity: 0.9;
            box-shadow: 0 0 8px 2px rgba(254, 243, 199, 0.5);
        }
    }

    /* Indicador extra de homologação (canto superior) */
    .modern-header.ambiente-homologacao::before {
        content: '⚠ HOMOLOGAÇÃO';
        position: absolute;
        top: 0;
        right: 120px;
        background: #fbbf24;
        color: #78350f;
        font-size: 9px;
        font-weight: 800;
        padding: 2px 10px;
        border-radius: 0 0 6px 6px;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }

    @media (max-width: 768px) {
        .modern-header.ambiente-homologacao::before {
            right: 60px;
            font-size: 8px;
            padding: 2px 6px;
        }
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

    .sidebar-version-footer {
        flex-shrink: 0;
        padding: 10px 16px;
        text-align: center;
        font-size: 10px;
        font-weight: 500;
        color: #94a3b8;
        letter-spacing: 0.03em;
        cursor: default;
        border-top: 1px solid #e2e8f0;
    }

    .modern-sidebar.collapsed .sidebar-version-footer {
        font-size: 8px;
        padding: 8px 4px;
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
        padding: 0;
        z-index: 999;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 2px 0 8px rgba(0, 0, 0, 0.04);
    }

    .sidebar-content {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 16px 0;
    }

    .modern-sidebar.collapsed {
        width: 70px;
    }

    .sidebar-content::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar-content::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    .sidebar-content::-webkit-scrollbar-thumb:hover {
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

        .modern-header-title .system-version {
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

    /* Seletor de Ambiente - Desenvolvedor */
    .ambiente-selector {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-left: 16px;
    }

    .ambiente-radio-group {
        display: flex;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        padding: 3px;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }

    .ambiente-radio {
        cursor: pointer;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.6);
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
    }

    .ambiente-radio input[type="radio"] {
        display: none;
    }

    .ambiente-radio:hover {
        color: rgba(255, 255, 255, 0.9);
    }

    .ambiente-radio.active {
        background: rgba(255, 255, 255, 0.2);
        color: #fff;
    }

    .ambiente-radio.active:first-child {
        background: #fbbf24;
        color: #0f172a;
    }

    .ambiente-radio.active:last-child {
        background: #22c55e;
        color: #fff;
    }

    .ambiente-aviso {
        color: #f97316;
        font-size: 16px;
        display: flex;
        align-items: center;
        animation: pulse 2s infinite;
    }

    .ambiente-badge.alterado {
        animation: pulse 2s infinite;
        box-shadow: 0 0 8px rgba(251, 191, 36, 0.6);
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.6;
        }
    }

    @media (max-width: 768px) {
        .ambiente-selector {
            margin-left: 8px;
        }

        .ambiente-radio {
            padding: 3px 6px;
            font-size: 9px;
        }
    }

    /* User Info Wrapper */
    .user-info-wrapper {
        position: relative;
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
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .user-info:hover {
        background: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.25);
    }

    .user-chevron {
        font-size: 14px;
        opacity: 0.7;
        transition: transform 0.2s ease;
    }

    .user-info.active .user-chevron {
        transform: rotate(180deg);
    }

    /* User Dropdown */
    .user-dropdown {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        width: 300px;
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(0, 0, 0, 0.05);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.2s ease;
        z-index: 1001;
        overflow: hidden;
    }

    .user-dropdown.active {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .user-dropdown-header {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 20px;
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        color: white;
    }

    .user-dropdown-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: 700;
        flex-shrink: 0;
        border: 3px solid rgba(255, 255, 255, 0.2);
    }

    .user-dropdown-avatar.external {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .user-dropdown-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }

    .user-dropdown-name {
        font-size: 15px;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-dropdown-group {
        font-size: 12px;
        opacity: 0.8;
        background: rgba(255, 255, 255, 0.15);
        padding: 3px 10px;
        border-radius: 100px;
        width: fit-content;
    }

    .user-dropdown-divider {
        height: 1px;
        background: #e2e8f0;
        margin: 0;
    }

    .user-dropdown-details {
        padding: 12px 16px;
    }

    .user-detail-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .user-detail-item:last-child {
        border-bottom: none;
    }

    .user-detail-item ion-icon {
        font-size: 18px;
        color: #64748b;
        margin-top: 2px;
        flex-shrink: 0;
    }

    .user-detail-content {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }

    .user-detail-label {
        font-size: 11px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }

    .user-detail-value {
        font-size: 13px;
        color: #1e293b;
        font-weight: 500;
        word-break: break-word;
    }

    .user-dropdown-logout {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px 20px;
        background: #fef2f2;
        color: #dc2626;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .user-dropdown-logout:hover {
        background: #fee2e2;
        color: #b91c1c;
    }

    .user-dropdown-logout ion-icon {
        font-size: 18px;
    }

    /* Responsivo */
    @media (max-width: 768px) {
        .user-name {
            display: none;
        }

        .user-chevron {
            display: none;
        }

        .user-info {
            padding: 6px;
        }

        .user-dropdown {
            position: fixed;
            top: 60px;
            right: 12px;
            left: 12px;
            width: auto;
        }
    }

    /* Permissão da Página Atual */
    .permissao-tipo-badge {
        display: inline-block;
        font-size: 9px;
        padding: 2px 6px;
        border-radius: 4px;
        margin-left: 6px;
        font-weight: 600;
        text-transform: uppercase;
        vertical-align: middle;
    }

    .permissao-tipo-badge.leitura {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .permissao-tipo-badge.escrita {
        background: #dcfce7;
        color: #15803d;
    }

    .user-detail-item.permissao-tela {
        background: #faf5ff;
        border-left: 3px solid #8b5cf6;
    }

    .user-detail-item.permissao-tela ion-icon {
        color: #8b5cf6;
    }
</style>

<!-- Overlay para mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

<!-- Header Moderno -->
<header class="modern-header <?= $ambiente === 'HOMOLOGAÇÃO' ? 'ambiente-homologacao' : 'ambiente-producao' ?>">
    <div class="modern-header-left">
        <button class="btn-toggle-menu" onclick="toggleSidebar()" title="Menu">
            <ion-icon name="menu-outline"></ion-icon>
        </button>

        <a href="dashboard.php">
            <img src="imagens/logo_icon.png" class="modern-header-logo" alt="Logo SIMP">
            <div class="modern-header-title">
                <span class="brand-name">
                    SIMP
                    <?php if (!$isDesenvolvedor): ?>
                        <span
                            class="ambiente-badge <?= $ambiente === 'PRODUÇÃO' ? 'producao' : '' ?>"><?= $ambiente ?></span>
                    <?php endif; ?>
                </span>
                <span class="system-fullname">Sistema Integrado de Macromedição e Pitometria</span>
            </div>
        </a>

        <?php if ($isDesenvolvedor): ?>
            <!-- Seletor de Ambiente (Desenvolvedor) -->
            <form method="POST" class="ambiente-selector" id="formAmbiente">
                <div class="ambiente-radio-group">
                    <label class="ambiente-radio <?= $ambiente === 'HOMOLOGAÇÃO' ? 'active' : '' ?>">
                        <input type="radio" name="alternar_ambiente" value="HOMOLOGAÇÃO"
                            <?= $ambiente === 'HOMOLOGAÇÃO' ? 'checked' : '' ?>
                            onchange="document.getElementById('formAmbiente').submit()">
                        <span class="radio-label">HOM</span>
                    </label>
                    <label class="ambiente-radio <?= $ambiente === 'PRODUÇÃO' ? 'active' : '' ?>">
                        <input type="radio" name="alternar_ambiente" value="PRODUÇÃO"
                            <?= $ambiente === 'PRODUÇÃO' ? 'checked' : '' ?>
                            onchange="document.getElementById('formAmbiente').submit()">
                        <span class="radio-label">PROD</span>
                    </label>
                </div>
                <?php if ($ambienteAlterado): ?>
                    <span class="ambiente-aviso" title="Ambiente forçado (real: <?= $ambienteReal ?>)">
                        <ion-icon name="warning-outline"></ion-icon>
                    </span>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

    <div class="modern-header-right">
        <?php if (isset($_SESSION['login'])) { ?>
            <div class="user-info-wrapper">
                <div class="user-info" onclick="toggleUserDropdown(event)">
                    <div class="user-avatar <?= ($_SESSION['externo'] ?? '') == 'externo' ? 'external' : '' ?>">
                        <?= getIniciaisUsuario() ?>
                    </div>
                    <span class="user-name"><?= explode(' ', $_SESSION['nome'])[0] ?></span>
                    <ion-icon name="chevron-down-outline" class="user-chevron"></ion-icon>
                </div>

                <!-- Dropdown do Usuário -->
                <div class="user-dropdown" id="userDropdown">
                    <div class="user-dropdown-header">
                        <div
                            class="user-dropdown-avatar <?= ($_SESSION['externo'] ?? '') == 'externo' ? 'external' : '' ?>">
                            <?= getIniciaisUsuario() ?>
                        </div>
                        <div class="user-dropdown-info">
                            <span class="user-dropdown-name"><?= $_SESSION['nome'] ?></span>
                            <span class="user-dropdown-group"><?= $_SESSION['grupo'] ?? 'Sem grupo' ?></span>
                        </div>
                    </div>

                    <div class="user-dropdown-divider"></div>

                    <div class="user-dropdown-details">
                        <div class="user-detail-item">
                            <ion-icon name="person-outline"></ion-icon>
                            <div class="user-detail-content">
                                <span class="user-detail-label">Login</span>
                                <span class="user-detail-value"><?= $_SESSION['login'] ?></span>
                            </div>
                        </div>

                        <div class="user-detail-item">
                            <ion-icon name="card-outline"></ion-icon>
                            <div class="user-detail-content">
                                <span class="user-detail-label">Matrícula</span>
                                <span class="user-detail-value"><?= $_SESSION['matricula'] ?: '-' ?></span>
                            </div>
                        </div>

                        <div class="user-detail-item">
                            <ion-icon name="mail-outline"></ion-icon>
                            <div class="user-detail-content">
                                <span class="user-detail-label">E-mail</span>
                                <span class="user-detail-value"><?= $_SESSION['email'] ?: '-' ?></span>
                            </div>
                        </div>

                        <div class="user-detail-item">
                            <ion-icon name="shield-checkmark-outline"></ion-icon>
                            <div class="user-detail-content">
                                <span class="user-detail-label">Grupo de Acesso</span>
                                <span class="user-detail-value"><?= $_SESSION['grupo'] ?? '-' ?></span>
                            </div>
                        </div>

                        <?php if (isset($GLOBALS['permissao_tela_atual'])): ?>
                            <div class="user-detail-item permissao-tela">
                                <ion-icon name="lock-closed-outline"></ion-icon>
                                <div class="user-detail-content">
                                    <span class="user-detail-label">Permissão da Página</span>
                                    <span class="user-detail-value">
                                        <?= htmlspecialchars($GLOBALS['permissao_tela_atual']['nome']) ?>
                                        <span class="permissao-tipo-badge <?= ($GLOBALS['permissao_tela_atual']['tipo'] ?? 0) === ACESSO_ESCRITA ? 'escrita' : 'leitura' ?>">
                                            <?= $GLOBALS['permissao_tela_atual']['tipo_label'] ?>
                                        </span>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="user-detail-item">
                            <ion-icon name="key-outline"></ion-icon>
                            <div class="user-detail-content">
                                <span class="user-detail-label">Permissões</span>
                                <span class="user-detail-value"><?= count($_SESSION['permissoes'] ?? []) ?> funcionalidades</span>
                            </div>
                        </div>

                        <?php
                        // Mostrar botão "Simular Grupo" apenas para Administrador A-DDS (real ou simulando)
                        $grupoRealUsuario = isset($_SESSION['simulacao_original']) ? $_SESSION['simulacao_original']['grupo'] : ($_SESSION['grupo'] ?? '');
                        if ($grupoRealUsuario === 'Administrador A-DDS'):
                        ?>
                        <div class="user-detail-item" style="border-top: 1px solid #e2e8f0; padding-top: 12px;">
                            <button onclick="abrirModalSimularGrupo()" class="btn-simular-grupo">
                                <ion-icon name="swap-horizontal-outline"></ion-icon>
                                Simular Grupo
                            </button>
                        </div>
                        <?php endif; ?>

                        <?php if (temPermissaoTela('Desenvolvedor')): ?>
                        <div class="user-detail-item" style="border-top: 1px solid #e2e8f0; padding-top: 12px;">
                            <button onclick="abrirModalIntegracaoCCO()" class="btn-simular-grupo">
                                <ion-icon name="sync-outline"></ion-icon>
                                Integração CCO por PM
                            </button>
                        </div>
                        <?php endif; ?>

                    </div>

                    <div class="user-dropdown-divider"></div>

                    <a href="logout.php" class="user-dropdown-logout">
                        <ion-icon name="log-out-outline"></ion-icon>
                        Sair do Sistema
                    </a>
                </div>
            </div>
        <?php } ?>
    </div>
</header>

<?php if (!empty($_SESSION['simulando_grupo'])): ?>
<div class="simulacao-banner">
    <ion-icon name="warning-outline"></ion-icon>
    Simulando grupo: <strong><?= htmlspecialchars($_SESSION['simulando_grupo_nome']) ?></strong>
    <button onclick="desativarSimulacao()">Encerrar simulação</button>
</div>
<?php endif; ?>

<!-- Sidebar Moderna -->
<?php
// Permissões dos itens do menu
$menuPermissoes = [
    'cadastros_gerais'    => temPermissaoTela('CADASTRO'),
    'prog_manutencao'     => temPermissaoTela('Programação de Manutenção'),
    'reg_manutencao'      => temPermissaoTela('Registro de Manutenção'),
    'ponto_medicao'       => temPermissaoTela('Cadastro de Ponto de Medição'),
    'motor_bomba'         => temPermissaoTela('Cadastro de Conjunto Motor-Bomba'),
    'reg_vazao_pressao'   => temPermissaoTela('Registro de Vazão e Pressão'),
    'entidade'            => temPermissaoTela('Cadastro de Entidade'),
    'validacoes'          => temPermissaoTela('Validação dos Dados'),
    'calculo_kpc'         => temPermissaoTela('Cálculo do KPC'),
    'cadastros_adm'       => temPermissaoTela('CADASTROS ADMINISTRATIVOS'),
    'flowchart'           => temPermissaoTela('flowchart'),
    'modelos_ml'          => temPermissaoTela('Modelos ML'),
    'tratamento_lote'     => temPermissaoTela('Tratamento em Lote'),
    'treinamento_ia'      => temPermissaoTela('Treinamento IA'),
    'consulta_log'        => temPermissaoTela('Consultar Log'),
    'integracao_cco'      => temPermissaoTela('Integração CCO'),
];

// Visibilidade das seções (seção aparece se ao menos 1 item for visível)
$secaoVisivel = [
    'cadastros_basicos' => $menuPermissoes['cadastros_gerais'],
    'manutencao'        => $menuPermissoes['prog_manutencao'] || $menuPermissoes['reg_manutencao'],
    'cadastros'         => $menuPermissoes['ponto_medicao'] || $menuPermissoes['motor_bomba'] || $menuPermissoes['reg_vazao_pressao'] || $menuPermissoes['entidade'],
    'operacao'          => $menuPermissoes['validacoes'],
    'calculos'          => $menuPermissoes['calculo_kpc'],
    'administracao'     => $menuPermissoes['cadastros_adm'] || $menuPermissoes['flowchart'] || $menuPermissoes['modelos_ml'] || $menuPermissoes['tratamento_lote'] || $menuPermissoes['treinamento_ia'] || $menuPermissoes['consulta_log'],
];
?>
<aside class="modern-sidebar" id="modernSidebar">
    <div class="sidebar-content">

    <?php if ($secaoVisivel['cadastros_basicos']): ?>
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
                        data-title="Cadastros Gerais">
                        <ion-icon name="list-outline"></ion-icon>
                        <span class="sidebar-link-text">Cadastros Gerais</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="sidebar-divider"></div>
    <?php endif; ?>

    <?php if ($secaoVisivel['manutencao']): ?>
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
                <?php if ($menuPermissoes['prog_manutencao']): ?>
                <li class="sidebar-item">
                    <a href="programacaoManutencao.php"
                        class="sidebar-link <?= in_array($paginaAtual, ['programacaoManutencao', 'programacaoManutencaoForm', 'programacaoManutencaoView']) ? 'active' : '' ?>"
                        data-title="Programação">
                        <ion-icon name="calendar-outline"></ion-icon>
                        <span class="sidebar-link-text">Programação</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($menuPermissoes['reg_manutencao']): ?>
                <li class="sidebar-item">
                    <a href="registroManutencao.php"
                        class="sidebar-link <?= in_array($paginaAtual, ['registroManutencao', 'registroManutencaoForm', 'registroManutencaoView']) ? 'active' : '' ?>"
                        data-title="Registro">
                        <ion-icon name="clipboard-outline"></ion-icon>
                        <span class="sidebar-link-text">Registro</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="sidebar-divider"></div>
    <?php endif; ?>

    <?php if ($secaoVisivel['cadastros']): ?>
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
                <?php if ($menuPermissoes['ponto_medicao']): ?>
                <li class="sidebar-item">
                    <a href="pontoMedicao.php"
                        class="sidebar-link <?= $paginaAtual === 'pontoMedicao' ? 'active' : '' ?>"
                        data-title="Ponto de Medição">
                        <ion-icon name="pin-outline"></ion-icon>
                        <span class="sidebar-link-text">Ponto de Medição</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($menuPermissoes['motor_bomba']): ?>
                <li class="sidebar-item">
                    <a href="motorBomba.php" class="sidebar-link <?= $paginaAtual === 'motorBomba' ? 'active' : '' ?>"
                        data-title="Motor-Bomba">
                        <ion-icon name="cog-outline"></ion-icon>
                        <span class="sidebar-link-text">Motobomba</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($menuPermissoes['reg_vazao_pressao']): ?>
                <li class="sidebar-item">
                    <a href="registroVazaoPressao.php"
                        class="sidebar-link <?= $paginaAtual === 'registroVazaoPressao' ? 'active' : '' ?>"
                        data-title="Registro Vazão/Pressão">
                        <ion-icon name="pulse-outline"></ion-icon>
                        <span class="sidebar-link-text">Registro Vazão/Pressão</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($menuPermissoes['entidade']): ?>
                <li class="sidebar-item">
                    <a href="entidade.php" class="sidebar-link <?= $paginaAtual === 'entidade' ? 'active' : '' ?>"
                        data-title="Entidade">
                        <ion-icon name="business-outline"></ion-icon>
                        <span class="sidebar-link-text">Entidade</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="sidebar-divider"></div>
    <?php endif; ?>

    <?php if ($secaoVisivel['operacao']): ?>
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
    <?php endif; ?>

    <?php if ($secaoVisivel['calculos']): ?>
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
    <?php endif; ?>

    <?php if ($secaoVisivel['administracao']): ?>
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
                <?php if ($menuPermissoes['cadastros_adm']): ?>
                <li class="sidebar-item">
                    <a href="cadastrosAdministrativos.php"
                        class="sidebar-link <?= $paginaAtual === 'cadastrosAdministrativos' ? 'active' : '' ?>"
                        data-title="Cadastros Administrativos">
                        <ion-icon name="shield-checkmark-outline"></ion-icon>
                        <span class="sidebar-link-text">Cadastros Adm</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($menuPermissoes['flowchart']): ?>
                <li class="sidebar-item">
                    <a href="entidadeCascata.php"
                        class="sidebar-link <?= $paginaAtual === 'entidadeCascata' ? 'active' : '' ?>"
                        data-title="Flowchart">
                        <ion-icon name="git-network-outline"></ion-icon>
                        <span class="sidebar-link-text">Flowchart</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($menuPermissoes['modelos_ml']): ?>
                <li class="sidebar-item">
                    <a href="modelosML.php" class="sidebar-link <?= $paginaAtual === 'modelosML' ? 'active' : '' ?>"
                        data-title="Modelos ML">
                        <ion-icon name="hardware-chip-outline"></ion-icon>
                        <span class="sidebar-link-text">Modelos ML</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($menuPermissoes['tratamento_lote']): ?>
                <li class="sidebar-item">
                    <a href="tratamentoLote.php" class="sidebar-link <?= $paginaAtual === 'tratamentoLote' ? 'active' : '' ?>"
                        data-title="Tratamento em Lote">
                        <ion-icon name="checkmark-done-outline"></ion-icon>
                        <span class="sidebar-link-text">Tratamento Lote</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($menuPermissoes['treinamento_ia']): ?>
                <li class="sidebar-item">
                    <a href="iaRegras.php" class="sidebar-link <?= $paginaAtual === 'iaRegras' ? 'active' : '' ?>"
                        data-title="Treinamento IA">
                        <ion-icon name="sparkles-outline"></ion-icon>
                        <span class="sidebar-link-text">Treinamento IA</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($menuPermissoes['consulta_log']): ?>
                <li class="sidebar-item">
                    <a href="log.php" class="sidebar-link <?= $paginaAtual === 'log' ? 'active' : '' ?>"
                        data-title="Consulta de Log">
                        <ion-icon name="document-text-outline"></ion-icon>
                        <span class="sidebar-link-text">Consulta de Log</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>
    </div>

    <div class="sidebar-version-footer" title="<?= $simpVersion['hash'] ? 'Build ' . $simpVersion['hash'] . ' - ' . $simpVersion['date'] : '' ?>">
        <?= $simpVersion['display'] ?>
    </div>

</aside>

<!-- Modal Integração CCO -->
<div id="modalIntegracaoCCO" class="modal-integracao-cco" style="display: none;">
    <div class="modal-integracao-cco-overlay" onclick="fecharModalIntegracaoCCO()"></div>
    <div class="modal-integracao-cco-content">
        <div class="modal-integracao-cco-header">
            <h3><ion-icon name="sync-outline"></ion-icon> Integração CCO - Ponto de Medição</h3>
            <button class="modal-integracao-cco-close" onclick="fecharModalIntegracaoCCO()">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-integracao-cco-body">
            <form id="formIntegracaoCCO" onsubmit="executarIntegracaoCCO(event)">
                <div class="form-group-cco">
                    <label for="pontosIntegracaoInput">
                        <ion-icon name="speedometer-outline"></ion-icon>
                        Ponto de Medição
                    </label>
                    <div class="autocomplete-container-cco">
                        <input type="text" id="pontosIntegracaoInput" class="form-control-cco"
                            placeholder="Clique para selecionar ou digite para filtrar..." autocomplete="off">
                        <input type="hidden" id="pontosIntegracao" name="pontos" value="">
                        <div id="pontosIntegracaoDropdown" class="autocomplete-dropdown-cco"></div>
                        <button type="button" id="btnLimparPontoCCO" class="btn-limpar-autocomplete-cco" style="display: none;"
                            title="Limpar">
                            <ion-icon name="close-circle"></ion-icon>
                        </button>
                    </div>
                    <small class="form-help">Selecione o ponto de medição para sincronizar com o CCO</small>
                </div>
                <div class="form-actions-cco">
                    <button type="button" class="btn-cco btn-cco-secondary" onclick="fecharModalIntegracaoCCO()">
                        <ion-icon name="close-outline"></ion-icon> Cancelar
                    </button>
                    <button type="submit" class="btn-cco btn-cco-primary" id="btnExecutarIntegracao">
                        <ion-icon name="play-outline"></ion-icon> Executar
                    </button>
                </div>
            </form>
            <div id="resultadoIntegracao" class="resultado-integracao" style="display: none;"></div>
        </div>
    </div>
</div>

<!-- Modal Simular Grupo de Usuário -->
<?php
$grupoRealModal = isset($_SESSION['simulacao_original']) ? $_SESSION['simulacao_original']['grupo'] : ($_SESSION['grupo'] ?? '');
if ($grupoRealModal === 'Administrador A-DDS'):
?>
<div id="modalSimularGrupo" class="modal-simular-grupo" style="display: none;">
    <div class="modal-simular-grupo-overlay" onclick="fecharModalSimularGrupo()"></div>
    <div class="modal-simular-grupo-content">
        <div class="modal-simular-grupo-header">
            <h3><ion-icon name="swap-horizontal-outline"></ion-icon> Simular Grupo de Usuário</h3>
            <button class="modal-simular-grupo-close" onclick="fecharModalSimularGrupo()">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-simular-grupo-body">
            <?php if (!empty($_SESSION['simulando_grupo'])): ?>
            <div class="simulacao-ativa-info">
                <ion-icon name="information-circle-outline"></ion-icon>
                <span>Simulando: <strong><?= htmlspecialchars($_SESSION['simulando_grupo_nome']) ?></strong></span>
                <button onclick="desativarSimulacao()" class="btn-voltar-grupo">
                    <ion-icon name="arrow-undo-outline"></ion-icon> Voltar ao meu grupo
                </button>
            </div>
            <?php endif; ?>
            <div class="busca-grupo-container">
                <ion-icon name="search-outline"></ion-icon>
                <input type="text" id="buscaGrupoSimulacao" placeholder="Buscar grupo..." oninput="buscarGruposSimulacaoDebounce(this.value)">
            </div>
            <div id="listaGruposSimulacao" class="lista-grupos-simulacao">
                <div class="loading-grupos">Carregando grupos...</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Modal Integração CCO */
.modal-integracao-cco {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-integracao-cco-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
}

.modal-integracao-cco-content {
    position: relative;
    background: #fff;
    border-radius: 16px;
    width: 90%;
    max-width: 550px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-integracao-cco-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 16px 16px 0 0;
    color: #fff;
}

.modal-integracao-cco-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-integracao-cco-header h3 ion-icon {
    font-size: 22px;
}

.modal-integracao-cco-close {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: #fff;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.modal-integracao-cco-close:hover {
    background: rgba(255, 255, 255, 0.2);
}

.modal-integracao-cco-close ion-icon {
    font-size: 20px;
}

.modal-integracao-cco-body {
    padding: 24px;
}

.form-group-cco {
    margin-bottom: 20px;
}

.form-group-cco label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-group-cco label ion-icon {
    font-size: 18px;
    color: #64748b;
}

/* Autocomplete CCO */
.autocomplete-container-cco {
    position: relative;
}

.form-control-cco {
    width: 100%;
    padding: 12px 40px 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.2s;
    box-sizing: border-box;
    background-color: #f8fafc;
}

.form-control-cco:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    background-color: #ffffff;
}

.autocomplete-dropdown-cco {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #e2e8f0;
    border-top: none;
    border-radius: 0 0 10px 10px;
    max-height: 280px;
    overflow-y: auto;
    z-index: 10001;
    display: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.autocomplete-dropdown-cco.active {
    display: block;
}

.autocomplete-item-cco {
    padding: 12px 14px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    transition: all 0.15s;
}

.autocomplete-item-cco:last-child {
    border-bottom: none;
}

.autocomplete-item-cco:hover,
.autocomplete-item-cco.highlighted {
    background-color: #3b82f6;
    color: white;
}

.autocomplete-item-cco .item-code-cco {
    font-family: 'SF Mono', Monaco, 'Courier New', monospace;
    font-size: 12px;
    color: #3b82f6;
    font-weight: 600;
}

.autocomplete-item-cco:hover .item-code-cco,
.autocomplete-item-cco.highlighted .item-code-cco {
    color: rgba(255, 255, 255, 0.9);
}

.autocomplete-item-cco .item-name-cco {
    display: block;
    margin-top: 4px;
    color: #475569;
}

.autocomplete-item-cco:hover .item-name-cco,
.autocomplete-item-cco.highlighted .item-name-cco {
    color: rgba(255, 255, 255, 0.85);
}

.autocomplete-loading-cco,
.autocomplete-empty-cco {
    padding: 16px;
    text-align: center;
    color: #64748b;
    font-size: 13px;
}

.btn-limpar-autocomplete-cco {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.btn-limpar-autocomplete-cco:hover {
    color: #ef4444;
}

.btn-limpar-autocomplete-cco ion-icon {
    font-size: 20px;
}

.form-help {
    display: block;
    margin-top: 6px;
    font-size: 12px;
    color: #64748b;
}

.form-actions-cco {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 24px;
}

.btn-cco {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.btn-cco ion-icon {
    font-size: 18px;
}

.btn-cco-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: #fff;
}

.btn-cco-primary:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.btn-cco-primary:disabled {
    background: #94a3b8;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-cco-secondary {
    background: #f1f5f9;
    color: #475569;
}

.btn-cco-secondary:hover {
    background: #e2e8f0;
}

.resultado-integracao {
    margin-top: 20px;
    padding: 16px;
    border-radius: 10px;
    font-size: 14px;
}

.resultado-integracao.sucesso {
    background: #dcfce7;
    border: 1px solid #86efac;
    color: #166534;
}

.resultado-integracao.erro {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.resultado-integracao.loading {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    color: #0369a1;
    display: flex;
    align-items: center;
    gap: 10px;
}

.spinner-cco {
    width: 20px;
    height: 20px;
    border: 2px solid #0369a1;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ============================================
   SIMULAÇÃO DE GRUPO DE USUÁRIO
   ============================================ */

/* Banner de simulação ativa */
.simulacao-banner {
    position: fixed;
    top: 60px;
    left: 220px;
    right: 0;
    z-index: 1002;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #fff;
    padding: 8px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(217, 119, 6, 0.3);
    transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

body.sidebar-collapsed .simulacao-banner {
    left: 70px;
}

@media (max-width: 768px) {
    .simulacao-banner {
        left: 0;
    }
}

.simulacao-banner ion-icon {
    font-size: 18px;
    flex-shrink: 0;
}

.simulacao-banner button {
    margin-left: auto;
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.4);
    color: #fff;
    padding: 4px 14px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
}

.simulacao-banner button:hover {
    background: rgba(255, 255, 255, 0.35);
}

/* Botão no dropdown */
.btn-simular-grupo {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 8px 12px;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #fff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-simular-grupo:hover {
    filter: brightness(1.1);
    transform: translateY(-1px);
}

.btn-simular-grupo ion-icon {
    font-size: 16px;
}

/* Modal */
.modal-simular-grupo {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-simular-grupo-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.modal-simular-grupo-content {
    position: relative;
    background: #fff;
    border-radius: 16px;
    width: 520px;
    max-width: 95vw;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalSimularIn 0.3s ease-out;
}

@keyframes modalSimularIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(-10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.modal-simular-grupo-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #fff;
    padding: 18px 24px;
    border-radius: 16px 16px 0 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-simular-grupo-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-simular-grupo-close {
    background: rgba(255,255,255,0.1);
    border: none;
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.modal-simular-grupo-close:hover {
    background: rgba(255,255,255,0.2);
}

.modal-simular-grupo-body {
    padding: 20px 24px;
    overflow-y: auto;
    flex: 1;
}

/* Info de simulação ativa dentro do modal */
.simulacao-ativa-info {
    background: #fffbeb;
    border: 1px solid #fcd34d;
    border-radius: 10px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
    font-size: 13px;
    color: #92400e;
}

.simulacao-ativa-info ion-icon {
    font-size: 20px;
    color: #f59e0b;
    flex-shrink: 0;
}

.btn-voltar-grupo {
    margin-left: auto;
    background: #d97706;
    color: #fff;
    border: none;
    padding: 6px 14px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: background 0.2s;
    white-space: nowrap;
}

.btn-voltar-grupo:hover {
    background: #b45309;
}

/* Campo de busca */
.busca-grupo-container {
    position: relative;
    margin-bottom: 16px;
}

.busca-grupo-container ion-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 18px;
}

.busca-grupo-container input {
    width: 100%;
    padding: 10px 12px 10px 40px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s;
    box-sizing: border-box;
}

.busca-grupo-container input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Lista de grupos */
.lista-grupos-simulacao {
    max-height: 400px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.loading-grupos {
    text-align: center;
    padding: 30px;
    color: #94a3b8;
    font-size: 14px;
}

.grupo-item {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    gap: 12px;
}

.grupo-item:hover {
    background: #f0f9ff;
    border-color: #3b82f6;
    transform: translateX(4px);
}

.grupo-item.grupo-atual {
    background: #fffbeb;
    border-color: #f59e0b;
}

.grupo-item-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 18px;
    flex-shrink: 0;
}

.grupo-item.grupo-atual .grupo-item-icon {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.grupo-item-info {
    flex: 1;
    min-width: 0;
}

.grupo-item-nome {
    font-weight: 600;
    font-size: 14px;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.grupo-item-meta {
    display: flex;
    gap: 12px;
    font-size: 12px;
    color: #94a3b8;
    margin-top: 2px;
}

.grupo-item-meta span {
    display: flex;
    align-items: center;
    gap: 3px;
}

.nenhum-grupo {
    text-align: center;
    padding: 30px;
    color: #94a3b8;
    font-size: 14px;
}
</style>

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

    // Toggle User Dropdown
    function toggleUserDropdown(event) {
        event.stopPropagation();
        const dropdown = document.getElementById('userDropdown');
        const userInfo = event.currentTarget;

        dropdown.classList.toggle('active');
        userInfo.classList.toggle('active');
    }

    // Fechar dropdown ao clicar fora
    document.addEventListener('click', function (event) {
        const dropdown = document.getElementById('userDropdown');
        const userInfo = document.querySelector('.user-info');

        if (dropdown && !dropdown.contains(event.target) && !userInfo.contains(event.target)) {
            dropdown.classList.remove('active');
            userInfo.classList.remove('active');
        }
    });

    // Fechar dropdown ao pressionar ESC
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            const dropdown = document.getElementById('userDropdown');
            const userInfo = document.querySelector('.user-info');
            if (dropdown) dropdown.classList.remove('active');
            if (userInfo) userInfo.classList.remove('active');
            // Fechar modal de integração CCO
            fecharModalIntegracaoCCO();
        }
    });

    // ============================================
    // Modal Integração CCO
    // ============================================
    let autocompleteCCOTimeout = null;
    let autocompleteCCOIndex = -1;

    // Mapeamento de letras por tipo de medidor
    const letrasTipoMedidorCCO = {
        1: 'M', // Macromedidor
        2: 'E', // Estação Pitométrica
        4: 'P', // Medidor Pressão
        6: 'R', // Nível Reservatório
        8: 'H'  // Hidrômetro
    };

    function abrirModalIntegracaoCCO() {
        const modal = document.getElementById('modalIntegracaoCCO');
        if (modal) {
            modal.style.display = 'flex';
            
            // Limpar campos
            document.getElementById('pontosIntegracaoInput').value = '';
            document.getElementById('pontosIntegracao').value = '';
            document.getElementById('btnLimparPontoCCO').style.display = 'none';
            
            // Limpar resultado anterior
            const resultado = document.getElementById('resultadoIntegracao');
            resultado.style.display = 'none';
            resultado.innerHTML = '';
            resultado.className = 'resultado-integracao';
            
            // Inicializar autocomplete
            initAutocompleteCCO();
            
            // NÃO dar foco automático para evitar abrir dropdown
        }
    }

    function fecharModalIntegracaoCCO() {
        const modal = document.getElementById('modalIntegracaoCCO');
        if (modal) {
            modal.style.display = 'none';
            document.getElementById('formIntegracaoCCO').reset();
            document.getElementById('pontosIntegracao').value = '';
            document.getElementById('btnLimparPontoCCO').style.display = 'none';
            document.getElementById('pontosIntegracaoDropdown').classList.remove('active');
        }
    }

    function initAutocompleteCCO() {
        const input = document.getElementById('pontosIntegracaoInput');
        const hidden = document.getElementById('pontosIntegracao');
        const dropdown = document.getElementById('pontosIntegracaoDropdown');
        const btnLimpar = document.getElementById('btnLimparPontoCCO');

        // Remove listeners anteriores clonando o elemento
        const newInput = input.cloneNode(true);
        input.parentNode.replaceChild(newInput, input);
        
        const newBtnLimpar = btnLimpar.cloneNode(true);
        btnLimpar.parentNode.replaceChild(newBtnLimpar, btnLimpar);

        // Referências atualizadas
        const inputEl = document.getElementById('pontosIntegracaoInput');
        const btnLimparEl = document.getElementById('btnLimparPontoCCO');

        // Evento de clique - abre dropdown (não no foco)
        inputEl.addEventListener('click', function (e) {
            e.stopPropagation();
            if (!hidden.value && !dropdown.classList.contains('active')) {
                buscarPontosMedicaoCCO('');
            }
        });

        // Evento de digitação
        inputEl.addEventListener('input', function () {
            const termo = this.value.trim();

            // Limpa seleção anterior
            hidden.value = '';
            btnLimparEl.style.display = 'none';
            autocompleteCCOIndex = -1;

            // Debounce
            clearTimeout(autocompleteCCOTimeout);
            autocompleteCCOTimeout = setTimeout(() => {
                buscarPontosMedicaoCCO(termo);
            }, 300);
        });

        // Navegação por teclado
        inputEl.addEventListener('keydown', function (e) {
            const items = dropdown.querySelectorAll('.autocomplete-item-cco');

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                autocompleteCCOIndex = Math.min(autocompleteCCOIndex + 1, items.length - 1);
                atualizarHighlightCCO(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                autocompleteCCOIndex = Math.max(autocompleteCCOIndex - 1, 0);
                atualizarHighlightCCO(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (autocompleteCCOIndex >= 0 && items[autocompleteCCOIndex]) {
                    items[autocompleteCCOIndex].click();
                }
            } else if (e.key === 'Escape') {
                dropdown.classList.remove('active');
            }
        });

        // Botão limpar
        btnLimparEl.addEventListener('click', function () {
            inputEl.value = '';
            hidden.value = '';
            btnLimparEl.style.display = 'none';
            dropdown.classList.remove('active');
            inputEl.focus();
        });

        // Fecha ao clicar fora
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.autocomplete-container-cco') && !e.target.closest('.modal-integracao-cco-content')) {
                dropdown.classList.remove('active');
            }
        });
    }

    function atualizarHighlightCCO(items) {
        items.forEach((item, index) => {
            if (index === autocompleteCCOIndex) {
                item.classList.add('highlighted');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('highlighted');
            }
        });
    }

    function buscarPontosMedicaoCCO(termo) {
        const dropdown = document.getElementById('pontosIntegracaoDropdown');

        dropdown.innerHTML = '<div class="autocomplete-loading-cco">🔍 Buscando pontos de medição...</div>';
        dropdown.classList.add('active');

        const params = new URLSearchParams({ busca: termo });

        fetch(`bd/pontoMedicao/buscarPontosMedicao.php?${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    let html = '';
                    data.data.forEach(item => {
                        const letraTipo = letrasTipoMedidorCCO[item.ID_TIPO_MEDIDOR] || 'X';
                        const codigoPonto = (item.CD_LOCALIDADE || '000') + '-' +
                            String(item.CD_PONTO_MEDICAO).padStart(6, '0') + '-' +
                            letraTipo + '-' +
                            (item.CD_UNIDADE || '00');
                        html += `
                            <div class="autocomplete-item-cco" 
                                 data-value="${item.CD_PONTO_MEDICAO}" 
                                 data-label="${codigoPonto} - ${item.DS_NOME || ''}">
                                <span class="item-code-cco">${codigoPonto}</span>
                                <span class="item-name-cco">${item.DS_NOME || 'Sem nome'}</span>
                            </div>
                        `;
                    });
                    dropdown.innerHTML = html;

                    // Adiciona eventos de clique
                    dropdown.querySelectorAll('.autocomplete-item-cco').forEach(item => {
                        item.addEventListener('click', function () {
                            selecionarPontoCCO(this.dataset.value, this.dataset.label);
                        });
                    });
                } else {
                    dropdown.innerHTML = '<div class="autocomplete-empty-cco">Nenhum ponto de medição encontrado</div>';
                }
            })
            .catch(error => {
                console.error('Erro ao buscar pontos:', error);
                dropdown.innerHTML = '<div class="autocomplete-empty-cco">Erro ao buscar pontos</div>';
            });
    }

    function selecionarPontoCCO(value, label) {
        const input = document.getElementById('pontosIntegracaoInput');
        const hidden = document.getElementById('pontosIntegracao');
        const dropdown = document.getElementById('pontosIntegracaoDropdown');
        const btnLimpar = document.getElementById('btnLimparPontoCCO');

        input.value = label;
        hidden.value = value;
        dropdown.classList.remove('active');
        btnLimpar.style.display = 'flex';
    }

    function executarIntegracaoCCO(event) {
        event.preventDefault();
        
        const pontos = document.getElementById('pontosIntegracao').value.trim();
        const pontosLabel = document.getElementById('pontosIntegracaoInput').value.trim();
        const btnExecutar = document.getElementById('btnExecutarIntegracao');
        const resultado = document.getElementById('resultadoIntegracao');
        
        if (!pontos) {
            resultado.innerHTML = '<strong>❌ Erro:</strong> Selecione um ponto de medição.';
            resultado.className = 'resultado-integracao erro';
            resultado.style.display = 'block';
            return;
        }

        // Mostrar loading
        btnExecutar.disabled = true;
        btnExecutar.innerHTML = '<div class="spinner-cco"></div> Executando...';
        resultado.innerHTML = '<div class="spinner-cco"></div> Executando integração CCO para: <strong>' + pontosLabel + '</strong>';
        resultado.className = 'resultado-integracao loading';
        resultado.style.display = 'flex';

        // Fazer requisição AJAX
        fetch('bd/integracaoCCO/executarIntegracaoCCO.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'pontos=' + encodeURIComponent(pontos)
        })
        .then(response => response.json())
        .then(data => {
            let debugHtml = '';
            if (data.debug) {
                debugHtml = '<details style="margin-top: 12px; font-size: 12px;"><summary style="cursor: pointer; color: #64748b;">🔍 Debug Info</summary><pre style="background: #f1f5f9; padding: 10px; border-radius: 6px; overflow-x: auto; margin-top: 8px; font-size: 11px;">' + JSON.stringify(data.debug, null, 2) + '</pre></details>';
            }
            
            if (data.sucesso) {
                resultado.innerHTML = '<strong>✅ Sucesso!</strong><br>' + data.mensagem + debugHtml;
                resultado.className = 'resultado-integracao sucesso';
                showToast('Integração CCO executada com sucesso!', 'sucesso');
            } else {
                resultado.innerHTML = '<strong>❌ Erro:</strong><br>' + data.mensagem + debugHtml;
                resultado.className = 'resultado-integracao erro';
                showToast('Erro na integração CCO', 'erro');
            }
        })
        .catch(error => {
            resultado.innerHTML = '<strong>❌ Erro de conexão:</strong><br>' + error.message;
            resultado.className = 'resultado-integracao erro';
            showToast('Erro de conexão', 'erro');
        })
        .finally(() => {
            btnExecutar.disabled = false;
            btnExecutar.innerHTML = '<ion-icon name="play-outline"></ion-icon> Executar';
            resultado.style.display = 'block';
        });
    }

    // ============================================
    // Simulação de Grupo de Usuário
    // ============================================
    let _debounceSimulacao = null;

    function abrirModalSimularGrupo() {
        const modal = document.getElementById('modalSimularGrupo');
        if (!modal) return;
        modal.style.display = 'flex';
        document.getElementById('buscaGrupoSimulacao').value = '';
        carregarGruposSimulacao('');
    }

    function fecharModalSimularGrupo() {
        const modal = document.getElementById('modalSimularGrupo');
        if (modal) modal.style.display = 'none';
    }

    function carregarGruposSimulacao(busca) {
        const lista = document.getElementById('listaGruposSimulacao');
        lista.innerHTML = '<div class="loading-grupos">Carregando grupos...</div>';

        const params = new URLSearchParams({ busca: busca, pagina: 1, porPagina: 100 });
        fetch('bd/grupoUsuario/getGruposUsuario.php?' + params)
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.data.length) {
                    lista.innerHTML = '<div class="nenhum-grupo">Nenhum grupo encontrado</div>';
                    return;
                }

                const grupoAtualNome = <?= json_encode($_SESSION['grupo'] ?? '') ?>;

                lista.innerHTML = data.data.map(g => {
                    const isAtual = (g.DS_NOME === grupoAtualNome);
                    return `
                        <div class="grupo-item ${isAtual ? 'grupo-atual' : ''}"
                             onclick="selecionarGrupoSimulacao(${g.CD_GRUPO_USUARIO}, '${g.DS_NOME.replace(/'/g, "\\'")}')">
                            <div class="grupo-item-icon">
                                <ion-icon name="${isAtual ? 'checkmark-outline' : 'people-outline'}"></ion-icon>
                            </div>
                            <div class="grupo-item-info">
                                <div class="grupo-item-nome">${g.DS_NOME}${isAtual ? ' (atual)' : ''}</div>
                                <div class="grupo-item-meta">
                                    <span><ion-icon name="key-outline"></ion-icon> ${g.QTD_PERMISSOES} permissões</span>
                                    <span><ion-icon name="person-outline"></ion-icon> ${g.QTD_USUARIOS} usuários</span>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            })
            .catch(() => {
                lista.innerHTML = '<div class="nenhum-grupo">Erro ao carregar grupos</div>';
            });
    }

    function buscarGruposSimulacaoDebounce(valor) {
        clearTimeout(_debounceSimulacao);
        _debounceSimulacao = setTimeout(() => carregarGruposSimulacao(valor), 300);
    }

    function selecionarGrupoSimulacao(cdGrupo, nomeGrupo) {
        if (!confirm('Simular permissões do grupo "' + nomeGrupo + '"?\n\nO menu e as permissões serão alterados temporariamente.')) return;

        const formData = new FormData();
        formData.append('cd_grupo', cdGrupo);

        fetch('bd/grupoUsuario/simularGrupo.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(() => alert('Erro de conexão'));
    }

    function desativarSimulacao() {
        const formData = new FormData();
        formData.append('reset', 1);

        fetch('bd/grupoUsuario/simularGrupo.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(() => alert('Erro de conexão'));
    }

    // Fechar modal com Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            fecharModalSimularGrupo();
        }
    });
</script>

<?php if (!empty($msgSistema)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            showToast(<?= json_encode($msgSistema) ?>, 'info');
        });
    </script>
<?php endif; ?>