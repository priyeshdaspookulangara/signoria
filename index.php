<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Zenithar Assets | Grow with a community that rises together</title>
<meta name="description" content="Zenithar Assets is a premium community platform. Start with 50 or 300 USDT, grow with automatic team placement and fresh opportunities at every milestone.">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={theme:{extend:{
 fontFamily:{display:['"Cormorant Garamond"','Georgia','serif'],sans:['Montserrat','system-ui','sans-serif']},
 colors:{ink:'#060c1f',navy:'#0b1633',deep:'#10224a',gold:'#c9a24b',lgold:'#ecd08a',azure:'#14709f',paper:'#f7f5ef'}
}}}
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
 body{background:#060c1f}
 .goldtext{background:linear-gradient(100deg,#c9a24b,#f4e0a1,#c9a24b);-webkit-background-clip:text;background-clip:text;color:transparent}
 .goldbtn{background:linear-gradient(120deg,#c9a24b,#ecd08a 50%,#c9a24b);color:#0b1633}
 .card{background:linear-gradient(160deg,rgba(255,255,255,.06),rgba(255,255,255,.02));border:1px solid rgba(201,162,75,.22)}
 .glow{box-shadow:0 0 80px -20px rgba(201,162,75,.45)}
 .float{animation:fl 6s ease-in-out infinite}
 @keyframes fl{50%{transform:translateY(-10px)}}
 .rv{opacity:0;transform:translateY(22px);transition:opacity .8s,transform .8s}
 .rv.in{opacity:1;transform:none}
 @media(prefers-reduced-motion:reduce){.float{animation:none}.rv{opacity:1;transform:none;transition:none}}
 a:focus-visible,summary:focus-visible{outline:2px solid #ecd08a;outline-offset:3px}
 details summary::-webkit-details-marker{display:none}
</style>
</head>
<body class="font-sans text-paper/80 antialiased">

<header class="fixed top-0 inset-x-0 z-50 bg-ink/80 backdrop-blur border-b border-gold/15">
 <div class="max-w-7xl mx-auto px-5 h-20 flex items-center justify-between">
  <a href="/" class="flex items-center gap-3" aria-label="Zenithar Assets">
   <svg width="46" height="40" viewBox="0 0 46 40" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <defs><linearGradient id="g1" x1="0" x2="1"><stop offset="0" stop-color="#c9a24b"/><stop offset="1" stop-color="#ecd08a"/></linearGradient>
    <linearGradient id="g2" x1="0" x2="1"><stop offset="0" stop-color="#14709f"/><stop offset="1" stop-color="#0b3a63"/></linearGradient></defs>
    <path d="M4 5h17L6 24h16" stroke="url(#g1)" stroke-width="3.5"/>
    <path d="M24 24c0-12 5-19 12-19s8 9 7 19c-1 4-5 4-5 0s4-6 0-6-8 6-8 6" stroke="url(#g2)" stroke-width="3.5" transform="translate(-4 0)"/>
    <path d="M12 34c6 4 16 4 22 0" stroke="url(#g1)" stroke-width="3"/>
   </svg>
   <span class="leading-none"><span class="block font-display text-2xl font-semibold text-paper">Zenithar</span><span class="block text-[10px] tracking-[.35em] uppercase text-lgold mt-1">Assets</span></span>
  </a>
  <nav class="hidden md:flex gap-8 text-sm font-medium">
   <a href="/business-plan/index.php" class="text-lgold font-semibold hover:text-white transition">Business Plan</a>
   <a href="#benefits" class="hover:text-lgold transition">Benefits</a>
   <a href="#journey" class="hover:text-lgold transition">How it works</a>
   <a href="#packages" class="hover:text-lgold transition">Packages</a>
   <a href="#earnings" class="hover:text-lgold transition">Earnings</a>
   <a href="#faq" class="hover:text-lgold transition">FAQ</a>
  </nav>
  <div class="flex items-center gap-3">
   <a href="/auth/login.php" class="hidden sm:inline text-sm font-medium hover:text-lgold transition">Sign in</a>
   <a href="/auth/register.php" class="goldbtn px-5 py-2.5 rounded-full text-sm font-bold hover:brightness-110 transition">Join now</a>
  </div>
 </div>
</header>

<main>
<!-- HERO -->
<section class="relative overflow-hidden pt-32 pb-20 lg:pt-40 lg:pb-28" style="background:radial-gradient(ellipse at 75% 30%,#17306a 0%,#0b1633 45%,#060c1f 80%)">
 <div class="max-w-7xl mx-auto px-5 grid lg:grid-cols-2 gap-14 items-center">
  <div>
   <p class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full card text-xs tracking-widest uppercase text-lgold"><i class="fa-solid fa-gem"></i> Premium community platform</p>
   <h1 class="mt-6 font-display font-semibold text-5xl sm:text-7xl leading-[1.02] text-paper">Build your future,<br><span class="goldtext">one asset at a time.</span></h1>
   <p class="mt-6 text-lg text-paper/70 max-w-xl leading-relaxed">Zenithar Assets brings people together to grow side by side. Start with a single package, let your community form around you, and move up through every stage with clear, transparent USDT rewards.</p>
   <div class="mt-9 flex flex-wrap gap-4">
    <a href="/auth/register.php" class="goldbtn px-8 py-4 rounded-full font-bold hover:brightness-110 transition glow">Create free account</a>
    <a href="#packages" class="px-8 py-4 rounded-full font-semibold border border-gold/50 text-lgold hover:bg-gold/10 transition">Explore packages</a>
   </div>
   <dl class="mt-12 grid grid-cols-3 gap-4 max-w-lg">
    <div><dt class="font-display text-4xl goldtext font-bold">50</dt><dd class="text-xs text-paper/55 mt-1">USDT entry point</dd></div>
    <div><dt class="font-display text-4xl goldtext font-bold">7</dt><dd class="text-xs text-paper/55 mt-1">levels of growth</dd></div>
    <div><dt class="font-display text-4xl goldtext font-bold">24/7</dt><dd class="text-xs text-paper/55 mt-1">account access</dd></div>
   </dl>
  </div>
  <div class="relative">
   <div class="absolute -inset-6 rounded-[3rem] bg-gold/10 blur-3xl" aria-hidden="true"></div>
   <img src="assets/hero-candles.jpg" alt="Golden candlestick waves rising on a deep navy background" class="relative rounded-[2rem] border border-gold/30 glow w-full aspect-[16/11] object-cover object-top" width="1600" height="1100" fetchpriority="high">
   <div class="float absolute -bottom-6 left-4 sm:-left-6 card backdrop-blur-xl bg-navy/80 rounded-2xl px-5 py-4 flex items-center gap-3">
    <span class="w-11 h-11 grid place-items-center rounded-full goldbtn"><i class="fa-solid fa-arrow-trend-up"></i></span>
    <span><span class="block text-sm font-bold text-paper">Rewards in USDT</span><span class="block text-xs text-paper/55">Stable &amp; easy to track</span></span>
   </div>
  </div>
 </div>
</section>

<!-- TRUST STRIP -->
<section class="bg-ink border-y border-gold/15">
 <div class="max-w-7xl mx-auto px-5 py-6 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-paper/70">
  <span class="flex items-center gap-3"><i class="fa-solid fa-shield-halved text-gold text-lg"></i>Secure account protection</span>
  <span class="flex items-center gap-3"><i class="fa-solid fa-receipt text-gold text-lg"></i>Full earnings history</span>
  <span class="flex items-center gap-3"><i class="fa-solid fa-globe text-gold text-lg"></i>Members worldwide</span>
  <span class="flex items-center gap-3"><i class="fa-solid fa-headset text-gold text-lg"></i>Friendly member support</span>
 </div>
</section>

<!-- BENEFITS -->
<section id="benefits" class="py-24 bg-navy">
 <div class="max-w-7xl mx-auto px-5">
  <div class="rv max-w-2xl">
   <p class="text-xs tracking-[.3em] uppercase text-gold">Why Zenithar</p>
   <h2 class="mt-3 font-display font-semibold text-4xl sm:text-6xl text-paper leading-tight">Everything designed to <span class="goldtext">work for you</span></h2>
  </div>
  <div class="mt-14 grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
   <article class="rv card rounded-3xl p-7">
    <div class="w-12 h-12 rounded-2xl goldbtn grid place-items-center text-lg"><i class="fa-solid fa-people-group"></i></div>
    <h3 class="mt-5 font-display text-2xl font-semibold text-paper">Your team grows for you</h3>
    <p class="mt-2 text-sm leading-relaxed text-paper/65">New members are welcomed into your community automatically, so your progress doesn't depend on constant chasing.</p>
   </article>
   <article class="rv card rounded-3xl p-7">
    <div class="w-12 h-12 rounded-2xl goldbtn grid place-items-center text-lg"><i class="fa-solid fa-coins"></i></div>
    <h3 class="mt-5 font-display text-2xl font-semibold text-paper">Paid in USDT</h3>
    <p class="mt-2 text-sm leading-relaxed text-paper/65">A stable digital dollar for every activation and reward, with a clear record of each one in your dashboard.</p>
   </article>
   <article class="rv card rounded-3xl p-7">
    <div class="w-12 h-12 rounded-2xl goldbtn grid place-items-center text-lg"><i class="fa-solid fa-arrows-rotate"></i></div>
    <h3 class="mt-5 font-display text-2xl font-semibold text-paper">Fresh starts as you climb</h3>
    <p class="mt-2 text-sm leading-relaxed text-paper/65">Reach key milestones and new opportunities open up automatically, keeping your momentum going.</p>
   </article>
   <article class="rv card rounded-3xl p-7">
    <div class="w-12 h-12 rounded-2xl goldbtn grid place-items-center text-lg"><i class="fa-solid fa-mobile-screen"></i></div>
    <h3 class="mt-5 font-display text-2xl font-semibold text-paper">Simple, anywhere</h3>
    <p class="mt-2 text-sm leading-relaxed text-paper/65">A clean dashboard that works beautifully on your phone, tablet or computer. No experience needed.</p>
   </article>
  </div>
 </div>
</section>

<!-- JOURNEY -->
<section id="journey" class="py-24 bg-ink">
 <div class="max-w-6xl mx-auto px-5">
  <h2 class="rv font-display font-semibold text-4xl sm:text-6xl text-paper text-center">Your journey in <span class="goldtext">three steps</span></h2>
  <div class="mt-16 grid md:grid-cols-3 gap-10 relative">
   <div class="hidden md:block absolute top-8 left-[16%] right-[16%] border-t-2 border-dashed border-gold/40" aria-hidden="true"></div>
   <div class="rv relative text-center">
    <div class="mx-auto w-16 h-16 rounded-full goldbtn grid place-items-center font-display text-2xl font-bold relative">1</div>
    <h3 class="mt-5 font-display text-2xl font-semibold text-paper">Create your account</h3>
    <p class="mt-2 text-sm text-paper/65 max-w-xs mx-auto">Sign up free in about a minute and get your personal dashboard.</p>
   </div>
   <div class="rv relative text-center">
    <div class="mx-auto w-16 h-16 rounded-full goldbtn grid place-items-center font-display text-2xl font-bold relative">2</div>
    <h3 class="mt-5 font-display text-2xl font-semibold text-paper">Pick your package</h3>
    <p class="mt-2 text-sm text-paper/65 max-w-xs mx-auto">Choose the 50 or 300 USDT package and activate it securely.</p>
   </div>
   <div class="rv relative text-center">
    <div class="mx-auto w-16 h-16 rounded-full goldbtn grid place-items-center font-display text-2xl font-bold relative">3</div>
    <h3 class="mt-5 font-display text-2xl font-semibold text-paper">Grow &amp; collect rewards</h3>
    <p class="mt-2 text-sm text-paper/65 max-w-xs mx-auto">Watch your community form, unlock new levels and track every reward.</p>
   </div>
  </div>
 </div>
</section>

<!-- PACKAGES -->
<section id="packages" class="py-24 bg-navy">
 <div class="max-w-6xl mx-auto px-5">
  <h2 class="rv font-display font-semibold text-4xl sm:text-6xl text-paper text-center">Choose your <span class="goldtext">package</span></h2>
  <p class="rv mt-4 text-center text-paper/60">Two ways to begin. Both are built to keep growing with you.</p>
  <div class="mt-14 grid lg:grid-cols-2 gap-6">
   <article class="rv card rounded-[2rem] p-9 sm:p-10">
    <p class="text-xs tracking-[.3em] uppercase text-lgold">Package I</p>
    <h3 class="mt-2 font-display text-3xl font-semibold text-paper">Starter</h3>
    <p class="mt-4 font-display text-7xl font-bold goldtext leading-none">50 <span class="text-2xl text-paper/60 font-sans font-medium">USDT</span></p>
    <ul class="mt-8 space-y-3.5 text-sm">
     <li class="flex gap-3"><i class="fa-solid fa-circle-check text-gold mt-0.5"></i>Grow through 6 levels</li>
     <li class="flex gap-3"><i class="fa-solid fa-circle-check text-gold mt-0.5"></i>Room for over 1,000 community members</li>
     <li class="flex gap-3"><i class="fa-solid fa-circle-check text-gold mt-0.5"></i>Automatic team placement</li>
     <li class="flex gap-3"><i class="fa-solid fa-circle-check text-gold mt-0.5"></i>5 rebirth opportunities (Levels 4, 5, and 6)</li>
     <li class="flex gap-3"><i class="fa-solid fa-circle-check text-gold mt-0.5"></i>300 USDT upgrade reserved at Level 5 to enter Tier 2</li>
    </ul>
    <a href="/auth/register.php?pkg=package_1" class="mt-9 block text-center px-8 py-4 rounded-full font-bold border border-gold/60 text-lgold hover:bg-gold hover:text-navy transition">Start with Package I</a>
   </article>
   <article class="rv relative rounded-[2rem] p-9 sm:p-10 border border-gold glow" style="background:linear-gradient(160deg,#173a7a,#0f2455 55%,#0b1633)">
    <span class="absolute top-6 right-6 goldbtn text-[11px] font-bold px-3 py-1 rounded-full">Most ambitious</span>
    <p class="text-xs tracking-[.3em] uppercase text-lgold">Package II</p>
    <h3 class="mt-2 font-display text-3xl font-semibold text-paper">Premier</h3>
    <p class="mt-4 font-display text-7xl font-bold goldtext leading-none">300 <span class="text-2xl text-paper/60 font-sans font-medium">USDT</span></p>
    <ul class="mt-8 space-y-3.5 text-sm">
     <li class="flex gap-3"><i class="fa-solid fa-circle-check text-gold mt-0.5"></i>Grow through 7 levels</li>
     <li class="flex gap-3"><i class="fa-solid fa-circle-check text-gold mt-0.5"></i>Room for over 3,000 community members</li>
     <li class="flex gap-3"><i class="fa-solid fa-circle-check text-gold mt-0.5"></i>Automatic team placement</li>
     <li class="flex gap-3"><i class="fa-solid fa-circle-check text-gold mt-0.5"></i>10 rebirth opportunities (Levels 4, 5, 6, and 7)</li>
    </ul>
    <a href="/auth/register.php?pkg=package_2" class="goldbtn mt-9 block text-center px-8 py-4 rounded-full font-bold hover:brightness-110 transition">Go Premier with Package II</a>
   </article>
  </div>
  <p class="mt-8 text-center text-xs text-paper/45">You may join one package or both. Each grows on its own path.</p>
 </div>
</section>

<!-- TIER 1 EARNINGS TABLE -->
<section id="earnings" class="py-24 bg-ink">
 <div class="max-w-5xl mx-auto px-5">
  <div class="rv text-center">
   <p class="text-xs tracking-[.3em] uppercase text-gold">Starter &middot; 50 USDT</p>
   <h2 class="mt-3 font-display font-semibold text-4xl sm:text-6xl text-paper">Exclusive Tier <span class="goldtext">Architecture</span></h2>
   <p class="mt-4 text-paper/60">6-level 3-matrix downline earnings table &ndash; calculated projections</p>
  </div>
  <div class="rv mt-12 card rounded-3xl overflow-hidden glow">
   <div class="overflow-x-auto">
    <table class="w-full min-w-[640px] text-sm text-center">
     <thead class="bg-gold/10 text-paper">
      <tr>
       <th scope="col" class="py-4 px-4 font-semibold text-left">Downline Level</th>
       <th scope="col" class="py-4 px-4 font-semibold">Members on Level (3<sup>L</sup>)</th>
       <th scope="col" class="py-4 px-4 font-semibold">Commission per Head (USDT)</th>
       <th scope="col" class="py-4 px-4 font-semibold">Potential Total<span class="block text-xs font-normal text-paper/50">Members &times; Commission</span></th>
       <th scope="col" class="py-4 px-4 font-semibold text-lgold">Effective Total<span class="block text-xs font-normal text-paper/50">60% of Potential</span></th>
      </tr>
     </thead>
     <tbody class="divide-y divide-gold/10 text-paper/80">
      <tr><th scope="row" class="py-3.5 px-4 text-left font-medium">Level 1</th><td>3</td><td>1.50</td><td>4.50</td><td class="text-lgold font-semibold">2.70</td></tr>
      <tr><th scope="row" class="py-3.5 px-4 text-left font-medium">Level 2</th><td>9</td><td>2.00</td><td>18.00</td><td class="text-lgold font-semibold">10.80</td></tr>
      <tr><th scope="row" class="py-3.5 px-4 text-left font-medium">Level 3</th><td>27</td><td>4.00</td><td>108.00</td><td class="text-lgold font-semibold">64.80</td></tr>
      <tr><th scope="row" class="py-3.5 px-4 text-left font-medium">Level 4</th><td>81</td><td>6.00</td><td>486.00</td><td class="text-lgold font-semibold">291.60</td></tr>
      <tr><th scope="row" class="py-3.5 px-4 text-left font-medium">Level 5</th><td>243</td><td>8.00</td><td>1,944.00</td><td class="text-lgold font-semibold">1,166.40</td></tr>
      <tr><th scope="row" class="py-3.5 px-4 text-left font-medium">Level 6</th><td>729</td><td>10.00</td><td>7,290.00</td><td class="text-lgold font-semibold">4,374.00</td></tr>
     </tbody>
     <tfoot class="goldbtn font-bold">
      <tr><th scope="row" class="py-4 px-4 text-left">TOTALS</th><td>1,092</td><td>31.50</td><td>9,850.50 USDT</td><td>5,910.30 USDT</td></tr>
     </tfoot>
    </table>
   </div>
  </div>
  <p class="rv mt-5 text-center text-xs text-paper/45 max-w-2xl mx-auto">Projections assume every position on every level is filled. Actual results depend on community growth and are not guaranteed.</p>
  <div class="rv mt-8 text-center"><a href="/auth/register.php?pkg=package_1" class="goldbtn inline-block px-8 py-4 rounded-full font-bold hover:brightness-110 transition">Start with the 50 USDT Starter</a></div>
 </div>
</section>

<!-- PHOTO BAND -->
<section class="relative py-28 overflow-hidden">
 <div class="absolute inset-0 bg-cover bg-center" style="background-color:#0b1633;background-image:url('https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1800&q=70')" aria-hidden="true"></div>
 <div class="absolute inset-0 bg-gradient-to-r from-ink via-ink/85 to-navy/60" aria-hidden="true"></div>
 <div class="rv relative max-w-6xl mx-auto px-5 grid md:grid-cols-2 gap-10 items-center">
  <div>
   <h2 class="font-display font-semibold text-4xl sm:text-6xl text-paper leading-tight">See every step <span class="goldtext">with total clarity</span></h2>
   <p class="mt-5 text-paper/70 max-w-md">Your dashboard shows your community, your levels and every reward in one elegant place, so you always know where you stand.</p>
  </div>
  <ul class="grid grid-cols-2 gap-4 text-sm">
   <li class="card rounded-2xl p-5 backdrop-blur"><i class="fa-solid fa-chart-line text-gold text-xl"></i><span class="block mt-3 font-semibold text-paper">Live progress</span></li>
   <li class="card rounded-2xl p-5 backdrop-blur"><i class="fa-solid fa-wallet text-gold text-xl"></i><span class="block mt-3 font-semibold text-paper">Wallet &amp; history</span></li>
   <li class="card rounded-2xl p-5 backdrop-blur"><i class="fa-solid fa-sitemap text-gold text-xl"></i><span class="block mt-3 font-semibold text-paper">Team overview</span></li>
   <li class="card rounded-2xl p-5 backdrop-blur"><i class="fa-solid fa-bell text-gold text-xl"></i><span class="block mt-3 font-semibold text-paper">Instant updates</span></li>
  </ul>
 </div>
</section>

<!-- FAQ -->
<section id="faq" class="py-24 bg-ink">
 <div class="max-w-3xl mx-auto px-5">
  <h2 class="rv font-display font-semibold text-4xl sm:text-5xl text-paper text-center">Good to <span class="goldtext">know</span></h2>
  <div class="rv mt-10 divide-y divide-gold/15 border-y border-gold/15">
   <details class="group py-5"><summary class="cursor-pointer list-none flex justify-between items-center gap-4 font-semibold text-paper">Do I need any experience?<i class="fa-solid fa-plus text-gold transition group-open:rotate-45"></i></summary><p class="mt-3 text-sm text-paper/65">No. Create an account, choose a package and your dashboard guides you through the rest.</p></details>
   <details class="group py-5"><summary class="cursor-pointer list-none flex justify-between items-center gap-4 font-semibold text-paper">How are payments handled?<i class="fa-solid fa-plus text-gold transition group-open:rotate-45"></i></summary><p class="mt-3 text-sm text-paper/65">Activations and rewards are all in USDT, and each transaction appears in your history.</p></details>
   <details class="group py-5"><summary class="cursor-pointer list-none flex justify-between items-center gap-4 font-semibold text-paper">Can I join both packages?<i class="fa-solid fa-plus text-gold transition group-open:rotate-45"></i></summary><p class="mt-3 text-sm text-paper/65">Yes. Each package grows on its own path, so you can take part in either or both.</p></details>
  </div>
 </div>
</section>

<!-- CTA -->
<section class="py-24 text-center" style="background:radial-gradient(ellipse at center,#17306a 0%,#0b1633 60%,#060c1f 100%)">
 <div class="rv max-w-3xl mx-auto px-5">
  <h2 class="font-display font-semibold text-5xl sm:text-7xl text-paper leading-tight">Your next level <span class="goldtext">starts today</span></h2>
  <p class="mt-5 text-paper/70">Join Zenithar Assets in about a minute.</p>
  <a href="/auth/register.php" class="goldbtn inline-block mt-9 px-10 py-4 rounded-full font-bold hover:brightness-110 transition glow">Create my account</a>
 </div>
</section>
</main>

<footer class="bg-ink border-t border-gold/15 py-10">
 <div class="max-w-6xl mx-auto px-5 text-center md:text-left grid md:grid-cols-2 gap-4 text-xs text-paper/45">
  <span class="font-display text-xl text-paper">Zenithar <span class="text-lgold text-[10px] tracking-[.3em] uppercase font-sans">Assets</span></span>
  <span class="md:text-right">&copy; 2026 Zenithar Assets. All rights reserved.</span>
  <p class="md:col-span-2 leading-relaxed">Participation involves financial risk and results are not guaranteed. Rewards depend on community activity. Please read the terms before joining.</p>
 </div>
</footer>

<script>
 const io=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}}),{threshold:.12});
 document.querySelectorAll('.rv').forEach(el=>io.observe(el));
</script>
</body>
</html>
