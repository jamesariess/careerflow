<?php
require_once '../components/layout.php';
cf_layout_head('Kanban Board');
cf_layout_sidebar('kanban');

$uid = (int)$user['id'];

// Handle status update via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] === 'move') {
    if (!csrf_verify()) { echo json_encode(['ok'=>false]); exit; }
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $valid = ['Wishlist','Applied','Screening','Assessment','Interview','Final Interview','Offer','Rejected','Hired'];
    if (in_array($status, $valid)) {
        DB::run('UPDATE applications SET status=? WHERE id=? AND user_id=?', [$status, $id, $uid]);
        log_activity($uid, 'kanban_move', "Moved to $status", $id);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }
    exit;
}

$statuses = ['Wishlist','Applied','Screening','Assessment','Interview','Final Interview','Offer','Rejected','Hired'];
$apps = DB::all('SELECT * FROM applications WHERE user_id=? ORDER BY sort_order ASC, created_at DESC', [$uid]);

// Group by status
$grouped = [];
foreach ($statuses as $s) $grouped[$s] = [];
foreach ($apps as $a) {
    if (isset($grouped[$a['status']])) $grouped[$a['status']][] = $a;
}

$colors = [
  'Wishlist'=>'#94a3b8','Applied'=>'#60a5fa','Screening'=>'#fbbf24','Assessment'=>'#a78bfa',
  'Interview'=>'#34d399','Final Interview'=>'#fb7185','Offer'=>'#22c55e','Rejected'=>'#f87171','Hired'=>'#4ECDC4'
];
?>

<div>
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 style="font-size:22px;font-weight:700;color:#fff">Kanban Board</h1>
      <p style="color:var(--muted);font-size:13px;margin-top:2px">Drag cards to update application status</p>
    </div>
    <a href="applications.php?new=1" class="btn btn-primary btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg>
      Add Application
    </a>
  </div>

  <div style="display:flex;gap:12px;overflow-x:auto;padding-bottom:16px;align-items:flex-start">
    <?php foreach ($statuses as $status):
      $col  = $grouped[$status];
      $cnt  = count($col);
      $color = $colors[$status];
    ?>
    <div class="kanban-col" id="col-<?= str_replace(' ','-',$status) ?>" data-status="<?= $status ?>">
      <div class="kanban-col-header">
        <div style="display:flex;align-items:center;gap-7px">
          <span style="width:8px;height:8px;border-radius:50%;background:<?= $color ?>;display:inline-block;margin-right:7px"></span>
          <span style="font-size:12px;font-weight:700;color:#fff;font-family:'Syne',sans-serif"><?= $status ?></span>
        </div>
        <span style="font-size:11px;background:rgba(255,255,255,.07);color:var(--muted);padding:2px 7px;border-radius:20px;font-weight:600"><?= $cnt ?></span>
      </div>

      <div class="kanban-drop" id="drop-<?= str_replace(' ','-',$status) ?>">
        <?php foreach ($col as $a): ?>
        <div class="kanban-card" data-id="<?= $a['id'] ?>">
          <div style="font-size:13px;font-weight:600;color:#fff;margin-bottom:4px;line-height:1.3"><?= htmlspecialchars($a['job_title']) ?></div>
          <div style="font-size:11px;color:var(--muted);margin-bottom:8px"><?= htmlspecialchars($a['company']) ?></div>
          <?php if ($a['location']): ?>
          <div style="font-size:11px;color:var(--muted);display:flex;align-items:center;gap:4px;margin-bottom:6px">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="10" r="3" stroke="currentColor" stroke-width="2"/></svg>
            <?= htmlspecialchars($a['location']) ?>
          </div>
          <?php endif; ?>
          <?php if ($a['salary_min']): ?>
          <div style="font-size:11px;color:var(--accent2)">
            <?= cf_currency((float)$a['salary_min'], true) ?><?= $a['salary_max'] ? '–'.cf_currency((float)$a['salary_max'],true) : '+' ?>
          </div>
          <?php endif; ?>
          <div style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,.06);display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:10px;color:var(--muted)"><?= $a['applied_date'] ? date('M j', strtotime($a['applied_date'])) : 'No date' ?></span>
            <a href="application_detail.php?id=<?= $a['id'] ?>" style="font-size:10px;color:var(--accent);text-decoration:none" onclick="event.stopPropagation()">View →</a>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($col)): ?>
        <div style="text-align:center;padding:20px 10px;color:rgba(255,255,255,.15);font-size:12px">Drop here</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
const csrf = '<?= csrf_token() ?>';

document.querySelectorAll('.kanban-drop').forEach(zone => {
  new Sortable(zone, {
    group: 'kanban',
    animation: 180,
    ghostClass: 'sortable-ghost',
    onEnd: async function(evt) {
      const card   = evt.item;
      const newCol = evt.to.closest('.kanban-col');
      const status = newCol.dataset.status;
      const id     = card.dataset.id;

      // Update count badges
      document.querySelectorAll('.kanban-col').forEach(col => {
        const cnt = col.querySelectorAll('.kanban-card').length;
        col.querySelector('.kanban-col-header span:last-child').textContent = cnt;
        const empty = col.querySelector('[style*="Drop here"]');
        if (cnt > 0 && empty) empty.remove();
      });

      // API call
      const fd = new FormData();
      fd.append('_csrf', csrf);
      fd.append('_action', 'move');
      fd.append('id', id);
      fd.append('status', status);
      const r = await fetch('kanban.php', { method:'POST', body:fd });
      const j = await r.json();
      if (j.ok) showToast('Moved to ' + status, 'success');
    }
  });
});
</script>

</main>
</body>
</html>
