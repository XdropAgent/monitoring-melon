<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Monitoring</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🍈</text></svg>">
  <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --green-dark: #1a5c2a;
      --green-mid: #2d8a45;
      --green-light: #4caf68;
      --green-pale: #e8f5ec;
      --bg: #f0f4f0;
      --card: #ffffff;
      --text: #1a2e1e;
      --muted: #6b7c6e;
      --border: #d4e4d8;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

    nav {
      background: var(--green-dark); padding: 0 20px; height: 48px;
      display: flex; align-items: center; justify-content: space-between;
      border-bottom: 3px solid var(--green-light); flex-wrap: wrap; gap: 6px;
    }
    nav .brand { font-family: 'Space Mono', monospace; font-size: 15px; font-weight: 700; color: #fff; letter-spacing: 1px; }
    nav .brand span { color: var(--green-light); }
    nav .nav-right { display: flex; gap: 12px; font-size: 12px; color: rgba(255,255,255,0.7); font-family: 'Space Mono', monospace; align-items: center; flex-wrap: wrap; }
    nav .status-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #4ade80; margin-right: 5px; animation: blink 1.5s infinite; }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }

    .tab-menu { background: #fff; border-bottom: 2px solid var(--border); display: flex; padding: 0 12px; overflow-x: auto; }
    .tab-menu::-webkit-scrollbar { display: none; }
    .tab-btn { padding: 10px 16px; font-size: 13px; font-weight: 600; color: var(--muted); border: none; background: none; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; transition: all .2s; font-family: 'DM Sans', sans-serif; white-space: nowrap; }
    .tab-btn:hover { color: var(--green-mid); }
    .tab-btn.active { color: var(--green-dark); border-bottom-color: var(--green-mid); }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    .container { max-width: 1100px; margin: 18px auto; padding: 0 16px; display: grid; grid-template-columns: 1fr 300px; gap: 16px; align-items: start; }
    .card { background: var(--card); border-radius: 10px; border: 1px solid var(--border); overflow: hidden; }
    .card-header { padding: 10px 14px; border-bottom: 1px solid var(--border); font-size: 12px; font-weight: 600; color: var(--muted); letter-spacing: .5px; text-transform: uppercase; background: var(--green-pale); display: flex; align-items: center; gap: 6px; }

    .main-image-wrap { position: relative; background: #111; aspect-ratio: 16/9; max-height: 340px; overflow: hidden; width: 100%; }
    .main-image-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; opacity: .95; }
    .offline-overlay { display: none; position: absolute; top:0; left:0; right:0; bottom:0; background: #111; align-items: center; justify-content: center; flex-direction: column; gap: 10px; z-index: 3; }
    .offline-overlay.show { display: flex; }
    .offline-overlay svg { opacity: .3; }
    .offline-overlay span { font-family: 'Space Mono', monospace; font-size: 12px; color: rgba(255,255,255,.4); letter-spacing: 1px; }
    #mainImg.hidden { display: none; }
    .img-overlay { position: absolute; bottom:0; left:0; right:0; background: linear-gradient(transparent, rgba(0,0,0,.8)); padding: 16px 12px 10px; z-index: 4; }
    .live-badge { color: #fff; font-size: 10px; font-family: 'Space Mono', monospace; font-weight: 700; padding: 2px 7px; border-radius: 4px; letter-spacing: 1px; margin-right: 8px; }
    .img-meta { font-family: 'Space Mono', monospace; font-size: 10px; color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,.5); line-height: 1.8; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    .img-meta .val { color: var(--green-light); font-weight: 700; }
    .stream-status { position: absolute; top: 8px; right: 10px; font-size: 10px; font-family: 'Space Mono', monospace; color: #fff; background: rgba(0,0,0,0.55); padding: 2px 8px; border-radius: 4px; z-index: 5; }

    /* Tombol ambil foto di atas live camera */
    .btn-capture {
      display: inline-flex; align-items: center; gap: 5px;
      background: var(--green-mid); color: #fff;
      border: none; border-radius: 6px; padding: 5px 12px;
      font-size: 11px; font-weight: 600; cursor: pointer;
      font-family: 'DM Sans', sans-serif; transition: background .2s;
    }
    .btn-capture:hover { background: var(--green-dark); }
    .btn-capture:disabled { background: #9ca3af; cursor: not-allowed; }

    .timelapse-strip { display: flex; gap: 8px; padding: 12px 14px; overflow-x: auto; min-height: 90px; align-items: center; }
    .timelapse-strip::-webkit-scrollbar { height: 4px; }
    .timelapse-strip::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

    .right-col { display: flex; flex-direction: column; gap: 16px; }
    .gauges-row { display: flex; justify-content: space-around; padding: 14px 12px 12px; gap: 6px; }
    .gauge-wrap { display: flex; flex-direction: column; align-items: center; gap: 6px; }
    .gauge-label { font-size: 10px; color: var(--muted); font-weight: 600; text-align: center; }
    .gauge-svg { width: 80px; height: 80px; transform: rotate(-90deg); }
    .gauge-svg circle { fill: none; stroke-width: 8; stroke-linecap: round; }
    .gauge-bg { stroke: #e9ecef; }
    .gauge-val-text { font-family: 'DM Sans', sans-serif; font-size: 12px; font-weight: 700; fill: var(--text); dominant-baseline: middle; text-anchor: middle; }
    .gauge-sub { font-size: 9px; fill: var(--muted); dominant-baseline: middle; text-anchor: middle; }

    .chart-wrap { padding: 12px 14px; height: 170px; }
    .chart-legend { display: flex; gap: 12px; padding: 0 14px 10px; flex-wrap: wrap; }
    .legend-item { display: flex; align-items: center; gap: 5px; font-size: 11px; color: var(--muted); }
    .legend-dot { width: 10px; height: 3px; border-radius: 2px; }

    .sensor-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .sensor-table td { padding: 7px 14px; border-bottom: 1px solid var(--border); }
    .sensor-table tr:last-child td { border-bottom: none; }
    .sensor-table .key { color: var(--muted); font-weight: 500; }
    .sensor-table .val-cell { font-family: 'Space Mono', monospace; font-weight: 700; text-align: right; }
    .badge-ok     { background: #dcfce7; color: #16a34a; border-radius: 4px; padding: 2px 8px; font-size: 10px; font-weight: 700; }
    .badge-warn   { background: #fef9c3; color: #ca8a04; border-radius: 4px; padding: 2px 8px; font-size: 10px; font-weight: 700; }
    .badge-danger { background: #fee2e2; color: #dc2626; border-radius: 4px; padding: 2px 8px; font-size: 10px; font-weight: 700; }
    .badge-info   { background: #dbeafe; color: #1d4ed8; border-radius: 4px; padding: 2px 8px; font-size: 10px; font-weight: 700; }

    .pompa-body { padding: 16px; display: flex; flex-direction: column; gap: 14px; }
    .pompa-status-row { display: flex; align-items: center; justify-content: space-between; }
    .pompa-status-left { display: flex; align-items: center; gap: 8px; }
    .pompa-dot { width: 10px; height: 10px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,0.15); transition: all 0.3s; flex-shrink: 0; }
    .pompa-dot.off { background: #9ca3af; box-shadow: none; }
    .pompa-status-text { font-size: 14px; font-weight: 600; color: var(--text); }
    .pompa-mode-badge { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; transition: all 0.2s; }
    .pompa-mode-badge.auto { background: #fef9c3; color: #92400e; border: 0.5px solid #fde68a; }
    .pompa-mode-badge.on   { background: #dcfce7; color: #14532d; border: 0.5px solid #86efac; }
    .pompa-mode-badge.off  { background: #f3f4f6; color: #4b5563; border: 0.5px solid #d1d5db; }
    .pompa-btn-group { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
    .pompa-btn { display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 11px 6px; border-radius: 8px; border: 1px solid var(--border); background: #fff; cursor: pointer; transition: all 0.15s; font-size: 11px; font-weight: 600; color: var(--muted); font-family: 'DM Sans', sans-serif; }
    .pompa-btn i { font-size: 18px; }
    .pompa-btn:hover { background: var(--green-pale); border-color: var(--green-light); color: var(--green-dark); }
    .pompa-btn.active-auto { border: 1.5px solid #f59e0b; background: #fef9c3; color: #92400e; }
    .pompa-btn.active-on   { border: 1.5px solid #16a34a; background: #dcfce7; color: #14532d; }
    .pompa-btn.active-off  { border: 1.5px solid #9ca3af; background: #f3f4f6; color: #374151; }
    .pompa-info { font-size: 11px; color: var(--muted); text-align: center; line-height: 1.5; padding: 8px 10px; background: var(--green-pale); border-radius: 6px; }

    .riwayat-wrap { max-width: 1100px; margin: 18px auto; padding: 0 16px; }
    .riwayat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
    .riwayat-header h2 { font-size: 15px; font-weight: 700; color: var(--text); }
    .btn-hapus-toggle { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; padding: 8px 16px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
    .btn-hapus-toggle:hover { background: #fecaca; }
    .btn-hapus-all { background: #dc2626; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
    .btn-hapus-all:hover { background: #b91c1c; }
    .riwayat-section { background: #fff; border-radius: 10px; border: 1px solid var(--border); overflow: hidden; margin-bottom: 16px; }
    .riwayat-section-header { padding: 10px 14px; border-bottom: 1px solid var(--border); font-size: 12px; font-weight: 600; color: var(--muted); letter-spacing: .5px; text-transform: uppercase; background: var(--green-pale); }
    .foto-histori-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; padding: 12px 14px; }
    .foto-histori-item { border-radius: 8px; overflow: hidden; border: 1px solid var(--border); cursor: pointer; transition: transform .2s, box-shadow .2s; }
    .foto-histori-item:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.12); }
    .foto-histori-item img { width: 100%; height: 110px; object-fit: cover; display: block; }
    .foto-histori-label { padding: 4px 8px; font-size: 10px; font-family: 'Space Mono', monospace; color: var(--muted); background: #fff; }
    .riwayat-chart-inner { height: 200px; padding: 12px 14px; }
    .riwayat-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .riwayat-table th { background: var(--green-pale); padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid var(--border); }
    .riwayat-table td { padding: 9px 14px; border-bottom: 1px solid var(--border); font-family: 'Space Mono', monospace; font-size: 11px; }
    .riwayat-table tr:last-child td { border-bottom: none; }
    .riwayat-table tr:hover td { background: var(--green-pale); }
    .loading-text { text-align: center; padding: 30px; color: var(--muted); font-size: 13px; }
    .foto-count-badge { background: var(--green-pale); color: var(--green-dark); border-radius: 20px; padding: 3px 12px; font-size: 11px; font-weight: 600; }

    .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 999; align-items: center; justify-content: center; }
    .modal-backdrop.show { display: flex; }
    .modal-box { background: #fff; border-radius: 12px; padding: 24px; width: 90%; max-width: 400px; }
    .modal-box h3 { font-size: 15px; font-weight: 700; color: #1a2e1e; margin-bottom: 6px; }
    .modal-box p { font-size: 12px; color: var(--muted); margin-bottom: 18px; }
    .modal-box label { font-size: 11px; color: var(--muted); display: block; margin-bottom: 4px; }
    .modal-box input[type=date] { width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; font-family: 'DM Sans', sans-serif; margin-bottom: 12px; }
    .modal-footer { display: flex; gap: 8px; justify-content: flex-end; margin-top: 8px; flex-wrap: wrap; }
    .btn-batal { background: none; border: 1px solid var(--border); padding: 8px 16px; border-radius: 6px; font-size: 12px; cursor: pointer; color: var(--muted); }
    .btn-danger { background: #dc2626; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
    .btn-danger:hover { background: #b91c1c; }
    .divider { border: none; border-top: 1px solid var(--border); margin: 14px 0; }

    #modal-foto-viewer .modal-inner { background: #111; border-radius: 12px; padding: 16px; max-width: 90vw; max-height: 90vh; display: flex; flex-direction: column; align-items: center; gap: 10px; position: relative; }
    #modal-foto-viewer img { max-width: 80vw; max-height: 70vh; border-radius: 8px; object-fit: contain; }
    #modal-foto-viewer .close-btn { position: absolute; top: 10px; right: 12px; background: rgba(255,255,255,0.15); border: none; color: #fff; font-size: 18px; cursor: pointer; border-radius: 50%; width: 30px; height: 30px; line-height: 30px; text-align: center; }
    #modal-foto-viewer .foto-waktu { color: rgba(255,255,255,0.7); font-family: 'Space Mono', monospace; font-size: 11px; margin: 0; }
    #modal-foto-viewer .btn-download { background: var(--green-mid); color: #fff; padding: 6px 16px; border-radius: 6px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; }

    /* Toast notifikasi */
    #toast {
      position: fixed; bottom: 20px; right: 20px; z-index: 9999;
      background: #1a2e1e; color: #fff; padding: 10px 18px;
      border-radius: 8px; font-size: 13px; font-family: 'DM Sans', sans-serif;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      opacity: 0; transition: opacity 0.3s; pointer-events: none;
    }
    #toast.show { opacity: 1; }

    @media (max-width: 900px) { .container { grid-template-columns: 1fr; } }
    @media (max-width: 768px) {
      nav { padding: 8px 12px; height: auto; }
      nav .brand { font-size: 13px; }
      nav .nav-right { font-size: 10px; gap: 8px; }
      .cam-id { display: none; }
      .main-image-wrap { max-height: 240px; }
      .gauges-row { gap: 4px; }
      .gauge-svg { width: 65px; height: 65px; }
      .foto-histori-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); }
    }
  </style>
</head>
<body>

<!-- ===== MODAL HAPUS DATA SENSOR ===== -->
<div id="hapus-modal" class="modal-backdrop">
  <div class="modal-box">
    <h3>🗑️ Hapus Data Sensor</h3>
    <p>Pilih rentang tanggal yang ingin dihapus, atau hapus semua data sekaligus.</p>
    <label>Dari Tanggal</label>
    <input type="date" id="hapus-dari">
    <label>Sampai Tanggal</label>
    <input type="date" id="hapus-sampai">
    <div class="modal-footer">
      <button class="btn-batal" onclick="closeModal('hapus-modal')">Batal</button>
      <button class="btn-danger" onclick="hapusData()">Hapus Rentang</button>
    </div>
    <hr class="divider">
    <p style="margin-bottom:0;font-size:11px;color:#dc2626;">⚠️ Hapus semua data riwayat sensor secara permanen.</p>
    <div class="modal-footer" style="margin-top:10px;">
      <button class="btn-danger" onclick="hapusSemua()" style="background:#7f1d1d;">🗑️ Hapus Semua Data</button>
    </div>
  </div>
</div>

<!-- ===== MODAL HAPUS RIWAYAT FOTO ===== -->
<div id="hapus-foto-modal" class="modal-backdrop">
  <div class="modal-box">
    <h3>🗑️ Hapus Riwayat Foto</h3>
    <p style="color:#dc2626;font-size:12px;margin-bottom:20px;">⚠️ Semua riwayat foto akan dihapus secara permanen dan tidak dapat dikembalikan.</p>
    <div class="modal-footer">
      <button class="btn-batal" onclick="closeModal('hapus-foto-modal')">Batal</button>
      <button class="btn-danger" onclick="hapusFoto()">Ya, Hapus Semua Foto</button>
    </div>
  </div>
</div>

<!-- ===== MODAL VIEWER FOTO ===== -->
<div id="modal-foto-viewer" class="modal-backdrop" onclick="if(event.target===this) tutupModalFoto()">
  <div class="modal-inner">
    <button class="close-btn" onclick="tutupModalFoto()">✕</button>
    <img id="modal-foto-img" src="" alt="foto tanaman">
    <p id="modal-foto-waktu" class="foto-waktu"></p>
    <button class="btn-download" onclick="downloadFoto()">⬇️ Download Foto</button>
  </div>
</div>

<!-- Toast -->
<div id="toast"></div>

<!-- ===== NAV ===== -->
<nav>
  <div class="brand"><span>Monitoring</span>&nbsp;Melon</div>
  <div class="nav-right">
    <span>
      <span class="status-dot" id="nav-dot"></span>
      <span id="nav-live-text">CONNECTING</span>
    </span>
    <span class="cam-id">CAM_ID: MELON01</span>
    <span id="clock">--:--:--</span>
    <span style="border-left:1px solid rgba(255,255,255,0.3);padding-left:12px;">
      Halo, {{ auth()->user()->name ?? 'Guest' }}
    </span>
    <form method="POST" action="{{ route('logout') }}" style="display:inline;">
      @csrf
      <button type="submit" style="background:none;border:none;color:rgba(255,255,255,0.7);cursor:pointer;font-family:'Space Mono',monospace;font-size:12px;text-decoration:underline;padding:0;">
        Logout
      </button>
    </form>
  </div>
</nav>

<!-- ===== TAB MENU ===== -->
<div class="tab-menu">
  <button class="tab-btn active" onclick="switchTab(this,'dashboard')">📊 Dashboard</button>
  <button class="tab-btn" onclick="switchTab(this,'riwayat')">📋 Riwayat Data</button>
  <button class="tab-btn" onclick="switchTab(this,'foto')">📸 Riwayat Foto</button>
</div>

<!-- ===== TAB DASHBOARD ===== -->
<div id="tab-dashboard" class="tab-content active">
  <div class="container">
    <div style="display:flex;flex-direction:column;gap:16px;min-width:0;">
      <div class="card">
        {{-- Header dengan tombol ambil foto --}}
        <div class="card-header" style="justify-content:space-between;">
          <span>📹 Live Camera — MELON01</span>
          <button class="btn-capture" id="btn-capture" onclick="ambilFotoManual()">
            📸 Ambil Foto
          </button>
        </div>
        <div class="main-image-wrap">
          <img id="mainImg" alt="Live Stream" style="width:100%;height:100%;object-fit:cover;display:block;">
          <div class="offline-overlay" id="offline-overlay">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5">
              <path d="M15.6 11.6L22 7v10l-6.4-4.5v-1zM4 5h9a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z"/>
              <line x1="2" y1="2" x2="22" y2="22" stroke="#ef4444" stroke-width="2"/>
            </svg>
            <span>STREAM OFFLINE</span>
          </div>
          <span class="stream-status" id="stream-status">● CONNECTING...</span>
          <div class="img-overlay">
            <div class="img-meta">
              <span class="live-badge" id="live-badge" style="background:#f59e0b;">CONNECTING</span>
              <span>TEMP: <span class="val" id="overlay-suhu">{{ $latest->suhu ?? '-' }}°C</span></span>
              <span>HUM: <span class="val" id="overlay-kelembapan">{{ $latest->kelembapan ?? '-' }}%</span></span>
              <span>SOIL: <span class="val" id="overlay-soil">{{ $latest->soil ?? '-' }}%</span></span>
              <span>DATE: <span class="val" id="overlay-date">{{ $latest->created_at ?? 'n/a' }}</span></span>
            </div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header">⏱ Time-lapse Progress</div>
        <div class="timelapse-strip" id="timelapse-strip">
          <p style="color:var(--muted);font-size:12px;padding:10px;">Memuat foto...</p>
        </div>
      </div>
    </div>

    <div class="right-col">
      <div class="card">
        <div class="card-header">
          <i class="ti ti-droplet" style="font-size:14px;color:#1d4ed8;"></i>
          Kontrol Pompa
        </div>
        <div class="pompa-body">
          <div class="pompa-status-row">
            <div class="pompa-status-left">
              <div class="pompa-dot" id="pompa-dot"></div>
              <span class="pompa-status-text" id="pompa-status-text">Menyala</span>
            </div>
            <div class="pompa-mode-badge auto" id="pompa-mode-badge">Otomatis</div>
          </div>
          <div class="pompa-btn-group">
            <button class="pompa-btn active-auto" id="btn-auto" onclick="setPompa(-1)">
              <i class="ti ti-refresh"></i>Auto
            </button>
            <button class="pompa-btn" id="btn-on" onclick="setPompa(1)">
              <i class="ti ti-player-play"></i>Nyala
            </button>
            <button class="pompa-btn" id="btn-off" onclick="setPompa(0)">
              <i class="ti ti-player-stop"></i>Mati
            </button>
          </div>
          <div class="pompa-info" id="pompa-info-text">
            Pompa dikontrol otomatis oleh sensor soil moisture
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">🌡 Sensor Status</div>
        <div class="gauges-row">
          @php
            $suhu       = $latest->suhu ?? 0;
            $kelembapan = $latest->kelembapan ?? 0;
            $soil       = $latest->soil ?? 0;
            $circumf    = 201;
            $offsetSuhu = round($circumf * (1 - min($suhu, 100) / 100));
            $offsetHum  = round($circumf * (1 - min($kelembapan, 100) / 100));
            $offsetSoil = round($circumf * (1 - min($soil, 100) / 100));
          @endphp
          <div class="gauge-wrap">
            <svg class="gauge-svg" viewBox="0 0 80 80">
              <circle class="gauge-bg" cx="40" cy="40" r="32" stroke-dasharray="{{ $circumf }}" stroke-dashoffset="0"/>
              <circle id="gauge-suhu-circle" cx="40" cy="40" r="32" fill="none" stroke-width="8" stroke-linecap="round" stroke="#e53e3e" stroke-dasharray="{{ $circumf }}" stroke-dashoffset="{{ $offsetSuhu }}"/>
              <g transform="rotate(90, 40, 40)">
                <text id="gauge-suhu-text" class="gauge-val-text" x="40" y="37">{{ $suhu }}°</text>
                <text class="gauge-sub" x="40" y="51">Temp</text>
              </g>
            </svg>
            <div class="gauge-label">Temperature</div>
          </div>
          <div class="gauge-wrap">
            <svg class="gauge-svg" viewBox="0 0 80 80">
              <circle class="gauge-bg" cx="40" cy="40" r="32" stroke-dasharray="{{ $circumf }}" stroke-dashoffset="0"/>
              <circle id="gauge-hum-circle" cx="40" cy="40" r="32" fill="none" stroke-width="8" stroke-linecap="round" stroke="#00b5a3" stroke-dasharray="{{ $circumf }}" stroke-dashoffset="{{ $offsetHum }}"/>
              <g transform="rotate(90, 40, 40)">
                <text id="gauge-hum-text" class="gauge-val-text" x="40" y="37">{{ $kelembapan }}%</text>
                <text class="gauge-sub" x="40" y="51">Humid</text>
              </g>
            </svg>
            <div class="gauge-label">Humidity</div>
          </div>
          <div class="gauge-wrap">
            <svg class="gauge-svg" viewBox="0 0 80 80">
              <circle class="gauge-bg" cx="40" cy="40" r="32" stroke-dasharray="{{ $circumf }}" stroke-dashoffset="0"/>
              <circle id="gauge-soil-circle" cx="40" cy="40" r="32" fill="none" stroke-width="8" stroke-linecap="round" stroke="#2563eb" stroke-dasharray="{{ $circumf }}" stroke-dashoffset="{{ $offsetSoil }}"/>
              <g transform="rotate(90, 40, 40)">
                <text id="gauge-soil-text" class="gauge-val-text" x="40" y="37">{{ $soil }}%</text>
                <text class="gauge-sub" x="40" y="51">Soil</text>
              </g>
            </svg>
            <div class="gauge-label">Soil Moisture</div>
          </div>
        </div>
        <table class="sensor-table">
          <tr>
            <td class="key">Suhu</td>
            <td class="val-cell" id="val-suhu">{{ $suhu }} °C</td>
            <td><span id="badge-suhu" class="badge-ok">NORMAL</span></td>
          </tr>
          <tr>
            <td class="key">Kelembapan</td>
            <td class="val-cell" id="val-kelembapan">{{ $kelembapan }} %</td>
            <td><span id="badge-kelembapan" class="badge-ok">NORMAL</span></td>
          </tr>
          <tr>
            <td class="key">Soil Moisture</td>
            <td class="val-cell" id="val-soil">{{ $soil }} %</td>
            <td><span id="badge-soil" class="badge-ok">IDEAL</span></td>
          </tr>
        </table>
      </div>

      <div class="card">
        <div class="card-header">📈 Time-series Chart</div>
        <div class="chart-wrap"><canvas id="miniChart"></canvas></div>
        <div class="chart-legend">
          <div class="legend-item"><div class="legend-dot" style="background:#e53e3e"></div>Suhu</div>
          <div class="legend-item"><div class="legend-dot" style="background:#00b5a3"></div>Kelembapan</div>
          <div class="legend-item"><div class="legend-dot" style="background:#2563eb"></div>Soil Moisture</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ===== TAB RIWAYAT DATA ===== -->
<div id="tab-riwayat" class="tab-content">
  <div class="riwayat-wrap">
    <div class="riwayat-header">
      <h2>📋 Riwayat Data Sensor</h2>
      <button class="btn-hapus-toggle" onclick="openModal('hapus-modal')">🗑️ Hapus Data</button>
    </div>
    <div class="riwayat-section">
      <div class="riwayat-section-header">📈 Grafik Riwayat Sensor</div>
      <div class="riwayat-chart-inner"><canvas id="riwayatChart"></canvas></div>
      <div class="chart-legend" style="padding:0 14px 12px;">
        <div class="legend-item"><div class="legend-dot" style="background:#e53e3e"></div>Suhu</div>
        <div class="legend-item"><div class="legend-dot" style="background:#00b5a3"></div>Kelembapan</div>
        <div class="legend-item"><div class="legend-dot" style="background:#2563eb"></div>Soil Moisture</div>
      </div>
    </div>
    <div class="riwayat-section">
      <div class="riwayat-section-header">📊 Tabel Data Sensor</div>
      <div style="overflow-x:auto;">
        <table class="riwayat-table">
          <thead>
            <tr>
              <th>No</th><th>Waktu</th><th>Suhu (°C)</th><th>Kelembapan (%)</th>
              <th>Soil (%)</th><th>St. Suhu</th><th>St. Kelembapan</th><th>St. Soil</th>
            </tr>
          </thead>
          <tbody id="riwayat-tbody">
            <tr><td colspan="8" class="loading-text">Memuat data...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ===== TAB RIWAYAT FOTO ===== -->
<div id="tab-foto" class="tab-content">
  <div class="riwayat-wrap">
    <div class="riwayat-header">
      <h2>📸 Riwayat Foto</h2>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <span class="foto-count-badge" id="foto-count">0 foto</span>
        <button class="btn-hapus-all" onclick="openModal('hapus-foto-modal')">🗑️ Hapus Riwayat Foto</button>
      </div>
    </div>
    <div class="riwayat-section">
      <div class="riwayat-section-header">Histori Foto Tanaman (Maks. 30 Foto Terbaru)</div>
      <div class="foto-histori-grid" id="foto-grid">
        <p style="color:var(--muted);font-size:13px;padding:10px;">Memuat foto...</p>
      </div>
    </div>
  </div>
</div>

<!-- ===== SCRIPT UTAMA ===== -->
<script>
  // ===================== KONFIGURASI =====================
  // Stream via VPS proxy (multi-device support)
  const ESP32_STREAM = 'https://stream.xdrop-agent.my.id/stream?token=melon-cam-2024';

  // ===================== CLOCK =====================
  function tick() { document.getElementById('clock').textContent = new Date().toLocaleTimeString('id-ID'); }
  tick(); setInterval(tick, 1000);

  // ===================== MODAL =====================
  function openModal(id)  { document.getElementById(id).classList.add('show'); }
  function closeModal(id) { document.getElementById(id).classList.remove('show'); }

  // ===================== TOAST =====================
  function showToast(msg, durasi = 3000) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), durasi);
  }

  // ===================== TAB =====================
  function switchTab(btn, tab) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    btn.classList.add('active');
  }

  // ===================== LIVE STREAM =====================
  function setMain(src) {
    const img = document.getElementById('mainImg');
    img.classList.remove('hidden');
    document.getElementById('offline-overlay').classList.remove('show');
    img.src = src;
    // Kembali ke stream setelah 10 detik
    setTimeout(() => startStream(), 10000);
  }

  function setStreamState(state) {
    const streamStatus   = document.getElementById('stream-status');
    const liveBadge      = document.getElementById('live-badge');
    const navDot         = document.getElementById('nav-dot');
    const navText        = document.getElementById('nav-live-text');
    const offlineOverlay = document.getElementById('offline-overlay');
    const img            = document.getElementById('mainImg');
    if (state === 'live') {
      offlineOverlay.classList.remove('show'); img.classList.remove('hidden');
      streamStatus.textContent = '● LIVE'; streamStatus.style.color = '#4ade80';
      liveBadge.textContent = 'LIVE'; liveBadge.style.background = '#ef4444';
      navDot.style.background = '#4ade80'; navText.textContent = 'LIVE';
    } else if (state === 'offline') {
      offlineOverlay.classList.add('show'); img.classList.add('hidden');
      streamStatus.textContent = '● OFFLINE'; streamStatus.style.color = '#ef4444';
      liveBadge.textContent = 'OFFLINE'; liveBadge.style.background = '#6b7280';
      navDot.style.background = '#ef4444'; navText.textContent = 'OFFLINE';
    } else {
      offlineOverlay.classList.remove('show'); img.classList.remove('hidden');
      streamStatus.textContent = '● CONNECTING...'; streamStatus.style.color = '#fff';
      liveBadge.textContent = 'CONNECTING'; liveBadge.style.background = '#f59e0b';
      navDot.style.background = '#f59e0b'; navText.textContent = 'CONNECTING';
    }
  }

  const mainImg = document.getElementById('mainImg');
  let reconnectTimer = null;
  function startStream() {
    if (reconnectTimer) { clearTimeout(reconnectTimer); reconnectTimer = null; }
    setStreamState('connecting');
    mainImg.src = ESP32_STREAM + '&' + Date.now();
  }
  mainImg.onload  = function() {
    setStreamState('live');
    if (reconnectTimer) { clearTimeout(reconnectTimer); reconnectTimer = null; }
  };
  mainImg.onerror = function() {
    setStreamState('offline');
    reconnectTimer = setTimeout(startStream, 5000);
  };
  startStream();

  // ===================== BADGE HELPERS =====================
  function getBadgeSuhu(val) {
    val = parseFloat(val);
    if (isNaN(val)) return '<span class="badge-warn">-</span>';
    if (val >= 20 && val <= 35) return '<span class="badge-ok">NORMAL</span>';
    if (val > 35)               return '<span class="badge-danger">PANAS</span>';
    return '<span class="badge-warn">DINGIN</span>';
  }
  function getBadgeKelembapan(val) {
    val = parseFloat(val);
    if (isNaN(val)) return '<span class="badge-warn">-</span>';
    if (val >= 60 && val <= 90) return '<span class="badge-ok">NORMAL</span>';
    if (val < 60)               return '<span class="badge-warn">RENDAH</span>';
    return '<span class="badge-warn">TINGGI</span>';
  }
  function getBadgeSoil(val) {
    val = parseFloat(val);
    if (isNaN(val)) return '<span class="badge-warn">-</span>';
    if (val <= 40)  return '<span class="badge-danger">KERING</span>';
    if (val <= 60)  return '<span class="badge-ok">IDEAL</span>';
    return           '<span class="badge-info">BASAH</span>';
  }

  // ===================== MINI CHART =====================
  const timeseries = @json($timeseries);
  new Chart(document.getElementById('miniChart').getContext('2d'), {
    type: 'line',
    data: {
      labels: timeseries.map(t => t.t),
      datasets: [
        { label:'Suhu',          data: timeseries.map(t=>t.suhu),       borderColor:'#e53e3e', backgroundColor:'rgba(229,62,62,.08)',  tension:0.4, pointRadius:0, fill:true },
        { label:'Kelembapan',    data: timeseries.map(t=>t.kelembapan), borderColor:'#00b5a3', backgroundColor:'rgba(0,181,163,.08)',   tension:0.4, pointRadius:0, fill:true },
        { label:'Soil Moisture', data: timeseries.map(t=>t.soil),       borderColor:'#2563eb', backgroundColor:'rgba(37,99,235,.08)',   tension:0.4, pointRadius:0, fill:true },
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { x: { display: false }, y: { grid: { color:'#e8f5ec' }, ticks: { font: { size:9 }, color:'#6b7c6e' } } }
    }
  });

  // ===================== AMBIL FOTO MANUAL =====================
  // Set Firebase flag → ESP32 baca flag → capture + watermark + upload
  window.ambilFotoManual = async function() {
    const btn = document.getElementById('btn-capture');
    btn.disabled = true;
    btn.textContent = '⏳ Menunggu ESP32...';
    showToast('📷 Mengirim perintah ke ESP32...');

    try {
      // Set flag di Firebase
      await set(ref(db, '/capture'), true);
      showToast('✅ Perintah terkirim! ESP32 akan ambil foto dalam beberapa detik...', 5000);
      
      // Tunggu foto baru muncul (listener /foto akan update otomatis)
      setTimeout(() => {
        btn.disabled = false;
        btn.textContent = '📸 Ambil Foto';
      }, 15000); // Wait 15s for ESP32 to capture + upload
      
    } catch (err) {
      console.error('Capture error:', err);
      showToast('❌ Gagal: ' + err.message, 4000);
      btn.disabled = false;
      btn.textContent = '📸 Ambil Foto';
    }
  };

  // ===================== MODAL VIEWER FOTO =====================
  window.allFotoSrc = [];
  window.bukaModalFoto = function(idx) {
    const item = window.allFotoSrc[idx];
    if (!item) return;
    document.getElementById('modal-foto-img').src = item.src;
    document.getElementById('modal-foto-waktu').textContent = item.waktu;
    document.getElementById('modal-foto-viewer').classList.add('show');
  };
  window.tutupModalFoto = function() {
    document.getElementById('modal-foto-viewer').classList.remove('show');
    setTimeout(() => { document.getElementById('modal-foto-img').src = ''; }, 300);
  };
  window.downloadFoto = function() {
    const img   = document.getElementById('modal-foto-img');
    const waktu = document.getElementById('modal-foto-waktu').textContent;
    const a = document.createElement('a');
    a.href = img.src;
    a.download = 'foto-melon-' + waktu.replace(/[/:, ]/g, '-') + '.jpg';
    a.click();
  };

  // ===================== POMPA UI =====================
  function updatePompaUI(mode, pompaStatus) {
    const dot       = document.getElementById('pompa-dot');
    const statusTxt = document.getElementById('pompa-status-text');
    const badge     = document.getElementById('pompa-mode-badge');
    const infoTxt   = document.getElementById('pompa-info-text');
    const btnAuto   = document.getElementById('btn-auto');
    const btnOn     = document.getElementById('btn-on');
    const btnOff    = document.getElementById('btn-off');
    btnAuto.className = 'pompa-btn';
    btnOn.className   = 'pompa-btn';
    btnOff.className  = 'pompa-btn';
    if (mode === -1) {
      btnAuto.className = 'pompa-btn active-auto';
      badge.className   = 'pompa-mode-badge auto';
      badge.textContent = 'Otomatis';
      infoTxt.textContent = 'Pompa dikontrol otomatis oleh sensor soil moisture';
    } else if (mode === 1) {
      btnOn.className   = 'pompa-btn active-on';
      badge.className   = 'pompa-mode-badge on';
      badge.textContent = 'Manual ON';
      infoTxt.textContent = '⚠️ Pompa dipaksa menyala dari dashboard';
    } else {
      btnOff.className  = 'pompa-btn active-off';
      badge.className   = 'pompa-mode-badge off';
      badge.textContent = 'Manual OFF';
      infoTxt.textContent = '⚠️ Pompa dipaksa mati dari dashboard';
    }
    const nyala = (pompaStatus === 'ON') || (mode === 1);
    dot.className       = nyala ? 'pompa-dot' : 'pompa-dot off';
    statusTxt.textContent = nyala ? 'Menyala' : 'Mati';
  }
</script>

<!-- ===== FIREBASE ===== -->
<script type="module">
  import { initializeApp }                          from "https://www.gstatic.com/firebasejs/10.0.0/firebase-app.js";
  import { getDatabase, ref, onValue, remove, set, push } from "https://www.gstatic.com/firebasejs/10.0.0/firebase-database.js";

  const app = initializeApp({
    apiKey:      "AIzaSyCmIIEjajnv7m95T4gMoUUMCwFcf2qwClw",
    databaseURL: "https://monitoring-tanaman-d2cd2-default-rtdb.firebaseio.com",
    projectId:   "monitoring-tanaman-d2cd2",
  });
  const db      = getDatabase(app);
  const circumf = 201;

  // ===================== SIMPAN FOTO KE FIREBASE =====================
  // Dipanggil dari event 'simpanFoto' yang di-dispatch setelah canvas selesai
  document.addEventListener('simpanFoto', async () => {
    const pending = window._pendingFoto;
    if (!pending) return;
    window._pendingFoto = null;

    try {
      showToast('☁️ Menyimpan foto ke Firebase...');
      await push(ref(db, '/foto'), {
        image:     pending.image,
        timestamp: pending.timestamp,
        waktu:     pending.waktu
      });
      showToast('✅ Foto berhasil disimpan!', 3000);
    } catch (err) {
      console.error('Firebase save error:', err);
      showToast('❌ Gagal simpan: ' + err.message, 4000);
    }
  });

  // ===================== KONTROL POMPA =====================
  window.setPompa = async function(mode) {
    try {
      await set(ref(db, '/relay'), mode);
      const label = mode === -1 ? 'AUTO' : mode === 1 ? 'ON MANUAL' : 'OFF MANUAL';
      console.log('✅ Pompa set ke:', label);
    } catch (e) {
      alert('❌ Gagal kirim perintah pompa: ' + e.message);
    }
  };

  // ===================== LISTENER /relay =====================
  onValue(ref(db, '/relay'), (snapshot) => {
    const mode = snapshot.val() ?? -1;
    onValue(ref(db, '/sensor/latest'), (snap) => {
      const latest = snap.val();
      const pompaStatus = latest?.pompa ?? 'OFF';
      updatePompaUI(mode, pompaStatus);
    }, { onlyOnce: true });
  });

  // ===================== RIWAYAT FOTO =====================
  onValue(ref(db, '/foto'), (snapshot) => {
    const data       = snapshot.val();
    const grid       = document.getElementById('foto-grid');
    const countBadge = document.getElementById('foto-count');

    if (!data) {
      grid.innerHTML = '<p style="color:var(--muted);font-size:13px;padding:10px;">Belum ada foto tersimpan</p>';
      countBadge.textContent = '0 foto';
      return;
    }

    const fotos = Object.values(data)
      .sort((a, b) => b.timestamp - a.timestamp)
      .slice(0, 30);

    countBadge.textContent = fotos.length + ' foto';
    window.allFotoSrc = [];

    // Timelapse strip
    let stripHtml = '';
    fotos.forEach((foto, i) => {
      const ts    = foto.timestamp < 1e10 ? foto.timestamp * 1000 : foto.timestamp;
      const src   = 'data:image/jpeg;base64,' + foto.image;
      const waktu = new Date(ts).toLocaleString('id-ID', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' });
      stripHtml += `
        <div style="flex-shrink:0;position:relative;">
          <img src="${src}" onclick="setMain('${src}')"
            style="width:80px;height:58px;object-fit:cover;border-radius:6px;
                   border:2px solid ${i===0?'var(--green-mid)':'transparent'};cursor:pointer;" loading="lazy">
          <span style="position:absolute;bottom:3px;left:50%;transform:translateX(-50%);
                 background:rgba(0,0,0,.55);color:#fff;font-size:9px;font-family:'Space Mono',monospace;
                 padding:1px 5px;border-radius:3px;white-space:nowrap;">${waktu}</span>
        </div>`;
    });
    document.getElementById('timelapse-strip').innerHTML = stripHtml;

    // Grid foto
    let gridHtml = '';
    fotos.forEach((foto, i) => {
      const ts    = foto.timestamp < 1e10 ? foto.timestamp * 1000 : foto.timestamp;
      const src   = 'data:image/jpeg;base64,' + foto.image;
      const waktu = new Date(ts).toLocaleString('id-ID');
      window.allFotoSrc.push({ src, waktu });
      gridHtml += `
        <div class="foto-histori-item" onclick="bukaModalFoto(${i})">
          <img src="${src}" title="${waktu}" alt="foto tanaman" loading="lazy">
          <div class="foto-histori-label">${waktu}</div>
        </div>`;
    });
    grid.innerHTML = gridHtml;
  });

  // ===================== SENSOR LATEST =====================
  onValue(ref(db, '/sensor/latest'), (snapshot) => {
    const data = snapshot.val();
    if (!data) return;
    const suhu       = parseFloat(data.suhu)       || 0;
    const kelembapan = parseFloat(data.kelembapan) || 0;
    const soil       = parseFloat(data.soil)       || 0;
    const updatedAt  = data.updated_at ?? data.created_at ?? 'n/a';

    document.getElementById('overlay-suhu').textContent       = suhu + '°C';
    document.getElementById('overlay-kelembapan').textContent = kelembapan + '%';
    document.getElementById('overlay-soil').textContent       = soil + '%';
    document.getElementById('overlay-date').textContent       = updatedAt;
    document.getElementById('val-suhu').textContent           = suhu + ' °C';
    document.getElementById('val-kelembapan').textContent     = kelembapan + ' %';
    document.getElementById('val-soil').textContent           = soil + ' %';

    document.getElementById('gauge-suhu-circle').setAttribute('stroke-dashoffset',  Math.round(circumf * (1 - Math.min(suhu, 100) / 100)));
    document.getElementById('gauge-suhu-text').textContent  = suhu + '°';
    document.getElementById('gauge-hum-circle').setAttribute('stroke-dashoffset',   Math.round(circumf * (1 - Math.min(kelembapan, 100) / 100)));
    document.getElementById('gauge-hum-text').textContent   = kelembapan + '%';
    document.getElementById('gauge-soil-circle').setAttribute('stroke-dashoffset',  Math.round(circumf * (1 - Math.min(soil, 100) / 100)));
    document.getElementById('gauge-soil-text').textContent  = soil + '%';

    const bs = document.getElementById('badge-suhu');
    if (suhu >= 20 && suhu <= 35)      { bs.className = 'badge-ok';    bs.textContent = 'NORMAL'; }
    else if (suhu > 35)                { bs.className = 'badge-danger'; bs.textContent = 'PANAS'; }
    else                               { bs.className = 'badge-warn';   bs.textContent = 'DINGIN'; }

    const bh = document.getElementById('badge-kelembapan');
    if (kelembapan >= 60 && kelembapan <= 90) { bh.className = 'badge-ok';   bh.textContent = 'NORMAL'; }
    else if (kelembapan < 60)                  { bh.className = 'badge-warn'; bh.textContent = 'RENDAH'; }
    else                                        { bh.className = 'badge-warn'; bh.textContent = 'TINGGI'; }

    const bso = document.getElementById('badge-soil');
    if (soil <= 40)      { bso.className = 'badge-danger'; bso.textContent = 'KERING'; }
    else if (soil <= 60) { bso.className = 'badge-ok';     bso.textContent = 'IDEAL'; }
    else                 { bso.className = 'badge-info';   bso.textContent = 'BASAH'; }
  });

  // ===================== HAPUS DATA RENTANG =====================
  window.hapusData = async function() {
    const dari   = document.getElementById('hapus-dari').value;
    const sampai = document.getElementById('hapus-sampai').value;
    if (!dari || !sampai) { alert('Pilih tanggal mulai dan akhir dulu!'); return; }
    if (!confirm(`Yakin hapus data dari ${dari} sampai ${sampai}?`)) return;
    const snapshot = await new Promise(res => onValue(ref(db, '/sensor/history'), res, { onlyOnce: true }));
    const raw = snapshot.val();
    if (!raw) { alert('Tidak ada data!'); return; }
    const keysToDelete = [];
    for (const [key, val] of Object.entries(raw)) {
      const waktuData = val.created_at || val.updated_at || '';
      const tgl = waktuData.slice(0, 10);
      if (tgl && tgl >= dari && tgl <= sampai) keysToDelete.push(key);
    }
    if (keysToDelete.length === 0) { alert('Tidak ada data pada rentang tanggal tersebut.'); return; }
    await Promise.all(keysToDelete.map(key => remove(ref(db, '/sensor/history/' + key))));
    alert(`✅ ${keysToDelete.length} data berhasil dihapus!`);
    closeModal('hapus-modal');
  };

  window.hapusSemua = async function() {
    if (!confirm('⚠️ Yakin ingin menghapus SEMUA data riwayat sensor?')) return;
    await remove(ref(db, '/sensor/history'));
    alert('✅ Semua data riwayat sensor berhasil dihapus!');
    closeModal('hapus-modal');
  };

  window.hapusFoto = async function() {
    await remove(ref(db, '/foto'));
    alert('✅ Semua riwayat foto berhasil dihapus!');
    closeModal('hapus-foto-modal');
  };

  // ===================== SENSOR HISTORY CHART & TABLE =====================
  let riwayatChart = null;
  onValue(ref(db, '/sensor/history'), (snapshot) => {
    const raw = snapshot.val();
    if (!raw) {
      document.getElementById('riwayat-tbody').innerHTML =
        '<tr><td colspan="8" class="loading-text">Belum ada data riwayat</td></tr>';
      return;
    }

    const data = Object.values(raw).sort((a, b) => {
      return new Date(b.created_at || b.updated_at || 0) - new Date(a.created_at || a.updated_at || 0);
    });

    let rows = '';
    data.forEach((d, i) => {
      const waktu = d.created_at || d.updated_at || '-';
      rows += `<tr>
        <td>${i + 1}</td>
        <td>${waktu}</td>
        <td>${d.suhu ?? '-'}</td>
        <td>${d.kelembapan ?? '-'}</td>
        <td>${d.soil ?? '-'}</td>
        <td>${getBadgeSuhu(d.suhu)}</td>
        <td>${getBadgeKelembapan(d.kelembapan)}</td>
        <td>${getBadgeSoil(d.soil)}</td>
      </tr>`;
    });
    document.getElementById('riwayat-tbody').innerHTML = rows;

    const ascending = [...data].reverse().slice(-50);
    const rLabels   = ascending.map(d => { const w = d.created_at || d.updated_at || ''; return w ? w.slice(11,16) : '-'; });
    const rSuhu     = ascending.map(d => d.suhu);
    const rHum      = ascending.map(d => d.kelembapan);
    const rSoil     = ascending.map(d => d.soil);

    if (riwayatChart) {
      riwayatChart.data.labels           = rLabels;
      riwayatChart.data.datasets[0].data = rSuhu;
      riwayatChart.data.datasets[1].data = rHum;
      riwayatChart.data.datasets[2].data = rSoil;
      riwayatChart.update();
    } else {
      riwayatChart = new Chart(document.getElementById('riwayatChart').getContext('2d'), {
        type: 'line',
        data: {
          labels: rLabels,
          datasets: [
            { label:'Suhu',          data:rSuhu,  borderColor:'#e53e3e', backgroundColor:'rgba(229,62,62,.08)',  tension:0.4, pointRadius:2, fill:true },
            { label:'Kelembapan',    data:rHum,   borderColor:'#00b5a3', backgroundColor:'rgba(0,181,163,.08)',   tension:0.4, pointRadius:2, fill:true },
            { label:'Soil Moisture', data:rSoil,  borderColor:'#2563eb', backgroundColor:'rgba(37,99,235,.08)',   tension:0.4, pointRadius:2, fill:true },
          ]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
          scales: {
            x: { ticks: { font: { size:9 }, color:'#6b7c6e', maxRotation:45 } },
            y: { grid: { color:'#e8f5ec' }, ticks: { font: { size:9 }, color:'#6b7c6e' } }
          }
        }
      });
    }
  });
</script>

</body>
</html>