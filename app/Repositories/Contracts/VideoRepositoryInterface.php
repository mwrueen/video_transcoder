<?php

namespace App\Repositories\Contracts;

use App\Models\Video;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface VideoRepositoryInterface
{
    public function create(array $data): Video;

    public function findById(int $id): ?Video;

    public function findByUuid(string $uuid): ?Video;

    public function update(Video $video, array $data): Video;

    public function delete(Video $video): bool;

    public function all(): Collection;

    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function findByStatus(string $status): Collection;

    public function getPendingVideos(): Collection;
}

