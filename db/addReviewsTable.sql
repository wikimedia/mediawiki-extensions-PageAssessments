-- Add article assessments table

CREATE TABLE IF NOT EXISTS /*_*/page_assessments (
	pa_page_id          INT(10) NOT NULL,
	pa_page_name        VARCHAR(255) NOT NULL,
	pa_page_namespace   INT(11) NOT NULL,
	pa_project          VARCHAR(128) DEFAULT NULL,
	pa_class            VARCHAR(20) DEFAULT NULL,
	pa_importance       VARCHAR(20) DEFAULT NULL,
	pa_page_revision    INT(10) NOT NULL
)/*$wgDBTableOptions*/;

CREATE INDEX /*i*/pa_project ON /*_*/ page_assessments (pa_project);
CREATE INDEX /*i*/pa_page_name ON /*_*/ page_assessments (pa_page_name);
CREATE UNIQUE INDEX /*i*/pa_page_project ON /*_*/ page_assessments (pa_page_name, pa_project);
