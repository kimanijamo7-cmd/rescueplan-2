# RescuePlan Kenya

The hosted application uses a Netlify Server, Netlify Database (Postgres), and Netlify Identity. The original PHP files are retained as a reference; they are not included in the deployed public directory.

## Deployment

Netlify installs dependencies, runs the configured Vite build, publishes `public`, and deploys `netlify/server/index.ts`. The database schema is defined in `db/schema.ts`; generated migrations in `netlify/database/migrations` are applied by Netlify during deployment. Do not run migration SQL or `drizzle-kit push` manually.

The database starts empty. There are no shared demo accounts or default passwords. Existing MySQL data, if any, is not automatically migrated. Beneficiary CSV files can be imported by an administrator.

## First administrator

Identity handles email confirmation, password reset, and invitations. To establish the first administrator, open this project's Netlify dashboard, go to **Identity**, invite the administrator's email, and add the `admin` role on that user's detail page. Accept the invitation and set a password using its email link. A verified Identity `admin` or `administrator` role grants administrator access. User-editable metadata cannot grant roles.

Other staff can register as field officers using their email address. They appear under **Users** after confirming their email and logging in. Any active staff member can then assign application roles and deactivate accounts (but not change their own). Roles assigned in Netlify Identity are controlled in the dashboard; application role changes do not remove those Identity roles.

## Features

- Beneficiary registration, validation, editing, verification, search, camp/status filters, pagination, and CSV export.
- Administrator CSV/TSV import, validated atomically with existing national IDs skipped.
- Phonetic blocking, weighted Jaro-Winkler similarity, transitive clustering, and administrator review. Any active staff member, including field officers, can run a deduplication scan, review and resolve duplicate clusters, manage staff accounts under **Users**, and view or export the audit log. CSV import remains administrator-only. Confirmed duplicates are deactivated rather than deleted; false positives are retained as reviewed decisions.
- Saved deduplication, distribution eligibility, and anomaly reports with camp and registration-date filters.
- Staff role and activation controls and a permanent audit log with CSV export.
- Legacy `.php` page links redirect to the corresponding application screen. PHP source, installers, and SQL source are not publicly served.

Interactive duplicate scans support up to 5,000 active records. Imports support up to 2,000 rows and 1.5 MB per file. The duplicate review screen shows the latest 100 clusters. Reports use UTC registration date boundaries.

## Checks

Run `npm run check` for TypeScript validation and `npm test` for matching, input validation, CSV safety, authentication requirements, cross-origin write rejection, and legacy routing checks. Authenticated browser and database flows also require a deployed environment with Identity enabled and database migrations applied.
