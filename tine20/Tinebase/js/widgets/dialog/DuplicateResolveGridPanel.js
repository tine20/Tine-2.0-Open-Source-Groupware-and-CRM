/*
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2007-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 */
Ext.ns('Tine.widgets.dialog');

/**
 * 
 * @namespace   Tine.widgets.dialog
 * @class       Tine.widgets.dialog.DuplicateResolveGridPanel
 * @extends     Ext.grid.EditorGridPanel
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @constructor
 * @param {Object} config The configuration options.
 */
Tine.widgets.dialog.DuplicateResolveGridPanel = Ext.extend(Ext.grid.EditorGridPanel, {
    /**
     * @cfg {Tine.Tinebase.Application} app
     * instance of the app object (required)
     */
    app: null,
    
    // private config overrides
    cls: 'tw-editdialog',
    border: false,
    layout: 'fit',
    enableColumnMove: false,
    stripeRows: true,
    trackMouseOver: false,
    clicksToEdit:1,
    enableHdMenu : false,
    viewConfig : {
        forceFit:true
    },
    
    initComponent: function() {
//        this.addEvents();
        this.title = _('The record you try to add might already exist.');
        
        this.initColumnModel();
        this.initToolbar();
        
        this.store.on('load', this.onStoreLoad, this);
        this.on('cellclick', this.onCellClick, this);
        Tine.widgets.dialog.DuplicateResolveGridPanel.superclass.initComponent.call(this);
    },
    
    
    /**
     * adopt final value to the one selected
     */
    onCellClick: function(grid, rowIndex, colIndex, e) {
        var dataIndex = this.getColumnModel().getDataIndex(colIndex),
            resolveRecord = this.store.getAt(rowIndex);
        
        if (resolveRecord && dataIndex && dataIndex.match(/clientValue|value\d+/)) {
            resolveRecord.set('finalValue', resolveRecord.get(dataIndex));
            
            var celEl = this.getView().getCell(rowIndex, this.getColumnModel().getIndexById('finalValue'));
            if (celEl) {
                Ext.fly(celEl).highlight();
            }
        }
    },
    
    /**
     * called when the store got new data
     */
    onStoreLoad: function() {
        var strategy = this.store.resolveStrategy;
        
        this.actionCombo.setValue(strategy);
        this.applyStrategy(strategy);
    },
    
    /**
     * select handler of action combo
     */
    onActionSelect: function(combo, record, idx) {
        var strategy = record.get('value');
        
        this.store.applyStrategy(strategy);
        this.applyStrategy(strategy);
    },
    
    /**
     * apply an action (generate final data)
     * - mergeTheirs:   merge keep existing values (discards client record)
     * - mergeMine:     merge, keep client values (discards client record)
     * - discard:       discard client record
     * - keep:          keep client record (create duplicate)
     * 
     * @param {Ext.data.Store} store with field records (DuplicateResolveModel)
     * @param {Sting} strategy
     */
    applyStrategy: function(strategy) {
        
        var cm = this.getColumnModel(),
            view = this.getView();
            
        if (cm) {
            cm.setHidden(cm.getIndexById('clientValue'), strategy == 'discard');
            cm.setHidden(cm.getIndexById('finalValue'), strategy == 'keep');
            
            if (view && view.grid) {
                this.getView().refresh();
            }
        }
    },

    /**
     * init our column model
     */
    initColumnModel: function() {
        var valueRendererDelegate = this.valueRenderer.createDelegate(this);
        
        this.cm = new Ext.grid.ColumnModel([{
            header: _('Field Name'), 
            width:50, 
            sortable: true, 
            dataIndex:'i18nFieldName', 
            id: 'i18nFieldName', 
            menuDisabled:true
        }, {
            header: _('My Value'), 
            width:50, 
            resizable:false, 
            dataIndex: 'clientValue', 
            id: 'clientValue', 
            menuDisabled:true, 
            renderer: valueRendererDelegate
        }, {
            header: _('Existing Value'), 
            width:50, 
            resizable:false, 
            dataIndex: 'value' + this.store.duplicateIdx, 
            id: 'value' + this.store.duplicateIdx, 
            menuDisabled:true, 
            renderer: valueRendererDelegate
        }, {
            header: _('Final Value'), 
            width:50, 
            resizable:false, 
            dataIndex: 'finalValue', 
            id: 'finalValue', 
            menuDisabled:true, 
            renderer: valueRendererDelegate
        }]);
        
    },
    
    /**
     * init the toolbar
     */
    initToolbar: function() {
        this.tbar = [{
            xtype: 'label',
            text: _('Action:') + ' '
        }, {
            xtype: 'combo',
            ref: '../actionCombo',
            typeAhead: true,
            width: 250,
            triggerAction: 'all',
            lazyRender:true,
            mode: 'local',
            valueField: 'value',
            displayField: 'text',
            value: this.store.resolveStrategy,
            store: new Ext.data.ArrayStore({
                id: 0,
                fields: ['value', 'text'],
                data: [
                    ['mergeTheirs', _('Merge, keeping existing details')],
                    ['mergeMine',   _('Merge, keeping my details')],
                    ['discard',     _('Keep existing record and discard mine')],
                    ['keep',        _('Keep both records')]
                ]
            }),
            listeners: {
                scope: this, 
                select: this.onActionSelect
            }
        }];
    },
    
    /**
     * interceptor for all renderers
     * - manage colors
     * - pick appropriate renderer
     */
    valueRenderer: function(value, metaData, record, rowIndex, colIndex, store) {
        var fieldName = record.get('fieldName'),
            dataIndex = this.getColumnModel().getDataIndex(colIndex),
            renderer = Tine.widgets.grid.RendererManager.get(this.app, this.store.recordClass, fieldName, Tine.widgets.grid.RendererManager.CATEGORY_GRIDPANEL);
        
        try {
            // color management
            if (dataIndex && dataIndex.match(/clientValue|value\d+/) && !this.store.resolveStrategy.match(/(keep|discard)/)) {
                
                var action = record.get('finalValue') == value ? 'keep' : 'discard';
                metaData.css = 'tine-duplicateresolve-' + action + 'value';
//                metaData.css = 'tine-duplicateresolve-adoptedvalue';
            }
            
            return renderer.apply(this, arguments);
        } catch (e) {
            Tine.log.err('Tine.widgets.dialog.DuplicateResolveGridPanel::valueRenderer');
            Tine.log.err(e.stack ? e.stack : e);
        }
    }
    
});

