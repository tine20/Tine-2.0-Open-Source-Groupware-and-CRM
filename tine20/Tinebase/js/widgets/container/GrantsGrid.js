/*
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2011-2015 Metaways Infosystems GmbH (http://www.metaways.de)
 */
Ext.ns('Tine.widgets', 'Tine.widgets.container');

/**
 * Container Grants grid
 * 
 * @namespace   Tine.widgets.container
 * @class       Tine.widgets.container.GrantsDialog
 * @extends     Tine.widgets.dialog.EditDialog
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @constructor
 * @param {Object} config The configuration options.
 */
Tine.widgets.container.GrantsGrid = Ext.extend(Tine.widgets.account.PickerGridPanel, {

    /**
     * @cfg {Array} data of Tine.Tinebase.Model.Container
     * Container to manage grants for
     */
    grantContainer: null,
    
    /**
     * @cfg {Boolean}
     * always show the admin grant (default: false)
     */
    alwaysShowAdminGrant: false,
    
    /**
     * Tine.widgets.account.PickerGridPanel config values
     */
    selectType: 'both',
    selectTypeDefault: 'group',
    selectRole: true,
    hasAccountPrefix: true,
    recordClass: Tine.Tinebase.Model.Grant,
    
    readGrantTitle: 'Read', // i18n._('Read')
    readGrantDescription: 'The grant to read records of this container', // i18n._('The grant to read records of this container')
    addGrantTitle: 'Add', // i18n._('Add')
    addGrantDescription: 'The grant to add records to this container', // i18n._('The grant to add records to this container')
    editGrantTitle: 'Edit', // i18n._('Edit')
    editGrantDescription: 'The grant to edit records in this container', // i18n._('The grant to edit records in this container')
    deleteGrantTitle: 'Delete', // i18n._('Delete')
    deleteGrantDescription: 'The grant to delete records in this container', // i18n._('The grant to delete records in this container')
    exportGrantTitle: 'Export', // i18n._('Export')
    exportGrantDescription: 'The grant to export records from this container', // i18n._('The grant to export records from this container')
    syncGrantTitle: 'Sync', // i18n._('Sync')
    syncGrantDescription: 'The grant to synchronise records with this container', // i18n._('The grant to synchronise records with this container')
    adminGrantTitle: 'Admin', // i18n._('Admin')
    adminGrantDescription: 'The grant to administrate this container', // i18n._('The grant to administrate this container')
    
    freebusyGrantTitle: 'Free Busy', // i18n._('Free Busy')
    freebusyGrantDescription: 'The grant to access free busy information of events in this calendar', // i18n._('The grant to access free busy information of events in this calendar')
    privateGrantTitle: 'Private', // i18n._('Private')
    privateGrantDescription: 'The grant to access records marked as private in this container', // i18n._('The grant to access records marked as private in this container')
    
    
    /**
     * @private
     */
    initComponent: function () {
        this.initColumns();
        
        this.recordDefaults = {
            readGrant: true,
            exportGrant: true,
            syncGrant: true
        };
        
        Tine.widgets.container.GrantsGrid.superclass.initComponent.call(this);
        
        this.getStore().on('update', this.onStoreUpdate, this);
        
        this.getStore().on('load', function(store) {
            store.each(function(r) {this.onStoreUpdate(store, r)}, this);
        }, this);
    },

    onStoreUpdate: function(store, record, operation) {
        if (this.alwaysShowAdminGrant || (this.grantContainer && this.grantContainer.type == 'shared')) {
            if (record.get('adminGrant')) {
                // set all grants and mask other checkboxes
                Ext.each(this.getColumnModel().columns, function(col, colIdx) {
                    var matches;
                    if (col.dataIndex && (matches = col.dataIndex.match(/^([a-z]+)Grant$/)) && matches[1] != 'admin') {
                        record.set(col.dataIndex, true);
                    }
                }, this);
                
            } else {
                // make sure grants are not masked
            }
        }
    },
    
    /**
     * init grid columns
     */
    initColumns: function() {
        var grants = this.grantContainer
            ? Tine.widgets.container.GrantsManager.getByContainer(this.grantContainer)
            : Tine.widgets.container.GrantsManager.defaultGrants();
        
        if (this.alwaysShowAdminGrant || (this.grantContainer && this.grantContainer.type == 'shared')) {
            grants.push('admin');
        }
        
        this.configColumns = [];
        
        Ext.each(grants, function(grant) {
            this.configColumns.push(new Ext.ux.grid.CheckColumn({
                id: grant,
                header: i18n._(this[grant + 'GrantTitle']),
                tooltip: i18n._(this[grant + 'GrantDescription']),
                dataIndex: grant + 'Grant',
                width: 55
            }));
        }, this);
    },

    useGrant: function(grant, use) {
        var cm = this.getColumnModel(),
            findFn = function(o) { return o.id == grant;},
            current = lodash.find(cm.config, findFn),
            config = lodash.find(this.configColumns, findFn);

        if (use && ! current) {
            cm.setConfig(cm.config.push(config));
        } else if (! use && current) {
            cm.setConfig(lodash.filter(cm.config, lodash.negate(findFn)));
        }
    }
});

/**
 * grants by model registry
 *
 * @type {{defaultGrants, getByContainer, register}}
 */
Tine.widgets.container.GrantsManager = function() {
    /**
     * contains Array of grants or a function that returns the grants depending on some condition
     *
     * @type {Object}
     */
    var grantsForModels = {};

    return {
        /**
         * default grants
         *
         * @return Array
         */
        defaultGrants: function() {
            return ['read', 'add', 'edit', 'delete', 'export', 'sync'];
        },

        /**
         * get container grants for specific container
         *
         * @param {Tine.Tinebase.Model.Container} container
         * @return Array
         */
        getByContainer: function(container) {
            var modelName = container.model || container,
                grants  = grantsForModels[modelName]
                    ? (Ext.isFunction(grantsForModels[modelName]) ? grantsForModels[modelName](container) : grantsForModels[modelName])
                    : this.defaultGrants();

            return grants;
        },

        /**
         * register grants or grants function for given model
         *
         * @param {Record/String} modelName
         * @param {Array|Function} grants
         */
        register: function(modelName, grantsOrFunction) {
            grantsForModels[modelName] = grantsOrFunction;
        },
    };
}();
