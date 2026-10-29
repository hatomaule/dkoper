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
- Local development port allocation: DKoper uses `8000`, Ekosan uses `8001`, and KosKosan uses `8002`. Start each project from its own directory and avoid taking another project's assigned port.
- The working tree contains uncommitted business-flow and UI changes. Review `git status` before further edits; do not discard unrelated/pre-existing user changes.

## FIDES Platform Foundation

- Platform branding is now FIDES. The web app still uses one codebase and keeps the existing DKoper data as the first cooperative database.
- Platform mode is opt-in (`FIDES_ENABLED=false` by default), so existing local sign-in and database routing remain unchanged until the central catalog is installed, the current database is registered, account links are indexed, and the environment is explicitly enabled.
- The central catalog is configured through the `platform` connection (`FIDES_PLATFORM_DB_*`). It stores cooperative records, encrypted optional tenant connection overrides, login identifier-to-tenant links, and Super Admin accounts. The default platform catalog is a separate local SQLite file; production should configure a dedicated central MySQL/MariaDB database for shared deployment.
- Each tenant's business data stays in its own database. Tenant context is restored from the authenticated session before implicit route-model binding and application queries. Login accepts the existing username/phone/borrower-code identifiers, resolves the correct tenant from the central index, and asks the user to choose a cooperative if the same identifier/password authenticates in multiple spaces.
- Tenant user and borrower identity changes update the central login index after the tenant transaction commits. Initial existing users are indexed with the registration command. Transfer proof uploads are stored under per-cooperative paths while legacy proof paths remain readable.
- The Super Admin portal is available at `/platform`. Bootstrap the central schema with `php artisan fides:install`; create the initial platform account with `php artisan fides:make-super-admin <username> <name> --email=<email>` (the command prompts for a password); register the existing database and index its users with `php artisan fides:register-current "<cooperative name>" <slug>`. Only after these steps should `FIDES_ENABLED=true` be configured and Laravel configuration caches refreshed.
- Super Admin can create a new cooperative from the portal. Provisioning creates a separate MySQL/MariaDB database when the configured database user has permission, or a dedicated SQLite file when the MySQL server rejects `CREATE DATABASE` with permission error 1044. Both receive the same application migrations and isolated Owner/Admin accounts with forced password change. The generated Admin password is shown once. Existing databases/files are never overwritten.
- The Super Admin portal provides an account-only dashboard grouped by cooperative, cooperative/user counts, and Owner/Admin account management (name, username, role, and password reset). It intentionally does not expose transaction or financial data. SQLite tenant files are stored under `storage/fides-tenants` and require persistent writable storage and inclusion in backup/recovery procedures.
- The first platform implementation routes by account-to-tenant mapping after shared login. Public subdomain/DNS/TLS configuration, aggregate comparative reports, per-tenant credential configuration in the portal, automated migration/rollback tooling, and production backup/recovery procedures remain follow-up deployment work. Do not enable the platform in production before provisioning and isolation checks are complete.
- Platform verification: latest full suite passed (73 tests, 417 assertions), including SQLite permission-fallback provisioning, cooperative account management, tenant login, and isolation. `npm run build`, `php artisan view:cache`, PHP syntax checks, and targeted `git diff --check` passed. Platform activation and provisioning have not been run against the local business database.
- `phpunit.xml` explicitly sets `FIDES_ENABLED=false` for deterministic legacy test behavior; the FIDES integration tests opt in to platform mode in their test setup. This avoids local `.env` values changing the authentication branch in unrelated tests. Latest full suite: 73 tests, 417 assertions.

## Chat UX Redesign Direction

- Default experience should start in a mailbox-style list view, not in a full conversation panel.
- A back button must return the user from the active conversation to the list of chats.
- The user must be able to switch among multiple conversations (for example, different owners or counterparties) without losing the list state.
- Preserve the legacy behavior of supporting direct property/owner conversations, admin/support chats, private attachments, and personal message folders when they are still useful.
- Keep the system simple for new users: the inbox should show recent conversations, unread counters, avatars/initials, optional status labels, and a compact preview of the last message.
- On desktop, prefer a two-column layout: left column for conversation list and right column for active thread. On mobile, use a full-width list view with a dedicated back navigation into the active conversation.
- The interaction model should remain close to the working behavior in the legacy app, but the UI should not carry over the old visual clutter or heavy complexity.

## Classic vs Pro Theme Direction

