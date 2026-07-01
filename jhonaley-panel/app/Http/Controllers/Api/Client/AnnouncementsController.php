<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Pterodactyl\Models\Announcement;
use Pterodactyl\Models\AnnouncementRead;

class AnnouncementsController extends ClientApiController
{
    /**
     * Retrieve all active announcements for the user.
     */
    public function index(Request $request): array
    {
        $user = $request->user();

        if (!Schema::hasTable('announcements')) {
            return ['object' => 'list', 'data' => []];
        }

        $query = Announcement::query();

        if (Schema::hasColumn('announcements', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('announcements', 'expires_at')) {
            $query->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            });
        }

        if (Schema::hasTable('announcement_reads')) {
            $query->whereNotIn('id', function ($query) use ($user) {
                $query->select('announcement_id')
                    ->from('announcement_reads')
                    ->where('user_id', $user->id);
            });
        }

        if (Schema::hasColumn('announcements', 'priority')) {
            $query->orderBy('priority', 'desc');
        }

        if (Schema::hasColumn('announcements', 'created_at')) {
            $query->orderBy('created_at', 'desc');
        }
        
        $announcements = $query->get();

        return [
            'object' => 'list',
            'data' => $announcements->map(function ($item) {
                return [
                    'object' => Announcement::RESOURCE_NAME,
                    'attributes' => [
                        'id' => $item->id,
                        'title' => $item->title,
                        'content' => $item->content,
                        'type' => $item->type ?: 'info',
                        'priority' => $item->priority ?: 2,
                        'target_display' => $item->target_display ?: ['dashboard'],
                    ]
                ];
            })->toArray()
        ];
    }

    /**
     * Mark an announcement as read.
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        if (!Schema::hasTable('announcements') || !Schema::hasTable('announcement_reads')) {
            return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
        }

        $announcement = Announcement::findOrFail($id);

        AnnouncementRead::firstOrCreate([
            'user_id' => $request->user()->id,
            'announcement_id' => $announcement->id,
        ]);

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }
}
