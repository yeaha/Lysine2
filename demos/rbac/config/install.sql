--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: rbac; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA rbac;


SET search_path = rbac, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: users; Type: TABLE; Schema: rbac; Owner: -; Tablespace: 
--

CREATE TABLE users (
    user_id integer NOT NULL,
    email character varying(128) NOT NULL,
    passwd character(32) NOT NULL,
    create_time timestamp(0) without time zone NOT NULL
);


--
-- Name: users_role; Type: TABLE; Schema: rbac; Owner: -; Tablespace: 
--

CREATE TABLE users_role (
    user_id integer NOT NULL,
    role character varying(32) NOT NULL,
    expire_time timestamp(0) without time zone
);


--
-- Name: users_user_id_seq; Type: SEQUENCE; Schema: rbac; Owner: -
--

CREATE SEQUENCE users_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_user_id_seq; Type: SEQUENCE OWNED BY; Schema: rbac; Owner: -
--

ALTER SEQUENCE users_user_id_seq OWNED BY users.user_id;


--
-- Name: users_user_id_seq; Type: SEQUENCE SET; Schema: rbac; Owner: -
--

SELECT pg_catalog.setval('users_user_id_seq', 2, true);


--
-- Name: user_id; Type: DEFAULT; Schema: rbac; Owner: -
--

ALTER TABLE ONLY users ALTER COLUMN user_id SET DEFAULT nextval('users_user_id_seq'::regclass);


--
-- Data for Name: users; Type: TABLE DATA; Schema: rbac; Owner: -
--

COPY users (user_id, email, passwd, create_time) FROM stdin;
1	admin@example.com	0235a412d95eb6ac89714e37ad0cbb92	2013-01-22 17:31:51
2	user@example.com	b3a489536ecb9eb1456bf5366f4acf74	2013-01-22 17:32:02
\.


--
-- Data for Name: users_role; Type: TABLE DATA; Schema: rbac; Owner: -
--

COPY users_role (user_id, role, expire_time) FROM stdin;
1	admin	\N
\.


--
-- Name: pk_users; Type: CONSTRAINT; Schema: rbac; Owner: -; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT pk_users PRIMARY KEY (user_id);


--
-- Name: pk_users_role; Type: CONSTRAINT; Schema: rbac; Owner: -; Tablespace: 
--

ALTER TABLE ONLY users_role
    ADD CONSTRAINT pk_users_role PRIMARY KEY (user_id, role);


--
-- Name: uk_users_email; Type: INDEX; Schema: rbac; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX uk_users_email ON users USING btree (email);


--
-- Name: fk_users_role_uid; Type: FK CONSTRAINT; Schema: rbac; Owner: -
--

ALTER TABLE ONLY users_role
    ADD CONSTRAINT fk_users_role_uid FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

