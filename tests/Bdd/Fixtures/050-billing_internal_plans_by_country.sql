SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

SET search_path = public, pg_catalog;

INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (1, 1, 'FR');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (2, 2, 'FR');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (3, 3, 'FR');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (4, 4, 'FR');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (5, 5, 'FR');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (6, 6, 'FR');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (7, 7, 'FR');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (8, 8, 'FR');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (9, 9, 'FR');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (10, 10, 'FR');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (11, 11, 'FR');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (12, 12, 'FR');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (13, 13, 'FR');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (14, 14, 'FR');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (15, 15, 'FR');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (16, 16, 'FR');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (17, 1, 'BE');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (18, 2, 'BE');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (19, 3, 'BE');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (20, 4, 'BE');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (21, 5, 'BE');
INSERT INTO billing_internal_plans_by_country (_id, internal_plan_id, country) VALUES (22, 6, 'BE');

SELECT setval('billing_internal_plans_by_country__id_seq', (SELECT MAX(_id) FROM billing_internal_plans_by_country));