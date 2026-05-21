<?php
require_once '../components/layout.php';
require_once '../includes/ai.php';
cf_layout_head('Cover Letters');
cf_layout_sidebar('cover_letter');

<style>
@media (max-width: 768px) {
  /* Force all modal form grids to single column */
  .modal-box .rg-2,
  .modal-box [style*="grid-template-columns:1fr 1fr"],
  .modal-box [style*="grid-template-columns: 1fr 1fr"],
  .modal-box [style*="grid-template-columns:1fr 2fr"],
  .modal-box [style*="grid-template-columns:2fr 1fr"] {
    grid-template-columns: 1fr !important;
  }
  .modal-box [style*="grid-column:1/-1"] {
    grid-column: 1 !important;
  }
  /* Inputs inside modal — prevent iOS zoom */
  .modal-box input, .modal-box select, .modal-box textarea {
    font-size: 16px !important;
  }
  /* Modal action buttons — full width stack */
  .modal-box [style*="justify-content:flex-end"][style*="display:flex"],
  .modal-box [style*="justify-content: flex-end"][style*="display:flex"] {
    flex-direction: column !important;
    gap: 8px !important;
  }
  .modal-box [style*="justify-content:flex-end"] .btn,
  .modal-box [style*="justify-content: flex-end"] .btn {
    width: 100% !important;
    justify-content: center !important;
  }
}
</style>


$uid    = (int)$user['id'];
$dbUser = DB::one('SELECT * FROM users WHERE id=?', [$uid]);
$msg    = ''; $msgType = '';

