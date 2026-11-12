<?php

namespace App\Console\Commands;

use App\Models\Invitation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class CloseUnansweredInvitations extends Command
{
    protected $signature = 'invitations:close-unanswered';

    protected $description = 'Mark guests who never answered as not attending once the meeting has started';

    public function handle(): int
    {
        $now = now();

        $closed = Invitation::query()
            ->where('attendance_status', 'pending')
            ->where(fn (Builder $q) => $q
                ->whereHas('meeting', fn (Builder $m) => $m->whereNull('cancelled_at')->where('starts_at', '<=', $now))
                ->orWhere(fn (Builder $s) => $s->whereNull('meeting_id')->where('expires_at', '<=', $now)))
            ->update(['attendance_status' => 'not_attending', 'no_response_at' => $now]);

        $this->info("Marked {$closed} unanswered invitation(s) as not attending.");

        return self::SUCCESS;
    }
}
