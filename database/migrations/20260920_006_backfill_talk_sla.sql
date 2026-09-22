-- Moves Talk | SLA baseline
-- Backfill existing tickets so the SLA indicators have a persisted first-response deadline.

UPDATE talk_tickets
SET sla_due_at = DATE_ADD(COALESCE(queued_at, created_at), INTERVAL 15 MINUTE)
WHERE sla_due_at IS NULL;

UPDATE talk_tickets
SET last_activity_at = COALESCE(last_activity_at, updated_at, created_at)
WHERE last_activity_at IS NULL;
