<?php
declare(strict_types=1);

/**
 * Database settings. Fill these four values from the hosting control panel, under
 * "MySQL Databases" -> your database. Note that the MySQL username is NOT your
 * Member Area username: it is the full prefixed name, normally identical to the
 * database name, e.g. ifo_43425406_rescueplan.
 */
const DB_HOST = 'REPLACE_WITH_YOUR_SQL_HOST';
const DB_PORT = 3306;
const DB_NAME = 'REPLACE_WITH_YOUR_DATABASE_NAME';
const DB_USER = 'REPLACE_WITH_YOUR_DATABASE_USERNAME';
const DB_PASS = 'REPLACE_WITH_YOUR_DATABASE_PASSWORD';

/** Sessions are dropped after this many minutes of inactivity. */
const SESSION_TIMEOUT_MINUTES = 15;

/**
 * Shared hosting settings.
 *
 * ALLOW_HOSTED_INSTALL lets install.php run from outside localhost, which is how a
 * hosted install has to work. Set it to true only while installing, then back to false
 * (or delete install.php): it can wipe and re-seed the live registry.
 *
 * SHOW_ERROR_DETAIL prints the real database error on the failure page. Useful while
 * the connection is being fixed, but the text names the database account, so leave it
 * false on a public site.
 */
const ALLOW_HOSTED_INSTALL = true;
const SHOW_ERROR_DETAIL = true;

/** Deduplication pipeline tuning (identical to the Python reference engine). */
const SIMILARITY_THRESHOLD = 0.88;
const NAME_WEIGHT = 0.45;
const NATIONAL_ID_WEIGHT = 0.55;

const APP_NAME = 'RescuePlan Kenya';

const ROLE_ADMIN = 'administrator';
const ROLE_FIELD = 'field_officer';

const RECORD_STATUSES = ['pending_review', 'verified', 'duplicate_flagged', 'merged_inactive'];

const REPORT_TYPES = [
    'deduplication_summary' => 'Deduplication Summary Report',
    'distribution_eligibility' => 'Distribution Eligibility Report',
    'anomaly' => 'Anomaly Report',
];
