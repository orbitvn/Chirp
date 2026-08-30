<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>Maps — {{ config('app.name', 'Chirp') }}</title>

  {{-- PWA: installable + offline drive mode --}}
  <link rel="manifest" href="{{ asset('manifest.webmanifest') }}" />
  <meta name="theme-color" content="#101320" />
  <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}" />
  <script>
    // Register the service worker (secure context only — https / localhost).
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('{{ asset('sw.js') }}')
          .then(reg => console.log('SW registered', reg.scope))
          .catch(err => console.warn('SW registration failed', err));
      });
    }
  </script>

  {{-- Leaflet (OpenStreetMap) — self-hosted so the service worker can cache it for offline drive mode --}}
  <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}" />
  <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>

  {{-- Leaflet-Geoman (free) — polyline draw + vertex/midpoint editing --}}
  <link rel="stylesheet" href="{{ asset('vendor/geoman/leaflet-geoman.css') }}" />
  <script src="{{ asset('vendor/geoman/leaflet-geoman.min.js') }}"></script>

  {{-- Turf.js — nearest-point-on-line / chainage math --}}
  <script src="{{ asset('vendor/turf/turf.min.js') }}"></script>

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
      --success:     #00d2a0;
    }

    html, body {
      height: 100%; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      background: var(--bg); color: var(--text);
    }

    .wrapper {
      max-width: 1100px; margin: 0 auto; padding: 32px 32px 40px;
      display: flex; flex-direction: column; min-height: 100vh;
    }

    /* Nav */
    .topnav {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 28px; padding-bottom: 24px; border-bottom: 1px solid var(--border);
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
    .btn-outline {
      background: transparent; border: 1px solid var(--border);
      border-radius: 10px; color: var(--muted); font-size: 13.5px; font-weight: 500;
      padding: 9px 18px; cursor: pointer; text-decoration: none;
      transition: color 0.2s, border-color 0.2s;
      display: inline-flex; align-items: center; gap: 7px;
    }
    .btn-outline:hover { color: var(--text); border-color: var(--muted); }

    /* Header */
    .page-head { margin-bottom: 16px; display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; }
    .page-title { font-size: 26px; font-weight: 800; letter-spacing: -0.6px; margin-bottom: 6px; }
    .page-sub { color: var(--muted); font-size: 14.5px; }

    /* Toolbar */
    .toolbar { display: flex; gap: 10px; flex-wrap: wrap; }
    .tool-btn {
      display: inline-flex; align-items: center; gap: 8px;
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 10px; color: var(--text); font-size: 13.5px; font-weight: 500;
      padding: 9px 15px; cursor: pointer; transition: border-color 0.2s, background 0.2s, color 0.2s;
    }
    .tool-btn:hover { border-color: var(--muted); background: var(--surface2); }
    .tool-btn .ico { font-size: 15px; }
    .tool-btn.active { border-color: var(--accent); color: #fff; background: rgba(108,99,255,0.18); box-shadow: 0 0 0 3px rgba(108,99,255,0.15); }

    /* Map */
    #map {
      flex: 1; min-height: 480px;
      border: 1px solid var(--border); border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 24px 48px rgba(0,0,0,0.4);
    }
    #map.placing { cursor: crosshair; }
    #map.road-hot { cursor: crosshair; }
    #map.fullscreen {
      position: fixed; inset: 0; z-index: 1000;
      border: none; border-radius: 0; min-height: 0; box-shadow: none;
    }
    /* Fullscreen toggle (Leaflet control) */
    .leaflet-control.fs-ctl a {
      display: flex; align-items: center; justify-content: center;
      width: 34px; height: 34px; font-size: 17px; line-height: 1;
      background: var(--surface2) !important; color: var(--text) !important;
      border: 1px solid var(--border) !important; border-radius: 8px; text-decoration: none;
    }
    .leaflet-control.fs-ctl a:hover { background: #222638 !important; border-color: var(--muted) !important; }
    .leaflet-control.fs-ctl a.active-fs { background: rgba(108,99,255,0.22) !important; border-color: var(--accent) !important; color: #fff !important; }
    .leaflet-control-attribution { background: rgba(21,24,32,0.85) !important; color: var(--muted) !important; }
    .leaflet-control-attribution a { color: var(--accent-h) !important; }
    .leaflet-bar a { background: var(--surface2) !important; color: var(--text) !important; border-color: var(--border) !important; }
    .leaflet-bar a:hover { background: #222638 !important; }
    .point-label {
      background: rgba(21,24,32,0.9); border: 1px solid var(--border);
      border-radius: 6px; color: var(--text); font-size: 12px; font-weight: 600;
      padding: 2px 7px; white-space: nowrap;
    }
    .point-label::before { display: none; }
    .coord-box {
      background: rgba(21,24,32,0.9); border: 1px solid var(--border);
      border-radius: 8px; color: var(--text); font-size: 12px; padding: 4px 9px;
      font-variant-numeric: tabular-nums; box-shadow: 0 4px 16px rgba(0,0,0,0.4);
    }
    .leaflet-control-scale-line {
      background: rgba(21,24,32,0.85) !important; color: var(--text) !important;
      border: 1px solid var(--muted) !important; border-top: none !important;
    }
    .measure-label {
      background: #f59e0b; border: none; border-radius: 6px; color: #1a1400;
      font-size: 12px; font-weight: 700; padding: 2px 7px; white-space: nowrap;
    }
    .measure-label::before { display: none; }
    .road-info {
      background: rgba(21,24,32,0.92); border: 1px solid var(--accent);
      border-radius: 10px; padding: 8px 12px; color: var(--text);
      box-shadow: 0 8px 24px rgba(0,0,0,0.45); max-width: 240px;
    }
    .road-info .ri-name { font-weight: 700; font-size: 13.5px; display: block; }
    .road-info .ri-ch { color: var(--accent-h); font-size: 12px; font-variant-numeric: tabular-nums; }
    .popup-del {
      margin-top: 8px; background: #fdecee; border: 1px solid #f2a9b0; color: #c0242f;
      border-radius: 8px; padding: 5px 10px; font-size: 12px; font-weight: 600; cursor: pointer;
    }
    .popup-del:hover { background: #f8d7db; }
    /* Road-history panel — mobileroads-style stacked info on the right */
    .works-panel {
      position: fixed; right: 8px; top: 8px; bottom: 8px; width: 340px;
      z-index: 1150; display: none; flex-direction: column;
      background: rgba(13,15,20,0.96); backdrop-filter: blur(6px);
      border: 1px solid var(--border); border-radius: 14px; overflow: hidden;
      color: var(--text); font-size: 13px; box-shadow: 0 12px 40px rgba(0,0,0,0.55);
    }
    .works-panel.show { display: flex; }
    .wp-head { padding: 14px 16px 12px; border-bottom: 1px solid var(--border); }
    .wp-pos { color: var(--accent-h); font-size: 15px; font-weight: 600; font-variant-numeric: tabular-nums; }
    .wp-name { display: block; font-weight: 800; font-size: 18px; letter-spacing: -0.3px; margin-top: 2px; line-height: 1.2; }
    .wp-body { overflow-y: auto; flex: 1; }
    .wp-section {
      background: var(--accent); color: #fff; font-weight: 700; font-size: 11.5px;
      letter-spacing: 0.5px; text-transform: uppercase; padding: 5px 16px;
    }
    .wp-row { padding: 9px 16px; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .wp-row .wp-date { font-weight: 700; font-variant-numeric: tabular-nums; }
    .wp-row .wp-detail { display: block; color: var(--muted); font-size: 12.5px; margin-top: 2px; line-height: 1.45; }
    .wp-empty { color: var(--muted); font-size: 12.5px; padding: 12px 16px; }
    .wp-foot { padding: 10px 16px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; gap: 8px; }
    .wp-export { color: var(--muted); font-size: 12px; text-decoration: none; border: 1px solid var(--border); border-radius: 8px; padding: 6px 12px; }
    .wp-export:hover { color: var(--text); border-color: var(--muted); }
    .works-panel button {
      background: transparent; border: 1px solid var(--border); color: var(--muted);
      border-radius: 8px; padding: 6px 14px; font-size: 12px; cursor: pointer;
    }
    .works-panel button:hover { color: var(--text); border-color: var(--muted); }

    /* Forward Works Programme block */
    .fwp-block { border-bottom: 1px solid var(--border); }
    .fwp-current { display: flex; gap: 10px; padding: 10px 16px 6px; }
    .fwp-cell { flex: 1; background: var(--surface2); border: 1px solid var(--border); border-radius: 10px; padding: 8px 12px; }
    .fwp-label { display: block; color: var(--muted); font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.5px; }
    .fwp-val { display: block; font-weight: 800; font-size: 20px; font-variant-numeric: tabular-nums; margin-top: 2px; }
    .fwp-val .fwp-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; vertical-align: middle; }
    .fwp-was { color: var(--accent-h); font-size: 12px; padding: 0 16px; min-height: 16px; }
    .fwp-was.changed { color: var(--success); }
    .fwp-btns { display: flex; gap: 6px; padding: 8px 16px; }
    .fwp-btns .fwp-y, .fwp-btns .fwp-t, .fwp-btns .fwp-u { flex: 1; }
    .works-panel .fwp-y { color: var(--text); border-color: var(--border); font-weight: 700; }
    .works-panel .fwp-y:hover { border-color: var(--accent); }
    .works-panel .fwp-u { color: var(--danger); border-color: rgba(255,95,109,0.4); }

    body.drive-mode .fwp-current { padding: 14px 24px 8px; }
    body.drive-mode .fwp-val { font-size: 30px; }
    body.drive-mode .fwp-label { font-size: 12px; }
    body.drive-mode .fwp-was { font-size: 15px; padding: 0 24px; }
    body.drive-mode .fwp-btns { padding: 10px 24px; gap: 10px; }
    body.drive-mode .works-panel .fwp-y,
    body.drive-mode .works-panel .fwp-t,
    body.drive-mode .works-panel .fwp-u { font-size: 20px; padding: 18px 0; border-radius: 12px; }

    .fwp-legend { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 8px 10px; color: var(--text); box-shadow: 0 8px 24px rgba(0,0,0,0.5); }
    .fwp-legend .fl-title { font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); margin-bottom: 5px; }
    .fwp-legend .fl-scale { display: flex; }
    .fwp-legend .fl-scale span { font-size: 10px; font-weight: 700; color: #0d0f14; padding: 3px 6px; font-variant-numeric: tabular-nums; }
    .fwp-legend .fl-scale span:first-child { border-radius: 5px 0 0 5px; }
    .fwp-legend .fl-scale span:last-child { border-radius: 0 5px 5px 0; }

    /* Drive mode (GPS follow): drop the map entirely, show a full-screen live
       text readout that updates with position — no basemap tiles needed. */
    body.drive-mode #map { display: none; }
    body.drive-mode .topnav, body.drive-mode .page-head { display: none; }
    body.drive-mode .works-panel { inset: 0; width: auto; border: none; border-radius: 0; }
    body.drive-mode .wp-head { padding: 22px 24px 18px; }
    body.drive-mode .wp-pos { font-size: 21px; }
    body.drive-mode .wp-name { font-size: 36px; line-height: 1.1; }
    body.drive-mode .wp-section { font-size: 14px; padding: 10px 24px; }
    body.drive-mode .wp-row { padding: 17px 24px; }
    body.drive-mode .wp-row .wp-date { font-size: 19px; }
    body.drive-mode .wp-row .wp-detail { font-size: 15.5px; margin-top: 4px; }
    body.drive-mode .wp-empty { font-size: 15px; padding: 18px 24px; }
    body.drive-mode .wp-foot { padding: 16px 24px; }
    body.drive-mode .works-panel button { font-size: 15px; padding: 11px 22px; }

    /* Placement banner */
    .place-banner {
      position: fixed; left: 50%; top: 20px; transform: translateX(-50%);
      z-index: 1200; display: none; align-items: center; gap: 14px;
      background: var(--surface); border: 1px solid var(--accent);
      border-radius: 12px; padding: 12px 18px; color: var(--text);
      font-size: 14px; box-shadow: 0 12px 40px rgba(0,0,0,0.5), 0 0 30px var(--accent-glow);
    }
    .place-banner.show { display: flex; }
    .place-banner .dot { width: 9px; height: 9px; border-radius: 50%; background: var(--accent); animation: pulse 1.2s ease-in-out infinite; }
    @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }
    .place-banner button {
      background: transparent; border: 1px solid var(--border); color: var(--muted);
      border-radius: 8px; padding: 5px 12px; font-size: 12.5px; cursor: pointer;
    }
    .place-banner button:hover { color: var(--text); border-color: var(--muted); }

    /* Modal */
    .modal-overlay {
      position: fixed; inset: 0; z-index: 1300;
      background: rgba(5,6,10,0.7); backdrop-filter: blur(3px);
      display: none; align-items: center; justify-content: center; padding: 24px;
    }
    .modal-overlay.show { display: flex; }
    .modal {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 18px; padding: 28px 30px 30px; width: 100%; max-width: 420px;
      box-shadow: 0 32px 64px rgba(0,0,0,0.55), 0 0 60px var(--accent-glow);
    }
    .modal h2 { font-size: 20px; font-weight: 700; letter-spacing: -0.4px; margin-bottom: 4px; }
    .modal .modal-sub { color: var(--muted); font-size: 13px; margin-bottom: 22px; }
    .form-group { margin-bottom: 16px; }
    .form-row { display: flex; gap: 14px; }
    .form-row .form-group { flex: 1; }
    label { display: block; font-size: 13px; font-weight: 500; color: var(--muted); margin-bottom: 7px; }
    input[type="text"], input[type="number"], textarea {
      width: 100%; padding: 11px 13px; background: var(--surface2);
      border: 1px solid var(--border); border-radius: 10px; color: var(--text);
      font-size: 14px; outline: none; font-family: inherit;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    input:focus, textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(108,99,255,0.18); }
    textarea { resize: vertical; min-height: 64px; }
    input.is-invalid { border-color: var(--danger); }
    .field-error { color: var(--danger); font-size: 12px; margin-top: 5px; display: none; }
    .field-error.show { display: block; }

    .color-row { display: flex; align-items: center; gap: 10px; }
    .swatches { display: flex; gap: 8px; flex-wrap: wrap; }
    .swatch {
      width: 26px; height: 26px; border-radius: 50%; cursor: pointer;
      border: 2px solid transparent; transition: transform 0.12s, border-color 0.12s;
    }
    .swatch:hover { transform: scale(1.12); }
    .swatch.selected { border-color: #fff; }
    input[type="color"] {
      width: 40px; height: 32px; padding: 0; border: 1px solid var(--border);
      border-radius: 8px; background: var(--surface2); cursor: pointer;
    }

    .modal-actions { display: flex; gap: 12px; margin-top: 24px; }
    .btn-primary {
      flex: 1; padding: 12px; background: linear-gradient(135deg, var(--accent), #8b5cf6);
      border: none; border-radius: 10px; color: #fff; font-size: 14.5px; font-weight: 600;
      cursor: pointer; box-shadow: 0 6px 24px var(--accent-glow); transition: opacity 0.2s, transform 0.15s;
    }
    .btn-primary:hover { opacity: 0.92; transform: translateY(-1px); }
    .btn-ghost {
      padding: 12px 18px; background: var(--surface2); border: 1px solid var(--border);
      border-radius: 10px; color: var(--muted); font-size: 14px; cursor: pointer; transition: color 0.2s;
    }
    .btn-ghost:hover { color: var(--text); }

    /* Toast */
    .toast {
      position: fixed; right: 24px; bottom: 24px; z-index: 1400;
      background: var(--surface); border: 1px solid var(--border); border-left: 3px solid var(--accent);
      border-radius: 10px; padding: 13px 18px; color: var(--text); font-size: 13.5px;
      box-shadow: 0 12px 40px rgba(0,0,0,0.5); opacity: 0; transform: translateY(10px);
      transition: opacity 0.25s, transform 0.25s; pointer-events: none;
    }
    .toast.show { opacity: 1; transform: translateY(0); }
    .toast.error { border-left-color: var(--danger); }

    @media (max-width: 700px) {
      .topnav { flex-wrap: wrap; gap: 12px; }
      .page-head { align-items: flex-start; }
    }

    /* ---- Full-bleed map: map fills the window, chrome floats over it ---- */
    .wrapper { max-width: none; margin: 0; padding: 0; min-height: 100vh; display: block; }
    #map {
      position: fixed; inset: 0; min-height: 0; z-index: 0;
      border: none; border-radius: 0; box-shadow: none;
    }
    .topnav {
      position: fixed; top: 8px; left: 8px; z-index: 1100; margin: 0;
      padding: 7px 13px; gap: 16px; border-bottom: none;
      background: rgba(21,24,32,0.92); backdrop-filter: blur(6px);
      border: 1px solid var(--border); border-radius: 12px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.45);
    }
    .page-head { position: fixed; top: 58px; left: 8px; z-index: 1100; margin: 0; display: block; }
    .page-head > div:first-child { display: none; }   /* hide the "Maps" title text */
    .toolbar {
      padding: 7px; gap: 7px;
      background: rgba(21,24,32,0.92); backdrop-filter: blur(6px);
      border: 1px solid var(--border); border-radius: 12px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.45);
    }
    .leaflet-top { top: 108px; }   /* push zoom / controls below the floating toolbar */
  </style>
</head>
<body>

<div class="wrapper">

  {{-- Nav --}}
  <nav class="topnav">
    <div class="nav-logo">
      <div class="logo-icon">⚡</div>
      <span class="logo-name">{{ config('app.name', 'Chirp') }}</span>
    </div>
    <a href="{{ route('dashboard') }}" class="btn-outline">← Back to dashboard</a>
  </nav>

  {{-- Header + toolbar --}}
  <div class="page-head">
    <div>
      <h1 class="page-title">Maps</h1>
      <p class="page-sub">Right-click to add a point. Use the toolbar to draw or edit lines.</p>
    </div>
    <div class="toolbar">
      <button type="button" class="tool-btn active" id="btnRoads"><span class="ico">🛣️</span> Roads</button>
      <button type="button" class="tool-btn" id="btnAddPoint"><span class="ico">📍</span> Add point</button>
      <button type="button" class="tool-btn" id="btnAddLine"><span class="ico">📈</span> Add line</button>
      <button type="button" class="tool-btn" id="btnEditLines"><span class="ico">✎</span> Edit lines</button>
      <button type="button" class="tool-btn" id="btnMeasure"><span class="ico">📏</span> Measure</button>
      <button type="button" class="tool-btn" id="btnWorks"><span class="ico">🛠️</span> Road history</button>
      <button type="button" class="tool-btn" id="btnGps"><span class="ico">🧭</span> Follow GPS</button>
      <button type="button" class="tool-btn" id="btnFullscreen"><span class="ico">⛶</span> <span class="fs-label">Full screen</span></button>
    </div>
  </div>

  {{-- Map --}}
  <div id="map"></div>

</div>

{{-- Placement banner (points) --}}
<div class="place-banner" id="placeBanner">
  <span class="dot"></span>
  <span>Click on the map to place <strong id="placeName">your point</strong></span>
  <button type="button" id="cancelPlace">Cancel</button>
</div>

{{-- Drawing banner (lines) --}}
<div class="place-banner" id="drawBanner">
  <span class="dot"></span>
  <span>Click to add nodes for <strong id="drawName">your line</strong> — double-click the last node to finish</span>
  <button type="button" id="cancelDraw">Cancel</button>
</div>

{{-- Edit banner (lines) --}}
<div class="place-banner" id="editBanner" style="border-color: var(--success);">
  <span class="dot" style="background: var(--success);"></span>
  <span>Editing lines — drag a node to move it, or drag a mid-point to add a node</span>
  <button type="button" id="doneEdit">Done</button>
</div>

{{-- Road history (RAMM surfacing on the hovered treatment length) panel --}}
<div class="works-panel" id="worksPanel">
  <div class="wp-head">
    <span class="wp-pos" id="wpPos"></span>
    <span class="wp-name" id="wpName">Road</span>
  </div>
  <div class="fwp-block" id="fwpBlock" style="display:none;">
    <div class="wp-section">Forward Works Programme</div>
    <div class="fwp-current">
      <div class="fwp-cell"><span class="fwp-label">Year</span><span class="fwp-val" id="fwpYear">—</span></div>
      <div class="fwp-cell"><span class="fwp-label">Treatment</span><span class="fwp-val" id="fwpTmt">—</span></div>
    </div>
    <div class="fwp-was" id="fwpWas"></div>
    <div class="fwp-btns" id="fwpYearBtns">
      <button type="button" class="fwp-y" data-dy="-3">−3</button>
      <button type="button" class="fwp-y" data-dy="-2">−2</button>
      <button type="button" class="fwp-y" data-dy="-1">−1</button>
      <button type="button" class="fwp-y" data-dy="1">+1</button>
      <button type="button" class="fwp-y" data-dy="2">+2</button>
      <button type="button" class="fwp-y" data-dy="3">+3</button>
    </div>
    <div class="fwp-btns">
      <button type="button" id="fwpTmtPrev" class="fwp-t">◀ Treatment</button>
      <button type="button" id="fwpTmtNext" class="fwp-t">Treatment ▶</button>
      <button type="button" id="fwpUndo" class="fwp-u">Undo</button>
    </div>
  </div>
  <div class="wp-body">
    <div class="wp-section" id="wpSection">Surfacing history</div>
    <div id="wpBody"></div>
    <div class="wp-empty" id="wpEmpty" style="display:none;">No surfacing history on this length.</div>
  </div>
  <div class="wp-foot">
    <button type="button" id="wpClear">Close</button>
    <a href="{{ route('fwp.changes.csv') }}" id="fwpExport" class="wp-export">Export changes</a>
  </div>
</div>

{{-- Measure banner --}}
<div class="place-banner" id="measureBanner" style="border-color: #f59e0b;">
  <span class="dot" style="background: #f59e0b;"></span>
  <span>Click points to measure — total: <strong id="measureTotal">0 m</strong> — double-click to finish</span>
  <button type="button" id="clearMeasureBtn">Clear</button>
</div>

{{-- Add-point modal --}}
<div class="modal-overlay" id="pointModal">
  <div class="modal">
    <h2>Add point</h2>
    <p class="modal-sub">Fill in the details, then click the map to drop it.</p>

    <div class="form-group">
      <label for="pName">Name</label>
      <input type="text" id="pName" placeholder="e.g. Head office" maxlength="120" />
      <div class="field-error" id="errPName">Name is required.</div>
    </div>
    <div class="form-group">
      <label for="pDesc">Description</label>
      <textarea id="pDesc" placeholder="Optional notes about this point" maxlength="1000"></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="pValue">Value</label>
        <input type="number" id="pValue" placeholder="e.g. 42" step="any" />
      </div>
      <div class="form-group">
        <label>Color</label>
        <div class="color-row"><input type="color" id="pColor" value="#6c63ff" /></div>
      </div>
    </div>
    <div class="form-group">
      <div class="swatches" data-target="pColor">
        <span class="swatch selected" data-color="#6c63ff" style="background:#6c63ff"></span>
        <span class="swatch" data-color="#00d2a0" style="background:#00d2a0"></span>
        <span class="swatch" data-color="#f59e0b" style="background:#f59e0b"></span>
        <span class="swatch" data-color="#ec4899" style="background:#ec4899"></span>
        <span class="swatch" data-color="#ff5f6d" style="background:#ff5f6d"></span>
        <span class="swatch" data-color="#38bdf8" style="background:#38bdf8"></span>
      </div>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn-ghost" data-close="pointModal">Cancel</button>
      <button type="button" class="btn-primary" id="pointNext">Next: place on map →</button>
    </div>
  </div>
</div>

{{-- Add-line modal --}}
<div class="modal-overlay" id="lineModal">
  <div class="modal">
    <h2>Add line</h2>
    <p class="modal-sub">Fill in the details, then click nodes on the map to draw the line.</p>

    <div class="form-group">
      <label for="lName">Name</label>
      <input type="text" id="lName" placeholder="e.g. Delivery route" maxlength="120" />
      <div class="field-error" id="errLName">Name is required.</div>
    </div>
    <div class="form-group">
      <label for="lDesc">Description</label>
      <textarea id="lDesc" placeholder="Optional notes about this line" maxlength="1000"></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="lValue">Value</label>
        <input type="number" id="lValue" placeholder="e.g. 42" step="any" />
      </div>
      <div class="form-group">
        <label>Color</label>
        <div class="color-row"><input type="color" id="lColor" value="#00d2a0" /></div>
      </div>
    </div>
    <div class="form-group">
      <div class="swatches" data-target="lColor">
        <span class="swatch selected" data-color="#00d2a0" style="background:#00d2a0"></span>
        <span class="swatch" data-color="#6c63ff" style="background:#6c63ff"></span>
        <span class="swatch" data-color="#f59e0b" style="background:#f59e0b"></span>
        <span class="swatch" data-color="#ec4899" style="background:#ec4899"></span>
        <span class="swatch" data-color="#ff5f6d" style="background:#ff5f6d"></span>
        <span class="swatch" data-color="#38bdf8" style="background:#38bdf8"></span>
      </div>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn-ghost" data-close="lineModal">Cancel</button>
      <button type="button" class="btn-primary" id="lineNext">Next: draw on map →</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
  const CSRF        = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  const POINTS_URL  = @json(route('points.index'));
  const POINTS_POST = @json(route('points.store'));
  const LINES_URL   = @json(route('lines.index'));   // base for /maps/lines and /maps/lines/{id}

  const mapEl = document.getElementById('map');

  // --- Map setup: Hawke's Bay, NZ (Napier–Hastings) ---
  const map = L.map('map').setView([-39.5661, 176.8750], 11);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);

  // Scale bar (metric) — shows real ground distance for the current zoom.
  L.control.scale({ metric: true, imperial: false, position: 'bottomleft', maxWidth: 160 }).addTo(map);

  // Live cursor coordinate readout.
  const CoordControl = L.Control.extend({
    options: { position: 'bottomright' },
    onAdd() { this._div = L.DomUtil.create('div', 'coord-box'); this._div.innerHTML = 'lat, lng'; return this._div; }
  });
  const coordCtl = new CoordControl();
  map.addControl(coordCtl);
  map.on('mousemove', e => { coordCtl._div.innerHTML = e.latlng.lat.toFixed(5) + ', ' + e.latlng.lng.toFixed(5); });

  // We manage our own toolbar, so hide Geoman's default controls.
  map.pm.setGlobalOptions({ allowSelfIntersection: true });

  // --- Fullscreen: expand the map to fill the viewport ---
  const mapContainer = document.getElementById('map');
  let isFullscreen = false;
  let fsCtlLink = null;

  const FullscreenControl = L.Control.extend({
    options: { position: 'topleft' },
    onAdd() {
      const wrap = L.DomUtil.create('div', 'leaflet-bar fs-ctl');
      fsCtlLink = L.DomUtil.create('a', '', wrap);
      fsCtlLink.href = '#';
      fsCtlLink.innerHTML = '⛶';
      fsCtlLink.title = 'Full screen';
      L.DomEvent.on(fsCtlLink, 'click', L.DomEvent.stop).on(fsCtlLink, 'click', () => toggleFullscreen());
      return wrap;
    }
  });
  map.addControl(new FullscreenControl());

  function setFullscreen(on) {
    isFullscreen = on;
    mapContainer.classList.toggle('fullscreen', on);
    const btn = document.getElementById('btnFullscreen');
    btn.classList.toggle('active', on);
    btn.querySelector('.fs-label').textContent = on ? 'Exit full screen' : 'Full screen';
    if (fsCtlLink) {
      fsCtlLink.classList.toggle('active-fs', on);
      fsCtlLink.title = on ? 'Exit full screen (Esc)' : 'Full screen';
    }
    setTimeout(() => map.invalidateSize(), 210);
  }
  function toggleFullscreen() { setFullscreen(!isFullscreen); }

  document.getElementById('btnFullscreen').addEventListener('click', () => toggleFullscreen());
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && isFullscreen) setFullscreen(false); });

  const esc = (s) => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const fmtVal = (v) => (v !== null && v !== undefined && v !== '') ? Number(v).toLocaleString() : null;

  // Real ground distance helpers (uses Earth's curvature via map.distance).
  const fmtDistance = (m) => m >= 1000 ? (m / 1000).toFixed(2) + ' km' : Math.round(m) + ' m';
  function polylineLength(latlngs) {
    let total = 0;
    for (let i = 1; i < latlngs.length; i++) total += map.distance(L.latLng(latlngs[i - 1]), L.latLng(latlngs[i]));
    return total;
  }

  // ---------- Toast ----------
  const toast = document.getElementById('toast');
  let toastTimer;
  function showToast(msg, isError) {
    toast.textContent = msg;
    toast.classList.toggle('error', !!isError);
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
  }

  // ---------- Render points ----------
  const pointMarkers = new Map();   // id -> marker
  function renderPoint(p) {
    const valueTxt = fmtVal(p.value);
    const marker = L.circleMarker([p.lat, p.lng], {
      radius: 9, color: p.color, fillColor: p.color, fillOpacity: 0.85, weight: 2,
    }).addTo(map);
    marker._dbId = p.id;
    pointMarkers.set(p.id, marker);
    marker.bindTooltip(valueTxt !== null ? valueTxt : p.name, {
      permanent: true, direction: 'top', offset: [0, -8], className: 'point-label'
    });
    let html = '<b>' + esc(p.name) + '</b>';
    if (valueTxt !== null) html += '<br>Value: <b>' + esc(valueTxt) + '</b>';
    if (p.description) html += '<br>' + esc(p.description);
    html += '<div><button class="popup-del" data-kind="point" data-id="' + p.id + '">Delete point</button></div>';
    marker.bindPopup(html);
    return marker;
  }

  // ---------- Render lines ----------
  const lineLayers = [];
  function lineDetailHtml(l, lengthMeters) {
    const valueTxt = fmtVal(l.value);
    let html = '<b>' + esc(l.name) + '</b>';
    if (valueTxt !== null) html += '<br>Value: <b>' + esc(valueTxt) + '</b>';
    html += '<br>Length: <b>' + fmtDistance(lengthMeters) + '</b>';
    if (l.description) html += '<br>' + esc(l.description);
    html += '<div><button class="popup-del" data-kind="line" data-id="' + l.id + '">Delete line</button></div>';
    return html;
  }
  function updateLinePopup(poly) {
    poly.setPopupContent(lineDetailHtml(poly._meta, polylineLength(poly.getLatLngs())));
  }
  function renderLine(l) {
    const latlngs = (l.coordinates || []).map(c => [c[0], c[1]]);
    const poly = L.polyline(latlngs, { color: l.color, weight: 4, opacity: 0.85 }).addTo(map);
    poly._dbId = l.id;
    poly._meta = l;
    poly.bindPopup(lineDetailHtml(l, polylineLength(poly.getLatLngs())));
    // Save geometry + refresh length whenever the shape is edited
    poly.on('pm:edit', () => { saveLineGeometry(poly); updateLinePopup(poly); });
    lineLayers.push(poly);
    return poly;
  }

  // ---------- Load everything from the DB ----------
  async function loadAll() {
    const allLatLngs = [];
    try {
      const [pRes, lRes] = await Promise.all([
        fetch(POINTS_URL, { headers: { 'Accept': 'application/json' } }),
        fetch(LINES_URL,  { headers: { 'Accept': 'application/json' } }),
      ]);
      if (pRes.ok) {
        (await pRes.json()).forEach(p => { renderPoint(p); allLatLngs.push([p.lat, p.lng]); });
      }
      if (lRes.ok) {
        (await lRes.json()).forEach(l => {
          renderLine(l);
          (l.coordinates || []).forEach(c => allLatLngs.push([c[0], c[1]]));
        });
      }
    } catch (e) { /* ignore load errors */ }

    if (allLatLngs.length === 1) map.setView(allLatLngs[0], 14);
    else if (allLatLngs.length > 1) map.fitBounds(L.latLngBounds(allLatLngs), { padding: [50, 50], maxZoom: 15 });
  }
  loadAll();

  // ---------- Shared modal helpers ----------
  function closeModal(id) { document.getElementById(id).classList.remove('show'); }
  document.querySelectorAll('[data-close]').forEach(b =>
    b.addEventListener('click', () => closeModal(b.dataset.close)));
  document.querySelectorAll('.modal-overlay').forEach(ov =>
    ov.addEventListener('click', e => { if (e.target === ov) ov.classList.remove('show'); }));

  // Swatches drive their paired color input
  document.querySelectorAll('.swatches').forEach(group => {
    const input = document.getElementById(group.dataset.target);
    group.addEventListener('click', e => {
      const sw = e.target.closest('.swatch');
      if (!sw) return;
      input.value = sw.dataset.color;
      group.querySelectorAll('.swatch').forEach(s => s.classList.remove('selected'));
      sw.classList.add('selected');
    });
    input.addEventListener('input', () => {
      group.querySelectorAll('.swatch').forEach(s =>
        s.classList.toggle('selected', s.dataset.color.toLowerCase() === input.value.toLowerCase()));
    });
  });

  // ============================================================
  //  POINTS: right-click / button -> modal -> click to place
  // ============================================================
  const pName = document.getElementById('pName');
  const pDesc = document.getElementById('pDesc');
  const pValue = document.getElementById('pValue');
  const pColor = document.getElementById('pColor');
  const errPName = document.getElementById('errPName');
  const placeBanner = document.getElementById('placeBanner');
  const placeName = document.getElementById('placeName');

  let pendingPoint = null, placing = false;

  function openPointModal() {
    if (measuring || measureLayer) clearMeasure();
    pName.value = ''; pDesc.value = ''; pValue.value = ''; pColor.value = '#6c63ff';
    errPName.classList.remove('show'); pName.classList.remove('is-invalid');
    const g = document.querySelector('.swatches[data-target="pColor"]');
    g.querySelectorAll('.swatch').forEach((s, i) => s.classList.toggle('selected', i === 0));
    document.getElementById('pointModal').classList.add('show');
    setTimeout(() => pName.focus(), 50);
  }
  document.getElementById('btnAddPoint').addEventListener('click', openPointModal);
  map.on('contextmenu', e => { e.originalEvent.preventDefault(); if (!placing && !drawing && !editing && !measuring) openPointModal(); });

  document.getElementById('pointNext').addEventListener('click', () => {
    const name = pName.value.trim();
    if (!name) { errPName.classList.add('show'); pName.classList.add('is-invalid'); pName.focus(); return; }
    pendingPoint = { name, description: pDesc.value.trim(), value: pValue.value === '' ? null : pValue.value, color: pColor.value };
    closeModal('pointModal');
    placing = true; placeName.textContent = name; placeBanner.classList.add('show'); mapEl.classList.add('placing');
  });
  function stopPlacing() { placing = false; pendingPoint = null; placeBanner.classList.remove('show'); mapEl.classList.remove('placing'); }
  document.getElementById('cancelPlace').addEventListener('click', stopPlacing);

  map.on('click', async e => {
    if (!placing || !pendingPoint) return;
    const payload = { ...pendingPoint, lat: e.latlng.lat, lng: e.latlng.lng };
    try {
      const res = await fetch(POINTS_POST, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify(payload),
      });
      if (!res.ok) { showToast('Could not save point.', true); return; }
      const saved = await res.json();
      renderPoint(saved);
      showToast('Point "' + saved.name + '" added.');
    } catch (err) { showToast('Network error saving point.', true); }
    finally { stopPlacing(); }
  });

  // ============================================================
  //  LINES: button -> modal -> draw nodes -> save; plus edit mode
  // ============================================================
  const lName = document.getElementById('lName');
  const lDesc = document.getElementById('lDesc');
  const lValue = document.getElementById('lValue');
  const lColor = document.getElementById('lColor');
  const errLName = document.getElementById('errLName');
  const drawBanner = document.getElementById('drawBanner');
  const drawName = document.getElementById('drawName');

  let pendingLine = null, drawing = false, editing = false;

  function openLineModal() {
    if (measuring || measureLayer) clearMeasure();
    lName.value = ''; lDesc.value = ''; lValue.value = ''; lColor.value = '#00d2a0';
    errLName.classList.remove('show'); lName.classList.remove('is-invalid');
    const g = document.querySelector('.swatches[data-target="lColor"]');
    g.querySelectorAll('.swatch').forEach((s, i) => s.classList.toggle('selected', i === 0));
    document.getElementById('lineModal').classList.add('show');
    setTimeout(() => lName.focus(), 50);
  }
  document.getElementById('btnAddLine').addEventListener('click', () => { if (editing) toggleEdit(); openLineModal(); });

  document.getElementById('lineNext').addEventListener('click', () => {
    const name = lName.value.trim();
    if (!name) { errLName.classList.add('show'); lName.classList.add('is-invalid'); lName.focus(); return; }
    pendingLine = { name, description: lDesc.value.trim(), value: lValue.value === '' ? null : lValue.value, color: lColor.value };
    closeModal('lineModal');
    startDrawing(name);
  });

  function startDrawing(name) {
    drawing = true;
    drawName.textContent = name;
    drawBanner.classList.add('show');
    document.getElementById('btnAddLine').classList.add('active');
    map.pm.enableDraw('Line', {
      templineStyle: { color: pendingLine.color, weight: 4 },
      hintlineStyle: { color: pendingLine.color, dashArray: '5,5' },
      pathOptions:  { color: pendingLine.color, weight: 4, opacity: 0.85 },
      finishOn: 'dblclick',
    });
  }
  function stopDrawing() {
    drawing = false;
    drawBanner.classList.remove('show');
    document.getElementById('btnAddLine').classList.remove('active');
    if (map.pm.globalDrawModeEnabled && map.pm.globalDrawModeEnabled()) map.pm.disableDraw();
  }
  document.getElementById('cancelDraw').addEventListener('click', () => {
    map.pm.disableDraw();
    stopDrawing();
  });

  // Fired when a shape finishes drawing
  map.on('pm:create', async e => {
    if (e.shape !== 'Line' || !pendingLine) { return; }
    const layer = e.layer;
    const coords = layer.getLatLngs().map(ll => [ll.lat, ll.lng]);
    map.removeLayer(layer); // remove the raw geoman layer; re-add via renderLine after save

    const meta = pendingLine;
    pendingLine = null; stopDrawing();

    if (coords.length < 2) { showToast('A line needs at least 2 nodes.', true); return; }

    try {
      const res = await fetch(LINES_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ ...meta, coordinates: coords }),
      });
      if (!res.ok) { showToast('Could not save line.', true); return; }
      const saved = await res.json();
      renderLine(saved);
      showToast('Line "' + saved.name + '" added.');
    } catch (err) { showToast('Network error saving line.', true); }
  });

  // ---- Edit mode: reshape existing lines ----
  const editBanner = document.getElementById('editBanner');
  function toggleEdit() {
    editing = !editing;
    document.getElementById('btnEditLines').classList.toggle('active', editing);
    editBanner.classList.toggle('show', editing);
    lineLayers.forEach(l => { editing ? l.pm.enable({ allowSelfIntersection: true }) : l.pm.disable(); });
    if (!editing) showToast('Line changes saved.');
  }
  document.getElementById('btnEditLines').addEventListener('click', toggleEdit);
  document.getElementById('doneEdit').addEventListener('click', () => { if (editing) toggleEdit(); });

  // Persist a reshaped line's geometry
  const saveTimers = new WeakMap();
  async function saveLineGeometry(poly) {
    if (!poly._dbId) return;
    clearTimeout(saveTimers.get(poly));
    saveTimers.set(poly, setTimeout(async () => {
      const coords = poly.getLatLngs().map(ll => [ll.lat, ll.lng]);
      try {
        const res = await fetch(LINES_URL + '/' + poly._dbId, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
          body: JSON.stringify({ coordinates: coords }),
        });
        if (!res.ok) showToast('Could not save line change.', true);
      } catch (err) { showToast('Network error saving line change.', true); }
    }, 400));
  }

  // ============================================================
  //  ROADS: LINZ centrelines + hover (road name + chainage)
  // ============================================================
  map.createPane('roadsPane');
  map.getPane('roadsPane').style.zIndex = 320;   // above tiles (200), below points/lines (400)

  // HDC OpenData_RoadCentrelines, served live as WGS84 GeoJSON (road_id = RAMM road id).
  const ROADS_URL = 'https://services1.arcgis.com/8L3DQUzjrkgEmDpQ/arcgis/rest/services/OpenData_RoadCentrelines/FeatureServer/1/query'
    + '?where=1%3D1&outFields=road_id%2CRoadname&outSR=4326&f=geojson';
  const ROAD_STYLE = { color: '#9aa3bf', weight: 2, opacity: 0.55 };
  const ROAD_HOVER = { color: '#6c63ff', weight: 5, opacity: 1 };

  let roadsLayer = null;
  let roadsIndex = [];            // [{ layer, feature, bbox:[minX,minY,maxX,maxY] }]
  let roadsVisible = true;
  let hoveredRoad = null;

  // Info box (top-right) showing the hovered road + chainage
  const roadInfo = L.control({ position: 'topright' });
  roadInfo.onAdd = function () { this._d = L.DomUtil.create('div', 'road-info'); this._d.style.display = 'none'; return this._d; };
  roadInfo.addTo(map);
  function hideRoadInfo() { roadInfo._d.style.display = 'none'; }

  // ============================================================
  //  OFFLINE DATA: RAMM roads + works cached in IndexedDB so drive
  //  mode works with zero coverage. Network stays primary; this is
  //  the fallback for road detection and works/line lookups.
  // ============================================================
  const RammOffline = (() => {
    const DB = 'chirp-ramm', DB_VER = 1, STORE = 'roads', META = 'meta';
    const BUNDLE_URL  = @json(asset('data/ramm-offline.json'));
    const VERSION_URL = @json(asset('data/ramm-offline.version.json'));
    let db = null, version = null, treatments = [];

    function open() {
      return new Promise((resolve, reject) => {
        const rq = indexedDB.open(DB, DB_VER);
        rq.onupgradeneeded = () => {
          const d = rq.result;
          if (!d.objectStoreNames.contains(STORE)) d.createObjectStore(STORE, { keyPath: 'road_id' });
          if (!d.objectStoreNames.contains(META))  d.createObjectStore(META);
        };
        rq.onsuccess = () => resolve(rq.result);
        rq.onerror   = () => reject(rq.error);
      });
    }
    const os = (store, mode) => db.transaction(store, mode).objectStore(store);
    const getMeta = k => new Promise(r => { const q = os(META,'readonly').get(k); q.onsuccess = () => r(q.result); q.onerror = () => r(undefined); });
    const putMeta = (k,v) => new Promise(r => { const q = os(META,'readwrite').put(v,k); q.onsuccess = () => r(); q.onerror = () => r(); });
    const count   = () => new Promise(r => { const q = os(STORE,'readonly').count(); q.onsuccess = () => r(q.result); q.onerror = () => r(0); });

    async function download() {
      const res = await fetch(BUNDLE_URL, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error('bundle HTTP ' + res.status);
      const data = await res.json();
      const entries = Object.values(data.roads || {});
      await new Promise((resolve, reject) => {
        const t = db.transaction(STORE, 'readwrite');
        const store = t.objectStore(STORE);
        store.clear();
        for (const r of entries) store.put(r);
        t.oncomplete = resolve;
        t.onerror = () => reject(t.error);
      });
      await putMeta('version', data.version);
      await putMeta('treatments', data.treatments || []);
      version = data.version;
      treatments = data.treatments || [];
      return entries.length;
    }

    async function refresh() {
      // Compare version cheaply; only download the full bundle when it changed
      // or we have nothing. Silently keeps existing data when offline.
      let remote = null;
      try {
        const res = await fetch(VERSION_URL + '?t=' + Date.now(), { cache: 'no-store' });
        if (res.ok) remote = (await res.json()).version;
      } catch (e) { /* offline — keep cached data */ }
      const local = await getMeta('version');
      version = local || null;
      const have = await count();
      if (remote && (remote !== local || have === 0)) {
        try { const n = await download(); console.log('Offline RAMM cached:', n, 'roads @', version); }
        catch (e) { console.warn('Offline cache download failed', e); }
      }
    }

    const getRoad  = roadId => db ? new Promise(r => { const q = os(STORE,'readonly').get(Number(roadId)); q.onsuccess = () => r(q.result || null); q.onerror = () => r(null); }) : Promise.resolve(null);
    const allRoads = ()     => db ? new Promise(r => { const q = os(STORE,'readonly').getAll();          q.onsuccess = () => r(q.result || []);  q.onerror = () => r([]);   }) : Promise.resolve([]);

    const ready = (async () => {
      try {
        db = await open();
        treatments = (await getMeta('treatments')) || [];
        await refresh();
      } catch (e) { console.warn('Offline store unavailable', e); }
      return true;
    })();

    return { ready, getRoad, allRoads, count, get version() { return version; }, get treatments() { return treatments; } };
  })();

  // RAMM RP: cache each road's RP-ordered line, project the cursor onto it for the true route position.
  const rammLineCache = new Map();   // road_id -> {line,total_rp,lenM} | 'loading' | 'none'
  async function fetchRammLine(roadId) {
    if (rammLineCache.has(roadId)) return;
    rammLineCache.set(roadId, 'loading');
    let data = null;
    try {
      const res = await fetch('/ramm/road/' + roadId + '/line', { headers: { 'Accept': 'application/json' } });
      if (res.ok) data = await res.json();
    } catch (e) { /* offline — try the cached bundle below */ }
    if (!data || !data.line || data.line.length < 2) {
      await RammOffline.ready;
      const off = await RammOffline.getRoad(roadId);
      if (off && off.line && off.line.length >= 2) data = off;
    }
    if (!data || !data.line || data.line.length < 2) { rammLineCache.set(roadId, 'none'); return; }
    const line = turf.lineString(data.line);
    const lenM = turf.length(line, { units: 'kilometers' }) * 1000;
    rammLineCache.set(roadId, { line, total_rp: data.total_rp || lenM, lenM });
  }

  function showRoadInfoRP(name, roadId, latlng, hdcChainageM) {
    const rid = roadId != null ? Math.round(Number(roadId)) : null;
    const entry = rid != null ? rammLineCache.get(rid) : null;
    let rpHtml;
    if (entry && entry.line) {
      const snap = turf.nearestPointOnLine(entry.line, turf.point([latlng.lng, latlng.lat]), { units: 'kilometers' });
      let rp = snap.properties.location * 1000;
      if (entry.lenM > 0 && entry.total_rp > 0) rp = rp * (entry.total_rp / entry.lenM);
      rpHtml = 'RP ' + Math.round(rp) + ' m';
    } else if (entry === 'loading') {
      rpHtml = 'RP loading…';
    } else if (entry === 'none') {
      rpHtml = '~' + fmtDistance(hdcChainageM) + ' (no RAMM RP)';
    } else {
      rpHtml = 'RP …';
      if (rid != null) fetchRammLine(rid);
    }
    roadInfo._d.style.display = 'block';
    roadInfo._d.innerHTML = '<span class="ri-name">' + esc(name || '(unnamed road)') + '</span>' +
                            '<span class="ri-ch">' + rpHtml + '</span>';
  }

  function clearRoadHover() {
    if (hoveredRoad) { hoveredRoad.layer.setStyle(ROAD_STYLE); hoveredRoad = null; }
    hideRoadInfo();
    mapEl.classList.remove('road-hot');
  }

  async function loadRoads() {
    try {
      const res = await fetch(ROADS_URL, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      roadsLayer = L.geoJSON(data, {
        pane: 'roadsPane',
        interactive: false,          // let clicks pass through to points/lines/measure
        style: ROAD_STYLE,
        onEachFeature: (feature, layer) => roadsIndex.push({ layer, feature, bbox: turf.bbox(feature) }),
      }).addTo(map);
      buildRoadGrid();
      map.attributionControl.addAttribution(
        'Roads &copy; <a href="https://data-hdcgis.opendata.arcgis.com/" target="_blank" rel="noopener">Hastings DC</a> / NZTA');
    } catch (e) {
      // Offline / ArcGIS unreachable: fall back to the cached RAMM road network.
      await loadRoadsFromOffline();
    }
  }

  // Build the detection network from the offline bundle (RAMM roads with geometry).
  async function loadRoadsFromOffline() {
    await RammOffline.ready;
    const roads = await RammOffline.allRoads();
    const feats = roads
      .filter(r => r.line && r.line.length > 1)
      .map(r => ({ type: 'Feature', properties: { road_id: r.road_id, Roadname: r.road_name },
                   geometry: { type: 'LineString', coordinates: r.line } }));
    if (feats.length === 0) { showToast('No offline road data cached yet — open once while online.', true); return; }
    roadsLayer = L.geoJSON({ type: 'FeatureCollection', features: feats }, {
      pane: 'roadsPane',
      interactive: false,
      style: ROAD_STYLE,
      onEachFeature: (feature, layer) => roadsIndex.push({ layer, feature, bbox: turf.bbox(feature) }),
    }).addTo(map);
    buildRoadGrid();
    showToast('Offline: using cached road network (' + feats.length + ' roads).');
  }
  loadRoads();

  // Spatial grid: bucket each road into the ~0.004° (~350 m) cells its geometry
  // passes through, so hover only tests the few roads near the cursor (not all).
  const GRID_CELL = 0.004;
  const ROAD_SNAP_PX = 10;                  // cursor must be within ~10 px of a road to match
  const roadGrid = new Map();               // "cx:cy" -> [roadsIndex idx, ...]
  function buildRoadGrid() {
    roadGrid.clear();
    roadsIndex.forEach((entry, idx) => {
      const seen = new Set();
      const add = coords => {
        for (const c of coords) {
          const k = Math.floor(c[0] / GRID_CELL) + ':' + Math.floor(c[1] / GRID_CELL);
          if (!seen.has(k)) {
            seen.add(k);
            if (!roadGrid.has(k)) roadGrid.set(k, []);
            roadGrid.get(k).push(idx);
          }
        }
      };
      const g = entry.feature.geometry;
      if (g.type === 'LineString') add(g.coordinates);
      else if (g.type === 'MultiLineString') g.coordinates.forEach(add);
    });
  }

  // Find the nearest road to a lat/lng using the grid; return {best,bestLoc} if
  // within ROAD_SNAP_PX screen pixels, else null. Shared by mouse hover + GPS.
  function locateRoadAt(lat, lng, maxMeters) {
    if (roadsIndex.length === 0) return null;
    const pt = turf.point([lng, lat]);
    let best = null, bestDist = Infinity, bestLoc = 0;

    const cx = Math.floor(lng / GRID_CELL), cy = Math.floor(lat / GRID_CELL);
    const cand = new Set();
    for (let dx = -1; dx <= 1; dx++) {
      for (let dy = -1; dy <= 1; dy++) {
        const arr = roadGrid.get((cx + dx) + ':' + (cy + dy));
        if (arr) for (const i of arr) cand.add(i);
      }
    }

    const pad = 0.0007;   // ~60 m bbox skirt — drop far candidates before the costly test
    for (const i of cand) {
      const entry = roadsIndex[i];
      const b = entry.bbox;
      if (lng < b[0] - pad || lng > b[2] + pad || lat < b[1] - pad || lat > b[3] + pad) continue;
      const snap = turf.nearestPointOnLine(entry.feature, pt, { units: 'kilometers' });
      if (snap.properties.dist < bestDist) { bestDist = snap.properties.dist; best = entry; bestLoc = snap.properties.location; }
    }

    if (!best) return null;
    // GPS passes a metric tolerance (accounts for fix jitter); mouse uses a pixel tolerance.
    if (maxMeters != null) {
      return (bestDist * 1000 <= maxMeters) ? { best, bestLoc } : null;
    }
    const resM = 40075016.686 * Math.cos(lat * Math.PI / 180) / (256 * Math.pow(2, map.getZoom()));
    const bestPx = (bestDist * 1000) / resM;
    return (bestPx <= ROAD_SNAP_PX) ? { best, bestLoc } : null;
  }

  // Apply the hover/selection for a matched road at a given point.
  function applyRoadHover(match, latlng) {
    if (!match) { clearRoadHover(); return; }
    const { best, bestLoc } = match;
    if (hoveredRoad && hoveredRoad !== best) hoveredRoad.layer.setStyle(ROAD_STYLE);
    hoveredRoad = best;
    best.layer.setStyle(ROAD_HOVER);
    showRoadInfoRP(best.feature.properties.Roadname, best.feature.properties.road_id, latlng, bestLoc * 1000);
    mapEl.classList.add('road-hot');
    if (worksMode) updateWorksHover(best.feature.properties.road_id, latlng);
  }

  // Throttled mouse hover (desktop). Suspended while GPS follow drives the map.
  let lastHover = 0;
  map.on('mousemove', e => {
    if (gpsFollow || !roadsVisible || roadsIndex.length === 0 || drawing || editing) return;
    const now = performance.now();
    if (now - lastHover < 45) return;
    lastHover = now;
    applyRoadHover(locateRoadAt(e.latlng.lat, e.latlng.lng), e.latlng);
  });

  document.getElementById('btnRoads').addEventListener('click', () => {
    roadsVisible = !roadsVisible;
    document.getElementById('btnRoads').classList.toggle('active', roadsVisible);
    if (roadsLayer) { roadsVisible ? roadsLayer.addTo(map) : map.removeLayer(roadsLayer); }
    if (!roadsVisible) clearRoadHover();
  });

  // ============================================================
  //  ROAD HISTORY: RAMM surfacing on the treatment length under the cursor
  // ============================================================
  let worksMode = false;
  const worksLayer = L.layerGroup().addTo(map);
  const worksPanel = document.getElementById('worksPanel');
  const TL_CASING    = { color: '#101320', weight: 11, opacity: 0.9, interactive: false, lineCap: 'round' };
  const TL_HIGHLIGHT = { color: '#ffd23f', weight: 6,  opacity: 1,   interactive: false, lineCap: 'round' };

  // Per-road cache of RP-ordered line + treatment lengths + surfacing.
  const worksCache = new Map();   // road_id -> obj | 'loading' | 'none'
  let activeTl = null;            // {roadId, start_m, end_m} shown now, to avoid redraw churn

  function yearsSince(dateStr) {
    if (!dateStr) return null;
    const d = new Date(dateStr);
    return isNaN(d) ? null : (Date.now() - d.getTime()) / (365.25 * 24 * 3600 * 1000);
  }
  function fmtSurfDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d)) return String(dateStr).slice(0, 7);
    return String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
  }

  async function fetchWorks(roadId, latlng) {
    if (worksCache.has(roadId)) return;
    worksCache.set(roadId, 'loading');
    try {
      let data = null;
      try {
        const res = await fetch('/ramm/road/' + roadId + '/works', { headers: { 'Accept': 'application/json' } });
        if (res.ok) {
          const d = await res.json();
          if (d && d.ok !== false && d.line && d.line.length >= 2) data = d;
        }
      } catch (e) { /* offline — try the cached bundle below */ }

      if (!data) {
        await RammOffline.ready;
        const off = await RammOffline.getRoad(roadId);   // same shape as /works (line, surfacing, treatment_lengths)
        if (off && off.line && off.line.length >= 2) data = off;
      }

      if (!data) {
        worksCache.set(roadId, 'none');
        showToast('Road history unavailable (no data cached for offline).', true);
        return;
      }

      const line = turf.lineString(data.line);
      const lenM = turf.length(line, { units: 'kilometers' }) * 1000;
      const totalRp = data.total_rp || lenM;
      const factor = totalRp > 0 ? lenM / totalRp : 1;   // RP metres -> geometric metres

      const surfaces = (data.surfacing || [])
        .filter(s => Number(s.surf_offset || 0) === 0 && s.start_m != null && s.end_m != null && s.end_m > s.start_m);

      let tls = (data.treatment_lengths || []).filter(t => t.end_m > t.start_m);
      if (tls.length === 0 && data.tl_error) console.warn('RAMM treatment_length error:', data.tl_error);
      // If RAMM has no treatment lengths for this road, fall back to surfacing extents.
      const tlFallback = tls.length === 0;
      if (tlFallback) {
        tls = surfaces.map(s => ({ start_m: s.start_m, end_m: s.end_m, tl_id: null }))
                      .sort((a, b) => a.start_m - b.start_m);
      }

      worksCache.set(roadId, {
        name: data.road_name || ('Road ' + roadId), line, factor, tls, surfaces, tlFallback,
        fwp: (data.fwp || []).filter(f => f.end_m > f.start_m).sort((a, b) => a.start_m - b.start_m),
        overrides: (data.fwp_overrides || []),
      });
      // Render immediately for the road/point under the cursor (the hover that
      // triggered the fetch won't re-fire if the mouse is momentarily still).
      if (worksMode && latlng) updateWorksHover(roadId, latlng);
    } catch (e) {
      worksCache.set(roadId, 'none');
      showToast('Road history error: ' + e.message, true);
    }
  }

  // One readable surfacing line, e.g. "Two Coat Seal Reseal, Grade 3/5, width 6.5 m, life 12 y".
  function surfLine(s) {
    const parts = [];
    const mat = (s.surf_material != null && s.surf_material !== '') ? String(s.surf_material) : null;
    const fn  = (s.surf_function != null && s.surf_function !== '') ? String(s.surf_function) : null;
    const desc = [mat, fn].filter(Boolean).join(' ');
    if (desc) parts.push(desc);
    const c1 = s.chip_size, c2 = s.chip_2nd_size;
    if (c1 != null && c1 !== '') parts.push('Grade ' + c1 + (c2 != null && c2 !== '' ? '/' + c2 : ''));
    if (s.surf_width != null && s.surf_width !== '') parts.push('width ' + s.surf_width + ' m');
    if (s.life != null && s.life !== '') parts.push('life ' + s.life + ' y');
    return parts.join(', ') || '—';
  }

  function renderWorksPanel(entry, tl) {
    // Surfacing overlapping this treatment length, youngest date at the top, last 5 only.
    const rows = entry.surfaces
      .filter(s => s.start_m < tl.end_m && s.end_m > tl.start_m)
      .sort((a, b) => new Date(b.surface_date || 0) - new Date(a.surface_date || 0))
      .slice(0, 5);

    document.getElementById('wpName').textContent = entry.name;

    const label = [];
    if (tl.name) label.push(tl.name);
    else if (tl.tl_id != null) label.push('TL ' + tl.tl_id);
    label.push('RP ' + Math.round(tl.start_m) + '–' + Math.round(tl.end_m) + ' m');
    document.getElementById('wpSection').textContent = 'Surfacing · ' + label.join(' · ');

    const body = document.getElementById('wpBody');
    const empty = document.getElementById('wpEmpty');
    body.innerHTML = '';
    if (rows.length === 0) {
      empty.style.display = 'block';
    } else {
      empty.style.display = 'none';
      rows.forEach(s => {
        const div = document.createElement('div');
        div.className = 'wp-row';
        div.innerHTML =
          '<span class="wp-date">' + esc(fmtSurfDate(s.surface_date)) + '</span>' +
          '<span class="wp-detail">' + esc(surfLine(s)) + '</span>';
        body.appendChild(div);
      });
    }
    worksPanel.classList.add('show');
  }

  function highlightTl(entry, tl) {
    worksLayer.clearLayers();
    try {
      const seg = turf.lineSliceAlong(
        entry.line, (tl.start_m * entry.factor) / 1000, (tl.end_m * entry.factor) / 1000, { units: 'kilometers' });
      const coords = seg.geometry.coordinates.map(c => [c[1], c[0]]);
      if (coords.length >= 2) {
        L.polyline(coords, TL_CASING).addTo(worksLayer);      // dark outline for contrast
        L.polyline(coords, TL_HIGHLIGHT).addTo(worksLayer);   // bright fill on top
      }
    } catch (e) { /* slice outside the line — ignore */ }
  }

  // Called from the road-hover handler while works mode is on.
  function updateWorksHover(roadId, latlng) {
    try {
      const rid = roadId != null ? Math.round(Number(roadId)) : null;
      if (rid == null) return;
      const entry = worksCache.get(rid);
      if (entry === undefined) { fetchWorks(rid, latlng); return; }
      if (entry === 'loading' || entry === 'none' || !entry.line) return;

      // Cursor RP = geometric distance along the RP-ordered line / factor.
      const snap = turf.nearestPointOnLine(entry.line, turf.point([latlng.lng, latlng.lat]), { units: 'kilometers' });
      const rp = (snap.properties.location * 1000) / (entry.factor || 1);

      // Treatment length containing this RP (else the nearest by endpoint).
      let tl = entry.tls.find(t => rp >= t.start_m && rp <= t.end_m);
      if (!tl && entry.tls.length) {
        tl = entry.tls.reduce((best, t) => {
          const d = Math.min(Math.abs(rp - t.start_m), Math.abs(rp - t.end_m));
          return (best === null || d < best.d) ? { t, d } : best;
        }, null).t;
      }
      if (!tl) return;

      // Live position readout (updates every move, like mobileroads).
      document.getElementById('wpPos').textContent = 'RP ' + Math.round(rp) + ' m along ' + entry.name;
      worksPanel.classList.add('show');

      // FWP block refreshes with position (segment under you can change mid-length).
      updateFwpBlock(rid, entry, rp, tl);

      // Rebuild the list + highlight only when the treatment length changes.
      if (activeTl && activeTl.roadId === rid && activeTl.start_m === tl.start_m && activeTl.end_m === tl.end_m) return;
      activeTl = { roadId: rid, start_m: tl.start_m, end_m: tl.end_m };
      renderWorksPanel(entry, tl);
      highlightTl(entry, tl);
      drawFwpSegments(entry, rid);
    } catch (e) {
      showToast('Road history render error: ' + e.message, true);
    }
  }

  // ============================================================
  //  FORWARD WORKS PROGRAMME: show programmed year + treatment,
  //  and edit them in the field via +/- buttons (saved as local
  //  overrides, never written straight back to RAMM). Offline-safe.
  // ============================================================
  const FWP_STORE_KEY = 'chirp_fwp_overrides';   // device-local overrides + queue
  const fwpLayer = L.layerGroup().addTo(map);
  let fwpTreatments = [];           // [{code,category,...}] cycle vocabulary
  let fwpCtx = null;                // segment the buttons currently act on
  const fwpLocal = new Map();       // key -> override object (this device)
  let fwpQueue = [];                // overrides awaiting POST when online

  // Load device-local overrides + queue from localStorage.
  try {
    const saved = JSON.parse(localStorage.getItem(FWP_STORE_KEY) || '{}');
    (saved.overrides || []).forEach(o => fwpLocal.set(o._key, o));
    fwpQueue = saved.queue || [];
  } catch (e) { /* ignore corrupt store */ }

  function fwpPersist() {
    try {
      localStorage.setItem(FWP_STORE_KEY, JSON.stringify({
        overrides: [...fwpLocal.values()], queue: fwpQueue,
      }));
    } catch (e) { /* storage full / disabled — keep in memory */ }
  }

  // NZ financial year helpers.
  function currentFyStart() {
    const d = new Date();
    return d.getMonth() >= 6 ? d.getFullYear() : d.getFullYear() - 1;  // FY starts 1 July
  }
  function fyLabel(ys) {
    if (ys == null) return '—';
    return ys + '/' + String((ys + 1) % 100).padStart(2, '0');
  }

  // Load the treatment vocabulary (online first, then the offline bundle).
  async function loadFwpTreatments() {
    try {
      const res = await fetch('/fwp/treatments', { headers: { 'Accept': 'application/json' } });
      if (res.ok) { const t = await res.json(); if (Array.isArray(t) && t.length) { fwpTreatments = t; return; } }
    } catch (e) { /* offline */ }
    await RammOffline.ready;
    fwpTreatments = RammOffline.treatments || [];
  }
  loadFwpTreatments();

  const fwpKey = (roadId, seg) => roadId + ':' + (seg && seg.treat_length_id != null
    ? 'tl' + seg.treat_length_id
    : 'rp' + Math.round((seg && seg.start_m) || 0) + '-' + Math.round((seg && seg.end_m) || 0));

  // The segment programmed at this RP (a real FWP row, else the treatment length
  // as a blank canvas for a from-scratch entry).
  function fwpSegmentAt(entry, rp, tl) {
    const seg = (entry.fwp || []).find(f => rp >= f.start_m && rp <= f.end_m);
    if (seg) return { ...seg, _scratch: false };
    if (tl) return { treat_length_id: tl.tl_id, start_m: tl.start_m, end_m: tl.end_m, _scratch: true };
    return null;
  }

  // Merge RAMM baseline with any override (local edit wins, then server-sent).
  function fwpEffective(roadId, entry, seg) {
    const key = fwpKey(roadId, seg);
    let ov = fwpLocal.get(key);
    if (!ov) {
      const s = (entry.overrides || []).find(o =>
        (seg.treat_length_id != null && o.treat_length_id === seg.treat_length_id) ||
        (seg.treat_length_id == null && Math.round(o.start_m) === Math.round(seg.start_m)));
      if (s) ov = { year_start: s.year_start, treatment_id: s.treatment_id, treatment: s.treatment };
    }
    // RAMM uses 1900/01 as a placeholder for "no scheduled year" — treat as unscheduled.
    const rawYs = seg._scratch ? null : (seg.year_start ?? null);
    const baseYs = (rawYs != null && rawYs >= 2000) ? rawYs : null;
    const baseTmt = seg._scratch ? null : (seg.treatment || seg.treatment_id || null);
    const effTid = (ov && ov.treatment_id) ? ov.treatment_id : (seg._scratch ? null : (seg.treatment_id || null));
    const vocab = effTid ? fwpTreatments.find(t => t.code === effTid) : null;
    const category = (vocab && vocab.category) ? vocab.category : (seg._scratch ? null : (seg.category || null));

    return {
      key,
      baseYearStart: baseYs, baseTreatment: baseTmt,
      yearStart: (ov && ov.year_start != null) ? ov.year_start : baseYs,
      treatment: (ov && ov.treatment) ? ov.treatment : baseTmt,
      treatmentId: effTid,
      category,
      overridden: !!ov,
    };
  }

  // Colour a year: sooner = hotter. null = grey (unprogrammed).
  function fwpYearColor(ys) {
    if (ys == null) return '#5a6178';
    const d = Math.max(0, Math.min(10, ys - currentFyStart()));
    const stops = ['#ff3b30','#ff6b2c','#ff9f1c','#ffd23f','#c6e84f','#7ed957','#43c6ac','#2ea3d6','#5a7bd6','#7a6cff','#9a7cff'];
    return stops[Math.round(d)] || stops[stops.length - 1];
  }

  // Year colour legend (map mode only).
  const fwpLegend = L.control({ position: 'bottomleft' });
  fwpLegend.onAdd = function () {
    const d = L.DomUtil.create('div', 'fwp-legend');
    const cy = currentFyStart();
    let html = '<div class="fl-title">FWP year</div><div class="fl-scale">';
    for (let i = 0; i <= 10; i += 2) html += '<span style="background:' + fwpYearColor(cy + i) + '">' + fyLabel(cy + i).slice(2) + '</span>';
    html += '</div>';
    d.innerHTML = html;
    d.style.display = 'none';
    this._d = d;
    return d;
  };
  fwpLegend.addTo(map);
  function toggleFwpLegend(on) { if (fwpLegend._d) fwpLegend._d.style.display = on ? 'block' : 'none'; }

  function updateFwpBlock(roadId, entry, rp, tl) {
    renderFwpSegment(roadId, entry, fwpSegmentAt(entry, rp, tl));
  }

  // Render a specific segment (reused after button edits so the block stays put
  // instead of being re-derived from cursor position).
  function renderFwpSegment(roadId, entry, seg) {
    const block = document.getElementById('fwpBlock');
    if (!seg) { block.style.display = 'none'; fwpCtx = null; return; }

    const eff = fwpEffective(roadId, entry, seg);
    fwpCtx = { roadId, entry, seg, eff };

    block.style.display = 'block';
    const yearEl = document.getElementById('fwpYear');
    yearEl.innerHTML = '<span class="fwp-dot" style="background:' + fwpYearColor(eff.yearStart) + '"></span>' + fyLabel(eff.yearStart);
    document.getElementById('fwpTmt').textContent = eff.treatment
      ? (eff.treatment + (eff.category ? ' · ' + eff.category : ''))
      : (seg._scratch ? 'Set…' : '—');

    const was = document.getElementById('fwpWas');
    if (eff.overridden) {
      was.className = 'fwp-was changed';
      was.textContent = 'was ' + fyLabel(eff.baseYearStart) + ' · ' + (eff.baseTreatment || '—') + '  → edited';
    } else {
      was.className = 'fwp-was';
      was.textContent = seg._scratch
        ? 'No RAMM programme here — set year & treatment'
        : (eff.baseYearStart == null ? 'Unscheduled — set a year' : (seg.reason || ''));
    }
  }

  // Draw the road's FWP segments coloured by programmed year.
  function drawFwpSegments(entry, roadId) {
    fwpLayer.clearLayers();
    (entry.fwp || []).forEach(f => {
      try {
        const seg = turf.lineSliceAlong(entry.line,
          (f.start_m * entry.factor) / 1000, (f.end_m * entry.factor) / 1000, { units: 'kilometers' });
        const coords = seg.geometry.coordinates.map(c => [c[1], c[0]]);
        const eff = fwpEffective(roadId ?? 0, entry, { ...f, _scratch: false });
        if (coords.length >= 2) {
          L.polyline(coords, { color: fwpYearColor(eff.yearStart), weight: 5, opacity: 0.85,
                               interactive: false, lineCap: 'round' }).addTo(fwpLayer);
        }
      } catch (e) { /* slice outside line */ }
    });
  }

  // Persist + (try to) push one override.
  async function fwpSaveOverride(ov) {
    ov._key = fwpKey(ov.road_id, { treat_length_id: ov.treat_length_id, start_m: ov.start_m, end_m: ov.end_m });
    fwpLocal.set(ov._key, ov);
    fwpQueue = fwpQueue.filter(q => q._key !== ov._key);
    fwpQueue.push(ov);
    fwpPersist();
    flushFwpQueue();
  }

  async function flushFwpQueue() {
    if (!navigator.onLine || fwpQueue.length === 0) return;
    const items = [...fwpQueue];
    try {
      const res = await fetch('/fwp/sync', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ items }),
      });
      if (res.ok) { fwpQueue = []; fwpPersist(); }
    } catch (e) { /* stay queued */ }
  }
  window.addEventListener('online', flushFwpQueue);

  // Apply an edit to the current segment and save it.
  function fwpApply(patch) {
    if (!fwpCtx) return;
    const { roadId, seg, eff } = fwpCtx;
    const ys = patch.yearStart != null ? patch.yearStart : eff.yearStart;
    const tId = patch.treatmentId !== undefined ? patch.treatmentId : eff.treatmentId;
    const tName = patch.treatment !== undefined ? patch.treatment : eff.treatment;
    fwpSaveOverride({
      road_id: roadId,
      treat_length_id: seg.treat_length_id ?? null,
      start_m: seg.start_m, end_m: seg.end_m,
      base_year: fyLabel(eff.baseYearStart), base_treatment: eff.baseTreatment,
      year: ys != null ? fyLabel(ys) : null, year_start: ys,
      treatment_id: tId, treatment: tName,
    });
    // Refresh display + colours from the updated store.
    renderFwpSegment(roadId, fwpCtx.entry, seg);
    drawFwpSegments(fwpCtx.entry, roadId);
  }

  // Year shift buttons.
  document.getElementById('fwpYearBtns').addEventListener('click', (e) => {
    const btn = e.target.closest('button'); if (!btn || !fwpCtx) return;
    const dy = parseInt(btn.dataset.dy, 10);
    // Scheduled: shift from the programmed year. Unscheduled/scratch: +1 lands on this FY.
    const base = fwpCtx.eff.yearStart != null ? fwpCtx.eff.yearStart : (currentFyStart() - 1);
    fwpApply({ yearStart: Math.max(currentFyStart(), base + dy) });
  });

  // Treatment cycle buttons.
  function cycleTreatment(dir) {
    if (!fwpCtx || fwpTreatments.length === 0) return;
    const cur = fwpCtx.eff.treatmentId;
    let i = fwpTreatments.findIndex(t => t.code === cur);
    i = (i + dir + fwpTreatments.length) % fwpTreatments.length;
    if (i < 0) i = 0;
    const t = fwpTreatments[i];
    fwpApply({ treatmentId: t.code, treatment: t.code });
  }
  document.getElementById('fwpTmtPrev').addEventListener('click', () => cycleTreatment(-1));
  document.getElementById('fwpTmtNext').addEventListener('click', () => cycleTreatment(1));

  // Undo: drop the override, revert to RAMM baseline.
  document.getElementById('fwpUndo').addEventListener('click', () => {
    if (!fwpCtx) return;
    const { roadId, seg } = fwpCtx;
    const key = fwpKey(roadId, seg);
    fwpLocal.delete(key);
    fwpQueue = fwpQueue.filter(q => q._key !== key);
    fwpPersist();
    // Tell the server to drop it too (if online).
    fetch('/fwp/override/delete', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify({ road_id: roadId, treat_length_id: seg.treat_length_id ?? null,
                             start_m: seg.start_m, end_m: seg.end_m }),
    }).catch(() => {});
    renderFwpSegment(roadId, fwpCtx.entry, seg);
    drawFwpSegments(fwpCtx.entry, roadId);
  });

  document.getElementById('btnWorks').addEventListener('click', () => {
    worksMode = !worksMode;
    document.getElementById('btnWorks').classList.toggle('active', worksMode);
    if (worksMode && !roadsVisible) document.getElementById('btnRoads').click(); // need roads to hover
    if (worksMode) {
      showToast('Hover a road to see its surfacing history + forward works.');
      toggleFwpLegend(true);
    } else {
      worksLayer.clearLayers();
      fwpLayer.clearLayers();
      document.getElementById('fwpBlock').style.display = 'none';
      worksPanel.classList.remove('show');
      toggleFwpLegend(false);
      activeTl = null;
    }
  });

  document.getElementById('wpClear').addEventListener('click', () => {
    worksLayer.clearLayers();
    fwpLayer.clearLayers();
    document.getElementById('fwpBlock').style.display = 'none';
    worksPanel.classList.remove('show');
    activeTl = null;
    if (gpsFollow) stopGps();
  });

  // ============================================================
  //  GPS FOLLOW: drive the panel from device location (mobile)
  //  On desktop this stays off and the mouse drives the hover.
  // ============================================================
  const isTouch = !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);
  let gpsFollow = false, gpsWatchId = null;

  function ensureWorksOn() {
    if (!roadsVisible) document.getElementById('btnRoads').click();
    if (!worksMode) {
      worksMode = true;
      document.getElementById('btnWorks').classList.add('active');
    }
  }

  function startGps() {
    if (!('geolocation' in navigator)) { showToast('This device has no geolocation.', true); return; }
    gpsFollow = true;
    document.getElementById('btnGps').classList.add('active');
    ensureWorksOn();
    clearRoadHover();

    // Drive mode: hide the map, show the full-screen live text readout.
    document.body.classList.add('drive-mode');
    worksPanel.classList.add('show');
    document.getElementById('wpPos').textContent = 'Locating…';
    document.getElementById('wpName').textContent = '—';
    document.getElementById('wpBody').innerHTML = '';
    document.getElementById('wpEmpty').style.display = 'none';

    showToast('Following your location — data refreshes as you move.');
    gpsWatchId = navigator.geolocation.watchPosition(onGpsFix, onGpsError, {
      enableHighAccuracy: true, maximumAge: 1000, timeout: 15000,
    });
  }

  function stopGps() {
    gpsFollow = false;
    activeTl = null;
    document.getElementById('btnGps').classList.remove('active');
    if (gpsWatchId != null) { navigator.geolocation.clearWatch(gpsWatchId); gpsWatchId = null; }

    // Leave drive mode: restore the map (it was display:none, so resize it).
    document.body.classList.remove('drive-mode');
    worksPanel.classList.remove('show');
    setTimeout(() => map.invalidateSize(), 60);
  }

  function onGpsFix(pos) {
    const lat = pos.coords.latitude, lng = pos.coords.longitude;

    if (roadsIndex.length === 0) {
      document.getElementById('wpPos').textContent = 'Waiting for road data…';
      return;
    }

    // Tolerance follows the fix accuracy (min 30 m) so GPS jitter still snaps to the road.
    const tol = Math.max(30, pos.coords.accuracy || 0);
    const match = locateRoadAt(lat, lng, tol);
    if (match) {
      applyRoadHover(match, L.latLng(lat, lng));
    } else {
      document.getElementById('wpPos').textContent = 'No road within ' + Math.round(tol) + ' m';
    }
  }

  function onGpsError(err) {
    showToast('Location error: ' + (err.message || ('code ' + err.code)) +
              (location.protocol !== 'https:' && location.hostname !== 'localhost'
                ? ' (GPS needs HTTPS on a phone)' : ''), true);
    stopGps();
  }

  document.getElementById('btnGps').addEventListener('click', () => {
    gpsFollow ? stopGps() : startGps();
  });

  // On touch devices (phones/tablets) auto-start GPS follow — the driving use case.
  if (isTouch) {
    setTimeout(() => { if (!gpsFollow) startGps(); }, 1500);
  }

  // ============================================================
  //  MEASURE: click points to measure real ground distance
  // ============================================================
  const measureBanner = document.getElementById('measureBanner');
  const measureTotal  = document.getElementById('measureTotal');
  let measuring = false, measurePts = [], measureLayer = null, measureClickTimer = null;

  function startMeasure() {
    if (placing) stopPlacing();
    if (drawing) { map.pm.disableDraw(); stopDrawing(); }
    if (editing) toggleEdit();
    clearMeasure();
    measuring = true;
    measureLayer = L.layerGroup().addTo(map);
    document.getElementById('btnMeasure').classList.add('active');
    measureBanner.classList.add('show');
    measureTotal.textContent = '0 m';
    mapEl.classList.add('placing');
    map.doubleClickZoom.disable();
  }
  function clearMeasure() {
    measuring = false;
    measurePts = [];
    if (measureLayer) { map.removeLayer(measureLayer); measureLayer = null; }
    document.getElementById('btnMeasure').classList.remove('active');
    measureBanner.classList.remove('show');
    mapEl.classList.remove('placing');
    map.doubleClickZoom.enable();
  }
  function redrawMeasure() {
    if (!measureLayer) return;
    measureLayer.clearLayers();
    if (measurePts.length >= 2) {
      L.polyline(measurePts, { color: '#f59e0b', weight: 3, dashArray: '6,6' }).addTo(measureLayer);
    }
    let total = 0;
    measurePts.forEach((pt, i) => {
      if (i > 0) total += map.distance(measurePts[i - 1], pt);
      L.circleMarker(pt, { radius: 4, color: '#f59e0b', fillColor: '#f59e0b', fillOpacity: 1, weight: 2 }).addTo(measureLayer);
    });
    if (measurePts.length) {
      const last = measurePts[measurePts.length - 1];
      L.marker(last, { opacity: 0, interactive: false }).addTo(measureLayer)
        .bindTooltip(fmtDistance(total), { permanent: true, direction: 'right', offset: [10, 0], className: 'measure-label' }).openTooltip();
    }
    measureTotal.textContent = fmtDistance(total);
  }

  document.getElementById('btnMeasure').addEventListener('click', () => { measuring ? clearMeasure() : startMeasure(); });
  document.getElementById('clearMeasureBtn').addEventListener('click', clearMeasure);

  map.on('click', e => {
    if (!measuring) return;
    clearTimeout(measureClickTimer);
    const latlng = e.latlng;
    measureClickTimer = setTimeout(() => { measurePts.push(latlng); redrawMeasure(); }, 220);
  });
  map.on('dblclick', () => {
    if (!measuring) return;
    clearTimeout(measureClickTimer);   // cancel the pending single-click add
    measuring = false;                 // finish: keep the result, stop adding
    document.getElementById('btnMeasure').classList.remove('active');
    mapEl.classList.remove('placing');
    map.doubleClickZoom.enable();
  });

  // Delete a point or line from its popup
  document.addEventListener('click', async e => {
    const btn = e.target.closest('.popup-del');
    if (!btn) return;
    const id = Number(btn.dataset.id);
    const kind = btn.dataset.kind || 'point';
    if (!confirm('Delete this ' + kind + '?')) return;
    const url = (kind === 'line' ? LINES_URL : POINTS_URL) + '/' + id;
    try {
      const res = await fetch(url, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
      });
      if (!res.ok) { showToast('Could not delete ' + kind + '.', true); return; }
      if (kind === 'line') {
        const idx = lineLayers.findIndex(l => l._dbId === id);
        if (idx !== -1) { map.removeLayer(lineLayers[idx]); lineLayers.splice(idx, 1); }
      } else {
        const m = pointMarkers.get(id);
        if (m) { map.removeLayer(m); pointMarkers.delete(id); }
      }
      showToast(kind.charAt(0).toUpperCase() + kind.slice(1) + ' deleted.');
    } catch (err) { showToast('Network error deleting ' + kind + '.', true); }
  });

  // Esc cancels whatever mode is active
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show'));
    if (placing) stopPlacing();
    if (drawing) { map.pm.disableDraw(); stopDrawing(); }
    if (editing) toggleEdit();
    if (measuring || measureLayer) clearMeasure();
  });
</script>

</body>
</html>
