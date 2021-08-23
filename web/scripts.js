function toggleVisibility(id) {
    var el = document.getElementById(id);
    if (el) {
        if (el.style.display && (el.style.display == 'block' || el.style.display == ''))
            el.style.display = 'none';
        else
            el.style.display = 'block';
    }
}

function setVisibility(id, state) {
    if (id == "")
        return;
    var el = document.getElementById(id);
    el.style.display = state;
}

function getVisibility(id) {
    if (id == "")
        return "";
    var el = document.getElementById(id);
    if (el)
        return el.style.display;
    return 'none';
}

function deleteDeviceStatus(sn, id) {
    var el = document.getElementById(id);
    if (el && el.parentNode) {
        if (confirm('Are you sure you want to delete all status information for "' + sn + '"?')) {

            var request = new XMLHttpRequest();
            request.open("GET", "admin/admin.php?mode=ui&cmd=delstatus&sn=" + sn);
            request.addEventListener('load', function (event) {
                if (request.status >= 200 && request.status < 300) {
                    var r = JSON.parse(request.responseText);
                    if (r) {
                        el.parentNode.removeChild(el);
                    }
                }
            });
            request.send();
        }
    }
}

function touchDeviceStatus(sn, id) {
    var el = document.getElementById(id);
    if (el && el.parentNode) {

        var request = new XMLHttpRequest();
        request.open("GET", "admin/admin.php?mode=ui&cmd=touchstatus&sn=" + sn);
        request.addEventListener('load', function (event) {
            if (request.status >= 200 && request.status < 300) {
                var r = JSON.parse(request.responseText);
                if (r) {
                    el.className = el.className.replace(/(?:^|\s)missing(?!\S)/g, '');
                }
            }
        });
        request.send();
    }
}

function deleteFile(sn, id, fn) {
    var el = document.getElementById(id);
    if (el && el.parentNode) {
        if (confirm('Are you sure you want to delete "' + fn + '"?')) {
            var request = new XMLHttpRequest();
            request.open("GET", "admin/admin.php?mode=ui&cmd=delfile&sn=" + sn + "&file=" + encodeURIComponent(fn));
            request.addEventListener('load', function (event) {
                if (request.status >= 200 && request.status < 300) {
                    var r = JSON.parse(request.responseText);
                    if (r) {
                        updateDeviceStatus(sn, id);
                    }
                }
            });
            request.send();
        }
    }
}

function updateDeviceStatus(sn, id) {
    // console.log("updateDeviceStatus(" + sn + ", " + id + ")");
    var el = document.getElementById(id);
    if (el && el.parentNode) {

        var request = new XMLHttpRequest();
        request.open("GET", "admin/admin.php?mode=ui&cmd=updatestatus&sn=" + sn);
        request.addEventListener('load', function (event) {
            if (request.status >= 200 && request.status < 300) {
                var r = JSON.parse(request.responseText);
                if (r) {
                    // save and restore expandable data views
                    var msgsid = el.getAttribute("data-msgsid");
                    var backupsid = el.getAttribute("data-backupsid");
                    var scriptsid = el.getAttribute("data-scriptsid");
                    // console.log("id(" + id + "): msg=" + msgsid + ", backup=" + backupsid + ", script=" + scriptsid);
                    var msgsstate = getVisibility(msgsid);
                    var backupsstate = getVisibility(backupsid);
                    var scriptsstate = getVisibility(scriptsid);
                    el.outerHTML = r;
                    setVisibility(msgsid, msgsstate);
                    setVisibility(backupsid, backupsstate);
                    setVisibility(scriptsid, scriptsstate);
                    asyncTableMatcher(id);
                }
            }
        });
        request.send();
    }
}

var updateDeviceStateTimer;
function initDeviceStateTimer(interval) {
    updateDeviceStateTimer = setInterval(updateDeviceStatusTable, interval * 1000);
    updateDeviceStatusTable();
}

function asyncTableMatcher(id) {
    field = document.getElementById("devicesmatch");
    tablematcher(field, "devices", "devfiltered", id);
}

