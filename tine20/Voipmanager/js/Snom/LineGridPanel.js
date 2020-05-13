/*
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  widgets
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2010-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

Ext.namespace('Tine.Voipmanager');

require('../CallForwardPanel');

/**
 * Line Picker GridPanel
 * 
 * @namespace   Tine.Voipmanager
 * @class       Tine.Voipmanager.LineGridPanel
 * @extends     Tine.widgets.grid.PickerGridPanel
 * 
 * <p>Line Picker GridPanel</p>
 * <p><pre>
 * TODO         check max number of lines ?
 * </pre></p>
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2010 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 * @param       {Object} config
 * @constructor
 * Create a new Tine.Voipmanager.LineGridPanel
 */
Tine.Voipmanager.LineGridPanel = Ext.extend(Tine.widgets.grid.PickerGridPanel, {
    
    /**
     * 
     * @type tinebase app 
     */
    app: null,
    
    /**
     * 
     * @type Tine.Voipmanager.CallForwardPanel
     */
    cfPanel: null,
    
    /**
     * Tine record
     * @type 
     */
    activeRecord: null,
    
    /**
     * @type Tine.widgets.dialog.EditDialog
     */
    editDialog: null,
    
    /**
     * 
     * @cfg
     */
    clicksToEdit: 2,
    autoExpandColumn: 'name',
    // needed to select the first row after render!
    deferRowRender: false,
    
    /**
     * @private
     */
    initComponent: function() {
        
        this.recordClass = Tine.Voipmanager.Model.SnomLine;
        this.searchRecordClass = Tine.Voipmanager.Model.AsteriskSipPeer;

        this.cfPanel.on('change', this.onFieldChange, this);
        this.cfPanel.setDisabled(true);
        
        Tine.Voipmanager.LineGridPanel.superclass.initComponent.call(this);
        
        this.on('afterrender', this.onAfterRender, this);
        this.store.on('load', this.onStoreLoad, this);
    },

    /**
     * select first row after render
     */
    onAfterRender: function() {
        if (this.store.getCount() > 0) {
            this.getSelectionModel().selectFirstRow();
        }
    },
    
    /**
     * select first row after render
     */
    onStoreLoad: function(store, records, options) {
        if (this.rendered && records.length > 0) {
            var index = store.indexOf(records[0]),
                row = this.getView().getRow(index);
                
            Ext.fly(row).highlight();
            this.getSelectionModel().selectRow(index);
        }
    },
    
    /**
     * on call forward form field change: update store
     */
    onFieldChange: function() {
        this.editDialog.getForm().updateRecord(this.activeRecord);
        this.getView().refresh();
    },
    
    /**
     * Return CSS class to apply to rows depending upon record state
     * 
     * @param {} record
     * @param {Integer} index
     * @return {String}
     */
    getViewRowClass: function(record, index) {
        var result = '';
        if (record.dirty) {
            result = 'voipmanager-row-changed';
        }
        return result;
    },
    
    /**
     * init actions and toolbars
     */
    initActionsAndToolbars: function() {
        
        Tine.Voipmanager.LineGridPanel.superclass.initActionsAndToolbars.call(this);
        
        // only allow to add new lines from Voipmanager
        if (this.editDialog.recordProxy.appName == 'Voipmanager') {
            this.searchCombo = this.getSearchCombo();
    
            this.comboPanel = new Ext.Panel({
                layout: 'hfit',
                border: false,
                items: this.searchCombo,
                columnWidth: 1
            });
            
            this.tbar = new Ext.Toolbar({
                items: [
                    this.comboPanel
                ],
                layout: 'column'
            });
        }
    },
    
    /**
     * Is called when a record gets selected in the picker combo
     * 
     * @param {Ext.form.ComboBox} picker
     * @param {Record} recordToAdd
     */
    onAddRecordFromCombo: function(picker, recordToAdd) {
        
        if(! recordToAdd) {
            return;
        }
        
        var recordData = {
            asteriskline_id: recordToAdd.data,
            linenumber: this.store.getCount() + 1,
            lineactive: 1
        };
        
        var record = new Tine.Voipmanager.Model.SnomLine(recordData);
        
        var fields = ['cfi_mode','cfi_number','cfb_mode','cfb_number','cfd_mode','cfd_number','cfd_time' ];
        for (var i=0; i < fields.length; i++) {
            record.data[fields[i]] = recordToAdd.data[fields[i]];
        }
        
        // check if already in
        var found = false;
        this.store.each(function (line) {
            if (line.data.asteriskline_id.id == recordToAdd.data.id) {
                found = true;
            }
            if (line.get('linenumber') == record.get('linenumber')) {
                // use next linenumber
                // TODO should be improved, maybe server should decide which (free) linenumber should be used
                record.set('linenumber', line.get('linenumber') + 1);
            }
        }, this);
        
        if (! found) {
            // if not found -> add
            this.store.add([record]);
        }
        
        picker.reset();
    },
    
    /**
     * init selection model
     */
    initGrid: function() {
        Tine.Voipmanager.LineGridPanel.superclass.initGrid.call(this);
        
        this.selModel.on('selectionchange', this.onSelectionChange, this);

        // init view
        this.view =  new Ext.grid.GridView({
            getRowClass: this.getViewRowClass,
            autoFill: true,
            forceFit:true
        });
    },
    
    /**
     * on selection change handler
     * @param {} sm
     */
    onSelectionChange: function(sm) {
        var rowCount = sm.getCount();
        if (rowCount == 1) {
            var selectedRows = sm.getSelections();
            this.activeRecord = selectedRows[0];
            this.cfPanel.setDisabled(false);
            //this.cfPanel.getForm().loadRecord(this.activeRecord);
            this.editDialog.getForm().loadRecord(this.activeRecord);
            this.cfPanel.onRecordLoad(this.activeRecord);
        } else {
            //this.cfPanel.getForm().reset();
            this.cfPanel.setDisabled(true);
        }
        
        // only allow to remove lines from Voipmanager and if rowCount > 0
        this.actionRemove.setDisabled(this.editDialog.recordProxy.appName != 'Voipmanager' || rowCount == 0);
    },
    
    /**
     * @return Ext.grid.ColumnModel
     * @private
     */
    getColumnModel: function() {
        if (! this.colModel) {
            // we need to init translations because it could be that we call this from Phone app without Voipmanager
            var translations;
            if (!this.app) {
                translations = new Locale.Gettext();
                translations.textdomain('Voipmanager');
            } else {
                translations = this.app.i18n;
            }

            this.colModel = new Ext.grid.ColumnModel({
                defaults: {
                    sortable: true
                },
                columns: [
                    {id: 'linenumber', header: '', dataIndex: 'linenumber', width: 20},
                    {
                        id: 'name',
                        header: translations._('Line'),
                        dataIndex: 'asteriskline_id',
                        width: 120,
                        renderer: this.nameRenderer
                    },
                    {
                        id: 'idletext',
                        header: translations._('Idle Text'),
                        dataIndex: 'idletext',
                        width: 100,
                        editor: new Ext.form.TextField({
                            allowBlank: false,
                            allowNegative: false,
                            maxLength: 60
                        })
                    }
                ]
            });
        }
        
        return this.colModel;
    },
    
    nameRenderer: function(value) {
        return (value && value.name) ? value.name : '';
    },
    
    initFilterPanel: function() {
    }
});
