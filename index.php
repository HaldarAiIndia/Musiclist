<?php
/**
 * HAIMUSIC - HOME EDITION (OPTIMIZED)
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <style>
        :root { --p: #00ff88; --bg: #050505; --s: #121212; --text: #fff; --dim: #888; }
        
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        
        body { 
            margin: 0; background: var(--bg); color: var(--text); 
            font-family: 'Inter', sans-serif; overflow: hidden;
            height: 100vh; height: -webkit-fill-available;
        }

        /* --- ANIMATIONS --- */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pulseGlow { 0%, 100% { box-shadow: 0 0 10px rgba(0,255,136,0.2); } 50% { box-shadow: 0 0 25px rgba(0,255,136,0.5); } }
        @keyframes barPulse { 0%, 100% { height: 4px; } 50% { height: 16px; } }
        @keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-100%); } }

        .app { 
            display: flex; flex-direction: column; height: 100vh; 
            max-width: 600px; margin: 0 auto; position: relative;
            padding-bottom: env(safe-area-inset-bottom);
        }

        header { padding: 20px 20px 10px; animation: fadeInUp 0.6s ease-out; }
        .logo { font-size: clamp(20px, 5vw, 24px); font-weight: 800; margin-bottom: 15px; letter-spacing: -1px; }
        .logo span { color: var(--p); }

        .tabs { 
            display: flex; gap: 10px; overflow-x: auto; padding-bottom: 10px; 
            scrollbar-width: none; -ms-overflow-style: none;
        }
        .tabs::-webkit-scrollbar { display: none; }
        .tab { 
            padding: 8px 20px; background: var(--s); border-radius: 25px; font-size: 13px; 
            white-space: nowrap; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            cursor: pointer; border: 1px solid #222;
        }
        .tab.active { background: var(--p); color: #000; font-weight: 700; border-color: var(--p); transform: scale(1.05); }

        .list { 
            flex: 1; overflow-y: auto; padding: 10px 20px 180px; 
            scroll-behavior: smooth; -webkit-overflow-scrolling: touch;
        }
        .item { 
            display: flex; align-items: center; gap: 15px; padding: 14px; 
            border-radius: 16px; margin-bottom: 10px; cursor: pointer; 
            background: var(--s); border: 1px solid transparent;
            transition: 0.2s; animation: fadeInUp 0.4s both;
        }
        .item:active { transform: scale(0.98); background: #1a1a1a; }
        .item.active { border-color: var(--p); background: rgba(0, 255, 136, 0.08); }

        .eq-icon { display: none; gap: 3px; align-items: flex-end; width: 18px; height: 15px; }
        .eq-bar { width: 3px; background: var(--p); animation: barPulse 0.6s infinite ease-in-out; }
        .item.active .eq-icon { display: flex; }
        .item.active .default-icon { display: none; }

        .player { 
            position: fixed; bottom: calc(15px + env(safe-area-inset-bottom)); 
            left: 15px; right: 15px; max-width: 570px; margin: 0 auto;
            background: rgba(15, 15, 15, 0.9); backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px);
            border-radius: 28px; padding: 18px; border: 1px solid #333; z-index: 100;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            transition: 0.3s transform;
        }
        
        .prog-bg { width: 100%; height: 4px; background: #222; border-radius: 2px; margin-bottom: 15px; position: relative; }
        .prog-fill { height: 100%; background: var(--p); border-radius: 2px; width: 0%; transition: width 0.1s linear; }
        .prog-input { 
            position: absolute; top: -10px; left: 0; width: 100%; height: 25px; 
            opacity: 0; cursor: pointer; z-index: 5; 
        }

        .btn-play { 
            width: 54px; height: 54px; background: var(--p); border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            border: none; color: #000; cursor: pointer; transition: 0.2s;
        }
        .btn-play:active { transform: scale(0.9); }
        .playing .btn-play { animation: pulseGlow 2s infinite; }

        /* --- DEVICE OPTIMIZATIONS --- */
        @media (max-width: 300px) { /* Smartwatch / Tiny screens */
            header { padding: 10px; }
            .logo { font-size: 16px; }
            .tabs { display: none; } /* Hide tabs on watch, use Home only */
            .item { padding: 8px; gap: 8px; }
            .player { padding: 10px; bottom: 5px; left: 5px; right: 5px; }
            .btn-play { width: 40px; height: 40px; }
            #cur-meta { display: none; }
        }

        @media (min-width: 1024px) { /* Desktop */
            .list::-webkit-scrollbar { width: 6px; }
            .list::-webkit-scrollbar-thumb { background: #333; border-radius: 10px; }
            .item:hover { background: #1a1a1a; border-color: #333; }
        }
    </style>
</head>
<body>

<div class="app">
    <header>
        <div class="logo">Hai<span>Music</span></div>
        <div class="tabs" id="category-tabs">
            <div class="tab active" onclick="setCategory('Home')">Home</div>
            <div class="tab" onclick="setCategory('Bollywood')">Bollywood</div>
            <div class="tab" onclick="setCategory('Tollywood')">Tollywood</div>
            <div class="tab" onclick="setCategory('Bhakti')">Bhakti</div>
            <div class="tab" onclick="setCategory('Phonk')">Phonk</div>
            <div class="tab" onclick="setCategory('Local')">Local</div>
        </div>
    </header>

    <div class="list" id="list"></div>

    <div class="player" id="player-container">
        <div class="prog-bg">
            <div class="prog-fill" id="fill"></div>
            <input type="range" class="prog-input" id="seek" value="0" step="0.1">
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="width: 50%; overflow:hidden">
                <b id="cur-name" style="display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Discover Music</b>
                <span id="cur-meta" style="font-size:11px; color:var(--dim)">HaiMusic Home Edition</span>
            </div>
            <div style="display: flex; align-items: center; gap: 12px;">
                <button onclick="prev()" style="background:none; border:none; color:#fff; cursor:pointer"><span class="material-icons-round">skip_previous</span></button>
                <button class="btn-play" onclick="toggle()"><span class="material-icons-round" id="play-btn" style="font-size:32px">play_arrow</span></button>
                <button onclick="next()" style="background:none; border:none; color:#fff; cursor:pointer"><span class="material-icons-round">skip_next</span></button>
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

    // Init
    loadOnlineMusic();
    render();
</script>
</body>
</html>