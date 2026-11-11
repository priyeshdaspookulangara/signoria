<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Business Plan | Zenithar Assets</title>
  <meta name="description" content="Explore the Zenithar Assets business plan: tier comparison and level-by-level earnings tables for the 50 USDT and 300 USDT tiers.">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config={theme:{extend:{fontFamily:{display:['"Cormorant Garamond"','Georgia','serif'],sans:['Montserrat','system-ui','sans-serif']},colors:{ink:'#060c1f',navy:'#0b1633',deep:'#10224a',gold:'#c9a24b',lgold:'#ecd08a',azure:'#14709f',paper:'#f7f5ef'}}}}
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
    .rv{opacity:0;transform:translateY(22px);transition:opacity .8s,transform .8s}
    .rv.in{opacity:1;transform:none}
    table td,table th{padding:.85rem 1rem}
    @media(prefers-reduced-motion:reduce){.rv{opacity:1;transform:none;transition:none}}
    a:focus-visible{outline:2px solid #ecd08a;outline-offset:3px}
  </style>
</head>
<body class="font-sans text-paper/80 antialiased">
  <header class="fixed top-0 inset-x-0 z-50 bg-ink/80 backdrop-blur border-b border-gold/15">
    <div class="max-w-7xl mx-auto px-5 h-20 flex items-center justify-between">
      <a href="/" class="leading-none" aria-label="Zenithar Assets home"><span class="block font-display text-2xl font-semibold text-paper">Zenithar</span><span class="block text-[10px] tracking-[.35em] uppercase text-lgold mt-1">Assets</span></a>
      <nav class="hidden md:flex gap-8 text-sm font-medium">
        <a href="/" class="hover:text-lgold transition">Home</a>
        <a href="#compare" class="hover:text-lgold transition">Compare</a>
        <a href="#tier1" class="hover:text-lgold transition">Tier 1</a>
        <a href="#tier2" class="hover:text-lgold transition">Tier 2</a>
      </nav>
      <div class="flex items-center gap-3">
        <a href="/auth/login.php" class="hidden sm:inline text-sm font-medium hover:text-lgold transition">Sign in</a>
        <a href="/auth/register.php" class="goldbtn px-5 py-2.5 rounded-full text-sm font-bold hover:brightness-110 transition">Join now</a>
      </div>
    </div>
  </header>

  <main>
    <!-- HERO -->
    <section class="pt-36 pb-16 text-center" style="background:radial-gradient(ellipse at 50% 0%,#17306a 0%,#0b1633 50%,#060c1f 85%)">
      <div class="max-w-3xl mx-auto px-5">
        <p class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full card text-xs tracking-widest uppercase text-lgold"><i class="fa-solid fa-chart-column"></i> Business plan</p>
        <h1 class="mt-6 font-display font-semibold text-5xl sm:text-7xl leading-[1.02] text-paper">Two tiers. <span class="goldtext">One clear path.</span></h1>
        <p class="mt-6 text-lg text-paper/70 leading-relaxed">See exactly how each tier grows, level by level, and pick the one that fits your goals.</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3 text-sm">
          <a href="#tier1" class="px-6 py-3 rounded-full border border-gold/50 text-lgold hover:bg-gold/10 transition">Tier 1 &middot; 50 USDT</a>
          <a href="#tier2" class="px-6 py-3 rounded-full border border-gold/50 text-lgold hover:bg-gold/10 transition">Tier 2 &middot; 300 USDT</a>
        </div>
      </div>
    </section>

    <!-- COMPARE -->
    <section id="compare" class="py-20 bg-ink">
      <div class="max-w-5xl mx-auto px-5">
        <h2 class="rv font-display font-semibold text-4xl sm:text-5xl text-paper text-center">Tiers at a <span class="goldtext">glance</span></h2>
        <div class="rv mt-10 card rounded-3xl overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full min-w-[560px] text-sm text-center">
              <thead class="bg-gold/10 text-paper"><tr><th class="text-left"></th><th scope="col">Tier 1</th><th scope="col" class="text-lgold">Tier 2</th></tr></thead>
              <tbody class="divide-y divide-gold/10">
                <tr><th scope="row" class="text-left font-semibold text-paper">Entry price</th><td>50 USDT</td><td>300 USDT</td></tr>
                <tr><th scope="row" class="text-left font-semibold text-paper">Levels of growth</th><td>6 levels</td><td>7 levels</td></tr>
                <tr><th scope="row" class="text-left font-semibold text-paper">Community capacity</th><td>1,092 members<br><span class="text-xs text-paper/50">3 + 9 + 27 + 81 + 243 + 729</span></td><td>3,279 members<br><span class="text-xs text-paper/50">up to 2,187 on level 7</span></td></tr>
                <tr><th scope="row" class="text-left font-semibold text-paper">Renewal opportunities</th><td>Levels 4, 5 and 6</td><td>Levels 4, 5, 6 and 7</td></tr>
                <tr><th scope="row" class="text-left font-semibold text-paper">Team placement</th><td>Automatic</td><td>Automatic, on its own path</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>

    <!-- TIER 1 -->
    <section id="tier1" class="py-20 bg-navy">
      <div class="max-w-5xl mx-auto px-5">
        <div class="rv text-center">
          <p class="text-xs tracking-[.3em] uppercase text-gold">Tier 1 &middot; 50 USDT</p>
          <h2 class="mt-3 font-display font-semibold text-4xl sm:text-5xl text-paper">6-level earnings <span class="goldtext">table</span></h2>
        </div>
        <div id="t1" class="rv mt-10 card rounded-3xl overflow-hidden glow"></div>
        <div class="rv mt-8 text-center"><a href="/auth/register.php?pkg=package_1" class="goldbtn inline-block px-8 py-4 rounded-full font-bold hover:brightness-110 transition">Join Tier 1 for 50 USDT</a></div>
      </div>
    </section>

    <!-- TIER 2 -->
    <section id="tier2" class="py-20 bg-ink">
      <div class="max-w-5xl mx-auto px-5">
        <div class="rv text-center">
          <p class="text-xs tracking-[.3em] uppercase text-gold">Tier 2 &middot; 300 USDT</p>
          <h2 class="mt-3 font-display font-semibold text-4xl sm:text-5xl text-paper">7-level earnings <span class="goldtext">table</span></h2>
        </div>
        <div id="t2" class="rv mt-10 card rounded-3xl overflow-hidden glow"></div>
        <div class="rv mt-8 text-center"><a href="/auth/register.php?pkg=package_2" class="goldbtn inline-block px-8 py-4 rounded-full font-bold hover:brightness-110 transition">Join Tier 2 for 300 USDT</a></div>
        <p class="rv mt-8 text-center text-xs text-paper/45 max-w-2xl mx-auto">Projections assume every position on every level is filled; "effective" figures are 60% of potential. Results depend on community growth and are not guaranteed.</p>
      </div>
    </section>

    <section class="py-20 text-center" style="background:radial-gradient(ellipse at center,#17306a 0%,#0b1633 60%,#060c1f 100%)">
      <div class="rv max-w-2xl mx-auto px-5">
        <h2 class="font-display font-semibold text-4xl sm:text-6xl text-paper leading-tight">Ready to <span class="goldtext">begin?</span></h2>
        <a href="/auth/register.php" class="goldbtn inline-block mt-8 px-10 py-4 rounded-full font-bold hover:brightness-110 transition glow">Create my account</a>
      </div>
    </section>
  </main>

  <footer class="bg-ink border-t border-gold/15 py-8 text-center text-xs text-paper/45"> &copy; 2026 Zenithar Assets. Participation involves financial risk. Please read the terms before joining.</footer>

  <script>
    /* Edit commission per head here. Tier 2 rates filled with values from image. */
    const TIERS = {
      t1: { rates: [1.5, 2, 4, 6, 8, 10] },
      t2: { rates: [10, 20, 30, 40, 50, 60, 70] }
    };

    const f = n => n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function build(id, cfg) {
      let rows = '', m = 0, c = 0, ce = 0, p = 0, e = 0, missing = false;
      cfg.rates.forEach((r, i) => {
        const n = Math.pow(3, i + 1);
        m += n;
        if (r == null) {
          missing = true;
          rows += `<tr><th scope="row" class="text-left font-medium">Level ${i + 1}</th><td>${n.toLocaleString('en-US')}</td><td>&ndash;</td><td class="text-emerald-400 font-semibold">&ndash;</td><td>&ndash;</td><td class="text-lgold font-bold">&ndash;</td></tr>`;
          return;
        }
        const pot = n * r, eff = pot * .6, effPerHead = r * .6;
        c += r; ce += effPerHead; p += pot; e += eff;
        rows += `<tr><th scope="row" class="text-left font-medium">Level ${i + 1}</th><td>${n.toLocaleString('en-US')}</td><td>${f(r)}</td><td class="text-emerald-400 font-semibold">${f(effPerHead)}</td><td>${f(pot)}</td><td class="text-lgold font-bold">${f(eff)}</td></tr>`;
      });

      const tot = missing ? ['&ndash;', '&ndash;'] : [f(p) + ' USDT', f(e) + ' USDT'];
      document.getElementById(id).innerHTML = `<div class="overflow-x-auto"><table class="w-full min-w-[640px] text-sm text-center"> <thead class="bg-gold/10 text-paper"><tr><th scope="col" class="text-left">Downline Level</th><th scope="col">Members on Level (3<sup>L</sup>)</th><th scope="col">Base Rate per Head (USDT)</th><th scope="col" class="text-emerald-400">Effective Payout per Head<span class="block text-xs font-normal text-paper/50">60% Net Payout</span></th><th scope="col">Potential Total<span class="block text-xs font-normal text-paper/50">Members &times; Base Rate</span></th><th scope="col" class="text-lgold">Effective Total<span class="block text-xs font-normal text-paper/50">Customer Net Earnings</span></th></tr></thead> <tbody class="divide-y divide-gold/10 text-paper/80">${rows}</tbody> <tfoot class="goldbtn font-bold"><tr><th scope="row" class="text-left">TOTALS</th><td>${m.toLocaleString('en-US')}</td><td>${missing ? '&ndash;' : f(c)}</td><td class="text-emerald-950">${missing ? '&ndash;' : f(ce)}</td><td>${tot[0]}</td><td>${tot[1]}</td></tr></tfoot></table></div>`;
    }

    build('t1', TIERS.t1);
    build('t2', TIERS.t2);

    const io = new IntersectionObserver(es => es.forEach(x => {
      if (x.isIntersecting) {
        x.target.classList.add('in');
        io.unobserve(x.target);
      }
    }), { threshold: .1 });

    document.querySelectorAll('.rv').forEach(el => io.observe(el));
  </script>
</body>
</html>