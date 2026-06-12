<?php

namespace App\Push;

/**
 * A staff-app push notification. The data map is the machine payload
 * (FCM requires string values); title/body are the optional human
 * content shown by the OS when the app is backgrounded.
 */
class PushMessage
{
    /** @var array<string, string> */
    public readonly array $data;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $event,
        public readonly ?string $title = null,
        public readonly ?string $body = null,
        array $data = [],
    ) {
        $stringified = ['event' => $event];
        foreach ($data as $key => $value) {
            if ($value !== null) {
                $stringified[$key] = (string) $value;
            }
        }
        $this->data = $stringified;
    }
}
