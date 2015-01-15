SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

CREATE SCHEMA data;
CREATE SCHEMA geobi;
CREATE SCHEMA impexp;

CREATE EXTENSION IF NOT EXISTS unaccent;
CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;
COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';

SET search_path = data, pg_catalog;

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

SET search_path = geobi, pg_catalog;

CREATE FUNCTION map_children_of(map_id integer) RETURNS TABLE(map_id integer, map_parent_id integer, map_hash character varying, us_id integer, map_temporary boolean)
    LANGUAGE sql
    AS $_$
    WITH RECURSIVE data(map_id, map_id_parent) AS (
        SELECT map_id, map_id_parent, map_hash, us_id, map_temporary 
        FROM geobi.map t1
        WHERE map_id_parent=$1
        UNION
		SELECT t2.map_id, t2.map_id_parent, t2.map_hash, t2.us_id, t2.map_temporary 
        FROM geobi.map t2, data AS t1
        WHERE t2.map_id_parent=t1.map_id
    )
    SELECT map_id, map_id_parent, map_hash, us_id, map_temporary 
    FROM data;
$_$;

ALTER FUNCTION geobi.map_children_of(map_id integer) OWNER TO geobi;

SET search_path = data, pg_catalog;


CREATE TABLE area (
    ar_id serial NOT NULL,
    at_id integer NOT NULL,
    ar_code character varying(40) NOT NULL,
    ar_name_id integer NOT NULL,
    ar_parent_code character varying,
    ar_label_priority smallint,
    the_geom public.geometry NOT NULL,
    ar_name_local character varying,
    CONSTRAINT enforce_dims_the_geom CHECK ((public.st_ndims(the_geom) = 2)),
    CONSTRAINT enforce_geotype_the_geom CHECK (((public.geometrytype(the_geom) = 'MULTIPOLYGON'::text) OR (the_geom IS NULL))),
    CONSTRAINT enforce_is_valid_the_geom CHECK (public.st_isvalid(the_geom)),
    CONSTRAINT enforce_srid_the_geom CHECK ((public.st_srid(the_geom) = 3857))
)
WITH (fillfactor=100);
ALTER TABLE ONLY area ALTER COLUMN ar_code SET STORAGE PLAIN;
ALTER TABLE ONLY area ALTER COLUMN ar_parent_code SET STORAGE MAIN;
ALTER TABLE ONLY area ALTER COLUMN the_geom SET STATISTICS 1000;

COMMENT ON TABLE area IS 'Main area table';
COMMENT ON COLUMN area.ar_code IS 'Fixed length, storage: main';
COMMENT ON COLUMN area.ar_name_id IS 'FK to localization.msg_id';

    
CREATE TABLE area_detail (
    ad_id serial NOT NULL,
    ar_id integer NOT NULL,
    ar_id_detail integer NOT NULL
);
COMMENT ON TABLE area_detail IS 'ar_id & ar_id_detail are FK to partition table area';
COMMENT ON COLUMN area_detail.ar_id IS 'FK to area.ar_id';
COMMENT ON COLUMN area_detail.ar_id_detail IS 'FK to area.ar_id';


CREATE TABLE area_type (
    at_id serial NOT NULL,
    at_code character varying(20) NOT NULL,
    at_name_id integer NOT NULL,
    at_order integer DEFAULT 0 NOT NULL,
    at_base_layer boolean DEFAULT false NOT NULL,
    CONSTRAINT area_type_chk CHECK (((at_code)::text ~ '^[0-9A-Z]+'::text))
);
ALTER TABLE area_type ADD CONSTRAINT area_type_idx PRIMARY KEY (at_id);
COMMENT ON COLUMN area_type.at_name_id IS 'FK to localization.msg_id';


SET search_path = geobi, pg_catalog;

CREATE TABLE division_type (
    dt_id serial NOT NULL,
    dt_code character varying NOT NULL,
    dt_name character varying NOT NULL,
    dt_order integer DEFAULT 0 NOT NULL
);


