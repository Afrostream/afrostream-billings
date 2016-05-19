SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

SET search_path = public, pg_catalog;

INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (1, 1, 1);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (2, 2, 4);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (6, 3, 8);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (3, 4, 5);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (7, 5, 9);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (5, 6, 6);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (8, 7, 10);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (9, 8, 13);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (10, 4, 16);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (11, 3, 18);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (12, 9, 19);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (13, 10, 20);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (14, 11, 21);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (15, 12, 22);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (16, 13, 25);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (17, 14, 26);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (18, 15, 27);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (19, 16, 28);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (20, 17, 29);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (21, 18, 30);
INSERT INTO billing_internal_plans_links (_id, internal_plan_id, provider_plan_id) VALUES (22, 19, 31);

SELECT setval('billing_internal_plans_links__id_seq', (SELECT MAX(_id) FROM billing_internal_plans_links));