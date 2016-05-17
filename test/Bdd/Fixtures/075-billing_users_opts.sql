SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

SET search_path = public, pg_catalog;

INSERT INTO billing_users_opts (_id, userid, key, value, deleted) VALUES (1, 1, 'email', 'test@domain.tld', false);
INSERT INTO billing_users_opts (_id, userid, key, value, deleted) VALUES (2, 1, 'firstName', 'firstname test', false);
INSERT INTO billing_users_opts (_id, userid, key, value, deleted) VALUES (3, 1, 'lastName', 'coelho', false);
INSERT INTO billing_users_opts (_id, userid, key, value, deleted) VALUES (4, 2, 'email', 'email@domain.com', false);
INSERT INTO billing_users_opts (_id, userid, key, value, deleted) VALUES (5, 2, 'firstName', 'nelson', false);
INSERT INTO billing_users_opts (_id, userid, key, value, deleted) VALUES (6, 2, 'lastName', 'coelho', false);
