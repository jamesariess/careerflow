<?php
require_once '../components/layout.php';

$uid = (int)$user['id'];
$id  = (int)($_GET['id'] ?? 0);

$app = DB::one('SELECT a.*, r.label AS resume_label, r.filename AS resume_file FROM applications a LEFT JOIN resumes r ON a.resume_id=r.id WHERE a.id=? AND a.user_id=?', [$id, $uid]);
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
    if ($action === 'edit_note') {
        $content = trim($_POST['content'] ?? '');
        if ($content) DB::run('UPDATE notes SET content=? WHERE id=? AND user_id=?', [$content,(int)$_POST['note_id'],$uid]);
        $msg = 'Note updated.'; $msgType = 'success';
    }
    if ($action === 'update_status') {
        $st = $_POST['status'] ?? '';
        $valid = ['Wishlist','Applied','Screening','Assessment','Interview','Final Interview','Offer','Rejected','Hired'];
        if (in_array($st,$valid)) {
            DB::run('UPDATE applications SET status=? WHERE id=? AND user_id=?', [$st,$id,$uid]);
            $app['status'] = $st;
            log_activity($uid,'status_change',"Status → $st",$id);
            $msg = "Status updated to $st."; $msgType = 'success';
        }
    }
    if ($action === 'update_field') {
        $field = $_POST['field'] ?? '';
        $val   = trim($_POST['value'] ?? '');
        $safe  = ['recruiter_name','recruiter_email','recruiter_phone','notes','job_url'];
        if (in_array($field,$safe)) {
            DB::run("UPDATE applications SET $field=? WHERE id=? AND user_id=?", [$val,$id,$uid]);
            $app[$field] = $val;
            $msg = 'Updated.'; $msgType = 'success';
        }
    }
    if ($action === 'update_interview_outcome') {
        $ivId    = (int)($_POST['interview_id'] ?? 0);
        $outcome = $_POST['outcome'] ?? 'Pending';
        $valid   = ['Pending','Passed','Failed','Cancelled','Rescheduled'];
        if (in_array($outcome,$valid))
            DB::run('UPDATE interviews SET outcome=? WHERE id=? AND user_id=?', [$outcome,$ivId,$uid]);
        header('Content-Type: application/json');
        echo json_encode(['ok'=>true]);
        exit;
    }
    if ($action === 'star') {
        $newStar = $app['is_starred'] ? 0 : 1;
        DB::run('UPDATE applications SET is_starred=? WHERE id=? AND user_id=?', [$newStar,$id,$uid]);
        $app['is_starred'] = $newStar;
    }
}

$notes      = DB::all('SELECT * FROM notes WHERE application_id=? ORDER BY created_at DESC', [$id]);
$interviews = DB::all('SELECT * FROM interviews WHERE application_id=? ORDER BY scheduled_at DESC', [$id]);
$coverLetters = DB::all('SELECT id,title,tone,updated_at FROM cover_letters WHERE application_id=? AND user_id=? ORDER BY updated_at DESC', [$id,$uid]);
$relatedEmails = DB::all('SELECT * FROM gmail_emails WHERE application_id=? AND user_id=? ORDER BY received_at DESC LIMIT 5', [$id,$uid]);

cf_layout_head($app['company'].' – '.$app['job_title']);
cf_layout_sidebar('applications');

$statuses  = ['Wishlist','Applied','Screening','Assessment','Interview','Final Interview','Offer','Rejected','Hired'];
$noteTypes = ['General','Follow-up','Recruiter','Feedback','Other'];

// Status colour
$statusColors = [
    'Wishlist'=>'#94a3b8','Applied'=>'#60a5fa','Screening'=>'#fbbf24','Assessment'=>'#a78bfa',
    'Interview'=>'#34d399','Final Interview'=>'#fb7185','Offer'=>'#22c55e','Rejected'=>'#f87171','Hired'=>'#4ECDC4'
];
$sc = $statusColors[$app['status']] ?? '#6C63FF';

