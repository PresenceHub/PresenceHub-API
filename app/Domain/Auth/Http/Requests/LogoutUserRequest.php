<?php

namespace App\Domain\Auth\Http\Requests;

use App\Http\Requests\Api\V1FormRequest;

class LogoutUserRequest extends V1FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }
}
