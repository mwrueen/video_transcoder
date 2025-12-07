<?php

namespace App\Services;

use App\DTOs\TranscodingResultDTO;
use App\Models\Video;
use App\Services\Contracts\TranscodingServiceInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class FFmpegTranscodingService implements TranscodingServiceInterface
{
    protected string $ffmpegPath;
    protected string $ffprobePath;
    protected int $hlsSegmentDuration;
    protected string $outputDisk;

    public function __construct()
    {
        $this->ffmpegPath = config('transcoder.ffmpeg_path', '/usr/bin/ffmpeg');
        $this->ffprobePath = config('transcoder.ffprobe_path', '/usr/bin/ffprobe');
        $this->hlsSegmentDuration = config('transcoder.hls_segment_duration', 10);
        $this->outputDisk = config('transcoder.output_disk', 's3');
    }

    public function transcodeToHLS(Video $video): TranscodingResultDTO
    {
        try {
            $inputPath = Storage::disk($video->original_disk)->path($video->original_path);
            
            // Create output directory for HLS files
            $outputDir = storage_path("app/hls/{$video->uuid}");
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $playlistPath = "{$outputDir}/playlist.m3u8";

            // Get video metadata first
            $metadata = $this->getVideoMetadata($inputPath);

            // Build FFmpeg command for HLS transcoding with multiple quality variants
            $command = $this->buildFFmpegCommand($inputPath, $outputDir);

            Log::info('Starting FFmpeg transcoding', [
                'video_id' => $video->id,
                'command' => implode(' ', $command),
            ]);

            $process = new Process($command);
            $process->setTimeout(3600); // 1 hour timeout
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // Upload to S3
            $s3Result = $this->uploadToS3($video->uuid, $outputDir);

            Log::info('FFmpeg transcoding completed', [
                'video_id' => $video->id,
                'hls_path' => $s3Result['key'],
            ]);

            return TranscodingResultDTO::success(
                hlsPath: $s3Result['key'],
                hlsUrl: $s3Result['url'],
                s3Bucket: $s3Result['bucket'],
                s3Key: $s3Result['key'],
                duration: $metadata['duration'] ?? null,
                width: $metadata['width'] ?? null,
                height: $metadata['height'] ?? null,
            );

        } catch (\Exception $e) {
            Log::error('FFmpeg transcoding failed', [
                'video_id' => $video->id,
                'error' => $e->getMessage(),
            ]);

            return TranscodingResultDTO::failure($e->getMessage());
        }
    }

    protected function buildFFmpegCommand(string $inputPath, string $outputDir): array
    {
        return [
            $this->ffmpegPath,
            '-i', $inputPath,
            '-preset', 'fast',
            '-g', '48',
            '-sc_threshold', '0',
            // 720p variant
            '-map', '0:v:0', '-map', '0:a:0',
            '-c:v:0', 'libx264', '-b:v:0', '2800k',
            '-c:a:0', 'aac', '-b:a:0', '128k',
            '-s:v:0', '1280x720',
            // 480p variant  
            '-map', '0:v:0', '-map', '0:a:0',
            '-c:v:1', 'libx264', '-b:v:1', '1400k',
            '-c:a:1', 'aac', '-b:a:1', '128k',
            '-s:v:1', '854x480',
            // 360p variant
            '-map', '0:v:0', '-map', '0:a:0',
            '-c:v:2', 'libx264', '-b:v:2', '800k',
            '-c:a:2', 'aac', '-b:a:2', '96k',
            '-s:v:2', '640x360',
            // HLS settings
            '-f', 'hls',
            '-hls_time', (string) $this->hlsSegmentDuration,
            '-hls_playlist_type', 'vod',
            '-hls_flags', 'independent_segments',
            '-hls_segment_type', 'mpegts',
            '-hls_segment_filename', "{$outputDir}/segment_%v_%03d.ts",
            '-master_pl_name', 'master.m3u8',
            '-var_stream_map', 'v:0,a:0 v:1,a:1 v:2,a:2',
            "{$outputDir}/stream_%v.m3u8",
        ];
    }

    public function getVideoMetadata(string $inputPath): array
    {
        $command = [
            $this->ffprobePath,
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $inputPath,
        ];

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            return [];
        }

        $output = json_decode($process->getOutput(), true);
        
        $videoStream = collect($output['streams'] ?? [])
            ->firstWhere('codec_type', 'video');

        return [
            'duration' => isset($output['format']['duration']) 
                ? (int) round((float) $output['format']['duration']) 
                : null,
            'width' => $videoStream['width'] ?? null,
            'height' => $videoStream['height'] ?? null,
            'codec' => $videoStream['codec_name'] ?? null,
            'bitrate' => $output['format']['bit_rate'] ?? null,
        ];
    }

    protected function uploadToS3(string $videoUuid, string $localDir): array
    {
        $bucket = config('filesystems.disks.s3.bucket');
        $s3KeyPrefix = "hls/{$videoUuid}";
        
        $files = glob("{$localDir}/*");
        
        foreach ($files as $file) {
            $filename = basename($file);
            $s3Key = "{$s3KeyPrefix}/{$filename}";
            
            Storage::disk('s3')->put($s3Key, file_get_contents($file), [
                'visibility' => 'public',
            ]);
        }

        $masterPlaylistKey = "{$s3KeyPrefix}/master.m3u8";
        $url = Storage::disk('s3')->url($masterPlaylistKey);

        // Clean up local files after upload
        array_map('unlink', $files);
        rmdir($localDir);

        return [
            'bucket' => $bucket,
            'key' => $masterPlaylistKey,
            'url' => $url,
        ];
    }

    public function isFFmpegAvailable(): bool
    {
        $process = new Process([$this->ffmpegPath, '-version']);
        $process->run();
        
        return $process->isSuccessful();
    }
}

