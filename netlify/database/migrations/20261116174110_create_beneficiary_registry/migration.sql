CREATE TABLE "audit_log" (
	"id" serial PRIMARY KEY,
	"user_id" text,
	"action" text NOT NULL,
	"details" text NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "beneficiaries" (
	"id" serial PRIMARY KEY,
	"full_name" text NOT NULL,
	"national_id" text NOT NULL,
	"household_size" integer NOT NULL,
	"camp_location" text NOT NULL,
	"phone" text DEFAULT '' NOT NULL,
	"notes" text DEFAULT '' NOT NULL,
	"status" text DEFAULT 'pending_review' NOT NULL,
	"registered_by" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "positive_household" CHECK ("household_size" > 0),
	CONSTRAINT "valid_record_status" CHECK ("status" in ('pending_review','verified','duplicate_flagged','merged_inactive'))
);
--> statement-breakpoint
CREATE TABLE "match_clusters" (
	"id" serial PRIMARY KEY,
	"signature" text NOT NULL UNIQUE,
	"score" double precision NOT NULL,
	"status" text DEFAULT 'pending' NOT NULL,
	"keep_id" integer,
	"reviewed_by" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"reviewed_at" timestamp with time zone,
	CONSTRAINT "valid_match_status" CHECK ("status" in ('pending','confirmed_duplicate','false_positive'))
);
--> statement-breakpoint
CREATE TABLE "cluster_members" (
	"id" serial PRIMARY KEY,
	"cluster_id" integer NOT NULL,
	"beneficiary_id" integer NOT NULL
);
--> statement-breakpoint
CREATE TABLE "reports" (
	"id" serial PRIMARY KEY,
	"user_id" text,
	"type" text NOT NULL,
	"camp" text DEFAULT '' NOT NULL,
	"date_from" text DEFAULT '' NOT NULL,
	"date_to" text DEFAULT '' NOT NULL,
	"payload" jsonb NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "users" (
	"id" text PRIMARY KEY,
	"name" text NOT NULL,
	"email" text NOT NULL,
	"role" text DEFAULT 'field_officer' NOT NULL,
	"active" boolean DEFAULT true NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "valid_user_role" CHECK ("role" in ('administrator','field_officer'))
);
--> statement-breakpoint
CREATE UNIQUE INDEX "beneficiary_national_id" ON "beneficiaries" ("national_id");--> statement-breakpoint
CREATE INDEX "beneficiary_camp" ON "beneficiaries" ("camp_location");--> statement-breakpoint
CREATE UNIQUE INDEX "cluster_member_unique" ON "cluster_members" ("cluster_id","beneficiary_id");--> statement-breakpoint
ALTER TABLE "audit_log" ADD CONSTRAINT "audit_log_user_id_users_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users"("id");--> statement-breakpoint
ALTER TABLE "beneficiaries" ADD CONSTRAINT "beneficiaries_registered_by_users_id_fkey" FOREIGN KEY ("registered_by") REFERENCES "users"("id");--> statement-breakpoint
ALTER TABLE "match_clusters" ADD CONSTRAINT "match_clusters_keep_id_beneficiaries_id_fkey" FOREIGN KEY ("keep_id") REFERENCES "beneficiaries"("id");--> statement-breakpoint
ALTER TABLE "match_clusters" ADD CONSTRAINT "match_clusters_reviewed_by_users_id_fkey" FOREIGN KEY ("reviewed_by") REFERENCES "users"("id");--> statement-breakpoint
ALTER TABLE "cluster_members" ADD CONSTRAINT "cluster_members_cluster_id_match_clusters_id_fkey" FOREIGN KEY ("cluster_id") REFERENCES "match_clusters"("id") ON DELETE CASCADE;--> statement-breakpoint
ALTER TABLE "cluster_members" ADD CONSTRAINT "cluster_members_beneficiary_id_beneficiaries_id_fkey" FOREIGN KEY ("beneficiary_id") REFERENCES "beneficiaries"("id");--> statement-breakpoint
ALTER TABLE "reports" ADD CONSTRAINT "reports_user_id_users_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users"("id");