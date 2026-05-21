<?php
require_once '../components/layout.php';

$uid = (int)$user['id'];
$msg = ''; $msgType = '';
$dbUser = DB::one('SELECT * FROM users WHERE id=?', [$uid]);

// ── Handle POST actions ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { die('CSRF error'); }
    $action = $_POST['_action'] ?? '';

    if ($action === 'save') {
        $id      = (int)($_POST['id'] ?? 0);
        $company = trim($_POST['company'] ?? '');
        $title   = trim($_POST['job_title'] ?? '');

        if ($company && $title) {
            $fields = [
                'company' => $company, 'job_title' => $title,
                'location' => trim($_POST['location'] ?? ''), 'job_type' => $_POST['job_type'] ?? 'Full-time',
                'status' => $_POST['status'] ?? 'Applied', 'applied_date' => $_POST['applied_date'] ?: null,
                'salary_min' => $_POST['salary_min'] ?: null, 'salary_max' => $_POST['salary_max'] ?: null,
                'salary_currency' => $dbUser['currency'] ?? 'USD',
                'recruiter_name' => trim($_POST['recruiter_name'] ?? ''), 'recruiter_email' => trim($_POST['recruiter_email'] ?? ''),
                'job_url' => trim($_POST['job_url'] ?? ''), 'job_description' => trim($_POST['job_description'] ?? ''),
                'notes' => trim($_POST['notes'] ?? ''),
            ];
            if ($id > 0) {
                // update
                $set = implode(', ', array_map(fn($k) => "$k=?", array_keys($fields)));
                DB::run("UPDATE applications SET $set, updated_at=NOW() WHERE id=? AND user_id=?",
                    [...array_values($fields), $id, $uid]);
                log_activity($uid, 'update_application', "Updated {$company} – {$title}", $id);
                $msg = 'Application updated.'; $msgType = 'success';
            } else {
                DB::run("INSERT INTO applications (" . implode(',', array_keys($fields)) . ", user_id) VALUES (" . implode(',', array_fill(0, count($fields), '?')) . ",?)",
                    [...array_values($fields), $uid]);
                $newId = (int)DB::lastId();
                log_activity($uid, 'add_application', "Added {$company} – {$title}", $newId);
                $msg = 'Application added!'; $msgType = 'success';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $app = DB::one('SELECT * FROM applications WHERE id=? AND user_id=?', [$id, $uid]);
        if ($app) {
            DB::run('DELETE FROM applications WHERE id=? AND user_id=?', [$id, $uid]);
            log_activity($uid, 'delete_application', "Deleted {$app['company']} – {$app['job_title']}");
            $msg = 'Application deleted.'; $msgType = 'success';
        }
    }

    if ($action === 'status') {
        $id  = (int)($_POST['id'] ?? 0);
        $st  = $_POST['status'] ?? '';
        $valid = ['Wishlist','Applied','Screening','Assessment','Interview','Final Interview','Offer','Rejected','Hired'];
        if (in_array($st, $valid)) {
            DB::run('UPDATE applications SET status=? WHERE id=? AND user_id=?', [$st, $id, $uid]);
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
    }
}

// ── Fetch applications ────────────────────────
$q    = trim($_GET['q'] ?? '');
$fSt  = $_GET['status'] ?? '';
$fTyp = $_GET['type']   ?? '';
$sort = $_GET['sort']   ?? 'created_at';
$dir  = ($_GET['dir']   ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$where = ['user_id=?']; $params = [$uid];
if ($q)    { $where[] = '(company LIKE ? OR job_title LIKE ? OR location LIKE ?)'; $p = "%$q%"; $params[] = $p; $params[] = $p; $params[] = $p; }
if ($fSt)  { $where[] = 'status=?'; $params[] = $fSt; }
if ($fTyp) { $where[] = 'job_type=?'; $params[] = $fTyp; }

$sortCols = ['company','job_title','status','applied_date','salary_min','created_at'];
if (!in_array($sort, $sortCols)) $sort = 'created_at';

$sql = 'SELECT * FROM applications WHERE ' . implode(' AND ', $where) . " ORDER BY $sort $dir";
$apps = DB::all($sql, $params);

// ── Resumes for dropdown ──────────────────────
$resumes = DB::all('SELECT id, label FROM resumes WHERE user_id=?', [$uid]);

$statuses = ['Wishlist','Applied','Screening','Assessment','Interview','Final Interview','Offer','Rejected','Hired'];
$types    = ['Full-time','Part-time','Contract','Freelance','Internship','Remote'];

// Edit prefill
$editApp = null;
if (isset($_GET['edit'])) {
    $editApp = DB::one('SELECT * FROM applications WHERE id=? AND user_id=?', [(int)$_GET['edit'], $uid]);
}
$openModal = isset($_GET['new']) || $editApp;

cf_layout_head('Applications');
cf_layout_sidebar('applications');

<style>
/* ── Applications modal: mobile form fix ── */
@media (max-width: 768px) {
  #appModal .modal-box { padding: 16px 14px !important; }
  #appModal .rg-2 { grid-template-columns: 1fr !important; }
  #appModal [style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
  #appModal [style*="grid-column:1/-1"] { grid-column: 1 !important; }
  #appModal textarea { min-height: 80px; }
  /* salary row side by side even on mobile */
  #appModal .salary-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
}
</style>


function statusBadge(string $s): string {
    $cls = [
        'Wishlist' => 'wishlist', 'Applied' => 'applied', 'Screening' => 'screening',
        'Assessment' => 'assessment', 'Interview' => 'interview', 'Final Interview' => 'finalinterview',
        'Offer' => 'offer', 'Rejected' => 'rejected', 'Hired' => 'hired',
    ];
    $c = $cls[$s] ?? 'applied';
    $dot = ['wishlist'=>'#94a3b8','applied'=>'#60a5fa','screening'=>'#fbbf24','assessment'=>'#a78bfa','interview'=>'#34d399','finalinterview'=>'#fb7185','offer'=>'#22c55e','rejected'=>'#f87171','hired'=>'#4ECDC4'];
    return "<span class='badge badge-$c'><span style='width:5px;height:5px;border-radius:50%;background:{$dot[$c]};display:inline-block;'></span>$s</span>";
}
?>

<?php if ($msg): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast('<?= addslashes($msg) ?>','<?= $msgType ?>'));</script>
<?php endif; ?>

<div style="max-width:1280px">
  <!-- Header -->
  <div class="flex items-center justify-between mb-6 page-header">
    <div>
      <h1 style="font-size:22px;font-weight:700;color:#fff">Applications</h1>
      <p style="color:var(--muted);font-size:13px;margin-top:2px"><?= count($apps) ?> result<?= count($apps) !== 1 ? 's' : '' ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="?export=csv" class="btn btn-secondary btn-sm">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Export CSV
      </a>
      <button onclick="openModal('appModal')" class="btn btn-primary btn-sm">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg>
        Add Application
      </button>
    </div>
  </div>

  <!-- Filters -->
  <div class="card filter-bar" style="padding:14px 18px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
    <form method="GET" style="display:contents">
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search…" class="cf-input" style="width:200px;padding:8px 12px">
      <select name="status" class="cf-input" style="width:150px;padding:8px 12px">
        <option value="">All Statuses</option>
        <?php foreach ($statuses as $s): ?>
        <option value="<?= $s ?>" <?= $fSt === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
      <select name="type" class="cf-input" style="width:140px;padding:8px 12px">
        <option value="">All Types</option>
        <?php foreach ($types as $t): ?>
        <option value="<?= $t ?>" <?= $fTyp === $t ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
      <select name="sort" class="cf-input" style="width:160px;padding:8px 12px">
        <option value="created_at" <?= $sort==='created_at' ? 'selected':'' ?>>Date Added</option>
        <option value="applied_date" <?= $sort==='applied_date' ? 'selected':'' ?>>Applied Date</option>
        <option value="company" <?= $sort==='company' ? 'selected':'' ?>>Company</option>
        <option value="status" <?= $sort==='status' ? 'selected':'' ?>>Status</option>
      </select>
      <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
      <a href="applications.php" class="btn btn-secondary btn-sm" style="color:var(--muted)">Reset</a>
    </form>
  </div>

  <!-- Table -->
  <div class="card" style="overflow:hidden">
    <div class="cf-table-wrap">
      <table class="cf-table">
        <thead>
          <tr>
            <th>Company / Role</th>
            <th>Status</th>
            <th class="hide-mobile">Type</th>
            <th class="hide-mobile">Location</th>
            <th>Applied</th>
            <th class="hide-mobile">Salary</th>
            <th class="hide-mobile">Recruiter</th>
            <th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($apps)): ?>
          <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted)">
            No applications yet. <a onclick="openModal('appModal')" href="#" style="color:var(--accent)">Add your first one →</a>
          </td></tr>
          <?php else: foreach ($apps as $a): ?>
          <tr>
            <td>
              <div style="font-weight:600;color:#fff;font-size:14px"><?= htmlspecialchars($a['company']) ?></div>
              <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($a['job_title']) ?></div>
            </td>
            <td>
              <select onchange="quickStatus(<?= $a['id'] ?>,this.value)" class="cf-input" style="padding:5px 8px;font-size:12px;width:auto;background:transparent;border-color:transparent">
                <?php foreach ($statuses as $s): ?>
                <option <?= $a['status']===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td class="hide-mobile" style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($a['job_type']) ?></td>
            <td class="hide-mobile" style="font-size:13px"><?= htmlspecialchars($a['location'] ?: '—') ?></td>
            <td style="font-size:12px;color:var(--muted)"><?= $a['applied_date'] ? date('M j, Y', strtotime($a['applied_date'])) : '—' ?></td>
            <td class="hide-mobile" style="font-size:12px">
              <?php if ($a['salary_min']): ?>
                <?= cf_currency((float)$a['salary_min'], true) ?><?= $a['salary_max'] ? '–'.cf_currency((float)$a['salary_max'],true) : '+' ?>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="hide-mobile" style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($a['recruiter_name'] ?: '—') ?></td>
            <td style="text-align:right">
              <div style="display:flex;gap:6px;justify-content:flex-end">
                <a href="?edit=<?= $a['id'] ?>" class="btn btn-secondary btn-sm" style="padding:5px 10px">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="1.8"/></svg>
                </a>
                <a href="application_detail.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm" style="padding:5px 10px">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/></svg>
                </a>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this application?')">
                  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="_action" value="delete">
                  <input type="hidden" name="id" value="<?= $a['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm" style="padding:5px 10px" onclick="cfBtnLoad(this,true)">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><polyline points="3 6 5 6 21 6" stroke="currentColor" stroke-width="1.8"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6M10 11v6M14 11v6M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── Add/Edit Modal ──────────────────────── -->
