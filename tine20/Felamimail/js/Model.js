/*
 * Tine 2.0
 * 
 * @package     Felamimail
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2007-2012 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 * TODO         think about adding a generic felamimail backend with the exception handler
 */
Ext.ns('Tine.Felamimail.Model');

/**
 * @namespace Tine.Felamimail.Model
 * @class Tine.Felamimail.Model.Message
 * @extends Tine.Tinebase.data.Record
 * 
 * Message Record Definition
 */ 
Tine.Felamimail.Model.Message = Tine.Tinebase.data.Record.create([
      { name: 'id' },
      { name: 'account_id' },
      { name: 'subject' },
      { name: 'from_email' },
      { name: 'from_name' },
      { name: 'sender' },
      { name: 'to' },
      { name: 'cc' },
      { name: 'bcc' },
      { name: 'sent',     type: 'date', dateFormat: Date.patterns.ISO8601Long },
      { name: 'received', type: 'date', dateFormat: Date.patterns.ISO8601Long },
      { name: 'flags' },
      { name: 'size' },
      { name: 'body',     defaultValue: undefined },
      { name: 'body_content_type_of_body_property_of_this_record'},
      { name: 'headers' },
      { name: 'content_type' },
      { name: 'body_content_type' },
      { name: 'structure' },
      { name: 'attachments' },
      { name: 'has_attachment', type: 'bool' },
      { name: 'original_id' },
      { name: 'folder_id' },
      { name: 'note' },
      { name: 'preparedParts' }, // contains invitation event record
      { name: 'reading_conf' }
    ], {
    appName: 'Felamimail',
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
     * adds given flag to message
     * 
     * @param  {String} flag
     * @return {Boolean} false if flag was already set before, else true
     */
    addFlag: function(flag) {
        Tine.log.info('Tine.Felamimail.Model.Message::addFlag - add flag ' + flag);

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
    },

    /**
     * returns actual mimeType of the current body property
     *
     * NOTE: This is not the contents of body_content_type!
     *       body_content_type is the type of the original message derrived by the message structure.
     *       But the server transforms the original type into the requested format/display_format.
     */
    getBodyType: function() {
        return this.get('body_content_type_of_body_property_of_this_record');
    }
});

/**
 * get default message data
 * 
 * @return {Object}
 */
Tine.Felamimail.Model.Message.getDefaultData = function() {
    var autoAttachNote = Tine.Felamimail.registry.get('preferences').get('autoAttachNote');
    
    return {
        note: autoAttachNote,
        content_type: 'text/html'
    };
};

/**
 * get filtermodel for messages
 * 
 * @namespace Tine.Felamimail.Model
 * @static
 * @return {Object} filterModel definition
 */ 
Tine.Felamimail.Model.Message.getFilterModel = function() {
    var app = Tine.Tinebase.appMgr.get('Felamimail');
    
    return [
        {filtertype: 'tine.felamimail.folder.filtermodel', app: app},
        {label: app.i18n._('Subject/From'),field: 'query',         operators: ['contains']},
        {label: app.i18n._('Subject'),     field: 'subject',       operators: ['contains']},
        {label: app.i18n._('From (Email)'),field: 'from_email',    operators: ['contains']},
        {label: app.i18n._('From (Name)'), field: 'from_name',     operators: ['contains']},
        {label: app.i18n._('To'),          field: 'to',            operators: ['contains']},
        {label: app.i18n._('Cc'),          field: 'cc',            operators: ['contains']},
        {label: app.i18n._('Bcc'),         field: 'bcc',           operators: ['contains']},
        {label: app.i18n._('Flags'),       field: 'flags',         filtertype: 'tinebase.multiselect', app: app, multiselectFieldConfig: {
            valueStore: Tine.Felamimail.loadFlagsStore()
        }},
        {label: app.i18n._('Received'),    field: 'received',      valueType: 'date', pastOnly: true}
    ];
};

/**
 * @namespace Tine.Felamimail
 * @class Tine.Felamimail.messageBackend
 * @extends Tine.Tinebase.data.RecordProxy
 * 
 * Message Backend
 * 
 * TODO make clear/addFlags send filter as param instead of array of ids
 */ 
