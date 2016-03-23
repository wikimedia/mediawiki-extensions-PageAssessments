-- Add article assessments table

CREATE TABLE IF NOT EXISTS /*_*/page_assessments (
	pa_page_id          INT UNSIGNED NOT NULL,      -- ID of the page
	pa_project_id       INT UNSIGNED NOT NULL,      -- ID of the project assessing the page
	pa_class            VARCHAR(20) DEFAULT NULL,   -- class of the page, e.g. 'B'
	pa_importance       VARCHAR(20) DEFAULT NULL,   -- importance of the page for the project, e.g. 'Low'
	pa_page_revision    INT UNSIGNED NOT NULL,      -- revision of the page upon assessment
	PRIMARY KEY (pa_page_id, pa_project_id)
)/*$wgDBTableOptions*/;

CREATE INDEX /*i*/pa_project ON /*_*/ page_assessments (pa_project_id, pa_page_id);
