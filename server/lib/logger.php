<?php
function log_message(string $message, string $level = 'INFO'): void {
    $timestamp = date('c');
    $line = "[$timestamp] [$level] $message\n";

    // Use a dedicated logs directory with daily-rotated files
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        // Best effort create; 0775 is safer than 0777 but still permissive on shared hosts
        @mkdir($logDir, 0775, true);
    }

    $logFile = $logDir . '/' . date('Y-m-d') . '.log';

    // Ensure file exists and has sane permissions
    if (!file_exists($logFile)) {
        if (@file_put_contents($logFile, '') !== false) {
            @chmod($logFile, 0644);
        }
    }

    // Try to append; if it fails, fall back to PHP error log
    if (@file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) === false) {
        error_log($line);
    }
}
