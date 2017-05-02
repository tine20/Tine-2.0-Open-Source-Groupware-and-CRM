/*
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2007-2014 Metaways Infosystems GmbH (http://www.metaways.de)
 */
Ext.ns('Tine.Filemanager.Model');
    
/**
 * @namespace   Tine.Filemanager.Model
 * @class       Tine.Filemanager.Model.Node
 * @extends     Tine.Tinebase.data.Record
 * Example record definition
 * 
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 */
Tine.Filemanager.Model.Node = Tine.Tinebase.data.Record.create(Tine.Tinebase.Model.Tree_NodeArray, {
    appName: 'Filemanager',
    modelName: 'Node',
    idProperty: 'id',
    titleProperty: 'name',
    // ngettext('File', 'Files', n); gettext('File');
    recordName: 'File',
    recordsName: 'Files',
    // ngettext('Folder', 'Folders', n); gettext('Folder');
    containerName: 'Folder',
    containersName: 'Folders',
    
    /**
     * checks whether creating folders is allowed
     */
    isCreateFolderAllowed: function() {
        var grants = this.get('account_grants');
        
        if(!grants && this.data.id !== 'personal' && this.data.id !== 'shared') {
            return false;
        }
        else if(!grants && (this.data.id === 'personal' 
            || (this.data.id === 'shared' && (Tine.Tinebase.common.hasRight('admin', this.appName) 
            || Tine.Tinebase.common.hasRight('manage_shared_folders', this.appName))))) {
            return true;
        } else if (!grants) {
            return false;
        }
        
        return this.get('type') == 'file' ? grants.editGrant : grants.addGrant;
    },
    
    isDropFilesAllowed: function() {
        var grants = this.get('account_grants');
        if(!grants) {
            return false;
        }
        else if(!grants.addGrant) {
            return false;
        }
        return true;
    },
    
    isDragable: function() {
        var grants = this.get('account_grants');
        
        if(!grants) {
            return false;
        }
        
        if(this.get('id') === 'personal' || this.get('id') === 'shared' || this.get('id') === 'otherUsers') {
            return false;
        }
        
        return true;
    }
});

// register grants for nodes
Tine.widgets.container.GrantsManager.register('Filemanager_Model_Node', function(container) {
    var grants = Tine.widgets.container.GrantsManager.defaultGrants();
    grants.push('download', 'publish');

    return grants;
});

/**
 * create Node from File
 * 
 * @param {File} file
 */
Tine.Filemanager.Model.Node.createFromFile = function(file) {
    return new Tine.Filemanager.Model.Node({
        name: file.name ? file.name : file.fileName,  // safari and chrome use the non std. fileX props
        size: file.size || 0,
        type: 'file',
        contenttype: file.type ? file.type : file.fileType, // missing if safari and chrome 
        revision: 0
    });
};

// NOTE: atm the activity records are stored as Tinebase_Model_Tree_Node records
Tine.widgets.grid.RendererManager.register('Tinebase', 'Tree_Node', 'revision', function(revision, metadata, record) {
    revision = parseInt(revision, 10);
    var revisionString = Tine.Tinebase.appMgr.get('Filemanager').i18n._('Revision') + " " + revision,
        availableRevisions = record.get('available_revisions');

    return Ext.isArray(availableRevisions) && availableRevisions.indexOf(String(revision)) >= 0 ?
        '<a href="#"; onclick="Tine.Filemanager.downloadFile(\'' + record.get('path')+ '\',' + revision + '); return false;">' + revisionString + '</a>' :
        revisionString;
});


/**
 * default ExampleRecord backend
 */
