SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

SET search_path = public, pg_catalog;

INSERT INTO billing_users (_id, user_billing_uuid, creation_date, providerid, user_reference_uuid, user_provider_uuid, deleted) VALUES (1, '11111111-1111-1111-1111-111111111111', '2016-01-01 00:00:00.000000+01', 2, '1234', '22222222-2222-2222-2222-222222222222', false);
INSERT INTO billing_users (_id, user_billing_uuid, creation_date, providerid, user_reference_uuid, user_provider_uuid, deleted) VALUES (2, '33333333-3333-3333-3333-333333333333', '2016-01-01 00:00:00.000000+01', 2, '1234', '44444444-4444-4444-4444-444444444444', true);

