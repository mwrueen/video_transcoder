<?php

namespace App\Repositories;

use App\Models\Video;
use App\Repositories\Contracts\VideoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class VideoRepository implements VideoRepositoryInterface
{
    public function __construct(
        protected Video $model
    ) {}

    public function create(array $data): Video
    {
        return $this->model->create($data);
    }

    public function findById(int $id): ?Video
    {
        return $this->model->find($id);
    }

    public function findByUuid(string $uuid): ?Video
    {
        return $this->model->where('uuid', $uuid)->first();
    }

    public function update(Video $video, array $data): Video
    {
        $video->update($data);
        return $video->fresh();
    }

    public function delete(Video $video): bool
    {
        return $video->delete();
    }

    public function all(): Collection
    {
        return $this->model->orderBy('created_at', 'desc')->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function findByStatus(string $status): Collection
    {
        return $this->model->where('status', $status)->get();
    }

    public function getPendingVideos(): Collection
    {
        return $this->model->where('status', Video::STATUS_PENDING)
            ->orderBy('created_at', 'asc')
            ->get();
    }
}

