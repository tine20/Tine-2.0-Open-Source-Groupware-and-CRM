/*
 * Tine 2.0
 *
 * @package     Expressomail
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2007-2012 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 * TODO         think about adding a generic expressomail backend with the exception handler
 */
Ext.ns('Tine.Expressomail.Model');

/**
 * @namespace Tine.Expressomail.Model
 * @class Tine.Expressomail.Model.MessageAttachments
 * @extends Tine.Tinebase.data.Record
 *
 * MessageAttachments Record Definition
 */
Tine.Expressomail.Model.MessageAttachments = Tine.Tinebase.data.Record.create([
      { name: 'id' },
      { name: 'attachments' }
    ], {
    appName: 'Expressomail',
    modelName: 'MessageContent',
    idProperty: 'id'
});

/**
 * @namespace Tine.Expressomail.Model
 * @class Tine.Expressomail.Model.MessageContent
 * @extends Tine.Tinebase.data.Record
 *
 * MessageContent Record Definition
 */
Tine.Expressomail.Model.MessageContent = Tine.Tinebase.data.Record.create([
      { name: 'id' },
      { name: 'body' },
      { name: 'attachments' },
      { name: 'embedded_images' }
    ], {
    appName: 'Expressomail',
    modelName: 'MessageContent',
    idProperty: 'id'
});

/**
 * @namespace Tine.Expressomail.Model
 * @class Tine.Expressomail.Model.Message
 * @extends Tine.Tinebase.data.Record
 *
 * Message Record Definition
 */
Tine.Expressomail.Model.Message = Tine.Tinebase.data.Record.create([
      { name: 'id' },
      { name: 'account_id' },
      { name: 'subject' },
      { name: 'from_email' },
      { name: 'from_name' },
      { name: 'sender' },
      { name: 'sender_account' },
      { name: 'to' },
      { name: 'cc' },
      { name: 'bcc' },
      { name: 'sent',     type: 'date', dateFormat: Date.patterns.ISO8601Long },
      { name: 'received', type: 'date', dateFormat: Date.patterns.ISO8601Long },
      { name: 'flags' },
      { name: 'size' },
      { name: 'body',     defaultValue: undefined },
      { name: 'headers' },
      { name: 'content_type' },
      { name: 'body_content_type' },
      { name: 'structure' },
      { name: 'attachments' },
      { name: 'has_attachment', type: 'bool' },
      { name: 'original_id' },
      { name: 'initial_id' }, // contains the initial id of edited message
      { name: 'draft_id' },
      { name: 'folder_id' },
      { name: 'note' },
      { name: 'add_contacts' }, // contains true if to add unknown contacts
      { name: 'added_contacts' }, // contains how many contacts where added (or -1 if error)
      { name: 'preparedParts' }, // contains invitation event record
      { name: 'reading_conf' },
      { name: 'smime' },
      { name: 'importance' },
      { name: 'smimeEml' },
      { name: 'signature_info' },      
      { name: 'embedded_images' },
      { name: 'error_message' }
    ], {
    appName: 'Expressomail',
    modelName: 'Message',
    idProperty: 'id',
    titleProperty: 'subject',
    // ngettext('Message', 'Messages', n);
    recordName: 'Message',
    recordsName: 'Messages',
    containerProperty: 'folder_id',
    // ngettext('Folder', 'Folders', n);
    containerName: 'Folder',
    containersName: 'Folders',

    /**
     * check if message has given flag
     *
     * @param  {String} flag
     * @return {Boolean}
     */
    hasFlag: function(flag) {
        var flags = this.get('flags') || [];
        return flags.indexOf(flag) >= 0;
    },

        /**
     * check if message has impertance flag
     *
     * @param  {String} flag
     * @return {Boolean}
     */
    isImportant: function() {
        var value = this.get('importance');
        if (value == true){
            return true;
        }

        return false;

    },

    /**
     * adds given flag to message
     *
     * @param  {String} flag
     * @return {Boolean} false if flag was already set before, else true
     */
    addFlag: function(flag) {
        if (! this.hasFlag(flag)) {
            var flags = Ext.unique(this.get('flags'));
            flags.push(flag);

            this.set('flags', flags);
            return true;
        }

        return false;
    },

    /**
     * check if body has been fetched
     *
     * @return {Boolean}
     */
    bodyIsFetched: function() {
        return this.get('body') !== undefined;
    },

    /**
     * clears given flag from message
     *
     * @param {String} flag
     * @return {Boolean} false if flag was not set before, else true
     */
    clearFlag: function(flag) {
        if (this.hasFlag(flag)) {
            var flags = Ext.unique(this.get('flags'));
            flags.remove(flag);

            this.set('flags', flags);
            return true;
        }

        return false;
    },

    /**
     * returns true if given record obsoletes this one
     *
     * NOTE: this does only work for Tine.widgets.grid.GridPanel::onStoreBeforeLoadRecords record comparison
     *
     * @param {Tine.Tinebase.data.Record} record
     * @return {Boolean}
     */
    isObsoletedBy: function(record) {
        return record.mtime || record.ctime > this.ctime;
    }
});

