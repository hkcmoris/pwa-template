<?php
function log_message(string $message, string $level = 'INFO'): void {
    $date = date('c');
    $logLine = "[$date] [$level] $message\n";
    $logFile = __DIR__ . '/../backend.log';
    file_put_contents($logFile, $logLine, FILE_APPEND);
}

