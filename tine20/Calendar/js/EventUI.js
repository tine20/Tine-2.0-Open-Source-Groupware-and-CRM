/* 
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2007-2015 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 * @note: lot to do here, i just started to move stuff from views here
 */
 
Ext.ns('Tine.Calendar');

Tine.Calendar.EventUI = function(event) {
    this.event = event;
    this.domIds = [];
    this.app = Tine.Tinebase.appMgr.get('Calendar');
    this.init();
};

Tine.Calendar.EventUI.prototype = {
    addClass: function(cls) {
        Ext.each(this.getEls(), function(el){
            el.addClass(cls);
        });
    },
    
    blur: function() {
        Ext.each(this.getEls(), function(el){
            el.blur();
        });
    },
    
    clearDirty: function() {
        Ext.each(this.getEls(), function(el) {
            el.setOpacity(1, 1);
        });
    },
    
    focus: function() {
        Ext.each(this.getEls(), function(el){
            el.focus();
        });
    },
    
    /**
     * returns events dom
     * @return {Array} of Ext.Element
     */
    getEls: function() {
        var domEls = [];
        for (var i=0; i < this.domIds.length; i++) {
            var el = Ext.get(this.domIds[i]);
            if (el) {
                domEls.push(el);
            }
        }
        return domEls;
    },
    
    init: function() {
        // shortcut
        //this.colMgr = Tine.Calendar.colorMgr;
    },
    
    markDirty: function() {
        Ext.each(this.getEls(), function(el) {
            el.setOpacity(0.5, 1);
        });
    },
    
    markOutOfFilter: function() {
        Ext.each(this.getEls(), function(el) {
            el.setOpacity(0.5, 0);
            el.setStyle({'background-color': '#aaa', 'border-color': '#888'});
            Ext.DomHelper.applyStyles(el.dom.firstChild, {'background-color': '#888'});
            if (el.dom.firstChild.hasOwnProperty('firstChild') && el.dom.firstChild.firstChild) {
                Ext.DomHelper.applyStyles(el.dom.firstChild.firstChild, {'background-color': '#888'});
            }
        });
    },
    
    onSelectedChange: function(state){
        if(state){
            //this.focus();
            this.addClass('cal-event-active');
            this.setStyle({'z-index': 1000});
            
        }else{
            //this.blur();
            this.removeClass('cal-event-active');
            this.setStyle({'z-index': 100});
        }
    },
    
    /**
     * removes a event from the dom
     */
    remove: function() {
        var eventEls = this.getEls();
        for (var i=0; i<eventEls.length; i++) {
            if (eventEls[i] && typeof eventEls[i].remove == 'function') {
                eventEls[i].remove();
            }
        }
        if (this.resizeable) {
            this.resizeable.destroy();
            this.resizeable = null;
        }
        this.domIds = [];
    },
    
    removeClass: function(cls) {
        Ext.each(this.getEls(), function(el){
            el.removeClass(cls);
        });
    },
    
    render: function() {
        // do nothing
    },
    
    setOpacity: function(v) {
        Ext.each(this.getEls(), function(el){
            el.setStyle(v);
        });
    },
    
    setStyle: function(style) {
        Ext.each(this.getEls(), function(el){
            el.setStyle(style);
        });
    }
    
};