CREATE TABLE groups (
    gr_id serial NOT NULL,
    gr_name character varying NOT NULL,
    gr_mod_user integer NOT NULL,
    gr_mod_date timestamp(0) without time zone DEFAULT now() NOT NULL,
    CONSTRAINT groups_gr_name_chk CHECK ((length((gr_name)::text) > 0))
);


CREATE TABLE import_tables (
    it_id serial NOT NULL,
    it_schema character varying(63) NOT NULL,
    it_table character varying(63) NOT NULL,
    it_sheet character varying,
    it_ckan_package character varying NOT NULL,
    it_ckan_id character varying NOT NULL,
    it_ckan_date timestamp(0) without time zone NOT NULL,
    it_ins_date timestamp(0) without time zone DEFAULT now() NOT NULL,
    it_ckan_valid boolean NOT NULL,
    it_is_shape boolean DEFAULT false NOT NULL,
    it_shape_prj_status character(1),
    CONSTRAINT import_tables_chk CHECK ((it_shape_prj_status = ANY (ARRAY['V'::bpchar, 'I'::bpchar, 'M'::bpchar])))
);
COMMENT ON TABLE import_tables IS 'Table with imported cached data from ckan';
COMMENT ON COLUMN import_tables.it_schema IS 'Schema name of the table';
COMMENT ON COLUMN import_tables.it_sheet IS 'Sheet of the table (eg. excel)';
COMMENT ON COLUMN import_tables.it_ckan_package IS 'cKan package name';
COMMENT ON COLUMN import_tables.it_ckan_id IS 'cKan id';
COMMENT ON COLUMN import_tables.it_ckan_date IS 'cKan timestamp (needed for data refresh)';
COMMENT ON COLUMN import_tables.it_ckan_valid IS 'If false the table is invalid (eg: no data)';
COMMENT ON CONSTRAINT import_tables_chk ON import_tables IS 'V=valid, I=invalid, M=missing';


CREATE TABLE import_tables_detail (
    itd_id serial NOT NULL,
    it_id integer,
    itd_column character varying(63) NOT NULL,
    itd_name character varying NOT NULL,
    itd_unique_data boolean DEFAULT false NOT NULL,
    itd_spatial_data boolean DEFAULT false NOT NULL,
    itd_numeric_data boolean DEFAULT false NOT NULL
);

CREATE VIEW import_tables_data AS
SELECT import_tables.it_id, import_tables.it_schema, import_tables.it_table, import_tables.it_sheet, import_tables.it_ckan_package, import_tables.it_ckan_id, import_tables.it_ckan_date, import_tables.it_ins_date, import_tables.it_ckan_valid, import_tables.it_is_shape, bool_or(import_tables_detail.itd_unique_data) AS itd_unique_data, bool_or(import_tables_detail.itd_spatial_data) AS itd_spatial_data, bool_or(import_tables_detail.itd_numeric_data) AS itd_numeric_data 
FROM ((import_tables JOIN import_tables_detail USING (it_id)) JOIN information_schema.tables ON ((((tables.table_schema)::text = (import_tables.it_schema)::text) AND ((tables.table_name)::text = (import_tables.it_table)::text)))) WHERE (((tables.table_schema)::text = 'impexp'::text) AND ((tables.table_type)::text = 'BASE TABLE'::text)) 
GROUP BY import_tables.it_id, import_tables.it_schema, import_tables.it_table, import_tables.it_sheet, import_tables.it_ckan_package, import_tables.it_ckan_id, import_tables.it_ckan_date, import_tables.it_ins_date, import_tables.it_ckan_valid, import_tables.it_is_shape;


CREATE TABLE language (
    lang_id character(2) NOT NULL,
    lang_name character varying,
    lang_name_en character varying,
    lang_active boolean DEFAULT false,
    lang_order integer
);

