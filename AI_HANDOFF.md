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

- Repository cloned and Laravel installed.
- Breeze and Tailwind assets installed; Vite build has completed once.
- Local MariaDB database `dkoper` is available.
- Database user is local-only: `dkoper`.
- Laravel migration skeletons and revisions have been created and migrated.
- Breeze login has been modified for ID, username, or phone identifier.
- Password-first-change middleware and routes have been added.
- Alan and Chalo staff seeder exists.
- DashboardController and KPI dashboard Blade view have been added.
- Model relations for users, borrowers, loans, payments, cash, and profit have been partially implemented.
- Default staff/password is not to be used outside local development or reset immediately after deployment.

## Business Rules

1. 12 installment numbers are stored per loan. They are sequence labels, not calendar requirements.
2. Total receivable is principal plus 20% interest.
3. Administration fee is 7% of principal.
4. The borrower usually receives principal minus administration fee; the example is Rp1,000,000 principal, Rp70,000 administration fee, Rp930,000 net payout.
5. Administration fee is income but must be recorded separately from the main principal cash movement.
6. Payments are recorded by their real cash amount. System nominal and manual nominal are separate input choices.
7. A shortage is recorded as outstanding without changing future installment schedules.
8. An overpayment is allocated to oldest outstanding installments, then optionally to a payment-in-advance balance.
9. Every installment amount remains separate from the actual paid amount.
10. Borrowers may have multiple active loans.
11. Payment approval is required by the Manager before financial effects are posted.
12. Profit from a cleared installment goes to staff profit balances: Alan 60%, Chalo 40%.
13. Staff withdrawals reduce earned staff profit. A withdrawal above available balance creates debt/negative-balance treatment and must be visible to the Manager.
14. Bonus and administration amounts may be transferred into modal/cash capital through an explicit financial transaction.
15. Keuangan history and activity logs are separate modules. Financial history records monetary movements; activity logs record operational actions.

## Authentication Rules

- Login identifier accepts borrower code, username, or phone.
- Staff usernames are generated or seeded as `alan` and `chalo`.
- Default password is `123456`.
- All first-login accounts must change the password.
- Cashier can reset borrower passwords.
- Staff and borrowers can change their own password.
- Store password only as a Laravel hash.

## Roles

- `manager`: approvals, dashboard, staff profit visibility, cash transfer to modal, full borrower management.
- `kasir`: payment/loan entry, limited staff visibility according to policy.
- `nasabah`: only own loan and installment information.

## Database Modules

- `users`: identity, username, phone, role, password-change state.
- `borrowers`: borrower identity linked to an optional user account.
- `loans`: principal, administration fee, net payout, interest, receivable, approval state.
- `installments`: 12 numbered installment rows per loan.
- `payments`: real cash input, selected/system nominal source, approval state.
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
php artisan test --compact
npm run build
```

## Resume Sequence

1. Confirm folder is open in VS Code as `/home/hatomaule/projects/DKoper`.
2. Restore `.env` from `.env.example` if needed; never publish real production credentials.
3. Start MariaDB: `sudo service mariadb start`.
4. Run migrations: `php artisan migrate --force`.
5. Seed staff only on local/test database: `php artisan db:seed --class=StaffAccountSeeder`.
6. Implement login identifier and force-password-change behavior.
7. Implement borrower, loan, installment, payment, approval, cash, and profit services.
8. Import/seed baseline spreadsheet after field mapping is confirmed.
9. Convert the provided `index.baseline.html` visual design to Blade.
10. Run tests, build assets, commit, and push.

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

The database foundation is present locally, and migration revision files are applied. Remaining work is the executable business service layer, authenticated dashboard, borrower/loan/payment workflows, seed/import, and final UI conversion.
