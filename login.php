<?php
/**
 * Manawa Review — koneksi DB + helper bersama.
 * Semua endpoint meng-include file ini paling atas.
 */

// ---- Sesi (auth penjual/admin) -----------------------------
session_start();

// ---- Header umum -------------------------------------------
header('Content-Type: application/json; charset=utf-8');

// ---- Koneksi PDO (XAMPP default: root tanpa password) ------
const DB_HOST = '127.0.0.1';
const DB_NAME = 'manawa_review';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            json_out(['error' => 'Koneksi database gagal. Pastikan MySQL XAMPP berjalan & schema.sql sudah diimport.'], 500);
        }
    }
    return $pdo;
}

// ---- Helper ------------------------------------------------
function json_out($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Ambil body JSON dari request. */
function json_in(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Pastikan method sesuai, kalau tidak balas 405. */
function require_method(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        json_out(['error' => 'Method tidak diizinkan'], 405);
    }
}

/** Kembalikan akun login saat ini, atau 401 bila belum login. */
function require_login(): array {
    if (empty($_SESSION['akun'])) {
        json_out(['error' => 'Belum login'], 401);
    }
    return $_SESSION['akun'];
}

/** Versi tenant yang aman dikirim ke klien. */
function tenant_public(array $t): array {
    return [
        'id'   => (int) $t['id'],
        'slug' => $t['slug'],
        'name' => $t['nama_bisnis'],
        'type' => $t['tipe'],
        'spot' => $t['spot'],
        'logo' => $t['logo_url'] ?? null,
    ];
}
