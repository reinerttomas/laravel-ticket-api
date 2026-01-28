<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UserRequest extends FormRequest
{
    private ?User $user = null;

    /**
     * @return array<string, list<string|Rule>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($this->user),
            ],

            'password' => [
                'required',
                'string',
                Password::default(),
                'confirmed',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('user') instanceof User) {
            $this->user = $this->route('user');
        }
    }
}
