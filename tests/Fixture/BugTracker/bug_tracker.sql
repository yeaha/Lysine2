--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: bug_tracker; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA bug_tracker;


SET search_path = bug_tracker, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: accounts; Type: TABLE; Schema: bug_tracker; Owner: -; Tablespace: 
--

CREATE TABLE accounts (
    account_id integer NOT NULL,
    account_name character varying(20) NOT NULL,
    first_name character varying(20) NOT NULL,
    last_name character varying(20) NOT NULL,
    email character varying(100) NOT NULL,
    password_hash character(64) NOT NULL,
    portrait_image character varying(100),
    hourly_rate numeric(9,2) NOT NULL
);


--
-- Name: accounts_account_id_seq; Type: SEQUENCE; Schema: bug_tracker; Owner: -
--

CREATE SEQUENCE accounts_account_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: accounts_account_id_seq; Type: SEQUENCE OWNED BY; Schema: bug_tracker; Owner: -
--

ALTER SEQUENCE accounts_account_id_seq OWNED BY accounts.account_id;


--
-- Name: bugs; Type: TABLE; Schema: bug_tracker; Owner: -; Tablespace: 
--

CREATE TABLE bugs (
    bug_id integer NOT NULL,
    date_reported date NOT NULL,
    summary character varying(80) NOT NULL,
    description text NOT NULL,
    resolution text,
    reported_by integer NOT NULL,
    assigned_to integer,
    verified_by integer,
    status character varying(20) DEFAULT 'NEW'::character varying NOT NULL,
    priority character varying(20),
    hours numeric(9,2)
);


--
-- Name: bugs_bug_id_seq; Type: SEQUENCE; Schema: bug_tracker; Owner: -
--

CREATE SEQUENCE bugs_bug_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: bugs_bug_id_seq; Type: SEQUENCE OWNED BY; Schema: bug_tracker; Owner: -
--

ALTER SEQUENCE bugs_bug_id_seq OWNED BY bugs.bug_id;


--
-- Name: bugs_products; Type: TABLE; Schema: bug_tracker; Owner: -; Tablespace: 
--

CREATE TABLE bugs_products (
    bug_id integer NOT NULL,
    product_id integer NOT NULL
);


--
-- Name: bugs_status; Type: TABLE; Schema: bug_tracker; Owner: -; Tablespace: 
--

CREATE TABLE bugs_status (
    status character varying(20) NOT NULL
);


--
-- Name: comments; Type: TABLE; Schema: bug_tracker; Owner: -; Tablespace: 
--

CREATE TABLE comments (
    comment_id integer NOT NULL,
    bug_id integer NOT NULL,
    author integer NOT NULL,
    comment_date timestamp(0) with time zone NOT NULL,
    comment text NOT NULL
);


--
-- Name: comments_comment_id_seq; Type: SEQUENCE; Schema: bug_tracker; Owner: -
--

CREATE SEQUENCE comments_comment_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: comments_comment_id_seq; Type: SEQUENCE OWNED BY; Schema: bug_tracker; Owner: -
--

ALTER SEQUENCE comments_comment_id_seq OWNED BY comments.comment_id;


--
-- Name: products; Type: TABLE; Schema: bug_tracker; Owner: -; Tablespace: 
--

CREATE TABLE products (
    product_id integer NOT NULL,
    product_name character varying(50) NOT NULL
);


--
-- Name: products_product_id_seq; Type: SEQUENCE; Schema: bug_tracker; Owner: -
--

CREATE SEQUENCE products_product_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: products_product_id_seq; Type: SEQUENCE OWNED BY; Schema: bug_tracker; Owner: -
--

ALTER SEQUENCE products_product_id_seq OWNED BY products.product_id;


--
-- Name: screenshots; Type: TABLE; Schema: bug_tracker; Owner: -; Tablespace: 
--

CREATE TABLE screenshots (
    bug_id integer NOT NULL,
    image_id integer NOT NULL,
    screenshot_image character varying(100) NOT NULL,
    caption character varying(100)
);


--
-- Name: account_id; Type: DEFAULT; Schema: bug_tracker; Owner: -
--

ALTER TABLE ONLY accounts ALTER COLUMN account_id SET DEFAULT nextval('accounts_account_id_seq'::regclass);


--
-- Name: bug_id; Type: DEFAULT; Schema: bug_tracker; Owner: -
--

ALTER TABLE ONLY bugs ALTER COLUMN bug_id SET DEFAULT nextval('bugs_bug_id_seq'::regclass);


--
-- Name: comment_id; Type: DEFAULT; Schema: bug_tracker; Owner: -
--

ALTER TABLE ONLY comments ALTER COLUMN comment_id SET DEFAULT nextval('comments_comment_id_seq'::regclass);


