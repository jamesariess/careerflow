<?php
require_once '../components/layout.php';
cf_layout_head('Analytics');
cf_layout_sidebar('analytics');

$uid    = (int)$user['id'];
$dbUser = DB::one('SELECT * FROM users WHERE id=?', [$uid]);
$curr   = $dbUser['currency'] ?? 'USD';

// Overview stats
$total      = (int)(DB::one('SELECT COUNT(*) AS n FROM applications WHERE user_id=?', [$uid])['n'] ?? 0);
$hired      = (int)(DB::one("SELECT COUNT(*) AS n FROM applications WHERE user_id=? AND status='Hired'", [$uid])['n'] ?? 0);
$offers     = (int)(DB::one("SELECT COUNT(*) AS n FROM applications WHERE user_id=? AND status IN ('Offer','Hired')", [$uid])['n'] ?? 0);
$interviews = (int)(DB::one("SELECT COUNT(*) AS n FROM applications WHERE user_id=? AND status IN ('Interview','Final Interview','Offer','Hired')", [$uid])['n'] ?? 0);

// Monthly (12 months)
$months = [];
for ($i = 11; $i >= 0; $i--) {
    $months[] = [
        'label' => date('M y', strtotime("-$i months")),
        'y'     => date('Y',   strtotime("-$i months")),
        'm'     => date('m',   strtotime("-$i months")),
    ];
}
$monthlyApps = $monthlyIvs = [];
foreach ($months as $mo) {
    $r  = DB::one("SELECT COUNT(*) AS n FROM applications WHERE user_id=? AND YEAR(applied_date)=? AND MONTH(applied_date)=?", [$uid, $mo['y'], $mo['m']]);
    $r2 = DB::one("SELECT COUNT(*) AS n FROM applications WHERE user_id=? AND status IN ('Interview','Final Interview','Offer','Hired') AND YEAR(applied_date)=? AND MONTH(applied_date)=?", [$uid, $mo['y'], $mo['m']]);
    $monthlyApps[] = (int)($r['n']  ?? 0);
    $monthlyIvs[]  = (int)($r2['n'] ?? 0);
}

// Status distribution
$statusRows  = DB::all("SELECT status, COUNT(*) AS n FROM applications WHERE user_id=? GROUP BY status ORDER BY n DESC", [$uid]);
// Top companies
$topCompanies = DB::all("SELECT company, COUNT(*) AS n FROM applications WHERE user_id=? GROUP BY company ORDER BY n DESC LIMIT 8", [$uid]);
// Job types
$jobTypes    = DB::all("SELECT job_type, COUNT(*) AS n FROM applications WHERE user_id=? GROUP BY job_type ORDER BY n DESC", [$uid]);
// Salary data for avg
$salaryRow   = DB::one("SELECT AVG((salary_min+salary_max)/2) AS avg_sal, MAX(salary_max) AS max_sal FROM applications WHERE user_id=? AND salary_min IS NOT NULL", [$uid]);
$avgSal      = $salaryRow['avg_sal'] ? number_format((float)$salaryRow['avg_sal']) : '—';
$maxSal      = $salaryRow['max_sal'] ? number_format((float)$salaryRow['max_sal']) : '—';

$ivRate   = $total > 0 ? round($interviews/$total*100) : 0;
$offerRate = $total > 0 ? round($offers/$total*100) : 0;
$hireRate  = $total > 0 ? round($hired/$total*100) : 0;
?>