/**
 * @class Tine.widgets.dialog.DuplicateResolveModel
 * A specific {@link Ext.data.Record} type that represents a field/clientValue/doublicateValues/finalValue set and is made to work with the
 * {@link Tine.widgets.dialog.DuplicateResolveGridPanel}.
 * @constructor
 */
Tine.widgets.dialog.DuplicateResolveModel = Ext.data.Record.create([
    {name: 'fieldName', type: 'string'},
    {name: 'i18nFieldName', type: 'string'},
    'clientValue', 'value0' , 'value1' , 'value2' , 'value3' , 'value4', 'finalValue'
]);

Tine.widgets.dialog.DuplicateResolveStore = Ext.extend(Ext.data.JsonStore, {
    /**
     * @cfg {Tine.Tinebase.Application} app
     * instance of the app object (required)
     */
    app: null,
    
    /**
     * @cfg {Ext.data.Record} recordClass
     * record definition class  (required)
     */
    recordClass: null,
    
    /**
     * @cfg {Ext.data.DataProxy} recordProxy
     */
    recordProxy: null,
    
    /**
     * @cfg {Object/Record} clientRecord
     */
    clientRecord: null,
    
    /**
     * @cfg {Array} duplicates
     * array of Objects or Records
     */
    duplicates: null,
    
    /**
     * @cfg {String} resolveStrategy
     * default resolve action
     */
    resolveStrategy: null,
    
    /**
     * @cfg {String} defaultResolveStrategy
     * default resolve action
     */
    defaultResolveStrategy: 'mergeTheirs',
    
    // private config overrides
    idProperty: 'fieldName',
    fields: Tine.widgets.dialog.DuplicateResolveModel,
    
    constructor: function(config) {
        var initialData = config.data;
        delete config.data;
        
        Tine.widgets.dialog.DuplicateResolveStore.superclass.constructor.apply(this, arguments);
        
        if (! this.recordProxy && this.recordClass) {
            this.recordProxy = new Tine.Tinebase.data.RecordProxy({
                recordClass: this.recordClass
            });
        }
        
        // forece dublicate 0 atm.
        this.duplicateIdx = 0;
        
        if (initialData) {
            this.loadData(initialData);
        }
    },
    
    loadData: function(data, resolveStrategy, finalRecord) {
        // init records
        this.clientRecord = this.createRecord(data.clientRecord);
        
        this.duplicates = data.duplicates;
        Ext.each([].concat(this.duplicates), function(duplicate, idx) {this.duplicates[idx] = this.createRecord(this.duplicates[idx])}, this);
        
        this.resolveStrategy = resolveStrategy || this.defaultResolveStrategy;
        
        if (finalRecord) {
            finalRecord = this.createRecord(finalRecord);
        }
        
        // @TODO sort conflict fileds first 
        //   - group fields (contact org, home / phones etc.)
        // @TODO add customfields
        Ext.each(this.recordClass.getFieldDefinitions(), function(field) {
            if (field.isMetaField || field.ommitDuplicateResolveing) return;
            
            var fieldName = field.name,
                recordData = {
                    fieldName: fieldName,
                    i18nFieldName: field.label ? this.app.i18n._hidden(field.label) : this.app.i18n._hidden(fieldName),
                    clientValue: Tine.Tinebase.common.assertComparable(this.clientRecord.get(fieldName))
                };
            
            Ext.each([].concat(this.duplicates), function(duplicate, idx) {recordData['value' + idx] =  Tine.Tinebase.common.assertComparable(this.duplicates[idx].get(fieldName))}, this);
            
            var record = new Tine.widgets.dialog.DuplicateResolveModel(recordData, fieldName);
            
            if (finalRecord) {
                if (finalRecord.modified && finalRecord.modified.hasOwnProperty(fieldName)) {
//                    Tine.log.debug('Tine.widgets.dialog.DuplicateResolveStore::loadData ' + fieldName + 'changed from  ' + finalRecord.modified[fieldName] + ' to ' + finalRecord.get(fieldName));
                    record.set('finalValue', finalRecord.modified[fieldName]);
                    
                }
                
                record.set('finalValue', finalRecord.get(fieldName));
            }
            
            this.addSorted(record);
        }, this);
        
        if (! finalRecord) {
            this.applyStrategy(this.resolveStrategy);
        }
        
        this.fireEvent('load', this);
    },
    
    /**
     * apply an strategy (generate final data)
     * - mergeTheirs:   merge keep existing values (discards client record)
     * - mergeMine:     merge, keep client values (discards client record)
     * - discard:       discard client record
     * - keep:          keep client record (create duplicate)
     * 
     * @param {Sting} strategy
     */
    applyStrategy: function(strategy) {
        Tine.log.debug('Tine.widgets.dialog.DuplicateResolveStore::applyStrategy action: ' + strategy);
        
        this.resolveStrategy = strategy;
        
        this.each(function(resolveRecord) {
            var theirs = resolveRecord.get('value' + this.duplicateIdx),
                mine = resolveRecord.get('clientValue'),
                location = strategy === 'keep' ? 'mine' : 'theirs';
            
            // undefined or empty theirs value -> keep mine
            if (strategy == 'mergeTheirs' && ['', 'null', 'undefined', '[]'].indexOf(String(theirs)) > -1) {
                location = 'mine';
            }
            
            // only keep mine if its not undefined or empty
            if (strategy == 'mergeMine' && ['', 'null', 'undefined', '[]'].indexOf(String(mine)) < 0) {
                location = 'mine';
            }
            
            // spechial merge for tags
            // @TODO generalize me
            if (resolveRecord.get('fieldName') == 'tags') {
                resolveRecord.set('finalValue', Tine.Tinebase.common.assertComparable([].concat(mine).concat(theirs)));
            } else {
                resolveRecord.set('finalValue', location === 'mine' ? mine : theirs);
            }
        }, this);
        
        this.commitChanges();
    },
    
    /**
     * returns record with conflict resolved data
     */
    getResolvedRecord: function() {
        var record = (this.resolveStrategy == 'keep' ? this.clientRecord : this.duplicates[this.duplicateIdx]).copy();
        
        this.each(function(resolveRecord) {
            var fieldName = resolveRecord.get('fieldName'),
                finalValue = resolveRecord.get('finalValue'),
                modified = resolveRecord.modified || {};
            
            // also record changes
            if (modified.hasOwnProperty('finalValue')) {
//                Tine.log.debug('Tine.widgets.dialog.DuplicateResolveStore::getResolvedRecord ' + fieldName + ' changed from ' + modified.finalValue + ' to ' + finalValue);
                record.set(fieldName, Tine.Tinebase.common.assertComparable(modified.finalValue));
            }
            
            record.set(fieldName, Tine.Tinebase.common.assertComparable(finalValue));
            
        }, this);
        
        return record;
    },
    
    /**
     * create record from data
     * 
     * @param {Object} data
     * @return {Record}
     */
    createRecord: function(data) {
        return Ext.isFunction(data.beginEdit) ? data : this.recordProxy.recordReader({responseText: Ext.encode(data)});
    }
});