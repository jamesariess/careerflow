# CareerFlow – Smart Job Application Tracker

> A production-ready, full-stack PHP/MySQL SaaS dashboard for managing your entire job search in one place.

---

## ✨ Features

| Category | What's included |
|---|---|
| **Auth** | Register · Login · Forgot/Reset password · Remember me · CSRF protection · bcrypt hashing |
| **Dashboard** | KPI stats · Monthly bar chart · Status donut chart · Upcoming interviews · Recent activity |
| **Applications** | Full CRUD · 9 status stages · Quick inline status change · CSV export · Filter & search |
| **Kanban Board** | Drag-and-drop cards via SortableJS · Auto-save status to DB · Live column counters |
| **Calendar** | Monthly view · Schedule interviews · Upcoming list · Interview types · Link/location |
| **Resumes** | Upload PDF/DOC/DOCX · Version tracking · Default resume · Secure file storage |
| **Analytics** | Line chart (apps + interviews) · Status donut · Top companies bar · Job type pie |
| **Notes** | Per-application notes · 5 note types · Edit in-place · Reminders with datetime |
| **Settings** | Profile · Timezone · Theme · Change password · Export data · Delete account |
| **Security** | Prepared statements · CSRF tokens · XSS prevention · Input sanitization · Session hardening · Upload type/size validation |

---

## 🗂 Folder Structure

```
careerflow/
├── index.php                  ← Root redirect
├── .htaccess                  ← Security headers + compression
├── database.sql               ← Full MySQL schema
│
├── includes/
│   ├── config.php             ← DB credentials, app constants
│   ├── db.php                 ← PDO singleton helper (DB::run/all/one)
│   ├── auth.php               ← Session, CSRF, hashing, activity log
│   └── .htaccess              ← Block direct access
│
├── components/
│   └── layout.php             ← Shared sidebar, topbar, CSS vars, JS utils
│
├── pages/
│   ├── login.php
│   ├── register.php
│   ├── forgot.php
│   ├── reset.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── applications.php       ← List + Add/Edit modal + CSV export
│   ├── application_detail.php ← Detail view + notes + interviews
│   ├── kanban.php             ← Drag-and-drop board
│   ├── calendar.php           ← Interview scheduler + monthly grid
│   ├── resumes.php            ← Upload + version management
│   ├── analytics.php          ← Charts & metrics
│   ├── notes.php              ← Notes browser + reminders
│   └── settings.php           ← Profile, password, danger zone
│
└── uploads/
    ├── .htaccess              ← Block PHP in uploads
    └── resumes/               ← Stored resume files
```

---

## ⚡ Quick Start

### Requirements

| Software | Version |
|---|---|
| PHP | 8.0 or higher |
| MySQL / MariaDB | 5.7 / 10.4+ |
| Apache | 2.4+ with `mod_rewrite` |
| PHP extensions | `pdo_mysql`, `fileinfo`, `mbstring` |

---

### 1 — Clone / Download

```bash
git clone https://github.com/yourname/careerflow.git
# or extract the ZIP into your web server's document root
```

Place the `careerflow/` folder inside:
- **XAMPP/WAMP**: `htdocs/careerflow`
- **LAMP**: `/var/www/html/careerflow`
- **Valet / Herd**: `~/Sites/careerflow`

---

### 2 — Create the Database

```sql
-- In MySQL / phpMyAdmin / TablePlus:
CREATE DATABASE careerflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then import the schema:

```bash
mysql -u root -p careerflow < database.sql
```

Or paste `database.sql` directly into phpMyAdmin → Import.

---

### 3 — Configure the App

Open **`includes/config.php`** and update:

```php
define('DB_HOST', 'localhost');   // MySQL host
define('DB_NAME', 'careerflow'); // Database name
define('DB_USER', 'root');       // MySQL username
define('DB_PASS', '');           // MySQL password

