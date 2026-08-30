<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard — {{ config('app.name', 'Chirp') }}</title>
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
      --success:     #00d2a0;
    }

    html, body {
      min-height: 100%; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      background: var(--bg); color: var(--text);
    }

    /* Background */
    .bg-grid {
      position: fixed; inset: 0; pointer-events: none; z-index: 0;
      background-image:
        linear-gradient(rgba(108,99,255,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(108,99,255,0.04) 1px, transparent 1px);
      background-size: 48px 48px;
    }
    .bg-orb { position: fixed; border-radius: 50%; filter: blur(100px); pointer-events: none; z-index: 0; }
    .bg-orb-1 {
      width: 500px; height: 500px; background: rgba(108,99,255,0.10);
      top: -120px; left: -100px; animation: orbFloat 14s ease-in-out infinite alternate;
    }
    .bg-orb-2 {
      width: 400px; height: 400px; background: rgba(0,210,160,0.06);
      bottom: -100px; right: -80px; animation: orbFloat 18s ease-in-out infinite alternate-reverse;
    }
    @keyframes orbFloat {
      from { transform: translate(0,0) scale(1); }
      to   { transform: translate(40px,30px) scale(1.1); }
    }

    /* Layout wrapper */
    .wrapper {
      position: relative; z-index: 1;
      max-width: 1100px; margin: 0 auto; padding: 32px 32px 60px;
    }

    /* Nav */
    .topnav {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 40px; padding-bottom: 24px; border-bottom: 1px solid var(--border);
    }
    .nav-logo { display: flex; align-items: center; gap: 10px; }
    .logo-icon {
      width: 36px; height: 36px;
      background: linear-gradient(135deg, var(--accent), #a855f7);
      border-radius: 10px; display: flex; align-items: center; justify-content: center;
      font-size: 18px; box-shadow: 0 4px 20px var(--accent-glow);
    }
    .logo-name {
      font-size: 18px; font-weight: 700; letter-spacing: -0.4px;
      background: linear-gradient(90deg, #fff, var(--muted));
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    }
    .nav-actions { display: flex; align-items: center; gap: 12px; }
    .nav-badge { position: relative; }
    .nav-btn {
      background: var(--surface2); border: 1px solid var(--border);
      border-radius: 10px; color: var(--text); font-size: 18px;
      width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;
      cursor: pointer; transition: background 0.2s;
    }
    .nav-btn:hover { background: #222638; }
    .notif-dot {
      position: absolute; top: -2px; right: -2px;
      width: 9px; height: 9px; background: var(--accent);
      border-radius: 50%; border: 2px solid var(--bg);
    }
    .avatar {
      width: 38px; height: 38px;
      background: linear-gradient(135deg, var(--accent), #a855f7);
      border-radius: 50%; display: flex; align-items: center; justify-content: center;
      font-size: 15px; font-weight: 700; cursor: pointer;
      border: 2px solid var(--border); transition: border-color 0.2s;
    }
    .avatar:hover { border-color: var(--accent); }
    .btn-outline {
      background: transparent; border: 1px solid var(--border);
      border-radius: 10px; color: var(--muted); font-size: 13.5px; font-weight: 500;
      padding: 9px 18px; cursor: pointer;
      transition: color 0.2s, border-color 0.2s;
      display: flex; align-items: center; gap: 7px; text-decoration: none;
    }
    .btn-outline:hover { color: var(--text); border-color: var(--muted); }

    /* Welcome hero */
    .welcome-hero { margin-bottom: 40px; }
    .welcome-tag {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(108,99,255,0.12); border: 1px solid rgba(108,99,255,0.25);
      border-radius: 20px; padding: 5px 12px; font-size: 12px; font-weight: 600;
      color: var(--accent-h); letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 16px;
    }
    .welcome-title {
      font-size: clamp(28px, 4vw, 42px); font-weight: 800;
      letter-spacing: -1px; line-height: 1.15; margin-bottom: 12px;
    }
    .welcome-title span {
      background: linear-gradient(90deg, var(--accent), #a855f7, #ec4899);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    }
    .welcome-sub { color: var(--muted); font-size: 15.5px; max-width: 540px; line-height: 1.6; }

    /* Section label */
    .section-label {
      font-size: 11.5px; font-weight: 700; color: var(--muted);
      text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 14px;
    }

    /* Stats grid */
    .stats-grid {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 16px; margin-bottom: 32px;
    }
    .stat-card {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 14px; padding: 20px 22px;
      transition: border-color 0.2s, transform 0.2s;
    }
    .stat-card:hover { border-color: rgba(108,99,255,0.4); transform: translateY(-2px); }
    .stat-label { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.6px; font-weight: 600; margin-bottom: 8px; }
    .stat-value { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; }
    .stat-change { font-size: 12px; color: var(--success); margin-top: 4px; font-weight: 500; }
    .stat-change.neutral { color: var(--muted); }

    /* Two column */
    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

    /* Action cards */
    .actions-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .action-card {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 14px; padding: 22px; cursor: pointer;
      transition: border-color 0.2s, background 0.2s, transform 0.2s;
      display: flex; flex-direction: column; gap: 10px;
    }
    .action-card:hover { border-color: rgba(108,99,255,0.45); background: var(--surface2); transform: translateY(-2px); }
    .action-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
    .action-title { font-size: 14.5px; font-weight: 600; }
    .action-desc { font-size: 12.5px; color: var(--muted); line-height: 1.5; }

    /* Activity */
    .section-card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 24px; }
    .activity-list { display: flex; flex-direction: column; gap: 2px; }
    .activity-item {
      display: flex; align-items: center; gap: 14px;
      padding: 12px 10px; border-radius: 10px; transition: background 0.15s;
    }
    .activity-item:hover { background: var(--surface2); }
    .activity-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .activity-text { font-size: 13.5px; flex: 1; }
    .activity-time { font-size: 12px; color: var(--muted); white-space: nowrap; }

    @media (max-width: 700px) {
      .wrapper { padding: 20px 16px 48px; }
      .two-col { grid-template-columns: 1fr; }
      .topnav { flex-wrap: wrap; gap: 12px; }
    }
  </style>
</head>
<body>

<div class="bg-grid"></div>
<div class="bg-orb bg-orb-1"></div>
<div class="bg-orb bg-orb-2"></div>

<div class="wrapper">

  {{-- Nav --}}
  <nav class="topnav">
    <div class="nav-logo">
      <div class="logo-icon">⚡</div>
      <span class="logo-name">{{ config('app.name', 'Chirp') }}</span>
    </div>
    <div class="nav-actions">
      <div class="nav-badge">
        <div class="nav-btn" title="Notifications">🔔</div>
        <div class="notif-dot"></div>
      </div>
      <div class="nav-btn" title="Settings">⚙️</div>
      <div class="avatar" title="Profile">{{ strtoupper(substr($user['name'], 0, 1)) }}</div>
      <form method="POST" action="{{ route('logout') }}" style="margin:0;">
        @csrf
        <button type="submit" class="btn-outline">← Sign out</button>
      </form>
    </div>
  </nav>

  {{-- Hero --}}
  <div class="welcome-hero">
    <div class="welcome-tag">✦ You're in</div>
    <h1 class="welcome-title">
      Welcome back,<br/>
      <span>{{ $user['name'] }}!</span>
    </h1>
    <p class="welcome-sub">
      Here's what's been happening on your workspace. Pick up where you left off or start something new.
    </p>
  </div>

  {{-- Stats --}}
  <div class="section-label">Overview</div>
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Projects</div>
      <div class="stat-value">12</div>
      <div class="stat-change">▲ 2 this week</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Tasks done</div>
      <div class="stat-value">48</div>
      <div class="stat-change">▲ 6 today</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Team members</div>
      <div class="stat-value">7</div>
      <div class="stat-change neutral">3 online now</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Storage used</div>
      <div class="stat-value">4.2<span style="font-size:16px;font-weight:500;color:var(--muted)">GB</span></div>
      <div class="stat-change neutral">of 20 GB</div>
    </div>
  </div>

  {{-- Two column: actions + activity --}}
  <div class="two-col">
    <div>
      <div class="section-label">Quick Actions</div>
      <div class="actions-grid">
        <div class="action-card">
          <div class="action-icon" style="background:rgba(108,99,255,0.15)">📁</div>
          <div class="action-title">New Project</div>
          <div class="action-desc">Start a fresh workspace</div>
        </div>
        <div class="action-card">
          <div class="action-icon" style="background:rgba(0,210,160,0.12)">✅</div>
          <div class="action-title">My Tasks</div>
          <div class="action-desc">View open items</div>
        </div>
        <div class="action-card">
          <div class="action-icon" style="background:rgba(236,72,153,0.12)">📊</div>
          <div class="action-title">Analytics</div>
          <div class="action-desc">See your metrics</div>
        </div>
        <div class="action-card">
          <div class="action-icon" style="background:rgba(245,158,11,0.12)">👥</div>
          <div class="action-title">Invite Team</div>
          <div class="action-desc">Grow your team</div>
        </div>
        <a href="{{ route('maps') }}" class="action-card" style="text-decoration:none; color:inherit;">
          <div class="action-icon" style="background:rgba(0,210,160,0.12)">🗺️</div>
          <div class="action-title">Maps</div>
          <div class="action-desc">Open the map view</div>
        </a>
      </div>
    </div>

    <div class="section-card">
      <div class="section-label" style="margin-bottom:12px">Recent Activity</div>
      <div class="activity-list">
        <div class="activity-item">
          <div class="activity-dot" style="background:var(--accent)"></div>
          <div class="activity-text">Deployment <strong>v2.4.1</strong> completed</div>
          <div class="activity-time">2m ago</div>
        </div>
        <div class="activity-item">
          <div class="activity-dot" style="background:var(--success)"></div>
          <div class="activity-text">New member <strong>Sarah</strong> joined</div>
          <div class="activity-time">1h ago</div>
        </div>
        <div class="activity-item">
          <div class="activity-dot" style="background:#f59e0b"></div>
          <div class="activity-text">Report <strong>Q1 Review</strong> updated</div>
          <div class="activity-time">3h ago</div>
        </div>
        <div class="activity-item">
          <div class="activity-dot" style="background:#ec4899"></div>
          <div class="activity-text">Milestone <strong>Beta Launch</strong> reached</div>
          <div class="activity-time">Yesterday</div>
        </div>
        <div class="activity-item">
          <div class="activity-dot" style="background:var(--muted)"></div>
          <div class="activity-text">Task <strong>API integration</strong> closed</div>
          <div class="activity-time">2 days ago</div>
        </div>
      </div>
    </div>
  </div>

</div>

</body>
</html>
