<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FFmpeg Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the paths to FFmpeg and FFprobe binaries.
    |
    */

    'ffmpeg_path' => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
    'ffprobe_path' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),

    /*
    |--------------------------------------------------------------------------
    | HLS Configuration
    |--------------------------------------------------------------------------
    |
    | Configure HLS output settings.
    |
    */

    'hls_segment_duration' => env('HLS_SEGMENT_DURATION', 10),

    /*
    |--------------------------------------------------------------------------
    | Output Storage
    |--------------------------------------------------------------------------
    |
    | Configure where transcoded videos are stored.
    |
    */

    'output_disk' => env('TRANSCODER_OUTPUT_DISK', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Upload Settings
    |--------------------------------------------------------------------------
    |
    | Configure upload limitations.
    |
    */

    // Maximum upload size in kilobytes (default: 2GB = 2097152 KB)
    'max_upload_size' => env('TRANSCODER_MAX_UPLOAD_SIZE', 2097152),

    /*
    |--------------------------------------------------------------------------
    | Quality Presets
    |--------------------------------------------------------------------------
    |
    | Define quality presets for transcoding.
    | Each preset contains resolution, video bitrate, and audio bitrate.
    |
    */

    'quality_presets' => [
        '1080p' => [
            'resolution' => '1920x1080',
            'video_bitrate' => '5000k',
            'audio_bitrate' => '192k',
        ],
        '720p' => [
            'resolution' => '1280x720',
            'video_bitrate' => '2800k',
            'audio_bitrate' => '128k',
        ],
        '480p' => [
            'resolution' => '854x480',
            'video_bitrate' => '1400k',
            'audio_bitrate' => '128k',
        ],
        '360p' => [
            'resolution' => '640x360',
            'video_bitrate' => '800k',
            'audio_bitrate' => '96k',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Enabled Quality Levels
    |--------------------------------------------------------------------------
    |
    | Specify which quality levels to include in HLS output.
    |
    */

    'enabled_qualities' => ['720p', '480p', '360p'],
];

