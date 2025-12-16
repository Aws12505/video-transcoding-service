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
        public TranscodeJob $transcodeJob,
        public string $videoUrl
    ) {
        $this->onQueue('transcoding');
    }

    public function handle(): void
    {
        $this->transcodeJob->update(['status' => 'processing']);

        try {
            $uniqueId = $this->transcodeJob->getUniqueIdentifier();
            
            // Download original - public disk
            $tempInput = "temp/{$uniqueId}_original.mp4";
            Log::info("Starting download for {$uniqueId}");
            $this->downloadVideo($tempInput);
            
            // Verify file exists
            if (!Storage::disk('public')->exists($tempInput)) {
                throw new \Exception("Downloaded file not found at: {$tempInput}");
            }
            
            Log::info("File confirmed at: " . Storage::disk('public')->path($tempInput));
            
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

                // FFmpeg transcode
                $format = new X264();
                $format->setKiloBitrate((int) rtrim($settings['bitrate'], 'k'));
                $format->setAudioKiloBitrate(128);

                FFMpeg::fromDisk('public')
                    ->open($tempInput)
                    ->export()
                    ->toDisk('public')
                    ->inFormat($format)
                    ->resize($settings['width'], $settings['height'])
                    ->save($outputPath);

                $outputPaths[$quality] = $outputPath;
                $downloadUrls[$quality] = url("/api/download/{$this->transcodeJob->project_key}/{$this->transcodeJob->video_id}/{$quality}");
                
                Log::info("Completed {$quality} transcoding");
            }

            // Cleanup temp file
            Storage::disk('public')->delete($tempInput);

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
        
        // Create temp file and write stream to it
        $tempFile = tmpfile();
        $metaData = stream_get_meta_data($tempFile);
        $tempPath = $metaData['uri'];
        
        // Write stream to temp file
        while (!$stream->eof()) {
            fwrite($tempFile, $stream->read(1024 * 1024)); // 1MB chunks
        }
        
        // Rewind and put to storage
        rewind($tempFile);
        Storage::disk('public')->put($outputPath, $tempFile);
        
        fclose($tempFile);
        
        Log::info("Downloaded video to: {$outputPath}");
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
