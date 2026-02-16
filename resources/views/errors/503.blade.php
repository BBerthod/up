<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 - Maintenance Mode | Up</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #09090b; color: #ececef; line-height: 1.6;
            min-height: 100vh; display: flex; flex-direction: column;
            align-items: center; justify-content: center; padding: 24px;
            position: relative; overflow: hidden;
        }
        body::before {
            content: ''; position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%); width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(251,191,36,0.06) 0%, transparent 70%);
            pointer-events: none;
        }
        body::after {
            content: ''; position: absolute; inset: 0;
            background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 64px 64px; pointer-events: none;
        }
        .container { position: relative; z-index: 1; text-align: center; max-width: 480px; width: 100%; }
        .logo { margin-bottom: 40px; display: inline-flex; }
        .logo svg { width: 64px; height: 64px; filter: drop-shadow(0 0 20px rgba(16,185,129,0.3)); }
        .card {
            background: #111113; border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px; padding: 48px 40px;
        }
        .indicator {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(251,191,36,0.1); border: 1px solid rgba(251,191,36,0.2);
            border-radius: 20px; padding: 6px 14px; font-size: 12px; color: #fbbf24; margin-bottom: 24px;
        }
        .indicator svg { width: 14px; height: 14px; animation: spin 2s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .code {
            font-size: 120px; font-weight: 700; line-height: 1; letter-spacing: -0.04em; margin-bottom: 8px;
            background: linear-gradient(180deg, #ececef 0%, #8b8b8e 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .title { font-size: 24px; font-weight: 600; color: #ececef; margin-bottom: 12px; }
        .desc { font-size: 15px; color: #8b8b8e; margin-bottom: 32px; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            background: #06b6d4; color: #fff; font-family: 'Inter', sans-serif;
            font-size: 14px; font-weight: 500; padding: 12px 24px; border-radius: 8px;
            text-decoration: none; border: none; cursor: pointer; transition: all 0.15s ease;
            box-shadow: 0 0 20px rgba(6,182,212,0.2);
        }
        .btn:hover { background: #22d3ee; box-shadow: 0 0 30px rgba(6,182,212,0.3); transform: translateY(-1px); }
        .footer { position: relative; z-index: 1; margin-top: 40px; font-size: 13px; color: #5c5c5f; }
        .footer span { color: #10b981; font-weight: 500; }
        @media (max-width: 480px) {
            .card { padding: 32px 24px; }
            .code { font-size: 80px; }
            .title { font-size: 20px; }
            .logo svg { width: 48px; height: 48px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <svg viewBox="0 0 64 64" fill="none"><circle cx="32" cy="32" r="30" fill="#10b981"/><path d="M32 18L44 34H36V46H28V34H20L32 18Z" fill="white"/></svg>
        </div>
        <div class="card">
            <div class="indicator">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M12 2V4M12 20V22M4.93 4.93L6.34 6.34M17.66 17.66L19.07 19.07M2 12H4M20 12H22M6.34 17.66L4.93 19.07M19.07 4.93L17.66 6.34"/>
                </svg>
                Under Maintenance
            </div>
            <div class="code">503</div>
            <h1 class="title">Maintenance Mode</h1>
            <p class="desc">We're performing scheduled maintenance. We'll be back shortly.</p>
            <a href="javascript:window.location.reload();" class="btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                </svg>
                Try Again
            </a>
        </div>
    </div>
    <footer class="footer">Powered by <span>Up</span></footer>
</body>
</html>
