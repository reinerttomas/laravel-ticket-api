<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

final readonly class UserController
{
    public function index(): ResourceCollection
    {
        return User::query()->paginate()->toResourceCollection();
    }

    public function show(User $user): JsonResource
    {
        return $user->toResource();
    }

    public function store(UserRequest $request): JsonResource
    {
        $user = User::query()->create($request->validated());

        return $user->toResource();
    }

    public function update(UserRequest $request, User $user): JsonResource
    {
        $user->update($request->validated());

        return $user->toResource();
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(null, 204);
    }
}