Tine.Filemanager.fileRecordBackend =  new Tine.Tinebase.data.RecordProxy({
    appName: 'Filemanager',
    modelName: 'Node',
    recordClass: Tine.Filemanager.Model.Node,
    
    /**
     * creating folder
     * 
     * @param name      folder name
     * @param options   additional options
     * @returns
     */
    createFolder: function(name, options) {
        
        options = options || {};
        var params = {
                application : this.appName,
                filename : name,
                type : 'folder',
                method : this.appName + ".createNode"  
        };
        
        options.params = params;
        
        options.beforeSuccess = function(response) {
            var folder = this.recordReader(response);
            folder.set('client_access_time', new Date());
            return [folder];
        };
        
        options.success = function(result){
            var app = Tine.Tinebase.appMgr.get(Tine.Filemanager.fileRecordBackend.appName);
            var grid = app.getMainScreen().getCenterPanel();
            var nodeData = Ext.util.JSON.decode(result);
            var newNode = app.getMainScreen().getWestPanel().getContainerTreePanel().createTreeNode(nodeData, parentNode);
            
            var parentNode = grid.currentFolderNode;
            if(parentNode) {
                parentNode.appendChild(newNode);
            }
            grid.getStore().reload();
        };
        
        return this.doXHTTPRequest(options);
    },
    
    /**
     * is automatically called in generic GridPanel
     */
    saveRecord : function(record, request) {
        if(record.hasOwnProperty('fileRecord')) {
            return;
        } else {
            Tine.Tinebase.data.RecordProxy.prototype.saveRecord.call(this, record, request);
        }
    },

    /**
     * deleting file or folder
     * 
     * @param items     files/folders to delete
     * @param options   additional options
     * @returns
     */
    deleteItems: function(items, options) {
        options = options || {};
        
        var filenames = new Array();
        var nodeCount = items.length;
        for(var i=0; i<nodeCount; i++) {
            filenames.push(items[i].data.path );
        }
        
        var params = {
            application: this.appName,
            filenames: filenames,
            method: this.appName + ".deleteNodes",
            timeout: 300000 // 5 minutes
        };
        
        options.params = params;
        
        options.beforeSuccess = function(response) {
            var folder = this.recordReader(response);
            folder.set('client_access_time', new Date());
            return [folder];
        };
        
        options.success = (function(result){
            var app = Tine.Tinebase.appMgr.get(Tine.Filemanager.fileRecordBackend.appName),
                grid = app.getMainScreen().getCenterPanel(),
                treePanel = app.getMainScreen().getWestPanel().getContainerTreePanel(),
                nodeData = this.items;
            
            for(var i=0; i<nodeData.length; i++) {
                var treeNode = treePanel.getNodeById(nodeData[i].id);
                if(treeNode) {
                    treeNode.parentNode.removeChild(treeNode);
                }
            }
            
            grid.getStore().remove(nodeData);
            grid.selectionModel.deselectRange(0, grid.getStore().getCount());
            grid.pagingToolbar.refresh.enable();
            
        }).createDelegate({items: items});
        
        return this.doXHTTPRequest(options);
    },
    
    /**
     * copy/move folder/files to a folder
     * 
     * @param items files/folders to copy
     * @param targetPath
     * @param move
     */
    
    copyNodes : function(items, target, move, params) {
        
        var containsFolder = false,
            message = '',
            app = Tine.Tinebase.appMgr.get(Tine.Filemanager.fileRecordBackend.appName);
        
        
        if(!params) {
        
            if(!target || !items || items.length < 1) {
                return false;
            }
            
            var sourceFilenames = new Array(),
            destinationFilenames = new Array(),
            forceOverwrite = false,
            treeIsTarget = false,
            treeIsSource = false,
            targetPath = target;
            
            if(target.data) {
                targetPath = target.data.path;
            }
            else if (target.attributes) {
                targetPath = target.attributes.path;
                treeIsTarget = true;
            }
            
            for(var i=0; i<items.length; i++) {
                
                var item = items[i];
                var itemData = item.data;
                if(!itemData) {
                    itemData = item.attributes;
                    treeIsSource = true;
                }
                sourceFilenames.push(itemData.path);
                
                var itemName = itemData.name;
                if(typeof itemName == 'object') {
                    itemName = itemName.name;
                }
                
                destinationFilenames.push(targetPath + '/' + itemName);
                if(itemData.type == 'folder') {
                    containsFolder = true;
                }
            }
            
            var method = "Filemanager.copyNodes",
                message = app.i18n._('Copying data .. {0}');
            if(move) {
                method = "Filemanager.moveNodes";
                message = app.i18n._('Moving data .. {0}');
            }
            
            params = {
                    application: this.appName,
                    sourceFilenames: sourceFilenames,
                    destinationFilenames: destinationFilenames,
                    forceOverwrite: forceOverwrite,
                    method: method
            };
            
        } else {
            message = app.i18n._('Copying data .. {0}');
            if(params.method == 'Filemanager.moveNodes') {
                message = app.i18n._('Moving data .. {0}');
            }
        }
        
        this.loadMask = new Ext.LoadMask(app.getMainScreen().getCenterPanel().getEl(), {msg: String.format(i18n._('Please wait')) + '. ' + String.format(message, '' )});
        app.getMainScreen().getWestPanel().setDisabled(true);
        app.getMainScreen().getNorthPanel().setDisabled(true);
        this.loadMask.show();
        
        Ext.Ajax.request({
            params: params,
            timeout: 300000, // 5 minutes
            scope: this,
            success: function(result, request){
                
                this.loadMask.hide();
                app.getMainScreen().getWestPanel().setDisabled(false);
                app.getMainScreen().getNorthPanel().setDisabled(false);
                
                var nodeData = Ext.util.JSON.decode(result.responseText),
                    treePanel = app.getMainScreen().getWestPanel().getContainerTreePanel(),
                    grid = app.getMainScreen().getCenterPanel();
             
                // Tree refresh
                if(treeIsTarget) {
                    
                    for(var i=0; i<items.length; i++) {
                        
                        var nodeToCopy = items[i];
                        
                        if(nodeToCopy.data && nodeToCopy.data.type !== 'folder') {
                            continue;
                        }
                        
                        if(move) {
                            var copiedNode = treePanel.cloneTreeNode(nodeToCopy, target),
                                nodeToCopyId = nodeToCopy.id,
                                removeNode = treePanel.getNodeById(nodeToCopyId);
                            
                            if(removeNode && removeNode.parentNode) {
                                removeNode.parentNode.removeChild(removeNode);
                            }
                            
                            target.appendChild(copiedNode);
                            copiedNode.setId(nodeData[i].id);
                        } 
                        else {
                            var copiedNode = treePanel.cloneTreeNode(nodeToCopy, target);
                            target.appendChild(copiedNode);
                            copiedNode.setId(nodeData[i].id);
                            
                        }
                    }
                }
               
                // Grid refresh
                grid.getStore().reload();
            },
            failure: function(response, request) {
                var nodeData = Ext.util.JSON.decode(response.responseText),
                    request = Ext.util.JSON.decode(request.jsonData);
                
                this.loadMask.hide();
                app.getMainScreen().getWestPanel().setDisabled(false);
                app.getMainScreen().getNorthPanel().setDisabled(false);
                
                Tine.Filemanager.fileRecordBackend.handleRequestException(nodeData.data, request);
            }
        });
    },
    
    /**
     * upload file 
     * 
     * @param {} params Request parameters
     * @param String uploadKey
     * @param Boolean addToGridStore 
     */
    createNode: function(params, uploadKey, addToGridStore) {
        var app = Tine.Tinebase.appMgr.get('Filemanager'),
            me = this,
            grid = app.getMainScreen().getCenterPanel(),
            gridStore = grid.getStore();
        
        params.application = 'Filemanager';
        params.method = 'Filemanager.createNode';
        params.uploadKey = uploadKey;
        params.addToGridStore = addToGridStore;
        
        var onSuccess = (function(result, request){

            var nodeData = Ext.util.JSON.decode(response.responseText),
                fileRecord = Tine.Tinebase.uploadManager.upload(this.uploadKey);

            fileRecord.on('update', me.onUploadUpdate.createDelegate(me));

            if(addToGridStore) {
                var recordToRemove = gridStore.query('name', fileRecord.get('name'));
                if(recordToRemove.items[0]) {
                    gridStore.remove(recordToRemove.items[0]);
                }
                
                fileRecord = Tine.Filemanager.fileRecordBackend.updateNodeRecord(nodeData[i], fileRecord);
                var nodeRecord = new Tine.Filemanager.Model.Node(nodeData[i]);
                
                nodeRecord.fileRecord = fileRecord;
                gridStore.add(nodeRecord);
                
            }
        }).createDelegate({uploadKey: uploadKey, addToGridStore: addToGridStore});
        
        var onFailure = (function(response, request) {
            
            var nodeData = Ext.util.JSON.decode(response.responseText),
                request = Ext.util.JSON.decode(request.jsonData);
            
            nodeData.data.uploadKey = this.uploadKey;
            nodeData.data.addToGridStore = this.addToGridStore;
            Tine.Filemanager.fileRecordBackend.handleRequestException(nodeData.data, request);
            
        }).createDelegate({uploadKey: uploadKey, addToGridStore: addToGridStore});
        
        Ext.Ajax.request({
            params: params,
            timeout: 300000, // 5 minutes
            scope: this,
            success: onSuccess || Ext.emptyFn,
            failure: onFailure || Ext.emptyFn
        });
    },
    
    /**
     * upload files
     * 
     * @param {} params Request parameters
     * @param [] uploadKeyArray
     * @param Boolean addToGridStore
     */
    createNodes: function (params, uploadKeyArray, addToGridStore) {
        var app = Tine.Tinebase.appMgr.get('Filemanager'),
            grid = app.getMainScreen().getCenterPanel(),
            me = this,
            gridStore = grid.store;

        params.application = 'Filemanager';
        params.method = 'Filemanager.createNodes';
        params.uploadKeyArray = uploadKeyArray;
        params.addToGridStore = addToGridStore;


        var onSuccess = (function (response, request) {

            var nodeData = Ext.util.JSON.decode(response.responseText);

            for (var i = 0; i < this.uploadKeyArray.length; i++) {
                var fileRecord = Tine.Tinebase.uploadManager.upload(this.uploadKeyArray[i]);

                Tine.Tinebase.uploadManager.getUpload(this.uploadKeyArray[i]).on('update', me.onUploadUpdate.createDelegate(me));

                if (addToGridStore) {
                    fileRecord = Tine.Filemanager.fileRecordBackend.updateNodeRecord(nodeData[i], fileRecord);
                    var nodeRecord = new Tine.Filemanager.Model.Node(nodeData[i]);

                    nodeRecord.fileRecord = fileRecord;

                    var existingRecordIdx = gridStore.find('name', fileRecord.get('name'));
                    if (existingRecordIdx > -1) {
                        gridStore.removeAt(existingRecordIdx);
                        gridStore.insert(existingRecordIdx, nodeRecord);
                    } else {
                        gridStore.add(nodeRecord);
                    }
                }
            }

        }).createDelegate({uploadKeyArray: uploadKeyArray, addToGridStore: addToGridStore});

        var onFailure = (function (response, request) {

            var nodeData = Ext.util.JSON.decode(response.responseText),
                request = Ext.util.JSON.decode(request.jsonData);

            nodeData.data.uploadKeyArray = this.uploadKeyArray;
            nodeData.data.addToGridStore = this.addToGridStore;
            Tine.Filemanager.fileRecordBackend.handleRequestException(nodeData.data, request);

        }).createDelegate({uploadKeyArray: uploadKeyArray, addToGridStore: addToGridStore});

        Ext.Ajax.request({
            params: params,
            timeout: 300000, // 5 minutes
            scope: this,
            success: onSuccess || Ext.emptyFn,
            failure: onFailure || Ext.emptyFn
        });
    },

    /**
     * Is fired if there is any change for an uploading file
     *
     * Is bind to the record proxy scope
     */
    onUploadUpdate: function (change, upload, fileRecord) {
        var app = Tine.Tinebase.appMgr.get('Filemanager'),
            grid = app.getMainScreen().getCenterPanel(),
            rowsToUpdate = grid.store.query('name', fileRecord.get('name'));

        if (change === 'uploadstart') {
            Tine.Tinebase.uploadManager.onUploadStart();
        } else if (change === 'uploadfailure') {
            grid.onUploadFail();
        }

        if (rowsToUpdate.get(0)) {
            if (change === 'uploadcomplete') {
                this.onUploadComplete.call(grid, this, upload, fileRecord);
            } else if (change === 'uploadfinished') {
                rowsToUpdate.get(0).set('size', fileRecord.get('size'));
                rowsToUpdate.get(0).set('contenttype', fileRecord.get('contenttype'));
            }

            rowsToUpdate.get(0).afterEdit();
            rowsToUpdate.get(0).commit(false);
        }
    },

    /**
     * updating fileRecord after creating node
     *
     * @param response
     * @param request
     * @param upload
     */
    onNodeCreated: function (response, request, upload) {
        var record = Ext.util.JSON.decode(response.responseText);

        var fileRecord = upload.fileRecord;
        fileRecord.beginEdit();
        fileRecord.set('contenttype', record.contenttype);
        fileRecord.set('created_by', Tine.Tinebase.registry.get('currentAccount'));
        fileRecord.set('creation_time', record.creation_time);
        fileRecord.set('revision', record.revision);
        fileRecord.set('last_modified_by', record.last_modified_by);
        fileRecord.set('last_modified_time', record.last_modified_time);
        fileRecord.set('name', record.name);
        fileRecord.set('path', record.path);
        fileRecord.set('status', 'complete');
        fileRecord.set('progress', 100);
        fileRecord.commit(false);

        upload.fireEvent('update', 'uploadfinished', upload, fileRecord);

        var allRecordsComplete = true;
        var storeItems = this.getStore().getRange();
        for (var i = 0; i < storeItems.length; i++) {
            if (storeItems[i].get('status') && storeItems[i].get('status') !== 'complete') {
                allRecordsComplete = false;
                break;
            }
        }

        if (allRecordsComplete) {
            this.pagingToolbar.refresh.enable();
        }
    },

    /**
     * copies uploaded temporary file to target location
     *
     * @param proxy  {Tine.Filemanager.fileRecordBackend}
     * @param upload  {Ext.ux.file.Upload}
     * @param file  {Ext.ux.file.Upload.file}
     */
    onUploadComplete: function(proxy, upload, file) {
        Ext.Ajax.request({
            timeout: 10*60*1000, // Overriding Ajax timeout - important!
            params: {
                method: 'Filemanager.createNode',
                filename: upload.id,
                type: 'file',
                tempFileId: file.get('id'),
                forceOverwrite: true
            },
            success: proxy.onNodeCreated.createDelegate(this, [upload], true),
            failure: proxy.onNodeCreated.createDelegate(this, [upload], true)
        });

    },

    /**
     * exception handler for this proxy
     * 
     * @param {Tine.Exception} exception
     */
    handleRequestException: function(exception, request) {
        Tine.Filemanager.handleRequestException(exception, request);
    },
    
    /**
     * updates given record with nodeData from from response
     */
    updateNodeRecord : function(nodeData, nodeRecord) {
        
        for(var field in nodeData) {
            nodeRecord.set(field, nodeData[field]);
        }
        
        return nodeRecord;
    }
});


