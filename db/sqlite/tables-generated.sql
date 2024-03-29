-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: db\tables.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/page_assessments_projects (
  pap_project_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  pap_project_title VARCHAR(255) DEFAULT NULL,
  pap_parent_id INTEGER UNSIGNED DEFAULT NULL
);

CREATE UNIQUE INDEX pap_project_title ON /*_*/page_assessments_projects (pap_project_title);


CREATE TABLE /*_*/page_assessments (
  pa_page_id INTEGER UNSIGNED NOT NULL,
  pa_project_id INTEGER UNSIGNED NOT NULL,
  pa_class VARCHAR(20) DEFAULT NULL,
  pa_importance VARCHAR(20) DEFAULT NULL,
  pa_page_revision INTEGER UNSIGNED NOT NULL,
  PRIMARY KEY(pa_page_id, pa_project_id)
);

CREATE INDEX pa_project ON /*_*/page_assessments (pa_project_id, pa_page_id);
