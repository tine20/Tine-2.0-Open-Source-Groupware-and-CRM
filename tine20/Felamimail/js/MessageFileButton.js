/*
 * Tine 2.0
 *
 * @package     Felamimail
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiß <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2018 Metaways Infosystems GmbH (http://www.metaways.de)
 */

Tine.Felamimail.MessageFileButton = Ext.extend(Ext.SplitButton, {

    /**
     * @cfg {String} fileInstant|selectOnly
     */
    mode: 'fileInstant',

    /**
     * @cfg {Tinebase.data.Record} record optional instead of selectionModel (implicit fom grid)
     */
    record: null,

    /**
     * @property {Boolean} isManualSelection (of file locations)
     */
    isManualSelection: false,

    requiredGrant: 'readGrant',
    allowMultiple: true,
    iconCls: 'action_file',
    disabled: true,
    suggestionsLoaded: false,

    initComponent: function() {
        var _ = window.lodash,
            me = this;

        this.app = Tine.Tinebase.appMgr.get('Felamimail');
        this.i18n = this.app.i18n;

        this.text = this.i18n._('Save Message as');

        this.menu = [];

        this.selectionHandler = this.mode === 'fileInstant' ?
            this.fileMessage.createDelegate(this) :
            this.selectLocation.createDelegate(this);

        if (this.mode !== 'fileInstant') {
            this.disabled = false;
            this.enableToggle = true;
            this.pressed = Tine.Felamimail.registry.get('preferences').get('autoAttachNote') === "1";

            // check suggestions (file_location) for reply/forward
            if (this.composeDialog) {
                this.composeDialog.on('load', this.onMessageLoad, this);
            }

            me.on('toggle', me.onToggle, me);
        }

        // grid selection interface for DisplayPanel/Dialog
        if (! this.initialConfig.selectionModel && this.initialConfig.record) {
            _.assign(this.initialConfig, {
                selections: [this.initialConfig.record],
                selectionModel: {
                    getSelectionFilter: function() {
                        return [{field: 'id', operator: 'equals', value: me.initialConfig.record.id }];
                    },
                    getCount: function() {
                        return 1
                    }
                }
            });
        }
        this.supr().initComponent.call(this);
    },

    handler: function() {
        if (this.mode != 'fileInstant') {
            // just toggle
            return;
        }

        this.showFileMenu();
    },

    arrowHandler: function() {
        if (this.mode == 'fileInstant') {
            return this.showFileMenu();
        }

        this.syncRecipents();
    },

    onToggle: function(btn, pressed) {
        var _ = window.lodash,
            me = this;

        if (pressed) {
            _.each(_.filter(this.menu.items.items, {isRecipientItem: true}), function(item) {
                item.suspendEvents();
                item.setChecked(true);
                item.resumeEvents();
            });
        } else {
            _.each(_.filter(this.menu.items.items, {checked: true}), function(item) {
                item.suspendEvents();
                item.setChecked(false);
                item.resumeEvents();
            });
        }

        var selection = me.getSelected();
        me.fireEvent('selectionchange', me, selection);
    },

    showFileMenu: function () {
        var _ = window.lodash,
            selection = _.map(this.initialConfig.selections, 'data');

        if (! this.suggestionsLoaded || this.mode === 'fileInstant') {
            this.loadSuggestions(selection[0])
                .then(this.showMenu.createDelegate(this));
        } else {
            this.showMenu();
        }
    },

    /**
     * message is loaded in compose dialog
     *
     * @param dlg
     * @param message
     * @param ticketFn
     */
    onMessageLoad: function(dlg, message, ticketFn) {
        var _ = window.lodash,
            me = this;

        if (message.get('original_id')) {
            me.loadSuggestions(message.data).then(function () {
                // auto file if original_message (from forwared/reply) was filed
                if (_.find(_.map(me.menu.items, 'suggestion'), { type : 'file_location' })) {
                    // @TODO: select this suggestion!
                    var selection = me.getSelected();
                    me.suspendEvents();
                    me.toggle(selection.length);
                    me.resumeEvents();
                    me.fireEvent('selectionchange', me, selection);
                }
            }).catch(function(error) {
                Tine.log.notice('No file suggestions available for this message');
                Tine.log.notice(error);
                me.addStaticMenuItems();
            })
        } else {
            me.addStaticMenuItems();
        }

        me.composeDialog.recipientGrid.store.on('add', me.syncRecipents, me);
        me.composeDialog.recipientGrid.store.on('update', me.syncRecipents, me);
    },

    syncRecipents: function() {
        var _ = window.lodash,
            me = this,
            emailsInRecipientGrid = [];

        _.each(me.composeDialog.recipientGrid.store.data.items, function(recipient) {
            var full = recipient.get('address'),
                parsed = addressparser.parse(String(full).replace(/,/g, '\\\\,')),
                email = parsed.length ? parsed[0].address : '';

            if (email) {
                emailsInRecipientGrid.push(email);
                var fileTarget = {
                    record_title: full,
                    model: Tine.Addressbook.Model.EmailAddress,
                    data: {
                        email: email
                    },
                };

                if (! me.menu.getComponent(email)) {
                    var checked = me.pressed && !me.isManualSelection;
                    me.menu.insert(0, {
                        itemId: email,
                        isRecipientItem: true,
                        xtype: 'menucheckitem',
                        checked: checked,
                        fileTarget: fileTarget,
                        // iconCls: fileTarget.model.getIconCls(),
                        text: Ext.util.Format.htmlEncode(fileTarget.record_title),
                        checkHandler: function (item) {
                            var selection = me.getSelected();
                            me.suspendEvents();
                            me.toggle(selection.length);
                            me.resumeEvents();
                            me.fireEvent('selectionchange', me, selection);
                        }
                    });

                    if (checked) {
                        var selection = me.getSelected();
                        me.suspendEvents();
                        me.toggle(selection.length);
                        me.resumeEvents();
                        me.fireEvent('selectionchange', me, selection);
                    }
                }
            }
        });

        // remove all items no longer in recipient grid
        _.each(me.menu.items.items, function(item) {
            // check if in grid
            if (_.get(item, 'itemId') && emailsInRecipientGrid.indexOf(item.itemId) === -1) {
                // item no longer in grid
                me.menu.remove(item);
            }
        });
    },



    loadSuggestions: function(message) {
        var _ = window.lodash,
            me = this,
            suggestionIds = [];

        me.setIconClass('x-btn-wait');
        me.hideMenu();
        me.menu.removeAll();

        return Tine.Felamimail.getFileSuggestions(message).then(function(suggestions) {
            //sort by suggestion.type so file_location record survives deduplication
            _.each(_.sortBy(suggestions, 'type'), function (suggestion) {
                var model, record, id, suggestionId, fileTarget;

                // file_location means message reference is already filed (global registry)
                if (suggestion.type == 'file_location') {
                    id = suggestion.record.record_id;
                    fileTarget = {
                        record_title: suggestion.record.record_title,
                        model: Tine.Tinebase.data.RecordMgr.get(suggestion.record.model),
                        data: id
                    };


                } else {
                    model = Tine.Tinebase.data.RecordMgr.get(suggestion.model);
                    record = Tine.Tinebase.data.Record.setFromJson(suggestion.record, model);
                    id = record.getId();
                    fileTarget = {
                        record_title: record.getTitle(),
                        model: model,
                        data: id
                    };

                }
                suggestionId = fileTarget.model.getPhpClassName() + '-' + id;

                if (suggestionIds.indexOf(suggestionId) < 0) {
                    me.menu.addItem({
                        itemId: suggestionId,
                        isSuggestedItem: true,
                        suggestion: suggestion,
                        fileTarget: fileTarget,
                        iconCls: fileTarget.model.getIconCls(),
                        text: Ext.util.Format.htmlEncode(fileTarget.record_title),
                        handler: me.selectionHandler
                    });
                    suggestionIds.push(suggestionId);
                }
            });

            me.addStaticMenuItems();

            me.addDownloadMenuItem();

            me.suggestionsLoaded = true;
            me.setIconClass('action_file');
        });
    },

    addStaticMenuItems: function() {
        var _ = window.lodash,
            me = this;

        me.menu.addItem('-');
        me.menu.addItem({
            text: me.app.i18n._('File (in Filemanager) ...'),
            hidden: ! Tine.Tinebase.common.hasRight('run', 'Filemanager'),
            handler: me.selectFilemanagerFolder.createDelegate(me)
        });
        me.menu.addItem({
            text: me.app.i18n._('Attachment (of Record)'),
            menu:_.reduce(Tine.Tinebase.data.RecordMgr.items, function(menu, model) {
                if (model.hasField('attachments') && model.getMeta('appName') !== 'Felamimail') {
                    menu.push({
                        text: model.getRecordName() + ' ...',
                        iconCls: model.getIconCls(),
                        handler: me.selectAttachRecord.createDelegate(me, [model], true)
                    });
                }
                return menu;
            }, [])
        });
    },

    addDownloadMenuItem: function() {
        if (! _.isFunction(_.get(this, 'initialConfig.selectionModel.getSelectionFilter'))) return;

        var me = this,
            messageFilter = this.initialConfig.selectionModel.getSelectionFilter(),
            messageIds = messageFilter.length === 1 && messageFilter[0].field === 'id' ?
                messageFilter[0].value : null,
            messageCount = this.initialConfig.selectionModel.getCount();

        if (messageCount === 1 && messageIds) {
            me.menu.addItem('-');
            me.menu.addItem({
                text: me.app.i18n._('Download'),
                iconCls: 'action_download',
                handler: me.onMessageDownload.createDelegate(me, [messageIds])
            });
        }
    },

    onMessageDownload: function(messageId) {
        var downloader = new Ext.ux.file.Download({
            params: {
                method: 'Felamimail.downloadMessage',
                requestType: 'HTTP',
                messageId: messageId
            }
        }).start();
    },

    /**
     * directly file a single message
     *
     * @param item
     * @param e
     */
    fileMessage: function(item, e) {
        var me = this,
            messageFilter = this.initialConfig.selectionModel.getSelectionFilter(),
            messageCount = this.initialConfig.selectionModel.getCount(),
            locations = [me.itemToLocation(item)];

        this.setIconClass('x-btn-wait');
        Tine.Felamimail.fileMessages(messageFilter, locations)
            .then(function() {
                var msg = me.app.formatMessage('{messageCount, plural, one {Message was saved} other {# messages where saved}}',
                    {messageCount: messageCount });
                Ext.ux.MessageBox.msg(me.app.formatMessage('Success'), msg);
            })
            .catch(function(error) {
                Ext.Msg.show({
                    title: me.app.formatMessage('Error'),
                    msg: error.message,
                    buttons: Ext.MessageBox.OK,
                    icon: Ext.MessageBox.ERROR
                });
            })
            .then(function() {
                me.setIconClass('action_file');

                window.postal.publish({
                    channel: "recordchange",
                    topic: 'Felamimail.Message.massupdate',
                    data: {}
                });
            });
    },

    /**
     * returns currently selected locations
     */
    getSelected: function() {
        var _ = window.lodash,
        me = this;

        return _.reduce(this.menu.items.items, function(selected, item) {
            if (item.checked) {
                selected.push(me.itemToLocation(item));
            }
            return selected;
        }, []);
    },

    /**
     * converts (internal) item representation to location
     * @param item
     * @return {{type: string, model: String, record_id: data|{email}|*}}
     */
    itemToLocation:function(item) {
        return {
            type: item.fileTarget.model.getMeta('appName') === 'Filemanager' ? 'node' : 'attachment',
            model: item.fileTarget.model.getPhpClassName(),
            record_id: item.fileTarget.data,
            record_title: item.fileTarget.record_title
        };
    },

    selectLocation: function(item, e) {
        var me = this,
            selection;

        item.setVisible(!item.isSuggestedItem);
        item.selectItem = this.menu.insert(Math.max(0, this.menu.items.indexOf(item)), {
            text: item.fileTarget ? Ext.util.Format.htmlEncode(item.fileTarget.record_title) : item.text,
            checked: true,
            instantItem: item,
            fileTarget: item.fileTarget,
            checkHandler: function(item) {
                var selection = me.getSelected();

                item.setVisible(!item.instantItem.isSuggestedItem);
                item.instantItem.show();

                me.suspendEvents();
                me.toggle(selection.length);
                me.resumeEvents();
                me.fireEvent('selectionchange', me, selection);
            }
        });

        this.isManualSelection = true;
        selection = this.getSelected();
        me.suspendEvents();
        this.toggle(selection.length);
        me.resumeEvents();
        this.fireEvent('selectionchange', this, selection);
    },

    selectFilemanagerFolder: function(item, e) {
        var filePickerDialog = new Tine.Filemanager.FilePickerDialog({
            constraint: 'folder',
            singleSelect: true,
            requiredGrants: ['addGrant']
        });

        filePickerDialog.on('selected', this.onFilemanagerNodesSelected.createDelegate(this, [item, e], 0));
        filePickerDialog.openWindow();
    },

    onFilemanagerNodesSelected: function(item, e, nodes) {
        var _ = window.lodash,
            nodeData = _.get(nodes[0], 'nodeRecord', nodes[0]),
            fakeItem = new Ext.menu.Item();

        nodeData = _.get(nodeData, 'data', nodeData);

        fakeItem.fileTarget = {
            record_title: nodeData.name,
            model: Tine.Filemanager.Model.Node,
            data: nodeData,
        };
        this.selectionHandler(fakeItem, e)
    },

    selectAttachRecord: function(item, e, model) {
        var pickerDialog = Tine.WindowFactory.getWindow({
            layout: 'fit',
            width: 250,
            height: 100,
            padding: '5px',
            modal: true,
            title: this.app.i18n._('Save Messages as Attachment'),
            items: new Tine.Tinebase.dialog.Dialog({
                listeners: {
                    scope: this,
                    apply: function(fileTarget) {
                        item.fileTarget = fileTarget;
                        this.selectionHandler(item, e);
                    }
                },
                getEventData: function(eventName) {
                    if (eventName === 'apply') {
                        var attachRecord = this.getForm().findField('attachRecord').selectedRecord;
                        return {
                            record_title: attachRecord.getTitle(),
                            model: model,
                            data: attachRecord.data,
                        };
                    }
                },
                items: Tine.widgets.form.RecordPickerManager.get(model.getMeta('appName'), model.getMeta('modelName'), {
                    fieldLabel: model.getRecordName(),
                    name: 'attachRecord'
                })
            })
        });
    }
});

