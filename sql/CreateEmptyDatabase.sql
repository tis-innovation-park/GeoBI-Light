SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

CREATE SCHEMA data;
ALTER SCHEMA data OWNER TO geobi;

CREATE SCHEMA geobi;
ALTER SCHEMA geobi OWNER TO geobi;

CREATE SCHEMA impexp;
ALTER SCHEMA impexp OWNER TO geobi;

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;
COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';

CREATE EXTENSION IF NOT EXISTS unaccent WITH SCHEMA public;
COMMENT ON EXTENSION unaccent IS 'text search dictionary that removes accents';


SET search_path = data, pg_catalog;

CREATE FUNCTION add_area_partition(code text) RETURNS void
    LANGUAGE plpgsql
    AS $$
DECLARE
  sql VARCHAR(4000);
BEGIN

END;
$$;
ALTER FUNCTION data.add_area_partition(code text) OWNER TO geobi;

CREATE FUNCTION area_insert_trigger() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
  sql TEXT;
  code TEXT;
BEGIN
  SELECT INTO code LOWER(at_code) FROM data.area_type WHERE at_id=NEW.at_id;
  CREATE TEMPORARY TABLE area_part AS SELECT NEW.*;
  sql := 'INSERT INTO data.area_part_' || code || ' SELECT * FROM area_part';
  EXECUTE sql;
  DROP TABLE area_part;
  RETURN NULL;
END;
$$;
ALTER FUNCTION data.area_insert_trigger() OWNER TO geobi;

CREATE FUNCTION check_area_detail_fk() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    fk INTEGER;
BEGIN
    /* Manual foreign key check */
    IF TG_OP='INSERT' OR TG_OP='UPDATE' THEN
        IF NEW.ar_id=NEW.ar_id_detail THEN
            RAISE EXCEPTION  'ar_id and ar_id_detail must be different on table %.%: %', TG_TABLE_SCHEMA, TG_RELNAME, NEW;
        END IF;
        SELECT ar_id INTO fk FROM stat.area WHERE ar_id=NEW.ar_id;
        IF NOT FOUND THEN
            RAISE EXCEPTION  'Foreign key violated on table %.% for field ar_id: %', TG_TABLE_SCHEMA, TG_RELNAME, NEW;
        END IF;
        SELECT ar_id INTO fk FROM stat.area WHERE ar_id=NEW.ar_id_detail;
        IF NOT FOUND THEN
            RAISE EXCEPTION  'Foreign key violated on table %.% for field ar_id_detail: %', TG_TABLE_SCHEMA, TG_RELNAME, NEW;
        END IF;
    END IF;
    RETURN NEW;
END;
$$;
ALTER FUNCTION data.check_area_detail_fk() OWNER TO geobi;


CREATE FUNCTION get_session_var(name text) RETURNS text
    LANGUAGE plpgsql
    AS $$
DECLARE
    value TEXT;
BEGIN
    SELECT current_setting('r3_session.' || name) INTO STRICT value;
    RETURN value;
    EXCEPTION WHEN OTHERS THEN
    RAISE NOTICE 'Session variable % not initialized', name;
    RETURN NULL;
END;
$$;
ALTER FUNCTION data.get_session_var(name text) OWNER TO geobi;

CREATE FUNCTION set_session_var(name text, value text) RETURNS void
    LANGUAGE plpgsql
    AS $$
DECLARE
  sql VARCHAR(4000);
BEGIN
    EXECUTE 'SET r3_session.' || name || '=' || quote_literal(value);
END;
$$;
ALTER FUNCTION data.set_session_var(name text, value text) OWNER TO geobi;


SET search_path = geobi, pg_catalog;

CREATE FUNCTION map_children_of(map_id integer) RETURNS TABLE(map_id integer, map_parent_id integer, map_hash character varying, us_id integer, map_temporary boolean, map_ins_date timestamp without time zone, map_mod_date timestamp without time zone)
    LANGUAGE sql
    AS $_$
    WITH RECURSIVE data(map_id, map_id_parent) AS (
        SELECT map_id, map_id_parent, map_hash, us_id, map_temporary , map_ins_date, map_mod_date
        FROM geobi.map t1
        WHERE map_id_parent=$1
        UNION
		SELECT t2.map_id, t2.map_id_parent, t2.map_hash, t2.us_id, t2.map_temporary , t2.map_ins_date, t2.map_mod_date
        FROM geobi.map t2, data AS t1
        WHERE t2.map_id_parent=t1.map_id
    )
    SELECT map_id, map_id_parent, map_hash, us_id, map_temporary , map_ins_date, map_mod_date
    FROM data;
$_$;


ALTER FUNCTION geobi.map_children_of(map_id integer) OWNER TO ssegala;

SET search_path = data, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;


CREATE TABLE area (
    ar_id integer NOT NULL,
    at_id integer NOT NULL,
    ar_code character varying(40) NOT NULL,
    ar_name_id integer NOT NULL,
    ar_parent_code character varying,
    ar_label_priority smallint,
    ar_name_local character varying
)
WITH (fillfactor=100);
ALTER TABLE ONLY area ALTER COLUMN ar_code SET STORAGE PLAIN;
ALTER TABLE ONLY area ALTER COLUMN ar_parent_code SET STORAGE MAIN;
ALTER TABLE data.area OWNER TO geobi;
COMMENT ON TABLE area IS 'Main area table';
COMMENT ON COLUMN area.ar_code IS 'Fixed length, storage: main';
COMMENT ON COLUMN area.ar_name_id IS 'FK to localization.msg_id';

CREATE SEQUENCE area_ar_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE data.area_ar_id_seq OWNER TO geobi;

--
-- Name: area_ar_id_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: geobi
--

