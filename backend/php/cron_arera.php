#!/usr/bin/env php
<?php
/**
 * cron_arera.php — Sincronizzazione automatica offerte ARERA (eseguito da OVH Cron)
 *
 * USAGE: php cron_arera.php
 * 
 * OVH Cron settings:
 *   Frequenza: Daily (ogni giorno)
 *   Comando:   php /home/<TUO_USER>/www/cron_arera.php
 *   Email:     (lasciare vuota o tua email per i log)
 */

// ── Setup ──────────────────────────────────────────────────────
$start = microtime(true);
$date = date('Y-m-d H:i:s');
echo "=== ARERA Sync Cron [{$date}] ===\n";

// Trova il percorso della directory corrente (www/)
$baseDir = __DIR__;
chdir($baseDir);

require_once __DIR__ . '/inc/arera_sync.php';

// ── Variabili globali (definite in arera_sync.php) ────────────
global $brand_metadata, $parametri_mercato;

// ── Sync Luce ─────────────────────────────────────────────────
echo "\n--- Sync LUCE ---\n";
try {
    $resultLuce = arera_run_sync('luce', $brand_metadata, $parametri_mercato);
    if ($resultLuce['success']) {
        echo "LUCE: {$resultLuce['count']} offerte sincronizzate in {$resultLuce['elapsed']}s\n";
    } else {
        echo "LUCE: ERRORE - " . ($resultLuce['error'] ?? 'sconosciuto') . "\n";
    }
} catch (Throwable $e) {
    echo "LUCE: EXCEPTION - {$e->getMessage()}\n";
}

// ── Sync Gas ──────────────────────────────────────────────────
echo "\n--- Sync GAS ---\n";
try {
    $resultGas = arera_run_sync('gas', $brand_metadata, $parametri_mercato);
    if ($resultGas['success']) {
        echo "GAS: {$resultGas['count']} offerte sincronizzate in {$resultGas['elapsed']}s\n";
    } else {
        echo "GAS: ERRORE - " . ($resultGas['error'] ?? 'sconosciuto') . "\n";
    }
} catch (Throwable $e) {
    echo "GAS: EXCEPTION - {$e->getMessage()}\n";
}

// ── Riepilogo ─────────────────────────────────────────────────
$elapsed = round(microtime(true) - $start, 2);
echo "\n=== Fine sync ({$elapsed}s) ===\n";