// Timeline
$timeline = [
    'Wishlist'=>0,'Applied'=>1,'Screening'=>2,'Assessment'=>3,
    'Interview'=>4,'Final Interview'=>5,'Offer'=>6,'Hired'=>7
];
$currentStep = $timeline[$app['status']] ?? -1;
$rejected    = $app['status'] === 'Rejected';
?>
<?php if ($msg): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast(<?= json_encode($msg) ?>,'<?= $msgType ?>'));</script>
<?php endif; ?>

<style>
.detail-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:14px;}
.detail-label{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;}
.detail-val{font-size:14px;color:var(--text);line-height:1.5;}
.timeline-step{display:flex;flex-direction:column;align-items:center;gap:4px;flex:1;}
.timeline-dot{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;border:2px solid;}
.timeline-label{font-size:9px;font-weight:600;text-align:center;text-transform:uppercase;letter-spacing:.4px;}
.note-card{background:rgba(255,255,255,.025);border:1px solid rgba(255,255,255,.06);border-radius:10px;padding:14px;margin-bottom:8px;}
.outcome-badge{display:inline-flex;align-items:center;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;}
</style>

<div style="max-width:1100px">

  <!-- Breadcrumb -->
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:18px;font-size:13px;color:var(--muted)">
    <a href="applications.php" style="color:var(--accent);text-decoration:none">Applications</a>
    <span>›</span>
    <span style="color:#fff"><?= htmlspecialchars($app['company']) ?></span>
    <button onclick="toggleStar()" id="starBtn" style="background:none;border:none;cursor:pointer;font-size:18px;margin-left:4px;line-height:1" title="Star this application">
      <?= $app['is_starred'] ? '⭐' : '☆' ?>
    </button>
  </div>

  <!-- ── Hero card ── -->
  <div class="detail-card" style="margin-bottom:18px">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px">
      <div style="flex:1;min-width:0">
        <!-- Company logo placeholder -->
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px">
          <div style="width:52px;height:52px;border-radius:12px;background:linear-gradient(135deg,rgba(108,99,255,.3),rgba(78,205,196,.2));display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:#fff;font-family:'Syne',sans-serif;flex-shrink:0;border:1px solid var(--border)">
            <?= strtoupper(mb_substr($app['company'],0,1)) ?>
          </div>
          <div>
            <h1 style="font-size:20px;font-weight:700;color:#fff;margin-bottom:3px"><?= htmlspecialchars($app['job_title']) ?></h1>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
              <span style="font-size:15px;color:var(--accent2);font-weight:600"><?= htmlspecialchars($app['company']) ?></span>
              <?php if ($app['location']): ?><span style="font-size:12px;color:var(--muted)">📍 <?= htmlspecialchars($app['location']) ?></span><?php endif; ?>
              <span style="font-size:12px;color:var(--muted)">🏢 <?= htmlspecialchars($app['job_type']) ?></span>
            </div>
          </div>
        </div>

        <?php if ($app['salary_min']): ?>
        <div style="font-size:15px;color:var(--success);font-weight:600;margin-bottom:8px">
          <?= cf_currency((float)$app['salary_min'], true) ?><?= $app['salary_max'] ? ' – ' . cf_currency((float)$app['salary_max'], true) : '+' ?> / year
        </div>
        <?php endif; ?>

        <!-- Quick links -->
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <?php if ($app['job_url']): ?>
          <a href="<?= htmlspecialchars($app['job_url']) ?>" target="_blank" class="btn btn-secondary btn-sm">🔗 View Job Posting</a>
          <?php endif; ?>
          <?php if ($app['resume_file']): ?>
          <a href="<?= APP_URL ?>/uploads/resumes/<?= urlencode($app['resume_file']) ?>" download class="btn btn-secondary btn-sm">📄 <?= htmlspecialchars($app['resume_label']) ?></a>
          <?php endif; ?>
          <a href="cover_letters.php?app=<?= $id ?>" class="btn btn-secondary btn-sm">✍️ Cover Letters</a>
          <a href="gmail.php?filter=job" class="btn btn-secondary btn-sm">📧 Related Emails</a>
        </div>
      </div>

      <!-- Status control -->
      <div style="display:flex;flex-direction:column;gap:8px;min-width:200px">
        <div style="padding:14px;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;text-align:center">
          <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Current Status</div>
          <div style="font-size:16px;font-weight:700;color:<?= $sc ?>;font-family:'Syne',sans-serif"><?= htmlspecialchars($app['status']) ?></div>
          <?php if ($app['applied_date']): ?>
          <div style="font-size:11px;color:var(--muted);margin-top:4px">Applied <?= date('M j, Y',strtotime($app['applied_date'])) ?></div>
          <?php endif; ?>
        </div>
        <form method="POST" style="display:flex;gap:6px">
          <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="update_status">
          <select name="status" class="cf-input" style="flex:1;padding:7px 10px;font-size:12px">
            <?php foreach($statuses as $s): ?><option <?= $app['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary btn-sm">✓</button>
        </form>
        <div style="display:flex;gap:6px">
          <a href="applications.php?edit=<?= $id ?>" class="btn btn-secondary btn-sm" style="flex:1;justify-content:center">✏️ Edit</a>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Status timeline ── -->
  <?php if (!$rejected): ?>
  <div class="detail-card" style="margin-bottom:18px">
    <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px">Application Progress</div>
    <div style="display:flex;align-items:flex-start;gap:0">
      <?php
      $steps = ['Applied','Screening','Assessment','Interview','Final Interview','Offer','Hired'];
      foreach($steps as $i=>$step):
        $stepIdx = $timeline[$step] ?? $i+1;
        $done    = $currentStep >= $stepIdx;
        $active  = $currentStep === $stepIdx;
        $sc2     = $statusColors[$step] ?? '#6C63FF';
      ?>
      <div class="timeline-step">
        <div class="timeline-dot" style="background:<?= $done?$sc2:'transparent' ?>;border-color:<?= $done?$sc2:'rgba(255,255,255,.15)' ?>;color:<?= $done?'#fff':'rgba(255,255,255,.3)' ?>">
          <?= $done ? '✓' : ($i+1) ?>
        </div>
        <div class="timeline-label" style="color:<?= $done?'#fff':'rgba(255,255,255,.3)' ?>"><?= $step ?></div>
      </div>
      <?php if ($i < count($steps)-1): ?>
      <div style="flex:1;height:2px;background:<?= $currentStep>$timeline[$step]?'var(--accent)':'rgba(255,255,255,.08)' ?>;margin-top:12px;border-radius:2px"></div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: ?>
  <div style="background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.2);border-radius:12px;padding:14px 18px;margin-bottom:18px;display:flex;align-items:center;gap:12px">
    <span style="font-size:22px">❌</span>
    <div><div style="color:var(--danger);font-weight:700;font-size:14px">Application Rejected</div><div style="font-size:12px;color:var(--muted)">Keep going — every rejection is one step closer to the right role.</div></div>
  </div>
  <?php endif; ?>

  <!-- ── Main two-column layout ── -->
  <div style="display:grid;grid-template-columns:1fr 340px;gap:16px">

    <!-- LEFT column -->
    <div>

      <!-- Recruiter -->
      <?php if ($app['recruiter_name'] || $app['recruiter_email']): ?>
      <div class="detail-card">
        <div class="detail-label">Recruiter Contact</div>
        <div style="display:flex;align-items:center;gap:14px;margin-top:8px">
          <div style="width:40px;height:40px;border-radius:50%;background:rgba(108,99,255,.2);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:var(--accent);flex-shrink:0">
            <?= strtoupper(mb_substr($app['recruiter_name']??'?',0,1)) ?>
          </div>
          <div>
            <?php if ($app['recruiter_name']): ?><div style="font-size:14px;font-weight:600;color:#fff"><?= htmlspecialchars($app['recruiter_name']) ?></div><?php endif; ?>
            <?php if ($app['recruiter_email']): ?><a href="mailto:<?= htmlspecialchars($app['recruiter_email']) ?>" style="font-size:13px;color:var(--accent);text-decoration:none"><?= htmlspecialchars($app['recruiter_email']) ?></a><?php endif; ?>
            <?php if ($app['recruiter_phone']): ?><div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($app['recruiter_phone']) ?></div><?php endif; ?>
          </div>
          <a href="mailto:<?= htmlspecialchars($app['recruiter_email']??'') ?>" class="btn btn-secondary btn-sm" style="margin-left:auto">✉️ Email</a>
        </div>
      </div>
      <?php endif; ?>

      <!-- Job Description -->
      <?php if ($app['job_description']): ?>
      <div class="detail-card">
        <div class="detail-label">Job Description</div>
        <div style="font-size:13px;color:var(--muted);line-height:1.7;white-space:pre-wrap;margin-top:8px;max-height:300px;overflow-y:auto"><?= htmlspecialchars($app['job_description']) ?></div>
      </div>
      <?php endif; ?>

      <!-- Notes feed -->
      <div class="detail-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
          <div class="detail-label" style="margin:0">Activity Notes</div>
          <span style="font-size:11px;color:var(--muted)"><?= count($notes) ?> note<?= count($notes)!==1?'s':'' ?></span>
        </div>

        <!-- Add note -->
        <form method="POST" style="margin-bottom:14px;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:10px;padding:14px">
          <input type="hidden" name="_csrf"    value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="add_note">
          <div style="display:flex;gap:8px;margin-bottom:8px">
            <?php foreach($noteTypes as $nt): ?>
            <label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:11px;color:var(--muted)">
              <input type="radio" name="note_type" value="<?= $nt ?>" <?= $nt==='General'?'checked':'' ?> style="accent-color:var(--accent)"> <?= $nt ?>
            </label>
            <?php endforeach; ?>
          </div>
          <textarea name="content" class="cf-input" rows="2" placeholder="Add a note, follow-up reminder, or recruiter update…" required style="margin-bottom:8px"></textarea>
          <button type="submit" class="btn btn-primary btn-sm">Add Note</button>
        </form>

        <!-- Notes list -->
        <?php if (empty($notes)): ?>
        <p style="color:var(--muted);font-size:13px;text-align:center;padding:16px 0">No notes yet</p>
        <?php else: foreach($notes as $n):
          $ntColors=['General'=>['rgba(108,99,255,.15)','#a78bfa'],'Follow-up'=>['rgba(251,191,36,.12)','#fbbf24'],'Recruiter'=>['rgba(78,205,196,.12)','#4ECDC4'],'Feedback'=>['rgba(34,197,94,.12)','#4ade80'],'Other'=>['rgba(148,163,184,.1)','#94a3b8']];
          [$nbg,$nc]=$ntColors[$n['note_type']]??$ntColors['General'];
        ?>
        <div class="note-card" id="nc-<?= $n['id'] ?>">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:7px">
            <span style="font-size:10px;padding:2px 8px;border-radius:20px;background:<?= $nbg ?>;color:<?= $nc ?>;font-weight:700"><?= htmlspecialchars($n['note_type']) ?></span>
            <span style="font-size:11px;color:var(--muted)"><?= date('M j, Y g:i A',strtotime($n['created_at'])) ?></span>
            <div style="display:flex;gap:4px;margin-left:auto">
              <button onclick="editNote(<?= $n['id'] ?>)" style="background:none;border:none;cursor:pointer;font-size:12px;color:var(--muted);padding:2px 5px;border-radius:4px" title="Edit">✏️</button>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete note?')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete_note"><input type="hidden" name="note_id" value="<?= $n['id'] ?>">
                <button type="submit" style="background:none;border:none;cursor:pointer;font-size:12px;color:var(--muted);padding:2px 5px;border-radius:4px" title="Delete">🗑</button>
              </form>
            </div>
          </div>
          <div class="nv-<?= $n['id'] ?>">
            <p style="font-size:13px;color:var(--text);line-height:1.6;white-space:pre-wrap"><?= htmlspecialchars($n['content']) ?></p>
          </div>
          <div class="ne-<?= $n['id'] ?>" style="display:none">
            <form method="POST">
              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="edit_note"><input type="hidden" name="note_id" value="<?= $n['id'] ?>">
              <textarea name="content" class="cf-input" rows="3" style="margin-bottom:7px"><?= htmlspecialchars($n['content']) ?></textarea>
              <div style="display:flex;gap:6px"><button type="submit" class="btn btn-primary btn-sm">Save</button><button type="button" onclick="cancelEditNote(<?= $n['id'] ?>)" class="btn btn-secondary btn-sm">Cancel</button></div>
            </form>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- RIGHT column -->
    <div>

      <!-- Details card -->
      <div class="detail-card">
        <div class="detail-label" style="margin-bottom:12px">Details</div>
        <?php $details=[
          ['Applied','applied_date', $app['applied_date'] ? date('M j, Y',strtotime($app['applied_date'])) : '—'],
          ['Job Type','job_type',    $app['job_type']],
          ['Location','location',   $app['location'] ?: '—'],
          ['Added',   '',           date('M j, Y',strtotime($app['created_at']))],
        ];
        foreach($details as [$l,,$v]): ?>
        <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.04)">
          <span style="font-size:12px;color:var(--muted)"><?= $l ?></span>
          <span style="font-size:12px;color:#fff;font-weight:500;text-align:right;max-width:60%"><?= htmlspecialchars($v) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if ($app['salary_min']): ?>
        <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.04)">
          <span style="font-size:12px;color:var(--muted)">Salary</span>
          <span style="font-size:12px;color:var(--success);font-weight:600"><?= cf_currency((float)$app['salary_min'],true) ?><?= $app['salary_max']?' – '.cf_currency((float)$app['salary_max'],true):'+' ?></span>
        </div>
        <?php endif; ?>
        <?php if ($app['notes']): ?>
        <div style="margin-top:10px">
          <div class="detail-label">Personal Notes</div>
          <p style="font-size:12px;color:var(--muted);line-height:1.6;margin-top:4px"><?= htmlspecialchars($app['notes']) ?></p>
        </div>
        <?php endif; ?>
      </div>

      <!-- Interviews -->
      <div class="detail-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
          <div class="detail-label" style="margin:0">Interviews</div>
          <a href="calendar.php" style="font-size:11px;color:var(--accent);text-decoration:none">+ Schedule</a>
        </div>
        <?php if (empty($interviews)): ?>
        <p style="font-size:12px;color:var(--muted);text-align:center;padding:10px 0">No interviews scheduled</p>
        <?php else: foreach($interviews as $iv):
          $outcomeColors=['Pending'=>['#fbbf24','rgba(251,191,36,.12)'],'Passed'=>['#4ade80','rgba(34,197,94,.12)'],'Failed'=>['#f87171','rgba(248,113,113,.12)'],'Cancelled'=>['#94a3b8','rgba(148,163,184,.1)'],'Rescheduled'=>['#a78bfa','rgba(167,139,250,.12)']];
          [$oc,$ob]=$outcomeColors[$iv['outcome']]??$outcomeColors['Pending'];
          $isPast = strtotime($iv['scheduled_at']) < time();
        ?>
        <div style="padding:10px 0;border-bottom:1px solid rgba(255,255,255,.04)">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px">
            <div style="flex:1;min-width:0">
              <div style="font-size:13px;font-weight:600;color:#fff;margin-bottom:2px"><?= htmlspecialchars($iv['title']) ?></div>
              <div style="font-size:11px;color:var(--accent2)"><?= htmlspecialchars($iv['interview_type']) ?> · <?= $iv['duration_min'] ?>min</div>
              <div style="font-size:11px;color:var(--muted);margin-top:2px"><?= date('M j, Y g:i A',strtotime($iv['scheduled_at'])) ?></div>
              <?php if ($iv['interviewer']): ?><div style="font-size:11px;color:var(--muted)">👤 <?= htmlspecialchars($iv['interviewer']) ?></div><?php endif; ?>
              <?php if ($iv['location_or_link']): ?><a href="<?= htmlspecialchars($iv['location_or_link']) ?>" target="_blank" style="font-size:10px;color:var(--accent);text-decoration:none;display:block;margin-top:2px">🔗 Join link</a><?php endif; ?>
            </div>
            <span class="outcome-badge" style="background:<?= $ob ?>;color:<?= $oc ?>"><?= $iv['outcome'] ?></span>
          </div>
          <?php if ($isPast && $iv['outcome']==='Pending'): ?>
          <div style="margin-top:8px;display:flex;gap:4px">
            <?php foreach(['Passed','Failed','Rescheduled'] as $outcome): ?>
            <button onclick="setOutcome(<?= $iv['id'] ?>,'<?= $outcome ?>')" class="btn btn-secondary btn-sm" style="flex:1;justify-content:center;padding:4px;font-size:10px"><?= $outcome ?></button>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Cover letters -->
      <?php if (!empty($coverLetters)): ?>
      <div class="detail-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
          <div class="detail-label" style="margin:0">Cover Letters</div>
          <a href="cover_letters.php" style="font-size:11px;color:var(--accent);text-decoration:none">+ New</a>
        </div>
        <?php foreach($coverLetters as $cl): ?>
        <a href="cover_letters.php?edit=<?= $cl['id'] ?>" style="display:block;padding:8px 10px;border-radius:8px;border:1px solid var(--border);text-decoration:none;margin-bottom:6px;transition:border-color .18s" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
          <div style="font-size:12px;font-weight:600;color:#fff"><?= htmlspecialchars($cl['title']) ?></div>
          <div style="font-size:10px;color:var(--muted)"><?= ucfirst($cl['tone']) ?> · <?= date('M j',strtotime($cl['updated_at'])) ?></div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Related emails -->
      <?php if (!empty($relatedEmails)): ?>
      <div class="detail-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
          <div class="detail-label" style="margin:0">Related Emails</div>
          <a href="gmail.php" style="font-size:11px;color:var(--accent);text-decoration:none">View all</a>
        </div>
        <?php foreach($relatedEmails as $em): ?>
        <div style="padding:8px 0;border-bottom:1px solid rgba(255,255,255,.04)">
          <div style="font-size:12px;font-weight:600;color:<?= $em['is_read']?'rgba(255,255,255,.6)':'#fff' ?>"><?= htmlspecialchars(mb_substr($em['subject'],0,42)) ?></div>
          <div style="font-size:10px;color:var(--muted);display:flex;justify-content:space-between;margin-top:2px">
            <span><?= htmlspecialchars($em['sender_name']?:$em['sender_email']) ?></span>
            <span><?= date('M j',strtotime($em['received_at'])) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
