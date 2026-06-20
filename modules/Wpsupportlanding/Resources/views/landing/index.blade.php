<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>ConvoConnect — Social Commerce Platform for WhatsApp</title>
<meta name="description" content="Sell from chat, run campaigns, take bookings, and get paid on WhatsApp. ConvoConnect is the social commerce platform with catalogs, team inbox, and M-Pesa payments." />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet" />
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        wa: {
          green: '#25D366',
          dark: '#128C7E',
          darker: '#075E54',
          light: '#dcfce7',
        }
      },
      fontFamily: {
        display: ['Syne', 'sans-serif'],
        body: ['DM Sans', 'sans-serif'],
      }
    }
  }
}
</script>
<style>
  *, body { font-family: 'DM Sans', sans-serif; }
  h1,h2,h3,h4,h5,.font-display { font-family: 'Syne', sans-serif; }

  :root {
    --wa-green: #25D366;
    --wa-dark: #128C7E;
    --wa-darker: #075E54;
    --hero-bg: #040f0c;
  }

  html { scroll-padding-top: 72px; }

  /* Noise texture overlay */
  .noise::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
    pointer-events: none;
    z-index: 0;
  }

  /* Gradient mesh */
  .hero-glow {
    background: radial-gradient(ellipse 80% 50% at 50% -10%, rgba(37,211,102,0.18) 0%, transparent 70%),
                radial-gradient(ellipse 50% 40% at 80% 60%, rgba(18,140,126,0.12) 0%, transparent 60%),
                radial-gradient(ellipse 40% 30% at 10% 80%, rgba(7,94,84,0.10) 0%, transparent 50%);
  }

  /* Grid pattern */
  .grid-pattern {
    background-image: linear-gradient(rgba(37,211,102,0.04) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(37,211,102,0.04) 1px, transparent 1px);
    background-size: 48px 48px;
  }

  /* Float animation */
  @keyframes float { 0%,100% { transform: translateY(0px); } 50% { transform: translateY(-12px); } }
  @keyframes float2 { 0%,100% { transform: translateY(0px) rotate(-2deg); } 50% { transform: translateY(-8px) rotate(2deg); } }
  @keyframes pulse-ring { 0% { transform: scale(1); opacity: 1; } 100% { transform: scale(1.6); opacity: 0; } }
  @keyframes slide-up { from { opacity:0; transform: translateY(20px); } to { opacity:1; transform: translateY(0); } }
  @keyframes scan { 0% { transform: translateY(-100%); } 100% { transform: translateY(400%); } }

  .float { animation: float 4s ease-in-out infinite; }
  .float2 { animation: float2 5s ease-in-out infinite; }
  .float3 { animation: float 6s ease-in-out infinite 1s; }

  .pulse-ring::after {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    border: 2px solid var(--wa-green);
    animation: pulse-ring 1.8s ease-out infinite;
  }

  .card-lift {
    transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.25s ease;
  }
  .card-lift:hover {
    transform: translateY(-6px) scale(1.01);
    box-shadow: 0 20px 60px rgba(37,211,102,0.12);
  }

  /* Navbar blur */
  .navbar-blur {
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    background: rgba(4,15,12,0.85);
    border-bottom: 1px solid rgba(37,211,102,0.08);
  }

  /* Phone mockup */
  .phone-frame {
    background: #1a1a2e;
    border-radius: 40px;
    border: 2px solid rgba(37,211,102,0.15);
    box-shadow: 0 40px 120px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.04), inset 0 0 0 1px rgba(255,255,255,0.04);
  }
  .phone-notch {
    background: #0d0d1a;
    border-radius: 0 0 20px 20px;
    margin: 0 auto;
    width: 40%;
    height: 28px;
  }

  /* WhatsApp chat bubble */
  .bubble-in {
    background: #1f2c34;
    border-radius: 0 12px 12px 12px;
  }
  .bubble-out {
    background: #005c4b;
    border-radius: 12px 12px 0 12px;
  }

  /* Code block */
  .code-block {
    background: #0d1117;
    border: 1px solid rgba(37,211,102,0.15);
    border-radius: 12px;
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 13px;
    line-height: 1.7;
  }

  /* Gradient text */
  .grad-text {
    background: linear-gradient(135deg, #25D366 0%, #128C7E 50%, #a8e6cf 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  /* Flow node */
  .flow-node {
    background: rgba(37,211,102,0.06);
    border: 1px solid rgba(37,211,102,0.2);
    border-radius: 12px;
    transition: all 0.2s ease;
  }
  .flow-node:hover {
    background: rgba(37,211,102,0.12);
    border-color: rgba(37,211,102,0.4);
    transform: scale(1.02);
  }
  .flow-line {
    width: 2px;
    background: linear-gradient(to bottom, rgba(37,211,102,0.4), rgba(37,211,102,0.1));
    margin: 0 auto;
    height: 24px;
  }

  /* Pricing card */
  .pricing-popular {
    background: linear-gradient(135deg, rgba(37,211,102,0.08), rgba(18,140,126,0.05));
    border: 1px solid rgba(37,211,102,0.3);
    position: relative;
  }
  .pricing-popular::before {
    content: '';
    position: absolute;
    inset: -1px;
    border-radius: inherit;
    background: linear-gradient(135deg, rgba(37,211,102,0.3), rgba(18,140,126,0.1));
    z-index: -1;
  }

  /* Stat bar */
  .stat-item {
    position: relative;
  }
  .stat-item + .stat-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 20%;
    height: 60%;
    width: 1px;
    background: rgba(37,211,102,0.15);
  }

  /* Badge */
  .badge {
    background: rgba(37,211,102,0.1);
    border: 1px solid rgba(37,211,102,0.25);
    color: #25D366;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 4px 10px;
    border-radius: 999px;
  }

  /* Scrollbar */
  ::-webkit-scrollbar { width: 6px; }
  ::-webkit-scrollbar-track { background: #040f0c; }
  ::-webkit-scrollbar-thumb { background: rgba(37,211,102,0.3); border-radius: 3px; }

  /* Accordion transition */
  [x-cloak] { display: none !important; }

  /* Dots */
  .dots-bg {
    background-image: radial-gradient(rgba(37,211,102,0.08) 1px, transparent 1px);
    background-size: 24px 24px;
  }

  /* Integration card */
  .integration-card {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.06);
    transition: all 0.2s ease;
  }
  .integration-card:hover {
    background: rgba(37,211,102,0.04);
    border-color: rgba(37,211,102,0.2);
    transform: translateY(-3px);
  }
</style>
</head>

<body class="bg-[#040f0c] text-white" x-data="{ mobileOpen: false, billing: 'monthly' }">

<!-- ===== NAVBAR ===== -->
<nav class="navbar-blur fixed top-0 left-0 right-0 z-50" style="height:72px;">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-full">
    <!-- Logo -->
    <a href="#" class="flex items-center gap-2.5 flex-shrink-0">
      <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background:linear-gradient(135deg,#25D366,#075E54);">
      <img src="public/uploads/lgU7zxttjQlFP0g8jI9EE9FxfvelUIQ6wVT5Qwq1.png" alt="icon" class="w-full h-full object-contain p-1" />
      </div>
      <span class="font-display font-800 text-lg tracking-tight text-white">Convo<span class="grad-text">Connect</span></span>
    </a>

    <!-- Desktop Nav -->
    <div class="hidden lg:flex items-center gap-7">
      <a href="#features" class="text-sm text-gray-400 hover:text-white transition-colors">Features</a>
      <a href="#platform" class="text-sm text-gray-400 hover:text-white transition-colors">Platform</a>
      <a href="#bookings" class="text-sm text-gray-400 hover:text-white transition-colors">Bookings</a>
      <a href="#catalog" class="text-sm text-gray-400 hover:text-white transition-colors">Catalog</a>
      <a href="#automation" class="text-sm text-gray-400 hover:text-white transition-colors">Automation</a>
      <a href="#integrations" class="text-sm text-gray-400 hover:text-white transition-colors">Integrations</a>
      <a href="#api" class="text-sm text-gray-400 hover:text-white transition-colors">API</a>
      <a href="#pricing" class="text-sm text-gray-400 hover:text-white transition-colors">Pricing</a>
      <a href="#faq" class="text-sm text-gray-400 hover:text-white transition-colors">FAQ</a>
      <!-- <a href="#book" class="text-sm text-gray-400 hover:text-white transition-colors">Book Demo</a> -->
      @if(isset($hasBlog) && $hasBlog)
      <a href="/blog" class="text-sm text-gray-400 hover:text-white transition-colors">Blog</a>
      @endif
    </div>

    <!-- CTAs -->
    <div class="hidden lg:flex items-center gap-3">
      <a href="{{ route('login') }}" class="text-sm text-gray-300 hover:text-white transition-colors font-medium">Sign In</a>
      <a href="{{ route('register') }}" class="text-sm font-semibold px-4 py-2 rounded-lg text-black transition-all hover:opacity-90 hover:scale-105" style="background:#25D366;">Get Started Free</a>
    </div>

    <!-- Mobile toggle -->
    <button class="lg:hidden text-gray-400 hover:text-white" @click="mobileOpen = !mobileOpen">
      <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      <svg x-show="mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>

  <!-- Mobile Menu -->
  <div x-show="mobileOpen" x-cloak class="lg:hidden border-t border-white/5 px-4 py-4 space-y-1" style="background:rgba(4,15,12,0.98);">
    <a href="#features" class="block py-2.5 text-sm text-gray-300 hover:text-white" @click="mobileOpen=false">Features</a>
    <a href="#platform" class="block py-2.5 text-sm text-gray-300 hover:text-white" @click="mobileOpen=false">Platform</a>
    <a href="#bookings" class="block py-2.5 text-sm text-gray-300 hover:text-white" @click="mobileOpen=false">Bookings</a>
    <a href="#catalog" class="block py-2.5 text-sm text-gray-300 hover:text-white" @click="mobileOpen=false">Catalog</a>
    <a href="#automation" class="block py-2.5 text-sm text-gray-300 hover:text-white" @click="mobileOpen=false">Automation</a>
    <a href="#integrations" class="block py-2.5 text-sm text-gray-300 hover:text-white" @click="mobileOpen=false">Integrations</a>
    <a href="#api" class="block py-2.5 text-sm text-gray-300 hover:text-white" @click="mobileOpen=false">API</a>
    <a href="#pricing" class="block py-2.5 text-sm text-gray-300 hover:text-white" @click="mobileOpen=false">Pricing</a>
    <a href="#faq" class="block py-2.5 text-sm text-gray-300 hover:text-white" @click="mobileOpen=false">FAQ</a>
    
    @if(isset($hasBlog) && $hasBlog)
    <a href="/blog" class="block py-2.5 text-sm text-gray-300 hover:text-white" @click="mobileOpen=false">Blog</a>
    @endif
    <div class="pt-3 flex flex-col gap-2">
      <a href="{{ route('login') }}" class="text-center py-2.5 text-sm text-gray-300 border border-white/10 rounded-lg">Sign In</a>
      <a href="{{ route('register') }}" class="text-center py-2.5 text-sm font-semibold text-black rounded-lg" style="background:#25D366;">Get Started Free</a>
    </div>
  </div>
</nav>

<!-- ===== HERO ===== -->
<section class="relative min-h-screen flex items-center pt-20 overflow-hidden noise hero-glow grid-pattern">
  <!-- Background elements -->
  <div class="absolute inset-0 z-0">
    <div class="absolute top-1/4 left-1/4 w-96 h-96 rounded-full opacity-10 blur-3xl" style="background:radial-gradient(circle,#25D366,transparent 70%);"></div>
    <div class="absolute bottom-1/4 right-1/4 w-64 h-64 rounded-full opacity-8 blur-3xl" style="background:radial-gradient(circle,#128C7E,transparent 70%);"></div>
  </div>

  <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 py-16 grid lg:grid-cols-2 gap-16 items-center">
    <!-- Left: Copy -->
    <div>
      <div class="badge inline-flex mb-6">
        <span class="w-2 h-2 rounded-full mr-2 pulse-ring relative" style="background:#25D366;"></span>
        Social commerce platform · Official Meta WhatsApp Business API
      </div>

      <h1 class="font-display text-5xl sm:text-6xl lg:text-7xl font-800 leading-[1.05] mb-6" style="letter-spacing:-0.02em;">
        The social commerce<br />
        platform for<br />
        <span class="grad-text">WhatsApp</span>
      </h1>

      <p class="text-lg text-gray-400 leading-relaxed mb-8 max-w-xl">
        Sell from chat, run campaigns, take bookings, and get paid — product catalogs, team inbox, and M-Pesa in one platform. Go from signup to your first sale in as little as 15 minutes.
      </p>

      <div class="flex flex-wrap gap-3 mb-10">
        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-6 py-3.5 text-sm font-semibold text-black rounded-xl transition-all hover:opacity-90 hover:scale-105" style="background:#25D366;">
          Start Free Today
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </a>
        <a href="#features" class="inline-flex items-center gap-2 px-6 py-3.5 text-sm font-semibold text-white rounded-xl border border-white/10 hover:border-white/20 transition-all hover:bg-white/5">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          See Features
        </a>
      </div>

      <!-- Social proof -->
      <div class="flex items-center gap-4">
        <div class="flex -space-x-2">
          <div class="w-8 h-8 rounded-full border-2 border-[#040f0c] flex items-center justify-center text-xs font-bold" style="background:linear-gradient(135deg,#667eea,#764ba2);">A</div>
          <div class="w-8 h-8 rounded-full border-2 border-[#040f0c] flex items-center justify-center text-xs font-bold" style="background:linear-gradient(135deg,#f093fb,#f5576c);">B</div>
          <div class="w-8 h-8 rounded-full border-2 border-[#040f0c] flex items-center justify-center text-xs font-bold" style="background:linear-gradient(135deg,#4facfe,#00f2fe);">C</div>
          <div class="w-8 h-8 rounded-full border-2 border-[#040f0c] flex items-center justify-center text-xs font-bold" style="background:linear-gradient(135deg,#43e97b,#38f9d7);">D</div>
          <div class="w-8 h-8 rounded-full border-2 border-[#040f0c] flex items-center justify-center text-xs font-semibold text-gray-300" style="background:#1a2a1a;">+</div>
        </div>
        <div>
          <div class="flex text-yellow-400 text-sm mb-0.5">★★★★★</div>
          <p class="text-xs text-gray-500"><span class="text-white font-semibold">2,400+</span> businesses on ConvoConnect</p>
        </div>
      </div>
    </div>

    <!-- Right: Phone Mockup -->
    <div class="relative flex justify-center items-center">
      <!-- Floating stat badges -->
      <div class="absolute -left-4 top-12 float2 hidden md:block">
        <div class="bg-[#0f1f1a] border border-green-900/40 rounded-2xl px-4 py-3 shadow-2xl">
          <p class="text-xs text-gray-500 mb-1">Sales via WhatsApp today</p>
          <p class="text-xl font-display font-bold" style="color:#25D366;">KES 284K</p>
          <div class="flex items-center gap-1 mt-1">
            <span class="text-xs text-emerald-400">↑ 18%</span>
            <span class="text-xs text-gray-600">vs yesterday</span>
          </div>
        </div>
      </div>

      <div class="absolute -right-4 bottom-20 float3 hidden md:block">
        <div class="bg-[#0f1f1a] border border-green-900/40 rounded-2xl px-4 py-3 shadow-2xl">
          <div class="flex items-center gap-2 mb-1">
            <div class="w-2 h-2 rounded-full" style="background:#25D366;"></div>
            <p class="text-xs text-gray-400">Campaign sent</p>
          </div>
          <p class="text-sm font-semibold text-white">12,400 delivered</p>
          <p class="text-xs text-gray-500 mt-0.5">89% read rate · 3 regions</p>
        </div>
      </div>

      <!-- Phone -->
      <div class="phone-frame w-64 sm:w-72 float overflow-hidden" style="height:560px;">
        <div class="phone-notch"></div>
        <!-- WhatsApp header -->
        <div class="flex items-center gap-2.5 px-3 py-2.5" style="background:#1f2c34;">
          <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold" style="background:linear-gradient(135deg,#25D366,#075E54);">CC</div>
          <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-white">StyleHive Shop</p>
            <p class="text-xs" style="color:#25D366;">● Online</p>
          </div>
          <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
        </div>

        <!-- Chat messages -->
        <div class="px-3 py-3 space-y-3 overflow-hidden" style="background:#0b1418; height:calc(100% - 130px);">
          <div class="flex justify-start">
            <div class="bubble-in px-3 py-2 max-w-[80%]">
              <p class="text-xs text-white">👋 Hi John! New arrivals just dropped. Want to see the catalog?</p>
              <p class="text-right text-xs text-gray-500 mt-1">09:41</p>
            </div>
          </div>
          <div class="flex justify-end">
            <div class="bubble-out px-3 py-2 max-w-[80%]">
              <p class="text-xs text-white">Yes — do you have the blue sneakers in 42?</p>
              <p class="text-right text-xs text-gray-400 mt-1">09:42 ✓✓</p>
            </div>
          </div>
          <!-- Product card -->
          <div class="bg-[#1f2c34] rounded-xl overflow-hidden border border-white/5">
            <div class="px-3 pt-3 pb-2">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-sm" style="background:rgba(37,211,102,0.15);">👟</div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-semibold text-white">Blue Runner Sneakers</p>
                  <p class="text-xs" style="color:#25D366;">KES 4,500 · Size 42</p>
                </div>
              </div>
              <button class="w-full mt-1 py-1.5 rounded-lg text-xs font-semibold text-black" style="background:#25D366;">Buy on WhatsApp →</button>
            </div>
          </div>
          <div class="flex justify-end">
            <div class="bubble-out px-3 py-2 max-w-[80%]">
              <p class="text-xs text-white">I'll take them!</p>
              <p class="text-right text-xs text-gray-400 mt-1">09:43 ✓✓</p>
            </div>
          </div>
          <div class="flex justify-start">
            <div class="bubble-in px-3 py-2 max-w-[80%]">
              <p class="text-xs text-white">✅ M-Pesa received · Order #1042 confirmed. Delivery tomorrow!</p>
              <p class="text-right text-xs text-gray-500 mt-1">09:44</p>
            </div>
          </div>
        </div>
        <!-- Input bar -->
        <div class="flex items-center gap-2 px-3 py-2" style="background:#1f2c34;">
          <div class="flex-1 bg-[#2a3942] rounded-full px-3 py-1.5 text-xs text-gray-500">Type a message</div>
          <div class="w-7 h-7 rounded-full flex items-center justify-center" style="background:#25D366;">
            <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== STATS BAR ===== -->
<section class="border-y border-white/5 py-8" style="background:rgba(37,211,102,0.03);">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 grid grid-cols-2 lg:grid-cols-4 gap-0">
    <div class="stat-item text-center px-6 py-4">
      <p class="font-display text-3xl sm:text-4xl font-800 grad-text mb-1">2B+</p>
      <p class="text-sm text-gray-500">WhatsApp users reachable</p>
    </div>
    <div class="stat-item text-center px-6 py-4">
      <p class="font-display text-3xl sm:text-4xl font-800 grad-text mb-1">100%</p>
      <p class="text-sm text-gray-500">Official Meta API compliant</p>
    </div>
    <div class="stat-item text-center px-6 py-4">
      <p class="font-display text-3xl sm:text-4xl font-800 grad-text mb-1">15 min</p>
      <p class="text-sm text-gray-500">Average time to first reply</p>
    </div>
    <div class="stat-item text-center px-6 py-4">
      <p class="font-display text-3xl sm:text-4xl font-800 grad-text mb-1">4.9★</p>
      <p class="text-sm text-gray-500">Average customer rating</p>
    </div>
  </div>
</section>

<!-- ===== FEATURES GRID ===== -->
<section id="features" class="py-24 relative">
  <div class="absolute inset-0 dots-bg opacity-50"></div>
  <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">Commerce + operations</div>
      <h2 class="font-display text-4xl sm:text-5xl font-800 mb-4">Everything to sell and<br/>support on WhatsApp</h2>
      <p class="text-gray-400 max-w-xl mx-auto">Catalogs and checkout for social selling, plus inbox, campaigns, bookings, and payments — one platform for your entire WhatsApp business.</p>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <!-- Card 1 -->
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-xl" style="background:rgba(37,211,102,0.1);">👥</div>
        <h3 class="font-display font-700 text-lg mb-2">Shared Team Inbox</h3>
        <p class="text-sm text-gray-500 mb-4 leading-relaxed">Multi-agent inbox with Customer 360 — journeys, bookings, orders, and conversation context in one panel. AI Copilot suggests replies from your knowledge base.</p>
        <ul class="space-y-2">
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Customer 360 unified sidebar</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>AI Copilot reply suggestions</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Chat assignment & agent handover</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Bookings, catalog & store sidebars</li>
        </ul>
      </div>
      <!-- Card 2 -->
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-xl" style="background:rgba(37,211,102,0.1);">📢</div>
        <h3 class="font-display font-700 text-lg mb-2">Campaigns & Broadcasts</h3>
        <p class="text-sm text-gray-500 mb-4 leading-relaxed">Three broadcast modes — file upload, contact groups, or quick phone lists — with delivery analytics and timezone-aware scheduling.</p>
        <ul class="space-y-2">
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>File, group & quick broadcasts</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Pause, resume & CSV reports</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Geographic delivery analytics</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>API-triggered campaigns</li>
        </ul>
      </div>
      <!-- Card 3 -->
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-xl" style="background:rgba(37,211,102,0.1);">🗂️</div>
        <h3 class="font-display font-700 text-lg mb-2">Contact CRM</h3>
        <p class="text-sm text-gray-500 mb-4 leading-relaxed">Segment contacts, run kanban journey pipelines with ready-made playbooks, and see every touchpoint from one CRM.</p>
        <ul class="space-y-2">
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Journey playbooks (sales, e-commerce, events)</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Groups, tags & custom fields</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Stage-triggered campaigns</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>CSV import & export</li>
        </ul>
      </div>
      <!-- Card 4 -->
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-xl" style="background:rgba(37,211,102,0.1);">⚡</div>
        <h3 class="font-display font-700 text-lg mb-2">Flow Automation</h3>
        <p class="text-sm text-gray-500 mb-4 leading-relaxed">Install curated flow templates in one click, or describe what you want and let the AI Flow Assistant draft automation for you to review.</p>
        <ul class="space-y-2">
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Flow template library (payments, booking, support)</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>AI Flow Assistant — natural language to draft</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Visual no-code flow builder</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>M-Pesa, HTTP, catalog & AI nodes</li>
        </ul>
      </div>
      <!-- Card 5 -->
      <div class="card-lift border rounded-2xl p-6 relative overflow-hidden" style="background:rgba(37,211,102,0.04);border-color:rgba(37,211,102,0.2);">
        <div class="absolute top-3 right-3 badge text-xs">New</div>
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-xl" style="background:rgba(37,211,102,0.15);">📋</div>
        <h3 class="font-display font-700 text-lg mb-2">WhatsApp Flows</h3>
        <p class="text-sm text-gray-500 mb-4 leading-relaxed">Native interactive forms that open inside WhatsApp. No link. No redirect. Pure WhatsApp.</p>
        <ul class="space-y-2">
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Drag-and-drop form builder</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Multi-screen, all input types</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>One-click publish to Meta</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Automation on form submission</li>
        </ul>
      </div>
      <!-- Card 6 -->
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-xl" style="background:rgba(37,211,102,0.1);">📞</div>
        <h3 class="font-display font-700 text-lg mb-2">WhatsApp Calling</h3>
        <p class="text-sm text-gray-500 mb-4 leading-relaxed">Inbound and outbound voice calls via the official WhatsApp Business API. Full call analytics included.</p>
        <ul class="space-y-2">
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Inbound & outbound voice calls</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Call routing & queuing</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Call analytics dashboard</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Agent performance reports</li>
        </ul>
      </div>
      <!-- Card 7 -->
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-xl" style="background:rgba(37,211,102,0.1);">💰</div>
        <h3 class="font-display font-700 text-lg mb-2">Invoices & Payments</h3>
        <p class="text-sm text-gray-500 mb-4 leading-relaxed">Share public invoice pages, collect M-Pesa or Stripe in-chat, and sync paid status back to contacts and journey stages automatically.</p>
        <ul class="space-y-2">
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Public invoice & payment links</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>M-Pesa STK push integration</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Stripe card payment links</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Auto-update CRM on payment</li>
        </ul>
      </div>
      <!-- Card 8 -->
      <div class="card-lift border rounded-2xl p-6 relative overflow-hidden" style="background:rgba(37,211,102,0.04);border-color:rgba(37,211,102,0.2);">
        <div class="absolute top-3 right-3 badge text-xs">Updated</div>
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-xl" style="background:rgba(37,211,102,0.15);">📅</div>
        <h3 class="font-display font-700 text-lg mb-2">Appointments & Events</h3>
        <p class="text-sm text-gray-500 mb-4 leading-relaxed">Full booking system with multi-staff scheduling, public widgets, events with seat limits, and automated WhatsApp reminders.</p>
        <ul class="space-y-2">
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Multi-staff & department scheduling</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Embeddable booking widgets & events</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Google Calendar sync & REST API</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>WhatsApp reminder campaigns</li>
        </ul>
      </div>
      <!-- Card 9 -->
      <div class="card-lift border rounded-2xl p-6 relative overflow-hidden" style="background:rgba(37,211,102,0.04);border-color:rgba(37,211,102,0.2);">
        <div class="absolute top-3 right-3 badge text-xs">Updated</div>
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-xl" style="background:rgba(37,211,102,0.15);">🛍️</div>
        <h3 class="font-display font-700 text-lg mb-2">Product Catalog & Shop</h3>
        <p class="text-sm text-gray-500 mb-4 leading-relaxed">Build branded WhatsApp shops, sync from Shopify or WooCommerce, and sell directly from the chat sidebar.</p>
        <ul class="space-y-2">
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Branded shop links & in-chat selling</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Store sync & inventory tracking</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Checkout via WhatsApp or invoice</li>
          <li class="flex items-start gap-2 text-sm text-gray-400"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Catalog analytics & A/B tests</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- ===== PLATFORM INTELLIGENCE ===== -->
<section id="platform" class="py-24 border-y border-white/5 relative overflow-hidden" style="background:rgba(37,211,102,0.02);">
  <div class="absolute inset-0 dots-bg opacity-40"></div>
  <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">Go live faster · Run smarter</div>
      <h2 class="font-display text-4xl sm:text-5xl font-800 mb-4">Operations intelligence<br/>built into the platform</h2>
      <p class="text-gray-400 max-w-2xl mx-auto">From guided activation to revenue dashboards and proactive health alerts — ConvoConnect helps you launch quickly and stay on top of what matters.</p>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl mb-4" style="background:rgba(37,211,102,0.1);">🚀</div>
        <h3 class="font-display font-700 text-base mb-2">Activation OS</h3>
        <p class="text-sm text-gray-500 leading-relaxed">Step-by-step onboarding wizard tracks WhatsApp setup, team invites, first flow, and first campaign — so owners go live in minutes, not days.</p>
      </div>
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl mb-4" style="background:rgba(37,211,102,0.1);">👤</div>
        <h3 class="font-display font-700 text-base mb-2">Customer 360</h3>
        <p class="text-sm text-gray-500 leading-relaxed">See journeys, bookings, orders, invoices, and conversation history in one inbox sidebar — agents never hunt across tabs again.</p>
      </div>
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl mb-4" style="background:rgba(37,211,102,0.1);">✨</div>
        <h3 class="font-display font-700 text-base mb-2">Agent Copilot</h3>
        <p class="text-sm text-gray-500 leading-relaxed">AI suggests on-brand replies from your knowledge base. Agents stay in control — accept, edit, or ignore every suggestion.</p>
      </div>
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl mb-4" style="background:rgba(37,211,102,0.1);">📊</div>
        <h3 class="font-display font-700 text-base mb-2">Revenue Dashboard</h3>
        <p class="text-sm text-gray-500 leading-relaxed">Pipeline value, paid invoices, and journey stage metrics on your home screen — see WhatsApp revenue at a glance.</p>
      </div>
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl mb-4" style="background:rgba(37,211,102,0.1);">🔔</div>
        <h3 class="font-display font-700 text-base mb-2">Health Monitor</h3>
        <p class="text-sm text-gray-500 leading-relaxed">Proactive alerts for disconnected numbers, failed webhooks, low credits, and stalled journeys — fix issues before customers notice.</p>
      </div>
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl mb-4" style="background:rgba(37,211,102,0.1);">👥</div>
        <h3 class="font-display font-700 text-base mb-2">Organization Managers</h3>
        <p class="text-sm text-gray-500 leading-relaxed">Invite managers with scoped module access — they run inbox and campaigns without full owner permissions.</p>
      </div>
    </div>
  </div>
</section>

<!-- ===== WHATSAPP FLOWS SPOTLIGHT ===== -->
<section class="py-24 border-y border-white/5" style="background:rgba(37,211,102,0.02);">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 grid lg:grid-cols-2 gap-16 items-center">
    <div>
      <div class="badge inline-flex mb-6">WhatsApp Native Forms</div>
      <h2 class="font-display text-4xl sm:text-5xl font-800 leading-tight mb-6">Forms that open<br/><span class="grad-text">inside WhatsApp.</span></h2>
      <p class="text-gray-400 leading-relaxed mb-8">WhatsApp Flows are interactive, multi-screen forms built by Meta that open natively inside WhatsApp — no links, no browsers, no friction. ConvoConnect gives you a drag-and-drop builder and one-click publish.</p>
      <ul class="space-y-4 mb-8">
        <li class="flex items-start gap-3">
          <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" style="background:rgba(37,211,102,0.15);">
            <svg class="w-3.5 h-3.5" style="color:#25D366;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
          </div>
          <div>
            <p class="font-semibold text-white text-sm">All input types supported</p>
            <p class="text-sm text-gray-500">Text fields, radio buttons, dropdowns, date pickers, checkboxes, and opt-in confirmations.</p>
          </div>
        </li>
        <li class="flex items-start gap-3">
          <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" style="background:rgba(37,211,102,0.15);">
            <svg class="w-3.5 h-3.5" style="color:#25D366;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
          </div>
          <div>
            <p class="font-semibold text-white text-sm">One-click publish to Meta</p>
            <p class="text-sm text-gray-500">Build in our editor and deploy directly to Meta's servers — no JSON editing or API calls needed.</p>
          </div>
        </li>
        <li class="flex items-start gap-3">
          <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" style="background:rgba(37,211,102,0.15);">
            <svg class="w-3.5 h-3.5" style="color:#25D366;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
          </div>
          <div>
            <p class="font-semibold text-white text-sm">Trigger automation on submit</p>
            <p class="text-sm text-gray-500">Every form submission fires a flow — send confirmations, update CRM, notify your team, or charge a payment.</p>
          </div>
        </li>
        <li class="flex items-start gap-3">
          <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" style="background:rgba(37,211,102,0.15);">
            <svg class="w-3.5 h-3.5" style="color:#25D366;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
          </div>
          <div>
            <p class="font-semibold text-white text-sm">Response dashboard & exports</p>
            <p class="text-sm text-gray-500">View every submission in a structured table. Export to CSV or push to your CRM automatically.</p>
          </div>
        </li>
      </ul>
      <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-5 py-3 text-sm font-semibold text-black rounded-xl" style="background:#25D366;">Build Your First Flow →</a>
    </div>

    <!-- Form Builder Mockup -->
    <div class="relative">
      <div class="bg-[#0d1a15] border border-white/[0.07] rounded-2xl overflow-hidden shadow-2xl">
        <!-- Toolbar -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-white/[0.06]" style="background:#0a1410;">
          <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-full bg-red-500 opacity-70"></div>
            <div class="w-3 h-3 rounded-full bg-yellow-400 opacity-70"></div>
            <div class="w-3 h-3 rounded-full opacity-70" style="background:#25D366;"></div>
          </div>
          <p class="text-xs text-gray-500 font-medium">Flow Builder — Lead Capture Form</p>
          <div class="badge text-xs">Published ✓</div>
        </div>
        <div class="flex" style="min-height:320px;">
          <!-- Sidebar -->
          <div class="w-36 border-r border-white/[0.06] px-3 py-3 space-y-1.5" style="background:#0a1410;">
            <p class="text-xs text-gray-600 uppercase tracking-wider mb-2" style="font-size:9px;">Components</p>
            <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs text-gray-400 hover:bg-white/5 cursor-pointer transition-colors">
              <span>📝</span> Text Input
            </div>
            <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs text-gray-400 hover:bg-white/5 cursor-pointer transition-colors">
              <span>🔘</span> Radio Group
            </div>
            <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs text-gray-400 hover:bg-white/5 cursor-pointer transition-colors">
              <span>▾</span> Dropdown
            </div>
            <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs text-gray-400 hover:bg-white/5 cursor-pointer transition-colors">
              <span>📅</span> Date Picker
            </div>
            <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs text-gray-400 hover:bg-white/5 cursor-pointer transition-colors">
              <span>☑️</span> Opt-in
            </div>
            <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-colors" style="color:#25D366;background:rgba(37,211,102,0.08);">
              <span>📸</span> Image
            </div>
          </div>
          <!-- Canvas -->
          <div class="flex-1 px-4 py-4 space-y-2.5">
            <p class="text-xs text-gray-600 mb-3" style="font-size:9px; text-transform:uppercase; letter-spacing:.08em;">Screen 1 of 2 · Preview</p>
            <!-- Form fields -->
            <div class="bg-[#162318] border border-white/[0.06] rounded-xl p-3 relative group cursor-pointer" style="border-left:3px solid #25D366;">
              <div class="flex items-center justify-between mb-1">
                <p class="text-xs text-gray-400 font-medium">Full Name</p>
                <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                  <span class="text-gray-600 cursor-pointer hover:text-white">✎</span>
                  <span class="text-gray-600 cursor-pointer hover:text-red-400">✕</span>
                </div>
              </div>
              <div class="h-6 bg-[#1f2c34] rounded-md border border-white/5"></div>
              <p class="text-xs text-gray-600 mt-1" style="font-size:9px;">Required · Text</p>
            </div>
            <div class="bg-[#1a1a2a] border border-white/[0.06] rounded-xl p-3 group cursor-pointer hover:border-white/10 transition-colors">
              <p class="text-xs text-gray-400 font-medium mb-1">Business Size</p>
              <div class="space-y-1">
                <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full border border-gray-600"></div><p class="text-xs text-gray-500">1–10 employees</p></div>
                <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full border flex items-center justify-center" style="border-color:#25D366;"><div class="w-1.5 h-1.5 rounded-full" style="background:#25D366;"></div></div><p class="text-xs text-gray-300">11–50 employees</p></div>
                <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full border border-gray-600"></div><p class="text-xs text-gray-500">50+ employees</p></div>
              </div>
            </div>
            <div class="bg-[#1a1a2a] border border-white/[0.06] rounded-xl p-3 group cursor-pointer hover:border-white/10 transition-colors">
              <p class="text-xs text-gray-400 font-medium mb-1">Demo Date</p>
              <div class="h-6 bg-[#1f2c34] rounded-md border border-white/5 flex items-center px-2">
                <p class="text-xs text-gray-600">MM / DD / YYYY</p>
              </div>
            </div>
            <!-- Submit -->
            <button class="w-full py-2 rounded-xl text-xs font-semibold text-black" style="background:#25D366;">Submit Form →</button>
          </div>
        </div>
        <!-- Footer -->
        <div class="flex items-center justify-between px-4 py-2.5 border-t border-white/[0.06]" style="background:#0a1410;">
          <p class="text-xs text-gray-600">Drag to reorder components</p>
          <div class="flex gap-2">
            <button class="text-xs px-3 py-1 rounded-lg border border-white/10 text-gray-400">Preview</button>
            <button class="text-xs px-3 py-1 rounded-lg text-black font-semibold" style="background:#25D366;">Publish to Meta</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== BOOKINGS SPOTLIGHT ===== -->
<section id="bookings" class="py-24 border-y border-white/5" style="background:rgba(37,211,102,0.02);">
  <div class="max-w-7xl mx-auto px-4 sm:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">Appointments & Events</div>
      <h2 class="font-display text-4xl sm:text-5xl font-800 mb-4">Book appointments.<br/><span class="grad-text">Run events. Reduce no-shows.</span></h2>
      <p class="text-gray-400 max-w-xl mx-auto">A complete booking platform inside ConvoConnect — multi-staff scheduling, public widgets, event registrations, Google Calendar sync, and WhatsApp reminders.</p>
    </div>

    <!-- 3-step flow -->
    <div class="grid md:grid-cols-3 gap-4 mb-16">
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6 text-center">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" style="background:rgba(37,211,102,0.1);">⚙️</div>
        <p class="font-display font-700 text-base mb-2">1. Set Up Services & Staff</p>
        <p class="text-sm text-gray-500 leading-relaxed">Define bookable services, departments, working hours, and team members. Choose round-robin, least-busy, or customer-picks-staff assignment.</p>
      </div>
      <div class="card-lift border rounded-2xl p-6 text-center" style="background:rgba(37,211,102,0.04);border-color:rgba(37,211,102,0.2);">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" style="background:rgba(37,211,102,0.15);">🌐</div>
        <p class="font-display font-700 text-base mb-2" style="color:#25D366;">2. Share Your Booking Page</p>
        <p class="text-sm text-gray-500 leading-relaxed">Embed a branded widget on your site or share a public booking link. Customers pick services, staff, and available slots — no app download needed.</p>
      </div>
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6 text-center">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" style="background:rgba(37,211,102,0.1);">🔔</div>
        <p class="font-display font-700 text-base mb-2">3. Automate Reminders</p>
        <p class="text-sm text-gray-500 leading-relaxed">Send WhatsApp utility-template reminders before and after appointments. Staff get notified on book, cancel, and reschedule — synced to Google Calendar.</p>
      </div>
    </div>

    <!-- Two-column detail -->
    <div class="grid lg:grid-cols-2 gap-12 items-center">
      <div>
        <h3 class="font-display text-2xl font-800 mb-6">Everything you need to manage bookings on WhatsApp</h3>
        <div class="space-y-4">
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">👥</div>
            <div>
              <p class="text-sm font-semibold text-white mb-0.5">Multi-staff & department scheduling</p>
              <p class="text-sm text-gray-500">Assign services to teams, set per-staff hours, and handle holiday closures. Smart assignment modes distribute bookings fairly.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">🎟️</div>
            <div>
              <p class="text-sm font-semibold text-white mb-0.5">Events with seat capacity</p>
              <p class="text-sm text-gray-500">Run workshops, classes, or webinars with multiple occurrences, party sizes, and registration management — all from one dashboard.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">💬</div>
            <div>
              <p class="text-sm font-semibold text-white mb-0.5">Bookings sidebar in chat</p>
              <p class="text-sm text-gray-500">Agents see a contact's upcoming appointments and event registrations right in the inbox sidebar — open chat from any booking detail page.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">🔌</div>
            <div>
              <p class="text-sm font-semibold text-white mb-0.5">REST API with scoped keys</p>
              <p class="text-sm text-gray-500">Integrate with your CRM or website using slot-based booking API. Scoped public keys keep your main account secure.</p>
            </div>
          </div>
        </div>
        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 mt-8 px-5 py-3 text-sm font-semibold text-black rounded-xl" style="background:#25D366;">Start Booking on WhatsApp →</a>
      </div>

      <!-- Booking widget mockup -->
      <div class="relative">
        <div class="bg-[#0d1a15] border border-white/[0.07] rounded-2xl overflow-hidden shadow-2xl">
          <div class="flex items-center justify-between px-4 py-3 border-b border-white/[0.06]" style="background:#0a1410;">
            <div class="flex items-center gap-2">
              <div class="w-3 h-3 rounded-full bg-red-500 opacity-70"></div>
              <div class="w-3 h-3 rounded-full bg-yellow-400 opacity-70"></div>
              <div class="w-3 h-3 rounded-full opacity-70" style="background:#25D366;"></div>
            </div>
            <p class="text-xs text-gray-500 font-medium">Booking Widget — StyleHive Salon</p>
            <div class="badge text-xs">Live</div>
          </div>
          <div class="px-5 py-5 space-y-4">
            <div class="text-center mb-2">
              <p class="text-sm font-semibold text-white">Book an Appointment</p>
              <p class="text-xs text-gray-500">Select a service and available time</p>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div class="flow-node px-3 py-2.5 text-center" style="border-color:rgba(37,211,102,0.4);background:rgba(37,211,102,0.08);">
                <p class="text-xs font-semibold" style="color:#25D366;">Premium Cut</p>
                <p class="text-xs text-gray-500">45 min · KES 2,500</p>
              </div>
              <div class="flow-node px-3 py-2.5 text-center">
                <p class="text-xs font-semibold text-white">Beard Trim</p>
                <p class="text-xs text-gray-500">20 min · KES 800</p>
              </div>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-2 uppercase tracking-wider" style="font-size:9px;">Available — Sat, Jun 21</p>
              <div class="grid grid-cols-3 gap-2">
                <div class="flow-node px-2 py-1.5 text-center text-xs text-gray-500">10:00</div>
                <div class="flow-node px-2 py-1.5 text-center text-xs font-semibold" style="color:#25D366;border-color:rgba(37,211,102,0.4);">14:00</div>
                <div class="flow-node px-2 py-1.5 text-center text-xs text-gray-500">16:30</div>
              </div>
            </div>
            <div class="flex items-center gap-3 px-3 py-2 rounded-xl" style="background:rgba(37,211,102,0.06);border:1px solid rgba(37,211,102,0.15);">
              <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold" style="background:linear-gradient(135deg,#25D366,#075E54);">SM</div>
              <div>
                <p class="text-xs font-semibold text-white">Sarah Mwangi</p>
                <p class="text-xs text-gray-500">Senior Stylist</p>
              </div>
            </div>
            <button class="w-full py-2.5 rounded-xl text-xs font-semibold text-black" style="background:#25D366;">Confirm Booking →</button>
          </div>
          <div class="px-4 py-2.5 border-t border-white/[0.06] flex items-center justify-between" style="background:#0a1410;">
            <p class="text-xs text-gray-600">Powered by ConvoConnect</p>
            <div class="flex gap-2">
              <div class="badge text-xs">Calendar ✓</div>
              <div class="badge text-xs">Reminders ✓</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== CATALOG SPOTLIGHT ===== -->
<section id="catalog" class="py-24 border-y border-white/5" style="background:rgba(18,140,126,0.02);">
  <div class="max-w-7xl mx-auto px-4 sm:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">Product Catalog & Commerce</div>
      <h2 class="font-display text-4xl sm:text-5xl font-800 mb-4">Import products. Sell inside<br/><span class="grad-text">WhatsApp.</span></h2>
      <p class="text-gray-400 max-w-xl mx-auto">Import from Excel, Shopify, or WooCommerce with live inventory sync. Share branded shop links, sell from the chat sidebar, and collect payment via invoice or M-Pesa.</p>
    </div>

    <!-- 3-step flow -->
    <div class="grid md:grid-cols-3 gap-4 mb-16">
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6 text-center">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" style="background:rgba(37,211,102,0.1);">🛒</div>
        <p class="font-display font-700 text-base mb-2">1. Import Your Catalog</p>
        <p class="text-sm text-gray-500 leading-relaxed">Import from Shopify, WooCommerce, Excel, or your own API. Ongoing store sync keeps prices and stock up to date automatically.</p>
      </div>
      <div class="card-lift border rounded-2xl p-6 text-center" style="background:rgba(37,211,102,0.04);border-color:rgba(37,211,102,0.2);">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" style="background:rgba(37,211,102,0.15);">⚡</div>
        <p class="font-display font-700 text-base mb-2" style="color:#25D366;">2. Sell from Chat or Flows</p>
        <p class="text-sm text-gray-500 leading-relaxed">Send shop links or individual products from the inbox sidebar. Add catalog nodes to flows — checkout resumes the automation automatically.</p>
      </div>
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6 text-center">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" style="background:rgba(37,211,102,0.1);">💳</div>
        <p class="font-display font-700 text-base mb-2">3. Invoice & Collect Payment</p>
        <p class="text-sm text-gray-500 leading-relaxed">Generate a PDF invoice and send a payment link in the same conversation. Accept M-Pesa STK or Stripe cards — confirmed in seconds.</p>
      </div>
    </div>

    <!-- Two-column detail -->
    <div class="grid lg:grid-cols-2 gap-12 items-center">
      <!-- Left: features list -->
      <div>
        <h3 class="font-display text-2xl font-800 mb-6">Everything you need to run commerce on WhatsApp</h3>
        <div class="space-y-4">
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">📦</div>
            <div>
              <p class="text-sm font-semibold text-white mb-0.5">Branded WhatsApp shops</p>
              <p class="text-sm text-gray-500">Create catalogs with images, variants, and collections. Share at /shop/yourbrand — customers browse and add to cart without leaving WhatsApp.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">💬</div>
            <div>
              <p class="text-sm font-semibold text-white mb-0.5">Catalog sidebar in chat</p>
              <p class="text-sm text-gray-500">Search and send individual products or shop links directly from the inbox. Agents close sales without switching tools.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">🔄</div>
            <div>
              <p class="text-sm font-semibold text-white mb-0.5">Live store sync & inventory</p>
              <p class="text-sm text-gray-500">Shopify and WooCommerce webhooks keep stock accurate. Inventory reservations prevent overselling during checkout.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">🧾</div>
            <div>
              <p class="text-sm font-semibold text-white mb-0.5">Checkout & payments</p>
              <p class="text-sm text-gray-500">Customers checkout via WhatsApp message or auto-generated invoice. M-Pesa STK and Stripe links confirm payment and trigger next flow steps.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">📊</div>
            <div>
              <p class="text-sm font-semibold text-white mb-0.5">Analytics & A/B experiments</p>
              <p class="text-sm text-gray-500">Track views, cart adds, and checkouts. Run weighted catalog experiments to optimize which products convert best.</p>
            </div>
          </div>
        </div>
        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 mt-8 px-5 py-3 text-sm font-semibold text-black rounded-xl" style="background:#25D366;">Start Selling on WhatsApp →</a>
      </div>

      <!-- Right: mockup -->
      <div class="relative">
        <div class="bg-[#0d1a15] border border-white/[0.07] rounded-2xl overflow-hidden shadow-2xl">
          <!-- Bar -->
          <div class="flex items-center justify-between px-4 py-3 border-b border-white/[0.06]" style="background:#0a1410;">
            <div class="flex items-center gap-2">
              <div class="w-3 h-3 rounded-full bg-red-500 opacity-70"></div>
              <div class="w-3 h-3 rounded-full bg-yellow-400 opacity-70"></div>
              <div class="w-3 h-3 rounded-full opacity-70" style="background:#25D366;"></div>
            </div>
            <p class="text-xs text-gray-500 font-medium">Flow Builder — Product Order Flow</p>
            <div class="badge text-xs">Live</div>
          </div>
          <!-- Flow nodes -->
          <div class="px-6 py-5 space-y-0 flex flex-col items-center">
            <div class="flow-node px-4 py-3 w-full text-center">
              <div class="flex items-center gap-2 justify-center mb-0.5">
                <span class="text-base">💬</span>
                <p class="text-xs font-semibold text-white">Keyword: "shop"</p>
              </div>
              <p class="text-xs text-gray-500">Trigger when customer types shop</p>
            </div>
            <div class="flow-line"></div>
            <div class="flow-node px-4 py-3 w-full text-center" style="border-color:rgba(37,211,102,0.3);">
              <div class="flex items-center gap-2 justify-center mb-0.5">
                <span class="text-base">🛒</span>
                <p class="text-xs font-semibold" style="color:#25D366;">Product Catalog</p>
              </div>
              <p class="text-xs text-gray-500">Show catalog · customer selects items</p>
            </div>
            <div class="flow-line"></div>
            <div class="flow-node px-4 py-3 w-full text-center">
              <div class="flex items-center gap-2 justify-center mb-0.5">
                <span class="text-base">🧾</span>
                <p class="text-xs font-semibold text-white">Generate Invoice</p>
              </div>
              <p class="text-xs text-gray-500">Auto-create PDF invoice from order</p>
            </div>
            <div class="flow-line"></div>
            <div class="grid grid-cols-2 gap-3 w-full">
              <div class="flow-node px-3 py-3 text-center">
                <span class="text-sm">📱</span>
                <p class="text-xs font-semibold text-white mt-1">M-Pesa STK</p>
                <p class="text-xs text-gray-500">Push to phone</p>
              </div>
              <div class="flow-node px-3 py-3 text-center">
                <span class="text-sm">💳</span>
                <p class="text-xs font-semibold text-white mt-1">Stripe Link</p>
                <p class="text-xs text-gray-500">Card payment</p>
              </div>
            </div>
            <div class="flow-line"></div>
            <div class="flow-node px-4 py-3 w-full text-center" style="background:rgba(37,211,102,0.08);border-color:rgba(37,211,102,0.3);">
              <div class="flex items-center gap-2 justify-center mb-0.5">
                <span class="text-base">✅</span>
                <p class="text-xs font-semibold" style="color:#25D366;">Payment Confirmed</p>
              </div>
              <p class="text-xs text-gray-500">Send receipt · update order status</p>
            </div>
          </div>
          <div class="px-4 py-2.5 border-t border-white/[0.06] flex items-center justify-between" style="background:#0a1410;">
            <p class="text-xs text-gray-600">Drag to add more nodes</p>
            <div class="flex gap-2">
              <div class="badge text-xs">Catalog ✓</div>
              <div class="badge text-xs">Payments ✓</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== AUTOMATION SECTION ===== -->
<section id="automation" class="py-24 relative overflow-hidden" style="background:#050f0b;">
  <div class="absolute inset-0 grid-pattern"></div>
  <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-px" style="background:linear-gradient(90deg,transparent,rgba(37,211,102,0.3),transparent);"></div>
  <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">Workflow Automation</div>
      <h2 class="font-display text-4xl sm:text-5xl font-800 mb-4">Orchestrate every<br/>customer touchpoint</h2>
      <p class="text-gray-400 max-w-xl mx-auto">Build sales, support, and onboarding flows visually — start from curated templates or describe what you need and let the AI Flow Assistant draft it for you.</p>
    </div>

    <div class="grid lg:grid-cols-2 gap-12 items-center">
      <!-- Flow diagram -->
      <div class="space-y-0">
        <div class="flex flex-col items-center">
          <div class="flow-node px-5 py-3 w-64 text-center">
            <div class="flex items-center gap-2 justify-center mb-1">
              <span class="text-lg">⚡</span>
              <p class="text-sm font-semibold text-white">Trigger Node</p>
            </div>
            <p class="text-xs text-gray-500">Keyword "book" received</p>
          </div>
          <div class="flow-line"></div>
          <div class="flow-node px-5 py-3 w-64 text-center">
            <div class="flex items-center gap-2 justify-center mb-1">
              <span class="text-lg">💬</span>
              <p class="text-sm font-semibold text-white">Send Message</p>
            </div>
            <p class="text-xs text-gray-500">"Hi! Ready to book? Fill our form 👇"</p>
          </div>
          <div class="flow-line"></div>
          <div class="flow-node px-5 py-3 w-64 text-center" style="border-color:rgba(37,211,102,0.35);">
            <div class="flex items-center gap-2 justify-center mb-1">
              <span class="text-lg">📋</span>
              <p class="text-sm font-semibold" style="color:#25D366;">WhatsApp Flow</p>
            </div>
            <p class="text-xs text-gray-500">Open booking widget in-chat</p>
          </div>
          <div class="flow-line"></div>
          <div class="grid grid-cols-2 gap-4 w-full max-w-sm">
            <div class="flow-node px-3 py-3 text-center">
              <span class="text-sm">✅</span>
              <p class="text-xs font-semibold text-white mt-1">Confirmed</p>
              <p class="text-xs text-gray-500">Send receipt</p>
            </div>
            <div class="flow-node px-3 py-3 text-center">
              <span class="text-sm">🔁</span>
              <p class="text-xs font-semibold text-white mt-1">Retry</p>
              <p class="text-xs text-gray-500">Send reminder</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Node types -->
      <div class="space-y-3">
        <h3 class="font-display font-700 text-xl mb-4">6 powerful node types</h3>
        <div class="flow-node px-4 py-3.5 flex items-start gap-4">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg flex-shrink-0" style="background:rgba(37,211,102,0.1);">⚡</div>
          <div>
            <p class="text-sm font-semibold text-white mb-0.5">Trigger Nodes</p>
            <p class="text-xs text-gray-500">Keyword match, incoming message, button reply, flow submission, campaign delivery, scheduled time, or API call.</p>
          </div>
        </div>
        <div class="flow-node px-4 py-3.5 flex items-start gap-4">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg flex-shrink-0" style="background:rgba(37,211,102,0.1);">💬</div>
          <div>
            <p class="text-sm font-semibold text-white mb-0.5">Message Nodes</p>
            <p class="text-xs text-gray-500">Send text, images, video, documents, buttons, lists, and approved Meta message templates.</p>
          </div>
        </div>
        <div class="flow-node px-4 py-3.5 flex items-start gap-4">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg flex-shrink-0" style="background:rgba(37,211,102,0.1);">🔀</div>
          <div>
            <p class="text-sm font-semibold text-white mb-0.5">Condition Nodes</p>
            <p class="text-xs text-gray-500">Branch based on contact fields, message content, time of day, payment status, or previous flow steps.</p>
          </div>
        </div>
        <div class="flow-node px-4 py-3.5 flex items-start gap-4">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg flex-shrink-0" style="background:rgba(37,211,102,0.1);">⏱️</div>
          <div>
            <p class="text-sm font-semibold text-white mb-0.5">Delay & Wait Nodes</p>
            <p class="text-xs text-gray-500">Wait minutes, hours, or days. Wait for a user reply. Wait for a specific keyword or event before continuing.</p>
          </div>
        </div>
        <div class="flow-node px-4 py-3.5 flex items-start gap-4">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg flex-shrink-0" style="background:rgba(37,211,102,0.1);">🔗</div>
          <div>
            <p class="text-sm font-semibold text-white mb-0.5">Action & Webhook Nodes</p>
            <p class="text-xs text-gray-500">Update contact fields, tag contacts, assign to agent, send webhook to your system, or trigger another flow.</p>
          </div>
        </div>
        <div class="flow-node px-4 py-3.5 flex items-start gap-4">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg flex-shrink-0" style="background:rgba(37,211,102,0.1);">🤖</div>
          <div>
            <p class="text-sm font-semibold text-white mb-0.5">AI Flow Nodes</p>
            <p class="text-xs text-gray-500">Deflect tier-1 FAQs using your docs and knowledge base — then hand off to an agent in the shared inbox when needed.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== INTEGRATIONS ===== -->
<section id="integrations" class="py-24 relative">
  <div class="max-w-7xl mx-auto px-4 sm:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">Integrations</div>
      <h2 class="font-display text-4xl sm:text-5xl font-800 mb-4">Connects with<br/>your stack</h2>
      <p class="text-gray-400 max-w-xl mx-auto">ConvoConnect plugs into your existing tools — e-commerce, payments, email, and more. The Integration Hub centralizes connector setup in one place.</p>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-12">
      <div class="integration-card rounded-2xl p-5 flex flex-col items-center text-center gap-2.5">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl" style="background:rgba(149,191,71,0.1);">🛍️</div>
        <div><p class="text-sm font-semibold text-white">Shopify</p><p class="text-xs text-gray-500 mt-0.5">Products, orders & abandoned cart</p></div>
      </div>
      <div class="integration-card rounded-2xl p-5 flex flex-col items-center text-center gap-2.5">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl" style="background:rgba(150,88,183,0.1);">🛒</div>
        <div><p class="text-sm font-semibold text-white">WooCommerce</p><p class="text-xs text-gray-500 mt-0.5">Products, orders & webhooks</p></div>
      </div>
      <div class="integration-card rounded-2xl p-5 flex flex-col items-center text-center gap-2.5">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl" style="background:rgba(99,91,255,0.1);">💳</div>
        <div><p class="text-sm font-semibold text-white">Stripe</p><p class="text-xs text-gray-500 mt-0.5">Card payments & subscriptions</p></div>
      </div>
      <div class="integration-card rounded-2xl p-5 flex flex-col items-center text-center gap-2.5">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl" style="background:rgba(0,150,64,0.1);">📱</div>
        <div><p class="text-sm font-semibold text-white">M-Pesa</p><p class="text-xs text-gray-500 mt-0.5">STK push & C2B payments</p></div>
      </div>
      <div class="integration-card rounded-2xl p-5 flex flex-col items-center text-center gap-2.5">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl" style="background:rgba(245,47,47,0.1);">📨</div>
        <div><p class="text-sm font-semibold text-white">Twilio SMS</p><p class="text-xs text-gray-500 mt-0.5">SMS fallback & notifications</p></div>
      </div>
      <div class="integration-card rounded-2xl p-5 flex flex-col items-center text-center gap-2.5">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl" style="background:rgba(37,99,235,0.1);">📧</div>
        <div><p class="text-sm font-semibold text-white">SMTP Email</p><p class="text-xs text-gray-500 mt-0.5">Transactional email delivery</p></div>
      </div>
      <div class="integration-card rounded-2xl p-5 flex flex-col items-center text-center gap-2.5">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl" style="background:rgba(24,119,242,0.1);">🔵</div>
        <div><p class="text-sm font-semibold text-white">Meta Embedded</p><p class="text-xs text-gray-500 mt-0.5">One-click WhatsApp onboarding</p></div>
      </div>
      <div class="integration-card rounded-2xl p-5 flex flex-col items-center text-center gap-2.5">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl" style="background:rgba(37,211,102,0.1);">🌐</div>
        <div><p class="text-sm font-semibold text-white">Chat Widget</p><p class="text-xs text-gray-500 mt-0.5">Embeddable WhatsApp button</p></div>
      </div>
    </div>

    <!-- Widget code snippet -->
    <div class="max-w-2xl mx-auto">
      <p class="text-sm text-gray-400 mb-3 font-medium">Add WhatsApp chat to your website in 2 lines:</p>
      <div class="code-block p-4 overflow-x-auto">
        <pre class="text-sm"><code><span style="color:#6a9955;">// Paste before &lt;/body&gt;</span>
<span style="color:#569cd6;">&lt;script</span> <span style="color:#9cdcfe;">src</span><span style="color:#d4d4d4;">=</span><span style="color:#ce9178;">"https://cdn.convoconnect.io/widget.js"</span><span style="color:#569cd6;">&gt;&lt;/script&gt;</span>
<span style="color:#569cd6;">&lt;script&gt;</span>
  <span style="color:#dcdcaa;">ConvoWidget</span><span style="color:#d4d4d4;">.</span><span style="color:#dcdcaa;">init</span><span style="color:#d4d4d4;">({</span>
    <span style="color:#9cdcfe;">phone</span><span style="color:#d4d4d4;">: </span><span style="color:#ce9178;">"254700000000"</span><span style="color:#d4d4d4;">,</span>
    <span style="color:#9cdcfe;">message</span><span style="color:#d4d4d4;">: </span><span style="color:#ce9178;">"Hi! I'd like to know more."</span><span style="color:#d4d4d4;">,</span>
    <span style="color:#9cdcfe;">color</span><span style="color:#d4d4d4;">: </span><span style="color:#ce9178;">"#25D366"</span>
  <span style="color:#d4d4d4;">});</span>
<span style="color:#569cd6;">&lt;/script&gt;</span></code></pre>
      </div>
    </div>
  </div>
</section>

<!-- ===== API SECTION ===== -->
<section id="api" class="py-24 relative overflow-hidden" style="background:#030a07;">
  <div class="absolute inset-0 grid-pattern opacity-60"></div>
  <div class="absolute top-0 left-0 right-0 h-px" style="background:linear-gradient(90deg,transparent,rgba(37,211,102,0.2),transparent);"></div>
  <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">Developer API</div>
      <h2 class="font-display text-4xl sm:text-5xl font-800 mb-4">Build anything<br/><span class="grad-text">with our REST API</span></h2>
      <p class="text-gray-400 max-w-xl mx-auto">Every feature available in the UI is also accessible via API. Send messages, manage contacts, trigger campaigns, book appointments, and collect payments programmatically.</p>
    </div>

    <div class="grid lg:grid-cols-2 gap-12 items-start">
      <!-- Endpoints list -->
      <div class="space-y-2">
        <h3 class="font-display font-700 text-lg mb-5">Core endpoints</h3>
        <div class="space-y-2">
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/[0.02] border border-white/[0.05]">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(37,211,102,0.15);color:#25D366;">POST</span>
            <code class="text-sm text-gray-300">/v1/messages/send</code>
            <span class="text-xs text-gray-600 ml-auto">Send message</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/[0.02] border border-white/[0.05]">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(37,211,102,0.15);color:#25D366;">POST</span>
            <code class="text-sm text-gray-300">/v1/messages/template</code>
            <span class="text-xs text-gray-600 ml-auto">Send template</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/[0.02] border border-white/[0.05]">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(59,130,246,0.15);color:#60a5fa;">GET</span>
            <code class="text-sm text-gray-300">/v1/contacts</code>
            <span class="text-xs text-gray-600 ml-auto">List contacts</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/[0.02] border border-white/[0.05]">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(37,211,102,0.15);color:#25D366;">POST</span>
            <code class="text-sm text-gray-300">/v1/contacts</code>
            <span class="text-xs text-gray-600 ml-auto">Create contact</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/[0.02] border border-white/[0.05]">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(37,211,102,0.15);color:#25D366;">POST</span>
            <code class="text-sm text-gray-300">/v1/campaigns/trigger</code>
            <span class="text-xs text-gray-600 ml-auto">Trigger campaign</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/[0.02] border border-white/[0.05]">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(37,211,102,0.15);color:#25D366;">POST</span>
            <code class="text-sm text-gray-300">/v1/flows/trigger</code>
            <span class="text-xs text-gray-600 ml-auto">Trigger automation flow</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/[0.02] border border-white/[0.05]">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(37,211,102,0.15);color:#25D366;">POST</span>
            <code class="text-sm text-gray-300">/api/bookings/appointments</code>
            <span class="text-xs text-gray-600 ml-auto">Create booking</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/[0.02] border border-white/[0.05]">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(59,130,246,0.15);color:#60a5fa;">GET</span>
            <code class="text-sm text-gray-300">/api/bookings/availability</code>
            <span class="text-xs text-gray-600 ml-auto">Get available slots</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/[0.02] border border-white/[0.05]">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(37,211,102,0.15);color:#25D366;">POST</span>
            <code class="text-sm text-gray-300">/v1/payments/mpesa</code>
            <span class="text-xs text-gray-600 ml-auto">Initiate M-Pesa STK</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/[0.02] border border-white/[0.05]">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(251,146,60,0.15);color:#fb923c;">DEL</span>
            <code class="text-sm text-gray-300">/v1/contacts/:id</code>
            <span class="text-xs text-gray-600 ml-auto">Delete contact</span>
          </div>
        </div>
        <div class="pt-4">
          <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-black rounded-xl" style="background:#25D366;">Get Your API Key →</a>
        </div>
      </div>

      <!-- Code snippet -->
      <div>
        <h3 class="font-display font-700 text-lg mb-5">Send a message in seconds</h3>
        <div class="code-block p-5 overflow-x-auto">
          <pre class="text-sm leading-relaxed"><code><span style="color:#569cd6;">const</span> <span style="color:#9cdcfe;">response</span> <span style="color:#d4d4d4;">= </span><span style="color:#569cd6;">await</span> <span style="color:#dcdcaa;">fetch</span><span style="color:#d4d4d4;">(</span>
  <span style="color:#ce9178;">"https://api.convoconnect.io/v1/messages/send"</span><span style="color:#d4d4d4;">,</span>
  <span style="color:#d4d4d4;">{</span>
    <span style="color:#9cdcfe;">method</span><span style="color:#d4d4d4;">: </span><span style="color:#ce9178;">"POST"</span><span style="color:#d4d4d4;">,</span>
    <span style="color:#9cdcfe;">headers</span><span style="color:#d4d4d4;">: {</span>
      <span style="color:#ce9178;">"Authorization"</span><span style="color:#d4d4d4;">: </span><span style="color:#ce9178;">`Bearer ${API_KEY}`</span><span style="color:#d4d4d4;">,</span>
      <span style="color:#ce9178;">"Content-Type"</span><span style="color:#d4d4d4;">: </span><span style="color:#ce9178;">"application/json"</span>
    <span style="color:#d4d4d4;">},</span>
    <span style="color:#9cdcfe;">body</span><span style="color:#d4d4d4;">: </span><span style="color:#dcdcaa;">JSON</span><span style="color:#d4d4d4;">.</span><span style="color:#dcdcaa;">stringify</span><span style="color:#d4d4d4;">({</span>
      <span style="color:#9cdcfe;">to</span><span style="color:#d4d4d4;">: </span><span style="color:#ce9178;">"254700000000"</span><span style="color:#d4d4d4;">,</span>
      <span style="color:#9cdcfe;">type</span><span style="color:#d4d4d4;">: </span><span style="color:#ce9178;">"text"</span><span style="color:#d4d4d4;">,</span>
      <span style="color:#9cdcfe;">text</span><span style="color:#d4d4d4;">: {</span>
        <span style="color:#9cdcfe;">body</span><span style="color:#d4d4d4;">: </span><span style="color:#ce9178;">"Hello from ConvoConnect! 👋"</span>
      <span style="color:#d4d4d4;">}</span>
    <span style="color:#d4d4d4;">})</span>
  <span style="color:#d4d4d4;">}</span>
<span style="color:#d4d4d4;">);</span>

<span style="color:#569cd6;">const</span> <span style="color:#9cdcfe;">data</span> <span style="color:#d4d4d4;">= </span><span style="color:#569cd6;">await</span> <span style="color:#9cdcfe;">response</span><span style="color:#d4d4d4;">.</span><span style="color:#dcdcaa;">json</span><span style="color:#d4d4d4;">();</span>
<span style="color:#dcdcaa;">console</span><span style="color:#d4d4d4;">.</span><span style="color:#dcdcaa;">log</span><span style="color:#d4d4d4;">(</span><span style="color:#9cdcfe;">data</span><span style="color:#d4d4d4;">.</span><span style="color:#9cdcfe;">messageId</span><span style="color:#d4d4d4;">);</span>
<span style="color:#6a9955;">// → "wamid.HBgN2547..."</span></code></pre>
        </div>
        <div class="mt-4 flex items-center gap-3 p-3 rounded-xl" style="background:rgba(37,211,102,0.05);border:1px solid rgba(37,211,102,0.1);">
          <div class="w-2 h-2 rounded-full flex-shrink-0" style="background:#25D366;"></div>
          <p class="text-xs text-gray-400">Webhooks available for inbound messages, delivery receipts, flow submissions, and payment events.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== PRICING ===== -->
<section id="pricing" class="py-24">
  <div class="max-w-7xl mx-auto px-4 sm:px-6">
    <div class="text-center mb-12">
      <div class="badge inline-flex mb-4">Pricing</div>
      <h2 class="font-display text-4xl sm:text-5xl font-800 mb-4">Simple, transparent pricing</h2>
      <p class="text-gray-400">No surprise bills. Pick a plan, connect your WhatsApp number, and grow.</p>
    </div>

    @if(isset($plans) && $plans->count() > 0)
    @php $planCount = $plans->count(); @endphp
    <div class="grid gap-4 {{ $planCount === 1 ? 'max-w-sm mx-auto' : ($planCount === 2 ? 'md:grid-cols-2 max-w-2xl mx-auto' : 'md:grid-cols-3') }}">
      @foreach($plans as $loopIndex => $plan)
      @php
        $isPopular = $planCount >= 3 && $loopIndex === intval(floor($planCount / 2));
        $currencySymbol = config('money')[strtoupper(config('settings.cashier_currency', 'usd'))]['symbol'] ?? '$';
        $periodLabel = ($plan->period == 1) ? 'month' : 'year';
        $features = array_filter(array_map('trim', explode(',', $plan->features ?? '')));
      @endphp

      @if($isPopular)
      <div class="pricing-popular card-lift rounded-2xl p-7 relative">
        <div class="absolute -top-3 left-1/2 -translate-x-1/2">
          <span class="badge font-semibold" style="background:rgba(37,211,102,0.2);">Most Popular</span>
        </div>
        <div class="mb-6">
          <p class="text-sm mb-1 font-medium" style="color:#25D366;">{{ $plan->name }}</p>
          <div class="flex items-baseline gap-1">
            <span class="text-gray-400 text-lg font-semibold self-start mt-1">{{ $currencySymbol }}</span>
            <span class="font-display text-4xl font-800 text-white">{{ number_format($plan->price, 0) }}</span>
            <span class="text-gray-400 text-sm">/ {{ $periodLabel }}</span>
          </div>
          @if($plan->description)
          <p class="text-xs text-gray-500 mt-2 leading-relaxed">{{ $plan->description }}</p>
          @endif
        </div>
        @if(!config('settings.disable_registration_page', false))
        <a href="{{ route('register') }}" class="block w-full text-center py-2.5 rounded-xl text-sm font-semibold text-black transition-all hover:opacity-90 mb-6" style="background:#25D366;">Get Started</a>
        @endif
        @if(count($features) > 0)
        <ul class="space-y-2.5">
          @foreach($features as $feature)
          <li class="flex items-center gap-2.5 text-sm text-gray-300">
            <svg class="w-4 h-4 flex-shrink-0" style="color:#25D366;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ $feature }}
          </li>
          @endforeach
        </ul>
        @endif
        <p class="text-xs text-gray-600 mt-4 text-center">No contracts · Cancel anytime</p>
      </div>
      @else
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-7">
        <div class="mb-6">
          <p class="text-sm text-gray-400 mb-1 font-medium">{{ $plan->name }}</p>
          <div class="flex items-baseline gap-1">
            <span class="text-gray-500 text-lg font-semibold self-start mt-1">{{ $currencySymbol }}</span>
            <span class="font-display text-4xl font-800 text-white">{{ number_format($plan->price, 0) }}</span>
            <span class="text-gray-500 text-sm">/ {{ $periodLabel }}</span>
          </div>
          @if($plan->description)
          <p class="text-xs text-gray-600 mt-2 leading-relaxed">{{ $plan->description }}</p>
          @endif
        </div>
        @if(!config('settings.disable_registration_page', false))
        <a href="{{ route('register') }}" class="block w-full text-center py-2.5 rounded-xl border border-white/10 text-sm font-semibold text-white hover:bg-white/5 transition-all mb-6">Get Started</a>
        @endif
        @if(count($features) > 0)
        <ul class="space-y-2.5">
          @foreach($features as $feature)
          <li class="flex items-center gap-2.5 text-sm text-gray-400">
            <svg class="w-4 h-4 flex-shrink-0" style="color:#25D366;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ $feature }}
          </li>
          @endforeach
        </ul>
        @endif
        <p class="text-xs text-gray-600 mt-4 text-center">No contracts · Cancel anytime</p>
      </div>
      @endif

      @endforeach
    </div>
    @else
    <p class="text-center text-gray-500 text-sm">No pricing plans available at this time.</p>
    @endif
  </div>
</section>

<!-- ===== TESTIMONIALS ===== -->
<section class="py-24 border-t border-white/5" style="background:rgba(37,211,102,0.02);">
  <div class="max-w-7xl mx-auto px-4 sm:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">Testimonials</div>
      <h2 class="font-display text-4xl sm:text-5xl font-800">Loved by businesses<br/>across Africa & beyond</h2>
    </div>
    <div class="grid md:grid-cols-3 gap-4">
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6">
        <div class="flex text-yellow-400 mb-4 text-sm">★★★★★</div>
        <p class="text-gray-300 text-sm leading-relaxed mb-5">"The new booking widgets cut our no-show rate in half. Customers book directly from WhatsApp, get automatic reminders, and our stylists see everything synced to Google Calendar. Game changer for salons."</p>
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-full flex items-center justify-center font-semibold text-sm" style="background:linear-gradient(135deg,#25D366,#075E54);">AM</div>
          <div><p class="text-sm font-semibold text-white">Amina Mwangi</p><p class="text-xs text-gray-500">CEO, StyleHive Kenya</p></div>
        </div>
      </div>
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6">
        <div class="flex text-yellow-400 mb-4 text-sm">★★★★★</div>
        <p class="text-gray-300 text-sm leading-relaxed mb-5">"File broadcasts with per-row template variables saved us hours. We upload a CSV, map columns to contact fields, and reach 10,000 customers with personalized messages — pause and resume whenever we need."</p>
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-full flex items-center justify-center font-semibold text-sm" style="background:linear-gradient(135deg,#4facfe,#00f2fe);">DO</div>
          <div><p class="text-sm font-semibold text-white">David Okonkwo</p><p class="text-xs text-gray-500">Founder, WhatsApp Agency (Lagos)</p></div>
        </div>
      </div>
      <div class="card-lift bg-white/[0.02] border border-white/[0.06] rounded-2xl p-6">
        <div class="flex text-yellow-400 mb-4 text-sm">★★★★★</div>
        <p class="text-gray-300 text-sm leading-relaxed mb-5">"We sell products straight from the chat sidebar now — search catalog, send a product link, customer checks out on WhatsApp. Replaced our separate e-commerce chat tool entirely."</p>
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-full flex items-center justify-center font-semibold text-sm" style="background:linear-gradient(135deg,#667eea,#764ba2);">TN</div>
          <div><p class="text-sm font-semibold text-white">Taiwo Nwosu</p><p class="text-xs text-gray-500">Head of CX, PayStack Partner</p></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== FAQ ===== -->
<section id="faq" class="py-24 border-t border-white/5">
  <div class="max-w-3xl mx-auto px-4 sm:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">FAQ</div>
      <h2 class="font-display text-4xl sm:text-5xl font-800">Common questions</h2>
    </div>

    <div class="space-y-2" x-data="{ open: null }">
      <div class="border border-white/[0.06] rounded-2xl overflow-hidden bg-white/[0.02]">
        <button class="w-full flex items-center justify-between px-6 py-4 text-left" @click="open = open === 1 ? null : 1">
          <span class="text-sm font-semibold text-white">Do I need a WhatsApp Business API account to use ConvoConnect?</span>
          <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform" :class="open === 1 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div x-show="open === 1" x-cloak class="px-6 pb-4">
          <p class="text-sm text-gray-400 leading-relaxed">No — ConvoConnect is built on the official Meta WhatsApp Business Cloud API. Our Activation OS walks you through Embedded Signup, team setup, and your first flow — most businesses send their first reply within 15 minutes.</p>
        </div>
      </div>
      <div class="border border-white/[0.06] rounded-2xl overflow-hidden bg-white/[0.02]">
        <button class="w-full flex items-center justify-between px-6 py-4 text-left" @click="open = open === 2 ? null : 2">
          <span class="text-sm font-semibold text-white">Can I connect multiple WhatsApp numbers to one account?</span>
          <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform" :class="open === 2 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div x-show="open === 2" x-cloak class="px-6 pb-4">
          <p class="text-sm text-gray-400 leading-relaxed">Absolutely. On the Growth plan you can connect up to 3 numbers, and on Scale there's no limit. Each number has its own inbox, flows, campaigns, and contacts — but you manage everything from a single dashboard.</p>
        </div>
      </div>
      <div class="border border-white/[0.06] rounded-2xl overflow-hidden bg-white/[0.02]">
        <button class="w-full flex items-center justify-between px-6 py-4 text-left" @click="open = open === 3 ? null : 3">
          <span class="text-sm font-semibold text-white">What exactly are WhatsApp Flows — are they different from chatbots?</span>
          <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform" :class="open === 3 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div x-show="open === 3" x-cloak class="px-6 pb-4">
          <p class="text-sm text-gray-400 leading-relaxed">Yes — completely different. WhatsApp Flows are interactive, multi-screen forms that open inside WhatsApp itself (like an app within WhatsApp). They support text inputs, dropdowns, radio buttons, date pickers, checkboxes, and opt-ins. Our Flowmaker automations are the chatbot/workflow engine that decides when to send a message or trigger a WhatsApp Flow.</p>
        </div>
      </div>
      <div class="border border-white/[0.06] rounded-2xl overflow-hidden bg-white/[0.02]">
        <button class="w-full flex items-center justify-between px-6 py-4 text-left" @click="open = open === 4 ? null : 4">
          <span class="text-sm font-semibold text-white">Which integrations are currently available?</span>
          <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform" :class="open === 4 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div x-show="open === 4" x-cloak class="px-6 pb-4">
          <p class="text-sm text-gray-400 leading-relaxed">We support Shopify, WooCommerce, Stripe, M-Pesa (STK push and C2B), Google Calendar, Twilio SMS, SMTP email, and our embeddable WhatsApp chat widget. The Integration Hub lets owners connect and manage these from one dashboard. Our REST API and webhooks also let you build custom integrations.</p>
        </div>
      </div>
      <div class="border border-white/[0.06] rounded-2xl overflow-hidden bg-white/[0.02]">
        <button class="w-full flex items-center justify-between px-6 py-4 text-left" @click="open = open === 5 ? null : 5">
          <span class="text-sm font-semibold text-white">Do you support M-Pesa payments?</span>
          <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform" :class="open === 5 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div x-show="open === 5" x-cloak class="px-6 pb-4">
          <p class="text-sm text-gray-400 leading-relaxed">Yes. We have native M-Pesa STK push integration for Kenya and East Africa. For international businesses, we integrate with Stripe for card payments and payment links that can be sent via WhatsApp. Both payment methods trigger confirmation messages and can kick off automation flows on success or failure.</p>
        </div>
      </div>
      <div class="border border-white/[0.06] rounded-2xl overflow-hidden bg-white/[0.02]">
        <button class="w-full flex items-center justify-between px-6 py-4 text-left" @click="open = open === 6 ? null : 6">
          <span class="text-sm font-semibold text-white">Is there a free trial? Do I need a credit card?</span>
          <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform" :class="open === 6 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div x-show="open === 6" x-cloak class="px-6 pb-4">
          <p class="text-sm text-gray-400 leading-relaxed">Yes — all plans include a 14-day free trial with no credit card required. You get full access to the plan's features from day one. After the trial, you can subscribe or cancel — no questions asked.</p>
        </div>
      </div>
      <div class="border border-white/[0.06] rounded-2xl overflow-hidden bg-white/[0.02]">
        <button class="w-full flex items-center justify-between px-6 py-4 text-left" @click="open = open === 7 ? null : 7">
          <span class="text-sm font-semibold text-white">How is ConvoConnect different from Meta's Business Agent?</span>
          <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform" :class="open === 7 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div x-show="open === 7" x-cloak class="px-6 pb-4">
          <p class="text-sm text-gray-400 leading-relaxed">Meta's Business Agent handles simple FAQ support. ConvoConnect is a full social commerce platform: sell from catalog sidebars and branded shops, run file/group/quick campaigns, take bookings, manage a Customer 360 inbox with AI Copilot, collect M-Pesa and Stripe payments, and track revenue — with AI as one step in your flows, not a black-box replacement for your stack.</p>
        </div>
      </div>
      <div class="border border-white/[0.06] rounded-2xl overflow-hidden bg-white/[0.02]">
        <button class="w-full flex items-center justify-between px-6 py-4 text-left" @click="open = open === 8 ? null : 8">
          <span class="text-sm font-semibold text-white">How does the booking system work?</span>
          <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform" :class="open === 8 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div x-show="open === 8" x-cloak class="px-6 pb-4">
          <p class="text-sm text-gray-400 leading-relaxed">Set up bookable services, departments, and staff with working hours. Share a public booking page or embed a widget on your website. Customers pick services, staff, and available time slots. Bookings sync to Google Calendar, appear in the chat sidebar, and trigger automated WhatsApp reminder campaigns. You can also run events with seat capacity and registrations.</p>
        </div>
      </div>
      <div class="border border-white/[0.06] rounded-2xl overflow-hidden bg-white/[0.02]">
        <button class="w-full flex items-center justify-between px-6 py-4 text-left" @click="open = open === 9 ? null : 9">
          <span class="text-sm font-semibold text-white">What campaign broadcast types are available?</span>
          <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform" :class="open === 9 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div x-show="open === 9" x-cloak class="px-6 pb-4">
          <p class="text-sm text-gray-400 leading-relaxed">Three modes: <strong class="text-gray-300">File broadcast</strong> — upload a CSV/Excel with per-row template variables mapped to contact fields. <strong class="text-gray-300">Group broadcast</strong> — send to an existing contact group or all subscribed contacts. <strong class="text-gray-300">Quick broadcast</strong> — paste a list of phone numbers for ad-hoc sends. All support scheduling, pause/resume, delivery analytics, and geographic breakdowns.</p>
        </div>
      </div>
      <div class="border border-white/[0.06] rounded-2xl overflow-hidden bg-white/[0.02]">
        <button class="w-full flex items-center justify-between px-6 py-4 text-left" @click="open = open === 10 ? null : 10">
          <span class="text-sm font-semibold text-white">Can I sell products directly in WhatsApp chat?</span>
          <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform" :class="open === 10 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div x-show="open === 10" x-cloak class="px-6 pb-4">
          <p class="text-sm text-gray-400 leading-relaxed">Yes. Build product catalogs and branded shop pages, then sell from the inbox catalog sidebar — search products and send shop links without leaving the conversation. Catalogs sync from Shopify, WooCommerce, Excel, or your own API. Customers checkout via WhatsApp or receive a public invoice page with M-Pesa/Stripe payment links that sync back to your CRM.</p>
        </div>
      </div>
      <div class="border border-white/[0.06] rounded-2xl overflow-hidden bg-white/[0.02]">
        <button class="w-full flex items-center justify-between px-6 py-4 text-left" @click="open = open === 11 ? null : 11">
          <span class="text-sm font-semibold text-white">What are flow templates and the AI Flow Assistant?</span>
          <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform" :class="open === 11 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div x-show="open === 11" x-cloak class="px-6 pb-4">
          <p class="text-sm text-gray-400 leading-relaxed">Flow templates are pre-built automations for common use cases — payment reminders, booking confirmations, support triage, and more. Install one in a click, then customize. The AI Flow Assistant lets you describe what you want in plain language and generates a draft flow you review and publish — no blank canvas required.</p>
        </div>
      </div>
      <div class="border border-white/[0.06] rounded-2xl overflow-hidden bg-white/[0.02]">
        <button class="w-full flex items-center justify-between px-6 py-4 text-left" @click="open = open === 12 ? null : 12">
          <span class="text-sm font-semibold text-white">Can I invite managers without giving them full owner access?</span>
          <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform" :class="open === 12 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div x-show="open === 12" x-cloak class="px-6 pb-4">
          <p class="text-sm text-gray-400 leading-relaxed">Yes. Organization managers get scoped access to specific modules — like inbox, campaigns, or flows — without billing or WhatsApp setup permissions. They see Customer 360, Copilot suggestions, and revenue widgets based on what you grant them. Owners keep control of activation, integrations, and account settings.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== FINAL CTA ===== -->
<section class="py-24 relative overflow-hidden" style="background:#030a07;">
  <div class="absolute inset-0" style="background:radial-gradient(ellipse 80% 60% at 50% 50%, rgba(37,211,102,0.08) 0%, transparent 70%);"></div>
  <div class="absolute top-0 left-0 right-0 h-px" style="background:linear-gradient(90deg,transparent,rgba(37,211,102,0.3),transparent);"></div>
  <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 text-center">
    <div class="badge inline-flex mb-6">No credit card required</div>
    <h2 class="font-display text-5xl sm:text-6xl lg:text-7xl font-800 leading-tight mb-6" style="letter-spacing:-0.02em;">
      Start selling on<br/><span class="grad-text">WhatsApp today</span>
    </h2>
    <p class="text-xl text-gray-400 mb-10 max-w-xl mx-auto">Join 2,400+ brands selling on WhatsApp — catalogs, campaigns, bookings, inbox, and payments in one social commerce platform.</p>
    <div class="flex flex-wrap justify-center gap-4 mb-6">
      <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-8 py-4 text-base font-semibold text-black rounded-xl transition-all hover:opacity-90 hover:scale-105" style="background:#25D366;">
        Start Free Today
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
      </a>
      <a href="#" class="inline-flex items-center gap-2 px-8 py-4 text-base font-semibold text-white rounded-xl border border-white/10 hover:border-white/20 transition-all hover:bg-white/5">Contact Sales</a>
    </div>
    <p class="text-sm text-gray-600">No credit card required · Cancel anytime · Guided activation in ~15 minutes</p>
  </div>
</section>
<!-- ===== BOOKING SECTION ===== -->
<!-- ===== BOOKING SECTION ===== -->
<!-- <section id="book" class="py-24 border-t border-white/5">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
      <div class="text-center mb-12">
        <div class="badge inline-flex mb-4">Book a Demo</div>
        <h2 class="font-display text-4xl sm:text-5xl font-800 mb-3">Schedule a consultation</h2>
        <p class="text-gray-400">Pick a time — we'll walk you through ConvoConnect live.</p>
      </div>

      <div class="rounded-2xl overflow-hidden" style="border:1px solid rgba(37,211,102,0.15);">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-3.5" style="background:rgba(7,94,84,0.15);border-bottom:1px solid rgba(37,211,102,0.12);">
          <div class="flex items-center gap-2.5">
            <span class="w-2 h-2 rounded-full pulse-ring relative" style="background:#25D366;"></span>
            <span class="text-sm font-semibold text-gray-200">ConvoConnect — Live Consultation</span>
          </div>
          <span class="badge">Free · 30 min</span>
        </div>

        {{-- iframe + loader --}}
        <div class="relative" style="height:660px;">

          {{-- Loader overlay --}}
          <div id="bookingLoader" class="absolute inset-0 flex flex-col items-center justify-center gap-5 z-10" style="background:#040f0c; transition:opacity 0.5s ease;">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center" style="background:rgba(37,211,102,0.08);border:1px solid rgba(37,211,102,0.2);">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="#25D366">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347zM12 0C5.373 0 0 5.373 0 12c0 2.105.547 4.085 1.505 5.805L0 24l6.388-1.493A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.815 9.815 0 01-5.003-1.368l-.359-.214-3.72.869.936-3.624-.236-.373A9.818 9.818 0 012.182 12C2.182 6.57 6.57 2.182 12 2.182S21.818 6.57 21.818 12 17.43 21.818 12 21.818z"/>
              </svg>
            </div>
            <div class="text-center">
              <p class="font-semibold text-white text-base mb-1">Loading your booking page</p>
              <p class="text-sm text-gray-500">Setting up your consultation calendar</p>
            </div>
            <div class="w-48 rounded-full overflow-hidden" style="height:2px;background:rgba(37,211,102,0.1);">
              <div id="loaderBar" style="height:100%;width:0%;background:linear-gradient(90deg,#25D366,#128C7E);transition:width 0.4s ease;border-radius:999px;"></div>
            </div>
            <div class="space-y-2.5 w-52">
              <div id="lStep1" class="flex items-center gap-2.5" style="opacity:0;transform:translateY(4px);transition:all 0.3s ease;">
                <div class="step-circle w-5 h-5 rounded-full border flex items-center justify-center flex-shrink-0" style="border-color:rgba(37,211,102,0.3);">
                  <svg class="check-icon w-2.5 h-2.5" style="display:none;" fill="none" stroke="#25D366" stroke-width="2.5" viewBox="0 0 12 12"><polyline points="2,6 5,9 10,3"/></svg>
                </div>
                <span class="step-text text-xs text-gray-500">Connecting to calendar</span>
              </div>
              <div id="lStep2" class="flex items-center gap-2.5" style="opacity:0;transform:translateY(4px);transition:all 0.3s ease;">
                <div class="step-circle w-5 h-5 rounded-full border flex items-center justify-center flex-shrink-0" style="border-color:rgba(37,211,102,0.3);">
                  <svg class="check-icon w-2.5 h-2.5" style="display:none;" fill="none" stroke="#25D366" stroke-width="2.5" viewBox="0 0 12 12"><polyline points="2,6 5,9 10,3"/></svg>
                </div>
                <span class="step-text text-xs text-gray-500">Fetching available slots</span>
              </div>
              <div id="lStep3" class="flex items-center gap-2.5" style="opacity:0;transform:translateY(4px);transition:all 0.3s ease;">
                <div class="step-circle w-5 h-5 rounded-full border flex items-center justify-center flex-shrink-0" style="border-color:rgba(37,211,102,0.3);">
                  <svg class="check-icon w-2.5 h-2.5" style="display:none;" fill="none" stroke="#25D366" stroke-width="2.5" viewBox="0 0 12 12"><polyline points="2,6 5,9 10,3"/></svg>
                </div>
                <span class="step-text text-xs text-gray-500">Ready to book</span>
              </div>
            </div>
          </div>

          {{-- Iframe --}}
          <iframe
            id="bookingIframe"
            src="https://glady-volcanologic-resourcefully.ngrok-free.dev/book/254759608209/Consultation?token==MFlujqPkkzOFoxUrUWK7DhpaLVTNHmJZWx6UvrArea0c6b48"
            width="100%"
            height="100%"
            frameborder="0"
            title="Book a ConvoConnect consultation"
            style="display:block;background:#040f0c;"
          ></iframe>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-between px-5 py-2.5" style="background:rgba(7,94,84,0.08);border-top:1px solid rgba(37,211,102,0.08);">
          <span class="text-xs text-gray-600">Powered by ConvoConnect</span>
          <span class="text-xs text-gray-600">🔒 Secure &amp; encrypted</span>
        </div>
      </div>
    </div>
  </section>

 -->
<!-- ===== FOOTER ===== -->
<footer class="border-t border-white/[0.05] py-16" style="background:#020907;">
  <div class="max-w-7xl mx-auto px-4 sm:px-6">
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-10 mb-12">
      <!-- Brand -->
      <div class="lg:col-span-2">
        <a href="#" class="flex items-center gap-2.5 mb-4">
          <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background:linear-gradient(135deg,#25D366,#075E54);">
          <img src="public/uploads/lgU7zxttjQlFP0g8jI9EE9FxfvelUIQ6wVT5Qwq1.png" alt="icon" class="w-full h-full object-contain p-1" />
          </div>
          <span class="font-display font-800 text-lg tracking-tight">Convo<span class="grad-text">Connect</span></span>
        </a>
        <p class="text-sm text-gray-500 leading-relaxed max-w-xs mb-5">The social commerce platform for WhatsApp — sell from chat, run campaigns, take bookings, and get paid. Built on the official Meta WhatsApp Business API.</p>
        <div class="flex items-center gap-3">
          <a href="#" class="w-8 h-8 rounded-lg border border-white/10 flex items-center justify-center text-gray-500 hover:text-white hover:border-white/20 transition-all">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.253 5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
          </a>
          <a href="#" class="w-8 h-8 rounded-lg border border-white/10 flex items-center justify-center text-gray-500 hover:text-white hover:border-white/20 transition-all">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
          </a>
        </div>
      </div>
      <!-- Product -->
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-4">Product</p>
        <ul class="space-y-2.5">
          <li><a href="#features" class="text-sm text-gray-400 hover:text-white transition-colors">Features</a></li>
          <li><a href="#bookings" class="text-sm text-gray-400 hover:text-white transition-colors">Bookings</a></li>
          <li><a href="#catalog" class="text-sm text-gray-400 hover:text-white transition-colors">Product Catalog</a></li>
          <li><a href="#automation" class="text-sm text-gray-400 hover:text-white transition-colors">Automation</a></li>
          <li><a href="#integrations" class="text-sm text-gray-400 hover:text-white transition-colors">Integrations</a></li>
          <li><a href="#api" class="text-sm text-gray-400 hover:text-white transition-colors">API Docs</a></li>
          <li><a href="#pricing" class="text-sm text-gray-400 hover:text-white transition-colors">Pricing</a></li>
        </ul>
      </div>
      <!-- Company -->
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-4">Company</p>
        <ul class="space-y-2.5">
          <li><a href="#" class="text-sm text-gray-400 hover:text-white transition-colors">About</a></li>
          <li><a href="#" class="text-sm text-gray-400 hover:text-white transition-colors">Blog</a></li>
          <li><a href="#" class="text-sm text-gray-400 hover:text-white transition-colors">Contact</a></li>
          <li><a href="{{ route('policy.show') }}" class="text-sm text-gray-400 hover:text-white transition-colors">Privacy Policy</a></li>
          <li><a href="{{ route('terms.show') }}" class="text-sm text-gray-400 hover:text-white transition-colors">Terms of Service</a></li>
          <li><a href="#faq" class="text-sm text-gray-400 hover:text-white transition-colors">FAQ</a></li>
        </ul>
      </div>
    </div>

    <!-- Bottom bar -->
    <div class="border-t border-white/[0.05] pt-8 flex flex-col sm:flex-row items-center justify-between gap-4">
      <p class="text-xs text-gray-600">© {{ date('Y') }} ConvoConnect. All rights reserved.</p>
      <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-white/[0.06]" style="background:rgba(37,211,102,0.04);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.105.547 4.085 1.505 5.805L0 24l6.388-1.493A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.815 9.815 0 01-5.003-1.368l-.359-.214-3.72.869.936-3.624-.236-.373A9.818 9.818 0 012.182 12C2.182 6.57 6.57 2.182 12 2.182S21.818 6.57 21.818 12 17.43 21.818 12 21.818z"/></svg>
        <p class="text-xs text-gray-500">Built on <span class="text-gray-300">Meta WhatsApp Business API</span></p>
      </div>
    </div>
  </div>
</footer>
<script src="https://glady-volcanologic-resourcefully.ngrok-free.dev/popup/whatsapp?id=0OPfclU79P"></script>
<div id="embed-whatsapp-chat"></div>

{{-- Booking loader script --}}
<script>
(function () {
  const steps = [
    { id: 'lStep1', barW: '40%', delay: 300 },
    { id: 'lStep2', barW: '75%', delay: 900 },
    { id: 'lStep3', barW: '95%', delay: 1600 },
  ];

  steps.forEach(({ id, barW, delay }) => {
    setTimeout(() => {
      const el = document.getElementById(id);
      if (!el) return;
      el.style.opacity = '1';
      el.style.transform = 'translateY(0)';
      setTimeout(() => {
        el.querySelector('.step-circle').style.background = 'rgba(37,211,102,0.15)';
        el.querySelector('.step-circle').style.borderColor = '#25D366';
        el.querySelector('.check-icon').style.display = 'block';
        el.querySelector('.step-text').style.color = '#a7f3d0';
        document.getElementById('loaderBar').style.width = barW;
      }, 400);
    }, delay);
  });

  const iframe = document.getElementById('bookingIframe');
  const loader = document.getElementById('bookingLoader');

  iframe.addEventListener('load', () => {
    document.getElementById('loaderBar').style.width = '100%';
    setTimeout(() => {
      loader.style.opacity = '0';
      loader.style.pointerEvents = 'none';
    }, 600);
  });

  setTimeout(() => {
    loader.style.opacity = '0';
    loader.style.pointerEvents = 'none';
  }, 8000);
})();
</script>
</body>
</html>
