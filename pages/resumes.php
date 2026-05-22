<?php
require_once '../components/layout.php';
cf_layout_head('Resumes');
cf_layout_sidebar('resumes');
?>
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
<?php

$uid = (int)$user['id'];
$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) die('CSRF');
    $action = $_POST['_action'] ?? '';

    if ($action === 'upload') {
        $label = trim($_POST['label'] ?? '');
        $file  = $_FILES['resume'] ?? null;
        if (!$label) { $msg = 'Please add a label.'; $msgType = 'error'; }
        elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) { $msg = 'Upload failed.'; $msgType = 'error'; }
        else {
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf','doc','docx'];
            if (!in_array($ext, $allowed)) { $msg = 'Only PDF/DOC/DOCX allowed.'; $msgType = 'error'; }
            elseif ($file['size'] > CF_UPLOAD_MAX_MB * 1024 * 1024) { $msg = 'File too large (max ' . CF_UPLOAD_MAX_MB . 'MB).'; $msgType = 'error'; }
            else {
                if (!is_dir(CF_UPLOAD_DIR)) mkdir(CF_UPLOAD_DIR, 0755, true);
                $fname = 'resume_' . $uid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest  = CF_UPLOAD_DIR . $fname;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // Get next version for this label
                    $ver = DB::one('SELECT MAX(version) AS v FROM resumes WHERE user_id=? AND label=?', [$uid, $label]);
                    $version = ($ver['v'] ?? 0) + 1;
                    DB::run('INSERT INTO resumes (user_id,label,filename,original_name,file_size,version) VALUES (?,?,?,?,?,?)',
                        [$uid, $label, $fname, $file['name'], $file['size'], $version]);
                    log_activity($uid, 'upload_resume', "Uploaded resume: $label v$version");
                    $msg = 'Resume uploaded!'; $msgType = 'success';
                } else { $msg = 'Could not save file.'; $msgType = 'error'; }
            }
        }
    }

    if ($action === 'set_default') {
        $id = (int)($_POST['id'] ?? 0);
        DB::run('UPDATE resumes SET is_default=0 WHERE user_id=?', [$uid]);
        DB::run('UPDATE resumes SET is_default=1 WHERE id=? AND user_id=?', [$id, $uid]);
        $msg = 'Default resume set.'; $msgType = 'success';
    }

    if ($action === 'delete_resume') {
        $id = (int)($_POST['id'] ?? 0);
        $r  = DB::one('SELECT * FROM resumes WHERE id=? AND user_id=?', [$id, $uid]);
        if ($r) {
            $path = CF_UPLOAD_DIR . $r['filename'];
            if (file_exists($path)) unlink($path);
            DB::run('DELETE FROM resumes WHERE id=?', [$id]);
            $msg = 'Resume deleted.'; $msgType = 'success';
        }
    }
}

$resumes = DB::all('SELECT * FROM resumes WHERE user_id=? ORDER BY created_at DESC', [$uid]);
?>
<?php if ($msg): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast('<?= addslashes($msg) ?>','<?= $msgType ?>'));</script>
<?php endif; ?>

<div style="max-width:900px">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 style="font-size:22px;font-weight:700;color:#fff">Resumes</h1>
      <p style="color:var(--muted);font-size:13px;margin-top:2px">Manage and version your resumes</p>
    </div>
    <button onclick="openModal('uploadModal')" class="btn btn-primary btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>
      Upload Resume
    </button>
  </div>

  <?php if (empty($resumes)): ?>
  <div class="card" style="padding:60px;text-align:center">
    <div style="font-size:40px;margin-bottom:12px">📄</div>
    <h3 style="color:#fff;font-size:17px;font-weight:600;margin-bottom:8px">No resumes yet</h3>
    <p style="color:var(--muted);font-size:14px;margin-bottom:20px">Upload your first resume to attach it to job applications.</p>
    <button onclick="openModal('uploadModal')" class="btn btn-primary">Upload Resume</button>
  </div>
  <?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px">
    <?php foreach ($resumes as $r): ?>
    <div class="card" style="padding:20px">
      <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:14px">
        <div style="width:44px;height:44px;border-radius:10px;background:rgba(108,99,255,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke="#6C63FF" stroke-width="1.8" fill="none"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke="#6C63FF" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-size:14px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($r['label']) ?></div>
          <div style="font-size:11px;color:var(--muted);margin-top:2px">v<?= $r['version'] ?> · <?= round($r['file_size']/1024) ?>KB</div>
          <div style="font-size:11px;color:var(--muted)"><?= date('M j, Y', strtotime($r['created_at'])) ?></div>
        </div>
        <?php if ($r['is_default']): ?>
        <span style="font-size:9px;background:rgba(34,197,94,.15);color:#4ade80;padding:2px 7px;border-radius:20px;font-weight:700;white-space:nowrap">DEFAULT</span>
        <?php endif; ?>
      </div>
      <div style="font-size:11px;color:var(--muted);margin-bottom:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= htmlspecialchars($r['original_name']) ?>">
        📎 <?= htmlspecialchars($r['original_name']) ?>
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <a href="<?= APP_URL ?>/uploads/resumes/<?= urlencode($r['filename']) ?>" download="<?= htmlspecialchars($r['original_name']) ?>" class="btn btn-secondary btn-sm">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          Download
        </a>
        <?php if (!$r['is_default']): ?>
        <form method="POST" style="display:inline">
          <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="set_default">
          <input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button type="submit" class="btn btn-secondary btn-sm">Set Default</button>
        </form>
        <?php endif; ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this resume?')">
          <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete_resume">
          <input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm">Delete</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Upload Modal -->
<div class="modal-overlay" id="uploadModal">
  <div class="modal-box" style="max-width:460px">
    <div class="cf-modal-header">
      <h2 style="font-size:17px;font-weight:700;color:#fff">Upload Resume</h2>
      <button onclick="closeModal('uploadModal')" style="background:rgba(255,255,255,.07);border:none;color:var(--muted);width:32px;height:32px;border-radius:8px;cursor:pointer;font-size:20px;display:flex;align-items:center;justify-content:center">×</button>
    </div>
    <div class="cf-modal-body">
    <form method="POST" enctype="multipart/form-data" id="uploadForm">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="upload">
      <div style="margin-bottom:14px">
        <label class="cf-label">Label / Version Name</label>
        <input type="text" name="label" class="cf-input" required placeholder="e.g. Software Engineer Resume">
      </div>
      <div style="margin-bottom:20px">
        <label class="cf-label">File (PDF, DOC, DOCX – max <?= CF_UPLOAD_MAX_MB ?>MB)</label>
        <input type="file" name="resume" accept=".pdf,.doc,.docx" class="cf-input" required style="padding:8px">
      </div>
    </form>
    </div>
    <div class="cf-modal-footer">
      <button type="button" onclick="closeModal('uploadModal')" class="btn btn-secondary">Cancel</button>
      <button type="submit" form="uploadForm" class="btn btn-primary">Upload</button>
    </div>
  </div>
</div>

</main>
</body>
</html>
