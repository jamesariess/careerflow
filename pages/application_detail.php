<?php
require_once '../components/layout.php';
$uid = (int)$user['id'];
$id  = (int)($_GET['id'] ?? 0);

$app = DB::one('SELECT * FROM applications WHERE id=? AND user_id=?', [$id, $uid]);
if (!$app) { header('Location: ' . APP_URL . '/pages/applications.php'); exit; }

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) die('CSRF');
    $action = $_POST['_action'] ?? '';
    if ($action === 'add_note') {
        $content = trim($_POST['content'] ?? '');
        $type    = $_POST['note_type'] ?? 'General';
        if ($content) {
            DB::run('INSERT INTO notes (application_id,user_id,content,note_type) VALUES (?,?,?,?)', [$id,$uid,$content,$type]);
            log_activity($uid,'add_note',"Added note to {$app['company']}",$id);
            $msg = 'Note added.'; $msgType = 'success';
        }
    }
    if ($action === 'delete_note') {
        DB::run('DELETE FROM notes WHERE id=? AND user_id=?', [(int)$_POST['note_id'], $uid]);
        $msg = 'Note deleted.'; $msgType = 'success';
    }
    if ($action === 'update_status') {
        $st = $_POST['status'] ?? '';
        DB::run('UPDATE applications SET status=? WHERE id=? AND user_id=?', [$st,$id,$uid]);
        $app['status'] = $st;
        log_activity($uid,'status_change',"Status changed to $st",$id);
        $msg = "Status updated to $st."; $msgType = 'success';
    }
}

$notes = DB::all('SELECT * FROM notes WHERE application_id=? ORDER BY created_at DESC', [$id]);
$interviews = DB::all('SELECT * FROM interviews WHERE application_id=? ORDER BY scheduled_at DESC', [$id]);

cf_layout_head($app['company'] . ' – ' . $app['job_title']);
cf_layout_sidebar('applications');

$statuses = ['Wishlist','Applied','Screening','Assessment','Interview','Final Interview','Offer','Rejected','Hired'];
$noteTypes = ['General','Follow-up','Recruiter','Feedback','Other'];
?>
<?php if ($msg): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast('<?= addslashes($msg) ?>','<?= $msgType ?>'));</script>
<?php endif; ?>

