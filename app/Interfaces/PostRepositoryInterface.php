<?php

namespace App\Interfaces;

use App\Http\Resources\PostResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;


interface PostRepositoryInterface
{
    /**
     * Get paginated posts wrapped in PostResource.
     */
    public function getAll(int $perPage = 10): AnonymousResourceCollection;

    /**
     * Find a single post by ID and return as PostResource.
     */
    public function getById(int $id): PostResource;

    /**
     * Create a new post and return as PostResource.
     */
    public function create(array $data): PostResource;

    /**
     * Update an existing post and return as PostResource.
     */
    public function update(int $id, array $data): PostResource;

    /**
     * Delete a post by ID.
     */
    public function delete(int $id): bool;
}


?>