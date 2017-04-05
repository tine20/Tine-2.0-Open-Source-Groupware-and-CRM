/*
 * Tine 2.0
 *
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2007-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 */
Ext.ns('Tine.widgets', 'Tine.widgets.container');

/**
 * @namespace Tine.widgets.container
 * @class Tine.widgets.container.SelectionDialog
 * @extends Ext.Component
 *
 * This widget shows a modal container selection dialog
 */
Tine.widgets.container.SelectionDialog = Ext.extend(Ext.Component, {
    /**
     * @cfg {Boolean} allowNodeSelect
     */
    allowNodeSelect: false,
    /**
     * @cfg {Boolean} allowToplevelNodeSelect
     */
    allowToplevelNodeSelect: true,
    /**
     * @cfg {Tine.data.Record} recordClass
     */
    recordClass: false,
    /**
     * @cfg {?} defaultContainer
     */
    defaultContainer: null,
    /**
     * @cfg {string}
     * title of dialog
     */
    title: null,
    /**
     * @cfg {Number}
     */
    windowHeight: 400,
    /**
     * @property {Ext.Window}
     */
    win: null,
    /**
     * @property {Ext.tree.TreePanel}
     */
    tree: null,
    /**
     * @cfg {Array} requiredGrants
     * grants which are required to select leaf node(s)
     */
    requiredGrants: ['readGrant'],

    treePanelClass: null,

    allowNonLeafSelection: false,

    /**
     * @private
     */
    initComponent: function(){
        Tine.widgets.container.SelectionDialog.superclass.initComponent.call(this);

        this.title = this.title ? this.title : String.format(i18n._('please select a {0}'), this.recordClass.getContainerName());

        this.cancelAction = new Ext.Action({
            text: i18n._('Cancel'),
            iconCls: 'action_cancel',
            minWidth: 70,
            handler: this.onCancel,
            scope: this
        });

        this.okAction = new Ext.Action({
            disabled: true,
            text: i18n._('Ok'),
            iconCls: 'action_saveAndClose',
            minWidth: 70,
            handler: this.onOk,
            scope: this
        });

        // adjust window height
        if (Ext.getBody().getHeight(true) * 0.7 < this.windowHeight) {
            this.windowHeight = Ext.getBody().getHeight(true) * 0.7;
        }

        this.initTree();

        this.win = Tine.WindowFactory.getWindow({
            title: this.title,
            closeAction: 'close',
            modal: true,
            width: 375,
            height: this.windowHeight,
            minWidth: 375,
            minHeight: this.windowHeight,
            layout: 'fit',
            plain: true,
            bodyStyle: 'padding:5px;',
            buttonAlign: 'right',

            buttons: [
                this.cancelAction,
                this.okAction
            ],

            items: [ this.tree ]
        });
    },

    initTree: function() {
        this.treePanelClass = this.treePanelClass || Tine.widgets.container.TreePanel;

        this.tree = new this.treePanelClass({
            recordClass: this.recordClass,
            allowMultiSelection: false,
            defaultContainer: this.defaultContainer || this.TriggerField ? this.TriggerField.defaultContainer : null,
            requiredGrants: this.requiredGrants,
            // TODO find a better way for this. currently the Filemanager container tree subfolder creation works differently...
            // this disables context menu for *Node' containers...
            hasContextMenu: this.recordClass ? (this.recordClass.getMeta('modelName') != 'Node') : true
        });

        this.tree.on('click', this.onTreeNodeClick, this);
        this.tree.on('dblclick', this.onTreeNoceDblClick, this);
    },

    /**
     * @private
     */
    onTreeNodeClick: function(node) {
        this.okAction.setDisabled(
            ! (node.leaf
                || (this.allowNodeSelect
                        // TODO create isTopLevelNode() in Tine.Tinebase.container
                    && (this.allowToplevelNodeSelect
                        || node.attributes
                        && (
                            (node.attributes.path.match(/^\/personal/) && node.attributes.path.split("/").length > 3)
                            || (node.attributes.path.match(/^\/other/) && node.attributes.path.split("/").length > 3)
                            || (node.attributes.path.match(/^\/shared/) && node.attributes.path.split("/").length > 2)
                        )
                    )
                )
            )
        );

        if (! node.leaf ) {
            node.expand();
        }
    },

    /**
     * @private
     */
    onTreeNoceDblClick: function(node) {
        if (! this.okAction.isDisabled()) {
            this.onOk();
        }
    },

    /**
     * @private
     */
    onCancel: function() {
        this.onClose();
    },

    /**
     * @private
     */
    onClose: function() {
        this.win.close();
    },

    /**
     * @private
     */
    onOk: function() {
        var  node = this.tree.getSelectionModel().getSelectedNode();
        if (node) {
            if (this.fireEvent('select', this, node) !== false) {
                this.onClose();
            }
        }
    }
});
