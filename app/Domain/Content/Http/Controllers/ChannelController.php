<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Http\Requests\ConnectChannelRequest;
use App\Domain\Content\Http\Requests\DisconnectChannelRequest;
use App\Domain\Content\Http\Requests\IndexChannelsRequest;
use App\Domain\Content\Http\Resources\ChannelResource;
use App\Domain\Content\Models\Channel;
use App\Domain\Content\Services\ChannelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ChannelController
{
    public function __construct(private ChannelService $channelService) {}

    public function index(IndexChannelsRequest $request): JsonResponse
    {
        $channels = $this->channelService->index(
            $request->workspace(),
            $request->integer('per_page', 15),
        );

        return ChannelResource::collection($channels)->response();
    }

    // TODO: Review this controller action
    public function connect(ConnectChannelRequest $request): JsonResponse
    {
        $channel = $this->channelService->create(
            $request->workspace(),
            $request->user(),
            $request->createAttributes(),
        );

        return ChannelResource::make($channel->load('platform'))->response()->setStatusCode(201);
    }

    // TODO: Review this controller action
    public function disconnect(DisconnectChannelRequest $request, Channel $channel): Response
    {
        $this->channelService->delete($request->workspace(), $request->user(), $channel);

        return response()->noContent();
    }
}
