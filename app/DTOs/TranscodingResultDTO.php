<?php

namespace App\DTOs;

readonly class TranscodingResultDTO
{
    public function __construct(
        public bool $success,
        public ?string $hlsPath = null,
        public ?string $hlsUrl = null,
        public ?string $s3Bucket = null,
        public ?string $s3Key = null,
        public ?string $errorMessage = null,
        public ?int $duration = null,
        public ?int $width = null,
        public ?int $height = null,
    ) {}

    public static function success(
        string $hlsPath,
        string $hlsUrl,
        ?string $s3Bucket = null,
        ?string $s3Key = null,
        ?int $duration = null,
        ?int $width = null,
        ?int $height = null,
    ): self {
        return new self(
            success: true,
            hlsPath: $hlsPath,
            hlsUrl: $hlsUrl,
            s3Bucket: $s3Bucket,
            s3Key: $s3Key,
            duration: $duration,
            width: $width,
            height: $height,
        );
    }

    public static function failure(string $errorMessage): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
        );
    }
}