/**
 * get default message data
 *
 * @return {Object}
 */
Tine.Expressomail.Model.Message.getDefaultData = function() {
    var autoAttachNote = Tine.Expressomail.registry.get('preferences').get('autoAttachNote');

    return {
        note: autoAttachNote,
        content_type: 'text/html'
    };
};

/**
 * get filtermodel for messages
 *
 * @namespace Tine.Expressomail.Model
 * @static
 * @return {Object} filterModel definition
 */
Tine.Expressomail.Model.Message.getFilterModel = function() {
    var app = Tine.Tinebase.appMgr.get('Expressomail');

    return [
        {filtertype: 'tine.expressomail.folder.filtermodel', app: app},
        {label: app.i18n._('Subject/From/CC/Body'),field: 'query',         operators: ['contains']},
        {label: app.i18n._('Subject'),     field: 'subject',       operators: ['contains']},
        {label: app.i18n._('Body'),        field: 'body',          operators: ['contains']},
        {label: app.i18n._('From (Email)'),field: 'from_email',    operators: ['contains']},
        {label: app.i18n._('From (Name)'), field: 'from_name',     operators: ['contains']},
        {label: app.i18n._('To'),          field: 'to',            operators: ['contains']},
        {label: app.i18n._('Cc'),          field: 'cc',            operators: ['contains']},
        {label: app.i18n._('Bcc'),         field: 'bcc',           operators: ['contains']},
        {label: app.i18n._('Flags'),       field: 'flags',         filtertype: 'tinebase.multiselect', app: app, multiselectFieldConfig: {
            valueStore: Tine.Expressomail.loadFlagsStore()
        }},
        {label: app.i18n._('Received'),    field: 'received',      valueType: 'date', pastOnly: true}
    ];
};

/**
 * @namespace Tine.Expressomail
 * @class Tine.Expressomail.messageBackend
 * @extends Tine.Tinebase.data.RecordProxy
 *
 * Message Backend
 *
 * TODO make clear/addFlags send filter as param instead of array of ids
 */
