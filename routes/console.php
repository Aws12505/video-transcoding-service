<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('transcode:cleanup')->dailyAt('06:00');