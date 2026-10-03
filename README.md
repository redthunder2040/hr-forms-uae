# hr-forms-uae
HR Managments Forms

## 1. What is inside

| Area | What it does |
|---|---|
| **Employee master database** | 479 employees imported with code, name, sponsor, company, department, joining date, profession, basic, allowance, total. Add / edit / delete, filter, paginate, export CSV. |
| **Companies** | The two legal names used on every form and printed document: `Company 1` and `Company 2`. |
| **Departments / professions** | Seeded from the workbook (`Labour`, `Staff`, helpers, engineers…) and fully editable. |
| **8 request forms** | Emergency leave, Leave salary, Vacation, Salary increment, Advance, Cancellation letter, **Offer letter**, **Salary certificate**. |
| **Dynamic form builder** | Admin/HR can add, edit, delete, re-order, hide/show **any field** of **any** form — no code change. |
| **Requests** | Every submission is stored in `requests` (field values in JSON), referee-numbered, status `Waiting → Approved / Rejected / Cancelled`, with a full status history. |
| **Print / download** | HTML print view (A4) and **PDF download** through the bundled Dompdf — no Composer needed on the host. |
| **Reports** | Filters by form, company, status, employee, free text and date range; totals per status/company/month; CSV export. |
| **Users & roles** | `admin`, `HR`, `user` + a permission-rules page where the **admin** grants or revokes each right per role. |
| **Security** | `password_hash()`/`password_verify()`, prepared statements everywhere, CSRF tokens on every POST, session regeneration, activity log, upload folder hardened. |
---

## 2. Install on cPanel / shared hosting (5 minutes)

1. **Upload** the contents of this folder to `public_html` (or a sub-folder such as
   `public_html/hr`). Keep `vendor/`, `sql/` and `data/` exactly as they are.
2. **Create the database** in cPanel → *MySQL® Databases*: e.g. database `hr_system`, user
   `hr_user`, password of your choice. Give the user *All privileges* on the database.
3. **Edit `config.php`** and set `DB_NAME`, `DB_USER`, `DB_PASS` (`DB_HOST` stays `localhost`
   on almost every host).
4. **Import the data** — either:
   * cPanel → *phpMyAdmin* → select the database → **Import** → choose `sql/install.sql` → Go; **or**
   * open `https://your-domain/run.php` once and press **Run installation**.
   Both routes create the 11 tables and seed companies, departments, professions, the 8 forms,
   the 3 users and all 479 employees. Running them twice changes nothing (nothing is duplicated).
5. **Sign in** at `https://your-domain/index.php` with:

   | Username | Password | Role |
   |---|---|---|
   | `admin` | `Admin@123` | Administrator (full control) |
   | `hr` | `Hr@12345` | HR Officer |
   | `user` | `User@12345` | User (own requests) |

6. **Immediately** open *My Account → Change password* for `admin`, and change or delete the
   `hr` / `user` demo accounts in *Users & Access*. Then delete `run.php`.
7. Make sure `uploads/` is writable (`chmod 775 uploads`) so attachments can be saved.
---

## 3. Everyday use

* **New request** → pick a form tab → choose the employee (type in the filter box, the list
  narrows, it auto-fills salary, profession, department, passport) → fill the fields →
  Submit. The request is stored with a reference such as `VAC-2026-0007`, status **Waiting**.
* **Approve / reject** → *Request Register* (✓ / ✕ buttons) or open the request
  (*Approval workflow*). Every change is written to the request history with user and time.
* **Print / PDF** → open a request and press **Print document** or **Download PDF**; the offer
  letter and the salary certificate come out on your pre-printed letterhead with the real template
  wording, amount in words, signature blocks and a stamp line.
* **Reports** → filter by form / company / status / dates, read the totals, export CSV.
* **Form Builder** → choose a form, then add a field (label, key, type, required, order,
  dropdown options) — it appears on the live form at once. Hide/delete fields the same way.
* **Permission Rules** (admin only) → tick/untick what `HR` and `User` may do; the
  Administrator always keeps full access so you can never lock yourself out.
* **Employee master** → add/edit/delete, or export CSV for Excel. Deactivating keeps history.


---

## 4. Folder map

```
index.php            login
dashboard.php        KPIs, headcount & payroll, latest requests
print.php            A4 print view of a stored request
pdf.php              PDF download (bundled Dompdf)
run.php              one-time installer (delete after use)
config.php           database + app settings  ← edit DB credentials here
db.php / auth.php    PDO layer, roles, permissions, CSRF, activity log
seed.php             reference data, the 8 forms, 3 users, 479 employees
sql/schema.sql       table structure
sql/install.sql      structure + all seeded data (import this in phpMyAdmin)
data/employees.json  the employee master data extracted from your workbook
assets/  css, js, img/logo.png (your Company logo)
includes/ header, footer, fields engine, document builders, 403 page
pages/   employees, master, forms (builder), request_new, requests,
         request_view, reports, users, permissions, settings, profile
vendor/  dompdf + php-font-lib + php-svg-lib + Masterminds html5 + CSS parser
uploads/ request attachments (PHP execution disabled)
```

---


© HR Management System — Company 1 · Company 2
