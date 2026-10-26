<?php

namespace App\Notifications\Concerns;

trait UsesPreferences
{
    /** Database always (unless opted out); mail when the user opted in and a mail key is enabled. */
    protected function channelsFor(object $notifiable, string $event, ?string $settingKey = null): array
    {
        $channels = [];
        $prefs = method_exists($notifiable, 'wantsNotification');
        if (! $prefs || $notifiable->wantsNotification($event, 'database')) {
            $channels[] = 'database';
        }
        $mailAllowed = $settingKey === null || settings()->bool($settingKey, true);
        if ($mailAllowed && ! empty($notifiable->email) && (! $prefs || $notifiable->wantsNotification($event, 'mail'))) {
            $channels[] = 'mail';
        }

        return $channels;
    }
}