ALTER SEQUENCE area_ar_id_seq OWNED BY area.ar_id;


--
-- Name: area_detail; Type: TABLE; Schema: data; Owner: geobi; Tablespace: 
--

CREATE TABLE area_detail (
    ad_id integer NOT NULL,
    ar_id integer NOT NULL,
    ar_id_detail integer NOT NULL
);


ALTER TABLE data.area_detail OWNER TO geobi;

--
-- Name: TABLE area_detail; Type: COMMENT; Schema: data; Owner: geobi
--

COMMENT ON TABLE area_detail IS 'ar_id & ar_id_detail are FK to partition table area';


--
-- Name: COLUMN area_detail.ar_id; Type: COMMENT; Schema: data; Owner: geobi
--

COMMENT ON COLUMN area_detail.ar_id IS 'FK to area.ar_id';


--
-- Name: COLUMN area_detail.ar_id_detail; Type: COMMENT; Schema: data; Owner: geobi
--

COMMENT ON COLUMN area_detail.ar_id_detail IS 'FK to area.ar_id';


--
-- Name: area_detail_ad_id_seq; Type: SEQUENCE; Schema: data; Owner: geobi
--

CREATE SEQUENCE area_detail_ad_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE data.area_detail_ad_id_seq OWNER TO geobi;

--
-- Name: area_detail_ad_id_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: geobi
--

ALTER SEQUENCE area_detail_ad_id_seq OWNED BY area_detail.ad_id;


--
-- Name: area_type; Type: TABLE; Schema: data; Owner: geobi; Tablespace: 
--

CREATE TABLE area_type (
    at_id integer NOT NULL,
    at_code character varying(20) NOT NULL,
    at_name_id integer NOT NULL,
    at_order integer DEFAULT 0 NOT NULL,
    at_base_layer boolean DEFAULT false NOT NULL,
    CONSTRAINT area_type_chk CHECK (((at_code)::text ~ '^[0-9A-Z]+'::text))
);


ALTER TABLE data.area_type OWNER TO geobi;

--
-- Name: COLUMN area_type.at_name_id; Type: COMMENT; Schema: data; Owner: geobi
--

COMMENT ON COLUMN area_type.at_name_id IS 'FK to localization.msg_id';


--
-- Name: area_type_at_id_seq; Type: SEQUENCE; Schema: data; Owner: geobi
--

CREATE SEQUENCE area_type_at_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE data.area_type_at_id_seq OWNER TO geobi;

--
-- Name: area_type_at_id_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: geobi
--

ALTER SEQUENCE area_type_at_id_seq OWNED BY area_type.at_id;


SET search_path = geobi, pg_catalog;

--
-- Name: division_type; Type: TABLE; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE TABLE division_type (
    dt_id integer NOT NULL,
    dt_code character varying NOT NULL,
    dt_name character varying NOT NULL,
    dt_order integer DEFAULT 0 NOT NULL
);


ALTER TABLE geobi.division_type OWNER TO geobi;

--
-- Name: division_type_dt_id_seq; Type: SEQUENCE; Schema: geobi; Owner: geobi
--

CREATE SEQUENCE division_type_dt_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE geobi.division_type_dt_id_seq OWNER TO geobi;

--
-- Name: division_type_dt_id_seq; Type: SEQUENCE OWNED BY; Schema: geobi; Owner: geobi
--

ALTER SEQUENCE division_type_dt_id_seq OWNED BY division_type.dt_id;


--
-- Name: groups; Type: TABLE; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE TABLE groups (
    gr_id integer NOT NULL,
    gr_name character varying NOT NULL,
    gr_mod_user integer NOT NULL,
    gr_mod_date timestamp(0) without time zone DEFAULT now() NOT NULL,
    CONSTRAINT groups_gr_name_chk CHECK ((length((gr_name)::text) > 0))
);


ALTER TABLE geobi.groups OWNER TO geobi;

--
-- Name: groups_gr_id_seq; Type: SEQUENCE; Schema: geobi; Owner: geobi
--

CREATE SEQUENCE groups_gr_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE geobi.groups_gr_id_seq OWNER TO geobi;

--
-- Name: groups_gr_id_seq; Type: SEQUENCE OWNED BY; Schema: geobi; Owner: geobi
--

ALTER SEQUENCE groups_gr_id_seq OWNED BY groups.gr_id;


--
-- Name: import_tables; Type: TABLE; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE TABLE import_tables (
    it_id integer NOT NULL,
    it_schema character varying(63) NOT NULL,
    it_table character varying(63) NOT NULL,
    it_sheet character varying,
    it_ckan_package character varying NOT NULL,
    it_ckan_id character varying NOT NULL,
    it_ckan_date timestamp(0) without time zone NOT NULL,
    it_ins_date timestamp(0) without time zone DEFAULT now() NOT NULL,
    it_ckan_valid boolean NOT NULL,
    __has_unique_data boolean DEFAULT false NOT NULL,
    __has_spatial_data boolean DEFAULT false NOT NULL,
    __has_numeric_data boolean DEFAULT false NOT NULL,
    it_is_shape boolean DEFAULT false NOT NULL,
    it_shape_prj_status character(1),
    CONSTRAINT import_tables_chk CHECK ((it_shape_prj_status = ANY (ARRAY['V'::bpchar, 'I'::bpchar, 'M'::bpchar])))
);


ALTER TABLE geobi.import_tables OWNER TO geobi;

--
-- Name: TABLE import_tables; Type: COMMENT; Schema: geobi; Owner: geobi
--

COMMENT ON TABLE import_tables IS 'Table with imported cached data from ckan';


--
-- Name: COLUMN import_tables.it_schema; Type: COMMENT; Schema: geobi; Owner: geobi
--

