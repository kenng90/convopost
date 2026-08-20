<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" dir="{{ in_array($locale ?? 'en', ['ar','he','fa','ur']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ config('settings.site_description', 'WhatsApp revenue & operations platform. Team inbox, campaigns, workflow automation, payments, and integrations — built on the official Meta Business API.') }}">
    <title>{{ config('settings.site_name', config('app.name')) }} — WhatsApp Operations & Revenue Platform</title>
    @include('layouts.favicon')

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        wa: { DEFAULT: '#25D366', dark: '#128C7E', light: '#DCF8C6', darker: '#075E54' },
                        brand: { DEFAULT: '#25D366', 50: '#f0fdf4', 100: '#dcfce7', 500: '#22c55e', 600: '#16a34a', 700: '#15803d' }
                    },
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .gradient-hero { background: linear-gradient(135deg, #064e3b 0%, #065f46 30%, #047857 60%, #0d9488 100%); }
        .gradient-wa { background: linear-gradient(135deg, #25D366, #128C7E); }
        .gradient-subtle { background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%); }
        .card-hover { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .feature-icon { background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); }
        .text-gradient { background: linear-gradient(135deg, #25D366, #0d9488); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .nav-blur { backdrop-filter: blur(12px); background: rgba(255,255,255,0.92); }
        .chat-bubble { border-radius: 18px 18px 18px 4px; }
        .chat-bubble-right { border-radius: 18px 18px 4px 18px; }
        .pulse-ring { animation: pulse-ring 2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite; }
        @keyframes pulse-ring { 0% { transform: scale(0.8); opacity: 1; } 100% { transform: scale(1.6); opacity: 0; } }
        .animate-float { animation: float 6s ease-in-out infinite; }
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-12px); } }
        .scroll-hidden { scrollbar-width: none; -ms-overflow-style: none; }
        .scroll-hidden::-webkit-scrollbar { display: none; }
        html { scroll-behavior: smooth; }
        .section-divider { background: linear-gradient(90deg, transparent, #25D366, transparent); height: 1px; }
    </style>
</head>
<body class="bg-white text-gray-900 antialiased">

{{-- ===== NAVBAR ===== --}}
<nav class="fixed top-0 left-0 right-0 z-50 nav-blur border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center gap-2">
                <img src="{{ config('settings.logo') }}" alt="{{ config('app.name') }}" class="h-8 w-auto">
                <span class="font-bold text-lg text-gray-900">{{ config('settings.site_name', config('app.name')) }}</span>
            </div>
            <div class="hidden md:flex items-center gap-8 text-sm font-medium text-gray-600">
                <a href="#features" class="hover:text-wa transition-colors">Features</a>
                <a href="#journeys" class="hover:text-wa transition-colors">Journeys</a>
                <a href="#automation" class="hover:text-wa transition-colors">Automation</a>
                <a href="#integrations" class="hover:text-wa transition-colors">Integrations</a>
                <a href="#api" class="hover:text-wa transition-colors">API</a>
                @if(config('settings.enable_pricing'))
                <a href="#pricing" class="hover:text-wa transition-colors">Pricing</a>
                @endif
                <a href="#faq" class="hover:text-wa transition-colors">FAQ</a>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('login') }}" class="text-sm font-medium text-gray-700 hover:text-wa transition-colors">Sign in</a>
                <a href="{{ route('register') }}" class="gradient-wa text-white text-sm font-semibold px-5 py-2.5 rounded-full hover:opacity-90 transition-opacity shadow-md">
                    Get Started Free
                </a>
            </div>
        </div>
    </div>
</nav>

{{-- ===== HERO ===== --}}
<section class="gradient-hero min-h-screen flex items-center relative overflow-hidden pt-16">

    {{-- Background decorations --}}
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-white/5 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 -left-24 w-80 h-80 bg-wa/10 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-white/3 rounded-full blur-3xl"></div>
        {{-- Grid pattern --}}
        <svg class="absolute inset-0 w-full h-full opacity-5" xmlns="http://www.w3.org/2000/svg">
            <defs><pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"/></pattern></defs>
            <rect width="100%" height="100%" fill="url(#grid)"/>
        </svg>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 relative z-10">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            {{-- Left: Copy --}}
            <div>
                <div class="inline-flex items-center gap-2 bg-white/10 backdrop-blur text-white/90 text-xs font-semibold px-4 py-2 rounded-full mb-8 border border-white/20">
                    <span class="w-2 h-2 bg-wa rounded-full animate-pulse"></span>
                    Powered by WhatsApp Business API
                </div>

                <h1 class="text-5xl lg:text-6xl font-black text-white leading-tight mb-6">
                    Run Your Whole<br>
                    <span class="text-wa">WhatsApp Business</span>
                </h1>

                <p class="text-xl text-white/75 leading-relaxed mb-10 max-w-xl">
                    Team inbox, journey pipelines, outbound campaigns, visual workflows, bookings, in-chat payments, and integrations — one platform for sales, support, and operations on WhatsApp.
                </p>

                <div class="flex flex-col sm:flex-row gap-4 mb-12">
                    <a href="{{ route('register') }}" class="bg-wa hover:bg-wa-dark text-white font-bold px-8 py-4 rounded-full text-base transition-all shadow-xl hover:shadow-wa/30 hover:scale-105 text-center">
                        Start Free Today →
                    </a>
                    <a href="#features" class="bg-white/10 hover:bg-white/20 backdrop-blur text-white font-semibold px-8 py-4 rounded-full text-base transition-all border border-white/20 text-center">
                        See All Features
                    </a>
                </div>

                {{-- Social proof --}}
                <div class="flex items-center gap-6 flex-wrap">
                    <div class="flex -space-x-2">
                        @foreach(['065f46','047857','0d9488','128C7E'] as $c)
                        <div class="w-9 h-9 rounded-full border-2 border-white bg-{{ $c }}-600 flex items-center justify-center text-white text-xs font-bold">{{ chr(rand(65,90)) }}</div>
                        @endforeach
                    </div>
                    <div class="text-white/80 text-sm">
                        <span class="font-bold text-white">1,000+</span> businesses trust us
                    </div>
                    <div class="flex gap-0.5">
                        @for($i=0;$i<5;$i++)<svg class="w-4 h-4 text-yellow-400 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>@endfor
                    </div>
                    <span class="text-white/70 text-sm">4.9/5 rating</span>
                </div>
            </div>

            {{-- Right: Chat mockup --}}
            <div class="hidden lg:flex justify-center">
                <div class="animate-float relative">
                    {{-- Phone frame --}}
                    <div class="w-80 bg-gray-900 rounded-[3rem] p-3 shadow-2xl shadow-black/50">
                        <div class="bg-white rounded-[2.4rem] overflow-hidden" style="height: 620px;">
                            {{-- Status bar --}}
                            <div class="bg-wa-darker px-6 py-2 flex justify-between text-white/80 text-xs">
                                <span>9:41</span><span>●●●</span>
                            </div>
                            {{-- Chat header --}}
                            <div class="bg-wa-dark px-4 py-3 flex items-center gap-3">
                                <div class="w-10 h-10 bg-wa rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white fill-current" viewBox="0 0 24 24"><path d="M17.498 14.382c-.301-.15-1.767-.867-2.04-.966-.273-.101-.473-.15-.673.15-.197.295-.771.964-.944 1.162-.175.195-.349.21-.646.075-.3-.15-1.263-.465-2.403-1.485-.888-.795-1.484-1.77-1.66-2.07-.174-.3-.019-.465.13-.615.136-.135.301-.345.451-.523.146-.181.194-.301.297-.496.1-.21.049-.375-.025-.524-.075-.15-.672-1.62-.922-2.206-.24-.584-.487-.51-.672-.51-.172-.015-.371-.015-.571-.015-.2 0-.523.074-.797.359-.273.3-1.045 1.02-1.045 2.475s1.07 2.865 1.219 3.075c.149.195 2.105 3.195 5.1 4.485.714.3 1.27.48 1.704.629.714.227 1.365.195 1.88.121.574-.091 1.767-.721 2.016-1.426.255-.705.255-1.29.18-1.425-.074-.135-.27-.21-.57-.345m-5.446 7.443h-.016c-1.77 0-3.524-.48-5.055-1.38l-.36-.214-3.75.975 1.005-3.645-.239-.375a9.869 9.869 0 01-1.516-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                                </div>
                                <div>
                                    <div class="text-white font-semibold text-sm">Support Team</div>
                                    <div class="text-green-300 text-xs">● Online</div>
                                </div>
                            </div>
                            {{-- Messages --}}
                            <div class="bg-gray-50 px-4 py-4 flex flex-col gap-3" style="height: 470px; background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); background-size: 300px;">
                                <div class="chat-bubble bg-white text-gray-800 text-xs px-3 py-2 max-w-xs shadow-sm self-start">
                                    Hi! How can we help you today? 👋
                                </div>
                                <div class="chat-bubble-right bg-wa text-white text-xs px-3 py-2 max-w-xs shadow-sm self-end">
                                    I'd like to track my order
                                </div>
                                <div class="chat-bubble bg-white text-gray-800 text-xs px-3 py-2 max-w-xs shadow-sm self-start">
                                    Sure! Let me pull that up for you. Can you share your order number?
                                </div>
                                <div class="bg-wa/10 rounded-2xl p-3 self-start max-w-xs border border-wa/20">
                                    <div class="text-xs font-semibold text-wa mb-1">📦 Order Tracker</div>
                                    <div class="text-xs text-gray-600 mb-2">Fill in the form to track your order</div>
                                    <div class="bg-wa text-white text-xs font-semibold px-3 py-1.5 rounded-full text-center">Open Form →</div>
                                </div>
                                <div class="chat-bubble-right bg-white border border-gray-200 text-gray-700 text-xs px-3 py-2 max-w-xs shadow-sm self-end">
                                    <div class="text-gray-400 text-xs mb-1">Form submitted ✓</div>
                                    Order #1042 — <span class="text-wa font-semibold">Out for delivery</span>
                                </div>
                                <div class="chat-bubble bg-white text-gray-800 text-xs px-3 py-2 max-w-xs shadow-sm self-start">
                                    Your order is estimated to arrive by 3 PM today! 🚚
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Floating badges --}}
                    <div class="absolute -right-10 top-16 bg-white rounded-2xl shadow-xl px-4 py-3 flex items-center gap-2 border border-gray-100">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">✅</div>
                        <div><div class="text-xs font-semibold">Flow completed</div><div class="text-xs text-gray-400">Just now</div></div>
                    </div>
                    <div class="absolute -left-10 bottom-24 bg-white rounded-2xl shadow-xl px-4 py-3 flex items-center gap-2 border border-gray-100">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">📨</div>
                        <div><div class="text-xs font-semibold">Campaign sent</div><div class="text-xs text-gray-400">2,450 contacts</div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Wave --}}
    <div class="absolute bottom-0 left-0 right-0">
        <svg viewBox="0 0 1440 80" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
            <path d="M0 80L60 74.7C120 69.3 240 58.7 360 53.3C480 48 600 48 720 53.3C840 58.7 960 69.3 1080 72C1200 74.7 1320 69.3 1380 66.7L1440 64V80H1380C1320 80 1200 80 1080 80C960 80 840 80 720 80C600 80 480 80 360 80C240 80 120 80 60 80H0Z" fill="white"/>
        </svg>
    </div>
