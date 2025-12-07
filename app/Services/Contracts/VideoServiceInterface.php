<?php

namespace App\Services\Contracts;

use App\DTOs\VideoUploadDTO;
use App\Models\Video;

interface VideoServiceInterface
{
    /**
     * Upload and store a video
     */
    public function upload(VideoUploadDTO $dto): Video;

    /**
     * Get video by UUID
     */
    public function getByUuid(string $uuid): ?Video;

    /**
     * Get video by ID
     */
    public function getById(int $id): ?Video;

    /**
     * Delete a video and its related files
     */
    public function delete(Video $video): bool;
}

