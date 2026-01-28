<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\getJson;

describe('Index', function (): void {
    it('returns a paginated list of users', function (): void {
        getJson('/api/users')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    });
});

describe('Show', function (): void {
    it('returns a single user', function (): void {
        $user = User::factory()->create();

        getJson("/api/users/$user->id")
            ->assertOk()
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'created_at',
                'updated_at',
            ]);
    });
});
