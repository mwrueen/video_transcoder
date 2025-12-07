<?php

namespace App\Services\Contracts;

use App\DTOs\TranscodingResultDTO;
use App\Models\Video;

interface TranscodingServiceInterface
{
    /**
     * Transcode a video to HLS format
     */
    public function transcodeToHLS(Video $video): TranscodingResultDTO;

    /**
     * Get video metadata (duration, resolution, etc.)
     */
    public function getVideoMetadata(string $inputPath): array;

    /**
     * Check if FFmpeg is available
     */
    public function isFFmpegAvailable(): bool;
}

