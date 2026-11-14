# School Reconciliation Management Dashboard

A complete, modern, responsive, and secure **School Reconciliation Management Web Application** designed specifically for school administrators, accounting supervisors, and reconciliation staff.

Built purely using **HTML5, CSS3, Vanilla JavaScript (ES6+), PHP, and MySQL**, with zero external frameworks, node modules, or paid APIs.

---

## 🌟 Key Features

### 1. Main Dashboard & Live Overview
- **3 Dynamic Overview Cards**:
  - 🟦 **Total Schools** (Blue gradient card) – Shows total active registered schools.
  - 🟧 **Pending** (Orange gradient card) – Live count of schools awaiting reconciliation for the chosen session and date.
  - 🟩 **Completed** (Green gradient card) – Live count of schools successfully reconciled for the chosen session and date.
- **Header Live Indicators**:
  - Real-time digital clock ticking every second with 12-hour AM/PM format.
  - Current calendar date.
  - Selected active session badge (`☀️ AM Session` / `🌙 PM Session`).
  - Logged-in staff username and role badge.

### 2. Strict AM / PM Session Separation
- **AM Session**: Scheduled from **7:00 AM – 1:00 PM**.
- **PM Session**: Scheduled from **2:00 PM – 7:00 PM**.
- **Data Isolation**: AM and PM records are stored independently in the database. AM records **never** spill into PM, and PM records **never** overwrite AM records.
- **Session Switcher**: Instant switching between AM and PM sessions with separate pending and completed calculations.
- **Working Date Selector**: Ability to navigate to any past or upcoming date with independent AM/PM records per date.

### 3. ERP Auto-Login Gateway
- **One-Click Automated Login**: Each school has an **`ERP Login`** button placed right beside its name in the reconciliation table and workspace.
- **Hands-Free Credential Handling**: Clicking the button automatically opens that school's exact ERP login portal, passes the predefined username and password, and automatically redirects the browser into the **Online Fees Collection** reconciliation page.
- **Configured Institutions**:
  1. **Blue Mount**: `https://bluemount.amserp.in/index.php/core/feescollection/onlinepayment` (`admin` / `Bluemount_2026$`)
  2. **BVB Poochatty**: `https://bvbpoochatty.amserp.in/index.php/core/feescollection/onlinepayment` (`admin` / `Bvbpty@2023#&`)

### 4. School Selection Workflow & Accidental Switching Lock
- **School Dropdown & Quick-Select**: Select any registered school to open its interactive reconciliation workspace.
- **Workflow Lock Protection**: When a school reconciliation is started, the workspace engages an active lock. Accidental switching to another school or session is prevented until the current school's task is completed or explicitly confirmed.
- **Post-Completion Unlock**: Once marked complete (via Payment Success, No Payment, Reconciliation Issue, or Refund), the workflow unlocks to select the next institution.

### 4. Mutually Exclusive School Payment Controls
- **A. No Payment (Blue Button)**:
  - When clicked, saves `No Payment` status.
  - Automatically **disables** Payment Success, Reconciliation Issue, and Refund controls for that school.
- **B. Payment Success (Green Button)**:
  - When clicked, saves `Payment Success` status.
  - Automatically **disables** No Payment, Reconciliation Issue, and Refund controls for that school.

### 5. Reconciliation Issue Tracking
- **Reconciliation Issue (Red Button)**:
  - Alphanumeric input field accepting issue codes, numbers, letters, and references (e.g. `123`, `REF102`, `BANK-ISSUE-05`).
  - **10-Character Warning Condition**: Entering more than 10 characters immediately displays a warning message on the right side (`⚠️ Warning: Issue reference exceeds 10 characters`).
  - Does **not** block the user from submitting longer values; the full value is safely stored in the MySQL database.
  - Optional flag to mark issue as an **Urgent Case** with priority level (*High*, *Critical*, *Medium*).

### 6. Refund Management
- **Refund Button (Light-Orange)**:
  - Input field for entering refund reference numbers, voucher codes, or transaction details.
  - Immediately updates school status to `Refund` and reflects in the main reconciliation table.

### 7. Main Reconciliation Table
- Clean table with columns: `School Name` | `Reconciliation` | `Refund` | `Remark`.
- Real-time search filter by school name or code.
- Status badges: *Pending*, *Payment Success*, *No Payment*, *Reconciliation Issue*, *Refund*, *Completed*.
- Staff attribution: Displays the staff username who performed each action.

### 8. Role-Based Access Control (RBAC)
| Role | Reconciliation | Urgent Cases | School CRUD | User CRUD | Privilege Matrix | Undo/Redo |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **USER** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **ADMIN** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **SUPER ADMIN** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

### 9. Super Admin Features
- **User Management**:
  - User creation requires only **Username**, **Password**, and **Role**.
  - User list table with **View**, **Edit**, and **Delete** actions.
  - Duplicate username prevention.
  - Passwords hashed with standard PHP `password_hash(..., PASSWORD_BCRYPT)`.
- **Privilege Management Matrix**:
  - Granular interactive permission toggles for all roles.
  - Strictly enforced on the PHP backend.
- **Undo / Redo Functionality**:
  - Comprehensive action history capturing previous state, new state, acting user, timestamp, session, and school.
  - Super Admin can revert mistakes (e.g. accidental No Payment selection) or redo undone actions.
  - Confirmation modals before restoring states.
