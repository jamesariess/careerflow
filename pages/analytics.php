<?php
require_once '../components/layout.php';
cf_layout_head('Analytics');
cf_layout_sidebar('analytics');

$uid = (int)$user['id'];

// ── Overview stats ─────────────────────────────
$total = DB::one('SELECT COUNT(*) AS n FROM applications WHERE user_id=?', [$uid])['n'];
$hired = DB::one("SELECT COUNT(*) AS n FROM applications WHERE user_id=? AND status='Hired'", [$uid])['n'];
$offers = DB::one("SELECT COUNT(*) AS n FROM applications WHERE user_id=? AND status IN ('Offer','Hired')", [$uid])['n'];
$interviews = DB::one("SELECT COUNT(*) AS n FROM applications WHERE user_id=? AND status IN ('Interview','Final Interview','Offer','Hired')", [$uid])['n'];

// ── Monthly (12 months) ────────────────────────
$months = [];
for ($i = 11; $i >= 0; $i--) {
    $months[] = [
        'label' => date('M y', strtotime("-$i months")),
        'y'     => date('Y', strtotime("-$i months")),
        'm'     => date('m', strtotime("-$i months")),
    ];
}
$monthlyApps = $monthlyHired = [];
foreach ($months as $mo) {
    $r = DB::one("SELECT COUNT(*) AS n FROM applications WHERE user_id=? AND YEAR(applied_date)=? AND MONTH(applied_date)=?", [$uid, $mo['y'], $mo['m']]);
    $monthlyApps[] = (int)($r['n'] ?? 0);
    $r2 = DB::one("SELECT COUNT(*) AS n FROM applications WHERE user_id=? AND status='Hired' AND YEAR(applied_date)=? AND MONTH(applied_date)=?", [$uid, $mo['y'], $mo['m']]);
    $monthlyHired[] = (int)($r2['n'] ?? 0);
}

// ── Status distribution ────────────────────────
$statusRows = DB::all("SELECT status, COUNT(*) AS n FROM applications WHERE user_id=? GROUP BY status ORDER BY n DESC", [$uid]);

// ── Top companies applied to ───────────────────
$topCompanies = DB::all("SELECT company, COUNT(*) AS n FROM applications WHERE user_id=? GROUP BY company ORDER BY n DESC LIMIT 8", [$uid]);

// ── Job types ─────────────────────────────────
$jobTypes = DB::all("SELECT job_type, COUNT(*) AS n FROM applications WHERE user_id=? GROUP BY job_type", [$uid]);

// ── Interview rate per month ───────────────────
$monthlyInterviews = [];
foreach ($months as $mo) {
    $r = DB::one("SELECT COUNT(*) AS n FROM applications WHERE user_id=? AND status IN ('Interview','Final Interview','Offer','Hired') AND YEAR(applied_date)=? AND MONTH(applied_date)=?", [$uid, $mo['y'], $mo['m']]);
    $monthlyInterviews[] = (int)($r['n'] ?? 0);
}
?>

<div style="max-width:1200px">
  <div class="flex items-center justify-between mb-7">
    <div>
      <h1 style="font-size:22px;font-weight:700;color:#fff">Analytics</h1>
      <p style="color:var(--muted);font-size:13px;margin-top:2px">Insights from your job search journey</p>
    </div>
  </div>

  <!-- Key metrics -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px">
    <?php
    $mets = [
      ['Total Applications', $total, '#6C63FF', round($total > 0 ? 100 : 0).'%'],
      ['Interview Rate', $total > 0 ? round($interviews/$total*100).'%' : '0%', '#4ECDC4', "$interviews / $total"],
      ['Offer Rate', $total > 0 ? round($offers/$total*100).'%' : '0%', '#22c55e', "$offers offers"],
      ['Hire Rate', $total > 0 ? round($hired/$total*100).'%' : '0%', '#fbbf24', "$hired hired"],
    ];
    foreach ($mets as [$label, $main, $color, $sub]): ?>
    <div class="stat-card">
      <div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:10px"><?= $label ?></div>
      <div style="font-size:30px;font-weight:700;font-family:'Syne',sans-serif;color:<?= $color ?>"><?= $main ?></div>
      <div style="font-size:12px;color:var(--muted);margin-top:4px"><?= $sub ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Charts row 1 -->
  <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px">
    <div class="card" style="padding:22px">
      <h3 style="font-size:15px;font-weight:700;color:#fff;margin-bottom:18px">Applications & Interviews Over Time</h3>
      <canvas id="lineChart" height="200"></canvas>
    </div>
    <div class="card" style="padding:22px">
      <h3 style="font-size:15px;font-weight:700;color:#fff;margin-bottom:18px">Application Status</h3>
      <canvas id="donutChart" height="200"></canvas>
    </div>
  </div>

  <!-- Charts row 2 -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <div class="card" style="padding:22px">
      <h3 style="font-size:15px;font-weight:700;color:#fff;margin-bottom:18px">Top Companies Applied</h3>
      <canvas id="barChart" height="220"></canvas>
    </div>
    <div class="card" style="padding:22px">
      <h3 style="font-size:15px;font-weight:700;color:#fff;margin-bottom:18px">Job Type Breakdown</h3>
      <canvas id="typeChart" height="220"></canvas>
    </div>
  </div>
