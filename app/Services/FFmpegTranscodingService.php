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

            // Save files locally using Storage
            $localResult = $this->saveLocally($video->uuid, $outputDir);

            // Fix playlist URLs to use API routes
            $this->fixPlaylistUrls($video->uuid, $localResult['path']);

            Log::info('FFmpeg transcoding completed', [
                'video_id' => $video->id,
                'hls_path' => $localResult['path'],
            ]);

            return TranscodingResultDTO::success(
                hlsPath: $localResult['path'],
                hlsUrl: $localResult['url'],
                s3Bucket: null,
                s3Key: null,
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
            // 1080p variant
            '-map', '0:v:0', '-map', '0:a:0',
            '-c:v:0', 'libx264',
            '-b:v:0', '5000k',
            '-maxrate:v:0', '5500k',
            '-bufsize:v:0', '10000k',
            '-c:a:0', 'aac',
            '-b:a:0', '192k',
            '-s:v:0', '1920x1080',
            '-r:v:0', '30',
            // 720p variant
            '-map', '0:v:0', '-map', '0:a:0',
            '-c:v:1', 'libx264',
            '-b:v:1', '2800k',
            '-maxrate:v:1', '3000k',
            '-bufsize:v:1', '5600k',
            '-c:a:1', 'aac',
            '-b:a:1', '128k',
            '-s:v:1', '1280x720',
            '-r:v:1', '30',
            // 480p variant  
            '-map', '0:v:0', '-map', '0:a:0',
            '-c:v:2', 'libx264',
            '-b:v:2', '1400k',
            '-maxrate:v:2', '1500k',
            '-bufsize:v:2', '2800k',
            '-c:a:2', 'aac',
            '-b:a:2', '128k',
            '-s:v:2', '854x480',
            '-r:v:2', '30',
            // 360p variant
            '-map', '0:v:0', '-map', '0:a:0',
            '-c:v:3', 'libx264',
            '-b:v:3', '800k',
            '-maxrate:v:3', '900k',
            '-bufsize:v:3', '1600k',
            '-c:a:3', 'aac',
            '-b:a:3', '96k',
            '-s:v:3', '640x360',
            '-r:v:3', '30',
            // 240p variant
            '-map', '0:v:0', '-map', '0:a:0',
            '-c:v:4', 'libx264',
            '-b:v:4', '400k',
            '-maxrate:v:4', '450k',
            '-bufsize:v:4', '800k',
            '-c:a:4', 'aac',
            '-b:a:4', '64k',
            '-s:v:4', '426x240',
            '-r:v:4', '30',
            // 144p variant
            '-map', '0:v:0', '-map', '0:a:0',
            '-c:v:5', 'libx264',
            '-b:v:5', '200k',
            '-maxrate:v:5', '250k',
            '-bufsize:v:5', '400k',
            '-c:a:5', 'aac',
            '-b:a:5', '64k',
            '-s:v:5', '256x144',
            '-r:v:5', '30',
            // HLS settings
            '-f', 'hls',
            '-hls_time', (string) $this->hlsSegmentDuration,
            '-hls_playlist_type', 'vod',
            '-hls_flags', 'independent_segments',
            '-hls_segment_type', 'mpegts',
            '-hls_segment_filename', "{$outputDir}/segment_%v_%03d.ts",
            '-master_pl_name', 'master.m3u8',
            '-var_stream_map', 'v:0,a:0 v:1,a:1 v:2,a:2 v:3,a:3 v:4,a:4 v:5,a:5',
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

    protected function saveLocally(string $videoUuid, string $localDir): array
    {
        $storagePath = "hls/{$videoUuid}";
        $files = glob("{$localDir}/*");
        
        // Ensure the storage directory exists using Storage facade
        Storage::disk('local')->makeDirectory($storagePath);
        
        // Copy all files to the storage directory using Storage
        foreach ($files as $file) {
            $filename = basename($file);
            $destinationPath = "{$storagePath}/{$filename}";
            Storage::disk('local')->put($destinationPath, file_get_contents($file));
        }

        $masterPlaylistPath = "{$storagePath}/master.m3u8";
        
        // Generate URL for the master playlist - using API route
        $url = url("/api/v1/videos/{$videoUuid}/hls/master.m3u8");

        // Clean up temporary directory
        array_map('unlink', $files);
        rmdir($localDir);

        return [
            'path' => $masterPlaylistPath,
            'url' => $url,
        ];
    }

    protected function fixPlaylistUrls(string $videoUuid, string $masterPlaylistPath): void
    {
        $baseUrl = url("/api/v1/videos/{$videoUuid}/hls");
        $storagePath = "hls/{$videoUuid}";
        
        // Fix master playlist - update stream playlist references
        $masterContent = Storage::disk('local')->get($masterPlaylistPath);
        // Replace relative stream playlist paths with full URLs
        $masterContent = preg_replace(
            '/(stream_\d+\.m3u8)/',
            "{$baseUrl}/$1",
            $masterContent
        );
        Storage::disk('local')->put($masterPlaylistPath, $masterContent);
        
        // Fix individual stream playlists - update segment references
        $streamPlaylists = Storage::disk('local')->files($storagePath);
        foreach ($streamPlaylists as $playlistPath) {
            $filename = basename($playlistPath);
            if (str_ends_with($filename, '.m3u8') && $filename !== 'master.m3u8') {
                $content = Storage::disk('local')->get($playlistPath);
                // Replace segment filenames with full URLs (only lines that are just filenames)
                $lines = explode("\n", $content);
                $fixedLines = [];
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    // If line is just a segment filename (not a comment or other directive)
                    if (preg_match('/^segment_\d+_\d+\.ts$/', $trimmed) && !str_starts_with($trimmed, '#')) {
                        $fixedLines[] = "{$baseUrl}/{$trimmed}";
                    } else {
                        $fixedLines[] = $line;
                    }
                }
                Storage::disk('local')->put($playlistPath, implode("\n", $fixedLines));
            }
        }
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

