SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

SET search_path = public, pg_catalog;

INSERT INTO billing_processing_logs (_id, providerid, processing_type, processing_status, started_date, ended_date, message) VALUES (1, 4, 'subs_response_renew', 'postponed', '2016-03-02 02:21:00.915731+01', '2016-03-02 02:21:01.133098+01', NULL);


SELECT setval('billing_processing_logs__id_seq', (SELECT MAX(_id) FROM billing_processing_logs));