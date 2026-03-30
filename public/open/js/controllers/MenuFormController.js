'use strict';

MetronicApp.controller('MenuFormController', [
    '$rootScope', '$scope', '$http', '$log', '$stateParams', 'Notification', function($rootScope, $scope, $http, $log, $stateParams, Notification) {


    var form_loading = function(flag) {
        loading($('#menu-form-portlet'), flag);
    };

    $scope.$on('$viewContentLoaded', function() {
        // initialize core components
        Metronic.initAjax();
    });

    var dh = $rootScope.$on('menu_form_request', function(e, menu) {

        $log.debug("Render menu form: id:" + menu.id + "; type:" + menu.type + "; visiable:" + menu.visiable);

        if (!menu.id) {
            $scope.title = '创建新页面';
            $scope.menu = menu;
            return;
        } else {
            $rootScope.$broadcast('register_data_version', {
                name : 'menu',
                pk : menu.id,
                onSelect : function(version_info, version_data) {
                    $scope.menu = angular.fromJson(version_data);
                    $scope.menu.visiable = $scope.menu.visiable == 1;
                    $scope.refresh = true;
                }
            });
        }

        form_loading(true);

        $scope.title = '更新页面';

        get_json('/menu/detail.json', { id : menu.id }, function(data){
            form_loading(false);
            $scope.menu = data.menu;
            $scope.refresh = true;
        });
    });

    $scope.$on('$destroy', dh);

    $scope.title = '创建新页面';

    $scope.contentEditorOptions = {
        mode : 'application/x-dataddy',
        extraKeys: {"Cmd-/": "toggleComment"},
        lineNumbers: true,
        lineWrapping: true,
        matchBrackets: true,
        indentWithTabs: true,
        indentUnit: 4
    };

    $scope.error_line = [];
    $scope.line_widget = [];

    $scope.codemirrorLoaded = function(editor) {
        if (editor._dataddy) return;

        editor._dataddy = true;

        var pending;
        var current_mode = 'application/x-dataddy';
        var syntaxCheckXhr = null;
        var syntaxCheckSeq = 0;

        function clearSyntaxWidgets() {
            $scope.line_widget.forEach(function (line) {
                line.clear();
            });
            $scope.error_line = [];
            $scope.line_widget = [];
        }

        function renderSyntaxErrors(editors, res) {
            clearSyntaxWidgets();
            if (res.code !== 0 && res.data) {
                $log.error("PHP代码语法错误：" + res.data.error);
                var line = (res.data.line || 1) - 1;
                var div = document.createElement("div");
                div.setAttribute('class', 'code-error-widget');
                div.appendChild(document.createTextNode(res.data.error));
                if ($scope.line_widget[line] === undefined) {
                    $scope.line_widget[line] = editors.addLineWidget(line, div, true);
                }
            }
        }

        editor.on('change', debounce(function(editors){

            if($scope.menu !== undefined) {
                var id = $scope.menu.id;
                var val = editors.getValue();
                var requestSeq = ++syntaxCheckSeq;

                if (syntaxCheckXhr && syntaxCheckXhr.readyState !== 4) {
                    syntaxCheckXhr.abort();
                }

                syntaxCheckXhr = $.ajax({
                    url: '/report/syntaxCheck',
                    data: {id:id, code: val},
                    contentType: 'application/x-www-form-urlencoded;charset=utf-8',
                    type: "POST",
                    dataType: 'json',
                    timeout: 10000,
                    success: function (res) {
                        if (requestSeq !== syntaxCheckSeq) {
                            return;
                        }
                        renderSyntaxErrors(editors, res);
                    },
                    error: function (e) {
                        if (e && e.statusText === 'abort') {
                            return;
                        }
                    }
                });
            }
             clearTimeout(pending);
             pending = setTimeout(check_mode_update, 600);
        }, 900));

        function check_mode_update() {
            var mode = 'application/x-dataddy';
            var value = editor.getValue();
            if (/^#!markdown/i.test(value)) {
                mode = 'markdown';
            }

            if (current_mode != mode) {
                $log.debug("Change codemirror mode:" + mode);
                editor.setOption("mode", mode);
                current_mode = mode;
            }
        }

        check_mode_update();
    };

    $scope.settingEditorOptions = {
        mode : { name : "javascript", json : true },
        extraKeys: {"Cmd-/": "toggleComment"},
        lineNumbers: true,
        lineWrapping: true,
        matchBrackets: true,
        indentWithTabs: true,
        indentUnit: 4
    };

    if ($rootScope.session?.config?.editor?.vim_mode) {
        $scope.contentEditorOptions.keyMap = "vim";
        $scope.settingEditorOptions.keyMap = "vim";
    }

    $('#menu-form').on('submit', function(event){
        event.preventDefault();

        $log.debug('Save menu:' + $scope.menu.name);

        form_loading(true);

        $http.post('/menu/save?format=json', $scope.menu).success(function(ret){

            form_loading(false);

            if (ret && ret.code == 0) {
                $scope.menu = ret.data;
                Notification.success($scope.title + "【" + $scope.menu.name + "】成功！");
                $rootScope.$broadcast('menu_update', $scope.menu);
            } else {
                Notification.error(ret ? ret.message : '系统错误');
            }
        });
    });

    $scope.cancel = function() {
        $rootScope.$broadcast('menu_form_cancel');
    };
}]);