Tine.Expressomail.messageBackend = new Tine.Tinebase.data.RecordProxy({
    appName: 'Expressomail',
    modelName: 'Message',
    recordClass: Tine.Expressomail.Model.Message,

    /**
     * move messsages to folder
     *
     * @param  array $filterData filter data
     * @param  string $targetFolderId
     * @return  {Number} Ext.Ajax transaction id
     */
    moveMessages: function(filter, targetFolderId, options) {
        options = options || {};
        options.params = options.params || {};

        var p = options.params;

        p.method = this.appName + '.moveMessages';
        p.filterData = filter;
        p.targetFolderId = targetFolderId;

        options.beforeSuccess = function(response) {
            return [Tine.Expressomail.folderBackend.recordReader(response)];
        };

        // increase timeout as this can take a longer (5 minutes)
        options.timeout = 300000;

        return this.doXHTTPRequest(options);
    },
    /**
     * searches all (lightweight) records matching filter
     * 
     * @param   {Object} filter
     * @param   {Object} paging
     * @param   {Object} options
     * @return  {Number} Ext.Ajax transaction id
     * @success {Object} root:[records], totalcount: number
     */
    searchRecords: function(filter, paging, options) {
        options = options || {};
        options.params = options.params || {};
        
        var p = options.params;
        
        this.normalizeQuickFilterSearch(filter);

        p.method = this.appName + '.search' + this.modelName + 's';
        p.filter = (filter) ? filter : [];
        p.paging = paging;
        
        options.beforeSuccess = function(response) {
            return [this.jsonReader.read(response)];
        };
        
        // increase timeout as this can take a longer (1 minute)
        options.timeout = 300000;
        
        return this.doXHTTPRequest(options);
    },        

    /**
     * normalizes a quickFilter search: pushes the current path if it is
     * not in the filter criteria.
     * 
     * @param   {Object} filter
     */
    normalizeQuickFilterSearch: function(filter) {
        var isQuickFilter = false;

        Ext.each(filter, function(filterData) {
            isQuickFilter |= (filterData.id == "quickFilter");
        }, isQuickFilter);

        if(isQuickFilter) {
            var hasFolderData = false;
            Ext.each(filter, function(filterData) {
                hasFolderData |= (filterData.field == "path");
            }, hasFolderData);
            if(!hasFolderData) {
                folderFilterData = {
                    field: "path",
                    operator: "in",
                    value: [Tine.Tinebase.appMgr.get('Expressomail').mainScreen.GridPanel.getCurrentFolderFromTree().get("path")],
                    id: ""
                }
                filter.push(folderFilterData);
            }
        }
    },

    /**
     * fetches body and additional headers (which are needed for the preview panel) into given message
     *
     * @param {Message} message
     * @param {Function|Object} callback (NOTE: this has NOTHING to do with standard Ext request callback fn)
     */
    fetchBody: function(message, callback) {
        return this.loadRecord(message, {
            timeout: 120000, // 2 minutes
            nonblocking: true,
            scope: this,
            success: function(response, options) {
                var msg = this.recordReader({responseText: Ext.util.JSON.encode(response.data)});
                // NOTE: Flags from the server might be outdated, so we skip them
                Ext.copyTo(message.data, msg.data, Tine.Expressomail.Model.Message.getFieldNames().remove('flags'));
                if (Ext.isFunction(callback)) {
                    callback(message);
                } else if (callback.success) {
                    Ext.callback(callback.success, callback.scope, [message]);
                }
            },
            failure: function(exception) {
                if (callback.failure) {
                    Ext.callback(callback.failure, callback.scope, [exception]);
                } else {
                    this.handleRequestException(exception);
                }
            }
        });
    },

    /**
     * saves a message into a folder
     *
     * @param   {Ext.data.Record} record
     * @param   {String} folderName
     * @param   {Object} options
     * @return  {Number} Ext.Ajax transaction id
     * @success {Ext.data.Record}
     */
    saveInFolder: function(record, folderName, options) {
        options = options || {};
        options.params = options.params || {};
        options.beforeSuccess = function(response) {
            return [this.recordReader(response)];
        };

        var p = options.params;
        p.method = this.appName + '.saveMessageInFolder';
        p.recordData = record.data;
        p.folderName = folderName;

        // increase timeout as this can take a longer (5 minutes)
        options.timeout = 300000;

        return this.doXHTTPRequest(options);
    },

    /**
     * saves a draft message
     *
     * @param   {Ext.data.Record} record
     * @param   {String} folderName
     * @param   {Object} options
     * @return  {Number} Ext.Ajax transaction id
     * @success {Ext.data.Record}
     */
    saveDraft: function(record, folderName, options) {
        options = options || {};
        options.params = options.params || {};
        options.beforeSuccess = function(response) {
            return [this.recordReader(response)];
        };

        var p = options.params;
        p.method = this.appName + '.saveDraftInFolder';
        p.recordData = record.data;
        p.folderName = folderName;

        // increase timeout as this can take a longer (5 minutes)
        options.timeout = 300000;

        return this.doXHTTPRequest(options);
    },

    /**
     * report message(s) as phishing
     *
     * @param   {Ext.data.Record} record
     * @param  {Array} msgIds
     * @param   {Object} options
     * @return  {Number} Ext.Ajax transaction id
     * @success {Ext.data.Record}
     */
    reportPhishing: function(record, msgIds, options)
    {
        var options = options || {};
        options.params = options.params || {};

        var p = options.params;

        p.method = this.appName + '.reportPhishing';
        p.recordData = record.data;
        p.msgIds = msgIds;

        // increase timeout as this can take a longer (5 minutes)
        options.timeout = 300000;

        return this.doXHTTPRequest(options);
    },

    /**
     * add given flags to given messages
     *
     * @param  {String/Array} ids
     * @param  {String/Array} flags
     */
    addFlags: function(ids, flags, options)
    {
        options = options || {};
        options.params = options.params || {};

        var p = options.params;

        p.method = this.appName + '.addFlags';
        p.filterData = ids;
        p.flags = flags;

        // increase timeout as this can take a longer (5 minutes)
        options.timeout = 300000;

        return this.doXHTTPRequest(options);
    },

    /**
     * clear given flags from given messages
     *
     * @param  {String/Array} ids
     * @param  {String/Array} flags
     */
    clearFlags: function(ids, flags, options)
    {
        options = options || {};
        options.params = options.params || {};

        var p = options.params;

        p.method = this.appName + '.clearFlags';
        p.filterData = ids;
        p.flags = flags;

        // increase timeout as this can take a longer (5 minutes)
        options.timeout = 300000;

        return this.doXHTTPRequest(options);
    },

    /**
     * exception handler for this proxy
     *
     * @param {Tine.Exception} exception
     */
    handleRequestException: function(exception) {
        Tine.Expressomail.handleRequestException(exception);
    }
});


