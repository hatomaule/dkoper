# DKoper AI Handoff

## Purpose

DKoper is a Laravel-based cooperative loan and cash management system. The source of truth is the database, not spreadsheet formulas or derived UI fields.

## Current Stack

- Laravel 12.70.1
- PHP 8.5 locally (target hosting PHP 8.3)
- MariaDB 11.8 locally
- MySQL-compatible MariaDB/MySQL database on InfinityFree
- Blade + Breeze + Tailwind CSS + Alpine.js/Vite
- GitHub repository: https://github.com/hatomaule/dkoper.git
- Local project: `/home/hatomaule/projects/DKoper`

## Runtime Status

- Laravel project and local MariaDB/MySQL connection are operational.
- All migrations are currently applied. The missing `cash_accounts` table reported on `/dashboard` was created by applying `2026_10_25_010000_add_financial_workflow_fields`; its initial balance was populated from the latest cash-ledger balance (zero when no ledger entries exist).
- Current local database connection reports `mysql`; Alan is the cooperative owner (Owner, username `owner`, internal role `manager`) and Chalo is the employee/admin operator (Admin, username `admin`, internal role `kasir`). Their IDs, roles, and passwords were preserved by the identity migration.
- Login accepts an identifier (borrower code, username, or phone); public registration is disabled.
- First-login password change is enforced. The manager account has `must_change_password = false`; the cashier account currently has `must_change_password = true`. Do not inspect or disclose either password.
- Staff passwords were synchronized with the user's supplied account sheet during this session. Password values are intentionally not recorded here; use private credentials and never retain shared/default passwords for production.
- Core borrower, loan, installment, payment approval, cash ledger, profit, and activity-log workflows have been implemented in the current worktree.
- The visual redesign includes a dark, icon-based desktop sidebar with grouped sections and a compact account footer; mobile uses a responsive top bar and slide-down menu. The dashboard uses an emerald hero, operational KPIs, weekly cash-flow lines, monthly cash transaction amount bars, daily/weekly/monthly reports, borrower count, Owner bonus and Admin employee bonus (earned/projected). Login, tables, forms, and status badges share the same lightweight glass-inspired design system and system fonts (no external font request or new UI dependency).
- Latest visual refinement: the login submit label is the concise “Login” and the button is compact and right-aligned. Cards have more pronounced layered shadows, subtle gradients, and a gentle hover lift to feel elevated from the canvas; common action buttons were reduced in size.
- A dedicated forced-password-change screen is in `resources/views/profile/edit.blade.php`; its visible “Simpan dan lanjutkan” button submits the first password change.
- The modern UI is an independent Blade design, not a strict conversion of `index.baseline.html`.
- A DKoper financial logo and SVG favicon are in `public/logo.svg` and `public/favicon.svg`. Dark/light theme selection persists in local storage and is available on guest and authenticated layouts.
- Payment entry supports server-calculated oldest-outstanding-installment nominal or manual amount, cash/transfer method, private transfer-proof storage, browser-side WebP compression/camera capture, proof viewing for Owner/Admin, and WhatsApp receipt links when a borrower phone number is available. Payment and loan tables support filtering, 5/10/25/50/all pagination, and table/card views.
- The local migration `2026_10_26_000000_enhance_payments_and_staff_identity` has been applied. Existing payments were assigned `cash` as their method; no payment, borrower, or loan records were reset. Transfer proofs are stored on the private `local` disk. Production must run the migration before deploying this code.
- Dashboard date boundaries use the business timezone `Asia/Singapore` (UTC+8) and convert to UTC for database queries; do not compare local wall-clock boundaries directly to UTC timestamps.
- Preview is currently running with `php artisan serve --host=0.0.0.0 --port=8000`; `http://localhost:8000/login` responds with HTTP 200. Login was visually checked in the browser. The user has another local browser window that reached `/dashboard` after login.
- The working tree contains uncommitted business-flow and UI changes. Review `git status` before further edits; do not discard unrelated/pre-existing user changes.