</section>

{{-- ===== STATS BAR ===== --}}
<section class="bg-white py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            @foreach([
                ['2B+', 'WhatsApp Users Globally', '🌍'],
                ['100%', 'Official Business API', '✅'],
                ['Multi', 'Agent Team Inbox', '👥'],
                ['99.9%', 'Uptime SLA', '⚡'],
            ] as $stat)
            <div class="p-6">
                <div class="text-4xl font-black text-gradient mb-1">{{ $stat[0] }}</div>
                <div class="text-sm text-gray-500">{{ $stat[1] }}</div>
            </div>
            @endforeach
        </div>
    </div>
    <div class="section-divider max-w-7xl mx-auto mt-8"></div>
</section>

{{-- ===== CORE FEATURES ===== --}}
<section id="features" class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="text-center mb-16">
            <span class="text-wa font-semibold text-sm uppercase tracking-widest">Everything You Need</span>
            <h2 class="text-4xl lg:text-5xl font-black mt-3 mb-4">One Platform, Infinite Possibilities</h2>
            <p class="text-xl text-gray-500 max-w-2xl mx-auto">Team inbox, journey pipelines, campaigns, workflows, bookings, and payments — everything your business needs to sell and support on WhatsApp.</p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach([
                [
                    'icon' => '💬',
                    'title' => 'Shared Team Inbox',
                    'desc' => 'All your WhatsApp conversations in one unified inbox. Assign chats to agents, add notes, resolve tickets, and collaborate as a team.',
                    'items' => ['Multi-agent support', 'Chat assignment & handover', 'Quick replies & templates', 'Contact notes & history'],
                ],
                [
                    'icon' => '📣',
                    'title' => 'Campaigns & Broadcasts',
                    'desc' => 'Send targeted bulk messages to thousands of contacts in seconds. Schedule campaigns, track delivery, and measure results.',
                    'items' => ['Bulk messaging', 'Message templates', 'Scheduled delivery', 'Delivery & read receipts'],
                ],
                [
                    'icon' => '🧭',
                    'title' => 'Journey Pipelines',
                    'desc' => 'Kanban CRM with templates for sales, support, e-commerce, and events. Move contacts across stages and trigger WhatsApp campaigns automatically.',
                    'items' => ['7 ready-made playbooks', 'Stage-triggered campaigns', 'Auto-enroll new contacts', 'Inbox journey sidebar'],
                ],
                [
                    'icon' => '👥',
                    'title' => 'Contact CRM',
                    'desc' => 'Manage your entire contact database. Organize into groups, add custom fields, import/export, and keep a full conversation history.',
                    'items' => ['Contact groups & segments', 'Custom fields', 'CSV import/export', 'Customer 360 in inbox'],
                ],
                [
                    'icon' => '⚡',
                    'title' => 'Flow Automation',
                    'desc' => 'Build powerful no-code automation flows. Trigger messages based on keywords, schedule follow-ups, and automate your entire sales funnel.',
                    'items' => ['Visual flow builder', 'Keyword triggers', 'Conditional routing', 'AI nodes for tier-1 deflection'],
                ],
                [
                    'icon' => '📋',
                    'title' => 'WhatsApp Flows',
                    'desc' => 'Collect structured data from customers via interactive Meta WhatsApp Forms — directly inside the chat. No links, no redirects.',
                    'items' => ['Drag & drop form builder', 'Multi-screen flows', 'Response dashboard', 'Automation on submit'],
                ],
                [
                    'icon' => '📞',
                    'title' => 'WhatsApp Calling',
                    'desc' => 'Make and receive voice calls directly through WhatsApp Business API. Full call analytics, agent performance tracking.',
                    'items' => ['Inbound & outbound calls', 'Call recording', 'Agent performance', 'Call analytics'],
                ],
                [
                    'icon' => '📚',
                    'title' => 'Knowledge Base',
                    'desc' => 'Build a self-service help center for your customers. Reduce support volume with articles, categories, and an embeddable website widget.',
                    'items' => ['Article management', 'Category organization', 'Embeddable widget', 'Powers AI flow nodes'],
                ],
                [
                    'icon' => '💰',
                    'title' => 'Invoices & Payments',
                    'desc' => 'Generate invoices and collect payments directly via WhatsApp. Integrated M-Pesa STK push and Stripe payments.',
                    'items' => ['Invoice generation', 'M-Pesa STK push', 'Stripe subscriptions', 'Payment status tracking'],
                ],
                [
                    'icon' => '📅',
                    'title' => 'Bookings',
                    'desc' => 'Let customers book appointments and events, receive automated WhatsApp reminders, and manage staff schedules from one Bookings app.',
                    'items' => ['Online booking widget', 'Events & registrations', 'Google Calendar sync', 'WhatsApp reminders'],
                ],
            ] as $feature)
            <div class="bg-white border border-gray-100 rounded-3xl p-8 card-hover shadow-sm hover:border-wa/30">
                <div class="text-4xl mb-5">{{ $feature['icon'] }}</div>
                <h3 class="text-xl font-bold mb-3">{{ $feature['title'] }}</h3>
                <p class="text-gray-500 text-sm leading-relaxed mb-5">{{ $feature['desc'] }}</p>
                <ul class="space-y-2">
                    @foreach($feature['items'] as $item)
                    <li class="flex items-center gap-2 text-sm text-gray-600">
                        <svg class="w-4 h-4 text-wa flex-shrink-0 fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        {{ $item }}
                    </li>
                    @endforeach
                </ul>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===== JOURNEYS ===== --}}
