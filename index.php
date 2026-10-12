<?php
require_once __DIR__ . '/includes/redirect.php';
redirect_if_logged_in();
?>
<!DOCTYPE html>
<html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login — Calamba General Merchandise Inc. Inc.</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Root file for the design -->
    <link rel="stylesheet" href="<?= app_url('/assets/css/root.css') ?>">
    
    <!-- Index css -->
    <link rel="stylesheet" href="<?= app_url('/assets/css/index.css') ?>">

    </head>

    <body>

    <main class="login-page" role="main">

    <!-- ── Brand Panel ────────────────────────────────────────── -->
    <aside class="brand-panel" aria-hidden="true">
        <div class="brand-grid"></div>
        <div class="brand-content">

        <div class="brand-logo">
            <svg viewBox="0 0 24 24">
            <path d="M6 2 3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
            <line x1="3" y1="6" x2="21" y2="6"/>
            <path d="M16 10a4 4 0 01-8 0"/>
            </svg>
        </div>

        <p class="brand-tagline">Inventory Management System</p>

        <h1 class="brand-title">
            Calamba General<br>
            <span>Merchandise</span> Inc.
        </h1>

        <div class="brand-divider"></div>

        <p class="brand-desc">
            Your complete solution for inventory management, point-of-sale, and business operations.
        </p>

        </div>
    </aside>

    <!-- ── Form Panel ─────────────────────────────────────────── -->
    <section class="form-panel">
        <div class="login-card" role="region" aria-label="Login">

            <header class="card-header">
                <p class="card-eyebrow">Welcome back</p>
                <h2 class="card-title">Sign In</h2>
                <p class="card-subtitle">Enter your credentials to access the system.</p>
            </header>

        <div class="card-sep"></div>

        <div class="field-group">

            <!-- Username -->
            <div class="field">
            <label class="field-label" for="login-user">Username</label>
            <div class="input-wrapper">
                <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                </span>
                <input
                class="field-input"
                id="login-user"
                type="text"
                placeholder="Enter username"
                autocomplete="username"
                required
                >
            </div>

            </div>

            <!-- Password -->
            <div class="field">
            <label class="field-label" for="password">Password</label>
            <div class="input-wrapper">
                <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                </span>
                <input
                class="field-input"
                id="password"
                type="password"
                placeholder="Enter password"
                autocomplete="current-password"
                required
                >
                <button class="toggle-pw" id="togglePw" type="button" aria-label="Show password" title="Toggle password visibility">
                <svg class="icon-eye" viewBox="0 0 24 24">
                    <path d="M1 12S5 4 12 4s11 8 11 8-4 8-11 8S1 12 1 12z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg class="icon-eye-off" viewBox="0 0 24 24">
                    <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/>
                    <path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                </svg>
                </button>
            </div>

            </div>

            <!-- Attempt counter -->
            <div class="attempt-row" role="status" aria-live="polite">
            <span class="attempt-label">Login Attempts</span>
            <span class="attempt-badge" id="login_attempt">0</span>
            </div>

            <!-- Lock message -->
            <div class="lock-message" id="lockMessage" role="alert" aria-live="assertive">
            <svg viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            Account locked due to too many failed login attempts.
            </div>

            <!-- Submit -->
            <button class="login-btn" id="loginBtn" type="button">
            <svg viewBox="0 0 24 24">
                <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/>
                <polyline points="10 17 15 12 10 7"/>
                <line x1="15" y1="12" x2="3" y2="12"/>
            </svg>
            Login
            </button>

        </div>

        <p class="card-footer">&copy; 2020 Calamba General Merchandise Inc. Inc.</p>

        </div>

    </section>

    </main>

    <!-- Script of the login page -->
    <script src="<?= app_url('/assets/js/index.js') ?>"></script>

    <script>window.CGMI_BASE_URL = <?= json_encode(app_base_path()); ?>;</script>
</body>
</html>