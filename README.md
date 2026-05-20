# CareerFlow v2.0 – Smart Job Application Tracker

A **production-ready, full-stack PHP/MySQL SaaS platform** for managing your entire job search — with AI email analysis, Gmail integration, smart reply generation, and AI-powered cover letter creation.

---

## ✨ What's Included

### Core
| Feature | Details |
|---|---|
| **Auth** | Register · Login · Forgot/Reset password · Remember me · bcrypt · CSRF · session hardening |
| **Dashboard** | Live KPIs · Monthly bar chart · Status donut · Upcoming interviews · Activity feed |
| **Applications** | Full CRUD · 9 status stages · Quick inline status · Search/filter/sort · CSV export |
| **Application Detail** | Status timeline · Progress tracker · Inline notes · Interview outcomes · Related emails |
| **Kanban Board** | SortableJS drag-and-drop · Auto-save to DB · Live column counts |
| **Calendar** | Monthly grid · Schedule interviews · Upcoming list · Join links |
| **Resumes** | Upload PDF/DOC/DOCX · Version tracking · Default resume · Secure storage |
| **Analytics** | Line chart · Status donut · Funnel · Top companies · Job type breakdown · Salary stats |
| **Notes** | Per-app notes · 5 types · Edit in-place · Reminders with datetime |

### AI & Integrations (v2)
| Feature | Details |
|---|---|
| **Gmail IMAP Sync** | Reads inbox via App Password · No OAuth needed · PHP `imap` extension |
| **AI Email Analysis** | Detects job-related emails · Categorises (offer/interview/rejection etc.) · Sentiment · Auto-updates application status |
| **AI Reply Generator** | One-click AI-written reply based on email content and tone |
| **Gmail SMTP Send** | Sends replies directly through Gmail SMTP via SSL |
| **Cover Letter AI** | Full AI-generated cover letters · 4 tones · Version history · Favourite · Download |
| **OpenRouter Free AI** | 7 free models (Mistral, Llama 3.1, Gemma 2, Phi-3, Qwen 2) · No credit card |

### Settings
| Feature | Details |
|---|---|
| **Currency Selector** | 25 currencies (USD, PHP, EUR, GBP, INR, SGD, MYR…) · Changes all salary displays site-wide |
| **AI Config** | API key · Model selector with live test |
| **Gmail Config** | Step-by-step App Password setup guide |
| **Profile** | Full name · Skills · Years exp · LinkedIn · Used by AI for cover letters |
| **Security** | Change password · Confirm match indicator · Sign out all sessions |
| **Data** | CSV export · Account deletion with full cleanup |

---

## 🗂 File Structure

```
careerflow/
├── index.php                    ← Root redirect
├── .htaccess                    ← Security headers, compression
├── setup_complete.sql           ← ✅ Single SQL file — run this
│
├── includes/
│   ├── config.php               ← DB credentials, constants
│   ├── db.php                   ← PDO singleton (DB::run/all/one)
│   ├── auth.php                 ← Session, CSRF, hashing, activity log
│   ├── ai.php                   ← OpenRouter AI helper (analyzeEmail, generateReply, generateCoverLetter)
│   ├── gmail.php                ← IMAP sync + email body parser
│   └── smtp.php                 ← Gmail SMTP sender (SSL port 465)
│
├── components/
│   └── layout.php               ← Sidebar, topbar, CSS vars, JS utils, cf_currency()
│
├── pages/
│   ├── login.php / register.php / forgot.php / reset.php / logout.php
│   ├── dashboard.php            ← KPIs + charts (fixed height containers)
│   ├── applications.php         ← List + CRUD modal + CSV export
│   ├── application_detail.php   ← Full detail · timeline · notes · interviews
│   ├── kanban.php               ← Drag-and-drop board
│   ├── calendar.php             ← Interview scheduler + monthly grid
│   ├── resumes.php              ← Upload + version management
│   ├── analytics.php            ← Charts + funnel (fixed heights)
│   ├── notes.php                ← Notes browser + reminders
│   ├── gmail.php                ← Gmail inbox + AI analysis + reply
│   ├── cover_letters.php        ← AI cover letter generator + editor
│   └── settings.php             ← Profile · Currency · AI · Gmail · Security
│
└── uploads/
    ├── .htaccess                ← Blocks PHP execution in uploads
    └── resumes/                 ← Stored resume files
```

