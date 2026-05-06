<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Events\ChannelConnected;
use App\Domain\Content\Events\ChannelDisconnected;
use App\Domain\Content\Models\Channel;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

class ChannelService
{
    public function index(Workspace $workspace, int $perPage = 15): LengthAwarePaginator
    {
        return Channel::query()
            ->where('workspace_id', $workspace->id)
            ->with('platform')
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @param  array{platform_id: int, handle: string, platform_account_id: string, access_token: string, refresh_token?: string|null, token_expires_at?: Carbon|string|null}  $attributes
     */
    public function create(Workspace $workspace, User $creator, array $attributes): Channel
    {
        $channel = Channel::query()->create([
            'workspace_id' => $workspace->id,
            'platform_id' => $attributes['platform_id'],
            'handle' => $attributes['handle'],
            'platform_account_id' => $attributes['platform_account_id'],
            'access_token' => $attributes['access_token'],
            'refresh_token' => $attributes['refresh_token'] ?? null,
            'token_expires_at' => $attributes['token_expires_at'] ?? null,
            'created_by' => $creator->id,
        ]);

        Event::dispatch(new ChannelConnected($channel->loadMissing('platform'), $workspace, $creator));

        return $channel;
    }

    public function delete(Workspace $workspace, User $user, Channel $channel): void
    {
        // TODO: Review this check
        if ($channel->workspace_id !== $workspace->id) {
            abort(404);
        }

        $channel->loadMissing('platform');
        $channel->delete();

        Event::dispatch(new ChannelDisconnected($channel, $workspace, $user));
    }
}
