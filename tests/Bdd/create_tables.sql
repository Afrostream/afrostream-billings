--
-- PostgreSQL database dump
--

-- Dumped from database version 9.5.2
-- Dumped by pg_dump version 9.5.2

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: public; Type: SCHEMA; Schema: -; Owner: postgres
--
DROP SCHEMA public;
CREATE SCHEMA public;


ALTER SCHEMA public OWNER TO postgres;

--
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: postgres
--

COMMENT ON SCHEMA public IS 'standard public schema';


--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

--
-- Name: collection_mode; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE collection_mode AS ENUM (
    'automatic',
    'manual'
);


ALTER TYPE collection_mode OWNER TO postgres;

--
-- Name: coupon_status; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE coupon_status AS ENUM (
    'waiting',
    'expired',
    'redeemed',
    'pending'
);


ALTER TYPE coupon_status OWNER TO postgres;

--
-- Name: plan_cycle; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE plan_cycle AS ENUM (
    'once',
    'auto'
);


ALTER TYPE plan_cycle OWNER TO postgres;

--
-- Name: plan_period_unit; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE plan_period_unit AS ENUM (
    'day',
    'month',
    'year'
);


ALTER TYPE plan_period_unit OWNER TO postgres;

--
-- Name: processing_status; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE processing_status AS ENUM (
    'waiting',
    'running',
    'done',
    'error',
    'aborted',
    'postponed'
);


ALTER TYPE processing_status OWNER TO postgres;

--
-- Name: processing_type; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE processing_type AS ENUM (
    'subs_request_renew',
    'subs_response_renew',
    'subs_request_cancel',
    'subs_response_cancel',
    'subs_refresh',
    'subs_expire_canceled',
    'subs_expire_ended'
);


ALTER TYPE processing_type OWNER TO postgres;

--
-- Name: subscription_action; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE subscription_action AS ENUM (
    'renew_request',
    'renew_notification',
    'cancel_request',
    'cancel_notification',
    'refresh',
    'refresh_renew',
    'refresh_cancel',
    'request_renew',
    'request_cancel',
    'response_renew',
    'response_cancel',
    'expire'
);


ALTER TYPE subscription_action OWNER TO postgres;

--
-- Name: subscription_status; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE subscription_status AS ENUM (
    'active',
    'canceled',
    'expired',
    'future',
    'pending_active',
    'pending_canceled',
    'pending_expired',
    'requesting_canceled'
);


ALTER TYPE subscription_status OWNER TO postgres;

--
-- Name: trial_period_unit; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE trial_period_unit AS ENUM (
    'day',
    'month'
);


ALTER TYPE trial_period_unit OWNER TO postgres;

--
-- Name: update_type; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE update_type AS ENUM (
    'hook',
    'api',
    'import'
);


ALTER TYPE update_type OWNER TO postgres;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: billing_contexts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_contexts (
    _id integer NOT NULL,
    context_uuid character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    description character varying(255),
    country character(2) NOT NULL
);


ALTER TABLE billing_contexts OWNER TO postgres;

--
-- Name: billing_contexts__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_contexts__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_contexts__id_seq OWNER TO postgres;

--
-- Name: billing_contexts__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_contexts__id_seq OWNED BY billing_contexts._id;


--
-- Name: billing_coupons; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_coupons (
    _id integer NOT NULL,
    couponscampaignsid integer NOT NULL,
    providerid integer NOT NULL,
    providerplanid integer NOT NULL,
    code character varying(255) NOT NULL,
    coupon_status coupon_status DEFAULT 'waiting'::coupon_status NOT NULL,
    creation_date timestamp with time zone DEFAULT now() NOT NULL,
    updated_date timestamp with time zone DEFAULT now() NOT NULL,
    redeemed_date timestamp with time zone,
    userid integer,
    subid integer,
    expires_date timestamp with time zone,
    coupon_billing_uuid uuid
);