/**
 * @namespace Tine.Expressomail.Model
 * @class Tine.Expressomail.Model.Account
 * @extends Tine.Tinebase.data.Record
 *
 * Account Record Definition
 */
Tine.Expressomail.Model.Account = Tine.Tinebase.data.Record.create(Tine.Tinebase.Model.modlogFields.concat([
    { name: 'id' },
    { name: 'original_id' }, // client only, used in message compose dialog for accounts combo
    { name: 'user_id' },
    { name: 'name' },
    { name: 'type' },
    { name: 'user' },
    { name: 'host' },
    { name: 'email' },
    { name: 'password' },
    { name: 'from' },
    { name: 'organization' },
    { name: 'port' },
    { name: 'ssl' },
    { name: 'imap_status', defaultValue: 'success'}, // client only {success|failure}
    { name: 'sent_folder' },
    { name: 'trash_folder' },
    { name: 'drafts_folder' },
    { name: 'templates_folder' },
    { name: 'has_children_support', type: 'bool' },
    { name: 'delimiter' },
    { name: 'display_format' },
    { name: 'ns_personal' },
    { name: 'ns_other' },
    { name: 'ns_shared' },
    { name: 'shared_seen' },
    { name: 'shared_seen_support' },
    { name: 'signature' },
    { name: 'signature_position' },
    { name: 'smtp_port' },
    { name: 'smtp_hostname' },
    { name: 'smtp_auth' },
    { name: 'smtp_ssl' },
    { name: 'smtp_user' },
    { name: 'smtp_password' },
    { name: 'sieve_hostname' },
    { name: 'sieve_port' },
    { name: 'sieve_ssl' },
    { name: 'sieve_vacation_active', type: 'bool' },
    { name: 'all_folders_fetched', type: 'bool', defaultValue: false } // client only
]), {
    appName: 'Expressomail',
    modelName: 'Account',
    idProperty: 'id',
    titleProperty: 'name',
    // ngettext('Account', 'Accounts', n);
    recordName: 'Account',
    recordsName: 'Accounts',
    // ngettext('Email Accounts', 'Email Accounts', n);
    containerName: 'Email Accounts',
    containersName: 'Contas de Email',  //'Email Accounts' 

    /**
     * @type Object
     */
    lastIMAPException: null,

    /**
     * get the last IMAP exception
     *
     * @return {Object}
     */
    getLastIMAPException: function() {
        return this.lastIMAPException;
    },

    /**
     * returns sendfolder id
     * -> needed as trash is saved as globname :(
     */
    getSendFolderId: function() {
        var app = Ext.ux.PopupWindowMgr.getMainWindow().Tine.Tinebase.appMgr.get('Expressomail'),
            sendName = this.get('sent_folder'),
            accountId = this.id,
            send = sendName ? app.getFolderStore().queryBy(function(record) {
                return record.get('account_id') === accountId && record.get('globalname') === sendName;
            }, this).first() : null;

        return send ? send.id : null;
    },

    /**
     * returns trashfolder id
     * -> needed as trash is saved as globname :(
     */
    getTrashFolderId: function() {
        var app = Ext.ux.PopupWindowMgr.getMainWindow().Tine.Tinebase.appMgr.get('Expressomail'),
            trashName = this.get('trash_folder'),
            accountId = this.id,
            trash = trashName ? app.getFolderStore().queryBy(function(record) {
                return record.get('account_id') === accountId && record.get('globalname') === trashName;
            }, this).first() : null;

        return trash ? trash.id : null;
    },

    /**
     * set or clear IMAP exception and update imap_state
     *
     * @param {Object} exception
     */
    setLastIMAPException: function(exception) {
        this.lastIMAPException = exception;
        this.set('imap_status', exception ? 'failure' : 'success');
        this.commit();
    }
});

