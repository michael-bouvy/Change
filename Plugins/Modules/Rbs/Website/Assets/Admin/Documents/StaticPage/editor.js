(function ($) {

	"use strict";

	function changeEditorWebsitePage ($rootScope, REST, Breadcrumb, MainMenu)
	{
		return {
			restrict    : 'A',
			templateUrl : 'Document/Rbs/Website/StaticPage/editor.twig',
			replace     : false,
			require     : 'rbsDocumentEditor',

			link : function (scope, element, attrs, editorCtrl) {

				scope.onReady = function ()
				{
					if (!scope.document.section && Breadcrumb.getCurrentNode()) {
						scope.document.section = Breadcrumb.getCurrentNode();
					}
				};

				scope.initSection = function (sectionName)
				{
					if (sectionName === 'content') {
						if (scope.document.pageTemplate)
						{
							REST.resource(scope.document.pageTemplate).then(function (template)
							{
								scope.pageTemplate = { "html" : template.htmlForBackoffice, "data" : template.editableContent };
							});
						}
					}
				};


				scope.leaveSection = function (section)
				{
					if (section === 'content') {
						$('#rbsWebsitePageDefaultAsides').show();
						$('#rbsWebsitePageBlockPropertiesAside').hide();
					}

				};

				scope.enterSection = function (section)
				{
					if (section === 'content') {
						$('#rbsWebsitePageDefaultAsides').hide();
						$('#rbsWebsitePageBlockPropertiesAside').show();
					}
				};

				editorCtrl.init('Rbs_Website_StaticPage');

				// This is for the "undo" dropdown menu:
				// Each item automatically activates its previous siblings.
				$('[data-role=undo-menu]').on('mouseenter', 'li', function ()
				{
					$(this).siblings().removeClass('active');
					$(this).prevAll().addClass('active');
				});

				$rootScope.$watch('website', function (website) {
					if (scope.document && ! scope.document.website) {
						scope.document.website = website;
					}
				}, true);

			}
		};

	}

	var app = angular.module('RbsChange');

	changeEditorWebsitePage.$inject = [
		'$rootScope',
		'RbsChange.REST',
		'RbsChange.Breadcrumb',
		'RbsChange.MainMenu'
	];
	app.directive('rbsDocumentEditorRbsWebsiteStaticpage', changeEditorWebsitePage);


	/**
	 * Localized version of the editor.
	 */
	function changeEditorWebsitePageTranslate (REST)
	{
		return {
			restrict    : 'A',
			templateUrl : 'Document/Rbs/Website/StaticPage/editor-translate.twig',
			replace     : false,
			require     : 'rbsDocumentEditor',

			link : function (scope, element, attrs, editorCtrl) {
				scope.onLoad = function ()
				{
					// Load PageTemplate Document
					if (scope.document.pageTemplate) {
						REST.resource(scope.document.pageTemplate).then(function (template) {
							scope.pageTemplate = { "html" : template.htmlForBackoffice, "data" : template.editableContent };
						});
					}
				};
				editorCtrl.init('Rbs_Website_StaticPage');
			}
		};
	}

	changeEditorWebsitePageTranslate.$inject = [
		'RbsChange.REST'
	];

	app.directive('rbsDocumentEditorRbsWebsiteStaticpageTranslate', changeEditorWebsitePageTranslate);

})(window.jQuery);