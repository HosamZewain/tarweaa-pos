<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Shift;
use App\Enums\ShiftStatus;

$openShifts = Shift::where('status', ShiftStatus::Open)->get();
echo "Number of open shifts: " . $openShifts->count() . "\n";
foreach ($openShifts as $shift) {
    echo "ID: " . $shift->id . ", Number: " . $shift->shift_number . ", Started at: " . $shift->started_at . "\n";
}

$recentClosed = Shift::where('status', ShiftStatus::Closed)->orderByDesc('ended_at')->limit(1)->first();
if ($recentClosed) {
    echo "Most recent closed shift: " . $recentClosed->shift_number . " at " . $recentClosed->ended_at . "\n";
} else {
    echo "No closed shifts found.\n";
}
unlink(__FILE__);
