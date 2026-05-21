<?php
require_once '../components/layout.php';
cf_layout_head('Dashboard');
cf_layout_sidebar('dashboard');

$uid = (int)$user['id'];

// Currency helper
$dbUser  = DB::one('SELECT * FROM users WHERE id=?', [$uid]);
$curr    = $dbUser['currency'] ?? 'USD';

// Stats
$stats = DB::one("
  SELECT
    COUNT(*) AS total,
    SUM(status IN ('Interview','Final Interview')) AS interviews,
    SUM(status = 'Offer') AS offers,
    SUM(status = 'Hired') AS hired,
    SUM(status = 'Rejected') AS rejected
  FROM applications WHERE user_id=?", [$uid]);

$rate = ($stats['total'] > 0 && $stats['hired'] > 0)
      ? round(($stats['hired'] / $stats['total']) * 100) : 0;

// Monthly data (last 6 months)
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[] = [
        'label' => date('M', strtotime("-$i months")),
        'year'  => date('Y', strtotime("-$i months")),
        'month' => date('m', strtotime("-$i months")),
    ];
}
$monthlyData = [];
foreach ($months as $m) {
    $row = DB::one("SELECT COUNT(*) AS n FROM applications WHERE user_id=? AND YEAR(applied_date)=? AND MONTH(applied_date)=?",
        [$uid, $m['year'], $m['month']]);
    $monthlyData[] = (int)($row['n'] ?? 0);
}

// Status breakdown
$statusData = DB::all("SELECT status, COUNT(*) AS n FROM applications WHERE user_id=? GROUP BY status", [$uid]);