<div style="max-width:1000px">
  <!-- Breadcrumb -->
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;font-size:13px;color:var(--muted)">
    <a href="applications.php" style="color:var(--accent);text-decoration:none">Applications</a>
    <span>›</span>
    <span style="color:#fff"><?= htmlspecialchars($app['company']) ?></span>
  </div>

  <!-- Header -->
  <div class="card" style="padding:24px;margin-bottom:16px">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px">
      <div>
        <h1 style="font-size:22px;font-weight:700;color:#fff;margin-bottom:6px"><?= htmlspecialchars($app['job_title']) ?></h1>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <span style="font-size:16px;color:var(--accent2);font-weight:600"><?= htmlspecialchars($app['company']) ?></span>
          <?php if ($app['location']): ?>
          <span style="font-size:13px;color:var(--muted)">📍 <?= htmlspecialchars($app['location']) ?></span>
          <?php endif; ?>
          <span style="font-size:13px;color:var(--muted)"><?= htmlspecialchars($app['job_type']) ?></span>
        </div>
        <?php if ($app['salary_min']): ?>
        <div style="font-size:14px;color:var(--success);margin-top:6px">
          <?= $app['salary_currency'] ?> <?= number_format($app['salary_min']) ?><?= $app['salary_max'] ? ' – ' . number_format($app['salary_max']) : '+' ?> / year
        </div>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <form method="POST" style="display:flex;align-items:center;gap:6px">
          <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="update_status">
          <select name="status" class="cf-input" style="padding:8px 12px;width:auto">
            <?php foreach ($statuses as $s): ?>
            <option <?= $app['status']===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary btn-sm">Update</button>
        </form>
        <a href="applications.php?edit=<?= $app['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
        <?php if ($app['job_url']): ?>
        <a href="<?= htmlspecialchars($app['job_url']) ?>" target="_blank" class="btn btn-secondary btn-sm">View Job ↗</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 320px;gap:16px">
    <!-- Left: details + notes -->
    <div>
      <!-- Recruiter -->
      <?php if ($app['recruiter_name'] || $app['recruiter_email']): ?>
      <div class="card" style="padding:18px;margin-bottom:14px">
        <h3 style="font-size:13px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">Recruiter</h3>
        <?php if ($app['recruiter_name']): ?><div style="font-size:14px;color:#fff;font-weight:500"><?= htmlspecialchars($app['recruiter_name']) ?></div><?php endif; ?>
        <?php if ($app['recruiter_email']): ?><a href="mailto:<?= htmlspecialchars($app['recruiter_email']) ?>" style="font-size:13px;color:var(--accent);text-decoration:none"><?= htmlspecialchars($app['recruiter_email']) ?></a><?php endif; ?>
        <?php if ($app['recruiter_phone']): ?><div style="font-size:13px;color:var(--muted)"><?= htmlspecialchars($app['recruiter_phone']) ?></div><?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Job description -->
      <?php if ($app['job_description']): ?>
      <div class="card" style="padding:18px;margin-bottom:14px">
        <h3 style="font-size:13px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">Job Description</h3>
        <div style="font-size:13px;color:var(--muted);line-height:1.6;white-space:pre-wrap"><?= htmlspecialchars($app['job_description']) ?></div>
      </div>
      <?php endif; ?>

      <!-- Personal notes -->
      <?php if ($app['notes']): ?>
      <div class="card" style="padding:18px;margin-bottom:14px">
        <h3 style="font-size:13px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Personal Notes</h3>
        <p style="font-size:13px;color:var(--muted);line-height:1.6"><?= htmlspecialchars($app['notes']) ?></p>
      </div>
      <?php endif; ?>

      <!-- Notes feed -->
      <div class="card" style="overflow:hidden">
        <div style="padding:16px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
          <h3 style="font-size:14px;font-weight:700;color:#fff">Activity Notes</h3>
          <span style="font-size:12px;color:var(--muted)"><?= count($notes) ?></span>
        </div>
        <!-- Add note form -->
        <div style="padding:16px 18px;border-bottom:1px solid var(--border)">
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="_action" value="add_note">
            <div style="display:flex;gap:8px;margin-bottom:8px">
              <select name="note_type" class="cf-input" style="width:auto;padding:7px 10px;font-size:12px">
                <?php foreach ($noteTypes as $t): ?><option><?= $t ?></option><?php endforeach; ?>
              </select>
            </div>
            <textarea name="content" class="cf-input" rows="2" placeholder="Add a note, follow-up, or recruiter update…" required style="margin-bottom:8px"></textarea>
            <button type="submit" class="btn btn-primary btn-sm">Add Note</button>
          </form>
        </div>
        <!-- Notes list -->
        <?php if (empty($notes)): ?>
        <p style="padding:20px;color:var(--muted);font-size:13px;text-align:center">No notes yet</p>
        <?php else: foreach ($notes as $n): ?>
        <div style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,.04);display:flex;gap:10px">
          <div style="flex:1">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px">
              <span style="font-size:10px;padding:2px 7px;border-radius:20px;background:rgba(108,99,255,.15);color:#a78bfa;font-weight:600"><?= htmlspecialchars($n['note_type']) ?></span>
              <span style="font-size:11px;color:var(--muted)"><?= date('M j, Y g:i A', strtotime($n['created_at'])) ?></span>
            </div>
            <p style="font-size:13px;color:var(--text);line-height:1.5;white-space:pre-wrap"><?= htmlspecialchars($n['content']) ?></p>
          </div>
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="_action" value="delete_note">
            <input type="hidden" name="note_id" value="<?= $n['id'] ?>">
            <button type="submit" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:16px;padding:0" onclick="return confirm('Delete note?')">×</button>
          </form>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Right sidebar -->
    <div>
      <!-- Quick info -->
      <div class="card" style="padding:18px;margin-bottom:14px">
        <h3 style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px">Details</h3>
        <?php $details = [
          'Applied Date' => $app['applied_date'] ? date('M j, Y', strtotime($app['applied_date'])) : '—',
          'Status' => $app['status'],
          'Job Type' => $app['job_type'],
          'Added' => date('M j, Y', strtotime($app['created_at'])),
        ];
        foreach ($details as $label => $val): ?>
        <div style="display:flex;justify-content:space-between;margin-bottom:10px">
          <span style="font-size:12px;color:var(--muted)"><?= $label ?></span>
          <span style="font-size:12px;color:#fff;font-weight:500"><?= htmlspecialchars($val) ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Interviews -->
      <div class="card" style="overflow:hidden">
        <div style="padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
          <h3 style="font-size:13px;font-weight:700;color:#fff">Interviews</h3>
          <a href="calendar.php" style="font-size:11px;color:var(--accent);text-decoration:none">+ Schedule</a>
        </div>
        <?php if (empty($interviews)): ?>
        <p style="padding:16px;font-size:12px;color:var(--muted);text-align:center">No interviews yet</p>
        <?php else: foreach ($interviews as $iv): ?>
        <div style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,.04)">
          <div style="font-size:12px;font-weight:600;color:#fff"><?= htmlspecialchars($iv['title']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= date('M j, g:i A', strtotime($iv['scheduled_at'])) ?></div>
          <div style="font-size:11px;color:var(--accent2)"><?= htmlspecialchars($iv['interview_type']) ?> · <?= $iv['duration_min'] ?>min</div>
          <?php if ($iv['outcome'] !== 'Pending'): ?>
          <div style="font-size:10px;color:<?= $iv['outcome']==='Passed' ? 'var(--success)' : 'var(--danger)' ?>;margin-top:3px"><?= $iv['outcome'] ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>

</main>
</body>
</html>