## Imported Borrower Data

- The supplied borrower table has been loaded into the local database: 13 borrowers, 13 borrower login accounts, 13 active loans, 156 installments, and 84 approved payments. All 13 borrower accounts require a password change on first login; no password values are recorded here.
- Source data totals: Rp19,000,000 principal, Rp22,800,000 total receivable, Rp11,240,000 paid, Rp11,560,000 outstanding, and Rp1,330,000 administration fees.
- Opening capital was recorded as Rp19,000,000. Cash ledger ending balance and `cash_accounts.balance` both reconcile to Rp12,570,000.
- The source did not provide actual payment dates. Imported payment records therefore use 26 October 2026 as the import date; this is not represented as the historical payment date.
- Profit is realized only for cleared installments. The 72 paid installments currently recognize Rp1,650,011 total: Owner Rp990,006 and Admin Rp660,005. `LoanService` distributes the 60:40 split using cumulative rounding per loan, so each installment's shares sum to its interest and each fully completed loan's total split is exact. Legacy `alan_profit_amount` and `chalo_profit_amount` columns remain for compatibility.
- Borrower names and passwords are intentionally not copied into this handoff. Do not rerun the one-time import/reset script; it has been removed after use.

## Business Rules

1. 12 installment numbers are stored per loan. They are sequence labels, not calendar requirements.
2. Total receivable is principal plus 20% interest.
3. Administration fee is 7% of principal.
4. The borrower usually receives principal minus administration fee; the example is Rp1,000,000 principal, Rp70,000 administration fee, Rp930,000 net payout.
5. Administration fee is income but must be recorded separately from the main principal cash movement.
6. Payments are recorded by their real cash amount. System nominal is calculated server-side from the oldest outstanding installment; manual nominal is entered separately. Transfer payments require an image proof; cash payments do not.
7. A shortage is recorded as outstanding without changing future installment schedules.
8. An overpayment is allocated to oldest outstanding installments, then optionally to a payment-in-advance balance.
9. Every installment amount remains separate from the actual paid amount.
10. Borrowers may have multiple active loans.
11. Payment approval is required by the Manager before financial effects are posted.
12. Profit from a cleared installment is allocated to the owner (Owner) at 60% and the employee/admin operator (Admin) at 40%.
13. Owner/Admin bonus withdrawals reduce their earned bonus balance. A withdrawal above available balance creates debt/negative-balance treatment and must be visible to the Owner.
14. Bonus and administration amounts may be transferred into modal/cash capital through an explicit financial transaction.
15. Keuangan history and activity logs are separate modules. Financial history records monetary movements; activity logs record operational actions.

## Authentication Rules

- Login identifier accepts borrower code, username, or phone.
- Usernames are `owner` and `admin`; `manager` and `kasir` are internal permission roles, not job titles. Alan is the owner; Chalo is the employee/admin operator.
- Initial staff credentials are development-only and must not be documented in plaintext or used in production.
- Staff accounts currently require a first-login password change (`must_change_password = true`).
- Cashier can reset borrower passwords.
- Staff and borrowers can change their own password.
- Store password only as a Laravel hash.

## Roles

- `manager`: Owner permissions for approvals, dashboard, Owner/Admin bonus visibility, cash transfer to modal, and full borrower management.
- `kasir`: Admin employee permissions for payment/loan entry and limited Owner/Admin bonus visibility according to policy.
- `nasabah`: only own loan and installment information.

## Database Modules

- `users`: identity, username, phone, role, password-change state.
- `borrowers`: borrower identity linked to an optional user account.
- `loans`: principal, administration fee, net payout, interest, receivable, approval state.
- `installments`: 12 numbered installment rows per loan.
- `payments`: real cash input, selected/system nominal source, cash/transfer method, optional private proof path, approval state.
- `approval_requests`: normalized pending action instead of text parsing.
- `cash_transactions`: immutable cash ledger with direction, source, balance after, and references.
- `profit_balances`: staff total earned, withdrawn, transferred to modal, and available balance.
- `profit_transactions`: staff profit ledger.
- `activity_logs`: operational audit separate from cash history.