/**
 * get default data for account
 *
 * @return {Object}
 */
Tine.Expressomail.Model.Account.getDefaultData = function() {
    var defaults = (Tine.Expressomail.registry.get('defaults'))
        ? Tine.Expressomail.registry.get('defaults')
        : {};

    var currentUserDisplayName = Tine.Tinebase.registry.get('currentAccount').accountDisplayName;

    return {
        from: currentUserDisplayName,
        host: (defaults.host) ? defaults.host : '',
        port: (defaults.port) ? defaults.port : 143,
        smtp_hostname: (defaults.smtp && defaults.smtp.hostname) ? defaults.smtp.hostname : '',
        smtp_port: (defaults.smtp && defaults.smtp.port) ? defaults.smtp.port : 25,
        smtp_ssl: (defaults.smtp && defaults.smtp.ssl) ? defaults.smtp.ssl : 'none',
        sieve_port: 2000,
        sieve_ssl: 'none',
        signature: 'Sent with love from the new tine 2.0 email client ...<br/>'
            + 'Please visit <a href="http://www.tine20.com">http://www.tine20.com</a>',
        sent_folder: (defaults.sent_folder) ? defaults.sent_folder : 'Sent',
        trash_folder: (defaults.trash_folder) ? defaults.trash_folder : 'Trash'
    };
};

/**
 * @namespace Tine.Expressomail
 * @class Tine.Expressomail.accountBackend
 * @extends Tine.Tinebase.data.RecordProxy
 *
 * Account Backend
 */
Tine.Expressomail.accountBackend = new Tine.Tinebase.data.RecordProxy({
    appName: 'Expressomail',
    modelName: 'Account',
    recordClass: Tine.Expressomail.Model.Account
});

