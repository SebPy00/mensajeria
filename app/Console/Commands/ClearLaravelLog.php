<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class ClearLaravelLog extends Command
{
    protected $signature = 'logs:clear-laravel';
    protected $description = 'Clear Laravel log file, keeping entries from the last 15 days';

    public function handle()
    {
        $logPath = storage_path('logs');
        $logFile = 'laravel.log';
        $logFilePath = $logPath . '/' . $logFile;

        if (File::exists($logFilePath)) {
            $content = File::get($logFilePath);

            // Mantener solo las entradas de los últimos 15 días
            $cutOffDate = Carbon::now()->subDays(15)->format('Y-m-d H:i:s');
            $filteredContent = preg_replace("/^\[.*?\] local\.[^\n]*\n?/m", "", $content);
            $filteredContent = preg_replace("/^\[.*?\] production\.[^\n]*\n?/m", "", $filteredContent);

            $lines = explode("\n", $filteredContent);
            $filteredLines = array_filter($lines, function ($line) use ($cutOffDate) {
                return empty($line) || Carbon::parse(substr($line, 1, 19))->gt($cutOffDate);
            });

            $finalContent = implode("\n", $filteredLines);

            File::put($logFilePath, $finalContent);
            $this->info("Cleared and kept entries from the last 15 days in {$logFile}.");
        } else {
            $this->info("{$logFile} not found.");
        }
    }
}
