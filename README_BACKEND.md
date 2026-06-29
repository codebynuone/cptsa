# CPTSA Driving School — PHP/MySQL Backend
## Complete Setup Guide

---

## 1. Requirements

| Software | Minimum Version |
|----------|----------------|
| PHP      | 8.0+           |
| MySQL    | 5.7+ / MariaDB 10.3+ |
| Apache   | 2.4+ (with mod_rewrite) |
| Browser  | Any modern browser |

> **XAMPP / WAMP / LAMP / MAMP** all work perfectly.

---

## 2. Project Structure

```
cptsa_project/          ← place this as your web root or a subfolder
│
├── api/                ← PHP API endpoints (backend)
│   ├── _core.php       ← shared helpers (DB, auth, response)
│   ├── auth.php        ← login / logout / session
│   ├── students.php    ← student CRUD + hours
│   ├── timetable.php   ← slots + bookings
│   ├── announcements.php
│   ├── documents.php
│   ├── feedback.php
│   ├── messages.php
│   ├── exams.php
│   ├── vehicles.php
│   ├── instructors.php
│   ├── fees.php
│   └── settings.php    ← reg code, forms, signin logs, stats
│
├── config/
│   └── database.php    ← ⚠️  EDIT THIS with your DB credentials
│
├── uploads/
│   ├── photos/         ← student & instructor photos (auto-created)
│   └── docs/           ← uploaded documents (auto-created)
│
├── schema.sql          ← run this ONCE to create all tables
├── api_client.js       ← JS library used by all HTML pages
├── .htaccess           ← Apache URL + security rules
│
├── index.html          ← Home page
├── students.html       ← Student Portal
├── admin.html          ← Admin Portal
├── ann.html            ← Announcements admin
├── doc.html            ← Documents admin
├── fees.html           ← Fees admin
├── adminpanel.html     ← Instructors admin
├── admin_panel.html    ← Registration system admin
├── student_access.html ← Student self-registration form
├── announcements.html  ← Public announcements
├── instructor.html     ← Public instructor listing
├── services.html       ← Public services page
├── feedback.html       ← Public reviews page
├── about.html          ← About page
├── contact.html        ← Contact page
│
├── styles.css          ← Shared CSS
├── style1.css          ← Instructor admin CSS
└── nav.js              ← Shared navigation
```

---

## 3. Database Setup

### Step 1 — Create the database and tables

```bash
# Using MySQL command line:
mysql -u root -p < schema.sql

# Or open phpMyAdmin → SQL tab → paste contents of schema.sql → Go
```

This creates:
- Database `cptsa_driving`
- All 16 tables with correct relationships
- Default admin account (`admin` / `admin123`)
- Default vehicle categories (Bike, Van, Three-Wheeler)
- Default registration code (`5566`)

---

## 4. Configure Database Connection

Open `config/database.php` and edit:

```php
define('DB_HOST', 'localhost');   // usually 'localhost'
define('DB_PORT', '3306');        // default MySQL port
define('DB_NAME', 'cptsa_driving');
define('DB_USER', 'root');        // ← change to your MySQL username
define('DB_PASS', '');            // ← change to your MySQL password
define('DB_CHARSET', 'utf8mb4');
```

---

## 5. Deploy Files

### Option A — XAMPP (Windows/Mac)
1. Copy the entire `cptsa_project/` folder to `C:\xampp\htdocs\cptsa\`
2. Start Apache and MySQL in XAMPP Control Panel
3. Open browser: `http://localhost/cptsa/index.html`

### Option B — Live server / cPanel
1. Upload all files to `public_html/` (or a subfolder)
2. Create MySQL database via cPanel → MySQL Databases
3. Import `schema.sql` via phpMyAdmin
4. Edit `config/database.php` with live credentials

### Option C — Docker / WSL
```bash
# Example with PHP built-in server (development only)
cd cptsa_project
php -S localhost:8000
# Open http://localhost:8000
```

---

## 6. Default Login Credentials

| Portal | URL | Username | Password |
|--------|-----|----------|----------|
| Admin Portal | `/admin.html` | `admin` | `admin123` |
| Student Portal | `/students.html` | NIC (student ID) | Phone number |

> **Important:** Change the admin password immediately after first login by editing the `admins` table in the database:
> ```sql
> UPDATE admins SET password_hash = '$2y$10$YOUR_BCRYPT_HASH' WHERE username = 'admin';
> ```
> Or add a password-change endpoint to `api/auth.php`.

---

## 7. Adding Your First Students

### Via Admin Portal (recommended)
1. Login to `admin.html` (admin / admin123)
2. Click **Students** tab → **+ Add Student**
3. Fill NIC, phone, name, select vehicle categories
4. Student can now log in with their **NIC** as the username and their **phone number** as the password

### Via Registration Form flow
1. Go to `admin_panel.html` → login → **Add Student** section
2. Enter name + phone → **Add Student**
3. Student visits `student_access.html`, enters name + phone
4. Admin gives them the registration code (default: `5566`)
5. Student fills in full form

---

## 8. API Reference

All endpoints follow the pattern:
```
GET/POST  /api/endpoint.php?action=action_name
```

Session authentication uses PHP sessions (cookie-based).

### Auth
| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `student_login` | POST | — | `{id, password}` |
| `admin_login` | POST | — | `{username, password}` |
| `logout` | POST | — | Clear session |
| `session` | GET | — | Check current session |

