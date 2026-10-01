<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Interfaces\PostRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    /**
     * Inject the PostRepositoryInterface via the constructor.
     */
    public function __construct(
        protected PostRepositoryInterface $postRepository
    ){}

    /**
     * GET /api/posts
     * Return a paginated list of posts.
     */
    public function index(): AnonymousResourceCollection
    {
        return $this->postRepository->getAll();
    }

    /**
     * GET /api/posts/{id}
     * Retrieve a single post.
     */
    public function show(int $id): JsonResponse
    {
        $post = $this->postRepository->getById($id);

        return response()->json([
            'data' => $post,
        ], 200);
    }
    
    /**
     * POST /api/posts
     * Validate payload with StorePostRequest, then create.
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->postRepository->create($request->validated());

        return response()->json([
            'message' => 'Post created successfully.',
            'data'    => $post,
        ], 201);
    }

    

    /**
     * PUT/PATCH /api/posts/{id}
     * Validate with UpdatePostRequest, then update.
     */
    public function update(UpdatePostRequest $request, int $id): JsonResponse
    {
        $post = $this->postRepository->update($id, $request->validated());

        return response()->json([
            'message' => 'Post updated successfully.',
            'data'    => $post,
        ],200);
    }

    /**
     * DELETE /api/posts/{id}
     * Delete post by ID.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->postRepository->delete($id);

        return response()->json([
            'message' => 'Post deleted successfully.',
        ], 200);
    }
}
