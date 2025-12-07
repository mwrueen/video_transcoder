<?php

namespace App\Http\Controllers\Api;

use App\DTOs\VideoUploadDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadVideoRequest;
use App\Http\Resources\VideoResource;
use App\Services\Contracts\VideoServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VideoController extends Controller
{
    public function __construct(
        protected VideoServiceInterface $videoService
    ) {}

    /**
     * Upload a new video for transcoding
     */
    public function upload(UploadVideoRequest $request): JsonResponse
    {
        $dto = VideoUploadDTO::fromRequest($request->validated());
        
        $video = $this->videoService->upload($dto);

        return response()->json([
            'success' => true,
            'message' => 'Video uploaded successfully. Transcoding has been queued.',
            'data' => new VideoResource($video),
        ], 201);
    }

    /**
     * Get video status and details by UUID
     */
    public function show(string $uuid): JsonResponse
    {
        $video = $this->videoService->getByUuid($uuid);

        if (!$video) {
            return response()->json([
                'success' => false,
                'message' => 'Video not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new VideoResource($video),
        ]);
    }

    /**
     * List all videos with pagination
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $videos = \App\Models\Video::orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => VideoResource::collection($videos),
            'meta' => [
                'current_page' => $videos->currentPage(),
                'last_page' => $videos->lastPage(),
                'per_page' => $videos->perPage(),
                'total' => $videos->total(),
            ],
        ]);
    }

    /**
     * Delete a video
     */
    public function destroy(string $uuid): JsonResponse
    {
        $video = $this->videoService->getByUuid($uuid);

        if (!$video) {
            return response()->json([
                'success' => false,
                'message' => 'Video not found',
            ], 404);
        }

        $this->videoService->delete($video);

        return response()->json([
            'success' => true,
            'message' => 'Video deleted successfully',
        ]);
    }

    /**
     * Retry transcoding for a failed video
     */
    public function retry(string $uuid): JsonResponse
    {
        $video = $this->videoService->getByUuid($uuid);

        if (!$video) {
            return response()->json([
                'success' => false,
                'message' => 'Video not found',
            ], 404);
        }

        if (!$video->isFailed()) {
            return response()->json([
                'success' => false,
                'message' => 'Only failed videos can be retried',
            ], 422);
        }

        // Reset status and dispatch new job
        $video->update([
            'status' => \App\Models\Video::STATUS_PENDING,
            'error_message' => null,
            'transcoding_started_at' => null,
            'transcoding_completed_at' => null,
        ]);

        \App\Jobs\TranscodeVideoJob::dispatch($video);

        return response()->json([
            'success' => true,
            'message' => 'Transcoding job has been re-queued',
            'data' => new VideoResource($video->fresh()),
        ]);
    }
}

