<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class VideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'original_filename' => $this->original_filename,
            'original_path' => $this->original_path,
            'original_file_path' => $this->getOriginalFilePath(),
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->formatFileSize($this->file_size),
            'mime_type' => $this->mime_type,
            'duration' => $this->duration,
            'duration_formatted' => $this->formatDuration($this->duration),
            'resolution' => $this->width && $this->height 
                ? "{$this->width}x{$this->height}" 
                : null,
            'status' => $this->status,
            'hls_path' => $this->hls_path,
            'hls_url' => $this->getHlsUrl(),
            'hls_file_path' => $this->getHlsFilePath(),
            'error_message' => $this->when($this->status === 'failed', $this->error_message),
            'metadata' => $this->metadata,
            'transcoding_started_at' => $this->transcoding_started_at?->toIso8601String(),
            'transcoding_completed_at' => $this->transcoding_completed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    protected function formatDuration(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%02d:%02d', $minutes, $secs);
    }

    protected function getOriginalFilePath(): ?string
    {
        if (!$this->original_path) {
            return null;
        }

        try {
            return Storage::disk($this->original_disk)->path($this->original_path);
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getHlsFilePath(): ?string
    {
        if (!$this->hls_path) {
            return null;
        }

        try {
            // HLS files are stored in the local disk
            return Storage::disk('local')->path($this->hls_path);
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getHlsUrl(): ?string
    {
        // If hls_url is already set (from database), return it
        if ($this->hls_url) {
            return $this->hls_url;
        }

        // Otherwise, generate the expected URL based on the video UUID
        // This allows clients to know the URL even before transcoding completes
        return url("/api/v1/videos/{$this->uuid}/hls/master.m3u8");
    }
}

