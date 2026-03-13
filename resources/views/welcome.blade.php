<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                @import 'tailwindcss';
            </style>
        @endif

        <style>
            :root {
                --nav-width: 280px;
                --nav-width-collapsed: 80px;
                --transition-speed: 0.4s;
                --primary-color: #2563eb;
                --primary-hover: #1d4ed8;
                --bg-card: #ffffff;
                --bg-sidebar: #0f172a;
                --text-primary: #1e293b;
                --text-secondary: #64748b;
                --text-light: #94a3b8;
                --border-color: #e2e8f0;
                --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
                --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
                --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            }

            * {
                box-sizing: border-box;
            }

            body {
                font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
                background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                min-height: 100vh;
                margin: 0;
            }

            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                width: var(--nav-width);
                background: linear-gradient(180deg, var(--bg-sidebar) 0%, #1e293b 100%);
                color: white;
                transition: width var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 1000;
                display: flex;
                flex-direction: column;
                box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
                overflow: hidden;
            }

            .sidebar.collapsed {
                width: var(--nav-width-collapsed);
            }

            .sidebar-header {
                padding: 24px 20px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                display: flex;
                align-items: center;
                gap: 12px;
                min-height: 80px;
            }

            .logo-container {
                width: 42px;
                height: 42px;
                background: linear-gradient(135deg, #3b82f6, #8b5cf6);
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 18px;
                flex-shrink: 0;
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
                transition: transform 0.3s ease;
            }

            .logo-container:hover {
                transform: scale(1.05) rotate(3deg);
            }

            .logo-text {
                font-size: 20px;
                font-weight: 600;
                white-space: nowrap;
                opacity: 1;
                transition: opacity var(--transition-speed) ease, transform var(--transition-speed) ease;
                transform: translateX(0);
            }

            .sidebar.collapsed .logo-text {
                opacity: 0;
                transform: translateX(-20px);
                width: 0;
                overflow: hidden;
            }

            .sidebar-nav {
                flex: 1;
                padding: 20px 12px;
                overflow-y: auto;
                overflow-x: hidden;
            }

            .nav-section {
                margin-bottom: 24px;
            }

            .nav-section-title {
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 1.5px;
                color: var(--text-light);
                padding: 0 12px;
                margin-bottom: 12px;
                white-space: nowrap;
                opacity: 1;
                transition: opacity var(--transition-speed) ease;
            }

            .sidebar.collapsed .nav-section-title {
                opacity: 0;
                height: 0;
                margin: 0;
                padding: 0;
                overflow: hidden;
            }

            .nav-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 14px 16px;
                border-radius: 12px;
                color: rgba(255, 255, 255, 0.7);
                text-decoration: none;
                margin-bottom: 4px;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                position: relative;
                overflow: hidden;
                cursor: pointer;
            }

            .nav-item::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                width: 0;
                height: 100%;
                background: linear-gradient(90deg, rgba(59, 130, 246, 0.2), transparent);
                transition: width 0.3s ease;
                border-radius: 12px;
            }

            .nav-item:hover {
                background: rgba(255, 255, 255, 0.08);
                color: white;
                transform: translateX(4px);
            }

            .nav-item:hover::before {
                width: 100%;
            }

            .nav-item.active {
                background: linear-gradient(135deg, #3b82f6, #2563eb);
                color: white;
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
            }

            .nav-icon {
                width: 22px;
                height: 22px;
                flex-shrink: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: transform 0.3s ease;
            }

            .nav-item:hover .nav-icon {
                transform: scale(1.1);
            }

            .nav-text {
                white-space: nowrap;
                opacity: 1;
                transition: opacity var(--transition-speed) ease, width var(--transition-speed) ease;
            }

            .sidebar.collapsed .nav-text {
                opacity: 0;
                width: 0;
                overflow: hidden;
            }

            .nav-badge {
                margin-left: auto;
                background: #ef4444;
                color: white;
                font-size: 11px;
                font-weight: 600;
                padding: 2px 8px;
                border-radius: 20px;
                opacity: 1;
                transition: opacity var(--transition-speed) ease;
            }

            .sidebar.collapsed .nav-badge {
                opacity: 0;
                width: 0;
                padding: 0;
                overflow: hidden;
            }

            .sidebar-toggle {
                position: absolute;
                right: -14px;
                top: 50%;
                transform: translateY(-50%);
                width: 28px;
                height: 28px;
                background: white;
                border: none;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
                transition: all 0.3s ease;
                z-index: 1001;
            }

            .sidebar-toggle:hover {
                transform: translateY(-50%) scale(1.1);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            }

            .sidebar-toggle svg {
                width: 16px;
                height: 16px;
                color: var(--text-primary);
                transition: transform 0.3s ease;
            }

            .sidebar.collapsed .sidebar-toggle svg {
                transform: rotate(180deg);
            }

            .nav-toggle-btn {
                position: fixed;
                left: 20px;
                top: 20px;
                width: 44px;
                height: 44px;
                background: white;
                border: none;
                border-radius: 12px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                transition: all 0.3s ease;
                z-index: 1001;
            }

            .nav-toggle-btn:hover {
                transform: scale(1.05);
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
            }

            .nav-toggle-btn svg {
                width: 22px;
                height: 22px;
                color: var(--text-primary);
                transition: transform 0.3s ease;
            }

            .nav-toggle-btn.hidden-nav svg {
                transform: rotate(180deg);
            }

            .main-content.expanded {
                margin-left: 0;
            }

            .sidebar-footer {
                padding: 16px;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
            }

            .user-profile {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px;
                border-radius: 12px;
                background: rgba(255, 255, 255, 0.05);
                transition: all 0.3s ease;
                cursor: pointer;
            }

            .user-profile:hover {
                background: rgba(255, 255, 255, 0.1);
            }

            .user-avatar {
                width: 40px;
                height: 40px;
                border-radius: 10px;
                background: linear-gradient(135deg, #10b981, #059669);
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                font-size: 14px;
                flex-shrink: 0;
            }

            .user-info {
                overflow: hidden;
                opacity: 1;
                transition: opacity var(--transition-speed) ease, width var(--transition-speed) ease;
            }

            .sidebar.collapsed .user-info {
                opacity: 0;
                width: 0;
                overflow: hidden;
            }

            .user-name {
                font-weight: 600;
                font-size: 14px;
                white-space: nowrap;
            }

            .user-role {
                font-size: 12px;
                color: var(--text-light);
                white-space: nowrap;
            }

            .main-content {
                margin-left: var(--nav-width);
                padding: 32px;
                transition: margin-left var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1), width var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
                min-height: 100vh;
                width: calc(100% - var(--nav-width));
            }

            .sidebar.collapsed ~ .main-content,
            .main-content.expanded {
                margin-left: var(--nav-width-collapsed);
                width: calc(100% - var(--nav-width-collapsed));
            }

            .content-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 32px;
            }

            .page-title {
                font-size: 28px;
                font-weight: 700;
                color: var(--text-primary);
                margin: 0;
            }

            .page-subtitle {
                color: var(--text-secondary);
                margin-top: 4px;
                font-size: 14px;
            }

            .header-actions {
                display: flex;
                gap: 12px;
            }

            .btn {
                padding: 12px 24px;
                border-radius: 10px;
                font-weight: 500;
                font-size: 14px;
                cursor: pointer;
                transition: all 0.3s ease;
                border: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .btn-primary {
                background: linear-gradient(135deg, #3b82f6, #2563eb);
                color: white;
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
            }

            .btn-secondary {
                background: white;
                color: var(--text-primary);
                border: 1px solid var(--border-color);
            }

            .btn-secondary:hover {
                background: #f8fafc;
                transform: translateY(-2px);
            }

            .cards-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 24px;
                margin-bottom: 32px;
            }

            .card {
                background: var(--bg-card);
                border-radius: 16px;
                padding: 24px;
                box-shadow: var(--shadow-sm);
                border: 1px solid var(--border-color);
                transition: all 0.3s ease;
                animation: fadeInUp 0.5s ease forwards;
                opacity: 0;
            }

            .card:nth-child(1) { animation-delay: 0.1s; }
            .card:nth-child(2) { animation-delay: 0.2s; }
            .card:nth-child(3) { animation-delay: 0.3s; }
            .card:nth-child(4) { animation-delay: 0.4s; }

            .card:hover {
                transform: translateY(-4px);
                box-shadow: var(--shadow-lg);
            }

            .card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 16px;
            }

            .card-icon {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .card-icon.blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
            .card-icon.green { background: rgba(16, 185, 129, 0.1); color: #10b981; }
            .card-icon.purple { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
            .card-icon.orange { background: rgba(249, 115, 22, 0.1); color: #f97316; }

            .card-title {
                font-size: 14px;
                color: var(--text-secondary);
                margin-bottom: 8px;
            }

            .card-value {
                font-size: 32px;
                font-weight: 700;
                color: var(--text-primary);
            }

            .card-change {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                font-size: 13px;
                font-weight: 500;
                margin-top: 12px;
                padding: 4px 10px;
                border-radius: 20px;
            }

            .card-change.positive {
                background: rgba(16, 185, 129, 0.1);
                color: #10b981;
            }

            .card-change.negative {
                background: rgba(239, 68, 68, 0.1);
                color: #ef4444;
            }

            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .table-container {
                background: white;
                border-radius: 16px;
                box-shadow: var(--shadow-sm);
                border: 1px solid var(--border-color);
                overflow: hidden;
                animation: fadeInUp 0.5s ease 0.5s forwards;
                opacity: 0;
            }

            .table-header {
                padding: 20px 24px;
                border-bottom: 1px solid var(--border-color);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .table-title {
                font-size: 18px;
                font-weight: 600;
                color: var(--text-primary);
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            th {
                text-align: left;
                padding: 16px 24px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: var(--text-secondary);
                background: #f8fafc;
                border-bottom: 1px solid var(--border-color);
            }

            td {
                padding: 16px 24px;
                font-size: 14px;
                color: var(--text-primary);
                border-bottom: 1px solid var(--border-color);
            }

            tr:last-child td {
                border-bottom: none;
            }

            tr:hover td {
                background: #f8fafc;
            }

            .status-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 13px;
                font-weight: 500;
            }

            .status-badge.success {
                background: rgba(16, 185, 129, 0.1);
                color: #10b981;
            }

            .status-badge.warning {
                background: rgba(245, 158, 11, 0.1);
                color: #f59e0b;
            }

            .status-badge.pending {
                background: rgba(59, 130, 246, 0.1);
                color: #3b82f6;
            }

            .status-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: currentColor;
            }

            @media (max-width: 768px) {
                .sidebar {
                    width: var(--nav-width-collapsed);
                }

                .sidebar .logo-text,
                .sidebar .nav-text,
                .sidebar .nav-section-title,
                .sidebar .nav-badge,
                .sidebar .user-info {
                    opacity: 0;
                    width: 0;
                    overflow: hidden;
                }

                .main-content {
                    margin-left: var(--nav-width-collapsed);
                    padding: 20px;
                }

                .content-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 16px;
                }

                .cards-grid {
                    grid-template-columns: 1fr;
                }
            }

            .menu-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .menu-overlay.active {
                display: block;
                opacity: 1;
            }

            @media (max-width: 1024px) {
                .menu-overlay.active {
                    display: block;
                }
            }
        </style>
    </head>
    <body>
        <div class="menu-overlay" id="menuOverlay"></div>

        <button class="nav-toggle-btn" id="navToggleBtn" aria-label="Mostrar/Ocultar menú">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">CV</div>
                <span class="logo-text">Control Volumétrico</span>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Principal</div>
                    <a href="#" class="nav-item active">
                        <span class="nav-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        </span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="#" class="nav-item">
                        <span class="nav-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                        </span>
                        <span class="nav-text">Inventario</span>
                        <span class="nav-badge">12</span>
                    </a>
                    <a href="#" class="nav-item">
                        <span class="nav-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg>
                        </span>
                        <span class="nav-text">Reportes</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Gestión</div>
                    <a href="#" class="nav-item">
                        <span class="nav-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        </span>
                        <span class="nav-text">Usuarios</span>
                    </a>
                    <a href="#" class="nav-item">
                        <span class="nav-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                        </span>
                        <span class="nav-text">Configuración</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Sistema</div>
                    <a href="#" class="nav-item">
                        <span class="nav-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        </span>
                        <span class="nav-text">Logs</span>
                    </a>
                    <a href="#" class="nav-item">
                        <span class="nav-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        </span>
                        <span class="nav-text">Ayuda</span>
                    </a>
                </div>
            </nav>

            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar">CA</div>
                    <div class="user-info">
                        <div class="user-name">César Admin</div>
                        <div class="user-role">Administrador</div>
                    </div>
                </div>
            </div>

            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
        </aside>

        <main class="main-content" id="mainContent">
            <header class="content-header">
                <div>
                    <h1 class="page-title">Dashboard</h1>
                    <p class="page-subtitle">Bienvenido al sistema de control volumétrico</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        Notificaciones
                    </button>
                    <button class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        Nuevo Registro
                    </button>
                </div>
            </header>

            <div class="cards-grid">
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon blue">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                        </div>
                    </div>
                    <div class="card-title">Total Tanques</div>
                    <div class="card-value">24</div>
                    <div class="card-change positive">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                        +12% este mes
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-icon green">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                        </div>
                    </div>
                    <div class="card-title">Nivel Promedio</div>
                    <div class="card-value">78%</div>
                    <div class="card-change positive">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                        +5% vs semana anterior
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-icon purple">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                        </div>
                    </div>
                    <div class="card-title">Entregas Hoy</div>
                    <div class="card-value">156</div>
                    <div class="card-change positive">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                        +8% vs ayer
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-icon orange">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                        </div>
                    </div>
                    <div class="card-title">Alertas</div>
                    <div class="card-value">3</div>
                    <div class="card-change negative">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline><polyline points="17 18 23 18 23 12"></polyline></svg>
                        Requiere atención
                    </div>
                </div>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h2 class="table-title">Registros Recientes</h2>
                    <button class="btn btn-secondary" style="padding: 8px 16px; font-size: 13px;">
                        Ver todos
                    </button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Tanque</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>#REG-001</td>
                            <td>13 Mar 2026</td>
                            <td>Tanque A-01</td>
                            <td>Entrada</td>
                            <td>5,000 L</td>
                            <td><span class="status-badge success"><span class="status-dot"></span>Completado</span></td>
                        </tr>
                        <tr>
                            <td>#REG-002</td>
                            <td>13 Mar 2026</td>
                            <td>Tanque B-03</td>
                            <td>Salida</td>
                            <td>2,500 L</td>
                            <td><span class="status-badge success"><span class="status-dot"></span>Completado</span></td>
                        </tr>
                        <tr>
                            <td>#REG-003</td>
                            <td>13 Mar 2026</td>
                            <td>Tanque A-02</td>
                            <td>Entrada</td>
                            <td>8,000 L</td>
                            <td><span class="status-badge pending"><span class="status-dot"></span>Pendiente</span></td>
                        </tr>
                        <tr>
                            <td>#REG-004</td>
                            <td>12 Mar 2026</td>
                            <td>Tanque C-01</td>
                            <td>Salida</td>
                            <td>1,200 L</td>
                            <td><span class="status-badge warning"><span class="status-dot"></span>En Proceso</span></td>
                        </tr>
                        <tr>
                            <td>#REG-005</td>
                            <td>12 Mar 2026</td>
                            <td>Tanque B-02</td>
                            <td>Entrada</td>
                            <td>6,750 L</td>
                            <td><span class="status-badge success"><span class="status-dot"></span>Completado</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const sidebar = document.getElementById('sidebar');
                const sidebarToggle = document.getElementById('sidebarToggle');
                const menuOverlay = document.getElementById('menuOverlay');
                const mainContent = document.getElementById('mainContent');
                const navToggleBtn = document.getElementById('navToggleBtn');
                let isHidden = false;

                function toggleSidebar() {
                    isHidden = !isHidden;
                    
                    if (isHidden) {
                        sidebar.style.width = '0';
                        sidebar.style.padding = '0';
                        sidebar.style.overflow = 'hidden';
                        mainContent.style.marginLeft = '0';
                        navToggleBtn.style.left = '20px';
                    } else {
                        sidebar.style.width = '';
                        sidebar.style.padding = '';
                        sidebar.style.overflow = '';
                        mainContent.style.marginLeft = '';
                        navToggleBtn.style.left = 'calc(var(--nav-width-collapsed) + 20px)';
                    }
                    
                    navToggleBtn.classList.toggle('hidden-nav', !isHidden);
                    
                    if (window.innerWidth <= 1024) {
                        if (isHidden) {
                            sidebar.style.transform = 'translateX(-100%)';
                        } else {
                            sidebar.style.transform = 'translateX(0)';
                        }
                    }
                }

                function toggleCollapsed() {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('expanded');
                    
                    if (sidebar.classList.contains('collapsed')) {
                        mainContent.style.marginLeft = 'var(--nav-width-collapsed)';
                        navToggleBtn.style.left = 'calc(var(--nav-width-collapsed) + 20px)';
                    } else {
                        mainContent.style.marginLeft = 'var(--nav-width)';
                        navToggleBtn.style.left = 'calc(var(--nav-width) + 20px)';
                    }
                    
                    if (window.innerWidth <= 1024) {
                        if (sidebar.classList.contains('collapsed')) {
                            sidebar.style.transform = 'translateX(-100%)';
                        } else {
                            sidebar.style.transform = 'translateX(0)';
                        }
                    }
                }

                sidebarToggle.addEventListener('click', toggleCollapsed);
                navToggleBtn.addEventListener('click', toggleSidebar);

                if (menuOverlay) {
                    menuOverlay.addEventListener('click', function() {
                        sidebar.classList.add('collapsed');
                        sidebar.style.transform = 'translateX(-100%)';
                        menuOverlay.classList.remove('active');
                    });
                }

                window.addEventListener('resize', function() {
                    if (window.innerWidth <= 1024) {
                        sidebar.classList.add('collapsed');
                        sidebar.style.transform = 'translateX(-100%)';
                    } else {
                        sidebar.style.transform = '';
                    }
                });

                if (window.innerWidth <= 1024) {
                    sidebar.classList.add('collapsed');
                    sidebar.style.transform = 'translateX(-100%)';
                } else {
                    navToggleBtn.style.left = 'calc(var(--nav-width) + 20px)';
                }

                const navItems = document.querySelectorAll('.nav-item');
                navItems.forEach(item => {
                    item.addEventListener('click', function(e) {
                        e.preventDefault();
                        navItems.forEach(nav => nav.classList.remove('active'));
                        this.classList.add('active');
                    });
                });
            });
        </script>
    </body>
</html>
