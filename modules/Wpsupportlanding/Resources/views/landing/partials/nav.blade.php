<nav x-data="{ mobile: false }" class="rounded-full lp:!max-w-[1290px] xl:max-w-[1140px] lg:max-w-[960px] md:max-w-[720px] sm:max-w-[540px] min-[500px]:max-w-[450px] min-[425px]:max-w-[375px] max-w-[320px] mx-auto w-full fixed left-1/2 -translate-x-1/2 z-50 top-12 flex items-center justify-between px-4 xl:py-2 py-4 bg-white/60 backdrop-blur-[20px] dark:border dark:border-stroke-7 dark:bg-background-7">
    <div class="relative z-20 flex items-center justify-between">
        <div class="">
            <a class="text-xl font-bold text-gray-800 dark:text-white md:text-2xl hover:text-gray-700 dark:hover:text-gray-300" href="/">
                <img style="max-height: 60px" src="{{ config('settings.logo') }}" alt="">
            </a>
        </div>
    </div>

    <!-- Mobile menu button -->
    <div @click="mobile = !mobile" class="xl:hidden ml-auto">
        <button type="button" class="text-gray-500 dark:text-gray-300 hover:text-gray-600 dark:hover:text-white focus:outline-none focus:text-gray-600" aria-label="toggle menu">
            <svg viewBox="0 0 24 24" class="w-6 h-6 fill-current">
                <path fill-rule="evenodd" d="M4 5h16a1 1 0 0 1 0 2H4a1 1 0 1 1 0-2zm0 6h16a1 1 0 0 1 0 2H4a1 1 0 0 1 0-2zm0 6h16a1 1 0 0 1 0 2H4a1 1 0 0 1 0-2z">
                </path>
            </svg>
        </button>
    </div>

    <!-- Desktop Menu -->
    <div class="hidden xl:flex items-center justify-center flex-1  select-none">
        <div class="flex items-center space-x-6 lg:space-x-8">
            <a class="py-2.5 text-gray-800 dark:text-white/80 hover:text-gray-700 dark:hover:text-white transition-colors duration-200" href="{{ config('app.url') }}#features">{{ __('wpsupport.features') }}</a>
            <a class="py-2.5 text-gray-800 dark:text-white/80 hover:text-gray-700 dark:hover:text-white transition-colors duration-200" href="{{ config('app.url') }}#demo">{{ __('wpsupport.demo') }}</a>
            <a class="py-2.5 text-gray-800 dark:text-white/80 hover:text-gray-700 dark:hover:text-white transition-colors duration-200" href="{{ config('app.url') }}#pricing">{{ __('wpsupport.pricing') }}</a>
            <a class="py-2.5 text-gray-800 dark:text-white/80 hover:text-gray-700 dark:hover:text-white transition-colors duration-200" href="{{ config('app.url') }}#faq">{{ __('wpsupport.faq') }}</a>
            @if(isset($hasBlog) && $hasBlog)
                <a class="py-2.5 text-gray-800 dark:text-white/80 hover:text-gray-700 dark:hover:text-white transition-colors duration-200" href="{{ config('app.url') }}/blog">{{ __('Blog') }}</a>
            @endif
            @include('wpsupportlanding::landing.partials.lang')
        </div>
    </div>

    <!-- Check if logged in -->
    @guest
    <div class="hidden xl:flex items-center space-x-6 relative z-20">
        <a class="flex-shrink-0 font-semibold text-gray-900 dark:text-white hover:text-gray-700 dark:hover:text-gray-300 transition-colors duration-200" href="{{ route('login') }}">{{ __('wpsupport.login')}}</a>
        @if (!config('settings.disable_registration_page',false))
        <a href="{{ route('register') }}" class="flex-shrink-0 rounded-full text-sm py-3 px-8 font-medium text-white bg-gray-900 dark:bg-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors duration-200">
            {{ __('wpsupport.signup')}}
        </a>
        @endif
    </div>
    @endguest

    @auth
    <div class="hidden xl:flex items-center space-x-6 relative z-20">
        <a href="{{ route('home') }}" class="rounded-full flex-shrink-0 text-sm py-3 px-8 font-medium text-white bg-gray-900 dark:bg-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors duration-200">
            {{ __('wpsupport.dashboard')}}
        </a>
    </div>
    @endauth

    <!-- Mobile Menu -->
    <div x-show="mobile" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute top-full left-0 right-0 mt-2 mx-2 bg-white dark:bg-background-7 rounded-3xl shadow-lg border border-gray-200 dark:border-stroke-7 xl:hidden overflow-hidden backdrop-blur-[20px]">
        <div class="flex flex-col p-4 space-y-3">
            <a class="py-3 px-4 text-gray-800 dark:text-white/80 hover:bg-gray-100 dark:hover:bg-background-6 rounded-xl transition-colors duration-200" href="{{ config('app.url') }}#features">{{ __('wpsupport.features') }}</a>
            <a class="py-3 px-4 text-gray-800 dark:text-white/80 hover:bg-gray-100 dark:hover:bg-background-6 rounded-xl transition-colors duration-200" href="{{ config('app.url') }}#demo">{{ __('wpsupport.demo') }}</a>
            <a class="py-3 px-4 text-gray-800 dark:text-white/80 hover:bg-gray-100 dark:hover:bg-background-6 rounded-xl transition-colors duration-200" href="{{ config('app.url') }}#pricing">{{ __('wpsupport.pricing') }}</a>
            <a class="py-3 px-4 text-gray-800 dark:text-white/80 hover:bg-gray-100 dark:hover:bg-background-6 rounded-xl transition-colors duration-200" href="{{ config('app.url') }}#faq">{{ __('wpsupport.faq') }}</a>
            @if(isset($hasBlog) && $hasBlog)
                <a class="py-3 px-4 text-gray-800 dark:text-white/80 hover:bg-gray-100 dark:hover:bg-background-6 rounded-xl transition-colors duration-200" href="{{ config('app.url') }}/blog">{{ __('Blog') }}</a>
            @endif
            
            @guest
            <div class="border-t border-gray-200 dark:border-stroke-7 pt-3 mt-3 space-y-3">
                <a class="block py-3 px-4 text-gray-900 dark:text-white font-semibold hover:bg-gray-100 dark:hover:bg-background-6 rounded-xl transition-colors duration-200" href="{{ route('login') }}">{{ __('wpsupport.login')}}</a>
                @if (!config('settings.disable_registration_page',false))
                <a href="{{ route('register') }}" class="block py-3 px-8 text-center rounded-full font-medium text-white bg-gray-900 dark:bg-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors duration-200">
                    {{ __('wpsupport.signup')}}
                </a>
                @endif
            </div>
            @endguest

            @auth
            <div class="border-t border-gray-200 dark:border-stroke-7 pt-3 mt-3">
                <a href="{{ route('home') }}" class="block py-3 px-8 text-center rounded-full font-medium text-white bg-gray-900 dark:bg-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors duration-200">
                    {{ __('wpsupport.dashboard')}}
                </a>
            </div>
            @endauth
        </div>
    </div>

</nav>