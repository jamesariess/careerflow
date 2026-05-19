<?php
require_once '../components/layout.php';
cf_layout_head('Notes');
cf_layout_sidebar('notes');

$uid = (int)$user['id'];
$msg = ''; $msgType = '';

/* ── POST handlers ──────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) die('CSRF error');
    $action = $_POST['_action'] ?? '';

    if ($action === 'add_note') {
        $appId   = (int)($_POST['application_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $type    = $_POST['note_type'] ?? 'General';
        if ($appId && $content) {
            // verify ownership
            $own = DB::one('SELECT id FROM applications WHERE id=? AND user_id=?', [$appId, $uid]);
            if ($own) {
                DB::run('INSERT INTO notes (application_id,user_id,content,note_type) VALUES (?,?,?,?)',
                    [$appId, $uid, $content, $type]);
                log_activity($uid, 'add_note', "Added note", $appId);
                $msg = 'Note added!'; $msgType = 'success';
            }
        }
    }

    if ($action === 'delete_note') {
        $nid = (int)($_POST['note_id'] ?? 0);
        DB::run('DELETE FROM notes WHERE id=? AND user_id=?', [$nid, $uid]);
        $msg = 'Note deleted.'; $msgType = 'success';
    }

    if ($action === 'edit_note') {
        $nid     = (int)($_POST['note_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        if ($content) {
            DB::run('UPDATE notes SET content=? WHERE id=? AND user_id=?', [$content, $nid, $uid]);
            $msg = 'Note updated.'; $msgType = 'success';
        }
    }

    if ($action === 'add_reminder') {
        $title     = trim($_POST['title'] ?? '');
        $remindAt  = $_POST['remind_at'] ?? '';
        $appId     = (int)($_POST['application_id'] ?? 0) ?: null;
        if ($title && $remindAt) {
            DB::run('INSERT INTO reminders (user_id, application_id, title, remind_at) VALUES (?,?,?,?)',
                [$uid, $appId, $title, $remindAt]);
            $msg = 'Reminder set!'; $msgType = 'success';
        }
    }

    if ($action === 'delete_reminder') {
        DB::run('DELETE FROM reminders WHERE id=? AND user_id=?', [(int)$_POST['reminder_id'], $uid]);
        $msg = 'Reminder removed.'; $msgType = 'success';
    }
}

/* ── Filters ─────────────────────────────────── */
$filterApp  = (int)($_GET['app'] ?? 0);
$filterType = $_GET['type'] ?? '';
$q          = trim($_GET['q'] ?? '');

$where  = ['n.user_id=?']; $params = [$uid];
if ($filterApp)  { $where[] = 'n.application_id=?'; $params[] = $filterApp; }
if ($filterType) { $where[] = 'n.note_type=?';      $params[] = $filterType; }
if ($q)          { $where[] = 'n.content LIKE ?';    $params[] = "%$q%"; }