function updateDeviceStatusTable() {
    var el = document.getElementById("devicestable");
    if (el) {
        console.log("async table update");
        var nodes = el.childNodes;
        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            if (node.nodeName == "TR") {
                var sn = node.getAttribute("data-sn");
                updateDeviceStatus(sn, node.id);
            }
        }
    }
}

var ignoreFormSubmit = false;
function checkFormSubmittal(form, event) {
    if (ignoreFormSubmit) {
        ignoreFormSubmit = false;
        return false;
    }
    return true;
}
function updateTableMatcher(field, tableid, displayid) {
    tablematcher(field, tableid, displayid, null);
    ignoreFormSubmit = true;
    return false;
}

function getFilterList(values) {
    var filters = [];
    var splitted = values.split(' ');
    for (var _i in splitted) {
        if (splitted[_i].trim() != '')
            filters.push({filter: splitted[_i].trim(), regexp: globStringToRegex(splitted[_i].trim())});
    }
    return filters;
}

function tablematcher(field, tableid, displayid, deviceid) {
    var matchCount = 0;
    var totalCount = 0;
    var values = field.value;
    // normalize mac address
    if (values.length == 12 && values.slice(0, 6) == "009033") {
        values = values.slice(0, 2)+'-'+values.slice(2, 4)+'-'+values.slice(4, 6)+'-'+values.slice(6, 8)+'-'+values.slice(8, 10)+'-'+values.slice(10, 12);
        // console.log('normalize mac to: '+values);
    }

    var filters = getFilterList(values);

    var table = document.getElementById(tableid);
    for (var row in table.rows) {
        var tr = table.rows[row];
        var visible = true;
        if (tr && tr.dataset && tr.dataset.sn) {
            if (deviceid == null || deviceid == tr.id) {
                // console.log("checking " + tr.id);
                // console.log(regValue);
                for (var index in filters) {
                    var value = filters[index].filter;
                    var regValue = filters[index].regexp;
                    var attrmatch = false;
                    // console.log("match: " + value);
                    for (var attr in tr.dataset) {
                        if (attr.substring(0, 5) == "match") {
                            attrvalue = eval('tr.dataset.' + attr);
                            if (regValue.test(attrvalue)) {
                                // console.log("match on " + attr + " (" + attrvalue + ")");
                                attrmatch = true;
                                // console.log("match: " + value + ": attr: " + attr + "=" + attrvalue);
                            }
                        }
                    }
                    if (!attrmatch) {
                        // console.log("no match for " + value);
                    }
                    visible = visible & attrmatch;
                }
                tr.style.display = !visible ? "none" : "";
                if (visible) {
                    matchCount++;
                }
            } else {
                matchCount += (getVisibility(tr.id) == "none" ? 0 : 1);
            }
            totalCount++;
        }
    }
    var display = document.getElementById(displayid);
    if (display) {
        var tstr = document.createTextNode(values);
        var div = document.createElement('div');
        div.appendChild(tstr);
        // alert(tstr.innerHTML);
        display.innerHTML = (totalCount == matchCount) ? "" : ("(" + matchCount + " filtered by <i>" + div.innerHTML + "</i>)");
    }
    return matchCount;
}

function noSubmit() {
    ignoreFormSubmit = true;
}

function globStringToRegex(str) {
    return new RegExp("\\b" + preg_quote(str).replace(/\\\*/g, '.*').replace(/\\\?/g, '.') + "\\b", 'gi');
}
function preg_quote(str, delimiter) {
    // http://kevin.vanzonneveld.net
    // +   original by: booeyOH
    // +   improved by: Ates Goral (http://magnetiq.com)
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   bugfixed by: Onno Marsman
    // +   improved by: Brett Zamir (http://brett-zamir.me)
    // *     example 1: preg_quote("$40");
    // *     returns 1: '\$40'
    // *     example 2: preg_quote("*RRRING* Hello?");
    // *     returns 2: '\*RRRING\* Hello\?'
    // *     example 3: preg_quote("\\.+*?[^]$(){}=!<>|:");
    // *     returns 3: '\\\.\+\*\?\[\^\]\$\(\)\{\}\=\!\<\>\|\:'
    return (str + '').replace(new RegExp('[.\\\\+*?\\[\\^\\]$(){}=!<>|:\\' + (delimiter || '') + '-]', 'g'), '\\$&');
}