/*
 * Tine 2.0
 *
 * @package     Tinebase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2013 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 * TODO maybe we should generalize parts of this and have a common parent for this and Tine.widgets.relation.GenericPickerGridPanel
 * TODO add more columns
 * TODO allow to edit description
 */
Ext.ns('Tine.widgets.dialog');

/**
 * @namespace   Tine.widgets.dialog
 * @class       Tine.widgets.dialog.AttachmentsGridPanel
 * @extends     Tine.widgets.grid.FileUploadGrid
 * @author      Philipp Schüle <p.schuele@metaways.de>
 */
Tine.widgets.dialog.AttachmentsGridPanel = Ext.extend(Tine.widgets.grid.FileUploadGrid, {
    /**
     * @cfg for FileUploadGrid
     */
    filesProperty: 'attachments',
    
    /**
     * The calling EditDialog
     * @type Tine.widgets.dialog.EditDialog
     */
    editDialog: null,
    
    /**
     * title
     * 
     * @type String
     */
    title: null,
    
    /**
     * the record
     * @type Record
     */
    record: null,

    /**
     * @type Tinebase.Application
     */
    app: null,

    /**
     * @cfg {String} requiredGrant to make actions
     */
    requiredGrant: 'editGrant',

    /* config */
    frame: true,
    border: true,
    autoScroll: true,
    layout: 'fit',
    canonicalName: 'AttachmentsGrid',

    /**
     * initializes the component
     */
    initComponent: function() {
        this.record = this.editDialog.record;
        this.app = this.editDialog.app;
        this.title = this.i18nTitle = i18n._('Attachments');
        this.i18nFileString = i18n._('Attachment');
        
        Tine.widgets.dialog.MultipleEditDialogPlugin.prototype.registerSkipItem(this);
        
        this.editDialog.on('save', this.onSaveRecord, this);
        this.editDialog.on('load', this.onLoadRecord, this);

        Tine.widgets.dialog.AttachmentsGridPanel.superclass.initComponent.call(this);
        
        this.initActions();
    },
    
    /**
     * get columns
     * @return Array
     */
    getColumns: function() {
        var columns = [{
            resizable: true,
            id: 'name',
            dataIndex: 'name',
            width: 150,
            header: i18n._('Name'),
            renderer: Ext.ux.PercentRendererWithName,
            sortable: true
        }, {
            resizable: true,
            id: 'size',
            dataIndex: 'size',
            width: 50,
            header: i18n._('Size'),
            renderer: Ext.util.Format.fileSize,
            sortable: true
        }, {
            resizable: true,
            id: 'contenttype',
            dataIndex: 'contenttype',
            width: 80,
            header: i18n._('Content Type'),
            sortable: true
        },{ id: 'creation_time',      header: i18n._('Creation Time'),         dataIndex: 'creation_time',         renderer: Tine.Tinebase.common.dateRenderer,     width: 80,
            sortable: true },
          { id: 'created_by',         header: i18n._('Created By'),            dataIndex: 'created_by',            renderer: Tine.Tinebase.common.usernameRenderer, width: 80,
            sortable: true }
        ];
        
        return columns;
    },
    
    /**
     * init store
     * @private
     */
    initStore: function () {
        this.store = new Ext.data.SimpleStore({
            fields: Tine.Tinebase.Model.Tree_Node
        });
    },
    
    /**
     * initActions
     */
    initActions: function () {
        this.action_download = new Ext.Action({
            requiredGrant: 'readGrant',
            allowMultiple: false,
            actionType: 'download',
            text: i18n._('Download'),
            handler: this.onDownload,
            iconCls: 'action_download',
            scope: this,
            disabled:true
        });
        this.actionUpdater.addActions([this.action_download]);
        this.getTopToolbar().addItem(this.action_download);
        this.contextMenu.addItem(this.action_download);
        
        this.on('rowdblclick', this.onDownload.createDelegate(this), this);
    },
    
    /**
     * is called from onApplyChanges of the edit dialog per save event
     * 
     * @param {Tine.widgets.dialog.EditDialog} dialog
     * @param {Tine.Tinebase.data.Record} record
     * @param {Function} ticket
     * @return {Boolean}
     */
    onSaveRecord: function(dialog, record, ticket) {
        var interceptor = ticket();

        if (record.data.hasOwnProperty('attachments')) {
            delete record.data.attachments;
        }
        var attachments = this.getData();
        record.set('attachments', attachments);
        
        interceptor();
    },
    
    /**
     * updates the title ot the tab
     * @param {Integer} count
     */
    updateTitle: function(count) {
        count = Ext.isNumber(count) ? count : this.store.getCount();
        this.setTitle(this.i18nTitle + ' (' + count + ')');
    },
    
    /**
     * populate store
     * 
     * @param {EditDialog} dialog
     * @param {Record} record
     * @param {Function} ticketFn
     */
    onLoadRecord: function(dialog, record, ticketFn) {
        this.store.removeAll();
        var interceptor = ticketFn();
        var attachments = record.get('attachments');
        if (attachments && attachments.length > 0) {
            this.updateTitle(attachments.length);
            var attachmentRecords = [];
            
            Ext.each(attachments, function(attachment) {
                attachmentRecords.push(new Tine.Tinebase.Model.Tree_Node(attachment, attachment.id));
            }, this);
            this.store.add(attachmentRecords);
        } else {
            this.updateTitle(0);
        }
        
        // add other listeners after population
        if (this.store) {
            this.store.on('update', this.updateTitle, this);
            this.store.on('add', this.updateTitle, this);
            this.store.on('remove', this.updateTitle, this);
        }
        interceptor();

        if (record.constructor.hasField(this.requiredGrant) && ! record.get(this.requiredGrant)) {
            this.setReadOnly(true);
        }
    },

    /**
     * get attachments data as array
     * 
     * @return {Array}
     */
    getData: function() {
        var attachments = [];
        
        this.store.each(function(attachment) {
            attachments.push(attachment.data);
        }, this);

        return attachments;
    },
    
    /**
     * download file
     * 
     * @param {} button
     * @param {} event
     */
    onDownload: function(button, event) {
        var selectedRows = this.getSelectionModel().getSelections(),
            fileRow = selectedRows[0],
            recordId = this.record.id;
        
        // TODO should be done by action updater
        if (! recordId || Ext.isObject(fileRow.get('tempFile'))) {
            Tine.log.debug('Tine.widgets.dialog.AttachmentsGridPanel::onDownload - file not yet available for download');
            return;
        }
        
        Tine.log.debug('Tine.widgets.dialog.AttachmentsGridPanel::onDownload - selected file:');
        Tine.log.debug(fileRow);
        
        var downloader = new Ext.ux.file.Download({
            params: {
                method: 'Tinebase.downloadRecordAttachment',
                requestType: 'HTTP',
                nodeId: fileRow.id,
                recordId: recordId,
                modelName: this.app.name + '_Model_' + this.editDialog.modelName
            }
        }).start();
    }
});
