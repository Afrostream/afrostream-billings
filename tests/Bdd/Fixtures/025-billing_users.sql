SET search_path = public, pg_catalog;

INSERT INTO billing_users (_id, user_billing_uuid, creation_date, providerid, user_reference_uuid, user_provider_uuid, deleted) VALUES (1, '11111111-1111-1111-1111-111111111111', '2016-01-01 00:00:00.000000+01', 2, 'ref-afrostream1', '22222222-2222-2222-2222-222222222222', false);
INSERT INTO billing_users (_id, user_billing_uuid, creation_date, providerid, user_reference_uuid, user_provider_uuid, deleted) VALUES (2, '33333333-3333-3333-3333-333333333333', '2016-01-01 00:00:00.000000+01', 2, 'ref-afrostream2', '44444444-4444-4444-4444-444444444444', true);
INSERT INTO billing_users (_id, user_billing_uuid, creation_date, providerid, user_reference_uuid, user_provider_uuid, deleted) VALUES (3, '55555555-5555-5555-5555-555555555555', '2016-01-01 00:00:00.000000+01', 1, 'ref-afrostream3', '66666666-6666-6666-6666-666666666666', false);

SELECT setval('billing_users__id_seq', 3);