$notes = DB::all("
    SELECT n.*, a.company, a.job_title
    FROM notes n
    LEFT JOIN applications a ON n.application_id = a.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY n.created_at DESC", $params);

/* ── Upcoming reminders ──────────────────────── */
$reminders = DB::all("
    SELECT r.*, a.company, a.job_title
    FROM reminders r
    LEFT JOIN applications a ON r.application_id = a.id
    WHERE r.user_id=? AND r.remind_at >= NOW()
    ORDER BY r.remind_at ASC
    LIMIT 10", [$uid]);

/* ── Apps for dropdowns ──────────────────────── */
$apps      = DB::all('SELECT id, company, job_title FROM applications WHERE user_id=? ORDER BY company', [$uid]);
$noteTypes = ['General', 'Follow-up', 'Recruiter', 'Feedback', 'Other'];

/* ── Note type badge colours ─────────────────── */
$typeBg = [
    'General'   => 'rgba(108,99,255,.15)',  'General_c'   => '#a78bfa',
    'Follow-up' => 'rgba(251,191,36,.12)',  'Follow-up_c' => '#fbbf24',
    'Recruiter' => 'rgba(78,205,196,.12)',  'Recruiter_c' => '#4ECDC4',
    'Feedback'  => 'rgba(34,197,94,.12)',   'Feedback_c'  => '#4ade80',
    'Other'     => 'rgba(148,163,184,.1)', 'Other_c'     => '#94a3b8',
];
?>
<?php if ($msg): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast('<?= addslashes($msg) ?>','<?= $msgType ?>'));</script>
<?php endif; ?>

<div style="max-width:1100px">

  <!-- ── Page header ── -->
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 style="font-size:22px;font-weight:700;color:#fff">Notes &amp; Reminders</h1>
      <p style="color:var(--muted);font-size:13px;margin-top:2px"><?= count($notes) ?> note<?= count($notes) !== 1 ? 's' : '' ?></p>
    </div>
    <div style="display:flex;gap:8px">
      <button onclick="document.getElementById('reminderModal').classList.add('open')" class="btn btn-secondary btn-sm">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Set Reminder
      </button>
      <button onclick="document.getElementById('addNoteModal').classList.add('open')" class="btn btn-primary btn-sm">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg>
        Add Note
      </button>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 300px;gap:16px;align-items:start">

    <!-- ── Left: notes list ── -->
    <div>
      <!-- Filters bar -->
      <div class="card" style="padding:12px 16px;margin-bottom:14px;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
        <form method="GET" style="display:contents">
          <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search notes…" class="cf-input" style="width:180px;padding:7px 12px;font-size:13px">
          <select name="app" class="cf-input" style="width:180px;padding:7px 12px;font-size:13px">
            <option value="">All Applications</option>
            <?php foreach ($apps as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $filterApp===$a['id']?'selected':'' ?>><?= htmlspecialchars($a['company']) ?> – <?= htmlspecialchars(mb_substr($a['job_title'],0,22)) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="type" class="cf-input" style="width:130px;padding:7px 12px;font-size:13px">
            <option value="">All Types</option>
            <?php foreach ($noteTypes as $t): ?>
            <option value="<?= $t ?>" <?= $filterType===$t?'selected':'' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
          <a href="notes.php" class="btn btn-secondary btn-sm" style="color:var(--muted)">Reset</a>
        </form>
      </div>

      <!-- Notes cards -->
      <?php if (empty($notes)): ?>
      <div class="card" style="padding:50px;text-align:center">
        <div style="font-size:36px;margin-bottom:12px">📝</div>
        <h3 style="color:#fff;font-size:16px;font-weight:600;margin-bottom:6px">No notes yet</h3>
        <p style="color:var(--muted);font-size:13px;margin-bottom:18px">Track recruiter conversations, follow-ups, and feedback.</p>
        <button onclick="document.getElementById('addNoteModal').classList.add('open')" class="btn btn-primary btn-sm">Add First Note</button>
      </div>
      <?php else: foreach ($notes as $n):
        $bg = $typeBg[$n['note_type']] ?? 'rgba(108,99,255,.15)';
        $tc = $typeBg[$n['note_type'].'_c'] ?? '#a78bfa';
      ?>
      <div class="card" style="padding:18px;margin-bottom:10px" id="note-<?= $n['id'] ?>">
        <div style="display:flex;align-items:flex-start;gap:12px">
          <div style="flex:1;min-width:0">
            <!-- Meta row -->
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px">
              <span style="font-size:10px;padding:2px 8px;border-radius:20px;background:<?= $bg ?>;color:<?= $tc ?>;font-weight:700"><?= htmlspecialchars($n['note_type']) ?></span>
              <?php if ($n['company']): ?>
              <a href="application_detail.php?id=<?= $n['application_id'] ?>" style="font-size:12px;color:var(--accent);text-decoration:none;font-weight:500"><?= htmlspecialchars($n['company']) ?></a>
              <span style="font-size:11px;color:var(--muted)">· <?= htmlspecialchars(mb_substr($n['job_title'],0,30)) ?></span>
              <?php endif; ?>
              <span style="font-size:11px;color:var(--muted);margin-left:auto"><?= date('M j, Y · g:i A', strtotime($n['created_at'])) ?></span>
            </div>
            <!-- Content (view mode) -->
            <div class="note-view-<?= $n['id'] ?>">
              <p style="font-size:13px;color:var(--text);line-height:1.65;white-space:pre-wrap"><?= htmlspecialchars($n['content']) ?></p>
              <div style="display:flex;gap:6px;margin-top:10px">
                <button onclick="editNote(<?= $n['id'] ?>)" class="btn btn-secondary btn-sm" style="padding:4px 10px;font-size:11px">Edit</button>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this note?')">
                  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="_action" value="delete_note">
                  <input type="hidden" name="note_id" value="<?= $n['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm" style="padding:4px 10px;font-size:11px">Delete</button>
                </form>
              </div>
            </div>
            <!-- Content (edit mode) -->
            <div class="note-edit-<?= $n['id'] ?>" style="display:none">
              <form method="POST">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="_action" value="edit_note">
                <input type="hidden" name="note_id" value="<?= $n['id'] ?>">
                <textarea name="content" class="cf-input" rows="3" style="margin-bottom:8px"><?= htmlspecialchars($n['content']) ?></textarea>
                <div style="display:flex;gap:6px">
                  <button type="submit" class="btn btn-primary btn-sm" style="font-size:11px">Save</button>
                  <button type="button" onclick="cancelEdit(<?= $n['id'] ?>)" class="btn btn-secondary btn-sm" style="font-size:11px">Cancel</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- ── Right: reminders ── -->
    <div>
      <div class="card" style="overflow:hidden">
        <div style="padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
          <h3 style="font-size:13px;font-weight:700;color:#fff">Upcoming Reminders</h3>
          <button onclick="document.getElementById('reminderModal').classList.add('open')" style="font-size:11px;color:var(--accent);background:none;border:none;cursor:pointer">+ Add</button>
        </div>
        <?php if (empty($reminders)): ?>
        <p style="padding:18px;font-size:12px;color:var(--muted);text-align:center">No reminders set</p>
        <?php else: foreach ($reminders as $r): ?>
        <div style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,.04)">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px">
            <div>
              <div style="font-size:13px;font-weight:600;color:#fff;margin-bottom:2px"><?= htmlspecialchars($r['title']) ?></div>
              <?php if ($r['company']): ?>
              <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($r['company']) ?></div>
              <?php endif; ?>
              <div style="font-size:11px;color:var(--warning);margin-top:3px">
                ⏰ <?= date('M j, Y g:i A', strtotime($r['remind_at'])) ?>
              </div>
            </div>
            <form method="POST">
              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="delete_reminder">
              <input type="hidden" name="reminder_id" value="<?= $r['id'] ?>">
              <button type="submit" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:16px;line-height:1;padding:0">×</button>
            </form>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Quick stats card -->
      <div class="card" style="padding:16px;margin-top:12px">
        <h3 style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:12px">Notes by Type</h3>
        <?php
        $typeCounts = DB::all("SELECT note_type, COUNT(*) AS n FROM notes WHERE user_id=? GROUP BY note_type ORDER BY n DESC", [$uid]);
        $totalNotes = array_sum(array_column($typeCounts, 'n'));
        foreach ($typeCounts as $tc_row):
            $pct  = $totalNotes > 0 ? round($tc_row['n'] / $totalNotes * 100) : 0;
            $bg   = $typeBg[$tc_row['note_type']] ?? 'rgba(108,99,255,.15)';
            $col  = $typeBg[$tc_row['note_type'].'_c'] ?? '#a78bfa';
        ?>
        <div style="margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px">
            <span style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($tc_row['note_type']) ?></span>
            <span style="font-size:12px;color:#fff;font-weight:600"><?= $tc_row['n'] ?></span>
          </div>
          <div style="height:4px;background:rgba(255,255,255,.07);border-radius:4px;overflow:hidden">
            <div style="height:100%;width:<?= $pct ?>%;background:<?= $col ?>;border-radius:4px;transition:width .5s"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($typeCounts)): ?>
        <p style="font-size:12px;color:var(--muted);text-align:center">No notes yet</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ── Add Note Modal ── -->