COMMENT ON COLUMN import_tables.it_schema IS 'Schema name of the table';


--
-- Name: COLUMN import_tables.it_sheet; Type: COMMENT; Schema: geobi; Owner: geobi
--

COMMENT ON COLUMN import_tables.it_sheet IS 'Sheet of the table (eg. excel)';


--
-- Name: COLUMN import_tables.it_ckan_package; Type: COMMENT; Schema: geobi; Owner: geobi
--

COMMENT ON COLUMN import_tables.it_ckan_package IS 'cKan package name';


--
-- Name: COLUMN import_tables.it_ckan_id; Type: COMMENT; Schema: geobi; Owner: geobi
--

COMMENT ON COLUMN import_tables.it_ckan_id IS 'cKan id';


--
-- Name: COLUMN import_tables.it_ckan_date; Type: COMMENT; Schema: geobi; Owner: geobi
--

COMMENT ON COLUMN import_tables.it_ckan_date IS 'cKan timestamp (needed for data refresh)';


--
-- Name: COLUMN import_tables.it_ckan_valid; Type: COMMENT; Schema: geobi; Owner: geobi
--

COMMENT ON COLUMN import_tables.it_ckan_valid IS 'If false the table is invalid (eg: no data)';


--
-- Name: CONSTRAINT import_tables_chk ON import_tables; Type: COMMENT; Schema: geobi; Owner: geobi
--

COMMENT ON CONSTRAINT import_tables_chk ON import_tables IS 'V=valid, I=invalid, M=missing';


--
-- Name: import_tables_detail; Type: TABLE; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE TABLE import_tables_detail (
    itd_id integer NOT NULL,
    it_id integer,
    itd_column character varying(63) NOT NULL,
    itd_name character varying NOT NULL,
    itd_unique_data boolean DEFAULT false NOT NULL,
    itd_spatial_data boolean DEFAULT false NOT NULL,
    itd_numeric_data boolean DEFAULT false NOT NULL
);


ALTER TABLE geobi.import_tables_detail OWNER TO geobi;

--
-- Name: import_tables_data; Type: VIEW; Schema: geobi; Owner: geobi
--

CREATE VIEW import_tables_data AS
    SELECT import_tables.it_id, import_tables.it_schema, import_tables.it_table, import_tables.it_sheet, import_tables.it_ckan_package, import_tables.it_ckan_id, import_tables.it_ckan_date, import_tables.it_ins_date, import_tables.it_ckan_valid, import_tables.it_is_shape, bool_or(import_tables_detail.itd_unique_data) AS itd_unique_data, bool_or(import_tables_detail.itd_spatial_data) AS itd_spatial_data, bool_or(import_tables_detail.itd_numeric_data) AS itd_numeric_data FROM ((import_tables JOIN import_tables_detail USING (it_id)) JOIN information_schema.tables ON ((((tables.table_schema)::text = (import_tables.it_schema)::text) AND ((tables.table_name)::text = (import_tables.it_table)::text)))) WHERE (((tables.table_schema)::text = 'impexp'::text) AND ((tables.table_type)::text = 'BASE TABLE'::text)) GROUP BY import_tables.it_id, import_tables.it_schema, import_tables.it_table, import_tables.it_sheet, import_tables.it_ckan_package, import_tables.it_ckan_id, import_tables.it_ckan_date, import_tables.it_ins_date, import_tables.it_ckan_valid, import_tables.it_is_shape;


ALTER TABLE geobi.import_tables_data OWNER TO geobi;

--
-- Name: import_tables_detail_itd_id_seq; Type: SEQUENCE; Schema: geobi; Owner: geobi
--

CREATE SEQUENCE import_tables_detail_itd_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE geobi.import_tables_detail_itd_id_seq OWNER TO geobi;

--
-- Name: import_tables_detail_itd_id_seq; Type: SEQUENCE OWNED BY; Schema: geobi; Owner: geobi
--

ALTER SEQUENCE import_tables_detail_itd_id_seq OWNED BY import_tables_detail.itd_id;


--
-- Name: import_tables_it_id_seq; Type: SEQUENCE; Schema: geobi; Owner: geobi
--

CREATE SEQUENCE import_tables_it_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE geobi.import_tables_it_id_seq OWNER TO geobi;

--
-- Name: import_tables_it_id_seq; Type: SEQUENCE OWNED BY; Schema: geobi; Owner: geobi
--

ALTER SEQUENCE import_tables_it_id_seq OWNED BY import_tables.it_id;


--
-- Name: language; Type: TABLE; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE TABLE language (
    lang_id character(2) NOT NULL,
    lang_name character varying,
    lang_name_en character varying,
    lang_active boolean DEFAULT false,
    lang_order integer
);


ALTER TABLE geobi.language OWNER TO geobi;

--
-- Name: layer_type; Type: TABLE; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE TABLE layer_type (
    lt_id integer NOT NULL,
    lt_code character varying NOT NULL,
    lt_name character varying NOT NULL,
    lt_order integer DEFAULT 0 NOT NULL
);


ALTER TABLE geobi.layer_type OWNER TO geobi;

--
-- Name: layer_type_lt_id_seq; Type: SEQUENCE; Schema: geobi; Owner: geobi
--

CREATE SEQUENCE layer_type_lt_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE geobi.layer_type_lt_id_seq OWNER TO geobi;

--
-- Name: layer_type_lt_id_seq; Type: SEQUENCE OWNED BY; Schema: geobi; Owner: geobi
--

ALTER SEQUENCE layer_type_lt_id_seq OWNED BY layer_type.lt_id;