// Recent activity
$recent = DB::all("SELECT l.*, a.company, a.job_title FROM activity_logs l
    LEFT JOIN applications a ON l.application_id=a.id
    WHERE l.user_id=? ORDER BY l.created_at DESC LIMIT 8", [$uid]);

// Upcoming interviews
$upcoming = DB::all("SELECT i.*, a.company, a.job_title FROM interviews i
    JOIN applications a ON i.application_id=a.id
    WHERE i.user_id=? AND i.scheduled_at >= NOW()
    ORDER BY i.scheduled_at ASC LIMIT 4", [$uid]);
?>

<div style="max-width:1200px">

  <!-- Page header -->
  <div class="flex items-center justify-between mb-7 page-header">
    <div>
      <h1 style="font-size:24px;font-weight:700;color:#fff">
        Good <?= date('G') < 12 ? 'morning' : (date('G') < 17 ? 'afternoon' : 'evening') ?>,
        <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?> 👋
      </h1>
      <p style="color:var(--muted);font-size:14px;margin-top:3px"><?= date('l, F j, Y') ?></p>
    </div>
    <a href="<?= APP_URL ?>/pages/applications.php?new=1" class="btn btn-primary">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
        <path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
      New Application
    </a>
  </div>

  <!-- Stat cards -->
  <div class="rg-auto" style="margin-bottom:24px">
    <?php
    $cards = [
      ['Total Applied',  $stats['total'],       '#6C63FF', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
      ['Interviews',     $stats['interviews'],  '#4ECDC4', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
      ['Offers',         $stats['offers'],      '#22c55e', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
      ['Hired',          $stats['hired'],       '#fbbf24', 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z'],
      ['Rejected',       $stats['rejected'],    '#f87171', 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
      ['Success Rate',   $rate . '%',           '#a78bfa', 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
    ];
    foreach ($cards as [$label, $val, $color, $path]):
    ?>
    <div class="stat-card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <span style="font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.6px"><?= $label ?></span>
        <div style="width:30px;height:30px;border-radius:8px;background:<?= $color ?>22;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="color:<?= $color ?>">
            <path d="<?= $path ?>" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
      </div>
      <div style="font-size:28px;font-weight:700;font-family:'Syne',sans-serif;color:#fff"><?= $val ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Charts row — FIXED HEIGHT WRAPPERS prevent infinite expand -->
  <div class="rg-21" style="margin-bottom:24px">

    <!-- Monthly bar chart -->
    <div class="card" style="padding:22px">
      <div class="flex items-center justify-between mb-4">
        <h3 style="font-size:15px;font-weight:700;color:#fff">Applications per Month</h3>
        <span style="font-size:12px;color:var(--muted)">Last 6 months</span>
      </div>
      <!-- ✅ FIX: explicit pixel height on wrapper, canvas fills it -->
      <div style="position:relative;height:200px;width:100%">
        <canvas id="monthlyChart"></canvas>
      </div>
    </div>

    <!-- Status donut -->
    <div class="card" style="padding:22px">
      <h3 style="font-size:15px;font-weight:700;color:#fff;margin-bottom:16px">Status Breakdown</h3>
      <div style="position:relative;height:200px;width:100%">
        <canvas id="statusChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Bottom row -->
  <div class="rg-2">

    <!-- Upcoming interviews -->
    <div class="card">
      <div style="padding:18px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
        <h3 style="font-size:15px;font-weight:700;color:#fff">Upcoming Interviews</h3>
        <a href="<?= APP_URL ?>/pages/calendar.php" style="font-size:12px;color:var(--accent);text-decoration:none">View all →</a>
      </div>
      <div>
        <?php if (empty($upcoming)): ?>
        <p style="padding:24px;color:var(--muted);font-size:13px;text-align:center">No upcoming interviews</p>
        <?php else: foreach ($upcoming as $iv): ?>
        <div style="padding:12px 20px;border-bottom:1px solid rgba(255,255,255,.04);display:flex;align-items:center;gap:12px">
          <div style="width:40px;height:40px;border-radius:10px;background:rgba(108,99,255,.15);display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0">
            <span style="font-size:9px;color:var(--accent);font-weight:700;line-height:1;text-transform:uppercase"><?= date('M', strtotime($iv['scheduled_at'])) ?></span>
            <span style="font-size:15px;color:#fff;font-weight:700;font-family:'Syne',sans-serif;line-height:1.2"><?= date('d', strtotime($iv['scheduled_at'])) ?></span>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($iv['company']) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($iv['title']) ?> · <?= date('g:i A', strtotime($iv['scheduled_at'])) ?></div>
          </div>
          <span style="font-size:10px;padding:2px 8px;border-radius:20px;background:rgba(78,205,196,.12);color:var(--accent2);font-weight:600"><?= htmlspecialchars($iv['interview_type']) ?></span>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Recent activity -->
    <div class="card">
      <div style="padding:18px 20px;border-bottom:1px solid var(--border)">
        <h3 style="font-size:15px;font-weight:700;color:#fff">Recent Activity</h3>
      </div>
      <div>
        <?php if (empty($recent)): ?>
        <p style="padding:24px;color:var(--muted);font-size:13px;text-align:center">No activity yet — add your first application!</p>
        <?php else: foreach ($recent as $a): ?>
        <div style="padding:10px 20px;border-bottom:1px solid rgba(255,255,255,.04);display:flex;align-items:flex-start;gap:10px">
          <div style="width:6px;height:6px;border-radius:50%;background:var(--accent);margin-top:6px;flex-shrink:0"></div>
          <div style="flex:1;min-width:0">
            <div style="font-size:13px;color:var(--text)"><?= htmlspecialchars($a['description'] ?: $a['action']) ?></div>
            <?php if (!empty($a['company'])): ?>
            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($a['company']) ?><?= !empty($a['job_title']) ? ' – ' . htmlspecialchars($a['job_title']) : '' ?></div>
            <?php endif; ?>
          </div>
          <span style="font-size:11px;color:var(--muted);white-space:nowrap;flex-shrink:0"><?= date('M j', strtotime($a['created_at'])) ?></span>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

</div>

<script>
// ── Monthly bar chart (FIXED: no maintainAspectRatio issues) ──
(function() {
  const ctx = document.getElementById('monthlyChart');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column($months, 'label')) ?>,
      datasets: [{
        label: 'Applications',
        data: <?= json_encode($monthlyData) ?>,
        backgroundColor: 'rgba(108,99,255,0.3)',
        borderColor: '#6C63FF',
        borderWidth: 2,
        borderRadius: 6,
        borderSkipped: false,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 600 },
      plugins: { legend: { display: false } },
      scales: {
        x: {
          grid: { color: 'rgba(255,255,255,.04)' },
          ticks: { color: 'rgba(255,255,255,.45)', font: { size: 11 } }
        },
        y: {
          grid: { color: 'rgba(255,255,255,.04)' },
          ticks: { color: 'rgba(255,255,255,.45)', font: { size: 11 }, stepSize: 1, precision: 0 },
          beginAtZero: true,
          min: 0
        }
      }
    }
  });
})();

// ── Status donut ──
(function() {
  const ctx   = document.getElementById('statusChart');
  const sData = <?= json_encode(array_values($statusData)) ?>;
  const clrs  = {
    Wishlist:'#94a3b8', Applied:'#60a5fa', Screening:'#fbbf24',
    Assessment:'#a78bfa', Interview:'#34d399', 'Final Interview':'#fb7185',
    Offer:'#22c55e', Rejected:'#f87171', Hired:'#4ECDC4'
  };

  if (sData.length === 0) {
    // Show placeholder text inside canvas
    const c2 = ctx.getContext('2d');
    ctx.height = 200;
    c2.fillStyle = 'rgba(255,255,255,.2)';
    c2.font = '13px DM Sans';
    c2.textAlign = 'center';
    c2.fillText('No data yet', ctx.width/2, 100);
    return;
  }

  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: sData.map(d => d.status),
      datasets: [{
        data: sData.map(d => parseInt(d.n)),
        backgroundColor: sData.map(d => clrs[d.status] || '#6C63FF'),
        borderWidth: 0,
        hoverOffset: 6,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 600 },
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            color: 'rgba(255,255,255,.55)',
            font: { size: 10 },
            padding: 8,
            boxWidth: 8,
            usePointStyle: true,
          }
        }
      },
      cutout: '62%'
    }
  });
})();
</script>

</main>
</body>
</html>
