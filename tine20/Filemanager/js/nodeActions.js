/*
 * Tine 2.0
 *
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiß <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2017 Metaways Infosystems GmbH (http://www.metaways.de)
 */
Ext.ns('Tine.Filemanager.nodeActions');

/**
 * @singleton
 */
Tine.Filemanager.nodeActionsMgr = new (Ext.extend(Tine.widgets.ActionManager, {
    actionConfigs: Tine.Filemanager.nodeActions
}))();

// /**
//  * reload
//  */
// Tine.Filemanager.nodeActions.Reload = {
//     app: 'Filemanager',
//     text: 'Reload', // _('Reload),
//     iconCls: 'x-tbar-loading',
//     handler: function() {
//         var record = this.initialConfig.selections[0];
//         // arg - does not trigger tree children reload!
//         Tine.Filemanager.fileRecordBackend.loadRecord(record);
//     }
// };

/**
 * create new folder, needs a single folder selection with addGrant
 */
Tine.Filemanager.nodeActions.CreateFolder = {
    app: 'Filemanager',
    requiredGrant: 'addGrant',
    allowMultiple: false,
    // actionType: 'add',
    text: 'Create Folder', // _('Create Folder')
    disabled: true,
    iconCls: 'action_create_folder',
    scope: this,
    handler: function() {
        var app = this.initialConfig.app,
            currentFolderNode = this.initialConfig.selections[0],
            nodeName = Tine.Filemanager.Model.Node.getContainerName();

        Ext.MessageBox.prompt(app.i18n._('New Folder'), app.i18n._('Please enter the name of the new folder:'), function(btn, text) {
            if(currentFolderNode && btn == 'ok') {
                if (! text) {
                    Ext.Msg.alert(String.format(app.i18n._('No {0} added'), nodeName), String.format(app.i18n._('You have to supply a {0} name!'), nodeName));
                    return;
                }

                var filename = currentFolderNode.get('path') + '/' + text;
                Tine.Filemanager.fileRecordBackend.createFolder(filename);
            }
        }, this);
    },
    actionUpdater: function(action, grants, records, isFilterSelect) {
        var enabled = !isFilterSelect
            && records && records.length == 1
            && records[0].get('type') == 'folder'
            && window.lodash.get(records, '[0].data.account_grants.addGrant', false);

        action.setDisabled(!enabled);
    }
};

/**
 * show native file select, upload files, create nodes
 * a single directory node with create grant has to be selected
 * for this action to be active
 */
// Tine.Filemanager.nodeActions.UploadFiles = {};

/**
 * single file or directory node with readGrant
 */
Tine.Filemanager.nodeActions.Edit = {
    app: 'Filemanager',
    requiredGrant: 'readGrant',
    allowMultiple: false,
    text: 'Edit Properties', // _('Edit Properties')
    iconCls: 'action_edit_file',
    disabled: true,
    // actionType: 'edit',
    scope: this,
    handler: function () {
        if(this.initialConfig.selections.length == 1) {
            Tine.Filemanager.NodeEditDialog.openWindow({record: this.initialConfig.selections[0]});
        }
    },
    actionUpdater: function(action, grants, records, isFilterSelect) {
        // run default updater
        Tine.widgets.ActionUpdater.prototype.defaultUpdater(action, grants, records, isFilterSelect);

        var _ = window.lodash,
            disabled = _.isFunction(action.isDisabled) ? action.isDisabled() : action.disabled;

        // if enabled check for not accessible node and disable
        if (! disabled) {
            action.setDisabled(window.lodash.reduce(records, function(disabled, record) {
                return disabled || record.isVirtual();
            }, false));
        }
    }
};

/**
 * single file or directory node with editGrant
 */
Tine.Filemanager.nodeActions.Rename = {
    app: 'Filemanager',
    requiredGrant: 'editGrant',
    allowMultiple: false,
    text: 'Rename', // _('Rename')
    iconCls: 'action_rename',
    disabled: true,
    // actionType: 'edit',
    scope: this,
    handler: function () {
        var _ = window.lodash,
            app = this.initialConfig.app,
            record = this.initialConfig.selections[0],
            nodeName = record.get('type') == 'folder' ?
                Tine.Filemanager.Model.Node.getContainerName() :
                Tine.Filemanager.Model.Node.getRecordName();

        Ext.MessageBox.show({
            title: String.format(i18n._('Rename {0}'), nodeName),
            msg: String.format(i18n._('Please enter the new name of the {0}:'), nodeName),
            buttons: Ext.MessageBox.OKCANCEL,
            value: record.get('name'),
            fn: function (btn, text) {
                if (btn == 'ok') {
                    if (!text) {
                        Ext.Msg.alert(String.format(i18n._('Not renamed {0}'), nodeName), String.format(i18n._('You have to supply a {0} name!'), nodeName));
                        return;
                    }

                    // @TODO validate filename
                    var targetPath = record.get('path').replace(new RegExp(record.get('name') +'$'), text);
                    Tine.Filemanager.fileRecordBackend.copyNodes([record], targetPath, true);

                }
            },
            scope: this,
            prompt: true,
            icon: Ext.MessageBox.QUESTION
        });
    }
};

/**
 * one or multiple nodes, all need deleteGrant
 */
