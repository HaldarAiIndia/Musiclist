<?php
/**
 * HAIMUSIC × Slowedfy - HOME EDITION (OPTIMIZED)
 */

// --- 1. SECURE LOCAL STREAMING BRIDGE ---
$mime_types = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav', 'flac' => 'audio/flac'];
if (isset($_GET['stream_path'])) {
    $realPath = realpath($_GET['stream_path']);
    if ($realPath && is_file($realPath)) {
        $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        if (isset($mime_types[$ext])) {
            $size = filesize($realPath);
            header("Content-Type: " . $mime_types[$ext]);
            header("Accept-Ranges: bytes"); // Fixes seeking in local files
            header("Content-Length: " . $size);
            readfile($realPath);
            exit;
        }
    }
    http_response_code(404);
    exit;
}

// --- 2. LOCAL SCANNING ---
$localSongs = [];
$homeDir = getenv('USERPROFILE') ?: getenv('HOME');
$paths = array_filter([$homeDir . '/Downloads', '/sdcard/Download', '/storage/emulated/0/Download', './music'], 'is_dir');
foreach ($paths as $path) {
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $ext = strtolower($file->getExtension());
            if (isset($mime_types[$ext])) {
                $p = $file->getRealPath();
                $localSongs[md5($p)] = [
                    'id' => md5($p),
                    'name' => $file->getBasename('.'.$ext),
                    'url' => "?stream_path=" . urlencode($p),
                    'category' => 'Local',
                    'size' => round($file->getSize() / 1048576, 1) . 'MB'
                ];
            }
        }
    } catch (Exception $e) {}
}
$localJson = json_encode(array_values($localSongs));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>HaiMusic</title>
    <link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,700,900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <style>
        /* ===================== TOKENS (GREEN EDITION) ===================== */
        :root {
            --primary:      #00ff88;
            --primary-dim:  rgba(0, 255, 136, 0.12);
            --bg:           #08080C;
            --surface:      #0e0e14;
            --surface2:     #141420;
            --surface3:     #1a1a28;
            --surface4:     #20202f;
            --border:       #23233a;
            --border-hi:    #32324d;
            --text:         #f0f0f8;
            --text-sub:     #8888aa;
            --text-dim:     #44445a;
            --error:        #ff4545;
            --success:      #00e676;
            --font:         'Satoshi', 'Inter', sans-serif;
            --r-card:       1.35rem;
            --r-input:      0.8rem;
            --r-btn:        0.75rem;
            --shadow:       0 24px 80px rgba(0, 0, 0, 0.8), 0 0 0 1px rgba(0, 255, 136, 0.08);

            /* Legacy variable aliases for JS inline style compatibility */
            --p:            var(--primary);
            --s:            var(--surface2);
            --dim:          var(--text-sub);
        }

        /* ===================== RESET ===================== */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            -webkit-tap-highlight-color: transparent;
        }

        html,
        body {
            height: 100%;
            margin: 0;
        }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            user-select: none;
            padding: 16px;
        }

        /* ===================== KEYFRAMES ===================== */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes glow {
            0%, 100% {
                box-shadow: 0 0 0 1px rgba(0, 255, 136, 0.25), 0 0 18px rgba(0, 255, 136, 0.08);
            }
            50% {
                box-shadow: 0 0 0 1px rgba(0, 255, 136, 0.55), 0 0 28px rgba(0, 255, 136, 0.22);
            }
        }

        @keyframes pulseGlow {
            0%, 100% {
                box-shadow: 0 4px 18px rgba(0, 255, 136, 0.3), 0 0 0 0 rgba(0, 255, 136, 0.4);
            }
            50% {
                box-shadow: 0 6px 28px rgba(0, 255, 136, 0.55), 0 0 0 6px rgba(0, 255, 136, 0);
            }
        }

        @keyframes barPulse {
            0%, 100% { height: 4px; }
            50%      { height: 16px; }
        }

        @keyframes artPulse {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.03); }
        }

        /* ===================== BG GRID + GLOW ===================== */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.013) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.013) 1px, transparent 1px);
            background-size: 48px 48px;
        }

        body::after {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background: radial-gradient(ellipse 70% 55% at 50% -5%, rgba(0, 255, 136, 0.12) 0%, transparent 65%);
        }

        /* ===================== WRAPPER & CARD ===================== */
        .app-wrap {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
            height: 100%;
            max-height: 860px;
            display: flex;
            flex-direction: column;
            animation: slideUp 0.65s cubic-bezier(0.2, 0.8, 0.2, 1) both;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-card);
            padding: 22px 18px 18px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            height: 100%;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent 5%, rgba(0, 255, 136, 0.65) 50%, transparent 95%);
        }

        /* ===================== HEADER ===================== */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-mark {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            background: linear-gradient(135deg, rgba(0, 255, 136, 0.2), rgba(0, 255, 136, 0.05));
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 16px rgba(0, 255, 136, 0.25), 0 0 0 1px rgba(0, 255, 136, 0.3);
            color: var(--primary);
            flex-shrink: 0;
        }

        .logo-mark .material-icons-round {
            font-size: 22px;
        }

        .brand-text h1 {
            font-size: 1.25rem;
            font-weight: 900;
            margin: 0;
            letter-spacing: -0.025em;
            line-height: 1.1;
        }

        .brand-text h1 span {
            color: var(--primary);
        }

        .brand-text p {
            color: var(--text-sub);
            font-size: 0.71rem;
            font-weight: 500;
            letter-spacing: 0.02em;
            margin: 2px 0 0;
        }

        .count-pill {
            background: var(--primary-dim);
            color: var(--primary);
            font-size: 0.62rem;
            font-weight: 800;
            padding: 4px 9px;
            border-radius: 999px;
            border: 1px solid rgba(0, 255, 136, 0.25);
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        /* ===================== CATEGORY TABS ===================== */
        .tabs {
            display: flex;
            gap: 7px;
            overflow-x: auto;
            padding-bottom: 2px;
            scrollbar-width: none;
            -ms-overflow-style: none;
            flex-shrink: 0;
        }

        .tabs::-webkit-scrollbar {
            display: none;
        }

        .tab {
            padding: 8px 15px;
            border-radius: 9px;
            border: 1px solid var(--border);
            background: var(--surface2);
            color: var(--text-sub);
            font-family: var(--font);
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            transition: border-color 0.18s, background 0.18s, color 0.18s, transform 0.15s, box-shadow 0.18s;
        }

        .tab:hover {
            border-color: var(--border-hi);
            color: var(--text);
            transform: translateY(-1px);
        }

        .tab:active {
            transform: scale(0.95);
        }

        .tab.active {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-dim);
            box-shadow: 0 0 16px rgba(0, 255, 136, 0.12);
        }

        /* ===================== SECTION HEADER ===================== */
        .sec-head {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-top: 4px;
            flex-shrink: 0;
        }

        .sec-label {
            font-size: 0.64rem;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.14em;
            white-space: nowrap;
        }

        .sec-rule {
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ===================== TRACK LIST ===================== */
        .list {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding-right: 2px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }

        .list::-webkit-scrollbar {
            width: 4px;
        }

        .list::-webkit-scrollbar-track {
            background: transparent;
        }

        .list::-webkit-scrollbar-thumb {
            background: var(--border-hi);
            border-radius: 999px;
        }

        .item {
            width: 100%;
            padding: 12px 13px;
            border-radius: var(--r-input);
            border: 1px solid var(--border);
            background: var(--surface2);
            color: var(--text);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 13px;
            transition: background 0.18s, border-color 0.18s, transform 0.16s, box-shadow 0.18s;
            animation: fadeInUp 0.35s both;
            flex-shrink: 0;
        }

        .item:hover {
            background: var(--surface3);
            border-color: var(--border-hi);
            transform: translateY(-1px);
        }

        .item:active {
            transform: scale(0.985);
        }

        .item.active {
            border-color: var(--primary);
            background: var(--primary-dim);
            animation: glow 2.4s ease-in-out infinite;
        }

        .item-icon {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            flex-shrink: 0;
            background: var(--surface3);
            border: 1px solid var(--border-hi);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.18s, border-color 0.18s;
        }

        .item:hover .item-icon {
            background: var(--surface4);
        }

        .item.active .item-icon {
            background: rgba(0, 255, 136, 0.16);
            border-color: rgba(0, 255, 136, 0.45);
        }

        .eq-icon {
            display: none;
            gap: 3px;
            align-items: flex-end;
            width: 16px;
            height: 15px;
            justify-content: center;
        }

        .eq-bar {
            width: 3px;
            border-radius: 2px;
            background: var(--primary);
            animation: barPulse 0.6s infinite ease-in-out;
            box-shadow: 0 0 6px var(--primary);
        }

        .item.active .eq-icon {
            display: flex;
        }

        .item.active .default-icon {
            display: none;
        }

        /* ===================== MINI PLAYER DOCK ===================== */
        .player {
            background: var(--surface2);
            border: 1px solid var(--border-hi);
            border-radius: 1.15rem;
            padding: 16px;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
            cursor: pointer;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.6);
            transition: border-color 0.25s, box-shadow 0.25s, transform 0.18s;
        }

        .player:hover {
            border-color: rgba(0, 255, 136, 0.4);
        }

        .player::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent 10%, rgba(0, 255, 136, 0.5) 50%, transparent 90%);
        }

        .player.playing {
            border-color: rgba(0, 255, 136, 0.35);
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.7), 0 0 24px rgba(0, 255, 136, 0.07);
        }

        .prog-bg {
            width: 100%;
            height: 5px;
            background: #09090d;
            border: 1px solid var(--border);
            border-radius: 999px;
            margin-bottom: 14px;
            position: relative;
        }

        .prog-fill {
            height: 100%;
            background: linear-gradient(90deg, #00cc6a, #00ff88);
            border-radius: 999px;
            width: 0%;
            box-shadow: 0 0 10px rgba(0, 255, 136, 0.55);
            transition: width 0.1s linear;
        }

        .prog-input {
            position: absolute;
            top: -10px;
            left: 0;
            width: 100%;
            height: 25px;
            opacity: 0;
            cursor: pointer;
            z-index: 5;
            margin: 0;
        }

        .player-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .track-meta {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .mini-art {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, rgba(0, 255, 136, 0.22), var(--surface3));
            border: 1px solid rgba(0, 255, 136, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }

        .mini-art img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }

        .track-text {
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }

        #cur-name {
            display: block;
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            letter-spacing: -0.01em;
        }

        #cur-meta {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-sub);
            margin-top: 2px;
        }

        .ctrl-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .ico-btn {
            width: 36px;
            height: 36px;
            background: var(--surface3);
            border: 1px solid var(--border-hi);
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-sub);
            transition: background 0.18s, border-color 0.18s, color 0.18s, transform 0.18s;
        }

        .ico-btn:hover {
            background: var(--surface4);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
        }

        .ico-btn:active {
            transform: scale(0.92);
        }

        .btn-play {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #00ff88 0%, #00cc6a 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            color: #06110b;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 18px rgba(0, 255, 136, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.35);
            transition: transform 0.18s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.18s;
        }

        .btn-play:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 255, 136, 0.45);
        }

        .btn-play:active {
            transform: scale(0.93);
        }

        .playing .btn-play {
            animation: pulseGlow 2.2s infinite;
        }

        /* ===================== FULL-SCREEN PLAYER MODAL (SPOTIFY STYLE) ===================== */
        .fs-modal {
            position: absolute;
            inset: 0;
            z-index: 500;
            background:
                radial-gradient(circle at 50% 18%, rgba(0, 255, 136, 0.22) 0%, rgba(20, 20, 32, 0.96) 55%, var(--surface) 100%);
            backdrop-filter: blur(28px);
            -webkit-backdrop-filter: blur(28px);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 16px 22px calc(22px + env(safe-area-inset-bottom));
            transform: translateY(105%);
            transition: transform 0.38s cubic-bezier(0.22, 1, 0.36, 1);
            touch-action: pan-y;
            user-select: none;
        }

        .fs-modal.open {
            transform: translateY(0%);
        }

        .fs-modal.dragging {
            transition: none;
        }

        .fs-drag-pill {
            width: 40px;
            height: 4px;
            border-radius: 999px;
            background: var(--border-hi);
            margin: 0 auto 6px;
            flex-shrink: 0;
        }

        /* Top Bar */
        .fs-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-shrink: 0;
        }

        .fs-top-center {
            text-align: center;
            flex: 1;
            overflow: hidden;
        }

        .fs-top-sub {
            font-size: 0.62rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--text-sub);
            display: block;
        }

        .fs-top-cat {
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--primary);
            display: block;
            margin-top: 2px;
        }

        .fs-icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.18s, border-color 0.18s, transform 0.15s;
        }

        .fs-icon-btn:hover {
            background: var(--surface3);
            border-color: var(--primary);
            color: var(--primary);
        }

        .fs-icon-btn:active {
            transform: scale(0.9);
        }

        /* Large Album Art */
        .fs-art-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px 0;
            min-height: 0;
        }

        .fs-art-card {
            width: 100%;
            max-width: 295px;
            aspect-ratio: 1 / 1;
            border-radius: 24px;
            background:
                radial-gradient(circle at 30% 25%, rgba(0, 255, 136, 0.35), transparent 60%),
                linear-gradient(145deg, #162620 0%, #0d1318 55%, #09090f 100%);
            border: 1px solid rgba(0, 255, 136, 0.3);
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.75), 0 0 40px rgba(0, 255, 136, 0.12);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }

        .fs-modal.playing .fs-art-card {
            animation: artPulse 4s ease-in-out infinite;
            box-shadow: 0 28px 70px rgba(0, 0, 0, 0.85), 0 0 55px rgba(0, 255, 136, 0.22);
        }

        .fs-art-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
            z-index: 1;
        }

        .fs-art-disc {
            width: 115px;
            height: 115px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0, 255, 136, 0.22) 0%, rgba(0, 255, 136, 0.04) 70%);
            border: 1.5px solid rgba(0, 255, 136, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            box-shadow: 0 0 30px rgba(0, 255, 136, 0.25);
        }

        .fs-art-disc .material-icons-round {
            font-size: 56px;
        }

        .fs-art-badge {
            position: absolute;
            bottom: 14px;
            right: 14px;
            z-index: 2;
            background: rgba(8, 8, 12, 0.75);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(0, 255, 136, 0.3);
            color: var(--primary);
            font-size: 0.62rem;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 999px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        /* Track Title + Like Row */
        .fs-info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 14px;
            flex-shrink: 0;
        }

        .fs-info-text {
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }

        #fs-title {
            font-size: 1.25rem;
            font-weight: 900;
            color: var(--text);
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            letter-spacing: -0.02em;
        }

        #fs-artist {
            font-size: 0.84rem;
            font-weight: 600;
            color: var(--text-sub);
            margin: 4px 0 0;
            display: block;
        }

        .fs-like-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1.5px solid var(--border-hi);
            background: transparent;
            color: var(--text-sub);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: color 0.2s, border-color 0.2s, transform 0.18s;
        }

        .fs-like-btn.liked {
            color: var(--primary);
            border-color: var(--primary);
            background: var(--primary-dim);
        }

        .fs-like-btn:active {
            transform: scale(0.88);
        }

        /* Full-Screen Seek Bar */
        .fs-seek-wrap {
            margin-bottom: 16px;
            flex-shrink: 0;
        }

        .fs-prog-bg {
            width: 100%;
            height: 6px;
            background: var(--surface3);
            border-radius: 999px;
            position: relative;
        }

        .fs-prog-fill {
            height: 100%;
            background: linear-gradient(90deg, #00cc6a, #00ff88);
            border-radius: 999px;
            width: 0%;
            position: relative;
            box-shadow: 0 0 12px rgba(0, 255, 136, 0.6);
            transition: width 0.1s linear;
        }

        .fs-prog-fill::after {
            content: '';
            position: absolute;
            right: -6px;
            top: 50%;
            transform: translateY(-50%);
            width: 13px;
            height: 13px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 0 10px var(--primary);
        }

        .fs-time-row {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-sub);
        }

        /* Main Playback Controls */
        .fs-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            flex-shrink: 0;
        }

        .fs-ctrl-sec {
            background: none;
            border: none;
            color: var(--text-sub);
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.18s, transform 0.15s;
        }

        .fs-ctrl-sec.active {
            color: var(--primary);
        }

        .fs-ctrl-sec:hover {
            color: var(--text);
        }

        .fs-ctrl-sec:active {
            transform: scale(0.88);
        }

        .fs-ctrl-skip {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--surface2);
            border: 1px solid var(--border-hi);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.18s, border-color 0.18s, transform 0.15s;
        }

        .fs-ctrl-skip:hover {
            background: var(--surface3);
            border-color: var(--primary);
            color: var(--primary);
        }

        .fs-ctrl-skip:active {
            transform: scale(0.9);
        }

        .fs-ctrl-skip .material-icons-round {
            font-size: 30px;
        }

        .fs-ctrl-play {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: linear-gradient(135deg, #00ff88 0%, #00cc6a 100%);
            border: none;
            color: #05100a;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 8px 28px rgba(0, 255, 136, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.4);
            transition: transform 0.18s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.18s;
        }

        .fs-ctrl-play:hover {
            transform: scale(1.05);
        }

        .fs-ctrl-play:active {
            transform: scale(0.93);
        }

        .fs-ctrl-play .material-icons-round {
            font-size: 38px;
        }

        /* Bottom Utility Row */
        .fs-bottom-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 6px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            flex-shrink: 0;
        }

        .fs-device-pill {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--primary);
            font-size: 0.72rem;
            font-weight: 700;
        }

        .fs-device-pill .material-icons-round {
            font-size: 17px;
        }

        /* ===================== RESPONSIVE ===================== */
        @media (max-width: 480px) {
            body {
                padding: 0;
            }
            .app-wrap {
                max-width: 100%;
                max-height: 100%;
            }
            .card {
                border-radius: 0;
                border: none;
                padding: 18px 16px calc(16px + env(safe-area-inset-bottom));
            }
        }

        @media (max-height: 700px) {
            .fs-art-card {
                max-width: 220px;
            }
            .fs-ctrl-play {
                width: 58px;
                height: 58px;
            }
        }

        @media (max-width: 300px) { /* Smartwatch / Tiny screens */
            .card { padding: 10px; gap: 8px; }
            .tabs, .sec-head, #cur-meta, .count-pill, .mini-art { display: none; }
            .item { padding: 8px; gap: 8px; }
            .player { padding: 10px; }
            .btn-play { width: 40px; height: 40px; border-radius: 10px; }
            .ico-btn { width: 30px; height: 30px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>

<div class="app-wrap">
    <div class="card">
        <header class="header">
            <div class="header-brand">
                <div class="logo-mark">
                    <span class="material-icons-round">graphic_eq</span>
                </div>
                <div class="brand-text">
                    <h1>Hai<span>Music</span></h1>
                    <p>Home Edition • Stream</p>
                </div>
            </div>
            <span class="count-pill">Hi-Fi</span>
        </header>

        <div class="tabs" id="category-tabs">
            <div class="tab active" onclick="setCategory('Home')">Home</div>
            <div class="tab" onclick="setCategory('Bollywood')">Bollywood</div>
            <div class="tab" onclick="setCategory('Tollywood')">Tollywood</div>
            <div class="tab" onclick="setCategory('Bhakti')">Bhakti</div>
            <div class="tab" onclick="setCategory('Phonk')">Phonk</div>
            <div class="tab" onclick="setCategory('Slowedfy')">Slowedfy</div>
            <div class="tab" onclick="setCategory('Local')">Local</div>
        </div>

        <div class="sec-head">
            <span class="sec-label">Library Tracks</span>
            <div class="sec-rule"></div>
        </div>

        <div class="list" id="list"></div>

        <!-- MINI PLAYER DOCK (Tap to open Full-Screen Modal) -->
        <div class="player" id="player-container" onclick="handleMiniPlayerTap(event)">
            <div class="prog-bg">
                <div class="prog-fill" id="fill"></div>
                <input type="range" class="prog-input" id="seek" value="0" step="0.1">
            </div>
            <div class="player-row">
                <div class="track-meta">
                    <div class="mini-art">
                        <img id="mini-art-img" alt="Cover" onerror="handleImageError('mini')">
                        <span class="material-icons-round" id="mini-art-icon">album</span>
                    </div>
                    <div class="track-text">
                        <b id="cur-name">Discover Music</b>
                        <span id="cur-meta">HaiMusic Home Edition</span>
                    </div>
                </div>
                <div class="ctrl-group">
                    <button class="ico-btn" onclick="prev()"><span class="material-icons-round">skip_previous</span></button>
                    <button class="btn-play" onclick="toggle()"><span class="material-icons-round" id="play-btn" style="font-size:28px">play_arrow</span></button>
                    <button class="ico-btn" onclick="next()"><span class="material-icons-round">skip_next</span></button>
                </div>
            </div>
        </div>

        <!-- FULL-SCREEN MUSIC PLAYER MODAL -->
        <div class="fs-modal" id="fs-modal">
            <div class="fs-drag-pill"></div>

            <div class="fs-top">
                <button class="fs-icon-btn" onclick="closeFullPlayer()" aria-label="Collapse Player">
                    <span class="material-icons-round">keyboard_arrow_down</span>
                </button>
                <div class="fs-top-center">
                    <span class="fs-top-sub">Playing From Category</span>
                    <span class="fs-top-cat" id="fs-top-cat">Home</span>
                </div>
                <button class="fs-icon-btn" onclick="closeFullPlayer()" aria-label="Queue">
                    <span class="material-icons-round">queue_music</span>
                </button>
            </div>

            <div class="fs-art-wrap">
                <div class="fs-art-card">
                    <img class="fs-art-img" id="fs-art-img" alt="Album Art" onerror="handleImageError('fs')">
                    <div class="fs-art-disc" id="fs-art-disc">
                        <span class="material-icons-round">music_note</span>
                    </div>
                    <span class="fs-art-badge" id="fs-badge">HAIMUSIC HI-FI</span>
                </div>
            </div>

            <div class="fs-info-row">
                <div class="fs-info-text">
                    <h2 id="fs-title">Discover Music</h2>
                    <span id="fs-artist">HaiMusic Home Edition</span>
                </div>
                <button class="fs-like-btn" id="fs-like-btn" onclick="this.classList.toggle('liked')">
                    <span class="material-icons-round" style="font-size:20px">add</span>
                </button>
            </div>

            <div class="fs-seek-wrap">
                <div class="fs-prog-bg">
                    <div class="fs-prog-fill" id="fs-fill"></div>
                    <input type="range" class="prog-input" id="fs-seek" value="0" step="0.1">
                </div>
                <div class="fs-time-row">
                    <span id="fs-cur-time">0:00</span>
                    <span id="fs-dur-time">0:00</span>
                </div>
            </div>

            <div class="fs-controls">
                <button class="fs-ctrl-sec" id="fs-shuffle-btn" onclick="this.classList.toggle('active')">
                    <span class="material-icons-round">shuffle</span>
                </button>
                <button class="fs-ctrl-skip" onclick="prev()">
                    <span class="material-icons-round">skip_previous</span>
                </button>
                <button class="fs-ctrl-play" onclick="toggle()">
                    <span class="material-icons-round" id="fs-play-btn">play_arrow</span>
                </button>
                <button class="fs-ctrl-skip" onclick="next()">
                    <span class="material-icons-round">skip_next</span>
                </button>
                <button class="fs-ctrl-sec" id="fs-repeat-btn" onclick="toggleRepeat(this)">
                    <span class="material-icons-round">repeat</span>
                </button>
            </div>

            <div class="fs-bottom-row">
                <div class="fs-device-pill">
                    <span class="material-icons-round">speaker_group</span>
                    <span>HaiMusic Audio Engine</span>
                </div>
                <button class="fs-ctrl-sec" onclick="closeFullPlayer()">
                    <span class="material-icons-round" style="font-size:20px">playlist_play</span>
                </button>
            </div>
        </div>
    </div>
</div>

<audio id="audio" preload="auto"></audio>

<script>
    const localSongs = <?= $localJson ?>;
    const onlineSources = [
        { url: 'https://api.github.com/repos/tzproapks/Mp3/contents/Bollywood/Item?ref=main', category: 'Bollywood' },
        { url: 'https://api.github.com/repos/tzproapks/Mp3/contents/Tollywood/Item?ref=main', category: 'Tollywood' },
        { url: 'https://api.github.com/repos/tzproapks/Mp3/contents/Bhakti?ref=main', category: 'Bhakti' },
        { url: 'https://api.github.com/repositories/1132495410/contents/Phonk?ref=main', category: 'Phonk' }
    ];

    const gDriveApiKey = "AIzaSyAFWjP192Andq3CnH8TmYd-AmmAezcUqg0";
    const gDriveFolderUrl = "https://drive.google.com/drive/folders/194R85uwyR_UyKRn6RC9xGsmN87kNC4bF?usp=sharing";

    let allSongs = [...localSongs];
    let currentCategory = 'Home';
    let currentSongId = null;
    const audio = document.getElementById('audio');

    async function loadOnlineMusic() {
        for (let source of onlineSources) {
            try {
                const res = await fetch(source.url);
                const files = await res.json();
                if(!Array.isArray(files)) continue;
                
                const processed = files.filter(f => f.name.endsWith('.mp3')).map(f => ({
                    id: f.sha,
                    name: f.name.replace('.mp3', '').replace(/_/g, ' '),
                    url: f.download_url,
                    category: source.category,
                    size: 'Online'
                }));
                allSongs = [...allSongs, ...processed];
                render(); 
            } catch (e) { console.error("Load failed for "+source.category); }
        }

        // Load Slowedfy category from Google Drive
        try {
            const folderMatch = gDriveFolderUrl.match(/folders\/([a-zA-Z0-9_-]+)/);
            const folderId = folderMatch ? folderMatch[1] : '';
            if (folderId) {
                const driveRes = await fetch(`https://www.googleapis.com/drive/v3/files?q='${folderId}'+in+parents+and+trashed=false&pageSize=1000&fields=files(id,name,size,mimeType)&key=${gDriveApiKey}`);
                const driveData = await driveRes.json();
                if (driveData && Array.isArray(driveData.files)) {
                    const driveSongs = driveData.files
                        .filter(f => (f.mimeType && f.mimeType.startsWith('audio/')) || /\.(mp3|m4a|wav|flac)$/i.test(f.name))
                        .map(f => ({
                            id: f.id,
                            name: f.name.replace(/\.(mp3|m4a|wav|flac)$/i, '').replace(/_/g, ' '),
                            url: `https://www.googleapis.com/drive/v3/files/${f.id}?alt=media&key=${gDriveApiKey}`,
                            category: 'Slowedfy',
                            size: f.size ? (f.size / 1048576).toFixed(1) + 'MB' : 'Online'
                        }));
                    allSongs = [...allSongs, ...driveSongs];
                    render();
                }
            }
        } catch (e) { console.error("Load failed for Slowedfy"); }
    }

    function setCategory(cat) {
        currentCategory = cat;
        document.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t.innerText === cat));
        render();
        document.getElementById('list').scrollTop = 0;
    }

    function render() {
        const listDiv = document.getElementById('list');
        listDiv.innerHTML = '';
        
        const filtered = allSongs.filter(s => {
            if (currentCategory === 'Home') return s.category !== 'Local';
            return s.category === currentCategory;
        });

        if (filtered.length === 0) {
            listDiv.innerHTML = `<div style="text-align:center; color:var(--dim); margin-top:50px">No tracks found</div>`;
        }

        filtered.forEach((song, index) => {
            const div = document.createElement('div');
            div.className = `item ${currentSongId === song.id ? 'active' : ''}`;
            div.style.animationDelay = `${Math.min(index * 0.05, 1)}s`;
            div.onclick = () => playSong(song);
            div.innerHTML = `
                <div class="item-icon">
                    <span class="material-icons-round default-icon" style="color:var(--dim)">music_note</span>
                    <div class="eq-icon">
                        <div class="eq-bar" style="animation-duration: 0.4s"></div>
                        <div class="eq-bar" style="animation-delay:0.2s; animation-duration: 0.5s"></div>
                        <div class="eq-bar" style="animation-delay:0.1s; animation-duration: 0.3s"></div>
                    </div>
                </div>
                <div style="flex:1; overflow:hidden">
                    <div style="font-size:14px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${song.name}</div>
                    <div style="font-size:11px; color:var(--dim)">${song.category} • ${song.size}</div>
                </div>
            `;
            listDiv.appendChild(div);
        });
    }

    function playSong(song) {
        currentSongId = song.id;
        audio.src = song.url;
        audio.play().catch(e => console.log("Autoplay blocked or failed"));
        
        document.getElementById('cur-name').innerText = song.name;
        document.getElementById('cur-meta').innerText = song.category;
        document.getElementById('play-btn').innerText = 'pause';
        document.getElementById('player-container').classList.add('playing');

        // Update Media Session (For Smartwatch/Lockscreen)
        if ('mediaSession' in navigator) {
            navigator.mediaSession.metadata = new MediaMetadata({
                title: song.name,
                artist: song.category,
                album: 'HaiMusic',
                artwork: [{ src: 'https://cdn-icons-png.flaticon.com/512/3844/3844724.png', sizes: '512x512', type: 'image/png' }]
            });
        }
        render();
        fetchAlbumArt(song.name);
    }

    function toggle() {
        if (!audio.src) {
            const currentList = allSongs.filter(s => currentCategory === 'Home' ? s.category !== 'Local' : s.category === currentCategory);
            if(currentList.length > 0) playSong(currentList[0]);
            return;
        }
        if (audio.paused) { 
            audio.play(); 
            document.getElementById('play-btn').innerText = 'pause';
            document.getElementById('player-container').classList.add('playing');
        } else { 
            audio.pause(); 
            document.getElementById('play-btn').innerText = 'play_arrow';
            document.getElementById('player-container').classList.remove('playing');
        }
    }

    function next() {
        const currentList = allSongs.filter(s => currentCategory === 'Home' ? s.category !== 'Local' : s.category === currentCategory);
        if (currentList.length === 0) return;
        let idx = currentList.findIndex(s => s.id === currentSongId);
        playSong(currentList[(idx + 1) % currentList.length]);
    }

    function prev() {
        const currentList = allSongs.filter(s => currentCategory === 'Home' ? s.category !== 'Local' : s.category === currentCategory);
        if (currentList.length === 0) return;
        let idx = currentList.findIndex(s => s.id === currentSongId);
        idx = idx < 1 ? currentList.length - 1 : idx - 1;
        playSong(currentList[idx]);
    }

    // Event Listeners
    audio.onended = next;
    audio.ontimeupdate = () => {
        if (isNaN(audio.duration)) return;
        const per = (audio.currentTime / audio.duration) * 100;
        document.getElementById('fill').style.width = per + '%';
        document.getElementById('seek').value = per;
    };

    document.getElementById('seek').oninput = (e) => {
        if (!audio.duration) return;
        audio.currentTime = (e.target.value / 100) * audio.duration;
    };

    // MediaSession Handlers
    if ('mediaSession' in navigator) {
        navigator.mediaSession.setActionHandler('play', toggle);
        navigator.mediaSession.setActionHandler('pause', toggle);
        navigator.mediaSession.setActionHandler('previoustrack', prev);
        navigator.mediaSession.setActionHandler('nexttrack', next);
    }

    // --- DYNAMIC ALBUM ART API INTEGRATION & ERROR FIX ---
    const fsModal = document.getElementById('fs-modal');
    const fsTitle = document.getElementById('fs-title');
    const fsArtist = document.getElementById('fs-artist');
    const fsTopCat = document.getElementById('fs-top-cat');
    const fsBadge = document.getElementById('fs-badge');
    const fsPlayBtn = document.getElementById('fs-play-btn');
    const fsFill = document.getElementById('fs-fill');
    const fsSeek = document.getElementById('fs-seek');
    const fsCurTime = document.getElementById('fs-cur-time');
    const fsDurTime = document.getElementById('fs-dur-time');
    const fsArtImg = document.getElementById('fs-art-img');
    const fsArtDisc = document.getElementById('fs-art-disc');
    const miniArtImg = document.getElementById('mini-art-img');
    const miniArtIcon = document.getElementById('mini-art-icon');

    function handleImageError(type) {
        if (type === 'fs') {
            fsArtImg.style.display = 'none';
            fsArtDisc.style.display = 'flex';
        } else if (type === 'mini') {
            miniArtImg.style.display = 'none';
            miniArtIcon.style.display = 'block';
        }
    }

    async function fetchAlbumArt(rawTitle) {
        fsArtImg.style.display = 'none';
        fsArtDisc.style.display = 'flex';
        miniArtImg.style.display = 'none';
        miniArtIcon.style.display = 'block';

        try {
            const cleanQuery = rawTitle
                .replace(/\(.*?\)|\[.*?\]/g, '')
                .replace(/lyrical|video|song|full|hd|mp3|160k|320k|sped up|version/gi, '')
                .trim();

            if (!cleanQuery) return;

            const res = await fetch(`https://itunes.apple.com/search?term=${encodeURIComponent(cleanQuery)}&entity=song&limit=1`);
            const data = await res.json();

            if (data.results && data.results.length > 0 && data.results[0].artworkUrl100) {
                const hiResArt = data.results[0].artworkUrl100.replace('100x100bb', '600x600bb');
                fsArtImg.src = hiResArt;
                fsArtImg.style.display = 'block';
                fsArtDisc.style.display = 'none';

                miniArtImg.src = hiResArt;
                miniArtImg.style.display = 'block';
                miniArtIcon.style.display = 'none';

                if ('mediaSession' in navigator && navigator.mediaSession.metadata) {
                    navigator.mediaSession.metadata.artwork = [{ src: hiResArt, sizes: '600x600', type: 'image/jpeg' }];
                }
            }
        } catch (err) {}
    }

    function formatTime(sec) {
        if (!sec || isNaN(sec)) return '0:00';
        const m = Math.floor(sec / 60);
        const s = Math.floor(sec % 60);
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    function syncFullPlayerUI() {
        fsTitle.innerText = document.getElementById('cur-name').innerText;
        fsArtist.innerText = document.getElementById('cur-meta').innerText;
        fsTopCat.innerText = document.getElementById('cur-meta').innerText;
        fsBadge.innerText = document.getElementById('cur-meta').innerText + ' • HI-FI';
        const isPaused = audio.paused;
        fsPlayBtn.innerText = isPaused ? 'play_arrow' : 'pause';
        fsModal.classList.toggle('playing', !isPaused && !!audio.src);
    }

    function openFullPlayer() {
        syncFullPlayerUI();
        fsModal.style.transform = '';
        fsModal.classList.add('open');
    }

    function closeFullPlayer() {
        fsModal.style.transform = '';
        fsModal.classList.remove('open');
    }

    function handleMiniPlayerTap(e) {
        if (e.target.closest('button') || e.target.closest('input')) return;
        openFullPlayer();
    }

    function toggleRepeat(btn) {
        audio.loop = !audio.loop;
        btn.classList.toggle('active', audio.loop);
    }

    audio.addEventListener('play', syncFullPlayerUI);
    audio.addEventListener('pause', syncFullPlayerUI);
    audio.addEventListener('loadedmetadata', () => {
        fsDurTime.innerText = formatTime(audio.duration);
        syncFullPlayerUI();
    });
    audio.addEventListener('timeupdate', () => {
        if (isNaN(audio.duration)) return;
        const per = (audio.currentTime / audio.duration) * 100;
        fsFill.style.width = per + '%';
        fsSeek.value = per;
        fsCurTime.innerText = formatTime(audio.currentTime);
        fsDurTime.innerText = formatTime(audio.duration);
    });

    fsSeek.addEventListener('input', (e) => {
        if (!audio.duration) return;
        audio.currentTime = (e.target.value / 100) * audio.duration;
    });

    let touchStartY = 0;
    let touchCurrentY = 0;
    let isDraggingModal = false;

    fsModal.addEventListener('touchstart', (e) => {
        if (e.target.closest('input') || e.target.closest('button')) return;
        touchStartY = e.touches[0].clientY;
        touchCurrentY = touchStartY;
        isDraggingModal = true;
        fsModal.classList.add('dragging');
    }, { passive: true });

    fsModal.addEventListener('touchmove', (e) => {
        if (!isDraggingModal) return;
        touchCurrentY = e.touches[0].clientY;
        const deltaY = Math.max(0, touchCurrentY - touchStartY);
        if (deltaY > 0) {
            fsModal.style.transform = `translateY(${deltaY}px)`;
        }
    }, { passive: true });

    fsModal.addEventListener('touchend', () => {
        if (!isDraggingModal) return;
        isDraggingModal = false;
        fsModal.classList.remove('dragging');
        const deltaY = touchCurrentY - touchStartY;
        if (deltaY > 110) {
            closeFullPlayer();
        } else {
            fsModal.style.transform = '';
        }
    });

    loadOnlineMusic();
    render();
</script>
</body>
</html>