<div class="modal-overlay <?= $openModal ? 'open' : '' ?>" id="appModal">
  <div class="modal-box" style="max-width:680px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px">
      <h2 style="font-size:18px;font-weight:700;color:#fff" id="modalTitle"><?= $editApp ? 'Edit Application' : 'New Application' ?></h2>
      <button onclick="closeAppModal()" style="background:rgba(255,255,255,.07);border:none;color:var(--muted);width:30px;height:30px;border-radius:7px;cursor:pointer;font-size:18px">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="save">
      <input type="hidden" name="id" value="<?= $editApp['id'] ?? 0 ?>">

      <div class="rg-2">
        <div style="grid-column:1/-1">
          <label class="cf-label">Company Name *</label>
          <input type="text" name="company" class="cf-input" required value="<?= htmlspecialchars($editApp['company'] ?? '') ?>" placeholder="e.g. Google, Meta, Airbnb">
        </div>
        <div style="grid-column:1/-1">
          <label class="cf-label">Job Title *</label>
          <input type="text" name="job_title" class="cf-input" required value="<?= htmlspecialchars($editApp['job_title'] ?? '') ?>" placeholder="e.g. Senior Product Designer">
        </div>
        <div>
          <label class="cf-label">Status</label>
          <select name="status" class="cf-input">
            <?php foreach ($statuses as $s): ?>
            <option <?= ($editApp['status']??'Applied')===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="cf-label">Job Type</label>
          <select name="job_type" class="cf-input">
            <?php foreach ($types as $t): ?>
            <option <?= ($editApp['job_type']??'Full-time')===$t?'selected':'' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="cf-label">Location</label>
          <input type="text" name="location" class="cf-input" value="<?= htmlspecialchars($editApp['location'] ?? '') ?>" placeholder="San Francisco, CA">
        </div>
        <div>
          <label class="cf-label">Applied Date</label>
          <input type="date" name="applied_date" class="cf-input" value="<?= $editApp['applied_date'] ?? date('Y-m-d') ?>">
        </div>
        <div>
          <label class="cf-label">Min Salary</label>
          <input type="number" name="salary_min" class="cf-input" value="<?= $editApp['salary_min'] ?? '' ?>" placeholder="80000">
        </div>
        <div>
          <label class="cf-label">Max Salary</label>
          <input type="number" name="salary_max" class="cf-input" value="<?= $editApp['salary_max'] ?? '' ?>" placeholder="120000">
        </div>
        <div>
          <label class="cf-label">Recruiter Name</label>
          <input type="text" name="recruiter_name" class="cf-input" value="<?= htmlspecialchars($editApp['recruiter_name'] ?? '') ?>" placeholder="Jane Smith">
        </div>
        <div>
          <label class="cf-label">Recruiter Email</label>
          <input type="email" name="recruiter_email" class="cf-input" value="<?= htmlspecialchars($editApp['recruiter_email'] ?? '') ?>" placeholder="recruiter@company.com">
        </div>
        <div style="grid-column:1/-1">
          <label class="cf-label">Job URL</label>
          <input type="url" name="job_url" class="cf-input" value="<?= htmlspecialchars($editApp['job_url'] ?? '') ?>" placeholder="https://careers.company.com/job-id">
        </div>
        <?php if (!empty($resumes)): ?>
        <div style="grid-column:1/-1">
          <label class="cf-label">Attach Resume</label>
          <select name="resume_id" class="cf-input">
            <option value="">— None —</option>
            <?php foreach ($resumes as $r): ?>
            <option value="<?= $r['id'] ?>" <?= ($editApp['resume_id']??0)==$r['id']?'selected':'' ?>><?= htmlspecialchars($r['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div style="grid-column:1/-1">
          <label class="cf-label">Job Description</label>
          <textarea name="job_description" class="cf-input" rows="3" placeholder="Paste job description here…"><?= htmlspecialchars($editApp['job_description'] ?? '') ?></textarea>
        </div>
        <div style="grid-column:1/-1">
          <label class="cf-label">Notes</label>
          <textarea name="notes" class="cf-input" rows="2" placeholder="Your personal notes about this role…"><?= htmlspecialchars($editApp['notes'] ?? '') ?></textarea>
        </div>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:22px;padding-top:18px;border-top:1px solid var(--border)">
        <button type="button" onclick="closeAppModal()" class="btn btn-secondary">Cancel</button>
        <button type="submit" class="btn btn-primary" onclick="cfBtnLoad(this,true)"><span class="btn-label"><?= $editApp ? 'Save Changes' : 'Add Application' ?></span></button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(){ openModal('appModal'); }
function closeAppModal(){ closeModal('appModal'); history.replaceState(null,'','applications.php'); }

async function quickStatus(id, status) {
  const fd = new FormData();
  fd.append('_csrf', '<?= csrf_token() ?>');
  fd.append('_action', 'status');
  fd.append('id', id);
  fd.append('status', status);
  const r = await fetch('applications.php', { method:'POST', body:fd });
  const j = await r.json();
  if (j.ok) showToast('Status updated to ' + status, 'success');
}

// CSV export
if(location.search.includes('export=csv')) {
  // handled server-side; this is just a placeholder
}
</script>

<?php
// ── CSV export ───────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="careerflow-applications-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Company','Job Title','Status','Type','Location','Applied Date','Salary Min','Salary Max','Recruiter','Notes']);
    foreach ($apps as $a) {
        fputcsv($out, [$a['company'],$a['job_title'],$a['status'],$a['job_type'],$a['location'],$a['applied_date'],$a['salary_min'],$a['salary_max'],$a['recruiter_name'],$a['notes']]);
    }
    fclose($out);
    exit;
}
?>

</main>
</body>
</html>
