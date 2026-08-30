<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sign In — {{ config('app.name', 'Chirp') }}</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:          #0d0f14;
      --surface:     #151820;
      --surface2:    #1c2030;
      --border:      #262c3e;
      --accent:      #6c63ff;
      --accent-h:    #8b84ff;
      --accent-glow: rgba(108,99,255,0.35);
      --text:        #e8eaf0;
      --muted:       #7a82a0;
      --danger:      #ff5f6d;
    }

    html, body {
      height: 100%;
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      background: var(--bg);
      color: var(--text);
    }

    /* Animated background */
    .bg-grid {
      position: fixed; inset: 0;
      background-image:
        linear-gradient(rgba(108,99,255,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(108,99,255,0.04) 1px, transparent 1px);
      background-size: 48px 48px;
      pointer-events: none; z-index: 0;
    }
    .bg-orb {
      position: fixed; border-radius: 50%;
      filter: blur(100px); pointer-events: none; z-index: 0;
    }
    .bg-orb-1 {
      width: 500px; height: 500px;
      background: rgba(108,99,255,0.12);
      top: -120px; left: -100px;
      animation: orbFloat 14s ease-in-out infinite alternate;
    }
    .bg-orb-2 {
      width: 400px; height: 400px;
      background: rgba(0,210,160,0.07);
      bottom: -100px; right: -80px;
      animation: orbFloat 18s ease-in-out infinite alternate-reverse;
    }
    @keyframes orbFloat {
      from { transform: translate(0,0) scale(1); }
      to   { transform: translate(40px,30px) scale(1.1); }
    }

    /* Layout */
    .page {
      position: relative; z-index: 1;
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      padding: 24px;
    }

    /* Card */
    .login-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 44px 48px 48px;
      width: 100%; max-width: 420px;
      box-shadow:
        0 0 0 1px rgba(255,255,255,0.03),
        0 32px 64px rgba(0,0,0,0.5),
        0 0 80px var(--accent-glow);
    }

    .login-logo {
      display: flex; align-items: center; gap: 10px;
      margin-bottom: 32px;
    }
    .logo-icon {
      width: 36px; height: 36px;
      background: linear-gradient(135deg, var(--accent), #a855f7);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px;
      box-shadow: 0 4px 20px var(--accent-glow);
    }
    .logo-name {
      font-size: 20px; font-weight: 700; letter-spacing: -0.4px;
      background: linear-gradient(90deg, #fff, var(--muted));
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    }

    h1 { font-size: 26px; font-weight: 700; letter-spacing: -0.5px; margin-bottom: 6px; }
    .subheading { color: var(--muted); font-size: 14px; margin-bottom: 32px; }

    /* Alert / Error */
    .alert-error {
      background: rgba(255,95,109,0.12);
      border: 1px solid rgba(255,95,109,0.3);
      border-radius: 8px; color: var(--danger);
      font-size: 13px; padding: 10px 14px; margin-bottom: 16px;
    }

    /* Form */
    .form-group { margin-bottom: 18px; }
    label {
      display: block; font-size: 13px; font-weight: 500;
      color: var(--muted); margin-bottom: 7px; letter-spacing: 0.2px;
    }
    .field-error { color: var(--danger); font-size: 12px; margin-top: 5px; }

    .input-wrap { position: relative; }
    .input-icon {
      position: absolute; left: 14px; top: 50%;
      transform: translateY(-50%); color: var(--muted);
      font-size: 15px; pointer-events: none;
    }
    input[type="email"], input[type="password"], input[type="text"] {
      width: 100%; padding: 12px 14px 12px 40px;
      background: var(--surface2); border: 1px solid var(--border);
      border-radius: 10px; color: var(--text); font-size: 14.5px;
      outline: none; transition: border-color 0.2s, box-shadow 0.2s;
    }
    input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(108,99,255,0.18);
    }
    input.is-invalid { border-color: var(--danger); }
    input::placeholder { color: #424760; }

    .toggle-pass {
      position: absolute; right: 13px; top: 50%;
      transform: translateY(-50%); background: none; border: none;
      color: var(--muted); cursor: pointer; font-size: 15px; padding: 2px;
      transition: color 0.2s;
    }
    .toggle-pass:hover { color: var(--text); }

    .form-options {
      display: flex; justify-content: space-between; align-items: center;
      margin-bottom: 24px; font-size: 13px;
    }
    .remember {
      display: flex; align-items: center; gap: 7px;
      color: var(--muted); cursor: pointer; user-select: none;
    }
    .remember input[type="checkbox"] {
      width: 15px; height: 15px; accent-color: var(--accent);
      padding: 0; margin: 0; cursor: pointer;
    }
    .forgot-link {
      color: var(--accent); text-decoration: none; font-size: 13px;
      transition: color 0.2s;
    }
    .forgot-link:hover { color: var(--accent-h); }

    .btn-primary {
      width: 100%; padding: 13px;
      background: linear-gradient(135deg, var(--accent), #8b5cf6);
      border: none; border-radius: 10px; color: #fff;
      font-size: 15px; font-weight: 600; cursor: pointer;
      transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
      box-shadow: 0 6px 24px var(--accent-glow); letter-spacing: 0.2px;
    }
    .btn-primary:hover { opacity: 0.92; transform: translateY(-1px); }
    .btn-primary:active { transform: translateY(0); }

    .divider {
      display: flex; align-items: center; gap: 12px;
      margin: 22px 0; color: var(--muted); font-size: 12px;
    }
    .divider::before, .divider::after {
      content: ''; flex: 1; height: 1px; background: var(--border);
    }

    .btn-social {
      width: 100%; padding: 12px; background: var(--surface2);
      border: 1px solid var(--border); border-radius: 10px; color: var(--text);
      font-size: 14px; font-weight: 500; cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: 10px;
      transition: background 0.2s, border-color 0.2s; margin-bottom: 10px;
    }
    .btn-social:hover { background: #222638; border-color: #3a4060; }

    .signup-prompt {
      text-align: center; margin-top: 22px;
      font-size: 13.5px; color: var(--muted);
    }
    .signup-prompt a { color: var(--accent); text-decoration: none; font-weight: 500; }
    .signup-prompt a:hover { color: var(--accent-h); }

    /* Demo hint */
    .demo-hint {
      background: rgba(108,99,255,0.08);
      border: 1px solid rgba(108,99,255,0.2);
      border-radius: 8px; padding: 10px 14px;
      font-size: 12.5px; color: var(--muted); margin-bottom: 20px;
      line-height: 1.5;
    }
    .demo-hint strong { color: var(--accent-h); }

    @media (max-width: 480px) {
      .login-card { padding: 32px 24px 36px; }
    }
  </style>
</head>
<body>

<div class="bg-grid"></div>
<div class="bg-orb bg-orb-1"></div>
<div class="bg-orb bg-orb-2"></div>

<div class="page">
  <div class="login-card">

    <div class="login-logo">
      <div class="logo-icon">⚡</div>
      <span class="logo-name">{{ config('app.name', 'Chirp') }}</span>
    </div>

    <h1>Welcome back</h1>
    <p class="subheading">Sign in to your account to continue</p>

    {{-- Session / validation errors --}}
    @if ($errors->any())
      <div class="alert-error">
        {{ $errors->first() }}
      </div>
    @endif

    {{-- Demo credentials hint --}}
    <div class="demo-hint">
      Demo: <strong>demo@chirp.com</strong> / <strong>password</strong>
    </div>

    <form method="POST" action="{{ route('login.post') }}">
      @csrf

      <div class="form-group">
        <label for="email">Email address</label>
        <div class="input-wrap">
          <span class="input-icon">✉</span>
          <input
            type="email"
            id="email"
            name="email"
            value="{{ old('email', app()->environment('local') ? 'demo@chirp.com' : '') }}"
            placeholder="you@company.com"
            autocomplete="email"
            class="{{ $errors->has('email') ? 'is-invalid' : '' }}"
            required
          />
        </div>
        @error('email')
          <div class="field-error">{{ $message }}</div>
        @enderror
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-wrap">
          <span class="input-icon">🔒</span>
          <input
            type="password"
            id="password"
            name="password"
            value="{{ app()->environment('local') ? 'password' : '' }}"
            placeholder="Enter your password"
            autocomplete="current-password"
            class="{{ $errors->has('password') ? 'is-invalid' : '' }}"
            required
          />
          <button type="button" class="toggle-pass" onclick="togglePassword()" id="toggleBtn">👁</button>
        </div>
        @error('password')
          <div class="field-error">{{ $message }}</div>
        @enderror
      </div>

      <div class="form-options">
        <label class="remember">
          <input type="checkbox" name="remember" />
          Remember me
        </label>
        <a href="#" class="forgot-link">Forgot password?</a>
      </div>

      <button type="submit" class="btn-primary">Sign in</button>
    </form>

    <div class="divider">or continue with</div>

    <button class="btn-social" type="button">
      <svg width="18" height="18" viewBox="0 0 48 48">
        <path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.2l6.7-6.7C35.8 2.5 30.3 0 24 0 14.8 0 6.9 5.4 3 13.3l7.8 6c1.8-5.5 6.9-9.8 13.2-9.8z"/>
        <path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v8.5h12.7c-.6 3-2.4 5.5-5 7.2l7.7 6c4.5-4.2 7.1-10.3 7.1-17.2z"/>
        <path fill="#FBBC05" d="M10.8 28.6A14.5 14.5 0 0 1 9.5 24c0-1.6.3-3.1.7-4.6L2.4 13.3A24 24 0 0 0 0 24c0 3.9.9 7.5 2.4 10.7l8.4-6.1z"/>
        <path fill="#34A853" d="M24 48c6.2 0 11.5-2.1 15.3-5.7l-7.7-6c-2.1 1.4-4.8 2.2-7.6 2.2-6.3 0-11.6-4.3-13.4-10l-7.8 6.1C6.9 42.6 14.8 48 24 48z"/>
      </svg>
      Sign in with Google
    </button>

    <button class="btn-social" type="button">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 0C5.37 0 0 5.37 0 12c0 5.3 3.44 9.8 8.2 11.39.6.11.82-.26.82-.58v-2.03c-3.34.73-4.04-1.61-4.04-1.61-.54-1.38-1.33-1.75-1.33-1.75-1.09-.74.08-.73.08-.73 1.2.09 1.83 1.24 1.83 1.24 1.07 1.83 2.81 1.3 3.5 1 .1-.78.42-1.3.76-1.6-2.67-.3-5.47-1.33-5.47-5.93 0-1.31.47-2.38 1.24-3.22-.12-.3-.54-1.52.12-3.18 0 0 1.01-.32 3.3 1.23a11.5 11.5 0 0 1 3-.4c1.02 0 2.04.14 3 .4 2.28-1.55 3.29-1.23 3.29-1.23.66 1.66.24 2.88.12 3.18.77.84 1.24 1.91 1.24 3.22 0 4.61-2.81 5.63-5.48 5.92.43.37.81 1.1.81 2.22v3.29c0 .32.22.7.83.58A12.01 12.01 0 0 0 24 12C24 5.37 18.63 0 12 0z"/>
      </svg>
      Sign in with GitHub
    </button>

    <p class="signup-prompt">
      Don't have an account? <a href="#">Create one free</a>
    </p>

  </div>
</div>

<script>
  function togglePassword() {
    const input = document.getElementById('password');
    const btn   = document.getElementById('toggleBtn');
    if (input.type === 'password') {
      input.type = 'text';
      btn.textContent = '🙈';
    } else {
      input.type = 'password';
      btn.textContent = '👁';
    }
  }
</script>
</body>
</html>
