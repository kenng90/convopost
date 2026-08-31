<section id="platform" class="py-24 border-b border-gray-200">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="mb-12 max-w-2xl">
      <p class="section-label mb-3">The platform</p>
      <h2 class="font-display section-title font-800 mb-4">Unganisha Hub: <br/><span class="grad-text">your business command center</span></h2>
      <p class="text-gray-600">{{ \Modules\Wpsupportlanding\Support\UnganishaBrand::positioning() }} One place to connect with customers, conversations, commerce and payments.</p>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
      @foreach(\Modules\Wpsupportlanding\Support\UnganishaBrand::products() as $product)
        <a href="{{ $product['href'] }}" class="card-lift rounded-2xl border border-gray-200 bg-white p-6 block">
          <p class="font-display text-lg font-800 text-ink mb-1.5">{{ $product['name'] }}</p>
          <p class="text-base text-gray-600 leading-relaxed">{{ $product['blurb'] }}</p>
        </a>
      @endforeach
    </div>
  </div>
</section>
