     1|<!DOCTYPE html>
     2|<html lang="id">
     3|<head>
     4|  <meta charset="UTF-8">
     5|  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
     6|  <title>Dashboard Monitoring</title>
     7|  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🍈</text></svg>">
     8|  <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
     9|  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    10|  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    11|  <style>
    12|    :root {
    13|      --green-dark: #1a5c2a;
    14|      --green-mid: #2d8a45;
    15|      --green-light: #4caf68;
    16|      --green-pale: #e8f5ec;
    17|      --bg: #f0f4f0;
    18|      --card: #ffffff;
    19|      --text: #1a2e1e;
    20|      --muted: #6b7c6e;
    21|      --border: #d4e4d8;
    22|    }
    23|    * { box-sizing: border-box; margin: 0; padding: 0; }
    24|    body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
    25|
    26|    nav {
    27|      background: var(--green-dark); padding: 0 20px; height: 48px;
    28|      display: flex; align-items: center; justify-content: space-between;
    29|      border-bottom: 3px solid var(--green-light); flex-wrap: wrap; gap: 6px;
    30|    }
    31|    nav .brand { font-family: 'Space Mono', monospace; font-size: 15px; font-weight: 700; color: #fff; letter-spacing: 1px; }
    32|    nav .brand span { color: var(--green-light); }
    33|    nav .nav-right { display: flex; gap: 12px; font-size: 12px; color: rgba(255,255,255,0.7); font-family: 'Space Mono', monospace; align-items: center; flex-wrap: wrap; }
    34|    nav .status-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #4ade80; margin-right: 5px; animation: blink 1.5s infinite; }
    35|    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
    36|
    37|    .tab-menu { background: #fff; border-bottom: 2px solid var(--border); display: flex; padding: 0 12px; overflow-x: auto; }
    38|    .tab-menu::-webkit-scrollbar { display: none; }
    39|    .tab-btn { padding: 10px 16px; font-size: 13px; font-weight: 600; color: var(--muted); border: none; background: none; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; transition: all .2s; font-family: 'DM Sans', sans-serif; white-space: nowrap; }
    40|    .tab-btn:hover { color: var(--green-mid); }
    41|    .tab-btn.active { color: var(--green-dark); border-bottom-color: var(--green-mid); }
    42|    .tab-content { display: none; }
    43|    .tab-content.active { display: block; }
    44|
    45|    .container { max-width: 1100px; margin: 18px auto; padding: 0 16px; display: grid; grid-template-columns: 1fr 300px; gap: 16px; align-items: start; }
    46|    .card { background: var(--card); border-radius: 10px; border: 1px solid var(--border); overflow: hidden; }
    47|    .card-header { padding: 10px 14px; border-bottom: 1px solid var(--border); font-size: 12px; font-weight: 600; color: var(--muted); letter-spacing: .5px; text-transform: uppercase; background: var(--green-pale); }
    48|
    49|    .main-image-wrap { position: relative; background: #111; aspect-ratio: 16/9; max-height: 340px; overflow: hidden; width: 100%; }
    50|    .main-image-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; opacity: .95; }
    51|    .offline-overlay { display: none; position: absolute; top:0; left:0; right:0; bottom:0; background: #111; align-items: center; justify-content: center; flex-direction: column; gap: 10px; z-index: 3; }
    52|    .offline-overlay.show { display: flex; }
    53|    .offline-overlay svg { opacity: .3; }
    54|    .offline-overlay span { font-family: 'Space Mono', monospace; font-size: 12px; color: rgba(255,255,255,.4); letter-spacing: 1px; }
    55|    #mainImg.hidden { display: none; }
    56|    .img-overlay { position: absolute; bottom:0; left:0; right:0; background: linear-gradient(transparent, rgba(0,0,0,.8)); padding: 16px 12px 10px; z-index: 4; }
    57|    .live-badge { color: #fff; font-size: 10px; font-family: 'Space Mono', monospace; font-weight: 700; padding: 2px 7px; border-radius: 4px; letter-spacing: 1px; margin-right: 8px; }
    58|    .img-meta { font-family: 'Space Mono', monospace; font-size: 10px; color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,.5); line-height: 1.8; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    59|    .img-meta .val { color: var(--green-light); font-weight: 700; }
    60|    .stream-status { position: absolute; top: 8px; right: 10px; font-size: 10px; font-family: 'Space Mono', monospace; color: #fff; background: rgba(0,0,0,0.55); padding: 2px 8px; border-radius: 4px; z-index: 5; }
    61|
    62|    .timelapse-strip { display: flex; gap: 8px; padding: 12px 14px; overflow-x: auto; min-height: 90px; align-items: center; }
    63|    .timelapse-strip::-webkit-scrollbar { height: 4px; }
    64|    .timelapse-strip::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
    65|
    66|    .right-col { display: flex; flex-direction: column; gap: 16px; }
    67|    .gauges-row { display: flex; justify-content: space-around; padding: 14px 12px 12px; gap: 6px; }
    68|    .gauge-wrap { display: flex; flex-direction: column; align-items: center; gap: 6px; }
    69|    .gauge-label { font-size: 10px; color: var(--muted); font-weight: 600; text-align: center; }
    70|    .gauge-svg { width: 80px; height: 80px; transform: rotate(-90deg); }
    71|    .gauge-svg circle { fill: none; stroke-width: 8; stroke-linecap: round; }
    72|    .gauge-bg { stroke: #e9ecef; }
    73|    .gauge-val-text { font-family: 'DM Sans', sans-serif; font-size: 12px; font-weight: 700; fill: var(--text); dominant-baseline: middle; text-anchor: middle; }
    74|    .gauge-sub { font-size: 9px; fill: var(--muted); dominant-baseline: middle; text-anchor: middle; }
    75|
    76|    .chart-wrap { padding: 12px 14px; height: 170px; }
    77|    .chart-legend { display: flex; gap: 12px; padding: 0 14px 10px; flex-wrap: wrap; }
    78|    .legend-item { display: flex; align-items: center; gap: 5px; font-size: 11px; color: var(--muted); }
    79|    .legend-dot { width: 10px; height: 3px; border-radius: 2px; }
    80|
    81|    .sensor-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    82|    .sensor-table td { padding: 7px 14px; border-bottom: 1px solid var(--border); }
    83|    .sensor-table tr:last-child td { border-bottom: none; }
    84|    .sensor-table .key { color: var(--muted); font-weight: 500; }
    85|    .sensor-table .val-cell { font-family: 'Space Mono', monospace; font-weight: 700; text-align: right; }
    86|    .badge-ok     { background: #dcfce7; color: #16a34a; border-radius: 4px; padding: 2px 8px; font-size: 10px; font-weight: 700; }
    87|    .badge-warn   { background: #fef9c3; color: #ca8a04; border-radius: 4px; padding: 2px 8px; font-size: 10px; font-weight: 700; }
    88|    .badge-danger { background: #fee2e2; color: #dc2626; border-radius: 4px; padding: 2px 8px; font-size: 10px; font-weight: 700; }
    89|    .badge-info   { background: #dbeafe; color: #1d4ed8; border-radius: 4px; padding: 2px 8px; font-size: 10px; font-weight: 700; }
    90|
    91|    /* ===== POMPA CONTROL CARD (REDESAIN) ===== */
    92|    .pompa-body { padding: 16px; display: flex; flex-direction: column; gap: 14px; }
    93|    .pompa-status-row { display: flex; align-items: center; justify-content: space-between; }
    94|    .pompa-status-left { display: flex; align-items: center; gap: 8px; }
    95|    .pompa-dot {
    96|      width: 10px; height: 10px; border-radius: 50%;
    97|      background: #22c55e;
    98|      box-shadow: 0 0 0 3px rgba(34,197,94,0.15);
    99|      transition: all 0.3s;
   100|      flex-shrink: 0;
   101|    }
   102|    .pompa-dot.off { background: #9ca3af; box-shadow: none; }
   103|    .pompa-status-text { font-size: 14px; font-weight: 600; color: var(--text); }
   104|    .pompa-mode-badge {
   105|      font-size: 11px; font-weight: 600; padding: 3px 10px;
   106|      border-radius: 20px; transition: all 0.2s;
   107|    }
   108|    .pompa-mode-badge.auto {
   109|      background: #fef9c3; color: #92400e; border: 0.5px solid #fde68a;
   110|    }
   111|    .pompa-mode-badge.on {
   112|      background: #dcfce7; color: #14532d; border: 0.5px solid #86efac;
   113|    }
   114|    .pompa-mode-badge.off {
   115|      background: #f3f4f6; color: #4b5563; border: 0.5px solid #d1d5db;
   116|    }
   117|    .pompa-btn-group { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
   118|    .pompa-btn {
   119|      display: flex; flex-direction: column; align-items: center; gap: 5px;
   120|      padding: 11px 6px; border-radius: 8px; border: 1px solid var(--border);
   121|      background: #fff; cursor: pointer; transition: all 0.15s;
   122|      font-size: 11px; font-weight: 600; color: var(--muted);
   123|      font-family: 'DM Sans', sans-serif;
   124|    }
   125|    .pompa-btn i { font-size: 18px; }
   126|    .pompa-btn:hover { background: var(--green-pale); border-color: var(--green-light); color: var(--green-dark); }
   127|    .pompa-btn.active-auto { border: 1.5px solid #f59e0b; background: #fef9c3; color: #92400e; }
   128|    .pompa-btn.active-on   { border: 1.5px solid #16a34a; background: #dcfce7; color: #14532d; }
   129|    .pompa-btn.active-off  { border: 1.5px solid #9ca3af; background: #f3f4f6; color: #374151; }
   130|    .pompa-info {
   131|      font-size: 11px; color: var(--muted); text-align: center;
   132|      line-height: 1.5; padding: 8px 10px;
   133|      background: var(--green-pale); border-radius: 6px;
   134|    }
   135|
   136|    .riwayat-wrap { max-width: 1100px; margin: 18px auto; padding: 0 16px; }
   137|    .riwayat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
   138|    .riwayat-header h2 { font-size: 15px; font-weight: 700; color: var(--text); }
   139|    .btn-hapus-toggle { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; padding: 8px 16px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
   140|    .btn-hapus-toggle:hover { background: #fecaca; }
   141|    .btn-hapus-all { background: #dc2626; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
   142|    .btn-hapus-all:hover { background: #b91c1c; }
   143|    .riwayat-section { background: #fff; border-radius: 10px; border: 1px solid var(--border); overflow: hidden; margin-bottom: 16px; }
   144|    .riwayat-section-header { padding: 10px 14px; border-bottom: 1px solid var(--border); font-size: 12px; font-weight: 600; color: var(--muted); letter-spacing: .5px; text-transform: uppercase; background: var(--green-pale); }
   145|    .foto-histori-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; padding: 12px 14px; }
   146|    .foto-histori-item { border-radius: 8px; overflow: hidden; border: 1px solid var(--border); cursor: pointer; transition: transform .2s, box-shadow .2s; }
   147|    .foto-histori-item:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.12); }
   148|    .foto-histori-item img { width: 100%; height: 110px; object-fit: cover; display: block; }
   149|    .foto-histori-label { padding: 4px 8px; font-size: 10px; font-family: 'Space Mono', monospace; color: var(--muted); background: #fff; }
   150|    .riwayat-chart-inner { height: 200px; padding: 12px 14px; }
   151|    .riwayat-table { width: 100%; border-collapse: collapse; font-size: 12px; }
   152|    .riwayat-table th { background: var(--green-pale); padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid var(--border); }
   153|    .riwayat-table td { padding: 9px 14px; border-bottom: 1px solid var(--border); font-family: 'Space Mono', monospace; font-size: 11px; }
   154|    .riwayat-table tr:last-child td { border-bottom: none; }
   155|    .riwayat-table tr:hover td { background: var(--green-pale); }
   156|    .loading-text { text-align: center; padding: 30px; color: var(--muted); font-size: 13px; }
   157|    .foto-count-badge { background: var(--green-pale); color: var(--green-dark); border-radius: 20px; padding: 3px 12px; font-size: 11px; font-weight: 600; }
   158|
   159|    .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 999; align-items: center; justify-content: center; }
   160|    .modal-backdrop.show { display: flex; }
   161|    .modal-box { background: #fff; border-radius: 12px; padding: 24px; width: 90%; max-width: 400px; }
   162|    .modal-box h3 { font-size: 15px; font-weight: 700; color: #1a2e1e; margin-bottom: 6px; }
   163|    .modal-box p { font-size: 12px; color: var(--muted); margin-bottom: 18px; }
   164|    .modal-box label { font-size: 11px; color: var(--muted); display: block; margin-bottom: 4px; }
   165|    .modal-box input[type=date] { width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; font-family: 'DM Sans', sans-serif; margin-bottom: 12px; }
   166|    .modal-footer { display: flex; gap: 8px; justify-content: flex-end; margin-top: 8px; flex-wrap: wrap; }
   167|    .btn-batal { background: none; border: 1px solid var(--border); padding: 8px 16px; border-radius: 6px; font-size: 12px; cursor: pointer; color: var(--muted); }
   168|    .btn-danger { background: #dc2626; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
   169|    .btn-danger:hover { background: #b91c1c; }
   170|    .divider { border: none; border-top: 1px solid var(--border); margin: 14px 0; }
   171|
   172|    #modal-foto-viewer .modal-inner { background: #111; border-radius: 12px; padding: 16px; max-width: 90vw; max-height: 90vh; display: flex; flex-direction: column; align-items: center; gap: 10px; position: relative; }
   173|    #modal-foto-viewer img { max-width: 80vw; max-height: 70vh; border-radius: 8px; object-fit: contain; }
   174|    #modal-foto-viewer .close-btn { position: absolute; top: 10px; right: 12px; background: rgba(255,255,255,0.15); border: none; color: #fff; font-size: 18px; cursor: pointer; border-radius: 50%; width: 30px; height: 30px; line-height: 30px; text-align: center; }
   175|    #modal-foto-viewer .foto-waktu { color: rgba(255,255,255,0.7); font-family: 'Space Mono', monospace; font-size: 11px; margin: 0; }
   176|    #modal-foto-viewer .btn-download { background: var(--green-mid); color: #fff; padding: 6px 16px; border-radius: 6px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; }
   177|
   178|    @media (max-width: 900px) { .container { grid-template-columns: 1fr; } }
   179|    @media (max-width: 768px) {
   180|      nav { padding: 8px 12px; height: auto; }
   181|      nav .brand { font-size: 13px; }
   182|      nav .nav-right { font-size: 10px; gap: 8px; }
   183|      .cam-id { display: none; }
   184|      .main-image-wrap { max-height: 240px; }
   185|      .gauges-row { gap: 4px; }
   186|      .gauge-svg { width: 65px; height: 65px; }
   187|      .foto-histori-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); }
   188|    }
   189|  </style>
   190|</head>
   191|<body>
   192|
   193|<!-- ===== MODAL HAPUS DATA SENSOR ===== -->
   194|<div id="hapus-modal" class="modal-backdrop">
   195|  <div class="modal-box">
   196|    <h3>🗑️ Hapus Data Sensor</h3>
   197|    <p>Pilih rentang tanggal yang ingin dihapus, atau hapus semua data sekaligus.</p>
   198|    <label>Dari Tanggal</label>
   199|    <input type="date" id="hapus-dari">
   200|    <label>Sampai Tanggal</label>
   201|    <input type="date" id="hapus-sampai">
   202|    <div class="modal-footer">
   203|      <button class="btn-batal" onclick="closeModal('hapus-modal')">Batal</button>
   204|      <button class="btn-danger" onclick="hapusData()">Hapus Rentang</button>
   205|    </div>
   206|    <hr class="divider">
   207|    <p style="margin-bottom:0;font-size:11px;color:#dc2626;">⚠️ Hapus semua data riwayat sensor secara permanen.</p>
   208|    <div class="modal-footer" style="margin-top:10px;">
   209|      <button class="btn-danger" onclick="hapusSemua()" style="background:#7f1d1d;">🗑️ Hapus Semua Data</button>
   210|    </div>
   211|  </div>
   212|</div>
   213|
   214|<!-- ===== MODAL HAPUS RIWAYAT FOTO ===== -->
   215|<div id="hapus-foto-modal" class="modal-backdrop">
   216|  <div class="modal-box">
   217|    <h3>🗑️ Hapus Riwayat Foto</h3>
   218|    <p style="color:#dc2626;font-size:12px;margin-bottom:20px;">⚠️ Semua riwayat foto akan dihapus secara permanen dan tidak dapat dikembalikan.</p>
   219|    <div class="modal-footer">
   220|      <button class="btn-batal" onclick="closeModal('hapus-foto-modal')">Batal</button>
   221|      <button class="btn-danger" onclick="hapusFoto()">Ya, Hapus Semua Foto</button>
   222|    </div>
   223|  </div>
   224|</div>
   225|
   226|<!-- ===== MODAL VIEWER FOTO ===== -->
   227|<div id="modal-foto-viewer" class="modal-backdrop" onclick="if(event.target===this) tutupModalFoto()">
   228|  <div class="modal-inner">
   229|    <button class="close-btn" onclick="tutupModalFoto()">✕</button>
   230|    <img id="modal-foto-img" src="" alt="foto tanaman">
   231|    <p id="modal-foto-waktu" class="foto-waktu"></p>
   232|    <button class="btn-download" onclick="downloadFoto()">⬇️ Download Foto</button>
   233|  </div>
   234|</div>
   235|
   236|<!-- ===== NAV ===== -->
   237|<nav>
   238|  <div class="brand"><span>Monitoring</span>&nbsp;Melon</div>
   239|  <div class="nav-right">
   240|    <span>
   241|      <span class="status-dot" id="nav-dot"></span>
   242|      <span id="nav-live-text">CONNECTING</span>
   243|    </span>
   244|    <span class="cam-id">CAM_ID: MELON01</span>
   245|    <span id="clock">--:--:--</span>
   246|    <span style="border-left:1px solid rgba(255,255,255,0.3);padding-left:12px;">
   247|      Halo, {{ auth()->user()->name ?? 'Guest' }}
   248|    </span>
   249|    <form method="POST" action="{{ route('logout') }}" style="display:inline;">
   250|      @csrf
   251|      <button type="submit" style="background:none;border:none;color:rgba(255,255,255,0.7);cursor:pointer;font-family:'Space Mono',monospace;font-size:12px;text-decoration:underline;padding:0;">
   252|        Logout
   253|      </button>
   254|    </form>
   255|  </div>
   256|</nav>
   257|
   258|<!-- ===== TAB MENU ===== -->
   259|<div class="tab-menu">
   260|  <button class="tab-btn active" onclick="switchTab(this,'dashboard')">📊 Dashboard</button>
   261|  <button class="tab-btn" onclick="switchTab(this,'riwayat')">📋 Riwayat Data</button>
   262|  <button class="tab-btn" onclick="switchTab(this,'foto')">📸 Riwayat Foto</button>
   263|</div>
   264|
   265|<!-- ===== TAB DASHBOARD ===== -->
   266|<div id="tab-dashboard" class="tab-content active">
   267|  <div class="container">
   268|    <div style="display:flex;flex-direction:column;gap:16px;min-width:0;">
   269|      <div class="card">
   270|        <div class="card-header">📹 Live Camera — MELON01</div>
   271|        <div class="main-image-wrap">
   272|          <img id="mainImg" alt="Live Stream" style="width:100%;height:100%;object-fit:cover;display:block;">
   273|          <div class="offline-overlay" id="offline-overlay">
   274|            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5">
   275|              <path d="M15.6 11.6L22 7v10l-6.4-4.5v-1zM4 5h9a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z"/>
   276|              <line x1="2" y1="2" x2="22" y2="22" stroke="#ef4444" stroke-width="2"/>
   277|            </svg>
   278|            <span>STREAM OFFLINE</span>
   279|          </div>
   280|          <span class="stream-status" id="stream-status">● CONNECTING...</span>
   281|          <div class="img-overlay">
   282|            <div class="img-meta">
   283|              <span class="live-badge" id="live-badge" style="background:#f59e0b;">CONNECTING</span>
   284|              <span>TEMP: <span class="val" id="overlay-suhu">{{ $latest->suhu ?? '-' }}°C</span></span>
   285|              <span>HUM: <span class="val" id="overlay-kelembapan">{{ $latest->kelembapan ?? '-' }}%</span></span>
   286|              <span>SOIL: <span class="val" id="overlay-soil">{{ $latest->soil ?? '-' }}%</span></span>
   287|              <span>DATE: <span class="val" id="overlay-date">{{ $latest->created_at ?? 'n/a' }}</span></span>
   288|            </div>
   289|          </div>
   290|        </div>
   291|      </div>
   292|      <div class="card">
   293|        <div class="card-header">⏱ Time-lapse Progress</div>
   294|        <div class="timelapse-strip" id="timelapse-strip">
   295|          <p style="color:var(--muted);font-size:12px;padding:10px;">Memuat foto...</p>
   296|        </div>
   297|      </div>
   298|    </div>
   299|
   300|    <div class="right-col">
   301|
   302|      <!-- ===== CARD KONTROL POMPA (REDESAIN) ===== -->
   303|      <div class="card">
   304|        <div class="card-header" style="display:flex;align-items:center;gap:6px;">
   305|          <i class="ti ti-droplet" style="font-size:14px;color:#1d4ed8;"></i>
   306|          Kontrol Pompa
   307|        </div>
   308|        <div class="pompa-body">
   309|
   310|          <!-- Status baris -->
   311|          <div class="pompa-status-row">
   312|            <div class="pompa-status-left">
   313|              <div class="pompa-dot" id="pompa-dot"></div>
   314|              <span class="pompa-status-text" id="pompa-status-text">Menyala</span>
   315|            </div>
   316|            <div class="pompa-mode-badge auto" id="pompa-mode-badge">Otomatis</div>
   317|          </div>
   318|
   319|          <!-- Tombol mode -->
   320|          <div class="pompa-btn-group">
   321|            <button class="pompa-btn active-auto" id="btn-auto" onclick="setPompa(-1)">
   322|              <i class="ti ti-refresh"></i>
   323|              Auto
   324|            </button>
   325|            <button class="pompa-btn" id="btn-on" onclick="setPompa(1)">
   326|              <i class="ti ti-player-play"></i>
   327|              Nyala
   328|            </button>
   329|            <button class="pompa-btn" id="btn-off" onclick="setPompa(0)">
   330|              <i class="ti ti-player-stop"></i>
   331|              Mati
   332|            </button>
   333|          </div>
   334|
   335|          <!-- Info -->
   336|          <div class="pompa-info" id="pompa-info-text">
   337|            Pompa dikontrol otomatis oleh sensor soil moisture
   338|          </div>
   339|
   340|        </div>
   341|      </div>
   342|
   343|      <!-- ===== CARD SENSOR STATUS ===== -->
   344|      <div class="card">
   345|        <div class="card-header">🌡 Sensor Status</div>
   346|        <div class="gauges-row">
   347|          @php
   348|            $suhu       = $latest->suhu ?? 0;
   349|            $kelembapan = $latest->kelembapan ?? 0;
   350|            $soil       = $latest->soil ?? 0;
   351|            $circumf    = 201;
   352|            $offsetSuhu = round($circumf * (1 - min($suhu, 100) / 100));
   353|            $offsetHum  = round($circumf * (1 - min($kelembapan, 100) / 100));
   354|            $offsetSoil = round($circumf * (1 - min($soil, 100) / 100));
   355|          @endphp
   356|          <div class="gauge-wrap">
   357|            <svg class="gauge-svg" viewBox="0 0 80 80">
   358|              <circle class="gauge-bg" cx="40" cy="40" r="32" stroke-dasharray="{{ $circumf }}" stroke-dashoffset="0"/>
   359|              <circle id="gauge-suhu-circle" cx="40" cy="40" r="32" fill="none" stroke-width="8" stroke-linecap="round" stroke="#e53e3e" stroke-dasharray="{{ $circumf }}" stroke-dashoffset="{{ $offsetSuhu }}"/>
   360|              <g transform="rotate(90, 40, 40)">
   361|                <text id="gauge-suhu-text" class="gauge-val-text" x="40" y="37">{{ $suhu }}°</text>
   362|                <text class="gauge-sub" x="40" y="51">Temp</text>
   363|              </g>
   364|            </svg>
   365|            <div class="gauge-label">Temperature</div>
   366|          </div>
   367|          <div class="gauge-wrap">
   368|            <svg class="gauge-svg" viewBox="0 0 80 80">
   369|              <circle class="gauge-bg" cx="40" cy="40" r="32" stroke-dasharray="{{ $circumf }}" stroke-dashoffset="0"/>
   370|              <circle id="gauge-hum-circle" cx="40" cy="40" r="32" fill="none" stroke-width="8" stroke-linecap="round" stroke="#00b5a3" stroke-dasharray="{{ $circumf }}" stroke-dashoffset="{{ $offsetHum }}"/>
   371|              <g transform="rotate(90, 40, 40)">
   372|                <text id="gauge-hum-text" class="gauge-val-text" x="40" y="37">{{ $kelembapan }}%</text>
   373|                <text class="gauge-sub" x="40" y="51">Humid</text>
   374|              </g>
   375|            </svg>
   376|            <div class="gauge-label">Humidity</div>
   377|          </div>
   378|          <div class="gauge-wrap">
   379|            <svg class="gauge-svg" viewBox="0 0 80 80">
   380|              <circle class="gauge-bg" cx="40" cy="40" r="32" stroke-dasharray="{{ $circumf }}" stroke-dashoffset="0"/>
   381|              <circle id="gauge-soil-circle" cx="40" cy="40" r="32" fill="none" stroke-width="8" stroke-linecap="round" stroke="#2563eb" stroke-dasharray="{{ $circumf }}" stroke-dashoffset="{{ $offsetSoil }}"/>
   382|              <g transform="rotate(90, 40, 40)">
   383|                <text id="gauge-soil-text" class="gauge-val-text" x="40" y="37">{{ $soil }}%</text>
   384|                <text class="gauge-sub" x="40" y="51">Soil</text>
   385|              </g>
   386|            </svg>
   387|            <div class="gauge-label">Soil Moisture</div>
   388|          </div>
   389|        </div>
   390|        <table class="sensor-table">
   391|          <tr>
   392|            <td class="key">Suhu</td>
   393|            <td class="val-cell" id="val-suhu">{{ $suhu }} °C</td>
   394|            <td><span id="badge-suhu" class="badge-ok">NORMAL</span></td>
   395|          </tr>
   396|          <tr>
   397|            <td class="key">Kelembapan</td>
   398|            <td class="val-cell" id="val-kelembapan">{{ $kelembapan }} %</td>
   399|            <td><span id="badge-kelembapan" class="badge-ok">NORMAL</span></td>
   400|          </tr>
   401|          <tr>
   402|            <td class="key">Soil Moisture</td>
   403|            <td class="val-cell" id="val-soil">{{ $soil }} %</td>
   404|            <td><span id="badge-soil" class="badge-ok">IDEAL</span></td>
   405|          </tr>
   406|        </table>
   407|      </div>
   408|
   409|      <div class="card">
   410|        <div class="card-header">📈 Time-series Chart</div>
   411|        <div class="chart-wrap"><canvas id="miniChart"></canvas></div>
   412|        <div class="chart-legend">
   413|          <div class="legend-item"><div class="legend-dot" style="background:#e53e3e"></div>Suhu</div>
   414|          <div class="legend-item"><div class="legend-dot" style="background:#00b5a3"></div>Kelembapan</div>
   415|          <div class="legend-item"><div class="legend-dot" style="background:#2563eb"></div>Soil Moisture</div>
   416|        </div>
   417|      </div>
   418|    </div>
   419|  </div>
   420|</div>
   421|
   422|<!-- ===== TAB RIWAYAT DATA ===== -->
   423|<div id="tab-riwayat" class="tab-content">
   424|  <div class="riwayat-wrap">
   425|    <div class="riwayat-header">
   426|      <h2>📋 Riwayat Data Sensor</h2>
   427|      <button class="btn-hapus-toggle" onclick="openModal('hapus-modal')">🗑️ Hapus Data</button>
   428|    </div>
   429|    <div class="riwayat-section">
   430|      <div class="riwayat-section-header">📈 Grafik Riwayat Sensor</div>
   431|      <div class="riwayat-chart-inner"><canvas id="riwayatChart"></canvas></div>
   432|      <div class="chart-legend" style="padding:0 14px 12px;">
   433|        <div class="legend-item"><div class="legend-dot" style="background:#e53e3e"></div>Suhu</div>
   434|        <div class="legend-item"><div class="legend-dot" style="background:#00b5a3"></div>Kelembapan</div>
   435|        <div class="legend-item"><div class="legend-dot" style="background:#2563eb"></div>Soil Moisture</div>
   436|      </div>
   437|    </div>
   438|    <div class="riwayat-section">
   439|      <div class="riwayat-section-header">📊 Tabel Data Sensor</div>
   440|      <div style="overflow-x:auto;">
   441|        <table class="riwayat-table">
   442|          <thead>
   443|            <tr>
   444|              <th>No</th><th>Waktu</th><th>Suhu (°C)</th><th>Kelembapan (%)</th>
   445|              <th>Soil (%)</th><th>St. Suhu</th><th>St. Kelembapan</th><th>St. Soil</th>
   446|            </tr>
   447|          </thead>
   448|          <tbody id="riwayat-tbody">
   449|            <tr><td colspan="8" class="loading-text">Memuat data...</td></tr>
   450|          </tbody>
   451|        </table>
   452|      </div>
   453|    </div>
   454|  </div>
   455|</div>
   456|
   457|<!-- ===== TAB RIWAYAT FOTO ===== -->
   458|<div id="tab-foto" class="tab-content">
   459|  <div class="riwayat-wrap">
   460|    <div class="riwayat-header">
   461|      <h2>📸 Riwayat Foto</h2>
   462|      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
   463|        <span class="foto-count-badge" id="foto-count">0 foto</span>
   464|        <button class="btn-hapus-all" onclick="openModal('hapus-foto-modal')">🗑️ Hapus Riwayat Foto</button>
   465|      </div>
   466|    </div>
   467|    <div class="riwayat-section">
   468|      <div class="riwayat-section-header">Histori Foto Tanaman (Maks. 30 Foto Terbaru)</div>
   469|      <div class="foto-histori-grid" id="foto-grid">
   470|        <p style="color:var(--muted);font-size:13px;padding:10px;">Memuat foto...</p>
   471|      </div>
   472|    </div>
   473|  </div>
   474|</div>
   475|
   476|<!-- ===== SCRIPT UTAMA ===== -->
   477|<script>
   478|  const ESP32_IP = '{{ config('services.esp32_cam_ip') }}';
   479|
   480|  function tick() { document.getElementById('clock').textContent = new Date().toLocaleTimeString('id-ID'); }
   481|  tick(); setInterval(tick, 1000);
   482|
   483|  function openModal(id)  { document.getElementById(id).classList.add('show'); }
   484|  function closeModal(id) { document.getElementById(id).classList.remove('show'); }
   485|
   486|  function switchTab(btn, tab) {
   487|    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
   488|    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
   489|    document.getElementById('tab-' + tab).classList.add('active');
   490|    btn.classList.add('active');
   491|  }
   492|
   493|  function setMain(src) {
   494|    const img = document.getElementById('mainImg');
   495|    img.classList.remove('hidden');
   496|    document.getElementById('offline-overlay').classList.remove('show');
   497|    img.src = src;
   498|    setTimeout(() => startStream(), 10000);
   499|  }
   500|
   501|