Tine.Calendar.DaysViewEventUI = Ext.extend(Tine.Calendar.EventUI, {
    
    clearDirty: function() {
        Tine.Calendar.DaysViewEventUI.superclass.clearDirty.call(this);
        
        Ext.each(this.getEls(), function(el) {
            el.setStyle({'border-style': 'solid'});
        });
    },
    /**
     * get diff of resizeable
     * 
     * @param {Ext.Resizeable} rz
     */
    getRzInfo: function(rz, width, height) {
        var rzInfo = {};
        
        var event = rz.event;
        var view = event.view;
        
        // NOTE proxy might be gone after resize
        var box = rz.proxy.getBox();
        var width = width ? width: box.width;
        var height =  height? height : box.height;
        
        var originalDuration = (event.get('dtend').getTime() - event.get('dtstart').getTime()) / Date.msMINUTE;
        
        if(event.get('is_all_day_event')) {
            var dayWidth = Ext.fly(view.wholeDayArea).getWidth() / view.numOfDays;
            rzInfo.diff = Math.round((width - rz.originalWidth) / dayWidth);
            
        } else {
            rzInfo.diff = Math.round((height - rz.originalHeight) * (view.timeGranularity / view.granularityUnitHeights));
            // neglegt diffs due to borders etc.
            rzInfo.diff = Math.round(rzInfo.diff/view.timeIncrement) * view.timeIncrement;
        }
        rzInfo.duration = originalDuration + rzInfo.diff;
        
        if(event.get('is_all_day_event')) {
            rzInfo.dtend = event.get('dtend').add(Date.DAY, rzInfo.diff);
        } else {
            rzInfo.dtend = event.get('dtstart').add(Date.MINUTE, rzInfo.duration);
        }
        
        return rzInfo;
    },
    
    markDirty: function() {
        Tine.Calendar.DaysViewEventUI.superclass.markDirty.call(this);
        
        Ext.each(this.getEls(), function(el) {
            el.setStyle({'border-style': 'dashed'});
        });
    },
    
    onSelectedChange: function(state){
        Tine.Calendar.DaysViewEventUI.superclass.onSelectedChange.call(this, state);
        if(state){
            this.addClass('cal-daysviewpanel-event-active');
            
        }else{
            this.removeClass('cal-daysviewpanel-event-active');
        }
    },
    
    render: function(view) {
        this.event.view = view;

        this.attendeeRecord = view.ownerCt && view.ownerCt.attendee ?
            Tine.Calendar.Model.Attender.getAttendeeStore.getAttenderRecord(this.event.getAttendeeStore(), view.ownerCt.attendee) :
            this.event.getMyAttenderRecord();

        this.colorSet = Tine.Calendar.colorMgr.getColor(this.event, this.attendeeRecord);
        this.event.colorSet = this.colorSet;
        
        this.dtStart = this.event.get('dtstart');
        this.startColNum = view.getColumnNumber(this.dtStart);
        
        this.dtEnd = this.event.get('dtend');
        
        if (this.event.get('editGrant')) {
            this.extraCls = 'cal-daysviewpanel-event-editgrant';
        }
        
        this.extraCls += ' cal-status-' + this.event.get('status');
        
        // 00:00 in users timezone is a spechial case where the user expects
        // something like 24:00 and not 00:00
        if (this.dtEnd.format('H:i') == '00:00') {
            this.dtEnd = this.dtEnd.add(Date.MINUTE, -1);
        }
        this.endColNum = view.getColumnNumber(this.dtEnd);
        
        // skip dates not in our diplay range
        if (this.endColNum < 0 || this.startColNum > view.numOfDays-1) {
            return;
        }
        
        // compute status icons
        this.statusIcons = [];
        if (this.event.get('class') === 'PRIVATE') {
            this.statusIcons.push({
                status: 'private',
                text: this.app.i18n._('private classification')
            });
        }
        
        if (this.event.get('rrule')) {
            this.statusIcons.push({
                status: 'recur',
                text: this.app.i18n._('recurring event')
            });
        } else if (this.event.isRecurException()) {
            this.statusIcons.push({
                status: 'recurex',
                text: this.app.i18n._('recurring event exception')
            });
        }

        
        
        if (! Ext.isEmpty(this.event.get('alarms'))) {
            this.statusIcons.push({
                status: 'alarm',
                text: this.app.i18n._('has alarm')
            });
        }
        
        if (! Ext.isEmpty(this.event.get('attachments'))) {
            this.statusIcons.push({
                status: 'attachment',
                text: this.app.i18n._('has attachments')
            });
        }
        
        var attenderStatusRecord = this.attendeeRecord ? Tine.Tinebase.widgets.keyfield.StoreMgr.get('Calendar', 'attendeeStatus').getById(this.attendeeRecord.get('status')) : null;
            
        if (attenderStatusRecord && attenderStatusRecord.get('system')) {
            this.statusIcons.push({
                status: this.attendeeRecord.get('status'),
                text: attenderStatusRecord.get('i18nValue')
            });
        }
        
        var registry = this.event.get('is_all_day_event') ? view.parallelWholeDayEventsRegistry : view.parallelScrollerEventsRegistry;
        
        var position = registry.getPosition(this.event);
        var maxParallels = registry.getMaxParalles(this.dtStart, this.dtEnd);
        
        if (this.event.get('is_all_day_event')) {
            this.renderAllDayEvent(view, maxParallels, position);
        } else {
            this.renderScrollerEvent(view, maxParallels, position);
        }
        
        var ids = Tine.Tinebase.data.Clipboard.getIds('Calendar', 'Event');
        
        if (ids.indexOf(this.event.get('id')) > -1) {
            this.markDirty(true);
        } else if (this.event.dirty) {
            // the event was selected before
            this.onSelectedChange(true);
        }
        
        if (this.event.outOfFilter) {
            this.markOutOfFilter();
        }
        
        this.rendered = true;
    },
    
    renderAllDayEvent: function(view, parallels, pos) {
        // lcocal COPY!
        var extraCls = this.extraCls;
        
        var offsetWidth = Ext.fly(view.wholeDayArea).getWidth();
        
        //var width = Math.round(offsetWidth * (this.dtEnd.getTime() - this.dtStart.getTime()) / (view.numOfDays * Date.msDAY)) -5;
        //var left = Math.round(offsetWidth * (this.dtStart.getTime() - view.startDate.getTime()) / (view.numOfDays * Date.msDAY));

        var width = Math.floor(1000 * (this.dtEnd.getTime() - this.dtStart.getTime()) / (view.numOfDays * Date.msDAY) -5) /10;
        var left = 100 * (this.dtStart.getTime() - view.startDate.getTime()) / (view.numOfDays * Date.msDAY);


        if (left < 0) {
            width = width + left;
            left = 0;
            extraCls = extraCls + ' cal-daysviewpanel-event-cropleft';
        }
        
        if (left + width > offsetWidth) {
            width = offsetWidth - left;
            extraCls = extraCls + ' cal-daysviewpanel-event-cropright';
        }
        
        var domId = Ext.id() + '-event:' + this.event.get('id');
        this.domIds.push(domId);
        
        var eventEl = view.templates.wholeDayEvent.insertFirst(view.wholeDayArea, {
            id: domId,
            tagsHtml: Tine.Tinebase.common.tagsRenderer(this.event.get('tags')),
            summary: this.event.get('summary'),
            startTime: this.dtStart.format('H:i'),
            extraCls: extraCls,
            color: this.colorSet.color,
            bgColor: this.colorSet.light,
            textColor: this.colorSet.text,
            zIndex: 100,
            width: width  +'%',
            height: '15px',
            left: left + '%',
            top: pos * 18 + 'px',//'1px'
            statusIcons: this.statusIcons
        }, true);
        
        if (this.event.dirty) {
            eventEl.setStyle({'border-style': 'dashed'});
            eventEl.setOpacity(0.5);
        }
        
        if (! (this.endColNum > view.numOfDays) && this.event.get('editGrant')) {
            this.resizeable = new Ext.Resizable(eventEl, {
                handles: 'e',
                disableTrackOver: true,
                dynamic: true,
                //dynamic: !!this.event.isRangeAdd,
                widthIncrement: Math.round(offsetWidth / view.numOfDays),
                minWidth: Math.round(offsetWidth / view.numOfDays),
                listeners: {
                    scope: view,
                    resize: view.onEventResize,
                    beforeresize: view.onBeforeEventResize
                }
            });
        }
        //console.log([eventEl.dom, parallels, pos])
    },
    
    renderScrollerEvent: function(view, parallels, pos) {
        var scrollerHeight = view.granularityUnitHeights * ((24 * 60)/view.timeGranularity);
        
        for (var currColNum=this.startColNum; currColNum<=this.endColNum; currColNum++) {
            
            // lcocal COPY!
            var extraCls = this.extraCls;
            
            if (currColNum < 0 || currColNum >= view.numOfDays) {
                continue;
            }
            
            var top = view.getTimeOffset(this.dtStart);
            var height = this.startColNum == this.endColNum ? view.getTimeHeight(this.dtStart, this.dtEnd) : view.getTimeOffset(this.dtEnd);
            
            if (currColNum != this.startColNum) {
                top = 0;
                extraCls = extraCls + ' cal-daysviewpanel-event-croptop';
            }
            
            if (this.endColNum != currColNum) {
                height = view.getTimeHeight(this.dtStart, this.dtStart.add(Date.DAY, 1));
                extraCls = extraCls + ' cal-daysviewpanel-event-cropbottom';
            }
            
            var domId = Ext.id() + '-event:' + this.event.get('id');
            this.domIds.push(domId);
            
            // minimal height
            if (height <= 12) {
                height = 12;
            }
            
            // minimal top
            if (top > scrollerHeight -12) {
                top = scrollerHeight -12;
            }
            
            var eventEl = view.templates.event.append(view.getDateColumnEl(currColNum), {
                id: domId,
                summary: height >= 24 ? this.event.get('summary') : '',
                tagsHtml: height >= 24 ? Tine.Tinebase.common.tagsRenderer(this.event.get('tags')) : '',
                startTime: (height >= 24 && top <= scrollerHeight-24) ? this.dtStart.format('H:i') : this.dtStart.format('H:i') + ' ' +  this.event.get('summary'),
                extraCls: extraCls,
                color: this.colorSet.color,
                bgColor: this.colorSet.light,
                textColor: this.colorSet.text,
                zIndex: 100,
                height: height + 'px',
                left: Math.round(pos * 90 * 1/parallels) + '%',
                width: Math.round(90 * 1/parallels) + '%',
                // max shift to 20+gap
                //left: 80 - 80/Math.sqrt(pos+1) + 10*Math.sqrt(pos) + '%',
                //width: 80/Math.sqrt(pos+1) + '%',
                top: top + 'px',
                statusIcons: this.statusIcons
            }, true);
            
            if (this.event.dirty) {
                eventEl.setStyle({'border-style': 'dashed'});
                eventEl.setOpacity(0.5);
            }
            
            if (currColNum == this.endColNum && this.event.get('editGrant')) {
                var maxHeight = 10000;
                
                if (view.cropDayTime) { 
                    maxHeight = view.dayEndPx - top;
                }
                
                this.resizeable = new Ext.Resizable(eventEl, {
                    handles: 's',
                    disableTrackOver: true,
                    dynamic: true,
                    //dynamic: !!this.event.isRangeAdd,
                    heightIncrement: view.granularityUnitHeights / view.timeGranularity * view.timeIncrement,
                    maxHeight: maxHeight,
                    listeners: {
                        scope: view,
                        resize: view.onEventResize,
                        beforeresize: view.onBeforeEventResize
                    }
                });
            }
        }
    }
});