CREATE TABLE layer_type (
    lt_id serial NOT NULL,
    lt_code character varying NOT NULL,
    lt_name character varying NOT NULL,
    lt_order integer DEFAULT 0 NOT NULL
);


CREATE TABLE localization (
    loc_id serial NOT NULL,
    msg_id integer NOT NULL,
    lang_id character(2) NOT NULL,
    loc_text text NOT NULL
);

CREATE SEQUENCE message_msg_id_seq;
ALTER SEQUENCE message_msg_id_seq OWNED BY localization.msg_id;



CREATE TABLE map (
    map_id serial NOT NULL,
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
    map_user_extent box
);
COMMENT ON COLUMN map.map_temporary IS 'If true the map is temporary and can be deleted (after x seconds)';
COMMENT ON COLUMN map.map_user_extent IS 'Map extent (defined by the user)';


CREATE TABLE map_class (
    mc_id serial NOT NULL,
    ml_id integer,
    mc_order integer NOT NULL,
    mc_name character varying,
    mc_number double precision,
    mc_text character varying,
    mc_color character varying,
    CONSTRAINT map_class_chk CHECK ((((mc_number IS NOT NULL) AND (mc_text IS NULL)) OR ((mc_number IS NULL) AND (mc_text IS NOT NULL))))
);
COMMENT ON COLUMN map_class.mc_name IS 'Class title';
COMMENT ON COLUMN map_class.mc_number IS 'Numeric value';
COMMENT ON COLUMN map_class.mc_text IS 'Text value';


CREATE TABLE map_layer (
    ml_id serial NOT NULL,
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
    CONSTRAINT map_layer_chk CHECK (((ml_is_shape IS TRUE) OR (ml_data_column IS NOT NULL))),
    CONSTRAINT map_layer_chk2 CHECK ((((dt_id IS NULL) AND (ml_data_column IS NULL)) OR ((dt_id IS NOT NULL) AND (ml_data_column IS NOT NULL))))
);


CREATE TABLE "user" (
    us_id serial NOT NULL,
    us_login character varying NOT NULL,
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
    CONSTRAINT users_us_login_chk CHECK ((length((us_login)::text) > 0)),
    CONSTRAINT users_us_password_chk CHECK (((us_password IS NULL) OR (length((us_password)::text) > 0))),
    CONSTRAINT users_us_status_chk CHECK ((us_status = ANY (ARRAY['E'::bpchar, 'D'::bpchar, 'X'::bpchar, 'W'::bpchar])))
);

COMMENT ON CONSTRAINT users_us_status_chk ON "user" IS 'E=Enabled
D=Disabled
X=Removed (Needed for log pourpose only)
W=Wait to email confirm';


SET search_path = data, pg_catalog;

INSERT INTO area_type VALUES (1, 'NATION', 1, 0, true);
INSERT INTO area_type VALUES (2, 'REGION', 2, 0, true);
INSERT INTO area_type VALUES (3, 'PROVINCE', 3, 0, true);
INSERT INTO area_type VALUES (4, 'MUNICIPALITY', 4, 0, true);
SELECT pg_catalog.setval('area_type_at_id_seq', 4, true);


SET search_path = geobi, pg_catalog;

INSERT INTO division_type VALUES (1, 'manual', 'Manual', 0);
INSERT INTO division_type VALUES (2, 'natural', 'Natural', 0);
INSERT INTO division_type VALUES (4, 'quantile-round', 'Quantile (Round)', 0);
INSERT INTO division_type VALUES (3, 'quantile', 'Quantile', 0);
SELECT pg_catalog.setval('division_type_dt_id_seq', 4, true);


INSERT INTO groups VALUES (1, 'ADMIN', 0, NOW());
INSERT INTO groups VALUES (2, 'MAP_PRODUCER', 0, NOW());
SELECT pg_catalog.setval('groups_gr_id_seq', 2, true);


