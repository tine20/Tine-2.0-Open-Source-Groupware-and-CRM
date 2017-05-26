/*
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2009 Metaways Infosystems GmbH (http://www.metaways.de)
 */

Ext.ns('Ext.ux.form');

/**
 * @namespace   Ext.ux.form
 * @class       Ext.ux.form.ClearableTextField
 * @extends     Ext.form.TriggerField
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 * Textfield with clearing trigger
 */
Ext.ux.form.ClearableTextField = Ext.extend(Ext.form.TriggerField, {
    /**
     * @cfg {Boolean} disableTrigger
     */
    disableTrigger: false,

    enableKeyEvents: true,
    triggerClass: 'x-form-clear-trigger',
    
    checkTrigger: function() {
        this.setHideTrigger(! (this.getValue() && !this.disableTrigger));
    },
    
    initComponent: function() {
        this.supr().initComponent.call(this);
        this.on('keyup', this.checkTrigger, this);
    },
    
    afterRender: function() {
        this.supr().afterRender.call(this);
        this.checkTrigger();
    },
    
    onTriggerClick: function() {
        var value = this.getValue();
        this.setValue('');
        
        if (value) {
            this.fireEvent('change', this, '', value);
        }
        
        this.checkTrigger();
        this.el.focus();
    },
    
    onDestroy: function() {
        this.un('keyup', this.checkTrigger);
    },
    
    setValue: function(value) {
        var ret = this.supr().setValue.call(this, value);
        this.checkTrigger();
        
        return ret;
    }
});
Ext.reg('extuxclearabletextfield', Ext.ux.form.ClearableTextField);
