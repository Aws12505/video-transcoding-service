<?php

namespace App\Jobs;

use App\Models\TranscodeJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use FFMpeg\Format\Video\X264;

class TranscodeVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 7200; // 2 hours
    public $tries = 1;

    public function __construct(
        public TranscodeJob $transcodeJob,  // Changed from $job
        public string $videoUrl
    ) {
        $this->onQueue('transcoding');
    }

    public function handle(): void
    {
        $this->transcodeJob->update(['status' => 'processing']);

        try {
            $uniqueId = $this->transcodeJob->getUniqueIdentifier();
            
            // Download original
            $tempInput = "temp/{$uniqueId}_original.mp4";
            Log::info("Starting download for {$uniqueId}");
            $this->downloadVideo($tempInput);
            
            // Get quality settings
            $qualitySettings = config('transcoding.quality_settings');
            $outputPaths = [];
            $downloadUrls = [];

            // Transcode each quality
            foreach ($this->transcodeJob->qualities_requested as $quality) {
                if (!isset($qualitySettings[$quality])) {
                    continue;
                }

                Log::info("Transcoding {$uniqueId} to {$quality}");
                
                $settings = $qualitySettings[$quality];
                $outputPath = "transcoded/{$this->transcodeJob->project_key}/{$this->transcodeJob->video_id}/{$quality}.mp4";

                // Create output directory
                $outputDir = storage_path("app/transcoded/{$this->transcodeJob->project_key}/{$this->transcodeJob->video_id}");
                if (!is_dir($outputDir)) {
                    mkdir($outputDir, 0755, true);
                }

                // FFmpeg transcode
                $format = new X264();
                $format->setKiloBitrate((int) rtrim($settings['bitrate'], 'k'));
                $format->setAudioKiloBitrate(128);

                FFMpeg::fromDisk('local')
                    ->open($tempInput)
                    ->export()
                    ->toDisk('local')
                    ->inFormat($format)
                    ->resize($settings['width'], $settings['height'])
                    ->save($outputPath);

                $outputPaths[$quality] = $outputPath;
                $downloadUrls[$quality] = url("/api/download/{$this->transcodeJob->project_key}/{$this->transcodeJob->video_id}/{$quality}");
            }

            // Cleanup temp file
            Storage::disk('local')->delete($tempInput);

            // Update job status
            $this->transcodeJob->update([
                'status' => 'completed',
                'output_paths' => $outputPaths,
                'completed_at' => now(),
            ]);

            Log::info("Transcoding completed for {$uniqueId}");

            // Send webhook
            $this->sendWebhook($downloadUrls);

        } catch (\Exception $e) {
            Log::error("Transcoding failed for {$this->transcodeJob->getUniqueIdentifier()}: " . $e->getMessage());
            
            $this->transcodeJob->update(['status' => 'failed']);
            
            $this->notifyFailure($e->getMessage());
            
            throw $e;
        }
    }

    private function downloadVideo(string $outputPath): void
    {
        $response = Http::timeout(3600)
            ->withOptions(['stream' => true])
            ->get($this->videoUrl);

        if (!$response->successful()) {
            throw new \Exception("Failed to download video: HTTP {$response->status()}");
        }

        $stream = $response->toPsrResponse()->getBody();
        $localPath = storage_path("app/{$outputPath}");
        
        $directory = dirname($localPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $handle = fopen($localPath, 'w');
        
        while (!$stream->eof()) {
            fwrite($handle, $stream->read(1024 * 1024)); // 1MB chunks
        }
        
        fclose($handle);
    }

    private function sendWebhook(array $downloadUrls): void
    {
        Http::timeout(60)
            ->retry(3, 1000)
            ->post($this->transcodeJob->callback_url, [
                'project_key' => $this->transcodeJob->project_key,
                'video_id' => $this->transcodeJob->video_id,
                'status' => 'completed',
                'download_urls' => $downloadUrls,
                'qualities' => $this->transcodeJob->qualities_requested,
            ]);
    }

    private function notifyFailure(string $error): void
    {
        Http::timeout(30)->post($this->transcodeJob->callback_url, [
            'project_key' => $this->transcodeJob->project_key,
            'video_id' => $this->transcodeJob->video_id,
            'status' => 'failed',
            'error' => $error,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->transcodeJob->update(['status' => 'failed']);
        $this->notifyFailure($exception->getMessage());
    }
}
