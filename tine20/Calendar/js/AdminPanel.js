/*
 * Tine 2.0
 * 
 * @package     Calendar
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2009 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

Ext.namespace('Tine.Calendar');

/**
 * admin settings panel
 * 
 * @namespace   Tine.Calendar
 * @class       Tine.Calendar.AdminPanel
 * @extends     Ext.TabPanel
 * 
 * <p>Calendar Admin Panel</p>
 * <p><pre>
 * </pre></p>
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 * @param       {Object} config
 * @constructor
 * Create a new Tine.Calendar.AdminPanel
 */
Tine.Calendar.AdminPanel = Ext.extend(Ext.TabPanel, {

    border: false,
    activeTab: 0,

    /**
     * @private
     */
    initComponent: function() {
        
        this.app = Tine.Tinebase.appMgr.get('Calendar');
        
        this.items = [
            new Tine.Calendar.ResourcesGridPanel({
                title: this.app.i18n._('Manage Resources'),
                disabled: !Tine.Tinebase.common.hasRight('manage_resources', 'Calendar')
            }),
            new Tine.Admin.config.GridPanel({
                configApp: this.app
            })

        ];
        
        Tine.Calendar.AdminPanel.superclass.initComponent.call(this);
    }
});
    
/**
 * Calendar Admin Panel Popup
 * 
 * @param   {Object} config
 * @return  {Ext.ux.Window}
 */
Tine.Calendar.AdminPanel.openWindow = function (config) {
    var window = Tine.WindowFactory.getWindow({
        width: 600,
        height: 470,
        name: 'cal-mange-resources',
        contentPanelConstructor: 'Tine.Calendar.AdminPanel',
        contentPanelConstructorConfig: config
    });
};
