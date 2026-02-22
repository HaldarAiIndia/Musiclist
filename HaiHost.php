<?php
// Configuration
$baseDir = __DIR__;
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$baseUrl = $protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

$successUrl = '';
$errorMsg = '';
$generalSuccessMsg = '';
$editSiteName = '';
$editContent = '';

// Helper function to recursively delete directory
function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return rmdir($dir);
}

// Process Form Submissions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $rawSiteName = trim($_POST['site_name'] ?? '');
    
    // Sanitize folder name to be URL-safe (letters, numbers, hyphens, underscores)
    $siteName = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($rawSiteName));
    
    if (empty($siteName)) {
        $errorMsg = "Please enter a valid website name.";
    } else {
        $targetDir = $baseDir . '/' . $siteName;
        
        // ACTION: DELETE
        if ($action === 'delete') {
            if (is_dir($targetDir)) {
                deleteDirectory($targetDir);
                $generalSuccessMsg = "Website '$siteName' has been deleted successfully.";
            } else {
                $errorMsg = "Website not found.";
            }
        } 
        // ACTION: UPDATE (Edit existing site)
        elseif ($action === 'update') {
            $codeContent = $_POST['code_content'] ?? '';
            $indexPath = $targetDir . '/index.php';
            // Fallback to html if php doesn't exist
            if (!file_exists($indexPath)) {
                $indexPath = $targetDir . '/index.html';
            }
            
            if (file_put_contents($indexPath, $codeContent) !== false) {
                $generalSuccessMsg = "Website '$siteName' updated successfully.";
            } else {
                $errorMsg = "Failed to update the code file.";
            }
        } 
        // ACTIONS: CREATE / UPLOAD (Original Logic)
        else {
            // Check if directory already exists
            if (file_exists($targetDir)) {
                $errorMsg = "A website with that name already exists. Please choose another name.";
            } else {
                // Create the directory
                if (!mkdir($targetDir, 0777, true)) {
                    $errorMsg = "Failed to create directory.";
                } else {
                    
                    // CREATE (Paste Code)
                    if ($action === 'create') {
                        $codeContent = $_POST['code_content'] ?? '';
                        
                        // Get selected file type (default to php if invalid)
                        $fileType = (isset($_POST['file_type']) && $_POST['file_type'] === 'html') ? 'html' : 'php';
                        $indexPath = $targetDir . '/index.' . $fileType; 
                        
                        if (file_put_contents($indexPath, $codeContent) !== false) {
                            $successUrl = $baseUrl . '/' . $siteName;
                        } else {
                            $errorMsg = "Failed to save the code file.";
                            rmdir($targetDir); // Rollback
                        }
                    }
                    
                    // UPLOAD (ZIP or Single File)
                    elseif ($action === 'upload') {
                        if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] === UPLOAD_ERR_OK) {
                            $tmpName = $_FILES['upload_file']['tmp_name'];
                            $fileName = basename($_FILES['upload_file']['name']);
                            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                            
                            // If it's a ZIP file, extract it
                            if ($fileExt === 'zip') {
                                $zip = new ZipArchive();
                                if ($zip->open($tmpName) === TRUE) {
                                    $zip->extractTo($targetDir);
                                    $zip->close();
                                    $successUrl = $baseUrl . '/' . $siteName;
                                } else {
                                    $errorMsg = "Failed to extract ZIP file.";
                                    rmdir($targetDir); // Rollback
                                }
                            } else {
                                // If it's a regular file (e.g., an HTML file), just move it
                                $uploadPath = $targetDir . '/' . $fileName;
                                if (move_uploaded_file($tmpName, $uploadPath)) {
                                    // If they uploaded an index file, point directly to the folder. 
                                    if (in_array(strtolower($fileName), ['index.html', 'index.php'])) {
                                        $successUrl = $baseUrl . '/' . $siteName;
                                    } else {
                                        $successUrl = $baseUrl . '/' . $siteName . '/' . $fileName;
                                    }
                                } else {
                                    $errorMsg = "Failed to upload file.";
                                    rmdir($targetDir); // Rollback
                                }
                            }
                        } else {
                            $errorMsg = "No valid file was uploaded.";
                            rmdir($targetDir); // Rollback
                        }
                    }
                }
            }
        }
    }
}

