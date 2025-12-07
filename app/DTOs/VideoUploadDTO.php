<?php

namespace App\DTOs;

use Illuminate\Http\UploadedFile;

readonly class VideoUploadDTO
{
    public function __construct(
        public string $title,
        public UploadedFile $file,
        public ?array $metadata = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            title: $data['title'],
            file: $data['video'],
            metadata: $data['metadata'] ?? null,
        );
    }

    public function getOriginalFilename(): string
    {
        return $this->file->getClientOriginalName();
    }

    public function getMimeType(): string
    {
        return $this->file->getMimeType();
    }

    public function getFileSize(): int
    {
        return $this->file->getSize();
    }
}