function editNote(id) {
  document.querySelector('.nv-'+id).style.display='none';
  document.querySelector('.ne-'+id).style.display='block';
}
function cancelEditNote(id) {
  document.querySelector('.nv-'+id).style.display='block';
  document.querySelector('.ne-'+id).style.display='none';
}

async function setOutcome(ivId, outcome) {
  const fd=new FormData();
  fd.append('_csrf','<?= csrf_token() ?>');
  fd.append('_action','update_interview_outcome');
  fd.append('interview_id',ivId);
  fd.append('outcome',outcome);
  await fetch('application_detail.php?id=<?= $id ?>',{method:'POST',body:fd});
  showToast('Interview marked as '+outcome,'success');
  setTimeout(()=>location.reload(),1000);
}

async function toggleStar() {
  const fd=new FormData();
  fd.append('_csrf','<?= csrf_token() ?>');
  fd.append('_action','star');
  const r = await fetch('application_detail.php?id=<?= $id ?>',{method:'POST',body:fd});
  const btn = document.getElementById('starBtn');
  const isStarred = btn.textContent.trim()==='⭐';
  btn.textContent = isStarred ? '☆' : '⭐';
  showToast(isStarred?'Removed from starred':'Added to starred','info');
}
</script>

</main>
</body>
</html>
