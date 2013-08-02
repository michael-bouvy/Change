(function ()
{
	"use strict";

	var app = angular.module('RbsChange');

	/**
	 * Controller for list.
	 *
	 * @param $scope
	 * @param Breadcrumb
	 * @param MainMenu
	 * @param i18n
	 * @param ArrayUtils
	 * @param Plugins
	 * @constructor
	 */
	function ListController($scope, Breadcrumb, MainMenu, i18n, ArrayUtils, Plugins)
	{
		Breadcrumb.resetLocation([
			[i18n.trans('m.rbs.plugins.admin.js.module-name | ucf'), "Rbs/Plugins/Installed"]
		]);

		$scope.plugins = Plugins.getNew().then(function (data){
			$scope.plugins = data;
		});

		$scope.verify = function (plugin){ Plugins.verify(plugin).then(function (pluginInfos){
			$scope.pluginInfos = pluginInfos;
		}, function (pluginInfos){
			$scope.pluginInfos = pluginInfos;
		}) };

		$scope.verifyAll = function (){ Plugins.verifyAll($scope.plugins) };

		$scope.register = function (plugin){ Plugins.register(plugin).then(function (){
			ArrayUtils.removeValue($scope.plugins, plugin);
		}); };

		//sort
		$scope.predicate = 'vendor';
		$scope.reverse = false;
		$scope.isSortedOn = function (column) { return column == $scope.predicate; };

		MainMenu.loadModuleMenu('Rbs_Plugins');
	}

	ListController.$inject = ['$scope', 'RbsChange.Breadcrumb', 'RbsChange.MainMenu', 'RbsChange.i18n', 'RbsChange.ArrayUtils', 'RbsChange.Plugins'];
	app.controller('Rbs_Plugins_Registered_ListController', ListController);

})();