<?php

namespace App\Console\Commands;

use App\Models\TranscodeJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupTranscodedFiles extends Command
{
    protected $signature = 'transcode:cleanup';
    protected $description = 'Cleanup old transcoded files after downloads';

    public function handle()
    {
        $hoursAgo = config('transcoding.cleanup_after_hours');
        
        $jobs = TranscodeJob::where('status', 'completed')
            ->where('completed_at', '<', now()->subHours($hoursAgo))
            ->where('download_count', '>=', function($query) {
                // Downloads >= number of requested qualities (all downloaded)
                return $query->selectRaw('JSON_LENGTH(qualities_requested)');
            })
            ->get();

        $cleaned = 0;

        foreach ($jobs as $job) {
            // Delete all transcoded files
            if ($job->output_paths) {
                foreach ($job->output_paths as $path) {
                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }
            
            // Delete directory
            $directory = "transcoded/{$job->project_key}/{$job->video_id}";
            if (Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->deleteDirectory($directory);
            }
            
            // Delete job record
            $job->delete();
            
            $cleaned++;
            $this->info("Cleaned: {$job->project_key}/{$job->video_id}");
        }

        $this->info("Cleanup complete: {$cleaned} jobs cleaned");
        
        return 0;
    }
}
