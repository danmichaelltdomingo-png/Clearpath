# BPC ClearPath — Student Clearance System
## Installation Guide for XAMPP

---

### STEP 1 — Copy Files
Place the entire `clearpath` folder inside:
```
C:\xampp\htdocs\clearpath\
```

### STEP 2 — Setup Database
1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click **"New"** to create a new database (it will be created automatically by the SQL script)
3. Click the **"SQL"** tab
4. Open `clearpath/database.sql` in a text editor, copy all contents
5. Paste into phpMyAdmin's SQL tab and click **"Go"**

That's it! The database `bpc_clearpath` with all tables and demo data will be created.

### STEP 3 — Access the System
Open your browser and go to:
```
http://localhost/clearpath/
```

---

## Default Login Credentials

| Role       | Login ID / Email             | Password  |
|------------|------------------------------|-----------|
| Admin      | admin@bpc.edu.ph             | @bpc123   |
| Signatory  | sgadviser@bpc.edu.ph         | @bpc123   |
| Signatory  | research@bpc.edu.ph          | @bpc123   |
| Signatory  | ptca@bpc.edu.ph              | @bpc123   |
| Signatory  | scholarship@bpc.edu.ph       | @bpc123   |
| Signatory  | programhead@bpc.edu.ph       | @bpc123   |
| Signatory  | classadviser@bpc.edu.ph      | @bpc123   |
| Student    | 2020-0001                    | @bpc123   |
| Student    | 2020-0002                    | @bpc123   |

---

## System Roles

### Admin (Registrar)
- User management (add/deactivate students & signatories)
- Office management
- View & verify all clearance requests
- Activity logs

### Signatory (Per Office)
- View clearance requests for their office
- Approve or reject student clearance items
- Add office requirements checklist

### Student
- Submit a clearance request
- Upload documents per office
- Track real-time clearance status
- Download PDF clearance certificate (when fully cleared)

---

## File Structure
```
clearpath/
├── index.php              ← Login page
├── logout.php
├── database.sql           ← Run this in phpMyAdmin
├── includes/
│   ├── config.php         ← DB config + session helpers
│   ├── header.php         ← Shared sidebar + nav
│   └── footer.php
├── admin/
│   ├── dashboard.php
│   ├── students.php
│   ├── clearances.php
│   ├── clearance_view.php
│   ├── signatories.php
│   ├── offices.php
│   └── logs.php
├── signatory/
│   ├── dashboard.php
│   ├── requests.php
│   └── requirements.php
├── student/
│   ├── dashboard.php
│   ├── clearance.php
│   ├── submit.php
│   ├── history.php
│   └── download.php       ← Printable clearance certificate
└── uploads/               ← Student uploaded documents (auto-created)
```

---

## Notes
- Passwords are stored as **SHA1** hash (no bcrypt, as requested)
- Default password for all accounts: `@bpc123`
- Uploaded files are stored in `/uploads/` directory
- The `.htaccess` in uploads prevents direct PHP execution
- To change DB credentials, edit `includes/config.php`

---

## Troubleshooting
- **Blank page?** Enable PHP error display in `php.ini` or add `ini_set('display_errors', 1);` to config.php
- **DB connection error?** Make sure MySQL is running in XAMPP Control Panel
- **File upload issues?** Check `upload_max_filesize` in `php.ini` (set to at least 5M)