<div class="modal-overlay" id="addNoteModal">
  <div class="modal-box" style="max-width:500px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
      <h2 style="font-size:17px;font-weight:700;color:#fff">Add Note</h2>
      <button onclick="document.getElementById('addNoteModal').classList.remove('open')" style="background:rgba(255,255,255,.07);border:none;color:var(--muted);width:28px;height:28px;border-radius:7px;cursor:pointer;font-size:18px">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="add_note">
      <div style="margin-bottom:12px">
        <label class="cf-label">Application</label>
        <select name="application_id" class="cf-input" required>
          <option value="">Select application…</option>
          <?php foreach ($apps as $a): ?>
          <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['company']) ?> – <?= htmlspecialchars($a['job_title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="margin-bottom:12px">
        <label class="cf-label">Note Type</label>
        <select name="note_type" class="cf-input">
          <?php foreach ($noteTypes as $t): ?><option><?= $t ?></option><?php endforeach; ?>
        </select>
      </div>
      <div style="margin-bottom:18px">
        <label class="cf-label">Content</label>
        <textarea name="content" class="cf-input" rows="4" required placeholder="Write your note, follow-up, or recruiter update…"></textarea>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" onclick="document.getElementById('addNoteModal').classList.remove('open')" class="btn btn-secondary">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Note</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Reminder Modal ── -->
<div class="modal-overlay" id="reminderModal">
  <div class="modal-box" style="max-width:440px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
      <h2 style="font-size:17px;font-weight:700;color:#fff">Set Reminder</h2>
      <button onclick="document.getElementById('reminderModal').classList.remove('open')" style="background:rgba(255,255,255,.07);border:none;color:var(--muted);width:28px;height:28px;border-radius:7px;cursor:pointer;font-size:18px">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="add_reminder">
      <div style="margin-bottom:12px">
        <label class="cf-label">Reminder Title</label>
        <input type="text" name="title" class="cf-input" required placeholder="e.g. Follow up with recruiter">
      </div>
      <div style="margin-bottom:12px">
        <label class="cf-label">Date &amp; Time</label>
        <input type="datetime-local" name="remind_at" class="cf-input" required>
      </div>
      <div style="margin-bottom:18px">
        <label class="cf-label">Application (optional)</label>
        <select name="application_id" class="cf-input">
          <option value="">— General reminder —</option>
          <?php foreach ($apps as $a): ?>
          <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['company']) ?> – <?= htmlspecialchars($a['job_title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" onclick="document.getElementById('reminderModal').classList.remove('open')" class="btn btn-secondary">Cancel</button>
        <button type="submit" class="btn btn-primary">Set Reminder</button>
      </div>
    </form>
  </div>
</div>

<script>
function editNote(id) {
  document.querySelector('.note-view-' + id).style.display = 'none';
  document.querySelector('.note-edit-' + id).style.display = 'block';
}
function cancelEdit(id) {
  document.querySelector('.note-view-' + id).style.display = 'block';
  document.querySelector('.note-edit-' + id).style.display = 'none';
}
</script>

</main>
</body>
</html>