ALTER TABLE billing_coupons OWNER TO postgres;

--
-- Name: billing_coupons__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_coupons__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_coupons__id_seq OWNER TO postgres;

--
-- Name: billing_coupons__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_coupons__id_seq OWNED BY billing_coupons._id;


--
-- Name: billing_coupons_campaigns; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_coupons_campaigns (
    _id integer NOT NULL,
    coupons_campaigns_uuid uuid NOT NULL,
    creation_date timestamp with time zone DEFAULT now() NOT NULL,
    name character varying(255) NOT NULL,
    description character varying(255),
    providerid integer NOT NULL,
    providerplanid integer NOT NULL,
    prefix character varying(255),
    generated_code_length integer,
    total_number integer
);


ALTER TABLE billing_coupons_campaigns OWNER TO postgres;

--
-- Name: billing_coupons_campaigns__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_coupons_campaigns__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_coupons_campaigns__id_seq OWNER TO postgres;

--
-- Name: billing_coupons_campaigns__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_coupons_campaigns__id_seq OWNED BY billing_coupons_campaigns._id;


--
-- Name: billing_coupons_opts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_coupons_opts (
    _id integer NOT NULL,
    couponid integer NOT NULL,
    key character varying(255) NOT NULL,
    value character varying(255),
    deleted boolean DEFAULT false NOT NULL
);


ALTER TABLE billing_coupons_opts OWNER TO postgres;

--
-- Name: billing_coupons_opts__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_coupons_opts__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_coupons_opts__id_seq OWNER TO postgres;

--
-- Name: billing_coupons_opts__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_coupons_opts__id_seq OWNED BY billing_coupons_opts._id;


--
-- Name: billing_internal_plans; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_internal_plans (
    _id integer NOT NULL,
    internal_plan_uuid character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    description character varying(255),
    amount_in_cents integer,
    currency character(3),
    cycle plan_cycle,
    period_unit plan_period_unit,
    period_length integer,
    thumbid integer,
    vat_rate numeric(4,2),
    trial_enabled boolean DEFAULT false,
    trial_period_length integer,
    trial_period_unit trial_period_unit,
    is_visible boolean DEFAULT true
);


ALTER TABLE billing_internal_plans OWNER TO postgres;

--
-- Name: billing_internal_plans__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_internal_plans__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_internal_plans__id_seq OWNER TO postgres;

--
-- Name: billing_internal_plans__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_internal_plans__id_seq OWNED BY billing_internal_plans._id;


--
-- Name: billing_internal_plans_by_context; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_internal_plans_by_context (
    _id integer NOT NULL,
    internal_plan_id integer NOT NULL,
    context_id integer NOT NULL,
    index integer NOT NULL
);


ALTER TABLE billing_internal_plans_by_context OWNER TO postgres;

--
-- Name: billing_internal_plans_by_context__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_internal_plans_by_context__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_internal_plans_by_context__id_seq OWNER TO postgres;

--
-- Name: billing_internal_plans_by_context__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_internal_plans_by_context__id_seq OWNED BY billing_internal_plans_by_context._id;


--
-- Name: billing_internal_plans_by_country; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_internal_plans_by_country (
    _id integer NOT NULL,
    internal_plan_id integer NOT NULL,
    country character(2) NOT NULL
);


ALTER TABLE billing_internal_plans_by_country OWNER TO postgres;

--
-- Name: billing_internal_plans_by_country__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_internal_plans_by_country__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_internal_plans_by_country__id_seq OWNER TO postgres;

--
-- Name: billing_internal_plans_by_country__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_internal_plans_by_country__id_seq OWNED BY billing_internal_plans_by_country._id;


--
-- Name: billing_internal_plans_links; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_internal_plans_links (
    _id integer NOT NULL,
    internal_plan_id integer NOT NULL,
    provider_plan_id integer NOT NULL
);


ALTER TABLE billing_internal_plans_links OWNER TO postgres;

