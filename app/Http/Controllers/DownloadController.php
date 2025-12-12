<?php

namespace App\Http\Controllers;

use App\Models\TranscodeJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DownloadController extends Controller
{
    public function download(Request $request, string $projectKey, string $videoId, string $quality)
    {
        $project = $request->attributes->get('project');

        // Verify project owns this video
        if ($project->project_key !== $projectKey) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $job = TranscodeJob::where('project_key', $projectKey)
            ->where('video_id', $videoId)
            ->where('status', 'completed')
            ->first();

        if (!$job) {
            return response()->json(['error' => 'Video not ready or not found'], 404);
        }

        $outputPaths = $job->output_paths;
        
        if (!isset($outputPaths[$quality])) {
            return response()->json(['error' => 'Quality not available'], 404);
        }

        $filePath = storage_path("app/{$outputPaths[$quality]}");

        if (!file_exists($filePath)) {
            return response()->json(['error' => 'File missing on server'], 404);
        }

        // Increment download count
        $job->increment('download_count');

        // Stream file download
        return response()->download(
            $filePath,
            "{$projectKey}_{$videoId}_{$quality}.mp4",
            ['Content-Type' => 'video/mp4']
        );
    }
}