---

## ⚡ Installation (5 minutes)

### Requirements
| | Minimum |
|---|---|
| PHP | 8.0+ |
| MySQL / MariaDB | 5.7 / 10.4+ |
| Apache | 2.4+ with `mod_rewrite` |
| PHP extensions | `pdo_mysql` `mbstring` `imap` `openssl` `curl` `fileinfo` |

### Step 1 — Place Files
Copy the `careerflow/` folder into your web root:
- **XAMPP/WAMP** → `htdocs/careerflow`
- **LAMP** → `/var/www/html/careerflow`

### Step 2 — Create Database
```sql
CREATE DATABASE careerflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
Then import:
```bash
mysql -u root -p careerflow < setup_complete.sql
```

### Step 3 — Configure
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'careerflow');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('APP_URL',  'http://localhost/careerflow');  // no trailing slash
```

### Step 4 — Permissions
```bash
chmod 755 uploads/
chmod 755 uploads/resumes/
# Linux/Apache:
sudo chown -R www-data:www-data uploads/
```

### Step 5 — Enable PHP IMAP (for Gmail)
```bash
# Ubuntu/Debian
sudo apt install php-imap php-curl
sudo phpenmod imap
sudo systemctl restart apache2

# XAMPP (Windows): uncomment in php.ini:
# extension=imap
```

### Step 6 — Open & Register
```
http://localhost/careerflow
```
Create an account → go to **Settings → AI Settings** to add your free OpenRouter key.

---

## 🤖 AI Setup (Free)

1. Visit **[openrouter.ai](https://openrouter.ai)** → create a free account
2. Go to **Keys** → **Create Key** → copy it (`sk-or-v1-…`)
3. In CareerFlow: **Settings → 🤖 AI Settings** → paste key → choose model → **Save & Test**

**Recommended free model:** `mistralai/mistral-7b-instruct:free` (fast, 7B params, excellent quality)

---

## 📧 Gmail Setup

1. Enable **2-Step Verification** on your Google account
2. Go to [myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords)
3. App name: **CareerFlow** → Create → copy the 16-char password
4. In CareerFlow: **Settings → 📧 Gmail** → enter your Gmail + App Password → Save
5. Go to **Gmail Inbox** → click **Sync Gmail**

The AI will automatically:
- Detect which emails are job-related
- Categorise them (interview invite, offer, rejection, etc.)
- Update your application statuses
- Suggest smart email replies

---

## 💱 Currency

Go to **Settings → 💱 Currency** → pick your currency (PHP, USD, EUR, etc.) → Save.

All salary figures across the entire platform update instantly — dashboard, applications list, kanban cards, analytics, application detail, and cover letters.

---

## 🔐 Security

- All SQL via PDO prepared statements (zero raw interpolation)
- CSRF tokens on every POST form and AJAX call
- bcrypt password hashing (cost 12)
- `htmlspecialchars()` on all output
- Upload allowlist (pdf/doc/docx) + random filename + size cap
- PHP execution blocked in `uploads/` via `.htaccess`
- `includes/` directory blocked from direct HTTP access
- Session: `httponly`, `samesite=Lax`, strict mode, regenerate on login
- Password reset: cryptographically random token, 1-hour expiry, single-use
- `OPTIONS -Indexes` everywhere

---

## 🐛 Chart Fix Notes

Charts use `maintainAspectRatio: false` + explicit pixel-height wrapper `<div>`:
```html
<div style="position:relative; height:200px; width:100%">
  <canvas id="myChart"></canvas>
</div>
```
This prevents the infinite-expand bug where Chart.js keeps growing the canvas on each render.

---

## 📦 Third-Party (CDN — no install needed)

| Library | Version | Use |
|---|---|---|
| Tailwind CSS | Latest | Utility CSS |
| Chart.js | 4.4.0 | All charts |
| SortableJS | 1.15.2 | Kanban drag-and-drop |
| Google Fonts | — | Syne + DM Sans |

---

## 📄 License

MIT — free for personal and commercial use.
