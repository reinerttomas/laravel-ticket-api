<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Users')]
final readonly class UserController
{
    /**
     * Index
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::query()->paginate(
                perPage: $request->input('perPage', 15),
                page: $request->input('page', 1),
            )
        );
    }

    /**
     * Show
     */
    public function show(User $user): UserResource
    {
        return UserResource::make($user);
    }

    /**
     * Create
     */
    public function store(UserRequest $request): UserResource
    {
        $user = User::query()->create($request->validated());

        return UserResource::make($user);
    }

    /**
     * Update
     */
    public function update(UserRequest $request, User $user): UserResource
    {
        $user->update($request->validated());

        return UserResource::make($user);
    }

    /**
     * Delete
     */
    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(null, 204);
    }
}