--
-- Name: billing_internal_plans_links__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_internal_plans_links__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_internal_plans_links__id_seq OWNER TO postgres;

--
-- Name: billing_internal_plans_links__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_internal_plans_links__id_seq OWNED BY billing_internal_plans_links._id;


--
-- Name: billing_internal_plans_opts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_internal_plans_opts (
    _id integer NOT NULL,
    internalplanid integer NOT NULL,
    key character varying(255) NOT NULL,
    value character varying(255),
    deleted boolean DEFAULT false NOT NULL
);


ALTER TABLE billing_internal_plans_opts OWNER TO postgres;

--
-- Name: billing_internal_plans_opts__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_internal_plans_opts__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_internal_plans_opts__id_seq OWNER TO postgres;

--
-- Name: billing_internal_plans_opts__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_internal_plans_opts__id_seq OWNED BY billing_internal_plans_opts._id;


--
-- Name: billing_plans; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_plans (
    _id integer NOT NULL,
    providerid integer NOT NULL,
    plan_uuid character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    description character varying(255)
);


ALTER TABLE billing_plans OWNER TO postgres;

--
-- Name: billing_plans__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_plans__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_plans__id_seq OWNER TO postgres;

--
-- Name: billing_plans__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_plans__id_seq OWNED BY billing_plans._id;


--
-- Name: billing_plans_opts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_plans_opts (
    _id integer NOT NULL,
    planid integer NOT NULL,
    key character varying(255) NOT NULL,
    value character varying(255),
    deleted boolean DEFAULT false NOT NULL
);


ALTER TABLE billing_plans_opts OWNER TO postgres;

--
-- Name: billing_plans_opts__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_plans_opts__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_plans_opts__id_seq OWNER TO postgres;

--
-- Name: billing_plans_opts__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_plans_opts__id_seq OWNED BY billing_plans_opts._id;


--
-- Name: billing_processing_logs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_processing_logs (
    _id integer NOT NULL,
    providerid integer,
    processing_type processing_type NOT NULL,
    processing_status processing_status DEFAULT 'waiting'::processing_status NOT NULL,
    started_date timestamp with time zone DEFAULT now() NOT NULL,
    ended_date timestamp with time zone,
    message text
);


ALTER TABLE billing_processing_logs OWNER TO postgres;

--
-- Name: billing_processing_logs__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_processing_logs__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_processing_logs__id_seq OWNER TO postgres;

--
-- Name: billing_processing_logs__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_processing_logs__id_seq OWNED BY billing_processing_logs._id;


--
-- Name: billing_providers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_providers (
    _id integer NOT NULL,
    name character varying(255) NOT NULL
);


ALTER TABLE billing_providers OWNER TO postgres;

--
-- Name: billing_providers__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_providers__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_providers__id_seq OWNER TO postgres;

--
-- Name: billing_providers__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_providers__id_seq OWNED BY billing_providers._id;


--
-- Name: billing_subscriptions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_subscriptions (
    _id integer NOT NULL,
    subscription_billing_uuid uuid NOT NULL,
    providerid integer NOT NULL,
    userid integer NOT NULL,
    planid integer NOT NULL,
    creation_date timestamp with time zone DEFAULT now() NOT NULL,
    updated_date timestamp with time zone DEFAULT now() NOT NULL,
    sub_uuid character varying(255) NOT NULL,
    sub_status subscription_status NOT NULL,
    sub_activated_date timestamp with time zone,
    sub_canceled_date timestamp with time zone,
    sub_expires_date timestamp with time zone,
    sub_period_started_date timestamp with time zone,
    sub_period_ends_date timestamp with time zone,
    sub_collection_mode collection_mode,
    update_type update_type NOT NULL,
    updateid integer,
    deleted boolean DEFAULT false NOT NULL
);


ALTER TABLE billing_subscriptions OWNER TO postgres;

--
-- Name: billing_subscriptions__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_subscriptions__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_subscriptions__id_seq OWNER TO postgres;

