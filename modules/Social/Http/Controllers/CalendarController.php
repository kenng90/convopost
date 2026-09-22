<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class CalendarController extends Controller
{
    public function __invoke(): View
    {
        return view('social::calendar.index');
    }
}