--
-- Name: localization; Type: TABLE; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE TABLE localization (
    loc_id integer NOT NULL,
    msg_id integer NOT NULL,
    lang_id character(2) NOT NULL,
    loc_text text NOT NULL
);


ALTER TABLE geobi.localization OWNER TO geobi;

--
-- Name: localization_loc_id_seq; Type: SEQUENCE; Schema: geobi; Owner: geobi
--

CREATE SEQUENCE localization_loc_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE geobi.localization_loc_id_seq OWNER TO geobi;

--
-- Name: localization_loc_id_seq; Type: SEQUENCE OWNED BY; Schema: geobi; Owner: geobi
--

ALTER SEQUENCE localization_loc_id_seq OWNED BY localization.loc_id;


--
-- Name: map; Type: TABLE; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE TABLE map (
    map_id integer NOT NULL,
    map_name character varying NOT NULL,
    map_description character varying,
    lang_id character(2) NOT NULL,
    map_private boolean DEFAULT false NOT NULL,
    us_id integer NOT NULL,
    map_mod_date timestamp(0) without time zone,
    map_background_type character varying NOT NULL,
    map_id_parent integer,
    map_hash character varying NOT NULL,
    map_click_count integer DEFAULT 0 NOT NULL,
    map_ins_date timestamp(0) without time zone DEFAULT now() NOT NULL,
    map_temporary boolean DEFAULT false NOT NULL,
    map_user_extent character varying,
    map_background_active boolean DEFAULT true
);


ALTER TABLE geobi.map OWNER TO geobi;

--
-- Name: COLUMN map.map_temporary; Type: COMMENT; Schema: geobi; Owner: geobi
--

COMMENT ON COLUMN map.map_temporary IS 'If true the map is temporary and can be deleted (after x seconds)';


--
-- Name: COLUMN map.map_user_extent; Type: COMMENT; Schema: geobi; Owner: geobi
--

COMMENT ON COLUMN map.map_user_extent IS 'Map extent (defined by the user)';


--
-- Name: map_class; Type: TABLE; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE TABLE map_class (
    mc_id integer NOT NULL,
    ml_id integer,
    mc_order integer NOT NULL,
    mc_name character varying,
    mc_number double precision,
    mc_text character varying,
    mc_color character varying,
    CONSTRAINT map_class_chk CHECK ((((mc_number IS NOT NULL) AND (mc_text IS NULL)) OR ((mc_number IS NULL) AND (mc_text IS NOT NULL))))
);


ALTER TABLE geobi.map_class OWNER TO geobi;

--
-- Name: COLUMN map_class.mc_name; Type: COMMENT; Schema: geobi; Owner: geobi
--

COMMENT ON COLUMN map_class.mc_name IS 'Class title';


--
-- Name: COLUMN map_class.mc_number; Type: COMMENT; Schema: geobi; Owner: geobi
--

COMMENT ON COLUMN map_class.mc_number IS 'Numeric value';


--
-- Name: COLUMN map_class.mc_text; Type: COMMENT; Schema: geobi; Owner: geobi
--

COMMENT ON COLUMN map_class.mc_text IS 'Text value';


--
-- Name: map_class_mc_id_seq; Type: SEQUENCE; Schema: geobi; Owner: geobi
--

CREATE SEQUENCE map_class_mc_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE geobi.map_class_mc_id_seq OWNER TO geobi;

--
-- Name: map_class_mc_id_seq; Type: SEQUENCE OWNED BY; Schema: geobi; Owner: geobi
--

ALTER SEQUENCE map_class_mc_id_seq OWNED BY map_class.mc_id;


--
-- Name: map_layer; Type: TABLE; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE TABLE map_layer (
    ml_id integer NOT NULL,
    map_id integer,
    ml_order integer NOT NULL,
    ml_name character varying NOT NULL,
    lt_id integer NOT NULL,
    dt_id integer,
    ml_divisions smallint,
    ml_precision smallint DEFAULT 0,
    ml_unit character varying,
    ml_nodata_color character varying,
    ml_mod_date timestamp(0) without time zone NOT NULL,
    ml_temporary boolean NOT NULL,
    ml_schema character varying NOT NULL,
    ml_table character varying NOT NULL,
    ml_ckan_package character varying NOT NULL,
    ml_ckan_id character varying NOT NULL,
    ml_ckan_sheet character varying NOT NULL,
    ml_is_shape boolean NOT NULL,
    ml_data_column character varying,
    ml_opacity integer,
    at_id integer,
    ml_outline_color character varying,
    ml_min_size integer,
    ml_max_size integer,
    ml_size_type character varying,
    ml_symbol character varying,
    ml_active boolean DEFAULT true NOT NULL,
    ml_spatial_column character varying,
    ml_spatial_column_header character varying,
    ml_data_column_header character varying,
    CONSTRAINT map_layer_chk CHECK (((ml_is_shape IS TRUE) OR (ml_data_column IS NOT NULL))),
    CONSTRAINT map_layer_chk2 CHECK ((((dt_id IS NULL) AND (ml_data_column IS NULL)) OR ((dt_id IS NOT NULL) AND (ml_data_column IS NOT NULL))))
);


ALTER TABLE geobi.map_layer OWNER TO geobi;

--
-- Name: COLUMN map_layer.ml_spatial_column; Type: COMMENT; Schema: geobi; Owner: geobi
--

COMMENT ON COLUMN map_layer.ml_spatial_column IS 'Name of the column with names of spatial entity (not geometry)';


--
-- Name: map_layer_ml_id_seq; Type: SEQUENCE; Schema: geobi; Owner: geobi
--

CREATE SEQUENCE map_layer_ml_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE geobi.map_layer_ml_id_seq OWNER TO geobi;

--
-- Name: map_layer_ml_id_seq; Type: SEQUENCE OWNED BY; Schema: geobi; Owner: geobi
--