<div style="max-width:1200px">
  <div class="flex items-center justify-between mb-7 page-header">
    <div>
      <h1 style="font-size:22px;font-weight:700;color:#fff">Analytics</h1>
      <p style="color:var(--muted);font-size:13px;margin-top:2px">Insights across <?= $total ?> application<?= $total!==1?'s':'' ?></p>
    </div>
  </div>

  <!-- Key metrics -->
  <div class="rg-4" style="margin-bottom:24px">
    <?php foreach ([
      ['Total Applications', $total,      '#6C63FF', '100%'],
      ['Interview Rate',     $ivRate.'%', '#4ECDC4', "$interviews reached interview"],
      ['Offer Rate',         $offerRate.'%','#22c55e',"$offers offers received"],
      ['Hire Rate',          $hireRate.'%','#fbbf24', "$hired hired"],
    ] as [$label,$main,$color,$sub]): ?>
    <div class="stat-card">
      <div style="font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:10px"><?= $label ?></div>
      <div style="font-size:30px;font-weight:700;font-family:'Syne',sans-serif;color:<?= $color ?>"><?= $main ?></div>
      <div style="font-size:11px;color:var(--muted);margin-top:4px"><?= $sub ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Salary row -->
  <div class="rg-2" style="margin-bottom:24px">
    <div class="stat-card" style="display:flex;align-items:center;gap:14px">
      <div style="width:42px;height:42px;border-radius:10px;background:rgba(34,197,94,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:18px">💰</div>
      <div>
        <div style="font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">Avg Salary Target</div>
        <div style="font-size:20px;font-weight:700;font-family:'Syne',sans-serif;color:#fff"><?= $curr ?> <?= $avgSal ?></div>
      </div>
    </div>
    <div class="stat-card" style="display:flex;align-items:center;gap:14px">
      <div style="width:42px;height:42px;border-radius:10px;background:rgba(251,191,36,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:18px">🏆</div>
      <div>
        <div style="font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">Highest Salary Target</div>
        <div style="font-size:20px;font-weight:700;font-family:'Syne',sans-serif;color:#fff"><?= $curr ?> <?= $maxSal ?></div>
      </div>
    </div>
  </div>

  <!-- Chart row 1 — FIXED heights -->
  <div class="rg-21" style="margin-bottom:16px">
    <div class="card" style="padding:22px">
      <h3 style="font-size:15px;font-weight:700;color:#fff;margin-bottom:18px">Applications &amp; Interviews Over Time</h3>
      <div style="position:relative;height:220px;width:100%">
        <canvas id="lineChart"></canvas>
      </div>
    </div>
    <div class="card" style="padding:22px">
      <h3 style="font-size:15px;font-weight:700;color:#fff;margin-bottom:18px">Status Distribution</h3>
      <div style="position:relative;height:220px;width:100%">
        <canvas id="donutChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Chart row 2 -->
  <div class="rg-2">
    <div class="card" style="padding:22px">
      <h3 style="font-size:15px;font-weight:700;color:#fff;margin-bottom:18px">Top Companies Applied To</h3>
      <div style="position:relative;height:240px;width:100%">
        <canvas id="barChart"></canvas>
      </div>
    </div>
    <div class="card" style="padding:22px">
      <h3 style="font-size:15px;font-weight:700;color:#fff;margin-bottom:18px">Job Type Breakdown</h3>
      <div style="position:relative;height:240px;width:100%">
        <canvas id="typeChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Funnel section -->
  <div class="card" style="padding:22px;margin-top:16px">
    <h3 style="font-size:15px;font-weight:700;color:#fff;margin-bottom:20px">Application Funnel</h3>
    <?php
    $funnel = [
      ['Applied',    $total,      '#6C63FF'],
      ['Screening',  (int)(DB::one("SELECT COUNT(*) AS n FROM applications WHERE user_id=? AND status IN ('Screening','Assessment','Interview','Final Interview','Offer','Hired')", [$uid])['n']??0), '#fbbf24'],
      ['Interview',  $interviews, '#4ECDC4'],
      ['Offer',      $offers,     '#22c55e'],
      ['Hired',      $hired,      '#fbbf24'],
    ];
    foreach ($funnel as [$label,$count,$color]):
      $pct = $total > 0 ? round($count/$total*100) : 0;
    ?>
    <div style="margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;margin-bottom:5px">
        <span style="font-size:13px;color:var(--text);font-weight:500"><?= $label ?></span>
        <span style="font-size:13px;color:#fff;font-weight:700"><?= $count ?> <span style="color:var(--muted);font-weight:400;font-size:12px">(<?= $pct ?>%)</span></span>
      </div>
      <div style="height:8px;background:rgba(255,255,255,.06);border-radius:8px;overflow:hidden">
        <div style="height:100%;width:<?= $pct ?>%;background:<?= $color ?>;border-radius:8px;transition:width .8s ease"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

</div>

