-- Add wikiprojects table

CREATE TABLE IF NOT EXISTS /*_*/page_assessments_projects (
  -- Generated ID of the project
  pap_project_id INT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Name of the project assessing the page. In the case of a subproject or task
  -- force, this will be a combination of the project and subproject name, e.g.
  -- Films/Korean cinema task force.
  pap_project_title VARCHAR(255) DEFAULT NULL,

  -- ID of the parent project (for subprojects and task forces)
  pap_parent_id INT UNSIGNED NULL DEFAULT NULL,

  PRIMARY KEY (pap_project_id)
)/*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/pap_project_title ON /*_*/ page_assessments_projects (pap_project_title);
