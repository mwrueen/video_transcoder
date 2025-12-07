<?php

namespace App\Providers;

use App\Repositories\Contracts\VideoRepositoryInterface;
use App\Repositories\VideoRepository;
use App\Services\Contracts\TranscodingServiceInterface;
use App\Services\Contracts\VideoServiceInterface;
use App\Services\FFmpegTranscodingService;
use App\Services\VideoService;
use Illuminate\Support\ServiceProvider;

class TranscoderServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     */
    public array $bindings = [
        VideoRepositoryInterface::class => VideoRepository::class,
        VideoServiceInterface::class => VideoService::class,
        TranscodingServiceInterface::class => FFmpegTranscodingService::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/transcoder.php',
            'transcoder'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/transcoder.php' => config_path('transcoder.php'),
        ], 'transcoder-config');
    }
}

