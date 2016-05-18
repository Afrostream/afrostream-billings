SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

SET search_path = public, pg_catalog;

INSERT INTO billing_subscriptions (_id, subscription_billing_uuid, providerid, userid, planid, creation_date, updated_date, sub_uuid, sub_status, sub_activated_date, sub_canceled_date, sub_expires_date, sub_period_started_date, sub_period_ends_date, sub_collection_mode, update_type, updateid, deleted) VALUES (1, '5995e90f-bbe0-cde8-9314-133ba3db7c10', 1, 1, 6, '2015-12-30 10:37:23.89884+01', '2015-12-30 10:37:23.89884+01', 'C0B86866-39F7-ED5E-33DA-321D3C4D0862', 'canceled', '2015-09-01 01:00:00+02', NULL, NULL, '2015-09-01 01:00:00+02', '2016-09-01 01:00:00+02', 'manual', 'import', 0, false);
INSERT INTO billing_subscriptions (_id, subscription_billing_uuid, providerid, userid, planid, creation_date, updated_date, sub_uuid, sub_status, sub_activated_date, sub_canceled_date, sub_expires_date, sub_period_started_date, sub_period_ends_date, sub_collection_mode, update_type, updateid, deleted) VALUES (2, '3537b8e6-f530-1362-8dea-d87d68e618cc', 1, 2, 6, '2015-12-30 10:37:24.251559+01', '2015-12-30 10:37:24.251559+01', '0596D0FF-1C2B-A6C9-2C29-C1D7759E2CB8', 'active', '2015-09-01 01:00:00+02', NULL, NULL, '2015-09-01 01:00:00+02', '2016-09-01 01:00:00+02', 'manual', 'import', 0, false);

SELECT setval('billing_subscriptions__id_seq', 3);