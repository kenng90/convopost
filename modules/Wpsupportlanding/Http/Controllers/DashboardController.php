<?php

namespace Modules\Wpsupportlanding\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ConfChanger;
use Akaunting\Module\Facade as Module;
use App\Models\Plans;
use App\Models\Posts;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;

class DashboardController extends Controller
{
    public function index()
    {
        return $this->asCompany();
    }

    public function asCompany()
    {

        $company=$this->getCompany();

        //Change Language
        ConfChanger::switchLanguage($company);

        //Change currency
        ConfChanger::switchCurrency($company);


        $data=[
                'non_wpsupportlanding'=>[
                    'title'=>'Campaigns',
                    'icon'=>'ni-notification-70',
                    'icon_color'=>'bg-gradient-info',
                    'main_value'=>0,
                    'sub_value'=>0,
                    'sub_value_color'=>'text-success',
                    'sub_title'=>"Read rate",
                    'href'=>route('campaigns.index')
                ]
            ];
        

    

        return $data;
    }

    public function landing()
    {

        //Change Language
        $locale = Cookie::get('lang') ? Cookie::get('lang') : config('settings.app_locale');
        if (isset($_GET['lang'])) {
            //this is language route
            $locale = $_GET['lang'];
        }

        if ($locale != 'android-chrome-256x256.png') {
            App::setLocale(strtolower($locale));
            session(['applocale_change' => strtolower($locale)]);
        }

        //Landing page content
        $features = Posts::where('post_type', 'feature')->get();
        $testimonials = Posts::where('post_type', 'testimonial')->get();
        $faqs = Posts::where('post_type', 'faq')->get();
        $mainfeatures = Posts::where('post_type', 'mainfeature')->get();

        $colCounter = [1, 2, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4];
        $plans = config('settings.forceUserToPay', false) ? Plans::where('id', '!=', intval(config('settings.free_pricing_id')))->get() : Plans::get();
        $data = [
            'col' => count($plans) > 0 ? $colCounter[count($plans) - 1] : 4,
            'plans' => $plans,
            'features' => $features,
            'processes' => $features,
            'mainfeatures' => $mainfeatures,
            'locale' => strtolower($locale),
            'faqs' => $faqs,
            'testimonials' => $testimonials,
            'hasAIBots' => Module::has('flowiseai'),
            'hasBlog' => Module::has('blog'),
        ];

        try {
            $response = new \Illuminate\Http\Response(view('wpsupportlanding::landing.index', $data));
        } catch (\Throwable $th) {
            dd('Please read the update guide for version 3.2.0. You need to upload the landing page module');
        }

        App::setLocale(strtolower($locale));
        $response->withCookie(cookie('lang', $locale, 120));
        App::setLocale(strtolower($locale));

        return $response;
    }

}


