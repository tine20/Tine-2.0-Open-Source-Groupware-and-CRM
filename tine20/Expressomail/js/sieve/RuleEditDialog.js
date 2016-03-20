/*
 * Tine 2.0
 * 
 * @package     Expressomail
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2010-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */
 
Ext.namespace('Tine.Expressomail.sieve');

/**
 * @namespace   Tine.Expressomail.sieve
 * @class       Tine.Expressomail.sieve.RuleEditDialog
 * @extends     Tine.widgets.dialog.EditDialog
 * 
 * <p>Sieve Filter Dialog</p>
 * <p>This dialog is editing a filter rule.</p>
 * <p>
 * </p>
 * 
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2010 Metaways Infosystems GmbH (http://www.metaways.de)
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * 
 * @param       {Object} config
 * @constructor
 * Create a new RuleEditDialog
 */
Tine.Expressomail.sieve.RuleEditDialog = Ext.extend(Tine.widgets.dialog.EditDialog, {
    /**
     * @cfg {Tine.Expressomail.Model.Account}
     */
    account: null,
    
    /**
     * @private
     */
    windowNamePrefix: 'RuleEditWindow_',
    appName: 'Expressomail',
    recordClass: Tine.Expressomail.Model.Rule,
    mode: 'local',
    loadRecord: true,
    tbarItems: [],
    evalGrants: false,
    
    conditionsIsValid : false,
    validationMessage: '',
    
    /**
     * overwrite update toolbars function (we don't have record grants yet)
     * 
     * @private
     */
    updateToolbars: function() {

    },
    
    /**
     * @private
     */
    onRender: function(ct, position) {
        Tine.Expressomail.sieve.RuleEditDialog.superclass.onRender.call(this, ct, position);
        
        this.onChangeType.defer(250, this);
    },
    
    /**
     * Change type card layout depending on selected combo box entry and set field value
     */
    onChangeType: function() {
        var type = this.actionTypeCombo.getValue();
        
        var cardLayout = Ext.getCmp(this.idPrefix + 'CardLayout').getLayout();
        if (cardLayout !== 'card') {
            cardLayout.setActiveItem(this.idPrefix + type);
            if (this.record.get('action_type') == type) {
                var field = this.getForm().findField('action_argument_' + type);
                if (field !== null) {
                    field.setValue(this.record.get('action_argument'));
                }
            }
        }
    },
    
    /**
     * Custom isValid method to check both conditions and actions
     * @return {Boolean}
     */
    isValid: function(){
        var actionType = this.actionTypeCombo.getValue();
        var actionField = this.getForm().findField('action_argument_' + actionType);
        var actionIsValid = actionField == null || actionField.isValid();
        
        var conditionsIsValid = true;
        var msg = '';
        var app = Tine.Tinebase.appMgr.get('Expressomail');
        var conditions = this.conditionsPanel.getValue();
        Ext.each(conditions, function(condition){
            if(Ext.util.Format.trim(condition.value).length == 0){
                conditionsIsValid = false;
                msg = app.i18n._('All fields must be filled');
                return;
            }
            if(['header', 'headerregex'].indexOf(condition.field) >= 0 && Ext.util.Format.trim(condition.operator).length == 0){
                conditionsIsValid = false;
                msg = app.i18n._('All fields must be filled');
                return;
            }
            if(condition.field == 'size' && /^[0-9]*$/.test(Ext.util.Format.trim(condition.value)) == false ){
                conditionsIsValid = false;
                msg = app.i18n._('Size value should be numeric');
                return;
            }
            if(condition.field == 'size' && condition.value > 50){
                conditionsIsValid = false;
                msg = app.i18n._('Size value must not be more than') + " 50 MB";
                return;
            }
        });
        this.conditionsIsValid = conditionsIsValid;
        this.validationMessage = msg;
        
        return actionIsValid && conditionsIsValid;
        
    },
    
     /**
     * get validation error message
     * 
     * @return {String}
     */
    getValidationErrorMessage: function() {
        if(!this.conditionsIsValid){
            return this.validationMessage;
        }
        else {
            return _('Please fix the errors noted.');
        }
    },
    
    /**
     * executed after record got updated from proxy
     * 
     * @private
     */
    onRecordLoad: function() {
        // interrupt process flow till dialog is rendered
        if (! this.rendered) {
            this.onRecordLoad.defer(250, this);
            return;
        }
        
        var title = this.app.i18n._('Edit Filter Rule');
        this.window.setTitle(title);
        
        this.getForm().loadRecord(this.record);
        
        this.loadMask.hide();
    },
    
    /**
     * @private
     */
    onRecordUpdate: function() {
        Tine.Expressomail.sieve.RuleEditDialog.superclass.onRecordUpdate.call(this);
        
        this.record.set('conditions', this.getConditions());
        
        var argumentField = this.getForm().findField('action_argument_' + this.actionTypeCombo.getValue()),
            argumentValue = (argumentField !== null) ? argumentField.getValue() : '';
        this.record.set('action_argument', argumentValue);
    },
    
    /**
     * get conditions and do the mapping
     * 
     * @return {Array}
     */
    getConditions: function() {
        var conditions = this.conditionsPanel.getAllFilterData();
        var result = [],
            i = 0, 
            condition,
            test,
            comperator,
            header;
            
        for (i = 0; i < conditions.length; i++) {
            // set defaults
            comperator = conditions[i].operator;
            header = conditions[i].field;
            test = 'header';

            switch (conditions[i].field) {
                case 'from':
                case 'to':
                    test = 'address';
                    break;
                case 'fromheader':
                    header = 'From';
                    break;
                case 'size':
                    test = 'size';
                    comperator = (conditions[i].operator == 'greater') ? 'over' : 'under';
                    break;
                case 'header':
                    header = conditions[i].operator;
                    comperator = 'contains';
                    break;
                case 'headerregex':
                    header = conditions[i].operator;
                    comperator = 'regex';
                    break;
            }
            condition = {
                test: test,
                header: header,
                comperator: comperator,
                key: conditions[i].value
            };
            result.push(condition);
        }
        return result;
    },
    
    /**
     * get conditions filter data (reverse of getConditions)
     * 
     * @return {Array}
     */
    getConditionsFilter: function() {
        var conditions = this.record.get('conditions');
        var result = [],
            i = 0, 
            filter,
            operator,
            field;
            
        for (i = 0; i < conditions.length; i++) {
            field = conditions[i].header;
            switch (field) {
                case 'size':
                    operator = (conditions[i].comperator == 'over') ? 'greater' : 'less';
                    break;
                case 'from':
                case 'to':
                case 'subject':
                    operator = conditions[i].comperator;
                    break;
                default:
                    if (field == 'From') {
                        operator = conditions[i].comperator;
                        field = 'fromheader';
                    } else {
                        operator = field;
                        field = (conditions[i].comperator == 'contains') ? 'header' : 'headerregex';
                    }
            }
            filter = {
                field: field,
                operator: operator,
                value: conditions[i].key
            };
            result.push(filter);
        }
        
        return result;
    },
    
    /**
     * returns dialog
     * 
     * NOTE: when this method gets called, all initalisation is done.
     * 
     * @return {Object}
     * @private
     */
    getFormItems: function() {
        
        this.conditionsPanel = new Tine.Expressomail.sieve.RuleConditionsPanel({
            filters: this.getConditionsFilter()
        });
        
        this.actionTypeCombo = new Ext.form.ComboBox({
            hideLabel       : true,
            name            : 'action_type',
            typeAhead       : false,
            triggerAction   : 'all',
            lazyRender      : true,
            editable        : false,
            mode            : 'local',
            forceSelection  : true,
            value           : 'discard',
            columnWidth     : 0.4,
            store: Tine.Expressomail.sieve.RuleEditDialog.getActionTypes(this.app),
            listeners: {
                scope: this,
                change: this.onChangeType,
                select: this.onChangeType
            }
        });
        
        this.idPrefix = Ext.id();
        
        return [{
            xtype: 'panel',
            layout: 'border',
            autoScroll: true,
            items: [
            {
                title: this.app.i18n._('If all of the following conditions are met:'),
                region: 'north',
                border: false,
                autoScroll: true,
                items: [
                    this.conditionsPanel
                ],
                xtype: 'panel',
                listeners: {
                    scope: this,
                    afterlayout: function(ct, layout) {
                        ct.suspendEvents();
                        if (this.conditionsPanel.getHeight() < 170) {
                            ct.setHeight(this.conditionsPanel.getHeight() + 30);
                        }
                        ct.ownerCt.layout.layout();
                        ct.resumeEvents();
                    }
                }
            }, {
                title: this.app.i18n._('Do this action:'),
                region: 'center',
                border: false,
                frame: true,
                layout: 'column',
                items: [
                    this.actionTypeCombo,
                    // TODO try to add a spacer/margin between the two input fields
                /*{
                    // spacer
                    columnWidth: 0.1,
                    layout: 'fit',
                    title: '',
                    items: []
                }, */{
                    id: this.idPrefix + 'CardLayout',
                    layout: 'card',
                    activeItem: this.idPrefix + 'fileinto',
                    border: false,
                    columnWidth: 0.5,
                    defaults: {
                        border: false
                    },
                    items: [{
                        id: this.idPrefix + 'fileinto',
                        layout: 'form',
                        items: [{
                            name: 'action_argument_fileinto',
                            xtype: 'expressomailfolderselect',
                            width: 200,
                            hideLabel: true,
                            allowBlank: false,
                            account: this.account
                        }]
                    }, {
                        // TODO add email validator?
                        id: this.idPrefix + 'redirect',
                        layout: 'form',
                        items: [{
                            name: 'action_argument_redirect',
                            xtype: 'textfield',
                            vtype: 'email',
                            allowBlank: false,
                            emptyText: 'test@example.org',
                            width: 200,
                            hideLabel: true,
                            validator: function(text){
                                allowedDomais = Tine.Expressomail.registry.get("allowedDomais");
                                if(allowedDomais){
                                    domains = allowedDomais.replace(/\s/g, "").replace(/\./g, "\\.").replace(/,/g, "|");
                                    domainRegexp = new RegExp("(" + domains +")$");
                                    return domainRegexp.test(text)?true:Tine.Tinebase.appMgr.get('Expressomail').i18n._('Redirect to this address not allowed.');;
                                }else{
                                    return true
                                }
                            }
                        }]
                    }, {
                        id: this.idPrefix + 'reject',
                        layout: 'form',
                        items: [{
                            name: 'action_argument_reject',
                            xtype: 'textarea',
                            width: 300,
                            height: 60,
                            allowBlank: false,
                            hideLabel: true
                        }]
                    }, {
                        id: this.idPrefix + 'discard',
                        layout: 'fit',
                        items: []
                    }]
                }]
            }]
        }];
    }
});

/**
 * Expressomail Edit Popup
 * 
 * @param   {Object} config
 * @return  {Ext.ux.Window}
 */
Tine.Expressomail.sieve.RuleEditDialog.openWindow = function (config) {
    var window = Tine.WindowFactory.getWindow({
        width: 700,
        height: 300,
        name: Tine.Expressomail.sieve.RuleEditDialog.prototype.windowNamePrefix + Ext.id(),
        contentPanelConstructor: 'Tine.Expressomail.sieve.RuleEditDialog',
        contentPanelConstructorConfig: config
    });
    return window;
};

/**
 * get action types for action combo and action type renderer
 * 
 * @param {} app
 * @return {Array}
 */
Tine.Expressomail.sieve.RuleEditDialog.getActionTypes = function(app) {
    if(app === undefined){
        app = Tine.Tinebase.appMgr.get('Expressomail');
    }
    return [
        ['fileinto',    app.i18n._('Move mail to folder')],
        ['redirect',    app.i18n._('Redirect mail to address')],
        ['reject',      app.i18n._('Reject mail with this text')],
        ['discard',     app.i18n._('Discard mail')]
        //['keep',        app.i18n._('Keep mail')],
    ];
};