ALTER SEQUENCE map_layer_ml_id_seq OWNED BY map_layer.ml_id;


--
-- Name: map_map_id_seq; Type: SEQUENCE; Schema: geobi; Owner: geobi
--

CREATE SEQUENCE map_map_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE geobi.map_map_id_seq OWNER TO geobi;

--
-- Name: map_map_id_seq; Type: SEQUENCE OWNED BY; Schema: geobi; Owner: geobi
--

ALTER SEQUENCE map_map_id_seq OWNED BY map.map_id;


--
-- Name: message_msg_id_seq; Type: SEQUENCE; Schema: geobi; Owner: geobi
--

CREATE SEQUENCE message_msg_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE geobi.message_msg_id_seq OWNER TO geobi;

--
-- Name: message_msg_id_seq; Type: SEQUENCE OWNED BY; Schema: geobi; Owner: geobi
--

ALTER SEQUENCE message_msg_id_seq OWNED BY localization.msg_id;


--
-- Name: user; Type: TABLE; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE TABLE "user" (
    us_id integer DEFAULT nextval(('geobi.user_us_id_seq'::text)::regclass) NOT NULL,
    us_password character varying,
    us_status character(1) DEFAULT 'D'::bpchar NOT NULL,
    us_name character varying NOT NULL,
    us_email character varying NOT NULL,
    us_pw_last_change date,
    us_last_ip character varying,
    us_last_login timestamp(0) without time zone,
    us_mod_user integer DEFAULT 0 NOT NULL,
    us_mod_date timestamp(0) without time zone DEFAULT now() NOT NULL,
    lang_id character(2),
    gr_id integer NOT NULL,
    us_validation_hash character varying(32),
    us_validation_hash_created_time timestamp without time zone,
    us_reset_password_hash character varying(32),
    us_reset_password_hash_created_time timestamp without time zone,
    CONSTRAINT users_us_password_chk CHECK (((us_password IS NULL) OR (length((us_password)::text) > 0))),
    CONSTRAINT users_us_status_chk CHECK ((us_status = ANY (ARRAY['E'::bpchar, 'D'::bpchar, 'X'::bpchar, 'W'::bpchar])))
);


ALTER TABLE geobi."user" OWNER TO geobi;

--
-- Name: CONSTRAINT users_us_status_chk ON "user"; Type: COMMENT; Schema: geobi; Owner: geobi
--

COMMENT ON CONSTRAINT users_us_status_chk ON "user" IS 'E=Enabled
D=Disabled
X=Removed (Needed for log pourpose only)
W=Wait to email confirm';


--
-- Name: user_us_id_seq; Type: SEQUENCE; Schema: geobi; Owner: geobi
--

CREATE SEQUENCE user_us_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE geobi.user_us_id_seq OWNER TO geobi;

--
-- Name: user_us_id_seq; Type: SEQUENCE OWNED BY; Schema: geobi; Owner: geobi
--

ALTER SEQUENCE user_us_id_seq OWNED BY "user".us_id;


SET search_path = data, pg_catalog;

--
-- Name: ar_id; Type: DEFAULT; Schema: data; Owner: geobi
--

ALTER TABLE ONLY area ALTER COLUMN ar_id SET DEFAULT nextval('area_ar_id_seq'::regclass);


--
-- Name: ad_id; Type: DEFAULT; Schema: data; Owner: geobi
--

ALTER TABLE ONLY area_detail ALTER COLUMN ad_id SET DEFAULT nextval('area_detail_ad_id_seq'::regclass);


--
-- Name: at_id; Type: DEFAULT; Schema: data; Owner: geobi
--

ALTER TABLE ONLY area_type ALTER COLUMN at_id SET DEFAULT nextval('area_type_at_id_seq'::regclass);


SET search_path = geobi, pg_catalog;

--
-- Name: dt_id; Type: DEFAULT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY division_type ALTER COLUMN dt_id SET DEFAULT nextval('division_type_dt_id_seq'::regclass);


--
-- Name: gr_id; Type: DEFAULT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY groups ALTER COLUMN gr_id SET DEFAULT nextval('groups_gr_id_seq'::regclass);


--
-- Name: it_id; Type: DEFAULT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY import_tables ALTER COLUMN it_id SET DEFAULT nextval('import_tables_it_id_seq'::regclass);


--
-- Name: itd_id; Type: DEFAULT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY import_tables_detail ALTER COLUMN itd_id SET DEFAULT nextval('import_tables_detail_itd_id_seq'::regclass);


--
-- Name: lt_id; Type: DEFAULT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY layer_type ALTER COLUMN lt_id SET DEFAULT nextval('layer_type_lt_id_seq'::regclass);


--
-- Name: loc_id; Type: DEFAULT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY localization ALTER COLUMN loc_id SET DEFAULT nextval('localization_loc_id_seq'::regclass);


--
-- Name: map_id; Type: DEFAULT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY map ALTER COLUMN map_id SET DEFAULT nextval('map_map_id_seq'::regclass);


--
-- Name: mc_id; Type: DEFAULT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY map_class ALTER COLUMN mc_id SET DEFAULT nextval('map_class_mc_id_seq'::regclass);


--
-- Name: ml_id; Type: DEFAULT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY map_layer ALTER COLUMN ml_id SET DEFAULT nextval('map_layer_ml_id_seq'::regclass);


SET search_path = data, pg_catalog;

--
-- Data for Name: area_type; Type: TABLE DATA; Schema: data; Owner: geobi
--

