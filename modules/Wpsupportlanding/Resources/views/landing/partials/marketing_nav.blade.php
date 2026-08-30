@php
    $registrationEnabled = $registrationEnabled ?? !config('settings.disable_registration_page', false);
@endphp
<nav class="navbar-blur fixed top-0 left-0 right-0 z-50" style="height:72px;" x-data="{ mobileOpen: false }">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-full">
    <a href="{{ url('/') }}" class="flex items-center gap-2.5 flex-shrink-0">
      @include('wpsupportlanding::landing.partials.chatduka.logo', ['wordmarkClass' => 'font-display font-800 text-lg tracking-tight text-white'])
    </a>

    <div class="hidden lg:flex items-center gap-7">
      <a href="{{ url('/#features') }}" class="text-sm text-white/70 hover:text-white transition-colors">{{ __('Features') }}</a>
      <a href="{{ url('/#pricing') }}" class="text-sm text-white/70 hover:text-white transition-colors">{{ __('Pricing') }}</a>
      <a href="{{ url('/#faq') }}" class="text-sm text-white/70 hover:text-white transition-colors">{{ __('FAQ') }}</a>
      @if($hasBlog ?? false)
        <a href="{{ url('/blog') }}" class="text-sm font-medium" style="color:#E8A317;">{{ __('Blog') }}</a>
      @endif
    </div>

    <div class="hidden lg:flex items-center gap-3">
      <a href="{{ route('login') }}" class="text-sm text-white/80 hover:text-white transition-colors font-medium">{{ __('Sign In') }}</a>
      @if ($registrationEnabled)
        <a href="{{ route('register') }}" class="text-sm font-semibold px-4 py-2 rounded-lg text-ink transition-all hover:opacity-90" style="background:#E8A317;">{{ __('Get Started') }}</a>
      @endif
    </div>

    <button type="button" class="lg:hidden text-white/80 hover:text-white" @click="mobileOpen = !mobileOpen" aria-label="Menu">
      <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>

  <div x-show="mobileOpen" x-cloak class="lg:hidden border-t border-white/10 px-4 py-4 space-y-1" style="background:rgba(21,29,64,0.98);">
    <a href="{{ url('/#features') }}" class="block py-2.5 text-sm text-white/80">{{ __('Features') }}</a>
    <a href="{{ url('/#pricing') }}" class="block py-2.5 text-sm text-white/80">{{ __('Pricing') }}</a>
    <a href="{{ url('/#faq') }}" class="block py-2.5 text-sm text-white/80">{{ __('FAQ') }}</a>
    @if($hasBlog ?? false)
      <a href="{{ url('/blog') }}" class="block py-2.5 text-sm" style="color:#E8A317;">{{ __('Blog') }}</a>
    @endif
    <div class="pt-3 flex flex-col gap-2">
      <a href="{{ route('login') }}" class="text-center py-2.5 text-sm text-white/80 border border-white/15 rounded-lg">{{ __('Sign In') }}</a>
      @if ($registrationEnabled)
        <a href="{{ route('register') }}" class="text-center py-2.5 text-sm font-semibold text-ink rounded-lg" style="background:#E8A317;">{{ __('Get Started') }}</a>
      @endif
    </div>
  </div>
</nav>
