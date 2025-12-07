<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\Contracts\TranscodingServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TranscodeVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 3600; // 1 hour

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    public function __construct(
        public Video $video
    ) {
        $this->queue = 'transcoding';
    }

    public function handle(TranscodingServiceInterface $transcodingService): void
    {
        Log::info('Starting video transcoding job', [
            'video_id' => $this->video->id,
            'uuid' => $this->video->uuid,
        ]);

        // Mark video as processing
        $this->video->markAsProcessing();

        // Perform transcoding
        $result = $transcodingService->transcodeToHLS($this->video);

        if ($result->success) {
            // Update video metadata if available
            $updateData = [];
            if ($result->duration !== null) {
                $updateData['duration'] = $result->duration;
            }
            if ($result->width !== null) {
                $updateData['width'] = $result->width;
            }
            if ($result->height !== null) {
                $updateData['height'] = $result->height;
            }
            
            if (!empty($updateData)) {
                $this->video->update($updateData);
            }

            $this->video->markAsCompleted(
                $result->hlsPath,
                $result->hlsUrl,
                $result->s3Bucket,
                $result->s3Key
            );

            Log::info('Video transcoding completed successfully', [
                'video_id' => $this->video->id,
                'hls_url' => $result->hlsUrl,
            ]);
        } else {
            $this->video->markAsFailed($result->errorMessage);
            
            Log::error('Video transcoding failed', [
                'video_id' => $this->video->id,
                'error' => $result->errorMessage,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Video transcoding job failed', [
            'video_id' => $this->video->id,
            'exception' => $exception->getMessage(),
        ]);

        $this->video->markAsFailed($exception->getMessage());
    }
}

