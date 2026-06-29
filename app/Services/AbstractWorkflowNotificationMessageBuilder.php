<?php

namespace App\Services;

abstract class AbstractWorkflowNotificationMessageBuilder
{
    protected function greeting(): string
    {
        $hour = (int) date('H');

        if ($hour < 12) {
            return "🌅 Bonjour,";
        }

        if ($hour < 18) {
            return "☀️ Bon après-midi,";
        }

        return "🌙 Bonsoir,";
    }
}