<section id="journeys" class="py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <div>
                <span class="text-wa font-semibold text-sm uppercase tracking-widest">Journey Pipelines</span>
                <h2 class="text-4xl lg:text-5xl font-black mt-3 mb-6">Kanban CRM for<br>WhatsApp Contacts</h2>
                <p class="text-lg text-gray-600 mb-8 leading-relaxed">
                    Launch pipelines from templates, drag contacts between stages on a kanban board, and attach WhatsApp campaigns that fire when someone enters a stage. Configure journeys, auto-enrollment, and staff permissions from Company Apps.
                </p>
                <div class="space-y-4 mb-10">
                    @foreach([
                        ['Ready-made playbooks', 'Sales, support, marketing, onboarding, revenue, e-commerce, and event registration templates.'],
                        ['Stage-triggered campaigns', 'Link broadcast templates to stages so messages send automatically on move.'],
                        ['Inbox journey sidebar', 'Agents move contacts and view pipeline context without leaving chat.'],
                    ] as [$title, $desc])
                    <div class="flex gap-4">
                        <div class="w-10 h-10 gradient-wa rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5">
                            <svg class="w-5 h-5 text-white fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-900">{{ $title }}</div>
                            <div class="text-gray-500 text-sm mt-0.5">{{ $desc }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <a href="{{ route('register') }}" class="gradient-wa text-white font-bold px-8 py-4 rounded-full inline-block hover:opacity-90 transition-opacity shadow-lg">
                    Start Your First Journey →
                </a>
            </div>
            <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 p-8">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach(['Lead', 'Qualified', 'Proposal', 'Won'] as $stage)
                    <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100">
                        <div class="text-xs font-bold text-gray-400 uppercase mb-3">{{ $stage }}</div>
                        <div class="space-y-2">
                            <div class="bg-white rounded-xl p-3 shadow-sm border border-gray-100 text-xs text-gray-600">Contact A</div>
                            <div class="bg-white rounded-xl p-3 shadow-sm border border-gray-100 text-xs text-gray-600">Contact B</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ===== WHATSAPP FLOWS SPOTLIGHT ===== --}}