### Students
| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `list` | GET | Admin | `?search=` optional |
| `get` | GET | Admin/Self | `?id=` |
| `create` | POST | Admin | JSON body |
| `update` | PUT | Admin | `?id=` + JSON body |
| `delete` | DELETE | Admin | `?id=` |
| `toggle_status` | POST | Admin | `?id=` |
| `pay_restart` | POST | Admin | `?id=&vehicle=` |
| `dashboard` | GET | Student | Own data + hours |
| `change_password` | POST | Student | `{current_password, new_password}` |
| `upload_photo` | POST | Student | multipart `photo` field |
| `hours` | GET | Admin/Self | `?id=` |

### Timetable
| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `slots` | GET | Public | `?vehicle=&date=` |
| `create_slot` | POST | Admin | JSON body |
| `delete_slot` | DELETE | Admin | `?id=` |
| `breakdown` | GET | Admin | `?vehicle=&date=` |
| `book` | POST | Student | `{slot_id}` |
| `cancel` | DELETE | Student | `?slot_id=` |
| `my_bookings` | GET | Student | — |

### Other endpoints
- `announcements.php` — list / create / delete
- `documents.php` — list / upload / delete / download
- `feedback.php` — list / summary / submit / delete
- `messages.php` — send / inbox / list / delete
- `exams.php` — list / my_exams / update
- `vehicles.php` — list / create / delete
- `instructors.php` — list / create / update / delete
- `fees.php` — list / create / delete
- `settings.php` — reg_code / set_reg_code / stats / signin_log / form_submit / form_get / form_list

---

## 9. JavaScript API Client

Every HTML page includes `api_client.js` which provides the `CPTSA` global namespace:

```javascript
// Authentication
await CPTSA.Auth.adminLogin('admin', 'admin123');
await CPTSA.Auth.studentLogin('0771234567', 's0771234567');
await CPTSA.Auth.logout();
var session = await CPTSA.Auth.session();

// Students
var list    = await CPTSA.Students.list('search term');
var student = await CPTSA.Students.get(42);
await CPTSA.Students.create({ nic:'901234567V', phone:'0771234567', ... });
await CPTSA.Students.update(42, { name_init:'Perera A.B.' });
await CPTSA.Students.delete(42);

// Timetable
var slots = await CPTSA.Timetable.slots('bike', '2026-07-01');
await CPTSA.Timetable.book('abc123slotid');
await CPTSA.Timetable.cancel('abc123slotid');

// Feedback
var summary = await CPTSA.Feedback.summary();
await CPTSA.Feedback.submit({ name:'Kamal', category:'Instructor Quality', rating:5, message:'...' });

// Documents
var docs = await CPTSA.Documents.list();
var url  = CPTSA.Documents.downloadUrl(7);  // direct download link

// All API calls return Promises and throw on error
```

---

## 10. Security Notes

### Change before going live
1. **Admin password** — update `admins` table with a bcrypt hash
2. **Registration code** — change from `5566` in Admin Panel → Registration Code
3. **DB credentials** — use a dedicated MySQL user, not `root`
4. **HTTPS** — always use SSL/TLS in production
5. **Session security** — add `session_regenerate_id(true)` after login (already in code)

### File permissions (Linux servers)
```bash
chmod 755 uploads/
chmod 755 uploads/photos/
chmod 755 uploads/docs/
chmod 644 config/database.php
```

### Protect config directory
Add to your server config or `.htaccess` in `config/`:
```apache
Order deny,allow
Deny from all
```

---

## 11. Migrating Existing localStorage Data

If you had students/data in localStorage from the old version, use this migration script in the browser console **before** switching to the backend:

```javascript
// Run in browser console on the OLD version to export data
var data = {
    students:      JSON.parse(localStorage.getItem('studentDB') || '[]'),
    announcements: JSON.parse(localStorage.getItem('announcements') || '[]'),
    feedback:      JSON.parse(localStorage.getItem('cptsa_feedbacks') || '[]'),
    fees:          JSON.parse(localStorage.getItem('vehicleFees') || '[]'),
    slots:         JSON.parse(localStorage.getItem('availableSlots') || '[]'),
    bookings:      JSON.parse(localStorage.getItem('slotBookings') || '[]'),
};
console.log(JSON.stringify(data));
// Copy the output and import via phpMyAdmin or a custom migration script
```

---

## 12. Troubleshooting

| Problem | Fix |
|---------|-----|
| `Database connection failed` | Check `config/database.php` credentials |
| `Unauthorised — admin login required` | Session expired; log in again |
| `CORS error` | Ensure `api_client.js` BASE path matches your server path |
| 404 on API calls | Check `.htaccess` is in place and mod_rewrite is enabled |
| Upload fails | Check `uploads/` folder exists and is writable (`chmod 755`) |
| White screen / PHP error | Enable error display: `ini_set('display_errors',1)` in `_core.php` |
| Sessions not persisting | Make sure PHP session path is writable |

---

## 13. Database Tables Reference

| Table | Purpose |
|-------|---------|
| `admins` | Admin user accounts |
| `students` | Student records |
| `student_vehicles` | Student ↔ vehicle category with hours |
| `vehicle_categories` | Vehicle types (Bike, Van, Three-Wheeler, custom) |
| `time_slots` | Admin-created training slots |
| `slot_bookings` | Student slot bookings |
| `exam_results` | Written/practical exam results per student per vehicle |
| `announcements` | Public announcements |
| `documents` | Uploaded fee/admin documents |
| `feedback` | Student reviews |
| `messages` | Admin messages |
| `message_recipients` | Message → student links |
| `instructors` | Instructor profiles |
| `fees` | Fee structures |
| `registration_forms` | student_access.html form submissions |
| `signin_logs` | Student sign-in activity |
| `settings` | Key-value app settings (reg code etc.) |

---

*CPTSA Driving School Management System — PHP/MySQL Backend*
*Built for Transport Services Authority, Central Province, Sri Lanka*
