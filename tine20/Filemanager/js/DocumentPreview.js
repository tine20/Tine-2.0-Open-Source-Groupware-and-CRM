/**
 * Tine 2.0
 *
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2017-2018 Metaways Infosystems GmbH (http://www.metaways.de)
 */

Ext.ns('Tine.Filemanager');

Tine.Filemanager.DocumentPreview = Ext.extend(Ext.Panel, {
    /**
     * Node record to preview
     */
    record: null,

    /**
     * filemanager
     */
    app: null,

    /**
     * App which triggered this action
     */
    initialApp: null,

    /**
     * Required for overflow auto
     */
    autoScroll: true,

    /**
     * Overflow auto to enable scrollbar automatically
     */
    overflow: 'auto',

    /**
     * Layout
     */
    layout: 'hfit',

    initComponent: function () {
        this.addEvents(
            /**
             * Fires if no preview is available. Later it should be used to be fired if the browser is not able to load images.
             */
            'noPreviewAvailable'
        );

        this.on('noPreviewAvailable', this.onNoPreviewAvailable, this);

        if (!this.app) {
            this.app = Tine.Tinebase.appMgr.get('Filemanager');
        }

        Tine.Filemanager.DocumentPreview.superclass.initComponent.apply(this, arguments);

        if (!this.record) {
            this.fireEvent('noPreviewAvailable');
            return;
        }

        this.loadPreview();
    },

    loadPreview: function () {
        var _ = window.lodash,
            me = this,
            recordClass = this.record.constructor,
            records = [];

        // attachments preview
        if (! recordClass.hasField('preview_count') && recordClass.hasField('attachments')) {
            _.each(this.record.get('attachments'), function(attachmentData) {
                records.push(new Tine.Tinebase.Model.Tree_Node(attachmentData));
            });
        } else if (this.record.get('preview_count')) {
            records.push(this.record);
        }

        records = _.filter(records, function(record) {
            return !!record.get('preview_count');
        });

        if (! records.length) {
            this.fireEvent('noPreviewAvailable');
            return;
        }

        this.afterIsRendered().then(function () {
            _.each(records, function(record) {
                me.addPreviewPanelForRecord(me, record);
            });
        });
    },

    addPreviewPanelForRecord: function (me, record) {
        var _ = window.lodash;
        _.range(record.get('preview_count')).forEach(function (previewNumber) {
            var path = record.get('path'),
                revision = record.get('revision');

            var url = Ext.urlEncode({
                method: 'Tinebase.downloadPreview',
                frontend: 'http',
                _path: path,
                _appId: me.initialApp ? me.initialApp.id : me.app.id,
                _type: 'previews',
                _num: previewNumber,
                _revision: revision
            }, Tine.Tinebase.tineInit.requestUrl + '?');

            me.add({
                html: '<img style="width: 100%;" src="' + url + '" />',
                xtype: 'panel',
                frame: true,
                border: true
            });
            me.doLayout();
        });
    },

    /**
     * Fires if no previews are available
     */
    onNoPreviewAvailable: function () {
        var me = this;
        me.afterIsRendered().then(function() {
            var text = '';

            if (!Tine.Tinebase.configManager.get('filesystem').createPreviews) {
                text = '<br/><br/>' + me.app.i18n._('Sorry, Tine 2.0 would like to show you the contents of the file displayed.') + ' ' +
                    me.app.i18n._('This is possible for .doc, .jpg, .pdf and even more file formats.') + '<br/>' +
                    '<a href="https://www.tine20.com/kontakt/" target="_blank">' +
                    me.app.i18n._('Interested? Then let us know!') + '</a><br/>' +
                    me.app.i18n._('We are happy make you a non-binding offer.');
            }

            me.add({
                html: '<b>' + me.app.i18n._('No preview available yet - Please try again in a few minutes.') + '</b>' + text,
                xtype: 'panel',
                frame: true,
                border: true
            });
            me.doLayout();
        });
    }
});