/**
 * @namespace Tine.Expressomail.Model
 * @class Tine.Expressomail.Model.Record
 * @extends Ext.data.Record
 *
 * Folder Record Definition
 */
Tine.Expressomail.Model.Folder = Tine.Tinebase.data.Record.create([
      { name: 'id' },
      { name: 'localname' },
      { name: 'globalname' },
      { name: 'path' }, // /accountid/folderid/...
      { name: 'parent' },
      { name: 'parent_path' }, // /accountid/folderid/...
      { name: 'account_id' },
      { name: 'has_children',       type: 'bool' },
      { name: 'is_selectable',      type: 'bool' },
      { name: 'system_folder',      type: 'bool' },
      { name: 'imap_status' },
      { name: 'imap_timestamp',     type: 'date', dateFormat: Date.patterns.ISO8601Long },
      { name: 'imap_uidvalidity',   type: 'int' },
      { name: 'imap_totalcount',    type: 'int' },
      { name: 'can_share',          type: 'bool' },
      { name: 'sharing_with',       type: 'Array' },
      { name: 'cache_status' },
      { name: 'cache_recentcount',  type: 'int' },
      { name: 'cache_totalcount',   type: 'int' },
      { name: 'cache_unreadcount',  type: 'int' },
      { name: 'cache_timestamp',    type: 'date', dateFormat: Date.patterns.ISO8601Long  },
      { name: 'cache_job_actions_est',     type: 'int' },
      { name: 'cache_job_actions_done',         type: 'int' },
      { name: 'quota_usage',         type: 'int' },
      { name: 'quota_limit',         type: 'int' },
      { name: 'client_access_time', type: 'date', dateFormat: Date.patterns.ISO8601Long  }, // client only {@see Tine.Expressomail.folderBackend#updateMessageCache}
      { name: 'unread_children', type: 'Array', defaultValue: [] } // client only / array of unread child ids
], {
    // translations for system folders:
    // _('INBOX') _('Drafts') _('Sent') _('Templates') _('Junk') _('Trash')

    appName: 'Expressomail',
    modelName: 'Folder',
    idProperty: 'id',
    titleProperty: 'localname',
    // ngettext('Folder', 'Folders', n);
    recordName: 'Folder',
    recordsName: 'Folders',
    // ngettext('record list', 'record lists', n);
    containerName: 'Folder list',
    containersName: 'Folder lists',

    /**
     * is this folder the currently selected folder
     *
     * @return {Boolean}
     */
    isCurrentSelection: function() {
        if (Tine.Tinebase.appMgr.get(this.appName).getMainScreen().getTreePanel()) {
            // get active node
            var node = Tine.Tinebase.appMgr.get(this.appName).getMainScreen().getTreePanel().getSelectionModel().getSelectedNode();
            if (node && node.attributes.folder_id) {
                return node.id == this.id;
            }
        }

        return false;
    },

    /**
     * is this folder an inbox?
     *
     * @return {Boolean}
     */
    isInbox: function() {
        return Ext.util.Format.lowercase(this.get('localname')) === 'inbox';
    },

    /**
     * returns true if current folder needs an update
     */
    needsUpdate: function(updateInterval) {
        if (! this.get('is_selectable')) {
            return false;
        }

        var timestamp = this.get('client_access_time');
        return this.get('cache_status') !== 'complete' || ! Ext.isDate(timestamp) || timestamp.getElapsed() > updateInterval;
    }
});

/**
 * @namespace Tine.Expressomail
 * @class Tine.Expressomail.folderBackend
 * @extends Tine.Tinebase.data.RecordProxy
 *
 * Folder Backend
 */