/* ── POST handlers ──────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) die('CSRF error');
    $action = $_POST['_action'] ?? '';

    /* Generate with AI */
    if ($action === 'generate') {
        if (empty($dbUser['ai_api_key'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No AI API key configured. Go to Settings → AI Settings.']);
            exit;
        }
        $company    = trim($_POST['company']         ?? '');
        $jobTitle   = trim($_POST['job_title']       ?? '');
        $jobDesc    = trim($_POST['job_description'] ?? '');
        $tone       = $_POST['tone']                 ?? 'professional';
        $extraNotes = trim($_POST['extra_notes']     ?? '');
        $appId      = (int)($_POST['application_id'] ?? 0) ?: null;

        if (!$company || !$jobTitle) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Company and Job Title are required.']);
            exit;
        }

        /* If linked to an application, pull its job description */
        if ($appId && !$jobDesc) {
            $appRow  = DB::one('SELECT * FROM applications WHERE id=? AND user_id=?', [$appId, $uid]);
            $jobDesc = $appRow['job_description'] ?? '';
        }

        try {
            $profile = [
                'id'             => $uid,
                'name'           => $dbUser['full_name'] ?: $dbUser['name'],
                'skills_summary' => $dbUser['skills_summary'] ?? '',
                'years_exp'      => $dbUser['years_exp']      ?? 0,
                'linkedin_url'   => $dbUser['linkedin_url']   ?? '',
            ];
            $body = AI::generateCoverLetter($profile, $company, $jobTitle, $jobDesc, $tone, $extraNotes);

            /* Auto-save */
            $title   = "$jobTitle at $company";
            $version = (int)(DB::one("SELECT COALESCE(MAX(version),0) AS v FROM cover_letters WHERE user_id=? AND company=? AND job_title=?", [$uid,$company,$jobTitle])['v'] ?? 0) + 1;
            DB::run("INSERT INTO cover_letters (user_id,application_id,title,company,job_title,tone,body,ai_generated,version) VALUES (?,?,?,?,?,?,?,1,?)",
                [$uid,$appId,$title,$company,$jobTitle,$tone,$body,$version]);
            $newId = (int)DB::lastId();
            log_activity($uid,'generate_cover_letter',"Generated cover letter: $title",$appId);

            header('Content-Type: application/json');
            echo json_encode(['body' => $body, 'id' => $newId, 'title' => $title]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /* Save / update */
    if ($action === 'save') {
        $id    = (int)($_POST['id']    ?? 0);
        $title = trim($_POST['title']  ?? '');
        $body  = trim($_POST['body']   ?? '');
        $appId = (int)($_POST['application_id'] ?? 0) ?: null;
        if ($id > 0) {
            DB::run('UPDATE cover_letters SET title=?, body=?, application_id=?, updated_at=NOW() WHERE id=? AND user_id=?',
                [$title,$body,$appId,$id,$uid]);
            $msg = 'Cover letter saved!'; $msgType = 'success';
        }
    }

    /* Toggle favourite */
    if ($action === 'favourite') {
        $id  = (int)($_POST['id'] ?? 0);
        $cur = DB::one('SELECT is_favourite FROM cover_letters WHERE id=? AND user_id=?', [$id,$uid]);
        $new = $cur ? ($cur['is_favourite'] ? 0 : 1) : 0;
        DB::run('UPDATE cover_letters SET is_favourite=? WHERE id=? AND user_id=?', [$new,$id,$uid]);
        header('Content-Type: application/json');
        echo json_encode(['ok'=>true,'fav'=>$new]);
        exit;
    }

    /* Delete */
    if ($action === 'delete') {
        DB::run('DELETE FROM cover_letters WHERE id=? AND user_id=?', [(int)$_POST['id'],$uid]);
        $msg = 'Cover letter deleted.'; $msgType = 'success';
    }

    /* Duplicate */
    if ($action === 'duplicate') {
        $id  = (int)($_POST['id'] ?? 0);
        $src = DB::one('SELECT * FROM cover_letters WHERE id=? AND user_id=?', [$id,$uid]);
        if ($src) {
            $ver = (int)(DB::one("SELECT COALESCE(MAX(version),0) AS v FROM cover_letters WHERE user_id=? AND company=? AND job_title=?",
                [$uid,$src['company'],$src['job_title']])['v'] ?? 0) + 1;
            DB::run("INSERT INTO cover_letters (user_id,application_id,title,company,job_title,tone,body,ai_generated,version,is_favourite) VALUES (?,?,?,?,?,?,?,?,?,0)",
                [$uid,$src['application_id'],$src['title'].' (copy)',$src['company'],$src['job_title'],$src['tone'],$src['body'],$src['ai_generated'],$ver]);
            $msg = 'Duplicated!'; $msgType = 'success';
        }
    }
}

/* ── Fetch cover letters ─────────────────────────────────────── */
$filter  = $_GET['filter'] ?? 'all';
$q       = trim($_GET['q'] ?? '');
$where   = ['cl.user_id=?']; $params = [$uid];
if ($filter === 'fav')     $where[] = 'cl.is_favourite=1';
if ($filter === 'ai')      $where[] = 'cl.ai_generated=1';
if ($q)                    { $where[] = '(cl.title LIKE ? OR cl.company LIKE ? OR cl.job_title LIKE ?)'; $p="%$q%"; $params[]=$p;$params[]=$p;$params[]=$p; }

$letters = DB::all("SELECT cl.*, a.company AS app_company FROM cover_letters cl
    LEFT JOIN applications a ON cl.application_id=a.id
    WHERE ".implode(' AND ',$where)."
    ORDER BY cl.is_favourite DESC, cl.updated_at DESC", $params);

$apps   = DB::all("SELECT id,company,job_title FROM applications WHERE user_id=? ORDER BY company", [$uid]);
$tones  = ['professional'=>'Professional','conversational'=>'Conversational','bold'=>'Bold & Confident','creative'=>'Creative'];
$toneColors = ['professional'=>['#60a5fa','rgba(96,165,250,.12)'],'conversational'=>['#4ECDC4','rgba(78,205,196,.12)'],'bold'=>['#f87171','rgba(248,113,113,.12)'],'creative'=>['#a78bfa','rgba(167,139,250,.12)']];
$counts = DB::one("SELECT COUNT(*) AS total, SUM(is_favourite) AS fav, SUM(ai_generated) AS ai FROM cover_letters WHERE user_id=?", [$uid]);

/* Selected letter for editor */
$editId  = (int)($_GET['edit'] ?? 0);
$editCL  = $editId ? DB::one('SELECT * FROM cover_letters WHERE id=? AND user_id=?', [$editId,$uid]) : null;

$hasAI = !empty($dbUser['ai_api_key']);
$hasProfile = !empty($dbUser['skills_summary']) || !empty($dbUser['job_title_pref']);
?>
<?php if ($msg): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast(<?= json_encode($msg) ?>,'<?= $msgType ?>'));</script>
<?php endif; ?>

<style>
.cl-card { background:var(--card);border:1px solid var(--border);border-radius:14px;padding:20px;transition:all .2s;display:flex;flex-direction:column;gap:12px; }
.cl-card:hover { border-color:rgba(108,99,255,.35);box-shadow:0 6px 24px rgba(0,0,0,.25); }
.tone-badge { display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px; }
.editor-area { font-family:'DM Sans',sans-serif;font-size:13px;line-height:1.75;color:var(--text);background:rgba(0,0,0,.2);border:1px solid var(--border);border-radius:10px;padding:18px;width:100%;resize:vertical;min-height:420px;outline:none;transition:border-color .2s; }
.editor-area:focus { border-color:var(--accent); }
.tone-btn { padding:8px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--border);background:transparent;color:var(--muted);transition:all .18s;font-family:'DM Sans',sans-serif; }
.tone-btn:hover,.tone-btn.active { border-color:var(--accent);background:rgba(108,99,255,.14);color:#fff; }
.word-count { font-size:11px;color:var(--muted);text-align:right;margin-top:4px; }
</style>

<div style="max-width:1280px">

  <!-- ── Header ── -->
  <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:22px">
    <div>
      <h1 style="font-size:22px;font-weight:700;color:#fff">Cover Letters</h1>
      <p style="color:var(--muted);font-size:13px;margin-top:2px"><?= (int)($counts['total']??0) ?> letter<?= ($counts['total']??0)!=1?'s':'' ?> saved · <?= (int)($counts['ai']??0) ?> AI-generated</p>
    </div>
    <button onclick="openGenerator()" class="btn btn-primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>
      Generate with AI
    </button>
  </div>

  <!-- Profile warning -->
  <?php if (!$hasProfile): ?>
  <div style="background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2);border-radius:12px;padding:14px 18px;margin-bottom:18px;display:flex;align-items:center;gap:12px">
    <span style="font-size:20px">💡</span>
    <div>
      <strong style="color:#fff;font-size:13px">Complete your profile for better AI cover letters</strong>
      <p style="font-size:12px;color:var(--muted);margin-top:2px">Add your skills, experience, and target job title in <a href="settings.php#profile" style="color:var(--accent)">Settings → Profile</a> so the AI can personalize your letters.</p>
    </div>
  </div>
  <?php endif; ?>

  <div class="rg-sidebar">

    <!-- ── Left panel ── -->
    <div>
      <!-- Stats -->
      <div class="card" style="padding:14px 16px;margin-bottom:12px">
        <?php foreach([['All Letters',$counts['total']??0,'all','#6C63FF'],['Favourites',$counts['fav']??0,'fav','#fbbf24'],['AI Generated',$counts['ai']??0,'ai','#4ECDC4']] as [$l,$v,$f,$c]): ?>
        <a href="?filter=<?= $f ?>" style="display:flex;align-items:center;justify-content:space-between;padding:7px 8px;border-radius:8px;text-decoration:none;color:<?= $filter===$f?'#fff':'var(--muted)' ?>;background:<?= $filter===$f?'rgba(108,99,255,.15)':'transparent' ?>;font-size:13px;font-weight:<?= $filter===$f?'600':'400' ?>;margin-bottom:2px">
          <?= $l ?><span style="font-size:11px;background:rgba(255,255,255,.07);padding:1px 7px;border-radius:20px;color:<?= $c ?>;font-weight:700"><?= (int)$v ?></span>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Search -->
      <form method="GET" style="margin-bottom:12px;display:flex;gap:6px">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search…" class="cf-input" style="font-size:13px;padding:8px 10px">
        <button type="submit" class="btn btn-secondary btn-sm">Go</button>
      </form>

      <!-- Letter list (sidebar when editing, grid otherwise) -->
      <?php if ($editCL): ?>
      <div style="display:flex;flex-direction:column;gap:8px;max-height:70vh;overflow-y:auto">
        <?php foreach($letters as $cl): ?>
        <a href="?edit=<?= $cl['id'] ?>" style="display:block;padding:12px 14px;border-radius:10px;border:1px solid <?= $cl['id']===$editId?'var(--accent)':'var(--border)' ?>;background:<?= $cl['id']===$editId?'rgba(108,99,255,.12)':'var(--card)' ?>;text-decoration:none;transition:all .18s">
          <div style="font-size:12px;font-weight:600;color:#fff;margin-bottom:2px"><?= htmlspecialchars(mb_substr($cl['title'],0,34)) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($cl['company']) ?> <?= $cl['is_favourite']?'⭐':'' ?></div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── Main content ── -->
    <?php if ($editCL): ?>
    <!-- ═══ EDITOR VIEW ═══ -->
    <div>
      <form method="POST" id="editorForm">
        <input type="hidden" name="_csrf"    value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="save">
        <input type="hidden" name="id"      value="<?= $editCL['id'] ?>">
        <input type="hidden" name="application_id" value="<?= $editCL['application_id'] ?? '' ?>">

        <div class="card" style="padding:22px;margin-bottom:14px">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
            <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0">
              <a href="cover_letters.php" style="color:var(--muted);text-decoration:none;font-size:20px;line-height:1">←</a>
              <input type="text" name="title" class="cf-input" value="<?= htmlspecialchars($editCL['title']) ?>" style="font-size:16px;font-weight:700;background:transparent;border-color:transparent;padding:4px 8px;flex:1" placeholder="Cover Letter Title">
            </div>
            <div style="display:flex;gap:8px">
              <button type="button" onclick="copyToClipboard()" class="btn btn-secondary btn-sm">📋 Copy</button>
              <button type="button" onclick="downloadTxt()" class="btn btn-secondary btn-sm">⬇️ Download</button>
              <button type="submit" class="btn btn-primary btn-sm">💾 Save</button>
            </div>
          </div>

          <!-- Meta row -->
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border)">
            <span style="font-size:12px;color:var(--muted)">📌 <?= htmlspecialchars($editCL['company']) ?> · <?= htmlspecialchars($editCL['job_title']) ?></span>
            <?php $tc=$toneColors[$editCL['tone']]??$toneColors['professional']; ?>
            <span class="tone-badge" style="background:<?= $tc[1] ?>;color:<?= $tc[0] ?>"><?= ucfirst($editCL['tone']) ?></span>
            <?php if ($editCL['ai_generated']): ?><span style="font-size:10px;padding:2px 7px;border-radius:20px;background:rgba(108,99,255,.15);color:#a78bfa;font-weight:700">✨ AI</span><?php endif; ?>
            <span style="font-size:11px;color:var(--muted);margin-left:auto">v<?= $editCL['version'] ?> · <?= date('M j, Y',strtotime($editCL['updated_at'])) ?></span>
          </div>

          <!-- Editor -->
          <textarea name="body" class="editor-area" id="editorBody" oninput="updateWordCount(this)"><?= htmlspecialchars($editCL['body']) ?></textarea>
          <div class="word-count" id="wordCountDisplay">— words</div>
        </div>

        <!-- AI Regenerate -->
        <?php if ($hasAI): ?>
        <div class="card" style="padding:18px">
          <h3 style="font-size:13px;font-weight:700;color:#fff;margin-bottom:12px">✨ Regenerate with AI</h3>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px" id="retoneGroup">
            <?php foreach($tones as $tKey=>$tLabel): ?>
            <button type="button" onclick="setTone('<?= $tKey ?>')" class="tone-btn <?= $editCL['tone']===$tKey?'active':'' ?>" data-tone="<?= $tKey ?>"><?= $tLabel ?></button>
            <?php endforeach; ?>
          </div>
          <div style="display:flex;gap:8px;align-items:center">
            <input type="text" id="regenNotes" class="cf-input" placeholder="Extra instructions (optional)…" style="flex:1;font-size:13px;padding:8px 12px">
            <button type="button" onclick="regenerate(<?= $editCL['id'] ?>)" class="btn btn-primary btn-sm" id="regenBtn">✨ Regenerate</button>
          </div>
        </div>
        <?php endif; ?>
      </form>
    </div>

    <?php else: ?>
    <!-- ═══ GRID VIEW ═══ -->
    <div>
      <?php if (empty($letters)): ?>
      <div class="card" style="padding:60px;text-align:center">
        <div style="font-size:48px;margin-bottom:14px">✍️</div>
        <h3 style="color:#fff;font-size:18px;font-weight:700;margin-bottom:8px">No cover letters yet</h3>
        <p style="color:var(--muted);font-size:14px;margin-bottom:22px">Use AI to generate a tailored cover letter in seconds.</p>
        <button onclick="openGenerator()" class="btn btn-primary">Generate Your First Cover Letter</button>
      </div>
      <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px">
        <?php foreach($letters as $cl):
          $tc = $toneColors[$cl['tone']] ?? $toneColors['professional'];
          $preview = mb_substr(strip_tags($cl['body']),0,160);
        ?>
        <div class="cl-card">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px">
            <div style="flex:1;min-width:0">
              <div style="font-size:14px;font-weight:700;color:#fff;margin-bottom:3px;line-height:1.3"><?= htmlspecialchars($cl['title']) ?></div>
              <div style="font-size:12px;color:var(--accent2)"><?= htmlspecialchars($cl['company']) ?> · <?= htmlspecialchars($cl['job_title']) ?></div>
            </div>
            <button onclick="toggleFav(<?= $cl['id'] ?>,this)" style="background:none;border:none;cursor:pointer;font-size:18px;flex-shrink:0;line-height:1" title="Favourite">
              <?= $cl['is_favourite'] ? '⭐' : '☆' ?>
            </button>
          </div>

          <p style="font-size:12px;color:var(--muted);line-height:1.6;flex:1"><?= htmlspecialchars($preview) ?>…</p>

          <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
            <span class="tone-badge" style="background:<?= $tc[1] ?>;color:<?= $tc[0] ?>"><?= ucfirst($cl['tone']) ?></span>
            <?php if ($cl['ai_generated']): ?><span style="font-size:10px;padding:2px 7px;border-radius:20px;background:rgba(108,99,255,.15);color:#a78bfa;font-weight:700">✨ AI</span><?php endif; ?>
            <span style="font-size:10px;color:var(--muted);margin-left:auto">v<?= $cl['version'] ?> · <?= date('M j',strtotime($cl['updated_at'])) ?></span>
          </div>

          <div style="display:flex;gap:6px;padding-top:8px;border-top:1px solid rgba(255,255,255,.06)">
            <a href="?edit=<?= $cl['id'] ?>" class="btn btn-secondary btn-sm" style="flex:1;justify-content:center">✏️ Edit</a>
            <form method="POST" style="display:contents">
              <input type="hidden" name="_csrf"    value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="duplicate">
              <input type="hidden" name="id"      value="<?= $cl['id'] ?>">
              <button type="submit" class="btn btn-secondary btn-sm" title="Duplicate">⧉</button>
            </form>
            <form method="POST" style="display:contents" onsubmit="return confirm('Delete this cover letter?')">
              <input type="hidden" name="_csrf"    value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id"      value="<?= $cl['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" title="Delete">🗑</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ══ Generator Modal ══════════════════════════════════════════ -->
<div class="modal-overlay" id="genModal">
  <div class="modal-box" style="max-width:640px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
      <div>
        <h2 style="font-size:18px;font-weight:700;color:#fff">✨ Generate Cover Letter</h2>
        <p style="font-size:12px;color:var(--muted);margin-top:2px">AI will write a tailored letter using your profile + job details</p>
      </div>
      <button onclick="closeModal('genModal')" style="background:rgba(255,255,255,.07);border:none;color:var(--muted);width:30px;height:30px;border-radius:7px;cursor:pointer;font-size:18px">×</button>
    </div>

    <?php if (!$hasAI): ?>
    <div style="background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.25);border-radius:10px;padding:14px 16px;margin-bottom:18px;font-size:13px;color:#f87171">
      ⚠️ No AI API key configured. <a href="settings.php#ai" style="color:#f87171;font-weight:700">Add your free OpenRouter key →</a>
    </div>
    <?php endif; ?>

    <div style="display:grid;gap:13px" id="genForm">
      <!-- Quick-fill from application -->
      <?php if (!empty($apps)): ?>
      <div>
        <label class="cf-label">Quick-fill from Application <span style="color:var(--muted);font-size:10px;text-transform:none">(optional)</span></label>
        <select id="quickFillApp" onchange="quickFill(this.value)" class="cf-input">
          <option value="">— Select to auto-fill fields —</option>
          <?php foreach($apps as $a): ?>
          <option value="<?= $a['id'] ?>" data-company="<?= htmlspecialchars($a['company']) ?>" data-title="<?= htmlspecialchars($a['job_title']) ?>"><?= htmlspecialchars($a['company']) ?> – <?= htmlspecialchars($a['job_title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <div class="rg-2">
        <div>
          <label class="cf-label">Company Name *</label>
          <input type="text" id="genCompany" class="cf-input" placeholder="Google, Meta, Airbnb…">
        </div>
        <div>
          <label class="cf-label">Job Title *</label>
          <input type="text" id="genJobTitle" class="cf-input" placeholder="Senior Engineer…">
        </div>
      </div>

      <div>
        <label class="cf-label">Job Description <span style="color:var(--muted);font-size:10px;text-transform:none">(paste from job posting for best results)</span></label>
        <textarea id="genJobDesc" class="cf-input" rows="4" placeholder="We are looking for a…"></textarea>
      </div>

      <div>
        <label class="cf-label">Tone</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap" id="genToneGroup">
          <?php foreach($tones as $tKey=>$tLabel): ?>
          <button type="button" onclick="setGenTone('<?= $tKey ?>')" class="tone-btn <?= $tKey==='professional'?'active':'' ?>" data-tone="<?= $tKey ?>"><?= $tLabel ?></button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" id="genTone" value="professional">
      </div>

      <div>
        <label class="cf-label">Extra Instructions <span style="color:var(--muted);font-size:10px;text-transform:none">(optional)</span></label>
        <input type="text" id="genExtra" class="cf-input" placeholder="Mention my remote work experience, keep it under 300 words…">
      </div>
      <input type="hidden" id="genAppId" value="">
    </div>

    <!-- Result area (hidden until generated) -->
    <div id="genResult" style="display:none;margin-top:16px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
        <label class="cf-label" style="margin:0">Generated Cover Letter</label>
        <div style="display:flex;gap:6px">
          <button onclick="copyGenResult()" class="btn btn-secondary btn-sm">📋 Copy</button>
          <a id="editGenBtn" href="#" class="btn btn-primary btn-sm">✏️ Open Editor →</a>
        </div>
      </div>
      <textarea id="genResultBody" class="editor-area" style="min-height:320px" readonly></textarea>
      <div class="word-count" id="genWordCount"></div>
    </div>

    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
      <button type="button" onclick="closeModal('genModal')" class="btn btn-secondary">Close</button>
      <button type="button" onclick="generateCL()" class="btn btn-primary" id="generateBtn" <?= !$hasAI?'disabled':'' ?>>
        ✨ Generate Letter
      </button>
    </div>
  </div>
</div>

<!-- Loading overlay -->
<div id="loadingOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,.75);backdrop-filter:blur(8px);z-index:9999;align-items:center;justify-content:center;flex-direction:column;gap:16px;display:none">
  <div style="width:48px;height:48px;border-radius:50%;border:3px solid rgba(108,99,255,.3);border-top-color:var(--accent);animation:spin 1s linear infinite"></div>
  <p style="color:#fff;font-size:15px;font-weight:600">✨ AI is writing your cover letter…</p>
  <p style="color:var(--muted);font-size:13px">This takes 10–30 seconds</p>
  <style>@keyframes spin{to{transform:rotate(360deg)}}</style>
</div>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
let genResultId = null;

function openGenerator() {
  openModal('genModal');
  document.getElementById('genResult').style.display = 'none';
}

// Quick-fill from application
function quickFill(appId) {
  const sel = document.querySelector(`#quickFillApp option[value="${appId}"]`);
  if (!sel || !appId) return;
  document.getElementById('genCompany').value  = sel.dataset.company  || '';
  document.getElementById('genJobTitle').value = sel.dataset.title    || '';
  document.getElementById('genAppId').value    = appId;
}

// Tone selectors
function setGenTone(t) {
  document.querySelectorAll('#genToneGroup .tone-btn').forEach(b=>b.classList.toggle('active',b.dataset.tone===t));
  document.getElementById('genTone').value = t;
}
function setTone(t) {
  document.querySelectorAll('#retoneGroup .tone-btn').forEach(b=>b.classList.toggle('active',b.dataset.tone===t));
  document.getElementById('currentTone').value = t;
}

// Generate
async function generateCL() {
  const company  = document.getElementById('genCompany').value.trim();
  const jobTitle = document.getElementById('genJobTitle').value.trim();
  const jobDesc  = document.getElementById('genJobDesc').value.trim();
  const tone     = document.getElementById('genTone').value;
  const extra    = document.getElementById('genExtra').value.trim();
  const appId    = document.getElementById('genAppId').value;

  if (!company || !jobTitle) { showToast('Company and Job Title are required.','error'); return; }

  const overlay = document.getElementById('loadingOverlay');
  overlay.style.display = 'flex';
  document.getElementById('generateBtn').disabled = true;

  const fd = new FormData();
  fd.append('_csrf',        CSRF);
  fd.append('_action',      'generate');
  fd.append('company',      company);
  fd.append('job_title',    jobTitle);
  fd.append('job_description', jobDesc);
  fd.append('tone',         tone);
  fd.append('extra_notes',  extra);
  fd.append('application_id', appId);

  try {
    const r = await fetch('cover_letters.php', { method:'POST', body:fd });
    const j = await r.json();
    overlay.style.display = 'none';
    document.getElementById('generateBtn').disabled = false;

    if (j.error) { showToast(j.error,'error'); return; }

    // Show result
    genResultId = j.id;
    document.getElementById('genResultBody').value = j.body;
    document.getElementById('genResultBody').removeAttribute('readonly');
    document.getElementById('genResult').style.display = 'block';
    document.getElementById('editGenBtn').href = 'cover_letters.php?edit=' + j.id;
    updateWordCount(document.getElementById('genResultBody'), 'genWordCount');
    showToast('Cover letter generated!','success');
  } catch(e) {
    overlay.style.display = 'none';
    document.getElementById('generateBtn').disabled = false;
    showToast('Network error: ' + e.message,'error');
  }
}

// Regenerate (editor view)
async function regenerate(id) {
  const btn   = document.getElementById('regenBtn');
  const tone  = document.querySelector('#retoneGroup .tone-btn.active')?.dataset.tone || 'professional';
  const extra = document.getElementById('regenNotes').value.trim();

  btn.disabled = true; btn.textContent = '✨ Generating…';
  document.getElementById('loadingOverlay').style.display = 'flex';

  const fd = new FormData();
  fd.append('_csrf',        CSRF);
  fd.append('_action',      'generate');
  fd.append('company',      <?= json_encode($editCL['company'] ?? '') ?>);
  fd.append('job_title',    <?= json_encode($editCL['job_title'] ?? '') ?>);
  fd.append('tone',         tone);
  fd.append('extra_notes',  extra);
  fd.append('application_id', <?= json_encode($editCL['application_id'] ?? '') ?>);

  try {
    const r = await fetch('cover_letters.php', { method:'POST', body:fd });
    const j = await r.json();
    document.getElementById('loadingOverlay').style.display = 'none';
    btn.disabled = false; btn.textContent = '✨ Regenerate';
    if (j.error) { showToast(j.error,'error'); return; }
    document.getElementById('editorBody').value = j.body;
    updateWordCount(document.getElementById('editorBody'));
    showToast('Regenerated! Save when happy.','success');
  } catch(e) {
    document.getElementById('loadingOverlay').style.display = 'none';
    btn.disabled = false; btn.textContent = '✨ Regenerate';
    showToast('Error: ' + e.message,'error');
  }
}

// Toggle favourite
async function toggleFav(id, btn) {
  const fd = new FormData();
  fd.append('_csrf','<?= csrf_token() ?>'); fd.append('_action','favourite'); fd.append('id',id);
  const r = await fetch('cover_letters.php',{method:'POST',body:fd});
  const j = await r.json();
  if (j.ok) { btn.textContent = j.fav ? '⭐' : '☆'; showToast(j.fav?'Added to favourites':'Removed from favourites','info'); }
}

// Copy to clipboard
function copyToClipboard() {
  const ta = document.getElementById('editorBody');
  ta.select(); document.execCommand('copy');
  showToast('Copied to clipboard!','success');
}
function copyGenResult() {
  const ta = document.getElementById('genResultBody');
  ta.select(); document.execCommand('copy');
  showToast('Copied!','success');
}

// Download as .txt
function downloadTxt() {
  const body  = document.getElementById('editorBody').value;
  const title = document.querySelector('[name=title]').value || 'cover-letter';
  const blob  = new Blob([body], {type:'text/plain'});
  const a     = document.createElement('a');
  a.href      = URL.createObjectURL(blob);
  a.download  = title.toLowerCase().replace(/\s+/g,'-') + '.txt';
  a.click();
}

// Word count
function updateWordCount(el, counterId) {
  const words = el.value.trim().split(/\s+/).filter(w=>w).length;
  const id    = counterId || 'wordCountDisplay';
  const el2   = document.getElementById(id);
  if (el2) el2.textContent = words + ' words';
}

// Init word count for editor view
<?php if ($editCL): ?>
document.addEventListener('DOMContentLoaded', () => {
  updateWordCount(document.getElementById('editorBody'));
});
<?php endif; ?>

// Ctrl+S save in editor
document.addEventListener('keydown', e => {
  if ((e.ctrlKey||e.metaKey) && e.key==='s') {
    e.preventDefault();
    const f = document.getElementById('editorForm');
    if (f) f.submit();
  }
});
</script>

</main>
</body>
</html>