<section class="py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <div>
                <span class="text-wa font-semibold text-sm uppercase tracking-widest">New Feature</span>
                <h2 class="text-4xl lg:text-5xl font-black mt-3 mb-6">Interactive Forms <br>Inside WhatsApp</h2>
                <p class="text-lg text-gray-600 mb-8 leading-relaxed">
                    With Meta WhatsApp Flows, you can collect structured responses from customers without them ever leaving WhatsApp. Build forms visually, publish to Meta, and automate what happens next.
                </p>
                <div class="space-y-5 mb-10">
                    @foreach([
                        ['Build forms visually', 'Drag-and-drop form builder with text fields, radio buttons, dropdowns, date pickers, and more.'],
                        ['Publish to Meta in one click', 'Validate, encrypt, and publish your flow directly to WhatsApp Business API. No technical setup needed.'],
                        ['Automate on submission', 'Connect form responses to your automation flows — send follow-ups, update CRM, trigger next steps automatically.'],
                        ['Full response dashboard', 'See every response, filter by date or status, and view submitted answers in a clean analytics dashboard.'],
                    ] as [$title, $desc])
                    <div class="flex gap-4">
                        <div class="w-10 h-10 gradient-wa rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5">
                            <svg class="w-5 h-5 text-white fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-900">{{ $title }}</div>
                            <div class="text-gray-500 text-sm mt-0.5">{{ $desc }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <a href="{{ route('register') }}" class="gradient-wa text-white font-bold px-8 py-4 rounded-full inline-block hover:opacity-90 transition-opacity shadow-lg">
                    Try WhatsApp Flows →
                </a>
            </div>

            {{-- Form builder mockup --}}
            <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 overflow-hidden">
                <div class="bg-gray-50 border-b border-gray-100 px-6 py-4 flex items-center gap-3">
                    <div class="flex gap-1.5"><div class="w-3 h-3 bg-red-400 rounded-full"></div><div class="w-3 h-3 bg-yellow-400 rounded-full"></div><div class="w-3 h-3 bg-green-400 rounded-full"></div></div>
                    <div class="flex-1 bg-white rounded-lg px-3 py-1.5 text-xs text-gray-400 border border-gray-200">WhatsApp Flow Builder</div>
                </div>
                <div class="p-6">
                    {{-- Screen preview --}}
                    <div class="flex gap-2 mb-4">
                        <div class="bg-wa text-white text-xs font-semibold px-3 py-1.5 rounded-full">Screen 1</div>
                        <div class="bg-gray-100 text-gray-500 text-xs font-semibold px-3 py-1.5 rounded-full">Screen 2</div>
                        <div class="text-xs text-wa font-semibold px-3 py-1.5 cursor-pointer">+ Add Screen</div>
                    </div>
                    {{-- Fields --}}
                    <div class="space-y-3">
                        <div class="border-2 border-wa/30 bg-wa/5 rounded-2xl p-4">
                            <div class="text-xs text-gray-400 mb-1">TextHeading</div>
                            <div class="font-bold text-gray-800">Customer Feedback Form</div>
                        </div>
                        <div class="border border-gray-200 rounded-2xl p-4">
                            <div class="text-xs text-gray-400 mb-1">TextArea · Required</div>
                            <div class="text-sm text-gray-600 border-b border-dashed border-gray-300 pb-1">Tell us about your experience...</div>
                        </div>
                        <div class="border border-gray-200 rounded-2xl p-4">
                            <div class="text-xs text-gray-400 mb-2">RadioButtonsGroup</div>
                            <div class="flex gap-2">
                                <div class="bg-wa/10 text-wa text-xs font-medium px-3 py-1.5 rounded-full border border-wa/20">⭐ Excellent</div>
                                <div class="bg-gray-50 text-gray-600 text-xs px-3 py-1.5 rounded-full border">Good</div>
                                <div class="bg-gray-50 text-gray-600 text-xs px-3 py-1.5 rounded-full border">Fair</div>
                            </div>
                        </div>
                        <div class="border border-gray-200 rounded-2xl p-4">
                            <div class="text-xs text-gray-400 mb-1">DatePicker</div>
                            <div class="text-sm text-gray-400">Select date of visit...</div>
                        </div>
                        <div class="bg-wa text-white text-sm font-bold py-3 rounded-2xl text-center">Submit Feedback</div>
                    </div>
                    {{-- Publish status --}}
                    <div class="mt-4 flex items-center justify-between bg-green-50 rounded-xl px-4 py-3">
                        <span class="text-xs text-green-700 font-medium">✅ Published to Meta</span>
                        <span class="text-xs text-gray-400">Flow ID: 936770...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ===== AUTOMATION ===== --}}
