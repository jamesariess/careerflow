<?php
require_once '../components/layout.php';
cf_layout_head('Calendar');
cf_layout_sidebar('calendar');

$uid = (int)$user['id'];
$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) die('CSRF');
    $action = $_POST['_action'] ?? '';
    if ($action === 'save_interview') {
        $appId  = (int)($_POST['application_id'] ?? 0);
        $title  = trim($_POST['title'] ?? '');
        $type   = $_POST['interview_type'] ?? 'Video';
        $sched  = $_POST['scheduled_at'] ?? '';
        $dur    = (int)($_POST['duration_min'] ?? 60);
        $loc    = trim($_POST['location_or_link'] ?? '');
        $iwr    = trim($_POST['interviewer'] ?? '');
        $notes  = trim($_POST['notes'] ?? '');
        if ($appId && $title && $sched) {
            DB::run('INSERT INTO interviews (application_id,user_id,title,interview_type,scheduled_at,duration_min,location_or_link,interviewer,notes) VALUES (?,?,?,?,?,?,?,?,?)',
                [$appId,$uid,$title,$type,$sched,$dur,$loc,$iwr,$notes]);
            // Update app status to Interview
            DB::run("UPDATE applications SET status='Interview' WHERE id=? AND user_id=?", [$appId,$uid]);
            log_activity($uid,'schedule_interview',"Scheduled: $title",$appId);
            $msg = 'Interview scheduled!'; $msgType = 'success';
        }
    }
    if ($action === 'delete_interview') {
        $id = (int)($_POST['id'] ?? 0);
        DB::run('DELETE FROM interviews WHERE id=? AND user_id=?', [$id,$uid]);
        $msg = 'Interview deleted.'; $msgType = 'success';
    }
}

// Get all interviews for this user (next 60 days + past 30)
$interviews = DB::all("
  SELECT i.*, a.company, a.job_title
  FROM interviews i JOIN applications a ON i.application_id=a.id
  WHERE i.user_id=? AND i.scheduled_at BETWEEN DATE_SUB(NOW(),INTERVAL 30 DAY) AND DATE_ADD(NOW(),INTERVAL 60 DAY)
  ORDER BY i.scheduled_at ASC", [$uid]);

// Calendar month
$year  = (int)($_GET['y'] ?? date('Y'));
$month = (int)($_GET['m'] ?? date('m'));
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$firstDay  = mktime(0,0,0,$month,1,$year);
$daysInMonth = (int)date('t', $firstDay);
$startDow  = (int)date('N', $firstDay); // 1=Mon

// Index interviews by date
$byDate = [];
foreach ($interviews as $iv) {
    $d = date('Y-m-d', strtotime($iv['scheduled_at']));
    $byDate[$d][] = $iv;
}

$types = ['Phone','Video','On-site','Technical','HR','Panel','Final'];
$apps  = DB::all("SELECT id,company,job_title FROM applications WHERE user_id=? ORDER BY company", [$uid]);
?>
<?php if ($msg): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast('<?= addslashes($msg) ?>','<?= $msgType ?>'));</script>
<?php endif; ?>