</div>

<script>
const months = <?= json_encode(array_column($months, 'label')) ?>;
const appsData = <?= json_encode($monthlyApps) ?>;
const ivsData  = <?= json_encode($monthlyInterviews) ?>;

// Line chart
new Chart(document.getElementById('lineChart'), {
  type: 'line',
  data: {
    labels: months,
    datasets: [
      { label:'Applications', data: appsData, borderColor:'#6C63FF', backgroundColor:'rgba(108,99,255,.1)', fill:true, tension:.4, pointBackgroundColor:'#6C63FF', pointRadius:4 },
      { label:'Interviews',   data: ivsData,  borderColor:'#4ECDC4', backgroundColor:'rgba(78,205,196,.08)', fill:true, tension:.4, pointBackgroundColor:'#4ECDC4', pointRadius:4 },
    ]
  },
  options: {
    responsive:true, maintainAspectRatio:false,
    plugins: { legend:{ labels:{ color:'rgba(255,255,255,.55)', font:{size:11} } } },
    scales: {
      x:{ grid:{color:'rgba(255,255,255,.04)'}, ticks:{color:'rgba(255,255,255,.45)',font:{size:10}} },
      y:{ grid:{color:'rgba(255,255,255,.04)'}, ticks:{color:'rgba(255,255,255,.45)',font:{size:10},stepSize:1}, beginAtZero:true }
    }
  }
});

// Donut
const sData = <?= json_encode($statusRows) ?>;
const sColors = { Wishlist:'#94a3b8', Applied:'#60a5fa', Screening:'#fbbf24', Assessment:'#a78bfa', Interview:'#34d399', 'Final Interview':'#fb7185', Offer:'#22c55e', Rejected:'#f87171', Hired:'#4ECDC4' };
new Chart(document.getElementById('donutChart'), {
  type:'doughnut',
  data: {
    labels: sData.map(d=>d.status),
    datasets:[{ data:sData.map(d=>d.n), backgroundColor:sData.map(d=>sColors[d.status]||'#6C63FF'), borderWidth:0 }]
  },
  options: {
    responsive:true, maintainAspectRatio:false, cutout:'60%',
    plugins:{ legend:{ position:'bottom', labels:{ color:'rgba(255,255,255,.5)', font:{size:10}, padding:8, boxWidth:8 } } }
  }
});

// Bar – top companies
const cData = <?= json_encode($topCompanies) ?>;
new Chart(document.getElementById('barChart'), {
  type:'bar',
  data:{
    labels: cData.map(d=>d.company),
    datasets:[{ label:'Applications', data:cData.map(d=>d.n), backgroundColor:'rgba(108,99,255,.35)', borderColor:'#6C63FF', borderWidth:1.5, borderRadius:5 }]
  },
  options:{
    indexAxis:'y', responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{display:false} },
    scales:{
      x:{ grid:{color:'rgba(255,255,255,.04)'}, ticks:{color:'rgba(255,255,255,.45)',font:{size:10},stepSize:1} },
      y:{ grid:{display:false}, ticks:{color:'rgba(255,255,255,.55)',font:{size:11}} }
    }
  }
});

// Type pie
const tData = <?= json_encode($jobTypes) ?>;
const tColors = ['#6C63FF','#4ECDC4','#fbbf24','#f87171','#a78bfa','#22c55e'];
new Chart(document.getElementById('typeChart'), {
  type:'pie',
  data:{
    labels: tData.map(d=>d.job_type),
    datasets:[{ data:tData.map(d=>d.n), backgroundColor:tColors, borderWidth:0 }]
  },
  options:{
    responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{ position:'bottom', labels:{ color:'rgba(255,255,255,.55)', font:{size:11}, padding:10, boxWidth:10 } } }
  }
});
</script>

</main>
</body>
</html>
