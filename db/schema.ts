import { pgTable, serial, text, integer, timestamp, boolean, jsonb, doublePrecision, uniqueIndex, index, check } from 'drizzle-orm/pg-core';
import { sql } from 'drizzle-orm';
export const users = pgTable('users', {
  id: text().primaryKey(), name: text().notNull(), email: text().notNull(),
  role: text().notNull().default('field_officer'), active: boolean().notNull().default(true),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
}, t => [check('valid_user_role', sql`${t.role} in ('administrator','field_officer')`)]);
export const beneficiaries = pgTable('beneficiaries', {
  id: serial().primaryKey(), fullName: text('full_name').notNull(), nationalId: text('national_id').notNull(),
  householdSize: integer('household_size').notNull(), campLocation: text('camp_location').notNull(),
  phone: text().notNull().default(''), notes: text().notNull().default(''),
  status: text().notNull().default('pending_review'), registeredBy: text('registered_by').references(() => users.id),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
  updatedAt: timestamp('updated_at', { withTimezone: true }).notNull().defaultNow(),
}, t => [uniqueIndex('beneficiary_national_id').on(t.nationalId), index('beneficiary_camp').on(t.campLocation),
  check('positive_household', sql`${t.householdSize} > 0`),
  check('valid_record_status', sql`${t.status} in ('pending_review','verified','duplicate_flagged','merged_inactive')`)]);
export const clusters = pgTable('match_clusters', {
  id: serial().primaryKey(), signature: text().notNull().unique(), score: doublePrecision().notNull(),
  status: text().notNull().default('pending'), keepId: integer('keep_id').references(() => beneficiaries.id),
  reviewedBy: text('reviewed_by').references(() => users.id),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
  reviewedAt: timestamp('reviewed_at', { withTimezone: true }),
}, t => [check('valid_match_status', sql`${t.status} in ('pending','confirmed_duplicate','false_positive')`)]);
export const members = pgTable('cluster_members', {
  id: serial().primaryKey(), clusterId: integer('cluster_id').notNull().references(() => clusters.id, { onDelete: 'cascade' }),
  beneficiaryId: integer('beneficiary_id').notNull().references(() => beneficiaries.id),
}, t => [uniqueIndex('cluster_member_unique').on(t.clusterId, t.beneficiaryId)]);
export const auditLog = pgTable('audit_log', {
  id: serial().primaryKey(), userId: text('user_id').references(() => users.id), action: text().notNull(), details: text().notNull(),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});
export const reports = pgTable('reports', {
  id: serial().primaryKey(), userId: text('user_id').references(() => users.id), type: text().notNull(),
  camp: text().notNull().default(''), dateFrom: text('date_from').notNull().default(''), dateTo: text('date_to').notNull().default(''),
  payload: jsonb().$type<{columns: string[], rows: (string | number)[][]}>().notNull(),
  createdAt: timestamp('created_at', { withTimezone: true }).notNull().defaultNow(),
});
