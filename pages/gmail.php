<?php
require_once '../components/layout.php';
require_once '../includes/ai.php';
require_once '../includes/gmail.php';
require_once '../includes/smtp.php';

cf_layout_head('Gmail Inbox');
cf_layout_sidebar('gmail');

$uid    = (int)$user['id'];
$dbUser = DB::one('SELECT * FROM users WHERE id=?', [$uid]);
$msg    = ''; $msgType = '';

$hasGmail = !empty($dbUser['gmail_address']) && !empty($dbUser['gmail_app_password']);
$hasAI    = !empty($dbUser['ai_api_key']);

/* ── POST handlers ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); die('CSRF'); }
    $action = $_POST['_action'] ?? '';

    /* Trigger Gmail sync */
    if ($action === 'sync') {
        if (!$hasGmail) { $msg = 'Configure Gmail credentials in Settings first.'; $msgType = 'error'; }
        elseif (!$hasAI) { $msg = 'Configure AI API key in Settings first.'; $msgType = 'error'; }
        else {
            try {
                $sync    = new GmailSync($uid);
                $results = $sync->sync((int)($_POST['days'] ?? 7), 50);
                $msg = "Sync complete: {$results['synced']} new emails, {$results['job_related']} job-related.";
                if (!empty($results['errors'])) $msg .= ' ' . count($results['errors']) . ' errors.';
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = $e->getMessage(); $msgType = 'error';
            }
        }
    }

    /* Generate AI reply */
    if ($action === 'generate_reply') {
        $emailId = (int)($_POST['email_id'] ?? 0);
        $emailRow = DB::one('SELECT e.*, a.company, a.job_title, a.applied_date FROM gmail_emails e LEFT JOIN applications a ON e.application_id=a.id WHERE e.id=? AND e.user_id=?', [$emailId, $uid]);
        if (!$emailRow) { echo json_encode(['error'=>'Email not found']); exit; }
        if (!$hasAI)    { echo json_encode(['error'=>'AI API key not configured']); exit; }

        try {
            $profile = [
                'id'   => $uid,
                'name' => $dbUser['name'],
            ];
            $appCtx = $emailRow['application_id'] ? [
                'company'      => $emailRow['company'],
                'job_title'    => $emailRow['job_title'],
                'applied_date' => $emailRow['applied_date'],
            ] : null;

            $reply = AI::generateReply(
                $emailRow['subject'],
                mb_substr($emailRow['body_plain'], 0, 2000),
                $emailRow['ai_category'] ?? 'other',
                $profile,
                $appCtx
            );
            // Save draft
            DB::run('UPDATE gmail_emails SET reply_body=? WHERE id=?', [$reply, $emailId]);
            header('Content-Type: application/json');
            echo json_encode(['reply' => $reply]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /* Send reply via Gmail SMTP */
    if ($action === 'send_reply') {
        $emailId   = (int)($_POST['email_id'] ?? 0);
        $replyBody = trim($_POST['reply_body'] ?? '');
        if (!$replyBody) { $msg = 'Reply body cannot be empty.'; $msgType = 'error'; }
        elseif (!$hasGmail) { $msg = 'Gmail not configured.'; $msgType = 'error'; }
        else {
            $emailRow = DB::one('SELECT * FROM gmail_emails WHERE id=? AND user_id=?', [$emailId, $uid]);
            if (!$emailRow) { $msg = 'Email not found.'; $msgType = 'error'; }
            else {
                $result = GmailSMTP::send(
                    $dbUser['gmail_address'],
                    $dbUser['name'],
                    $dbUser['gmail_app_password'],
                    $emailRow['sender_email'],
                    'Re: ' . $emailRow['subject'],
                    $replyBody
                );
                if ($result['ok']) {
                    DB::run('UPDATE gmail_emails SET reply_sent=1, reply_body=?, reply_sent_at=NOW() WHERE id=?',
                        [mb_substr($replyBody,0,4000), $emailId]);
                    log_activity($uid,'email_reply_sent','Replied to: '.$emailRow['subject']);
                    $msg = 'Reply sent!'; $msgType = 'success';
                } else {
                    $msg = 'Send failed: ' . $result['error']; $msgType = 'error';
                }
            }
        }
    }

    /* Mark as read */
    if ($action === 'mark_read') {
        DB::run('UPDATE gmail_emails SET is_read=1 WHERE id=? AND user_id=?', [(int)$_POST['email_id'], $uid]);
        header('Content-Type: application/json');
        echo json_encode(['ok'=>true]);
        exit;
    }

    /* Link email to application */
    if ($action === 'link_app') {
        $emailId = (int)($_POST['email_id'] ?? 0);
        $appId   = (int)($_POST['application_id'] ?? 0) ?: null;
        DB::run('UPDATE gmail_emails SET application_id=? WHERE id=? AND user_id=?', [$appId, $emailId, $uid]);
        header('Content-Type: application/json');
        echo json_encode(['ok'=>true]);
        exit;
    }

    /* Re-analyze single email with AI */
    if ($action === 'reanalyze') {
        $emailId  = (int)($_POST['email_id'] ?? 0);
        $emailRow = DB::one('SELECT * FROM gmail_emails WHERE id=? AND user_id=?', [$emailId, $uid]);
        if (!$emailRow || !$hasAI) { echo json_encode(['error'=>'Not available']); exit; }
        $apps     = DB::all("SELECT id,company,job_title FROM applications WHERE user_id=? AND status NOT IN ('Hired','Rejected')", [$uid]);
        $analysis = AI::analyzeEmail($emailRow['subject'], mb_substr($emailRow['body_plain'],0,3000), $uid, $apps);
        DB::run('UPDATE gmail_emails SET is_job_related=?, ai_category=?, ai_sentiment=?, ai_summary=? WHERE id=?',
            [$analysis['is_job_related']?1:0, $analysis['category']??'other', $analysis['sentiment']??'neutral', $analysis['summary']??'', $emailId]);
        header('Content-Type: application/json');
        echo json_encode(['ok'=>true, 'analysis'=>$analysis]);
        exit;
    }
}

/* ── Fetch emails ──────────────────────────────────────────── */
$filter   = $_GET['filter'] ?? 'all';          // all|job|unread
$category = $_GET['cat']    ?? '';
$q        = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['p'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

$where  = ['e.user_id=?']; $params = [$uid];
if ($filter === 'job')    { $where[] = 'e.is_job_related=1'; }
if ($filter === 'unread') { $where[] = 'e.is_read=0'; }
if ($category) { $where[] = 'e.ai_category=?'; $params[] = $category; }
if ($q)        { $where[] = '(e.subject LIKE ? OR e.sender_email LIKE ? OR e.ai_summary LIKE ?)'; $p="%$q%"; $params[]=$p;$params[]=$p;$params[]=$p; }

$sqlBase = 'FROM gmail_emails e LEFT JOIN applications a ON e.application_id=a.id WHERE ' . implode(' AND ', $where);
$total   = (int)(DB::one("SELECT COUNT(*) AS n $sqlBase", $params)['n']);
$emails  = DB::all("SELECT e.*, a.company AS app_company, a.job_title AS app_job_title $sqlBase ORDER BY e.received_at DESC LIMIT $perPage OFFSET $offset", $params);

/* Summary counts */
$counts = DB::one("SELECT
    COUNT(*) AS total,
    SUM(is_job_related=1) AS job_related,
    SUM(is_read=0) AS unread,
    SUM(is_job_related=1 AND is_read=0) AS job_unread
    FROM gmail_emails WHERE user_id=?", [$uid]);

$apps        = DB::all("SELECT id, company, job_title FROM applications WHERE user_id=? ORDER BY company", [$uid]);
$syncedAt    = $dbUser['gmail_sync_at'] ? date('M j, g:i A', strtotime($dbUser['gmail_sync_at'])) : 'Never';

/* Category colours */
$catColors = [
    'interview_invite' => ['bg'=>'rgba(52,211,153,.15)','c'=>'#34d399','icon'=>'📅'],
    'offer'            => ['bg'=>'rgba(34,197,94,.15)', 'c'=>'#22c55e','icon'=>'🎉'],
    'rejection'        => ['bg'=>'rgba(248,113,113,.12)','c'=>'#f87171','icon'=>'❌'],
    'screening'        => ['bg'=>'rgba(251,191,36,.12)', 'c'=>'#fbbf24','icon'=>'🔍'],
    'assessment'       => ['bg'=>'rgba(167,139,250,.12)','c'=>'#a78bfa','icon'=>'📝'],
    'follow_up'        => ['bg'=>'rgba(96,165,250,.12)', 'c'=>'#60a5fa','icon'=>'↩️'],
    'application_confirm'=>['bg'=>'rgba(78,205,196,.12)','c'=>'#4ECDC4','icon'=>'✅'],
    'recruiter_outreach'=>['bg'=>'rgba(249,115,22,.12)','c'=>'#fb923c','icon'=>'💼'],
    'other'            => ['bg'=>'rgba(148,163,184,.1)', 'c'=>'#94a3b8','icon'=>'📧'],
];
?>

<?php if ($msg): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast('<?= addslashes($msg) ?>','<?= $msgType ?>'));</script>
<?php endif; ?>

<style>
.email-row { transition: background .15s; cursor: pointer; }
.email-row:hover { background: rgba(255,255,255,.035) !important; }
.email-row.unread .email-subject { font-weight: 700; color: #fff; }
.email-row:not(.unread) .email-subject { color: rgba(255,255,255,.7); }
.reply-panel { display:none; }
.reply-panel.open { display:block; }
.cat-pill { display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px; }
.sent-il { display:inline-flex;align-items:center;gap:3px;font-size:10px;color:#4ade80;background:rgba(34,197,94,.12);padding:2px 7px;border-radius:20px; }
</style>

<div style="max-width:1280px">

  <!-- ── Header ── -->
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:22px">
    <div>
      <h1 style="font-size:22px;font-weight:700;color:#fff">Gmail Inbox</h1>
      <p style="color:var(--muted);font-size:13px;margin-top:2px">
        Last synced: <strong style="color:var(--text)"><?= $syncedAt ?></strong>
        <?php if (!$hasGmail || !$hasAI): ?>
        &nbsp;·&nbsp; <a href="settings.php#ai" style="color:var(--accent);font-size:12px">⚙️ Configure in Settings</a>
        <?php endif; ?>
      </p>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <form method="POST" style="display:flex;gap:6px;align-items:center">
        <input type="hidden" name="_csrf"   value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="sync">
        <select name="days" class="cf-input" style="width:auto;padding:7px 10px;font-size:12px">
          <option value="3">Last 3 days</option>
          <option value="7" selected>Last 7 days</option>
          <option value="14">Last 14 days</option>
          <option value="30">Last 30 days</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm" <?= (!$hasGmail||!$hasAI)?'disabled title="Configure Gmail + AI in Settings first"':'' ?>>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M23 4v6h-6M1 20v-6h6" stroke="#fff" stroke-width="2" stroke-linecap="round"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>
          Sync Gmail
        </button>
      </form>
      <a href="settings.php" class="btn btn-secondary btn-sm">⚙️ Settings</a>
    </div>
  </div>

  <?php if (!$hasGmail || !$hasAI): ?>
  <!-- Setup banner -->
  <div style="background:linear-gradient(135deg,rgba(108,99,255,.15),rgba(78,205,196,.1));border:1px solid rgba(108,99,255,.3);border-radius:14px;padding:24px;margin-bottom:22px;display:flex;align-items:center;gap:18px">
    <div style="font-size:36px">🔗</div>
    <div style="flex:1">
      <h3 style="color:#fff;font-size:16px;font-weight:700;margin-bottom:6px">Connect Gmail + AI to get started</h3>
      <p style="color:var(--muted);font-size:13px;line-height:1.6">
        CareerFlow reads your inbox via IMAP and uses AI to detect job emails, auto-update statuses, suggest smart replies, and generate cover letters.
        <?= !$hasGmail ? '<br>① Add your <strong style="color:#fff">Gmail App Password</strong> in Settings.' : '' ?>
        <?= !$hasAI    ? '<br>② Add your free <strong style="color:#fff">OpenRouter API key</strong> (openrouter.ai) in Settings.' : '' ?>
      </p>
    </div>
    <a href="settings.php" class="btn btn-primary">Open Settings →</a>
  </div>
  <?php endif; ?>

  <!-- ── Stats row ── -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px">
    <?php foreach ([
      ['Total Emails',    $counts['total']       ?? 0, '#6C63FF'],
      ['Job-Related',     $counts['job_related']  ?? 0, '#4ECDC4'],
      ['Unread',          $counts['unread']        ?? 0, '#fbbf24'],
      ['Job Unread',      $counts['job_unread']   ?? 0, '#f87171'],
    ] as [$l,$v,$c]): ?>
    <div class="stat-card" style="padding:14px 18px">
      <div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px"><?= $l ?></div>
      <div style="font-size:24px;font-weight:700;font-family:'Syne',sans-serif;color:<?= $c ?>"><?= (int)$v ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="display:grid;grid-template-columns:200px 1fr;gap:16px;align-items:start">

    <!-- ── Left filter sidebar ── -->
    <div class="card" style="padding:12px">
      <p style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;padding:4px 8px 8px">Filter</p>
      <?php
      $filters = [
        ['all',    'All Emails',     $counts['total']      ?? 0],
        ['job',    'Job-Related',    $counts['job_related'] ?? 0],
        ['unread', 'Unread',         $counts['unread']      ?? 0],
      ];
      foreach ($filters as [$fKey,$fLabel,$fCnt]): ?>
      <a href="?filter=<?= $fKey ?>" style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-radius:8px;text-decoration:none;color:<?= $filter===$fKey?'#fff':'var(--muted)' ?>;background:<?= $filter===$fKey?'rgba(108,99,255,.18)':'transparent' ?>;font-size:13px;font-weight:<?= $filter===$fKey?'600':'400' ?>;margin-bottom:2px">
        <?= $fLabel ?>
        <span style="font-size:10px;background:rgba(255,255,255,.07);padding:1px 6px;border-radius:20px"><?= $fCnt ?></span>
      </a>
      <?php endforeach; ?>
      <p style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;padding:14px 8px 6px">Categories</p>
      <?php
      $catCounts = DB::all("SELECT ai_category, COUNT(*) AS n FROM gmail_emails WHERE user_id=? AND is_job_related=1 GROUP BY ai_category ORDER BY n DESC", [$uid]);
      foreach ($catCounts as $cc):
        $cc_info = $catColors[$cc['ai_category']] ?? $catColors['other'];
      ?>
      <a href="?filter=job&cat=<?= urlencode($cc['ai_category']) ?>" style="display:flex;align-items:center;justify-content:space-between;padding:7px 10px;border-radius:8px;text-decoration:none;font-size:12px;color:<?= $category===$cc['ai_category']?'#fff':'var(--muted)' ?>;background:<?= $category===$cc['ai_category']?'rgba(108,99,255,.18)':'transparent' ?>;margin-bottom:2px">
        <span><?= $cc_info['icon'] ?> <?= str_replace('_',' ',ucfirst($cc['ai_category'])) ?></span>
        <span style="font-size:10px;background:rgba(255,255,255,.07);padding:1px 6px;border-radius:20px"><?= $cc['n'] ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- ── Email list ── -->
    <div>
      <!-- Search bar -->
      <div class="card" style="padding:10px 14px;margin-bottom:12px;display:flex;gap:8px">
        <form method="GET" style="display:flex;gap:8px;flex:1">
          <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
          <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search subject, sender, summary…" class="cf-input" style="flex:1;padding:8px 12px;font-size:13px">
          <button type="submit" class="btn btn-secondary btn-sm">Search</button>
          <?php if ($q): ?><a href="?filter=<?= $filter ?>" class="btn btn-secondary btn-sm" style="color:var(--muted)">Clear</a><?php endif; ?>
        </form>
      </div>

      <!-- Email rows -->
      <div class="card" style="overflow:hidden">
        <?php if (empty($emails)): ?>
        <div style="padding:50px;text-align:center">
          <div style="font-size:36px;margin-bottom:10px">📭</div>
          <p style="color:var(--muted);font-size:14px"><?= $hasGmail ? 'No emails synced yet. Click "Sync Gmail".' : 'Configure Gmail in Settings to get started.' ?></p>
        </div>
        <?php else: foreach ($emails as $i => $em):
          $catInfo = $catColors[$em['ai_category']] ?? $catColors['other'];
          $isUnread = !$em['is_read'];
          $isJobRel = (bool)$em['is_job_related'];
          $sentClass = $em['reply_sent'] ? 'sent-il' : '';
        ?>

        <!-- Email row -->
        <div class="email-row <?= $isUnread?'unread':'' ?>" id="email-row-<?= $em['id'] ?>"
          style="border-bottom:1px solid rgba(255,255,255,.04);padding:14px 18px;<?= $isUnread?'background:rgba(108,99,255,.04)':'' ?>">

          <!-- Row header (click to expand) -->
          <div style="display:flex;align-items:flex-start;gap:12px" onclick="toggleEmail(<?= $em['id'] ?>)" id="email-header-<?= $em['id'] ?>">

            <!-- Unread dot -->
            <div style="width:8px;height:8px;border-radius:50%;background:<?= $isUnread?'var(--accent)':'transparent' ?>;flex-shrink:0;margin-top:6px"></div>

            <!-- Sender + subject -->
            <div style="flex:1;min-width:0">
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
                <span style="font-size:13px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px"><?= htmlspecialchars($em['sender_name'] ?: $em['sender_email']) ?></span>
                <span style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($em['sender_email']) ?></span>
                <?php if ($isJobRel): ?>
                <span class="cat-pill" style="background:<?= $catInfo['bg'] ?>;color:<?= $catInfo['c'] ?>"><?= $catInfo['icon'] ?> <?= str_replace('_',' ',ucfirst($em['ai_category'])) ?></span>
                <?php endif; ?>
                <?php if ($em['reply_sent']): ?>
                <span class="sent-il">✉️ Replied</span>
                <?php endif; ?>
              </div>
              <div class="email-subject" style="font-size:13px;margin-bottom:3px"><?= htmlspecialchars($em['subject']) ?></div>
              <?php if ($em['ai_summary']): ?>
              <div style="font-size:12px;color:var(--muted);line-height:1.4"><?= htmlspecialchars(mb_substr($em['ai_summary'],0,140)) ?></div>
              <?php endif; ?>
              <?php if ($em['app_company']): ?>
              <div style="font-size:11px;color:var(--accent2);margin-top:3px">🏢 <?= htmlspecialchars($em['app_company']) ?> · <?= htmlspecialchars($em['app_job_title']) ?></div>
              <?php endif; ?>
            </div>

            <!-- Date + sentiment -->
            <div style="text-align:right;flex-shrink:0">
              <div style="font-size:11px;color:var(--muted)"><?= date('M j', strtotime($em['received_at'])) ?></div>
              <?php
              $sentColors = ['positive'=>['#34d399','rgba(52,211,153,.12)'],'negative'=>['#f87171','rgba(248,113,113,.12)'],'neutral'=>['#94a3b8','rgba(148,163,184,.1)']];
              $sc = $sentColors[$em['ai_sentiment']] ?? $sentColors['neutral'];
              ?>
              <span style="font-size:9px;padding:1px 6px;border-radius:20px;background:<?= $sc[1] ?>;color:<?= $sc[0] ?>;font-weight:700;display:inline-block;margin-top:4px"><?= ucfirst($em['ai_sentiment'] ?: 'neutral') ?></span>
            </div>
          </div>

          <!-- ── Expanded panel ── -->
          <div class="reply-panel" id="panel-<?= $em['id'] ?>" style="margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,.06)">

            <!-- Full body -->
            <div style="background:rgba(0,0,0,.2);border-radius:10px;padding:14px;margin-bottom:14px;max-height:220px;overflow-y:auto">
              <pre style="font-size:12px;color:var(--muted);white-space:pre-wrap;font-family:'DM Sans',sans-serif;line-height:1.6;margin:0"><?= htmlspecialchars(mb_substr($em['body_plain'] ?: '(no body)', 0, 3000)) ?></pre>
            </div>

            <!-- Actions row -->
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;align-items:center">
              <?php if ($hasAI): ?>
              <button onclick="generateReply(<?= $em['id'] ?>)" class="btn btn-primary btn-sm" id="gen-btn-<?= $em['id'] ?>">
                ✨ Generate AI Reply
              </button>
              <?php endif; ?>
              <button onclick="showReplyCompose(<?= $em['id'] ?>)" class="btn btn-secondary btn-sm">
                ✉️ Write Reply
              </button>
              <?php if ($hasAI): ?>
              <button onclick="reanalyze(<?= $em['id'] ?>)" class="btn btn-secondary btn-sm" title="Re-run AI analysis">
                🔄 Re-analyze
              </button>
              <?php endif; ?>
              <!-- Link to application -->
              <div style="display:flex;align-items:center;gap:6px;margin-left:auto">
                <select id="link-app-<?= $em['id'] ?>" class="cf-input" style="padding:5px 8px;font-size:11px;width:auto" onchange="linkApp(<?= $em['id'] ?>,this.value)">
                  <option value="">Link to app…</option>
                  <?php foreach ($apps as $a): ?>
                  <option value="<?= $a['id'] ?>" <?= $em['application_id']==$a['id']?'selected':'' ?>><?= htmlspecialchars($a['company']) ?> – <?= htmlspecialchars(mb_substr($a['job_title'],0,24)) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- Reply compose area -->
            <div id="compose-<?= $em['id'] ?>" style="display:none">
              <label class="cf-label">Reply to: <?= htmlspecialchars($em['sender_email']) ?></label>
              <textarea id="reply-text-<?= $em['id'] ?>" class="cf-input" rows="6" placeholder="Write your reply…" style="margin-bottom:10px"><?= htmlspecialchars($em['reply_body'] ?? '') ?></textarea>
              <div style="display:flex;gap:8px">
                <button onclick="sendReply(<?= $em['id'] ?>)" class="btn btn-primary btn-sm">Send Reply</button>
                <button onclick="document.getElementById('compose-<?= $em['id'] ?>').style.display='none'" class="btn btn-secondary btn-sm">Cancel</button>
              </div>
            </div>

          </div><!-- /panel -->
        </div><!-- /email-row -->

        <?php endforeach; endif; ?>
      </div><!-- /card -->

      <!-- Pagination -->
      <?php if ($total > $perPage): $totalPages = ceil($total / $perPage); ?>
      <div style="display:flex;justify-content:center;gap:6px;margin-top:14px">
        <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
        <a href="?filter=<?= $filter ?>&cat=<?= urlencode($category) ?>&q=<?= urlencode($q) ?>&p=<?= $pg ?>"
           class="btn btn-secondary btn-sm" style="<?= $pg===$page?'background:rgba(108,99,255,.25);color:#fff':'' ?>"><?= $pg ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= csrf_token() ?>';

/* Toggle expand */
function toggleEmail(id) {
  const panel = document.getElementById('panel-' + id);
  const isOpen = panel.classList.contains('open');
  panel.classList.toggle('open');
  if (!isOpen) {
    // mark read
    fetch('gmail.php', { method:'POST', body: (() => { const f=new FormData(); f.append('_csrf',CSRF); f.append('_action','mark_read'); f.append('email_id',id); return f; })() });
    const row = document.getElementById('email-row-' + id);
    row.classList.remove('unread');
    row.style.background = '';
  }
}

/* Generate AI reply */
async function generateReply(id) {
  const btn = document.getElementById('gen-btn-' + id);
  btn.disabled = true;
  btn.textContent = '✨ Generating…';
  const fd = new FormData();
  fd.append('_csrf', CSRF); fd.append('_action','generate_reply'); fd.append('email_id', id);
  try {
    const r = await fetch('gmail.php', { method:'POST', body:fd });
    const j = await r.json();
    if (j.error) { showToast(j.error, 'error'); }
    else {
      document.getElementById('reply-text-' + id).value = j.reply;
      document.getElementById('compose-' + id).style.display = 'block';
      showToast('AI reply generated!', 'success');
    }
  } catch(e) { showToast('Network error', 'error'); }
  btn.disabled = false;
  btn.textContent = '✨ Generate AI Reply';
}

function showReplyCompose(id) {
  const c = document.getElementById('compose-' + id);
  c.style.display = c.style.display === 'none' ? 'block' : 'none';
  if (!document.getElementById('panel-' + id).classList.contains('open')) toggleEmail(id);
}

/* Send reply */
async function sendReply(id) {
  const body = document.getElementById('reply-text-' + id).value.trim();
  if (!body) { showToast('Reply is empty.','error'); return; }
  const fd = new FormData();
  fd.append('_csrf',CSRF); fd.append('_action','send_reply'); fd.append('email_id',id); fd.append('reply_body',body);
  const r = await fetch('gmail.php', { method:'POST', body:fd });
  const text = await r.text();
  location.reload();
}

/* Re-analyze */
async function reanalyze(id) {
  showToast('Re-analyzing with AI…','info');
  const fd = new FormData();
  fd.append('_csrf',CSRF); fd.append('_action','reanalyze'); fd.append('email_id',id);
  const r = await fetch('gmail.php', { method:'POST', body:fd });
  const j = await r.json();
  if (j.ok) { showToast('Analysis updated!','success'); setTimeout(()=>location.reload(),1200); }
  else showToast(j.error||'Error','error');
}

/* Link to application */
async function linkApp(emailId, appId) {
  const fd = new FormData();
  fd.append('_csrf',CSRF); fd.append('_action','link_app'); fd.append('email_id',emailId); fd.append('application_id',appId);
  await fetch('gmail.php', { method:'POST', body:fd });
  showToast('Linked to application','success');
}
</script>

</main>
</body>
</html>
