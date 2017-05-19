/*
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Martin Jatho <m.jatho@metaways.de>
 * @copyright   Copyright (c) 2007-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 */
Ext.ns('Tine.Filemanager');

require('./nodeActions');
require('./nodeContextMenu');

/**
 * File grid panel
 *
 * @namespace   Tine.Filemanager
 * @class       Tine.Filemanager.NodeGridPanel
 * @extends     Tine.widgets.grid.GridPanel
 *
 * <p>Node Grid Panel</p>
 * <p><pre>
 * </pre></p>
 *
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Martin Jatho <m.jatho@metaways.de>
 * @copyright   Copyright (c) 2007-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 * @param       {Object} config
 * @constructor
 * Create a new Tine.Filemanager.FileGridPanel
 */
Tine.Filemanager.NodeGridPanel = Ext.extend(Tine.widgets.grid.GridPanel, {
    /**
     * @cfg filesProperty
     * @type String
     */
    filesProperty: 'files',

    /**
     * config values
     * @private
     */
    header: false,
    border: false,
    deferredRender: false,
    autoExpandColumn: 'name',
    showProgress: true,

    recordClass: Tine.Filemanager.Model.Node,
    listenMessageBus: true,
    hasDetailsPanel: false,
    evalGrants: true,
    // initialLoadAfterRender: false,

    /**
     * grid specific
     * @private
     */
    currentFolderNode: null,

    /**
     * Prevent download and edit of nodes/files and so on. Only allow the selection of items
     */
    selectOnly: false,

    /**
     * inits this cmp
     * @private
     */
    initComponent: function() {
        Ext.applyIf(this.defaultSortInfo, {field: 'name', direction: 'DESC'});
        Ext.applyIf(this.gridConfig, {
            autoExpandColumn: 'name',
            enableFileDialog: false,
            enableDragDrop: true,
            ddGroup: 'fileDDGroup',
            listeners: {
                scope: this,
                afterrender: this.initDragDrop
            }
        });

        if (this.readOnly) {
            this.gridConfig.enableDragDrop = false;
        }

        this.recordProxy = Tine.Filemanager.fileRecordBackend;

        this.gridConfig.cm = this.getColumnModel();

        this.defaultFilters = [
            {field: 'query', operator: 'contains', value: ''},
            {field: 'path', operator: 'equals', value: Tine.Tinebase.container.getMyFileNodePath()}
        ];

        this.plugins = this.plugins || [];

        this.filterToolbar = this.filterToolbar || this.getFilterToolbar();

        this.plugins.push(this.filterToolbar);

        if (!this.readOnly) {
            this.plugins.push({
                ptype: 'ux.browseplugin',
                multiple: true,
                scope: this,
                enableFileDialog: false,
                handler: this.onFilesSelect.createDelegate(this)
            });
        }

        if (this.hasQuickSearchFilterToolbarPlugin) {
            this.filterToolbar.getQuickFilterPlugin().criteriaIgnores.push({field: 'path'});
        }

        Tine.Filemanager.NodeGridPanel.superclass.initComponent.call(this);
        this.getStore().on('load', this.onLoad.createDelegate(this));

        // // cope with empty selections - dosn't work. It's confusing if e.g. the delte btn is enabled with no selections
        // this.selectionModel.on('selectionchange', function(sm) {
        //     if (sm.getSelections().length) {
        //         return;
        //     }
        //
        //     var _ = window.lodash,
        //         recordData = _.get(this, 'currentFolderNode.attributes'),
        //         record = recordData ? Tine.Tinebase.data.Record.setFromJson(JSON.stringify(recordData), this.recordClass) : null;
        //
        //     if (record) {
        //         this.actionUpdater.updateActions(record);
        //     }
        // }, this);

    },

    /**
     * after grid renderd
     */
    initDragDrop: function () {
        var grid = this.grid,
            view = grid.getView();

        view.dragZone.onBeforeDrag = this.onBeforeDrag.createDelegate(this);

        this.dropZone = new Ext.dd.DropZone(this.getEl(), {
            ddGroup: 'fileDDGroup',
            onNodeOver: this.onNodeOver.createDelegate(this),
            onNodeDrop: this.onNodeDrop.createDelegate(this),
            getTargetFromEvent: function(e) {
                var idx = view.findRowIndex(e.target),
                    record = grid.getStore().getAt(idx);

                return record;
            }
        })
    },

    /**
     * An empty function by default, but provided so that you can perform a custom action before the initial
     * drag event begins and optionally cancel it.
     * @param {Object} data An object containing arbitrary data to be shared with drop targets
     * @param {Event} e The event object
     * @return {Boolean} isValid True if the drag event is valid, else false to cancel
     */
    onBeforeDrag: function(data, e) {
        var _ = window.lodash,
            // @TODO: rethink: do I need delte on the record or parent?
            requiredGrant = e.ctrlKey || e.altKey ? 'readGrant' : 'editGrant';

        return !this.selectionModel.isFilterSelect &&
            _.reduce(this.selectionModel.getSelections(), function(allowed, record) {
                return allowed && !! _.get(record, 'data.account_grants.' + requiredGrant);
            }, true);
    },

    /*
     * @param {Object} nodeData The custom data associated with the drop node (this is the same value returned from {@link #getTargetFromEvent} for this node)
     * @param {Ext.dd.DragSource} source The drag source that was dragged over this drop zone
     * @param {Event} e The event
     * @param {Object} data An object containing arbitrary data supplied by the drag source
     * @return {String} status The CSS class that communicates the drop status back to the source so that the underlying {@link Ext.dd.StatusProxy} can be updated
     */
    onNodeOver: function(record, source, e, data) {
        var _ = window.lodash,
            dropAllowed =
                record.get('type') == 'folder'
                && _.get(record, 'data.account_grants.addGrant', false)
                && source == this.grid.getView().dragZone,
            action = e.ctrlKey || e.altKey ? 'copy' : 'move'

        return dropAllowed ?
            'tinebase-dd-drop-ok-' + action :
            Ext.dd.DropZone.prototype.dropNotAllowed;
    },

    /**
     * Called when the DropZone determines that a {@link Ext.dd.DragSource} has been dropped onto
     * the drop node.  The default implementation returns false, so it should be overridden to provide the
     * appropriate processing of the drop event and return true so that the drag source's repair action does not run.
     * @param {Object} nodeData The custom data associated with the drop node (this is the same value returned from
     * {@link #getTargetFromEvent} for this node)
     * @param {Ext.dd.DragSource} source The drag source that was dragged over this drop zone
     * @param {Event} e The event
     * @param {Object} data An object containing arbitrary data supplied by the drag source
     * @return {Boolean} True if the drop was valid, else false
     */
    onNodeDrop: function(target, dd, e, data) {
        Tine.Filemanager.fileRecordBackend.copyNodes(data.selections, target, !(e.ctrlKey || e.altKey));
        this.grid.getStore().remove(data.selections);
        return true;
    },

    /**
     * returns cm
     *
     * @return Ext.grid.ColumnModel
     * @private
     */
    getColumnModel: function(){
        var columns = [{
                id: 'tags',
                header: this.app.i18n._('Tags'),
                dataIndex: 'tags',
                width: 50,
                renderer: Tine.Tinebase.common.tagsRenderer,
                sortable: false,
                hidden: false
            }, {
                id: 'name',
                header: this.app.i18n._("Name"),
                width: 70,
                sortable: true,
                dataIndex: 'name',
                renderer: Ext.ux.PercentRendererWithName
            },{
                id: 'size',
                header: this.app.i18n._("Size"),
                width: 40,
                sortable: true,
                dataIndex: 'size',
                renderer: Tine.Tinebase.common.byteRenderer.createDelegate(this, [2, true], 3)
            },{
                id: 'contenttype',
                header: this.app.i18n._("Contenttype"),
                width: 50,
                sortable: true,
                dataIndex: 'contenttype',
                renderer: function(value, metadata, record) {

                    var app = Tine.Tinebase.appMgr.get('Filemanager');
                    if(record.data.type == 'folder') {
                        return app.i18n._("Folder");
                    }
                    else {
                        return value;
                    }
                }
            },{
                id: 'creation_time',
                header: this.app.i18n._("Creation Time"),
                width: 50,
                sortable: true,
                dataIndex: 'creation_time',
                renderer: Tine.Tinebase.common.dateTimeRenderer
            },{
                id: 'created_by',
                header: this.app.i18n._("Created By"),
                width: 50,
                sortable: true,
                dataIndex: 'created_by',
                renderer: Tine.Tinebase.common.usernameRenderer
            },{
                id: 'last_modified_time',
                header: this.app.i18n._("Last Modified Time"),
                width: 80,
                sortable: true,
                dataIndex: 'last_modified_time',
                renderer: Tine.Tinebase.common.dateTimeRenderer
            },{
                id: 'last_modified_by',
                header: this.app.i18n._("Last Modified By"),
                width: 50,
                sortable: true,
                dataIndex: 'last_modified_by',
                renderer: Tine.Tinebase.common.usernameRenderer
            }
        ];

        if (Tine.Tinebase.configManager.get('filesystem.modLogActive', 'Tinebase')) {
            columns.push({
                id: 'revision_size',
                header: this.app.i18n._("Revision Size"),
                tooltip: this.app.i18n._("Total size of all available revisions"),
                width: 40,
                sortable: true,
                dataIndex: 'revision_size',
                hidden: true,
                renderer: Tine.Tinebase.common.byteRenderer.createDelegate(this, [2, true], 3)
            });
        }

        return new Ext.grid.ColumnModel({
            defaults: {
                sortable: true,
                resizable: true
            },
            columns: columns
        });
    },

    /**
     * status column renderer
     * @param {string} value
     * @return {string}
     */
    statusRenderer: function(value) {
        return this.app.i18n._hidden(value);
    },

    /**
     * Capture space to toggle document preview
     */
    onKeyDown: function(e) {
        Tine.Filemanager.NodeGridPanel.superclass.onKeyDown.apply(this, arguments);

        var sm = this.selectionModel,
            node = sm.getSelected();

        // Open preview on space if a node is selected and the node type equals file
        if (e.getKey() === 32 && node && node.get('type') === 'file') {
            Tine.Filemanager.DocumentPreview.openWindow({
                record: node
            });
        }
    },

    /**
     * init ext grid panel
     * @private
     */
    initGrid: function() {
        Tine.Filemanager.NodeGridPanel.superclass.initGrid.call(this);

        if (this.usePagingToolbar) {
           this.initPagingToolbar();
        }
    },

    /**
     * inserts a quota Message when using old Browsers with html4upload
     */
    initPagingToolbar: function() {
        if(!this.pagingToolbar || !this.pagingToolbar.rendered) {
            this.initPagingToolbar.defer(50, this);
            return;
        }
        // old browsers
        if (!((! Ext.isGecko && window.XMLHttpRequest && window.File && window.FileList) || (Ext.isGecko && window.FileReader))) {
            var text = new Ext.Panel({padding: 2, html: String.format(this.app.i18n._('The max. Upload Filesize is {0} MB'), Tine.Tinebase.registry.get('maxFileUploadSize') / 1048576 )});
            this.pagingToolbar.insert(12, new Ext.Toolbar.Separator());
            this.pagingToolbar.insert(12, text);
            this.pagingToolbar.doLayout();
        }
    },

    /**
     * returns filter toolbar -> supress OR filters
     * @private
     */
    getFilterToolbar: function(config) {
        config = config || {};
        var plugins = [];

        if (this.hasQuickSearchFilterToolbarPlugin) {
            this.quickSearchFilterToolbarPlugin = new Tine.widgets.grid.FilterToolbarQuickFilterPlugin();
            plugins.push(this.quickSearchFilterToolbarPlugin);
        }

        return new Tine.widgets.grid.FilterToolbar(Ext.apply(config, {
            app: this.app,
            recordClass: this.recordClass,
            filterModels: this.recordClass.getFilterModel().concat(this.getCustomfieldFilters()),
            defaultFilter: 'query',
            filters: this.defaultFilters || [],
            plugins: plugins
        }));
    },

    /**
     * returns add action / test
     *
     * @return {Object} add action config
     */
    getAddAction: function () {
        return {
            requiredGrant: 'addGrant',
            actionType: 'add',
            text: this.app.i18n._('Upload'),
            handler: this.onFilesSelect,
            disabled: true,
            scope: this,
            plugins: [{
                ptype: 'ux.browseplugin',
                multiple: true,
                enableFileDrop: false,
                disable: true
            }],
            iconCls: this.app.appName + 'IconCls',
            actionUpdater: function(action) {
                var _ = window.lodash,
                    allowAdd = _.get(this, 'currentFolderNode.attributes.account_grants.addGrant', false);

                action.setDisabled(!allowAdd);
            }.createDelegate(this)
        };
    },

    initLayout: function() {
        this.supr().initLayout.call(this);

        var northPanel = lodash.find(this.items, function (i) {
            return i.region == 'north'
        });

        northPanel.tbar = new Tine.Filemanager.RecursiveFilter({
            gridPanel: this,
            hidden: true
        });
    },

    /**
     * init actions with actionToolbar, contextMenu and actionUpdater
     * @private
     */
    initActions: function() {
        // generic node actions - work on selections grid/tree nodes
        this.action_createFolder = Tine.Filemanager.nodeActionsMgr.get('createFolder');
        this.action_editFile = Tine.Filemanager.nodeActionsMgr.get('edit');
        this.action_deleteRecord = Tine.Filemanager.nodeActionsMgr.get('delete');
        this.action_download = Tine.Filemanager.nodeActionsMgr.get('download');
        this.action_moveRecord = Tine.Filemanager.nodeActionsMgr.get('move');
        this.action_publish = Tine.Filemanager.nodeActionsMgr.get('publish');

        // grid only actions - work on node which is displayed (this.currentFolderNode)
        // @TODO: fixme - ux problems with filterselect / initialData
        this.action_upload = new Ext.Action(this.getAddAction());
        this.action_goUpFolder = new Ext.Action({
            allowMultiple: true,
            actionType: 'goUpFolder',
            text: this.app.i18n._('Folder Up'),
            handler: this.onLoadParentFolder,
            iconCls: 'action_filemanager_folder_up',
            disabled: true,
            scope: this,
            actionUpdater: function(action) {
                var _ = window.lodash,
                    path = _.get(this, 'currentFolderNode.attributes.path', false);

                action.setDisabled(path == '/');
            }.createDelegate(this)
        });

        this.contextMenu = Tine.Filemanager.nodeContextMenu.getMenu({
            nodeName: Tine.Filemanager.Model.Node.getRecordName(),
            actions: [this.action_deleteRecord, 'rename', this.action_moveRecord, this.action_download, 'resume', 'pause', this.action_editFile, this.action_publish],
            scope: this,
            backend: 'Filemanager',
            backendModel: 'Node'
        });

        this.folderContextMenu = Tine.Filemanager.nodeContextMenu.getMenu({
            nodeName: Tine.Filemanager.Model.Node.getContainerName(),
            actions: [this.action_deleteRecord, 'rename', this.action_moveRecord, this.action_editFile, this.action_publish],
            scope: this,
            backend: 'Filemanager',
            backendModel: 'Node'
        });

        this.actionUpdater.addActions(this.contextMenu.items);
        this.actionUpdater.addActions(this.folderContextMenu.items);

        this.actionUpdater.addActions([
            this.action_upload,
            this.action_createFolder,
            this.action_goUpFolder,
            this.action_download,
            this.action_deleteRecord,
            this.action_editFile,
            this.action_publish
        ]);
    },

    /**
     * get the right contextMenu
     */
    getContextMenu: function(grid, row, e) {
        var r = this.store.getAt(row),
            type = r ? r.get('type') : null;

        return type === 'folder' ? this.folderContextMenu : this.contextMenu;
    },

    /**
     * get action toolbar
     *
     * @return {Ext.Toolbar}
     */
    getActionToolbar: function() {
        if (! this.actionToolbar) {
            this.actionToolbar = new Ext.Toolbar({
                defaults: {height: 55},
                items: [{
                    xtype: 'buttongroup',
                    layout: 'toolbar',
                    buttonAlign: 'left',
                    columns: 8,
                    defaults: {minWidth: 60},
                    items: [
                        this.splitAddButton ?
                        Ext.apply(new Ext.SplitButton(this.action_upload), {
                            scale: 'medium',
                            rowspan: 2,
                            iconAlign: 'top',
                            arrowAlign:'right',
                            menu: new Ext.menu.Menu({
                                items: [],
                                plugins: [{
                                    ptype: 'ux.itemregistry',
                                    key:   'Tine.widgets.grid.GridPanel.addButton'
                                }, {
                                    ptype: 'ux.itemregistry',
                                    key:   'Tinebase-MainContextMenu'
                                }]
                            })
                        }) :
                        Ext.apply(new Ext.Button(this.action_upload), {
                            scale: 'medium',
                            rowspan: 2,
                            iconAlign: 'top'
                        }),

                        Ext.apply(new Ext.Button(this.action_editFile), {
                            scale: 'medium',
                            rowspan: 2,
                            iconAlign: 'top'
                        }),
                        Ext.apply(new Ext.Button(this.action_deleteRecord), {
                            scale: 'medium',
                            rowspan: 2,
                            iconAlign: 'top'
                        }),
                        Ext.apply(new Ext.Button(this.action_createFolder), {
                            scale: 'medium',
                            rowspan: 2,
                            iconAlign: 'top'
                        }),
                        Ext.apply(new Ext.Button(this.action_goUpFolder), {
                            scale: 'medium',
                            rowspan: 2,
                            iconAlign: 'top'
                        }),
                        Ext.apply(new Ext.Button(this.action_download), {
                            scale: 'medium',
                            rowspan: 2,
                            iconAlign: 'top'
                        }),
                        Ext.apply(new Ext.Button(this.action_publish), {
                            scale: 'medium',
                            rowspan: 2,
                            iconAlign: 'top'
                        })
                 ]
                }, this.getActionToolbarItems()]
            });

            this.actionToolbar.on('resize', this.onActionToolbarResize, this, {buffer: 250});
            this.actionToolbar.on('show', this.onActionToolbarResize, this);

            if (this.filterToolbar && typeof this.filterToolbar.getQuickFilterField == 'function') {
                this.actionToolbar.add('->', this.filterToolbar.getQuickFilterField());
            }
        }

        return this.actionToolbar;
    },

    /**
     * grid row doubleclick handler
     *
     * @param {Tine.Filemanager.NodeGridPanel} grid
     * @param {} row record
     * @param {Ext.EventObjet} e
     */
    onRowDblClick: function(grid, row, e) {
        var app = this.app;
        var rowRecord = grid.getStore().getAt(row);

        if(rowRecord.data.type == 'file' && !this.readOnly) {
            Tine.Filemanager.downloadFile(rowRecord);
        }

        else if (rowRecord.data.type == 'folder'){
            var treePanel = this.treePanel || app.getMainScreen().getWestPanel().getContainerTreePanel();

            var currentFolderNode = treePanel.getNodeById(rowRecord.id);

            if(currentFolderNode) {
                currentFolderNode.select();
                currentFolderNode.expand();
                this.currentFolderNode = currentFolderNode;
            } else {
                // get ftb path filter
                this.filterToolbar.filterStore.each(function(filter) {
                    var field = filter.get('field');
                    if (field === 'path') {
                        filter.set('value', '');
                        filter.set('value', rowRecord.data);
                        filter.formFields.value.setValue(rowRecord.get('path'));

                        this.filterToolbar.onFiltertrigger();
                        return false;
                    }
                }, this);
            }
        }
    },

    /**
     * on remove handler
     *
     * @param {} button
     * @param {} event
     */
    onRemove: function (button, event) {
        var selectedRows = this.selectionModel.getSelections();
        for (var i = 0; i < selectedRows.length; i += 1) {
            this.store.remove(selectedRows[i]);
            var upload = Tine.Tinebase.uploadManager.getUpload(selectedRows[i].get('uploadKey'));

            if (upload) {
                upload.setPaused(true);
            }
        }
    },

    /**
     * populate grid store
     *
     * @param {} record
     */
    loadRecord: function (record) {
        if (record && record.get(this.filesProperty)) {
            var files = record.get(this.filesProperty);
            for (var i = 0; i < files.length; i += 1) {
                var file = new Ext.ux.file.Upload.file(files[i]);
                file.set('status', 'complete');
                file.set('nodeRecord', new Tine.Filemanager.Model.Node(file.data));
                this.store.add(file);
            }
        }
    },

    /**
     * upload new file and add to store
     *
     * @param {ux.BrowsePlugin} fileSelector
     * @param {} e
     */
    onFilesSelect: function (fileSelector, event) {
        var app = Tine.Tinebase.appMgr.get('Filemanager'),
            grid = this,
            targetNode = grid.currentFolderNode,
            gridStore = grid.store,
            rowIndex = false,
            targetFolderPath = grid.currentFolderNode.attributes.path,
            addToGrid = true,
            dropAllowed = false,
            nodeRecord = null;

        if(event && event.getTarget()) {
            rowIndex = grid.getView().findRowIndex(event.getTarget());
        }


        if(targetNode.attributes) {
            nodeRecord = targetNode.attributes.nodeRecord;
        }

        if(rowIndex !== false && rowIndex > -1) {
            var newTargetNode = gridStore.getAt(rowIndex);
            if(newTargetNode && newTargetNode.data.type == 'folder') {
                targetFolderPath = newTargetNode.data.path;
                addToGrid = false;
                nodeRecord = new Tine.Filemanager.Model.Node(newTargetNode.data);
            }
        }

        if(!nodeRecord.isDropFilesAllowed()) {
            Ext.MessageBox.alert(
                    i18n._('Upload Failed'),
                    app.i18n._('Putting files in this folder is not allowed!')
            ).setIcon(Ext.MessageBox.ERROR);

            return;
        }

        var files = fileSelector.getFileList();

        if(files.length > 0) {
            grid.pagingToolbar.refresh.disable();
        }

        var filePathsArray = [], uploadKeyArray = [];

        Ext.each(files, function (file) {
            var fileRecord = Tine.Filemanager.Model.Node.createFromFile(file),
                filePath = targetFolderPath + '/' + fileRecord.get('name');

            fileRecord.set('path', filePath);
            var existingRecordIdx = gridStore.find('name', fileRecord.get('name'));
            if(existingRecordIdx < 0) {
                gridStore.add(fileRecord);
            }

            var upload = new Ext.ux.file.Upload({
                fmDirector: grid,
                file: file,
                fileSelector: fileSelector,
                id: filePath
            });

            var uploadKey = Tine.Tinebase.uploadManager.queueUpload(upload);

            filePathsArray.push(filePath);
            uploadKeyArray.push(uploadKey);

        }, this);

        var params = {
                filenames: filePathsArray,
                type: "file",
                tempFileIds: [],
                forceOverwrite: false
        };
        Tine.Filemanager.fileRecordBackend.createNodes(params, uploadKeyArray, true);
    },

    /**
     * grid on load handler
     *
     * @param grid
     * @param records
     * @param options
     */
    onLoad: function(store, records, options){
        var grid = this;

        for(var i=records.length-1; i>=0; i--) {
            var record = records[i];
            if(record.get('type') == 'file' && (!record.get('size') || record.get('size') == 0)) {
                var upload = Tine.Tinebase.uploadManager.getUpload(record.get('path'));

                if(upload) {
                      if(upload.fileRecord && record.get('name') == upload.fileRecord.get('name')) {
                          grid.updateNodeRecord(record, upload.fileRecord);
                          record.afterEdit();
                    }
                }
            }
        }
    },

    /**
     * update grid nodeRecord with fileRecord data
     *
     * @param nodeRecord
     * @param fileRecord
     */
    updateNodeRecord: function(nodeRecord, fileRecord) {
        for(var field in fileRecord.fields) {
            nodeRecord.set(field, fileRecord.get(field));
        }
        nodeRecord.fileRecord = fileRecord;
    }
});
