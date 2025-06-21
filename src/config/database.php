<?php
$databasePath = __DIR__ . '/../../data/database.sqlite';

try {
    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Database error');
}

// Contract table for storing basic contract metadata
$pdo->exec("CREATE TABLE IF NOT EXISTS contracts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    contract_text TEXT,
    pdf_path TEXT,
    status TEXT,
    created_at TEXT,
    updated_at TEXT
)");

