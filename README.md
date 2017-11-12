PageAssessments
=============

See https://www.mediawiki.org/wiki/Extension:PageAssessments for detailed documentation.

This extension is for the purposes of storing article assessments in a database table and providing an API for retrieving them. This extension was primarily designed to support WikiProjects, but it can be used for a number of other similar purposes.

The parser function for invoking a new review is: {{#assessment: <Name of the WikiProject> | <Class> | <Importance>}}. Typically this parser function will be embedded in an assessment template that is then transcluded on an article's talk page.

If the extension is configured to support subprojects (see Configuration below), the subproject should follow the project name and be separated with a slash. For example, to record an assessment for the Crime task force of WikiProject Novels, you would use an assessment like: {{#assessment:Novels/Crime task force|B|Low}}

Configuration
-------------

The following configuration variables can be set from your LocalSettings.php file.

* `$wgPageAssessmentsOnTalkPages`: Set to 'true' if page assessments are recorded on talk pages, or 'false' if page assessments are recorded directly on main namespace pages. Default is true.
* `$wgPageAssessmentsSubprojects`: Set to 'true' if the wiki distinguishes between projects and subprojects. Default is false.