<div style="max-width:1100px">
  <div class="flex items-center justify-between mb-6 page-header">
    <div>
      <h1 style="font-size:22px;font-weight:700;color:#fff">Interview Calendar</h1>
      <p style="color:var(--muted);font-size:13px;margin-top:2px">Schedule and track your interviews</p>
    </div>
    <button onclick="document.getElementById('ivModal').classList.add('open')" class="btn btn-primary btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg>
      Schedule Interview
    </button>
  </div>

  <div class="rg-31">

    <!-- Calendar grid -->
    <div class="card" style="padding:20px">
      <!-- Nav -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
        <h3 style="font-size:18px;font-weight:700;color:#fff;font-family:'Syne',sans-serif"><?= date('F Y', $firstDay) ?></h3>
        <div style="display:flex;gap:6px">
          <a href="?y=<?= $month===1?$year-1:$year ?>&m=<?= $month===1?12:$month-1 ?>" class="btn btn-secondary btn-sm">←</a>
          <a href="?y=<?= date('Y') ?>&m=<?= date('m') ?>" class="btn btn-secondary btn-sm">Today</a>
          <a href="?y=<?= $month===12?$year+1:$year ?>&m=<?= $month===12?1:$month+1 ?>" class="btn btn-secondary btn-sm">→</a>
        </div>
      </div>
      <!-- Day headers -->
      <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:6px">
        <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
        <div style="text-align:center;font-size:11px;font-weight:600;color:var(--muted);padding:4px"><?= $d ?></div>
        <?php endforeach; ?>
      </div>
      <!-- Days -->
      <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px">
        <?php
        // Empty slots before first day
        for ($i = 1; $i < $startDow; $i++) echo '<div></div>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $isToday = $dateStr === date('Y-m-d');
            $hasEvents = !empty($byDate[$dateStr]);
            $weekend = date('N', mktime(0,0,0,$month,$d,$year)) >= 6;
        ?>
        <div style="border-radius:8px;padding:6px 4px;min-height:60px;border:1px solid <?= $isToday ? 'var(--accent)' : 'rgba(255,255,255,.05)' ?>;background:<?= $isToday ? 'rgba(108,99,255,.12)' : ($weekend ? 'rgba(255,255,255,.01)' : 'transparent') ?>;cursor:<?= $hasEvents ? 'pointer' : 'default' ?>;" onclick="<?= $hasEvents ? "showDayEvents('$dateStr')" : '' ?>">
          <div style="font-size:12px;font-weight:<?= $isToday ? '700' : '500' ?>;color:<?= $isToday ? 'var(--accent)' : ($weekend ? 'var(--muted)' : '#fff') ?>;text-align:center"><?= $d ?></div>
          <?php foreach (($byDate[$dateStr] ?? []) as $ev): ?>
          <div style="font-size:9px;background:rgba(108,99,255,.3);color:#c4b5fd;border-radius:3px;padding:2px 4px;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= htmlspecialchars($ev['title']) ?>">
            <?= date('g:i', strtotime($ev['scheduled_at'])) ?> <?= htmlspecialchars(mb_substr($ev['company'],0,10)) ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php } ?>
      </div>
    </div>

    <!-- Upcoming list -->
    <div class="card" style="overflow:hidden">
      <div style="padding:16px 18px;border-bottom:1px solid var(--border)">
        <h3 style="font-size:14px;font-weight:700;color:#fff">Upcoming Interviews</h3>
      </div>
      <div style="overflow-y:auto;max-height:520px">
        <?php
        $upcoming = array_filter($interviews, fn($i) => strtotime($i['scheduled_at']) >= time());
        if (empty($upcoming)):
        ?>
        <p style="padding:24px;color:var(--muted);font-size:13px;text-align:center">No upcoming interviews</p>
        <?php else: foreach ($upcoming as $iv): ?>
        <div style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,.04)">
          <div style="display:flex;align-items:flex-start;gap:10px">
            <div style="width:38px;height:38px;border-radius:9px;background:rgba(108,99,255,.15);display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0">
              <span style="font-size:9px;color:var(--accent);font-weight:700;line-height:1;text-transform:uppercase"><?= date('M',strtotime($iv['scheduled_at'])) ?></span>
              <span style="font-size:15px;color:#fff;font-weight:700;font-family:'Syne',sans-serif;line-height:1.1"><?= date('d',strtotime($iv['scheduled_at'])) ?></span>
            </div>
            <div style="flex:1;min-width:0">
              <div style="font-size:13px;font-weight:600;color:#fff"><?= htmlspecialchars($iv['company']) ?></div>
              <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($iv['title']) ?></div>
              <div style="font-size:11px;color:var(--accent2);margin-top:3px"><?= date('g:i A', strtotime($iv['scheduled_at'])) ?> · <?= htmlspecialchars($iv['interview_type']) ?></div>
              <?php if ($iv['location_or_link']): ?>
              <a href="<?= htmlspecialchars($iv['location_or_link']) ?>" target="_blank" style="font-size:10px;color:var(--accent);text-decoration:none;display:block;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                🔗 <?= htmlspecialchars($iv['location_or_link']) ?>
              </a>
              <?php endif; ?>
            </div>
            <form method="POST">
              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="delete_interview">
              <input type="hidden" name="id" value="<?= $iv['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" style="padding:4px 8px" onclick="return confirm('Remove this interview?')">×</button>
            </form>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Schedule Modal -->
<div class="modal-overlay" id="ivModal">
  <div class="modal-box" style="max-width:520px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
      <h2 style="font-size:17px;font-weight:700;color:#fff">Schedule Interview</h2>
      <button onclick="document.getElementById('ivModal').classList.remove('open')" style="background:rgba(255,255,255,.07);border:none;color:var(--muted);width:28px;height:28px;border-radius:7px;cursor:pointer">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="save_interview">
      <div style="display:grid;gap:12px">
        <div>
          <label class="cf-label">Application</label>
          <select name="application_id" class="cf-input" required>
            <option value="">Select application…</option>
            <?php foreach ($apps as $a): ?>
            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['company']) ?> – <?= htmlspecialchars($a['job_title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="cf-label">Interview Title</label>
          <input type="text" name="title" class="cf-input" required placeholder="e.g. Technical Round 1">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div>
            <label class="cf-label">Type</label>
            <select name="interview_type" class="cf-input">
              <?php foreach ($types as $t): ?><option><?= $t ?></option><?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="cf-label">Duration (min)</label>
            <input type="number" name="duration_min" class="cf-input" value="60" min="15" step="15">
          </div>
        </div>
        <div>
          <label class="cf-label">Date & Time</label>
          <input type="datetime-local" name="scheduled_at" class="cf-input" required>
        </div>
        <div>
          <label class="cf-label">Link / Location</label>
          <input type="text" name="location_or_link" class="cf-input" placeholder="https://meet.google.com/…">
        </div>
        <div>
          <label class="cf-label">Interviewer Name</label>
          <input type="text" name="interviewer" class="cf-input" placeholder="John Smith">
        </div>
        <div>
          <label class="cf-label">Notes</label>
          <textarea name="notes" class="cf-input" rows="2" placeholder="Prep notes, topics to cover…"></textarea>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" onclick="document.getElementById('ivModal').classList.remove('open')" class="btn btn-secondary">Cancel</button>
        <button type="submit" class="btn btn-primary">Schedule</button>
      </div>
    </form>
  </div>
</div>

<script>
function showDayEvents(date) {
  // Simple – could expand to show a detail popup
}
</script>

</main>
</body>
</html>
