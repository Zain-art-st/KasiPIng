<?php

return [
    // Minutes before the meeting starts at which a reminder is sent
    'intervals' => [1440, 720, 360, 180, 120, 60, 30, 15],

    // A reminder only fires within this many minutes after its time; later is skipped.
    // Keep it below the smallest gap between two intervals (15).
    'grace_minutes' => 10,

    // From this interval downwards, the PA(s) are copied on the reminder.
    'escalate_to_pa_from' => 180,
];