/*
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2007-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 */
Ext.ns('Tine.Tinebase');

/**
 * Tine 2.0 main application manager
 * 
 * @namespace   Tine.Tinebase
 * @class       Tine.Tinebase.AppManager
 * @extends     Ext.util.Observable
 * @consturctor
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 */
Tine.Tinebase.AppManager = function() {
    /**
     * @property apps
     * @type Ext.util.MixedCollection
     * 
     * enabled apps
     */
    this.apps = new Ext.util.MixedCollection({});
    
    this.addEvents(
        /**
         * @event beforeactivate
         * fired before an application gets activated. Retrun false to stop activation
         * @param {Tine.Aplication} app about to activate
         */
        'beforeactivate',
        /**
         * @event activate
         * fired when an application gets activated
         * @param {Tine.Aplication} activated app
         */
        'activate',
        /**
         * @event beforedeactivate
         * fired before an application gets deactivated. Retrun false to stop deactivation
         * @param {Tine.Aplication} app about to deactivate
         */
        'beforedeactivate',
        /**
         * @event deactivate
         * fired when an application gets deactivated
         * @param {Tine.Aplication} deactivated app
         */
        'deactivate',
        /**
         * @event windowopenexception
         * windowopenexceptionated 
         * @param {} Exception
         */
        'windowopenexception'
    );
    
    
    // fill this.apps with registry data 
    // do it the other way round because add() always adds records at the beginning of the MixedCollection
    var enabledApps = Tine.Tinebase.registry.get('userApplications'),
        app;
        
    Tine.log.debug('Tine.Tinebase.AppManager - enabled Apps: ');
    Tine.log.debug(enabledApps);
    
    for (var i = (enabledApps.length - 1); i >= 0; i--) {
        app = enabledApps[i];
        
        // if the app is not in the namespace, we don't initialise it
        // we don't have a Tinebase 'Application'
        if (Tine[app.name] && ! app.name.match(/(Tinebase)/)) {
            app.appName = app.name;
            app.isInitialised = false;
            this.apps.add(app.appName, app);
        }
    }
};