## Important Design Constraints

- Do not store derived totals as the only source of truth.
- Do not store passwords plaintext.
- Do not trust role, borrower identity, payment status, or approval state from browser requests.
- Use integer minimum currency unit for amounts. Example: Rupiah integer without decimals or string money values.
- Use database transactions and row locks for approvals, payments, cash, and profit updates.
- Existing imported spreadsheet values can be historical baseline data only.
- Spreadsheet horizontal setoran columns must become normalized loan/installment/payment records.
- Password reset actions are activity logs, never password values.

## Verified Commands

```bash
cd ~/projects/DKoper
php artisan --version
php artisan migrate:status
php artisan test
npm run build
```

## Change Completion and Git Procedure

- For each completed implementation task, update this handoff in the same task with the change summary, relevant data/runtime status, and the latest verification results.
- Run focused tests for the change; run the full `php artisan test` suite and `npm run build` when the change affects shared application behavior or frontend assets.
- Before publishing, inspect `git status` and the staged diff. Stage only intended project changes; never stage `.env`, credentials, real borrower data, one-time import files, or other private runtime data.
- Commit the completed changes on the current branch with a clear message and the required Copilot co-author trailer, then push that branch to `origin`. Do not rewrite existing commits. If tests fail, a secret is detected, the remote has diverged, or publishing is otherwise unsafe, stop and document the blocker here rather than forcing a push.
- If the user explicitly asks not to publish a particular change, document that exception and leave it unpushed.

Latest verification in this session:

- `php artisan test`: 33 tests passed, 121 assertions (latest run after cumulative profit-split rounding and imported-ledger reconciliation).
- `npm run build`: passed.
- `php artisan view:cache && php artisan view:clear`: passed.
- `npm run build`: passed.
- After the full sidebar redesign, `php artisan test`: 32 passed, 93 assertions; `npm run build`: passed; Blade view cache compilation: passed.
- After the compact button/card refinement, `npm run build` passed and `php artisan test --filter=AuthenticationTest` passed (4 tests, 8 assertions). Login button label, size, and placement were checked in browser.
- `php artisan test --filter=DashboardAccessTest`: 2 tests passed, 3 assertions after the password-change screen update.
- `php artisan migrate:status`: all migrations ran after applying the pending financial workflow migration.
- `php artisan test --filter=DashboardAccessTest`: passed after the migration was applied.
- `php artisan view:cache && php artisan view:clear`: passed after status badge changes.
- The login preview and forced-password-change page were visually checked in the browser; the latest preview was also checked in a narrow mobile viewport.
- Identity/payment migration applied locally: 13 borrowers, 13 active loans, 84 existing payments, and Rp12,570,000 cash balance remain intact; existing payments default to `cash`.
- Latest `php artisan test`: 36 tests passed, 144 assertions, including system nominal, private transfer proof, Owner/Admin proof viewing, and business-timezone report boundaries.
- Latest `npm run build` and `php artisan view:cache && php artisan view:clear`: passed after the complete redesign and payment updates.
- Browser check on `/login`: theme toggle switched light → dark → light and the page emitted no JavaScript errors.
- Latest `git diff --check` passed. Git reports expected CRLF-to-LF normalization warnings on several existing files.
- After clarifying Alan as Owner and Chalo as the employee/Admin operator, `php artisan test --filter=DashboardAccessTest` passed (3 tests, 10 assertions), `npm run build` passed, and Blade view compilation passed.

## Resume Sequence

