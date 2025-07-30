<?php
// shell_scanner_strict.php - Improved scanner to detect stealthy loaders and backdoors

error_reporting(0);
set_time_limit(0);

$base = isset($_GET['path']) ? realpath($_GET['path']) : getcwd();

if (isset($_GET['delete'])) {
    $target = realpath($_GET['delete']);
    if ($target && strpos($target, $base) === 0 && is_file($target)) {
        unlink($target);
        header("Location: ?path=" . urlencode($base));
        exit;
    }
}

$patterns = [
    // Classic suspicious functions
    '/eval\s*\(/i',
    '/base64_decode\s*\(/i',
    '/gzinflate\s*\(/i',
    '/assert\s*\(/i',
    '/preg_replace\s*\(.*\/e/i',
    '/(system|shell_exec|passthru|exec)\s*\(/i',
    '/php:\/\/input/i',

    // Remote fetch functions
    '/curl_(init|exec|setopt)/i',
    '/file_get_contents\s*\(/i',
    '/fopen\s*\(/i',
    '/stream_get_contents/i',

    // Dynamic includes
    '/include\s*\(\s*\$[a-z0-9_]+\s*\)/i',
    '/require\s*\(\s*\$[a-z0-9_]+\s*\)/i',

    // Obfuscation patterns
    '/["\']\s*\.\s*["\']/i',  // string concat e.g. "f"."o"."p"
    '/md5\s*\(\s*\$_SERVER\s*\[\s*[\'"]HTTP_HOST[\'"]\s*\]/i',

    // Suspicious paths
    '/\/dev\/shm\//i',

    // Known external URLs (like GitHub raw)
    '/raw\.githubusercontent\.com/i',
];

// Count how many patterns match in code
function is_shell($code, $patterns) {
    $score = 0;
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $code)) {
            $score++;
        }
    }
    return $score >= 3; // Threshold to flag as shell
}

// Get code snippet for preview
function snippet_preview($code, $match) {
    $pos = stripos($code, $match);
    return $pos === false ? '' : substr($code, max(0, $pos - 40), 400);
}

// Recursive scan for .php and .phtml files
function scan_folder($dir, $patterns, &$results, $base) {
    $items = @scandir($dir);
    if (!$items) return;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path)) {
            scan_folder($path, $patterns, $results, $base);
        } elseif (preg_match('/\.(php|phtml)$/i', $item) && filesize($path) < 1000000) {
            $code = @file_get_contents($path);
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $code, $match) && is_shell($code, $patterns)) {
                    $results[] = [
                        'file'    => $path,
                        'match'   => $match[0],
                        'snippet' => snippet_preview($code, $match[0]),
                    ];
                    break;
                }
            }
        }
    }
}

$results = [];
scan_folder($base, $patterns, $results, $base);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>Strict PHP Shell Scanner</title>
    <style>
        body { background:#111; color:#eee; font-family: monospace; padding:20px; }
        h1 { color:#0f0; }
        .result { background:#1e1e1e; border:1px solid #333; padding:15px; margin-bottom:15px; border-radius:8px; }
        .path { color:#4af; font-weight:bold; }
        .bad { color:#ff5252; }
        pre { background:#000; color:#0f0; padding:10px; border-radius:6px; overflow:auto; max-height: 200px; }
        a.button { color:#fff; background:#c00; padding:4px 10px; text-decoration:none; border-radius:5px; margin-left:10px; }
        a.button:hover { background:#f00; }
        code { color:#0af; }
    </style>
</head>
<body>
    <h1>🛡️ Strict PHP Shell Scanner (.php & .phtml)</h1>
    <p>📂 Scanning directory: <code><?= htmlspecialchars($base) ?></code></p>
    <hr>

    <?php if (empty($results)): ?>
        <p style="color:lime;">✅ No suspicious backdoor or shell found.</p>
    <?php else: ?>
        <?php foreach ($results as $r): ?>
            <div class="result">
                <div class="path">
                    📄 <a href="<?= htmlspecialchars(str_replace($_SERVER['DOCUMENT_ROOT'], '', $r['file'])) ?>" target="_blank"><?= htmlspecialchars($r['file']) ?></a>
                    <a class="button" href="?path=<?= urlencode($base) ?>&delete=<?= urlencode($r['file']) ?>" onclick="return confirm('Delete this file?\n<?= htmlspecialchars($r['file']) ?>')">Delete</a>
                </div>
                <div>🚨 Matched pattern: <span class="bad"><?= htmlspecialchars($r['match']) ?></span></div>
                <pre><?= htmlspecialchars($r['snippet']) ?></pre>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
