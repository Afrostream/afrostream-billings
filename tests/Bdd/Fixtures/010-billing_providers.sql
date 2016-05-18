SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: billing_providers; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO billing_providers (_id, name) VALUES (1, 'celery');
INSERT INTO billing_providers (_id, name) VALUES (2, 'recurly');
INSERT INTO billing_providers (_id, name) VALUES (3, 'gocardless');
INSERT INTO billing_providers (_id, name) VALUES (4, 'bachat');
INSERT INTO billing_providers (_id, name) VALUES (5, 'idipper');
INSERT INTO billing_providers (_id, name) VALUES (6, 'afr');
INSERT INTO billing_providers (_id, name) VALUES (7, 'cashway');
INSERT INTO billing_providers (_id, name) VALUES (8, 'orange');

SELECT setval('billing_providers__id_seq', 8);