1. Confirm folder is open in VS Code as `/home/hatomaule/projects/DKoper`.
2. Review `git status` and preserve all existing worktree changes.
3. Confirm `.env` targets the intended local database; never publish real database or account credentials.
4. Start MariaDB if necessary: `sudo service mariadb start`.
5. Start the preview if it is not already running: `php artisan serve --host=0.0.0.0 --port=8000`, then open `http://localhost:8000/login`.
6. On the current manager browser session, select and save a private password on `/password/change`. The starter password must not be retained for normal use.
7. Review the current business flows against `AI_HANDOFF.md`, especially negative-balance withdrawals, transfer-to-capital semantics, and borrower-role access restrictions.
8. Continue polishing responsive pages and verify all role-specific workflows in browser; the dashboard is currently manager/staff-oriented and should be checked alongside the borrower view.
9. Run `php artisan test` and `npm run build` after changes.
10. Review migration consistency and deployment compatibility with PHP 8.3 before release.

## Deployment Notes

- Keep Laravel `public` as web document root.
- Build assets locally before upload to InfinityFree.
- Set PHP 8.3-compatible code if the hosting version is confirmed.
- Use `DB_CONNECTION=mysql` with MariaDB/MySQL-compatible hosting credentials.
- InfinityFree deployment typically excludes SSH composer, queue workers, and reliable long-running cron.
- Keep `.env`, vendor, node_modules, storage secrets, and production credentials out of GitHub.

## Files to Read First

1. This file.
2. `README.md`.
3. `database/migrations/`.
4. `app/Models/`.
5. `app/Http/Requests/Auth/LoginRequest.php`.
6. `routes/web.php`.
7. `index.baseline.html`.

## Current Stop Point

The core service and approval flows, borrower data import, cumulative profit allocation, identity/payment migration, and requested visual redesign are implemented. The latest full test suite passes (42 tests, 177 assertions), the frontend production build and Blade compilation pass, and the local database import reconciles. The preview server is running at `http://localhost:8000`; the shared browser tab is at `/login`. Alan is the Owner; Chalo is the employee/Admin operator. Usernames are `owner` and `admin`; no password values are saved here.

### Latest follow-up implementation

- Removed the repeated Dashboard welcome/banner and shows the complete summary for both Owner and Admin. Added weekly incoming/outgoing cash charts, twelve-month cash transaction amount bars, and daily/weekly/monthly approved-payment totals.
- Distinguished Owner and Admin bonus summaries, including earned balance and total projected share from active loans. Added subtle individual KPI colors and matching dark-mode styling.
- Cooperative tables now default to 5 rows and provide 5/10/25/50/all selection plus separate table/card modes. Loan listings omit internal loan codes and show borrower names; payment capture actions are labelled “Catat”.
- Owner can change Owner/Admin passwords and borrower passwords; borrower password reset returns to `123456` and requires a change at next login. The Admin cannot change staff passwords.
- Owner-only correction of approved payment amounts reconstructs installment allocation, earned bonus entries, borrower advance balance, loan status, and cash-ledger difference in a database transaction. Corrections are also recorded in activity history.
- Do not re-import, clear, or reset borrower/payment data. No schema migration is required by this follow-up.
- Validation after these changes: `php artisan test` passed (41 tests, 168 assertions); `npm run build` passed; `php artisan view:cache` passed; `git diff --check` passed. Focused payment-correction tests cover installment allocation, earned bonus, borrower advance, cash ledger, and audit log.
- The changes described above are still in the worktree until committed and pushed. Review `git status` and follow the user's established commit/push procedure.

### UI and activity follow-up (26 October 2026)

