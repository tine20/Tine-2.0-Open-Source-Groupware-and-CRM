/*
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  widgets
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2009-2012 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 * @todo        add filter toolbar
 * @todo        use proxy store?
 */

Ext.ns('Tine.widgets', 'Tine.widgets.dialog');

/**
 * 'Edit Preferences' dialog
 *
 * @namespace   Tine.widgets.dialog
 * @class       Tine.widgets.dialog.Preferences
 * @extends     Ext.FormPanel
 * @constructor
 * @param       {Object} config The configuration options.
 */
Tine.widgets.dialog.Preferences = Ext.extend(Ext.FormPanel, {
    /**
     * @property {Locale.gettext} i18n
     */
    i18n: null,

    /**
     * @property {Tine.widgets.dialog.PreferencesCardPanel} prefsCardPanel
     */
    prefsCardPanel: null,
    
    /**
     * @property {Tine.widgets.dialog.PreferencesTreePanel} treePanel
     */
    treePanel: null,
    
    /**
     * @property {Object} prefPanels
     * here we store the pref panels for all apps
     */    
    prefPanels: {},

    /**
     * @property {boolean} adminMode
     * when adminMode is activated -> show defaults/forced values
     */    
    adminMode: false,

    /**
     * @property {Object} prefPanels
     * here we store the pref panels for all apps [admin mode]
     */    
    adminPrefPanels: {},
    
    /**
     * @cfg String  initialCardName to select after render
     */
    initialCardName: null,
    
    // private
    layout: 'fit',
    cls: 'tw-editdialog',
    anchor:'100% 100%',
    buttonAlign: 'right',
    border: false,
    
    //private
    initComponent: function(){
        this.addEvents(
            /**
             * @event cancel
             * Fired when user pressed cancel button
             */
            'cancel',
            /**
             * @event saveAndClose
             * Fired when user pressed OK button
             */
            'saveAndClose',
            /**
             * @event update
             * @desc  Fired when the record got updated
             * @param {Json String} data data of the entry
             */
            'update'
        );
        
        this.i18n = new Locale.Gettext();
        this.i18n.textdomain('Tinebase');

        this.initActions();
        this.initButtons();
        this.items = this.getItems();
        
        Tine.widgets.dialog.Preferences.superclass.initComponent.call(this);
    },
    
    /**
     * init actions
     * 
     * @todo only allow admin mode if user has admin right
     */
    initActions: function() {
        this.action_saveAndClose = new Ext.Action({
            text: i18n._('Ok'),
            minWidth: 70,
            scope: this,
            handler: this.onSaveAndClose,
            iconCls: 'action_saveAndClose'
        });
    
        this.action_cancel = new Ext.Action({
            text: i18n._('Cancel'),
            minWidth: 70,
            scope: this,
            handler: this.onCancel,
            iconCls: 'action_cancel'
        });

        this.action_switchAdminMode = new Ext.Action({
            text: i18n._('Admin Mode'),
            minWidth: 70,
            scope: this,
            handler: this.onSwitchAdminMode,
            iconCls: 'action_adminMode',
            enableToggle: true
        });
    },
    
    /**
     * init buttons
     * use preference settings for order of save and close buttons
     */
    initButtons: function () {
        this.buttons = [];
        
        this.buttons.push(this.action_cancel, this.action_saveAndClose);

        this.tbar = new Ext.Toolbar({
            items: [ this.action_switchAdminMode ]
        });
    },
    
    /**
     * returns dialog
     * 
     * NOTE: when this method gets called, all initalisation is done.
     */
    getItems: function() {
        this.prefsCardPanel = new Tine.widgets.dialog.PreferencesCardPanel({
            region: 'center'
        });
        this.treePanel = new Tine.widgets.dialog.PreferencesTreePanel({
            title: i18n._('Applications'),
            region: 'west',
            width: 200,
            border: false,
            frame: false,
            initialNodeId: this.initialCardName
        })
        return [{
            xtype: 'panel',
            autoScroll: true,
            border: false,
            frame: false,
            layout: 'border',
            items: [
                this.treePanel,
                this.prefsCardPanel
            ]
        }];
    },
    
    /**
     * @private
     */
    onRender: function (ct, position) {
        Tine.widgets.dialog.Preferences.superclass.onRender.call(this, ct, position);
        
        // recalculate height, as autoHeight fails for Ext.Window ;-(
        this.setHeight(Ext.fly(this.el.dom.parentNode).getHeight());
        
        this.window.setTitle(this.i18n._('Edit Preferences'));
        this.loadMask = new Ext.LoadMask(ct, {msg: i18n._('Loading ...')});
    },
    
    /**
     * @private
     */
    onCancel: function () {
        this.fireEvent('cancel');
        this.purgeListeners();
        this.window.close();
    },

    /**
     * @private
     * 
     * TODO check if this is working correctly
     */
    onDestroy: function(){
        // delete panels
        for (var panelName in this.adminPrefPanels) {
            if (this.adminPrefPanels.hasOwnProperty(panelName)) {
                if (this.adminPrefPanels[panelName] !== null) {
                    this.adminPrefPanels[panelName].destroy();
                    this.adminPrefPanels[panelName] = null;
                }
            }
        }
        for (panelName in this.prefPanels) {
            if (this.prefPanels.hasOwnProperty(panelName)) {
                if (this.prefPanels[panelName] !== null) {
                    this.prefPanels[panelName].destroy();
                    this.prefPanels[panelName] = null;
                }
            }
        }
        this.prefsCardPanel.destroy();
        this.prefsCardPanel = null;
        
        Tine.widgets.dialog.Preferences.superclass.onDestroy.apply(this, arguments);
    },
    
    /**
     * @private
     */
    onSaveAndClose: function(){
        this.onApplyChanges(true);
        this.fireEvent('saveAndClose');
    },
    
    /**
     * apply changes handler
     */
    onApplyChanges: function(closeWindow) {
        
        if (! this.isValid()) {
            Ext.MessageBox.alert(i18n._('Errors'), i18n._('You need to correct the red marked fields before config could be saved'));
            return;
        }
        
        this.loadMask.show();
        
        // get values from card panels
        var data = this.getValuesFromPanels();
        
        // save preference data
        Ext.Ajax.request({
            scope: this,
            params: {
                method: 'Tinebase.savePreferences',
                data: data,
                adminMode: (this.adminMode) ? 1 : 0
            },
            success: function(response) {
                this.loadMask.hide();
                
                // update registry
                this.updateRegistry(Ext.util.JSON.decode(response.responseText).results);
                
                if (closeWindow) {
                    this.purgeListeners();
                    this.window.close();
                }
            },
            failure: function (response) {
                Ext.MessageBox.alert(i18n._('Errors'), i18n._('Saving of preferences failed.'));
            }
        });
    },
    
    /**
     * check all panels if they are valid
     * 
     * @return {Boolean}
     */
    isValid: function() {
        var panel = {};
        var panelsToSave = (this.adminMode) ? this.adminPrefPanels : this.prefPanels;

        for (panelName in panelsToSave) {
            panel = panelsToSave[panelName];
            if (panel && typeof panel.isValid === 'function' && ! panel.isValid()) {
                return false;
            }
        }
        
        return true;
    },
    
    /**
     * get values from card panels
     * 
     * @return {Object} with form data
     */
    getValuesFromPanels: function() {
        var panel, data = {};
        var panelsToSave = (this.adminMode) ? this.adminPrefPanels : this.prefPanels;

        for (panelName in panelsToSave) {
            if (panelsToSave.hasOwnProperty(panelName)) {
                panel = panelsToSave[panelName];
                if (panel !== null) {
                    data[panel.appName] = {};
                    for (var j=0; j < panel.items.length; j++) {
                        var item = panel.items.items[j];
                        if (item && item.name) {
                            if (this.adminMode) {
                                // filter personal_only (disabled) items
                                if (! item.disabled) {
                                    data[panel.appName][item.prefId] = {value: item.getValue(), name: item.name};
                                    data[panel.appName][item.prefId].type = (Ext.getCmp(item.name + '_writable').getValue() == 1) ? 'default' : 'forced';
                                }
                            } else {
                                data[panel.appName][item.name] = {value: item.getValue()};
                            }
                        }
                    }
                }
            }
        }
        
        return data;
    },
    
    /**
     * update registry after saving of prefs
     * 
     * @param {Object} data
     */
    updateRegistry: function(data) {
        for (application in data) {
            if (data.hasOwnProperty(application)) {
                appPrefs = data[application];
                var registryValues = Tine[application].registry.get('preferences');
                var changed = false;
                for (var i=0; i < appPrefs.length; i++) {
                    if (registryValues.get(appPrefs[i].name) != appPrefs[i].value) {
                        registryValues.replace(appPrefs[i].name, appPrefs[i].value);
                        changed = true;
                    }
                }
                
                if (changed) {
                    Tine[application].registry.replace('preferences', registryValues);
                }
            }
        }
    },
    
    /**
     * onSwitchAdminMode
     * 
     * @private
     * 
     * @todo enable/disable apps according to admin right for applications
     */
    onSwitchAdminMode: function(button, event) {
        this.adminMode = (!this.adminMode);
        
        if (this.adminMode) {
            this.prefsCardPanel.addClass('prefpanel_adminMode');
        } else {
            this.prefsCardPanel.removeClass('prefpanel_adminMode');
        }
        
        // activate panel in card panel
        var selectedNode = this.treePanel.getSelectionModel().getSelectedNode();
        if (selectedNode) {
            this.showPrefsForApp(this.treePanel.getSelectionModel().getSelectedNode().id);
        }
        
        this.treePanel.checkGrants(this.adminMode);
    },

    /**
     * init app preferences store
     * 
     * @param {String} appName
     * 
     * @todo use generic json backend here?
     */
    initPrefStore: function(appName) {
        this.loadMask.show();
        
        // set filter to get only default/forced values if in admin mode
        var filter = (this.adminMode) ? [{field: 'account', operator: 'equals', value: {accountId: 0, accountType: 'anyone'}}] : '';
        
        var store = new Ext.data.JsonStore({
            fields: Tine.Tinebase.Model.Preference,
            baseParams: {
                method: 'Tinebase.searchPreferencesForApplication',
                applicationName: appName,
                filter: filter
            },
            listeners: {
                load: this.onStoreLoad,
                scope: this
            },
            root: 'results',
            totalProperty: 'totalcount',
            id: 'id',
            remoteSort: false
        });
        
        store.load();
    },

    /**
     * called after a new set of preference Records has been loaded
     * 
     * @param  {Ext.data.Store} this.store
     * @param  {Array}          loaded records
     * @param  {Array}          load options
     */
    onStoreLoad: function(store, records, options) {
        var appName = store.baseParams.applicationName;
        
        var card = new Tine.widgets.dialog.PreferencesPanel({
            prefStore: store,
            appName: appName,
            adminMode: this.adminMode
        });
        
        card.on('change', function(appName) {
            // mark card as changed in tree
            var node = this.treePanel.getNodeById(appName);
            node.setText(node.text + '*');
        }, this);
        
        // add to panel registry
        if (this.adminMode) {
            this.adminPrefPanels[appName] = card;
        } else {
            this.prefPanels[appName] = card;
        }
        
        this.activateCard(card, false);
        this.loadMask.hide();
    },
    
    /**
     * activateCard in preferences panel
     * 
     * @param {Tine.widgets.dialog.PreferencesPanel} panel
     * @param {boolean} exists
     */
    activateCard: function(panel, exists) {
        if (!exists) {
            this.prefsCardPanel.add(panel);
            this.prefsCardPanel.layout.container.add(panel);
        }
        this.prefsCardPanel.layout.setActiveItem(panel.id);
        panel.doLayout();
    },
    
    /**
     * showPrefsForApp 
     * - check stores (create new store if not exists)
     * - activate pref panel for app
     * 
     * @param {String} appName
     */
    showPrefsForApp: function(appName) {
        
        // TODO: invent panel hooking approach here
        if (appName === 'Tinebase.UserProfile') {
            
            if (! this.prefPanels[appName]) {
                this.prefPanels[appName] = new Tine.Tinebase.UserProfilePanel({
                    appName: appName
                });
                this.activateCard(this.prefPanels[appName], false);
            } 
        }
            
        var panel = (this.adminMode) ? this.adminPrefPanels[appName] : this.prefPanels[appName];

        if (!this.adminMode) {
            // check grant for pref and enable/disable button
            this.action_switchAdminMode.setDisabled(!Tine.Tinebase.common.hasRight('admin', appName));
        }
        
        // check stores/panels
        if (!panel) {
            // add new card + store
            this.initPrefStore(appName);
        } else {
            this.activateCard(panel, true);
        }
    }
});

/**
 * Timetracker Edit Popup
 */
Tine.widgets.dialog.Preferences.openWindow = function (config) {
    var window = Tine.WindowFactory.getWindow({
        width: 800,
        height: 470,
        name: 'Preferences',
        contentPanelConstructor: 'Tine.widgets.dialog.Preferences',
        contentPanelConstructorConfig: config
    });
    return window;
};