INSERT INTO area_type (at_id, at_code, at_name_id, at_order, at_base_layer) VALUES (1, 'NATION', 0, 0, true);
INSERT INTO area_type (at_id, at_code, at_name_id, at_order, at_base_layer) VALUES (2, 'REGION', 0, 0, true);
INSERT INTO area_type (at_id, at_code, at_name_id, at_order, at_base_layer) VALUES (3, 'PROVINCE', 0, 0, true);
INSERT INTO area_type (at_id, at_code, at_name_id, at_order, at_base_layer) VALUES (4, 'MUNICIPALITY', 0, 0, true);


--
-- Name: area_type_at_id_seq; Type: SEQUENCE SET; Schema: data; Owner: geobi
--

SELECT pg_catalog.setval('area_type_at_id_seq', 4, true);


SET search_path = geobi, pg_catalog;

--
-- Data for Name: division_type; Type: TABLE DATA; Schema: geobi; Owner: geobi
--

INSERT INTO division_type (dt_id, dt_code, dt_name, dt_order) VALUES (1, 'manual', 'Manual', 0);
INSERT INTO division_type (dt_id, dt_code, dt_name, dt_order) VALUES (2, 'natural', 'Natural', 0);
INSERT INTO division_type (dt_id, dt_code, dt_name, dt_order) VALUES (3, 'quantile', 'Quantile', 0);
INSERT INTO division_type (dt_id, dt_code, dt_name, dt_order) VALUES (4, 'quantile-round', 'Quantile (Round)', 0);


--
-- Name: division_type_dt_id_seq; Type: SEQUENCE SET; Schema: geobi; Owner: geobi
--

SELECT pg_catalog.setval('division_type_dt_id_seq', 4, true);


--
-- Data for Name: groups; Type: TABLE DATA; Schema: geobi; Owner: geobi
--

INSERT INTO groups (gr_id, gr_name, gr_mod_user, gr_mod_date) VALUES (1, 'ADMIN', 0, '2014-10-14 11:49:27');
INSERT INTO groups (gr_id, gr_name, gr_mod_user, gr_mod_date) VALUES (2, 'MAP_PRODUCER', 0, '2014-10-14 12:04:30');


--
-- Name: groups_gr_id_seq; Type: SEQUENCE SET; Schema: geobi; Owner: geobi
--

SELECT pg_catalog.setval('groups_gr_id_seq', 2, true);


--
-- Data for Name: language; Type: TABLE DATA; Schema: geobi; Owner: geobi
--

INSERT INTO language (lang_id, lang_name, lang_name_en, lang_active, lang_order) VALUES ('it', 'Italiano', 'Italian', true, 1);
INSERT INTO language (lang_id, lang_name, lang_name_en, lang_active, lang_order) VALUES ('en', 'English', 'English', true, 1);
INSERT INTO language (lang_id, lang_name, lang_name_en, lang_active, lang_order) VALUES ('de', 'Deutsch', 'German', true, 1);


--
-- Data for Name: layer_type; Type: TABLE DATA; Schema: geobi; Owner: geobi
--

INSERT INTO layer_type (lt_id, lt_code, lt_name, lt_order) VALUES (1, 'fill', 'Fill', 0);
INSERT INTO layer_type (lt_id, lt_code, lt_name, lt_order) VALUES (2, 'point', 'Point', 1);
INSERT INTO layer_type (lt_id, lt_code, lt_name, lt_order) VALUES (3, 'pie', 'Pie', 0);
INSERT INTO layer_type (lt_id, lt_code, lt_name, lt_order) VALUES (4, 'bar', 'Bar', 0);


--
-- Name: layer_type_lt_id_seq; Type: SEQUENCE SET; Schema: geobi; Owner: geobi
--

SELECT pg_catalog.setval('layer_type_lt_id_seq', 4, true);


--
-- Data for Name: user; Type: TABLE DATA; Schema: geobi; Owner: geobi
--

INSERT INTO "user" (us_id, us_password, us_status, us_name, us_email, us_pw_last_change, us_last_ip, us_last_login, us_mod_user, us_mod_date, lang_id, gr_id, us_validation_hash, us_validation_hash_created_time, us_reset_password_hash, us_reset_password_hash_created_time) VALUES (0, '$2y$12$ayjcGEVtuY36syesHwPU0esitvXP1/ZjQCaQJoP2PbjoQmD2Fb0GO', 'E', 'admin', 'admin@geobi', NULL, NULL, NULL, 1, NOW(), NULL, 1, NULL, NULL, NULL, NULL);


--
-- Name: user_us_id_seq; Type: SEQUENCE SET; Schema: geobi; Owner: geobi
--

SELECT pg_catalog.setval('user_us_id_seq', 1, true);


SET search_path = data, pg_catalog;

--
-- Name: area_ar_code_key; Type: CONSTRAINT; Schema: data; Owner: geobi; Tablespace: 
--

ALTER TABLE ONLY area
    ADD CONSTRAINT area_ar_code_key UNIQUE (ar_code) WITH (fillfactor=100);


--
-- Name: area_detail_pkey; Type: CONSTRAINT; Schema: data; Owner: geobi; Tablespace: 
--

ALTER TABLE ONLY area_detail
    ADD CONSTRAINT area_detail_pkey PRIMARY KEY (ad_id);


--
-- Name: area_pkey; Type: CONSTRAINT; Schema: data; Owner: geobi; Tablespace: 
--

ALTER TABLE ONLY area
    ADD CONSTRAINT area_pkey PRIMARY KEY (ar_id) WITH (fillfactor=100);


--
-- Name: area_type_at_code_key; Type: CONSTRAINT; Schema: data; Owner: geobi; Tablespace: 
--

ALTER TABLE ONLY area_type
    ADD CONSTRAINT area_type_at_code_key UNIQUE (at_code);