<section id="automation" class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <span class="text-wa font-semibold text-sm uppercase tracking-widest">Workflow Automation</span>
            <h2 class="text-4xl lg:text-5xl font-black mt-3 mb-4">Orchestrate Every Customer Journey</h2>
            <p class="text-xl text-gray-500 max-w-2xl mx-auto">Build complex conversation flows visually — from cart recovery to onboarding to agent handoff. No code required.</p>
        </div>

        <div class="grid lg:grid-cols-3 gap-8 mb-16">
            @foreach([
                ['🎯', 'Keyword Triggers', 'Respond instantly when a customer sends a specific word or phrase. Set up unlimited keyword-action pairs.'],
                ['📊', 'Catalog & Product Nodes', 'Automatically show product catalogs, generate quotes, and process orders through WhatsApp conversations.'],
                ['🔀', 'Conditional Logic', 'Branch your flows based on customer input, custom field values, or response data from forms.'],
                ['📱', 'WhatsApp Flow Nodes', 'Send interactive Meta forms mid-conversation, collect answers, and route based on responses.'],
                ['💳', 'Payment Collection', 'Integrate M-Pesa and Stripe payment nodes to collect payments without leaving WhatsApp.'],
                ['🤖', 'AI Flow Nodes', 'Deflect tier-1 FAQs inside your workflow — then hand off to a human in the same inbox with full context.'],
            ] as [$icon, $title, $desc])
            <div class="flex gap-4">
                <div class="w-12 h-12 bg-gray-50 border border-gray-100 rounded-2xl flex items-center justify-center text-2xl flex-shrink-0">{{ $icon }}</div>
                <div>
                    <div class="font-bold text-gray-900 mb-1">{{ $title }}</div>
                    <div class="text-sm text-gray-500 leading-relaxed">{{ $desc }}</div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Flow builder screenshot mockup --}}
        <div class="bg-gray-900 rounded-3xl overflow-hidden shadow-2xl border border-gray-700 relative">
            <div class="absolute inset-0 bg-gradient-to-b from-transparent to-gray-900/50 pointer-events-none z-10"></div>
            <div class="px-6 py-4 border-b border-gray-700 flex items-center gap-3 bg-gray-800">
                <div class="flex gap-1.5"><div class="w-3 h-3 bg-red-500 rounded-full"></div><div class="w-3 h-3 bg-yellow-500 rounded-full"></div><div class="w-3 h-3 bg-green-500 rounded-full"></div></div>
                <div class="text-gray-400 text-xs font-mono">Flow Builder — Customer Onboarding</div>
                <div class="ml-auto flex gap-2">
                    <div class="text-xs text-wa bg-wa/10 px-3 py-1 rounded-full border border-wa/20">● Live</div>
                </div>
            </div>
            <div class="p-8 min-h-64 flex items-center gap-6 flex-wrap justify-center">
                @foreach([
                    ['Start', 'Keyword: "Hello"', 'bg-blue-500'],
                    ['Message', 'Send welcome msg', 'bg-gray-600'],
                    ['WhatsApp Flow', 'Collect user info', 'bg-wa'],
                    ['Condition', 'Interested in demo?', 'bg-purple-500'],
                    ['Message', 'Send pricing PDF', 'bg-gray-600'],
                    ['End', 'Close flow', 'bg-red-500'],
                ] as [$type, $label, $color])
                <div class="flex items-center gap-2">
                    <div class="bg-gray-800 border border-gray-600 rounded-2xl px-5 py-3 text-center min-w-28 shadow-lg">
                        <div class="text-xs text-gray-400 mb-1">{{ $type }}</div>
                        <div class="text-sm text-white font-semibold">{{ $label }}</div>
                        <div class="mt-2 h-1 w-8 mx-auto rounded-full {{ $color }}"></div>
                    </div>
                    @if(!$loop->last)
                    <div class="text-gray-500 font-bold text-lg">→</div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ===== INTEGRATIONS ===== --}}
<section id="integrations" class="py-24 gradient-subtle">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <span class="text-wa font-semibold text-sm uppercase tracking-widest">Integrations</span>
            <h2 class="text-4xl lg:text-5xl font-black mt-3 mb-4">Connects with Your Stack</h2>
            <p class="text-xl text-gray-500 max-w-2xl mx-auto">Works seamlessly with the tools you already use — from e-commerce to email to payments.</p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-12">
            @foreach([
                ['WhatsApp Cloud API', 'Official Meta API integration', '💬'],
                ['Shopify', 'Sync products & orders', '🛒'],
                ['WooCommerce', 'WordPress e-commerce', '🛍️'],
                ['M-Pesa', 'Mobile money payments', '💸'],
                ['Stripe', 'Subscription billing', '💳'],
                ['SMTP Email', 'Send automated emails', '📧'],
                ['SMS / Twilio', 'Multi-channel messaging', '📱'],
                ['REST API', 'Connect any system', '🔌'],
            ] as [$name, $desc, $icon])
            <div class="bg-white rounded-2xl p-6 text-center border border-gray-100 card-hover shadow-sm">
                <div class="text-4xl mb-3">{{ $icon }}</div>
                <div class="font-bold text-gray-900 text-sm mb-1">{{ $name }}</div>
                <div class="text-gray-400 text-xs">{{ $desc }}</div>
            </div>
            @endforeach
        </div>

        {{-- Chat widget promo --}}
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-8 lg:p-12">
            <div class="grid lg:grid-cols-2 gap-10 items-center">
                <div>
                    <div class="text-3xl mb-4">🌐</div>
                    <h3 class="text-2xl font-black mb-4">Add a WhatsApp Chat Widget to Your Website</h3>
                    <p class="text-gray-500 mb-6">Let visitors start a WhatsApp conversation directly from your website. Customise the button, greeting text, and colors to match your brand.</p>
                    <a href="{{ route('register') }}" class="gradient-wa text-white font-semibold px-6 py-3 rounded-full text-sm inline-block hover:opacity-90 transition-opacity">
                        Get Your Widget →
                    </a>
                </div>
                <div class="bg-gray-50 rounded-2xl p-6 font-mono text-xs text-gray-600 overflow-x-auto">
<pre class="whitespace-pre-wrap">&lt;!-- WhatsApp Chat Widget --&gt;
&lt;script&gt;
  (function(d, s) {
    var j = d.createElement(s);
    j.src = "https://yourdomain.com/widget.js";
    j.setAttribute('data-phone', '+1234567890');
    j.setAttribute('data-company', 'Your Business');
    j.setAttribute('data-color', '#25D366');
    d.head.appendChild(j);
  })(document, 'script');
