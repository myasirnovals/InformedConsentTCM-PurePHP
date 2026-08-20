<?php
// Unified Environment Bootstrap for Local & Vercel Serverless

// Error handling configuration
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Ensure session directory is writable in Serverless / Lambda (/tmp)
if (session_status() === PHP_SESSION_NONE) {
    $tempSession = sys_get_temp_dir() . '/tcm_sessions';
    if (!file_exists($tempSession)) {
        @mkdir($tempSession, 0777, true);
    }
    if (is_dir($tempSession) && is_writable($tempSession)) {
        @session_save_path($tempSession);
    } elseif (is_writable(sys_get_temp_dir())) {
        @session_save_path(sys_get_temp_dir());
    }
    @session_start();
}

/**
 * Returns dynamic writable storage directory (Local or Vercel Serverless /tmp)
 */
function getTcmStorageDir() {
    $localDir = dirname(__DIR__) . '/storage';
    $isVercel = getenv('VERCEL') || isset($_SERVER['VERCEL']) || !is_writable($localDir);
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
    $pdo->exec('PRAGMA journal_mode=WAL;');

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