- Classic: clean and minimal, light background, restrained cards, clear hierarchy, straightforward typography, simple button states.
- Pro: dark premium appearance with near-black/navy surfaces, compact data cards, orange accent color, subtle gradients, stronger contrast, and tighter information density.
- Treat Classic and Pro as presentation modes, not a brand copy of the legacy reference. Use the provided screenshots as inspiration only.
- The user specifically wants the Pro appearance to feel like a dark cPanel-style dashboard with orange accents and compact cards, but without copying identity, assets, or branding from external systems.
- Theme selection should be user-controlled or settings-based unless product rules later require role-based presentation.

## Business Logic Migration Principle

- Keep the legacy business logic that is still valid, secure, and maintainable.
- Replace or redesign flows only when they are inaccurate, unsafe, hard to maintain, or inconsistent with the new product requirements.
- The established legacy behavior to preserve includes owner submission and admin review for property publication, booking approval/payment proof workflows, and invoice/payment activity mapping.
- Where the current legacy flow is incomplete or unclear, define the desired behavior explicitly before implementation rather than copying old assumptions blindly.

## Current Safe Next Execution Step

- An authenticated-only interactive UI mockup is available at `/chat-preview` (`prototype.chat.preview`): it switches between Classic and Pro, supports conversation search/filter/selection, marks unread chats as read, has mobile inbox/thread navigation, and accepts mock messages in client-side state.
- The mockup uses sample content only. It is not connected to a message database, delivery system, notifications, or real file uploads; do not present it as production chat functionality.
- Focused validation: `php artisan test --filter=ChatPreviewTest --no-coverage` passes (2 tests, 8 assertions); `npm run build` succeeds.
- Before building production chat, confirm message retention, recipient/owner scope, unread state synchronization, attachment policy/storage, and whether real-time delivery is required.
- Keep the app in a safe, non-production state until the product rules and environment are confirmed.

## Imported Borrower Dat
a
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
5. Start the preview if it is not already running with `./start-local.sh`, then open `http://localhost:8000/login`.
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

The core service and approval flows, borrower data import, cumulative profit allocation, identity/payment migration, and requested visual redesign are implemented. The latest full test suite passes (42 tests, 177 assertions), the frontend production build and Blade compilation pass, and the local database import reconciles. DKoper uses local port `8000`; start it with `./start-local.sh` when needed. Alan is the Owner; Chalo is the employee/Admin operator. Usernames are `owner` and `admin`; no password values are saved here.

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
- `./start-local.sh` binds the development preview to `127.0.0.1:8000`. Do not expose the unauthenticated-transport HTTP preview publicly.
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

### DKoper bonus and FIDES hardening follow-up (28 October 2026)

- Bonus withdrawals now allow an Admin to withdraw only their own bonus; the Owner can record staff withdrawals. A withdrawal may leave a visible negative bonus balance. Owner transfers to capital cannot exceed available bonus and do not double-count cash already recorded from loan payments.
- Tenant restoration purges and removes the active `fides_tenant` connection configuration. Provisioning validates sanitized slugs and verifies SQLite database file creation; isolated temporary-SQLite tests cover independent cooperatives, overwrite refusal, migration failure state, and invalid slugs.
- Cooperative selection login test now simulates a provisioned tenant connection while retaining an intentionally unprovisioned fixture database name; this exercises the selection/authentication flow without creating databases.
- Platform cooperative names and database paths wrap on narrow screens. Login was checked at 390px in light and dark themes with no horizontal overflow. Authenticated platform/staff pages still need visual review with a safe test account.
- Verification completed: `php artisan test --no-coverage` passed (65 tests, 338 assertions); `npm run build`, `php artisan view:cache`, and the focused `git diff --check` passed. Run tests that share `dkoper_test` sequentially, not in parallel.
- No production/business database provisioning, migration, or data changes were performed. The worktree remains uncommitted; preserve all existing local changes and review `git status` before any commit/push.

### FIDES platform management and dashboard follow-up (29 October 2026)

- Added the FIDES platform portal with isolated per-cooperative databases, account directory links, cooperative provisioning, cooperative selection during login, and the synthetic Cair Dana demo tenant. Production/business databases were not provisioned, migrated, or modified during this work.
- Platform account management is grouped through a sidebar submenu with a dynamic entry per cooperative. Account cards default to card mode, show fixed identity, and allow role changes for Owner/Admin; reset password, activation, and account deletion use confirmation dialogs. Accounts with operational history cannot be deleted.
- Cooperative management supports search, status filtering, pagination, card/table icon switch, and registration through a modal. Permanent deletion requires exact cooperative-name confirmation and a separate acknowledgement; the controller rejects shared application/catalog databases, verifies tenant DB names/paths, marks deletion failures, and logs failed database drops. A separate SQLite tenant deletion and shared-database refusal are covered by feature tests.
- Platform dashboard shows account/borrower comparison bars above the KPI cards. Superuser password change is in a modal; successful/validation flows retain explicit feedback.
- Account and cooperative listings refresh every 60 seconds only when the page is visible and no form is dirty, dialog is open, or field is focused.
- Latest complete verification: `php artisan test --no-coverage` passed (83 tests, 512 assertions). `npm run build`, `php artisan view:cache`, PHP syntax checks, and focused `git diff --check` passed. The FIDES tenant suite has 14 tests covering the account submenu, role-only edits, account/cooperative status, protected history, permanent isolated-SQLite tenant deletion, and refusal to delete the shared application database.
- Preserve `storage/fides-tenants` and `database/fides-platform.sqlite` as runtime data; these are intentionally ignored by Git. Never run demo seeding against a real tenant or drop shared DKoper/application databases.