// Process GET Requests (For Edit View Loader)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'edit' && !empty($_GET['site'])) {
    $rawSiteName = trim($_GET['site']);
    $editSiteName = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($rawSiteName));
    $targetDir = $baseDir . '/' . $editSiteName;
    
    if (is_dir($targetDir)) {
        $indexPath = $targetDir . '/index.php';
        if (!file_exists($indexPath)) {
            $indexPath = $targetDir . '/index.html';
        }
        
        if (file_exists($indexPath)) {
            $editContent = file_get_contents($indexPath);
        } else {
            // Default placeholder if no index exists inside a ZIP uploaded directory
            $editContent = "\n";
        }
    } else {
        $errorMsg = "Website not found for editing.";
        $editSiteName = '';
    }
}

// Fetch History (scan directories)
$historyList = [];
$scan = scandir($baseDir);
foreach ($scan as $item) {
    if ($item !== '.' && $item !== '..' && is_dir($baseDir . '/' . $item)) {
        $historyList[] = [
            'name' => $item,
            'url' => $baseUrl . '/' . $item,
            'time' => filemtime($baseDir . '/' . $item)
        ];
    }
}
usort($historyList, function($a, $b) {
    return $b['time'] - $a['time']; // Sort newest first
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    
    <title>HaiHost - Instant Web Deployer!</title>
    <meta name="description" content="HaiHost allows you to deploy and host HTML & PHP websites instantly from your mobile device. Fast, secure, and easy web hosting.">
    <meta name="keywords" content="HaiHost, free hosting, web deployer, PHP hosting, HTML hosting, mobile host, instant deploy, haihost.free.nf">
    <meta name="author" content="HaiHost">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://haihost.free.nf/">

    <meta property="og:type" content="website">
    <meta property="og:url" content="https://haihost.free.nf/">
    <meta property="og:title" content="HaiHost - Instant Web Deployer">
    <meta property="og:description" content="Deploy and host your HTML & PHP websites instantly from your mobile device. Fast, secure, and easy.">
    <meta property="og:image" content="https://haihost.free.nf/favicon.png">

    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://haihost.free.nf/">
    <meta property="twitter:title" content="HaiHost - Instant Web Deployer">
    <meta property="twitter:description" content="Deploy and host your HTML & PHP websites instantly from your mobile device. Fast, secure, and easy.">
    <meta property="twitter:image" content="https://haihost.free.nf/favicon.png">

    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="shortcut icon" href="favicon.png">

    <meta name="theme-color" content="#0b0e14">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="HaiHost">
    <link rel="apple-touch-icon" href="favicon.png">
    <link rel="manifest" href='data:application/manifest+json;utf8,{"name":"HaiHost","short_name":"HaiHost","start_url":"/","display":"standalone","background_color":"#0b0e14","theme_color":"#0b0e14","icons":[{"src":"favicon.png","sizes":"192x192","type":"image/png"},{"src":"favicon.png","sizes":"512x512","type":"image/png"}]}'>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&family=Roboto+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,300,0,0" />
    
    <style>
        :root {
            /* PROX Admin Pro Neon/Dark Theme Applied to HaiHost */
            --bg-body: #000000;        /* Deep black for outside container */
            --bg-primary: #0b0e14;     /* Dark navy/black for app background */
            --bg-secondary: rgba(255, 255, 255, 0.03); /* Glass effect cards */
            --bg-tertiary: rgba(255, 255, 255, 0.06);  /* Hover states / Input bg */
            --bg-hover: rgba(255, 255, 255, 0.08);       
            
            --text-primary: #f8fafc;   
            --text-secondary: #94a3b8; 
            --text-active: #3b82f6;    /* Bright Blue accent */
            
            --border-color: rgba(255, 255, 255, 0.08); /* Subtle glass border */
            
            --error-color: #ef4444;    /* Red */
            --success-color: #10b981;  /* Emerald */
            --warning-color: #f59e0b;  /* Yellow/Orange */
            
            --font-family: 'Plus Jakarta Sans', sans-serif;
            --font-family-code: 'Roboto Mono', monospace;
        }

        /* --- Base & Reset --- */
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; width: 100%; background-color: var(--bg-body); color: var(--text-primary); font-family: var(--font-family); font-size: 16px; display: flex; justify-content: center; overflow-x: hidden;}
        button, a { background: none; border: none; cursor: pointer; color: inherit; text-decoration: none; font-family: inherit; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24; font-size: 24px; color: var(--text-secondary); transition: color 0.2s; user-select: none; }

        /* --- App Container (Mobile Simulator) --- */
        .app-container { 
            width: 100%; 
            max-width: 480px; 
            height: 100dvh; 
            background-color: var(--bg-primary); 
            display: flex; 
            flex-direction: column; 
            position: relative; 
            box-shadow: 0 0 40px rgba(0,0,0,0.5);
            overflow: hidden;
        }

        /* --- App Header --- */
        .app-header { 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 60px; 
            padding: 0 16px; 
            background-color: rgba(11, 14, 20, 0.85); 
            backdrop-filter: blur(20px); 
            border-bottom: 1px solid var(--border-color); 
            flex-shrink: 0; 
            position: sticky; top: 0; z-index: 100; 
        }
        .header-title { font-size: 1.15rem; font-weight: 800; display: flex; align-items: center; gap: 8px; letter-spacing: 0.3px; }

        /* --- Content Area --- */
        .content-area { 
            flex-grow: 1; 
            position: relative; 
            overflow: hidden;
        }
        .screen { 
            display: none; 
            position: absolute; top: 0; left: 0; 
            width: 100%; height: 100%; 
            overflow-y: auto; 
            padding: 20px 16px 40px; 
            scrollbar-width: none; 
        }
        .screen::-webkit-scrollbar { display: none; }
        .screen.active { display: block; animation: fade-in 0.2s ease-out; }
        
        @keyframes fade-in { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* --- Page Titles --- */
        .page-header { margin-bottom: 24px; }
        .page-header h2 { font-size: 1.5rem; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .page-header p { font-size: 0.95rem; color: var(--text-secondary); margin-top: 6px; }

        /* --- App Bottom Navigation --- */
        .bottom-nav { 
            position: sticky; bottom: 0; z-index: 100; 
            height: 70px; 
            background-color: #131720; 
            border-top: 1px solid rgba(59, 130, 246, 0.2); /* Slight blue tint border */
            border-radius: 2rem 2rem 0 0;
            display: flex; justify-content: space-around; align-items: center; 
            padding-bottom: env(safe-area-inset-bottom); 
            box-shadow: 0 -4px 20px rgba(0,0,0,0.5);
        }
        .nav-btn { 
            display: flex; flex-direction: column; align-items: center; justify-content: center; 
            gap: 4px; color: var(--text-secondary); font-size: 0.7rem; font-weight: 600; 
            flex: 1; height: 100%; transition: color 0.2s; 
        }
        .nav-btn.active { color: var(--text-active); }
        .nav-btn .material-symbols-outlined { font-size: 26px; color: inherit; transition: all 0.2s;}
        .nav-btn.active .material-symbols-outlined { font-variation-settings: 'FILL' 1; transform: scale(1.1); }

        /* --- Home Cards (Mobile Touch Targets) --- */
        .suggestion-grid { display: flex; flex-direction: column; gap: 16px; }
        .suggestion-card { 
            background: var(--bg-secondary);
            backdrop-filter: blur(20px); 
            border: 1px solid var(--border-color); 
            border-radius: 1.5rem; /* 24px rounded corners */
            padding: 20px; 
            text-align: left; 
            display: flex; align-items: center; gap: 16px;
            transition: transform 0.1s, background-color 0.2s; 
        }
        .suggestion-card:active { transform: scale(0.98); background: var(--bg-hover); }
        .suggestion-card .icon-wrapper { 
            background-color: rgba(255, 255, 255, 0.05); 
            padding: 12px; border-radius: 1rem; display: flex; 
        }
        .suggestion-card .material-symbols-outlined { color: var(--text-active); font-size: 28px; }
        .suggestion-card-text h3 { font-size: 1.05rem; font-weight: 800; margin-bottom: 4px; color: var(--text-primary); }
        .suggestion-card-text p { font-size: 0.85rem; color: var(--text-secondary); line-height: 1.4; font-weight: 500;}

        /* --- Alerts / Banners --- */
        .alert-box { border-radius: 1rem; padding: 16px; margin-bottom: 24px; font-size: 0.95rem; font-weight: 600; display: flex; align-items: flex-start; gap: 12px;}
        .alert-error { color: #fca5a5; background-color: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); }
        .alert-success { color: #6ee7b7; background-color: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); }

        /* --- History App-List --- */
        .history-list { display: flex; flex-direction: column; gap: 12px; }
        .history-item { 
            background: var(--bg-secondary);
            backdrop-filter: blur(20px); 
            border-radius: 1.5rem; 
            padding: 16px; 
            border: 1px solid var(--border-color); 
            display: flex; flex-direction: column; gap: 16px; 
        }
        .history-info { display: flex; align-items: center; gap: 12px; }
        .history-info .icon { background: rgba(255, 255, 255, 0.05); padding: 10px; border-radius: 1rem; display: flex; color: var(--text-active); }
        .history-info .text-wrap { flex: 1; min-width: 0; }
        .history-info p { font-size: 1.05rem; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .history-info span { font-size: 0.8rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;}
        
        .history-actions { display: flex; gap: 8px; border-top: 1px solid var(--border-color); padding-top: 12px;}
        .history-actions .btn-action { 
            flex: 1; display: flex; justify-content: center; align-items: center; gap: 6px; 
            background: rgba(255, 255, 255, 0.03); padding: 10px; border-radius: 1rem; font-size: 0.85rem; font-weight: 800; text-transform: uppercase;
        }
        .history-actions .btn-action:active { background: var(--bg-hover); }

        /* --- Forms & Inputs (Mobile Optimized) --- */
        .app-card { 
            background: var(--bg-secondary);
            backdrop-filter: blur(20px); 
            border-radius: 1.5rem; 
            padding: 20px; 
            border: 1px solid var(--border-color); 
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary); margin-bottom: 8px; }
        
        .form-group input, .form-group textarea, .form-group select { 
            width: 100%; 
            background-color: var(--bg-tertiary); 
            border: 1px solid var(--border-color); 
            border-radius: 1rem; 
            padding: 14px 16px; 
            font-size: 1rem; color: var(--text-primary); 
            outline: none; transition: border-color 0.2s; 
            font-family: inherit; font-weight: 600;
            -webkit-appearance: none; 
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--text-active); }
        /* Glowing text in textarea for that PROX Editor feel */
        .form-group textarea { resize: vertical; font-family: var(--font-family-code); font-size: 0.9rem; color: #34d399; min-height: 150px; font-weight: 400;}
        
        .upload-dropzone { 
            border: 1px dashed rgba(255, 255, 255, 0.2); 
            padding: 32px 16px; 
            border-radius: 1rem; 
            text-align: center; 
            background: rgba(255, 255, 255, 0.02); 
            position: relative;
        }
        .upload-dropzone input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        
        /* --- Buttons --- */
        .btn { 
            width: 100%; 
            padding: 16px; 
            font-weight: 800; font-size: 1rem; 
            border-radius: 1rem; 
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: transform 0.1s, opacity 0.2s; 
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .btn:active { transform: scale(0.98); }
        .btn-primary { 
            background-color: var(--text-active); 
            color: #ffffff; 
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4); /* Glow effect */
        }
        .btn-secondary { background-color: transparent; border: 1px solid var(--border-color); color: var(--text-primary); }
    </style>
</head>
<body>

    <div class="app-container">
        
        <header class="app-header">
            <div class="header-title">
                <span class="material-symbols-outlined" style="color: var(--text-active); font-variation-settings: 'FILL' 1;">rocket_launch</span> 
                HaiHost
            </div>
        </header>

        <main class="content-area">
            
            <div id="home-screen" class="screen">
                
                <?php if ($errorMsg): ?>
                    <div class="alert-box alert-error">
                        <span class="material-symbols-outlined" style="font-size: 20px;">error</span>
                        <div style="flex:1;"><?php echo htmlspecialchars($errorMsg); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($successUrl): ?>
                    <div class="alert-box alert-success" style="flex-direction: column; align-items: stretch;">
                        <div style="display: flex; align-items: center; gap: 8px; font-weight: 800; font-size: 1.1rem; margin-bottom: 12px;">
                            <span class="material-symbols-outlined" style="color: inherit; font-size: 24px;">check_circle</span>
                            Deployed Successfully!
                        </div>
                        <a href="<?php echo htmlspecialchars($successUrl); ?>" target="_blank" class="btn" style="background-color: rgba(16, 185, 129, 0.2); color: #6ee7b7; border: 1px solid rgba(16, 185, 129, 0.4);">
                            Open Live Site <span class="material-symbols-outlined" style="font-size: 18px; color: inherit;">open_in_new</span>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($generalSuccessMsg): ?>
                    <div class="alert-box alert-success">
                        <span class="material-symbols-outlined" style="font-size: 20px;">check_circle</span>
                        <div style="flex:1;"><?php echo htmlspecialchars($generalSuccessMsg); ?></div>
                    </div>
                <?php endif; ?>

                <div class="page-header" style="text-align: left; margin-bottom: 30px;">
                    <h2 style="font-size: 1.8rem;">Dashboard</h2>
                    <p>What would you like to build today?</p>
                </div>

                <div class="suggestion-grid">
                    <button class="suggestion-card" onclick="showScreen('create-screen')">
                        <div class="icon-wrapper">
                            <span class="material-symbols-outlined">code</span>
                        </div>
                        <div class="suggestion-card-text">
                            <h3>Create Site</h3>
                            <p>Write or paste your code directly.</p>
                        </div>
                    </button>
                    
                    <button class="suggestion-card" onclick="showScreen('upload-screen')">
                        <div class="icon-wrapper">
                            <span class="material-symbols-outlined">cloud_upload</span>
                        </div>
                        <div class="suggestion-card-text">
                            <h3>Upload ZIP</h3>
                            <p>Extract and host files instantly.</p>
                        </div>
                    </button>
                </div>
            </div>

            <div id="create-screen" class="screen">
                <div class="page-header">
                    <h2>Create Website</h2>
                    <p>Deploy custom HTML or PHP code.</p>
                </div>
                
                <div class="app-card">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="form-group">
                            <label>Website Name</label>
                            <input type="text" name="site_name" placeholder="e.g. my-awesome-site" required autocomplete="off">
                        </div>

                        <div class="form-group">
                            <label>Index File Type</label>
                            <select name="file_type">
                                <option value="html">HTML Document (.html)</option>
                                <option value="php" selected>PHP Script (.php)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Paste Code</label>
                            <textarea name="code_content" placeholder="<!DOCTYPE html>&#10;<html>...</html>" required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-outlined" style="color: inherit; font-size: 20px;">rocket_launch</span> Deploy Now
                        </button>
                    </form>
                </div>
            </div>

            <div id="upload-screen" class="screen">
                <div class="page-header">
                    <h2>Upload Files</h2>
                    <p>Upload a .zip or an index file to host.</p>
                </div>
                
                <div class="app-card">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload">
                        
                        <div class="form-group">
                            <label>Website Name</label>
                            <input type="text" name="site_name" placeholder="e.g. portfolio-v2" required autocomplete="off">
                        </div>

                        <div class="form-group">
                            <label>Upload Archive / File</label>
                            <div class="upload-dropzone">
                                <span class="material-symbols-outlined" style="font-size: 40px; color: var(--text-active); margin-bottom: 8px;">folder_zip</span>
                                <h4 style="font-weight: 800; margin-bottom: 4px;">Tap to select file</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8rem;">ZIP files are automatically extracted.</p>
                                <input type="file" name="upload_file" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
                            <span class="material-symbols-outlined" style="color: inherit; font-size: 20px;">cloud_upload</span> Start Upload
                        </button>
                    </form>
                </div>
            </div>

            <div id="history-screen" class="screen">
                <div class="page-header">
                    <h2>Deployments</h2>
                    <p>Manage your hosted websites.</p>
                </div>
                
                <div class="history-list">
                    <?php if (empty($historyList)): ?>
                        <div style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                            <span class="material-symbols-outlined" style="font-size: 48px; margin-bottom: 12px; opacity: 0.5;">inbox</span>
                            <p>No websites deployed yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($historyList as $site): ?>
                            <div class="history-item">
                                <div class="history-info">
                                    <div class="icon">
                                        <span class="material-symbols-outlined" style="color: inherit;">public</span>
                                    </div>
                                    <div class="text-wrap">
                                        <p><?php echo htmlspecialchars($site['name']); ?></p>
                                        <span><?php echo date("M j, Y, g:i a", $site['time']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="history-actions">
                                    <a href="<?php echo htmlspecialchars($site['url']); ?>" target="_blank" class="btn-action" style="color: #34d399;">
                                        <span class="material-symbols-outlined" style="font-size: 18px; color: inherit;">open_in_new</span> Open
                                    </a>
                                    
                                    <a href="?action=edit&site=<?php echo urlencode($site['name']); ?>" class="btn-action" style="color: var(--text-active);">
                                        <span class="material-symbols-outlined" style="font-size: 18px; color: inherit;">edit</span> Edit
                                    </a>
                                    
                                    <form method="POST" action="" style="margin: 0; flex: 1; display: flex;" onsubmit="return confirm('Are you sure you want to delete \'<?php echo htmlspecialchars($site['name']); ?>\'? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="site_name" value="<?php echo htmlspecialchars($site['name']); ?>">
                                        <button type="submit" class="btn-action" style="color: var(--error-color); width: 100%;">
                                            <span class="material-symbols-outlined" style="font-size: 18px; color: inherit;">delete</span> Del
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div id="edit-screen" class="screen">
                <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;">
                    <div style="min-width: 0;">
                        <h2 style="font-size: 1.3rem; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            Edit: <?php echo htmlspecialchars($editSiteName); ?>
                        </h2>
                        <p style="margin:0;">Modify your index file.</p>
                    </div>
                    <a href="?" class="btn btn-secondary" style="width: auto; padding: 8px 16px; font-size: 0.85rem; height: auto;">Cancel</a>
                </div>
                
                <div class="app-card">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="site_name" value="<?php echo htmlspecialchars($editSiteName); ?>">

                        <div class="form-group" style="margin-bottom: 24px;">
                            <label>Source Code</label>
                            <textarea name="code_content" style="min-height: 250px;" required><?php echo htmlspecialchars($editContent); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-outlined" style="color: inherit; font-size: 20px;">save</span> Save Changes
                        </button>
                    </form>
                </div>
            </div>

        </main>

        <nav class="bottom-nav">
            <button class="nav-btn" data-target="home-screen" onclick="showScreen('home-screen')">
                <span class="material-symbols-outlined">dashboard</span>
                Home
            </button>
            <button class="nav-btn" data-target="create-screen" onclick="showScreen('create-screen')">
                <span class="material-symbols-outlined">add_box</span>
                Create
            </button>
            <button class="nav-btn" data-target="upload-screen" onclick="showScreen('upload-screen')">
                <span class="material-symbols-outlined">upload_file</span>
                Upload
            </button>
            <button class="nav-btn" data-target="history-screen" onclick="showScreen('history-screen')">
                <span class="material-symbols-outlined">format_list_bulleted</span>
                History
            </button>
        </nav>

    </div>

    <script>
        function showScreen(screenId) {
            // Hide all screens
            document.querySelectorAll('.screen').forEach(screen => {
                screen.classList.remove('active');
            });
            
            // Remove active state from nav buttons
            document.querySelectorAll('.nav-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show target screen
            const targetScreen = document.getElementById(screenId);
            if (targetScreen) {
                targetScreen.classList.add('active');
            }

            // Set active nav button
            const targetNavBtn = document.querySelector(`.nav-btn[data-target="${screenId}"]`);
            if (targetNavBtn) {
                targetNavBtn.classList.add('active');
            }

            // Clean URL if navigating away from Edit view to prevent returning to it on refresh
            if(screenId !== 'edit-screen' && window.location.search.includes('action=edit')) {
                window.history.replaceState(null, null, window.location.pathname);
            }
        }

        // Determine which screen to show on initial load based on PHP variables
        document.addEventListener('DOMContentLoaded', () => {
            const isEditing = <?php echo $editSiteName ? 'true' : 'false'; ?>;
            const hasActionParam = window.location.search.includes('action=');
            
            if (isEditing) {
                // Remove active states from nav since we are deep-linked into Edit
                document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
                document.getElementById('edit-screen').classList.add('active');
            } else if (hasActionParam) {
                // Fallback to history if there was an action but edit failed/finished
                showScreen('history-screen');
            } else {
                // Default to home
                showScreen('home-screen');
            }
        });
    </script>
</body>
</html>