--
-- Name: area_type_idx; Type: CONSTRAINT; Schema: data; Owner: geobi; Tablespace: 
--

ALTER TABLE ONLY area_type
    ADD CONSTRAINT area_type_idx PRIMARY KEY (at_id);

ALTER TABLE area_type CLUSTER ON area_type_idx;


SET search_path = geobi, pg_catalog;

--
-- Name: division_type_pkey; Type: CONSTRAINT; Schema: geobi; Owner: geobi; Tablespace: 
--

ALTER TABLE ONLY division_type
    ADD CONSTRAINT division_type_pkey PRIMARY KEY (dt_id);


--
-- Name: groups_pkey; Type: CONSTRAINT; Schema: geobi; Owner: geobi; Tablespace: 
--

ALTER TABLE ONLY groups
    ADD CONSTRAINT groups_pkey PRIMARY KEY (gr_id);


--
-- Name: import_tables_detail_pkey; Type: CONSTRAINT; Schema: geobi; Owner: geobi; Tablespace: 
--

ALTER TABLE ONLY import_tables_detail
    ADD CONSTRAINT import_tables_detail_pkey PRIMARY KEY (itd_id);


--
-- Name: import_tables_pkey; Type: CONSTRAINT; Schema: geobi; Owner: geobi; Tablespace: 
--

ALTER TABLE ONLY import_tables
    ADD CONSTRAINT import_tables_pkey PRIMARY KEY (it_id);


--
-- Name: language_pkey; Type: CONSTRAINT; Schema: geobi; Owner: geobi; Tablespace: 
--

ALTER TABLE ONLY language
    ADD CONSTRAINT language_pkey PRIMARY KEY (lang_id);


--
-- Name: layer_type_pkey; Type: CONSTRAINT; Schema: geobi; Owner: geobi; Tablespace: 
--

ALTER TABLE ONLY layer_type
    ADD CONSTRAINT layer_type_pkey PRIMARY KEY (lt_id);


--
-- Name: localization_pkey; Type: CONSTRAINT; Schema: geobi; Owner: geobi; Tablespace: 
--

ALTER TABLE ONLY localization
    ADD CONSTRAINT localization_pkey PRIMARY KEY (loc_id);


--
-- Name: map_class_pkey; Type: CONSTRAINT; Schema: geobi; Owner: geobi; Tablespace: 
--

ALTER TABLE ONLY map_class
    ADD CONSTRAINT map_class_pkey PRIMARY KEY (mc_id);


--
-- Name: map_idx; Type: CONSTRAINT; Schema: geobi; Owner: geobi; Tablespace: 
--

ALTER TABLE ONLY map
    ADD CONSTRAINT map_idx PRIMARY KEY (map_id);


--
-- Name: map_layer_pkey; Type: CONSTRAINT; Schema: geobi; Owner: geobi; Tablespace: 
--

ALTER TABLE ONLY map_layer
    ADD CONSTRAINT map_layer_pkey PRIMARY KEY (ml_id);


--
-- Name: users_pkey; Type: CONSTRAINT; Schema: geobi; Owner: geobi; Tablespace: 
--

ALTER TABLE ONLY "user"
    ADD CONSTRAINT users_pkey PRIMARY KEY (us_id);


SET search_path = data, pg_catalog;

--
-- Name: area_detail_idx1; Type: INDEX; Schema: data; Owner: geobi; Tablespace: 
--

CREATE UNIQUE INDEX area_detail_idx1 ON area_detail USING btree (ar_id, ar_id_detail);

ALTER TABLE area_detail CLUSTER ON area_detail_idx1;


SET search_path = geobi, pg_catalog;

--
-- Name: import_tables_detail_idx; Type: INDEX; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE UNIQUE INDEX import_tables_detail_idx ON import_tables_detail USING btree (it_id, itd_column);


--
-- Name: import_tables_idx; Type: INDEX; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE INDEX import_tables_idx ON import_tables USING btree (it_ckan_package, it_ckan_id, it_id);


--
-- Name: import_tables_idx1; Type: INDEX; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE UNIQUE INDEX import_tables_idx1 ON import_tables USING btree (it_ckan_package, it_ckan_id, (COALESCE(it_sheet, ''::character varying)));


--
-- Name: loc_text_de_idx; Type: INDEX; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE INDEX loc_text_de_idx ON localization USING gin (to_tsvector('german'::regconfig, loc_text)) WHERE (lang_id = 'de'::bpchar);


--
-- Name: loc_text_en_idx; Type: INDEX; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE INDEX loc_text_en_idx ON localization USING gin (to_tsvector('english'::regconfig, loc_text)) WHERE (lang_id = 'en'::bpchar);


--
-- Name: loc_text_it_idx; Type: INDEX; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE INDEX loc_text_it_idx ON localization USING gin (to_tsvector('italian'::regconfig, loc_text)) WHERE (lang_id = 'it'::bpchar);


--
-- Name: localization_msg_id_lang_id_ix; Type: INDEX; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE UNIQUE INDEX localization_msg_id_lang_id_ix ON localization USING btree (msg_id, lang_id);

ALTER TABLE localization CLUSTER ON localization_msg_id_lang_id_ix;


--
-- Name: map_class_idx; Type: INDEX; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE UNIQUE INDEX map_class_idx ON map_class USING btree (ml_id, mc_order);


--
-- Name: map_idx1; Type: INDEX; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE UNIQUE INDEX map_idx1 ON map USING btree (map_hash);


--
-- Name: map_layer_idx; Type: INDEX; Schema: geobi; Owner: geobi; Tablespace: 
--

CREATE UNIQUE INDEX map_layer_idx ON map_layer USING btree (map_id, ml_order);


SET search_path = data, pg_catalog;