define('APP_URL', 'http://localhost/careerflow'); // No trailing slash
```

---

### 4 — Set Upload Permissions

```bash
chmod 755 uploads/
chmod 755 uploads/resumes/
```

On Linux servers:
```bash
chown -R www-data:www-data uploads/
```

---

### 5 — Enable Apache mod_rewrite (if needed)

```bash
# Ubuntu/Debian
sudo a2enmod rewrite
sudo systemctl restart apache2
```

In your Apache VirtualHost or `httpd.conf`, ensure:
```apache
AllowOverride All
```

---

### 6 — Open in Browser

```
http://localhost/careerflow
```

You'll be redirected to the login page. Register a new account and start tracking!

---

## 🎨 Design System

The UI uses CSS custom properties defined in `components/layout.php`:

```css
--bg        #0D0D14   /* Page background        */
--sidebar   #0F0F18   /* Sidebar background      */
--accent    #6C63FF   /* Primary purple          */
--accent2   #4ECDC4   /* Teal accent             */
--accent3   #FF6B9D   /* Pink accent             */
--text      #E8E8F0   /* Body text               */
--muted     rgba(255,255,255,0.42)
--success   #4ade80
--warning   #fbbf24
--danger    #f87171
```

**Fonts**: Syne (headings) + DM Sans (body) — loaded from Google Fonts.

---

## 🔐 Security Checklist

- [x] All DB queries use PDO prepared statements (no raw SQL interpolation)
- [x] CSRF tokens on every POST form + AJAX header
- [x] Passwords hashed with `bcrypt` (cost 12)
- [x] `htmlspecialchars()` on all output
- [x] File uploads: extension allowlist + size cap + random filename
- [x] PHP execution blocked in `uploads/` via `.htaccess`
- [x] `includes/` directory blocked from direct HTTP access
- [x] Session: `httponly`, `samesite=Lax`, strict mode, regenerate on login
- [x] Password reset tokens: cryptographically random, 1-hour expiry, single-use
- [x] `OPTIONS -Indexes` in all `.htaccess` files

---

## 📦 Third-Party Libraries (CDN — no install needed)

| Library | Version | Use |
|---|---|---|
| Tailwind CSS | latest | Utility CSS |
| Chart.js | 4.4.0 | Dashboard & analytics charts |
| SortableJS | 1.15.2 | Kanban drag-and-drop |
| Google Fonts | — | Syne + DM Sans |

---

## 🗃 Database Tables

| Table | Purpose |
|---|---|
| `users` | Auth, profile, theme, tokens |
| `applications` | Job application records |
| `resumes` | Uploaded resume metadata |
| `interviews` | Scheduled interview slots |
| `reminders` | Date/time-based reminders |
| `notes` | Per-application notes |
| `activity_logs` | Audit trail of user actions |
| `notifications` | In-app notification queue |

Full schema with indexes and foreign keys: see `database.sql`.

---

## 🚀 Production Deployment

```bash
# 1. Set APP_URL to your real domain
define('APP_URL', 'https://careerflow.yourdomain.com');

# 2. Use environment variables or a .env loader for credentials
# 3. Enable HTTPS — sessions are set secure=true automatically
# 4. Set PHP error_reporting to 0 / log_errors = On in php.ini
# 5. Restrict DB user to minimum privileges (SELECT, INSERT, UPDATE, DELETE)
```

---

## 🛠 Customisation Tips

**Add a new status**: Edit the `ENUM` in `database.sql` + add to `$statuses` arrays in `applications.php` and `kanban.php` + add a `.badge-xxx` rule in `layout.php`.

**Change colours**: Update the CSS variables block in `components/layout.php`.

**Email notifications**: Implement `mail()` or PHPMailer in `includes/auth.php` for real password reset emails and interview reminders.

**Role-based access**: Add a `role` column to `users` and wrap routes with a middleware check in `auth.php`.

---

## 📄 License

MIT — free for personal and commercial use.

---

*Built with ❤️ as a production-quality PHP SaaS starter.*