&lt;/script&gt;</pre>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ===== API SECTION ===== --}}
<section id="api" class="py-24 bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <div>
                <span class="text-wa font-semibold text-sm uppercase tracking-widest">Developer API</span>
                <h2 class="text-4xl lg:text-5xl font-black text-white mt-3 mb-6">Built API-First for Developers</h2>
                <p class="text-lg text-gray-400 mb-8 leading-relaxed">
                    Every feature available in the UI is also accessible via REST API. Send messages, manage contacts, trigger campaigns, and integrate WhatsApp into your own applications.
                </p>
                <div class="space-y-4 mb-10">
                    @foreach([
                        ['POST /api/wpbox/sendmessage', 'Send WhatsApp messages to any number'],
                        ['POST /api/wpbox/sendcampaigns', 'Trigger bulk campaigns programmatically'],
                        ['GET /api/wpbox/getContacts', 'Fetch and search your contact list'],
                        ['POST /api/wpbox/sendTemplate', 'Send approved message templates'],
                        ['GET /api/accessible-companies', 'Multi-tenant company management'],
                    ] as [$endpoint, $desc])
                    <div class="flex items-start gap-4">
                        <code class="bg-gray-800 text-wa text-xs px-3 py-1.5 rounded-lg font-mono whitespace-nowrap border border-gray-700 flex-shrink-0">{{ $endpoint }}</code>
                        <span class="text-gray-400 text-sm pt-1.5">{{ $desc }}</span>
                    </div>
                    @endforeach
                </div>
                <div class="flex gap-4">
                    <a href="{{ route('register') }}" class="gradient-wa text-white font-bold px-6 py-3 rounded-full text-sm hover:opacity-90 transition-opacity">
                        Get API Key
                    </a>
                    <a href="#" class="text-gray-400 font-semibold px-6 py-3 rounded-full text-sm border border-gray-700 hover:border-gray-500 transition-colors">
                        View API Docs →
                    </a>
                </div>
            </div>

            {{-- Code snippet --}}
            <div class="bg-gray-800 rounded-3xl overflow-hidden shadow-2xl border border-gray-700">
                <div class="px-6 py-4 border-b border-gray-700 flex items-center gap-2">
                    <div class="flex gap-1.5"><div class="w-3 h-3 bg-red-500 rounded-full"></div><div class="w-3 h-3 bg-yellow-500 rounded-full"></div><div class="w-3 h-3 bg-green-500 rounded-full"></div></div>
                    <span class="text-gray-400 text-xs font-mono ml-2">send-message.js</span>
                </div>
                <div class="p-6 font-mono text-sm leading-relaxed">
<pre class="text-gray-300 whitespace-pre-wrap overflow-x-auto text-xs"><span class="text-blue-400">const</span> <span class="text-yellow-400">response</span> = <span class="text-blue-400">await</span> <span class="text-green-400">fetch</span>(
  <span class="text-orange-300">'https://yourdomain.com/api/wpbox/sendmessage'</span>,
  {
    <span class="text-yellow-300">method</span>: <span class="text-orange-300">'POST'</span>,
    <span class="text-yellow-300">headers</span>: {
      <span class="text-orange-300">'Authorization'</span>: <span class="text-orange-300">`Bearer ${apiKey}`</span>,
      <span class="text-orange-300">'Content-Type'</span>: <span class="text-orange-300">'application/json'</span>,
    },
    <span class="text-yellow-300">body</span>: <span class="text-blue-400">JSON</span>.<span class="text-green-400">stringify</span>({
      <span class="text-yellow-300">phone</span>: <span class="text-orange-300">'+254712345678'</span>,
      <span class="text-yellow-300">message</span>: <span class="text-orange-300">'Hello from the API! 👋'</span>,
    }),
  }
);

<span class="text-blue-400">const</span> <span class="text-yellow-400">data</span> = <span class="text-blue-400">await</span> response.<span class="text-green-400">json</span>();
<span class="text-gray-500">// { success: true, message_id: 'wamid.xxx' }</span></pre>
                </div>
                <div class="px-6 pb-6">
                    <div class="bg-gray-900/60 rounded-xl px-4 py-3 flex items-center gap-3 border border-gray-700">
                        <div class="w-2 h-2 bg-wa rounded-full"></div>
                        <span class="text-gray-400 text-xs font-mono">200 OK · message delivered ✓</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ===== PRICING ===== --}}
