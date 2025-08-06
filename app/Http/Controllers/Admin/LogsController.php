<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class LogsController extends Controller
{
    public function index(Request $request)
    {
        $logFile = storage_path('logs/laravel.log');
        $logs = [];
        $totalLines = 0;
        $filters = [
            'level' => $request->get('level', 'all'),
            'date' => $request->get('date', now()->format('Y-m-d')),
            'search' => '', // Gardé pour compatibilité de la vue
        ];

        if (File::exists($logFile)) {
            // Lire le fichier de logs
            $content = File::get($logFile);
            $lines = explode("\n", $content);
            $totalLines = count($lines);
            
            // Parser les logs
            $currentLog = null;
            foreach ($lines as $line) {
                if (preg_match('/^\[([\d-]+\s[\d:]+)\]\s(\w+\.\w+):\s(.+)/', $line, $matches)) {
                    // Nouvelle entrée de log
                    if ($currentLog) {
                        $logs[] = $currentLog;
                    }
                    
                    $currentLog = [
                        'timestamp' => Carbon::parse($matches[1]),
                        'level' => strtolower(explode('.', $matches[2])[1]),
                        'message' => $matches[3],
                        'stack_trace' => []
                    ];
                } elseif ($currentLog && trim($line) !== '') {
                    // Stack trace ou continuation du message
                    $currentLog['stack_trace'][] = $line;
                }
            }
            
            // Ajouter le dernier log
            if ($currentLog) {
                $logs[] = $currentLog;
            }
            
            // Filtrer les logs
            $logs = collect($logs)->filter(function ($log) use ($filters) {
                // Filtre par niveau
                if ($filters['level'] !== 'all' && $log['level'] !== $filters['level']) {
                    return false;
                }
                
                // Filtre par date
                if ($filters['date'] && $log['timestamp']->format('Y-m-d') !== $filters['date']) {
                    return false;
                }
                
                
                return true;
            })->sortByDesc('timestamp')->take(100); // Limiter à 100 entrées pour la performance
        }

        // Statistiques des logs
        $logStats = $this->getLogStatistics();

        return view('admin.logs.index', compact('logs', 'filters', 'logStats', 'totalLines'));
    }

    private function getLogStatistics(): array
    {
        $logFile = storage_path('logs/laravel.log');
        $stats = [
            'total' => 0,
            'today' => 0,
            'by_level' => [
                'error' => 0,
                'warning' => 0,
                'info' => 0,
                'debug' => 0,
            ],
            'recent_errors' => []
        ];

        if (!File::exists($logFile)) {
            return $stats;
        }

        $content = File::get($logFile);
        $lines = explode("\n", $content);
        $today = now()->format('Y-m-d');
        
        foreach ($lines as $line) {
            if (preg_match('/^\[([\d-]+\s[\d:]+)\]\s(\w+\.(\w+)):\s(.+)/', $line, $matches)) {
                $stats['total']++;
                
                $date = Carbon::parse($matches[1]);
                $level = strtolower($matches[3]);
                
                // Compter par niveau
                if (isset($stats['by_level'][$level])) {
                    $stats['by_level'][$level]++;
                }
                
                // Compter aujourd'hui
                if ($date->format('Y-m-d') === $today) {
                    $stats['today']++;
                }
                
                // Collecter les erreurs récentes
                if ($level === 'error' && $date->isAfter(now()->subHours(24))) {
                    $stats['recent_errors'][] = [
                        'timestamp' => $date,
                        'message' => substr($matches[4], 0, 100) . '...'
                    ];
                }
            }
        }
        
        // Limiter les erreurs récentes
        $stats['recent_errors'] = array_slice($stats['recent_errors'], -5);

        return $stats;
    }

    /**
     * Clear all log files
     */
    public function clear()
    {
        try {
            $logFile = storage_path('logs/laravel.log');
            
            if (File::exists($logFile)) {
                File::put($logFile, '');
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Logs vidés avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du vidage des logs: ' . $e->getMessage()
            ], 500);
        }
    }
}