<script>
// All chart IIFEs to prevent variable collisions
(function(){
  const ctx = document.getElementById('lineChart');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= json_encode(array_column($months,'label')) ?>,
      datasets: [
        {
          label: 'Applications',
          data: <?= json_encode($monthlyApps) ?>,
          borderColor: '#6C63FF',
          backgroundColor: 'rgba(108,99,255,.12)',
          fill: true, tension: .4,
          pointBackgroundColor: '#6C63FF', pointRadius: 4, pointHoverRadius: 6,
        },
        {
          label: 'Interviews',
          data: <?= json_encode($monthlyIvs) ?>,
          borderColor: '#4ECDC4',
          backgroundColor: 'rgba(78,205,196,.08)',
          fill: true, tension: .4,
          pointBackgroundColor: '#4ECDC4', pointRadius: 4, pointHoverRadius: 6,
        }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 700 },
      plugins: { legend: { labels: { color:'rgba(255,255,255,.55)', font:{ size:11 }, usePointStyle:true, padding:14 } } },
      scales: {
        x: { grid:{ color:'rgba(255,255,255,.04)' }, ticks:{ color:'rgba(255,255,255,.45)', font:{size:10} } },
        y: { grid:{ color:'rgba(255,255,255,.04)' }, ticks:{ color:'rgba(255,255,255,.45)', font:{size:10}, stepSize:1, precision:0 }, beginAtZero:true, min:0 }
      }
    }
  });
})();

(function(){
  const ctx   = document.getElementById('donutChart');
  const sData = <?= json_encode(array_values($statusRows)) ?>;
  const clrs  = { Wishlist:'#94a3b8', Applied:'#60a5fa', Screening:'#fbbf24', Assessment:'#a78bfa', Interview:'#34d399', 'Final Interview':'#fb7185', Offer:'#22c55e', Rejected:'#f87171', Hired:'#4ECDC4' };
  if (!sData.length) return;
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: sData.map(d=>d.status),
      datasets:[{ data: sData.map(d=>parseInt(d.n)), backgroundColor: sData.map(d=>clrs[d.status]||'#6C63FF'), borderWidth:0, hoverOffset:6 }]
    },
    options: {
      responsive:true, maintainAspectRatio:false, cutout:'60%',
      animation:{ duration:700 },
      plugins:{ legend:{ position:'bottom', labels:{ color:'rgba(255,255,255,.5)', font:{size:10}, padding:6, boxWidth:8, usePointStyle:true } } }
    }
  });
})();

(function(){
  const ctx   = document.getElementById('barChart');
  const cData = <?= json_encode(array_values($topCompanies)) ?>;
  if (!cData.length) return;
  new Chart(ctx, {
    type: 'bar',
    data:{
      labels: cData.map(d=>d.company),
      datasets:[{ label:'Applications', data:cData.map(d=>parseInt(d.n)), backgroundColor:'rgba(108,99,255,.35)', borderColor:'#6C63FF', borderWidth:1.5, borderRadius:5 }]
    },
    options:{
      indexAxis:'y', responsive:true, maintainAspectRatio:false,
      animation:{ duration:700 },
      plugins:{ legend:{ display:false } },
      scales:{
        x:{ grid:{ color:'rgba(255,255,255,.04)' }, ticks:{ color:'rgba(255,255,255,.45)', font:{size:10}, stepSize:1, precision:0 }, beginAtZero:true },
        y:{ grid:{ display:false }, ticks:{ color:'rgba(255,255,255,.55)', font:{size:11} } }
      }
    }
  });
})();

(function(){
  const ctx   = document.getElementById('typeChart');
  const tData = <?= json_encode(array_values($jobTypes)) ?>;
  if (!tData.length) return;
  const tClrs = ['#6C63FF','#4ECDC4','#fbbf24','#f87171','#a78bfa','#22c55e'];
  new Chart(ctx, {
    type: 'pie',
    data:{
      labels: tData.map(d=>d.job_type),
      datasets:[{ data:tData.map(d=>parseInt(d.n)), backgroundColor:tClrs, borderWidth:0, hoverOffset:6 }]
    },
    options:{
      responsive:true, maintainAspectRatio:false,
      animation:{ duration:700 },
      plugins:{ legend:{ position:'bottom', labels:{ color:'rgba(255,255,255,.55)', font:{size:11}, padding:8, boxWidth:10, usePointStyle:true } } }
    }
  });
})();
</script>

</main>
</body>
</html>