--
-- Name: billing_subscriptions__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_subscriptions__id_seq OWNED BY billing_subscriptions._id;


--
-- Name: billing_subscriptions_action_logs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_subscriptions_action_logs (
    _id integer NOT NULL,
    subid integer NOT NULL,
    processing_status processing_status DEFAULT 'waiting'::processing_status NOT NULL,
    action_type subscription_action NOT NULL,
    started_date timestamp with time zone DEFAULT now() NOT NULL,
    ended_date timestamp with time zone,
    message text
);


ALTER TABLE billing_subscriptions_action_logs OWNER TO postgres;

--
-- Name: billing_subscriptions_action_logs__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_subscriptions_action_logs__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_subscriptions_action_logs__id_seq OWNER TO postgres;

--
-- Name: billing_subscriptions_action_logs__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_subscriptions_action_logs__id_seq OWNED BY billing_subscriptions_action_logs._id;


--
-- Name: billing_subscriptions_opts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_subscriptions_opts (
    _id integer NOT NULL,
    subid integer NOT NULL,
    key character varying(255) NOT NULL,
    value character varying(255),
    deleted boolean DEFAULT false NOT NULL
);


ALTER TABLE billing_subscriptions_opts OWNER TO postgres;

--
-- Name: billing_subscriptions_opts__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_subscriptions_opts__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_subscriptions_opts__id_seq OWNER TO postgres;

--
-- Name: billing_subscriptions_opts__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_subscriptions_opts__id_seq OWNED BY billing_subscriptions_opts._id;


--
-- Name: billing_thumbs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_thumbs (
    _id integer NOT NULL,
    path character varying(255) NOT NULL,
    imgix character varying(255) NOT NULL
);


ALTER TABLE billing_thumbs OWNER TO postgres;

--
-- Name: billing_thumbs__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_thumbs__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_thumbs__id_seq OWNER TO postgres;

--
-- Name: billing_thumbs__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_thumbs__id_seq OWNED BY billing_thumbs._id;


--
-- Name: billing_users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_users (
    _id integer NOT NULL,
    user_billing_uuid uuid NOT NULL,
    creation_date timestamp with time zone DEFAULT now() NOT NULL,
    providerid integer NOT NULL,
    user_reference_uuid character varying(255),
    user_provider_uuid character varying(255),
    deleted boolean DEFAULT false NOT NULL
);


ALTER TABLE billing_users OWNER TO postgres;

--
-- Name: billing_users__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_users__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_users__id_seq OWNER TO postgres;

--
-- Name: billing_users__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_users__id_seq OWNED BY billing_users._id;


--
-- Name: billing_users_opts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_users_opts (
    _id integer NOT NULL,
    userid integer NOT NULL,
    key character varying(255) NOT NULL,
    value character varying(255),
    deleted boolean DEFAULT false NOT NULL
);


ALTER TABLE billing_users_opts OWNER TO postgres;

--
-- Name: billing_users_opts__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_users_opts__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_users_opts__id_seq OWNER TO postgres;

--
-- Name: billing_users_opts__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_users_opts__id_seq OWNED BY billing_users_opts._id;


--
-- Name: billing_webhook_logs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_webhook_logs (
    _id integer NOT NULL,
    webhookid integer NOT NULL,
    processing_status processing_status DEFAULT 'running'::processing_status NOT NULL,
    started_date timestamp with time zone DEFAULT now() NOT NULL,
    ended_date timestamp with time zone,
    message text
);


ALTER TABLE billing_webhook_logs OWNER TO postgres;

--
-- Name: billing_webhook_logs__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_webhook_logs__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_webhook_logs__id_seq OWNER TO postgres;

--
-- Name: billing_webhook_logs__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_webhook_logs__id_seq OWNED BY billing_webhook_logs._id;