Tine.Felamimail.MessageFileButton.getFileLocationText = function(locations, glue='') {
    var _ = window.lodash,
        formatMessage = Tine.Tinebase.appMgr.get('Felamimail').formatMessage;

    return _.reduce(locations, function(text, location) {
        var model = _.isString(location.model) ? Tine.Tinebase.data.RecordMgr.get(location.model) : location.model,
            iconCls = model ? model.getIconCls() : '',
            icon = iconCls ? '<span class="felamimail-location-icon ' + iconCls +'"></span>' : '',
            span = model ? '<span class="felamimail-location" ' +
                'onclick="Tine.Felamimail.MessageFileButton.locationClickHandler(\'' + model.getPhpClassName() +
                "','" + location.record_id + '\')">' + icon + '<span class="felamimail-location-text">'
                + Ext.util.Format.htmlEncode(location.record_title) + '</span></span>' : '';

        return text.concat(span);
    }, []).join(glue);
};

Tine.Felamimail.MessageFileButton.locationClickHandler = function (recordClassName, recordId) {
    let recordClass = Tine.Tinebase.data.RecordMgr.get(recordClassName);
    let recordData = {};
    let editDialogClass = Tine.widgets.dialog.EditDialog.getConstructor(recordClass);
    recordData[recordClass.getMeta('idProperty')] = recordId;

    editDialogClass.openWindow({
        record: Tine.Tinebase.data.Record.setFromJson(recordData, recordClass),
        recordId: recordId
    });
};