Tine.Filemanager.nodeActions.Delete = {
    app: 'Filemanager',
    requiredGrant: 'deleteGrant',
    allowMultiple: true,
    text: 'Delete', // _('Delete')
    disabled: true,
    iconCls: 'action_delete',
    scope: this,
    handler: function (button, event) {
        var app = this.initialConfig.app,
            nodeName = '',
            nodes = this.initialConfig.selections;

        if (nodes && nodes.length) {
            for (var i = 0; i < nodes.length; i++) {
                var currNodeData = nodes[i].data;

                if (typeof currNodeData.name == 'object') {
                    nodeName += currNodeData.name.name + '<br />';
                }
                else {
                    nodeName += currNodeData.name + '<br />';
                }
            }
        }

        this.conflictConfirmWin = Tine.widgets.dialog.FileListDialog.openWindow({
            modal: true,
            allowCancel: false,
            height: 180,
            width: 300,
            title: app.i18n._('Do you really want to delete the following files?'),
            text: nodeName,
            scope: this,
            handler: function (button) {
                if (nodes && button == 'yes') {
                    Tine.Filemanager.fileRecordBackend.deleteItems(nodes);
                }

                for (var i = 0; i < nodes.length; i++) {
                    var node = nodes[i];

                    if (node.fileRecord) {
                        var upload = Tine.Tinebase.uploadManager.getUpload(node.fileRecord.get('uploadKey'));
                        upload.setPaused(true);
                        Tine.Tinebase.uploadManager.unregisterUpload(upload.id);
                    }

                }
            }
        });
    }
};

/**
 * one node with readGrant
 */
// Tine.Filemanager.nodeActions.Copy = {};

/**
 * one or multiple nodes with read, edit AND deleteGrant
 */
Tine.Filemanager.nodeActions.Move = {
    app: 'Filemanager',
    requiredGrant: 'editGrant',
    allowMultiple: true,
    text: 'Move', // _('Move')
    disabled: true,
    actionType: 'edit',
    scope: this,
    iconCls: 'action_move',
    handler: function() {
        var app = this.initialConfig.app,
            i18n = app.i18n,
            records = this.initialConfig.selections;

        var filePickerDialog = new Tine.Filemanager.FilePickerDialog({
            title: app.i18n._('Move Items'),
            singleSelect: true,
            constraint: 'folder'
        });

        filePickerDialog.on('selected', function(nodes) {
            var node = nodes[0];
            Tine.Filemanager.fileRecordBackend.copyNodes(records, node.path, true);
        });

        filePickerDialog.openWindow();
    },
};

/**
 * one file node with download grant
 */
Tine.Filemanager.nodeActions.Download = {
    app: 'Filemanager',
    requiredGrant: 'downloadGrant',
    allowMultiple: false,
    actionType: 'download',
    text: 'Save locally', // _('Save locally')
    iconCls: 'action_filemanager_save_all',
    disabled: true,
    scope: this,
    handler: function() {
        Tine.Filemanager.downloadFile(this.initialConfig.selections[0]);
    },
    actionUpdater: function(action, grants, records, isFilterSelect) {
        var enabled = !isFilterSelect
            && records && records.length == 1
            && records[0].get('type') != 'folder'
            && window.lodash.get(records, '[0].data.account_grants.downloadGrant', false);

        action.setDisabled(!enabled);
    }
};

/**
 * one file node with readGrant
 */
// Tine.Filemanager.nodeActions.Preview = {};

/**
 * one node with publish grant
 */
Tine.Filemanager.nodeActions.Publish = {
    app: 'Filemanager',
    allowMultiple: false,
    text: 'Publish', // _('Publish')
    disabled: true,
    iconCls: 'action_publish',
    scope: this,
    handler: function() {
        var app = this.initialConfig.app,
            i18n = app.i18n,
            selections = this.initialConfig.selections;

        if (selections.length != 1) {
            return;
        }

        var date = new Date();
        date.setDate(date.getDate() + 30);

        var record = new Tine.Filemanager.Model.DownloadLink({node_id: selections[0].id, expiry_time: date});
        Tine.Filemanager.downloadLinkRecordBackend.saveRecord(record, {
            success: function (record) {
                // TODO: add mail-button
                Ext.MessageBox.show({
                    title: selections[0].data.type == 'folder' ? app.i18n._('Folder has been published successfully') : app.i18n._('File has been published successfully'),
                    msg: String.format(app.i18n._("Url: {0}") + '<br />' + app.i18n._("Valid Until: {1}"), record.get('url'), record.get('expiry_time')),
                    minWidth: 900,
                    buttons: Ext.Msg.OK,
                    icon: Ext.MessageBox.INFO,
                });
            }, failure: Tine.Tinebase.ExceptionHandler.handleRequestException, scope: this
        });
    },
    actionUpdater: function(action, grants, records, isFilterSelect) {
        var enabled = !isFilterSelect
            && records && records.length == 1
            && window.lodash.get(records, '[0].data.account_grants.publishGrant', false);

        action.setDisabled(!enabled);
    }
};

/**
 * one or multiple file nodes currently uploaded
 */
Tine.Filemanager.nodeActions.PauseUploadAction = {};

/**
 * one or multiple file nodes currently upload paused
 */
Tine.Filemanager.nodeActions.ResumeUploadAction = {};

/**
 * one or multiple file nodes currently uploaded or upload paused
 * @TODO deletes node as well?
 */
Tine.Filemanager.nodeActions.CancelUploadAction = {};
