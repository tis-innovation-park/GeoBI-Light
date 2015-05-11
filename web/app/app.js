(function () {
    "use strict";


        /*      CONFIG      */

    var app = angular.module('geobi', ['ui.router', 'ngDialog', 'colorpicker.module']);

    app.config(function($stateProvider, $urlRouterProvider){
        $stateProvider.state('homepage', {
            url: '/',
            templateUrl: 'app/homepage/homepage.html',
        });

        $stateProvider.state('map', {
            url: '/map/:hash',
            templateUrl: 'app/map/map.html',
            onExit: ['storageFactory', function(storageFactory) {
                var hashList = storageFactory.load('hashList');
                if (hashList) {
                    for (var i=0; i<hashList.length; i++) {
                        storageFactory.remove(hashList[i]);
                    }
                    storageFactory.save('hashList', []);
                }
            }]
        });

        $stateProvider.state('user', {
            url: '/user',
            templateUrl: 'app/users/users.html',
        });

        $stateProvider.state('login', {
            url: '/login',
            templateUrl: 'app/auth/login.html',
        });

        $stateProvider.state('logout', {
            url: '/logout',
            controller: 'LogoutController'
        });

        $stateProvider.state('signup', {
            url: '/signup',
            templateUrl: 'app/auth/signup.html',
        });

        $stateProvider.state('thankyou', {
            url: '/thankyou',
            templateUrl: 'app/auth/thankyou.html',
        });

        $stateProvider.state('passwordreset', {
            url: '/passwordreset',
            templateUrl: 'app/auth/passwordreset.html',
        });

        $stateProvider.state('reset', {
            url: '/reset/:hash',
            templateUrl: 'app/auth/reset.html',
        });

        $urlRouterProvider.otherwise("/");
    });

    app.config(['ngDialogProvider', function (ngDialogProvider) {
        ngDialogProvider.setDefaults({
            className: 'ngdialog-theme-geobi',
            showClose: true,
            closeByDocument: false,
            closeByEscape: true
        });
    }]);

        /*      CONTROLLERS     */

    app.controller('AddLayerController', function(apiFactory, storageFactory, ngDialog){
        var controller = this;
        controller.ckanData = [];

        function init() {
            controller.getImportData();
        }

        controller.checkCkanPackage = function(){
            controller.ckanId = controller.ckanPackage.resources[0];
        };

        controller.getImportData = function(){
            apiFactory.getImportData().success(function(res){
                if(res.success) {
                    controller.ckanData = res.result;
                    controller.ckanPackage = res.result[0];
                    controller.checkCkanPackage();
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };

        init();
    });

    app.controller('AppController', function($rootScope, $state, apiFactory, authFactory, storageFactory){
        var controller = this;
        controller.user = {};
        controller.lang = {
            it: 'italiano',
            de: 'deutsch',
            en: 'english'
        };
        controller.currentLang = 'it';

        function init(){
            $rootScope.text = {};
            storageFactory.save('lang', controller.lang);
            storageFactory.save('currentLang', controller.currentLang);
            controller.getTranslation('app');
            controller.getSetting('app');
        }

        controller.isAdmin = function() {
            return authFactory.isAdmin();
        };

        controller.isAuth = function() {
            if (authFactory.isAuth()) {
                var auth = storageFactory.load('auth');
                controller.user.name = auth.name;
                return true;
            }
           return false;
        };

        controller.getSetting = function(page) {
            var lang = controller.currentLang;
            if (page === 'app') {
                lang = null;
                page = 'global';
            }
            apiFactory.getSetting(lang, page).success(function(res) {
                if (res.success) {
                    controller.setting = res.result.data;
                    storageFactory.save('setting', controller.setting);
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };

        controller.getTranslation = function(page) {
            apiFactory.getTranslation(controller.currentLang, page).success(function(res){
                if (res.success) {
                    $rootScope.text[page] = res.result.data;
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };
        
        controller.setLang = function(lang){
            controller.currentLang = lang;
            storageFactory.save('currentLang', controller.currentLang);
            controller.getTranslation('app');
            controller.getTranslation($state.current.name);
            controller.getSetting('app');
            controller.getTranslation($state.current.name);
            $rootScope.$broadcast('languageChanged');
        };

        init();
    });

    app.controller('CaptchaController', function(apiFactory) {
        var controller = this;

        function init() {
            controller.getCaptcha();
        }

        controller.getCaptcha = function() {
            apiFactory.getCaptcha().success(function(res) {
                if (res.success) {
                    controller.image = res.result;
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };

        init();
    });

    app.controller('CopyMapController', function($location, apiFactory, storageFactory) {
        var controller = this;
        controller.languages = storageFactory.load('lang');
        controller.lang = storageFactory.load('currentLang');

        controller.copyMap = function(hash){
            apiFactory.copyMap(hash, controller.lang).success(function(res) {
                if (res.success) {
                    $location.url('map/' +res.result.hash);
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };
    });

    app.controller('DuplicateMapController', function($scope, $location, apiFactory) {
        var controller = this;
        controller.data = [];
        controller.filters = {
            priv_only: false,
            q: '',
            it: false,
            de: false,
            en: false
        };
        controller.paginations = {
            limit: 5,
            offset: 0
        };
        controller.total = 0;

        function init() {
            controller.getMaps();
        }

        controller.areAllMapsLoading = function() {
            if (controller.total) {
                return (controller.paginations.offset + controller.paginations.limit) >= controller.total;
            }
            return true;
        };

        controller.duplicate = function(hash) {
            apiFactory.copyMap(hash, $scope.ngDialogData.lang).success(function(res) {
                if (res.success) {
                    $location.url('map/' +res.result.hash);
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };

        controller.getMaps = function() {
            var filters = {
                priv_only: controller.filters.priv_only,
                q: controller.filters.q,
                it: controller.filters.it,
                de: controller.filters.de,
                en: controller.filters.en,
                limit: controller.paginations.limit,
                offset: controller.paginations.offset
            };
            apiFactory.getMaps(filters).success(function(res) {
                if (res.success) {
                    controller.total = res.total;
                    controller.data = res.result;
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };

        controller.next = function() {
            controller.paginations.offset += controller.paginations.limit;
            controller.getMaps();
        };

        controller.previous = function() {
            controller.paginations.offset -= controller.paginations.limit;
            controller.getMaps();
        };

        controller.search = function() {
            controller.total = 0;
            controller.paginations.offset = 0;
            controller.getMaps();
        };

        init();
    });

    app.controller('editDataLayerController', function($scope, apiFactory) {
        var controller = this;
        var hash = null;
        var order = null;
        controller.headers = [];
        controller.rows = [];
        controller.tempData = [];
        var total = null;
        var params = {
            offset: 0,
            limit: 5
        };


        function init() {
            hash = $scope.ngDialogData.hash;
            order = $scope.ngDialogData.order;
            apiFactory.checkDataLayer(hash, order, params).success(function(res) {
                if (res.success) {
                    controller.header = res.result.header;
                    controller.rows = res.result.rows;
                    params.offset = params.offset + params.limit;
                    total = res.total;

                    for (var i = 0; i < controller.rows.length; i++) {
                        controller.getAutocompleteOptions(i, controller.rows[i].spatialColumn);
                    }
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        }

        function removeRecord(i) {
            controller.rows.splice(i, 1);
            controller.tempData.splice(i,1);
        }

        function nextRecord() {
            if (params.offset < total) {
                params.limit = 1;
                apiFactory.checkDataLayer(hash, order, params).success(function(res) {
                if (res.success) {
                    params.offset = params.offset + params.limit;
                    controller.rows.push(res.result.rows[0]);
                    controller.getAutocompleteOptions(controller.rows.length -1, controller.rows[controller.rows.length -1].spatialColumn);
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
            }

        }

        controller.cancelData = function(i) {
            removeRecord(i);
            nextRecord();
        };

        controller.getAutocompleteOptions = function(i, text) {
            text = text.trim();
            if (text.length > 1) {
                apiFactory.getAutocompleteOptions(hash, order, text).success(function(res) {
                    if(res.success) {
                        var tmp = {
                            autoComplete: res.result
                        };
                        controller.tempData[i] = tmp;
                    }
                }).error(function(res){
                    alert('Error:\n' + res.result.error.text);
                    console.log(res);
                });
            }
        };

        controller.saveData = function(i) {
            var data = [
                {
                    id: controller.rows[i].id,
                    dataColumn: controller.rows[i].dataColumn,
                    spatialColumn: controller.tempData[i].correctValue.value,
                    spatialCode: controller.tempData[i].correctValue.key,
                }
            ];
            apiFactory.saveCorrectData(hash, order, data).success(function(res) {
                if (res.success) {
                    //console.log(res);
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
            controller.cancelData(i);
        };


        init();
    });

    app.controller('ImportTableController', function($scope, $filter, apiFactory) {
        var controller = this;
        
        function init() {
            $scope.ngDialogData.sheet = $scope.ngDialogData.sheetData[0];
            controller.checkSheet();
        }

        controller.checkSheet = function() {
            $scope.ngDialogData.spatialColumnData = $filter('geometryColumnFilter')($scope.ngDialogData.sheet.headers);
            $scope.ngDialogData.numericColumnData = $filter('numericColumnFilter')($scope.ngDialogData.sheet.headers);
            $scope.ngDialogData.spatialColumn = $scope.ngDialogData.spatialColumnData[0];
            $scope.ngDialogData.numericColumn = $scope.ngDialogData.numericColumnData[0];
            controller.getDatasetData();
        };

        controller.getDatasetData = function() {
            var params = {
                package: $scope.ngDialogData.package,
                id: $scope.ngDialogData.id,
                sheet: $scope.ngDialogData.sheet.code
            };
            apiFactory.getDatasetData(params).success(function(res) {
                if (res.success) {
                    controller.data = res.result;
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };

        init();
    });

    app.controller('LoginController', function($state, apiFactory, authFactory, ngDialog) {
        var controller = this;
        controller.credential = {};

        function init() {
            if (authFactory.isAuth()) {
                $state.go('logout');
            }
        }

        controller.login = function() {
            apiFactory.login(controller.credential.email, controller.credential.password).success(function(res, status, headers) {
                if (res.success) {
                    var token = headers('X-WSSE');
                    authFactory.auth(res.result, token);
                    $state.go('homepage');
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };

        controller.resetPassword = function() {
            apiFactory.resetPassword(controller.credential).success(function(res) {
                if (res.success) {
                    ngDialog.openConfirm({
                        template: 'common/successDialog.html'
                    }).then(function() {
                        $state.go('login');
                    });
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };

        init();
    });

    app.controller('LogoutController', function($state, authFactory) {
        var controller = this;

        function init() {
            authFactory.deauth();
            $state.go('homepage');
        }

        init();
    });

    app.controller('MapController', function($rootScope, $scope, $stateParams, $timeout, apiFactory, storageFactory, ngDialog) {
        var controller = this;
        var map = null;
        var toolTipTimer = null;
        var t = null;
        controller.mapLock = true;
        controller.configMap = {
            controls: [
                new OpenLayers.Control.KeyboardDefaults(),
                new OpenLayers.Control.Navigation(),
                new OpenLayers.Control.Attribution()
            ]
        };
        controller.configLayers = {};
        controller.data = {};
        controller.hash = null;
        controller.currentHash = null;
        controller.layers = {};

        function init() {
            controller.hash = $stateParams.hash;
            controller.currentHash = controller.hash;
            apiFactory.getMap(controller.hash).success(function(res) {
                if (res.success) {
                    var hashList = [];
                    controller.configMap.projection = new OpenLayers.Projection(res.result.map.displayProjection); 

                    hashList.push(controller.hash);
                    storageFactory.save('hashList', hashList);
                    storageFactory.save(res.result.hash, res.result.map);

                    initMap();
                    controller.loadMap(controller.hash);
                    if(controller.data.userExtent) {
                        controller.zoomToExtent(controller.data.userExtent);
                    } else {
                        controller.zoomToExtent(controller.data.extent);
                    }
                    if(controller.data.isMine) {
                        if (controller.layers.length === 1) {
                            controller.addLayerFromCkan();
                        }
                    }
                }
            }).error(function(res) {
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });

            loadSetting();
        }

        function addHighlightLayer(hash, order, id, t) {
            controller.setting = storageFactory.load('setting');
            var wmsBaseUrl = controller.setting.baseUrl +'map/stat/'+ hash +'/stat/'+ order;
            var layer = new OpenLayers.Layer.WMS('Highlight Layer '+ order +'_'+ id, wmsBaseUrl, {order: order, t: t, highlight: id, layers: 'stat', format: 'image/png; mode=8bit'}, {singleTile: true, isBaseLayer: false, noMagic: true, opacity: 1, visibility: true});
            map.addLayer(layer);            
        }

        function checkUpdateMap(hash) {
            apiFactory.checkUpdateMap(hash).success(function(res) {
                if (res.success) {
                    controller.update = [];
                    for (var i=0; i<res.result.length; i++) {
                        controller.update[res.result[i].order] = res.result[i];
                    }
                }
            }).error(function(res) {
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        }

        function initMap() {
            map = new OpenLayers.Map('map', controller.configMap);
            
            // Add osm
            var mapnik = new OpenLayers.Layer.OSM("Base Layer 0");
            map.addLayer(mapnik);
            map.events.register('click', map, function(e) {
                toolTip(e.xy);
            });
            map.events.register('moveend', map, function(e) {
                var extent = map.getExtent();
                controller.data.userExtent = [extent.left, extent.bottom, extent.right, extent.top];
            });
        }

        function loadSetting () {
            var lang = storageFactory.load('currentLang');
            controller.configLayers = storageFactory.load('setting-'+ lang + 'map');

            if (!storageFactory.load('setting-' + lang + 'map')) {
                apiFactory.getSetting(lang, 'map').success(function(res){
                    if (res.success) {
                        controller.configLayers = res.result.data;
                        storageFactory.save('setting-' + lang + 'map', res.result.data);
                    }
                }).error(function(res){
                    alert('Error:\n' + res.result.error.text);
                    console.log(res);
                });
            }
        }

        function removeHighlightLayer() {
            var OLlayersHighlight = map.getLayersByName(/Highlight Layer/);
            for (var i=0; i < OLlayersHighlight.length; i++) {
                map.removeLayer(OLlayersHighlight[i]);
            }
        }

        function removeTooltip() {
            if (controller.popup) {
                /* remove all Highlight layer */
                removeHighlightLayer();
                map.removePopup(controller.popup);
            }
        }

        function toolTip(xy) {
            var params = {};
            var t = new Date().getTime();

            var position = map.getLonLatFromViewPortPx(xy);
            params.lon = position.lon;
            params.lat = position.lat;

            var extent = map.getExtent();

            var callApi = function(layer) {
                var unit = layer.options.unit? layer.options.unit: '';
                apiFactory.getStatInfo(controller.currentHash, layer.order, params).success(function(res) {
                    if (res.success) {
                        for (var j=0; j<res.result.length; j++) {
                            var hl = map.getLayersByName(/Highlight Layer/);
                            if (hl.length === 0 || !(hl[0].params.ORDER === layer.order && hl[0].params.HIGHLIGHT === res.result[j].id)) {
                                if(hl.length > 0 && hl[0].params.T === t && hl[0].params.ORDER > layer.order) {
                                    return;
                                }
                                for (var k=0; k<hl.length; k++) {
                                    map.removeLayer(hl[k]);
                                }
                                removeTooltip();
                                addHighlightLayer(controller.currentHash, layer.order, res.result[j].id, t);
                                var html = '<b>'+ layer.name +'</b><br />Name: '+ res.result[j].name +'<br />Data: '+ res.result[j].data +' '+ unit;
                                controller.popup = new OpenLayers.Popup("Stat info", position, new OpenLayers.Size(200,100), html, true, removeTooltip);
                                map.addPopup(controller.popup);
                            }
                        }
                    }
                }).error(function(res){
                    alert('Error:\n' + res.result.error.text);
                    console.log(res);
                }); 
            };

            for (var i = 1; i < controller.layers.length; i++) {
                var layer = controller.layers[i];
                if(!layer.active) {
                    continue;
                }
                var scale = map.getScale();
                params.buffer = 3 * (scale/1000); //set the ideal buffer (3) on map unit (1000) and scale it!
                callApi(layer);
            }
        }

        controller.addLayerFromCkan = function() {
            ngDialog.open({
                template: 'app/map/AddLayer/addLayer.html',
                controller: 'AddLayerController as addLayer',
                preCloseCallback: function(returned) {
                    if(returned.hasOwnProperty('callback')) {
                        controller[returned.callback](returned.data);
                    }
                    return true;
                }
            });
        };

        controller.addMapLayer = function(layer) {
            controller.setting = storageFactory.load('setting');
            var t = new Date().getTime();
            var wmsBaseUrl = controller.setting.baseUrl +'map/stat/'+ controller.currentHash +'/stat/'+ layer.order;
            var OLlayer = new OpenLayers.Layer.WMS('Stats Layer ' + layer.order, wmsBaseUrl, {
                t: t,
                layers: 'stat',
                format: 'image/png; mode=8bit'
            }, {
                singleTile: true,
                isBaseLayer: false,
                noMagic: true,
                visibility: layer.active
            });
            map.addLayer(OLlayer);
        };

        controller.closePanel = function() {
            controller.panelToggleClass = 'panelClose';
            window.setTimeout(function(){map.updateSize();},100);
            
        };

        controller.download = function() {
            window.location = controller.setting.baseUrl + 'map/stat/' + controller.currentHash +'/1024/1024/preview.png?download&extent=' + map.calculateBounds().toBBOX();
        };

        controller.editDataLayer = function(i) {
            ngDialog.open({
                template: 'app/map/editDataLayer.html',
                controller: 'editDataLayerController as editData',
                data: {
                    hash: controller.currentHash,
                    order: i
                },
                preCloseCallback: function(returned) {
                    if(returned.hasOwnProperty('callback')) {
                        controller[returned.callback](returned.data);
                    }
                    return true;
                }
            });
        };

        controller.getCkanDataset = function(params){
            var ckanParams = {
                package: params.package,
                id: params.ckan_id
            };
            controller.pending = true;
            apiFactory.getDataset(ckanParams).success(function(res) {
                if(res.success) {
                    controller.pending = false;
                    controller.sheetData = res.result;
                    var data = {
                        sheetData: controller.sheetData,
                        package: ckanParams.package,
                        id: ckanParams.id
                    };
                    if (params.order) {
                        data.order = params.order;
/*                        data.sheet = params.sheet;
                        data.numericColumn = params.data_column;
                        data.spatialColumn = params.spatial_column;*/
                    }

                    ngDialog.open({
                        template: 'app/map/AddLayer/addLayer2.html',
                        data: data,
                        controller: 'ImportTableController as importTable',
                        preCloseCallback: function(returned){
                            if(returned.hasOwnProperty('callback')) {
                                controller[returned.callback](returned.data);
                            }
                            return true;
                        }
                    });
                }
            }).error(function() {
                controller.pending = false;
                ngDialog.open({
                    template: 'common/error/ngDialogError'
                });
            });
        };

        controller.getCkanLayer = function(params){
            var hashList = storageFactory.load('hashList');
            var options = {
                package: params.package,
                id: params.id,
                sheet: params.sheet.code,
                spatial_column: params.spatialColumn? params.spatialColumn.column : null,
                data_column: params.numericColumn? params.numericColumn.column : null,
                hash: controller.currentHash,
                duplicate: true
            };
            if (params.order) {
                options.order = params.order;
            }
            apiFactory.addLayer(options).success(function(res){
                if(res.success) {
                    controller.currentHash = res.result.hash;
                    hashList.push(controller.currentHash);
                    storageFactory.save('hashList', hashList);
                    storageFactory.save(res.result.hash, res.result.map);
                    controller.zoomToExtent(res.result.map.extent);
                    controller.loadMap(controller.currentHash);
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };

        controller.hasChanged = function() {
            var hashList = storageFactory.load('hashList');
            return (!!hashList && hashList.length > 1);
        };

        controller.loadMap = function(hash) {
            function fadeOLlayer(layer) {
                var opacityI = window.setInterval(function() {
                        if (layer.opacity <= 0  && map.getLayer(layer.id)) {
                            map.removeLayer(layer);
                            window.clearInterval(opacityI);
                        } else {
                            layer.setOpacity(layer.opacity - 0.05);
                        }
                }, 50);
            }

            /* remove all stat layer */
            var OLlayersStat = map.getLayersByName(/Stats Layer/);
            var i=0;
            for (i=0; i < OLlayersStat.length; i++) {
                fadeOLlayer(OLlayersStat[i]);
            }
            

            removeTooltip();

            controller.data = storageFactory.load(hash);
            controller.layers = controller.data.layers;

            /* visibility osm layer */
            controller.toggleActiveLayer(controller.layers[0], true);

            /* add stat layer */
            for (i=1; i < controller.layers.length; i++) {
                var layer = controller.layers[i];
                controller.addMapLayer(layer);

            }

            if(controller.data.isMine) {
                checkUpdateMap(hash);
            }
        };

        controller.openPanel = function() {
            controller.panelToggleClass = 'panelOpen';
            window.setTimeout(function(){map.updateSize();},100);
        };

        controller.print = function() {
            if (true) {
                var printContents = document.getElementById('map').innerHTML;
                var originalContents = document.body.innerHTML;        
                var popupWin = window.open('', '_blank', 'width=1024,height=1024');
                popupWin.document.open();
                popupWin.document.write('<html><head><link rel="stylesheet" type="text/css" href="app/styles/css/print.css" /></head><body onload="window.print()">' + printContents + '</html>');
                popupWin.document.close();
            } else {
                var tiles = [];
            
                for (var i=0; i < map.getNumLayers(); i++) {
                    var tile;
                    var layer = map.layers[i];
                    if (!layer.getVisibility()) {
                        return;
                    }
                    
                    if(layer.CLASS_NAME == 'OpenLayers.Layer.TMS') {
                        tile = {
                            url: layer.url.replace('/tms/', '/wms/'),
                            service: 'TMS',
                            parameters: {
                                service: 'WMS',
                                request: 'GetMap',
                                project: gisclient.getProject(),
                                map: gisclient.getMapOptions().mapsetName,
                                layers: [layer.layername.substr(0, layer.layername.indexOf('@'))],
                                version: '1.1.1',
                                format: 'image/png'
                            },
                            opacity: layer.opacity ? (layer.opacity * 100) : 100
                        };
                    } else if(layer.CLASS_NAME == 'OpenLayers.Layer.WMS') {
                        tile = {
                            url: layer.url,
                            service: 'WMS',
                            parameters: layer.params,
                            opacity: layer.opacity ? (layer.opacity * 100) : 100
                        };
                    } else if(layer.CLASS_NAME == 'OpenLayers.Layer.WMTS') {
                        tile = {
                            url: layer.url,
                            service: 'WMTS',
                            project: layer.projectName,
                            layer: layer.layerName,
                            parameters: layer.params,
                            opacity: layer.opacity ? (layer.opacity * 100) : 100
                        };
                    }
                    if(tile) {
                        tiles.push(tile);
                    }
                }
                var center = map.getCenter();
                var extent = map.calculateBounds().toBBOX();
                var params = {
                    viewport_size: [map.size.w, map.size.h],
                    center: [center.lon, center.lat],
                    format: 'PDF',
                    printFormat: 'A4',
                    direction: 'vertical',
                    scale_mode: 'auto',
                    text: 'Test 01',
                    extent: extent,
                    date: '01/02/2013',
                    dpi: 150,
                    srid: 'EPSG:' + controller.setting.srid,
                    lang: 'it',
                    northArrow:null,
                    copyrightString: 'GeoBi',
                    logoSx: null,
                    logoDx: null,
                    tiles: tiles
                };

                apiFactory.print(controller.setting.authorUrl,params).success(function() {
                    console.log('print');
                });
            }
        };

        controller.refreshMap = function() {
            apiFactory.getTemporaryMap(controller.currentHash, controller.data).success(function(res){
                if (res.success) {
                    var hashList = storageFactory.load('hashList');
                    controller.currentHash = res.result.hash;
                    hashList.push(controller.currentHash);
                    storageFactory.save('hashList', hashList);
                    storageFactory.save(res.result.hash, res.result.map);
                    controller.loadMap(controller.currentHash);
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };

        controller.removeMapLayer = function(i) {
            controller.mapLock = true;
            var layers = controller.layers.splice(i, 1);
            var OLlayers = map.getLayersByName('Stats Layer ' + layers[0].order);
            map.removeLayer(OLlayers[0]);
            controller.refreshMap();
        };

        controller.saveMap = function() {
            apiFactory.saveMap(controller.currentHash, controller.hash, controller.data).success(function(res){
                if (res.success) {
                    controller.data.state = 'saved';
                    $timeout(function() {
                        delete controller.data.state;
                    }, 3000);
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };

        controller.shareToSocial = function() {
            ngDialog.open({
                template: 'app/map/social/social.html',
                controller: 'SocialController as social'
            });
        };

        controller.toggleActiveLayer = function(layer, isBaseLayer) {
            controller.mapLock = true;

            var name = 'Stats Layer ';
            if (isBaseLayer) {
                name = 'Base Layer ';
            }
            var OLlayer = map.getLayersByName(name + layer.order);
            OLlayer[0].setVisibility(layer.active);
        };

        controller.updateDataLayer = function(order) {
            controller.getCkanDataset(order);
        }

        controller.undoMap = function() {
            controller.mapLock = true;
            var newHashList = [];
            var hashList = storageFactory.load('hashList');
            for(var i=0; i<hashList.length; i++) {
                if(controller.currentHash == hashList[i] && i>0) {
                    controller.currentHash = hashList[i-1];
                    storageFactory.save('hashList', newHashList);
                    break;
                } else {
                    newHashList.push(hashList[i]);
                }
            }
            controller.loadMap(controller.currentHash);
        };

        controller.zoomToExtent = function(extent) {
            map.zoomToExtent(new OpenLayers.Bounds(extent[0], extent[1], extent[2], extent[3]));
        };

        $scope.$watch('map.data.layers', function(newObj,oldObj) {
            controller.mapLock = controller.mapLock || controller.nameLock;
            if(!controller.mapLock && angular.toJson(oldObj) != "{}") {
                //controller.mapLock = false;
                if (t) {
                    window.clearTimeout(t);
                    console.log('clear');
                }
                t = window.setTimeout(function() {
                    controller.refreshMap();
                }, 200);
            }
            controller.mapLock = false;
        }, true);

        $rootScope.$on('languageChanged', function () {
            loadSetting();
        });

        init();
    });

    app.controller('MapsListController', function($scope, apiFactory, ngDialog) {
        var controller = this;
        controller.data = [];
        controller.filters = {
            priv_only: false,
            q: '',
            it: false,
            de: false,
            en: false
        };
        controller.paginations = {
            limit: 20,
            offset: 0
        };

        controller.areAllMapsLoading = function() {
            if (controller.total) {
                return controller.data.length >= controller.total;
            }
            return true;
        };

        controller.copyMap = function(hash){
            ngDialog.open({
                template: 'app/homepage/copyMap/copymap.html',
                controller: 'CopyMapController as copymap',
                data: {
                    hash: hash
                }
            });
        };

        controller.deleteMap = function(i){
            ngDialog.openConfirm({
                template: 'app/homepage/deleteMap/deletemap.html'
            }).then(function() {
                apiFactory.deleteMap(controller.data[i].hash).success(function(res) {
                    if(res.success) {
                        controller.data.splice(i, 1);
                        controller.paginations.offset--;
                    }
                }).error(function(res){
                    alert('Error:\n' + res.result.error.text);
                    console.log(res);
                });
            });
        };

        controller.getMaps = function(reset) {
            if (reset){
                controller.paginations.offset = 0;
                controller.total = 0;
            }
            var filters = {
                priv_only: controller.filters.priv_only,
                q: controller.filters.q,
                it: controller.filters.it,
                de: controller.filters.de,
                en: controller.filters.en,
                limit: controller.paginations.limit,
                offset: controller.paginations.offset
            };
            apiFactory.getMaps(filters).success(function(res){
                if (res.success) {
                    if (reset){
                        controller.data = [];
                    }
                    controller.total = res.total;
                    Array.prototype.push.apply(controller.data, res.result);
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };

        controller.loadMore = function() {
            controller.paginations.offset += controller.paginations.limit;
            controller.getMaps(false);
        };

        controller.newMap = function() {
            ngDialog.open({
                template: 'app/homepage/NewMap/newmap.html',
                controller: 'NewMapController as newmap'
            });
        };

        $scope.$watchCollection('mapslist.filters', function() {
            controller.getMaps(true);
        });
    });

    app.controller('NewMapController', function($location, storageFactory, apiFactory, ngDialog) {
        var controller = this;
        controller.setting = storageFactory.load('setting');
        controller.languages = storageFactory.load('lang');
        controller.lang = storageFactory.load('currentLang');
        controller.maps = {};
        
        controller.openNewMap = function(){
            apiFactory.newMap(controller.lang).success(function(res) {
                if (res.success) {
                    $location.url('map/' +res.result.hash);
                    ngDialog.close();
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };

        controller.getMaps = function(q){
            ngDialog.close();

            ngDialog.open({
                template: 'app/homepage/NewMap/duplicateMap.html',
                controller: 'DuplicateMapController as duplicateMap',
                data: {
                    lang: controller.lang,
                    baseUrl: controller.setting.baseUrl
                }
            });
        };

        controller.selectCopyMap = function(){
            controller.getMaps();
        };
    });

    app.controller('ResetController', function($stateParams, apiFactory) {
        var controller = this;
        var hash = null;
        controller.credential = {};

        function init() {
            controller.isValidHash();
        }

        controller.changePassword = function() {
            if (controller.credential.password === controller.credential.checkPassword) {
                apiFactory.changePassword(hash, controller.credential.password).success(function(res) {
                    if (res.success) {
                        console.log(res);
                    }
                }).error(function(res){
                    alert('Error:\n' + res.result.error.text);
                    console.log(res);
                });
            }
        };

        controller.isValidHash = function() {
            hash = $stateParams.hash;
            apiFactory.isValidResetHash(hash).success(function(res) {
                if (res.success) {
                    controller.isValid = res.success;
                    controller.expired = new Date(res.result.expireDate * 1000);
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };

        init();
    });

    app.controller('SignupController', function($state, authFactory, apiFactory) {
        var controller = this;
        controller.data = {};

        controller.isAdmin = function() {
            return authFactory.isAdmin();
        };

        controller.signup = function() {
            if (controller.data.password === controller.data.passwordCheck) {
                apiFactory.signup(controller.data).success(function(res) {
                    if (res.success) {
                        if (controller.isAdmin()) {
                            $state.go('user');
                        } else {
                            $state.go('thankyou');
                        }
                    }
                }).error(function(res){
                    alert('Error:\n' + res.result.error.text);
                    console.log(res);
                });
            }
        };
    });

    app.controller('SocialController', function($location) {
        var controller = this;

        function init() {
            controller.url = $location.absUrl().replace('#', 'social');
            controller.body = 'Link: ' + controller.url;
        }

        init();
    });

    app.controller('UserListController', function($timeout, apiFactory, storageFactory, ngDialog) {
        var controller = this;
        controller.data = {};
        controller.configUser = {};

        function init() {
            var lang = storageFactory.load('currentLang');
            
            apiFactory.getUserList().success(function(res) {
                if(res.success) {
                    controller.data = res.result;
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
            controller.configUser = storageFactory.load('setting-' + lang + 'user').user;
        }

        controller.deleteUser = function(id) {
            ngDialog.openConfirm({
                template: 'app/users/deleteUser.html'
            }).then(function() {
                apiFactory.deleteUser(id).success(function(res) {
                    if (res.success) {
                        init();
                    }
                }).error(function(res){
                    alert('Error:\n' + res.result.error.text);
                    console.log(res);
                });
            });
        };

        controller.editUserDialog = function(i) {
            ngDialog.open({
                template: 'app/users/editUser.html',
                data: {
                    config: controller.configUser,
                    user: controller.data[i]
                },
                preCloseCallback: function(returned) {
                    if(returned.hasOwnProperty('callback')) {
                        controller[returned.callback](returned.data);
                    }
                    return true;
                }
            });
        };

        controller.editUser = function(data) {
            apiFactory.editUser(data.id, data).success(function(res) {
                if(res.success) {
                    console.log(res);
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };

        controller.newUser = function() {
            ngDialog.open({
                template: 'app/auth/signup.html'
            });
        };

        controller.sendActivationLink = function(i) {
            var email = controller.data[i].email;
            apiFactory.sendActivationLink(email).success(function(res) {
                if(res.success) {
                    controller.data[i].state = 'ok';
                    $timeout(function() {
                        delete controller.data[i].state;
                    }, 1000);
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };

        controller.resetPassword = function(i) {
            var data = {
                email: controller.data[i].email,
                captcha: ''
            };
            apiFactory.resetPassword(data).success(function(res) {
                if(res.success) {
                    controller.data[i].state = 'ok';
                    $timeout(function() {
                        delete controller.data[i].state;
                    }, 1000);
                }
            }).error(function(res){
                alert('Error:\n' + res.result.error.text);
                console.log(res);
            });
        };

        init();
    });


        /*      FILTERS     */

    app.filter('geometryColumnFilter', function() {
        function filter(input) {
            var result = [];
            for (var i=0; i<input.length; i++) {
                if (input[i].spatial_data) {
                    result.push(input[i]);
                }
            }
            return result;
        }
        return filter;
    });

    app.filter('numericColumnFilter', function() {
        function filter(input) {
            var result = [];
            for (var i=0; i<input.length; i++) {
                if (input[i].numeric_data) {
                    result.push(input[i]);
                }
            }
            return result;
        }
        return filter;
    });


        /*      FACTORIES       */

    app.factory('apiFactory', function ($http) {
        var factory = {};
        var apiUrls = {
            captcha: 'api/1/captcha',
            ckan: 'api/1/ckan',
            import: 'api/1/import',
            map: 'api/1/map',
            resetPassword: 'api/1/resetpassword',
            setting: 'api/1/setting',
            translation: 'api/1/translation',
            user: 'api/1/user'
        };

        factory.addLayer = function(options) {
            var params = '';
            params += 'hash=' + options.hash;
            params += !!options.spatial_column? '&spatial_column=' + options.spatial_column : '&spatial_column=';
            params += !!options.data_column? '&data_column=' + options.data_column : '';
            params += !!options.order? '&order=' + options.order : '';
            params += '&duplicate=' + options.duplicate;

            var request = $http({
                method: "post",
                url: apiUrls.import +'/ckan/'+ options.package +'/'+ options.id +'/'+ options.sheet,
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                data: params
            });
            return request;

        };

        factory.changePassword = function(hash, password) {
            var data = {
                password: password
            };
            return $http.put(apiUrls.resetPassword + '/reset/' + hash, data);
        };

        factory.checkDataLayer = function(hash, order, params) {
            var url = '';
            url += !!params.limit? '&limit=' + params.limit: '';
            url += !!params.offset? '&offset=' + params.offset: '';
            return $http.get(apiUrls.map + '/' + hash + '/data/' + order + '/data.json?spatialMatch=false' + url);
        };

        factory.checkUpdateMap = function(hash) {
            return $http.get(apiUrls.map + '/' + hash + '/update_check.json')
        }

        factory.copyMap = function(hash, lang){
            var params = {
                temporary: false,
                language: lang
            };
            return $http.post(apiUrls.map +'/'+ hash, params);
        };

        factory.deleteMap = function(hash) {
            return $http.delete(apiUrls.map +'/'+ hash);
        };

        factory.deleteUser = function(id) {
            return $http.delete(apiUrls.user +'/'+ id);
        };

        factory.downloadMap = function(baseUrl, hash, extent) {
            return $http.get(baseUrl + 'map/stat/' + hash +'/800/1024/preview.png?extent=' + extent);
        };

        factory.editUser = function(id, data) {
            return $http.put(apiUrls.user +'/'+ id, data);
        };

        factory.getAutocompleteOptions = function(hash, order, q) {
            return $http.get(apiUrls.map + '/' + hash + '/data/' + order + '/complete.json?q=' + q);
        };

        factory.getCaptcha = function() {
            return $http.get(apiUrls.captcha +'/request.json');
        };

        factory.getDataset = function(ckanParams) {
            return $http.get(apiUrls.ckan +'/'+ ckanParams.package +'/'+ ckanParams.id +'/tables.json');
        };

        factory.getDatasetData = function(params) {
            return $http.get(apiUrls.ckan +'/'+ params.package +'/'+ params.id +'/'+ params.sheet +'/data.json');
        };

        factory.getImportData = function() {
            return $http.get(apiUrls.ckan +'/packages.json');
        };

        factory.isValidResetHash = function(hash) {
            return $http.get(apiUrls.resetPassword + '/reset/' + hash);
        };
        
        factory.getMaps = function(options) {
            var params = '';
            if (options) {
                params += '?language=';
                params += !!options.it?'it ':'';
                params += !!options.de?'de ':'';
                params += !!options.en?'en':'';
                params += !!options.q? '&q=' + options.q:'';
                params += !!options.priv_only? '&onlyMine=' + options.priv_only:'';
                params += !!options.order? '&order=' + options.order:'';
                params += !!options.limit? '&limit=' + options.limit:'';
                params += !!options.offset? '&offset=' + options.offset:'';
            }

            return $http.get(apiUrls.map +'/maps.json'+ params);
        };

        factory.getMap = function(hash) {
            return $http.get(apiUrls.map +'/'+ hash +'/map.json');
        };

        factory.getSetting = function(lang, page) {
            var route = '/';
            if (!!lang) {
                route += lang + '/';
            }
            route += page +'.json';
            return $http.get(apiUrls.setting + route);
        };

        factory.getStatInfo = function(hash, order, options) {
            var params = '';
            if (options) {
                params += '?x=' + options.lon;
                params += '&y=' + options.lat;
                params += '&buffer=' + options.buffer;
            }
            return $http.get(apiUrls.map +'/'+ hash +'/stat/'+ order +'/info.json'+ params);
        };

        factory.getTemporaryMap = function(currentHash, map) {
            var data = {
                map: map,
                duplicate: true,
            };
            return $http.put(apiUrls.map +'/'+ currentHash, data);
        };

        factory.getTranslation = function(lang, page) {
            return $http.get(apiUrls.translation +'/'+ lang +'/'+ page +'.json');
        };

        factory.getUserList = function() {
            return $http.get(apiUrls.user +'/users.json');
        };

        factory.login = function(email, password) {
            var data = {
                email: email,
                password: password
            };
            return $http.post(apiUrls.user + '/login', data);
        };

        factory.newMap = function(lang) {
            var request = $http({
                method: "post",
                url: apiUrls.map,
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                data: 'language='+ lang
            });
            return request;
        };

        factory.print = function(author, params) {
            var printServiceUrl = author +'services/print.php';
            return $http.post(printServiceUrl, params);
        };

        factory.resetPassword = function(data) {
            return $http.post(apiUrls.resetPassword +'/request', data);
        };

        factory.saveCorrectData = function(hash, order, data) {
            return $http.put(apiUrls.map +'/'+ hash +'/data/'+ order +'/data.json', data);
        };

        factory.saveMap = function(from, to, map) {
            var data = {
                map: map,
                duplicate: false,
                copyFromHash: from
            };
            return $http.put(apiUrls.map +'/'+ to, data);
        };

        factory.sendActivationLink = function(email, captcha) {
            var data = {
                email: email,
                captcha: captcha || ''
            };
            return $http.post(apiUrls.user + '/requestmail', data);
        };

        factory.signup = function(data) {
            return $http.post(apiUrls.user + '/register', data);
        };

        return factory;
    });

    app.factory('storageFactory', function() {
        var factory = {};

        factory.save = function(name, obj) {
            var json = JSON.stringify(obj);
            sessionStorage.setItem(name, json);
        };

        factory.load = function(name){
            return JSON.parse(sessionStorage.getItem(name));
        };

        factory.remove = function(name) {
            sessionStorage.removeItem(name);
        };

        return factory;
    });

    app.factory('authFactory', function($http, storageFactory) {
        var factory = {};
        var role = {
            admin: 'ROLE_ADMIN',
            mapProducer: 'ROLE_MAP_PRODUCER'
        };

        factory.auth = function(res, token) {
            storageFactory.save('auth', res);
            storageFactory.save('token', token);
            factory.setToken();
        };

        factory.deauth = function() {
            storageFactory.remove('auth');
            storageFactory.remove('token');
            factory.setToken();
        };

        factory.isAdmin = function() {
            if (factory.isAuth()) {
                var auth = storageFactory.load('auth');
                for (var i = 0; i < auth.roles.length; i++) {
                    if (auth.roles[i] === role.admin) {
                        return true;
                    }
                }
            }
            return false;
        };

        factory.isAuth = function() {
            factory.setToken();
            return !!storageFactory.load('auth');
        };

        factory.setToken = function() {
            var token = storageFactory.load('token');
            if (!!token) {
                $http.defaults.headers.common['X-WSSE'] = token;
            } else {
                delete $http.defaults.headers.common['X-WSSE'];
            }
        };

        return factory;
    });


        /*      DIRECTIVES      */

    app.directive('loader', function(){
        return {
            restrict: 'E',
            templateUrl: 'common/loader.html',
        };
    });

    app.directive('captcha', function(){
        return {
            restrict: 'E',
            templateUrl: 'common/captcha.html',
        };
    });



        /*      RUNS        */

    app.run(function($rootScope, ngDialog, apiFactory, storageFactory) {
        $rootScope.$on('$stateChangeStart', function(event, toState, toParams, fromState, fromParams) {
            var lang = storageFactory.load('currentLang');
            if (lang && toState.name) {
                if (storageFactory.load('lang-' + lang + toState.name)) {
                    $rootScope.text[toState.name] = storageFactory.load('lang-' + lang + toState.name);
                } else {
                    apiFactory.getTranslation(lang, toState.name).success(function(res){
                        if (res.success) {
                            $rootScope.text[toState.name] = res.result.data;
                            storageFactory.save('lang-' + lang + toState.name, res.result.data);
                        }
                    }).error(function(res){
                        alert('Error:\n' + res.result.error.text);
                        console.log(res);
                    });
                }
                
                if(toState.name == 'map' || toState.name == 'user') {
                    if (!storageFactory.load('setting-' + lang + toState.name)) {
                        apiFactory.getSetting(lang, toState.name).success(function(res){
                            if (res.success) {
                                storageFactory.save('setting-' + lang + toState.name, res.result.data);
                            }
                        }).error(function(res){
                            alert('Error:\n' + res.result.error.text);
                            console.log(res);
                        });
                    }
                }

            }
            ngDialog.closeAll();
        });
    });



})();