--
-- Name: check_area_detail_tr; Type: TRIGGER; Schema: data; Owner: geobi
--

CREATE TRIGGER check_area_detail_tr BEFORE INSERT OR UPDATE ON area_detail FOR EACH ROW EXECUTE PROCEDURE check_area_detail_fk();


--
-- Name: insert_area_trigger; Type: TRIGGER; Schema: data; Owner: geobi
--

CREATE TRIGGER insert_area_trigger BEFORE INSERT ON area FOR EACH ROW EXECUTE PROCEDURE area_insert_trigger();


--
-- Name: area_fk; Type: FK CONSTRAINT; Schema: data; Owner: geobi
--

ALTER TABLE ONLY area
    ADD CONSTRAINT area_fk FOREIGN KEY (at_id) REFERENCES area_type(at_id);


SET search_path = geobi, pg_catalog;

--
-- Name: import_tables_detail_fk; Type: FK CONSTRAINT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY import_tables_detail
    ADD CONSTRAINT import_tables_detail_fk FOREIGN KEY (it_id) REFERENCES import_tables(it_id) ON DELETE CASCADE;


--
-- Name: localization_lang_id_fkey; Type: FK CONSTRAINT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY localization
    ADD CONSTRAINT localization_lang_id_fkey FOREIGN KEY (lang_id) REFERENCES language(lang_id);


--
-- Name: map_class_fk; Type: FK CONSTRAINT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY map_class
    ADD CONSTRAINT map_class_fk FOREIGN KEY (ml_id) REFERENCES map_layer(ml_id) ON DELETE CASCADE;


--
-- Name: map_fk; Type: FK CONSTRAINT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY map
    ADD CONSTRAINT map_fk FOREIGN KEY (lang_id) REFERENCES language(lang_id);


--
-- Name: map_fk1; Type: FK CONSTRAINT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY map
    ADD CONSTRAINT map_fk1 FOREIGN KEY (us_id) REFERENCES "user"(us_id);


--
-- Name: map_fk2; Type: FK CONSTRAINT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY map
    ADD CONSTRAINT map_fk2 FOREIGN KEY (map_id_parent) REFERENCES map(map_id) DEFERRABLE INITIALLY DEFERRED;


--
-- Name: map_layer_fk; Type: FK CONSTRAINT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY map_layer
    ADD CONSTRAINT map_layer_fk FOREIGN KEY (map_id) REFERENCES map(map_id);


--
-- Name: map_layer_fk1; Type: FK CONSTRAINT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY map_layer
    ADD CONSTRAINT map_layer_fk1 FOREIGN KEY (lt_id) REFERENCES layer_type(lt_id);


--
-- Name: user_fk; Type: FK CONSTRAINT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY "user"
    ADD CONSTRAINT user_fk FOREIGN KEY (gr_id) REFERENCES groups(gr_id);


--
-- Name: users_fk; Type: FK CONSTRAINT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY "user"
    ADD CONSTRAINT users_fk FOREIGN KEY (us_mod_user) REFERENCES "user"(us_id) ON UPDATE CASCADE ON DELETE SET DEFAULT;


--
-- Name: users_fk1; Type: FK CONSTRAINT; Schema: geobi; Owner: geobi
--

ALTER TABLE ONLY "user"
    ADD CONSTRAINT users_fk1 FOREIGN KEY (lang_id) REFERENCES language(lang_id);


--
-- Name: data; Type: ACL; Schema: -; Owner: geobi
--

REVOKE ALL ON SCHEMA data FROM PUBLIC;
REVOKE ALL ON SCHEMA data FROM geobi;
GRANT ALL ON SCHEMA data TO geobi;


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


SET search_path = data, pg_catalog;

--
-- Name: area_insert_trigger(); Type: ACL; Schema: data; Owner: geobi
--

REVOKE ALL ON FUNCTION area_insert_trigger() FROM PUBLIC;
REVOKE ALL ON FUNCTION area_insert_trigger() FROM geobi;
GRANT ALL ON FUNCTION area_insert_trigger() TO geobi;
GRANT ALL ON FUNCTION area_insert_trigger() TO PUBLIC;


--
-- Name: check_area_detail_fk(); Type: ACL; Schema: data; Owner: geobi
--

REVOKE ALL ON FUNCTION check_area_detail_fk() FROM PUBLIC;
REVOKE ALL ON FUNCTION check_area_detail_fk() FROM geobi;
GRANT ALL ON FUNCTION check_area_detail_fk() TO geobi;
GRANT ALL ON FUNCTION check_area_detail_fk() TO PUBLIC;


--
-- Name: area; Type: ACL; Schema: data; Owner: geobi
--

REVOKE ALL ON TABLE area FROM PUBLIC;
REVOKE ALL ON TABLE area FROM geobi;
GRANT ALL ON TABLE area TO geobi;


--
-- Name: area_detail; Type: ACL; Schema: data; Owner: geobi
--

REVOKE ALL ON TABLE area_detail FROM PUBLIC;
REVOKE ALL ON TABLE area_detail FROM geobi;
GRANT ALL ON TABLE area_detail TO geobi;


--
-- Name: area_type; Type: ACL; Schema: data; Owner: geobi
--

REVOKE ALL ON TABLE area_type FROM PUBLIC;
REVOKE ALL ON TABLE area_type FROM geobi;
GRANT ALL ON TABLE area_type TO geobi;

SET search_path = public, pg_catalog;
SELECT AddGeometryColumn ('data','area','the_geom',3857,'MULTIPOLYGON', 2);
CREATE INDEX area_the_geom_gist ON data.area USING gist(the_geom);
ALTER TABLE data.area CLUSTER ON area_the_geom_gist;

