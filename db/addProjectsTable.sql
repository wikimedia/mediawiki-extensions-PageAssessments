-- Add wikiprojects table

CREATE TABLE IF NOT EXISTS /*_*/page_assessments_projects (
  pap_project_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,  -- generated ID of the project
  pap_project_title   VARCHAR(128) DEFAULT NULL,             -- name of the project assessing the page
  PRIMARY KEY (pap_project_id)
)/*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/pap_project_title ON /*_*/ page_assessments_projects (pap_project_title);
