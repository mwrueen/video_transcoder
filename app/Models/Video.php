<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'original_filename',
        'original_path',
        'original_disk',
        'file_size',
        'mime_type',
        'duration',
        'width',
        'height',
        'status',
        'hls_path',
        'hls_url',
        's3_bucket',
        's3_key',
        'error_message',
        'transcoding_started_at',
        'transcoding_completed_at',
        'metadata',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'duration' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'metadata' => 'array',
        'transcoding_started_at' => 'datetime',
        'transcoding_completed_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($video) {
            if (empty($video->uuid)) {
                $video->uuid = Str::uuid()->toString();
            }
        });
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'transcoding_started_at' => now(),
        ]);
    }

    public function markAsCompleted(string $hlsPath, string $hlsUrl, ?string $s3Bucket = null, ?string $s3Key = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'hls_path' => $hlsPath,
            'hls_url' => $hlsUrl,
            's3_bucket' => $s3Bucket,
            's3_key' => $s3Key,
            'transcoding_completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'transcoding_completed_at' => now(),
        ]);
    }
}
