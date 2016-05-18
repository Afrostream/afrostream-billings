SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: billing_coupons_campaigns; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO billing_coupons_campaigns (_id, coupons_campaigns_uuid, creation_date, name, description, providerid, providerplanid, prefix, generated_code_length, total_number) VALUES (1, '4aef0220-5a52-4781-bd4b-0283a277cfe8', '2016-03-07 11:57:12.412057+01', 'campaign-test-2months', 'campaign-test-2months', 6, 19, 'test-2months', 6, 1000);
INSERT INTO billing_coupons_campaigns (_id, coupons_campaigns_uuid, creation_date, name, description, providerid, providerplanid, prefix, generated_code_length, total_number) VALUES (2, '81f1c4ce-c191-4142-8b8a-0b8dab78d970', '2016-03-07 11:58:24.396526+01', 'campaign-test-oneyear', 'campaign-test-oneyear', 6, 20, 'test-oneyear', 6, 500);

SELECT setval('billing_coupons_campaigns__id_seq', 2);