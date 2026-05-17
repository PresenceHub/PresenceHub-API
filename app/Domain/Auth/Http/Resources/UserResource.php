<?php

namespace App\Domain\Auth\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'isEmailVerified' => $this->hasVerifiedEmail(),
            'emailVerifiedAt' => $this->email_verified_at?->toISOString(),
            'role' => RoleResource::make($this->whenLoaded('role')),
            'currentWorkspace' => WorkspaceResource::make($this->whenLoaded('currentWorkspace')),
            'workspaces' => WorkspaceResource::collection($this->whenLoaded('workspaces')),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
