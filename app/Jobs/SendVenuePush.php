<?php

namespace App\Jobs;

use App\Models\ApiDevice;
use App\Models\User;
use App\Push\Contracts\PushSender;
use App\Push\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fans one venue event out to every registered staff-app device of the
 * tenant (the editor and their staff). Best-effort: failures are logged
 * by the driver, never surfaced to the guest or staff request that
 * triggered the event.
 */
class SendVenuePush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $editorId,
        public readonly string $event,
        public readonly array $data = [],
    ) {}

    public function handle(PushSender $sender): void
    {
        $userIds = User::where(function ($query) {
            $query->where('id', $this->editorId)
                ->orWhere(function ($staff) {
                    $staff->where('is_staff', true)->where('editor_id', $this->editorId);
                });
        })->pluck('id');

        $devices = ApiDevice::whereIn('user_id', $userIds)
            ->whereNotNull('fcm_token')
            ->get();

        if ($devices->isEmpty()) {
            return;
        }

        $sender->send($this->message(), $devices);
    }

    private function message(): PushMessage
    {
        $table = $this->data['table_number'] ?? null;

        $title = match ($this->event) {
            'order.created' => $table ? __('New order — Table :n', ['n' => $table]) : __('New order'),
            'approval.requested' => $table ? __('Table approval — Table :n', ['n' => $table]) : __('Table approval requested'),
            'service.requested' => $this->serviceTitle($table),
            default => null,
        };

        return new PushMessage(
            $this->event,
            $title,
            null,
            $this->data + ['editor_id' => $this->editorId],
        );
    }

    private function serviceTitle(?string $table): string
    {
        $type = $this->data['type'] ?? null;

        if ($type === 'bill') {
            return $table ? __('Bill requested — Table :n', ['n' => $table]) : __('Bill requested');
        }

        return $table ? __('Waiter called — Table :n', ['n' => $table]) : __('Waiter called');
    }
}