Tine.Expressomail.folderBackend = new Tine.Tinebase.data.RecordProxy({
    appName: 'Expressomail',
    modelName: 'Folder',
    recordClass: Tine.Expressomail.Model.Folder,

    /**
     * update message cache of given folder for given execution time and sets the client_access_time
     *
     * @param   {String} folderId
     * @param   {Number} executionTime (seconds)
     * @return  {Number} Ext.Ajax transaction id
     */
    updateMessageCache: function(folderId, executionTime, options) {
        options = options || {};
        options.params = options.params || {};

        var p = options.params;

        p.method = this.appName + '.updateMessageCache';
        p.folderId = folderId;
        p.time = executionTime;

        options.beforeSuccess = function(response) {
            var folder = this.recordReader(response);
            folder.set('client_access_time', new Date());
            return [folder];
        };

        // give 5 times more before timeout
        options.timeout = executionTime * 5000;

        return this.doXHTTPRequest(options);
    },

    /**
     * exception handler for this proxy
     *
     * @param {Tine.Exception} exception
     */
    handleRequestException: function(exception) {
        Tine.Expressomail.handleRequestException(exception);
    }
});

/**
 * @namespace Tine.Expressomail.Model
 * @class Tine.Expressomail.Model.Vacation
 * @extends Tine.Tinebase.data.Record
 *
 * Vacation Record Definition
 */
Tine.Expressomail.Model.Vacation = Tine.Tinebase.data.Record.create(Tine.Tinebase.Model.modlogFields.concat([
    { name: 'id' },
    { name: 'reason' },
    { name: 'enabled', type: 'boolean'},
    { name: 'days' },
    { name: 'start_date', type: 'date' },
    { name: 'end_date', type: 'date' },
    { name: 'contact_ids' },
    { name: 'template_id' },
    { name: 'signature' },
    { name: 'mime' }
]), {
    appName: 'Expressomail',
    modelName: 'Vacation',
    idProperty: 'id',
    titleProperty: 'id',
    // ngettext('Vacation', 'Vacations', n);
    recordName: 'Vacation',
    recordsName: 'Vacations',
    // ngettext('record list', 'record lists', n);
    containerName: 'Vacation list',
    containersName: 'Vacation lists'
});

/**
 * get default data for vacation
 *
 * @return {Object}
 */
Tine.Expressomail.Model.Vacation.getDefaultData = function() {
    return {
        days: 7,
        mime: 'multipart/alternative'
    };
};

/**
 * @namespace Tine.Expressomail
 * @class Tine.Expressomail.vacationBackend
 * @extends Tine.Tinebase.data.RecordProxy
 *
 * Vacation Backend
 */
Tine.Expressomail.vacationBackend = new Tine.Tinebase.data.RecordProxy({
    appName: 'Expressomail',
    modelName: 'Vacation',
    recordClass: Tine.Expressomail.Model.Vacation,

    /**
     * exception handler for this proxy
     *
     * @param {Tine.Exception} exception
     */
    handleRequestException: function(exception) {
        Tine.Expressomail.handleRequestException(exception);
    }
});

/**
 * @namespace Tine.Expressomail.Model
 * @class Tine.Expressomail.Model.Rule
 * @extends Tine.Tinebase.data.Record
 *
 * Rule Record Definition
 */
Tine.Expressomail.Model.Rule = Tine.Tinebase.data.Record.create(Tine.Tinebase.Model.modlogFields.concat([
    { name: 'id', sortType: function(value) {
        // should be sorted as int
        return parseInt(value, 10);
    }
    },
    { name: 'action_type' },
    { name: 'action_argument' },
    { name: 'enabled', type: 'boolean'},
    { name: 'conditions' },
    { name: 'account_id' }
]), {
    appName: 'Expressomail',
    modelName: 'Rule',
    idProperty: 'id',
    titleProperty: 'id',
    // ngettext('Rule', 'Rules', n);
    recordName: 'Regra',
    recordsName: 'Regras',
    // ngettext('record list', 'record lists', n);
    containerName: 'Rule list',
    containersName: 'Rule lists'
});

/**
 * get default data for rules
 *
 * @return {Object}
 */
