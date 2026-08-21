<?php
// Unified Environment Bootstrap for Local & Vercel Serverless

// Error handling configuration: display errors for debugging
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * Returns dynamic writable storage directory (Local or Vercel Serverless /tmp)
 */
function getTcmStorageDir() {
    $localDir = dirname(__DIR__) . '/storage';
    $isVercel = getenv('VERCEL') || isset($_SERVER['VERCEL']) || (isset($_ENV['VERCEL']) && $_ENV['VERCEL']) || !is_writable($localDir);
    $dir = $isVercel ? sys_get_temp_dir() . '/tcm_storage' : $localDir;

    if (!file_exists($dir)) @mkdir($dir, 0777, true);
    if (!file_exists($dir . '/signatures')) @mkdir($dir . '/signatures', 0777, true);
    if (!file_exists($dir . '/pdf')) @mkdir($dir . '/pdf', 0777, true);
    if (!file_exists($dir . '/logs')) @mkdir($dir . '/logs', 0777, true);

    return $dir;
}

/**
 * Returns PDO connection with auto-migrated SQLite schema
 */
function getTcmDatabase() {
    $storageDir = getTcmStorageDir();
    $dbPath = $storageDir . '/consent.db';

    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA busy_timeout = 5000;');
    $pdo->exec('PRAGMA journal_mode = MEMORY;');

    // Auto-create SQLite tables if they do not exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS consent_forms (
            id TEXT PRIMARY KEY,
            status TEXT NOT NULL DEFAULT 'draft',
            language TEXT NOT NULL,
            consent_version TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NULL
        );
        CREATE TABLE IF NOT EXISTS patients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            consent_id TEXT NOT NULL,
            name TEXT NOT NULL,
            nric TEXT NOT NULL,
            address TEXT NOT NULL,
            postal_code TEXT NOT NULL,
            contact_number TEXT NOT NULL,
            gender TEXT NOT NULL,
            date_of_birth DATE NOT NULL,
            FOREIGN KEY (consent_id) REFERENCES consent_forms(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS guardians (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            consent_id TEXT NOT NULL,
            name TEXT NOT NULL,
            nric TEXT NOT NULL,
            relationship TEXT NOT NULL,
            FOREIGN KEY (consent_id) REFERENCES consent_forms(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS medical_answers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            consent_id TEXT NOT NULL,
            question_code TEXT NOT NULL,
            answer TEXT NOT NULL,
            specification TEXT,
            FOREIGN KEY (consent_id) REFERENCES consent_forms(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS signatures (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            consent_id TEXT NOT NULL,
            type TEXT NOT NULL,
            image_path TEXT NOT NULL,
            signed_by TEXT NOT NULL,
            signed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (consent_id) REFERENCES consent_forms(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            consent_id TEXT NOT NULL,
            event TEXT NOT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (consent_id) REFERENCES consent_forms(id) ON DELETE CASCADE
        );
    ");

    return $pdo;
}

/**
 * Flattens an RGBA 32-bit transparent PNG onto a clean white background in Pure PHP (zero dependencies)
 * to ensure 100% compatibility with TCPDF without producing solid black rectangles.
 */
function flattenPngAlphaToRgb($pngBinaryOrPath, $targetPath = null) {
    $raw = file_exists($pngBinaryOrPath) ? file_get_contents($pngBinaryOrPath) : $pngBinaryOrPath;
    if (substr($raw, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        if ($targetPath) file_put_contents($targetPath, $raw);
        return $raw;
    }

    $pos = 8;
    $len_raw = strlen($raw);
    $idat = "";
    $w = 0; $h = 0; $colorType = 0;
    while ($pos < $len_raw) {
        if ($pos + 8 > $len_raw) break;
        $len = unpack("N", substr($raw, $pos, 4))[1];
        $type = substr($raw, $pos + 4, 4);
        $data = substr($raw, $pos + 8, $len);
        if ($type === "IHDR") {
            $w = unpack("N", substr($data, 0, 4))[1];
            $h = unpack("N", substr($data, 4, 4))[1];
            $colorType = ord($data[9]);
        } elseif ($type === "IDAT") {
            $idat .= $data;
        }
        $pos += 12 + $len;
    }

    // If not RGBA (ColorType 6), it's already RGB or indexed
    if ($colorType !== 6 || empty($idat)) {
        if ($targetPath) file_put_contents($targetPath, $raw);
        return $raw;
    }

    $uncompressed = @gzuncompress($idat);
    if ($uncompressed === false) {
        if ($targetPath) file_put_contents($targetPath, $raw);
        return $raw;
    }

    $out_raw = "";
    $src_pos = 0;

    for ($y = 0; $y < $h; $y++) {
        if ($src_pos >= strlen($uncompressed)) break;
        $filter = ord($uncompressed[$src_pos++]);
        $out_raw .= chr(0); // None filter
        for ($x = 0; $x < $w; $x++) {
            if ($src_pos + 3 >= strlen($uncompressed)) break;
            $r = ord($uncompressed[$src_pos++]);
            $g = ord($uncompressed[$src_pos++]);
            $b = ord($uncompressed[$src_pos++]);
            $a = ord($uncompressed[$src_pos++]);

            $alpha = $a / 255.0;
            $out_r = (int)round(($r * $alpha) + (255 * (1.0 - $alpha)));
            $out_g = (int)round(($g * $alpha) + (255 * (1.0 - $alpha)));
            $out_b = (int)round(($b * $alpha) + (255 * (1.0 - $alpha)));

            $out_raw .= chr($out_r) . chr($out_g) . chr($out_b);
        }
    }

    $ihdr = pack("NNCCCCC", $w, $h, 8, 2, 0, 0, 0); // 8-bit RGB
    $ihdr_chunk = pack("N", 13) . "IHDR" . $ihdr . pack("N", crc32("IHDR" . $ihdr));
    $idat_data = gzcompress($out_raw, 9);
    $idat_chunk = pack("N", strlen($idat_data)) . "IDAT" . $idat_data . pack("N", crc32("IDAT" . $idat_data));
    $iend_chunk = pack("N", 0) . "IEND" . pack("N", crc32("IEND"));

    $png_out = "\x89PNG\r\n\x1a\n" . $ihdr_chunk . $idat_chunk . $iend_chunk;
    if ($targetPath) {
        file_put_contents($targetPath, $png_out);
    }
    return $png_out;
}

