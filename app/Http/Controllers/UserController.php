<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class UserController extends Controller
{
    public function index(): ResourceCollection
    {
        return User::query()->paginate()->toResourceCollection();
    }

    public function show(User $user): JsonResource
    {
        return $user->toResource();
    }
}