@if(config('settings.enable_pricing') && isset($plans) && $plans->count() > 0)
<section id="pricing" class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <span class="text-wa font-semibold text-sm uppercase tracking-widest">Pricing</span>
            <h2 class="text-4xl lg:text-5xl font-black mt-3 mb-4">Simple, Transparent Pricing</h2>
            <p class="text-xl text-gray-500 max-w-2xl mx-auto">Choose the plan that fits your business. Upgrade or downgrade at any time.</p>
        </div>
        <div class="grid md:grid-cols-{{ $col }} gap-8 max-w-5xl mx-auto">
            @foreach($plans as $plan)
            <div class="rounded-3xl border-2 {{ $plan->isPopular() ? 'border-wa bg-wa/5 shadow-xl shadow-wa/10 scale-105' : 'border-gray-100 bg-white shadow-sm' }} p-8 relative card-hover">
                @if($plan->isPopular())
                <div class="absolute -top-4 left-1/2 -translate-x-1/2 bg-wa text-white text-xs font-bold px-4 py-1.5 rounded-full">Most Popular</div>
                @endif
                <div class="font-black text-2xl mb-1">{{ $plan->name }}</div>
                <div class="text-gray-500 text-sm mb-6">{{ $plan->description ?? 'Perfect for growing businesses' }}</div>
                <div class="flex items-baseline gap-1 mb-8">
                    <span class="text-5xl font-black {{ $plan->isPopular() ? 'text-wa' : 'text-gray-900' }}">{{ $plan->currency_symbol ?? '$' }}{{ $plan->price }}</span>
                    <span class="text-gray-400">/{{ $plan->billing_period ?? 'mo' }}</span>
                </div>
                <a href="{{ route('register') }}" class="{{ $plan->isPopular() ? 'gradient-wa text-white' : 'bg-gray-900 text-white' }} font-bold py-3 px-6 rounded-full block text-center hover:opacity-90 transition-opacity mb-8">
                    Get Started
                </a>
                @if($plan->plan_features)
                <ul class="space-y-3">
                    @foreach(is_array($plan->plan_features) ? $plan->plan_features : json_decode($plan->plan_features, true) ?? [] as $feat)
                    <li class="flex items-center gap-2 text-sm text-gray-600">
                        <svg class="w-4 h-4 text-wa flex-shrink-0 fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        {{ is_array($feat) ? ($feat['name'] ?? $feat) : $feat }}
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ===== TESTIMONIALS ===== --}}
@if(isset($testimonials) && $testimonials->count() > 0)
<section class="py-24 gradient-subtle">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <span class="text-wa font-semibold text-sm uppercase tracking-widest">Testimonials</span>
            <h2 class="text-4xl font-black mt-3">Loved by Businesses Worldwide</h2>
        </div>
        <div class="grid md:grid-cols-3 gap-8">
            @foreach($testimonials->take(6) as $t)
            <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100 card-hover">
                <div class="flex gap-0.5 mb-4">
                    @for($i=0;$i<5;$i++)<svg class="w-4 h-4 text-yellow-400 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>@endfor
                </div>
                <p class="text-gray-600 text-sm leading-relaxed mb-6">"{{ $t->content ?? $t->description ?? '' }}"</p>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 gradient-wa rounded-full flex items-center justify-center text-white font-bold">{{ strtoupper(substr($t->title ?? 'A', 0, 1)) }}</div>
                    <div>
                        <div class="font-semibold text-sm">{{ $t->title ?? 'Customer' }}</div>
                        <div class="text-gray-400 text-xs">{{ $t->subtitle ?? 'Business Owner' }}</div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@else
{{-- Static testimonials when none in DB --}}
<section class="py-24 gradient-subtle">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <span class="text-wa font-semibold text-sm uppercase tracking-widest">Testimonials</span>
            <h2 class="text-4xl font-black mt-3">Loved by Businesses Worldwide</h2>
        </div>
        <div class="grid md:grid-cols-3 gap-8">
            @foreach([
                ['Our abandoned cart recovery rate jumped 40% in the first month. WhatsApp campaigns plus M-Pesa in the same flow — that\'s the difference.', 'Amina M.', 'E-commerce, Kenya'],
                ['We manage 18 client WhatsApp numbers from one dashboard. Campaigns, inboxes, and flows per brand — Meta\'s tools can\'t do that.', 'David O.', 'Agency Owner'],
                ['We replaced three tools with one platform. Shared inbox, broadcast campaigns, and the API all work exactly as advertised.', 'Taiwo N.', 'Head of CX'],
            ] as [$q, $n, $r])
            <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100 card-hover">
                <div class="flex gap-0.5 mb-4">@for($i=0;$i<5;$i++)<svg class="w-4 h-4 text-yellow-400 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>@endfor</div>
                <p class="text-gray-600 text-sm leading-relaxed mb-6">"{{ $q }}"</p>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 gradient-wa rounded-full flex items-center justify-center text-white font-bold">{{ strtoupper($n[0]) }}</div>
                    <div><div class="font-semibold text-sm">{{ $n }}</div><div class="text-gray-400 text-xs">{{ $r }}</div></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ===== FAQ ===== --}}
<section id="faq" class="py-24 bg-white">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <span class="text-wa font-semibold text-sm uppercase tracking-widest">FAQ</span>
            <h2 class="text-4xl font-black mt-3">Frequently Asked Questions</h2>
        </div>
        <div class="space-y-4" x-data="{ open: null }">
            @foreach([
                ['Do I need a WhatsApp Business API account?', 'Yes, this platform is built on the official Meta WhatsApp Business Cloud API. We support Meta\'s embedded signup — you can connect your WhatsApp number directly from within the platform in minutes.'],
                ['Can I manage multiple WhatsApp numbers?', 'Yes! The platform is multi-tenant. Each organisation can have its own WhatsApp number, agents, contacts, flows, and settings. You can also manage multiple organisations under one account.'],
                ['What is WhatsApp Flows?', 'WhatsApp Flows are interactive forms that open natively inside WhatsApp — no link, no redirect. Users can fill in structured data (text, dropdowns, dates, opt-ins) and submit, all within the chat.'],
                ['Does it have an API for developers?', 'Absolutely. Every feature is accessible via REST API. Send messages, manage contacts, trigger campaigns, and integrate with your own CRM or e-commerce system. API keys are available from your account.'],
                ['What payment gateways are supported?', 'We support M-Pesa STK push for mobile money payments (Kenya), Stripe for card and subscription payments. Additional gateways can be integrated via the API.'],
                ['Is there a free trial?', 'Create a free account to explore ConvoConnect on the Starter plan — team inbox, contacts, and flows with no credit card. Upgrade when you need campaigns, catalog, or higher limits.'],
                ['Can I integrate with Shopify or WooCommerce?', 'Yes. Our Shopify and WooCommerce integrations let you automatically sync product catalogs, show products inside WhatsApp conversations, and fetch customer order history.'],
                ['How is this different from Meta\'s Business Agent?', 'Meta\'s Business Agent is built for simple FAQ support inside WhatsApp Business. We\'re the operations layer: team inbox, outbound campaigns, custom workflows, payments, multi-number management, and integrations — with AI as one step in your flows, not the whole product.'],
                ['How does AI work in ConvoConnect?', 'AI runs as a node inside your automation flows — trained on your docs and knowledge base. It deflects common questions, then routes to a human agent in the shared inbox with full conversation context when needed.'],
            ] as $i => [$q, $a])
            <div class="border border-gray-100 rounded-2xl overflow-hidden" x-data="{ open: false }">
                <button @click="open = !open" class="w-full px-6 py-5 flex items-center justify-between text-left hover:bg-gray-50 transition-colors">
                    <span class="font-semibold text-gray-900">{{ $q }}</span>
                    <svg :class="{ 'rotate-180': open }" class="w-5 h-5 text-gray-400 transition-transform flex-shrink-0 ml-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-collapse class="px-6 pb-5 text-gray-500 text-sm leading-relaxed">{{ $a }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===== CTA ===== --}}
