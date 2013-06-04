(function ()
{
	var app = angular.module('RbsChange');

	app.config(['$routeProvider', function ($routeProvider)
	{
		$routeProvider.when('/Rbs/Media', { templateUrl: 'Rbs/Media/Image/list.twig', reloadOnSearch: false })
			.when('/Rbs/Media/Image', { templateUrl: 'Rbs/Media/Image/list.twig', reloadOnSearch: false })
			.when('/Rbs/Media/Image/:id/:LCID', { templateUrl: 'Rbs/Media/Image/form.twig', reloadOnSearch: false })
			.when('/Rbs/Media/Image/:id', { templateUrl: 'Rbs/Media/Image/form.twig', reloadOnSearch: false });
	}]);

	app.config(['$provide', function ($provide)
	{
		$provide.decorator('RbsChange.UrlManager', ['$delegate', function ($delegate)
		{
			$delegate.register('Rbs_Media_Image', {
				'form': '/Rbs/Media/Image/:id/:LCID',
				'list': '/Rbs/Media/Image/:LCID',
				'i18n': '/Rbs/Media/Image/:id/:LCID/translate-from/:fromLCID'
			});
			return $delegate;
		}]);
	}]);
})();