### DKoper workflow and notification follow-up (29 October 2026)

- Tightened the dashboard/header spacing and moved the cash charts to the top. The dashboard now shows monthly (12 months) and yearly (5 years) transaction amounts side by side, with explicit legends and no chart-panel background.
- Borrower registration, loan, payment, and cash-adjustment forms open in floating dialogs from the corresponding table toolbar. Modal triggers resolve their `#dialog-id` selectors correctly; validation failures reopen their dialog. List totals remain above the data and table row separators are more visible.
- Borrower loan history is card-only, with installments ordered from sequence 1. Staff retain their existing table/card controls. Password controls use “Ganti Password” and the confirmation dialog requires an explicit checkbox.
- Admin loan applications remain pending until Owner approval and do not appear in the primary loan ledger until approved. Rejected applications stay in approval/audit history but do not enter cash transactions or the main loan list. Admin-created borrower accounts do not require Owner approval.
- Added private in-app notifications for relevant account, loan, payment, cash, and bonus events. The navigation bell shows a read-state badge and refreshes every 60 seconds; notifications can be opened and marked read only by their recipient.
- The profile avatar and name are one menu trigger across roles. Added migration `2026_10_29_000000_create_user_notifications_table`; it was applied to the active DKoper database on 29 October after user approval. The separate Cair Dana SQLite tenant was not migrated at that time; apply the migration there before using its notification UI.
- Verification: `php artisan test --no-coverage` passed (89 tests, 563 assertions); `npm run build`, `php artisan view:cache`, and `git diff --check` passed. The borrower/dashboard tests were updated to verify the card-only borrower page and monthly/yearly charts instead of the superseded table switch and weekly chart.

### UI corrections follow-up (29 October 2026)

- Moved the theme, notification, and profile controls into the same page-header row as its title. The profile control retains its avatar/name grouping and fits the header at smaller widths.
- Fixed the modal-launch bug: `data-open-dialog` values contain `#`, but the previous JavaScript passed the selector as an element ID, so no dialog opened. The handler now resolves the hash-prefixed ID, reports a missing/invalid dialog, and opens it. Text-only styling is confined to actions inside the top-right profile popup; toolbar actions remain proper buttons.
- Resized loan cards and installment cells into a readable three-column sequence layout; amounts no longer wrap digit-by-digit. Card-mode parent surfaces are transparent in light and dark themes while individual cards remain elevated. Applied the shared background removal to cooperative and platform card-mode wrappers.
- Verification for this follow-up: full `php artisan test --no-coverage` passed (89 tests, 567 assertions); 23 targeted UI/role tests also passed. `npm run build`, `php artisan view:cache`, PHP syntax checks, and `git diff --check` passed. Browser checks confirmed the hash-prefixed toolbar trigger opens its dialog and the close control dismisses it. Header/card CSS was checked at 1085px and 390px widths; at 390px the page did not overflow horizontally and the title and controls remained in one row. Dark card-mode wrapper is transparent, installment cells use three columns, amounts do not wrap, and individual cards retain an elevated shadow.

### Loan card, archive, and correction follow-up (30 October 2026)

