<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Zenith Assets | Reach your highest point</title>
<meta name="description" content="Zenith Assets is a community built for rising together. Start small, grow automatically and earn in USDT.">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { theme: { extend: {
  fontFamily: { display: ['"Instrument Serif"','Georgia','serif'], sans: ['"DM Sans"','system-ui','sans-serif'] },
  colors: { night:'#0a0d2b', dusk:'#1a1450', ember:'#ff9a3c', sun:'#ffd27a', cream:'#fff4e0', teal:'#2fd4a4' }
}}}
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  body { background:#0a0d2b; }
  .sky { background:linear-gradient(180deg,#0a0d2b 0%,#1a1450 45%,#3b2a7a 75%,#7a3a62 100%); }
  .star { animation:tw 4s ease-in-out infinite; }
  @keyframes tw { 50% { opacity:.2; } }
  .ridge1 { animation:slide 1.6s ease-out both; }
  @keyframes slide { from { transform:translateY(40px); opacity:0; } }
  .fade { animation:fadeup 1.2s .6s ease-out both; }
  @keyframes fadeup { from { opacity:0; transform:translateY(20px); } }
  .shine { background:linear-gradient(100deg,#fff4e0,#ffd27a,#ff9a3c,#ffd27a,#fff4e0); background-size:250% auto; -webkit-background-clip:text; background-clip:text; color:transparent; animation:sh 7s linear infinite; }
  @keyframes sh { to { background-position:250% center; } }
  .photo { background-size:cover; background-position:center; }
  .path-draw { stroke-dasharray:1200; stroke-dashoffset:1200; transition:stroke-dashoffset 2.4s ease; }
  .in .path-draw { stroke-dashoffset:0; }
  .rv { opacity:0; transform:translateY(22px); transition:opacity .8s, transform .8s; }
  .rv.in { opacity:1; transform:none; }
  @media (prefers-reduced-motion:reduce){ .star,.ridge1,.fade,.shine{ animation:none; } .rv{ opacity:1; transform:none; transition:none; } .path-draw{ stroke-dashoffset:0; transition:none; } html{scroll-behavior:auto;} }
  a:focus-visible, summary:focus-visible { outline:2px solid #ffd27a; outline-offset:3px; }
</style>
</head>
<body class="font-sans text-cream/85 antialiased">

<header class="fixed top-0 inset-x-0 z-50">
  <div class="max-w-7xl mx-auto px-5 h-20 flex items-center justify-between">
    <a href="/" class="flex items-center gap-2.5 text-cream">
      <i class="fa-solid fa-mountain text-sun text-2xl"></i>
      <span class="font-display text-2xl">Zenith Assets</span>
    </a>
    <nav class="hidden md:flex items-center gap-8 text-sm font-medium">
      <a href="#rise" class="hover:text-sun transition">Why rise with us</a>
      <a href="#ascent" class="hover:text-sun transition">The ascent</a>
      <a href="#plans" class="hover:text-sun transition">Plans</a>
      <a href="#faq" class="hover:text-sun transition">Questions</a>
    </nav>
    <div class="flex items-center gap-3">
      <a href="/auth/login.php" class="text-sm font-medium hover:text-sun transition">Sign in</a>
      <a href="/auth/register.php" class="px-5 py-2.5 rounded-full text-sm font-bold bg-sun text-night hover:bg-white transition">Join now</a>
    </div>
  </div>
</header>

<main>
<!-- HERO: sunrise over ridgelines -->
<section class="sky relative min-h-screen overflow-hidden flex items-center">
  <div class="absolute inset-0" aria-hidden="true">
    <span class="star absolute top-[14%] left-[12%] w-1 h-1 rounded-full bg-cream"></span>
    <span class="star absolute top-[22%] left-[70%] w-1.5 h-1.5 rounded-full bg-cream" style="animation-delay:-1s"></span>
    <span class="star absolute top-[9%] left-[48%] w-1 h-1 rounded-full bg-cream" style="animation-delay:-2s"></span>
    <span class="star absolute top-[30%] left-[88%] w-1 h-1 rounded-full bg-cream" style="animation-delay:-3s"></span>
    <span class="star absolute top-[34%] left-[6%] w-1 h-1 rounded-full bg-cream" style="animation-delay:-.5s"></span>
  </div>
  <!-- sharp peak -->
  <svg class="ridge1 absolute bottom-0 right-0 w-full h-[88%]" viewBox="0 0 1440 700" preserveAspectRatio="xMaxYMax slice" aria-hidden="true">
    <path d="M0 700 L0 540 L150 440 L290 520 L450 380 L620 540 L760 470 L920 700Z" fill="#2a1a5c" opacity=".75"/>
    <path d="M1050 30 L1004 175 L950 250 L905 372 L835 440 L740 700 L1040 700 L1058 420 L1050 250Z" fill="#5a4aa8"/>
    <path d="M1050 30 L1058 250 L1040 700 L1440 700 L1330 520 L1275 430 L1215 360 L1150 215 L1100 175Z" fill="#1c1456"/>
    <path d="M1050 30 L1004 175 L1030 164 L1047 205 L1064 160 L1100 175Z" fill="#fff4e0"/>
    <path d="M1050 30 L1064 160 L1100 175 L1082 130Z" fill="#cfc6e8"/>
    <path d="M0 700 L0 610 L220 565 L430 625 L700 585 L1000 645 L1440 595 L1440 700Z" fill="#0a0d2b"/>
  </svg>
  <div class="absolute inset-0 bg-gradient-to-r from-night/70 via-night/10 to-transparent md:from-night/30" aria-hidden="true"></div>

  <div class="relative max-w-6xl mx-auto px-5 w-full pt-28 pb-48">
    <div class="fade max-w-2xl">
      <p class="font-display italic text-2xl text-sun">Every summit begins at sunrise.</p>
      <h1 class="font-display text-6xl sm:text-8xl leading-[.95] text-cream mt-4">Reach your <span class="shine">highest point</span></h1>
      <p class="mt-7 text-lg text-cream/80 max-w-xl leading-relaxed">Zenith Assets is a community that climbs together. Begin with one plan, let your team grow around you, and be rewarded in USDT every step of the way.</p>
      <div class="mt-9 flex flex-wrap gap-4">
        <a href="/auth/register.php" class="px-8 py-4 rounded-full font-bold bg-sun text-night hover:bg-white transition shadow-xl shadow-ember/30">Start my ascent</a>
        <a href="#ascent" class="px-8 py-4 rounded-full font-bold border border-cream/40 hover:bg-cream/10 transition">See the path</a>
      </div>
    </div>
  </div>
</section>

<!-- RISE: three statements, open layout -->
<section id="rise" class="bg-night py-28">
  <div class="max-w-6xl mx-auto px-5">
    <h2 class="rv font-display text-5xl sm:text-6xl text-cream max-w-3xl leading-tight">A community that lifts you, <em class="text-sun">not leaves you behind.</em></h2>
    <div class="mt-16 divide-y divide-cream/10 border-y border-cream/10">
      <div class="rv grid md:grid-cols-12 gap-4 py-9 items-start">
        <i class="md:col-span-1 fa-solid fa-people-group text-sun text-3xl"></i>
        <h3 class="md:col-span-4 font-display text-3xl text-cream">Your team builds itself</h3>
        <p class="md:col-span-7 text-cream/65 text-lg leading-relaxed">New members are welcomed into your community automatically and fairly, so your progress never depends on who you can chase.</p>
      </div>
      <div class="rv grid md:grid-cols-12 gap-4 py-9 items-start">
        <i class="md:col-span-1 fa-solid fa-coins text-sun text-3xl"></i>
        <h3 class="md:col-span-4 font-display text-3xl text-cream">Rewards in USDT</h3>
        <p class="md:col-span-7 text-cream/65 text-lg leading-relaxed">A steady, trusted currency, with a clear record of every reward that reaches your account.</p>
      </div>
      <div class="rv grid md:grid-cols-12 gap-4 py-9 items-start">
        <i class="md:col-span-1 fa-solid fa-arrows-rotate text-sun text-3xl"></i>
        <h3 class="md:col-span-4 font-display text-3xl text-cream">A fresh start at every milestone</h3>
        <p class="md:col-span-7 text-cream/65 text-lg leading-relaxed">Reach a new height and a new opportunity opens up, so your momentum keeps climbing.</p>
      </div>
    </div>
  </div>
</section>

<!-- ASCENT: a real sequence, drawn as a trail -->
<section id="ascent" class="relative py-28 bg-gradient-to-b from-night to-dusk overflow-hidden">
  <div class="max-w-6xl mx-auto px-5 rv">
    <h2 class="font-display text-5xl sm:text-6xl text-cream">The ascent, in three stages</h2>
    <p class="mt-4 text-cream/65 text-lg max-w-xl">No maps to decode. Just a clear trail from your first day to your own summit.</p>
    <div class="relative mt-16">
      <svg class="hidden md:block absolute inset-x-0 top-16 w-full h-40" viewBox="0 0 1000 160" preserveAspectRatio="none" aria-hidden="true">
        <path class="path-draw" d="M20 140 C 200 140, 250 90, 400 90 S 650 60, 700 40 S 900 20, 980 12" fill="none" stroke="#ffd27a" stroke-width="2.5" stroke-dasharray="6 8"/>
      </svg>
      <div class="grid md:grid-cols-3 gap-12 relative">
        <div class="md:pt-32">
          <div class="w-14 h-14 rounded-full bg-sun text-night grid place-items-center text-xl"><i class="fa-solid fa-campground"></i></div>
          <h3 class="mt-5 font-display text-3xl text-cream">Base camp</h3>
          <p class="mt-2 text-cream/65">Create your free account in a minute and make your place in the community.</p>
        </div>
        <div class="md:pt-16">
          <div class="w-14 h-14 rounded-full bg-sun text-night grid place-items-center text-xl"><i class="fa-solid fa-flag"></i></div>
          <h3 class="mt-5 font-display text-3xl text-cream">The ridge</h3>
          <p class="mt-2 text-cream/65">Choose Starter or Pro, activate with USDT, and watch your team take shape.</p>
        </div>
        <div>
          <div class="w-14 h-14 rounded-full bg-sun text-night grid place-items-center text-xl"><i class="fa-solid fa-mountain"></i></div>
          <h3 class="mt-5 font-display text-3xl text-cream">The summit</h3>
          <p class="mt-2 text-cream/65">Collect your rewards, unlock fresh opportunities and keep rising.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- PLANS -->
<section id="plans" class="bg-dusk py-28">
  <div class="max-w-6xl mx-auto px-5">
    <h2 class="rv font-display text-5xl sm:text-6xl text-cream text-center">Choose your route</h2>
    <p class="rv mt-4 text-center text-cream/65 text-lg">Two ways up. Both end with a view worth the climb.</p>
    <div class="mt-16 grid lg:grid-cols-2 gap-6">
      <article class="rv rounded-[2rem] p-10 bg-night border border-cream/10 relative overflow-hidden">
        <i class="fa-solid fa-person-hiking absolute -right-3 -top-3 text-[8rem] text-cream/5"></i>
        <p class="font-display italic text-2xl text-teal">The Starter Trail</p>
        <p class="mt-3 font-display text-7xl text-cream">50 <span class="text-2xl text-cream/60">USDT</span></p>
        <ul class="mt-8 space-y-3 text-cream/80">
          <li class="flex gap-3"><i class="fa-solid fa-check text-teal mt-1.5"></i>Six stages of growth to climb</li>
          <li class="flex gap-3"><i class="fa-solid fa-check text-teal mt-1.5"></i>Up to 60% shared back with the community</li>
          <li class="flex gap-3"><i class="fa-solid fa-check text-teal mt-1.5"></i>Automatic team placement</li>
          <li class="flex gap-3"><i class="fa-solid fa-check text-teal mt-1.5"></i>Bonus renewal opportunities</li>
        </ul>
        <a href="/auth/register.php?pkg=package_1" class="mt-9 inline-block px-8 py-3.5 rounded-full font-bold bg-teal text-night hover:bg-white transition">Take the Starter Trail</a>
      </article>
      <article class="rv rounded-[2rem] p-10 relative overflow-hidden border border-sun/50" style="background:linear-gradient(160deg,#3a1c5c,#7a2f55 60%,#c2543a)">
        <span class="absolute top-6 right-6 px-3 py-1 rounded-full bg-sun text-night text-xs font-bold">For the ambitious</span>
        <p class="font-display italic text-2xl text-sun">The Summit Route</p>
        <p class="mt-3 font-display text-7xl text-cream">300 <span class="text-2xl text-cream/70">USDT</span></p>
        <ul class="mt-8 space-y-3 text-cream/90">
          <li class="flex gap-3"><i class="fa-solid fa-check text-sun mt-1.5"></i>Seven stages of growth to climb</li>
          <li class="flex gap-3"><i class="fa-solid fa-check text-sun mt-1.5"></i>Up to 60% shared back with the community</li>
          <li class="flex gap-3"><i class="fa-solid fa-check text-sun mt-1.5"></i>Its own independent growth path</li>
          <li class="flex gap-3"><i class="fa-solid fa-check text-sun mt-1.5"></i>Even more renewal opportunities</li>
        </ul>
        <a href="/auth/register.php?pkg=package_2" class="mt-9 inline-block px-8 py-3.5 rounded-full font-bold bg-sun text-night hover:bg-white transition">Take the Summit Route</a>
      </article>
    </div>
  </div>
</section>

<!-- PHOTO CTA -->
<section class="relative py-32 overflow-hidden">
  <div class="absolute inset-0 photo" style="background-color:#1a1450; background-image:url('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1800&q=70')"></div>
  <div class="absolute inset-0 bg-gradient-to-t from-night via-night/60 to-dusk/70"></div>
  <div class="rv relative max-w-3xl mx-auto px-5 text-center">
    <h2 class="font-display text-5xl sm:text-7xl text-cream leading-tight">The view is better <span class="shine">from the top</span></h2>
    <p class="mt-6 text-lg text-cream/80">Your first step takes one minute. Take it today.</p>
    <a href="/auth/register.php" class="inline-block mt-9 px-10 py-4 rounded-full font-bold bg-sun text-night hover:bg-white transition shadow-xl shadow-ember/30">Create my account</a>
  </div>
</section>

<!-- FAQ -->
<section id="faq" class="bg-night py-24">
  <div class="max-w-3xl mx-auto px-5">
    <h2 class="rv font-display text-5xl text-cream text-center">Before you set off</h2>
    <div class="rv mt-10 divide-y divide-cream/10 border-y border-cream/10">
      <details class="group py-5">
        <summary class="cursor-pointer list-none flex justify-between items-center text-lg font-medium text-cream">Do I need experience to join?<i class="fa-solid fa-plus text-sun transition group-open:rotate-45"></i></summary>
        <p class="mt-3 text-cream/65">No. Sign up, choose a plan, and the dashboard guides you from there.</p>
      </details>
      <details class="group py-5">
        <summary class="cursor-pointer list-none flex justify-between items-center text-lg font-medium text-cream">How do I get paid?<i class="fa-solid fa-plus text-sun transition group-open:rotate-45"></i></summary>
        <p class="mt-3 text-cream/65">Activations and rewards are all settled in USDT, so your earnings stay stable and easy to follow.</p>
      </details>
      <details class="group py-5">
        <summary class="cursor-pointer list-none flex justify-between items-center text-lg font-medium text-cream">Can I join both plans?<i class="fa-solid fa-plus text-sun transition group-open:rotate-45"></i></summary>
        <p class="mt-3 text-cream/65">Yes. Each plan grows on its own path, so you can take part in either or both.</p>
      </details>
    </div>
  </div>
</section>
</main>

<footer class="bg-night border-t border-cream/10 py-8">
  <div class="max-w-6xl mx-auto px-5 flex flex-col md:flex-row justify-between items-center gap-3 text-sm text-cream/50">
    <span class="font-display text-xl text-cream"><i class="fa-solid fa-mountain text-sun mr-2"></i>Zenith Assets</span>
    <span>Secure USDT settlements, every time &middot; &copy; 2026 Zenith Assets</span>
  </div>
</footer>

<script>
  const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }), { threshold: .15 });
  document.querySelectorAll('.rv').forEach(el => io.observe(el));
</script>
</body>
</html>
