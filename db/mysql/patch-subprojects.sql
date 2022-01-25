-- ID of the parent project (for subprojects and task forces)
ALTER TABLE /*_*/page_assessments_projects ADD COLUMN pap_parent_id INT UNSIGNED NULL DEFAULT NULL;
-- Increase size of pap_project_title column to accommodate subprojects
ALTER TABLE /*_*/page_assessments_projects MODIFY pap_project_title VARCHAR(255) DEFAULT NULL;
