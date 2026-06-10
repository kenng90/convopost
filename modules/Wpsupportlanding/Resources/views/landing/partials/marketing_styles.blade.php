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
        wa: { green: '#25D366', dark: '#128C7E', darker: '#075E54', light: '#dcfce7' }
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
  :root { --wa-green: #25D366; --hero-bg: #040f0c; }
  html { scroll-padding-top: 72px; }
  .noise::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
    pointer-events: none;
    z-index: 0;
  }
  .hero-glow {
    background: radial-gradient(ellipse 80% 50% at 50% -10%, rgba(37,211,102,0.18) 0%, transparent 70%),
                radial-gradient(ellipse 50% 40% at 80% 60%, rgba(18,140,126,0.12) 0%, transparent 60%);
  }
  .grid-pattern {
    background-image: linear-gradient(rgba(37,211,102,0.04) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(37,211,102,0.04) 1px, transparent 1px);
    background-size: 48px 48px;
  }
  .navbar-blur {
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    background: rgba(4,15,12,0.85);
    border-bottom: 1px solid rgba(37,211,102,0.08);
  }
  .grad-text {
    background: linear-gradient(135deg, #25D366 0%, #128C7E 50%, #a8e6cf 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }
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
  .card-lift {
    transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.25s ease, border-color 0.25s ease;
  }
  .card-lift:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 60px rgba(37,211,102,0.12);
    border-color: rgba(37,211,102,0.25);
  }
  .blog-card {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 1rem;
    overflow: hidden;
  }
  .blog-prose { color: #d1d5db; line-height: 1.75; }
  .blog-prose h2, .blog-prose h3 { color: #fff; font-family: 'Syne', sans-serif; margin-top: 1.5em; margin-bottom: 0.5em; }
  .blog-prose p { margin-bottom: 1.25em; }
  .blog-prose a { color: #25D366; text-decoration: underline; }
  .blog-prose ul, .blog-prose ol { margin: 1em 0; padding-left: 1.5em; }
  .blog-prose img { border-radius: 0.75rem; margin: 1.5em 0; }
  [x-cloak] { display: none !important; }
</style>
