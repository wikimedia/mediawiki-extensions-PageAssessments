PageAssessments
=============

See https://www.mediawiki.org/wiki/Extension:PageAssessments for detailed documentation.

This extension is for the purposes of storing article assessments as they happen in a new database table. This extension was designed keeping in mind Wikiprojects use-cases but it can be used for a number of other similar purposes.

The parser function for invoking a new review is: {{#assessment: <Name of the wikiproject> | <Class> | <Importance>}}.

Configuration
-------------

The following configuration variables can be set from your LocalSettings.php file.

* `$wgPageAssessmentsOnTalkPages`: Set to 'true' if page assessments are recorded on talk pages, or 'false' if page assessments are recorded directly on main namespace pages. Default is true.
* `$wgPageAssessmentsSubprojects`: Set to 'true' if the wiki distinguishes between projects and subprojects. Default is false.