--
-- Name: product_id; Type: DEFAULT; Schema: bug_tracker; Owner: -
--

ALTER TABLE ONLY products ALTER COLUMN product_id SET DEFAULT nextval('products_product_id_seq'::regclass);


--
-- Name: pk_accounts; Type: CONSTRAINT; Schema: bug_tracker; Owner: -; Tablespace: 
--

ALTER TABLE ONLY accounts
    ADD CONSTRAINT pk_accounts PRIMARY KEY (account_id);


--
-- Name: pk_bugs; Type: CONSTRAINT; Schema: bug_tracker; Owner: -; Tablespace: 
--

ALTER TABLE ONLY bugs
    ADD CONSTRAINT pk_bugs PRIMARY KEY (bug_id);


--
-- Name: pk_bugs_products; Type: CONSTRAINT; Schema: bug_tracker; Owner: -; Tablespace: 
--

ALTER TABLE ONLY bugs_products
    ADD CONSTRAINT pk_bugs_products PRIMARY KEY (bug_id, product_id);


--
-- Name: pk_bugs_status; Type: CONSTRAINT; Schema: bug_tracker; Owner: -; Tablespace: 
--

ALTER TABLE ONLY bugs_status
    ADD CONSTRAINT pk_bugs_status PRIMARY KEY (status);


--
-- Name: pk_comments; Type: CONSTRAINT; Schema: bug_tracker; Owner: -; Tablespace: 
--

ALTER TABLE ONLY comments
    ADD CONSTRAINT pk_comments PRIMARY KEY (comment_id);


--
-- Name: pk_products; Type: CONSTRAINT; Schema: bug_tracker; Owner: -; Tablespace: 
--

ALTER TABLE ONLY products
    ADD CONSTRAINT pk_products PRIMARY KEY (product_id);


--
-- Name: pk_screenshots; Type: CONSTRAINT; Schema: bug_tracker; Owner: -; Tablespace: 
--

ALTER TABLE ONLY screenshots
    ADD CONSTRAINT pk_screenshots PRIMARY KEY (bug_id, image_id);


--
-- Name: fk_bugs_assigned_to; Type: FK CONSTRAINT; Schema: bug_tracker; Owner: -
--

ALTER TABLE ONLY bugs
    ADD CONSTRAINT fk_bugs_assigned_to FOREIGN KEY (assigned_to) REFERENCES accounts(account_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: fk_bugs_products_bug_id; Type: FK CONSTRAINT; Schema: bug_tracker; Owner: -
--

ALTER TABLE ONLY bugs_products
    ADD CONSTRAINT fk_bugs_products_bug_id FOREIGN KEY (bug_id) REFERENCES bugs(bug_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: fk_bugs_products_product_id; Type: FK CONSTRAINT; Schema: bug_tracker; Owner: -
--

ALTER TABLE ONLY bugs_products
    ADD CONSTRAINT fk_bugs_products_product_id FOREIGN KEY (product_id) REFERENCES products(product_id);


--
-- Name: fk_bugs_reported_by; Type: FK CONSTRAINT; Schema: bug_tracker; Owner: -
--

ALTER TABLE ONLY bugs
    ADD CONSTRAINT fk_bugs_reported_by FOREIGN KEY (reported_by) REFERENCES accounts(account_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: fk_bugs_status; Type: FK CONSTRAINT; Schema: bug_tracker; Owner: -
--

ALTER TABLE ONLY bugs
    ADD CONSTRAINT fk_bugs_status FOREIGN KEY (status) REFERENCES bugs_status(status) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: fk_bugs_verified_by; Type: FK CONSTRAINT; Schema: bug_tracker; Owner: -
--

ALTER TABLE ONLY bugs
    ADD CONSTRAINT fk_bugs_verified_by FOREIGN KEY (verified_by) REFERENCES accounts(account_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: fk_comments_author; Type: FK CONSTRAINT; Schema: bug_tracker; Owner: -
--

ALTER TABLE ONLY comments
    ADD CONSTRAINT fk_comments_author FOREIGN KEY (author) REFERENCES accounts(account_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: fk_comments_bug_id; Type: FK CONSTRAINT; Schema: bug_tracker; Owner: -
--

ALTER TABLE ONLY comments
    ADD CONSTRAINT fk_comments_bug_id FOREIGN KEY (bug_id) REFERENCES bugs(bug_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: fk_screenshots_bug_id; Type: FK CONSTRAINT; Schema: bug_tracker; Owner: -
--

ALTER TABLE ONLY screenshots
    ADD CONSTRAINT fk_screenshots_bug_id FOREIGN KEY (bug_id) REFERENCES bugs(bug_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

