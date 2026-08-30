@include('wpsupportlanding::landing.partials.chatduka.favicon')
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700;9..144,800&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet" />
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        ink: '#1A1410',
        paper: '#FBF6EE',
        saffron: '#E8A317',
        terracotta: '#C45C26',
        indigo: { DEFAULT: '#1E2A5A', deep: '#151D40' },
        wa: { green: '#25D366' }
      },
      fontFamily: {
        display: ['Fraunces', 'Georgia', 'serif'],
        body: ['Plus Jakarta Sans', 'sans-serif'],
      }
    }
  }
}
</script>
<style>
  *, body { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 17px; line-height: 1.65; }
  h1,h2,h3,h4,h5,.font-display { font-family: 'Fraunces', Georgia, serif; }

  :root {
    --ink: #1A1410;
    --paper: #FBF6EE;
    --saffron: #E8A317;
    --terracotta: #C45C26;
    --indigo: #1E2A5A;
    --wa-green: #25D366;
    --hero-bg: #FBF6EE;
  }

  html { scroll-padding-top: 72px; }

  .noise::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.03'/%3E%3C/svg%3E");
    pointer-events: none;
    z-index: 0;
  }

  .hero-glow {
    background: radial-gradient(ellipse 80% 50% at 50% -10%, rgba(232,163,23,0.18) 0%, transparent 70%),
                radial-gradient(ellipse 50% 40% at 80% 60%, rgba(196,92,38,0.10) 0%, transparent 60%),
                radial-gradient(ellipse 40% 30% at 10% 80%, rgba(30,42,90,0.08) 0%, transparent 50%);
  }

  .grid-pattern {
    background-image: linear-gradient(rgba(30,42,90,0.05) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(30,42,90,0.05) 1px, transparent 1px);
    background-size: 48px 48px;
  }

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
    border: 2px solid var(--saffron);
    animation: pulse-ring 1.8s ease-out infinite;
  }

  .card-lift {
    transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.25s ease, border-color 0.25s ease;
  }
  .card-lift:hover {
    transform: translateY(-6px) scale(1.01);
    box-shadow: 0 20px 60px rgba(30,42,90,0.10);
    border-color: rgba(232,163,23,0.35);
  }

  .navbar-blur {
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    background: rgba(21,29,64,0.94);
    border-bottom: 1px solid rgba(232,163,23,0.18);
  }

  .phone-frame {
    background: #1A1410;
    border-radius: 40px;
    border: 2px solid rgba(232,163,23,0.22);
    box-shadow: 0 40px 100px rgba(30,42,90,0.18), 0 0 0 1px rgba(30,42,90,0.06);
  }
  .phone-notch {
    background: #0d0d1a;
    border-radius: 0 0 20px 20px;
    margin: 0 auto;
    width: 40%;
    height: 28px;
  }

  .bubble-in {
    background: #1f2c34;
    border-radius: 0 12px 12px 12px;
  }
  .bubble-out {
    background: #005c4b;
    border-radius: 12px 12px 0 12px;
  }

  .code-block {
    background: #151D40;
    border: 1px solid rgba(232,163,23,0.2);
    border-radius: 12px;
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 13px;
    line-height: 1.7;
  }

  .grad-text {
    color: #C45C26;
  }

  .flow-node {
    background: rgba(232,163,23,0.08);
    border: 1px solid rgba(232,163,23,0.28);
    border-radius: 12px;
    transition: all 0.2s ease;
  }
  .flow-node:hover {
    background: rgba(232,163,23,0.14);
    border-color: rgba(232,163,23,0.5);
    transform: scale(1.02);
  }
  .flow-line {
    width: 2px;
    background: linear-gradient(to bottom, rgba(232,163,23,0.45), rgba(232,163,23,0.1));
    margin: 0 auto;
    height: 24px;
  }

  .pricing-popular {
    background: linear-gradient(135deg, rgba(232,163,23,0.10), rgba(196,92,38,0.06));
    border: 1px solid rgba(232,163,23,0.35);
    position: relative;
  }
  .pricing-popular::before {
    content: '';
    position: absolute;
    inset: -1px;
    border-radius: inherit;
    background: linear-gradient(135deg, rgba(232,163,23,0.3), rgba(30,42,90,0.12));
    z-index: -1;
  }

  .stat-item { position: relative; }
  .stat-item + .stat-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 20%;
    height: 60%;
    width: 1px;
    background: rgba(30,42,90,0.12);
  }

  .badge {
    background: rgba(232,163,23,0.12);
    border: 1px solid rgba(232,163,23,0.35);
    color: #C45C26;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 4px 10px;
    border-radius: 999px;
  }

  ::-webkit-scrollbar { width: 6px; }
  ::-webkit-scrollbar-track { background: #F4EBD8; }
  ::-webkit-scrollbar-thumb { background: rgba(30,42,90,0.28); border-radius: 3px; }

  .blog-card {
    background: #fff;
    border: 1px solid rgba(30,42,90,0.08);
    border-radius: 1rem;
    overflow: hidden;
  }
  .blog-prose { color: #3f3a34; line-height: 1.75; }
  .blog-prose h2, .blog-prose h3 { color: #1A1410; font-family: 'Fraunces', Georgia, serif; margin-top: 1.5em; margin-bottom: 0.5em; }
  .blog-prose p { margin-bottom: 1.25em; }
  .blog-prose a { color: #C45C26; text-decoration: underline; }
  .blog-prose ul, .blog-prose ol { margin: 1em 0; padding-left: 1.5em; }
  .blog-prose img { border-radius: 0.75rem; margin: 1.5em 0; }

  [x-cloak] { display: none !important; }

  @media (prefers-reduced-motion: reduce) {
    .float, .float2, .float3, .pulse-ring::after { animation: none !important; }
    .card-lift, .integration-card, .flow-node { transition: none !important; }
  }

  a:focus-visible, button:focus-visible {
    outline: 2px solid #E8A317;
    outline-offset: 2px;
  }

  .section-label {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #C45C26;
  }
  .hero-brand {
    font-size: clamp(2rem, 3.6vw, 3.25rem);
    line-height: 1;
    letter-spacing: -0.03em;
    text-transform: none;
  }
  .hero-title {
    font-size: clamp(2.85rem, 6vw, 4.75rem);
    line-height: 1.05;
    letter-spacing: -0.035em;
  }
  .section-title {
    font-size: clamp(2.15rem, 4.2vw, 3.35rem);
    line-height: 1.12;
    letter-spacing: -0.03em;
  }
  .body-lg {
    font-size: clamp(1.125rem, 1.4vw, 1.25rem);
    line-height: 1.7;
  }
  .hero-phone {
    width: min(100%, 320px);
    height: 560px;
    margin-left: auto;
    margin-right: auto;
    transform: rotate(-2deg);
    box-shadow: 0 32px 80px rgba(30,42,90,0.16), 0 0 0 1px rgba(30,42,90,0.06);
  }
  @media (min-width: 1024px) {
    .hero-phone {
      margin-right: 0;
      transform: rotate(-3deg) translateY(8px);
    }
  }
  .infographic-panel {
    box-shadow: 0 24px 60px rgba(30,42,90,0.08), inset 0 1px 0 rgba(255,255,255,0.8);
  }
  .outcome-num {
    font-size: clamp(3rem, 8vw, 5.5rem);
    line-height: 0.95;
    letter-spacing: -0.03em;
  }
  .reveal-line {
    background: linear-gradient(90deg, transparent, rgba(232,163,23,0.4), transparent);
    height: 1px;
  }
  @keyframes dash-flow {
    to { stroke-dashoffset: -24; }
  }
  .infographic-panel svg path[stroke-dasharray] {
    animation: dash-flow 2.4s linear infinite;
  }
  @media (prefers-reduced-motion: reduce) {
    .infographic-panel svg path[stroke-dasharray] { animation: none !important; }
  }
</style>
