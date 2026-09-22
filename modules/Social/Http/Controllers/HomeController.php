<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Offering;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

class HomeController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $home = Offering::socialHomeRoute();

        if ($home !== 'social.home' && Route::has($home)) {
            return redirect()->route($home);
        }

        return redirect()->route('social.calendar');
    }
}
