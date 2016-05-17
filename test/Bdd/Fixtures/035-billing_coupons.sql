SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

SET search_path = public, pg_catalog;

INSERT INTO billing_coupons (_id, couponscampaignsid, providerid, providerplanid, code, coupon_status, creation_date, updated_date, redeemed_date, userid, subid, expires_date, coupon_billing_uuid) VALUES (1, 1, 6, 19, 'test-2months-dk633q', 'waiting', '2016-03-07 12:03:15.226973+01', '2016-03-07 12:03:15.226973+01', NULL, NULL, NULL, NULL, NULL);
