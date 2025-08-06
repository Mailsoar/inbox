<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QueueStatusController extends Controller
{
    /**
     * Show queue status
     */
    public function index()
    {
        // Get pending jobs
        $pendingJobs = DB::table('jobs')
            ->select('queue', DB::raw('COUNT(*) as count'))
            ->groupBy('queue')
            ->get();
            
        // Get failed jobs
        $failedJobs = DB::table('failed_jobs')
            ->select('queue', DB::raw('COUNT(*) as count'))
            ->groupBy('queue')
            ->get();
            
        // Get recent jobs
        $recentJobs = DB::table('jobs')
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);
                return [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'attempts' => $job->attempts,
                    'created_at' => \Carbon\Carbon::createFromTimestamp($job->created_at),
                    'available_at' => \Carbon\Carbon::createFromTimestamp($job->available_at),
                    'job_name' => $payload['displayName'] ?? 'Unknown',
                    'test_id' => $this->extractTestId($payload)
                ];
            });
            
        // Get recent failed jobs
        $recentFailedJobs = DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);
                return [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'failed_at' => $job->failed_at,
                    'exception' => substr($job->exception, 0, 200),
                    'job_name' => $payload['displayName'] ?? 'Unknown',
                    'test_id' => $this->extractTestId($payload)
                ];
            });
            
        return view('admin.queue.index', compact(
            'pendingJobs',
            'failedJobs',
            'recentJobs',
            'recentFailedJobs'
        ));
    }
    
    /**
     * Process queue
     */
    public function process()
    {
        // Start processing email-addresses queue for 30 seconds
        $command = "/usr/local/bin/php " . base_path('artisan') . " emails:process-addresses --timeout=30";
        
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen("start /B " . $command, "r"));
        } else {
            exec($command . " > /dev/null 2>&1 &");
        }
        
        return redirect()->route('admin.queue.index')
            ->with('success', 'Traitement de la queue lancé pour 30 secondes.');
    }
    
    /**
     * Retry failed job
     */
    public function retry($id)
    {
        $failedJob = DB::table('failed_jobs')->where('id', $id)->first();
        
        if (!$failedJob) {
            return redirect()->route('admin.queue.index')
                ->with('error', 'Job non trouvé.');
        }
        
        // Retry the job
        $command = "/usr/local/bin/php " . base_path('artisan') . " queue:retry {$id}";
        exec($command);
        
        return redirect()->route('admin.queue.index')
            ->with('success', 'Job relancé.');
    }
    
    /**
     * Clear all failed jobs
     */
    public function clearFailed()
    {
        DB::table('failed_jobs')->truncate();
        
        return redirect()->route('admin.queue.index')
            ->with('success', 'Tous les jobs échoués ont été supprimés.');
    }
    
    /**
     * Cancel/delete a pending job
     */
    public function cancel($id)
    {
        $job = DB::table('jobs')->where('id', $id)->first();
        
        if (!$job) {
            return redirect()->route('admin.queue.index')
                ->with('error', 'Job non trouvé.');
        }
        
        // Delete the job
        DB::table('jobs')->where('id', $id)->delete();
        
        // Log the cancellation
        \Log::info('Job cancelled from admin', [
            'job_id' => $id,
            'queue' => $job->queue,
            'admin' => auth('admin')->user()->email ?? 'unknown'
        ]);
        
        return redirect()->route('admin.queue.index')
            ->with('success', 'Job annulé avec succès.');
    }
    
    /**
     * Clear all pending jobs in a specific queue
     */
    public function clearQueue($queue)
    {
        $count = DB::table('jobs')->where('queue', $queue)->count();
        
        if ($count > 0) {
            DB::table('jobs')->where('queue', $queue)->delete();
            
            \Log::info('Queue cleared from admin', [
                'queue' => $queue,
                'jobs_deleted' => $count,
                'admin' => auth('admin')->user()->email ?? 'unknown'
            ]);
            
            return redirect()->route('admin.queue.index')
                ->with('success', "Queue '{$queue}' vidée ({$count} jobs supprimés).");
        }
        
        return redirect()->route('admin.queue.index')
            ->with('info', 'Aucun job dans cette queue.');
    }
    
    /**
     * Delete a failed job
     */
    public function deleteFailed($id)
    {
        $job = DB::table('failed_jobs')->where('id', $id)->first();
        
        if (!$job) {
            return redirect()->route('admin.queue.index')
                ->with('error', 'Job échoué non trouvé.');
        }
        
        // Delete the failed job
        DB::table('failed_jobs')->where('id', $id)->delete();
        
        \Log::info('Failed job deleted from admin', [
            'job_id' => $id,
            'queue' => $job->queue,
            'admin' => auth('admin')->user()->email ?? 'unknown'
        ]);
        
        return redirect()->route('admin.queue.index')
            ->with('success', 'Job échoué supprimé.');
    }
    
    /**
     * Extract test ID or email account from payload
     */
    private function extractTestId($payload)
    {
        if (!isset($payload['data']['command'])) {
            return null;
        }
        
        // Try to unserialize the command
        try {
            $command = unserialize($payload['data']['command']);
            if (is_object($command)) {
                // Use reflection to access protected property
                $reflection = new \ReflectionClass($command);
                
                // For ProcessEmailAddressJob
                if ($reflection->hasProperty('emailAccount')) {
                    $property = $reflection->getProperty('emailAccount');
                    $property->setAccessible(true);
                    $emailAccount = $property->getValue($command);
                    if ($emailAccount && is_object($emailAccount)) {
                        // Return email account info instead of test ID
                        return $emailAccount->email ?? 'Account #' . ($emailAccount->id ?? '?');
                    }
                }
                
                // Legacy: Check if the property exists
                if ($reflection->hasProperty('uniqueId')) {
                    $property = $reflection->getProperty('uniqueId');
                    $property->setAccessible(true);
                    return $property->getValue($command);
                }
                
                // Legacy: check for test property
                if ($reflection->hasProperty('test')) {
                    $testProperty = $reflection->getProperty('test');
                    $testProperty->setAccessible(true);
                    $test = $testProperty->getValue($command);
                    if ($test && is_object($test) && property_exists($test, 'unique_id')) {
                        return $test->unique_id;
                    }
                }
            }
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::debug('Failed to extract info from job payload', [
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }
}