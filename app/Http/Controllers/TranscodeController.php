<?php

namespace App\Http\Controllers;

use App\Jobs\TranscodeVideo;
use App\Models\TranscodeJob;
use Illuminate\Http\Request;

class TranscodeController extends Controller
{
    public function create(Request $request)
    {
        $project = $request->attributes->get('project');

        $validated = $request->validate([
            'video_id' => 'required|string|max:255',
            'video_url' => 'required|url',
            'callback_url' => 'required|url',
            'qualities' => 'required|array|min:1',
            'qualities.*' => 'in:1080p,720p,480p,360p,240p',
        ]);

        // Check if job already exists
        $existingJob = TranscodeJob::where('project_key', $project->project_key)
            ->where('video_id', $validated['video_id'])
            ->first();

        if ($existingJob) {
            return response()->json([
                'error' => 'Job already exists',
                'status' => $existingJob->status,
                'job_id' => $existingJob->id,
            ], 409);
        }

        // Create transcode job
        $job = TranscodeJob::create([
            'project_key' => $project->project_key,
            'video_id' => $validated['video_id'],
            'qualities_requested' => $validated['qualities'],
            'status' => 'pending',
            'callback_url' => $validated['callback_url'],
        ]);

        // Dispatch to queue
        TranscodeVideo::dispatch($job, $validated['video_url']);

        return response()->json([
            'success' => true,
            'job_id' => $job->id,
            'project_key' => $project->project_key,
            'video_id' => $validated['video_id'],
            'status' => 'queued',
        ], 202);
    }

    public function status(Request $request, string $projectKey, string $videoId)
    {
        $project = $request->attributes->get('project');

        // Verify ownership
        if ($project->project_key !== $projectKey) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $job = TranscodeJob::where('project_key', $projectKey)
            ->where('video_id', $videoId)
            ->first();

        if (!$job) {
            return response()->json(['error' => 'Job not found'], 404);
        }

        return response()->json([
            'job_id' => $job->id,
            'project_key' => $job->project_key,
            'video_id' => $job->video_id,
            'status' => $job->status,
            'qualities_requested' => $job->qualities_requested,
            'completed_at' => $job->completed_at,
        ]);
    }
}