INSERT INTO language VALUES ('it', 'Italiano', 'Italian', true, 1);
INSERT INTO language VALUES ('en', 'English', 'English', true, 1);
INSERT INTO language VALUES ('de', 'Deutsch', 'German', true, 1);


INSERT INTO layer_type VALUES (1, 'fill', 'Fill', 0);
INSERT INTO layer_type VALUES (2, 'point', 'Point', 1);
SELECT pg_catalog.setval('layer_type_lt_id_seq', 2, false);


INSERT INTO "user" VALUES (0, 'admin', MD5('admin'), 'E', 'admin', 'admin', NULL, NULL, NULL, 0, NOW(), NULL, 1);
INSERT INTO "user" VALUES (1, 'user', MD5('user'), 'E', 'user', 'User', NULL, NULL, NULL, 0, NOW(), NULL, 2);
SELECT pg_catalog.setval('user_us_id_seq', 2, false);


SET search_path = data, pg_catalog;

ALTER TABLE ONLY area ADD CONSTRAINT area_pkey PRIMARY KEY (ar_id) WITH (fillfactor=100);
CREATE UNIQUE INDEX area_ar_code_key ON area USING btree (ar_code) WITH (fillfactor = 100);


ALTER TABLE ONLY area_detail ADD CONSTRAINT area_detail_pkey PRIMARY KEY (ad_id);
CREATE UNIQUE INDEX area_type_at_code_key ON area_type USING btree (at_code);
ALTER TABLE area_type CLUSTER ON area_type_at_code_key;


SET search_path = geobi, pg_catalog;

ALTER TABLE ONLY division_type ADD CONSTRAINT division_type_pkey PRIMARY KEY (dt_id);

ALTER TABLE ONLY groups ADD CONSTRAINT groups_pkey PRIMARY KEY (gr_id);

ALTER TABLE ONLY import_tables_detail ADD CONSTRAINT import_tables_detail_pkey PRIMARY KEY (itd_id);

ALTER TABLE ONLY import_tables ADD CONSTRAINT import_tables_pkey PRIMARY KEY (it_id);

ALTER TABLE ONLY language  ADD CONSTRAINT language_pkey PRIMARY KEY (lang_id);

ALTER TABLE ONLY layer_type ADD CONSTRAINT layer_type_pkey PRIMARY KEY (lt_id);

ALTER TABLE ONLY localization ADD CONSTRAINT localization_pkey PRIMARY KEY (loc_id);

ALTER TABLE ONLY map_class ADD CONSTRAINT map_class_pkey PRIMARY KEY (mc_id);

ALTER TABLE ONLY map ADD CONSTRAINT map_idx PRIMARY KEY (map_id);

ALTER TABLE ONLY map_layer ADD CONSTRAINT map_layer_pkey PRIMARY KEY (ml_id);

ALTER TABLE ONLY "user" ADD CONSTRAINT users_pkey PRIMARY KEY (us_id);


SET search_path = data, pg_catalog;

CREATE UNIQUE INDEX area_detail_idx1 ON area_detail USING btree (ar_id, ar_id_detail);
ALTER TABLE area_detail CLUSTER ON area_detail_idx1;

CREATE INDEX area_the_geom_gist ON area USING gist (the_geom);
ALTER TABLE area CLUSTER ON area_the_geom_gist;


SET search_path = geobi, pg_catalog;

CREATE UNIQUE INDEX import_tables_detail_idx ON import_tables_detail USING btree (it_id, itd_column);
CREATE INDEX import_tables_idx ON import_tables USING btree (it_ckan_package, it_ckan_id, it_id);
CREATE UNIQUE INDEX import_tables_idx1 ON import_tables USING btree (it_ckan_package, it_ckan_id, (COALESCE(it_sheet, ''::character varying)));
CREATE INDEX loc_text_de_idx ON localization USING gin (to_tsvector('german'::regconfig, loc_text)) WHERE (lang_id = 'de'::bpchar);
CREATE INDEX loc_text_en_idx ON localization USING gin (to_tsvector('english'::regconfig, loc_text)) WHERE (lang_id = 'en'::bpchar);
CREATE INDEX loc_text_it_idx ON localization USING gin (to_tsvector('italian'::regconfig, loc_text)) WHERE (lang_id = 'it'::bpchar);