- Removed the aggregate total card from loan card mode while retaining the aggregate ledger row in table mode. Each loan card displays its 12 installments in a horizontally scrollable sequence; card-mode parent panels stay transparent while individual cards remain elevated.
- Removed the cash-flow chart only from the finance view. The balance summary and transaction table/cards remain; the controller still computes the report data and no financial records are changed by hiding the chart.
- Added Owner-only loan correction and archival. Correction keeps the borrower identity fixed, validates the principal/fee/interest against all 12 scheduled amounts, replays approved payments, and records the old/new values in the activity log. Installments, profit bonuses, borrower advances, and cash disbursement differences are reconstructed through append-only correction transactions.
- Loan archival is a soft delete: paid loans may be archived, as may pending loans without payments or newly created active loans (within 24 hours) without any payment record. Active loans with payments and older unpaid active loans cannot be archived. Payments, installments, cash/profit ledgers, approvals, and activity logs are retained; historical model relations include archived loans.
- Borrower advance corrections now append signed `amount_delta` entries instead of deleting the prior advance rows. The migration drops the single-advance-per-payment constraint while retaining an index; rollback is refused when correction history would be lost.
- Added migrations `2026_10_30_000000_add_soft_deletes_to_loans` and `2026_10_30_000001_preserve_borrower_advance_history`. Both were applied to the active DKoper database and registered Cair Dana SQLite tenant; the latter also received the pending notification migration.
- Verification: `php artisan test --no-coverage` passed (94 tests, 596 assertions), including tenant-prefixed migration coverage. Targeted loan/payment/role tests also passed. `npm run build`, `php artisan view:cache`, PHP syntax checks, and `git diff --check` passed.

### Per-loan vertical card layout (29 October 2026)

- Updated only the loan card view to match the supplied reference: each loan card shows borrower name, principal, installments 1–12 in vertical rows, then that loan's total receivable, allocated paid total, outstanding amount, and approving authority. No portfolio-wide totals are included in cards; the aggregate stays in table mode.
- Card amounts are derived from that loan's `total_receivable` and its own 12 installment `paid_amount`/`outstanding_amount` values. Rows highlight on hover; card surfaces use the warm light reference palette and a dark-theme equivalent.
- Verification: focused loan-card/Owner access feature test passed (19 assertions); `npm run build`, `php artisan view:cache`, and `git diff --check` passed.

### Password visibility icon alignment (29 October 2026)

- Standardized password reveal controls across cooperative, login, and FIDES platform forms by placing each eye icon inside a wrapper aligned to its own input instead of centering against the full label. Existing input top spacing is preserved.
- Verification: authentication, profile, and password-management tests passed (11 tests, 41 assertions); Vite build passed. Browser check on the login form confirmed the eye center aligns with the input center (0px vertical offset) and show/hide restores the correct input type and accessible label.

### Loan deletion and layout refinement (30 October 2026)

- Renamed loan archival to deletion in the Owner UI, route, service, and activity log (`loan.deleted`). Deletion remains a soft delete; it does not remove payments, installments, cash/profit records, or activity history. A required confirmation checkbox is enforced in both the dialog and request validation.
- Added an Owner-only “Riwayat” page and desktop/mobile navigation links for soft-deleted loans, with retained financial totals and deletion timestamps. Admin access is denied.
- Changed loan actions to compact “Koreksi” and “Hapus” buttons, removed the authority row from per-loan cards, tightened their spacing, and added a divider after installment 12.
- Narrowed the correction dialog and stacked its fields and installment inputs vertically, including on mobile. Adjusted Dashboard chart panels to fill the available content width, keep values readable, and stack at narrow breakpoints.
- Verification: `php artisan test --no-coverage` passed (95 tests, 625 assertions); `npm run build`, `php artisan view:cache`, PHP syntax checks, and `git diff --check` passed.

### Platform page top spacing (29 October 2026)

- Reduced the top padding of the shared FIDES Platform content container while retaining its existing horizontal and bottom spacing. The change applies consistently to Dashboard, cooperative management/details, and account management pages.
- Verification: `php artisan test tests/Feature/FidesTenantLoginTest.php` passed (14 tests, 136 assertions); `php artisan view:cache` and `git diff --check` passed.

### Platform dashboard chart clarity (29 October 2026)

- Removed the large filled panel and individual filled tiles behind the cooperative summary chart.
- Replaced the two unlabeled horizontal tracks with paired vertical bars. Each bar has an explicit “Akun pengguna” or “Nasabah” label, a color key, and its numeric total; the chart description explains relative bar height. Scale is based on the nine visible cooperatives.
- Verification: focused platform dashboard test passed (39 assertions); Vite production build, Blade view cache, and `git diff --check` passed.

### Platform Owner/Admin account management (29 October 2026)

- Removed role-changing controls from account cards and tables; existing account roles are immutable through the account update action.
- Added a Create action before account search for creating Owner or Admin accounts with a required initial password and forced password change at first login.
- Limited account management listings and account update/reset/activation/deletion endpoints to Owner/Admin roles. Borrower accounts remain in their cooperative and cannot be viewed or changed from the Super Admin account-management page.
- Verification: `php artisan test tests/Feature/FidesTenantLoginTest.php` passed (15 tests, 151 assertions); Vite build, Blade view cache, PHP syntax checks, and `git diff --check` passed.