- Login card was narrowed to a 410px maximum (390px on small screens), with tighter spacing and typography.
- The Owner's top-right account menu now includes a compact “Ganti password Admin” dialog. Admin does not see the action; backend authorization remains Owner-only. Successful password changes return to Dashboard with a confirmation message.
- Withdrawal activity entries now include the name of the bonus account being withdrawn from, so an Admin bonus withdrawal is distinguishable in the activity history. A feature test verifies the withdrawal appears with the Admin name and formatted amount.
- Rupiah entry fields on loan, payment, correction, finance, and bonus forms now group thousands with dots while typing; submission sends only digits to the server. Inputs validate configured minimums and the available bonus maximum.
- Dashboard cash/transaction charts are now visually first, followed by financial and bonus summaries. KPI cards use soft abstract waves instead of oversized circles; chart and period cards have stronger contrast in light and dark themes.

## Known Follow-ups

- Payment capture now requires a specific active loan and outstanding installment. System nominal uses that installment's remaining amount; Admin payments remain pending Owner approval.
- Staff record tables are ordered newest-first by creation time with descending ID as a stable tie-breaker. Borrower loan history also places the latest contract first.
- Paid loans are retained as historical records; they are shown with the “Lunas” status in staff and borrower loan lists, and their installments/payment history remain available.
- The staff Nasabah table and card view show each borrower's combined billed total and paid installment total across active and paid contracts; pending/rejected loan applications are excluded from these financial totals.
- Mobile navigation uses an animated right-side drawer with overlay and Escape/link close behavior. Borrower installment history shows all installments without a page-size selector and defaults to interactive cards on phone-sized screens; the table view remains available via the view switch.
- The local preview server is already bound to `0.0.0.0:8000`; phone access depends on the phone being able to route to this development host. Keep this unauthenticated-transport HTTP preview on a trusted network only; do not expose it publicly.
- Owner correction can change amount, borrower/contract, and installment. Approved payments are reallocated transactionally across both affected loans, including bonus reversals/replay, advance balances, loan statuses, and cash-ledger references. Pending payment corrections also update the approval payload.
- Keuangan now includes a responsive 12-month cash-in/cash-out chart using `Asia/Singapore` month boundaries. Dashboard monthly bars continue to show transaction amounts, not transaction counts.
- Loan/payment rejection now requires a written reason (3–1000 characters); the reason is stored with the approval request and activity audit record. Rejection remains protected by a confirmation dialog.
- Borrower installment details now have table/card modes and a local 5/10/25/all display limit; the default remains five.
- A manual visual browser pass across every page, role, responsive size, and both theme modes remains outstanding; automated role and rendering coverage is included in the feature suite.
- Sidebar bonus label shortened to “Bonus”; bonus withdrawal/transfer controls are compact. Side forms were narrowed to 240–290px at desktop breakpoints to leave more room for their tables. Table cells use smaller, single-line amounts while explanatory details may wrap.
- Side forms now keep content height instead of stretching to the adjacent table; pagination summary text remains one line with a compact, horizontally scrollable page selector on narrow widths.
- Do not reset/re-import local borrower/payment data. No migration is required for this follow-up.
- Latest UI follow-up: dashboard financial summary and report cards display in two columns on phones; bonus/report cards have distinct abstract motifs; the login password eye control has no filled background; dark login fields and account dropdown have explicit contrast; table controls stay on one mobile row with horizontal scrolling when needed; light canvas and form/table panels use subtle colored line patterns.
- Visual browser check confirmed two dashboard KPI cards share one row at 390px. A broader page-by-page review in both themes remains outstanding.
- Latest automated verification: `php artisan test --no-coverage` passed (51 tests, 240 assertions); `npm run build`, `php artisan view:cache`, and `git diff --check` passed.

Remaining follow-up:

- Compare remaining pages against product needs; `index.baseline.html` is a reference only, not a mandatory visual target.
- Cross-check withdrawal behavior with negative balances, transfer-to-modal accounting, and borrower visibility restrictions.
- Review migration/schema consistency and PHP 8.3 deployment readiness.
- Apply the identity/payment migration to the production database before deploying the updated application. Do not rerun the one-time borrower import.
- Confirm production database credentials and rotate any development credentials before deployment.