/**
 * get filtermodel of Node records
 * 
 * @namespace Tine.Filemanager.Model
 * @static
 * @return {Object} filterModel definition
 */ 
Tine.Filemanager.Model.Node.getFilterModel = function() {
    var app = Tine.Tinebase.appMgr.get('Filemanager');
       
    return [
        {label : i18n._('Quick Search'), field : 'query', operators : [ 'contains' ]},
//        {label: app.i18n._('Type'), field: 'type'}, // -> should be a combo
        {label: app.i18n._('Contenttype'), field: 'contenttype'},
        {label: app.i18n._('Creation Time'), field: 'creation_time', valueType: 'date'},
        {label: app.i18n._('Description'), field: 'description'},
        {filtertype : 'tine.filemanager.pathfiltermodel', app : app},
        {filtertype : 'tinebase.tag', app : app} 
    ].concat(Tine.Tinebase.configManager.get('filesystem.index_content', 'Tinebase') ? [
        {label : i18n._('File Contents'), field : 'content', operators : [ 'contains' ]}
    ] : []);
};

/**
 * @namespace   Tine.Filemanager.Model
 * @class       Tine.Filemanager.Model.DownloadLink
 * @extends     Tine.Tinebase.data.Record
 * Example record definition
 */
Tine.Filemanager.Model.DownloadLink = Tine.Tinebase.data.Record.create(Tine.Tinebase.Model.modlogFields.concat([
    { name: 'id' },
    { name: 'node_id' },
    { name: 'url' },
    { name: 'expiry_time', type: 'datetime' },
    { name: 'access_count' },
    { name: 'password' }
]), {
    appName: 'Filemanager',
    modelName: 'DownloadLink',
    idProperty: 'id',
    titleProperty: 'url',
    // ngettext('Download Link', 'Download Links', n); gettext('Download Link');
    recordName: 'Download Link',
    recordsName: 'Download Links'
});

/**
 * download link backend
 */
Tine.Filemanager.downloadLinkRecordBackend = new Tine.Tinebase.data.RecordProxy({
    appName: 'Filemanager',
    modelName: 'DownloadLink',
    recordClass: Tine.Filemanager.Model.DownloadLink
});