CREATE UNIQUE INDEX localization_msg_id_lang_id_ix ON localization USING btree (msg_id, lang_id);

ALTER TABLE localization CLUSTER ON localization_msg_id_lang_id_ix;

CREATE UNIQUE INDEX map_class_idx ON map_class USING btree (ml_id, mc_order);

CREATE UNIQUE INDEX map_idx1 ON map USING btree (map_hash);

CREATE UNIQUE INDEX map_layer_idx ON map_layer USING btree (map_id, ml_order);

CREATE UNIQUE INDEX users_idx1 ON "user" USING btree (us_login, us_status);


SET search_path = data, pg_catalog;

CREATE TRIGGER check_area_detail_tr BEFORE INSERT OR UPDATE ON area_detail FOR EACH ROW EXECUTE PROCEDURE check_area_detail_fk();

CREATE TRIGGER insert_area_trigger BEFORE INSERT ON area FOR EACH ROW EXECUTE PROCEDURE area_insert_trigger();

ALTER TABLE ONLY area ADD CONSTRAINT area_fk FOREIGN KEY (at_id) REFERENCES area_type(at_id);


SET search_path = geobi, pg_catalog;

ALTER TABLE ONLY import_tables_detail ADD CONSTRAINT import_tables_detail_fk FOREIGN KEY (it_id) REFERENCES import_tables(it_id) ON DELETE CASCADE;

ALTER TABLE ONLY localization ADD CONSTRAINT localization_lang_id_fkey FOREIGN KEY (lang_id) REFERENCES language(lang_id);

ALTER TABLE ONLY map_class ADD CONSTRAINT map_class_fk FOREIGN KEY (ml_id) REFERENCES map_layer(ml_id) ON DELETE CASCADE;

ALTER TABLE ONLY map ADD CONSTRAINT map_fk FOREIGN KEY (lang_id) REFERENCES language(lang_id);

ALTER TABLE ONLY map ADD CONSTRAINT map_fk1 FOREIGN KEY (us_id) REFERENCES "user"(us_id);

ALTER TABLE ONLY map ADD CONSTRAINT map_fk2 FOREIGN KEY (map_id_parent) REFERENCES map(map_id) DEFERRABLE INITIALLY DEFERRED;

ALTER TABLE ONLY map_layer ADD CONSTRAINT map_layer_fk FOREIGN KEY (map_id) REFERENCES map(map_id);

ALTER TABLE ONLY map_layer ADD CONSTRAINT map_layer_fk1 FOREIGN KEY (lt_id) REFERENCES layer_type(lt_id);

ALTER TABLE ONLY "user" ADD CONSTRAINT user_fk FOREIGN KEY (gr_id) REFERENCES groups(gr_id);

ALTER TABLE ONLY "user" ADD CONSTRAINT users_fk FOREIGN KEY (us_mod_user) REFERENCES "user"(us_id) ON UPDATE CASCADE ON DELETE SET DEFAULT;

ALTER TABLE ONLY "user" ADD CONSTRAINT users_fk1 FOREIGN KEY (lang_id) REFERENCES language(lang_id);


REVOKE ALL ON SCHEMA data FROM PUBLIC;


SET search_path = data, pg_catalog;

REVOKE ALL ON FUNCTION area_insert_trigger() FROM PUBLIC;

REVOKE ALL ON FUNCTION check_area_detail_fk() FROM PUBLIC;

REVOKE ALL ON TABLE area FROM PUBLIC;

REVOKE ALL ON TABLE area_detail FROM PUBLIC;

REVOKE ALL ON TABLE area_type FROM PUBLIC;

