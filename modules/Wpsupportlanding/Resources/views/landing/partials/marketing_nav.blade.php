<nav class="navbar-blur fixed top-0 left-0 right-0 z-50" style="height:72px;" x-data="{ mobileOpen: false }">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-full">
    <a href="{{ url('/') }}" class="flex items-center gap-2.5 flex-shrink-0">
      @if(config('settings.logo'))
        <img src="{{ config('settings.logo') }}" alt="{{ config('app.name') }}" class="h-9 w-auto max-w-[140px] object-contain">
      @else
        <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background:linear-gradient(135deg,#25D366,#075E54);">
          <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.105.547 4.085 1.505 5.805L0 24l6.388-1.493A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/></svg>
        </div>
        <span class="font-display font-800 text-lg tracking-tight text-white">{{ config('app.name', 'ConvoConnect') }}</span>
      @endif
    </a>

    <div class="hidden lg:flex items-center gap-7">
      <a href="{{ url('/#features') }}" class="text-sm text-gray-400 hover:text-white transition-colors">{{ __('Features') }}</a>
      <a href="{{ url('/#pricing') }}" class="text-sm text-gray-400 hover:text-white transition-colors">{{ __('Pricing') }}</a>
      <a href="{{ url('/#faq') }}" class="text-sm text-gray-400 hover:text-white transition-colors">{{ __('FAQ') }}</a>
      @if($hasBlog ?? false)
        <a href="{{ url('/blog') }}" class="text-sm text-wa-green font-medium">{{ __('Blog') }}</a>
      @endif
    </div>

    <div class="hidden lg:flex items-center gap-3">
      <a href="{{ route('login') }}" class="text-sm text-gray-300 hover:text-white transition-colors font-medium">{{ __('Sign In') }}</a>
      @if (!config('settings.disable_registration_page', false))
        <a href="{{ route('register') }}" class="text-sm font-semibold px-4 py-2 rounded-lg text-black transition-all hover:opacity-90" style="background:#25D366;">{{ __('Get Started') }}</a>
      @endif
    </div>

    <button type="button" class="lg:hidden text-gray-400 hover:text-white" @click="mobileOpen = !mobileOpen" aria-label="Menu">
      <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>

  <div x-show="mobileOpen" x-cloak class="lg:hidden border-t border-white/5 px-4 py-4 space-y-1" style="background:rgba(4,15,12,0.98);">
    <a href="{{ url('/#features') }}" class="block py-2.5 text-sm text-gray-300">{{ __('Features') }}</a>
    <a href="{{ url('/#pricing') }}" class="block py-2.5 text-sm text-gray-300">{{ __('Pricing') }}</a>
    <a href="{{ url('/#faq') }}" class="block py-2.5 text-sm text-gray-300">{{ __('FAQ') }}</a>
    @if($hasBlog ?? false)
      <a href="{{ url('/blog') }}" class="block py-2.5 text-sm text-wa-green">{{ __('Blog') }}</a>
    @endif
    <div class="pt-3 flex flex-col gap-2">
      <a href="{{ route('login') }}" class="text-center py-2.5 text-sm text-gray-300 border border-white/10 rounded-lg">{{ __('Sign In') }}</a>
      @if (!config('settings.disable_registration_page', false))
        <a href="{{ route('register') }}" class="text-center py-2.5 text-sm font-semibold text-black rounded-lg" style="background:#25D366;">{{ __('Get Started') }}</a>
      @endif
    </div>
  </div>
</nav>