Tine.Expressomail.Model.Rule.getDefaultData = function() {
    return {
        enabled: true,
        conditions: [{
            test: 'address',
            header: 'from',
            comperator: 'contains',
            key: ''
        }],
        action_type: 'fileinto',
        action_argument: ''
    };
};

/**
 * @namespace Tine.Expressomail
 * @class Tine.Expressomail.rulesBackend
 * @extends Tine.Tinebase.data.RecordProxy
 *
 * Rule Backend
 */
Tine.Expressomail.rulesBackend = new Tine.Tinebase.data.RecordProxy({
    appName: 'Expressomail',
    modelName: 'Rule',
    recordClass: Tine.Expressomail.Model.Rule,

    /**
     * searches all (lightweight) records matching filter
     *
     * @param   {Object} filter accountId
     * @param   {Object} paging
     * @param   {Object} options
     * @return  {Number} Ext.Ajax transaction id
     * @success {Object} root:[records], totalcount: number
     */
    searchRecords: function(filter, paging, options) {
        options = options || {};
        options.params = options.params || {};
        var p = options.params;

        p.method = this.appName + '.get' + this.modelName + 's';
        p.accountId = filter;

        options.beforeSuccess = function(response) {
            return [this.jsonReader.read(response)];
        };

        // increase timeout as this can take a longer (1 minute)
        options.timeout = 60000;

        return this.doXHTTPRequest(options);
    },

    /**
     * save sieve rules
     *
     * @param  {String}     accountId
     * @param  {Array}      rules
     * @param  {Object}     options
     */
    saveRules: function(accountId, rules, options)
    {
        options = options || {};
        options.params = options.params || {};

        var p = options.params;

        p.method = this.appName + '.saveRules';
        p.accountId = accountId;
        p.rulesData = rules;

        return this.doXHTTPRequest(options);
    },

     /**
     * retrieve sieve rules
     *
     * @param   {String}     accountId
     * @param   {Object}     options
     * @return  {Object}     Ext.Ajax transaction results
     */
    getRules: function(accountId, options)
    {
        options = options || {};
        options.params = options.params || {};

        var p = options.params;

        p.method = this.appName + '.getRules';
        p.accountId = accountId;
        p.part = options.parts;

        options.timeout = 3000;

        return this.doXHTTPRequest(options);
    },


    /**
     * saves a single record
     *
     * NOTE: Single rule records can't be saved
     *
     * @param   {Ext.data.Record} record
     * @param   {Object} options
     * @return  {Number} Ext.Ajax transaction id
     * @success {Ext.data.Record}
     */
    saveRecord: function(record, options, additionalArguments) {
        // does nothing
    },

    /**
     * exception handler for this proxy
     *
     * @param {Tine.Exception} exception
     */
    handleRequestException: function(exception) {
        Tine.Expressomail.handleRequestException(exception);
    }
});

/**
 * @namespace Tine.Expressomail.Model
 * @class Tine.Expressomail.Model.Flag
 * @extends Tine.Tinebase.data.Record
 *
 * Flag Record Definition
 */
Tine.Expressomail.Model.Flag = Tine.Tinebase.data.Record.create(Tine.Tinebase.Model.modlogFields.concat([
    { name: 'id' },
    { name: 'name' }
]), {
    appName: 'Expressomail',
    modelName: 'Flag',
    idProperty: 'id',
    titleProperty: 'id',
    // ngettext('Flag', 'Flags', n);
    recordName: 'Flag',
    recordsName: 'Flags',
    // ngettext('Flag list', 'Flag lists', n);
    containerName: 'Flag list',
    containersName: 'Flag lists'
});

Tine.Expressomail.Model.Acl = Ext.data.Record.create([
    {name: 'account_id'},
    {name: 'account_name', sortType: Tine.Tinebase.common.accountSortType},
    {name: 'readacl',    type: 'boolean'},
    {name: 'writeacl',     type: 'boolean'},
    {name: 'sendacl',     type: 'boolean'}
]);