Tine.Calendar.MonthViewEventUI = Ext.extend(Tine.Calendar.EventUI, {
    onSelectedChange: function(state){
        Tine.Calendar.MonthViewEventUI.superclass.onSelectedChange.call(this, state);
        if (state){
            this.addClass('cal-monthview-active');
            this.setStyle({
                'background-color': this.color,
                'color':            (this.colorSet) ? this.colorSet.text : '#000000'
            });
            
        } else {
            this.removeClass('cal-monthview-active');
            this.setStyle({
                'background-color': this.is_all_day_event ? this.bgColor : '',
                'color':            this.is_all_day_event ? '#000000' : this.color
            });
        }
    }
});

Tine.Calendar.YearViewEventUI = Ext.extend(Tine.Calendar.EventUI, {
    onSelectedChange: function(state){
        Tine.Calendar.YearViewEventUI.superclass.onSelectedChange.call(this, state);
        if (state){
            this.addClass('cal-yearview-active');
            this.setStyle({
                'background-color': this.color,
                'color':            (this.colorSet) ? this.colorSet.text : '#000000'
            });
            
        } else {
            this.removeClass('cal-yearview-active');
            this.setStyle({
                'background-color': this.is_all_day_event ? this.bgColor : '',
                'color':            this.is_all_day_event ? '#000000' : this.color
            });
        }
    }
});