<section class="gradient-hero py-24 relative overflow-hidden">
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-white/5 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 -left-24 w-80 h-80 bg-black/10 rounded-full blur-3xl"></div>
    </div>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
        <div class="text-6xl mb-8">🚀</div>
        <h2 class="text-4xl lg:text-5xl font-black text-white mb-6">Ready to Run WhatsApp<br>Like a Revenue Channel?</h2>
        <p class="text-xl text-white/75 mb-10 max-w-2xl mx-auto">Join thousands of businesses using our platform for team inbox, campaigns, workflows, and in-chat payments on WhatsApp.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('register') }}" class="bg-wa hover:bg-wa-dark text-white font-black px-10 py-5 rounded-full text-lg transition-all shadow-2xl hover:scale-105 hover:shadow-wa/30">
                Start Free Today →
            </a>
            <a href="mailto:{{ config('settings.contact_email', 'hello@' . request()->getHost()) }}" class="bg-white/10 hover:bg-white/20 backdrop-blur text-white font-semibold px-10 py-5 rounded-full text-lg transition-all border border-white/20">
                Contact Sales
            </a>
        </div>
        <p class="text-white/50 text-sm mt-8">No credit card required. Setup takes less than 5 minutes.</p>
    </div>
</section>

{{-- ===== FOOTER ===== --}}
<footer class="bg-gray-900 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-4 gap-12 mb-12">
            <div class="md:col-span-2">
                <div class="flex items-center gap-2 mb-4">
                    <img src="{{ config('settings.logo') }}" alt="{{ config('app.name') }}" class="h-8 w-auto">
                    <span class="font-bold text-xl text-white">{{ config('settings.site_name', config('app.name')) }}</span>
                </div>
                <p class="text-gray-400 text-sm leading-relaxed max-w-xs">WhatsApp revenue & operations platform — team inbox, campaigns, workflows, and payments. Built on the official Meta Business Cloud API.</p>
                <div class="flex gap-4 mt-6">
                    @if(config('settings.twitter_url'))
                    <a href="{{ config('settings.twitter_url') }}" class="text-gray-500 hover:text-white transition-colors">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                    @endif
                    @if(config('settings.facebook_url'))
                    <a href="{{ config('settings.facebook_url') }}" class="text-gray-500 hover:text-white transition-colors">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    @endif
                </div>
            </div>
            <div>
                <h4 class="text-white font-bold mb-5">Product</h4>
                <ul class="space-y-3 text-sm text-gray-400">
                    <li><a href="#features" class="hover:text-white transition-colors">Features</a></li>
                    <li><a href="#automation" class="hover:text-white transition-colors">Automation</a></li>
                    <li><a href="#integrations" class="hover:text-white transition-colors">Integrations</a></li>
                    <li><a href="#api" class="hover:text-white transition-colors">API</a></li>
                    @if(config('settings.enable_pricing'))<li><a href="#pricing" class="hover:text-white transition-colors">Pricing</a></li>@endif
                    @if(isset($hasBlog) && $hasBlog)<li><a href="/blog" class="hover:text-white transition-colors">Blog</a></li>@endif
                </ul>
            </div>
            <div>
                <h4 class="text-white font-bold mb-5">Company</h4>
                <ul class="space-y-3 text-sm text-gray-400">
                    <li><a href="{{ route('register') }}" class="hover:text-white transition-colors">Sign Up Free</a></li>
                    <li><a href="{{ route('login') }}" class="hover:text-white transition-colors">Log In</a></li>
                    @if(config('settings.terms_url'))<li><a href="{{ config('settings.terms_url') }}" class="hover:text-white transition-colors">Terms of Service</a></li>@endif
                    @if(config('settings.privacy_url'))<li><a href="{{ config('settings.privacy_url') }}" class="hover:text-white transition-colors">Privacy Policy</a></li>@endif
                    <li><a href="mailto:{{ config('settings.contact_email', 'hello@' . request()->getHost()) }}" class="hover:text-white transition-colors">Contact Us</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-gray-500">
            <div>© {{ date('Y') }} {{ config('settings.site_name', config('app.name')) }}. All rights reserved.</div>
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-wa fill-current" viewBox="0 0 24 24"><path d="M17.498 14.382c-.301-.15-1.767-.867-2.04-.966-.273-.101-.473-.15-.673.15-.197.295-.771.964-.944 1.162-.175.195-.349.21-.646.075-.3-.15-1.263-.465-2.403-1.485-.888-.795-1.484-1.77-1.66-2.07-.174-.3-.019-.465.13-.615.136-.135.301-.345.451-.523.146-.181.194-.301.297-.496.1-.21.049-.375-.025-.524-.075-.15-.672-1.62-.922-2.206-.24-.584-.487-.51-.672-.51-.172-.015-.371-.015-.571-.015-.2 0-.523.074-.797.359-.273.3-1.045 1.02-1.045 2.475s1.07 2.865 1.219 3.075c.149.195 2.105 3.195 5.1 4.485.714.3 1.27.48 1.704.629.714.227 1.365.195 1.88.121.574-.091 1.767-.721 2.016-1.426.255-.705.255-1.29.18-1.425-.074-.135-.27-.21-.57-.345m-5.446 7.443h-.016c-1.77 0-3.524-.48-5.055-1.38l-.36-.214-3.75.975 1.005-3.645-.239-.375a9.869 9.869 0 01-1.516-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                <span>Built on Meta WhatsApp Business API</span>
            </div>
        </div>
    </div>
</footer>

{{-- Alpine.js for FAQ accordion --}}
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
    // Smooth scroll offset for fixed navbar
    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', e => {
            const target = document.querySelector(a.getAttribute('href'));
            if (target) {
                e.preventDefault();
                window.scrollTo({ top: target.offsetTop - 72, behavior: 'smooth' });
            }
        });
    });
</script>

</body>
</html>