<?php
/**
 * PROX ADMIN - PREVIEW EDITION (FULL CODE)
 * Features: Live Rendering, Media Playback, Resource Locking, Full Folder Uploads
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

$baseDir = realpath(__DIR__);
$scriptName = basename($_SERVER['SCRIPT_NAME']);

// --- 1. CORE PATH SECURITY ---
$subPath = isset($_GET['path']) ? str_replace(['..', './'], '', $_GET['path']) : '';
$fullPath = realpath($baseDir . '/' . $subPath);
if (!$fullPath || strpos($fullPath, $baseDir) !== 0 || !is_dir($fullPath)) {
    $fullPath = $baseDir; $subPath = '';
}

// Fixed Redirect URL construction
$currentParams = $_GET;
unset($currentParams['msg']); // Clear old messages
$queryString = http_build_query($currentParams);
$redirectUrl = $scriptName . ($queryString ? "?" . $queryString : "");

// --- 2. SECURITY HELPER ---
function isLocked($path) {
    return file_exists($path . '.lock');
}

// --- 3. ACTION HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $cleanName = basename($_POST['name'] ?? '');
    $targetPath = $fullPath . '/' . $cleanName;

    // Security Check: Block actions on locked files
    if (isLocked($targetPath) && !in_array($action, ['unlock', 'get_content'])) {
        $connector = (strpos($redirectUrl, '?') !== false) ? '&' : '?';
        header("Location: " . $redirectUrl . $connector . "msg=" . urlencode("Resource is Locked"));
        exit;
    }

    switch ($action) {
        case 'lock':
            $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
            file_put_contents($targetPath . '.lock', $pass);
            $msg = "Resource Locked";
            break;

        case 'unlock':
            $hash = @file_get_contents($targetPath . '.lock');
            if ($hash && password_verify($_POST['password'], $hash)) {
                unlink($targetPath . '.lock');
                $msg = "Resource Unlocked";
            } else {
                $connector = (strpos($redirectUrl, '?') !== false) ? '&' : '?';
                header("Location: " . $redirectUrl . $connector . "msg=" . urlencode("Wrong Password"));
                exit;
            }
            break;

        case 'create_folder':
            if (!file_exists($targetPath)) {
                mkdir($targetPath, 0777, true);
                $msg = "Folder Created";
            } else { $msg = "Folder already exists"; }
            break;

        case 'create_file':
            if (!file_exists($targetPath)) {
                file_put_contents($targetPath, "");
                $msg = "File Created";
            } else { $msg = "File already exists"; }
            break;

        case 'upload':
            if (isset($_FILES['file'])) {
                $files = $_FILES['file'];
                $count = is_array($files['name']) ? count($files['name']) : 1;
                for ($i = 0; $i < $count; $i++) {
                    $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                    $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                    // Support Webkit Folder structure (recreates folders)
                    $relPath = (isset($files['full_path'][$i]) && !empty($files['full_path'][$i])) ? $files['full_path'][$i] : $name;
                    $dest = $fullPath . DIRECTORY_SEPARATOR . $relPath;
                    $destDir = dirname($dest);
                    if (!is_dir($destDir)) mkdir($destDir, 0777, true);
                    move_uploaded_file($tmpName, $dest);
                }
                $msg = "Upload Successful";
            }
            break;

        case 'save_file':
            file_put_contents($targetPath, $_POST['content']);
            echo "success"; exit;

        case 'get_content':
            if (isLocked($targetPath)) { echo "LOCKED"; }
            else { echo file_get_contents($targetPath); }
            exit;

        case 'delete':
            if (is_dir($targetPath)) {
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($targetPath, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($it as $f) { $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname()); }
                rmdir($targetPath);
            } else { unlink($targetPath); }
            if(file_exists($targetPath.'.lock')) unlink($targetPath.'.lock');
            $msg = "Deleted";
            break;

        case 'rename':
            $new = $fullPath . '/' . basename($_POST['new_name']);
            rename($targetPath, $new);
            if(file_exists($targetPath.'.lock')) rename($targetPath.'.lock', $new.'.lock');
            $msg = "Renamed";
            break;
    }
    $connector = (strpos($redirectUrl, '?') !== false) ? '&' : '?';
    header("Location: " . $redirectUrl . $connector . "msg=" . urlencode($msg ?? 'Success'));
    exit;
}

// --- 4. DATA LISTER ---
$items = [];
$scan = array_diff(scandir($fullPath), ['.', '..']);
foreach ($scan as $name) {
    if (preg_match('/\.(lock|pinned)$/', $name)) continue;
    $p = $fullPath . '/' . $name;
    $is_dir = is_dir($p);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    
    // Icon Mapping
    $icon = $is_dir ? 'fa-folder text-yellow-500' : 'fa-file text-blue-400';
    if(in_array($ext, ['html','php','htm'])) $icon = 'fa-globe text-emerald-400';
    if(in_array($ext, ['mp3','wav','ogg','m4a'])) $icon = 'fa-music text-purple-400';
    if(in_array($ext, ['mp4','webm','mov'])) $icon = 'fa-video text-red-400';
    if(in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) $icon = 'fa-image text-orange-400';

    $items[] = [
        'name' => $name, 'is_dir' => $is_dir, 'size' => $is_dir ? 0 : filesize($p),
        'ext' => $ext, 'icon' => $icon, 'locked' => isLocked($p),
        'mtime' => date("d M, H:i", filemtime($p))
    ];
}
usort($items, function($a, $b) { return $b['is_dir'] <=> $a['is_dir'] ?: strnatcasecmp($a['name'], $b['name']); });
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>PROX Admin Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { background: #0b0e14; color: #f8fafc; font-family: 'Plus Jakarta Sans', sans-serif; overflow-x: hidden; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .bottom-sheet { position: fixed; bottom: 0; left: 0; right: 0; background: #131720; border-radius: 2rem 2rem 0 0; transform: translateY(110%); transition: 0.3s ease; z-index: 100; padding: 2rem; border-top: 1px solid #3b82f644; max-height: 85vh; overflow-y: auto; }
        .bottom-sheet.active { transform: translateY(0); }
        .overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); opacity: 0; pointer-events: none; transition: 0.3s; z-index: 90; }
        .overlay.active { opacity: 1; pointer-events: auto; }
        .fab { position: fixed; bottom: 3rem; right: 1.5rem; width: 3.5rem; height: 3.5rem; background: #3b82f6; border-radius: 1.2rem; display: flex; align-items: center; justify-content: center; z-index: 50; box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3); }
        #previewIframe { width: 100%; height: 100%; border: none; background: white; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        input[type="file"]::file-selector-button { display: none; }
    </style>
</head>
<body class="safe-area-top">

    <div id="overlay" class="overlay" onclick="closeAll()"></div>

    <!-- HEADER -->
    <header class="fixed top-0 w-full z-40 glass p-5 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg"><i class="fas fa-eye text-white"></i></div>
            <h1 class="font-extrabold text-sm italic">PROX <span class="text-blue-500 not-italic">ADMIN</span></h1>
        </div>
        <button onclick="location.reload()" class="w-10 h-10 glass rounded-xl flex items-center justify-center"><i class="fas fa-rotate text-gray-500 text-xs"></i></button>
    </header>

    <main class="pt-28 pb-32 px-6">
        <!-- BREADCRUMBS -->
        <div class="flex items-center gap-2 mb-6 text-[10px] font-bold uppercase tracking-widest overflow-x-auto no-scrollbar whitespace-nowrap">
            <a href="?" class="text-blue-500">Root</a>
            <?php 
            $parts = array_filter(explode('/', $subPath));
            $cum = "";
            foreach ($parts as $p): $cum .= ($cum?'/':'').$p; ?>
                <i class="fas fa-chevron-right opacity-20"></i>
                <a href="?path=<?= urlencode($cum) ?>" class="text-white"><?= $p ?></a>
            <?php endforeach; ?>
        </div>

        <!-- FILE LIST -->
        <div class="space-y-3">
            <?php foreach ($items as $f): ?>
                <div class="item glass p-4 rounded-2xl flex items-center justify-between" onclick="handleTap('<?= $f['name'] ?>', <?= $f['is_dir']?'true':'false' ?>, '<?= $f['ext'] ?>', <?= $f['locked']?'true':'false' ?>)">
                    <div class="flex items-center gap-4 flex-1 min-w-0">
                        <div class="w-12 h-12 bg-white/5 rounded-2xl flex items-center justify-center text-lg">
                            <i class="fas <?= $f['icon'] ?>"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-sm truncate"><?= $f['name'] ?></h3>
                            <p class="text-[9px] text-gray-500 font-bold uppercase"><?= $f['is_dir'] ? 'Directory' : round($f['size']/1024, 1).' KB' ?></p>
                        </div>
                    </div>
                    <?php if($f['locked']): ?><i class="fas fa-lock text-red-500 text-xs mr-3"></i><?php endif; ?>
                    <button onclick="event.stopPropagation(); openMenu('<?= $f['name'] ?>', <?= $f['locked']?'true':'false' ?>, '<?= $f['ext'] ?>')" class="w-10 h-10 flex items-center justify-center text-gray-700"><i class="fas fa-ellipsis-v"></i></button>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <button class="fab" onclick="openSheet('sheetAdd')"><i class="fas fa-plus text-white text-xl"></i></button>

    <!-- ADD SHEET -->
    <div id="sheetAdd" class="bottom-sheet">
        <div class="w-12 h-1 bg-gray-800 rounded-full mx-auto mb-8"></div>
        <div class="grid grid-cols-2 gap-4">
            <button onclick="promptAction('create_file', 'New Filename')" class="glass p-6 rounded-3xl flex flex-col items-center gap-3">
                <i class="fas fa-file-circle-plus text-blue-500 text-xl"></i>
                <span class="text-[10px] font-bold uppercase">New File</span>
            </button>
            <button onclick="promptAction('create_folder', 'New Folder Name')" class="glass p-6 rounded-3xl flex flex-col items-center gap-3">
                <i class="fas fa-folder-plus text-yellow-500 text-xl"></i>
                <span class="text-[10px] font-bold uppercase">New Folder</span>
            </button>
            <button onclick="document.getElementById('fileInp').click()" class="glass p-6 rounded-3xl flex flex-col items-center gap-3">
                <i class="fas fa-file-upload text-emerald-500 text-xl"></i>
                <span class="text-[10px] font-bold uppercase">Upload File</span>
            </button>
            <button onclick="document.getElementById('dirInp').click()" class="glass p-6 rounded-3xl flex flex-col items-center gap-3">
                <i class="fas fa-folder-tree text-pink-500 text-xl"></i>
                <span class="text-[10px] font-bold uppercase">Upload Folder</span>
            </button>
        </div>
        <form id="fileForm" method="POST" enctype="multipart/form-data" class="hidden"><input type="hidden" name="action" value="upload"><input type="file" name="file" id="fileInp" onchange="document.getElementById('fileForm').submit()"></form>
        <form id="dirForm" method="POST" enctype="multipart/form-data" class="hidden"><input type="hidden" name="action" value="upload"><input type="file" name="file[]" id="dirInp" webkitdirectory directory multiple onchange="document.getElementById('dirForm').submit()"></form>
    </div>

    <!-- ACTION MENU -->
    <div id="sheetMenu" class="bottom-sheet">
        <div class="w-12 h-1 bg-gray-800 rounded-full mx-auto mb-8"></div>
        <h2 id="mTitle" class="text-xs font-bold text-gray-500 mb-6 text-center uppercase tracking-widest">Options</h2>
        <div id="menuButtons" class="space-y-2"></div>
    </div>

    <!-- SECURITY MODAL -->
    <div id="sheetSecurity" class="bottom-sheet">
        <div class="w-12 h-1 bg-gray-800 rounded-full mx-auto mb-8"></div>
        <h2 id="secTitle" class="text-sm font-bold mb-4 text-center tracking-tight">Access Control</h2>
        <input type="password" id="secPass" placeholder="Enter Password" class="w-full glass p-4 rounded-2xl outline-none mb-4 text-center">
        <button id="secBtn" class="w-full bg-blue-600 p-4 rounded-2xl font-bold">Authenticate</button>
    </div>

    <!-- PREVIEW / MEDIA UI -->
    <div id="previewUI" class="fixed inset-0 bg-[#0b0e14] z-[200] hidden flex flex-col">
        <div class="p-4 flex justify-between items-center glass">
            <button onclick="closePreview()" class="text-gray-500 w-10"><i class="fas fa-xmark text-lg"></i></button>
            <span id="previewName" class="text-[10px] font-bold uppercase truncate px-4">Preview</span>
            <button onclick="document.getElementById('previewIframe')?.contentWindow?.location.reload()" class="text-blue-500 w-10 text-right"><i class="fas fa-rotate"></i></button>
        </div>
        <div id="previewContainer" class="flex-1 flex items-center justify-center overflow-hidden bg-black/20"></div>
    </div>

    <!-- EDITOR UI -->
    <div id="editorUI" class="fixed inset-0 bg-[#0b0e14] z-[200] hidden flex flex-col">
        <div class="p-5 flex justify-between items-center glass">
            <button onclick="closeEditor()" class="text-gray-500"><i class="fas fa-arrow-left"></i></button>
            <span id="edName" class="text-[10px] font-bold uppercase tracking-widest text-blue-500">Editor</span>
            <button onclick="saveEditor()" class="text-emerald-500 font-extrabold">SAVE</button>
        </div>
        <textarea id="edText" spellcheck="false" class="flex-1 w-full bg-transparent p-6 font-mono text-xs text-emerald-400 outline-none resize-none"></textarea>
    </div>

    <script>
        let cur = "";
        const webExts = ['html', 'php', 'htm', 'txt', 'css', 'js', 'json', 'xml', 'md'];
        const imgExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        const audExts = ['mp3', 'wav', 'ogg', 'm4a'];
        const vidExts = ['mp4', 'webm', 'mov'];

        function openSheet(id) {
            document.getElementById(id).classList.add('active');
            document.getElementById('overlay').classList.add('active');
        }

        function closeAll() {
            document.querySelectorAll('.bottom-sheet').forEach(s => s.classList.remove('active'));
            document.getElementById('overlay').classList.remove('active');
        }

        function handleTap(name, dir, ext, locked) {
            cur = name;
            if (locked) { openSecurity('unlock'); return; }
            if (dir) {
                const u = new URL(window.location.href);
                u.searchParams.set('path', (u.searchParams.get('path') ? u.searchParams.get('path') + '/' : '') + name);
                window.location.href = u.href;
            } else {
                openPreview(name, ext);
            }
        }

        function openPreview(name, ext) {
            cur = name;
            const sub = new URLSearchParams(window.location.search).get('path') || "";
            const path = (sub ? sub + '/' : '') + name;
            const container = document.getElementById('previewContainer');
            document.getElementById('previewName').innerText = name;
            container.innerHTML = "";

            if (imgExts.includes(ext)) {
                container.innerHTML = `<img src="${path}" class="max-w-full max-h-full object-contain">`;
            } else if (audExts.includes(ext)) {
                container.innerHTML = `<div class="text-center p-10 glass rounded-3xl w-80"><i class="fas fa-music text-6xl text-purple-500 mb-6 block"></i><audio controls autoplay class="w-full"><source src="${path}"></audio></div>`;
            } else if (vidExts.includes(ext)) {
                container.innerHTML = `<video controls autoplay class="w-full max-h-full"><source src="${path}"></video>`;
            } else if (webExts.includes(ext)) {
                container.innerHTML = `<iframe id="previewIframe" src="${path}"></iframe>`;
            } else {
                openEditor(name); return;
            }
            document.getElementById('previewUI').classList.remove('hidden');
        }

        function closePreview() {
            document.getElementById('previewUI').classList.add('hidden');
            document.getElementById('previewContainer').innerHTML = "";
        }

        function openMenu(name, locked, ext) {
            cur = name;
            document.getElementById('mTitle').innerText = name;
            const btnBox = document.getElementById('menuButtons');
            let btns = "";

            if (locked) {
                btns = `<button onclick="openSecurity('unlock')" class="w-full glass p-5 rounded-2xl flex items-center gap-4 text-green-400 font-bold"><i class="fas fa-unlock"></i> Unlock Resource</button>`;
            } else {
                btns += `<button onclick="closeAll(); openPreview('${name}', '${ext}')" class="w-full glass p-5 rounded-2xl flex items-center gap-4 text-emerald-400 font-bold"><i class="fas fa-eye"></i> View / Play</button>`;
                btns += `<button onclick="closeAll(); openEditor('${name}')" class="w-full glass p-5 rounded-2xl flex items-center gap-4 text-blue-400 font-bold"><i class="fas fa-pen-nib"></i> Edit Code</button>`;
                btns += `<button onclick="openSecurity('lock')" class="w-full glass p-5 rounded-2xl flex items-center gap-4 text-gray-400 font-bold"><i class="fas fa-lock"></i> Lock Access</button>`;
                btns += `<button onclick="run('delete')" class="w-full glass p-5 rounded-2xl flex items-center gap-4 text-red-500 font-extrabold"><i class="fas fa-trash-can"></i> Delete</button>`;
            }
            btnBox.innerHTML = btns;
            openSheet('sheetMenu');
        }

        function promptAction(a, t) {
            const val = prompt(t);
            if(val) post(a, { name: val });
        }

        function openSecurity(type) {
            closeAll();
            document.getElementById('secTitle').innerText = type === 'lock' ? "Set Password" : "Locked Resource";
            document.getElementById('secBtn').onclick = () => {
                const p = document.getElementById('secPass').value;
                if(p) post(type, { name: cur, password: p });
            };
            openSheet('sheetSecurity');
        }

        function run(a) { if(confirm("Are you sure?")) post(a, { name: cur }); }

        function post(a, d) {
            const f = document.createElement('form');
            f.method = 'POST';
            f.innerHTML = `<input type="hidden" name="action" value="${a}">`;
            for(let k in d) f.innerHTML += `<input type="hidden" name="${k}" value="${d[k]}">`;
            document.body.appendChild(f);
            f.submit();
        }

        function openEditor(name) {
            cur = name;
            const fd = new FormData();
            fd.append('action', 'get_content');
            fd.append('name', name);
            fetch('', { method: 'POST', body: fd }).then(r => r.text()).then(t => {
                if(t === "LOCKED") { openSecurity('unlock'); return; }
                document.getElementById('edText').value = t;
                document.getElementById('edName').innerText = name;
                document.getElementById('editorUI').classList.remove('hidden');
            });
        }

        function closeEditor() { document.getElementById('editorUI').classList.add('hidden'); }
        function saveEditor() {
            const fd = new FormData();
            fd.append('action', 'save_file');
            fd.append('name', cur);
            fd.append('content', document.getElementById('edText').value);
            fetch('', { method: 'POST', body: fd }).then(() => { alert("Saved Successfully"); });
        }

        // Show result messages
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.has('msg')) alert(decodeURIComponent(urlParams.get('msg')));
    </script>
</body>
</html>