Tine.Felamimail.messageBackend = new Tine.Tinebase.data.RecordProxy({
    appName: 'Felamimail',
    modelName: 'Message',
    recordClass: Tine.Felamimail.Model.Message,
    
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
            return [Tine.Felamimail.folderBackend.recordReader(response)];
        };
        
        // increase timeout as this can take a longer (5 minutes)
        options.timeout = 300000;
        
        return this.doXHTTPRequest(options);
    },
    
    /**
     * fetches body and additional headers (which are needed for the preview panel) into given message
     * 
     * @param {Message} message
     * @param {String} mimeType
     * @param {Function|Object} callback (NOTE: this has NOTHING to do with standard Ext request callback fn)
     */
    fetchBody: function(message, mimeType, callback) {

        if (mimeType == 'configured') {
            var account = Tine.Tinebase.appMgr.get('Felamimail').getAccountStore().getById(message.get('account_id'));
            if (account) {
                mimeType = account.get('display_format');
                if (!mimeType.match(/^text\//)) {
                    mimeType = 'text/' + mimeType;
                }
            } else {
                // no account found, might happen for .eml emails
                mimeType = 'text/plain';
            }
        }

        return this.loadRecord(message, {
            params: {mimeType: mimeType},
            timeout: 120000, // 2 minutes
            scope: this,
            success: function(response, options) {
                var msg = this.recordReader({responseText: Ext.util.JSON.encode(response.data)});
                // NOTE: Flags from the server might be outdated, so we skip them
                Ext.copyTo(message.data, msg.data, Tine.Felamimail.Model.Message.getFieldNames().remove('flags'));
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
        Tine.Felamimail.handleRequestException(exception);
    }
});


/**
 * @namespace Tine.Felamimail.Model
 * @class Tine.Felamimail.Model.Account
 * @extends Tine.Tinebase.data.Record
 * 
 * Account Record Definition
 */ 
Tine.Felamimail.Model.Account = Tine.Tinebase.data.Record.create(Tine.Tinebase.Model.modlogFields.concat([
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
    { name: 'compose_format' },
    { name: 'preserve_format' },
    { name: 'reply_to' },
    { name: 'ns_personal' },
    { name: 'ns_other' },
    { name: 'ns_shared' },
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
    { name: 'sieve_notification_email' },
    { name: 'all_folders_fetched', type: 'bool', defaultValue: false } // client only
]), {
    appName: 'Felamimail',
    modelName: 'Account',
    idProperty: 'id',
    titleProperty: 'name',
    // ngettext('Account', 'Accounts', n);
    recordName: 'Account',
    recordsName: 'Accounts',
    // ngettext('Email Accounts', 'Email Accounts', n);
    containerName: 'Email Accounts',
    containersName: 'Email Accounts',
    
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
        var app = Ext.ux.PopupWindowMgr.getMainWindow().Tine.Tinebase.appMgr.get('Felamimail'),
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
        var app = Ext.ux.PopupWindowMgr.getMainWindow().Tine.Tinebase.appMgr.get('Felamimail'),
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
Tine.Felamimail.Model.Account.getDefaultData = function() {
    var currentUserDisplayName = Tine.Tinebase.registry.get('currentAccount').accountDisplayName;
    
    return {
        from: currentUserDisplayName,
        port: 143,
        smtp_port: 25,
        smtp_ssl: 'none',
        sieve_port: 2000,
        sieve_ssl: 'none',
        signature: 'Sent with love from the Tine 2.0 email client ...<br/>'
            + 'Please visit <a href="http://www.tine20.com">http://www.tine20.com</a>',
        sent_folder: 'Sent',
        trash_folder: 'Trash'
    };
};

/**
 * @namespace Tine.Felamimail
 * @class Tine.Felamimail.accountBackend
 * @extends Tine.Tinebase.data.RecordProxy
 * 
 * Account Backend
 */ 
Tine.Felamimail.accountBackend = new Tine.Tinebase.data.RecordProxy({
    appName: 'Felamimail',
    modelName: 'Account',
    recordClass: Tine.Felamimail.Model.Account
});

/**
 * @namespace Tine.Felamimail.Model
 * @class Tine.Felamimail.Model.Record
 * @extends Ext.data.Record
 * 
 * Folder Record Definition
 */ 
Tine.Felamimail.Model.Folder = Tine.Tinebase.data.Record.create([
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
      { name: 'imap_lastmodseq',    type: 'int' },
      { name: 'cache_status' },
      { name: 'cache_recentcount',  type: 'int' },
      { name: 'cache_totalcount',   type: 'int' },
      { name: 'cache_unreadcount',  type: 'int' },
      { name: 'cache_timestamp',    type: 'date', dateFormat: Date.patterns.ISO8601Long  },
      { name: 'cache_job_actions_est',     type: 'int' },
      { name: 'cache_job_actions_done',         type: 'int' },
      { name: 'quota_usage',         type: 'int' },
      { name: 'quota_limit',         type: 'int' },
      { name: 'client_access_time', type: 'date', dateFormat: Date.patterns.ISO8601Long  }, // client only {@see Tine.Felamimail.folderBackend#updateMessageCache}
      { name: 'unread_children', type: 'Array', defaultValue: [] } // client only / array of unread child ids
], {
    // translations for system folders:
    // i18n._('INBOX') i18n._('Drafts') i18n._('Sent') i18n._('Templates') i18n._('Junk') i18n._('Trash')

    appName: 'Felamimail',
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
 * @namespace Tine.Felamimail
 * @class Tine.Felamimail.folderBackend
 * @extends Tine.Tinebase.data.RecordProxy
 * 
 * Folder Backend
 */ 
Tine.Felamimail.folderBackend = new Tine.Tinebase.data.RecordProxy({
    appName: 'Felamimail',
    modelName: 'Folder',
    recordClass: Tine.Felamimail.Model.Folder,
    
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
        Tine.Felamimail.handleRequestException(exception);
    }
});

/**
 * @namespace Tine.Felamimail.Model
 * @class Tine.Felamimail.Model.Vacation
 * @extends Tine.Tinebase.data.Record
 * 
 * Vacation Record Definition
 */ 
Tine.Felamimail.Model.Vacation = Tine.Tinebase.data.Record.create(Tine.Tinebase.Model.modlogFields.concat([
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
    appName: 'Felamimail',
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
Tine.Felamimail.Model.Vacation.getDefaultData = function() {
    return {
        days: 7,
        mime: 'multipart/alternative'
    };
};

/**
 * @namespace Tine.Felamimail
 * @class Tine.Felamimail.vacationBackend
 * @extends Tine.Tinebase.data.RecordProxy
 * 
 * Vacation Backend
 */ 
Tine.Felamimail.vacationBackend = new Tine.Tinebase.data.RecordProxy({
    appName: 'Felamimail',
    modelName: 'Vacation',
    recordClass: Tine.Felamimail.Model.Vacation,
    
    /**
     * exception handler for this proxy
     * 
     * @param {Tine.Exception} exception
     */
    handleRequestException: function(exception) {
        Tine.Felamimail.handleRequestException(exception);
    }
});

/**
 * @namespace Tine.Felamimail.Model
 * @class Tine.Felamimail.Model.Rule
 * @extends Tine.Tinebase.data.Record
 * 
 * Rule Record Definition
 */ 
Tine.Felamimail.Model.Rule = Tine.Tinebase.data.Record.create(Tine.Tinebase.Model.modlogFields.concat([
    { name: 'id', sortType: function(value) {
        // should be sorted as int
        return parseInt(value, 10);
    }
    },
    { name: 'action_type' },
    { name: 'action_argument' },
    { name: 'conjunction' },
    { name: 'enabled', type: 'boolean'},
    { name: 'conditions' },
    { name: 'account_id' }
]), {
    appName: 'Felamimail',
    modelName: 'Rule',
    idProperty: 'id',
    titleProperty: 'id',
    // ngettext('Rule', 'Rules', n);
    recordName: 'Rule',
    recordsName: 'Rules',
    // ngettext('record list', 'record lists', n);
    containerName: 'Rule list',
    containersName: 'Rule lists'    
});

/**
 * get default data for rules
 * 
 * @return {Object}
 */
Tine.Felamimail.Model.Rule.getDefaultData = function() {
    return {
        enabled: true,
        conditions: [{
            test: 'address',
            header: 'from',
            comperator: 'contains',
            key: ''
        }],
        conjunction: 'allof',
        action_type: 'fileinto',
        action_argument: ''
    };
};

/**
 * @namespace Tine.Felamimail
 * @class Tine.Felamimail.rulesBackend
 * @extends Tine.Tinebase.data.RecordProxy
 * 
 * Rule Backend
 */ 
Tine.Felamimail.rulesBackend = new Tine.Tinebase.data.RecordProxy({
    appName: 'Felamimail',
    modelName: 'Rule',
    recordClass: Tine.Felamimail.Model.Rule,
    
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
        Tine.Felamimail.handleRequestException(exception);
    }
});

/**
 * @namespace Tine.Felamimail.Model
 * @class Tine.Felamimail.Model.Flag
 * @extends Tine.Tinebase.data.Record
 * 
 * Flag Record Definition
 */ 
Tine.Felamimail.Model.Flag = Tine.Tinebase.data.Record.create(Tine.Tinebase.Model.modlogFields.concat([
    { name: 'id' },
    { name: 'name' }
]), {
    appName: 'Felamimail',
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
