/*
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2012-2013 Metaways Infosystems GmbH (http://www.metaways.de)
 */
Ext.ns('Tine.Tinebase');

/**
 * central config
 */
Tine.Tinebase.configManager = function(){
    return {
        get: function(name, appName) {
            var registry = appName && Tine[appName] ? Tine[appName].registry : Tine.Tinebase.registry,
                config = registry ? registry.get('config') : false,
                pathParts = String(name).split('.'),
                path = pathParts.join('.value.') + (pathParts.length == 1 ? '.value' : '');

            return lodash.get(config, path);
        }
    }
}();