Ext.extend(Tine.Tinebase.AppManager, Ext.util.Observable, {
    /**
     * @cfg {Tine.Application}
     */
    defaultApp: null,
    
    /**
     * @property activeApp
     * @type Tine.Application
     * 
     * currently active app
     */
    activeApp: null,
    
    /**
     * activate application
     * 
     * @param {Tine.Application} app
     * @return {Boolean}
     * 
     * TODO think about adding a fallback app if app mainscreen could not be fetched 
     */
    activate: function(app) {
        if (app || (app = this.getDefault()) ) {
            if (app == this.getActive()) {
                // app is already active, nothing to do
                return true;
            }
            
            if (this.activeApp) {
                if ((this.fireEvent('beforedeactivate', this.activeApp) === false || this.activeApp.onBeforeDeActivate() === false)) {
                    return false;
                }
                
                this.activeApp.onDeActivate();
                this.fireEvent('deactivate', this.activeApp);
                this.activeApp = null;
            }
            
            if (this.fireEvent('beforeactivate', app) === false || app.onBeforeActivate() === false) {
                return false;
            }
            
            var mainscreen = app.getMainScreen();
            if (mainscreen) {
                mainscreen.activate();
            } else {
                // app has no mainscreen / perhaps it has been disabled
                return false;
            }
            
            this.activeApp = app;
            app.onActivate();
            this.fireEvent('activate', app);
        }
    },
    
    /**
     * returns an appObject
     * 
     * @param {String} appName
     * @return {Tine.Application}
     */
    get: function(appName) {
        if (Ext.isObject(appName) && appName.hasOwnProperty('appName')) {
            appName = appName.appName;
        }
        if (! this.isEnabled(appName)) {
            return false;
        }
        
        var app = this.apps.get(appName);
        if (! app.isInitialised) {
            var appObj = this.getAppObj(app);
            appObj.isInitialised = true;
            Ext.applyIf(appObj, app);
            this.apps.replace(appName, appObj);
        }
        
        return this.apps.get(appName);
    },
    
    /**
     * returns appObject
     * 
     * @param {String} applicationId
     * @return {Tine.Application}
     */
    getById: function(applicationId) {
        var appObj = null;
        Ext.each(Tine.Tinebase.registry.get('userApplications'), function(rawApp) {
            if (rawApp.id === applicationId) {
                appObj = this.get(rawApp.appName);
                return false;
            }
        }, this);
        
        return appObj;
    },
    
    /**
     * returns currently activated app
     * @return {Tine.Application}
     */
    getActive: function() {
        return this.activeApp;
    },
    
    /**
     * returns appObject of default app
     * 
     * @return {Tine.Application}
     */
    getDefault: function() {
        if (! this.defaultApp) {
            var defaultAppName = (Tine.Tinebase.registry.get('preferences') && Tine.Tinebase.registry.get('preferences').get('defaultapp')) 
                ? Tine.Tinebase.registry.get('preferences').get('defaultapp') 
                : this.defaultAppName;
                
            this.defaultApp = this.get(defaultAppName) || this.apps.find(function(app) {return app.hasMainScreen});
            
            if (! this.defaultApp) {
                // no global exception concept yet...
                //throw Ext.Error('no apps enabled', 620);
                Ext.MessageBox.show({
                    title: i18n._('Missing Applications'),
                    msg: i18n._('There are no applications enabled for you. Please contact your administrator.'),
                    buttons: Ext.Msg.OK,
                    icon: Ext.MessageBox.WARNING
                });
            }
        }
        
        return this.defaultApp;
    },
    
    /**
     * set default app for this session
     * 
     * @param {Tine.Application/String} app
     */
    setDefault: function(app) {
        if (Ext.isString(app)) {
            app = this.get(app);
        }
        
        if (app) {
            this.defaultApp = app;
        }
    },
    
    /**
     * returns a list of all apps for current user
     */
    getAll: function() {
        this.initAll();
        return this.apps;
    },
    
    /**
     * checks wether a given app is enabled for current user or not
     */
    isEnabled: function(appName) {
        var app = this.apps.get(appName);
        return app ? app.status == 'enabled' : false;
    },
    
    /**
     * initialises all enabled apps
     * @private
     */
    initAll: function() {
        this.apps.each(function(app) {
            this.get(app.appName);
        }, this);
    },
    
    /**
     * @private
     */
    getAppObj: function(app) {
       try{
            // legacy
            if (typeof(Tine[app.appName].getPanel) == 'function') {
                // make a legacy Tine.Application
                return this.getLegacyApp(app);
            }

            return typeof(Tine[app.appName].Application) == 'function' ? new Tine[app.appName].Application(app) : new Tine.Tinebase.Application(app);
            
        } catch(e) {
            console.error('Initialising of Application "' + app.appName + '" failed with the following message:' + e);
            console.error(e.stack);
            if (! Tine[app.appName].registry) {
                // registry load problem: reload
                Tine.Tinebase.common.reload({});
            }

            return false;
        }
    },
    
    /**
     * @private
     */
    getLegacyApp: function(app) {
        app.hasMainScreen = true;
        var appPanel = Tine[app.appName].getPanel();
        var appObj =  new Tine.Tinebase.Application(app);
        var mainScreen = new Tine.widgets.MainScreen({app: appObj});

        Ext.apply(appObj, {
            mainScreen: mainScreen
        });

        Ext.apply(mainScreen, {
            appPanel: appPanel,
            getContainerTreePanel: function() {
                return this.appPanel;
            },
            getWestPanel: function() {
                return this.appPanel;
            },
            activate: function() {
                Tine.Tinebase.MainScreen.setActiveCenterPanel(mainScreen, true);
                Tine.Tinebase.MainScreen.setActiveTreePanel(appPanel, true);


                // remove favorite toolbar for legacy modules
                var westRegionPanel = mainScreen.westRegionPanel,
                    westPanelToolbar = westRegionPanel.getTopToolbar();

                westPanelToolbar.removeAll();
                westPanelToolbar.hide();
                westPanelToolbar.doLayout();

                appPanel.fireEvent('beforeexpand', appPanel);
            }
        });

        appPanel.on('render', function(p) {
            p.header.remove();
            // additionally to removing the DOM node, we also need to reset the 
            // header class variable, as IE evals "if (this.header)" to true otherwise 
            p.header = false;
            p.doLayout();
        });
        
        return appObj;
    }
});
