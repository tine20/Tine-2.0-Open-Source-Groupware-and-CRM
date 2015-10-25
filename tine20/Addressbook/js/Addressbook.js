/*
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2007-2008 Metaways Infosystems GmbH (http://www.metaways.de)
 */

Ext.ns('Tine.Addressbook');

/**
 * @namespace   Tine.Addressbook
 * @class       Tine.Addressbook.Application
 * @extends     Tine.Tinebase.Application
 * Addressbook Application Object <br>
 * 
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 */
Tine.Addressbook.Application = Ext.extend(Tine.Tinebase.Application, {
    
    /**
     * auto hook text _('New Contact')
     */
    addButtonText: 'New Contact',
    
    /**
     * Get translated application title of the calendar application
     * 
     * @return {String}
     */
    getTitle: function() {
        return this.i18n.ngettext('Addressbook', 'Addressbooks', 1);
    },

    /** 
     * Overide get main screen to allow for feature gating
     *
     **/
    getMainScreen: function() {
        var mainscreen = Tine.Addressbook.Application.superclass.getMainScreen.call(this);

        if (!Tine.Tinebase.appMgr.get('Addressbook').featureEnabled('featureListView')) {
            mainscreen.useModuleTreePanel = false;
        };

        return mainscreen;
    },
});

/**
 * @namespace   Tine.Addressbook
 * @class       Tine.Addressbook.MainScreen
 * @extends     Tine.widgets.MainScreen
 * MainScreen of the Addressbook Application <br>
 * 
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 */
 Tine.Addressbook.MainScreen = Ext.extend(Tine.widgets.MainScreen, {
    activeContentType: 'Contact',
    contentTypes: [
        {model: 'Contact',  requiredRight: null, singularContainerMode: false},
        {model: 'List',  requiredRight: null, singularContainerMode: false}
    ]
});

Tine.Addressbook.ContactTreePanel = function(config) {
    Ext.apply(this, config);
    
    this.id = 'Addressbook_Contact_Tree';
    this.filterMode = 'filterToolbar';
    this.recordClass = Tine.Addressbook.Model.Contact;
    Tine.Addressbook.ContactTreePanel.superclass.constructor.call(this);
};
Ext.extend(Tine.Addressbook.ContactTreePanel , Tine.widgets.container.TreePanel);

Tine.Addressbook.ListTreePanel = function(config) {
    Ext.apply(this, config);
    
    this.id = 'Addressbook_List_Tree';
    this.filterMode = 'filterToolbar';
    this.recordClass = Tine.Addressbook.Model.List;
    Tine.Addressbook.ListTreePanel.superclass.constructor.call(this);
};
Ext.extend(Tine.Addressbook.ListTreePanel , Tine.widgets.container.TreePanel);


Tine.Addressbook.handleRequestException = Tine.Tinebase.ExceptionHandler.handleRequestException;

Tine.Addressbook.ContactFilterPanel = function(config) {
    Ext.apply(this, config);
    Tine.Addressbook.ContactFilterPanel.superclass.constructor.call(this);
};

Ext.extend(Tine.Addressbook.ContactFilterPanel, Tine.widgets.persistentfilter.PickerPanel, {
    filter: [{field: 'model', operator: 'equals', value: 'Addressbook_Model_ContactFilter'}]
});

Tine.Addressbook.ListFilterPanel = function(config) {
    Ext.apply(this, config);
    Tine.Addressbook.ListFilterPanel.superclass.constructor.call(this);
};

Ext.extend(Tine.Addressbook.ListFilterPanel, Tine.widgets.persistentfilter.PickerPanel, {
    filter: [{field: 'model', operator: 'equals', value: 'Addressbook_Model_ListFilter'}]
});