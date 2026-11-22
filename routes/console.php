<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('lessons:expire')->everyMinute();
