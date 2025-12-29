<?php

namespace App\Services;

use App\DTOs\VideoUploadDTO;
use App\Jobs\TranscodeVideoJob;
use App\Models\Video;
use App\Repositories\Contracts\VideoRepositoryInterface;
use App\Services\Contracts\VideoServiceInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoService implements VideoServiceInterface
{
    public function __construct(
        protected VideoRepositoryInterface $videoRepository
    ) {}

    public function upload(VideoUploadDTO $dto): Video
    {
        // Generate unique filename
        $uuid = Str::uuid()->toString();
        $extension = $dto->file->getClientOriginalExtension();
        $storagePath = "videos/originals/{$uuid}.{$extension}";

        // Store the file locally first
        Storage::disk('local')->putFileAs(
            'videos/originals',
            $dto->file,
            "{$uuid}.{$extension}"
        );

        // Create video record
        $video = $this->videoRepository->create([
            'uuid' => $uuid,
            'original_filename' => $dto->getOriginalFilename(),
            'original_path' => $storagePath,
            'original_disk' => 'local',
            'file_size' => $dto->getFileSize(),
            'mime_type' => $dto->getMimeType(),
            'status' => Video::STATUS_PENDING,
            'metadata' => $dto->metadata,
        ]);

        // Dispatch transcoding job
        TranscodeVideoJob::dispatch($video);

        return $video;
    }

    public function getByUuid(string $uuid): ?Video
    {
        return $this->videoRepository->findByUuid($uuid);
    }

    public function getById(int $id): ?Video
    {
        return $this->videoRepository->findById($id);
    }

    public function delete(Video $video): bool
    {
        // Delete original file
        if ($video->original_path && Storage::disk($video->original_disk)->exists($video->original_path)) {
            Storage::disk($video->original_disk)->delete($video->original_path);
        }

        // Delete HLS files from S3 if they exist
        if ($video->s3_key) {
            $directory = dirname($video->s3_key);
            Storage::disk('s3')->deleteDirectory($directory);
        }

        return $this->videoRepository->delete($video);
    }
}