--
-- Name: billing_webhooks; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE billing_webhooks (
    _id integer NOT NULL,
    providerid integer NOT NULL,
    post_data text NOT NULL,
    processing_status processing_status DEFAULT 'waiting'::processing_status NOT NULL,
    creation_date timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE billing_webhooks OWNER TO postgres;

--
-- Name: billing_webhooks__id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE billing_webhooks__id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE billing_webhooks__id_seq OWNER TO postgres;

--
-- Name: billing_webhooks__id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE billing_webhooks__id_seq OWNED BY billing_webhooks._id;


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_contexts ALTER COLUMN _id SET DEFAULT nextval('billing_contexts__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_coupons ALTER COLUMN _id SET DEFAULT nextval('billing_coupons__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_coupons_campaigns ALTER COLUMN _id SET DEFAULT nextval('billing_coupons_campaigns__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_coupons_opts ALTER COLUMN _id SET DEFAULT nextval('billing_coupons_opts__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans ALTER COLUMN _id SET DEFAULT nextval('billing_internal_plans__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans_by_context ALTER COLUMN _id SET DEFAULT nextval('billing_internal_plans_by_context__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans_by_country ALTER COLUMN _id SET DEFAULT nextval('billing_internal_plans_by_country__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans_links ALTER COLUMN _id SET DEFAULT nextval('billing_internal_plans_links__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans_opts ALTER COLUMN _id SET DEFAULT nextval('billing_internal_plans_opts__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_plans ALTER COLUMN _id SET DEFAULT nextval('billing_plans__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_plans_opts ALTER COLUMN _id SET DEFAULT nextval('billing_plans_opts__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_processing_logs ALTER COLUMN _id SET DEFAULT nextval('billing_processing_logs__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_providers ALTER COLUMN _id SET DEFAULT nextval('billing_providers__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_subscriptions ALTER COLUMN _id SET DEFAULT nextval('billing_subscriptions__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_subscriptions_action_logs ALTER COLUMN _id SET DEFAULT nextval('billing_subscriptions_action_logs__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_subscriptions_opts ALTER COLUMN _id SET DEFAULT nextval('billing_subscriptions_opts__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_thumbs ALTER COLUMN _id SET DEFAULT nextval('billing_thumbs__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_users ALTER COLUMN _id SET DEFAULT nextval('billing_users__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_users_opts ALTER COLUMN _id SET DEFAULT nextval('billing_users_opts__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_webhook_logs ALTER COLUMN _id SET DEFAULT nextval('billing_webhook_logs__id_seq'::regclass);


--
-- Name: _id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_webhooks ALTER COLUMN _id SET DEFAULT nextval('billing_webhooks__id_seq'::regclass);


--
-- Name: billing_contexts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_contexts
    ADD CONSTRAINT billing_contexts_pkey PRIMARY KEY (_id);


--
-- Name: billing_coupons_opts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_coupons_opts
    ADD CONSTRAINT billing_coupons_opts_pkey PRIMARY KEY (_id);


--
-- Name: billing_coupons_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_coupons
    ADD CONSTRAINT billing_coupons_pkey PRIMARY KEY (_id);


--
-- Name: billing_coupons_rules_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_coupons_campaigns
    ADD CONSTRAINT billing_coupons_rules_pkey PRIMARY KEY (_id);


--
-- Name: billing_internal_plans_by_context_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans_by_context
    ADD CONSTRAINT billing_internal_plans_by_context_pkey PRIMARY KEY (_id);


--
-- Name: billing_internal_plans_by_country_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans_by_country
    ADD CONSTRAINT billing_internal_plans_by_country_pkey PRIMARY KEY (_id);


--
-- Name: billing_internal_plans_links_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans_links
    ADD CONSTRAINT billing_internal_plans_links_pkey PRIMARY KEY (_id);


--
-- Name: billing_internal_plans_opts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans_opts
    ADD CONSTRAINT billing_internal_plans_opts_pkey PRIMARY KEY (_id);


--
-- Name: billing_internal_plans_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans
    ADD CONSTRAINT billing_internal_plans_pkey PRIMARY KEY (_id);


--
-- Name: billing_plans_opts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_plans_opts
    ADD CONSTRAINT billing_plans_opts_pkey PRIMARY KEY (_id);


--
-- Name: billing_plans_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_plans
    ADD CONSTRAINT billing_plans_pkey PRIMARY KEY (_id);


--
-- Name: billing_processing_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_processing_logs
    ADD CONSTRAINT billing_processing_logs_pkey PRIMARY KEY (_id);


--
-- Name: billing_providers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_providers
    ADD CONSTRAINT billing_providers_pkey PRIMARY KEY (_id);


--
-- Name: billing_subscriptions_opts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_subscriptions_opts
    ADD CONSTRAINT billing_subscriptions_opts_pkey PRIMARY KEY (_id);


--
-- Name: billing_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_subscriptions
    ADD CONSTRAINT billing_subscriptions_pkey PRIMARY KEY (_id);


--
-- Name: billing_subscriptions_subid_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_subscriptions_action_logs
    ADD CONSTRAINT billing_subscriptions_subid_pkey PRIMARY KEY (_id);


--
-- Name: billing_thumbs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_thumbs
    ADD CONSTRAINT billing_thumbs_pkey PRIMARY KEY (_id);


--
-- Name: billing_users_opts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_users_opts
    ADD CONSTRAINT billing_users_opts_pkey PRIMARY KEY (_id);


--
-- Name: billing_users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_users
    ADD CONSTRAINT billing_users_pkey PRIMARY KEY (_id);


--
-- Name: billing_webhooks_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_webhook_logs
    ADD CONSTRAINT billing_webhooks_logs_pkey PRIMARY KEY (_id);


--
-- Name: billing_webhooks_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_webhooks
    ADD CONSTRAINT billing_webhooks_pkey PRIMARY KEY (_id);


--
-- Name: context_country_uniq; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_contexts
    ADD CONSTRAINT context_country_uniq UNIQUE (context_uuid, country);


--
-- Name: coupons_uniq_code; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_coupons
    ADD CONSTRAINT coupons_uniq_code UNIQUE (code);


--
-- Name: internalplan_context_uniq; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans_by_context
    ADD CONSTRAINT internalplan_context_uniq UNIQUE (internal_plan_id, context_id);


--
-- Name: internalplan_country_uniq; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans_by_country
    ADD CONSTRAINT internalplan_country_uniq UNIQUE (internal_plan_id, country);


--
-- Name: plan_links_uniq; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans_links
    ADD CONSTRAINT plan_links_uniq UNIQUE (internal_plan_id, provider_plan_id);


--
-- Name: subscriptions_uniq_uuid; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_subscriptions
    ADD CONSTRAINT subscriptions_uniq_uuid UNIQUE (subscription_billing_uuid);


--
-- Name: users_uniq_uuid; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_users
    ADD CONSTRAINT users_uniq_uuid UNIQUE (user_billing_uuid);


--
-- Name: internal_plans_internal_plan_uuid_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX internal_plans_internal_plan_uuid_idx ON billing_internal_plans USING btree (internal_plan_uuid DESC NULLS LAST);


--
-- Name: internal_plans_name_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX internal_plans_name_idx ON billing_internal_plans USING btree (name DESC NULLS LAST);


--
-- Name: plans_name_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX plans_name_idx ON billing_plans USING btree (name DESC NULLS LAST);


--
-- Name: plans_opts_planid_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX plans_opts_planid_idx ON billing_plans_opts USING btree (planid DESC NULLS LAST);


--
-- Name: plans_plan_uuid_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX plans_plan_uuid_idx ON billing_plans USING btree (plan_uuid DESC NULLS LAST);


--
-- Name: plans_providerid_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX plans_providerid_idx ON billing_plans USING btree (providerid DESC NULLS LAST);


--
-- Name: plans_thumbid_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX plans_thumbid_idx ON billing_internal_plans USING btree (thumbid);


--
-- Name: providers_name_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX providers_name_idx ON billing_providers USING btree (name DESC NULLS LAST);


--
-- Name: subs_opts_subid_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX subs_opts_subid_idx ON billing_subscriptions_opts USING btree (subid DESC NULLS LAST);


--
-- Name: subscriptions_idx_pro_sub; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX subscriptions_idx_pro_sub ON billing_subscriptions USING btree (providerid, sub_uuid);


--
-- Name: subscriptions_providerid_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX subscriptions_providerid_idx ON billing_subscriptions USING btree (providerid DESC NULLS LAST);


--
-- Name: subscriptions_sub_uuid_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX subscriptions_sub_uuid_idx ON billing_subscriptions USING btree (sub_uuid DESC NULLS LAST);


--
-- Name: subscriptions_userid_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX subscriptions_userid_idx ON billing_subscriptions USING btree (userid DESC NULLS LAST);


--
-- Name: users_idx_pro_user; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX users_idx_pro_user ON billing_users USING btree (providerid, user_provider_uuid);


--
-- Name: users_opts_userid_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX users_opts_userid_idx ON billing_users_opts USING btree (userid DESC NULLS LAST);


--
-- Name: users_user_provider_uuid_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX users_user_provider_uuid_idx ON billing_users USING btree (user_provider_uuid DESC NULLS LAST);


--
-- Name: users_user_reference_uuid_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX users_user_reference_uuid_idx ON billing_users USING btree (user_reference_uuid DESC NULLS LAST);


--
-- Name: Context_contextid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans_by_context
    ADD CONSTRAINT "Context_contextid_fkey" FOREIGN KEY (context_id) REFERENCES billing_contexts(_id);


--
-- Name: InternalPlans_internalplanid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans_links
    ADD CONSTRAINT "InternalPlans_internalplanid_fkey" FOREIGN KEY (internal_plan_id) REFERENCES billing_internal_plans(_id);


--
-- Name: InternalPlans_internalplanid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans_by_context
    ADD CONSTRAINT "InternalPlans_internalplanid_fkey" FOREIGN KEY (internal_plan_id) REFERENCES billing_internal_plans(_id);


--
-- Name: InternalPlans_internalplanid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans_by_country
    ADD CONSTRAINT "InternalPlans_internalplanid_fkey" FOREIGN KEY (internal_plan_id) REFERENCES billing_internal_plans(_id);


--
-- Name: Plans_planId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_subscriptions
    ADD CONSTRAINT "Plans_planId_fkey" FOREIGN KEY (planid) REFERENCES billing_plans(_id);


--
-- Name: Plans_providerplanid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans_links
    ADD CONSTRAINT "Plans_providerplanid_fkey" FOREIGN KEY (provider_plan_id) REFERENCES billing_plans(_id);


--
-- Name: Plans_providerplanid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_coupons_campaigns
    ADD CONSTRAINT "Plans_providerplanid_fkey" FOREIGN KEY (providerplanid) REFERENCES billing_plans(_id);


--
-- Name: Plans_providerplanid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_coupons
    ADD CONSTRAINT "Plans_providerplanid_fkey" FOREIGN KEY (providerplanid) REFERENCES billing_plans(_id);


--
-- Name: Providers_providerId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_webhooks
    ADD CONSTRAINT "Providers_providerId_fkey" FOREIGN KEY (providerid) REFERENCES billing_providers(_id);


--
-- Name: Providers_providerId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_users
    ADD CONSTRAINT "Providers_providerId_fkey" FOREIGN KEY (providerid) REFERENCES billing_providers(_id);


--
-- Name: Providers_providerId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_plans
    ADD CONSTRAINT "Providers_providerId_fkey" FOREIGN KEY (providerid) REFERENCES billing_providers(_id);


--
-- Name: Providers_providerId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_subscriptions
    ADD CONSTRAINT "Providers_providerId_fkey" FOREIGN KEY (providerid) REFERENCES billing_providers(_id);


--
-- Name: Providers_providerId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_processing_logs
    ADD CONSTRAINT "Providers_providerId_fkey" FOREIGN KEY (providerid) REFERENCES billing_providers(_id);


--
-- Name: Providers_providerId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_coupons_campaigns
    ADD CONSTRAINT "Providers_providerId_fkey" FOREIGN KEY (providerid) REFERENCES billing_providers(_id);


--
-- Name: Providers_providerId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_coupons
    ADD CONSTRAINT "Providers_providerId_fkey" FOREIGN KEY (providerid) REFERENCES billing_providers(_id);


--
-- Name: Thumbs_thumbid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans
    ADD CONSTRAINT "Thumbs_thumbid_fkey" FOREIGN KEY (thumbid) REFERENCES billing_thumbs(_id);


--
-- Name: Users_userId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_users_opts
    ADD CONSTRAINT "Users_userId_fkey" FOREIGN KEY (userid) REFERENCES billing_users(_id);


--
-- Name: Users_userId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_subscriptions
    ADD CONSTRAINT "Users_userId_fkey" FOREIGN KEY (userid) REFERENCES billing_users(_id);


--
-- Name: Users_userId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_coupons
    ADD CONSTRAINT "Users_userId_fkey" FOREIGN KEY (userid) REFERENCES billing_users(_id);


--
-- Name: Webhook_webhookId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_webhook_logs
    ADD CONSTRAINT "Webhook_webhookId_fkey" FOREIGN KEY (webhookid) REFERENCES billing_webhooks(_id);


--
-- Name: billing_subscriptions_subid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_subscriptions_action_logs
    ADD CONSTRAINT billing_subscriptions_subid_fkey FOREIGN KEY (subid) REFERENCES billing_subscriptions(_id);


--
-- Name: billing_subscriptions_subid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_subscriptions_opts
    ADD CONSTRAINT billing_subscriptions_subid_fkey FOREIGN KEY (subid) REFERENCES billing_subscriptions(_id);


--
-- Name: billing_subscriptions_subid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_coupons
    ADD CONSTRAINT billing_subscriptions_subid_fkey FOREIGN KEY (subid) REFERENCES billing_subscriptions(_id);


--
-- Name: coupons_campaigns_couponscampaignsid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_coupons
    ADD CONSTRAINT coupons_campaigns_couponscampaignsid_fkey FOREIGN KEY (couponscampaignsid) REFERENCES billing_coupons_campaigns(_id);


--
-- Name: ibilling_coupons_couponId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_coupons_opts
    ADD CONSTRAINT "ibilling_coupons_couponId_fkey" FOREIGN KEY (couponid) REFERENCES billing_coupons(_id);


--
-- Name: internal_plans_planId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_internal_plans_opts
    ADD CONSTRAINT "internal_plans_planId_fkey" FOREIGN KEY (internalplanid) REFERENCES billing_internal_plans(_id);


--
-- Name: plans_planId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY billing_plans_opts
    ADD CONSTRAINT "plans_planId_fkey" FOREIGN KEY (planid) REFERENCES billing_plans(_id);


CREATE TABLE billing_users_requests_logs
(
 _id serial NOT NULL,
 userid integer NOT NULL,
 creation_date timestamp with time zone NOT NULL DEFAULT now(),
 CONSTRAINT billing_users_requests_logs_pkey PRIMARY KEY (_id),
 CONSTRAINT "Users_userId_fkey" FOREIGN KEY (userid)
     REFERENCES billing_users (_id) MATCH SIMPLE
     ON UPDATE NO ACTION ON DELETE NO ACTION
);

ALTER TABLE billing_subscriptions_action_logs ADD COLUMN processing_status_code integer NOT NULL DEFAULT 0;

--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