- **School Management**:
  - Add, edit, view, and delete schools (Code, Name, Contact Person, Phone, Email, Address, Status).
- **System Settings & Rules**:
  - Configurable AM/PM session schedule times.
  - Configurable reference warning length threshold (default 10).
  - Session inactivity timeout (minutes).

---

## 🛠️ Technology Stack

- **Frontend**: HTML5, CSS3 (Modern Flexbox, CSS Grid, Glassmorphism, CSS Custom Properties), Vanilla JavaScript (ES6+ Fetch API, DOM manipulation).
- **Backend**: Pure PHP 8.0+ (OOP / Functional, PDO with prepared statements, strict authentication & CSRF validation).
- **Database**: MySQL 5.7+ / MariaDB 10.4+ (Indexed, normalized schema with foreign key constraints and transactional integrity).
- **Web Server**: Apache on XAMPP or any standard LAMP/WAMP/LEMP environment.

---

## 🚀 Installation & Setup on XAMPP

### Step 1: Place Files in XAMPP
Ensure the application folder is placed in your XAMPP `htdocs` directory:
```
C:\xampp\htdocs\reconsilation
```

### Step 2: Start Apache & MySQL
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.

### Step 3: Import Database via phpMyAdmin
1. Open your browser and navigate to:
   ```
   http://localhost/phpmyadmin/
   ```
2. Click **Import** in the top navigation bar.
3. Click **Choose File** and select:
   ```
   C:\xampp\htdocs\reconsilation\database.sql
   ```
4. Click **Import** at the bottom of the page.
5. The database `school_reconciliation_db` and all required tables, permissions, roles, system settings, and demo schools will be created automatically.

### Step 4: Open the Application
Navigate to the application URL in your web browser:
```
http://localhost/reconsilation/
```

---

## 🔐 Default Login Credentials

| Role | Username | Password | Purpose |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `superadmin` | `Admin@123` | Full system access, users, schools, privileges, undo/redo |
| **Admin** | `admin` | `Admin@123` | Reconciliation workspace & urgent case management |
| **Staff User** | `staff_user` | `Admin@123` | Daily reconciliation and payment processing |

> 💡 **Quick Login**: The sign-in page includes one-click demo credential fillers for convenience.

---

## 📁 Directory Structure

```
reconsilation/
├── api/                          # Backend RESTful AJAX Endpoints
│   ├── privileges.php            # Role permissions matrix API
│   ├── reconciliation.php        # AM/PM records, payment, issue & refund API
│   ├── schools.php               # School CRUD API
│   ├── settings.php              # System configuration API
│   ├── undo_redo.php             # Super Admin undo/redo action API
│   ├── urgent_cases.php          # Urgent case escalation API
│   └── users.php                 # User management API
├── assets/
│   ├── css/
│   │   ├── style.css             # Design system, sidebar, modals, toasts, tables
│   │   └── dashboard.css         # Overview cards, workspace drawer, pills, controls
│   └── js/
│       ├── app.js                # Live clock, toast notifications, custom confirm modals
│       ├── dashboard.js          # Session switcher, workflow locks, payment mutual exclusion
│       ├── privileges.js         # Permission matrix toggles
│       ├── schools.js            # School management modals & AJAX
│       ├── undo_redo.js          # History table & state restoration
│       ├── urgent.js             # Urgent case status management
│       └── users.js              # User management modals & AJAX
├── config/
│   ├── auth.php                  # Authentication, permission verification & CSRF checks
│   ├── constants.php             # Roles, sessions, payment constants
│   └── database.php              # Secure PDO MySQL connection
├── includes/
│   ├── footer.php                # Global footer, modal containers & base JS
│   ├── functions.php             # Shared helpers, XSS escaping, activity logger
│   ├── header.php                # Sticky topbar with live clock & user badges
│   └── sidebar.php               # Responsive sidebar navigation with role filtering
├── dashboard.php                 # Main reconciliation workspace (AM/PM toggle & table)
├── database.sql                  # Complete database schema and seed data
├── history.php                   # Super Admin Undo / Redo & Audit History
├── index.php                     # Secure authentication portal (Login)
├── logout.php                    # Secure session destruction & logout
├── privileges.php                # Super Admin Privilege Matrix
├── README.md                     # Documentation & setup guide
├── schools.php                   # Super Admin School Management
├── settings.php                  # Super Admin System Settings & Rules
├── urgent_cases.php              # Admin & Super Admin Urgent Cases
└── users.php                     # Super Admin User Management
```

---

## 🛡️ Security Implementations

1. **Password Hashing**: `password_hash()` with `PASSWORD_BCRYPT` (Cost factor 10). Plain-text passwords are never stored.
2. **SQL Injection Defense**: 100% of database queries use PDO prepared statements with bound parameters (`?` or named parameters).
3. **Cross-Site Request Forgery (CSRF)**: Cryptographically secure tokens (`random_bytes(32)`) validated on every state-altering POST request.
4. **Cross-Site Scripting (XSS)**: All dynamic outputs are sanitized through `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
5. **Session Fixation & Hijacking**: Automatic `session_regenerate_id(true)` upon successful login and configurable inactivity timeout.
6. **Backend Permission Verification**: Role and permission authorization is strictly validated on the server side (`require_permission()` / `require_role()`).
>>>>>>> bd1b043 (Initial commit: School Reconciliation Management System with Vercel serverless configuration)
