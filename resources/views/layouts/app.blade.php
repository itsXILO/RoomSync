<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'RoomSync — Multi-Channel Hotel Manager' }}</title>
    <style>
        :root {
            --bg-main: #0b0f19;
            --bg-card: #131b2e;
            --bg-card-hover: #1a243d;
            --border: #232f4b;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --success: #10b981;
            --success-bg: rgba(16, 185, 129, 0.15);
            --warning: #f59e0b;
            --warning-bg: rgba(245, 158, 11, 0.15);
            --danger: #ef4444;
            --danger-bg: rgba(239, 68, 68, 0.15);
            --accent: #8b5cf6;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            background-color: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 0.85rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--text-main);
        }

        .brand-icon {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
            color: #fff;
        }

        .brand-title {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .brand-badge {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            padding: 0.2rem 0.5rem;
            border-radius: 9999px;
            margin-left: 0.4rem;
        }

        nav {
            display: flex;
            gap: 0.5rem;
        }

        .nav-link {
            color: var(--text-muted);
            text-decoration: none;
            padding: 0.5rem 0.9rem;
            font-size: 0.9rem;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.15s ease;
        }

        .nav-link:hover {
            color: var(--text-main);
            background-color: var(--bg-card-hover);
        }

        .nav-link.active {
            color: #fff;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.3), rgba(139, 92, 246, 0.3));
            border: 1px solid rgba(59, 130, 246, 0.4);
        }

        main {
            flex: 1;
            padding: 2rem 1.5rem;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .page-subtitle {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        .card {
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            margin-bottom: 1.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.55rem 1.1rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--primary);
            color: #fff;
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-danger {
            background-color: var(--danger);
            color: #fff;
        }
        .btn-danger:hover {
            background-color: #dc2626;
        }

        .btn-outline {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border);
        }
        .btn-outline:hover {
            color: var(--text-main);
            background-color: var(--bg-card-hover);
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
            padding: 1rem;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            width: 100%;
            max-width: 440px;
            padding: 1.75rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
            animation: modalPop 0.18s ease-out;
        }

        @keyframes modalPop {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .close-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
            line-height: 1;
        }

        .form-group {
            margin-bottom: 1.1rem;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 0.4rem;
        }

        .form-control {
            width: 100%;
            padding: 0.65rem 0.85rem;
            background-color: var(--bg-main);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text-main);
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.15s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
        }

        footer {
            border-top: 1px solid var(--border);
            padding: 1.25rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.8rem;
        }
    </style>
    @stack('styles')
</head>
<body>
    <header>
        <a href="/" class="brand">
            <div class="brand-icon">RS</div>
            <div class="brand-title">RoomSync</div>
            <span class="brand-badge">Channel Manager</span>
        </a>
        <nav>
            <a href="/" class="nav-link {{ request()->is('/') ? 'active' : '' }}">Dashboard</a>
            <a href="/simulator" class="nav-link {{ request()->is('simulator*') ? 'active' : '' }}">Simulator</a>
            <a href="/logs" class="nav-link {{ request()->is('logs*') ? 'active' : '' }}">Sync Logs</a>
            <a href="/channels" class="nav-link {{ request()->is('channels*') ? 'active' : '' }}">Channels</a>
        </nav>
    </header>

    <main>
        @yield('content')
    </main>

    <footer>
        RoomSync &copy; {{ date('Y') }} &mdash; Atomic Multi-Channel Room Inventory Synchronizer
    </footer>

    @stack('scripts')
</body>
</html>
